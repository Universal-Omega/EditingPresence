'use strict';

// The mobile editor is an overlay opened and closed by MobileFrontend. This
// stub is cheap to load on every page and only loads the rest once an editor
// for wikitext actually opens.
let open = false;
let tracker = null;
let banner = null;

// The overlay keeps the section it edits in the address, as #/editor/all or #/editor/2.
function getSection() {
	const match = /^#\/editor\/(\d+|T-\d+)$/.exec( window.location.hash );
	return match ? match[ 1 ] : '';
}

function placeBanner( $banner ) {
	const $container = $( '.editor-container' ).first();
	if ( $container.length ) {
		$container.before( $banner );
	} else {
		$( '.overlay-content' ).first().prepend( $banner );
	}
}

function stop() {
	open = false;
	if ( tracker ) {
		tracker.stop();
		tracker = null;
	}
	if ( banner ) {
		banner.$element.remove();
		banner = null;
	}
}

function start( editor ) {
	if ( editor !== 'wikitext' || open ) {
		return;
	}
	open = true;

	mw.loader.using( 'ext.editingPresence' ).then( ( require ) => {
		// The editor may have been closed while the module was loading.
		if ( !open || tracker ) {
			return;
		}

		const presence = require( 'ext.editingPresence' );
		banner = new presence.PresenceBanner();
		placeBanner( banner.$element );

		tracker = new presence.PresenceTracker( {
			pageName: mw.config.get( 'wgRelevantPageName' ),
			interval: presence.heartbeatInterval,
			getSection: getSection,
			getTypingRoot: () => $( '.wikitext-editor' ),
			onUpdate: ( response ) => {
				banner.render( response, getSection() !== '' );
			}
		} );
		tracker.start();
	} );
}

mw.hook( 'mobileFrontend.editorOpened' ).add( start );
mw.hook( 'mobileFrontend.editorClosed' ).add( stop );
