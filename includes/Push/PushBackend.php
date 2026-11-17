<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Push;

/**
 * Something that can wake up the other open editors of a page right away.
 *
 * Backends are registered through the EditingPresencePushBackends extension
 * attribute, keyed by name, and picked with $wgEditingPresencePushBackend.
 * The notice itself never travels over a backend, it is only a nudge to
 * fetch the current list.
 */
interface PushBackend {

	/**
	 * Describes how the browser should connect for this page. The array must
	 * contain a string "type" that a registered browser transport understands.
	 * It may contain a "module" naming a ResourceLoader module to load first.
	 *
	 * @return array<string, mixed>|null Null when the backend is not usable.
	 */
	public function getClientConfig( string $pageKey ): ?array;

	/**
	 * Tells everyone connected for this page that the list of editors changed.
	 * Failures must not break the request that triggered them.
	 */
	public function publishChange( string $pageKey ): void;
}
