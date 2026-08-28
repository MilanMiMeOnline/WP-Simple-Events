/* global wpseEventFieldBlocks */

/**
 * Shared editor interface for the server-rendered atomic event-field blocks.
 */
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const {
	BaseControl,
	ColorPalette,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} = wp.components;
const { createElement: el, Fragment } = wp.element;
const { __ } = wp.i18n;
const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;

const commonAttributes = () => ( {
	eventId: { type: 'integer', default: 0 },
} );
const textSupports = () => ( {
	html: false,
	anchor: true,
	align: [ 'wide', 'full' ],
	color: { text: true, link: true },
	spacing: { margin: true },
	typography: { fontSize: true, lineHeight: true },
} );
const eventOptions = [
	{ label: __( 'Current event context', 'mime-simple-events-calendar' ), value: '0' },
	...Object.entries( wpseEventFieldBlocks.events || {} ).map(
		( [ value, label ] ) => ( { label, value: String( value ) } ),
	),
];

const fieldDefinitions = [
	{
		name: 'wpse/event-title',
		title: __( 'Event Title', 'mime-simple-events-calendar' ),
		description: __( 'Display the title of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'heading',
		attributes: {
			...commonAttributes(),
			heading: { type: 'string', default: 'h2' },
			link: { type: 'boolean', default: false },
		},
		supports: textSupports(),
		controls: 'title',
	},
	{
		name: 'wpse/event-featured-image',
		title: __( 'Event Featured Image', 'mime-simple-events-calendar' ),
		description: __( 'Display the featured image of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'format-image',
		attributes: {
			...commonAttributes(),
			imageSize: { type: 'string', default: 'large' },
			altMode: { type: 'string', default: 'attachment' },
			link: { type: 'boolean', default: false },
		},
		supports: {
			html: false,
			anchor: true,
			align: [ 'wide', 'full' ],
			spacing: { margin: true },
		},
		controls: 'image',
	},
	{
		name: 'wpse/event-date-time',
		title: __( 'Event Date & Time', 'mime-simple-events-calendar' ),
		description: __( 'Display the localized date, time and optional timezone of an event.', 'mime-simple-events-calendar' ),
		icon: 'calendar-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Date and time:', 'mime-simple-events-calendar' ),
	},
	{
		name: 'wpse/event-status',
		title: __( 'Event Status', 'mime-simple-events-calendar' ),
		description: __( 'Display a cancelled or postponed event status.', 'mime-simple-events-calendar' ),
		icon: 'warning',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-venue',
		title: __( 'Event Venue', 'mime-simple-events-calendar' ),
		description: __( 'Display the venue of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'location-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Location:', 'mime-simple-events-calendar' ),
	},
	{
		name: 'wpse/event-address',
		title: __( 'Event Address', 'mime-simple-events-calendar' ),
		description: __( 'Display the postal address of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'admin-home',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-location-link',
		title: __( 'Event Location Link', 'mime-simple-events-calendar' ),
		description: __( 'Display the route or location link saved on an event.', 'mime-simple-events-calendar' ),
		icon: 'admin-links',
		attributes: {
			...commonAttributes(),
			linkText: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'locationLink',
	},
	{
		name: 'wpse/event-content',
		title: __( 'Event Content', 'mime-simple-events-calendar' ),
		description: __( 'Display the main content of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'text-page',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-excerpt',
		title: __( 'Event Excerpt', 'mime-simple-events-calendar' ),
		description: __( 'Display the excerpt of the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'excerpt-view',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-external-action',
		title: __( 'External Event Action', 'mime-simple-events-calendar' ),
		description: __( 'Display the external information or registration link saved on an event.', 'mime-simple-events-calendar' ),
		icon: 'external',
		attributes: {
			...commonAttributes(),
			linkText: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'externalAction',
	},
	{
		name: 'wpse/event-categories',
		title: __( 'Event Categories', 'mime-simple-events-calendar' ),
		description: __( 'Display linked categories for the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'category',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Categories:', 'mime-simple-events-calendar' ),
	},
	{
		name: 'wpse/event-tags',
		title: __( 'Event Tags', 'mime-simple-events-calendar' ),
		description: __( 'Display linked tags for the current or selected event.', 'mime-simple-events-calendar' ),
		icon: 'tag',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Tags:', 'mime-simple-events-calendar' ),
	},
];

const sourceControls = ( attributes, setAttributes ) =>
	el( SelectControl, {
		label: __( 'Event source', 'mime-simple-events-calendar' ),
		help: __( 'Select a public event for a static page, or use the current event supplied by a template or query.', 'mime-simple-events-calendar' ),
		value: String( attributes.eventId || 0 ),
		options: eventOptions,
		onChange: ( value ) => {
			const eventId = Number.parseInt( value, 10 );
			setAttributes( { eventId: Number.isSafeInteger( eventId ) && eventId > 0 ? eventId : 0 } );
		},
	} );

const fieldControls = ( definition, attributes, setAttributes ) => {
	switch ( definition.controls ) {
		case 'title':
			return [
				el( SelectControl, {
					key: 'heading',
					label: __( 'HTML tag', 'mime-simple-events-calendar' ),
					value: attributes.heading,
					options: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
					onChange: ( heading ) => setAttributes( { heading } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'mime-simple-events-calendar' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'image':
			return [
				el( SelectControl, {
					key: 'imageSize',
					label: __( 'Image size', 'mime-simple-events-calendar' ),
					value: attributes.imageSize,
					options: [
						{ label: __( 'Thumbnail', 'mime-simple-events-calendar' ), value: 'thumbnail' },
						{ label: __( 'Medium', 'mime-simple-events-calendar' ), value: 'medium' },
						{ label: __( 'Medium large', 'mime-simple-events-calendar' ), value: 'medium_large' },
						{ label: __( 'Large', 'mime-simple-events-calendar' ), value: 'large' },
						{ label: __( 'Full size', 'mime-simple-events-calendar' ), value: 'full' },
					],
					onChange: ( imageSize ) => setAttributes( { imageSize } ),
				} ),
				el( SelectControl, {
					key: 'altMode',
					label: __( 'Alternative text', 'mime-simple-events-calendar' ),
					value: attributes.altMode,
					options: [
						{ label: __( 'Use Media Library alt text', 'mime-simple-events-calendar' ), value: 'attachment' },
						{ label: __( 'Decorative (empty alt)', 'mime-simple-events-calendar' ), value: 'decorative' },
					],
					onChange: ( altMode ) => setAttributes( { altMode } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'mime-simple-events-calendar' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'label':
			return [
				el( ToggleControl, {
					key: 'showLabel',
					label: __( 'Show label', 'mime-simple-events-calendar' ),
					checked: attributes.showLabel,
					onChange: ( showLabel ) => setAttributes( { showLabel } ),
				} ),
				attributes.showLabel && el( TextControl, {
					key: 'label',
					label: __( 'Label text', 'mime-simple-events-calendar' ),
					value: attributes.label,
					placeholder: definition.labelPlaceholder,
					maxLength: 120,
					onChange: ( label ) => setAttributes( { label } ),
				} ),
			].filter( Boolean );
		case 'locationLink':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Link text', 'mime-simple-events-calendar' ),
				value: attributes.linkText,
				placeholder: __( 'View location', 'mime-simple-events-calendar' ),
				maxLength: 120,
				onChange: ( linkText ) => setAttributes( { linkText } ),
			} ) ];
		case 'externalAction':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Override link text', 'mime-simple-events-calendar' ),
				help: __( 'Leave empty to use the label saved on the event.', 'mime-simple-events-calendar' ),
				value: attributes.linkText,
				maxLength: 120,
				onChange: ( linkText ) => setAttributes( { linkText } ),
			} ) ];
		default:
			return [];
	}
};

const emptyPreview = ( title ) => () => el( Placeholder, {
	icon: 'calendar-alt',
	label: title,
	instructions: __( 'This event field has no public value for the selected or current event.', 'mime-simple-events-calendar' ),
} );
const loadingPreview = () => el( Placeholder, {}, el( Spinner ) );
const errorPreview = ( response ) => el( Placeholder, {
	icon: 'warning',
	label: __( 'Event preview unavailable', 'mime-simple-events-calendar' ),
	instructions: response?.message || __( 'The server could not render this event field.', 'mime-simple-events-calendar' ),
} );

fieldDefinitions.forEach( ( definition ) => {
	const EventFieldEdit = ( { attributes, context = {}, setAttributes } ) => {
		const postId = context.postType === wpseEventFieldBlocks.eventPostType && Number.isInteger( context.postId )
			? context.postId
			: 0;

		return el(
			Fragment,
			{},
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Event settings', 'mime-simple-events-calendar' ), initialOpen: true },
					sourceControls( attributes, setAttributes ),
					...fieldControls( definition, attributes, setAttributes ),
				),
			),
			el(
				'div',
				useBlockProps(),
				el( ServerSideRender, {
					block: definition.name,
					attributes,
					httpMethod: 'POST',
					urlQueryArgs: postId > 0 ? { post_id: postId } : {},
					EmptyResponsePlaceholder: emptyPreview( definition.title ),
					LoadingResponsePlaceholder: loadingPreview,
					ErrorResponsePlaceholder: errorPreview,
				} ),
			),
		);
	};

	registerBlockType( definition.name, {
		apiVersion: 3,
		title: definition.title,
		description: definition.description,
		category: 'mime-simple-events-calendar',
		icon: definition.icon,
		attributes: definition.attributes,
		supports: definition.supports,
		usesContext: [ 'postId', 'postType' ],
		edit: EventFieldEdit,
		save: () => null,
	} );
} );

const taxonomyOptions = ( choices = {} ) => Object.entries( choices ).map(
	( [ value, label ] ) => ( { label, value } ),
);
const categoryOptions = taxonomyOptions( wpseEventFieldBlocks.categories );
const tagOptions = taxonomyOptions( wpseEventFieldBlocks.tags );
const componentSupports = () => ( {
	html: false,
	anchor: true,
	align: [ 'wide', 'full' ],
	color: { text: true, background: true, link: true },
	spacing: { margin: true, padding: true },
	typography: { fontSize: true, lineHeight: true },
} );
const filterAttributes = ( defaultResults ) => ( {
	filterCategories: { type: 'boolean', default: true },
	filterTags: { type: 'boolean', default: true },
	filterLayout: { type: 'string', default: 'auto', enum: [ 'auto', 'horizontal', 'stacked' ] },
	filterDisclosure: { type: 'string', default: 'auto', enum: [ 'auto', 'open', 'closed' ] },
	filterChips: { type: 'boolean', default: true },
	filterResults: { type: 'boolean', default: defaultResults },
	filterLabel: { type: 'string', default: '' },
	filterPeriodLabel: { type: 'string', default: '' },
	filterCategoryLabel: { type: 'string', default: '' },
	filterTagLabel: { type: 'string', default: '' },
	filterApplyLabel: { type: 'string', default: '' },
} );
const filterStyleAttributes = () => ( {
	filterContainerBackground: { type: 'string', default: '' },
	filterPanelBackground: { type: 'string', default: '' },
	filterTriggerBackground: { type: 'string', default: '' },
	filterTriggerText: { type: 'string', default: '' },
	filterFieldBackground: { type: 'string', default: '' },
	filterFieldText: { type: 'string', default: '' },
	filterAccent: { type: 'string', default: '' },
	filterChipBackground: { type: 'string', default: '' },
	filterChipText: { type: 'string', default: '' },
	filterActionBackground: { type: 'string', default: '' },
	filterActionText: { type: 'string', default: '' },
	filterStatusBackground: { type: 'string', default: '' },
	filterStatusText: { type: 'string', default: '' },
	filterGap: { type: 'integer' },
	filterContainerPadding: { type: 'integer' },
	filterPanelPadding: { type: 'integer' },
	filterPanelRadius: { type: 'integer' },
	filterTriggerPadding: { type: 'integer' },
	filterTriggerRadius: { type: 'integer' },
	filterOptionGap: { type: 'integer' },
	filterCheckboxSize: { type: 'integer' },
	filterOptionsMaxHeight: { type: 'integer' },
	filterChipPadding: { type: 'integer' },
	filterChipRadius: { type: 'integer' },
	filterActionPadding: { type: 'integer' },
	filterActionRadius: { type: 'integer' },
	filterStatusPadding: { type: 'integer' },
} );

const compositeDefinitions = [
	{
		name: 'wpse/event-list',
		title: __( 'Event List / Grid', 'mime-simple-events-calendar' ),
		description: __( 'Display a bounded list or grid of public events.', 'mime-simple-events-calendar' ),
		icon: 'list-view',
		attributes: {
			view: { type: 'string', default: 'grid', enum: [ 'list', 'grid' ] },
			period: { type: 'string', default: 'upcoming', enum: [ 'upcoming', 'past', 'all' ] },
			limit: { type: 'integer', default: 12 },
			columns: { type: 'integer', default: 3 },
			categories: { type: 'array', default: [], items: { type: 'string' } },
			tags: { type: 'array', default: [], items: { type: 'string' } },
			filters: { type: 'boolean', default: false },
			...filterAttributes( false ),
			...filterStyleAttributes(),
			pagination: { type: 'boolean', default: true },
			showExcerpt: { type: 'boolean', default: true },
			showImage: { type: 'boolean', default: true },
			showLocation: { type: 'boolean', default: true },
			showTitle: { type: 'boolean', default: true },
			showDate: { type: 'boolean', default: true },
			excerptLength: { type: 'integer', default: 30 },
			headingLevel: { type: 'string', default: 'h3', enum: [ 'h2', 'h3', 'h4', 'h5', 'h6' ] },
		},
		supports: componentSupports(),
		controls: 'list',
	},
	{
		name: 'wpse/event-calendar',
		title: __( 'Event Calendar', 'mime-simple-events-calendar' ),
		description: __( 'Display the interactive public event calendar with a server-rendered fallback.', 'mime-simple-events-calendar' ),
		icon: 'calendar-alt',
		attributes: {
			initialView: { type: 'string', default: 'month', enum: [ 'month', 'list' ] },
			mobileView: { type: 'string', default: 'list', enum: [ 'month', 'list' ] },
			categories: { type: 'array', default: [], items: { type: 'string' } },
			tags: { type: 'array', default: [], items: { type: 'string' } },
			filters: { type: 'boolean', default: true },
			...filterAttributes( true ),
			...filterStyleAttributes(),
			initialDate: { type: 'string', default: '' },
			showNavigation: { type: 'boolean', default: true },
			showToday: { type: 'boolean', default: true },
			showViewSwitcher: { type: 'boolean', default: true },
			fallbackHeadingLevel: { type: 'string', default: 'h3', enum: [ 'h2', 'h3', 'h4', 'h5', 'h6' ] },
		},
		supports: componentSupports(),
		controls: 'calendar',
	},
	{
		name: 'wpse/event-details',
		title: __( 'Event Details', 'mime-simple-events-calendar' ),
		description: __( 'Display the complete details of the current or selected public event.', 'mime-simple-events-calendar' ),
		icon: 'media-document',
		attributes: {
			...commonAttributes(),
			showTitle: { type: 'boolean', default: true },
			showImage: { type: 'boolean', default: true },
			showDate: { type: 'boolean', default: true },
			showStatus: { type: 'boolean', default: true },
			showLocation: { type: 'boolean', default: true },
			showContent: { type: 'boolean', default: true },
			showAction: { type: 'boolean', default: true },
			showTerms: { type: 'boolean', default: true },
			headingLevel: { type: 'string', default: 'h1', enum: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] },
			dateLabel: { type: 'string', default: '' },
			venueLabel: { type: 'string', default: '' },
			locationLabel: { type: 'string', default: '' },
			actionLabel: { type: 'string', default: '' },
			categoriesLabel: { type: 'string', default: '' },
			tagsLabel: { type: 'string', default: '' },
		},
		supports: componentSupports(),
		controls: 'details',
	},
];

const taxonomyControl = ( key, label, value, options, setAttributes ) => el( SelectControl, {
	key,
	label,
	multiple: true,
	value: Array.isArray( value ) ? value : [],
	options,
	help: options.length > 0
		? __( 'Leave empty to include every available term.', 'mime-simple-events-calendar' )
		: __( 'No event terms are available yet.', 'mime-simple-events-calendar' ),
	disabled: options.length === 0,
	onChange: ( selected ) => setAttributes( { [ key ]: Array.isArray( selected ) ? selected : [] } ),
} );

const listControls = ( attributes, setAttributes ) => [
	el( SelectControl, {
		key: 'view',
		label: __( 'Layout', 'mime-simple-events-calendar' ),
		value: attributes.view,
		options: [
			{ label: __( 'Grid', 'mime-simple-events-calendar' ), value: 'grid' },
			{ label: __( 'List', 'mime-simple-events-calendar' ), value: 'list' },
		],
		onChange: ( view ) => setAttributes( { view } ),
	} ),
	el( SelectControl, {
		key: 'period',
		label: __( 'Period', 'mime-simple-events-calendar' ),
		value: attributes.period,
		options: [
			{ label: __( 'Upcoming', 'mime-simple-events-calendar' ), value: 'upcoming' },
			{ label: __( 'Past', 'mime-simple-events-calendar' ), value: 'past' },
			{ label: __( 'All', 'mime-simple-events-calendar' ), value: 'all' },
		],
		onChange: ( period ) => setAttributes( { period } ),
	} ),
	el( RangeControl, {
		key: 'limit',
		label: __( 'Events per page', 'mime-simple-events-calendar' ),
		value: attributes.limit,
		min: 1,
		max: 50,
		onChange: ( limit ) => setAttributes( { limit } ),
	} ),
	attributes.view === 'grid' && el( RangeControl, {
		key: 'columns',
		label: __( 'Columns', 'mime-simple-events-calendar' ),
		value: attributes.columns,
		min: 1,
		max: 4,
		onChange: ( columns ) => setAttributes( { columns } ),
	} ),
	taxonomyControl( 'categories', __( 'Categories', 'mime-simple-events-calendar' ), attributes.categories, categoryOptions, setAttributes ),
	taxonomyControl( 'tags', __( 'Tags', 'mime-simple-events-calendar' ), attributes.tags, tagOptions, setAttributes ),
	attributes.showTitle && el( SelectControl, {
		key: 'headingLevel',
		label: __( 'Title heading level', 'mime-simple-events-calendar' ),
		value: attributes.headingLevel,
		options: [ 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
		onChange: ( headingLevel ) => setAttributes( { headingLevel } ),
	} ),
	...[
		[ 'filters', __( 'Show visitor filters', 'mime-simple-events-calendar' ) ],
		[ 'pagination', __( 'Show pagination', 'mime-simple-events-calendar' ) ],
		[ 'showImage', __( 'Show image', 'mime-simple-events-calendar' ) ],
		[ 'showTitle', __( 'Show title', 'mime-simple-events-calendar' ) ],
		[ 'showDate', __( 'Show date and time', 'mime-simple-events-calendar' ) ],
		[ 'showExcerpt', __( 'Show excerpt', 'mime-simple-events-calendar' ) ],
		[ 'showLocation', __( 'Show location', 'mime-simple-events-calendar' ) ],
	].map( ( [ key, label ] ) => el( ToggleControl, {
		key,
		label,
		checked: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
	attributes.showExcerpt && el( RangeControl, {
		key: 'excerptLength',
		label: __( 'Excerpt length (words)', 'mime-simple-events-calendar' ),
		value: attributes.excerptLength,
		min: 1,
		max: 100,
		onChange: ( excerptLength ) => setAttributes( { excerptLength } ),
	} ),
].filter( Boolean );

const calendarControls = ( attributes, setAttributes ) => [
	el( SelectControl, {
		key: 'initialView',
		label: __( 'Desktop view', 'mime-simple-events-calendar' ),
		value: attributes.initialView,
		options: [
			{ label: __( 'Month', 'mime-simple-events-calendar' ), value: 'month' },
			{ label: __( 'List', 'mime-simple-events-calendar' ), value: 'list' },
		],
		onChange: ( initialView ) => setAttributes( { initialView } ),
	} ),
	el( TextControl, {
		key: 'initialDate',
		label: __( 'Initial date', 'mime-simple-events-calendar' ),
		help: __( 'Optional. Use YYYY-MM-DD to open the calendar on a specific date.', 'mime-simple-events-calendar' ),
		type: 'date',
		value: attributes.initialDate,
		onChange: ( initialDate ) => setAttributes( { initialDate } ),
	} ),
	el( SelectControl, {
		key: 'mobileView',
		label: __( 'Mobile view', 'mime-simple-events-calendar' ),
		value: attributes.mobileView,
		options: [
			{ label: __( 'Month', 'mime-simple-events-calendar' ), value: 'month' },
			{ label: __( 'List', 'mime-simple-events-calendar' ), value: 'list' },
		],
		onChange: ( mobileView ) => setAttributes( { mobileView } ),
	} ),
	taxonomyControl( 'categories', __( 'Initial categories', 'mime-simple-events-calendar' ), attributes.categories, categoryOptions, setAttributes ),
	taxonomyControl( 'tags', __( 'Initial tags', 'mime-simple-events-calendar' ), attributes.tags, tagOptions, setAttributes ),
	el( ToggleControl, {
		key: 'filters',
		label: __( 'Show visitor filters', 'mime-simple-events-calendar' ),
		checked: attributes.filters,
		onChange: ( filters ) => setAttributes( { filters } ),
	} ),
	...[
		[ 'showNavigation', __( 'Show previous and next buttons', 'mime-simple-events-calendar' ) ],
		[ 'showToday', __( 'Show Today button', 'mime-simple-events-calendar' ) ],
		[ 'showViewSwitcher', __( 'Show month/list switcher', 'mime-simple-events-calendar' ) ],
	].map( ( [ key, label ] ) => el( ToggleControl, {
		key,
		label,
		checked: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
	el( SelectControl, {
		key: 'fallbackHeadingLevel',
		label: __( 'Fallback heading level', 'mime-simple-events-calendar' ),
		value: attributes.fallbackHeadingLevel,
		options: [ 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
		onChange: ( fallbackHeadingLevel ) => setAttributes( { fallbackHeadingLevel } ),
	} ),
];

const detailsControls = ( attributes, setAttributes ) => [
	sourceControls( attributes, setAttributes ),
	...[
		[ 'showTitle', __( 'Show title', 'mime-simple-events-calendar' ) ],
		[ 'showImage', __( 'Show image', 'mime-simple-events-calendar' ) ],
		[ 'showDate', __( 'Show date and time', 'mime-simple-events-calendar' ) ],
		[ 'showStatus', __( 'Show event status', 'mime-simple-events-calendar' ) ],
		[ 'showLocation', __( 'Show location details', 'mime-simple-events-calendar' ) ],
		[ 'showContent', __( 'Show content', 'mime-simple-events-calendar' ) ],
		[ 'showAction', __( 'Show external action', 'mime-simple-events-calendar' ) ],
		[ 'showTerms', __( 'Show categories and tags', 'mime-simple-events-calendar' ) ],
	].map( ( [ key, label ] ) => el( ToggleControl, {
		key,
		label,
		checked: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
	attributes.showTitle && el( SelectControl, {
		key: 'headingLevel',
		label: __( 'Title heading level', 'mime-simple-events-calendar' ),
		value: attributes.headingLevel,
		options: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
		onChange: ( headingLevel ) => setAttributes( { headingLevel } ),
	} ),
	...[
		[ 'dateLabel', __( 'Date label', 'mime-simple-events-calendar' ), attributes.showDate ],
		[ 'venueLabel', __( 'Venue label', 'mime-simple-events-calendar' ), attributes.showLocation ],
		[ 'locationLabel', __( 'Location link text', 'mime-simple-events-calendar' ), attributes.showLocation ],
		[ 'actionLabel', __( 'External action text', 'mime-simple-events-calendar' ), attributes.showAction ],
		[ 'categoriesLabel', __( 'Categories label', 'mime-simple-events-calendar' ), attributes.showTerms ],
		[ 'tagsLabel', __( 'Tags label', 'mime-simple-events-calendar' ), attributes.showTerms ],
	].map( ( [ key, label, visible ] ) => visible && el( TextControl, {
		key,
		label,
		help: __( 'Leave empty to use the event default.', 'mime-simple-events-calendar' ),
		value: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
].filter( Boolean );

const filterControls = ( attributes, setAttributes, showPeriod ) => [
	...[
		[ 'filterCategories', __( 'Show categories', 'mime-simple-events-calendar' ) ],
		[ 'filterTags', __( 'Show tags', 'mime-simple-events-calendar' ) ],
		[ 'filterChips', __( 'Show active filter chips', 'mime-simple-events-calendar' ) ],
		[ 'filterResults', __( 'Show result status', 'mime-simple-events-calendar' ) ],
	].map( ( [ key, label ] ) => el( ToggleControl, {
		key,
		label,
		checked: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
	el( SelectControl, {
		key: 'filterLayout',
		label: __( 'Filter layout', 'mime-simple-events-calendar' ),
		value: attributes.filterLayout,
		options: [
			{ label: __( 'Automatic', 'mime-simple-events-calendar' ), value: 'auto' },
			{ label: __( 'Horizontal', 'mime-simple-events-calendar' ), value: 'horizontal' },
			{ label: __( 'Stacked', 'mime-simple-events-calendar' ), value: 'stacked' },
		],
		onChange: ( filterLayout ) => setAttributes( { filterLayout } ),
	} ),
	el( SelectControl, {
		key: 'filterDisclosure',
		label: __( 'Initial filter panel', 'mime-simple-events-calendar' ),
		value: attributes.filterDisclosure,
		options: [
			{ label: __( 'Automatic', 'mime-simple-events-calendar' ), value: 'auto' },
			{ label: __( 'Open', 'mime-simple-events-calendar' ), value: 'open' },
			{ label: __( 'Closed', 'mime-simple-events-calendar' ), value: 'closed' },
		],
		onChange: ( filterDisclosure ) => setAttributes( { filterDisclosure } ),
	} ),
	...[
		[ 'filterLabel', __( 'Filter button label', 'mime-simple-events-calendar' ), true ],
		[ 'filterPeriodLabel', __( 'Period label', 'mime-simple-events-calendar' ), showPeriod ],
		[ 'filterCategoryLabel', __( 'Categories label', 'mime-simple-events-calendar' ), attributes.filterCategories ],
		[ 'filterTagLabel', __( 'Tags label', 'mime-simple-events-calendar' ), attributes.filterTags ],
		[ 'filterApplyLabel', __( 'Apply button label', 'mime-simple-events-calendar' ), true ],
	].map( ( [ key, label, visible ] ) => visible && el( TextControl, {
		key,
		label,
		help: __( 'Leave empty to use the translated default.', 'mime-simple-events-calendar' ),
		value: attributes[ key ],
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
].filter( Boolean );

const filterDesignControls = ( attributes, setAttributes ) => [
	...[
		[ 'filterContainerBackground', __( 'Container background', 'mime-simple-events-calendar' ) ],
		[ 'filterPanelBackground', __( 'Panel background', 'mime-simple-events-calendar' ) ],
		[ 'filterTriggerBackground', __( 'Trigger background', 'mime-simple-events-calendar' ) ],
		[ 'filterTriggerText', __( 'Trigger text', 'mime-simple-events-calendar' ) ],
		[ 'filterFieldBackground', __( 'Field background', 'mime-simple-events-calendar' ) ],
		[ 'filterFieldText', __( 'Field text', 'mime-simple-events-calendar' ) ],
		[ 'filterAccent', __( 'Checkbox accent', 'mime-simple-events-calendar' ) ],
		[ 'filterChipBackground', __( 'Chip background', 'mime-simple-events-calendar' ) ],
		[ 'filterChipText', __( 'Chip text', 'mime-simple-events-calendar' ) ],
		[ 'filterActionBackground', __( 'Action background', 'mime-simple-events-calendar' ) ],
		[ 'filterActionText', __( 'Action text', 'mime-simple-events-calendar' ) ],
		[ 'filterStatusBackground', __( 'Status background', 'mime-simple-events-calendar' ) ],
		[ 'filterStatusText', __( 'Status text', 'mime-simple-events-calendar' ) ],
	].map( ( [ key, label ] ) => el(
		BaseControl,
		{ key, label, __nextHasNoMarginBottom: true },
		el( ColorPalette, {
			value: attributes[ key ],
			clearable: true,
			onChange: ( value ) => setAttributes( { [ key ]: value || '' } ),
		} ),
	) ),
	...[
		[ 'filterGap', __( 'Filter gap', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterContainerPadding', __( 'Container padding', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterPanelPadding', __( 'Panel padding', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterPanelRadius', __( 'Panel radius', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterTriggerPadding', __( 'Trigger padding', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterTriggerRadius', __( 'Trigger radius', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterOptionGap', __( 'Option gap', 'mime-simple-events-calendar' ), 0, 40 ],
		[ 'filterCheckboxSize', __( 'Checkbox size', 'mime-simple-events-calendar' ), 8, 40 ],
		[ 'filterOptionsMaxHeight', __( 'Option list maximum height', 'mime-simple-events-calendar' ), 80, 800 ],
		[ 'filterChipPadding', __( 'Chip padding', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterChipRadius', __( 'Chip radius', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterActionPadding', __( 'Action padding', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterActionRadius', __( 'Action radius', 'mime-simple-events-calendar' ), 0, 80 ],
		[ 'filterStatusPadding', __( 'Status padding', 'mime-simple-events-calendar' ), 0, 80 ],
	].map( ( [ key, label, min, max ] ) => el( RangeControl, {
		key,
		label: `${ label } (px)`,
		value: attributes[ key ],
		min,
		max,
		allowReset: true,
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} ) ),
];

const compositeControls = ( definition, attributes, setAttributes ) => {
	switch ( definition.controls ) {
		case 'list':
			return listControls( attributes, setAttributes );
		case 'calendar':
			return calendarControls( attributes, setAttributes );
		case 'details':
			return detailsControls( attributes, setAttributes );
		default:
			return [];
	}
};

const compositeEmptyPreview = ( title ) => () => el( Placeholder, {
	icon: 'calendar-alt',
	label: title,
	instructions: __( 'No public event output is available for the current settings or context.', 'mime-simple-events-calendar' ),
} );

compositeDefinitions.forEach( ( definition ) => {
	const EventCompositeEdit = ( { attributes, context = {}, setAttributes } ) => {
		const postId = context.postType === wpseEventFieldBlocks.eventPostType && Number.isInteger( context.postId )
			? context.postId
			: 0;

		return el(
			Fragment,
			{},
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Event settings', 'mime-simple-events-calendar' ), initialOpen: true },
					...compositeControls( definition, attributes, setAttributes ),
				),
				definition.controls !== 'details' && attributes.filters && el(
					PanelBody,
					{ title: __( 'Visitor filter settings', 'mime-simple-events-calendar' ), initialOpen: false },
					...filterControls( attributes, setAttributes, definition.controls === 'list' ),
				),
				definition.controls !== 'details' && attributes.filters && el(
					PanelBody,
					{ title: __( 'Visitor filter design', 'mime-simple-events-calendar' ), initialOpen: false },
					...filterDesignControls( attributes, setAttributes ),
				),
			),
			el(
				'div',
				useBlockProps(),
				el( ServerSideRender, {
					block: definition.name,
					attributes,
					httpMethod: 'POST',
					urlQueryArgs: definition.controls === 'details' && postId > 0 ? { post_id: postId } : {},
					EmptyResponsePlaceholder: compositeEmptyPreview( definition.title ),
					LoadingResponsePlaceholder: loadingPreview,
					ErrorResponsePlaceholder: errorPreview,
				} ),
			),
		);
	};

	registerBlockType( definition.name, {
		apiVersion: 3,
		title: definition.title,
		description: definition.description,
		category: 'mime-simple-events-calendar',
		icon: definition.icon,
		attributes: definition.attributes,
		supports: definition.supports,
		usesContext: definition.controls === 'details' ? [ 'postId', 'postType' ] : [],
		edit: EventCompositeEdit,
		save: () => null,
	} );
} );
