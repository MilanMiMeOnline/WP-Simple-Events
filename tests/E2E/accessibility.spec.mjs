import { createRequire } from 'node:module';

import { expect, test } from '@playwright/test';

const require = createRequire( import.meta.url );
const axePath = require.resolve( 'axe-core/axe.min.js' );
const wcagTags = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

/**
 * Authenticate the deterministic isolated WordPress administrator.
 *
 * @param {import('@playwright/test').Page} page Browser page.
 */
const login = async ( page ) => {
	await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	const response = await page.context().request.post( '/wp-login.php', {
		form: {
			log: 'admin',
			pwd: 'password',
			redirect_to: 'http://localhost:8888/wp-admin/',
			testcookie: '1',
		},
	} );

	expect( response.ok() ).toBe( true );
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
};

/**
 * Open one deterministic public fixture and wait for its plugin surface.
 *
 * @param {import('@playwright/test').Page} page     Browser page.
 * @param {string}                          slug     Fixture page slug.
 * @param {string}                          selector Plugin root selector.
 */
const gotoFixture = async ( page, slug, selector ) => {
	const response = await page.goto( `/?pagename=${ slug }` );

	expect( response?.status() ).toBeLessThan( 400 );
	await expect( page.locator( selector ) ).toBeVisible();
};

/**
 * Return WCAG A/AA violations inside one plugin-owned component.
 *
 * Automated rules are a regression gate, not a claim that automation alone can
 * establish WCAG conformance. Keyboard, reflow and forced-colors journeys remain
 * separate assertions in this suite and in the RC4 manual matrix.
 *
 * @param {import('@playwright/test').Page} page     Browser page.
 * @param {string}                          selector Exact component selector.
 * @return {Promise<Array<Object>>} Compact violation evidence.
 */
const wcagViolations = async ( page, selector ) => {
	await page.addScriptTag( { path: axePath } );

	return page.evaluate( async ( { rootSelector, tags } ) => {
		const root = document.querySelector( rootSelector );

		if ( ! root ) {
			throw new Error( `Accessibility root not found: ${ rootSelector }` );
		}

		const result = await window.axe.run( root, {
			runOnly: {
				type: 'tag',
				values: tags,
			},
		} );

		return result.violations.map( ( violation ) => ( {
			help: violation.help,
			id: violation.id,
			impact: violation.impact,
			nodes: violation.nodes.map( ( node ) => node.target ),
		} ) );
	}, { rootSelector: selector, tags: wcagTags } );
};

test( 'has no automated WCAG A/AA violations in primary visitor components', async ( {
	page,
} ) => {
	const fixtures = [
		[ 'wpse-e2e-atomic-fields', '.wpse-add-to-calendar-block' ],
		[ 'wpse-e2e-composite-blocks', '.wpse-event-composite-block-list' ],
		[ 'wpse-e2e-composite-blocks', '.wpse-event-composite-block-calendar' ],
		[ 'wpse-e2e-composite-blocks', '.wpse-event-composite-block-details' ],
		[ 'wpse-e2e-calendar-filters', '[data-wpse-calendar]' ],
	];

	for ( const [ slug, selector ] of fixtures ) {
		await gotoFixture( page, slug, selector );
		expect(
			await wcagViolations( page, selector ),
			`${ slug } ${ selector }`,
		).toEqual( [] );
	}
} );

test( 'keeps compact filters keyboard-operable with visible restored focus', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 320, height: 800 } );
	await gotoFixture( page, 'wpse-e2e-calendar-filters', '[data-wpse-calendar]' );

	const filters = page.locator( '[data-wpse-calendar-filters]' );
	const toggle = filters.locator( '[data-wpse-filter-toggle]' );
	const panel = filters.locator( '[data-wpse-filter-panel]' );

	await toggle.focus();
	await expect( toggle ).toBeFocused();
	await page.keyboard.press( 'Enter' );
	await expect( panel ).toBeVisible();
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );

	const search = panel.getByRole( 'searchbox', { name: 'Search options' } ).first();

	await search.focus();
	await search.fill( 'Filter Option 11' );
	await expect( panel.getByLabel( 'E2E Filter Option 11' ) ).toBeVisible();
	await search.press( 'Escape' );
	await expect( panel ).not.toBeVisible();
	await expect( toggle ).toBeFocused();

	const focus = await toggle.evaluate( ( element ) => {
		const style = window.getComputedStyle( element );

		return {
			outlineStyle: style.outlineStyle,
			outlineWidth: Number.parseFloat( style.outlineWidth ),
		};
	} );

	expect( focus.outlineStyle ).not.toBe( 'none' );
	expect( focus.outlineWidth ).toBeGreaterThanOrEqual( 2 );
} );

test( 'reflows visitor components at 320 CSS pixels with enlarged text spacing', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 320, height: 800 } );
	await gotoFixture( page, 'wpse-e2e-composite-blocks', 'main' );
	await page.addStyleTag( {
		content: `
			* {
				letter-spacing: 0.12em !important;
				line-height: 1.5 !important;
				word-spacing: 0.16em !important;
			}
			p {
				margin-bottom: 2em !important;
			}
		`,
	} );

	const geometry = await page.evaluate( () => ( {
		documentClient: document.documentElement.clientWidth,
		documentScroll: document.documentElement.scrollWidth,
		roots: [ ...document.querySelectorAll( '[class*="wpse-event-composite-block-"]' ) ]
			.map( ( root ) => ( {
				className: root.className,
				client: root.clientWidth,
				scroll: root.scrollWidth,
				overflowing: [ ...root.querySelectorAll( '*' ) ]
					.filter( ( element ) => element.scrollWidth > element.clientWidth + 1 )
					.slice( 0, 8 )
					.map( ( element ) => ( {
						className: element.className,
						client: element.clientWidth,
						children: [ ...element.children ].map( ( child ) => ( {
							className: child.className,
							client: child.clientWidth,
							scroll: child.scrollWidth,
							text: child.textContent?.trim().slice( 0, 40 ),
						} ) ),
						display: window.getComputedStyle( element ).display,
						flexWrap: window.getComputedStyle( element ).flexWrap,
						scroll: element.scrollWidth,
						tag: element.tagName,
					} ) ),
			} ) ),
	} ) );

	expect( geometry.documentScroll ).toBeLessThanOrEqual( geometry.documentClient );
	for ( const root of geometry.roots ) {
		expect(
			root.scroll,
			`${ root.className }: ${ JSON.stringify( root.overflowing ) }`,
		).toBeLessThanOrEqual( root.client );
	}
} );

test( 'retains structure and focus indicators in forced-colors mode', async ( {
	page,
} ) => {
	await page.emulateMedia( { forcedColors: 'active', reducedMotion: 'reduce' } );
	await gotoFixture( page, 'wpse-e2e-calendar-filters', '[data-wpse-calendar]' );

	const calendar = page.locator( '[data-wpse-calendar]' );
	const next = calendar.getByRole( 'button', { name: 'Next' } );

	await next.focus();
	await expect( next ).toBeFocused();
	const focus = await next.evaluate( ( element ) => {
		const style = window.getComputedStyle( element );

		return {
			outlineStyle: style.outlineStyle,
			outlineWidth: Number.parseFloat( style.outlineWidth ),
		};
	} );

	expect( focus.outlineStyle ).not.toBe( 'none' );
	expect( focus.outlineWidth ).toBeGreaterThanOrEqual( 2 );
	const swatch = calendar.locator( '.wpse-event-category-swatch' );

	await expect( swatch ).toHaveCount( 1 );
	await expect( swatch ).toBeVisible();
	await expect( swatch ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect(
		calendar.getByRole( 'checkbox', { name: 'E2E Category' } ),
	).toBeVisible();
	expect( await wcagViolations( page, '[data-wpse-calendar]' ) ).toEqual( [] );
} );

test( 'has no automated WCAG A/AA violations in native and recurrence event controls', async ( {
	page,
} ) => {
	test.setTimeout( 120_000 );
	await login( page );
	const eventId = await page.evaluate( async () => {
		const date = new Date();

		date.setUTCDate( date.getUTCDate() + 30 );
		const start = date.toISOString().slice( 0, 10 );
		const event = await window.wp.apiFetch( {
			path: '/wp/v2/wpse_event',
			method: 'POST',
			data: {
				title: 'RC4 accessibility editor event',
				status: 'publish',
				meta: {
					_wpse_start_local: `${ start }T12:00`,
					_wpse_end_local: `${ start }T13:00`,
					_wpse_all_day: false,
					_wpse_timezone: 'Europe/Brussels',
					_wpse_event_status: 'scheduled',
				},
			},
		} );

		return event.id;
	} );

	try {
		await page.goto(
			`/wp-admin/post.php?post=${ eventId }&action=edit&wpse_e2e_classic_editor=1`,
		);
		await expect( page.locator( '[data-wpse-event-fields]' ) ).toBeVisible();
		expect(
			await wcagViolations( page, '[data-wpse-event-fields]' ),
		).toEqual( [] );

		await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit` );
		await expect.poll( () => page.evaluate( () =>
			Boolean(
				window.wpseRecurrenceEditor &&
					window.wp?.plugins?.getPlugin( 'wpse-recurrence-editor' ),
			),
		) ).toBe( true );

		const welcomeDialog = page.getByRole( 'dialog', { name: 'Welcome to the editor' } );
		let welcomeVisible = false;

		try {
			await welcomeDialog.waitFor( { state: 'visible', timeout: 3000 } );
			welcomeVisible = true;
		} catch {
			// The isolated editor preference may already have dismissed the guide.
		}

		if ( welcomeVisible ) {
			await page.waitForTimeout( 1000 );
			await page.evaluate( async () => {
				const result = window.wp?.data?.dispatch( 'core/preferences' )?.set?.(
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

		await expect( page.locator( '.wpse-recurrence-editor' ) ).toBeVisible();
		expect(
			await wcagViolations( page, '.wpse-recurrence-editor' ),
		).toEqual( [] );
	} finally {
		await page.evaluate( async ( id ) => {
			await window.wp.apiFetch( {
				path: `/wp/v2/wpse_event/${ id }?force=true`,
				method: 'DELETE',
			} );
		}, eventId );
	}
} );
