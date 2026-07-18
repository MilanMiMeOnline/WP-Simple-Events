import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const checkedCatalogue = join(
	projectDirectory,
	'languages',
	'wp-simple-events.pot',
);
const checkMode = process.argv.includes( '--check' );
const wpCliExecutable = process.env.WP_CLI_BIN ?? 'wp';
const temporaryDirectory = checkMode
	? await mkdtemp( join( tmpdir(), 'wp-simple-events-i18n-' ) )
	: null;
const outputPath = temporaryDirectory
	? join( temporaryDirectory, 'wp-simple-events.pot' )
	: checkedCatalogue;

function generateCatalogue() {
	return new Promise( ( resolve, reject ) => {
		const child = spawn(
			wpCliExecutable,
			[
				'i18n',
				'make-pot',
				'.',
				outputPath,
				'--slug=wp-simple-events',
				'--domain=wp-simple-events',
				'--include=wp-simple-events.php,src,templates',
				'--headers={"POT-Creation-Date":""}',
				'--file-comment=Copyright (C) 2026 MiMe\nThis file is distributed under the GPLv2 or later.',
			],
			{
				cwd: projectDirectory,
				stdio: 'inherit',
			},
		);

		child.once( 'error', reject );
		child.once( 'exit', ( code ) => {
			if ( code === 0 ) {
				resolve();
				return;
			}

			reject( new Error( `WP-CLI exited with code ${ code }.` ) );
		} );
	} );
}

try {
	await mkdir( join( projectDirectory, 'languages' ), { recursive: true } );
	await generateCatalogue();

	if ( checkMode ) {
		const [ expected, generated ] = await Promise.all( [
			readFile( checkedCatalogue ),
			readFile( outputPath ),
		] );

		if ( ! expected.equals( generated ) ) {
			throw new Error(
				'The translation catalogue is stale. Run npm run i18n:pot.',
			);
		}
	}
} finally {
	if ( temporaryDirectory ) {
		await rm( temporaryDirectory, { force: true, recursive: true } );
	}
}

process.stdout.write(
	checkMode
		? 'The translation catalogue is current.\n'
		: `Updated ${ checkedCatalogue }.\n`,
);
