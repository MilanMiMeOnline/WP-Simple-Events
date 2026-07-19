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

/**
 * Return the stable visual state properties used by calendar button assertions.
 *
 * @param {import('@playwright/test').Locator} button Calendar button.
 * @return {Promise<Object>} Computed state properties.
 */
const buttonState = ( button ) => button.evaluate( ( element ) => {
	const style = window.getComputedStyle( element );

	return {
		backgroundColor: style.backgroundColor,
		borderColor: style.borderColor,
		color: style.color,
		opacity: style.opacity,
		outlineColor: style.outlineColor,
		outlineStyle: style.outlineStyle,
		outlineWidth: style.outlineWidth,
	};
} );

/**
 * Return all selected values from a multiple select.
 *
 * @param {import('@playwright/test').Locator} select Multiple select control.
 * @return {Promise<Array<string>>} Selected option values.
 */
const selectedOptions = ( select ) =>
	select.evaluate( ( element ) =>
		Array.from( element.selectedOptions, ( option ) => option.value ),
	);

test( 'loads the progressively enhanced public calendar', async ( { page } ) => {
	const pageErrors = [];

	page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );

	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( calendar ).toBeVisible();
	await expect(
		calendar.locator( '[data-wpse-calendar-filters]' ),
	).toHaveCount( 0 );
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await expect( canvas ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Previous' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Next' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'Month' } ) ).toBeVisible();
	await expect( canvas.getByRole( 'button', { name: 'List' } ) ).toBeVisible();
	const activeState = await buttonState(
		canvas.getByRole( 'button', { name: 'Month' } ),
	);

	expect( activeState.backgroundColor ).not.toBe( activeState.color );
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

test( 'filters by category and tag with persistent namespaced URL state', async ( {
	page,
} ) => {
	await gotoFixturePage( page, 'wpse-e2e-calendar-filters' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );
	const filters = calendar.locator( '[data-wpse-calendar-filters]' );
	const category = filters.locator(
		'[data-wpse-calendar-filter="category"]',
	);
	const tag = filters.locator( '[data-wpse-calendar-filter="tag"]' );

	await expect( filters ).toBeVisible();
	await expect( filters ).toHaveAttribute( 'method', 'get' );
	await expect.poll( () => selectedOptions( category ) ).toEqual( [
		'wpse-e2e-category',
	] );
	await expect.poll( () => selectedOptions( tag ) ).toEqual( [] );
	await expect(
		filters.getByRole( 'button', { name: 'Apply filters' } ),
	).toHaveAttribute( 'aria-controls', 'wpse-calendar-1-canvas' );

	await canvas.getByRole( 'button', { name: 'Next' } ).click();
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'3 events loaded.',
	);

	await tag.selectOption( 'wpse-e2e-tag' );
	await filters.getByRole( 'button', { name: 'Apply filters' } ).click();
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'2 events loaded.',
	);
	await expect.poll( () => {
		const url = new URL( page.url() );

		return {
			categories: url.searchParams.getAll(
				'wpse_calendar_1_category[]',
			),
			tags: url.searchParams.getAll( 'wpse_calendar_1_tag[]' ),
		};
	} ).toEqual( {
		categories: [ 'wpse-e2e-category' ],
		tags: [ 'wpse-e2e-tag' ],
	} );

	await page.reload();
	await expect.poll( () => selectedOptions( category ) ).toEqual( [
		'wpse-e2e-category',
	] );
	await expect.poll( () => selectedOptions( tag ) ).toEqual( [
		'wpse-e2e-tag',
	] );
	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	const reset = calendar
		.locator( '[data-wpse-calendar-empty-action]' )
		.getByRole( 'button', { name: 'Reset filters' } );

	await expect( reset ).toBeVisible();
	await reset.click();
	await expect.poll( () => selectedOptions( category ) ).toEqual( [] );
	await expect.poll( () => selectedOptions( tag ) ).toEqual( [] );
	await expect.poll( () => {
		const url = new URL( page.url() );

		return [
			...url.searchParams.getAll( 'wpse_calendar_1_category[]' ),
			...url.searchParams.getAll( 'wpse_calendar_1_tag[]' ),
		];
	} ).toEqual( [] );
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

for ( const timezoneId of [ 'Europe/Brussels', 'America/Los_Angeles' ] ) {
	test( `preserves captured wall time in ${ timezoneId }`, async ( {
		browser,
	} ) => {
		const context = await browser.newContext( {
			baseURL: 'http://localhost:8888',
			timezoneId,
			viewport: { width: 1280, height: 900 },
		} );
		const page = await context.newPage();

		try {
			await gotoFixturePage( page, 'wpse-e2e-calendar-wall-time' );
			await expect.poll( () => page.evaluate(
				() => Intl.DateTimeFormat().resolvedOptions().timeZone,
			) ).toBe( timezoneId );

			const calendar = page.locator( '[data-wpse-calendar]' );
			const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

			await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
				'1 event loaded.',
			);
			const responsePromise = page.waitForResponse( ( response ) =>
				isEventFeed( new URL( response.url() ) ),
			);

			await canvas.getByRole( 'button', { name: 'Next' } ).click();
			const response = await responsePromise;
			const events = await response.json();
			const sameDay = events.find(
				( event ) => event.title === 'E2E UTC same-day event',
			);

			expect( sameDay ).toMatchObject( {
				start: '2026-08-10T12:05:00',
				end: '2026-08-10T22:05:00',
				extendedProps: {
					timezone: '+00:00',
					startInstant: '2026-08-10T12:05:00+00:00',
					endInstant: '2026-08-10T22:05:00+00:00',
				},
			} );
			await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
				'3 events loaded.',
			);
			await expect(
				canvas.locator( '[data-date="2026-08-10"]' ).getByText(
					'E2E UTC same-day event',
				),
			).toHaveCount( 1 );
			await expect(
				canvas.locator( '[data-date="2026-08-11"]' ).getByText(
					'E2E UTC same-day event',
				),
			).toHaveCount( 0 );
			await expect(
				canvas.locator( '[data-date="2026-08-01"]' ).getByText(
					'E2E positive-offset event',
				),
			).toHaveCount( 1 );
			await expect(
				canvas.locator( '[data-date="2026-08-31"]' ).getByText(
					'E2E negative-offset event',
				),
			).toHaveCount( 1 );

			await canvas.getByRole( 'button', { name: 'List' } ).click();
			const listEvent = canvas.locator( '.fc-list-event' ).filter( {
				hasText: 'E2E UTC same-day event',
			} );

			await expect( listEvent ).toHaveCount( 1 );
			expect( await listEvent.evaluate( ( row ) => {
				let sibling = row.previousElementSibling;

				while ( sibling ) {
					const date = sibling.getAttribute( 'data-date' ) ??
						sibling.querySelector( '[data-date]' )?.getAttribute( 'data-date' );

					if ( date ) {
						return date;
					}

					sibling = sibling.previousElementSibling;
				}

				return null;
			} ) ).toBe( '2026-08-10' );

			const eventLink = listEvent.getByRole( 'link', {
				name: 'E2E UTC same-day event',
			} );

			await expect( eventLink ).toBeVisible();
			const eventUrl = await eventLink.getAttribute( 'href' );

			expect( eventUrl ).not.toBeNull();
			await page.goto( new URL( eventUrl ).pathname );
			await expect( page.locator( '.wpse-event-date time' ) ).toHaveAttribute(
				'datetime',
				'2026-08-10T12:05:00+00:00',
			);
			await expect( page.locator( '.wpse-event-date time' ) ).toHaveAttribute(
				'data-wpse-end',
				'2026-08-10T22:05:00+00:00',
			);
		} finally {
			await context.close();
		}
	} );
}

test( 'keeps calendar button states readable with custom accent colors', async ( {
	page,
} ) => {
	await gotoFixturePage( page, 'wpse-e2e-calendar' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const canvas = calendar.locator( '[data-wpse-calendar-canvas]' );

	await expect( calendar.locator( '[data-wpse-calendar-status]' ) ).toHaveText(
		'No events match your selection.',
	);
	await calendar.evaluate( ( element ) => {
		element.style.color = 'rgb(75 80 90)';
		element.style.setProperty( '--wpse-calendar-accent', 'rgb(18 112 165)' );
		element.style.setProperty( '--wpse-calendar-on-accent', 'rgb(255 255 255)' );
	} );

	const next = canvas.getByRole( 'button', { name: 'Next' } );
	const month = canvas.getByRole( 'button', { name: 'Month' } );
	const today = canvas.getByRole( 'button', { name: 'Today' } );

	await expect( next ).toBeVisible();
	expect( await buttonState( next ) ).toMatchObject( {
		backgroundColor: 'rgba(0, 0, 0, 0)',
		color: 'rgb(75, 80, 90)',
	} );

	await next.hover();
	expect( await buttonState( next ) ).toMatchObject( {
		backgroundColor: 'rgb(18, 112, 165)',
		borderColor: 'rgb(18, 112, 165)',
		color: 'rgb(255, 255, 255)',
	} );
	await page.mouse.down();
	expect( await buttonState( next ) ).toMatchObject( {
		backgroundColor: 'rgb(18, 112, 165)',
		color: 'rgb(255, 255, 255)',
	} );
	await page.mouse.move( 0, 0 );
	await page.mouse.up();

	await page.keyboard.press( 'Tab' );
	await next.focus();
	expect( await buttonState( next ) ).toMatchObject( {
		backgroundColor: 'rgb(18, 112, 165)',
		color: 'rgb(255, 255, 255)',
		outlineColor: 'rgb(18, 112, 165)',
		outlineStyle: 'solid',
		outlineWidth: '2px',
	} );

	expect( await buttonState( month ) ).toMatchObject( {
		backgroundColor: 'rgb(18, 112, 165)',
		color: 'rgb(255, 255, 255)',
	} );
	await expect( today ).toBeDisabled();
	expect( Number.parseFloat( ( await buttonState( today ) ).opacity ) ).toBeLessThan( 1 );

	await page.emulateMedia( { forcedColors: 'active' } );
	const forcedActiveState = await buttonState( month );

	expect( forcedActiveState.backgroundColor ).not.toBe(
		forcedActiveState.color,
	);
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

	const firstCalendar = calendars.nth( 0 );
	const secondCalendar = calendars.nth( 1 );

	await firstCalendar
		.locator( '[data-wpse-calendar-canvas]' )
		.getByRole( 'button', { name: 'Next' } )
		.click();
	await expect(
		firstCalendar.locator( '[data-wpse-calendar-status]' ),
	).toHaveText( '3 events loaded.' );
	await expect(
		secondCalendar.locator( '[data-wpse-calendar-status]' ),
	).toHaveText( 'No events match your selection.' );

	await secondCalendar
		.locator( '[data-wpse-calendar-canvas]' )
		.getByRole( 'button', { name: 'Next' } )
		.click();
	await expect(
		secondCalendar.locator( '[data-wpse-calendar-status]' ),
	).toHaveText( '3 events loaded.' );
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
