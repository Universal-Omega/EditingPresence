<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Extension\EditingPresence\Push\PushBackend;

/**
 * Remembers which pages were published so tests can look at them.
 */
final class RecordingPushBackend implements PushBackend {

	/** @var list<string> */
	public array $published = [];

	/** @inheritDoc */
	public function getClientConfig( string $pageKey ): ?array {
		return null;
	}

	/** @inheritDoc */
	public function publishChange( string $pageKey ): void {
		$this->published[] = $pageKey;
	}
}
