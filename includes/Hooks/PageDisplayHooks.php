<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Hooks;

use MediaWiki\Output\Hook\BeforePageDisplayHook;
use const CONTENT_MODEL_WIKITEXT;

final class PageDisplayHooks implements BeforePageDisplayHook {

	/**
	 * The mobile editor is an overlay on the page, not an edit form, so the
	 * edit page hook never runs there. The module loaded here is a small stub
	 * that waits for the overlay to open.
	 *
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $skin->getSkinName() !== 'minerva' ) {
			return;
		}

		$title = $out->getTitle();
		if (
			$title === null ||
			!$title->canExist() ||
			!$title->hasContentModel( CONTENT_MODEL_WIKITEXT ) ||
			!$out->getAuthority()->probablyCan( 'edit', $title )
		) {
			return;
		}

		$out->addModules( [ 'ext.editingPresence.mobile' ] );
	}
}
