import { expect, test } from '@playwright/test';

const atomicBlockNames = [
	'wpse/event-title',
	'wpse/event-featured-image',
	'wpse/event-date-time',
	'wpse/event-status',
	'wpse/event-venue',
	'wpse/event-address',
	'wpse/event-location-link',
	'wpse/event-content',
	'wpse/event-excerpt',
	'wpse/event-external-action',
	'wpse/event-categories',
	'wpse/event-tags',
];
const compositeBlockNames = [
	'wpse/event-list',
	'wpse/event-calendar',
	'wpse/event-details',
];

/**
 * Authenticate the deterministic WordPress administrator.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 */
const login = async ( page ) => {
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
	const loginRedirect = new URL( page.url() );
	const redirectTarget = loginRedirect.searchParams.get( 'redirect_to' );
	const adminUrl = redirectTarget
		? new URL( redirectTarget ).href
		: new URL( '/wp-admin/', page.url() ).href;
	const loginUrl = new URL( '/wp-login.php', adminUrl ).href;

	await page.goto( loginUrl, { waitUntil: 'domcontentloaded' } );
	const response = await page.context().request.post( loginUrl, {
		form: {
			log: 'admin',
			pwd: 'password',
			redirect_to: adminUrl,
			testcookie: '1',
		},
	} );

	expect( response.ok() ).toBe( true );
	expect( response.url() ).toMatch( /\/wp-admin\// );
	await page.goto( adminUrl, { waitUntil: 'domcontentloaded' } );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
};

test( 'renders the complete explicit-source block palette without editor assets', async ( {
	page,
} ) => {
	const response = await page.goto( '/?pagename=wpse-e2e-atomic-fields' );

	expect( response?.status() ).toBeLessThan( 400 );
	await expect( page.locator( '.wpse-event-field-block-event-title' ) ).toContainText( 'E2E Same-day event' );
	await expect( page.locator( '.wpse-event-field-block-event-date-time' ) ).toBeVisible();
	await expect( page.locator( '.wpse-event-field-block-event-status' ) ).toContainText( 'Postponed' );
	await expect( page.locator( '.wpse-event-field-block-event-venue' ) ).toContainText( 'E2E Atomic Hall' );
	await expect( page.locator( '.wpse-event-field-block-event-address' ) ).toContainText( 'Test Street 1' );
	await expect( page.locator( '.wpse-event-field-block-event-content' ) ).toContainText( 'E2E atomic event content.' );
	await expect( page.locator( '.wpse-event-field-block-event-excerpt' ) ).toContainText( 'E2E atomic event excerpt.' );
	await expect( page.locator( '.wpse-event-field-block-event-external-action' ) ).toContainText( 'Reserve a place' );
	await expect( page.locator( '.wpse-event-field-block-event-location-link a' ) ).toHaveAttribute( 'target', '_blank' );
	await expect( page.locator( '.wpse-event-field-block-event-location-link a' ) ).toHaveAttribute( 'rel', 'noopener noreferrer' );
	await expect( page.locator( '.wpse-event-field-block-event-external-action a' ) ).toHaveAttribute( 'target', '_blank' );
	await expect( page.locator( '.wpse-event-field-block-event-external-action a' ) ).toHaveAttribute( 'rel', 'noopener noreferrer' );
	await expect( page.locator( '.wpse-event-field-block-event-categories' ) ).toContainText( 'E2E Category' );
	await expect( page.locator( '.wpse-event-field-block-event-tags' ) ).toContainText( 'E2E Atomic Tag' );
	await expect( page.locator( '.wpse-event-field-block-event-featured-image' ) ).toHaveCount( 0 );

	expect(
		await page.locator( 'script[src*="event-fields-editor"]' ).count(),
	).toBe( 0 );
} );

test( 'resolves current event context inside a Query Loop', async ( { page } ) => {
	const response = await page.goto( '/?pagename=wpse-e2e-atomic-query' );

	expect( response?.status() ).toBeLessThan( 400 );
	await expect( page.locator( '.wpse-event-field-block-event-title' ) ).toHaveCount( 3 );
	const titles = await page.locator( '.wpse-event-field-block-event-title' ).allTextContents();

	expect( new Set( titles ).size ).toBe( 3 );
	expect( titles ).toContain( 'E2E All-day event' );
} );

test( 'renders all primary event blocks with a useful no-JavaScript fallback', async ( {
	browser,
	page: setupPage,
} ) => {
	// Trigger the capability-protected fixture seeder before opening an anonymous,
	// JavaScript-free visitor context.
	await login( setupPage );

	const context = await browser.newContext( {
		baseURL: 'http://localhost:8888',
		javaScriptEnabled: false,
	} );
	const page = await context.newPage();

	try {
		const response = await page.goto( '/?pagename=wpse-e2e-composite-blocks' );

		expect( response?.status() ).toBeLessThan( 400 );
		await expect( page.locator( '.wpse-event-composite-block-list .wpse-events-view-list' ) ).toBeVisible();
		await expect( page.locator( '.wpse-event-composite-block-calendar .wpse-calendar-fallback' ) ).toBeVisible();
		await expect( page.locator( '.wpse-event-composite-block-calendar .wpse-calendar-canvas' ) ).toBeHidden();
		await expect( page.locator( '.wpse-event-composite-block-details .wpse-single-event' ) ).toContainText( 'E2E Same-day event' );
		await expect( page.locator( '.wpse-event-composite-block-details' ) ).toContainText( 'E2E Atomic Hall' );
		expect( await page.locator( 'script[src*="event-fields-editor"]' ).count() ).toBe( 0 );
	} finally {
		await context.close();
	}
} );

test( 'registers, serializes and previews atomic blocks in Gutenberg', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await expect.poll( () => page.evaluate( () => Boolean( window.wp?.blocks ) ) ).toBe( true );

	const contract = await page.evaluate( async ( names ) => {
		const eventIds = Object.keys( window.wpseEventFieldBlocks?.events || {} );
		const eventId = Number.parseInt( eventIds[ 0 ], 10 );
		const blocks = names.map( ( name ) => {
			const type = window.wp.blocks.getBlockType( name );

			return {
				name: type?.name,
				category: type?.category,
				hasEventId: type?.attributes?.eventId?.type === 'integer',
				hasEdit: typeof type?.edit === 'function',
				hasSave: typeof type?.save === 'function',
			};
		} );
		const serialized = window.wp.blocks.serialize( [
			window.wp.blocks.createBlock( 'wpse/event-venue', { eventId } ),
		] );
		const preview = await window.wp.apiFetch( {
			path: '/wp/v2/block-renderer/wpse/event-venue?context=edit',
			method: 'POST',
			data: { attributes: { eventId } },
		} );

		return {
			blocks,
			eventCount: eventIds.length,
			serialized,
			preview: preview.rendered,
		};
	}, atomicBlockNames );

	expect( contract.eventCount ).toBeGreaterThan( 0 );
	expect( contract.eventCount ).toBeLessThanOrEqual( 50 );
	for ( const [ index, block ] of contract.blocks.entries() ) {
		expect( block.name ).toBe( atomicBlockNames[ index ] );
		expect( block.category ).toBe( 'mime-simple-events-calendar' );
		expect( block.hasEventId ).toBe( true );
		expect( block.hasEdit ).toBe( true );
		expect( block.hasSave ).toBe( true );
	}
	expect( contract.serialized ).toContain( '<!-- wp:wpse/event-venue' );
	expect( contract.serialized ).not.toContain( 'E2E Atomic Hall' );
	expect( contract.preview ).toContain( 'wpse-event-field-block-event-venue' );
	expect( contract.preview ).toContain( 'E2E Atomic Hall' );
} );

test( 'registers, serializes and previews the primary event components in Gutenberg', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await expect.poll( () => page.evaluate( () => Boolean( window.wp?.blocks ) ) ).toBe( true );

	const contract = await page.evaluate( async ( names ) => {
		const eventIds = Object.keys( window.wpseEventFieldBlocks?.events || {} );
		const eventId = Number.parseInt( eventIds[ 0 ], 10 );
		const blocks = names.map( ( name ) => {
			const type = window.wp.blocks.getBlockType( name );

			return {
				name: type?.name,
				category: type?.category,
				hasEdit: typeof type?.edit === 'function',
				hasSave: typeof type?.save === 'function',
			};
		} );
		const serialized = window.wp.blocks.serialize( [
			window.wp.blocks.createBlock( 'wpse/event-list', { limit: 2, view: 'list' } ),
			window.wp.blocks.createBlock( 'wpse/event-calendar', { initialView: 'list', filters: false } ),
			window.wp.blocks.createBlock( 'wpse/event-details', { eventId } ),
		] );
		const previews = await Promise.all( [
			window.wp.apiFetch( {
				path: '/wp/v2/block-renderer/wpse/event-list?context=edit',
				method: 'POST',
				data: { attributes: { limit: 2, view: 'list', filters: false } },
			} ),
			window.wp.apiFetch( {
				path: '/wp/v2/block-renderer/wpse/event-calendar?context=edit',
				method: 'POST',
				data: { attributes: { initialView: 'list', filters: false } },
			} ),
			window.wp.apiFetch( {
				path: '/wp/v2/block-renderer/wpse/event-details?context=edit',
				method: 'POST',
				data: { attributes: { eventId } },
			} ),
		] );

		return {
			blocks,
			categoryCount: Object.keys( window.wpseEventFieldBlocks?.categories || {} ).length,
			tagCount: Object.keys( window.wpseEventFieldBlocks?.tags || {} ).length,
			serialized,
			previews: previews.map( ( preview ) => preview.rendered ),
		};
	}, compositeBlockNames );

	for ( const [ index, block ] of contract.blocks.entries() ) {
		expect( block.name ).toBe( compositeBlockNames[ index ] );
		expect( block.category ).toBe( 'mime-simple-events-calendar' );
		expect( block.hasEdit ).toBe( true );
		expect( block.hasSave ).toBe( true );
	}
	expect( contract.categoryCount ).toBeGreaterThan( 0 );
	expect( contract.tagCount ).toBeGreaterThan( 0 );
	expect( contract.serialized ).toContain( '<!-- wp:wpse/event-list' );
	expect( contract.serialized ).toContain( '<!-- wp:wpse/event-calendar' );
	expect( contract.serialized ).toContain( '<!-- wp:wpse/event-details' );
	expect( contract.serialized ).not.toContain( 'E2E Same-day event' );
	expect( contract.previews[ 0 ] ).toContain( 'wpse-event-composite-block-list' );
	expect( contract.previews[ 0 ] ).toContain( 'wpse-events-view-list' );
	expect( contract.previews[ 1 ] ).toContain( 'wpse-event-composite-block-calendar' );
	expect( contract.previews[ 1 ] ).toContain( 'wpse-calendar' );
	expect( contract.previews[ 2 ] ).toContain( 'wpse-event-composite-block-details' );
	expect( contract.previews[ 2 ] ).toContain( 'wpse-single-event' );

	await page.evaluate( () => {
		const block = window.wp.blocks.createBlock( 'wpse/event-list' );

		window.wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ block ] );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
	} );
	await expect( page.getByLabel( 'Layout', { exact: true } ) ).toBeVisible();
	await expect( page.locator( 'input[type="number"][aria-label="Events per page"]' ) ).toBeVisible();
	await expect( page.getByLabel( 'Title heading level', { exact: true } ) ).toBeVisible();
	await expect( page.locator( 'input[type="number"][aria-label="Excerpt length (words)"]' ) ).toBeVisible();

	await page.evaluate( () => {
		const block = window.wp.blocks.createBlock( 'wpse/event-calendar' );

		window.wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ block ] );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
	} );
	await expect( page.getByLabel( 'Desktop view', { exact: true } ) ).toBeVisible();
	await expect( page.getByLabel( 'Mobile view', { exact: true } ) ).toBeVisible();
	await expect( page.getByLabel( 'Initial date', { exact: true } ) ).toBeVisible();
	await expect( page.getByLabel( 'Show previous and next buttons', { exact: true } ) ).toBeVisible();

	await page.evaluate( () => {
		const block = window.wp.blocks.createBlock( 'wpse/event-details' );

		window.wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ block ] );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
	} );
	await expect( page.getByLabel( 'Event source', { exact: true } ) ).toBeVisible();
	await expect( page.getByLabel( 'Show title', { exact: true } ) ).toBeVisible();
	await expect( page.getByLabel( 'Title heading level', { exact: true } ) ).toBeVisible();
} );
