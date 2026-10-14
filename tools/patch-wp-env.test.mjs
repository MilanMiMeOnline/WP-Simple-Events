import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { patchSource } from './patch-wp-env.mjs';

const require = createRequire( import.meta.url );

test( 'the wp-env Git dependency still rejects external configuration includes', async () => {
	const wpEnvRequire = createRequire(
		new URL( '../node_modules/@wordpress/env/package.json', import.meta.url ),
	);
	const { simpleGit } = wpEnvRequire( 'simple-git' );
	await assert.rejects(
		simpleGit().raw( [ '-c', 'include.path=/nonexistent/mime-test', 'status' ] ),
		/Configuring include.path is not permitted/,
	);
} );

test( 'wp-env patch is repeatable and refuses unexpected upstream changes', () => {
	const source = "const SimpleGit = require( 'simple-git' );\n";
	const hash = createHash( 'sha256' ).update( source ).digest( 'hex' );
	const patched = patchSource( source, hash );
	assert.equal( patchSource( patched, hash ), patched );
	assert.throws(
		() => patchSource( `${ source }// changed`, hash ),
		/Unexpected wp-env/,
	);
} );

test( 'wp-env can clone, fetch and check out a source with patched simple-git', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'mime-git-compat-' ) );
	try {
		const source = join( directory, 'source' );
		const clone = join( directory, 'clone' );
		const git = ( ...args ) =>
			execFileSync(
				'git',
				[
					'-c',
					'core.hooksPath=/dev/null',
					'-c',
					'commit.gpgsign=false',
					...args,
				],
				{ stdio: 'pipe' },
			);
		git( 'init', '--initial-branch=main', source );
		await writeFile( join( source, 'fixture.txt' ), 'first' );
		git( '-C', source, 'add', 'fixture.txt' );
		git(
			'-C',
			source,
			'-c',
			'user.name=Test',
			'-c',
			'user.email=test@example.invalid',
			'commit',
			'-m',
			'fixture',
		);
		const {
			downloadGitSource,
		} = require( '../node_modules/@wordpress/env/lib/download-sources.js' );
		const options = { onProgress() {}, debug: false };
		await downloadGitSource(
			{ url: source, clonePath: clone, ref: 'main' },
			options,
		);
		assert.equal(
			await readFile( join( clone, 'fixture.txt' ), 'utf8' ),
			'first',
		);
		git( '-C', source, 'tag', 'fixture-tag' );
		await downloadGitSource(
			{ url: source, clonePath: clone, ref: 'fixture-tag' },
			options,
		);
		assert.equal(
			git( '-C', clone, 'describe', '--tags', '--exact-match' )
				.toString()
				.trim(),
			'fixture-tag',
		);
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );
