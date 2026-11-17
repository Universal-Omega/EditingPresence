'use strict';

const presence = require( 'ext.editingPresence' );

$( () => {
	const $form = $( '#editform' );
	if ( !$form.length ) {
		return;
	}

	const section = String( mw.config.get( 'wgEditingPresenceSection' ) || '' );
	const banner = new presence.PresenceBanner();
	$form.before( banner.$element );

	const tracker = new presence.PresenceTracker( {
		pageName: mw.config.get( 'wgRelevantPageName' ),
		interval: presence.heartbeatInterval,
		getSection: () => section,
		onUpdate: ( response ) => {
			banner.render( response, section !== '' );
		}
	} );
	tracker.start();
} );
