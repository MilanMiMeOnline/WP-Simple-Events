import { strict as assert } from 'node:assert';
import { createHash } from 'node:crypto';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { patchStorage, patchStorageSource } from './patch-playground-storage.mjs';

test( 'storage patch is repeatable and refuses unexpected source', () => {
	const source = 'const h = "event"; accept(h);';
	const target = { hash: createHash( 'sha256' ).update( source ).digest( 'hex' ), needle: 'accept(h);' };
	const patched = patchStorageSource( source, target );
	assert.equal( patchStorageSource( patched, target ), patched );
	assert.throws( () => patchStorageSource( source + '// changed', target ), /Unexpected Playground/ );
	assert.throws( () => patchStorageSource( patched.replace( "h.includes('/')", 'false' ), target ), /Unexpected Playground/ );
} );

test( 'storage patch rejects an unknown release before touching its files', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'wpse-storage-version-' ) );
	try {
		await writeFile( join( directory, 'package.json' ), '{"version":"3.1.45"}' );
		await assert.rejects( patchStorage( directory ), /Review the Git tree guard/ );
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );

test( 'storage patch validates both module formats before writing either', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'wpse-storage-validation-' ) );
	const file = 'isomorphic-git-internals-DwkB8rfP.js';
	const installed = await readFile( new URL( `../node_modules/@wp-playground/storage/${ file }`, import.meta.url ), 'utf8' );
	// Restore the reviewed original in this fixture so any partial patch would be visible.
	const original = installed.replace( /\/\* wpse-storage-path-guard \*\/[\s\S]*?\/\* wpse-storage-path-guard-end \*\/\n/, '' );
	try {
		await writeFile( join( directory, 'package.json' ), '{"version":"3.1.44"}' );
		await writeFile( join( directory, file ), original );
		await writeFile( join( directory, 'isomorphic-git-internals-oTG5Z0SU.cjs' ), 'changed' );
		await assert.rejects( patchStorage( directory ), /Unexpected Playground/ );
		assert.equal( await readFile( join( directory, file ), 'utf8' ), original );
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );
