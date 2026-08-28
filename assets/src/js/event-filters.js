const filterInstances = new WeakMap();
const SEARCH_THRESHOLD = 10;
const COMPACT_WIDTH = 599;
let elementorHookRegistered = false;

/**
 * Return the closest component whose own width controls filter reflow.
 *
 * @param {HTMLFormElement} form Event filter form.
 * @return {HTMLElement} Width-owning component.
 */
const componentRoot = ( form ) =>
	form.closest( '.wpse-calendar, .wpse-events, .wpse-event-archive' ) || form;

/**
 * Return all taxonomy checkboxes owned by one form.
 *
 * @param {HTMLFormElement} form Event filter form.
 * @return {Array<HTMLInputElement>} Taxonomy checkboxes.
 */
const taxonomyChoices = ( form ) =>
	Array.from( form.querySelectorAll( '[data-wpse-filter-group] input[type="checkbox"]' ) );

/**
 * Keep the compact trigger count synchronized with visitor choices.
 *
 * @param {HTMLFormElement} form Event filter form.
 */
const updateCount = ( form ) => {
	const count = taxonomyChoices( form ).filter( ( input ) => input.checked ).length;
	const output = form.querySelector( '[data-wpse-filter-count]' );

	if ( ! output ) {
		return;
	}

	output.textContent = `(${ count })`;
	output.hidden = count === 0;
};

/**
 * Add bounded client-side search to a long option group.
 *
 * @param {HTMLFieldSetElement} group Taxonomy choice group.
 */
const enhanceOptionSearch = ( group ) => {
	const options = Array.from(
		group.querySelectorAll( '.wpse-events-filter-option' ),
	);

	if ( options.length <= SEARCH_THRESHOLD ) {
		return;
	}

	const optionList = group.querySelector( '.wpse-events-filter-options' );
	const firstInput = group.querySelector( 'input[type="checkbox"]' );

	if ( ! optionList || ! firstInput ) {
		return;
	}

	const searchId = `${ firstInput.id || 'wpse-filter' }-search`;
	const optionListId = `${ firstInput.id || 'wpse-filter' }-options`;
	const searchLabel = group.dataset.wpseFilterSearchLabel;

	if ( ! searchLabel ) {
		return;
	}

	const wrapper = document.createElement( 'div' );
	const label = document.createElement( 'label' );
	const input = document.createElement( 'input' );
	const status = document.createElement( 'span' );

	wrapper.className = 'wpse-events-filter-search';
	label.className = 'wpse-screen-reader-text';
	label.htmlFor = searchId;
	label.textContent = searchLabel;
	input.id = searchId;
	input.type = 'search';
	input.autocomplete = 'off';
	input.placeholder = label.textContent;
	optionList.id ||= optionListId;
	input.setAttribute( 'aria-controls', optionList.id );
	status.className = 'wpse-screen-reader-text';
	status.setAttribute( 'role', 'status' );
	status.setAttribute( 'aria-live', 'polite' );

	const filter = () => {
		const query = input.value.trim().toLocaleLowerCase();
		let visible = 0;

		options.forEach( ( option ) => {
			const checkbox = option.querySelector( 'input[type="checkbox"]' );
			const matches = option.textContent.toLocaleLowerCase().includes( query );
			const show = ! query || matches || Boolean( checkbox?.checked );

			option.hidden = ! show;
			visible += show ? 1 : 0;
		} );

		status.textContent = visible === 0
			? group.dataset.wpseFilterSearchEmpty || ''
			: '';
	};

	input.addEventListener( 'input', filter );
	group.addEventListener( 'change', filter );
	wrapper.append( label, input, status );
	optionList.before( wrapper );
};

/**
 * Progressively enhance one valid GET filter form.
 *
 * @param {HTMLFormElement} form Event filter form.
 */
const initializeFilterForm = ( form ) => {
	if ( filterInstances.has( form ) ) {
		return;
	}

	const toggle = form.querySelector( '[data-wpse-filter-toggle]' );
	const panel = form.querySelector( '[data-wpse-filter-panel]' );

	if ( ! toggle || ! panel ) {
		return;
	}

	const host = componentRoot( form );
	const disclosure = [ 'auto', 'open', 'closed' ].includes(
		form.dataset.wpseFilterDisclosure,
	)
		? form.dataset.wpseFilterDisclosure
		: 'auto';
	const cleanups = [];
	let compact = false;
	let visitorExpanded = disclosure === 'open';

	const setExpanded = ( expanded, focusToggle = false ) => {
		panel.hidden = ! expanded;
		toggle.setAttribute( 'aria-expanded', String( expanded ) );

		if ( focusToggle ) {
			toggle.focus();
		}
	};

	const applyLayout = ( width ) => {
		const nextCompact = width <= COMPACT_WIDTH;

		const showToggle = disclosure === 'closed' || nextCompact;

		if ( nextCompact === compact && toggle.hidden === ! showToggle ) {
			return;
		}

		compact = nextCompact;
		host.classList.toggle( 'wpse-filters-compact', compact );
		toggle.hidden = ! showToggle;

		if ( showToggle ) {
			setExpanded( visitorExpanded );
		} else {
			setExpanded( true );
		}
	};

	const handleToggle = () => {
		visitorExpanded = toggle.getAttribute( 'aria-expanded' ) !== 'true';
		setExpanded( visitorExpanded );
	};
	const handleEscape = ( event ) => {
		if ( ! toggle.hidden && event.key === 'Escape' && ! panel.hidden ) {
			event.preventDefault();
			visitorExpanded = false;
			setExpanded( false, true );
		}
	};
	const handleChange = () => updateCount( form );

	toggle.addEventListener( 'click', handleToggle );
	panel.addEventListener( 'keydown', handleEscape );
	form.addEventListener( 'change', handleChange );
	form.addEventListener( 'wpse:filters-updated', handleChange );
	cleanups.push( () => toggle.removeEventListener( 'click', handleToggle ) );
	cleanups.push( () => panel.removeEventListener( 'keydown', handleEscape ) );
	cleanups.push( () => form.removeEventListener( 'change', handleChange ) );
	cleanups.push( () => form.removeEventListener( 'wpse:filters-updated', handleChange ) );

	form.querySelectorAll( '[data-wpse-filter-group]' ).forEach( enhanceOptionSearch );
	updateCount( form );
	applyLayout( host.getBoundingClientRect().width );

	if ( typeof window.ResizeObserver === 'function' ) {
		const observer = new window.ResizeObserver( ( entries ) => {
			if ( ! form.isConnected ) {
				observer.disconnect();
				filterInstances.delete( form );
				return;
			}

			applyLayout( entries[ 0 ]?.contentRect.width ?? host.getBoundingClientRect().width );
		} );

		observer.observe( host );
		cleanups.push( () => observer.disconnect() );
	} else {
		const handleResize = () => {
			if ( ! form.isConnected ) {
				window.removeEventListener( 'resize', handleResize );
				filterInstances.delete( form );
				return;
			}

			applyLayout( host.getBoundingClientRect().width );
		};

		window.addEventListener( 'resize', handleResize );
		cleanups.push( () => window.removeEventListener( 'resize', handleResize ) );
	}

	filterInstances.set( form, {
		destroy: () => {
			cleanups.forEach( ( cleanup ) => cleanup() );
			filterInstances.delete( form );
		},
	} );
};

/**
 * Initialize event filters inside one document or builder-provided scope.
 *
 * @param {Document|Element|Array<Element>|Object} scope Host-provided scope.
 */
export const initializeEventFilters = ( scope ) => {
	const scopeElement = scope?.jquery ? scope[ 0 ] : scope?.[ 0 ] ?? scope;

	if ( ! scopeElement || typeof scopeElement.querySelectorAll !== 'function' ) {
		return;
	}

	if ( scopeElement.matches?.( '[data-wpse-event-filters]' ) ) {
		initializeFilterForm( scopeElement );
	}

	scopeElement
		.querySelectorAll( '[data-wpse-event-filters]' )
		.forEach( initializeFilterForm );
};

/** Bind dynamic Elementor renderers without taking an Elementor dependency. */
const registerElementorHook = () => {
	if ( elementorHookRegistered ) {
		return;
	}

	const hooks = window.elementorFrontend?.hooks;

	if ( ! hooks || typeof hooks.addAction !== 'function' ) {
		return;
	}

	[
		'frontend/element_ready/wpse-event-list.default',
		'frontend/element_ready/wpse-event-calendar.default',
	].forEach( ( hook ) => hooks.addAction( hook, initializeEventFilters ) );
	elementorHookRegistered = true;
};

window.wpseInitializeEventFilters = initializeEventFilters;
initializeEventFilters( document );
registerElementorHook();

if ( ! elementorHookRegistered ) {
	window.addEventListener( 'elementor/frontend/init', registerElementorHook, {
		once: true,
	} );
}
