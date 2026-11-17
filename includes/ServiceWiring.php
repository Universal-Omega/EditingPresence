<?php

declare( strict_types=1 );

use MediaWiki\Extension\EditingPresence\PresenceManager;
use MediaWiki\Extension\EditingPresence\PresenceSettings;
use MediaWiki\Extension\EditingPresence\Push\NullPushBackend;
use MediaWiki\Extension\EditingPresence\Push\PushBackend;
use MediaWiki\Extension\EditingPresence\Push\PushSettings;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'EditingPresence.Settings' => static function ( MediaWikiServices $services ): PresenceSettings {
		return PresenceSettings::fromConfig( $services->getMainConfig() );
	},

	'EditingPresence.PushSettings' => static function ( MediaWikiServices $services ): PushSettings {
		return PushSettings::fromConfig( $services->getMainConfig() );
	},

	'EditingPresence.PushBackend' => static function ( MediaWikiServices $services ): PushBackend {
		/** @var PushSettings $settings */
		$settings = $services->getService( 'EditingPresence.PushSettings' );
		if ( $settings->backend === '' ) {
			return new NullPushBackend();
		}

		$specs = $services->getExtensionRegistry()->getAttribute( 'EditingPresencePushBackends' );
		if ( !isset( $specs[$settings->backend] ) ) {
			LoggerFactory::getInstance( 'EditingPresence' )->warning(
				'Unknown push backend {backend}, falling back to polling',
				[ 'backend' => $settings->backend ]
			);
			return new NullPushBackend();
		}

		return $services->getObjectFactory()->createObject(
			$specs[$settings->backend],
			[ 'assertClass' => PushBackend::class ]
		);
	},

	'EditingPresence.PresenceManager' => static function ( MediaWikiServices $services ): PresenceManager {
		/** @var PresenceSettings $settings */
		$settings = $services->getService( 'EditingPresence.Settings' );
		/** @var PushBackend $push */
		$push = $services->getService( 'EditingPresence.PushBackend' );

		$store = $settings->cacheId === ''
			? $services->getMainObjectStash()
			: $services->getObjectCacheFactory()->getInstance( $settings->cacheId );

		return new PresenceManager( $settings, $store, $push );
	},
];
