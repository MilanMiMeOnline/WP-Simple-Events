import { strict as assert } from 'node:assert';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
	copyFile,
	cp,
	mkdir,
	readdir,
	readFile,
	rm,
	stat,
	writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { basename, join, resolve as resolvePath } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isTransientWordPressMaintenance } from './upgrade-runner-utils.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const pluginSlug = 'mime-simple-events-calendar';
const baseUrl = 'http://localhost:8888';
const probeToken =
	'wpse-upgrade-lifecycle-6f1d0d84e9d14b2da44657001847805f';
const requestedPhp = process.env.WPSE_UPGRADE_PHP ?? '8.3';
const keepFailedEnvironment = process.env.WPSE_UPGRADE_KEEP_ENV === '1';
const currentPluginPath = resolvePath(
	projectDirectory,
	process.env.WPSE_UPGRADE_PLUGIN_PATH ??
		`.release/${ pluginSlug }`,
);
const fixturePluginPath = resolvePath(
	projectDirectory,
	'tests/Fixtures/plugins/wpse-lifecycle-probe',
);
const wpEnvExecutable = fileURLToPath(
	new URL( '../node_modules/.bin/wp-env', import.meta.url ),
);
const environmentRoot = join( tmpdir(), 'mime-simple-events-calendar-upgrade' );
const wpEnvHome = join( environmentRoot, 'wp-env-home' );
const configDirectory = join( environmentRoot, 'config' );
const pluginWorkDirectory = join( environmentRoot, pluginSlug );
const mustUsePluginDirectory = join( environmentRoot, 'mu-plugins' );
const releaseCache = join(
	tmpdir(),
	'mime-simple-events-calendar-upgrade-releases',
);
const releaseManifest = JSON.parse(
	await readFile(
		new URL( './upgrade-releases.json', import.meta.url ),
		'utf8',
	),
);
const requestedCore =
	process.env.WPSE_UPGRADE_CORE ?? releaseManifest.core.url;
const requestedVersions = new Set(
	( process.env.WPSE_UPGRADE_RELEASES ?? '' )
		.split( ',' )
		.map( ( value ) => value.trim() )
		.filter( Boolean ),
);
const releases = releaseManifest.releases.filter(
	( release ) =>
		requestedVersions.size === 0 ||
		requestedVersions.has( release.version ),
);
const expectedColumns = [
	'id',
	'event_id',
	'public_key',
	'recurrence_id',
	'generation',
	'created_utc',
	'segment_id',
	'source',
	'start_local',
	'end_local',
	'start_utc',
	'end_utc',
	'timezone',
	'all_day',
	'event_status',
];

assert.ok( releases.length > 0, 'No historical releases were selected.' );

function trace( message ) {
	process.stdout.write( `[upgrade] ${ message }\n` );
}

function run( command, argumentsList, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, argumentsList, {
			cwd: options.cwd ?? projectDirectory,
			env: { ...process.env, ...options.env },
			stdio: options.capture ? [ 'ignore', 'pipe', 'pipe' ] : 'inherit',
		} );
		let stdout = '';
		let stderr = '';

		if ( options.capture ) {
			child.stdout.on( 'data', ( value ) => {
				stdout += value;
			} );
			child.stderr.on( 'data', ( value ) => {
				stderr += value;
			} );
		}

		child.once( 'error', reject );
		child.once( 'exit', ( code ) => {
			if ( code === 0 || options.allowFailure ) {
				resolve( { code, stderr, stdout } );
				return;
			}

			reject(
				new Error(
					`${ command } exited with ${ code }${
						stderr ? `: ${ stderr.trim() }` : ''
					}`,
				),
			);
		} );
	} );
}

async function exists( path ) {
	try {
		await stat( path );
		return true;
	} catch {
		return false;
	}
}

async function hashFile( path ) {
	return createHash( 'sha256' )
		.update( await readFile( path ) )
		.digest( 'hex' );
}

async function downloadRelease( release ) {
	await mkdir( releaseCache, { recursive: true } );
	const filename = `${ pluginSlug }-${ release.version }.zip`;
	const archive = join( releaseCache, filename );

	if (
		( await exists( archive ) ) &&
		( await hashFile( archive ) ) === release.sha256
	) {
		return archive;
	}

	const url = `https://github.com/MilanMiMeOnline/WP-Simple-Events/releases/download/${ release.tag }/${ filename }`;
	trace( `downloading checksummed ${ release.version } package` );
	const response = await fetch( url, {
		signal: AbortSignal.timeout( 60_000 ),
	} );

	assert.ok(
		response.ok,
		`Could not download ${ url }: HTTP ${ response.status }`,
	);
	await writeFile( archive, Buffer.from( await response.arrayBuffer() ) );
	assert.equal(
		await hashFile( archive ),
		release.sha256,
		`Published ${ release.version } checksum mismatch.`,
	);

	return archive;
}

async function prepareCoreSource() {
	if ( requestedCore !== releaseManifest.core.url ) {
		return requestedCore;
	}

	await mkdir( releaseCache, { recursive: true } );
	const archive = join(
		releaseCache,
		`wordpress-${ releaseManifest.core.version }.zip`,
	);

	if (
		! ( await exists( archive ) ) ||
		( await hashFile( archive ) ) !== releaseManifest.core.sha256
	) {
		trace(
			`downloading checksummed WordPress ${ releaseManifest.core.version } core`,
		);
		const response = await fetch( releaseManifest.core.url, {
			signal: AbortSignal.timeout( 120_000 ),
		} );

		assert.ok(
			response.ok,
			`Could not download ${ releaseManifest.core.url }: HTTP ${ response.status }`,
		);
		await writeFile(
			archive,
			Buffer.from( await response.arrayBuffer() ),
		);
		assert.equal(
			await hashFile( archive ),
			releaseManifest.core.sha256,
			`WordPress ${ releaseManifest.core.version } checksum mismatch.`,
		);
	}

	const destination = join( environmentRoot, 'core' );
	const wordpressDirectory = join( destination, 'wordpress' );

	await rm( destination, { force: true, recursive: true } );
	await mkdir( destination, { recursive: true } );
	await run( 'unzip', [ '-q', archive, '-d', destination ] );
	await stat( join( wordpressDirectory, 'wp-settings.php' ) );

	return wordpressDirectory;
}

async function extractRelease( release ) {
	const archive = await downloadRelease( release );
	const destination = join( environmentRoot, 'historical', release.version );
	const listing = await run( 'unzip', [ '-Z1', archive ], { capture: true } );
	const entries = listing.stdout.split( '\n' ).filter( Boolean );

	assert.ok( entries.length > 0, `${ basename( archive ) } is empty.` );
	for ( const entry of entries ) {
		assert.ok(
			entry.startsWith( `${ pluginSlug }/` ) &&
				! entry.includes( '..' ) &&
				! entry.includes( '\\' ),
			`Unsafe historical archive entry: ${ entry }`,
		);
	}

	await rm( destination, { force: true, recursive: true } );
	await mkdir( destination, { recursive: true } );
	await run( 'unzip', [ '-q', archive, '-d', destination ] );
	const pluginDirectory = join( destination, pluginSlug );

	assert.ok(
		await exists( join( pluginDirectory, `${ pluginSlug }.php` ) ),
		`Historical ${ release.version } package omitted its bootstrap.`,
	);

	return pluginDirectory;
}

async function replacePluginFiles( source ) {
	await mkdir( pluginWorkDirectory, { recursive: true } );
	for ( const entry of await readdir( pluginWorkDirectory ) ) {
		await rm( join( pluginWorkDirectory, entry ), {
			force: true,
			recursive: true,
		} );
	}

	for ( const entry of await readdir( source ) ) {
		const sourcePath = join( source, entry );
		const destinationPath = join( pluginWorkDirectory, entry );

		if ( ( await stat( sourcePath ) ).isDirectory() ) {
			await cp( sourcePath, destinationPath, { recursive: true } );
		} else {
			await copyFile( sourcePath, destinationPath );
		}
	}
}

async function prepareConfiguration() {
	const configuration = JSON.parse(
		await readFile( join( projectDirectory, '.wp-env.json' ), 'utf8' ),
	);

	await rm( environmentRoot, { force: true, recursive: true } );
	await mkdir( configDirectory, { recursive: true } );
	configuration.core = await prepareCoreSource();
	configuration.phpVersion = requestedPhp;
	configuration.plugins = [];
	configuration.themes = [];
	configuration.config = {
		...( configuration.config ?? {} ),
		DISABLE_WP_CRON: true,
	};
	configuration.mappings = {
		...( configuration.mappings ?? {} ),
		'wp-content/mu-plugins': mustUsePluginDirectory,
		[ `wp-content/plugins/${ pluginSlug }` ]: pluginWorkDirectory,
	};

	await mkdir( mustUsePluginDirectory, { recursive: true } );
	await copyFile(
		join( fixturePluginPath, 'wpse-lifecycle-probe.php' ),
		join( mustUsePluginDirectory, 'wpse-lifecycle-probe.php' ),
	);
	await replacePluginFiles( currentPluginPath );
	await writeFile(
		join( configDirectory, '.wp-env.json' ),
		`${ JSON.stringify( configuration, null, 2 ) }\n`,
		'utf8',
	);
}

async function runWpEnv( argumentsList, options = {} ) {
	return run( wpEnvExecutable, argumentsList, {
		...options,
		cwd: configDirectory,
		env: {
			WP_ENV_HOME: wpEnvHome,
			...options.env,
		},
	} );
}

async function request( path, options = {}, redirects = 5, retries = 2 ) {
	const url = new URL( path, baseUrl );
	const headers = new Headers( options.headers );

	let response;

	try {
		response = await fetch( url, {
			...options,
			headers,
			redirect: 'manual',
			signal: options.signal ?? AbortSignal.timeout( 30_000 ),
		} );
	} catch ( error ) {
		if ( retries > 0 && ( options.method ?? 'GET' ) === 'GET' ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 100 ) );

			return request( path, options, redirects, retries - 1 );
		}

		throw error;
	}
	const location = response.headers.get( 'location' );

	if ( location && redirects > 0 ) {
		await response.body?.cancel();

		return request(
			new URL( location, url ).toString(),
			{ ...options, method: 'GET' },
			redirects - 1,
			retries,
		);
	}

	return response;
}

async function waitForWordPress() {
	let lastError;

	for ( let attempt = 0; attempt < 30; attempt += 1 ) {
		try {
			const response = await request( '/', {}, 0 );
			if ( attempt === 0 ) {
				trace( `initial WordPress response: HTTP ${ response.status }` );
			}

			if ( response.status >= 200 && response.status < 400 ) {
				await response.body?.cancel();
				return;
			}

			await response.body?.cancel();
		} catch ( error ) {
			lastError = error;
		}

		await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
	}

	throw lastError ?? new Error( 'WordPress did not become healthy.' );
}

async function api( session, route, options = {} ) {
	for ( let attempt = 0; attempt < 10; attempt += 1 ) {
		const response = await request( `/wp-json/wpse-lifecycle/v1${ route }`, {
			...options,
			headers: {
				...options.headers,
				cookie: 'playground_auto_login_already_happened=1',
				'X-WPSE-Lifecycle-Token': session.token,
				...( options.body ? { 'content-type': 'application/json' } : {} ),
			},
		} );
		const body = await response.text();

		if (
			attempt < 9 &&
			isTransientWordPressMaintenance(
				response.status,
				response.headers.get( 'content-type' ) ?? '',
				body,
			)
		) {
			if ( attempt === 0 ) {
				trace( `waiting for transient WordPress maintenance before ${ route }` );
			}
			await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
			continue;
		}

		let data;

		try {
			data = JSON.parse( body );
		} catch {
			throw new Error(
				`${ route } did not return JSON (HTTP ${ response.status }): ${ body.slice( 0, 500 ) }`,
			);
		}

		assert.ok(
			response.ok,
			`${ route } failed with HTTP ${ response.status }: ${ JSON.stringify( data ) }`,
		);

		return data;
	}

	throw new Error( `${ route } remained in WordPress maintenance mode.` );
}

function assertCurrentDerivedState( snapshot, context ) {
	assert.equal(
		snapshot.derived.schema_version,
		'2.1.0',
		`${ context }: schema version`,
	);
	assert.equal(
		snapshot.derived.migration_complete,
		true,
		`${ context }: migration completion`,
	);
	assert.equal(
		snapshot.derived.table_exists,
		true,
		`${ context }: occurrence table`,
	);
	assert.deepEqual(
		snapshot.derived.table_columns,
		expectedColumns,
		`${ context }: occurrence schema`,
	);
	assert.ok(
		snapshot.derived.occurrence_rows >= snapshot.canonical.events.length,
		`${ context }: every canonical event must have a derived occurrence`,
	);
	assert.deepEqual(
		snapshot.derived.scheduled,
		{
			wpse_occurrence_generation_cleanup: 1,
			wpse_occurrence_index_migrate: 0,
			wpse_occurrence_projection_renewal: 1,
		},
		`${ context }: one bounded job per maintenance hook`,
	);
	assert.equal(
		snapshot.derived.pending_rewrite,
		null,
		`${ context }: one-shot rewrite marker`,
	);
	assert.ok(
		snapshot.capabilities.administrator.length > 0 &&
			snapshot.capabilities.editor.length > 0,
		`${ context }: event capabilities`,
	);
}

function assertNoScheduledMaintenance( snapshot, context ) {
	assert.deepEqual(
		snapshot.derived.scheduled,
		{
			wpse_occurrence_generation_cleanup: 0,
			wpse_occurrence_index_migrate: 0,
			wpse_occurrence_projection_renewal: 0,
		},
		`${ context }: scheduled callbacks`,
	);
	assert.equal(
		snapshot.derived.renewal_offset,
		null,
		`${ context }: disposable renewal cursor`,
	);
}

function assertCanonicalPreserved( before, after, path = 'canonical' ) {
	if ( Array.isArray( before ) ) {
		assert.ok( Array.isArray( after ), `${ path } changed type` );
		assert.equal( after.length, before.length, `${ path } changed length` );

		before.forEach( ( value, index ) => {
			assertCanonicalPreserved(
				value,
				after[ index ],
				`${ path }[${ index }]`,
			);
		} );

		return;
	}

	if ( before !== null && typeof before === 'object' ) {
		assert.ok(
			after !== null && typeof after === 'object',
			`${ path } changed type`,
		);

		for ( const [ key, value ] of Object.entries( before ) ) {
			assert.ok(
				Object.hasOwn( after, key ),
				`${ path }.${ key } was removed`,
			);
			assertCanonicalPreserved( value, after[ key ], `${ path }.${ key }` );
		}

		return;
	}

	assert.equal( after, before, `${ path } changed` );
}

async function activateCanonicalPlugin( session ) {
	const result = await api( session, '/activate', { method: 'POST' } );
	assert.equal( result.activated, true, 'The canonical plugin was not activated.' );
}

async function removeCurrentData( session ) {
	const removed = await api( session, '/uninstall', {
		method: 'POST',
		body: JSON.stringify( { delete: true } ),
	} );

	assert.equal( removed.derived.table_exists, false );
	assert.equal( removed.canonical.events.length, 0 );
	assert.equal( removed.canonical.terms.length, 0 );
	assert.deepEqual( removed.capabilities, {
		administrator: [],
		editor: [],
	} );
	assertNoScheduledMaintenance( removed, 'destructive uninstall' );
	await api( session, '/purge-fixtures', { method: 'POST' } );
}

async function qualifyCleanInstall( session ) {
	trace( 'qualifying clean activation' );
	await activateCanonicalPlugin( session );
	const snapshot = await api( session, '/snapshot' );

	assert.equal( snapshot.canonical.events.length, 0 );
	assert.equal( snapshot.canonical.terms.length, 0 );
	assert.equal( snapshot.derived.table_exists, true );
	assert.equal( snapshot.derived.schema_version, '2.1.0' );
	assert.ok( snapshot.capabilities.administrator.length > 0 );
	assert.ok( snapshot.capabilities.editor.length > 0 );
	await removeCurrentData( session );
}

async function qualifyUpgrade( session, release ) {
	trace( `qualifying ${ release.version } -> current` );
	const historicalDirectory = await extractRelease( release );

	await replacePluginFiles( historicalDirectory );
	await activateCanonicalPlugin( session );
	await api( session, '/seed', {
		method: 'POST',
		body: JSON.stringify( { version: release.version } ),
	} );
	const before = await api( session, '/snapshot' );
	const hasOccurrenceSchema = release.schemaVersion === '2.1.0';

	assert.equal( before.plugin_version, release.version );
	assert.equal(
		before.derived.schema_version,
		release.schemaVersion,
	);
	assert.equal( before.derived.table_exists, hasOccurrenceSchema );

	await replacePluginFiles( currentPluginPath );
	await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
	let after = await api( session, '/snapshot' );

	if ( after.derived.migration_complete !== true ) {
		after = await api( session, '/run-migration', { method: 'POST' } );
	}

	// A following request lets the renewal scheduler observe completed migration.
	after = await api( session, '/snapshot' );
	assertCanonicalPreserved( before.canonical, after.canonical );
	assertCurrentDerivedState( after, `${ release.version } upgrade` );

	return after;
}

async function qualifyLifecycle( session, currentSnapshot ) {
	trace( 'qualifying deactivation and reactivation retention' );
	const deactivated = await api( session, '/deactivate', { method: 'POST' } );

	assert.deepEqual( deactivated.canonical, currentSnapshot.canonical );
	assert.equal( deactivated.derived.table_exists, true );
	assert.deepEqual( deactivated.capabilities, currentSnapshot.capabilities );
	assertNoScheduledMaintenance( deactivated, 'deactivation' );

	await activateCanonicalPlugin( session );
	let reactivated = await api( session, '/snapshot' );

	assert.deepEqual( reactivated.canonical, currentSnapshot.canonical );
	assertCurrentDerivedState( reactivated, 'reactivation' );

	trace( 'qualifying retained uninstall without prior deactivation' );
	const retained = await api( session, '/uninstall', {
		method: 'POST',
		body: JSON.stringify( { delete: false } ),
	} );

	assert.deepEqual( retained.canonical, currentSnapshot.canonical );
	assert.equal( retained.derived.table_exists, true );
	assert.deepEqual( retained.capabilities, currentSnapshot.capabilities );
	assertNoScheduledMaintenance( retained, 'retained uninstall' );

	await activateCanonicalPlugin( session );
	reactivated = await api( session, '/snapshot' );
	assert.deepEqual( reactivated.canonical, currentSnapshot.canonical );
	assertCurrentDerivedState( reactivated, 'activation after retained uninstall' );

	trace( 'qualifying explicit destructive uninstall' );
	const removed = await api( session, '/uninstall', {
		method: 'POST',
		body: JSON.stringify( { delete: true } ),
	} );

	assert.equal( removed.canonical.events.length, 0 );
	assert.equal( removed.canonical.terms.length, 0 );
	assert.ok(
		removed.canonical.pages.some( ( page ) => page.type === 'page' ) &&
			removed.canonical.pages.some(
				( page ) => page.type === 'attachment',
			),
		'Destructive uninstall removed shared pages or media.',
	);
	assert.equal( Object.keys( removed.canonical.options ).length, 0 );
	assert.equal( removed.derived.table_exists, false );
	assert.deepEqual( removed.capabilities, {
		administrator: [],
		editor: [],
	} );
	assertNoScheduledMaintenance( removed, 'destructive uninstall' );
	await api( session, '/purge-fixtures', { method: 'POST' } );
}

async function qualifyDerivedTableRepair( session, currentSnapshot ) {
	trace( 'qualifying missing derived-table repair' );
	const damaged = await api( session, '/drop-table', { method: 'POST' } );

	assert.equal( damaged.derived.table_exists, false );
	assertCanonicalPreserved( currentSnapshot.canonical, damaged.canonical );
	let repaired = await api( session, '/snapshot' );

	assert.equal( repaired.derived.table_exists, true );
	if ( repaired.derived.migration_complete !== true ) {
		repaired = await api( session, '/run-migration', { method: 'POST' } );
	}

	repaired = await api( session, '/snapshot' );
	assertCanonicalPreserved( currentSnapshot.canonical, repaired.canonical );
	assertCurrentDerivedState( repaired, 'missing-table repair' );

	return repaired;
}

await stat( join( currentPluginPath, `${ pluginSlug }.php` ) );
await stat( join( fixturePluginPath, 'wpse-lifecycle-probe.php' ) );
await prepareConfiguration();

let completed = false;

try {
	trace( `starting WordPress ${ requestedCore } on PHP ${ requestedPhp }` );
	await runWpEnv( [ 'start', '--runtime=playground' ], { capture: true } );
	trace( 'WordPress is running' );
	await waitForWordPress();
	trace( 'WordPress health check passed' );
	const session = { token: probeToken };
	trace( 'isolated lifecycle probe authorized' );

	await qualifyCleanInstall( session );
	let currentSnapshot;

	for ( const release of releases ) {
		currentSnapshot = await qualifyUpgrade( session, release );

		if ( release !== releases.at( -1 ) ) {
			await removeCurrentData( session );
		}
	}

	currentSnapshot = await qualifyDerivedTableRepair( session, currentSnapshot );
	await qualifyLifecycle( session, currentSnapshot );
	completed = true;
	trace( `all ${ releases.length } historical upgrade paths and lifecycle paths passed` );
} finally {
	if ( keepFailedEnvironment && ! completed ) {
		process.stderr.write(
			`Retained failed upgrade environment at ${ environmentRoot }.\n`,
		);
	} else {
		await runWpEnv( [ 'stop' ], { allowFailure: true, capture: true } );
		await rm( environmentRoot, { force: true, recursive: true } );
	}
}
