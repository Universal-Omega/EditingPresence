<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Tests\Unit;

use MediaWiki\Extension\EditingPresence\ConfigNames;
use MediaWikiUnitTestCase;
use ReflectionClass;
use function array_keys;
use function array_values;
use function file_get_contents;
use function json_decode;
use function sort;
use const JSON_THROW_ON_ERROR;

/**
 * @group EditingPresence
 * @covers \MediaWiki\Extension\EditingPresence\ConfigNames
 */
class ConfigNamesTest extends MediaWikiUnitTestCase {

	public function testNamesMatchExtensionJson(): void {
		$json = file_get_contents( __DIR__ . '/../../../extension.json' );
		$manifest = json_decode( $json, associative: true, flags: JSON_THROW_ON_ERROR );
		$this->assertIsArray( $manifest );
		$this->assertIsArray( $manifest['config'] );

		$declared = array_keys( $manifest['config'] );
		$constants = array_values( new ReflectionClass( ConfigNames::class )->getConstants() );

		sort( $declared );
		sort( $constants );

		$this->assertSame( $declared, $constants );
	}
}
