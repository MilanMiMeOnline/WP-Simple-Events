import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, realpathSync } from 'node:fs';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import {
	basename,
	dirname,
	isAbsolute,
	join,
	resolve as resolvePath,
} from 'node:path';
import { fileURLToPath } from 'node:url';

const projectDirectory = resolvePath(
	dirname( fileURLToPath( import.meta.url ) ),
	'../../..',
);
const baseConfiguration = JSON.parse(
	readFileSync( join( projectDirectory, '.wp-env.json' ), 'utf8' ),
);
const requestedCore =
	process.env.WPSE_PERFORMANCE_CORE ?? baseConfiguration.core;
const identifier = ( requestedCore ?? 'configured' ).replace(
	/[^a-z0-9.-]+/gi,
	'-',
);
const wpEnvHome = join(
	tmpdir(),
	`mime-simple-events-calendar-performance-wp-env-${ identifier }`,
);
const configDirectory = join(
	tmpdir(),
	`mime-simple-events-calendar-performance-config-${ identifier }`,
);
const configFilePath = join( configDirectory, '.wp-env.json' );
const pluginPath = resolvePath(
	projectDirectory,
	process.env.WPSE_PERFORMANCE_PLUGIN_PATH ??
		'.release/mime-simple-events-calendar',
);
const fixturePluginPath = join(
	projectDirectory,
	'tests/Performance/fixtures/wpse-performance-fixtures',
);
const wpEnvExecutable = join(
	projectDirectory,
	'node_modules/.bin/wp-env',
);
const localCoreDirectory =
	typeof requestedCore === 'string' && isAbsolute( requestedCore )
		? requestedCore
		: null;

/** Seed wp-env's current-version cache for deterministic offline runs. */
async function seedOfflineVersionCache() {
	const configuredVersion = baseConfiguration.core?.match(
		/#([0-9]+(?:\.[0-9]+)*)$/,
	)?.[ 1 ];

	if ( ! configuredVersion ) {
		return;
	}

	const configHash = createHash( 'md5' )
		.update( realpathSync( configFilePath ) )
		.digest( 'hex' )
		.slice( 0, 8 );
	const workDirectory = join(
		wpEnvHome,
		`wp-env-${ basename( configDirectory ) }-${ configHash }`,
	);

	await mkdir( workDirectory, { recursive: true } );
	await writeFile(
		join( workDirectory, 'wp-env-cache.json' ),
		`${ JSON.stringify( {
			latestWordPressVersion: configuredVersion,
		} ) }\n`,
		'utf8',
	);
}

/**
 * Run wp-env inside the isolated performance environment.
 *
 * @param {Array<string>} argumentsList        wp-env arguments.
 * @param {Object}        options              Failure/output behaviour.
 * @param {boolean}       options.allowFailure Accept a non-zero exit status.
 * @param {boolean}       options.silent       Suppress child output.
 * @return {Promise<void>}
 */
function runWpEnv(
	argumentsList,
	{ allowFailure = false, silent = false } = {},
) {
	return new Promise( ( resolve, reject ) => {
		const commandArguments = localCoreDirectory
			? [ ...argumentsList, '--config', configFilePath ]
			: argumentsList;
		const child = spawn( wpEnvExecutable, commandArguments, {
			cwd: localCoreDirectory ?? configDirectory,
			env: {
				...process.env,
				WP_ENV_HOME: wpEnvHome,
				...( requestedCore ? { WP_ENV_CORE: requestedCore } : {} ),
			},
			stdio: silent ? 'ignore' : 'inherit',
		} );

		child.once( 'error', reject );
		child.once( 'exit', ( code ) => {
			if ( code === 0 || allowFailure ) {
				resolve();
				return;
			}

			reject( new Error( `wp-env exited with code ${ code }.` ) );
		} );
	} );
}

/**
 * Fetch JSON with a bounded request timeout.
 *
 * @param {string}                 action     Fixture AJAX action.
 * @param {Record<string, string>} parameters Additional query parameters.
 * @return {Promise<Record<string, unknown>>} Parsed fixture response.
 */
async function fixtureRequest( action, parameters = {} ) {
	const url = new URL( 'http://localhost:8888/wp-admin/admin-ajax.php' );
	url.searchParams.set( 'action', action );

	for ( const [ key, value ] of Object.entries( parameters ) ) {
		url.searchParams.set( key, value );
	}

	const response = await fetch( url, {
		redirect: 'manual',
		signal: AbortSignal.timeout( 60_000 ),
	} );

	if ( ! response.ok ) {
		throw new Error( `Fixture request failed with HTTP ${ response.status }.` );
	}

	return response.json();
}

/** Populate the clean environment in bounded requests. */
async function seedFixturesWhenReady() {
	let lastOutcome = 'no response';

	for ( let attempt = 0; attempt < 120; attempt += 1 ) {
		try {
			const result = await fixtureRequest( 'wpse_performance_seed' );
			lastOutcome = `${ result.phase ?? 'unknown' } ${ result.current ?? 0 }/${ result.total ?? 0 }`;

			if ( result.complete === true ) {
				if (
					Number( result.occurrence_rows ) < 5400 ||
					Number( result.recurring_rows ) !== 5000 ||
					Number( result.sample_occurrence_rows ) !== 50 ||
					Number( result.sample_generation ) !== 2 ||
					Number( result.sample_coverage ) !== 2 ||
					Number( result.sample_index_dirty ) !== 0 ||
					Number( result.unfiltered_public_rows ) < 5000 ||
					Number( result.category_public_rows ) < 5000
				) {
					throw new Error(
						`Fixture health check failed: ${ JSON.stringify( result ) }`,
					);
				}

				return;
			}

			if ( result.phase === 'error' ) {
				throw new Error( `Fixture seeding stopped at ${ lastOutcome }.` );
			}
		} catch ( error ) {
			lastOutcome = error instanceof Error ? error.message : String( error );
		}

		await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
	}

	throw new Error(
		`Performance fixture did not become ready (${ lastOutcome }).`,
	);
}

/** Start one clean supported WordPress performance environment. */
export async function startPerformanceEnvironment() {
	const configuration = structuredClone( baseConfiguration );

	if ( requestedCore ) {
		configuration.core = requestedCore;
	}

	configuration.phpVersion = '8.2';
	configuration.plugins = [ pluginPath, fixturePluginPath ];

	await rm( configDirectory, { force: true, recursive: true } );
	await mkdir( configDirectory, { recursive: true } );
	await writeFile(
		configFilePath,
		`${ JSON.stringify( configuration, null, 2 ) }\n`,
		'utf8',
	);
	await runWpEnv( [ 'stop' ], { allowFailure: true, silent: true } );
	await runWpEnv( [ 'destroy', '--force' ], {
		allowFailure: true,
		silent: true,
	} );
	await rm( wpEnvHome, { force: true, recursive: true } );
	await seedOfflineVersionCache();
	await runWpEnv( [ 'start', '--runtime=playground' ] );
	await seedFixturesWhenReady();
}

/**
 * Measure one isolated PHP scenario.
 *
 * @param {string} scenario Allowlisted scenario identifier.
 * @return {Promise<Record<string, unknown>>} Parsed measurement response.
 */
export async function measurePerformanceScenario( scenario ) {
	return fixtureRequest( 'wpse_performance_measure', { scenario } );
}

/** Stop and destroy the isolated performance environment. */
export async function stopPerformanceEnvironment() {
	await runWpEnv( [ 'stop' ], { allowFailure: true, silent: true } );
	await rm( configDirectory, { force: true, recursive: true } );
	await rm( wpEnvHome, { force: true, recursive: true } );
}
