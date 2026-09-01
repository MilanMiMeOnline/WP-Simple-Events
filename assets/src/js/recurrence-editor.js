/* global wpseRecurrenceEditor */

import {
	addIsoDateDays,
	boundedOccurrenceWindow,
	followingMutationFromForm,
	followingOccurrenceChoices,
	initialOccurrenceWindowStart,
	isIsoDate,
	monthlyPatternFromDefinition,
	monthlyRuleFields,
	occurrenceChangesFromForm,
	occurrenceDateControls,
	occurrenceEditFormError,
	occurrenceEditFormFromContext,
	onlyThisMutationChanged,
	onlyThisMutationFromChanges,
	orderedIsoWeekdays,
	recurrenceAnchorParts,
	showsRecurrenceEndControls,
} from './recurrence-editor-utils.mjs';

/**
 * Scope-first Gutenberg recurrence editor.
 *
 * Canonical recurrence is saved only through the authenticated preview-confirm
 * API. Ordinary event fields remain owned by WordPress' normal post save.
 */
const { apiFetch } = wp;
const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
const {
	Button,
	CheckboxControl,
	ComboboxControl,
	Notice,
	PanelRow,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} = wp.components;
const { useSelect } = wp.data;
const { PluginDocumentSettingPanel } = wp.editor;
const { createElement: el, Fragment, useEffect, useMemo, useState } = wp.element;
const { __, _n, sprintf } = wp.i18n;
const { registerPlugin } = wp.plugins;

const weekdays = [
	__( 'Monday', 'mime-simple-events-calendar' ),
	__( 'Tuesday', 'mime-simple-events-calendar' ),
	__( 'Wednesday', 'mime-simple-events-calendar' ),
	__( 'Thursday', 'mime-simple-events-calendar' ),
	__( 'Friday', 'mime-simple-events-calendar' ),
	__( 'Saturday', 'mime-simple-events-calendar' ),
	__( 'Sunday', 'mime-simple-events-calendar' ),
];

const configurationFromDefinition = ( definition, anchor, recurring = true ) => {
	let frequency = definition.frequency;

	if ( definition.type === 'specific_dates' ) {
		frequency = recurring ? 'specific_dates' : 'once';
	}
	const end = definition.end || { mode: 'never' };

	return {
		frequency,
		interval: String( definition.interval || 1 ),
		endMode: end.mode,
		untilDate: end.date || '',
		count: String( end.count || 10 ),
		weekdays: definition.weekdays || [ anchor.weekday ],
		monthlyPattern: monthlyPatternFromDefinition( definition ),
		monthDay: definition.month_day || anchor.day,
		ordinal: definition.ordinal || anchor.ordinal,
		weekday: definition.weekday || anchor.weekday,
		specificDates:
			definition.type === 'specific_dates'
				? definition.dates.join( '\n' )
				: anchor.date,
	};
};

const recurrenceConfiguration = ( context ) =>
	configurationFromDefinition(
		context.aggregate.segments[ 0 ].definition,
		recurrenceAnchorParts( context.aggregate ),
		context.recurring,
	);

const followingConfiguration = ( editContext ) => {
	const target = editContext.target;
	const segments = editContext.context.aggregate.segments;
	const segment = [ ...segments ]
		.reverse()
		.find( ( candidate ) => candidate.anchor <= target );
	const anchor = recurrenceAnchorParts( { segments: [ { anchor: target } ] } );
	const configuration = configurationFromDefinition(
		segment.definition,
		anchor,
	);

	if ( segment.definition.type === 'specific_dates' ) {
		const dates = segment.definition.dates.filter( ( date ) => date >= anchor.date );

		configuration.specificDates = [
			...new Set( [ anchor.date, ...dates ] ),
		].sort().join( '\n' );
	}

	return configuration;
};

const recurrenceEnd = ( configuration ) => {
	if ( configuration.endMode === 'until' ) {
		return { mode: 'until', date: configuration.untilDate };
	}

	if ( configuration.endMode === 'count' ) {
		return { mode: 'count', count: Number( configuration.count ) };
	}

	return { mode: 'never' };
};

const definitionFromConfiguration = ( configuration, anchor ) => {
	if ( configuration.frequency === 'specific_dates' ) {
		const dates = [
			...new Set(
				configuration.specificDates
					.split( /[\s,;]+/ )
					.map( ( value ) => value.trim() )
					.filter( Boolean ),
			),
		].sort();

		return { type: 'specific_dates', dates };
	}

	const definition = {
		type: 'rule',
		frequency: configuration.frequency,
		interval: Number( configuration.interval ),
		end: recurrenceEnd( configuration ),
	};

	if ( configuration.frequency === 'weekly' ) {
		definition.weekdays = [ ...configuration.weekdays ].sort(
			( left, right ) => left - right,
		);
	} else if ( configuration.frequency === 'monthly' ) {
		Object.assign(
			definition,
			monthlyRuleFields( configuration.monthlyPattern, anchor ),
		);
	} else if ( configuration.frequency === 'yearly' ) {
		definition.month = anchor.month;
		definition.month_day = anchor.day;
	}

	return definition;
};

const validateConfiguration = ( configuration, anchor ) => {
	if ( configuration.frequency === 'once' ) {
		return __( 'Choose how this event repeats.', 'mime-simple-events-calendar' );
	}

	const interval = Number( configuration.interval );

	if ( ! Number.isInteger( interval ) || interval < 1 || interval > 999 ) {
		return __( 'Repeat interval must be between 1 and 999.', 'mime-simple-events-calendar' );
	}

	if (
		configuration.frequency === 'weekly' &&
		( configuration.weekdays.length === 0 ||
			! configuration.weekdays.includes( anchor.weekday ) )
	) {
		return __( 'Weekly recurrence must include the schedule’s first weekday.', 'mime-simple-events-calendar' );
	}

	if ( configuration.frequency === 'specific_dates' ) {
		const dates = configuration.specificDates
			.split( /[\s,;]+/ )
			.map( ( value ) => value.trim() )
			.filter( Boolean );

		if ( dates.length === 0 || ! dates.includes( anchor.date ) ) {
			return __( 'Selected dates must include the schedule’s first date.', 'mime-simple-events-calendar' );
		}
	}

	if ( configuration.endMode === 'until' ) {
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( configuration.untilDate ) ) {
			return __( 'Choose a valid inclusive end date.', 'mime-simple-events-calendar' );
		}

		if ( configuration.untilDate < anchor.date ) {
			return __( 'The recurrence end cannot be before the first event.', 'mime-simple-events-calendar' );
		}
	}

	if ( configuration.endMode === 'count' ) {
		const count = Number( configuration.count );

		if ( ! Number.isInteger( count ) || count < 1 || count > 10000 ) {
			return __( 'Number of events must be between 1 and 10,000.', 'mime-simple-events-calendar' );
		}
	}

	return '';
};

const scheduleSummary = ( configuration ) => {
	const interval = Number( configuration.interval ) || 1;
	const frequencyLabels = {
		daily: [
			__( 'day', 'mime-simple-events-calendar' ),
			__( 'days', 'mime-simple-events-calendar' ),
		],
		weekly: [
			__( 'week', 'mime-simple-events-calendar' ),
			__( 'weeks', 'mime-simple-events-calendar' ),
		],
		monthly: [
			__( 'month', 'mime-simple-events-calendar' ),
			__( 'months', 'mime-simple-events-calendar' ),
		],
		yearly: [
			__( 'year', 'mime-simple-events-calendar' ),
			__( 'years', 'mime-simple-events-calendar' ),
		],
	};

	if ( configuration.frequency === 'once' ) {
		return __( 'This event does not repeat.', 'mime-simple-events-calendar' );
	}

	if ( configuration.frequency === 'specific_dates' ) {
		const count = configuration.specificDates
			.split( /[\s,;]+/ )
			.filter( Boolean ).length;

		return sprintf(
			/* translators: %d: number of selected event dates. */
			__( 'Repeats on %d selected dates.', 'mime-simple-events-calendar' ),
			count,
		);
	}

	const units = frequencyLabels[ configuration.frequency ];
	let summary =
		interval === 1
			? sprintf(
				/* translators: %s: day, week, month or year. */
				__( 'Repeats every %s', 'mime-simple-events-calendar' ),
				units[ 0 ],
			)
			: sprintf(
				/* translators: 1: interval number, 2: plural unit such as days or weeks. */
				__( 'Repeats every %1$d %2$s', 'mime-simple-events-calendar' ),
				interval,
				units[ 1 ],
			);

	if ( configuration.endMode === 'until' ) {
		summary = `${ summary } ${ sprintf(
			/* translators: %s: inclusive recurrence end date. */
			__( 'through %s.', 'mime-simple-events-calendar' ),
			configuration.untilDate,
		) }`;
	} else if ( configuration.endMode === 'count' ) {
		summary = `${ summary }, ${ sprintf(
			/* translators: %d: total number of scheduled occurrences. */
			__( '%d times.', 'mime-simple-events-calendar' ),
			Number( configuration.count ),
		) }`;
	} else {
		summary = `${ summary }, ${ __(
			'with no end date.',
			'mime-simple-events-calendar',
		) }`;
	}

	return summary;
};

const NoEndProjectionHelp = () =>
	el(
		'p',
		{ className: 'components-base-control__help wpse-recurrence-projection-help' },
		sprintf(
			/* translators: %d: rolling public recurrence projection in days. */
			__(
				'This preview is limited to the next %d days. The series itself has no end date; its public occurrence window is renewed automatically.',
				'mime-simple-events-calendar',
			),
			Number( wpseRecurrenceEditor.horizonDays ),
		),
	);

const mutationFromConfiguration = ( context, configuration ) => {
	const aggregate = JSON.parse( JSON.stringify( context.aggregate ) );
	const anchor = recurrenceAnchorParts( aggregate );
	aggregate.segments[ 0 ].definition = definitionFromConfiguration(
		configuration,
		anchor,
	);
	const horizonEnd = addIsoDateDays(
		anchor.date,
		wpseRecurrenceEditor.horizonDays,
	);
	const throughDate =
		configuration.endMode === 'until' &&
		configuration.untilDate < horizonEnd
			? configuration.untilDate
			: horizonEnd;

	return {
		aggregate,
		scope: 'complete_series',
		target: '',
		revision: context.revision,
		from_date: anchor.date,
		through_date: throughDate,
		max_rows: Number( wpseRecurrenceEditor.maxRows ),
	};
};

const occurrenceWindow = ( fromDate ) =>
	boundedOccurrenceWindow(
		fromDate,
		wpseRecurrenceEditor.horizonDays,
		wpseRecurrenceEditor.maxRows,
	);

const disableMutation = ( context, target, fromDate ) => ( {
	target,
	revision: context.revision,
	...occurrenceWindow( fromDate ),
} );

const occurrenceOptionLabel = ( occurrence ) => {
	const statusLabels = {
		scheduled: __( 'scheduled', 'mime-simple-events-calendar' ),
		cancelled: __( 'cancelled', 'mime-simple-events-calendar' ),
		postponed: __( 'postponed', 'mime-simple-events-calendar' ),
	};

	return sprintf(
		/* translators: 1: local event start, 2: event status. */
		__( '%1$s — %2$s', 'mime-simple-events-calendar' ),
		occurrence.start_local,
		statusLabels[ occurrence.status ] || occurrence.status,
	);
};

const impactLabel = ( impact ) =>
	[
		sprintf(
			/* translators: %d: added events. */
			__( '%d added', 'mime-simple-events-calendar' ),
			impact.added,
		),
		sprintf(
			/* translators: %d: removed events. */
			__( '%d removed', 'mime-simple-events-calendar' ),
			impact.removed,
		),
		sprintf(
			/* translators: %d: moved events. */
			__( '%d moved', 'mime-simple-events-calendar' ),
			impact.moved,
		),
		sprintf(
			/* translators: %d: events with a changed status. */
			__( '%d status changes', 'mime-simple-events-calendar' ),
			impact.status_changed,
		),
	].join( ', ' );

const impactItemLabel = ( item ) => {
	const changeLabels = {
		added: __( 'added', 'mime-simple-events-calendar' ),
		removed: __( 'removed', 'mime-simple-events-calendar' ),
		moved: __( 'moved', 'mime-simple-events-calendar' ),
		status_changed: __( 'status changed', 'mime-simple-events-calendar' ),
		source_changed: __( 'source changed', 'mime-simple-events-calendar' ),
	};
	const visibleChanges = item.changes
		.map( ( change ) => changeLabels[ change ] || change )
		.join( ', ' );
	const changes =
		visibleChanges ||
		( item.exception_affected
			? __( 'individual fields changed', 'mime-simple-events-calendar' )
			: '' );
	const before = item.before?.start_local || '';
	const after = item.after?.start_local || '';

	if ( before && after && before !== after ) {
		return sprintf(
			/* translators: 1: previous local start, 2: new local start, 3: change summary. */
			__( '%1$s → %2$s: %3$s', 'mime-simple-events-calendar' ),
			before,
			after,
			changes,
		);
	}

	return sprintf(
		/* translators: 1: effective local start, 2: change summary. */
		__( '%1$s: %2$s', 'mime-simple-events-calendar' ),
		after || before || item.recurrence_id,
		changes,
	);
};

const occurrenceOverrideHelp = ( overridden, value, canHide ) => {
	if ( ! overridden ) {
		return __(
			'Inherited from the series. Editing creates an individual value for this occurrence.',
			'mime-simple-events-calendar',
		);
	}

	if ( canHide && value === '' ) {
		return __(
			'The series value is hidden for this occurrence.',
			'mime-simple-events-calendar',
		);
	}

	return __(
		'This occurrence uses an individual value.',
		'mime-simple-events-calendar',
	);
};

const OccurrenceTextOverrideControl = ( {
	label,
	value,
	overridden,
	canHide = false,
	multiline = false,
	type = 'text',
	maxLength,
	disabled,
	onChange,
	onRestore,
} ) => {
	const Control = multiline ? TextareaControl : TextControl;

	return el(
		'div',
		{ className: 'wpse-occurrence-override-field' },
		el( Control, {
			label,
			value,
			type: multiline ? undefined : type,
			maxLength,
			disabled,
			help: occurrenceOverrideHelp( overridden, value, canHide ),
			onChange,
		} ),
		overridden &&
			el(
				Button,
				{
					variant: 'tertiary',
					disabled,
					onClick: onRestore,
				},
				__( 'Use series value', 'mime-simple-events-calendar' ),
			),
	);
};

const OccurrenceFeaturedImageControl = ( {
	imageId,
	overridden,
	disabled,
	onChange,
	onRestore,
} ) => {
	const media = useSelect(
		( select ) => ( imageId > 0 ? select( 'core' ).getMedia( imageId ) : null ),
		[ imageId ],
	);
	const previewUrl =
		media?.media_details?.sizes?.thumbnail?.source_url || media?.source_url || '';
	const help = occurrenceOverrideHelp( overridden, imageId === 0 ? '' : String( imageId ), true );

	return el(
		'div',
		{
			className: 'wpse-occurrence-override-field wpse-occurrence-image-field',
			role: 'group',
			'aria-label': __( 'Featured image', 'mime-simple-events-calendar' ),
		},
		el(
			'p',
			{ className: 'wpse-occurrence-field-label' },
			__( 'Featured image', 'mime-simple-events-calendar' ),
		),
		previewUrl &&
			el( 'img', {
				className: 'wpse-occurrence-image-preview',
				src: previewUrl,
				alt: '',
				'aria-hidden': true,
			} ),
		el(
			'p',
			{ className: 'components-base-control__help' },
			help,
		),
		el(
			MediaUploadCheck,
			{
				fallback: el(
					'p',
					{ className: 'components-base-control__help' },
					__( 'You do not have permission to choose an image.', 'mime-simple-events-calendar' ),
				),
			},
			el( MediaUpload, {
				allowedTypes: [ 'image' ],
				value: imageId > 0 ? imageId : undefined,
				onSelect: ( selected ) => onChange( Number( selected?.id || 0 ) ),
				render: ( { open } ) =>
					el(
						Button,
						{
							variant: 'secondary',
							disabled,
							onClick: open,
						},
						imageId > 0
							? __( 'Replace image', 'mime-simple-events-calendar' )
							: __( 'Choose image', 'mime-simple-events-calendar' ),
					),
			} ),
		),
		imageId > 0 &&
			el(
				Button,
				{
					variant: 'tertiary',
					disabled,
					onClick: () => onChange( 0 ),
				},
				__( 'Hide image for this occurrence', 'mime-simple-events-calendar' ),
			),
		overridden &&
			el(
				Button,
				{
					variant: 'tertiary',
					disabled,
					onClick: onRestore,
				},
				__( 'Use series image', 'mime-simple-events-calendar' ),
			),
	);
};

const RecurrencePanel = () => {
	const editor = useSelect( ( select ) => {
		const store = select( 'core/editor' );

		return {
			id: store.getCurrentPostId(),
			type: store.getCurrentPostType(),
			dirty: store.isEditedPostDirty(),
			saving: store.isSavingPost() || store.isAutosavingPost(),
		};
	}, [] );
	const [ context, setContext ] = useState( null );
	const [ configuration, setConfiguration ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ success, setSuccess ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const [ occurrences, setOccurrences ] = useState( [] );
	const [ survivor, setSurvivor ] = useState( '' );
	const [ occurrenceWindowStart, setOccurrenceWindowStart ] = useState( '' );
	const [ loadedOccurrenceWindowStart, setLoadedOccurrenceWindowStart ] = useState( '' );
	const [ occurrenceAnchorFallback, setOccurrenceAnchorFallback ] = useState( false );
	const [ loadingOccurrences, setLoadingOccurrences ] = useState( false );
	const [ editorScope, setEditorScope ] = useState( 'complete_series' );
	const [ selectedOccurrence, setSelectedOccurrence ] = useState( '' );
	const [ occurrenceEditContext, setOccurrenceEditContext ] = useState( null );
	const [ occurrenceForm, setOccurrenceForm ] = useState( null );
	const [ followingForm, setFollowingForm ] = useState( null );
	const [ followingRule, setFollowingRule ] = useState( null );
	const [ loadingOccurrenceContext, setLoadingOccurrenceContext ] = useState( false );
	const onlyThis = editorScope === 'only_this';
	const thisAndFollowing = editorScope === 'this_and_following';
	const occurrenceScoped = onlyThis || thisAndFollowing;
	const advanced =
		context &&
		( context.aggregate.segments.length > 1 ||
			context.aggregate.manuals.length > 0 ||
			context.aggregate.exclusions.length > 0 ||
			context.aggregate.overrides.length > 0 );
	const anchor = useMemo(
		() => ( context ? recurrenceAnchorParts( context.aggregate ) : null ),
		[ context ],
	);
	const followingAnchor = useMemo(
		() => {
			const date = isIsoDate( followingForm?.startDate )
				? followingForm.startDate
				: occurrenceEditContext?.target?.slice( 0, 10 );

			return date
				? recurrenceAnchorParts( {
					segments: [ { anchor: `${ date }T00:00:00` } ],
				} )
				: null;
		},
		[ followingForm?.startDate, occurrenceEditContext?.target ],
	);
	const weekdayOrder = useMemo(
		() => orderedIsoWeekdays( Number( wpseRecurrenceEditor.startOfWeek ) ),
		[],
	);
	const repeatOptions = [
		{
			label: __( 'Does not repeat', 'mime-simple-events-calendar' ),
			value: 'once',
		},
		{ label: __( 'Daily', 'mime-simple-events-calendar' ), value: 'daily' },
		{ label: __( 'Weekly', 'mime-simple-events-calendar' ), value: 'weekly' },
		{ label: __( 'Monthly', 'mime-simple-events-calendar' ), value: 'monthly' },
		{ label: __( 'Yearly', 'mime-simple-events-calendar' ), value: 'yearly' },
		{
			label: __( 'Selected dates', 'mime-simple-events-calendar' ),
			value: 'specific_dates',
		},
	];
	const selectableOccurrences = thisAndFollowing
		? followingOccurrenceChoices( occurrences, context?.aggregate )
		: occurrences;

	useEffect( () => {
		if (
			editor.type !== wpseRecurrenceEditor.eventPostType ||
			! Number.isInteger( editor.id ) ||
			editor.id < 1
		) {
			return;
		}

		let active = true;
		setLoading( true );
		setError( '' );

		apiFetch( {
			path: `/wpse/v1/events/${ editor.id }/recurrence`,
		} )
			.then( ( value ) => {
				if ( ! active ) {
					return;
				}

				setContext( value );
				setConfiguration( recurrenceConfiguration( value ) );
				setOccurrenceWindowStart(
					initialOccurrenceWindowStart(
						value.aggregate,
						wpseRecurrenceEditor.today,
						wpseRecurrenceEditor.horizonDays,
					),
				);
				setLoadedOccurrenceWindowStart( '' );
				setOccurrenceAnchorFallback( false );
				setOccurrences( [] );
				setSurvivor( '' );
				setEditorScope( 'complete_series' );
				setSelectedOccurrence( '' );
				setOccurrenceEditContext( null );
				setOccurrenceForm( null );
				setFollowingForm( null );
				setFollowingRule( null );
			} )
			.catch( () => {
				if ( active ) {
					setError(
						__(
							'Save valid event dates before configuring recurrence.',
							'mime-simple-events-calendar',
						),
					);
				}
			} )
			.finally( () => active && setLoading( false ) );

		return () => {
			active = false;
		};
	}, [ editor.id, editor.type ] );

	useEffect( () => {
		if ( ! context ) {
			return;
		}

		document.dispatchEvent(
			new CustomEvent( 'wpse:recurrence-state', {
				detail: { recurring: context.recurring === true },
			} ),
		);
	}, [ context ] );

	if ( editor.type !== wpseRecurrenceEditor.eventPostType ) {
		return null;
	}

	const change = ( changes ) => {
		setConfiguration( { ...configuration, ...changes } );
		setPreview( null );
		setSuccess( '' );
		setError( '' );
	};

	const loadOccurrences = async (
		requestedStart,
		fallbackToAnchor = false,
		selectionScope = editorScope,
	) => {
		if ( ! context?.recurring || ! isIsoDate( requestedStart ) ) {
			return;
		}

		setLoadingOccurrences( true );
		setError( '' );

		try {
			const request = async ( fromDate ) => {
				const query = new URLSearchParams( occurrenceWindow( fromDate ) );

				return apiFetch( {
					path: `/wpse/v1/events/${ editor.id }/recurrence/occurrences?${ query.toString() }`,
				} );
			};
			let loadedStart = requestedStart;
			let value = await request( loadedStart );
			const anchorDate = recurrenceAnchorParts( context.aggregate ).date;
			let usedAnchorFallback = false;

			if (
				fallbackToAnchor &&
				( value.occurrences || [] ).length === 0 &&
				loadedStart !== anchorDate
			) {
				loadedStart = anchorDate;
				value = await request( loadedStart );
				usedAnchorFallback = true;
				setOccurrenceWindowStart( loadedStart );
			}

			setLoadedOccurrenceWindowStart( loadedStart );
			setOccurrenceAnchorFallback( usedAnchorFallback );
			const loadedOccurrences = value.occurrences || [];
			const selectable =
				selectionScope === 'this_and_following'
					? followingOccurrenceChoices( loadedOccurrences, context.aggregate )
					: loadedOccurrences;

			setOccurrences( loadedOccurrences );
			setSurvivor( value.occurrences?.[ 0 ]?.recurrence_id || '' );
			setSelectedOccurrence( selectable[ 0 ]?.recurrence_id || '' );
			setOccurrenceEditContext( null );
			setOccurrenceForm( null );
			setFollowingForm( null );
			setFollowingRule( null );
			setPreview( null );
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The occurrences could not be loaded. Reload the event and try again.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setLoadingOccurrences( false );
		}
	};

	const clearOccurrenceSelection = () => {
		setLoadedOccurrenceWindowStart( '' );
		setOccurrenceAnchorFallback( false );
		setOccurrences( [] );
		setSelectedOccurrence( '' );
		setOccurrenceEditContext( null );
		setOccurrenceForm( null );
		setFollowingForm( null );
		setFollowingRule( null );
		setPreview( null );
		setError( '' );
	};

	const enterOnlyThis = () => {
		setEditorScope( 'only_this' );
		setPreview( null );
		setSuccess( '' );
		setError( '' );

		if ( occurrences.length === 0 || ! loadedOccurrenceWindowStart ) {
			loadOccurrences( occurrenceWindowStart, true, 'only_this' );
		}
	};

	const enterThisAndFollowing = () => {
		setEditorScope( 'this_and_following' );
		setOccurrenceEditContext( null );
		setOccurrenceForm( null );
		setFollowingForm( null );
		setFollowingRule( null );
		setPreview( null );
		setSuccess( '' );
		setError( '' );

		if ( occurrences.length === 0 || ! loadedOccurrenceWindowStart ) {
			loadOccurrences( occurrenceWindowStart, true, 'this_and_following' );
			return;
		}

		setSelectedOccurrence(
			followingOccurrenceChoices( occurrences, context.aggregate )[ 0 ]
				?.recurrence_id || '',
		);
	};

	const leaveOccurrenceScope = () => {
		setEditorScope( 'complete_series' );
		setOccurrenceEditContext( null );
		setOccurrenceForm( null );
		setFollowingForm( null );
		setFollowingRule( null );
		setPreview( null );
		setSuccess( '' );
		setError( '' );
	};

	const loadOccurrenceContext = async () => {
		if ( ! selectedOccurrence || ! loadedOccurrenceWindowStart ) {
			setError(
				__( 'Choose an occurrence to edit.', 'mime-simple-events-calendar' ),
			);
			return;
		}

		setLoadingOccurrenceContext( true );
		setError( '' );
		setSuccess( '' );
		setPreview( null );

		try {
			const query = new URLSearchParams( {
				...occurrenceWindow( loadedOccurrenceWindowStart ),
				target: selectedOccurrence,
			} );
			const value = await apiFetch( {
				path: `/wpse/v1/events/${ editor.id }/recurrence/occurrence?${ query.toString() }`,
			} );

			setOccurrenceEditContext( value );

			if ( thisAndFollowing ) {
				setOccurrenceForm( null );
				setFollowingForm( occurrenceDateControls( value.inherited ) );
				setFollowingRule( followingConfiguration( value ) );
			} else {
				setOccurrenceForm( occurrenceEditFormFromContext( value ) );
				setFollowingForm( null );
				setFollowingRule( null );
			}
		} catch ( apiError ) {
			setOccurrenceEditContext( null );
			setOccurrenceForm( null );
			setFollowingForm( null );
			setFollowingRule( null );
			setError(
				apiError?.message ||
					__(
						'This occurrence could not be loaded. Search again and retry.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setLoadingOccurrenceContext( false );
		}
	};

	const changeOccurrenceForm = ( changes ) => {
		setOccurrenceForm( { ...occurrenceForm, ...changes } );
		setPreview( null );
		setSuccess( '' );
		setError( '' );
	};

	const changeFollowingForm = ( changes ) => {
		setFollowingForm( { ...followingForm, ...changes } );
		setPreview( null );
		setSuccess( '' );
		setError( '' );
	};

	const changeFollowingRule = ( changes ) => {
		setFollowingRule( { ...followingRule, ...changes } );
		setPreview( null );
		setSuccess( '' );
		setError( '' );
	};

	const selectFrequency = ( frequency ) => {
		change( { frequency } );

		if ( frequency === 'once' && context.recurring ) {
			loadOccurrences( occurrenceWindowStart, true );
		}
	};

	const occurrenceFormMessage = ( code ) => {
		const messages = {
			invalid_date: __( 'Choose valid start and end dates.', 'mime-simple-events-calendar' ),
			invalid_time: __( 'Choose valid start and end times.', 'mime-simple-events-calendar' ),
			invalid_range: __( 'The occurrence must end after it starts.', 'mime-simple-events-calendar' ),
			outside_window: __(
				'The edited occurrence must remain inside the loaded search period. Choose a closer date or search a different period first.',
				'mime-simple-events-calendar',
			),
			invalid_status: __( 'Choose a valid event status.', 'mime-simple-events-calendar' ),
			invalid_title: __( 'Enter a plain-text occurrence title within the allowed length.', 'mime-simple-events-calendar' ),
			invalid_note: __( 'Enter a non-empty plain-text occurrence note within the allowed length.', 'mime-simple-events-calendar' ),
			invalid_featured_image: __( 'Choose a valid featured image.', 'mime-simple-events-calendar' ),
			invalid_venue: __( 'Enter a plain-text venue within the allowed length.', 'mime-simple-events-calendar' ),
			invalid_address: __( 'Enter a plain-text address within the allowed length.', 'mime-simple-events-calendar' ),
			invalid_location_url: __( 'Enter an HTTP(S) location URL or leave it empty to hide the series link.', 'mime-simple-events-calendar' ),
			invalid_event_url: __( 'Enter an HTTP(S) external event URL or leave it empty to hide the series action.', 'mime-simple-events-calendar' ),
			invalid_event_url_label: __( 'Enter a plain-text external action label within the allowed length.', 'mime-simple-events-calendar' ),
		};

		return messages[ code ] || __( 'Review this occurrence and try again.', 'mime-simple-events-calendar' );
	};

	const previewOccurrenceChanges = async () => {
		if ( ! occurrenceEditContext || ! occurrenceForm ) {
			setError(
				__( 'Load an occurrence before editing it.', 'mime-simple-events-calendar' ),
			);
			return;
		}

		const validation = occurrenceEditFormError(
			occurrenceForm,
			occurrenceEditContext,
		);

		if ( validation ) {
			setError( occurrenceFormMessage( validation ) );
			return;
		}

		if ( editor.dirty ) {
			setError(
				__(
					'Save the event’s ordinary details before previewing this occurrence change.',
					'mime-simple-events-calendar',
				),
			);
			return;
		}

		setApplying( true );
		setError( '' );

		try {
			const mutation = onlyThisMutationFromChanges(
				occurrenceEditContext,
				occurrenceChangesFromForm( occurrenceForm ),
			);

			if ( ! onlyThisMutationChanged( occurrenceEditContext, mutation ) ) {
				setError(
					__( 'Make a change before creating the preview.', 'mime-simple-events-calendar' ),
				);
				return;
			}

			const value = await apiFetch( {
				path: `/wpse/v1/events/${ editor.id }/recurrence/preview`,
				method: 'POST',
				data: mutation,
			} );
			setPreview( { ...value, mutation, kind: 'occurrence' } );
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The occurrence preview could not be created. Search again and retry.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setApplying( false );
		}
	};

	const previewFollowingChanges = async () => {
		if ( ! occurrenceEditContext || ! followingForm || ! followingRule ) {
			setError(
				__(
					'Load a generated occurrence before changing this and following events.',
					'mime-simple-events-calendar',
				),
			);
			return;
		}

		const formValidation = occurrenceEditFormError(
			{ ...followingForm, status: 'scheduled' },
			occurrenceEditContext,
		);
		const ruleValidation = validateConfiguration(
			followingRule,
			followingAnchor,
		);

		if ( formValidation || ruleValidation ) {
			setError(
				formValidation
					? occurrenceFormMessage( formValidation )
					: ruleValidation,
			);
			return;
		}

		if ( editor.dirty ) {
			setError(
				__(
					'Save the event’s ordinary details before previewing this schedule change.',
					'mime-simple-events-calendar',
				),
			);
			return;
		}

		setApplying( true );
		setError( '' );

		try {
			const request = followingMutationFromForm(
				occurrenceEditContext,
				followingForm,
				definitionFromConfiguration( followingRule, followingAnchor ),
			);
			const value = await apiFetch( {
				path: `/wpse/v1/events/${ editor.id }/recurrence/following/preview`,
				method: 'POST',
				data: request,
			} );
			const mutation = {
				aggregate: value.proposal,
				scope: 'this_and_following',
				target: request.target,
				revision: request.revision,
				from_date: request.from_date,
				through_date: request.through_date,
				max_rows: request.max_rows,
			};

			setPreview( { ...value, mutation, kind: 'following' } );
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The future schedule preview could not be created. Search again and retry.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setApplying( false );
		}
	};

	const previewChanges = async () => {
		if ( onlyThis ) {
			await previewOccurrenceChanges();
			return;
		}

		if ( thisAndFollowing ) {
			await previewFollowingChanges();
			return;
		}

		const disabling = context.recurring && configuration.frequency === 'once';
		let validation = validateConfiguration( configuration, anchor );

		if ( disabling ) {
			validation = survivor && loadedOccurrenceWindowStart
				? ''
				: __( 'Choose the occurrence that should remain.', 'mime-simple-events-calendar' );
		}

		if ( validation ) {
			setError( validation );
			return;
		}

		if ( editor.dirty ) {
			setError(
				__(
					'Save the event’s ordinary details before previewing recurrence changes.',
					'mime-simple-events-calendar',
				),
			);
			return;
		}

		setApplying( true );
		setError( '' );

		try {
			const mutation = disabling
				? disableMutation( context, survivor, loadedOccurrenceWindowStart )
				: mutationFromConfiguration( context, configuration );
			const value = await apiFetch( {
				path: disabling
					? `/wpse/v1/events/${ editor.id }/recurrence/disable/preview`
					: `/wpse/v1/events/${ editor.id }/recurrence/preview`,
				method: 'POST',
				data: mutation,
			} );
			setPreview( { ...value, mutation, kind: disabling ? 'disable' : 'recurrence' } );
		} catch ( apiError ) {
			setError(
				apiError?.message ||
					__(
						'The recurrence preview could not be created. Reload the event and try again.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setApplying( false );
		}
	};

	const applyChanges = async () => {
		if ( ! preview || editor.dirty ) {
			setPreview( null );
			setError(
				__(
					'The event changed after preview. Save and preview the recurrence again.',
					'mime-simple-events-calendar',
				),
			);
			return;
		}

		setApplying( true );
		setError( '' );

		try {
			const value = await apiFetch( {
				path:
					preview.kind === 'disable'
						? `/wpse/v1/events/${ editor.id }/recurrence/disable/save`
						: `/wpse/v1/events/${ editor.id }/recurrence/save`,
				method: 'POST',
				data: {
					...preview.mutation,
					confirmation: preview.confirmation,
				},
			} );

			if ( preview.kind === 'disable' ) {
				window.location.reload();
				return;
			}

			setContext( value.context );
			setConfiguration( recurrenceConfiguration( value.context ) );
			setPreview( null );

			if ( preview.kind === 'following' ) {
				setEditorScope( 'complete_series' );
				setOccurrenceEditContext( null );
				setOccurrenceForm( null );
				setFollowingForm( null );
				setFollowingRule( null );
				setOccurrences( [] );
				setSelectedOccurrence( '' );
				setLoadedOccurrenceWindowStart( '' );
				setOccurrenceAnchorFallback( false );
				setOccurrenceWindowStart(
					initialOccurrenceWindowStart(
						value.context.aggregate,
						wpseRecurrenceEditor.today,
						wpseRecurrenceEditor.horizonDays,
					),
				);
				setSuccess(
					__(
						'This and following occurrences now use the new schedule.',
						'mime-simple-events-calendar',
					),
				);
			} else if ( preview.kind === 'occurrence' ) {
				const query = new URLSearchParams( {
					from_date: preview.mutation.from_date,
					through_date: preview.mutation.through_date,
					max_rows: preview.mutation.max_rows,
					target: preview.mutation.target,
				} );
				try {
					const refreshed = await apiFetch( {
						path: `/wpse/v1/events/${ editor.id }/recurrence/occurrence?${ query.toString() }`,
					} );

					setOccurrenceEditContext( refreshed );
					setOccurrenceForm( occurrenceEditFormFromContext( refreshed ) );
					setOccurrences( ( currentOccurrences ) =>
						currentOccurrences.map( ( occurrence ) =>
							occurrence.recurrence_id === refreshed.target
								? refreshed.current
								: occurrence,
						),
					);
					setSuccess(
						__( 'This occurrence was updated.', 'mime-simple-events-calendar' ),
					);
				} catch {
					setOccurrenceEditContext( null );
					setOccurrenceForm( null );
					setSuccess(
						__(
							'This occurrence was updated. Reload it before making another change.',
							'mime-simple-events-calendar',
						),
					);
				}
			} else {
				setSuccess(
					__( 'The recurring schedule was updated.', 'mime-simple-events-calendar' ),
				);
			}
		} catch ( apiError ) {
			setPreview( null );
			setError(
				apiError?.message ||
					__(
						'The recurrence change was not saved. Reload the event and review it again.',
						'mime-simple-events-calendar',
					),
			);
		} finally {
			setApplying( false );
		}
	};

	let previewButtonLabel = __( 'Preview recurrence', 'mime-simple-events-calendar' );

	if ( onlyThis ) {
		previewButtonLabel = __( 'Preview this occurrence', 'mime-simple-events-calendar' );
	} else if ( thisAndFollowing ) {
		previewButtonLabel = __( 'Preview this and following', 'mime-simple-events-calendar' );
	}

	if ( preview ) {
		previewButtonLabel = __( 'Refresh preview', 'mime-simple-events-calendar' );

		if ( onlyThis ) {
			previewButtonLabel = __( 'Refresh occurrence preview', 'mime-simple-events-calendar' );
		} else if ( thisAndFollowing ) {
			previewButtonLabel = __( 'Refresh future schedule preview', 'mime-simple-events-calendar' );
		}
	} else if ( configuration?.frequency === 'once' && context?.recurring ) {
		previewButtonLabel = __( 'Preview stopping recurrence', 'mime-simple-events-calendar' );
	}

	let applyButtonLabel = __( 'Apply to complete series', 'mime-simple-events-calendar' );

	if ( preview?.kind === 'disable' ) {
		applyButtonLabel = __( 'Keep selected event only', 'mime-simple-events-calendar' );
	} else if ( preview?.kind === 'occurrence' ) {
		applyButtonLabel = __( 'Apply to this occurrence', 'mime-simple-events-calendar' );
	} else if ( preview?.kind === 'following' ) {
		applyButtonLabel = __( 'Apply to this and following', 'mime-simple-events-calendar' );
	}

	let scopeNotice = __(
		'Editing scope: complete series. You will review every affected date before applying.',
		'mime-simple-events-calendar',
	);

	if ( onlyThis ) {
		scopeNotice = __(
			'Editing scope: only this occurrence. Other dates in the series stay unchanged.',
			'mime-simple-events-calendar',
		);
	} else if ( thisAndFollowing ) {
		scopeNotice = __(
			'Editing scope: this and following occurrences. Earlier dates stay unchanged.',
			'mime-simple-events-calendar',
		);
	}

	let loadOccurrenceButtonLabel = __(
		'Edit selected occurrence',
		'mime-simple-events-calendar',
	);

	if ( occurrenceEditContext ) {
		loadOccurrenceButtonLabel = thisAndFollowing
			? __( 'Reload schedule boundary', 'mime-simple-events-calendar' )
			: __( 'Reload selected occurrence', 'mime-simple-events-calendar' );
	} else if ( thisAndFollowing ) {
		loadOccurrenceButtonLabel = __(
			'Configure new schedule',
			'mime-simple-events-calendar',
		);
	}

	let missingScopedForm = false;

	if ( onlyThis ) {
		missingScopedForm = ! occurrenceEditContext || ! occurrenceForm;
	} else if ( thisAndFollowing ) {
		missingScopedForm =
			! occurrenceEditContext || ! followingForm || ! followingRule;
	}

	let previewDisabled = true;

	if ( context && configuration ) {
		previewDisabled =
			( occurrenceScoped
				? missingScopedForm
				: advanced && configuration.frequency !== 'once' ) ||
			applying ||
			editor.saving ||
			( ! occurrenceScoped &&
				configuration.frequency === 'once' &&
				( ! context.recurring || ! survivor || loadingOccurrences ) );
	}

	return el(
		PluginDocumentSettingPanel,
		{
			name: 'wpse-recurrence',
			title: __( 'Repeating event', 'mime-simple-events-calendar' ),
			className: 'wpse-recurrence-editor',
		},
		loading && el( PanelRow, null, el( Spinner ) ),
		error &&
			el(
				Notice,
				{ status: 'error', isDismissible: false },
				error,
			),
		success &&
			el(
				Notice,
				{ status: 'success', isDismissible: true, onRemove: () => setSuccess( '' ) },
				success,
			),
		context &&
			configuration &&
			el(
				Fragment,
				null,
				el(
					Notice,
					{ status: 'info', isDismissible: false },
					scopeNotice,
				),
				context.recurring &&
					configuration.frequency !== 'once' &&
					! occurrenceScoped &&
					el(
						Fragment,
						null,
						el(
							Button,
							{
								variant: 'secondary',
								disabled: applying || loadingOccurrences,
								onClick: enterOnlyThis,
							},
							__( 'Edit one occurrence…', 'mime-simple-events-calendar' ),
						),
						el(
							Button,
							{
								variant: 'secondary',
								disabled: applying || loadingOccurrences,
								onClick: enterThisAndFollowing,
							},
							__( 'Change this and following…', 'mime-simple-events-calendar' ),
						),
					),
				occurrenceScoped &&
					el(
						Fragment,
						null,
						el(
							Button,
							{
								variant: 'tertiary',
								disabled: applying || loadingOccurrenceContext,
								onClick: leaveOccurrenceScope,
							},
							__( 'Back to complete series', 'mime-simple-events-calendar' ),
						),
						el( TextControl, {
							label: __( 'Find occurrences from', 'mime-simple-events-calendar' ),
							type: 'date',
							value: occurrenceWindowStart,
							disabled: loadingOccurrences || applying,
							help: __(
								'Searches one bounded 18-month period. Choose another date to find an older or later event.',
								'mime-simple-events-calendar',
							),
							onChange: ( value ) => {
								setOccurrenceWindowStart( value );
								clearOccurrenceSelection();
							},
						} ),
						el(
							Button,
							{
								variant: 'secondary',
								disabled:
									loadingOccurrences ||
									applying ||
									! isIsoDate( occurrenceWindowStart ) ||
									occurrenceWindowStart === loadedOccurrenceWindowStart,
								isBusy: loadingOccurrences,
								onClick: () => loadOccurrences( occurrenceWindowStart ),
							},
							__( 'Search this period', 'mime-simple-events-calendar' ),
						),
						loadingOccurrences && el( Spinner ),
						! loadingOccurrences &&
							occurrenceAnchorFallback &&
							el(
								Notice,
								{ status: 'info', isDismissible: false },
								__(
									'No occurrences were found near today, so the first period of this series is shown.',
									'mime-simple-events-calendar',
								),
							),
						! loadingOccurrences &&
							loadedOccurrenceWindowStart &&
							occurrences.length === 0 &&
							el(
								Notice,
								{ status: 'info', isDismissible: false },
								__(
									'No occurrences were found in this period. Choose another start date and search again.',
									'mime-simple-events-calendar',
								),
							),
						! loadingOccurrences &&
							thisAndFollowing &&
							loadedOccurrenceWindowStart &&
							occurrences.length > 0 &&
							selectableOccurrences.length === 0 &&
							el(
								Notice,
								{ status: 'info', isDismissible: false },
								__(
									'This period has no generated occurrence after the first event. Search a later period or edit the complete series.',
									'mime-simple-events-calendar',
								),
							),
						! loadingOccurrences &&
							selectableOccurrences.length > 0 &&
							el(
								Fragment,
								null,
								el( ComboboxControl, {
									label: thisAndFollowing
										? __( 'Start the new schedule at', 'mime-simple-events-calendar' )
										: __( 'Occurrence to edit', 'mime-simple-events-calendar' ),
									value: selectedOccurrence,
									options: selectableOccurrences.map( ( occurrence ) => ( {
										label: occurrenceOptionLabel( occurrence ),
										value: occurrence.recurrence_id,
									} ) ),
									disabled: applying || loadingOccurrenceContext,
									onChange: ( value ) => {
										setSelectedOccurrence( value || '' );
										setOccurrenceEditContext( null );
										setOccurrenceForm( null );
										setFollowingForm( null );
										setFollowingRule( null );
										setPreview( null );
										setError( '' );
									},
									help: __( 'Search by the local event date and time.', 'mime-simple-events-calendar' ),
								} ),
								el(
									Button,
									{
										variant: 'secondary',
										disabled:
											! selectedOccurrence ||
											loadingOccurrenceContext ||
											applying,
										isBusy: loadingOccurrenceContext,
										onClick: loadOccurrenceContext,
									},
									loadOccurrenceButtonLabel,
								),
							),
						loadingOccurrenceContext && el( Spinner ),
						onlyThis &&
							occurrenceEditContext &&
							occurrenceForm &&
							el(
								'fieldset',
								{ className: 'wpse-occurrence-edit-fields' },
								el(
									'legend',
									null,
									__( 'Selected occurrence', 'mime-simple-events-calendar' ),
								),
								el(
									'p',
									{ className: 'wpse-occurrence-edit-summary' },
									occurrenceOptionLabel( occurrenceEditContext.current ),
								),
								( Object.keys( occurrenceEditContext.override_fields || {} ).length > 0 ||
									occurrenceEditContext.exclusion_action ) &&
									el(
										Notice,
										{ status: 'info', isDismissible: false },
										__(
											'This occurrence has individual changes. Each inherited field can be restored to the current series value.',
											'mime-simple-events-calendar',
										),
									),
								el(
									'h4',
									{ className: 'wpse-occurrence-section-title' },
									__( 'Content', 'mime-simple-events-calendar' ),
								),
								el( OccurrenceTextOverrideControl, {
									label: __( 'Occurrence title', 'mime-simple-events-calendar' ),
									value: occurrenceForm.title,
									overridden: occurrenceForm.titleOverridden,
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.title ),
									disabled: applying,
									onChange: ( title ) =>
										changeOccurrenceForm( { title, titleOverridden: true } ),
									onRestore: () =>
										changeOccurrenceForm( {
											title: occurrenceEditContext.inherited_fields.title,
											titleOverridden: false,
										} ),
								} ),
								el( OccurrenceTextOverrideControl, {
									label: __( 'Occurrence note', 'mime-simple-events-calendar' ),
									value: occurrenceForm.note,
									overridden: occurrenceForm.noteOverridden,
									multiline: true,
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.note ),
									disabled: applying,
									onChange: ( note ) =>
										changeOccurrenceForm( { note, noteOverridden: true } ),
									onRestore: () =>
										changeOccurrenceForm( {
											note: occurrenceEditContext.inherited_fields.note,
											noteOverridden: false,
										} ),
								} ),
								el( OccurrenceFeaturedImageControl, {
									imageId: occurrenceForm.featuredImageId,
									overridden: occurrenceForm.featuredImageOverridden,
									disabled: applying,
									onChange: ( featuredImageId ) =>
										changeOccurrenceForm( {
											featuredImageId,
											featuredImageOverridden: true,
										} ),
									onRestore: () =>
										changeOccurrenceForm( {
											featuredImageId:
												occurrenceEditContext.inherited_fields.featured_image_id,
											featuredImageOverridden: false,
										} ),
								} ),
								el(
									'h4',
									{ className: 'wpse-occurrence-section-title' },
									__( 'Schedule and status', 'mime-simple-events-calendar' ),
								),
								el( CheckboxControl, {
									label: __( 'All-day event', 'mime-simple-events-calendar' ),
									checked: occurrenceForm.allDay,
									disabled: applying,
									onChange: ( allDay ) =>
										changeOccurrenceForm( {
											allDay,
											dateOverridden: true,
										} ),
								} ),
								el(
									'div',
									{ className: 'wpse-occurrence-date-grid' },
									el( TextControl, {
										label: __( 'Start date', 'mime-simple-events-calendar' ),
										type: 'date',
										value: occurrenceForm.startDate,
										disabled: applying,
										onChange: ( startDate ) =>
											changeOccurrenceForm( { startDate, dateOverridden: true } ),
									} ),
									! occurrenceForm.allDay &&
										el( TextControl, {
											label: __( 'Start time', 'mime-simple-events-calendar' ),
											type: 'time',
											value: occurrenceForm.startTime,
											disabled: applying,
											onChange: ( startTime ) =>
												changeOccurrenceForm( { startTime, dateOverridden: true } ),
										} ),
									el( TextControl, {
										label: __( 'End date', 'mime-simple-events-calendar' ),
										type: 'date',
										value: occurrenceForm.endDate,
										disabled: applying,
										onChange: ( endDate ) =>
											changeOccurrenceForm( { endDate, dateOverridden: true } ),
									} ),
									! occurrenceForm.allDay &&
										el( TextControl, {
											label: __( 'End time', 'mime-simple-events-calendar' ),
											type: 'time',
											value: occurrenceForm.endTime,
											disabled: applying,
											onChange: ( endTime ) =>
												changeOccurrenceForm( { endTime, dateOverridden: true } ),
										} ),
								),
								el(
									'p',
									{ className: 'components-base-control__help' },
									sprintf(
										/* translators: %s: captured event timezone. */
										__( 'Times use the series timezone: %s.', 'mime-simple-events-calendar' ),
										occurrenceEditContext.inherited.timezone,
									),
								),
								occurrenceForm.dateOverridden &&
									el(
										Button,
										{
											variant: 'tertiary',
											disabled: applying,
											onClick: () =>
												changeOccurrenceForm( {
													...occurrenceDateControls(
														occurrenceEditContext.inherited,
													),
													dateOverridden: false,
												} ),
										},
										__( 'Use series date and time', 'mime-simple-events-calendar' ),
									),
								el( SelectControl, {
									label: __( 'Event status', 'mime-simple-events-calendar' ),
									value: occurrenceForm.status,
									disabled: applying,
									options: [
										{ label: __( 'Scheduled', 'mime-simple-events-calendar' ), value: 'scheduled' },
										{ label: __( 'Postponed', 'mime-simple-events-calendar' ), value: 'postponed' },
										...( occurrenceForm.status === 'cancelled'
											? [ { label: __( 'Cancelled (legacy status)', 'mime-simple-events-calendar' ), value: 'cancelled' } ]
											: [] ),
									],
									onChange: ( status ) =>
										changeOccurrenceForm( { status, statusOverridden: true } ),
								} ),
								occurrenceForm.statusOverridden &&
									el(
										Button,
										{
											variant: 'tertiary',
											disabled: applying,
											onClick: () =>
												changeOccurrenceForm( {
													status: occurrenceEditContext.inherited.status,
													statusOverridden: false,
												} ),
										},
										__( 'Use series status', 'mime-simple-events-calendar' ),
									),
								el( CheckboxControl, {
									label: __( 'Cancel this occurrence', 'mime-simple-events-calendar' ),
									checked: occurrenceForm.cancelled,
									disabled: applying,
									help: occurrenceForm.cancelled
										? __( 'This occurrence stays linked to the series and can be restored later.', 'mime-simple-events-calendar' )
										: __( 'Only this occurrence will be cancelled; the rest of the series continues.', 'mime-simple-events-calendar' ),
									onChange: ( cancelled ) => changeOccurrenceForm( { cancelled } ),
								} ),
								el(
									'h4',
									{ className: 'wpse-occurrence-section-title' },
									__( 'Location', 'mime-simple-events-calendar' ),
								),
								el( OccurrenceTextOverrideControl, {
									label: __( 'Venue', 'mime-simple-events-calendar' ),
									value: occurrenceForm.venue,
									overridden: occurrenceForm.venueOverridden,
									canHide: true,
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.venue ),
									disabled: applying,
									onChange: ( venue ) =>
										changeOccurrenceForm( { venue, venueOverridden: true } ),
									onRestore: () =>
										changeOccurrenceForm( {
											venue: occurrenceEditContext.inherited_fields.venue,
											venueOverridden: false,
										} ),
								} ),
								el( OccurrenceTextOverrideControl, {
									label: __( 'Address', 'mime-simple-events-calendar' ),
									value: occurrenceForm.address,
									overridden: occurrenceForm.addressOverridden,
									canHide: true,
									multiline: true,
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.address ),
									disabled: applying,
									onChange: ( address ) =>
										changeOccurrenceForm( { address, addressOverridden: true } ),
									onRestore: () =>
										changeOccurrenceForm( {
											address: occurrenceEditContext.inherited_fields.address,
											addressOverridden: false,
										} ),
								} ),
								el( OccurrenceTextOverrideControl, {
									label: __( 'Location URL', 'mime-simple-events-calendar' ),
									value: occurrenceForm.locationUrl,
									overridden: occurrenceForm.locationUrlOverridden,
									canHide: true,
									type: 'url',
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.url ),
									disabled: applying,
									onChange: ( locationUrl ) =>
										changeOccurrenceForm( {
											locationUrl,
											locationUrlOverridden: true,
										} ),
									onRestore: () =>
										changeOccurrenceForm( {
											locationUrl: occurrenceEditContext.inherited_fields.location_url,
											locationUrlOverridden: false,
										} ),
								} ),
								el(
									'h4',
									{ className: 'wpse-occurrence-section-title' },
									__( 'External action', 'mime-simple-events-calendar' ),
								),
								el( OccurrenceTextOverrideControl, {
									label: __( 'External event URL', 'mime-simple-events-calendar' ),
									value: occurrenceForm.eventUrl,
									overridden: occurrenceForm.eventUrlOverridden,
									canHide: true,
									type: 'url',
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.url ),
									disabled: applying,
									onChange: ( eventUrl ) =>
										changeOccurrenceForm( { eventUrl, eventUrlOverridden: true } ),
									onRestore: () =>
										changeOccurrenceForm( {
											eventUrl: occurrenceEditContext.inherited_fields.event_url,
											eventUrlOverridden: false,
										} ),
								} ),
								el( OccurrenceTextOverrideControl, {
									label: __( 'External event action label', 'mime-simple-events-calendar' ),
									value: occurrenceForm.eventUrlLabel,
									overridden: occurrenceForm.eventUrlLabelOverridden,
									maxLength: Number( wpseRecurrenceEditor.overrideLimits.eventUrlLabel ),
									disabled: applying,
									onChange: ( eventUrlLabel ) =>
										changeOccurrenceForm( {
											eventUrlLabel,
											eventUrlLabelOverridden: true,
										} ),
									onRestore: () =>
										changeOccurrenceForm( {
											eventUrlLabel:
												occurrenceEditContext.inherited_fields.event_url_label,
											eventUrlLabelOverridden: false,
										} ),
								} ),
							),
						thisAndFollowing &&
							occurrenceEditContext &&
							followingForm &&
							followingRule &&
							followingAnchor &&
							el(
								'fieldset',
								{ className: 'wpse-occurrence-edit-fields' },
								el(
									'legend',
									null,
									__( 'New schedule from this occurrence', 'mime-simple-events-calendar' ),
								),
								el(
									Notice,
									{ status: 'warning', isDismissible: false },
									__(
										'Every later scheduled change will be replaced. Earlier occurrences stay unchanged. Individual changes are preserved and may become standalone occurrences.',
										'mime-simple-events-calendar',
									),
								),
								el(
									'p',
									{ className: 'wpse-occurrence-edit-summary' },
									occurrenceOptionLabel( occurrenceEditContext.current ),
								),
								el( CheckboxControl, {
									label: __( 'All-day event', 'mime-simple-events-calendar' ),
									checked: followingForm.allDay,
									disabled: applying,
									onChange: ( allDay ) => changeFollowingForm( { allDay } ),
								} ),
								el(
									'div',
									{ className: 'wpse-occurrence-date-grid' },
									el( TextControl, {
										label: __( 'Start date', 'mime-simple-events-calendar' ),
										type: 'date',
										value: followingForm.startDate,
										disabled: applying,
										onChange: ( startDate ) => changeFollowingForm( { startDate } ),
									} ),
									! followingForm.allDay &&
										el( TextControl, {
											label: __( 'Start time', 'mime-simple-events-calendar' ),
											type: 'time',
											value: followingForm.startTime,
											disabled: applying,
											onChange: ( startTime ) => changeFollowingForm( { startTime } ),
										} ),
									el( TextControl, {
										label: __( 'End date', 'mime-simple-events-calendar' ),
										type: 'date',
										value: followingForm.endDate,
										disabled: applying,
										onChange: ( endDate ) => changeFollowingForm( { endDate } ),
									} ),
									! followingForm.allDay &&
										el( TextControl, {
											label: __( 'End time', 'mime-simple-events-calendar' ),
											type: 'time',
											value: followingForm.endTime,
											disabled: applying,
											onChange: ( endTime ) => changeFollowingForm( { endTime } ),
										} ),
								),
								el(
									'p',
									{ className: 'components-base-control__help' },
									sprintf(
										/* translators: %s: captured event timezone. */
										__( 'Times use the series timezone: %s.', 'mime-simple-events-calendar' ),
										occurrenceEditContext.context.aggregate.timezone,
									),
								),
								el( SelectControl, {
									label: __( 'Repeats from here', 'mime-simple-events-calendar' ),
									value: followingRule.frequency,
									disabled: applying,
									options: repeatOptions.filter( ( option ) => option.value !== 'once' ),
									onChange: ( frequency ) => changeFollowingRule( { frequency } ),
								} ),
								followingRule.frequency !== 'specific_dates' &&
									el( TextControl, {
										label: __( 'Repeat every', 'mime-simple-events-calendar' ),
										type: 'number',
										min: 1,
										max: 999,
										value: followingRule.interval,
										disabled: applying,
										onChange: ( interval ) => changeFollowingRule( { interval } ),
									} ),
								followingRule.frequency === 'weekly' &&
									el(
										'fieldset',
										{ className: 'wpse-recurrence-weekdays' },
										el(
											'legend',
											null,
											__( 'On these days', 'mime-simple-events-calendar' ),
										),
										weekdayOrder.map( ( day ) =>
											el( CheckboxControl, {
												key: day,
												label: weekdays[ day - 1 ],
												checked: followingRule.weekdays.includes( day ),
												disabled:
													applying ||
													( day === followingAnchor.weekday &&
														followingRule.weekdays.includes( day ) ),
												onChange: ( checked ) =>
													changeFollowingRule( {
														weekdays: checked
															? [ ...followingRule.weekdays, day ]
															: followingRule.weekdays.filter( ( value ) => value !== day ),
													} ),
											} ),
										),
									),
								followingRule.frequency === 'monthly' &&
									el( SelectControl, {
										label: __( 'Monthly pattern', 'mime-simple-events-calendar' ),
										value: followingRule.monthlyPattern,
										disabled: applying,
										options: [
											{
												label: sprintf(
													/* translators: %d: event day of month. */
													__( 'Day %d of the month', 'mime-simple-events-calendar' ),
													followingAnchor.day,
												),
												value: 'day_of_month',
											},
											{
												label: __( 'Same weekday position in the month', 'mime-simple-events-calendar' ),
												value: 'ordinal_weekday',
											},
											...( followingAnchor.isLastWeekday ||
											followingRule.monthlyPattern === 'last_weekday'
												? [ {
													label: __( 'Last same weekday of the month', 'mime-simple-events-calendar' ),
													value: 'last_weekday',
												} ]
												: [] ),
										],
										onChange: ( monthlyPattern ) => changeFollowingRule( { monthlyPattern } ),
										help: __( 'Months without this calendar day are skipped; dates are never shifted silently.', 'mime-simple-events-calendar' ),
									} ),
								followingRule.frequency === 'specific_dates' &&
									el( TextareaControl, {
										label: __( 'Event dates from here', 'mime-simple-events-calendar' ),
										value: followingRule.specificDates,
										disabled: applying,
										help: __( 'Enter one YYYY-MM-DD date per line. Include the new schedule’s first date.', 'mime-simple-events-calendar' ),
										onChange: ( specificDates ) => changeFollowingRule( { specificDates } ),
									} ),
								followingRule.frequency !== 'specific_dates' &&
									el( SelectControl, {
										label: __( 'Ends', 'mime-simple-events-calendar' ),
										value: followingRule.endMode,
										disabled: applying,
										options: [
											{ label: __( 'Never', 'mime-simple-events-calendar' ), value: 'never' },
											{ label: __( 'On a date', 'mime-simple-events-calendar' ), value: 'until' },
											{ label: __( 'After a number of events', 'mime-simple-events-calendar' ), value: 'count' },
										],
										onChange: ( endMode ) => changeFollowingRule( { endMode } ),
									} ),
								followingRule.frequency !== 'specific_dates' &&
									followingRule.endMode === 'never' &&
									el( NoEndProjectionHelp ),
								followingRule.frequency !== 'specific_dates' &&
									followingRule.endMode === 'until' &&
									el( TextControl, {
										label: __( 'Last event date', 'mime-simple-events-calendar' ),
										type: 'date',
										value: followingRule.untilDate,
										disabled: applying,
										onChange: ( untilDate ) => changeFollowingRule( { untilDate } ),
									} ),
								followingRule.frequency !== 'specific_dates' &&
									followingRule.endMode === 'count' &&
									el( TextControl, {
										label: __( 'Number of events', 'mime-simple-events-calendar' ),
										type: 'number',
										min: 1,
										max: 10000,
										value: followingRule.count,
										disabled: applying,
										onChange: ( count ) => changeFollowingRule( { count } ),
									} ),
								el(
									'p',
									{ className: 'wpse-recurrence-summary' },
									el( 'strong', null, __( 'New schedule:', 'mime-simple-events-calendar' ) ),
									' ',
									scheduleSummary( followingRule ),
								),
							),
					),
				! occurrenceScoped &&
					advanced &&
					el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__(
							'This series contains individual changes or a future schedule segment. Simple complete-series editing is locked, but you can still edit one occurrence, change this and following, or stop recurrence and keep one occurrence.',
							'mime-simple-events-calendar',
						),
					),
				! occurrenceScoped &&
					el( SelectControl, {
						label: __( 'Repeats', 'mime-simple-events-calendar' ),
						value: configuration.frequency,
						disabled: advanced || applying,
						options: repeatOptions,
						onChange: selectFrequency,
					} ),
				! occurrenceScoped &&
					advanced &&
					context.recurring &&
					configuration.frequency !== 'once' &&
					el(
						Button,
						{
							variant: 'secondary',
							isDestructive: true,
							disabled: applying,
							onClick: () => selectFrequency( 'once' ),
						},
						__( 'Stop repeating…', 'mime-simple-events-calendar' ),
					),
				! occurrenceScoped &&
					context.recurring &&
					configuration.frequency === 'once' &&
					el(
						Fragment,
						null,
						el(
							Notice,
							{ status: 'warning', isDismissible: false },
							__(
								'Choose the one occurrence that should become the normal event. Every other occurrence in the complete series will be removed.',
								'mime-simple-events-calendar',
							),
						),
						el( TextControl, {
							label: __( 'Find occurrences from', 'mime-simple-events-calendar' ),
							type: 'date',
							value: occurrenceWindowStart,
							disabled: loadingOccurrences || applying,
							help: __(
								'Searches one bounded 18-month period. Choose another date to find an older or later event.',
								'mime-simple-events-calendar',
							),
							onChange: ( value ) => {
								setOccurrenceWindowStart( value );
								setLoadedOccurrenceWindowStart( '' );
								setOccurrenceAnchorFallback( false );
								setOccurrences( [] );
								setSurvivor( '' );
								setPreview( null );
								setError( '' );
							},
						} ),
						el(
							Button,
							{
								variant: 'secondary',
								disabled:
									loadingOccurrences ||
									applying ||
									! isIsoDate( occurrenceWindowStart ) ||
									occurrenceWindowStart === loadedOccurrenceWindowStart,
								isBusy: loadingOccurrences,
								onClick: () => loadOccurrences( occurrenceWindowStart ),
							},
							__( 'Search this period', 'mime-simple-events-calendar' ),
						),
						loadingOccurrences && el( Spinner ),
						! loadingOccurrences &&
							occurrenceAnchorFallback &&
							el(
								Notice,
								{ status: 'info', isDismissible: false },
								__(
									'No occurrences were found near today, so the first period of this series is shown.',
									'mime-simple-events-calendar',
								),
							),
						! loadingOccurrences &&
							loadedOccurrenceWindowStart &&
							occurrences.length === 0 &&
							el(
								Notice,
								{ status: 'info', isDismissible: false },
								__(
									'No occurrences were found in this period. Choose another start date and search again.',
									'mime-simple-events-calendar',
								),
							),
						! loadingOccurrences &&
							occurrences.length > 0 &&
							el( ComboboxControl, {
								label: __( 'Keep as the single event', 'mime-simple-events-calendar' ),
								value: survivor,
								options: occurrences.map( ( occurrence ) => ( {
									label: occurrenceOptionLabel( occurrence ),
									value: occurrence.recurrence_id,
								} ) ),
								onChange: ( value ) => {
									setSurvivor( value || '' );
									setPreview( null );
								},
								help: __( 'Search by the local event date and time.', 'mime-simple-events-calendar' ),
							} ),
					),
				! occurrenceScoped &&
					configuration.frequency !== 'once' &&
					configuration.frequency !== 'specific_dates' &&
					el( TextControl, {
						label: __( 'Repeat every', 'mime-simple-events-calendar' ),
						type: 'number',
						min: 1,
						max: 999,
						value: configuration.interval,
						disabled: advanced || applying,
						onChange: ( interval ) => change( { interval } ),
					} ),
				! occurrenceScoped &&
					configuration.frequency === 'weekly' &&
					el(
						'fieldset',
						{ className: 'wpse-recurrence-weekdays' },
						el(
							'legend',
							null,
							__( 'On these days', 'mime-simple-events-calendar' ),
						),
						weekdayOrder.map( ( day ) => {
							const label = weekdays[ day - 1 ];

							return el( CheckboxControl, {
								key: day,
								label,
								checked: configuration.weekdays.includes( day ),
								disabled: advanced || applying || day === anchor.weekday,
								onChange: ( checked ) =>
									change( {
										weekdays: checked
											? [ ...configuration.weekdays, day ]
											: configuration.weekdays.filter(
												( current ) => current !== day,
											),
									} ),
							} );
						} ),
					),
				! occurrenceScoped &&
					configuration.frequency === 'monthly' &&
					el( SelectControl, {
						label: __( 'Monthly pattern', 'mime-simple-events-calendar' ),
						value: configuration.monthlyPattern,
						disabled: advanced || applying,
						options: [
							{
								label: sprintf(
									/* translators: %d: event day of month. */
									__( 'Day %d of the month', 'mime-simple-events-calendar' ),
									anchor.day,
								),
								value: 'day_of_month',
							},
							{
								label: __( 'Same weekday position in the month', 'mime-simple-events-calendar' ),
								value: 'ordinal_weekday',
							},
							...( anchor.isLastWeekday ||
								configuration.monthlyPattern === 'last_weekday'
								? [
									{
										label: __( 'Last same weekday of the month', 'mime-simple-events-calendar' ),
										value: 'last_weekday',
									},
								]
								: [] ),
						],
						onChange: ( monthlyPattern ) => change( { monthlyPattern } ),
						help: __(
							'Months without the selected calendar day are skipped; dates are never shifted silently.',
							'mime-simple-events-calendar',
						),
					} ),
				! occurrenceScoped &&
					configuration.frequency === 'specific_dates' &&
					el( TextareaControl, {
						label: __( 'Event dates', 'mime-simple-events-calendar' ),
						value: configuration.specificDates,
						disabled: advanced || applying,
						help: __(
							'Enter one YYYY-MM-DD date per line. Keep the original start date in the list.',
							'mime-simple-events-calendar',
						),
						onChange: ( specificDates ) => change( { specificDates } ),
					} ),
				! occurrenceScoped &&
					showsRecurrenceEndControls( configuration.frequency ) &&
					el( SelectControl, {
						label: __( 'Ends', 'mime-simple-events-calendar' ),
						value: configuration.endMode,
						disabled: advanced || applying,
						options: [
							{ label: __( 'Never', 'mime-simple-events-calendar' ), value: 'never' },
							{
								label: __( 'On a date', 'mime-simple-events-calendar' ),
								value: 'until',
							},
							{
								label: __( 'After a number of events', 'mime-simple-events-calendar' ),
								value: 'count',
							},
						],
						onChange: ( endMode ) => change( { endMode } ),
					} ),
				! occurrenceScoped &&
					showsRecurrenceEndControls( configuration.frequency ) &&
					configuration.endMode === 'never' &&
					el( NoEndProjectionHelp ),
				! occurrenceScoped &&
					showsRecurrenceEndControls( configuration.frequency ) &&
					configuration.endMode === 'until' &&
					el( TextControl, {
						label: __( 'Last event date', 'mime-simple-events-calendar' ),
						type: 'date',
						value: configuration.untilDate,
						disabled: advanced || applying,
						onChange: ( untilDate ) => change( { untilDate } ),
					} ),
				! occurrenceScoped &&
					showsRecurrenceEndControls( configuration.frequency ) &&
					configuration.endMode === 'count' &&
					el( TextControl, {
						label: __( 'Number of events', 'mime-simple-events-calendar' ),
						type: 'number',
						min: 1,
						max: 10000,
						value: configuration.count,
						disabled: advanced || applying,
						onChange: ( count ) => change( { count } ),
					} ),
				! occurrenceScoped &&
					el(
						'p',
						{ className: 'wpse-recurrence-summary' },
						el( 'strong', null, __( 'Summary:', 'mime-simple-events-calendar' ) ),
						' ',
						scheduleSummary( configuration ),
					),
				preview &&
					el(
						Notice,
						{ status: 'warning', isDismissible: false },
						el(
							'p',
							null,
							el(
								'strong',
								null,
								__( 'Review impact', 'mime-simple-events-calendar' ),
							),
						),
						el( 'p', null, impactLabel( preview.impact ) ),
						preview.impact.outside_window_removed &&
							el(
								'p',
								null,
								el(
									'strong',
									null,
									__(
										'All other occurrences outside this preview window will also be removed.',
										'mime-simple-events-calendar',
									),
								),
							),
						preview.kind === 'disable' &&
							el(
								'p',
								null,
								__(
									'The ordinary event keeps the series title, content, image, location and external action. Individual occurrence fields are removed with recurrence.',
									'mime-simple-events-calendar',
								),
							),
						preview.impact.exception_affected > 0 &&
							el(
								'p',
								null,
								sprintf(
									/* translators: %d: individual occurrence exceptions affected by the proposal. */
									_n(
										'%d individual change is affected.',
										'%d individual changes are affected.',
										preview.impact.exception_affected,
										'mime-simple-events-calendar',
									),
									preview.impact.exception_affected,
								),
							),
						preview.impact.items.length > 0 &&
							el(
								'details',
								{ className: 'wpse-recurrence-impact' },
								el(
									'summary',
									null,
									sprintf(
										/* translators: %d: number of changed occurrence identities. */
										__( 'Review %d affected dates', 'mime-simple-events-calendar' ),
										preview.impact.items.length,
									),
								),
								el(
									'ul',
									null,
									preview.impact.items.map( ( item ) =>
										el(
											'li',
											{ key: item.public_key },
											impactItemLabel( item ),
										),
									),
								),
							),
					),
				el(
					PanelRow,
					null,
					el(
						Button,
						{
							variant: preview ? 'secondary' : 'primary',
							disabled: previewDisabled,
							isBusy: applying,
							onClick: previewChanges,
						},
						previewButtonLabel,
					),
					preview &&
						el(
							Button,
							{
								variant: 'primary',
								isDestructive:
								preview.kind === 'disable' ||
								( preview.kind === 'occurrence' && occurrenceForm?.cancelled ) ||
								preview.impact.removed > 0 ||
									preview.impact.exception_affected > 0,
								disabled: applying || editor.saving,
								isBusy: applying,
								onClick: applyChanges,
							},
							applyButtonLabel,
						),
				),
			),
	);
};

registerPlugin( 'wpse-recurrence-editor', {
	render: RecurrencePanel,
	icon: 'update',
} );
