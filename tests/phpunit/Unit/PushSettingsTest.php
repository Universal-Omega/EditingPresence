<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use Generator;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\EditingPresence\ConfigNames;
use MediaWiki\Extension\EditingPresence\Push\PushSettings;
use MediaWikiUnitTestCase;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\Push\PushSettings
 */
class PushSettingsTest extends MediaWikiUnitTestCase {

	public function testValuesAreRead(): void {
		$settings = PushSettings::fromConfig( new HashConfig( [
			ConfigNames::PushBackend => 'nchan',
			ConfigNames::NchanSubscribeUrl => '/sub',
			ConfigNames::NchanPublishUrl => 'http://127.0.0.1/pub',
			ConfigNames::NchanSecret => 'secret',
			ConfigNames::NchanProtocol => 'sse',
		] ) );

		$this->assertSame( 'nchan', $settings->backend );
		$this->assertSame( '/sub', $settings->nchanSubscribeUrl );
		$this->assertSame( 'http://127.0.0.1/pub', $settings->nchanPublishUrl );
		$this->assertSame( 'secret', $settings->nchanSecret );
		$this->assertSame( 'sse', $settings->nchanProtocol );
	}

	/**
	 * @dataProvider provideProtocols
	 */
	public function testProtocolFallsBackToWebSocket( string $configured, string $expected ): void {
		$settings = PushSettings::fromConfig( new HashConfig( [
			ConfigNames::PushBackend => '',
			ConfigNames::NchanSubscribeUrl => '',
			ConfigNames::NchanPublishUrl => '',
			ConfigNames::NchanSecret => '',
			ConfigNames::NchanProtocol => $configured,
		] ) );

		$this->assertSame( $expected, $settings->nchanProtocol );
	}

	public static function provideProtocols(): Generator {
		yield 'sse' => [ 'sse', 'sse' ];
		yield 'websocket' => [ 'websocket', 'websocket' ];
		yield 'unknown' => [ 'carrier pigeon', 'websocket' ];
		yield 'empty' => [ '', 'websocket' ];
	}
}
