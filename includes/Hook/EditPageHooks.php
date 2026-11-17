<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Hook;

use MediaWiki\Hook\EditPage__showEditForm_initialHook;

final class EditPageHooks implements EditPage__showEditForm_initialHook {

	/** @inheritDoc */
	public function onEditPage__showEditForm_initial( $editor, $out ): void {
		$out->addModules( modules: [ 'ext.editingPresence.wikitext' ] );
		$out->addJsConfigVars( keys: 'wgEditingPresenceSection', value: $editor->section );
	}
}
