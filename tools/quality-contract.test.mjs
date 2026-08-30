import { strict as assert } from 'node:assert';
import { readFile, readdir } from 'node:fs/promises';
import { test } from 'node:test';

const phpstanConfig = await readFile(
	new URL( '../phpstan.neon.dist', import.meta.url ),
	'utf8',
);
const qualityWorkflow = await readFile(
	new URL( '../.github/workflows/quality.yml', import.meta.url ),
	'utf8',
);
const publicReadme = await readFile(
	new URL( '../readme.txt', import.meta.url ),
	'utf8',
);
const pluginBootstrap = await readFile(
	new URL( '../mime-simple-events-calendar.php', import.meta.url ),
	'utf8',
);
const composerManifest = JSON.parse(
	await readFile( new URL( '../composer.json', import.meta.url ), 'utf8' ),
);
const productSpecification = await readFile(
	new URL( '../docs/PRODUCT-SPECIFICATION.md', import.meta.url ),
	'utf8',
);
const smokeRunner = await readFile(
	new URL( './smoke-playground.mjs', import.meta.url ),
	'utf8',
);
const wpEnvConfig = await readFile(
	new URL( '../.wp-env.json', import.meta.url ),
	'utf8',
);
const releaseProcess = await readFile(
	new URL( '../docs/RELEASE-PROCESS.md', import.meta.url ),
	'utf8',
);
const releaseNotesTemplate = await readFile(
	new URL( '../docs/RELEASE-NOTES-TEMPLATE.md', import.meta.url ),
	'utf8',
);
const wordpressOrgAssets = new URL( '../.wordpress-org/', import.meta.url );

test( 'keeps non-PHP dependency trees optional for PHP-only CI jobs', () => {
	assert.match( phpstanConfig, /^\s*- node_modules \(\?\)$/m );
} );

test( 'enforces PHP 8.2 as one consistent, executable compatibility floor', () => {
	assert.equal( composerManifest.require.php, '>=8.2' );
	assert.equal( composerManifest.config.platform.php, '8.2.0' );
	assert.match( pluginBootstrap, /^ \* Requires PHP:\s+8\.2$/m );
	assert.match( publicReadme, /^Requires PHP: 8\.2$/m );
	assert.match( productSpecification, /^\| Minimum PHP \| 8\.2 \|$/m );
	assert.match( qualityWorkflow, /^\s+- '8\.2'$/m );
	assert.match(
		qualityWorkflow,
		/php-version: \$\{\{ matrix\.php \}\}/,
	);
	assert.match( qualityWorkflow, /WPSE_SMOKE_PHP: \$\{\{ matrix\.php \}\}/ );
	assert.match(
		smokeRunner,
		/configuration\.phpVersion = requestedPhp/,
	);
} );

test( 'pins every remote GitHub Action to an immutable commit', () => {
	const references = [
		...qualityWorkflow.matchAll( /^\s*- uses:\s+([^\s#]+)/gm ),
	].map( ( match ) => match[ 1 ] );

	assert.ok( references.length > 0, 'No GitHub Actions were found.' );

	for ( const reference of references ) {
		assert.match(
			reference,
			/@[a-f0-9]{40}$/,
			`Remote action is not commit-pinned: ${ reference }`,
		);
	}
} );

test( 'keeps external WordPress QA inputs deterministic and available', () => {
	assert.match(
		qualityWorkflow,
		/wordpress\/plugin-check-action@[a-f0-9]{40}[\s\S]*?wp-version: trunk/,
	);
	assert.match( qualityWorkflow, /^\s+- wordpress: '6\.9'$/m );
	assert.match( qualityWorkflow, /^\s+- wordpress: '7\.1'$/m );
	assert.match( publicReadme, /^Tested up to: 7\.1$/m );
	assert.match( wpEnvConfig, /"core": "WordPress\/WordPress#7\.1"/ );
	assert.match(
		qualityWorkflow,
		/https:\/\/github\.com\/wp-cli\/wp-cli\/releases\/download\/v2\.12\.0\/wp-cli-2\.12\.0\.phar/,
	);
	assert.match(
		qualityWorkflow,
		/ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c/,
	);
} );

test( 'keeps the WordPress.org image set complete and correctly sized', async () => {
	const expected = new Map( [
		[ 'banner-1544x500.png', [ 1544, 500 ] ],
		[ 'banner-772x250.png', [ 772, 250 ] ],
		[ 'icon-128x128.png', [ 128, 128 ] ],
		[ 'icon-256x256.png', [ 256, 256 ] ],
		[ 'screenshot-1.png', [ 1920, 557 ] ],
		[ 'screenshot-2.png', [ 1920, 624 ] ],
		[ 'screenshot-3.png', [ 1200, 1280 ] ],
		[ 'screenshot-4.png', [ 1200, 999 ] ],
		[ 'screenshot-5.png', [ 1440, 917 ] ],
		[ 'screenshot-6.png', [ 1600, 1012 ] ],
	] );
	const actualFiles = ( await readdir( wordpressOrgAssets ) ).sort();

	assert.deepEqual( actualFiles, [ ...expected.keys() ].sort() );

	for ( const [ file, [ width, height ] ] of expected ) {
		const image = await readFile( new URL( file, wordpressOrgAssets ) );

		assert.equal(
			image.subarray( 0, 8 ).toString( 'hex' ),
			'89504e470d0a1a0a',
			`${ file } is not a PNG file.`,
		);
		assert.equal( image.readUInt32BE( 16 ), width, `${ file } width` );
		assert.equal( image.readUInt32BE( 20 ), height, `${ file } height` );
	}
} );

test( 'keeps six screenshot captions synchronized with the image set', () => {
	const screenshotSection = publicReadme.match(
		/== Screenshots ==\n([\s\S]+?)\n== Changelog ==/,
	)?.[ 1 ];

	assert.ok( screenshotSection, 'The readme screenshot section is missing.' );
	assert.deepEqual(
		[ ...screenshotSection.matchAll( /^(\d+)\.\s+/gm ) ].map( ( match ) =>
			Number.parseInt( match[ 1 ], 10 ),
		),
		[ 1, 2, 3, 4, 5, 6 ],
	);
} );

test( 'keeps public GitHub release notes consistent and user-facing', () => {
	assert.match(
		releaseProcess,
		/`docs\/RELEASE-NOTES-TEMPLATE\.md`/,
	);
	assert.match(
		releaseNotesTemplate,
		/^## MiMe Simple Events and Calendar \{VERSION\}$/m,
	);
	assert.match( releaseNotesTemplate, /^### Highlights$/m );
	assert.match(
		releaseNotesTemplate,
		/^### Safety and compatibility$/m,
	);
	assert.match(
		releaseNotesTemplate,
		/See \[CHANGELOG\.md\]\(https:\/\/github\.com\/MilanMiMeOnline\/WP-Simple-Events\/blob\/v\{VERSION\}\/CHANGELOG\.md\)/,
	);
	assert.match(
		releaseNotesTemplate,
		/Do not publish test counts, CI job lists, commit hashes, checksums/,
	);
} );
