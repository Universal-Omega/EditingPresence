<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence;

use MediaWiki\Extension\EditingPresence\Push\PushBackend;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use function array_map;
use function array_values;
use function count;
use function is_array;

/**
 * Keeps track of who has an editor open on which page, and decides what
 * each editor is allowed to see about the others.
 */
final class PresenceManager {

	private const int MERGE_ATTEMPTS = 5;

	public function __construct(
		private readonly PresenceSettings $settings,
		private readonly BagOStuff $store,
		private readonly PushBackend $push,
	) {
	}

	/**
	 * Records the entry and returns everyone currently present on the page.
	 *
	 * @return list<PresenceEntry>
	 */
	public function heartbeat( string $pageKey, PresenceEntry $entry ): array {
		return $this->update( $pageKey, $entry->clientId, $entry );
	}

	public function leave( string $pageKey, string $clientId ): void {
		$this->update( $pageKey, $clientId, entry: null );
	}

	/**
	 * Returns everyone currently present without recording anything.
	 *
	 * @return list<PresenceEntry>
	 */
	public function peek( string $pageKey ): array {
		$stored = $this->store->get( $this->makeKey( $pageKey ), flags: 0 );

		return array_values(
			$this->activeEntries( $stored, ConvertibleTimestamp::time() )
		);
	}

	/**
	 * @param list<PresenceEntry> $entries
	 * @return list<array{group: string, anonymous: bool, username?: string}>
	 */
	public function visibleEditors( PresenceEntry $viewer, array $entries ): array {
		$seen = [];
		$visible = [];

		foreach ( $entries as $other ) {
			if ( $other->clientId === $viewer->clientId ) {
				continue;
			}
			if (
				!$this->settings->includeOwnOtherSessions &&
				$viewer->isRegistered &&
				$other->userId === $viewer->userId
			) {
				continue;
			}

			$group = $this->classify( $viewer, $other );
			if ( $group === null ) {
				continue;
			}

			$dedupeKey = $other->isRegistered
				? 'u' . $other->userId . ':' . $group->value
				: 'c' . $other->clientId;
			if ( isset( $seen[$dedupeKey] ) ) {
				continue;
			}
			$seen[$dedupeKey] = true;

			$editor = [
				'group' => $group->value,
				'anonymous' => !$other->isRegistered,
			];
			if ( $this->settings->showUsernames && $other->isRegistered ) {
				$editor['username'] = $other->userName;
			}
			$visible[] = $editor;
		}

		return $visible;
	}

	private function classify( PresenceEntry $viewer, PresenceEntry $other ): ?EditorGroup {
		if ( $viewer->isWholePage ) {
			return $other->isWholePage ? EditorGroup::Page : EditorGroup::Section;
		}

		if ( $other->isWholePage ) {
			return $this->settings->sectionIncludesWholePageEditors ? EditorGroup::Page : null;
		}

		if ( $other->section === $viewer->section ) {
			return $this->settings->sectionIncludesSameSectionEditors ? EditorGroup::Section : null;
		}

		return null;
	}

	private function makeKey( string $pageKey ): string {
		return $this->store->makeKey( 'editingpresence', 'page', $pageKey );
	}

	/**
	 * @return list<PresenceEntry>
	 */
	private function update( string $pageKey, string $clientId, ?PresenceEntry $entry ): array {
		$key = $this->makeKey( $pageKey );
		$now = ConvertibleTimestamp::time();
		$timeout = $this->settings->timeout;
		$result = [];
		$changed = false;

		$merged = $this->store->merge(
			$key,
			function ( BagOStuff $unusedCache, string $unusedKey, mixed $current, int &$ttl ) use (
				&$result, &$changed, $clientId, $entry, $now, $timeout
			): array {
				$entries = $this->activeEntries( $current, $now );
				$previous = $entries[$clientId] ?? null;
				$dropped = is_array( $current ) && count( $current ) > count( $entries );

				if ( $entry !== null ) {
					$entries[$entry->clientId] = $entry;
				} else {
					unset( $entries[$clientId] );
				}

				$changed = $dropped || $this->differs( $previous, $entry );
				$result = array_values( $entries );
				$ttl = $timeout;

				return array_map(
					static fn ( PresenceEntry $stored ): array => $stored->toArray(),
					$entries
				);
			},
			exptime: $timeout,
			attempts: self::MERGE_ATTEMPTS,
			flags: 0
		);

		if ( $merged ) {
			if ( $changed ) {
				$this->push->publishChange( $pageKey );
			}
			return $result;
		}

		$stored = $this->store->get( $key, flags: 0 );
		$entries = $this->activeEntries( $stored, $now );
		if ( $entry !== null ) {
			$entries[$entry->clientId] = $entry;
		} else {
			unset( $entries[$clientId] );
		}

		return array_values( $entries );
	}

	/**
	 * Whether the update means other editors need to hear about it. A repeated
	 * heartbeat that changes nothing does not.
	 */
	private function differs( ?PresenceEntry $previous, ?PresenceEntry $entry ): bool {
		if ( $entry === null ) {
			return $previous !== null;
		}

		if ( $previous === null ) {
			return true;
		}

		return $previous->section !== $entry->section;
	}

	/**
	 * @return array<string, PresenceEntry>
	 */
	private function activeEntries( mixed $stored, int $now ): array {
		if ( !is_array( $stored ) ) {
			return [];
		}

		$active = [];
		foreach ( $stored as $raw ) {
			$entry = is_array( $raw ) ? PresenceEntry::fromArray( $raw ) : null;
			if ( $entry === null ) {
				continue;
			}
			if ( $now - $entry->touched >= $this->settings->timeout ) {
				continue;
			}
			$active[$entry->clientId] = $entry;
		}

		return $active;
	}
}
