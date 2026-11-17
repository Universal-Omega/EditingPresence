<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Hooks;

use MediaWiki\Hook\EditPage__showEditForm_initialHook;

final class EditPageHooks implements EditPage__showEditForm_initialHook {

	/** @inheritDoc */
	public function onEditPage__showEditForm_initial( $editor, $out ): void {
		$out->addModules( [ 'ext.editingPresence.wikitext' ] );
		$out->addJsConfigVars( 'wgEditingPresenceSection', $editor->section );
	}
}
