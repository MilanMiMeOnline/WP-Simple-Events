const select = ( label, description, options, defaultValue ) => ( {
	label,
	description,
	defaultValue,
	component: 'divi/select',
	options,
} );

const toggle = ( label, description, defaultValue = 'off' ) => ( {
	label,
	description,
	defaultValue,
	component: 'divi/toggle',
} );

const text = ( label, description ) => ( {
	label,
	description,
	defaultValue: '',
	component: 'divi/text',
} );

const checkboxes = ( label, description ) => ( {
	label,
	description,
	defaultValue: [],
	component: 'divi/checkboxes',
	options: [],
} );

export const atomicModuleDefinitions = [
	{
		slug: 'event-featured-image',
		field: 'featured_image',
		title: 'Event Featured Image',
		icon: 'divi/module-image',
		selector: '.wpse-single-event-image',
		textStyle: false,
		controls: {
			imageSize: select(
				'Image Size',
				'Choose the registered WordPress image size.',
				{
					thumbnail: { label: 'Thumbnail' },
					medium: { label: 'Medium' },
					medium_large: { label: 'Medium Large' },
					large: { label: 'Large' },
					full: { label: 'Full Size' },
				},
				'large',
			),
			altMode: select(
				'Alternative Text',
				'Use the Media Library alternative text or mark the image decorative.',
				{
					attachment: { label: 'Media Library Alt Text' },
					decorative: { label: 'Decorative (Empty Alt)' },
				},
				'attachment',
			),
			linkField: toggle(
				'Link to Event',
				'Link the image to the selected event or occurrence.',
			),
		},
	},
	{
		slug: 'event-date-time',
		field: 'date_time',
		title: 'Event Date & Time',
		icon: 'divi/module-heading',
		selector: '.wpse-event-date',
		controls: {
			showLabel: toggle( 'Show Label', 'Show the date and time label.', 'on' ),
			label: text( 'Label Text', 'Leave empty to use the translated default label.' ),
		},
	},
	{
		slug: 'event-status',
		field: 'status',
		title: 'Event Status',
		icon: 'divi/module-notification',
		selector: '.wpse-event-status',
	},
	{
		slug: 'event-venue',
		field: 'venue',
		title: 'Event Venue',
		icon: 'divi/module-heading',
		selector: '.wpse-event-venue',
		controls: {
			showLabel: toggle( 'Show Label', 'Show the venue label.', 'on' ),
			label: text( 'Label Text', 'Leave empty to use the translated default label.' ),
		},
	},
	{
		slug: 'event-address',
		field: 'address',
		title: 'Event Address',
		icon: 'divi/module-heading',
		selector: '.wpse-event-address',
	},
	{
		slug: 'event-location-link',
		field: 'location_action',
		title: 'Event Location Link',
		icon: 'divi/module-button',
		selector: '.wpse-event-location-link',
		controls: {
			linkText: text( 'Link Text', 'Leave empty to use the translated default label.' ),
		},
	},
	{
		slug: 'event-content',
		field: 'content',
		title: 'Event Content',
		icon: 'divi/module-text',
		selector: '.wpse-single-event-content',
	},
	{
		slug: 'event-excerpt',
		field: 'excerpt',
		title: 'Event Excerpt',
		icon: 'divi/module-text',
		selector: '.wpse-event-excerpt',
	},
	{
		slug: 'external-event-action',
		field: 'external_action',
		title: 'External Event Action',
		icon: 'divi/module-button',
		selector: '.wpse-event-action-link',
		controls: {
			linkText: text( 'Override Link Text', 'Leave empty to use the label saved on the event.' ),
		},
	},
	{
		slug: 'event-categories',
		field: 'categories',
		title: 'Event Categories',
		icon: 'divi/module-heading',
		selector: '.wpse-event-categories',
		controls: {
			showLabel: toggle( 'Show Label', 'Show the categories label.', 'on' ),
			label: text( 'Label Text', 'Leave empty to use the translated default label.' ),
		},
	},
	{
		slug: 'event-tags',
		field: 'tags',
		title: 'Event Tags',
		icon: 'divi/module-heading',
		selector: '.wpse-event-tags',
		controls: {
			showLabel: toggle( 'Show Label', 'Show the tags label.', 'on' ),
			label: text( 'Label Text', 'Leave empty to use the translated default label.' ),
		},
	},
];

const detailsToggles = {
	showTitle: toggle( 'Show Title', 'Show the event title.', 'on' ),
	showImage: toggle( 'Show Image', 'Show the featured image.', 'on' ),
	showDate: toggle( 'Show Date and Time', 'Show the event date and time.', 'on' ),
	showStatus: toggle( 'Show Status', 'Show cancelled or postponed status.', 'on' ),
	showLocation: toggle( 'Show Location', 'Show the venue, address and location link.', 'on' ),
	showContent: toggle( 'Show Content', 'Show the saved event content.', 'on' ),
	showAction: toggle( 'Show External Action', 'Show the external event action.', 'on' ),
	showTerms: toggle( 'Show Categories and Tags', 'Show event categories and tags.', 'on' ),
};

export const compositeModuleDefinitions = [
	{
		slug: 'event-details',
		component: 'details',
		title: 'Event Details',
		icon: 'divi/module-text',
		selector: '.wpse-single-event',
		controls: {
			eventId: {
				...select(
					'Event',
					'Use the current event in a template, or select one public event for a regular page.',
					{},
					'0',
				),
				group: 'contentMain',
			},
			...Object.fromEntries(
				Object.entries( detailsToggles ).map( ( [ key, control ] ) => [
					key,
					{ ...control, group: 'contentDisplay' },
				] ),
			),
			headingLevel: {
				...select(
					'Title Heading Level',
					'Choose the semantic heading level used by the event title.',
					Object.fromEntries(
						[ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( ( value ) => [
							value,
							{ label: value.toUpperCase() },
						] ),
					),
					'h1',
				),
				group: 'contentDisplay',
			},
			dateLabel: { ...text( 'Date Label', 'Leave empty to use the translated default.' ), group: 'contentLabels' },
			venueLabel: { ...text( 'Venue Label', 'Leave empty to use the translated default.' ), group: 'contentLabels' },
			locationLabel: { ...text( 'Location Link Text', 'Leave empty to use the translated default.' ), group: 'contentLabels' },
			actionLabel: { ...text( 'External Action Text', 'Leave empty to use the label saved on the event.' ), group: 'contentLabels' },
			categoriesLabel: { ...text( 'Categories Label', 'Leave empty to use the translated default.' ), group: 'contentLabels' },
			tagsLabel: { ...text( 'Tags Label', 'Leave empty to use the translated default.' ), group: 'contentLabels' },
		},
	},
	{
		slug: 'event-list',
		component: 'list',
		title: 'Event List / Grid',
		icon: 'divi/module-blog',
		selector: '.wpse-events',
		controls: {
			view: { ...select( 'Layout', 'Choose a list or grid layout.', { grid: { label: 'Grid' }, list: { label: 'List' } }, 'grid' ), group: 'contentMain' },
			period: { ...select( 'Period', 'Choose which events are included.', { upcoming: { label: 'Upcoming' }, past: { label: 'Past' }, all: { label: 'All' } }, 'upcoming' ), group: 'contentMain' },
			limit: { ...text( 'Events per Page', 'Enter a number from 1 through 50.' ), defaultValue: '12', group: 'contentMain' },
			columns: { ...select( 'Grid Columns', 'Choose the number of grid columns.', { 1: { label: '1' }, 2: { label: '2' }, 3: { label: '3' }, 4: { label: '4' } }, '3' ), group: 'contentMain' },
			categories: { ...checkboxes( 'Categories', 'Optionally include only selected event categories.' ), optionSource: 'categories', group: 'contentMain' },
			tags: { ...checkboxes( 'Tags', 'Optionally include only selected event tags.' ), optionSource: 'tags', group: 'contentMain' },
			filters: { ...toggle( 'Show Visitor Filters', 'Let visitors filter this event list.' ), group: 'contentDisplay' },
			pagination: { ...toggle( 'Show Pagination', 'Show page navigation when more results exist.', 'on' ), group: 'contentDisplay' },
			showImage: { ...toggle( 'Show Image', 'Show featured images on event cards.', 'on' ), group: 'contentDisplay' },
			showTitle: { ...toggle( 'Show Title', 'Show event titles on cards.', 'on' ), group: 'contentDisplay' },
			showDate: { ...toggle( 'Show Date and Time', 'Show event dates and times on cards.', 'on' ), group: 'contentDisplay' },
			showExcerpt: { ...toggle( 'Show Excerpt', 'Show event excerpts on cards.', 'on' ), group: 'contentDisplay' },
			showLocation: { ...toggle( 'Show Location', 'Show event locations on cards.', 'on' ), group: 'contentDisplay' },
			excerptLength: { ...text( 'Excerpt Length', 'Enter a word count from 1 through 100.' ), defaultValue: '30', group: 'contentDisplay' },
			headingLevel: { ...select( 'Title Heading Level', 'Choose the semantic heading level for card titles.', { h2: { label: 'H2' }, h3: { label: 'H3' }, h4: { label: 'H4' }, h5: { label: 'H5' }, h6: { label: 'H6' } }, 'h3' ), group: 'contentDisplay' },
		},
	},
	{
		slug: 'event-calendar',
		component: 'calendar',
		title: 'Event Calendar',
		icon: 'divi/module-countdown-timer',
		selector: '.wpse-calendar',
		controls: {
			initialView: { ...select( 'Desktop View', 'Choose the initial desktop calendar view.', { month: { label: 'Month' }, list: { label: 'List' } }, 'month' ), group: 'contentMain' },
			mobileView: { ...select( 'Mobile View', 'Choose the initial narrow-screen calendar view.', { month: { label: 'Month' }, list: { label: 'List' } }, 'list' ), group: 'contentMain' },
			initialDate: { ...text( 'Initial Date', 'Optional. Use YYYY-MM-DD to open on a specific date.' ), group: 'contentMain' },
			categories: { ...checkboxes( 'Initial Categories', 'Apply selected categories when the calendar first loads.' ), optionSource: 'categories', group: 'contentMain' },
			tags: { ...checkboxes( 'Initial Tags', 'Apply selected tags when the calendar first loads.' ), optionSource: 'tags', group: 'contentMain' },
			filters: { ...toggle( 'Show Visitor Filters', 'Let visitors filter by available categories and tags.', 'on' ), group: 'contentDisplay' },
			showNavigation: { ...toggle( 'Show Previous and Next', 'Show previous and next calendar buttons.', 'on' ), group: 'contentDisplay' },
			showToday: { ...toggle( 'Show Today', 'Show the Today button.', 'on' ), group: 'contentDisplay' },
			showViewSwitcher: { ...toggle( 'Show View Switcher', 'Show the month and list view buttons.', 'on' ), group: 'contentDisplay' },
			fallbackHeadingLevel: { ...select( 'Fallback Heading Level', 'Choose the heading level used by the no-JavaScript fallback.', { h2: { label: 'H2' }, h3: { label: 'H3' }, h4: { label: 'H4' }, h5: { label: 'H5' }, h6: { label: 'H6' } }, 'h3' ), group: 'contentDisplay' },
		},
	},
];

const standardModuleAttribute = () => ( {
	type: 'object',
	selector: '{{selector}}',
	settings: {
		meta: { adminLabel: {}, meta: {} },
		advanced: {
			elements: {},
			html: {},
			htmlAttributes: {},
			link: {},
			loop: {},
			text: {},
		},
		decoration: {
			animation: {},
			attributes: {},
			background: {},
			border: {},
			boxShadow: {},
			conditions: {},
			disabledOn: {},
			filters: {},
			interactions: {},
			layout: {},
			order: {},
			overflow: {},
			position: {},
			scroll: {},
			sizing: {},
			spacing: {},
			sticky: {},
			transform: {},
			transition: {},
			zIndex: {},
		},
	},
} );

const fieldItem = ( key, definition, priority ) => ( {
	groupSlug: definition.group ?? 'contentEvent',
	priority,
	render: true,
	subName: key,
	label: definition.label,
	description: definition.description,
	features: {
		sticky: false,
		responsive: false,
		hover: false,
		dynamicContent: false,
	},
	component: {
		name: definition.component,
		type: 'field',
		...( definition.options ? { props: { options: definition.options } } : {} ),
	},
} );

export const buildCompositeModuleMetadata = ( definition ) => {
	const defaults = Object.fromEntries(
		Object.entries( definition.controls ).map( ( [ key, control ] ) => [
			key,
			control.defaultValue,
		] ),
	);
	const items = Object.fromEntries(
		Object.entries( definition.controls ).map( ( [ key, control ], index ) => [
			key,
			fieldItem( key, control, 10 * ( index + 1 ) ),
		] ),
	);

	return {
		name: `mime-simple-events-calendar/${ definition.slug }`,
		d4Shortcode: '',
		title: definition.title,
		titles: definition.title,
		moduleIcon: definition.icon,
		moduleClassName: `wpse_divi_${ definition.slug.replaceAll( '-', '_' ) }`,
		moduleOrderClassName: `wpse_divi_${ definition.slug.replaceAll( '-', '_' ) }`,
		category: 'module',
		attributes: {
			module: standardModuleAttribute(),
			event: {
				type: 'object',
				default: { innerContent: { desktop: { value: defaults } } },
				settings: { innerContent: { groupType: 'group-items', items } },
			},
			content: {
				type: 'object',
				selector: `{{selector}} ${ definition.selector }`,
				settings: {
					decoration: {
						font: {
							groupType: 'group-item',
							item: {
								groupSlug: 'designContentText',
								priority: 10,
								render: true,
								component: {
									name: 'divi/font',
									type: 'group',
									props: { grouped: false, fieldLabel: 'Content', dynamicSubgroupHost: true },
								},
							},
						},
					},
				},
			},
		},
		customCssFields: {
			content: { subName: 'content', label: definition.title, selectorSuffix: ` ${ definition.selector }` },
		},
		settings: {
			content: 'auto',
			design: 'auto',
			advanced: 'auto',
			groups: {
				contentMain: { panel: 'content', priority: 10, groupName: 'contentMain', multiElements: true, component: { name: 'divi/composite', props: { groupLabel: 'Events' } } },
				contentDisplay: { panel: 'content', priority: 20, groupName: 'contentDisplay', multiElements: true, component: { name: 'divi/composite', props: { groupLabel: 'Display' } } },
				contentLabels: { panel: 'content', priority: 30, groupName: 'contentLabels', multiElements: true, component: { name: 'divi/composite', props: { groupLabel: 'Labels' } } },
				designContentText: { panel: 'design', priority: 20, groupName: 'contentText', multiElements: true, component: { name: 'divi/composite', props: { groupLabel: 'Content Text', clipboardCategory: 'style', presetGroup: 'divi/font', dynamicSubgroupHost: true } } },
			},
		},
	};
};

export const buildAtomicModuleMetadata = ( definition ) => {
	const controls = {
		eventId: {
			label: 'Event',
			description:
				'Use the current event in a template, or select one public event for a regular page.',
			defaultValue: '0',
			component: 'divi/select',
			options: {},
		},
		...( definition.controls ?? {} ),
	};
	const defaults = Object.fromEntries(
		Object.entries( controls ).map( ( [ key, control ] ) => [
			key,
			control.defaultValue,
		] ),
	);
	const items = Object.fromEntries(
		Object.entries( controls ).map( ( [ key, control ], index ) => [
			key,
			fieldItem( key, control, 10 * ( index + 1 ) ),
		] ),
	);
	const fieldAttribute = {
		type: 'object',
		selector: `{{selector}} ${ definition.selector }`,
	};
	const groups = {
		contentEvent: {
			panel: 'content',
			priority: 10,
			groupName: 'contentEvent',
			multiElements: true,
			component: {
				name: 'divi/composite',
				props: { groupLabel: 'Event' },
			},
		},
	};

	if ( definition.textStyle !== false ) {
		fieldAttribute.settings = {
			decoration: {
				font: {
					groupType: 'group-item',
					item: {
						groupSlug: 'designFieldText',
						priority: 10,
						render: true,
						component: {
							name: 'divi/font',
							type: 'group',
							props: {
								grouped: false,
								fieldLabel: 'Field',
								dynamicSubgroupHost: true,
							},
						},
					},
				},
			},
		};
		groups.designFieldText = {
			panel: 'design',
			priority: 20,
			groupName: 'fieldText',
			multiElements: true,
			component: {
				name: 'divi/composite',
				props: {
					groupLabel: 'Field Text',
					clipboardCategory: 'style',
					presetGroup: 'divi/font',
					dynamicSubgroupHost: true,
				},
			},
		};
	}

	return {
		name: `mime-simple-events-calendar/${ definition.slug }`,
		d4Shortcode: '',
		title: definition.title,
		titles: definition.title,
		moduleIcon: definition.icon,
		moduleClassName: `wpse_divi_${ definition.slug.replaceAll( '-', '_' ) }`,
		moduleOrderClassName: `wpse_divi_${ definition.slug.replaceAll( '-', '_' ) }`,
		category: 'module',
		attributes: {
			module: standardModuleAttribute(),
			event: {
				type: 'object',
				default: {
					innerContent: { desktop: { value: defaults } },
				},
				settings: {
					innerContent: { groupType: 'group-items', items },
				},
			},
			field: fieldAttribute,
		},
		customCssFields: {
			field: {
				subName: 'field',
				label: definition.title,
				selectorSuffix: ` ${ definition.selector }`,
			},
		},
		settings: {
			content: 'auto',
			design: 'auto',
			advanced: 'auto',
			groups,
		},
	};
};
