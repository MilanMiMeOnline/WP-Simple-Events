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
const repositoryReadme = await readFile(
	new URL( '../README.md', import.meta.url ),
	'utf8',
);
const userGuide = await readFile(
	new URL( '../docs/USER-GUIDE.md', import.meta.url ),
	'utf8',
);
const gettingStartedGuide = await readFile(
	new URL( '../docs/GETTING-STARTED.md', import.meta.url ),
	'utf8',
);
const recurrenceGuide = await readFile(
	new URL( '../docs/RECURRING-EVENTS.md', import.meta.url ),
	'utf8',
);
const displayGuide = await readFile(
	new URL( '../docs/DISPLAYING-EVENTS.md', import.meta.url ),
	'utf8',
);
const builderGuide = await readFile(
	new URL( '../docs/BUILDERS-AND-TEMPLATES.md', import.meta.url ),
	'utf8',
);
const troubleshootingGuide = await readFile(
	new URL( '../docs/TROUBLESHOOTING.md', import.meta.url ),
	'utf8',
);
const privacyAndUpdatesGuide = await readFile(
	new URL( '../docs/PRIVACY-DATA-AND-UPDATES.md', import.meta.url ),
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
const packageManifest = JSON.parse(
	await readFile( new URL( '../package.json', import.meta.url ), 'utf8' ),
);
const performanceBudgets = await readFile(
	new URL( '../docs/PERFORMANCE-BUDGETS.md', import.meta.url ),
	'utf8',
);
const performanceRunner = await readFile(
	new URL( './performance-playground.mjs', import.meta.url ),
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

test( 'uses maintained Node 24 GitHub Actions without floating references', () => {
	assert.doesNotMatch( qualityWorkflow, /node-version:\s*20/ );
	assert.match(
		qualityWorkflow,
		/actions\/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6\.0\.2/,
	);
	assert.match(
		qualityWorkflow,
		/actions\/setup-node@249970729cb0ef3589644e2896645e5dc5ba9c38 # v6\.5\.0/,
	);
	assert.match(
		qualityWorkflow,
		/actions\/cache@27d5ce7f107fe9357f9df03efb73ab90386fccae # v5\.0\.5/,
	);
	assert.match(
		qualityWorkflow,
		/actions\/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7\.0\.1/,
	);
	assert.equal(
		[ ...qualityWorkflow.matchAll( /node-version:\s*24/g ) ].length,
		7,
	);
} );

test( 'keeps bounded performance budgets executable and hosted', () => {
	assert.equal(
		packageManifest.scripts[ 'test:performance' ],
		'node tools/performance-playground.mjs',
	);
	assert.match( qualityWorkflow, /^  performance:$/m );
	assert.match( qualityWorkflow, /npm run test:performance/ );
	assert.match(
		qualityWorkflow,
		/WPSE_PERFORMANCE_CORE: WordPress\/WordPress#7\.1/,
	);
	assert.match( performanceBudgets, /500 published, password-free event series/ );
	assert.match( performanceBudgets, /5,000 recurring occurrence rows/ );
	assert.match( performanceRunner, /maximumQueries: 2/ );
	assert.equal(
		[ ...performanceRunner.matchAll( /maximumQueries: 24/g ) ].length,
		2,
	);
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

test( 'keeps a complete task-based new-user documentation path', () => {
	const guideLinks = [
		'GETTING-STARTED.md',
		'DISPLAYING-EVENTS.md',
		'RECURRING-EVENTS.md',
		'BUILDERS-AND-TEMPLATES.md',
		'TROUBLESHOOTING.md',
		'PRIVACY-DATA-AND-UPDATES.md',
	];

	for ( const guide of guideLinks ) {
		assert.match( userGuide, new RegExp( `\\(${ guide }\\)` ) );
		assert.match(
			repositoryReadme,
			new RegExp(
				`https://github\\.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/${ guide }`,
			),
		);
	}

	assert.match( gettingStartedGuide, /Settings > General/ );
	assert.match( gettingStartedGuide, /valid start is required/i );
	assert.match( recurrenceGuide, /Save the draft first/ );
	assert.match( recurrenceGuide, /Complete series/ );
	assert.match( recurrenceGuide, /Edit one occurrence/ );
	assert.match( recurrenceGuide, /This and following/ );
	assert.match( recurrenceGuide, /Stop repeating/ );
	assert.match( displayGuide, /Clear all/ );
	assert.match( displayGuide, /Restore\s+defaults/ );
	assert.match( displayGuide, /Add to Calendar/ );
	assert.match( builderGuide, /Elementor\s+Free/ );
	assert.match( builderGuide, /Divi 5\.11\.1/ );
	assert.match( troubleshootingGuide, /Occurrence index/ );
	assert.match( privacyAndUpdatesGuide, /0\.2\.3/ );
	assert.match( privacyAndUpdatesGuide, /simple-events-by-mime/ );
} );

test( 'does not advertise internal manual occurrences as an editor feature', () => {
	assert.doesNotMatch( repositoryReadme, /manual occurrences/i );
	assert.doesNotMatch( publicReadme, /manual occurrences/i );
	assert.doesNotMatch( userGuide, /manual occurrences/i );
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
