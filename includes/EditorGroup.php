<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence;

/**
 * Which kind of edit another user is doing, relative to the viewer.
 */
enum EditorGroup: string {
	case Page = 'page';
	case Section = 'section';
}
