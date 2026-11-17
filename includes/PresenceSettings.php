<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence;

use MediaWiki\Config\Config;

final readonly class PresenceSettings {

	private const int MIN_INTERVAL = 5;

	private const int MIN_ACTIVE_INTERVAL = 2;

	public function __construct(
		public bool $showUsernames,
		public bool $sectionIncludesWholePageEditors,
		public bool $sectionIncludesSameSectionEditors,
		public bool $includeOwnOtherSessions,
		public int $maxListedNames,
		public int $heartbeatInterval,
		public int $activeHeartbeatInterval,
		public int $timeout,
		public string $cacheId,
	) {
	}

	public static function fromConfig( Config $config ): self {
		$interval = max( self::MIN_INTERVAL, (int)$config->get( ConfigNames::HeartbeatInterval ) );
		$active = min( $interval, max( self::MIN_ACTIVE_INTERVAL, (int)$config->get( ConfigNames::ActiveHeartbeatInterval ) ) );
		$timeout = max( $interval * 2, (int)$config->get( ConfigNames::Timeout ) );

		return new self(
			showUsernames: (bool)$config->get( ConfigNames::ShowUsernames ),
			sectionIncludesWholePageEditors: (bool)$config->get( ConfigNames::SectionIncludeWholePageEditors ),
			sectionIncludesSameSectionEditors: (bool)$config->get( ConfigNames::SectionIncludeSameSectionEditors ),
			includeOwnOtherSessions: (bool)$config->get( ConfigNames::IncludeOwnOtherSessions ),
			maxListedNames: max( 1, (int)$config->get( ConfigNames::MaxListedNames ) ),
			heartbeatInterval: $interval,
			activeHeartbeatInterval: $active,
			timeout: $timeout,
			cacheId: (string)$config->get( ConfigNames::CacheId ),
		);
	}
}
