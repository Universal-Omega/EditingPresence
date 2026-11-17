<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Push;

use MediaWiki\Config\Config;
use MediaWiki\Extension\EditingPresence\ConfigNames;

final readonly class PushSettings {

	public function __construct(
		public string $backend,
		public string $nchanSubscribeUrl,
		public string $nchanPublishUrl,
		public string $nchanSecret,
		public string $nchanProtocol,
	) {
	}

	public static function fromConfig( Config $config ): self {
		$protocol = (string)$config->get( ConfigNames::NchanProtocol );

		return new self(
			backend: (string)$config->get( ConfigNames::PushBackend ),
			nchanSubscribeUrl: (string)$config->get( ConfigNames::NchanSubscribeUrl ),
			nchanPublishUrl: (string)$config->get( ConfigNames::NchanPublishUrl ),
			nchanSecret: (string)$config->get( ConfigNames::NchanSecret ),
			nchanProtocol: $protocol === 'sse' ? 'sse' : 'websocket',
		);
	}
}
