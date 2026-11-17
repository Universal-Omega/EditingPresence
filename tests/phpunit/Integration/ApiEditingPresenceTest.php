<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Integration;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\EditingPresence\ConfigNames;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group EditingPresence
 * @group API
 * @group Database
 * @group medium
 * @covers \MediaWiki\Extension\EditingPresence\Api\ApiEditingPresence
 */
class ApiEditingPresenceTest extends ApiTestCase {

	private const string PAGE = 'EditingPresence test page';

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::MainStash, 'hash' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function request( string $task, string $clientId, string $section, bool $asSysop ): array {
		$user = $asSysop ? $this->getTestSysop()->getUser() : $this->getTestUser()->getUser();

		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'editingpresence',
			'task' => $task,
			'title' => self::PAGE,
			'section' => $section,
			'clientid' => $clientId,
		], null, $user, 'csrf' );

		return $result['editingpresence'];
	}

	public function testHeartbeatListsOtherEditors(): void {
		$this->overrideConfigValue( ConfigNames::ShowUsernames, true );

		$first = $this->request( 'heartbeat', 'aaaaaaaa01', '', false );
		$this->assertSame( [], $first['editors'] );

		$second = $this->request( 'heartbeat', 'bbbbbbbb02', '', true );
		$this->assertCount( 1, $second['editors'] );
		$this->assertSame( 5, $second['maxnames'] );
		$this->assertSame( 'page', $second['editors'][0]['group'] );
		$this->assertSame( $this->getTestUser()->getUser()->getName(), $second['editors'][0]['username'] );
	}

	public function testUsernamesAreNotSentWhenHidden(): void {
		$this->overrideConfigValue( ConfigNames::ShowUsernames, false );

		$this->request( 'heartbeat', 'aaaaaaaa01', '', false );
		$second = $this->request( 'heartbeat', 'bbbbbbbb02', '', true );

		$this->assertCount( 1, $second['editors'] );
		$this->assertArrayNotHasKey( 'username', $second['editors'][0] );
	}

	public function testLeaveRemovesTheEditor(): void {
		$this->request( 'heartbeat', 'aaaaaaaa01', '', false );
		$this->request( 'leave', 'aaaaaaaa01', '', false );

		$second = $this->request( 'heartbeat', 'bbbbbbbb02', '', true );
		$this->assertSame( [], $second['editors'] );
	}

	public function testInvalidSectionIsRejected(): void {
		$this->expectException( ApiUsageException::class );
		$this->request( 'heartbeat', 'aaaaaaaa01', 'not-a-section', false );
	}

	public function testInvalidClientIdIsRejected(): void {
		$this->expectException( ApiUsageException::class );
		$this->request( 'heartbeat', 'bad id!', '', false );
	}

	public function testRefreshListsEditorsWithoutRegisteringTheCaller(): void {
		$this->overrideConfigValue( ConfigNames::ShowUsernames, true );

		$this->request( 'heartbeat', 'aaaaaaaa01', '', false );

		$refreshed = $this->request( 'refresh', 'bbbbbbbb02', '', true );
		$this->assertCount( 1, $refreshed['editors'] );

		$again = $this->request( 'refresh', 'aaaaaaaa01', '', false );
		$this->assertSame( [], $again['editors'] );
	}

	public function testNoPushConfigWithoutABackend(): void {
		$result = $this->request( 'heartbeat', 'aaaaaaaa01', '', false );

		$this->assertArrayNotHasKey( 'push', $result );
		$this->assertSame( 3, $result['activeinterval'] );
	}

	public function testRichDetailsAndTypingReachOtherEditors(): void {
		$this->overrideConfigValue( ConfigNames::ShowUsernames, true );

		[ $first ] = $this->doApiRequestWithToken( [
			'action' => 'editingpresence',
			'task' => 'heartbeat',
			'title' => self::PAGE,
			'section' => '',
			'clientid' => 'aaaaaaaa01',
			'typing' => 1,
		], null, $this->getTestUser()->getUser(), 'csrf' );
		$this->assertTrue( $first['editingpresence']['typingenabled'] );

		$second = $this->request( 'heartbeat', 'bbbbbbbb02', '', true );

		$this->assertTrue( $second['editors'][0]['typing'] );
		$this->assertIsInt( $second['editors'][0]['hue'] );
	}

	public function testTypingIsDisabledWithoutRichPresence(): void {
		$this->overrideConfigValue( ConfigNames::RichPresence, false );

		$result = $this->request( 'heartbeat', 'aaaaaaaa01', '', false );

		$this->assertFalse( $result['typingenabled'] );
	}
}
