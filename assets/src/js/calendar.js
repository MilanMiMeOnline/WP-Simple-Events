import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';

const calendarInstances = new WeakMap();
let elementorHookRegistered = false;

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
	const controls = root.querySelectorAll(
		`[data-wpse-calendar-filter="${ type }"]`,
	);

	if ( ! controls.length ) {
		return Array.isArray( fallback ) ? fallback : [];
	}

	return Array.from( controls ).flatMap( ( control ) => {
		if ( control.tagName === 'SELECT' ) {
			return Array.from( control.selectedOptions, ( option ) => option.value );
		}

		if ( control.matches( 'input[type="checkbox"]' ) && control.checked ) {
			return [ control.value ];
		}

		return [];
	} );
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
 * @param {Object}           config      Calendar configuration.
 * @param {HTMLElement}      root        Calendar root.
 * @param {boolean}          submitted   Whether visitor choices should be encoded.
 * @param {'push'|'replace'} historyMode Browser-history operation.
 */
const updateUrl = ( config, root, submitted = true, historyMode = 'push' ) => {
	const url = new URL( window.location.href );
	const filterKeys = [
		[ config.categoryKey, 'category' ],
		[ config.tagKey, 'tag' ],
	];

	url.searchParams.delete( config.applyKey );
	url.searchParams.delete( `${ config.applyKey }[]` );

	if ( submitted ) {
		url.searchParams.set( config.applyKey, '1' );
	}

	filterKeys.forEach( ( [ key, type ] ) => {
		url.searchParams.delete( key );
		url.searchParams.delete( `${ key }[]` );

		const fallback = type === 'category' ? config.categories : config.tags;

		( submitted ? selectedValues( root, type, fallback ) : [] ).forEach( ( value ) => {
			url.searchParams.append( `${ key }[]`, value );
		} );
	} );

	if ( url.href !== window.location.href ) {
		const method = historyMode === 'replace' ? 'replaceState' : 'pushState';

		window.history[ method ]( {}, '', url );
	}
};

/**
 * Synchronize one calendar's controls from its namespaced browser URL state.
 *
 * @param {Object}      config Calendar configuration.
 * @param {HTMLElement} root   Calendar root.
 * @return {boolean} Whether this calendar's selection changed.
 */
const restoreFiltersFromUrl = ( config, root ) => {
	const before = {
		category: selectedValues( root, 'category', config.categories ),
		tag: selectedValues( root, 'tag', config.tags ),
	};
	const params = new URL( window.location.href ).searchParams;
	const submitted = params.has( config.applyKey ) || params.has( `${ config.applyKey }[]` );
	const selections = {
		category: submitted
			? [ ...params.getAll( config.categoryKey ), ...params.getAll( `${ config.categoryKey }[]` ) ]
			: config.initialCategories,
		tag: submitted
			? [ ...params.getAll( config.tagKey ), ...params.getAll( `${ config.tagKey }[]` ) ]
			: config.initialTags,
	};

	root.querySelectorAll( '[data-wpse-calendar-filter]' ).forEach( ( control ) => {
		const selected = selections[ control.dataset.wpseCalendarFilter ] || [];

		if ( control.tagName === 'SELECT' ) {
			Array.from( control.options ).forEach( ( option ) => {
				option.selected = selected.includes( option.value );
			} );
		} else if ( control.matches( 'input[type="checkbox"]' ) ) {
			control.checked = selected.includes( control.value );
		}
	} );

	root.querySelector( '[data-wpse-event-filters]' )?.dispatchEvent(
		new CustomEvent( 'wpse:filters-updated' ),
	);

	return ! sameSelection( before.category, selections.category ) ||
		! sameSelection( before.tag, selections.tag );
};

/**
 * Restore configured initial filters in one calendar instance.
 *
 * @param {Object}      config Calendar configuration.
 * @param {HTMLElement} root   Calendar root.
 */
const restoreInitialFilters = ( config, root ) => {
	root.querySelectorAll( '[data-wpse-calendar-filter]' ).forEach( ( control ) => {
		const type = control.dataset.wpseCalendarFilter;
		const initial = type === 'category'
			? config.initialCategories
			: config.initialTags;
		const selected = Array.isArray( initial ) && initial.includes( control.value );

		if ( control.tagName === 'SELECT' ) {
			Array.from( control.options ).forEach( ( option ) => {
				option.selected = Array.isArray( initial ) && initial.includes( option.value );
			} );
		} else if ( control.matches( 'input[type="checkbox"]' ) ) {
			control.checked = selected;
		}
	} );
};

/**
 * Compare two filter selections without relying on their option order.
 *
 * @param {Array<string>} first  First selection.
 * @param {Array<string>} second Second selection.
 * @return {boolean} Whether both selections contain the same values.
 */
const sameSelection = ( first, second ) => JSON.stringify( [ ...first ].sort() ) ===
	JSON.stringify( [ ...second ].sort() );

/**
 * Repair FullCalendar after a hidden or resized integration container becomes
 * measurable and follow configured views when a responsive builder canvas
 * crosses the mobile breakpoint.
 *
 * @param {HTMLElement} canvas      Calendar canvas.
 * @param {Calendar}    calendar    FullCalendar instance.
 * @param {string}      desktopView Configured desktop view.
 * @param {string}      mobileView  Configured mobile view.
 */
const observeCalendarSize = ( canvas, calendar, desktopView, mobileView ) => {
	const cleanups = [];
	const viewport = window.matchMedia( '(max-width: 599px)' );
	const handleViewportChange = ( event ) => {
		const desiredView = event.matches && mobileView
			? mobileView
			: desktopView;

		if ( desiredView && calendar.view.type !== desiredView ) {
			calendar.changeView( desiredView );
		}
	};

	if ( typeof viewport.addEventListener === 'function' ) {
		viewport.addEventListener( 'change', handleViewportChange );
		cleanups.push( () =>
			viewport.removeEventListener( 'change', handleViewportChange ),
		);
	} else if ( typeof viewport.addListener === 'function' ) {
		viewport.addListener( handleViewportChange );
		cleanups.push( () => viewport.removeListener( handleViewportChange ) );
	}

	if ( typeof window.ResizeObserver === 'function' ) {
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
		cleanups.push( () => observer.disconnect() );
	}

	return () => cleanups.forEach( ( cleanup ) => cleanup() );
};

/**
 * Progressively enhance one server-rendered calendar instance.
 *
 * @param {HTMLElement} root Calendar root.
 */
const initializeCalendar = ( root ) => {
	const configSource = root.dataset.wpseCalendar;
	const canvas = root.querySelector( '[data-wpse-calendar-canvas]' );
	const existing = calendarInstances.get( root );

	if (
		existing &&
		existing.canvas === canvas &&
		existing.configSource === configSource
	) {
		existing.calendar.updateSize();
		return;
	}

	existing?.destroy();

	let config;

	try {
		config = JSON.parse( configSource );
	} catch {
		return;
	}

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
	const toolbarStart = [
		config.showNavigation ? 'prev,next' : '',
		config.showToday ? 'today' : '',
	].filter( Boolean ).join( ' ' );

	const calendar = new Calendar( canvas, {
		plugins: [ dayGridPlugin, listPlugin ],
		initialView,
		initialDate: config.initialDate || undefined,
		firstDay: config.firstDay,
		timeZone: 'local',
		eventTimeFormat: config.eventTimeFormat,
		height: 'auto',
		headerToolbar: {
			start: toolbarStart,
			center: 'title',
			end: config.showViewSwitcher ? 'dayGridMonth,listMonth' : '',
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

			const hasActiveFilters = ! sameSelection(
				selectedValues( root, 'category', config.categories ),
				config.initialCategories,
			) || ! sameSelection(
				selectedValues( root, 'tag', config.tags ),
				config.initialTags,
			);

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
			const isListView = argument.view.type.startsWith( 'list' );
			const wrapper = document.createElement(
				isListView && argument.event.url ? 'a' : 'span',
			);
			const title = document.createElement( 'span' );

			if ( wrapper.tagName === 'A' ) {
				wrapper.href = argument.event.url;
			}

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
		eventDidMount: ( argument ) => {
			const isListView = argument.view.type.startsWith( 'list' );
			const row = isListView
				? argument.el.closest( '.fc-list-event' )
				: null;
			const color = argument.event.backgroundColor;
			const hasResolvedColor = /^#[0-9a-f]{6}$/i.test( color );

			if ( row && hasResolvedColor ) {
				row.classList.add( 'wpse-calendar-list-event-has-color' );
				row.style.setProperty( '--wpse-event-color', color );
			}

			if (
				hasResolvedColor &&
				argument.el.classList.contains( 'fc-daygrid-dot-event' )
			) {
				argument.el.classList.add(
					'wpse-calendar-daygrid-event-has-color',
				);
				argument.el.style.setProperty( '--wpse-event-color', color );
			}

			if (
				! isListView ||
				argument.event.allDay ||
				! argument.event.start ||
				! argument.event.end ||
				argument.event.start.toDateString() === argument.event.end.toDateString()
			) {
				return;
			}

			const time = row?.querySelector( '.fc-list-event-time' );

			if ( ! time ) {
				return;
			}

			const displayedTime = time.textContent.trim();

			if ( argument.isStart && ! argument.isEnd ) {
				time.textContent = config.strings.startsAt.replace(
					'%s',
					displayedTime,
				);
			} else if ( ! argument.isStart && argument.isEnd ) {
				time.textContent = config.strings.endsAt.replace(
					'%s',
					displayedTime,
				);
			} else if ( ! argument.isStart && ! argument.isEnd ) {
				time.textContent = config.strings.continues;
			}
		},
	} );

	// FullCalendar must be measurable during its initial render. The server-side
	// fallback remains available until the first event request succeeds.
	canvas.hidden = false;
	calendar.render();
	const stopObservingSize = observeCalendarSize(
		canvas,
		calendar,
		config.initialView,
		config.mobileView,
	);
	const emptyActionButton = emptyAction.querySelector( 'button' );
	const handleFilterSubmit = ( event ) => {
		event.preventDefault();
		updateUrl( config, root );
		calendar.refetchEvents();
	};
	const handleReset = ( event ) => {
		event?.preventDefault();
		restoreInitialFilters( config, root );
		filters?.dispatchEvent( new CustomEvent( 'wpse:filters-updated' ) );
		updateUrl( config, root, false );
		calendar.refetchEvents();
	};
	const handleHistoryChange = () => {
		if ( restoreFiltersFromUrl( config, root ) ) {
			calendar.refetchEvents();
		}
	};

	if ( filters ) {
		filters.addEventListener( 'submit', handleFilterSubmit );
	}

	emptyActionButton?.addEventListener( 'click', handleReset );
	window.addEventListener( 'popstate', handleHistoryChange );

	const instance = {
		calendar,
		canvas,
		configSource,
		destroy: () => {
			stopObservingSize();
			filters?.removeEventListener( 'submit', handleFilterSubmit );
			emptyActionButton?.removeEventListener( 'click', handleReset );
			window.removeEventListener( 'popstate', handleHistoryChange );
			calendar.destroy();

			if ( calendarInstances.get( root ) === instance ) {
				calendarInstances.delete( root );
			}
		},
	};

	calendarInstances.set( root, instance );
};

/**
 * Progressively enhance every calendar inside one document or widget scope.
 *
 * @param {Document|Element|Array<Element>|Object} scope Host-provided scope.
 */
export const initializeCalendars = ( scope ) => {
	const scopeElement = scope?.jquery ? scope[ 0 ] : scope?.[ 0 ] ?? scope;

	if ( ! scopeElement || typeof scopeElement.querySelectorAll !== 'function' ) {
		return;
	}

	if ( scopeElement.matches?.( '[data-wpse-calendar]' ) ) {
		initializeCalendar( scopeElement );
	}

	scopeElement
		.querySelectorAll( '[data-wpse-calendar]' )
		.forEach( initializeCalendar );
};

// Divi and other visual builders can insert server-rendered calendar markup
// after this bundle's initial document scan. Expose the same idempotent
// initializer instead of duplicating FullCalendar setup in each integration.
window.wpseInitializeCalendars = initializeCalendars;

/** Bind the calendar initializer to Elementor only when its public hook exists. */
const registerElementorHook = () => {
	if ( elementorHookRegistered ) {
		return;
	}

	const hooks = window.elementorFrontend?.hooks;

	if ( ! hooks || typeof hooks.addAction !== 'function' ) {
		return;
	}

	hooks.addAction(
		'frontend/element_ready/wpse-event-calendar.default',
		initializeCalendars,
	);
	elementorHookRegistered = true;
};

initializeCalendars( document );
registerElementorHook();

if ( ! elementorHookRegistered ) {
	window.addEventListener( 'elementor/frontend/init', registerElementorHook, {
		once: true,
	} );

	if ( typeof window.jQuery === 'function' ) {
		window
			.jQuery( window )
			.one( 'elementor/frontend/init.wpseCalendar', registerElementorHook );
	}
}
