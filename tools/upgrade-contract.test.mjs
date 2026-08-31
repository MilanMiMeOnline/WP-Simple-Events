import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { isTransientWordPressMaintenance } from './upgrade-runner-utils.mjs';

const manifest = JSON.parse(
	await readFile( new URL( './upgrade-releases.json', import.meta.url ), 'utf8' ),
);
const packageManifest = JSON.parse(
	await readFile( new URL( '../package.json', import.meta.url ), 'utf8' ),
);
const workflow = await readFile(
	new URL( '../.github/workflows/quality.yml', import.meta.url ),
	'utf8',
);
const upgradeContract = await readFile(
	new URL( '../docs/UPGRADE-CONTRACT.md', import.meta.url ),
	'utf8',
);
const releaseProcess = await readFile(
	new URL( '../docs/RELEASE-PROCESS.md', import.meta.url ),
	'utf8',
);
const runner = await readFile(
	new URL( './upgrade-playground.mjs', import.meta.url ),
	'utf8',
);

const expectedVersions = [
	'0.2.3',
	'0.2.4',
	'0.2.5',
	'0.3.0',
	'0.4.0',
	'0.5.0',
	'0.6.0',
	'0.7.0',
];

test( 'pins the historical upgrade inputs to immutable checksums', () => {
	assert.equal( manifest.core.version, '6.9' );
	assert.equal( manifest.core.url, 'https://wordpress.org/wordpress-6.9.zip' );
	assert.match( manifest.core.sha256, /^[a-f0-9]{64}$/ );
	assert.deepEqual(
		manifest.releases.map( ( release ) => release.version ),
		expectedVersions,
	);
	assert.equal(
		new Set( manifest.releases.map( ( release ) => release.tag ) ).size,
		expectedVersions.length,
	);

	for ( const [ index, release ] of manifest.releases.entries() ) {
		assert.equal( release.tag, `v${ release.version }` );
		assert.match( release.sha256, /^[a-f0-9]{64}$/ );
		assert.equal( release.schemaVersion, index < 4 ? '1.0.0' : '2.1.0' );
	}
} );

test( 'documents the automatic and manual upgrade boundaries', () => {
	for ( const version of expectedVersions ) {
		assert.match( upgradeContract, new RegExp( version.replaceAll( '.', '\\.' ) ) );
	}
	assert.match( upgradeContract, /Manual handoff from 0\.2\.1 or 0\.2\.2/ );
	assert.match( upgradeContract, /missing occurrence table/i );
	assert.match( releaseProcess, /npm run test:upgrade/ );
} );

test( 'keeps the real lifecycle matrix wired into package scripts and CI', () => {
	assert.equal( packageManifest.scripts[ 'pretest:upgrade' ], 'npm run build:release' );
	assert.equal(
		packageManifest.scripts[ 'test:upgrade' ],
		'node tools/upgrade-playground.mjs',
	);
	assert.match( workflow, /^  upgrade:$/m );
	assert.match( workflow, /^      - run: npm run test:upgrade$/m );
	assert.match( runner, /Published \$\{ release\.version \} checksum mismatch/ );
	assert.match( runner, /Unsafe historical archive entry/ );
	assert.match( runner, /DISABLE_WP_CRON: true/ );
	assert.match( runner, /drop-table/ );
	assert.match( runner, /deactivate/ );
	assert.match( runner, /uninstall/ );
} );

test( 'retries only the pre-bootstrap WordPress maintenance response', () => {
	const maintenance = '<!doctype html><title>Maintenance</title>';

	assert.equal(
		isTransientWordPressMaintenance( 503, 'text/html; charset=UTF-8', maintenance ),
		true,
	);
	assert.equal(
		isTransientWordPressMaintenance( 500, 'text/html', maintenance ),
		false,
	);
	assert.equal(
		isTransientWordPressMaintenance( 503, 'application/json', maintenance ),
		false,
	);
	assert.equal(
		isTransientWordPressMaintenance( 503, 'text/html', '<title>Error</title>' ),
		false,
	);
} );
