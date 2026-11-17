'use strict';

const presence = require( 'ext.editingPresence' );

let tracker = null;
let banner = null;

// Visual mode only edits a single section when visual section editing is on.
function getCurrentSection( target ) {
	const surface = target.getSurface();
	const mode = surface ? surface.getMode() : target.getDefaultMode();
	const section = ( mode === 'source' || target.enableVisualSectionEditing ) ? target.section : null;
	return section === null || section === undefined ? '' : String( section );
}

function start() {
	const target = ve.init.target;
	if ( !target || tracker ) {
		return;
	}

	banner = new presence.PresenceBanner();
	if ( target.$editableContent && target.$editableContent.length ) {
		target.$editableContent.before( banner.$element );
	} else {
		target.$element.prepend( banner.$element );
	}

	tracker = new presence.PresenceTracker( {
		pageName: target.getPageName(),
		interval: presence.heartbeatInterval,
		getSection: () => getCurrentSection( target ),
		onUpdate: ( response ) => {
			banner.render( response, getCurrentSection( target ) !== '' );
		}
	} );
	tracker.start();
}

function stop() {
	if ( tracker ) {
		tracker.stop();
		tracker = null;
	}
	if ( banner ) {
		banner.$element.remove();
		banner = null;
	}
}

mw.hook( 've.activationComplete' ).add( start );
mw.hook( 've.deactivationComplete' ).add( stop );
