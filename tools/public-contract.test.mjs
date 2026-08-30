import { strict as assert } from 'node:assert';
import { createHash } from 'node:crypto';
import { readFile, readdir } from 'node:fs/promises';
import { test } from 'node:test';

const root = new URL( '../', import.meta.url );
const contract = await readFile(
	new URL( 'docs/PUBLIC-COMPATIBILITY-CONTRACT.md', root ),
	'utf8',
);
const elementorInspector = await readFile(
	new URL( 'tests/Compatibility/elementor-inspector.php', root ),
	'utf8',
);

const expectedBlocks = {
	'wpse/add-to-calendar': '8ac6bc3fbc630ebc32d88bd88b73a7865e089e379f1e5a887cff6d86064a4fdc',
	'wpse/event-address': 'd95924ca3b094da9307160d53fdaab8dee265d9190cfabc1fe96019d8cb3f5b9',
	'wpse/event-calendar': '4fd02bd4ef19254bea524c5704851afb59690286ddef0e7d8a44409c686bbadf',
	'wpse/event-categories': '7e846a4b328568c0a014e4607eb32f4e8691638cb497ce9a72ed3d1d97848cc8',
	'wpse/event-content': 'd95924ca3b094da9307160d53fdaab8dee265d9190cfabc1fe96019d8cb3f5b9',
	'wpse/event-date-time': '7e846a4b328568c0a014e4607eb32f4e8691638cb497ce9a72ed3d1d97848cc8',
	'wpse/event-details': '5cbed5aae37d0c432cbbd66165379306a11b1aa5efe05c7a0f66243829aab704',
	'wpse/event-excerpt': 'd95924ca3b094da9307160d53fdaab8dee265d9190cfabc1fe96019d8cb3f5b9',
	'wpse/event-external-action': '10e9a7dcf01dc311231975a791c92322a2386a21322c72dd5b5c0b60db3f56f2',
	'wpse/event-featured-image': '19bc2bc806d141632a1fb7f4a5ca9a84c14aae182412b1c506a590117b1a3152',
	'wpse/event-list': 'eb7e214c1306ec9e76c0ead24052bb8af6272babb4bb43a540fae9c4bebff2f8',
	'wpse/event-location-link': '10e9a7dcf01dc311231975a791c92322a2386a21322c72dd5b5c0b60db3f56f2',
	'wpse/event-status': 'd95924ca3b094da9307160d53fdaab8dee265d9190cfabc1fe96019d8cb3f5b9',
	'wpse/event-tags': '7e846a4b328568c0a014e4607eb32f4e8691638cb497ce9a72ed3d1d97848cc8',
	'wpse/event-title': '45824a18fac6d62a015eb057ab4f2f05370918ea26ca9adce0de3a16b031c5d8',
	'wpse/event-venue': '7e846a4b328568c0a014e4607eb32f4e8691638cb497ce9a72ed3d1d97848cc8',
};

const expectedDiviModules = {
	'mime-simple-events-calendar/add-to-calendar': '2526cfcabf377bf2edf9173efd91eb4a6d6e0961fd68b64c9ee922cce41317f2',
	'mime-simple-events-calendar/event-address': 'ea1129b3924d4c89c5cc225407bd97dd5e329442ce993233728ee26152841cd9',
	'mime-simple-events-calendar/event-calendar': '90ad9fa06768b371d71f128ed0bf75a1683cbab2019fbf05cb8bca73ca5a4c0a',
	'mime-simple-events-calendar/event-categories': 'afaf63a5c977f565e9a455d3d54f557e6c2c81da4f6a14093add2474ca25c5d0',
	'mime-simple-events-calendar/event-content': '256d7a40fb1b3863c895392e13cb6e58b47ae670d38e421855f623dd14eee982',
	'mime-simple-events-calendar/event-date-time': '6ef08e71ded9b47fbcfc9abeb81644cf4861eaa4795fc771e7a6ad2f76eecbd8',
	'mime-simple-events-calendar/event-details': '86f80ef1c94880dce19f8b06fc5ca5f40c9c0b191c8d778d13d57dbde26084e6',
	'mime-simple-events-calendar/event-excerpt': '023aecc34779dcec45de7d56e8a05872f2b8f97be98d19b67097036eae411dc2',
	'mime-simple-events-calendar/event-featured-image': '5f9dd15ac9ba7045062a4a5a704117d001ea602a6b0a545e34494c08b1b233e0',
	'mime-simple-events-calendar/event-list': 'd641216e481f53e066e6ed3b3781697cadb6d1ec47658cb3ce00a29f8ab605d4',
	'mime-simple-events-calendar/event-location-link': 'fbb2fb0d33949ab542f7aff51f05da7ad658b174f3e9c455732dc86635b6c6c4',
	'mime-simple-events-calendar/event-status': 'c5732bf26891ca2aaa47cbe058187511e48949ce1f615dad2d8c2691fd5ada9b',
	'mime-simple-events-calendar/event-tags': 'c0bcc5eb07d582ff4405c45fca5d313506a331980028cee9e4b4280e104ea5cd',
	'mime-simple-events-calendar/event-title': 'fae12369e72846d49c43df79daf387262700a6c354df74f30c720112b5bf75c7',
	'mime-simple-events-calendar/event-venue': 'd656823876a1c95deed395425b6d25d28bd886c47eec90409719e3dbe4b7efef',
	'mime-simple-events-calendar/external-event-action': '2850be548f6dc8d11b5b6c6e2d753a9aa45ce797a8c3146adda205e6ed01c0d2',
};

const expectedElementorWidgets = [
	'wpse-add-to-calendar',
	'wpse-event-address',
	'wpse-event-calendar',
	'wpse-event-categories',
	'wpse-event-content',
	'wpse-event-date-time',
	'wpse-event-details',
	'wpse-event-excerpt',
	'wpse-event-external-action',
	'wpse-event-featured-image',
	'wpse-event-list',
	'wpse-event-location-link',
	'wpse-event-status',
	'wpse-event-tags',
	'wpse-event-title',
	'wpse-event-venue',
];

const stableSort = ( value ) => {
	if ( Array.isArray( value ) ) {
		return value.map( stableSort );
	}

	if ( null === value || 'object' !== typeof value ) {
		return value;
	}

	return Object.fromEntries(
		Object.keys( value )
			.sort()
			.map( ( key ) => [ key, stableSort( value[ key ] ) ] ),
	);
};

const fingerprint = ( value ) =>
	createHash( 'sha256' ).update( JSON.stringify( stableSort( value ) ) ).digest( 'hex' );

const metadataContracts = async ( directory ) => {
	const paths = ( await readdir( new URL( directory, root ), { withFileTypes: true } ) )
		.filter( ( entry ) => entry.isDirectory() )
		.map( ( entry ) => `${ directory }${ entry.name }/module.json` );
	const contracts = {};

	for ( const path of paths ) {
		const metadata = JSON.parse( await readFile( new URL( path, root ), 'utf8' ) );
		contracts[ metadata.name ] = fingerprint( metadata.attributes ?? {} );
	}

	return Object.fromEntries( Object.entries( contracts ).sort() );
};

test( 'freezes Gutenberg block identities and saved attribute schemas', async () => {
	const paths = ( await readdir( new URL( 'blocks/', root ), { withFileTypes: true } ) )
		.filter( ( entry ) => entry.isDirectory() )
		.map( ( entry ) => `blocks/${ entry.name }/block.json` );
	const actual = {};

	for ( const path of paths ) {
		const metadata = JSON.parse( await readFile( new URL( path, root ), 'utf8' ) );
		actual[ metadata.name ] = fingerprint( metadata.attributes ?? {} );
	}

	assert.deepEqual( Object.fromEntries( Object.entries( actual ).sort() ), expectedBlocks );

	for ( const name of Object.keys( expectedBlocks ) ) {
		assert.ok( contract.includes( `\`${ name }\`` ), `Undocumented block: ${ name }` );
	}
} );

test( 'freezes native Divi module identities and saved attribute schemas', async () => {
	assert.deepEqual(
		await metadataContracts( 'divi/modules/' ),
		expectedDiviModules,
	);

	for ( const name of Object.keys( expectedDiviModules ) ) {
		assert.ok( contract.includes( `\`${ name }\`` ), `Undocumented Divi module: ${ name }` );
	}
} );

test( 'freezes the complete Elementor widget palette in docs and host inspection', async () => {
	const files = ( await readdir( new URL( 'src/Elementor/', root ) ) )
		.filter( ( file ) => file.endsWith( 'Widget.php' ) );
	const actual = [];

	for ( const file of files ) {
		const source = await readFile( new URL( `src/Elementor/${ file }`, root ), 'utf8' );
		const name = source.match( /public function get_name\(\): string\s*\{\s*return '([^']+)'/ )?.[ 1 ];

		if ( name ) {
			actual.push( name );
		}
	}

	assert.deepEqual( actual.sort(), expectedElementorWidgets );

	for ( const name of expectedElementorWidgets ) {
		assert.ok( contract.includes( `\`${ name }\`` ), `Undocumented Elementor widget: ${ name }` );
		assert.ok( elementorInspector.includes( `'${ name }'` ), `Uninspected Elementor widget: ${ name }` );
	}
} );

test( 'freezes visitor, route and extension identities at the documented boundary', async () => {
	const sources = await Promise.all(
		[
			'src/Plugin.php',
			'src/Shortcode/EventListShortcode.php',
			'src/Shortcode/CalendarShortcode.php',
			'src/Shortcode/EventDetailsShortcode.php',
			'src/Shortcode/AddToCalendarShortcode.php',
			'src/Blocks/EventFieldBlockRegistry.php',
			'src/Frontend/BlockTemplates.php',
			'src/Frontend/NativeTemplateRenderer.php',
			'src/Query/EventArchiveQuery.php',
			'src/CalendarExport/CalendarExportController.php',
			'src/Routing/OccurrenceRouteUrlBuilder.php',
			'src/Rest/OccurrenceRestController.php',
			'src/Rest/RecurrenceEditorController.php',
			'src/Divi/DiviPreviewController.php',
			'src/Seo/StructuredDataSettings.php',
			'src/Content/EventPostType.php',
			'src/Content/EventTaxonomies.php',
		].map( ( path ) => readFile( new URL( path, root ), 'utf8' ) ),
	);
	const source = sources.join( '\n' );
	const identities = [
		'wpse_events',
		'wpse_calendar',
		'wpse_event_details',
		'wpse_add_to_calendar',
		'wpse_event',
		'wpse_event_category',
		'wpse_event_tag',
		'wpse_period',
		'wpse_category',
		'wpse_tag',
		'wpse_occurrence',
		'wpse_calendar_export',
		'wpse/native-single',
		'wpse/native-archive',
		'mime-simple-events-calendar/single-event-fields',
		'wpse/v1',
		'wpse/v2',
		'/divi-preview',
		'wpse_loaded',
		'wpse_render_single_template',
		'wpse_render_archive_template',
		'wpse_structured_data_enabled',
	];

	for ( const identity of identities ) {
		assert.ok( source.includes( identity ), `Missing source identity: ${ identity }` );
		assert.ok( contract.includes( identity ), `Missing documented identity: ${ identity }` );
	}

	assert.match( contract, /Removal is reserved\n  for a later major version\./ );
	assert.match( contract, /There is no public browser JavaScript API\./ );
	assert.match( contract, /PRESENTATION-CONTRACT\.md/ );
} );
