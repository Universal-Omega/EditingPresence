'use strict';

const MAX_RETRY_DELAY = 30000;

function resolveUrl( url, websocket ) {
	const resolved = new URL( url, window.location.href );
	if ( websocket ) {
		resolved.protocol = resolved.protocol === 'https:' ? 'wss:' : 'ws:';
	}
	return resolved.href;
}

/**
 * Listens to an Nchan channel over a WebSocket or server-sent events.
 * Any message on the channel only means that the editors may have changed.
 */
class NchanTransport {
	/**
	 * @param {Object} push
	 * @param {string} push.url Subscriber address including the channel id.
	 * @param {string} push.protocol Either websocket or sse.
	 */
	constructor( push ) {
		this.url = push.url;
		this.protocol = push.protocol;
		this.connection = null;
		this.timer = null;
		this.retries = 0;
		this.running = false;
		this.onSignal = null;
		this.onState = null;
	}

	start( onSignal, onState ) {
		this.onSignal = onSignal;
		this.onState = onState;
		this.running = true;
		this.connect();
	}

	stop() {
		this.running = false;
		window.clearTimeout( this.timer );
		this.close();
	}

	connect() {
		if ( !this.running ) {
			return;
		}

		if ( this.protocol === 'sse' && typeof window.EventSource === 'function' ) {
			this.connectEventSource();
		} else if ( this.protocol !== 'sse' && typeof window.WebSocket === 'function' ) {
			this.connectWebSocket();
		} else {
			this.onState( false );
		}
	}

	connectWebSocket() {
		const socket = new WebSocket( resolveUrl( this.url, true ) );
		this.connection = socket;

		socket.onopen = () => {
			this.handleOpen();
		};
		socket.onmessage = () => {
			this.onSignal();
		};
		socket.onclose = () => {
			if ( this.connection === socket ) {
				this.onState( false );
				this.reconnect();
			}
		};
	}

	connectEventSource() {
		const source = new EventSource( resolveUrl( this.url, false ) );
		this.connection = source;

		source.onopen = () => {
			this.handleOpen();
		};
		source.onmessage = () => {
			this.onSignal();
		};
		source.onerror = () => {
			if ( this.connection !== source ) {
				return;
			}
			this.onState( false );
			// EventSource retries on its own unless the browser gave up.
			if ( source.readyState === EventSource.CLOSED ) {
				this.reconnect();
			}
		};
	}

	handleOpen() {
		this.retries = 0;
		this.onState( true );
		// Anything published before the connection opened was missed.
		this.onSignal();
	}

	reconnect() {
		if ( !this.running ) {
			return;
		}

		this.close();
		const delay = Math.min( MAX_RETRY_DELAY, 1000 * Math.pow( 2, this.retries ) );
		this.retries++;
		this.timer = window.setTimeout( () => {
			this.connect();
		}, delay );
	}

	close() {
		const connection = this.connection;
		this.connection = null;
		if ( connection ) {
			connection.onopen = null;
			connection.onmessage = null;
			connection.onclose = null;
			connection.onerror = null;
			connection.close();
		}
	}
}

module.exports = NchanTransport;
