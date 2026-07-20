import { createHash } from 'node:crypto';
import { spawn } from 'node:child_process';
import {
	lstat,
	mkdtemp,
	readdir,
	readFile,
	realpath,
	rm,
} from 'node:fs/promises';
import { basename, join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

import {
	assertReleaseIdentity,
	assertReleaseEntries,
	getReleaseVersion,
	parseChecksumRecord,
	PLUGIN_SLUG,
} from './release-contract.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );

function run( command, argumentsList, { capture = false, cwd } = {} ) {
	return new Promise( ( resolve, reject ) => {
		let output = '';
		const child = spawn( command, argumentsList, {
			cwd: cwd ?? projectDirectory,
			env: { ...process.env, TZ: 'UTC' },
			stdio: capture ? [ 'ignore', 'pipe', 'pipe' ] : 'inherit',
		} );

		if ( capture ) {
			child.stdout.setEncoding( 'utf8' );
			child.stderr.setEncoding( 'utf8' );
			child.stdout.on( 'data', ( chunk ) => {
				output += chunk;
			} );
			child.stderr.on( 'data', ( chunk ) => {
				output += chunk;
			} );
		}

		child.once( 'error', reject );
		child.once( 'exit', ( code, signal ) => {
			if ( code === 0 ) {
				resolve( output );
				return;
			}

			reject(
				new Error(
					`${ command } failed with ${
						signal ? `signal ${ signal }` : `exit code ${ code }`
					}${ output ? `:\n${ output }` : '.' }`,
				),
			);
		} );
	} );
}

async function releaseVersion() {
	const [ packageSource, pluginSource, readmeSource ] = await Promise.all( [
		readFile( join( projectDirectory, 'package.json' ), 'utf8' ),
		readFile( join( projectDirectory, 'simple-events-by-mime.php' ), 'utf8' ),
		readFile( join( projectDirectory, 'readme.txt' ), 'utf8' ),
	] );
	assertReleaseIdentity( { pluginSource, readmeSource } );

	return getReleaseVersion( { packageSource, pluginSource, readmeSource } );
}

async function inspectExtractedTree( directory ) {
	const phpFiles = [];

	for ( const child of await readdir( directory ) ) {
		const childPath = join( directory, child );
		const childStat = await lstat( childPath );

		if ( childStat.isSymbolicLink() ) {
			throw new Error( `Release contains symbolic link: ${ childPath }` );
		}

		if ( childStat.isDirectory() ) {
			phpFiles.push( ...( await inspectExtractedTree( childPath ) ) );
		} else if ( childStat.isFile() && child.endsWith( '.php' ) ) {
			phpFiles.push( childPath );
		} else if ( ! childStat.isFile() ) {
			throw new Error( `Release contains unsupported entry: ${ childPath }` );
		}
	}

	return phpFiles;
}

const version = await releaseVersion();
const archivePath = join(
	projectDirectory,
	'dist',
	`${ PLUGIN_SLUG }-${ version }.zip`,
);
const checksumSource = await readFile( `${ archivePath }.sha256`, 'utf8' );
const expectedChecksum = parseChecksumRecord(
	checksumSource,
	basename( archivePath ),
);
const actualChecksum = createHash( 'sha256' )
	.update( await readFile( archivePath ) )
	.digest( 'hex' );

if ( expectedChecksum !== actualChecksum ) {
	throw new Error( 'The release archive does not match its SHA-256 checksum.' );
}

const archiveEntries = ( await run( 'unzip', [ '-Z1', archivePath ], {
	capture: true,
} ) )
	.trim()
	.split( '\n' )
	.filter( Boolean );

assertReleaseEntries( archiveEntries );

const archiveDetails = await run( 'zipinfo', [ '-l', archivePath ], {
	capture: true,
} );

if ( /^l[^\n]*\s+simple-events-by-mime\//m.test( archiveDetails ) ) {
	throw new Error( 'The release archive contains a symbolic link.' );
}

const temporaryDirectory = await mkdtemp(
	join( tmpdir(), 'simple-events-by-mime-release-' ),
);

try {
	await run( 'unzip', [ '-q', archivePath, '-d', temporaryDirectory ] );

	const canonicalTemporaryDirectory = await realpath( temporaryDirectory );
	const extractedPlugin = await realpath(
		join( temporaryDirectory, PLUGIN_SLUG ),
	);
	const expectedPluginRoot = join(
		canonicalTemporaryDirectory,
		PLUGIN_SLUG,
	);

	if ( extractedPlugin !== expectedPluginRoot ) {
		throw new Error( 'The extracted plugin root escaped its temporary directory.' );
	}

	const phpFiles = await inspectExtractedTree( extractedPlugin );

	for ( const phpFile of phpFiles ) {
		await run( 'php', [ '-l', phpFile ], { capture: true } );
	}

	await run(
		'php',
		[
			'-r',
			`require $argv[1]; exit(class_exists('MiMe\\\\WPSimpleEvents\\\\Plugin') ? 0 : 1);`,
			join( extractedPlugin, 'vendor', 'autoload.php' ),
		],
		{ capture: true },
	);
} finally {
	await rm( temporaryDirectory, { force: true, recursive: true } );
}

process.stdout.write(
	`Verified ${ basename( archivePath ) } (${ actualChecksum }).\n`,
);
