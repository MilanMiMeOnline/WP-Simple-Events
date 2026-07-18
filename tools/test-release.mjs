import { spawn } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { getReleaseVersion, PLUGIN_SLUG } from './release-contract.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );

function run( script ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( process.execPath, [ script ], {
			cwd: projectDirectory,
			stdio: 'inherit',
		} );

		child.once( 'error', reject );
		child.once( 'exit', ( code ) => {
			if ( code === 0 ) {
				resolve();
				return;
			}

			reject( new Error( `${ script } failed with exit code ${ code }.` ) );
		} );
	} );
}

const [ packageSource, pluginSource, readmeSource ] = await Promise.all( [
	readFile( join( projectDirectory, 'package.json' ), 'utf8' ),
	readFile( join( projectDirectory, 'wp-simple-events.php' ), 'utf8' ),
	readFile( join( projectDirectory, 'readme.txt' ), 'utf8' ),
] );
const version = getReleaseVersion( {
	packageSource,
	pluginSource,
	readmeSource,
} );
const checksumPath = join(
	projectDirectory,
	'dist',
	`${ PLUGIN_SLUG }-${ version }.zip.sha256`,
);

await run( 'tools/build-release.mjs' );
await run( 'tools/verify-release.mjs' );
const firstChecksum = await readFile( checksumPath, 'utf8' );

await run( 'tools/build-release.mjs' );
await run( 'tools/verify-release.mjs' );
const secondChecksum = await readFile( checksumPath, 'utf8' );

if ( firstChecksum !== secondChecksum ) {
	throw new Error( 'Two consecutive release builds produced different checksums.' );
}

process.stdout.write(
	'Two consecutive release builds are byte-for-byte reproducible.\n',
);
