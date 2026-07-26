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
 * Plugin Name: MiMe Simple Events and Calendar
 * Text Domain: mime-simple-events-calendar
`,
	readmeSource: '=== MiMe Simple Events and Calendar ===',
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
					'MiMe Simple Events and Calendar',
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
					'mime-simple-events-calendar',
					'wrong-text-domain',
				),
			} ),
		/Inconsistent release identity/,
	);
} );

test( 'accepts a minimal, rooted production archive', () => {
	assert.doesNotThrow( () =>
		assertReleaseEntries( [
			'mime-simple-events-calendar/mime-simple-events-calendar.php',
			'mime-simple-events-calendar/LICENSE',
			'mime-simple-events-calendar/readme.txt',
			'mime-simple-events-calendar/composer.json',
			'mime-simple-events-calendar/THIRD-PARTY-NOTICES.txt',
			'mime-simple-events-calendar/vendor/autoload.php',
			'mime-simple-events-calendar/languages/mime-simple-events-calendar.pot',
			'mime-simple-events-calendar/blocks/event-title/block.json',
			'mime-simple-events-calendar/src/Plugin.php',
			'mime-simple-events-calendar/templates/single-event.php',
			'mime-simple-events-calendar/assets/src/css/frontend.css',
			'mime-simple-events-calendar/assets/dist/js/calendar.min.js',
			'mime-simple-events-calendar/assets/dist/js/event-fields-editor.min.js',
		] ),
	);
} );

test( 'rejects development files, wrong roots and path traversal', () => {
	for ( const invalidEntry of [
		'mime-simple-events-calendar/.wordpress-org/banner-772x250.png',
		'mime-simple-events-calendar/tests/Unit/Test.php',
		'mime-simple-events-calendar/composer.lock',
		'mime-simple-events-calendar/assets/src/js/calendar.js',
		'mime-simple-events-calendar/languages/payload.php',
		'mime-simple-events-calendar/src/.hidden.php',
		'mime-simple-events-calendar/src/payload.txt',
		'mime-simple-events-calendar/secret.txt',
		'mime-simple-events-calendar/vendor/phpunit/phpunit.php',
		'other-plugin/mime-simple-events-calendar.php',
		'mime-simple-events-calendar/../secret.txt',
	] ) {
		assert.throws(
			() =>
				assertReleaseEntries( [
					'mime-simple-events-calendar/mime-simple-events-calendar.php',
					'mime-simple-events-calendar/LICENSE',
					'mime-simple-events-calendar/readme.txt',
					'mime-simple-events-calendar/composer.json',
					'mime-simple-events-calendar/THIRD-PARTY-NOTICES.txt',
					'mime-simple-events-calendar/vendor/autoload.php',
					'mime-simple-events-calendar/languages/mime-simple-events-calendar.pot',
					'mime-simple-events-calendar/blocks/event-title/block.json',
					'mime-simple-events-calendar/src/Plugin.php',
					'mime-simple-events-calendar/templates/single-event.php',
					'mime-simple-events-calendar/assets/src/css/frontend.css',
					'mime-simple-events-calendar/assets/dist/js/calendar.min.js',
					'mime-simple-events-calendar/assets/dist/js/event-fields-editor.min.js',
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
				'mime-simple-events-calendar/mime-simple-events-calendar.php',
				'mime-simple-events-calendar/LICENSE',
				'mime-simple-events-calendar/readme.txt',
				'mime-simple-events-calendar/composer.json',
				'mime-simple-events-calendar/THIRD-PARTY-NOTICES.txt',
				'mime-simple-events-calendar/vendor/autoload.php',
				'mime-simple-events-calendar/blocks/event-title/block.json',
				'mime-simple-events-calendar/src/Plugin.php',
				'mime-simple-events-calendar/templates/single-event.php',
				'mime-simple-events-calendar/assets/src/css/frontend.css',
				'mime-simple-events-calendar/assets/dist/js/calendar.min.js',
				'mime-simple-events-calendar/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*languages\/mime-simple-events-calendar\.pot/,
	);
} );

test( 'rejects an archive without its complete project license', () => {
	assert.throws(
		() =>
			assertReleaseEntries( [
				'mime-simple-events-calendar/mime-simple-events-calendar.php',
				'mime-simple-events-calendar/readme.txt',
				'mime-simple-events-calendar/composer.json',
				'mime-simple-events-calendar/THIRD-PARTY-NOTICES.txt',
				'mime-simple-events-calendar/vendor/autoload.php',
				'mime-simple-events-calendar/languages/mime-simple-events-calendar.pot',
				'mime-simple-events-calendar/blocks/event-title/block.json',
				'mime-simple-events-calendar/src/Plugin.php',
				'mime-simple-events-calendar/templates/single-event.php',
				'mime-simple-events-calendar/assets/src/css/frontend.css',
				'mime-simple-events-calendar/assets/dist/js/calendar.min.js',
				'mime-simple-events-calendar/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*LICENSE/,
	);
} );

test( 'rejects an archive without its third-party licence notices', () => {
	assert.throws(
		() =>
			assertReleaseEntries( [
				'mime-simple-events-calendar/mime-simple-events-calendar.php',
				'mime-simple-events-calendar/LICENSE',
				'mime-simple-events-calendar/readme.txt',
				'mime-simple-events-calendar/composer.json',
				'mime-simple-events-calendar/vendor/autoload.php',
				'mime-simple-events-calendar/languages/mime-simple-events-calendar.pot',
				'mime-simple-events-calendar/blocks/event-title/block.json',
				'mime-simple-events-calendar/src/Plugin.php',
				'mime-simple-events-calendar/templates/single-event.php',
				'mime-simple-events-calendar/assets/src/css/frontend.css',
				'mime-simple-events-calendar/assets/dist/js/calendar.min.js',
				'mime-simple-events-calendar/assets/dist/js/event-fields-editor.min.js',
			] ),
		/Missing required release path.*THIRD-PARTY-NOTICES\.txt/,
	);
} );

test( 'binds a SHA-256 checksum to the exact archive filename', () => {
	const checksum = 'a'.repeat( 64 );

	assert.equal(
		parseChecksumRecord(
			`${ checksum }  mime-simple-events-calendar-0.1.0.zip\n`,
			'mime-simple-events-calendar-0.1.0.zip',
		),
		checksum,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum }  different.zip\n`,
				'mime-simple-events-calendar-0.1.0.zip',
			),
		/invalid format/,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum } mime-simple-events-calendar-0.1.0.zip\ntrailing`,
				'mime-simple-events-calendar-0.1.0.zip',
			),
		/invalid format/,
	);
} );
