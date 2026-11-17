<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Extension\EditingPresence\Push\NchanPushBackend;
use MediaWiki\Extension\EditingPresence\Push\PushSettings;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\Push\NchanPushBackend
 */
class NchanPushBackendTest extends MediaWikiUnitTestCase {

	private function newSettings( string $secret, string $subscribe, string $publish ): PushSettings {
		return new PushSettings(
			backend: 'nchan',
			nchanSubscribeUrl: $subscribe,
			nchanPublishUrl: $publish,
			nchanSecret: $secret,
			nchanProtocol: 'websocket',
		);
	}

	private function newBackend(
		PushSettings $settings,
		HttpRequestFactory $http,
		LoggerInterface $logger
	): NchanPushBackend {
		return new NchanPushBackend( settings: $settings, http: $http, logger: $logger );
	}

	public function testIsOffWithoutASecret(): void {
		$backend = $this->newBackend(
			$this->newSettings( '', '/sub', 'http://127.0.0.1/pub' ),
			$this->createNoOpMock( HttpRequestFactory::class, [] ),
			$this->createNoOpMock( LoggerInterface::class, [] )
		);

		$this->assertNull( $backend->getClientConfig( pageKey: 'page' ) );
	}

	public function testIsOffWithoutUrls(): void {
		$backend = $this->newBackend(
			$this->newSettings( 'secret', '', '' ),
			$this->createNoOpMock( HttpRequestFactory::class, [] ),
			$this->createNoOpMock( LoggerInterface::class, [] )
		);

		$this->assertNull( $backend->getClientConfig( pageKey: 'page' ) );
	}

	public function testClientConfigPointsAtThePageChannel(): void {
		$backend = $this->newBackend(
			$this->newSettings( 'secret', '/editingpresence/sub/', 'http://127.0.0.1/pub' ),
			$this->createNoOpMock( HttpRequestFactory::class, [] ),
			$this->createNoOpMock( LoggerInterface::class, [] )
		);

		$config = $backend->getClientConfig( pageKey: 'page' );

		$this->assertNotNull( $config );
		$this->assertSame( 'nchan', $config['type'] );
		$this->assertSame( 'websocket', $config['protocol'] );
		$this->assertSame(
			'/editingpresence/sub/' . hash_hmac( 'sha256', 'page', 'secret' ),
			$config['url']
		);
	}

	public function testChannelIdsDependOnPageAndSecret(): void {
		$http = $this->createNoOpMock( HttpRequestFactory::class, [] );
		$logger = $this->createNoOpMock( LoggerInterface::class, [] );
		$one = $this->newBackend( $this->newSettings( 'one', '/sub', '/pub' ), $http, $logger );
		$two = $this->newBackend( $this->newSettings( 'two', '/sub', '/pub' ), $http, $logger );

		$this->assertSame( $one->getChannelId( pageKey: 'a' ), $one->getChannelId( pageKey: 'a' ) );
		$this->assertNotSame( $one->getChannelId( pageKey: 'a' ), $one->getChannelId( pageKey: 'b' ) );
		$this->assertNotSame( $one->getChannelId( pageKey: 'a' ), $two->getChannelId( pageKey: 'a' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $one->getChannelId( pageKey: 'a' ) );
	}

	public function testPublishPostsToThePageChannel(): void {
		$expectedUrl = 'http://127.0.0.1/pub/' . hash_hmac( 'sha256', 'page', 'secret' );

		$http = $this->createMock( HttpRequestFactory::class );
		$http->expects( $this->once() )
			->method( 'post' )
			->with(
				$expectedUrl,
				$this->callback( static fn ( array $options ): bool => $options['postData'] === 'changed' ),
				$this->anything()
			)
			->willReturn( 'ok' );

		$logger = $this->createNoOpMock( LoggerInterface::class, [] );
		$backend = $this->newBackend(
			$this->newSettings( 'secret', '/sub', 'http://127.0.0.1/pub/' ),
			$http,
			$logger
		);

		$backend->publishNow( pageKey: 'page' );
	}

	public function testFailedPublishIsLogged(): void {
		$http = $this->createMock( HttpRequestFactory::class );
		$http->method( 'post' )->willReturn( null );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' );

		$backend = $this->newBackend(
			$this->newSettings( 'secret', '/sub', 'http://127.0.0.1/pub' ),
			$http,
			$logger
		);

		$backend->publishNow( pageKey: 'page' );
	}
}
