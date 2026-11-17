<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\EditingPresence\ConfigNames;
use MediaWiki\Extension\EditingPresence\PresenceSettings;
use MediaWikiUnitTestCase;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\PresenceSettings
 */
class PresenceSettingsTest extends MediaWikiUnitTestCase {

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function newSettings( array $overrides ): PresenceSettings {
		return PresenceSettings::fromConfig( new HashConfig( $overrides + [
			ConfigNames::ActiveHeartbeatInterval => 3,
			ConfigNames::CacheId => '',
			ConfigNames::HeartbeatInterval => 20,
			ConfigNames::IncludeOwnOtherSessions => false,
			ConfigNames::MaxListedNames => 5,
			ConfigNames::SectionIncludeSameSectionEditors => true,
			ConfigNames::SectionIncludeWholePageEditors => true,
			ConfigNames::ShowUsernames => true,
			ConfigNames::Timeout => 60,
		] ) );
	}

	public function testValuesAreRead(): void {
		$settings = $this->newSettings( [
			ConfigNames::CacheId => 'redis',
			ConfigNames::SectionIncludeSameSectionEditors => false,
			ConfigNames::SectionIncludeWholePageEditors => false,
			ConfigNames::ShowUsernames => false,
		] );

		$this->assertFalse( $settings->showUsernames );
		$this->assertFalse( $settings->sectionIncludesWholePageEditors );
		$this->assertFalse( $settings->sectionIncludesSameSectionEditors );
		$this->assertFalse( $settings->includeOwnOtherSessions );
		$this->assertSame( 5, $settings->maxListedNames );
		$this->assertSame( 'redis', $settings->cacheId );
		$this->assertSame( 20, $settings->heartbeatInterval );
		$this->assertSame( 3, $settings->activeHeartbeatInterval );
		$this->assertSame( 60, $settings->timeout );
	}

	public function testMaxListedNamesHasAFloor(): void {
		$settings = $this->newSettings( [ ConfigNames::MaxListedNames => 0 ] );

		$this->assertSame( 1, $settings->maxListedNames );
	}

	public function testIntervalHasAFloor(): void {
		$settings = $this->newSettings( [ ConfigNames::HeartbeatInterval => 1 ] );

		$this->assertSame( 5, $settings->heartbeatInterval );
	}

	public function testActiveIntervalIsBounded(): void {
		$tooLow = $this->newSettings( [ ConfigNames::ActiveHeartbeatInterval => 0 ] );
		$tooHigh = $this->newSettings( [ ConfigNames::ActiveHeartbeatInterval => 90 ] );

		$this->assertSame( 2, $tooLow->activeHeartbeatInterval );
		$this->assertSame( 20, $tooHigh->activeHeartbeatInterval );
	}

	public function testTimeoutIsAtLeastTwiceTheInterval(): void {
		$settings = $this->newSettings( [
			ConfigNames::HeartbeatInterval => 30,
			ConfigNames::Timeout => 10,
		] );

		$this->assertSame( 60, $settings->timeout );
	}
}
