import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
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
const requestedCore = process.env.WPSE_E2E_CORE ?? baseConfiguration.core;
const identifier = ( requestedCore ?? 'configured' ).replace(
	/[^a-z0-9.-]+/gi,
	'-',
);
const wpEnvHome = join( tmpdir(), `simple-events-by-mime-e2e-wp-env-${ identifier }` );
const configDirectory = join(
	tmpdir(),
	`simple-events-by-mime-e2e-config-${ identifier }`,
);
const pluginPath = resolvePath(
	projectDirectory,
	process.env.WPSE_E2E_PLUGIN_PATH ?? '.release/simple-events-by-mime',
);
const fixturePluginPath = join(
	projectDirectory,
	'tests/E2E/fixtures/wpse-e2e-fixtures',
);
const wpEnvExecutable = join( projectDirectory, 'node_modules/.bin/wp-env' );
const localCoreDirectory =
	typeof requestedCore === 'string' && isAbsolute( requestedCore )
		? requestedCore
		: null;
const configFilePath = join( configDirectory, '.wp-env.json' );

/**
 * Seed the wp-env current-version cache for deterministic offline test runs.
 * wp-env parses its empty defaults before applying our explicit core override.
 */
async function seedOfflineVersionCache() {
	const configuredVersion = baseConfiguration.core?.match(
		/#([0-9]+(?:\.[0-9]+)*)$/,
	)?.[ 1 ];

	if ( ! configuredVersion ) {
		return;
	}

	const configHash = createHash( 'md5' )
		.update( configFilePath )
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
 * Run wp-env in the isolated browser-test environment.
 *
 * @param {Array<string>} argumentsList        wp-env arguments.
 * @param {Object}        options              Failure and output behaviour.
 * @param {boolean}       options.allowFailure Accept a non-zero exit status.
 * @param {boolean}       options.silent       Suppress child-process output.
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

/** Prepare and start a clean WordPress browser-test site. */
export async function startE2EEnvironment() {
	const configuration = structuredClone( baseConfiguration );

	if ( requestedCore ) {
		configuration.core = requestedCore;
	}

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
}

/** Stop and remove the isolated browser-test site. */
export async function stopE2EEnvironment() {
	await runWpEnv( [ 'stop' ], { allowFailure: true, silent: true } );
	await rm( configDirectory, { force: true, recursive: true } );
	await rm( wpEnvHome, { force: true, recursive: true } );
}
