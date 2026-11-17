<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Push;

/**
 * Used when no push backend is configured. Editors fall back to polling.
 */
final class NullPushBackend implements PushBackend {

	/** @inheritDoc */
	public function getClientConfig( string $pageKey ): ?array {
		return null;
	}

	/** @inheritDoc */
	public function publishChange( string $pageKey ): void {
	}
}
