/* global wpseEventFieldBlocks */

/**
 * Shared editor interface for the server-rendered atomic event-field blocks.
 */
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const {
	PanelBody,
	Placeholder,
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
	{ label: __( 'Current event context', 'wp-simple-events' ), value: '0' },
	...Object.entries( wpseEventFieldBlocks.events || {} ).map(
		( [ value, label ] ) => ( { label, value: String( value ) } ),
	),
];

const definitions = [
	{
		name: 'wpse/event-title',
		title: __( 'Event Title', 'wp-simple-events' ),
		description: __( 'Display the title of the current or selected event.', 'wp-simple-events' ),
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
		title: __( 'Event Featured Image', 'wp-simple-events' ),
		description: __( 'Display the featured image of the current or selected event.', 'wp-simple-events' ),
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
		title: __( 'Event Date & Time', 'wp-simple-events' ),
		description: __( 'Display the localized date, time and optional timezone of an event.', 'wp-simple-events' ),
		icon: 'calendar-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Date and time:', 'wp-simple-events' ),
	},
	{
		name: 'wpse/event-status',
		title: __( 'Event Status', 'wp-simple-events' ),
		description: __( 'Display a cancelled or postponed event status.', 'wp-simple-events' ),
		icon: 'warning',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-venue',
		title: __( 'Event Venue', 'wp-simple-events' ),
		description: __( 'Display the venue of the current or selected event.', 'wp-simple-events' ),
		icon: 'location-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Location:', 'wp-simple-events' ),
	},
	{
		name: 'wpse/event-address',
		title: __( 'Event Address', 'wp-simple-events' ),
		description: __( 'Display the postal address of the current or selected event.', 'wp-simple-events' ),
		icon: 'admin-home',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-location-link',
		title: __( 'Event Location Link', 'wp-simple-events' ),
		description: __( 'Display the route or location link saved on an event.', 'wp-simple-events' ),
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
		title: __( 'Event Content', 'wp-simple-events' ),
		description: __( 'Display the main content of the current or selected event.', 'wp-simple-events' ),
		icon: 'text-page',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-excerpt',
		title: __( 'Event Excerpt', 'wp-simple-events' ),
		description: __( 'Display the excerpt of the current or selected event.', 'wp-simple-events' ),
		icon: 'excerpt-view',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-external-action',
		title: __( 'External Event Action', 'wp-simple-events' ),
		description: __( 'Display the external information or registration link saved on an event.', 'wp-simple-events' ),
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
		title: __( 'Event Categories', 'wp-simple-events' ),
		description: __( 'Display linked categories for the current or selected event.', 'wp-simple-events' ),
		icon: 'category',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Categories:', 'wp-simple-events' ),
	},
	{
		name: 'wpse/event-tags',
		title: __( 'Event Tags', 'wp-simple-events' ),
		description: __( 'Display linked tags for the current or selected event.', 'wp-simple-events' ),
		icon: 'tag',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Tags:', 'wp-simple-events' ),
	},
];

const sourceControls = ( attributes, setAttributes ) =>
	el( SelectControl, {
		label: __( 'Event source', 'wp-simple-events' ),
		help: __( 'Select a public event for a static page, or use the current event supplied by a template or query.', 'wp-simple-events' ),
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
					label: __( 'HTML tag', 'wp-simple-events' ),
					value: attributes.heading,
					options: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
					onChange: ( heading ) => setAttributes( { heading } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'wp-simple-events' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'image':
			return [
				el( SelectControl, {
					key: 'imageSize',
					label: __( 'Image size', 'wp-simple-events' ),
					value: attributes.imageSize,
					options: [
						{ label: __( 'Thumbnail', 'wp-simple-events' ), value: 'thumbnail' },
						{ label: __( 'Medium', 'wp-simple-events' ), value: 'medium' },
						{ label: __( 'Medium large', 'wp-simple-events' ), value: 'medium_large' },
						{ label: __( 'Large', 'wp-simple-events' ), value: 'large' },
						{ label: __( 'Full size', 'wp-simple-events' ), value: 'full' },
					],
					onChange: ( imageSize ) => setAttributes( { imageSize } ),
				} ),
				el( SelectControl, {
					key: 'altMode',
					label: __( 'Alternative text', 'wp-simple-events' ),
					value: attributes.altMode,
					options: [
						{ label: __( 'Use Media Library alt text', 'wp-simple-events' ), value: 'attachment' },
						{ label: __( 'Decorative (empty alt)', 'wp-simple-events' ), value: 'decorative' },
					],
					onChange: ( altMode ) => setAttributes( { altMode } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'wp-simple-events' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'label':
			return [
				el( ToggleControl, {
					key: 'showLabel',
					label: __( 'Show label', 'wp-simple-events' ),
					checked: attributes.showLabel,
					onChange: ( showLabel ) => setAttributes( { showLabel } ),
				} ),
				attributes.showLabel && el( TextControl, {
					key: 'label',
					label: __( 'Label text', 'wp-simple-events' ),
					value: attributes.label,
					placeholder: definition.labelPlaceholder,
					maxLength: 120,
					onChange: ( label ) => setAttributes( { label } ),
				} ),
			].filter( Boolean );
		case 'locationLink':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Link text', 'wp-simple-events' ),
				value: attributes.linkText,
				placeholder: __( 'View location', 'wp-simple-events' ),
				maxLength: 120,
				onChange: ( linkText ) => setAttributes( { linkText } ),
			} ) ];
		case 'externalAction':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Override link text', 'wp-simple-events' ),
				help: __( 'Leave empty to use the label saved on the event.', 'wp-simple-events' ),
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
	instructions: __( 'This event field has no public value for the selected or current event.', 'wp-simple-events' ),
} );
const loadingPreview = () => el( Placeholder, {}, el( Spinner ) );
const errorPreview = ( response ) => el( Placeholder, {
	icon: 'warning',
	label: __( 'Event preview unavailable', 'wp-simple-events' ),
	instructions: response?.message || __( 'The server could not render this event field.', 'wp-simple-events' ),
} );

definitions.forEach( ( definition ) => {
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
					{ title: __( 'Event settings', 'wp-simple-events' ), initialOpen: true },
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
		category: 'wp-simple-events',
		icon: definition.icon,
		attributes: definition.attributes,
		supports: definition.supports,
		usesContext: [ 'postId', 'postType' ],
		edit: EventFieldEdit,
		save: () => null,
	} );
} );
