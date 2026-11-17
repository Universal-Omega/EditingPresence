<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use Generator;
use MediaWiki\Extension\EditingPresence\PresenceEntry;
use MediaWikiUnitTestCase;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\PresenceEntry
 */
class PresenceEntryTest extends MediaWikiUnitTestCase {

	public function testRoundTrip(): void {
		$entry = new PresenceEntry(
			clientId: 'abcdef0123',
			userId: 7,
			userName: 'Example',
			section: '2',
			touched: 1000,
		);

		$copy = PresenceEntry::fromArray( data: $entry->toArray() );

		$this->assertNotNull( $copy );
		$this->assertSame( $entry->toArray(), $copy->toArray() );
	}

	public function testDerivedProperties(): void {
		$wholePage = new PresenceEntry( clientId: 'a', userId: 0, userName: '', section: '', touched: 1 );
		$section = new PresenceEntry( clientId: 'b', userId: 5, userName: 'Example', section: '3', touched: 1 );

		$this->assertTrue( $wholePage->isWholePage );
		$this->assertFalse( $wholePage->isRegistered );
		$this->assertFalse( $section->isWholePage );
		$this->assertTrue( $section->isRegistered );
	}

	/**
	 * @dataProvider provideInvalidData
	 * @param array<mixed> $data
	 */
	public function testInvalidDataIsRejected( array $data ): void {
		$this->assertNull( PresenceEntry::fromArray( data: $data ) );
	}

	public static function provideInvalidData(): Generator {
		yield 'empty' => [ [] ];
		yield 'wrong user id type' => [ [
			'clientId' => 'a', 'userId' => '1', 'userName' => '', 'section' => '', 'touched' => 1,
		] ];
		yield 'missing touched' => [ [
			'clientId' => 'a', 'userId' => 1, 'userName' => '', 'section' => '',
		] ];
	}
}
