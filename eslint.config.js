'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const jsonc = require( 'eslint-plugin-jsonc' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

module.exports = defineConfig( [
	globalIgnores( [ 'vendor/' ] ),
	{
		ignores: [ 'i18n/**' ],
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki' ) ],
		languageOptions: {
			ecmaVersion: 2017
		},
		rules: {
			'no-jquery/no-global-selector': 'off'
		}
	},
	{
		files: [ 'resources/ext.editingPresence.ve/**/*.js' ],
		languageOptions: {
			globals: {
				ve: 'readonly'
			}
		}
	},
	{
		files: [ 'i18n/en.json', 'i18n/qqq.json', 'i18n/api/en.json', 'i18n/api/qqq.json' ],
		extends: [ jsonc.configs[ 'flat/base' ] ],
		rules: {
			'jsonc/sort-keys': 'error'
		}
	}
] );
