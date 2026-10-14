import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

const originalImport = "const SimpleGit = require( 'simple-git' );";
const patchedImport =
	"const { simpleGit: SimpleGit } = require( 'simple-git' );";
const sourceHashes = {
	'lib/download-sources.js':
		'673aa804243a76ca8ffc602b42a327ab552e69a883e0450eeffeecfa3f3399e8',
	'lib/runtime/docker/download-wp-phpunit.js':
		'bc117d85769fbfcc31e3fab62bef4669bbdcb2425c4e96f7aa2ea103e4467e83',
};

// ADR-099: wp-env still imports the removed v3 default export. Change only its
// import, preserving all v4 security guards. Unknown upstream code fails closed.
export function patchSource( source, expectedHash ) {
	const original = source.replace( patchedImport, originalImport );
	const actualHash = createHash( 'sha256' ).update( original ).digest( 'hex' );
	if ( actualHash !== expectedHash || ! original.includes( originalImport ) ) {
		throw new Error(
			'Unexpected wp-env source; review the compatibility patch.',
		);
	}
	return original.replace( originalImport, patchedImport );
}

export async function patchWpEnv( directory ) {
	const manifest = JSON.parse(
		await readFile( resolve( directory, 'package.json' ), 'utf8' ),
	);
	if ( manifest.version !== '11.11.0' ) {
		throw new Error(
			'Review the simple-git compatibility patch for this wp-env version.',
		);
	}
	const changes = [];
	for ( const [ relativePath, hash ] of Object.entries( sourceHashes ) ) {
		const path = resolve( directory, relativePath );
		const source = await readFile( path, 'utf8' );
		changes.push( [ path, patchSource( source, hash ) ] );
	}
	for ( const [ path, source ] of changes ) {
		await writeFile( path, source );
	}
}

if (
	process.argv[ 1 ] &&
	resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url )
) {
	await patchWpEnv(
		fileURLToPath(
			new URL( '../node_modules/@wordpress/env', import.meta.url ),
		),
	);
}
