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

/**
 * Authenticate the deterministic WordPress administrator.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 */
const login = async ( page ) => {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await Promise.all( [
		page.waitForURL( /\/wp-admin\//, { waitUntil: 'domcontentloaded' } ),
		page.locator( '#wp-submit' ).click(),
	] );
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
		expect( block.category ).toBe( 'simple-events-by-mime' );
		expect( block.hasEventId ).toBe( true );
		expect( block.hasEdit ).toBe( true );
		expect( block.hasSave ).toBe( true );
	}
	expect( contract.serialized ).toContain( '<!-- wp:wpse/event-venue' );
	expect( contract.serialized ).not.toContain( 'E2E Atomic Hall' );
	expect( contract.preview ).toContain( 'wpse-event-field-block-event-venue' );
	expect( contract.preview ).toContain( 'E2E Atomic Hall' );
} );
