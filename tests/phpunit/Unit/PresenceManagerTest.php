<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use Generator;
use MediaWiki\Extension\EditingPresence\PresenceEntry;
use MediaWiki\Extension\EditingPresence\PresenceManager;
use MediaWiki\Extension\EditingPresence\PresenceSettings;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\PresenceManager
 */
class PresenceManagerTest extends MediaWikiUnitTestCase {

	private const int NOW = 1000;

	private RecordingPushBackend $push;

	protected function setUp(): void {
		parent::setUp();
		$this->push = new RecordingPushBackend();
		ConvertibleTimestamp::setFakeTime( self::NOW, 0 );
	}

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false, 0 );
		parent::tearDown();
	}

	private function newManager(
		bool $names,
		bool $wholePage,
		bool $sameSection,
		bool $ownSessions
	): PresenceManager {
		return new PresenceManager(
			settings: new PresenceSettings(
				showUsernames: $names,
				sectionIncludesWholePageEditors: $wholePage,
				sectionIncludesSameSectionEditors: $sameSection,
				includeOwnOtherSessions: $ownSessions,
				maxListedNames: 5,
				heartbeatInterval: 20,
				timeout: 60,
				cacheId: '',
			),
			store: new HashBagOStuff( [] ),
			push: $this->push,
		);
	}

	private function newEntry( string $clientId, int $userId, string $section ): PresenceEntry {
		return new PresenceEntry(
			clientId: $clientId,
			userId: $userId,
			userName: $userId === 0 ? '' : 'User' . $userId,
			section: $section,
			touched: ConvertibleTimestamp::time(),
		);
	}

	/**
	 * @param list<PresenceEntry> $others
	 * @return list<array{group: string, anonymous: bool, username?: string}>
	 */
	private function viewFor(
		PresenceManager $manager,
		array $others,
		PresenceEntry $viewer
	): array {
		foreach ( $others as $other ) {
			$manager->heartbeat( pageKey: 'page', entry: $other );
		}

		$all = $manager->heartbeat( pageKey: 'page', entry: $viewer );
		return $manager->visibleEditors( viewer: $viewer, entries: $all );
	}

	/**
	 * @dataProvider provideVisibility
	 * @param array{bool, bool, bool} $flags Show names, include whole page, include same section.
	 * @param list<array{string, int, string}> $others Client id, user id, section.
	 * @param array{string, int, string} $viewer Client id, user id, section.
	 * @param list<array<string, mixed>> $expected
	 */
	public function testVisibility( array $flags, array $others, array $viewer, array $expected ): void {
		$manager = $this->newManager(
			names: $flags[0],
			wholePage: $flags[1],
			sameSection: $flags[2],
			ownSessions: false
		);

		$entries = array_map(
			fn ( array $other ): PresenceEntry => $this->newEntry( $other[0], $other[1], $other[2] ),
			$others
		);

		$this->assertSame(
			$expected,
			$this->viewFor( $manager, $entries, $this->newEntry( $viewer[0], $viewer[1], $viewer[2] ) )
		);
	}

	public static function provideVisibility(): Generator {
		$everyone = [
			[ 'aaaaaaaa', 1, '' ],
			[ 'bbbbbbbb', 2, '3' ],
			[ 'cccccccc', 3, '5' ],
			[ 'dddddddd', 0, '3' ],
		];

		yield 'whole page viewer sees everyone' => [
			[ true, true, true ], $everyone, [ 'zzzzzzzz', 9, '' ],
			[
				[ 'group' => 'page', 'anonymous' => false, 'username' => 'User1' ],
				[ 'group' => 'section', 'anonymous' => false, 'username' => 'User2' ],
				[ 'group' => 'section', 'anonymous' => false, 'username' => 'User3' ],
				[ 'group' => 'section', 'anonymous' => true ],
			],
		];

		yield 'section viewer sees whole page and same section' => [
			[ true, true, true ], $everyone, [ 'yyyyyyyy', 8, '3' ],
			[
				[ 'group' => 'page', 'anonymous' => false, 'username' => 'User1' ],
				[ 'group' => 'section', 'anonymous' => false, 'username' => 'User2' ],
				[ 'group' => 'section', 'anonymous' => true ],
			],
		];

		yield 'section viewer without whole page editors' => [
			[ true, false, true ], $everyone, [ 'yyyyyyyy', 8, '3' ],
			[
				[ 'group' => 'section', 'anonymous' => false, 'username' => 'User2' ],
				[ 'group' => 'section', 'anonymous' => true ],
			],
		];

		yield 'section viewer without same section editors' => [
			[ true, true, false ], $everyone, [ 'yyyyyyyy', 8, '3' ],
			[
				[ 'group' => 'page', 'anonymous' => false, 'username' => 'User1' ],
			],
		];

		yield 'section viewer with both disabled' => [
			[ true, false, false ], $everyone, [ 'yyyyyyyy', 8, '3' ], [],
		];

		yield 'usernames hidden' => [
			[ false, true, true ], [ [ 'aaaaaaaa', 1, '' ] ], [ 'yyyyyyyy', 8, '' ],
			[
				[ 'group' => 'page', 'anonymous' => false ],
			],
		];

		yield 'own account in another tab is ignored' => [
			[ true, true, true ], [ [ 'aaaaaaaa', 5, '' ] ], [ 'bbbbbbbb', 5, '' ], [],
		];

		yield 'two tabs of one other user count once' => [
			[ true, true, true ],
			[ [ 'cccccccc', 6, '' ], [ 'dddddddd', 6, '' ] ],
			[ 'eeeeeeee', 8, '' ],
			[
				[ 'group' => 'page', 'anonymous' => false, 'username' => 'User6' ],
			],
		];
	}

	public function testExpiredEntriesAreDropped(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );

		ConvertibleTimestamp::setFakeTime( self::NOW + 60, 0 );
		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( pageKey: 'page', entry: $viewer );

		$this->assertSame( [], $manager->visibleEditors( viewer: $viewer, entries: $all ) );
	}

	public function testLeaveRemovesTheEditor(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$manager->leave( pageKey: 'page', clientId: 'aaaaaaaa' );

		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( pageKey: 'page', entry: $viewer );

		$this->assertSame( [], $manager->visibleEditors( viewer: $viewer, entries: $all ) );
	}

	public function testPagesAreIndependent(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( pageKey: 'one', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );

		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( pageKey: 'two', entry: $viewer );

		$this->assertSame( [], $manager->visibleEditors( viewer: $viewer, entries: $all ) );
	}

	public function testOwnOtherSessionsAreShownWhenEnabled(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: true );
		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 5, '' ) );

		$viewer = $this->newEntry( 'bbbbbbbb', 5, '' );
		$all = $manager->heartbeat( pageKey: 'page', entry: $viewer );

		$this->assertSame(
			[ [ 'group' => 'page', 'anonymous' => false, 'username' => 'User5' ] ],
			$manager->visibleEditors( viewer: $viewer, entries: $all )
		);
	}

	public function testPublishesOnlyWhenEditorsChange(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );

		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertSame( [ 'page' ], $this->push->published );

		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertCount( 1, $this->push->published );

		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '3' ) );
		$this->assertCount( 2, $this->push->published );

		$manager->leave( pageKey: 'page', clientId: 'aaaaaaaa' );
		$this->assertCount( 3, $this->push->published );

		$manager->leave( pageKey: 'page', clientId: 'aaaaaaaa' );
		$this->assertCount( 3, $this->push->published );
	}

	public function testPeekReturnsEditorsWithoutRecordingAnything(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( pageKey: 'page', entry: $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$published = count( $this->push->published );

		$peeked = $manager->peek( pageKey: 'page' );

		$this->assertCount( 1, $peeked );
		$this->assertSame( 'aaaaaaaa', $peeked[0]->clientId );
		$this->assertCount( $published, $this->push->published );
		$this->assertSame( [], $manager->peek( pageKey: 'other' ) );
	}
}
