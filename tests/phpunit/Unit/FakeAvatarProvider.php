<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Extension\EditingPresence\Avatar\AvatarProvider;

/**
 * Hands out the avatars it was given so tests can control what the manager sees.
 */
final class FakeAvatarProvider implements AvatarProvider {

	/**
	 * @param array<int, string> $avatars Avatar URL by user id.
	 */
	public function __construct( private readonly array $avatars ) {
	}

	/** @inheritDoc */
	public function getAvatarUrl( int $userId ): ?string {
		return $this->avatars[$userId] ?? null;
	}
}
