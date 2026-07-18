import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import {
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

test( 'accepts a minimal, rooted production archive', () => {
	assert.doesNotThrow( () =>
		assertReleaseEntries( [
			'wp-simple-events/wp-simple-events.php',
			'wp-simple-events/readme.txt',
			'wp-simple-events/vendor/autoload.php',
			'wp-simple-events/languages/wp-simple-events.pot',
			'wp-simple-events/src/Plugin.php',
			'wp-simple-events/templates/single-event.php',
			'wp-simple-events/assets/src/css/frontend.css',
			'wp-simple-events/assets/dist/js/calendar.min.js',
		] ),
	);
} );

test( 'rejects development files, wrong roots and path traversal', () => {
	for ( const invalidEntry of [
		'wp-simple-events/tests/Unit/Test.php',
		'wp-simple-events/composer.json',
		'wp-simple-events/assets/src/js/calendar.js',
		'wp-simple-events/languages/payload.php',
		'wp-simple-events/src/.hidden.php',
		'wp-simple-events/src/payload.txt',
		'wp-simple-events/secret.txt',
		'wp-simple-events/vendor/phpunit/phpunit.php',
		'other-plugin/wp-simple-events.php',
		'wp-simple-events/../secret.txt',
	] ) {
		assert.throws(
			() =>
				assertReleaseEntries( [
					'wp-simple-events/wp-simple-events.php',
					'wp-simple-events/readme.txt',
					'wp-simple-events/vendor/autoload.php',
					'wp-simple-events/languages/wp-simple-events.pot',
					'wp-simple-events/src/Plugin.php',
					'wp-simple-events/templates/single-event.php',
					'wp-simple-events/assets/src/css/frontend.css',
					'wp-simple-events/assets/dist/js/calendar.min.js',
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
				'wp-simple-events/wp-simple-events.php',
				'wp-simple-events/readme.txt',
				'wp-simple-events/vendor/autoload.php',
				'wp-simple-events/src/Plugin.php',
				'wp-simple-events/templates/single-event.php',
				'wp-simple-events/assets/src/css/frontend.css',
				'wp-simple-events/assets/dist/js/calendar.min.js',
			] ),
		/Missing required release path.*languages\/wp-simple-events\.pot/,
	);
} );

test( 'binds a SHA-256 checksum to the exact archive filename', () => {
	const checksum = 'a'.repeat( 64 );

	assert.equal(
		parseChecksumRecord(
			`${ checksum }  wp-simple-events-0.1.0.zip\n`,
			'wp-simple-events-0.1.0.zip',
		),
		checksum,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum }  different.zip\n`,
				'wp-simple-events-0.1.0.zip',
			),
		/invalid format/,
	);
	assert.throws(
		() =>
			parseChecksumRecord(
				`${ checksum } wp-simple-events-0.1.0.zip\ntrailing`,
				'wp-simple-events-0.1.0.zip',
			),
		/invalid format/,
	);
} );
