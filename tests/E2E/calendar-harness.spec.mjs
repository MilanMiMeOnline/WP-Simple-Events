import { expect, test } from '@playwright/test';

/**
 * Assert that a rendered month grid has seven evenly distributed columns.
 *
 * @param {import('@playwright/test').Locator} canvas Calendar canvas.
 */
const expectHealthyMonthGrid = async ( canvas ) => {
	await expect
		.poll( async () => canvas.evaluate( ( element ) => {
			const widths = [
				...element.querySelectorAll( '.fc-col-header-cell' ),
			].map( ( column ) => column.getBoundingClientRect().width );

			return element.getBoundingClientRect().width > 300 &&
				widths.length === 7 &&
				Math.min( ...widths ) > 30 &&
				Math.max( ...widths ) - Math.min( ...widths ) < 3;
		} ) )
		.toBe( true );

	const geometry = await canvas.evaluate( ( element ) => {
		const widths = [
			...element.querySelectorAll( '.fc-col-header-cell' ),
		].map( ( column ) => column.getBoundingClientRect().width );

		return {
			canvasWidth: element.getBoundingClientRect().width,
			minimumColumnWidth: Math.min( ...widths ),
			columnWidthDifference:
				Math.max( ...widths ) - Math.min( ...widths ),
		};
	} );

	expect( geometry.canvasWidth ).toBeGreaterThan( 300 );
	expect( geometry.minimumColumnWidth ).toBeGreaterThan( 30 );
	expect( geometry.columnWidthDifference ).toBeLessThan( 3 );
};

/**
 * Match REST event-feed requests for both pretty and query-string permalinks.
 *
 * @param {URL} url Requested URL.
 * @return {boolean} Whether this is the public event feed.
 */
const isEventFeed = ( url ) =>
	decodeURIComponent( url.href ).includes( 'wpse/v1/events' );

/**
 * Open a fixture page, allowing the test-only plugin one request to seed a
 * fresh Playground database when activation hooks have not run yet.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 * @param {string}                          slug Fixture page slug.
 */
const gotoFixturePage = async ( page, slug ) => {
	let response = await page.goto( `/?pagename=${ slug }` );

	if ( response?.status() === 404 ) {
		response = await page.reload();
	}

	if ( ! response ) {
		throw new Error( `Fixture navigation for ${ slug } returned no response.` );
	}

	expect( response.status() ).toBeLessThan( 400 );
};

test( 'loads the progressively enhanced public calendar', async ( { page } ) => {
	const pageErrors = [];

	page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );

	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( calendar ).toBeVisible();
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await expect( canvas ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Previous' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Next' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Month' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'List' } ) ).toBeVisible();
	await expectHealthyMonthGrid( canvas );

	await page.reload();
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await expectHealthyMonthGrid( canvas );

	await page.setViewportSize( { width: 800, height: 900 } );
	await expectHealthyMonthGrid( canvas );
	expect( pageErrors ).toEqual( [] );
} );

test( 'uses the configured mobile list view on its first render', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 480, height: 900 } );
	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await expect( canvas.locator( '.fc-listMonth-view' ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Month' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'List' } ) ).toBeVisible();
} );

test( 'keeps a measurable grid after a delayed event feed', async ( {
	page,
} ) => {
	let feedWasDelayed = false;

	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);

	await page.route( isEventFeed, async ( route ) => {
		const response = await route.fetch();

		await new Promise( ( resolve ) => setTimeout( resolve, 750 ) );
		feedWasDelayed = true;
		await route.fulfill( { response } );
	} );

	await canvas.getByRole( 'button', { name: 'Next' } ).click();
	await expect.poll( () => feedWasDelayed ).toBe( true );

	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await expect( canvas ).toBeVisible();
	await expectHealthyMonthGrid( canvas );
	await page.unrouteAll( { behavior: 'wait' } );
} );

test( 'preserves the fallback when the event feed fails', async ( { page } ) => {
	await page.route( isEventFeed, async ( route ) => {
		await route.fulfill( {
			status: 503,
			contentType: 'application/json',
			body: JSON.stringify( { code: 'wpse_e2e_unavailable' } ),
		} );
	} );

	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );

	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'The calendar could not be loaded. The event list remains available below.',
	);
	await expect(
		calendar.locator( '[data-wpse-calendar-canvas]' ),
	).not.toBeVisible();
	await expect( calendar.locator( '.wpse-calendar-fallback' ) ).toBeVisible();
} );

test( 'keeps multiple calendar instances independent and measurable', async ( {
	page,
} ) => {
	await gotoFixturePage( page, 'wpse-e2e-calendar-multiple' );

	const calendars = page.locator( '[data-wpse-calendar]' );

	await expect( calendars ).toHaveCount( 2 );

	for ( let index = 0; index < 2; index++ ) {
		const calendar = calendars.nth( index );

		await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
			'No events match your selection.',
		);
		await expectHealthyMonthGrid(
			calendar.locator( '[data-wpse-calendar-canvas]' ),
		);
	}
} );

test( 'repairs its geometry after a hidden integration container is revealed', async ( {
	page,
} ) => {
	await gotoFixturePage( page, 'wpse-e2e-calendar-hidden' );

	const container = page.locator( '[data-wpse-e2e-hidden-calendar]' );
	const calendar = container.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( container ).not.toBeVisible();
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await container.evaluate( ( element ) => {
		element.hidden = false;
	} );
	await expect( container ).toBeVisible();
	await expectHealthyMonthGrid( canvas );
} );
