import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { test } from 'node:test';

const require = createRequire( import.meta.url );
const micromatchRequire = createRequire( require.resolve( 'micromatch' ) );
const bracesPath = micromatchRequire.resolve( 'braces' );
const braces = micromatchRequire( 'braces' );

test( 'brace matching retains nested sets, padded ranges and escaped literals', () => {
	assert.deepEqual( braces.expand( 'src/{css,{js,ts}}/*.{01..03..2}' ), [
		'src/css/*.01', 'src/css/*.03',
		'src/js/*.01', 'src/js/*.03',
		'src/ts/*.01', 'src/ts/*.03',
	] );
	assert.deepEqual( braces.expand( 'a{b\\,c,d}e' ), [ 'ab,ce', 'ade' ] );
	assert.deepEqual( braces.expand( 'a\\{b,c\\}d' ), [ 'a{b,c}d' ] );
	assert.equal( braces.compile( 'src/{css,js}/*.txt' ), 'src/(css|js)/*.txt' );
	const pattern = 'a/{b,c}/d';
	assert.equal( braces.stringify( braces.parse( pattern ) ), pattern );
} );

// Run malicious inputs in a bounded child: a regressed infinite traversal must
// fail this test without hanging the rest of the test suite.
for ( const method of [ 'parse', 'compile', 'expand', 'stringify' ] ) {
	test( `braces ${ method } rejects excessive string nesting before stack exhaustion`, () => {
		execFileSync( process.execPath, [ '-e', `
			const assert = require('node:assert/strict');
			const braces = require(process.argv[1]);
			const method = process.argv[2];
			for (const [open, close] of [['{', '}'], ['(', ')'], ['({', '})']]) {
				const pattern = open.repeat(2000) + 'a' + close.repeat(2000);
				for (const options of [{}, {maxDepth: Infinity}, {maxDepth: 1000000}, {maxDepth: NaN}]) {
					assert.throws(() => braces[method](pattern, options), /exceeds max depth/);
				}
			}
		`, bracesPath, method ], { timeout: 5000, stdio: 'pipe' } );
	} );
}

for ( const method of [ 'compile', 'expand', 'stringify' ] ) {
	test( `braces ${ method } bounds caller-supplied deep and cyclic syntax trees`, () => {
		execFileSync( process.execPath, [ '-e', `
			const assert = require('node:assert/strict');
			const braces = require(process.argv[1]);
			const method = process.argv[2];
			const root = {type: 'root', nodes: []};
			let parent = root;
			for (let i = 0; i < 2000; i++) {
				const child = {type: 'paren', nodes: [], parent};
				parent.nodes.push(child);
				parent = child;
			}
			assert.throws(() => braces[method](root), /exceeds max depth/);
			const cycle = {type: 'root', nodes: []};
			cycle.nodes.push(cycle);
			assert.throws(() => braces[method](cycle), /exceeds max depth/);
		`, bracesPath, method ], { timeout: 5000, stdio: 'pipe' } );
	} );
}

test( 'brace parser enforces the configured depth boundary', () => {
	assert.doesNotThrow( () => braces.parse( '{'.repeat( 100 ) + 'a' + '}'.repeat( 100 ) ) );
	assert.throws( () => braces.parse( '{'.repeat( 101 ) + 'a' + '}'.repeat( 101 ) ), /exceeds max depth/ );
	assert.doesNotThrow( () => braces.parse( '{a}', { maxDepth: 1.5 } ) );
	assert.throws( () => braces.parse( '{{a}}', { maxDepth: 1.5 } ), /exceeds max depth/ );
	assert.throws( () => braces.parse( '{a}', { maxDepth: 0 } ), /exceeds max depth/ );
} );

test( 'fast-glob keeps selecting the intended lint files through the replacement', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'mime-glob-compat-' ) );
	try {
		for ( const file of [ 'css/main.css', 'css/nested/detail.css', 'js/main.js', 'css/ignored.css', 'css/not-css.txt' ] ) {
			await mkdir( dirname( join( directory, file ) ), { recursive: true } );
			await writeFile( join( directory, file ), '' );
		}
		const glob = require( 'fast-glob' );
		const selected = await glob( [ '{css,js}/**/*.{css,js}', '!**/ignored.css' ], { cwd: directory } );
		assert.deepEqual( selected.sort(), [ 'css/main.css', 'css/nested/detail.css', 'js/main.js' ] );
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );
