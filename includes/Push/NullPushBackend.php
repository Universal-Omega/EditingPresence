<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Push;

/**
 * Used when no push backend is configured. Editors fall back to polling.
 */
final class NullPushBackend implements PushBackend {

	/**
	 * @inheritDoc
	 * @param string $pageKey @phan-unused-param
	 */
	public function getClientConfig( string $pageKey ): ?array {
		return null;
	}

	/**
	 * @inheritDoc
	 * @param string $pageKey @phan-unused-param
	 */
	public function publishChange( string $pageKey ): void {
	}
}
