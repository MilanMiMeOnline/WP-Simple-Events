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
const wordpressOrgAssets = new URL( '../.wordpress-org/', import.meta.url );

test( 'keeps non-PHP dependency trees optional for PHP-only CI jobs', () => {
	assert.match( phpstanConfig, /^\s*- node_modules \(\?\)$/m );
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
		[ 'screenshot-6.png', [ 295, 1000 ] ],
		[ 'screenshot-7.png', [ 1600, 1012 ] ],
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

test( 'keeps seven screenshot captions synchronized with the image set', () => {
	const screenshotSection = publicReadme.match(
		/== Screenshots ==\n([\s\S]+?)\n== Changelog ==/,
	)?.[ 1 ];

	assert.ok( screenshotSection, 'The readme screenshot section is missing.' );
	assert.deepEqual(
		[ ...screenshotSection.matchAll( /^(\d+)\.\s+/gm ) ].map( ( match ) =>
			Number.parseInt( match[ 1 ], 10 ),
		),
		[ 1, 2, 3, 4, 5, 6, 7 ],
	);
} );
