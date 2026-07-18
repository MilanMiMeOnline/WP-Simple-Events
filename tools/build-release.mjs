import { createHash } from 'node:crypto';
import { spawn } from 'node:child_process';
import {
	chmod,
	copyFile,
	lstat,
	mkdir,
	readdir,
	readFile,
	rm,
	utimes,
	writeFile,
} from 'node:fs/promises';
import { basename, dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
	assertReleaseEntries,
	getReleaseVersion,
	PLUGIN_SLUG,
} from './release-contract.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const releaseDirectory = join( projectDirectory, '.release' );
const pluginDirectory = join( releaseDirectory, PLUGIN_SLUG );
const distributionDirectory = join( projectDirectory, 'dist' );
const stableTimestamp = new Date( '2000-01-01T00:00:00.000Z' );

const releaseEntries = [
	'CHANGELOG.md',
	'README.md',
	'SECURITY.md',
	'THIRD-PARTY-NOTICES.md',
	'readme.txt',
	'uninstall.php',
	'wp-simple-events.php',
	'src',
	'templates',
	'languages',
	'assets/src/css',
	'assets/src/js/admin-event.js',
	'assets/dist/js',
];

function run( command, argumentsList, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, argumentsList, {
			cwd: options.cwd ?? projectDirectory,
			env: { ...process.env, TZ: 'UTC', ...options.env },
			stdio: 'inherit',
		} );

		child.once( 'error', reject );
		child.once( 'exit', ( code, signal ) => {
			if ( code === 0 ) {
				resolve();
				return;
			}

			reject(
				new Error(
					`${ command } failed with ${
						signal ? `signal ${ signal }` : `exit code ${ code }`
					}.`,
				),
			);
		} );
	} );
}

async function copyProductionPath( source, destination ) {
	const sourceStat = await lstat( source );

	if ( sourceStat.isSymbolicLink() ) {
		throw new Error( `Release input may not be a symbolic link: ${ source }` );
	}

	if ( sourceStat.isDirectory() ) {
		await mkdir( destination, { recursive: true } );

		for ( const child of await readdir( source ) ) {
			if ( child === '.DS_Store' ) {
				continue;
			}

			await copyProductionPath(
				join( source, child ),
				join( destination, child ),
			);
		}

		return;
	}

	if ( ! sourceStat.isFile() ) {
		throw new Error( `Unsupported release input: ${ source }` );
	}

	await mkdir( dirname( destination ), { recursive: true } );
	await copyFile( source, destination );
}

async function listFiles( directory ) {
	const files = [];

	for ( const child of await readdir( directory ) ) {
		const childPath = join( directory, child );
		const childStat = await lstat( childPath );

		if ( childStat.isSymbolicLink() ) {
			throw new Error( `Staged release may not contain a symbolic link: ${ childPath }` );
		}

		if ( childStat.isDirectory() ) {
			files.push( ...( await listFiles( childPath ) ) );
		} else if ( childStat.isFile() ) {
			files.push( childPath );
		} else {
			throw new Error( `Unsupported staged release entry: ${ childPath }` );
		}
	}

	return files;
}

async function releaseVersion() {
	const [ packageSource, pluginSource, readmeSource ] = await Promise.all( [
		readFile( join( projectDirectory, 'package.json' ), 'utf8' ),
		readFile( join( projectDirectory, 'wp-simple-events.php' ), 'utf8' ),
		readFile( join( projectDirectory, 'readme.txt' ), 'utf8' ),
	] );

	return getReleaseVersion( { packageSource, pluginSource, readmeSource } );
}

const version = await releaseVersion();
const archiveName = `${ PLUGIN_SLUG }-${ version }.zip`;
const archivePath = join( distributionDirectory, archiveName );

await run( 'npm', [ 'run', 'build:calendar' ] );
await rm( releaseDirectory, { force: true, recursive: true } );
await rm( distributionDirectory, { force: true, recursive: true } );
await mkdir( pluginDirectory, { recursive: true } );
await mkdir( distributionDirectory, { recursive: true } );

for ( const entry of releaseEntries ) {
	await copyProductionPath(
		join( projectDirectory, entry ),
		join( pluginDirectory, entry ),
	);
}

await Promise.all(
	[ 'composer.json', 'composer.lock' ].map( ( file ) =>
		copyFile( join( projectDirectory, file ), join( pluginDirectory, file ) ),
	),
);

await run(
	'composer',
	[
		'dump-autoload',
		'--working-dir',
		pluginDirectory,
		'--no-dev',
		'--no-interaction',
		'--optimize',
		'--classmap-authoritative',
		'--no-scripts',
		'--no-plugins',
	],
	{
		env: {
			COMPOSER_ALLOW_SUPERUSER: '1',
			COMPOSER_DISABLE_NETWORK: '1',
			COMPOSER_ROOT_VERSION: version,
		},
	},
);

await Promise.all(
	[ 'composer.json', 'composer.lock' ].map( ( file ) =>
		rm( join( pluginDirectory, file ), { force: true } ),
	),
);

const stagedFiles = ( await listFiles( pluginDirectory ) ).sort();
const archiveEntries = stagedFiles.map( ( file ) =>
	relative( releaseDirectory, file ).split( sep ).join( '/' ),
);

assertReleaseEntries( archiveEntries );

for ( const file of stagedFiles ) {
	await chmod( file, 0o644 );
	await utimes( file, stableTimestamp, stableTimestamp );
}

await run( 'zip', [ '-X', '-0', '-q', archivePath, ...archiveEntries ], {
	cwd: releaseDirectory,
} );

const checksum = createHash( 'sha256' )
	.update( await readFile( archivePath ) )
	.digest( 'hex' );
const checksumPath = `${ archivePath }.sha256`;

await writeFile(
	checksumPath,
	`${ checksum }  ${ basename( archivePath ) }\n`,
	'utf8',
);

process.stdout.write( `Release archive: ${ archivePath }\n` );
process.stdout.write( `SHA-256: ${ checksum }\n` );
