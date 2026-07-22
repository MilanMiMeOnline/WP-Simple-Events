import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import {
	assertReleaseIdentity,
	assertReleaseEntries,
	getReleaseVersion,
	parseChecksumRecord,
} from './release-contract.mjs';

const validSources = {
	packageSource: JSON.stringify( { version: '0.1.0' } ),
	pluginSource: `
 * Version: 0.1.0
define( 'WPSE_VERSION', '0.1.0' );
`,
	readmeSource: 'Stable tag: 0.1.0',
};

const validIdentitySources = {
	pluginSource: `
 * Plugin Name: Simple Events by MiMe
 * Text Domain: simple-events-by-mime
`,
	readmeSource: '=== Simple Events by MiMe ===',
};

test( 'returns the shared release version when all public versions match', () => {
	assert.equal( getReleaseVersion( validSources ), '0.1.0' );
} );

test( 'rejects inconsistent public release versions', () => {
	assert.throws(
		() =>
			getReleaseVersion( {
				...validSources,
				readmeSource: 'Stable tag: 0.2.0',
			} ),
		/Inconsistent release versions/,
	);
} );

test( 'accepts only the chosen public plugin identity', () => {
	assert.doesNotThrow( () => assertReleaseIdentity( validIdentitySources ) );
	assert.throws(
		() =>
			assertReleaseIdentity( {
				...validIdentitySources,
				pluginSource: validIdentitySources.pluginSource.replace(
					'Simple Events by MiMe',
					'Unapproved Events Name',
				),
			} ),
		/Inconsistent release identity/,
	);
	assert.throws(
		() =>
			assertReleaseIdentity( {
				...validIdentitySources,
				pluginSource: validIdentitySources.pluginSource.replace(
					'simple-events-by-mime',
					'wrong-text-domain',
				),
			} ),
		/Inconsistent release identity/,
	);
} );

test( 'accepts a minimal, rooted production archive', () => {
	assert.doesNotThrow( () =>
		assertReleaseEntries( [
			'simple-events-by-mime/simple-events-by-mime.php',
			'simple-events-by-mime/LICENSE',
			'simple-events-by-mime/readme.txt',
			'simple-events-by-mime/composer.json',
			'simple-events-by-mime/THIRD-PARTY-NOTICES.txt',
			'simple-events-by-mime/vendor/autoload.php',
			'simple-events-by-mime/languages/simple-events-by-mime.pot',
			'simple-events-by-mime/blocks/event-title/block.json',
			'simple-events-by-mime/src/Plugin.php',
			'simple-events-by-mime/templates/single-event.php',
			'simple-events-by-mime/assets/src/css/frontend.css',
			'simple-events-by-mime/assets/dist/js/calendar.min.js',
			'simple-events-by-mime/assets/dist/js/event-fields-editor.min.js',
		] ),
	);
} );

test( 'rejects development files, wrong roots and path traversal', () => {
	for ( const invalidEntry of [
		'simple-events-by-mime/.wordpress-org/banner-772x250.png',
		'simple-events-by-mime/tests/Unit/Test.php',
		'simple-events-by-mime/composer.lock',
		'simple-events-by-mime/assets/src/js/calendar.js',
		'simple-events-by-mime/languages/payload.php',
		'simple-events-by-mime/src/.hidden.php',
		'simple-events-by-mime/src/payload.txt',
		'simple-events-by-mime/secret.txt',
		'simple-events-by-mime/vendor/phpunit/phpunit.php',
		'other-plugin/simple-events-by-mime.php',
		'simple-events-by-mime/../secret.txt',
	] ) {
		assert.throws(
			() =>
				assertReleaseEntries( [
					'simple-events-by-mime/simple-events-by-mime.php',
					'simple-events-by-mime/LICENSE',
					'simple-events-by-mime/readme.txt',
					'simple-events-by-mime/composer.json',
					'simple-events-by-mime/THIRD-PARTY-NOTICES.txt',
					'simple-events-by-mime/vendor/autoload.php',
					'simple-events-by-mime/languages/simple-events-by-mime.pot',
					'simple-events-by-mime/blocks/event-title/block.json',
					'simple-events-by-mime/src/Plugin.php',
					'simple-events-by-mime/templates/single-event.php',
					'simple-events-by-mime/assets/src/css/frontend.css',
					'simple-events-by-mime/assets/dist/js/calendar.min.js',
					'simple-events-by-mime/assets/dist/js/event-fields-editor.min.js',
					invalidEntry,
				] ),
			/Invalid release archive/,
			invalidEntry,
		);
	}
} );

test( 'rejects an archive with a required production file missing', () => {
	assert.throws(
		() =>
			assertReleaseEntries( [
				'simple-events-by-mime/simple-events-by-mime.php',
				'simple-events-by-mime/LICENSE',
				'simple-events-by-mime/readme.txt',
				'simple-events-by-mime/composer.json',
				'simple-events-by-mime/THIRD-PARTY-NOTICES.txt',
				'simple-events-by-mime/vendor/autoload.php',
				'simple-events-by-mime/blocks/event-title/block.json',
				'simple-events-by-mime/src/Plugin.php',
				'simple-events-by-mime/templates/single-event.php',
				'simple-events-by-mime/assets/src/css/frontend.css',
				'simple-events-by-mime/assets/dist/js/calendar.min.js',
				'simple-events-by-mime/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*languages\/simple-events-by-mime\.pot/,
	);
} );

test( 'rejects an archive without its complete project license', () => {
	assert.throws(
		() =>
			assertReleaseEntries( [
				'simple-events-by-mime/simple-events-by-mime.php',
				'simple-events-by-mime/readme.txt',
				'simple-events-by-mime/composer.json',
				'simple-events-by-mime/THIRD-PARTY-NOTICES.txt',
				'simple-events-by-mime/vendor/autoload.php',
				'simple-events-by-mime/languages/simple-events-by-mime.pot',
				'simple-events-by-mime/blocks/event-title/block.json',
				'simple-events-by-mime/src/Plugin.php',
				'simple-events-by-mime/templates/single-event.php',
				'simple-events-by-mime/assets/src/css/frontend.css',
				'simple-events-by-mime/assets/dist/js/calendar.min.js',
				'simple-events-by-mime/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*LICENSE/,
	);
} );

test( 'rejects an archive without its third-party licence notices', () => {
	assert.throws(
		() =>
			assertReleaseEntries( [
				'simple-events-by-mime/simple-events-by-mime.php',
				'simple-events-by-mime/LICENSE',
				'simple-events-by-mime/readme.txt',
				'simple-events-by-mime/composer.json',
				'simple-events-by-mime/vendor/autoload.php',
				'simple-events-by-mime/languages/simple-events-by-mime.pot',
				'simple-events-by-mime/blocks/event-title/block.json',
				'simple-events-by-mime/src/Plugin.php',
				'simple-events-by-mime/templates/single-event.php',
				'simple-events-by-mime/assets/src/css/frontend.css',
				'simple-events-by-mime/assets/dist/js/calendar.min.js',
				'simple-events-by-mime/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*THIRD-PARTY-NOTICES\.txt/,
	);
} );

test( 'binds a SHA-256 checksum to the exact archive filename', () => {
	const checksum = 'a'.repeat( 64 );

	assert.equal(
		parseChecksumRecord(
			`${ checksum }  simple-events-by-mime-0.1.0.zip\n`,
			'simple-events-by-mime-0.1.0.zip',
		),
		checksum,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum }  different.zip\n`,
				'simple-events-by-mime-0.1.0.zip',
			),
		/invalid format/,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum } simple-events-by-mime-0.1.0.zip\ntrailing`,
				'simple-events-by-mime-0.1.0.zip',
			),
		/invalid format/,
	);
} );
