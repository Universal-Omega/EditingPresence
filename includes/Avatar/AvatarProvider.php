<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Avatar;

/**
 * Finds the avatar of a registered user. Other extensions can add their own
 * through the EditingPresence.AvatarProviders extension attribute.
 */
interface AvatarProvider {

	/**
	 * @param int $userId Local id of a registered user.
	 * @return string|null An absolute or root relative URL, or null when the user has no avatar.
	 */
	public function getAvatarUrl( int $userId ): ?string;
}
