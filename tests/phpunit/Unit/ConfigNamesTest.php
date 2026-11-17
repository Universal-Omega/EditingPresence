<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Extension\EditingPresence\ConfigNames;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\ConfigNames
 */
class ConfigNamesTest extends MediaWikiUnitTestCase {

	public function testNamesMatchExtensionJson(): void {
		$json = file_get_contents( __DIR__ . '/../../../extension.json' );
		$manifest = json_decode( $json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR );

		$declared = array_keys( $manifest['config'] );
		$constants = array_values( ( new ReflectionClass( ConfigNames::class ) )->getConstants( filter: null ) );

		sort( $declared );
		sort( $constants );

		$this->assertSame( $declared, $constants );
	}
}
