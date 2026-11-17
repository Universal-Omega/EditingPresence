<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Avatar;

use Telepedia\UserProfileV2\Avatar\UserProfileV2Avatar;
use function class_exists;
use function is_string;

/**
 * Reads avatars uploaded through the UserProfileV2 extension.
 */
final class UserProfileV2AvatarProvider implements AvatarProvider {

	/** @inheritDoc */
	public function getAvatarUrl( int $userId ): ?string {
		if ( !class_exists( UserProfileV2Avatar::class ) ) {
			return null;
		}

		$url = new UserProfileV2Avatar( $userId )->getAvatarUrl( [ 'raw' => true ] );

		return is_string( $url ) && $url !== '' ? $url : null;
	}
}
