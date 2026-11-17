<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Avatar;

/**
 * Used when no avatar provider is configured. Editors get their initial instead.
 */
final class NullAvatarProvider implements AvatarProvider {

	/**
	 * @inheritDoc
	 * @param int $userId @phan-unused-param
	 */
	public function getAvatarUrl( int $userId ): ?string {
		return null;
	}
}
