import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { mkdtemp, mkdir, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { test } from 'node:test';

const require = createRequire( import.meta.url );
const storageRequire = createRequire( require.resolve( '@wp-playground/storage/package.json' ) );
const gitPath = storageRequire.resolve( 'isomorphic-git' );
const git = storageRequire( 'isomorphic-git' );
const author = { name: 'Fixture', email: 'fixture@example.test', timestamp: 1000, timezoneOffset: 0 };

async function commitTree( dir, tree ) {
	return git.writeCommit( { fs, dir, commit: { tree, message: 'Fixture', parent: [], author, committer: author } } );
}

// GHSA-9qw7-j9xw-fv9c: exercise real filesystem writes, confined to a disposable fixture.
test( 'checkout refuses a symlinked parent and preserves files outside the worktree', async () => {
	const fixture = await mkdtemp( join( tmpdir(), 'wpse-git-symlink-' ) );
	const dir = join( fixture, 'repo' );
	const outside = join( fixture, 'outside' );
	try {
		await mkdir( dir );
		await mkdir( outside );
		await git.init( { fs, dir } );
		const commits = [];
		for ( const content of [ 'original', 'replacement' ] ) {
			const oid = await git.writeBlob( { fs, dir, blob: Buffer.from( content ) } );
			const subtree = await git.writeTree( { fs, dir, tree: [ { mode: '100644', path: 'event.txt', oid, type: 'blob' } ] } );
			const tree = await git.writeTree( { fs, dir, tree: [ { mode: '040000', path: 'events', oid: subtree, type: 'tree' } ] } );
			commits.push( await commitTree( dir, tree ) );
		}
		await git.checkout( { fs, dir, ref: commits[ 0 ], force: true } );
		assert.equal( await readFile( join( dir, 'events/event.txt' ), 'utf8' ), 'original' );
		await rm( join( dir, 'events' ), { recursive: true } );
		await writeFile( join( outside, 'event.txt' ), 'keep' );
		await symlink( outside, join( dir, 'events' ) );
		await assert.rejects( git.checkout( { fs, dir, ref: commits[ 1 ], force: true } ) );
		assert.equal( await readFile( join( outside, 'event.txt' ), 'utf8' ), 'keep' );
	} finally {
		await rm( fixture, { recursive: true, force: true } );
	}
} );

// GHSA-cw2g-2vwq-88w6: parse untrusted raw tree objects, not sanitized writer input.
test( 'Git tree decoding rejects traversal and reserved entry names', async () => {
	const dir = await mkdtemp( join( tmpdir(), 'wpse-git-tree-' ) );
	try {
		await git.init( { fs, dir } );
		const blob = await git.writeBlob( { fs, dir, blob: Buffer.from( 'fixture' ) } );
		for ( const name of [ '.', '..', '.git', '.GIT ', 'git~1', '.g\u200cit' ] ) {
			const object = Buffer.concat( [ Buffer.from( `100644 ${ name }\0` ), Buffer.from( blob, 'hex' ) ] );
			const oid = await git.writeObject( { fs, dir, type: 'tree', format: 'content', object } );
			await assert.rejects( git.readTree( { fs, dir, oid } ), /unsafe/i );
		}
	} finally {
		await rm( dir, { recursive: true, force: true } );
	}
} );

// GHSA-96wc-2m84-2cj7: a child timeout makes a regressed endless lookup fail safely.
test( 'circular symbolic refs terminate while normal refs still resolve', async () => {
	const dir = await mkdtemp( join( tmpdir(), 'wpse-git-refs-' ) );
	try {
		await git.init( { fs, dir } );
		await writeFile( join( dir, '.git/refs/heads/a' ), 'ref: refs/heads/b\n' );
		await writeFile( join( dir, '.git/refs/heads/b' ), 'ref: refs/heads/a\n' );
		await writeFile( join( dir, '.git/refs/heads/safe' ), 'a'.repeat( 40 ) + '\n' );
		execFileSync( process.execPath, [ '-e', `
			const assert = require('node:assert/strict');
			const git = require(process.argv[1]);
			const fs = require('node:fs');
			const dir = process.argv[2];
			(async () => {
				assert.equal(await git.resolveRef({fs, dir, ref: 'safe'}), 'a'.repeat(40));
				await assert.rejects(git.resolveRef({fs, dir, ref: 'a'}), /circular/i);
			})();
		`, gitPath, dir ], { timeout: 5000, stdio: 'pipe' } );
	} finally {
		await rm( dir, { recursive: true, force: true } );
	}
} );

// GHSA-474x-hrwr-fgx4: bound malformed stop-node processing and retain valid XML.
test( 'XML stop nodes reject unterminated tags without quadratic scanning', () => {
	const consumer = createRequire( require.resolve( '@php-wasm/cli-util/package.json' ) );
	execFileSync( process.execPath, [ '-e', `
		const assert = require('node:assert/strict');
		const {XMLParser} = require(process.argv[1]);
		const parser = new XMLParser({stopNodes: ['stop']});
		assert.deepEqual(parser.parse('<event><title>Open day</title></event>'), {event: {title: 'Open day'}});
		assert.throws(() => parser.parse('<stop>' + '<a '.repeat(200000)));
	`, consumer.resolve( 'fast-xml-parser' ) ], { timeout: 5000, stdio: 'pipe' } );
} );

// The Playground parser is bundled separately; a clean npm audit does not test it.
test( 'both embedded Playground tree parsers reject unsafe paths and retain normal entries', async () => {
	const directory = dirname( require.resolve( '@wp-playground/storage/package.json' ) );
	const esm = await import( pathToFileURL( join( directory, 'isomorphic-git-internals-DwkB8rfP.js' ) ) );
	const cjs = require( join( directory, 'isomorphic-git-internals-oTG5Z0SU.cjs' ) );
	const rawTree = ( name ) => Buffer.concat( [ Buffer.from( `100644 ${ name }\0` ), Buffer.alloc( 20, 1 ) ] );
	for ( const parser of [ esm.d, cjs.GitTree ] ) {
		assert.equal( parser.from( rawTree( 'event.txt' ) ).entries()[ 0 ].path, 'event.txt' );
		for ( const name of [ '.', '..', '.git', '.GIT ', 'git~1', '.g\u200cit', 'a/b', 'a\\b' ] ) {
			assert.throws( () => parser.from( rawTree( name ) ), /unsafe/i );
		}
	}
} );
