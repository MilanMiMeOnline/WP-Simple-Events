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

test( 'previews and applies a complete-series recurrence in Gutenberg', async ( {
	page,
} ) => {
	test.setTimeout( 180_000 );
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=wpse_event' );
	const editUrl = await page.locator( 'a.row-title' ).first().getAttribute( 'href' );

	expect( editUrl ).toBeTruthy();
	await page.goto( editUrl );
	await expect.poll( () =>
		page.evaluate( () =>
			Boolean(
				window.wpseRecurrenceEditor &&
					window.wp?.plugins?.getPlugin( 'wpse-recurrence-editor' ),
			),
		),
	).toBe( true );
	const welcomeDialog = page.getByRole( 'dialog', { name: 'Welcome to the editor' } );
	let welcomeVisible = false;

	try {
		await welcomeDialog.waitFor( { state: 'visible', timeout: 3000 } );
		welcomeVisible = true;
	} catch {
		// The guide has already been dismissed for this isolated test user.
	}

	if ( welcomeVisible ) {
		// Let persisted editor preferences finish hydrating before changing them;
		// otherwise a late hydration can reopen the guide during an isolated run.
		await page.waitForTimeout( 1000 );
		await page.evaluate( async () => {
			const preferences = window.wp?.data?.dispatch( 'core/preferences' );
			const result = preferences?.set?.(
				'core/edit-post',
				'welcomeGuide',
				false,
			);

			if ( result && typeof result.then === 'function' ) {
				await result;
			}
		} );
		await expect( welcomeDialog ).toBeHidden();
	}

	const toggle = page.getByRole( 'button', { name: 'Repeating event' } );

	await expect( toggle ).toBeVisible();

	if ( await toggle.getAttribute( 'aria-expanded' ) === 'false' ) {
		await toggle.click();
	}

	const panel = page.locator( '.wpse-recurrence-editor' );
	const eventFields = page.locator( '[data-wpse-event-fields]' );
	const ordinarySchedule = eventFields.locator( '[data-wpse-schedule-fields]' );
	const scheduleNotice = eventFields.locator(
		'[data-wpse-recurrence-schedule-notice]',
	);

	await expect( panel ).toBeVisible();
	await expect( ordinarySchedule ).not.toHaveAttribute( 'hidden', '' );
	await expect( scheduleNotice ).toHaveAttribute( 'hidden', '' );
	await expect( panel ).toContainText( 'Editing scope: complete series' );
	await panel.getByLabel( 'Repeats', { exact: true } ).selectOption( 'daily' );
	await panel.getByLabel( 'Ends', { exact: true } ).selectOption( 'count' );
	await panel.getByLabel( 'Number of events', { exact: true } ).fill( '3' );
	await panel.getByRole( 'button', { name: 'Preview recurrence' } ).click();
	await expect( panel ).toContainText( 'Review impact' );
	await expect( panel ).toContainText( '2 added' );
	await panel.getByRole( 'button', { name: 'Apply to complete series' } ).click();
	await expect( panel ).toContainText( 'The recurring schedule was updated.' );
	await expect( panel.getByLabel( 'Repeats', { exact: true } ) ).toHaveValue( 'daily' );
	await expect( ordinarySchedule ).toHaveAttribute( 'hidden', '' );
	await expect( scheduleNotice ).not.toHaveAttribute( 'hidden', '' );
	await expect( scheduleNotice ).toContainText(
		'This event’s schedule is managed in the block editor.',
	);
	await expect( eventFields.locator( '#wpse-status' ) ).toBeEnabled();
	await expect( eventFields.locator( '#wpse-venue' ) ).toBeEnabled();

	await panel.getByRole( 'button', { name: 'Edit one occurrence…' } ).click();
	await expect( panel ).toContainText( 'Editing scope: only this occurrence' );
	await expect( panel.getByLabel( 'Occurrence to edit' ) ).toBeVisible();
	await panel.getByRole( 'button', { name: 'Edit selected occurrence' } ).click();
	await expect( panel.getByRole( 'group', { name: 'Selected occurrence' } ) ).toBeVisible();
	await expect( panel ).toContainText( 'Times use the series timezone:' );
	await panel.getByLabel( 'Occurrence title' ).fill( 'E2E occurrence title' );
	await panel.getByLabel( 'Occurrence note' ).fill( 'E2E occurrence note' );
	await panel.getByLabel( 'Venue', { exact: true } ).fill( 'E2E side hall' );
	await panel.getByLabel( 'Address', { exact: true } ).fill( 'E2E side entrance' );
	await panel.getByLabel( 'Location URL', { exact: true } ).fill(
		'https://example.com/e2e-location',
	);
	await panel.getByLabel( 'External event URL', { exact: true } ).fill(
		'https://example.com/e2e-occurrence',
	);
	await panel.getByLabel( 'External event action label', { exact: true } ).fill(
		'E2E tickets',
	);
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await expect( panel ).toContainText( '1 individual change is affected.' );
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByLabel( 'Occurrence title' ) ).toHaveValue(
		'E2E occurrence title',
	);

	for ( const label of [
		'Occurrence title',
		'Occurrence note',
		'Venue',
		'Address',
		'Location URL',
		'External event URL',
		'External event action label',
	] ) {
		const field = panel
			.locator( '.wpse-occurrence-override-field' )
			.filter( { has: page.getByLabel( label, { exact: true } ) } );

		await field.getByRole( 'button', { name: 'Use series value' } ).click();
	}

	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByRole( 'button', { name: 'Use series value' } ) ).toHaveCount( 0 );
	const startDateControl = panel.getByLabel( 'Start date' );
	const endDateControl = panel.getByLabel( 'End date' );
	const originalStartDate = await startDateControl.inputValue();
	const originalEndDate = await endDateControl.inputValue();
	const movedStart = new Date( `${ originalStartDate }T12:00:00Z` );
	const movedEnd = new Date( `${ originalEndDate }T12:00:00Z` );

	movedStart.setUTCDate( movedStart.getUTCDate() + 1 );
	movedEnd.setUTCDate( movedEnd.getUTCDate() + 1 );
	await startDateControl.fill( movedStart.toISOString().slice( 0, 10 ) );
	await endDateControl.fill( movedEnd.toISOString().slice( 0, 10 ) );
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await expect( panel ).toContainText( '1 moved' );
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByLabel( 'Occurrence to edit' ) ).toHaveValue(
		new RegExp( movedStart.toISOString().slice( 0, 10 ) ),
	);
	await expect( panel.getByRole( 'button', { name: 'Use series date and time' } ) ).toBeVisible();
	await panel.getByRole( 'button', { name: 'Use series date and time' } ).click();
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByRole( 'button', { name: 'Use series date and time' } ) ).toHaveCount( 0 );
	await expect( panel.getByLabel( 'Occurrence to edit' ) ).toHaveValue(
		new RegExp( originalStartDate ),
	);

	await panel.getByLabel( 'Event status' ).selectOption( 'postponed' );
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await expect( panel ).toContainText( '1 status changes' );
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel ).toContainText( 'This occurrence was updated.' );
	await expect( panel.getByRole( 'button', { name: 'Use series status' } ) ).toBeVisible();

	await panel.getByRole( 'button', { name: 'Use series status' } ).click();
	await panel.getByLabel( 'Cancel this occurrence' ).check();
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await expect( panel ).toContainText( 'Review impact' );
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByLabel( 'Cancel this occurrence' ) ).toBeChecked();

	await panel.getByLabel( 'Cancel this occurrence' ).uncheck();
	await panel.getByRole( 'button', { name: 'Preview this occurrence' } ).click();
	await panel.getByRole( 'button', { name: 'Apply to this occurrence' } ).click();
	await expect( panel.getByLabel( 'Cancel this occurrence' ) ).not.toBeChecked();
	const backToSeries = panel.getByRole( 'button', { name: 'Back to complete series' } );

	await expect( backToSeries ).toBeEnabled();
	await backToSeries.click();
	await expect( panel ).toContainText( 'Editing scope: complete series' );
	await expect( panel.getByLabel( 'Repeats', { exact: true } ) ).toBeEnabled();

	await panel.getByRole( 'button', { name: 'Change this and following…' } ).click();
	await expect( panel ).toContainText( 'Editing scope: this and following occurrences' );
	await expect( panel.getByLabel( 'Start the new schedule at' ) ).toBeVisible();
	await panel.getByRole( 'button', { name: 'Configure new schedule' } ).click();
	const followingFields = panel.getByRole( 'group', {
		name: 'New schedule from this occurrence',
	} );

	await expect( followingFields ).toBeVisible();
	await expect( followingFields ).toContainText(
		'Every later scheduled change will be replaced.',
	);
	await followingFields.getByLabel( 'Repeat every' ).fill( '2' );
	await followingFields.getByLabel( 'Ends', { exact: true } ).selectOption( 'count' );
	await followingFields.getByLabel( 'Number of events', { exact: true } ).fill( '2' );
	await panel.getByRole( 'button', { name: 'Preview this and following' } ).click();
	await expect( panel ).toContainText( 'Review impact' );
	await expect( panel ).toContainText( '1 added' );
	await expect( panel ).toContainText( '1 removed' );
	await panel.getByRole( 'button', { name: 'Apply to this and following' } ).click();
	await expect( panel ).toContainText(
		'This and following occurrences now use the new schedule.',
	);
	await expect( panel ).toContainText( 'Editing scope: complete series' );

	await panel.getByRole( 'button', { name: 'Stop repeating…' } ).click();
	const searchStart = panel.getByLabel( 'Find occurrences from' );
	const loadedStart = await searchStart.inputValue();

	await searchStart.fill( '2099-01-01' );
	await panel.getByRole( 'button', { name: 'Search this period' } ).click();
	await expect( panel ).toContainText( 'No occurrences were found in this period.' );
	await searchStart.fill( loadedStart );
	await panel.getByRole( 'button', { name: 'Search this period' } ).click();
	await expect( panel.getByLabel( 'Keep as the single event' ) ).toBeVisible();
	await panel.getByRole( 'button', { name: 'Preview stopping recurrence' } ).click();
	await expect( panel ).toContainText( 'Every other occurrence in the complete series will be removed.' );
	await expect( panel ).toContainText( 'outside this preview window will also be removed' );

	const reloaded = page.waitForEvent(
		'framenavigated',
		( frame ) => frame === page.mainFrame(),
	);
	await panel.getByRole( 'button', { name: 'Keep selected event only' } ).click();
	await reloaded;
	await expect.poll(
		() =>
			page.evaluate( () =>
				Boolean(
					window.wpseRecurrenceEditor &&
						window.wp?.plugins?.getPlugin( 'wpse-recurrence-editor' ),
				),
			),
		{ timeout: 30_000 },
	).toBe( true );
	await expect(
		page
			.locator( '.wpse-recurrence-editor' )
			.getByLabel( 'Repeats', { exact: true } ),
	).toHaveValue( 'once' );
	await expect( page.locator( '[data-wpse-schedule-fields]' ) ).not.toHaveAttribute(
		'hidden',
		'',
	);
	await expect(
		page.locator( '[data-wpse-recurrence-schedule-notice]' ),
	).toHaveAttribute( 'hidden', '' );
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
