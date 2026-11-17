'use strict';

const transports = require( './transports.js' );

// Seconds after the last key press until the editor stops counting as typing.
const TYPING_IDLE = 5;

// While typing, how often the server is reminded. It forgets typing after ten seconds.
const TYPING_REFRESH = 4;

/**
 * Keeps one open editor registered on the server and reports who else is
 * editing. It polls, quickly while others are present and slowly otherwise,
 * and when the server offers a push connection it refreshes on demand and only
 * sends a slow keepalive.
 */
class PresenceTracker {
	/**
	 * @param {Object} options
	 * @param {string} options.pageName
	 * @param {number} options.interval Seconds between updates until the server says otherwise.
	 * @param {Function} options.getSection Returns the section, or '' for the whole page.
	 * @param {Function} options.onUpdate Called with the API response data.
	 * @param {Function} [options.getTypingRoot] Returns the jQuery element watched for key presses.
	 */
	constructor( options ) {
		this.pageName = options.pageName;
		this.idleInterval = options.interval;
		this.activeInterval = options.interval;
		this.getSection = options.getSection;
		this.onUpdate = options.onUpdate;
		this.clientId = mw.user.generateRandomSessionId();
		this.api = new mw.Api();
		this.timer = null;
		this.running = false;
		this.othersPresent = false;
		this.transport = null;
		this.pushKey = '';
		this.pushConnected = false;
		this.refreshing = false;
		this.refreshQueued = false;
		this.getTypingRoot = options.getTypingRoot || null;
		this.$typingRoot = null;
		this.typingEnabled = false;
		this.typing = false;
		this.typingTimer = null;
		this.typingSentAt = 0;
		this.onKey = this.handleKey.bind( this );
		this.onPageHide = this.sendLeaveBeacon.bind( this );
		this.onPageShow = this.handlePageShow.bind( this );
		this.onVisibility = this.handleVisibility.bind( this );
	}

	start() {
		if ( this.running ) {
			return;
		}
		this.running = true;
		window.addEventListener( 'pagehide', this.onPageHide );
		window.addEventListener( 'pageshow', this.onPageShow );
		document.addEventListener( 'visibilitychange', this.onVisibility );
		this.watchTyping();
		this.beat();
	}

	stop() {
		if ( !this.running ) {
			return;
		}
		this.running = false;
		window.clearTimeout( this.timer );
		window.clearTimeout( this.typingTimer );
		this.unwatchTyping();
		window.removeEventListener( 'pagehide', this.onPageHide );
		window.removeEventListener( 'pageshow', this.onPageShow );
		document.removeEventListener( 'visibilitychange', this.onVisibility );
		this.stopTransport();
		this.api.postWithToken( 'csrf', this.getParams( 'leave', '' ) ).then(
			() => {},
			() => {}
		);
	}

	beat() {
		if ( !this.running ) {
			return;
		}
		window.clearTimeout( this.timer );
		this.send( 'heartbeat' ).always( () => {
			this.schedule();
		} );
	}

	refresh() {
		if ( !this.running ) {
			return;
		}
		if ( this.refreshing ) {
			this.refreshQueued = true;
			return;
		}
		this.refreshing = true;
		this.send( 'refresh' ).always( () => {
			this.refreshing = false;
			if ( this.refreshQueued ) {
				this.refreshQueued = false;
				this.refresh();
			}
		} );
	}

	send( task ) {
		return this.api.postWithToken( 'csrf', this.getParams( task, this.getSection() ) ).then(
			( data ) => {
				if ( this.running && data && data.editingpresence ) {
					this.handleResponse( data.editingpresence );
				}
			},
			( code ) => {
				mw.log.warn( 'EditingPresence ' + task + ' failed: ' + code );
			}
		);
	}

	handleResponse( data ) {
		this.idleInterval = data.interval || this.idleInterval;
		this.activeInterval = data.activeinterval || this.idleInterval;
		this.othersPresent = ( data.editors || [] ).length > 0;
		this.typingEnabled = !!data.typingenabled;
		this.updateTransport( data.push || null );
		this.onUpdate( data );
	}

	getCurrentInterval() {
		if ( this.pushConnected || !this.othersPresent ) {
			return this.idleInterval;
		}
		return this.activeInterval;
	}

	schedule() {
		if ( !this.running ) {
			return;
		}
		window.clearTimeout( this.timer );
		this.timer = window.setTimeout( () => {
			this.beat();
		}, this.getCurrentInterval() * 1000 );
	}

	updateTransport( push ) {
		const key = push ? JSON.stringify( push ) : '';
		if ( key === this.pushKey ) {
			return;
		}

		this.stopTransport();
		this.pushKey = key;
		if ( !push ) {
			return;
		}

		transports.createTransport( push ).then( ( transport ) => {
			if ( !transport || !this.running || this.pushKey !== key ) {
				return;
			}
			this.transport = transport;
			transport.start(
				() => {
					this.refresh();
				},
				( connected ) => {
					this.setPushConnected( connected );
				}
			);
		} );
	}

	stopTransport() {
		if ( this.transport ) {
			this.transport.stop();
			this.transport = null;
		}
		this.setPushConnected( false );
	}

	setPushConnected( connected ) {
		if ( connected === this.pushConnected ) {
			return;
		}
		this.pushConnected = connected;
		mw.log( 'EditingPresence push ' + ( connected ? 'connected' : 'disconnected' ) );
		this.schedule();
	}

	watchTyping() {
		this.$typingRoot = this.getTypingRoot ? this.getTypingRoot() : null;
		if ( this.$typingRoot ) {
			this.$typingRoot.on( 'keydown.editingPresence input.editingPresence', this.onKey );
		}
	}

	unwatchTyping() {
		if ( this.$typingRoot ) {
			this.$typingRoot.off( '.editingPresence' );
			this.$typingRoot = null;
		}
	}

	handleKey( event ) {
		if ( !this.typingEnabled || event.ctrlKey || event.metaKey ) {
			return;
		}
		// Input events carry no key. Key presses only count when they change the text.
		const key = event.key;
		if ( key && key.length > 1 && key !== 'Backspace' && key !== 'Delete' && key !== 'Enter' ) {
			return;
		}

		window.clearTimeout( this.typingTimer );
		this.typingTimer = window.setTimeout( () => {
			this.setTyping( false );
		}, TYPING_IDLE * 1000 );
		this.setTyping( true );

		if ( this.typing && Date.now() - this.typingSentAt >= TYPING_REFRESH * 1000 ) {
			this.typingSentAt = Date.now();
			this.beat();
		}
	}

	setTyping( typing ) {
		if ( typing === this.typing ) {
			return;
		}
		this.typing = typing;
		this.typingSentAt = Date.now();
		this.beat();
	}

	handleVisibility() {
		if ( document.visibilityState !== 'visible' ) {
			return;
		}
		// Timers and sockets are frozen while a phone sleeps and often come back dead.
		this.stopTransport();
		this.pushKey = '';
		this.beat();
	}

	handlePageShow( event ) {
		if ( event.persisted ) {
			this.beat();
		}
	}

	sendLeaveBeacon() {
		if ( typeof navigator.sendBeacon !== 'function' ) {
			return;
		}
		const params = this.getParams( 'leave', '' );
		const body = new FormData();
		Object.keys( params ).forEach( ( name ) => {
			body.append( name, String( params[ name ] ) );
		} );
		body.append( 'token', mw.user.tokens.get( 'csrfToken' ) );
		navigator.sendBeacon( mw.util.wikiScript( 'api' ), body );
	}

	getParams( task, section ) {
		const params = {
			action: 'editingpresence',
			task: task,
			title: this.pageName,
			section: section,
			clientid: this.clientId,
			format: 'json',
			formatversion: 2
		};
		// Boolean API parameters are true whenever they are present.
		if ( task === 'heartbeat' && this.typing ) {
			params.typing = 1;
		}
		return params;
	}
}

module.exports = PresenceTracker;
