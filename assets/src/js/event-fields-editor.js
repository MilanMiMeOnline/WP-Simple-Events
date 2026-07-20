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
	{ label: __( 'Current event context', 'simple-events-by-mime' ), value: '0' },
	...Object.entries( wpseEventFieldBlocks.events || {} ).map(
		( [ value, label ] ) => ( { label, value: String( value ) } ),
	),
];

const definitions = [
	{
		name: 'wpse/event-title',
		title: __( 'Event Title', 'simple-events-by-mime' ),
		description: __( 'Display the title of the current or selected event.', 'simple-events-by-mime' ),
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
		title: __( 'Event Featured Image', 'simple-events-by-mime' ),
		description: __( 'Display the featured image of the current or selected event.', 'simple-events-by-mime' ),
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
		title: __( 'Event Date & Time', 'simple-events-by-mime' ),
		description: __( 'Display the localized date, time and optional timezone of an event.', 'simple-events-by-mime' ),
		icon: 'calendar-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Date and time:', 'simple-events-by-mime' ),
	},
	{
		name: 'wpse/event-status',
		title: __( 'Event Status', 'simple-events-by-mime' ),
		description: __( 'Display a cancelled or postponed event status.', 'simple-events-by-mime' ),
		icon: 'warning',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-venue',
		title: __( 'Event Venue', 'simple-events-by-mime' ),
		description: __( 'Display the venue of the current or selected event.', 'simple-events-by-mime' ),
		icon: 'location-alt',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Location:', 'simple-events-by-mime' ),
	},
	{
		name: 'wpse/event-address',
		title: __( 'Event Address', 'simple-events-by-mime' ),
		description: __( 'Display the postal address of the current or selected event.', 'simple-events-by-mime' ),
		icon: 'admin-home',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-location-link',
		title: __( 'Event Location Link', 'simple-events-by-mime' ),
		description: __( 'Display the route or location link saved on an event.', 'simple-events-by-mime' ),
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
		title: __( 'Event Content', 'simple-events-by-mime' ),
		description: __( 'Display the main content of the current or selected event.', 'simple-events-by-mime' ),
		icon: 'text-page',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-excerpt',
		title: __( 'Event Excerpt', 'simple-events-by-mime' ),
		description: __( 'Display the excerpt of the current or selected event.', 'simple-events-by-mime' ),
		icon: 'excerpt-view',
		attributes: commonAttributes(),
		supports: textSupports(),
	},
	{
		name: 'wpse/event-external-action',
		title: __( 'External Event Action', 'simple-events-by-mime' ),
		description: __( 'Display the external information or registration link saved on an event.', 'simple-events-by-mime' ),
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
		title: __( 'Event Categories', 'simple-events-by-mime' ),
		description: __( 'Display linked categories for the current or selected event.', 'simple-events-by-mime' ),
		icon: 'category',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Categories:', 'simple-events-by-mime' ),
	},
	{
		name: 'wpse/event-tags',
		title: __( 'Event Tags', 'simple-events-by-mime' ),
		description: __( 'Display linked tags for the current or selected event.', 'simple-events-by-mime' ),
		icon: 'tag',
		attributes: {
			...commonAttributes(),
			showLabel: { type: 'boolean', default: true },
			label: { type: 'string', default: '' },
		},
		supports: textSupports(),
		controls: 'label',
		labelPlaceholder: __( 'Tags:', 'simple-events-by-mime' ),
	},
];

const sourceControls = ( attributes, setAttributes ) =>
	el( SelectControl, {
		label: __( 'Event source', 'simple-events-by-mime' ),
		help: __( 'Select a public event for a static page, or use the current event supplied by a template or query.', 'simple-events-by-mime' ),
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
					label: __( 'HTML tag', 'simple-events-by-mime' ),
					value: attributes.heading,
					options: [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => ( { label: value.toUpperCase(), value } ) ),
					onChange: ( heading ) => setAttributes( { heading } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'simple-events-by-mime' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'image':
			return [
				el( SelectControl, {
					key: 'imageSize',
					label: __( 'Image size', 'simple-events-by-mime' ),
					value: attributes.imageSize,
					options: [
						{ label: __( 'Thumbnail', 'simple-events-by-mime' ), value: 'thumbnail' },
						{ label: __( 'Medium', 'simple-events-by-mime' ), value: 'medium' },
						{ label: __( 'Medium large', 'simple-events-by-mime' ), value: 'medium_large' },
						{ label: __( 'Large', 'simple-events-by-mime' ), value: 'large' },
						{ label: __( 'Full size', 'simple-events-by-mime' ), value: 'full' },
					],
					onChange: ( imageSize ) => setAttributes( { imageSize } ),
				} ),
				el( SelectControl, {
					key: 'altMode',
					label: __( 'Alternative text', 'simple-events-by-mime' ),
					value: attributes.altMode,
					options: [
						{ label: __( 'Use Media Library alt text', 'simple-events-by-mime' ), value: 'attachment' },
						{ label: __( 'Decorative (empty alt)', 'simple-events-by-mime' ), value: 'decorative' },
					],
					onChange: ( altMode ) => setAttributes( { altMode } ),
				} ),
				el( ToggleControl, {
					key: 'link',
					label: __( 'Link to event', 'simple-events-by-mime' ),
					checked: attributes.link,
					onChange: ( link ) => setAttributes( { link } ),
				} ),
			];
		case 'label':
			return [
				el( ToggleControl, {
					key: 'showLabel',
					label: __( 'Show label', 'simple-events-by-mime' ),
					checked: attributes.showLabel,
					onChange: ( showLabel ) => setAttributes( { showLabel } ),
				} ),
				attributes.showLabel && el( TextControl, {
					key: 'label',
					label: __( 'Label text', 'simple-events-by-mime' ),
					value: attributes.label,
					placeholder: definition.labelPlaceholder,
					maxLength: 120,
					onChange: ( label ) => setAttributes( { label } ),
				} ),
			].filter( Boolean );
		case 'locationLink':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Link text', 'simple-events-by-mime' ),
				value: attributes.linkText,
				placeholder: __( 'View location', 'simple-events-by-mime' ),
				maxLength: 120,
				onChange: ( linkText ) => setAttributes( { linkText } ),
			} ) ];
		case 'externalAction':
			return [ el( TextControl, {
				key: 'linkText',
				label: __( 'Override link text', 'simple-events-by-mime' ),
				help: __( 'Leave empty to use the label saved on the event.', 'simple-events-by-mime' ),
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
	instructions: __( 'This event field has no public value for the selected or current event.', 'simple-events-by-mime' ),
} );
const loadingPreview = () => el( Placeholder, {}, el( Spinner ) );
const errorPreview = ( response ) => el( Placeholder, {
	icon: 'warning',
	label: __( 'Event preview unavailable', 'simple-events-by-mime' ),
	instructions: response?.message || __( 'The server could not render this event field.', 'simple-events-by-mime' ),
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
					{ title: __( 'Event settings', 'simple-events-by-mime' ), initialOpen: true },
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
		category: 'simple-events-by-mime',
		icon: definition.icon,
		attributes: definition.attributes,
		supports: definition.supports,
		usesContext: [ 'postId', 'postType' ],
		edit: EventFieldEdit,
		save: () => null,
	} );
} );
