import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

const require = createRequire( import.meta.url );
const rimrafRequire = createRequire( require.resolve( 'rimraf' ) );
const glob = rimrafRequire( 'glob' );

// GHSA-6pq8-jhm2-2vcw: comma serialization must not crash on untrusted coercion.
test( 'comma query serialization handles objects with a non-callable toString', () => {
	const qs = require( 'qs' );
	for ( const encodeValuesOnly of [ false, true ] ) {
		assert.equal(
			qs.stringify( { a: [ { toString: 'x' } ] }, { arrayFormat: 'comma', encodeValuesOnly } ),
			'a=%5Bobject%20Object%5D',
		);
		assert.equal( qs.stringify( { a: [ 'one', 'two' ] }, { arrayFormat: 'comma' } ), 'a=one%2Ctwo' );
	}
} );

// GHSA-g3hv-75wg-m249: bound optimization through each real modern consumer.
for ( const consumer of [ 'glob', '@typescript-eslint/typescript-estree' ] ) {
	test( `${ consumer } bounds optimized glob expansion and retains normal matching`, () => {
		const consumerRequire = createRequire( require.resolve( consumer ) );
		execFileSync( process.execPath, [ '-e', `
			const assert = require('node:assert/strict');
			const {Minimatch} = require(process.argv[1]);
			const pattern = Array(12).fill('**/../a/b').join('/');
			const matcher = new Minimatch(pattern, {optimizationLevel: 2, braceExpandMax: 64});
			assert.ok(matcher.set.length <= 64);
			const normal = new Minimatch('**/../a/b/**/../c/d', {optimizationLevel: 2});
			assert.equal(normal.match('../a/b/../c/d'), true);
			assert.equal(normal.match('x/a/b/y/c/d'), true);
			assert.equal(normal.match('x/a/b/y/c/no'), false);
		`, consumerRequire.resolve( 'minimatch' ) ], { timeout: 3000, stdio: 'pipe' } );
	} );
}

test( 'legacy lint matching retains its callable API without optimized amplification', () => {
	const legacy = createRequire( require.resolve( 'eslint' ) )( 'minimatch' );
	assert.equal( legacy( 'src/event.js', 'src/*.js' ), true );
	assert.equal( legacy( 'src/event.php', 'src/*.js' ), false );
	const matcher = new legacy.Minimatch( Array( 12 ).fill( '**/../a/b' ).join( '/' ), { optimizationLevel: 2 } );
	assert.equal( matcher.set.length, 1 );
} );

// GHSA-3g3m-2fjr-6pw4: cancellation must stop filesystem work, not just reject.
test( 'aborted glob traversal stops reading directories', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'wpse-glob-abort-' ) );
	try {
		for ( let index = 0; index < 12; index++ ) {
			const child = join( directory, String( index ), 'nested' );
			await mkdir( child, { recursive: true } );
			await writeFile( join( child, 'event.txt' ), 'fixture' );
		}
		const controller = new AbortController();
		const reason = new Error( 'Fixture cancellation' );
		let reads = 0;
		const countedFs = {
			...fs,
			readdir: ( ...args ) => {
				reads++;
				if ( reads === 1 ) {
					controller.abort( reason );
				}
				return fs.readdir( ...args );
			},
		};
		await assert.rejects( glob.glob( '**', { cwd: directory, fs: countedFs, signal: controller.signal } ), reason );
		await new Promise( ( resolve ) => setTimeout( resolve, 100 ) );
		assert.equal( reads, 1, 'An aborted walk must not visit child directories.' );
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );

for ( const method of [ 'rimraf', 'rimrafSync' ] ) {
	test( `${ method } cleanup preserves files outside the selected fixture pattern`, async () => {
		const directory = await mkdtemp( join( tmpdir(), 'wpse-rimraf-selection-' ) );
		try {
			await writeFile( join( directory, 'remove.tmp' ), 'remove' );
			await writeFile( join( directory, 'keep.txt' ), 'keep' );
			await require( 'rimraf' )[ method ]( '*.tmp', { glob: { cwd: directory } } );
			assert.equal( fs.existsSync( join( directory, 'remove.tmp' ) ), false );
			assert.equal( fs.readFileSync( join( directory, 'keep.txt' ), 'utf8' ), 'keep' );
		} finally {
			await rm( directory, { recursive: true, force: true } );
		}
	} );
}
