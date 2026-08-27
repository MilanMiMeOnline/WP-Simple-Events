import { strict as assert } from 'node:assert';
import { test } from 'node:test';

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
	occurrenceContentFormError,
	occurrenceDateControls,
	occurrenceDateRangeFromControls,
	occurrenceEditFormError,
	occurrenceEditFormFromContext,
	onlyThisMutationChanged,
	onlyThisMutationFromChanges,
	orderedIsoWeekdays,
	recurrenceAnchorParts,
	showsRecurrenceEndControls,
} from '../assets/src/js/recurrence-editor-utils.mjs';

const aggregateOn = ( date ) => ( {
	segments: [ { anchor: `${ date }T19:00:00` } ],
} );

test( 'orders ISO weekdays from the WordPress week start', () => {
	assert.deepEqual( orderedIsoWeekdays( 0 ), [ 7, 1, 2, 3, 4, 5, 6 ] );
	assert.deepEqual( orderedIsoWeekdays( 1 ), [ 1, 2, 3, 4, 5, 6, 7 ] );
	assert.deepEqual( orderedIsoWeekdays( 6 ), [ 6, 7, 1, 2, 3, 4, 5 ] );
	assert.deepEqual( orderedIsoWeekdays( 99 ), [ 1, 2, 3, 4, 5, 6, 7 ] );
} );

test( 'normalizes localized numeric strings before date arithmetic', () => {
	assert.equal( addIsoDateDays( '2026-08-31', '540' ), '2028-02-22' );
} );

test( 'validates canonical real ISO dates at the editor boundary', () => {
	assert.equal( isIsoDate( '2028-02-29' ), true );
	assert.equal( isIsoDate( '2027-02-29' ), false );
	assert.equal( isIsoDate( '2027-99-99' ), false );
	assert.equal( isIsoDate( '2027-2-09' ), false );
	assert.equal( isIsoDate( null ), false );
} );

test( 'builds a normalized bounded survivor-search window', () => {
	assert.deepEqual( boundedOccurrenceWindow( '2026-08-31', '540', '1000' ), {
		from_date: '2026-08-31',
		through_date: '2028-02-22',
		max_rows: 1000,
	} );
} );

test( 'shows recurrence termination only for generated repeat rules', () => {
	assert.equal( showsRecurrenceEndControls( 'once' ), false );
	assert.equal( showsRecurrenceEndControls( 'specific_dates' ), false );
	assert.equal( showsRecurrenceEndControls( 'daily' ), true );
	assert.equal( showsRecurrenceEndControls( 'weekly' ), true );
	assert.equal( showsRecurrenceEndControls( 'monthly' ), true );
	assert.equal( showsRecurrenceEndControls( 'yearly' ), true );
} );

test( 'starts survivor discovery near active and already-ended schedules', () => {
	const active = {
		segments: [ {
			anchor: '2020-01-01T19:00:00',
			definition: {
				type: 'rule',
				frequency: 'weekly',
				end: { mode: 'never' },
			},
		} ],
	};
	const ended = {
		segments: [ {
			anchor: '2020-01-01T19:00:00',
			definition: {
				type: 'rule',
				frequency: 'weekly',
				end: { mode: 'until', date: '2024-06-30' },
			},
		} ],
	};
	const selectedDates = {
		segments: [ {
			anchor: '2020-01-01T19:00:00',
			definition: {
				type: 'specific_dates',
				dates: [ '2020-01-01', '2023-12-31' ],
			},
		} ],
	};

	assert.equal(
		initialOccurrenceWindowStart( active, '2026-08-22', 540 ),
		'2026-08-22',
	);
	assert.equal(
		initialOccurrenceWindowStart( ended, '2026-08-22', 540 ),
		'2023-01-07',
	);
	assert.equal(
		initialOccurrenceWindowStart( selectedDates, '2026-08-22', 540 ),
		'2022-07-09',
	);
	assert.equal(
		initialOccurrenceWindowStart( active, '2019-12-31', 540 ),
		'2020-01-01',
	);
} );

const occurrenceEditContext = () => ( {
	context: {
		revision: 'a'.repeat( 64 ),
		aggregate: {
			segments: [ { anchor: '2027-01-01T19:00:00' } ],
			manuals: [],
			exclusions: [
				{ recurrence_id: '2027-01-07T19:00:00', action: 'cancel' },
			],
			overrides: [
				{
					recurrence_id: '2027-01-05T19:00:00',
					fields: {
						title: 'Special edition',
						venue: 'Side room',
						status: 'postponed',
					},
				},
				{
					recurrence_id: '2027-01-07T19:00:00',
					fields: { title: 'Another occurrence' },
				},
			],
		},
	},
	window: {
		from_date: '2027-01-01',
		through_date: '2027-01-31',
		max_rows: 100,
	},
	target: '2027-01-05T19:00:00',
	inherited_fields: {
		title: 'Series title',
		note: '',
		featured_image_id: 91,
		venue: 'Main hall',
		address: 'Main Street 1',
		location_url: 'https://example.com/location',
		event_url: 'https://example.com/event',
		event_url_label: 'Register',
	},
	override_fields: {
		title: 'Special edition',
		venue: 'Side room',
		status: 'postponed',
	},
	exclusion_action: null,
} );

test( 'builds a lossless only-this proposal without touching another identity', () => {
	const editContext = occurrenceEditContext();
	const original = structuredClone( editContext );
	const mutation = onlyThisMutationFromChanges( editContext, {
		fields: {
			title: 'Updated special edition',
			status: null,
			date_range: {
				start_local: '2027-01-05T20:00:00',
				end_local: '2027-01-05T22:00:00',
				all_day: false,
			},
		},
		cancelled: true,
	} );

	assert.deepEqual( editContext, original );
	assert.equal( mutation.scope, 'only_this' );
	assert.equal( mutation.target, editContext.target );
	assert.equal( mutation.from_date, '2027-01-01' );
	assert.equal( mutation.max_rows, 100 );
	assert.deepEqual( mutation.aggregate.overrides[ 0 ].fields, {
		title: 'Updated special edition',
		venue: 'Side room',
		date_range: {
			start_local: '2027-01-05T20:00:00',
			end_local: '2027-01-05T22:00:00',
			all_day: false,
		},
	} );
	assert.deepEqual(
		mutation.aggregate.overrides[ 1 ],
		editContext.context.aggregate.overrides[ 1 ],
	);
	assert.deepEqual( mutation.aggregate.exclusions, [
		editContext.context.aggregate.exclusions[ 0 ],
		{ recurrence_id: editContext.target, action: 'cancel' },
	] );
	assert.equal( onlyThisMutationChanged( editContext, mutation ), true );
} );

test( 'restores inheritance by removing the final target override and cancellation', () => {
	const editContext = occurrenceEditContext();
	editContext.context.aggregate.exclusions.push( {
		recurrence_id: editContext.target,
		action: 'cancel',
	} );
	editContext.exclusion_action = 'cancel';
	const mutation = onlyThisMutationFromChanges( editContext, {
		fields: {
			title: null,
			venue: null,
			status: null,
		},
		cancelled: false,
	} );

	assert.equal(
		mutation.aggregate.overrides.some(
			( override ) => override.recurrence_id === editContext.target,
		),
		false,
	);
	assert.equal(
		mutation.aggregate.exclusions.some(
			( exclusion ) => exclusion.recurrence_id === editContext.target,
		),
		false,
	);
	assert.equal( onlyThisMutationChanged( editContext, mutation ), true );
} );

test( 'preserves a no-op and rejects stale or unsupported occurrence edit state', () => {
	const editContext = occurrenceEditContext();
	const noOp = onlyThisMutationFromChanges( editContext );

	assert.equal( onlyThisMutationChanged( editContext, noOp ), false );
	assert.throws(
		() => onlyThisMutationFromChanges( editContext, { fields: { body: 'No' } } ),
		/Unsupported occurrence override field/,
	);

	const stale = occurrenceEditContext();
	stale.override_fields.title = 'Different response';
	assert.throws(
		() => onlyThisMutationFromChanges( stale ),
		/no longer matches/,
	);
} );

test( 'offers only non-root generated occurrences as following boundaries', () => {
	const aggregate = {
		segments: [ { anchor: '2027-01-01T19:00:00' } ],
	};
	const occurrences = [
		{ recurrence_id: '2027-01-01T19:00:00', source: 'rule' },
		{ recurrence_id: '2027-01-02T19:00:00', source: 'rule' },
		{ recurrence_id: 'manual:test', source: 'manual' },
		{ recurrence_id: '2027-01-03T19:00:00', source: 'manual' },
	];

	assert.deepEqual( followingOccurrenceChoices( occurrences, aggregate ), [
		occurrences[ 1 ],
	] );
	assert.deepEqual( followingOccurrenceChoices( null, aggregate ), [] );
} );

test( 'builds a narrow following request without aggregate or client timezone', () => {
	const editContext = occurrenceEditContext();
	editContext.target = '2027-01-05T19:00:00';
	editContext.current = {
		recurrence_id: editContext.target,
		start_local: '2027-01-05T19:00:00',
		end_local: '2027-01-05T21:00:00',
		all_day: false,
		timezone: 'Europe/Brussels',
		status: 'postponed',
		source: 'rule',
	};
	const mutation = followingMutationFromForm(
		editContext,
		{
			allDay: false,
			startDate: '2027-01-05',
			startTime: '20:00',
			endDate: '2027-01-05',
			endTime: '22:00',
		},
		{
			type: 'rule',
			frequency: 'weekly',
			interval: 2,
			weekdays: [ 2, 5 ],
			end: { mode: 'count', count: 4 },
		},
	);

	assert.deepEqual( Object.keys( mutation ), [
		'target',
		'revision',
		'from_date',
		'through_date',
		'max_rows',
		'replacement',
	] );
	assert.equal( Object.hasOwn( mutation, 'aggregate' ), false );
	assert.deepEqual( mutation.replacement, {
		template: {
			start_local: '2027-01-05T20:00:00',
			end_local: '2027-01-05T22:00:00',
			all_day: false,
		},
		definition: {
			type: 'rule',
			frequency: 'weekly',
			interval: 2,
			weekdays: [ 2, 5 ],
			end: { mode: 'count', count: 4 },
		},
	} );
	assert.equal( Object.hasOwn( mutation.replacement, 'timezone' ), false );
} );

test( 'rejects root, manual and invalid following replacement state', () => {
	const editContext = occurrenceEditContext();
	editContext.current = {
		start_local: '2027-01-05T19:00:00',
		end_local: '2027-01-05T21:00:00',
		all_day: false,
		source: 'rule',
	};
	const form = {
		allDay: false,
		startDate: '2027-01-05',
		startTime: '19:00',
		endDate: '2027-01-05',
		endTime: '21:00',
	};
	const definition = {
		type: 'rule',
		frequency: 'daily',
		interval: 1,
		end: { mode: 'never' },
	};

	editContext.target = editContext.context.aggregate.segments[ 0 ].anchor;
	assert.throws(
		() => followingMutationFromForm( editContext, form, definition ),
		/Invalid following edit context/,
	);

	editContext.target = '2027-01-05T19:00:00';
	editContext.current.source = 'manual';
	assert.throws(
		() => followingMutationFromForm( editContext, form, definition ),
		/Invalid following edit context/,
	);

	editContext.current.source = 'rule';
	assert.throws(
		() => followingMutationFromForm( editContext, { ...form, startTime: '' }, definition ),
		/Invalid following date controls/,
	);
} );

test( 'derives only-this controls from inherited and overridden occurrence state', () => {
	const editContext = occurrenceEditContext();
	editContext.inherited = {
		start_local: '2027-01-05T19:00:00',
		end_local: '2027-01-05T21:00:00',
		all_day: false,
		status: 'scheduled',
	};
	let form = occurrenceEditFormFromContext( editContext );

	assert.deepEqual( form, {
		allDay: false,
		startDate: '2027-01-05',
		startTime: '19:00',
		endDate: '2027-01-05',
		endTime: '21:00',
		title: 'Special edition',
		titleOverridden: true,
		note: '',
		noteOverridden: false,
		featuredImageId: 91,
		featuredImageOverridden: false,
		dateOverridden: false,
		status: 'postponed',
		statusOverridden: true,
		venue: 'Side room',
		venueOverridden: true,
		address: 'Main Street 1',
		addressOverridden: false,
		locationUrl: 'https://example.com/location',
		locationUrlOverridden: false,
		eventUrl: 'https://example.com/event',
		eventUrlOverridden: false,
		eventUrlLabel: 'Register',
		eventUrlLabelOverridden: false,
		cancelled: false,
	} );

	editContext.override_fields.date_range = {
		start_local: '2027-01-06',
		end_local: '2027-01-07',
		all_day: true,
	};
	editContext.exclusion_action = 'cancel';
	form = occurrenceEditFormFromContext( editContext );
	assert.deepEqual( occurrenceDateControls( editContext.override_fields.date_range ), {
		allDay: true,
		startDate: '2027-01-06',
		startTime: '',
		endDate: '2027-01-07',
		endTime: '',
	} );
	assert.equal( form.dateOverridden, true );
	assert.equal( form.cancelled, true );
} );

test( 'encodes and validates timed occurrence edits inside their loaded window', () => {
	globalThis.wpseRecurrenceEditor = {
		overrideLimits: {
			title: 200,
			note: 1000,
			venue: 200,
			address: 500,
			url: 2048,
			eventUrlLabel: 120,
		},
	};
	const editContext = occurrenceEditContext();
	const form = {
		allDay: false,
		startDate: '2027-01-05',
		startTime: '20:30',
		endDate: '2027-01-05',
		endTime: '22:00',
		title: 'Special edition',
		titleOverridden: true,
		note: 'Doors open at 19:30.',
		noteOverridden: true,
		featuredImageId: 123,
		featuredImageOverridden: true,
		dateOverridden: true,
		status: 'postponed',
		statusOverridden: true,
		venue: '',
		venueOverridden: true,
		address: 'Side entrance',
		addressOverridden: true,
		locationUrl: '',
		locationUrlOverridden: true,
		eventUrl: 'https://example.com/special',
		eventUrlOverridden: true,
		eventUrlLabel: 'Special tickets',
		eventUrlLabelOverridden: true,
		cancelled: true,
	};

	assert.equal( occurrenceEditFormError( form, editContext ), '' );
	assert.deepEqual( occurrenceDateRangeFromControls( form ), {
		start_local: '2027-01-05T20:30:00',
		end_local: '2027-01-05T22:00:00',
		all_day: false,
	} );
	assert.deepEqual( occurrenceChangesFromForm( form ), {
		fields: {
			title: 'Special edition',
			note: 'Doors open at 19:30.',
			featured_image_id: 123,
			date_range: {
				start_local: '2027-01-05T20:30:00',
				end_local: '2027-01-05T22:00:00',
				all_day: false,
			},
			status: 'postponed',
			venue: '',
			address: 'Side entrance',
			location_url: '',
			event_url: 'https://example.com/special',
			event_url_label: 'Special tickets',
		},
		cancelled: true,
	} );
} );

test( 'validates bounded occurrence content without preventing deliberate hidden fields', () => {
	globalThis.wpseRecurrenceEditor = {
		overrideLimits: {
			title: 200,
			note: 1000,
			venue: 200,
			address: 500,
			url: 2048,
			eventUrlLabel: 120,
		},
	};
	const base = occurrenceEditFormFromContext( occurrenceEditContext() );

	assert.equal( occurrenceContentFormError( base ), '' );
	assert.equal(
		occurrenceContentFormError( {
			...base,
			title: '<strong>Unsafe</strong>',
			titleOverridden: true,
		} ),
		'invalid_title',
	);
	assert.equal(
		occurrenceContentFormError( {
			...base,
			note: '',
			noteOverridden: true,
		} ),
		'invalid_note',
	);
	assert.equal(
		occurrenceContentFormError( {
			...base,
			venue: '',
			venueOverridden: true,
			address: '',
			addressOverridden: true,
			locationUrl: '',
			locationUrlOverridden: true,
		} ),
		'',
	);
	assert.equal(
		occurrenceContentFormError( {
			...base,
			eventUrl: 'javascript:alert(1)',
			eventUrlOverridden: true,
		} ),
		'invalid_event_url',
	);
	assert.equal(
		occurrenceContentFormError( {
			...base,
			eventUrlLabel: ' ',
			eventUrlLabelOverridden: true,
		} ),
		'invalid_event_url_label',
	);
} );

test( 'rejects malformed, reversed and out-of-window occurrence date controls', () => {
	const editContext = occurrenceEditContext();
	const base = {
		allDay: false,
		startDate: '2027-01-05',
		startTime: '19:00',
		endDate: '2027-01-05',
		endTime: '21:00',
		status: 'scheduled',
	};

	assert.equal(
		occurrenceEditFormError( { ...base, startDate: '2027-02-30' }, editContext ),
		'invalid_date',
	);
	assert.equal(
		occurrenceEditFormError( { ...base, startTime: '25:00' }, editContext ),
		'invalid_time',
	);
	assert.equal(
		occurrenceEditFormError( { ...base, endTime: '18:00' }, editContext ),
		'invalid_range',
	);
	assert.equal(
		occurrenceEditFormError( { ...base, startDate: '2027-02-01', endDate: '2027-02-01' }, editContext ),
		'outside_window',
	);
} );

test( 'derives ordinal and last-weekday state without local timezone drift', () => {
	assert.deepEqual( recurrenceAnchorParts( aggregateOn( '2027-02-22' ) ), {
		date: '2027-02-22',
		day: 22,
		month: 2,
		weekday: 1,
		ordinal: 4,
		isLastWeekday: true,
	} );
	assert.equal(
		recurrenceAnchorParts( aggregateOn( '2027-02-15' ) ).isLastWeekday,
		false,
	);
} );

test( 'round-trips last-weekday monthly rules without changing their meaning', () => {
	assert.equal(
		monthlyPatternFromDefinition( {
			monthly_mode: 'ordinal_weekday',
			ordinal: -1,
		} ),
		'last_weekday',
	);
	assert.deepEqual(
		monthlyRuleFields(
			'last_weekday',
			recurrenceAnchorParts( aggregateOn( '2027-02-22' ) ),
		),
		{
			monthly_mode: 'ordinal_weekday',
			ordinal: -1,
			weekday: 1,
		},
	);
} );

test( 'encodes ordinary ordinal and calendar-day monthly choices exactly', () => {
	const anchor = recurrenceAnchorParts( aggregateOn( '2027-02-15' ) );

	assert.deepEqual( monthlyRuleFields( 'ordinal_weekday', anchor ), {
		monthly_mode: 'ordinal_weekday',
		ordinal: 3,
		weekday: 1,
	} );
	assert.deepEqual( monthlyRuleFields( 'day_of_month', anchor ), {
		monthly_mode: 'day_of_month',
		month_day: 15,
	} );
} );
