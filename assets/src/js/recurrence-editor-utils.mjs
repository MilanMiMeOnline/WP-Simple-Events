/**
 * Return ISO weekdays in the order selected by WordPress.
 *
 * WordPress stores Sunday as zero, while recurrence uses Monday=1 through
 * Sunday=7. Invalid localized values fall back to Monday-first.
 *
 * @param {number} startOfWeek WordPress start_of_week value.
 * @return {number[]} Ordered ISO weekdays.
 */
export const orderedIsoWeekdays = ( startOfWeek ) => {
	const normalized = Number.isInteger( startOfWeek ) && startOfWeek >= 0 && startOfWeek <= 6
		? startOfWeek
		: 1;
	const firstIsoWeekday = normalized === 0 ? 7 : normalized;

	return Array.from(
		{ length: 7 },
		( unused, index ) => ( ( firstIsoWeekday - 1 + index ) % 7 ) + 1,
	);
};

/**
 * Add a bounded number of calendar days to an ISO date.
 *
 * WordPress localizes scalar script settings as strings, so the boundary must
 * normalize before arithmetic instead of allowing JavaScript concatenation.
 *
 * @param {string}        date ISO calendar date.
 * @param {number|string} days Number of days from localized configuration.
 * @return {string} Shifted ISO calendar date.
 */
export const addIsoDateDays = ( date, days ) => {
	const value = new Date( `${ date }T12:00:00Z` );
	value.setUTCDate( value.getUTCDate() + Number( days ) );

	return value.toISOString().slice( 0, 10 );
};

/**
 * Test whether a value is one exact Gregorian ISO calendar date.
 *
 * @param {unknown} value Candidate date.
 * @return {boolean} Whether the date is canonical and real.
 */
export const isIsoDate = ( value ) => {
	if ( typeof value !== 'string' || ! /^\d{4}-\d{2}-\d{2}$/.test( value ) ) {
		return false;
	}

	const parsed = new Date( `${ value }T12:00:00Z` );

	return ! Number.isNaN( parsed.getTime() ) && parsed.toISOString().slice( 0, 10 ) === value;
};

/**
 * Build the exact bounded window used to load and retain one occurrence.
 *
 * @param {string}        fromDate    Canonical first date.
 * @param {number|string} horizonDays Maximum calendar-day horizon.
 * @param {number|string} maxRows     Maximum returned rows.
 * @return {Object} REST window fields.
 */
export const boundedOccurrenceWindow = ( fromDate, horizonDays, maxRows ) => ( {
	from_date: fromDate,
	through_date: addIsoDateDays( fromDate, Number( horizonDays ) ),
	max_rows: Number( maxRows ),
} );

/**
 * Pick a useful first survivor-search window without making it unbounded.
 *
 * Active/open series start at the WordPress site's current date. Already-ended
 * rules start one horizon before their final date so their latest occurrences
 * remain visible. A caller may still fall back to the root window when a finite
 * count schedule has already ended.
 *
 * @param {Object}        aggregate   Complete recurrence aggregate.
 * @param {string}        today       WordPress site-local current date.
 * @param {number|string} horizonDays Bounded search horizon.
 * @return {string} Canonical initial search date.
 */
export const initialOccurrenceWindowStart = ( aggregate, today, horizonDays ) => {
	const anchor = recurrenceAnchorParts( aggregate ).date;

	if ( ! isIsoDate( today ) || today <= anchor ) {
		return anchor;
	}

	const definition = aggregate?.segments?.[ 0 ]?.definition || {};
	let finalDate = '';

	if ( definition.type === 'specific_dates' ) {
		const dates = [ ...( definition.dates || [] ) ].sort();

		finalDate = dates[ dates.length - 1 ] || '';
	} else if ( definition.end?.mode === 'until' ) {
		finalDate = definition.end.date || '';
	}

	if ( isIsoDate( finalDate ) && finalDate < today ) {
		const boundedStart = addIsoDateDays( finalDate, -Number( horizonDays ) );

		return boundedStart > anchor ? boundedStart : anchor;
	}

	return today;
};

const occurrenceOverrideFields = new Set( [
	'title',
	'note',
	'featured_image_id',
	'date_range',
	'status',
	'venue',
	'address',
	'location_url',
	'event_url',
	'event_url_label',
] );

const exactJsonEqual = ( left, right ) =>
	JSON.stringify( left ) === JSON.stringify( right );

/**
 * Build one lossless only-this proposal from server-resolved edit state.
 *
 * Missing fields remain unchanged, `null` restores inheritance and any concrete
 * value becomes the new sparse override. Cancellation is separate from status:
 * omitting `cancelled` preserves it, true cancels and false restores the slot.
 *
 * @param {Object} editContext Authorized occurrence edit-context response.
 * @param {Object} changes     Sparse UI changes.
 * @return {Object} Exact mutation accepted by the shared preview/save routes.
 */
export const onlyThisMutationFromChanges = ( editContext, changes = {} ) => {
	const context = editContext?.context;
	const aggregate = context?.aggregate;
	const target = editContext?.target;
	const window = editContext?.window;

	if (
		! aggregate ||
		! Array.isArray( aggregate.overrides ) ||
		! Array.isArray( aggregate.exclusions ) ||
		typeof target !== 'string' ||
		! target ||
		typeof context.revision !== 'string' ||
		! isIsoDate( window?.from_date ) ||
		! isIsoDate( window?.through_date ) ||
		window.through_date < window.from_date ||
		! Number.isInteger( window.max_rows ) ||
		window.max_rows < 1
	) {
		throw new Error( 'Invalid occurrence edit context.' );
	}

	const aggregateCopy = JSON.parse( JSON.stringify( aggregate ) );
	const overrideIndex = aggregateCopy.overrides.findIndex(
		( override ) => override?.recurrence_id === target,
	);
	const currentOverride =
		overrideIndex < 0 ? null : aggregateCopy.overrides[ overrideIndex ];
	const currentFields = currentOverride?.fields || null;
	const exclusionIndex = aggregateCopy.exclusions.findIndex(
		( exclusion ) => exclusion?.recurrence_id === target,
	);
	const currentExclusion =
		exclusionIndex < 0 ? null : aggregateCopy.exclusions[ exclusionIndex ];

	if (
		! exactJsonEqual( currentFields, editContext.override_fields ) ||
		( currentExclusion?.action || null ) !== editContext.exclusion_action ||
		currentExclusion?.action === 'skip'
	) {
		throw new Error( 'Occurrence edit context no longer matches its aggregate.' );
	}

	const fieldChanges = changes?.fields ?? {};

	if (
		! fieldChanges ||
		typeof fieldChanges !== 'object' ||
		Array.isArray( fieldChanges )
	) {
		throw new Error( 'Occurrence field changes must be an object.' );
	}

	const proposedFields = { ...( currentFields || {} ) };

	for ( const [ field, value ] of Object.entries( fieldChanges ) ) {
		if ( ! occurrenceOverrideFields.has( field ) ) {
			throw new Error( 'Unsupported occurrence override field.' );
		}

		if ( value === undefined ) {
			continue;
		}

		if ( value === null ) {
			delete proposedFields[ field ];
		} else {
			proposedFields[ field ] = JSON.parse( JSON.stringify( value ) );
		}
	}

	if ( Object.keys( proposedFields ).length === 0 ) {
		if ( overrideIndex >= 0 ) {
			aggregateCopy.overrides.splice( overrideIndex, 1 );
		}
	} else {
		const proposedOverride = { recurrence_id: target, fields: proposedFields };

		if ( overrideIndex >= 0 ) {
			aggregateCopy.overrides[ overrideIndex ] = proposedOverride;
		} else {
			aggregateCopy.overrides.push( proposedOverride );
		}
	}

	if ( Object.hasOwn( changes, 'cancelled' ) ) {
		if ( typeof changes.cancelled !== 'boolean' ) {
			throw new Error( 'Occurrence cancellation must be boolean.' );
		}

		if ( changes.cancelled ) {
			const cancellation = { recurrence_id: target, action: 'cancel' };

			if ( exclusionIndex >= 0 ) {
				aggregateCopy.exclusions[ exclusionIndex ] = cancellation;
			} else {
				aggregateCopy.exclusions.push( cancellation );
			}
		} else if ( exclusionIndex >= 0 ) {
			aggregateCopy.exclusions.splice( exclusionIndex, 1 );
		}
	}

	return {
		aggregate: aggregateCopy,
		scope: 'only_this',
		target,
		revision: context.revision,
		from_date: window.from_date,
		through_date: window.through_date,
		max_rows: window.max_rows,
	};
};

/**
 * Determine whether a complete mutation changes canonical aggregate state.
 *
 * @param {Object} editContext Authorized occurrence edit context.
 * @param {Object} mutation    Proposed only-this mutation.
 * @return {boolean} Whether preview would contain an exception change.
 */
export const onlyThisMutationChanged = ( editContext, mutation ) =>
	! exactJsonEqual( editContext?.context?.aggregate, mutation?.aggregate );

/**
 * Keep only effective generated occurrences that can start a future split.
 *
 * The root is owned by complete-series editing. Manual and detached occurrences
 * can be edited individually but cannot become a generated schedule boundary.
 *
 * @param {Object[]} occurrences Loaded effective occurrence choices.
 * @param {Object}   aggregate   Canonical aggregate from authorized context.
 * @return {Object[]} Valid this-and-following boundary choices.
 */
export const followingOccurrenceChoices = ( occurrences, aggregate ) => {
	const root = aggregate?.segments?.[ 0 ]?.anchor;

	if ( ! Array.isArray( occurrences ) || typeof root !== 'string' ) {
		return [];
	}

	return occurrences.filter(
		( occurrence ) =>
			occurrence?.source === 'rule' &&
			typeof occurrence.recurrence_id === 'string' &&
			occurrence.recurrence_id !== root,
	);
};

/**
 * Build the intentionally small this-and-following preview request.
 *
 * The browser sends neither the canonical aggregate nor a timezone. The server
 * owns structural mutation, exception reconciliation and canonical timezone.
 *
 * @param {Object} editContext Authorized occurrence edit-context response.
 * @param {Object} form        Replacement date/time controls.
 * @param {Object} definition  Strict recurrence definition built from the form.
 * @return {Object} Exact following-preview request.
 */
export const followingMutationFromForm = ( editContext, form, definition ) => {
	const context = editContext?.context;
	const aggregate = context?.aggregate;
	const target = editContext?.target;
	const window = editContext?.window;
	const root = aggregate?.segments?.[ 0 ]?.anchor;

	if (
		! aggregate ||
		typeof target !== 'string' ||
		! target ||
		target === root ||
		editContext?.current?.source !== 'rule' ||
		typeof context.revision !== 'string' ||
		! isIsoDate( window?.from_date ) ||
		! isIsoDate( window?.through_date ) ||
		window.through_date < window.from_date ||
		! Number.isInteger( window.max_rows ) ||
		window.max_rows < 1
	) {
		throw new Error( 'Invalid following edit context.' );
	}

	if ( occurrenceEditFormError( { ...form, status: 'scheduled' }, editContext ) ) {
		throw new Error( 'Invalid following date controls.' );
	}

	if ( ! definition || typeof definition !== 'object' || Array.isArray( definition ) ) {
		throw new Error( 'Invalid following recurrence definition.' );
	}

	return {
		target,
		revision: context.revision,
		from_date: window.from_date,
		through_date: window.through_date,
		max_rows: window.max_rows,
		replacement: {
			template: occurrenceDateRangeFromControls( form ),
			definition: JSON.parse( JSON.stringify( definition ) ),
		},
	};
};

/**
 * Convert one effective occurrence range into native date/time control values.
 *
 * @param {Object} range Serialized effective or override date range.
 * @return {Object} Native control values.
 */
export const occurrenceDateControls = ( range ) => ( {
	allDay: Boolean( range?.all_day ),
	startDate: String( range?.start_local || '' ).slice( 0, 10 ),
	startTime: range?.all_day
		? ''
		: String( range?.start_local || '' ).slice( 11, 16 ),
	endDate: String( range?.end_local || '' ).slice( 0, 10 ),
	endTime: range?.all_day
		? ''
		: String( range?.end_local || '' ).slice( 11, 16 ),
} );

/**
 * Build the editable date/status/cancellation form from server-owned context.
 *
 * @param {Object} editContext Authorized occurrence edit-context response.
 * @return {Object} Editable form state.
 */
export const occurrenceEditFormFromContext = ( editContext ) => {
	const fields = editContext?.override_fields || {};
	const inheritedFields = editContext?.inherited_fields || {};
	const dateOverridden = Object.hasOwn( fields, 'date_range' );
	const statusOverridden = Object.hasOwn( fields, 'status' );
	const range = dateOverridden ? fields.date_range : editContext?.inherited;
	const effectiveField = ( field, fallback = '' ) =>
		Object.hasOwn( fields, field ) ? fields[ field ] : inheritedFields[ field ] ?? fallback;
	const overridden = ( field ) => Object.hasOwn( fields, field );

	return {
		...occurrenceDateControls( range ),
		title: String( effectiveField( 'title' ) ),
		titleOverridden: overridden( 'title' ),
		note: String( effectiveField( 'note' ) ),
		noteOverridden: overridden( 'note' ),
		featuredImageId: Number( effectiveField( 'featured_image_id', 0 ) ),
		featuredImageOverridden: overridden( 'featured_image_id' ),
		dateOverridden,
		status: statusOverridden
			? fields.status
			: editContext?.inherited?.status || 'scheduled',
		statusOverridden,
		venue: String( effectiveField( 'venue' ) ),
		venueOverridden: overridden( 'venue' ),
		address: String( effectiveField( 'address' ) ),
		addressOverridden: overridden( 'address' ),
		locationUrl: String( effectiveField( 'location_url' ) ),
		locationUrlOverridden: overridden( 'location_url' ),
		eventUrl: String( effectiveField( 'event_url' ) ),
		eventUrlOverridden: overridden( 'event_url' ),
		eventUrlLabel: String( effectiveField( 'event_url_label' ) ),
		eventUrlLabelOverridden: overridden( 'event_url_label' ),
		cancelled: editContext?.exclusion_action === 'cancel',
	};
};

/**
 * Convert validated native date/time values into the strict aggregate shape.
 *
 * @param {Object} form Occurrence date controls.
 * @return {Object} Serialized date-range override.
 */
export const occurrenceDateRangeFromControls = ( form ) => ( {
	start_local: form.allDay
		? form.startDate
		: `${ form.startDate }T${ form.startTime }:00`,
	end_local: form.allDay
		? form.endDate
		: `${ form.endDate }T${ form.endTime }:00`,
	all_day: Boolean( form.allDay ),
} );

/**
 * Validate only-this date/status controls without guessing corrections.
 *
 * @param {Object} form        Editable occurrence form.
 * @param {Object} editContext Authorized occurrence edit context.
 * @return {string} Stable empty-or-error code.
 */
export const occurrenceEditFormError = ( form, editContext ) => {
	if ( ! isIsoDate( form?.startDate ) || ! isIsoDate( form?.endDate ) ) {
		return 'invalid_date';
	}

	if (
		! form.allDay &&
		( ! /^([01]\d|2[0-3]):[0-5]\d$/.test( form.startTime ) ||
			! /^([01]\d|2[0-3]):[0-5]\d$/.test( form.endTime ) )
	) {
		return 'invalid_time';
	}

	const range = occurrenceDateRangeFromControls( form );

	if ( range.end_local < range.start_local ) {
		return 'invalid_range';
	}

	if (
		range.start_local.slice( 0, 10 ) < editContext?.window?.from_date ||
		range.end_local.slice( 0, 10 ) > editContext?.window?.through_date
	) {
		return 'outside_window';
	}

	if ( ! [ 'scheduled', 'postponed', 'cancelled' ].includes( form.status ) ) {
		return 'invalid_status';
	}

	if ( Object.hasOwn( form, 'titleOverridden' ) ) {
		return occurrenceContentFormError( form );
	}

	return '';
};

const stringLength = ( value ) => [ ...value ].length;
const unsupportedSingleLineText = ( value ) =>
	/[\u0000-\u001f\u007f]/u.test( value ) || /<[^>]*>/u.test( value );
const unsupportedMultilineText = ( value ) =>
	/[\u0000-\u0009\u000b\u000c\u000d-\u001f\u007f]/u.test( value ) ||
	/<[^>]*>/u.test( value );
const invalidCanonicalText = ( value, maximum, allowEmpty, multiline = false ) =>
	typeof value !== 'string' ||
	value.trim() !== value ||
	( ! allowEmpty && value === '' ) ||
	stringLength( value ) > maximum ||
	( multiline ? unsupportedMultilineText( value ) : unsupportedSingleLineText( value ) );
const invalidHttpUrl = ( value, maximum ) => {
	if ( typeof value !== 'string' || value.trim() !== value || stringLength( value ) > maximum ) {
		return true;
	}

	if ( value === '' ) {
		return false;
	}

	try {
		const parsed = new URL( value );

		return ! [ 'http:', 'https:' ].includes( parsed.protocol );
	} catch {
		return true;
	}
};

/**
 * Validate occurrence-specific content and action fields.
 *
 * Ownership flags decide whether a value is written or inheritance is restored.
 * Limits are supplied by PHP so browser guidance cannot drift from the domain.
 *
 * @param {Object} form Editable occurrence form.
 * @return {string} Stable empty-or-error code.
 */
export const occurrenceContentFormError = ( form ) => {
	const limits = globalThis.wpseRecurrenceEditor?.overrideLimits || {};

	if (
		form.titleOverridden &&
		invalidCanonicalText( form.title, Number( limits.title ), false )
	) {
		return 'invalid_title';
	}

	if (
		form.noteOverridden &&
		invalidCanonicalText( form.note, Number( limits.note ), false, true )
	) {
		return 'invalid_note';
	}

	if (
		form.featuredImageOverridden &&
		( ! Number.isInteger( form.featuredImageId ) || form.featuredImageId < 0 )
	) {
		return 'invalid_featured_image';
	}

	if (
		form.venueOverridden &&
		invalidCanonicalText( form.venue, Number( limits.venue ), true )
	) {
		return 'invalid_venue';
	}

	if (
		form.addressOverridden &&
		invalidCanonicalText( form.address, Number( limits.address ), true, true )
	) {
		return 'invalid_address';
	}

	if (
		form.locationUrlOverridden &&
		invalidHttpUrl( form.locationUrl, Number( limits.url ) )
	) {
		return 'invalid_location_url';
	}

	if (
		form.eventUrlOverridden &&
		invalidHttpUrl( form.eventUrl, Number( limits.url ) )
	) {
		return 'invalid_event_url';
	}

	if (
		form.eventUrlLabelOverridden &&
		invalidCanonicalText(
			form.eventUrlLabel,
			Number( limits.eventUrlLabel ),
			false,
		)
	) {
		return 'invalid_event_url_label';
	}

	return '';
};

/**
 * Build exact only-this field changes from date/status form ownership flags.
 *
 * @param {Object} form Editable occurrence form.
 * @return {Object} Sparse only-this changes.
 */
export const occurrenceChangesFromForm = ( form ) => ( {
	fields: {
		title: form.titleOverridden ? form.title : null,
		note: form.noteOverridden ? form.note : null,
		featured_image_id: form.featuredImageOverridden
			? form.featuredImageId
			: null,
		date_range: form.dateOverridden
			? occurrenceDateRangeFromControls( form )
			: null,
		status: form.statusOverridden ? form.status : null,
		venue: form.venueOverridden ? form.venue : null,
		address: form.addressOverridden ? form.address : null,
		location_url: form.locationUrlOverridden ? form.locationUrl : null,
		event_url: form.eventUrlOverridden ? form.eventUrl : null,
		event_url_label: form.eventUrlLabelOverridden
			? form.eventUrlLabel
			: null,
	},
	cancelled: Boolean( form.cancelled ),
} );

/**
 * Derive calendar properties from one canonical local anchor date.
 *
 * @param {Object} aggregate Complete recurrence aggregate.
 * @return {Object} Calendar anchor fields.
 */
export const recurrenceAnchorParts = ( aggregate ) => {
	const anchor = aggregate?.segments?.[ 0 ]?.anchor || '';
	const date = anchor.slice( 0, 10 );
	const value = new Date( `${ date }T12:00:00Z` );
	const nextWeek = new Date( value );
	nextWeek.setUTCDate( nextWeek.getUTCDate() + 7 );

	return {
		date,
		day: value.getUTCDate(),
		month: value.getUTCMonth() + 1,
		weekday: value.getUTCDay() === 0 ? 7 : value.getUTCDay(),
		ordinal: Math.ceil( value.getUTCDate() / 7 ),
		isLastWeekday: nextWeek.getUTCMonth() !== value.getUTCMonth(),
	};
};

/**
 * Preserve the distinction between ordinal and last-weekday monthly rules.
 *
 * @param {Object} definition Canonically encoded recurrence definition.
 * @return {string} Editor monthly pattern.
 */
export const monthlyPatternFromDefinition = ( definition ) => {
	if (
		definition.monthly_mode === 'ordinal_weekday' &&
		definition.ordinal === -1
	) {
		return 'last_weekday';
	}

	return definition.monthly_mode || 'day_of_month';
};

/**
 * Encode only the monthly fields owned by the selected editor pattern.
 *
 * @param {string} pattern Selected editor pattern.
 * @param {Object} anchor  Derived anchor parts.
 * @return {Object} Canonical monthly rule fields.
 */
export const monthlyRuleFields = ( pattern, anchor ) => {
	if ( pattern === 'day_of_month' ) {
		return {
			monthly_mode: 'day_of_month',
			month_day: anchor.day,
		};
	}

	return {
		monthly_mode: 'ordinal_weekday',
		ordinal: pattern === 'last_weekday' ? -1 : anchor.ordinal,
		weekday: anchor.weekday,
	};
};
