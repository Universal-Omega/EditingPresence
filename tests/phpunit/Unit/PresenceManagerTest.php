<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use Generator;
use MediaWiki\Extension\EditingPresence\Avatar\AvatarProvider;
use MediaWiki\Extension\EditingPresence\Avatar\NullAvatarProvider;
use MediaWiki\Extension\EditingPresence\PresenceEntry;
use MediaWiki\Extension\EditingPresence\PresenceManager;
use MediaWiki\Extension\EditingPresence\PresenceSettings;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use function array_map;
use function count;
use function crc32;

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
		return $this->buildManager(
			names: $names,
			wholePage: $wholePage,
			sameSection: $sameSection,
			ownSessions: $ownSessions,
			rich: false,
			typing: false,
			avatars: new NullAvatarProvider()
		);
	}

	private function newRichManager( bool $names, bool $typing, AvatarProvider $avatars ): PresenceManager {
		return $this->buildManager(
			names: $names,
			wholePage: true,
			sameSection: true,
			ownSessions: false,
			rich: true,
			typing: $typing,
			avatars: $avatars
		);
	}

	private function buildManager(
		bool $names,
		bool $wholePage,
		bool $sameSection,
		bool $ownSessions,
		bool $rich,
		bool $typing,
		AvatarProvider $avatars
	): PresenceManager {
		return new PresenceManager(
			new PresenceSettings(
				showUsernames: $names,
				sectionIncludesWholePageEditors: $wholePage,
				sectionIncludesSameSectionEditors: $sameSection,
				includeOwnOtherSessions: $ownSessions,
				maxListedNames: 5,
				richPresence: $rich,
				typingIndicator: $typing,
				avatarProvider: '',
				heartbeatInterval: 20,
				activeHeartbeatInterval: 3,
				timeout: 60,
				cacheId: '',
			),
			new HashBagOStuff( [] ),
			$this->push,
			$avatars,
		);
	}

	private function newEntry( string $clientId, int $userId, string $section ): PresenceEntry {
		return $this->newTypingEntry( $clientId, $userId, $section, 0 );
	}

	private function newTypingEntry( string $clientId, int $userId, string $section, int $typingUntil ): PresenceEntry {
		return new PresenceEntry(
			clientId: $clientId,
			userId: $userId,
			userName: $userId === 0 ? '' : 'User' . $userId,
			section: $section,
			touched: ConvertibleTimestamp::time(),
			typingUntil: $typingUntil,
		);
	}

	/**
	 * @param PresenceManager $manager
	 * @param list<PresenceEntry> $others
	 * @param PresenceEntry $viewer
	 * @return list<array<string, bool|int|string>>
	 */
	private function viewFor(
		PresenceManager $manager,
		array $others,
		PresenceEntry $viewer
	): array {
		foreach ( $others as $other ) {
			$manager->heartbeat( 'page', $other );
		}

		$all = $manager->heartbeat( 'page', $viewer );
		return $manager->visibleEditors( $viewer, $all );
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
		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );

		ConvertibleTimestamp::setFakeTime( self::NOW + 60, 0 );
		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( 'page', $viewer );

		$this->assertSame( [], $manager->visibleEditors( $viewer, $all ) );
	}

	public function testLeaveRemovesTheEditor(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$manager->leave( 'page', 'aaaaaaaa' );

		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( 'page', $viewer );

		$this->assertSame( [], $manager->visibleEditors( $viewer, $all ) );
	}

	public function testPagesAreIndependent(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( 'one', $this->newEntry( 'aaaaaaaa', 1, '' ) );

		$viewer = $this->newEntry( 'bbbbbbbb', 2, '' );
		$all = $manager->heartbeat( 'two', $viewer );

		$this->assertSame( [], $manager->visibleEditors( $viewer, $all ) );
	}

	public function testOwnOtherSessionsAreShownWhenEnabled(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: true );
		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 5, '' ) );

		$viewer = $this->newEntry( 'bbbbbbbb', 5, '' );
		$all = $manager->heartbeat( 'page', $viewer );

		$this->assertSame(
			[ [ 'group' => 'page', 'anonymous' => false, 'username' => 'User5' ] ],
			$manager->visibleEditors( $viewer, $all )
		);
	}

	public function testPublishesOnlyWhenEditorsChange(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );

		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertSame( [ 'page' ], $this->push->published );

		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertCount( 1, $this->push->published );

		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '3' ) );
		$this->assertCount( 2, $this->push->published );

		$manager->leave( 'page', 'aaaaaaaa' );
		$this->assertCount( 3, $this->push->published );

		$manager->leave( 'page', 'aaaaaaaa' );
		$this->assertCount( 3, $this->push->published );
	}

	public function testPeekReturnsEditorsWithoutRecordingAnything(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );
		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$published = count( $this->push->published );

		$peeked = $manager->peek( 'page' );

		$this->assertCount( 1, $peeked );
		$this->assertSame( 'aaaaaaaa', $peeked[0]->clientId );
		$this->assertCount( $published, $this->push->published );
		$this->assertSame( [], $manager->peek( 'other' ) );
	}

	public function testRichDetailsAreAddedForNamedEditors(): void {
		$avatars = new FakeAvatarProvider( [
			1 => 'https://example.org/one.png',
			2 => 'javascript:alert(1)',
			3 => '//example.org/protocol-relative.png',
			4 => '/images/four.png',
		] );
		$manager = $this->newRichManager( names: true, typing: true, avatars: $avatars );
		$viewer = $this->newEntry( 'vvvvvvvv', 9, '' );
		$others = [
			$this->newEntry( 'aaaaaaaa', 1, '' ),
			$this->newEntry( 'bbbbbbbb', 2, '' ),
			$this->newEntry( 'cccccccc', 3, '' ),
			$this->newEntry( 'dddddddd', 4, '' ),
			$this->newEntry( 'eeeeeeee', 5, '' ),
		];

		$editors = $this->viewFor( $manager, $others, $viewer );

		$this->assertSame( 'https://example.org/one.png', $editors[0]['avatar'] );
		$this->assertSame( crc32( 'User1' ) % 360, $editors[0]['hue'] );
		$this->assertArrayNotHasKey( 'avatar', $editors[1] );
		$this->assertArrayNotHasKey( 'avatar', $editors[2] );
		$this->assertSame( '/images/four.png', $editors[3]['avatar'] );
		$this->assertArrayNotHasKey( 'avatar', $editors[4] );
		$this->assertArrayHasKey( 'hue', $editors[4] );
	}

	public function testRichDetailsNeverLeakWithoutNames(): void {
		$avatars = new FakeAvatarProvider( [ 1 => 'https://example.org/one.png' ] );
		$manager = $this->newRichManager( names: false, typing: true, avatars: $avatars );

		$editors = $this->viewFor(
			$manager,
			[ $this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 5 ) ],
			$this->newEntry( 'vvvvvvvv', 9, '' )
		);

		$this->assertSame( [ [ 'group' => 'page', 'anonymous' => false ] ], $editors );
	}

	public function testRichDetailsAreOffByDefault(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );

		$editors = $this->viewFor(
			$manager,
			[ $this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 5 ) ],
			$this->newEntry( 'vvvvvvvv', 9, '' )
		);

		$this->assertSame( [ 'group' => 'page', 'anonymous' => false, 'username' => 'User1' ], $editors[0] );
	}

	public function testTypingIsReportedOnlyWhileItLasts(): void {
		$manager = $this->newRichManager( names: true, typing: true, avatars: new NullAvatarProvider() );
		$viewer = $this->newEntry( 'vvvvvvvv', 9, '' );

		$editors = $this->viewFor(
			$manager,
			[
				$this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 5 ),
				$this->newTypingEntry( 'bbbbbbbb', 2, '', self::NOW - 1 ),
			],
			$viewer
		);

		$this->assertTrue( $editors[0]['typing'] );
		$this->assertArrayNotHasKey( 'typing', $editors[1] );
	}

	public function testTypingCanBeTurnedOff(): void {
		$manager = $this->newRichManager( names: true, typing: false, avatars: new NullAvatarProvider() );

		$editors = $this->viewFor(
			$manager,
			[ $this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 5 ) ],
			$this->newEntry( 'vvvvvvvv', 9, '' )
		);

		$this->assertArrayNotHasKey( 'typing', $editors[0] );
		$this->assertArrayHasKey( 'hue', $editors[0] );
	}

	public function testPublishesWhenTypingStartsAndStops(): void {
		$manager = $this->newManager( names: true, wholePage: true, sameSection: true, ownSessions: false );

		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertCount( 1, $this->push->published );

		$manager->heartbeat( 'page', $this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 10 ) );
		$this->assertCount( 2, $this->push->published );

		$manager->heartbeat( 'page', $this->newTypingEntry( 'aaaaaaaa', 1, '', self::NOW + 10 ) );
		$this->assertCount( 2, $this->push->published );

		$manager->heartbeat( 'page', $this->newEntry( 'aaaaaaaa', 1, '' ) );
		$this->assertCount( 3, $this->push->published );
	}
}
