import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';

/**
 * Format a translated string containing one integer placeholder.
 *
 * @param {string} template Translated template.
 * @param {number} value    Number to interpolate.
 * @return {string} Formatted text.
 */
const formatNumber = ( template, value ) =>
	template.replace( '%d', String( value ) );

/**
 * Return selected values for one filter type.
 *
 * @param {HTMLElement}   root     Calendar root.
 * @param {string}        type     Stable filter type.
 * @param {Array<string>} fallback Values used when no visitor control exists.
 * @return {Array<string>} Selected slugs.
 */
const selectedValues = ( root, type, fallback = [] ) => {
	const select = root.querySelector(
		`[data-wpse-calendar-filter="${ type }"]`,
	);

	if ( ! select ) {
		return Array.isArray( fallback ) ? fallback : [];
	}

	return Array.from( select.selectedOptions, ( option ) => option.value );
};

/**
 * Fetch a bounded number of result pages for the visible interval.
 *
 * @param {Object}      config Calendar configuration.
 * @param {HTMLElement} root   Calendar root.
 * @param {Object}      range  FullCalendar fetch range.
 * @return {Promise<{events: Array<Object>, truncated: boolean}>} Feed result.
 */
const fetchEvents = async ( config, root, range ) => {
	const endpoint = new URL( config.endpoint, window.location.href );
	const events = [];
	let totalPages = 1;

	endpoint.searchParams.set( 'start', range.startStr );
	endpoint.searchParams.set( 'end', range.endStr );
	endpoint.searchParams.set( 'per_page', String( config.perPage ) );

	const categories = selectedValues( root, 'category', config.categories );
	const tags = selectedValues( root, 'tag', config.tags );

	if ( categories.length ) {
		endpoint.searchParams.set( 'categories', categories.join( ',' ) );
	}

	if ( tags.length ) {
		endpoint.searchParams.set( 'tags', tags.join( ',' ) );
	}

	for ( let page = 1; page <= Math.min( totalPages, config.maxPages ); page++ ) {
		endpoint.searchParams.set( 'page', String( page ) );

		const response = await fetch( endpoint, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			throw new Error( `Calendar feed returned ${ response.status }.` );
		}

		const pageEvents = await response.json();

		if ( ! Array.isArray( pageEvents ) ) {
			throw new TypeError( 'Calendar feed did not return a collection.' );
		}

		events.push( ...pageEvents );
		totalPages = Math.max(
			1,
			Number.parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1,
		);
	}

	return {
		events,
		truncated: totalPages > config.maxPages,
	};
};

/**
 * Reflect active filters in the URL without reloading the page.
 *
 * @param {Object}      config Calendar configuration.
 * @param {HTMLElement} root   Calendar root.
 */
const updateUrl = ( config, root ) => {
	const url = new URL( window.location.href );
	const filterKeys = [
		[ config.categoryKey, 'category' ],
		[ config.tagKey, 'tag' ],
	];

	filterKeys.forEach( ( [ key, type ] ) => {
		url.searchParams.delete( key );
		url.searchParams.delete( `${ key }[]` );

		const fallback = type === 'category' ? config.categories : config.tags;

		selectedValues( root, type, fallback ).forEach( ( value ) => {
			url.searchParams.append( `${ key }[]`, value );
		} );
	} );

	window.history.replaceState( {}, '', url );
};

/**
 * Clear all filters in one calendar instance.
 *
 * @param {HTMLElement} root Calendar root.
 */
const clearFilters = ( root ) => {
	root.querySelectorAll( '[data-wpse-calendar-filter]' ).forEach( ( select ) => {
		Array.from( select.options ).forEach( ( option ) => {
			option.selected = false;
		} );
	} );
};

/**
 * Repair FullCalendar after a hidden or resized integration container becomes
 * measurable. This covers tabs, accordions and editor preview containers.
 *
 * @param {HTMLElement} canvas   Calendar canvas.
 * @param {Calendar}    calendar FullCalendar instance.
 */
const observeCalendarSize = ( canvas, calendar ) => {
	if ( typeof window.ResizeObserver !== 'function' ) {
		return;
	}

	let previousWidth = canvas.getBoundingClientRect().width;
	const observer = new window.ResizeObserver( ( entries ) => {
		if ( ! canvas.isConnected ) {
			observer.disconnect();
			return;
		}

		const width = entries[ 0 ]?.contentRect.width ?? 0;

		if ( width <= 0 || Math.abs( width - previousWidth ) < 1 ) {
			previousWidth = width;
			return;
		}

		previousWidth = width;
		window.requestAnimationFrame( () => {
			if ( canvas.isConnected && canvas.getBoundingClientRect().width > 0 ) {
				calendar.updateSize();
			}
		} );
	} );

	observer.observe( canvas );
};

/**
 * Progressively enhance one server-rendered calendar instance.
 *
 * @param {HTMLElement} root Calendar root.
 */
const initializeCalendar = ( root ) => {
	let config;

	try {
		config = JSON.parse( root.dataset.wpseCalendar );
	} catch {
		return;
	}

	const canvas = root.querySelector( '[data-wpse-calendar-canvas]' );
	const status = root.querySelector( '[data-wpse-calendar-status]' );
	const emptyAction = root.querySelector(
		'[data-wpse-calendar-empty-action]',
	);

	if ( ! canvas || ! status || ! emptyAction ) {
		return;
	}

	const filters = root.querySelector( '[data-wpse-calendar-filters]' );
	let lastResult = { events: [], truncated: false };
	let loadFailed = false;
	const initialView =
		window.matchMedia( '(max-width: 599px)' ).matches && config.mobileView
			? config.mobileView
			: config.initialView;

	const calendar = new Calendar( canvas, {
		plugins: [ dayGridPlugin, listPlugin ],
		initialView,
		firstDay: config.firstDay,
		height: 'auto',
		headerToolbar: {
			start: 'prev,next today',
			center: 'title',
			end: 'dayGridMonth,listMonth',
		},
		buttonText: {
			prev: config.strings.previous,
			next: config.strings.next,
			today: config.strings.today,
			month: config.strings.month,
			list: config.strings.list,
		},
		locale: {
			code: config.locale,
			buttonHints: {
				prev: config.strings.previous,
				next: config.strings.next,
				today: config.strings.today,
			},
			viewHint: config.strings.viewHint.replace( '%s', '$0' ),
			moreLinkHint: ( count ) =>
				formatNumber( config.strings.more, count ),
		},
		moreLinkContent: ( argument ) =>
			formatNumber( config.strings.more, argument.num ),
		events: async ( range, success, failure ) => {
			loadFailed = false;
			status.textContent = config.strings.loading;
			emptyAction.hidden = true;

			try {
				lastResult = await fetchEvents( config, root, range );
				success( lastResult.events );
				root.classList.add( 'is-ready' );
			} catch ( error ) {
				loadFailed = true;
				status.textContent = config.strings.loadError;
				root.classList.remove( 'is-ready' );
				canvas.hidden = true;
				failure( error );
			}
		},
		eventsSet: ( events ) => {
			if ( loadFailed ) {
				return;
			}

			if ( lastResult.truncated ) {
				status.textContent = formatNumber(
					config.strings.tooMany,
					events.length,
				);
			} else if ( events.length === 0 ) {
				status.textContent = config.strings.noEvents;
			} else if ( events.length === 1 ) {
				status.textContent = config.strings.oneEvent;
			} else {
				status.textContent = formatNumber(
					config.strings.manyEvents,
					events.length,
				);
			}

			const hasActiveFilters =
				selectedValues( root, 'category' ).length > 0 ||
				selectedValues( root, 'tag' ).length > 0;

			emptyAction.hidden =
				events.length !== 0 ||
				! config.filtersEnabled ||
				! hasActiveFilters;
		},
		eventClassNames: ( argument ) => {
			const eventStatus = argument.event.extendedProps.status;

			return eventStatus === 'cancelled' || eventStatus === 'postponed'
				? [ `wpse-calendar-event-${ eventStatus }` ]
				: [];
		},
		eventContent: ( argument ) => {
			const eventStatus = argument.event.extendedProps.status;
			const wrapper = document.createElement( 'span' );
			const title = document.createElement( 'span' );

			title.textContent = argument.event.title;
			wrapper.append( title );

			if ( eventStatus === 'cancelled' || eventStatus === 'postponed' ) {
				const statusLabel = document.createElement( 'span' );
				statusLabel.className = 'wpse-calendar-event-status';
				statusLabel.textContent =
					eventStatus === 'cancelled'
						? config.strings.cancelled
						: config.strings.postponed;
				wrapper.append( statusLabel );
			}

			return { domNodes: [ wrapper ] };
		},
	} );

	// FullCalendar must be measurable during its initial render. The server-side
	// fallback remains available until the first event request succeeds.
	canvas.hidden = false;
	calendar.render();
	observeCalendarSize( canvas, calendar );

	if ( filters ) {
		filters.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			updateUrl( config, root );
			calendar.refetchEvents();
		} );
	}

	emptyAction.querySelector( 'button' )?.addEventListener( 'click', () => {
		clearFilters( root );
		updateUrl( config, root );
		calendar.refetchEvents();
	} );
};

document.querySelectorAll( '[data-wpse-calendar]' ).forEach( initializeCalendar );
