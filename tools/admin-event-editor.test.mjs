import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const script = await readFile(
	new URL( '../assets/src/js/admin-event.js', import.meta.url ),
	'utf8',
);

function control( value = '' ) {
	const listeners = new Map();

	return {
		checked: false,
		disabled: false,
		value,
		addEventListener( eventName, listener ) {
			listeners.set( eventName, listener );
		},
		fire( eventName ) {
			listeners.get( eventName )?.();
		},
	};
}

function editorFixture( { withEditorStore = true } = {} ) {
	const controls = {
		'#wpse-address': control(),
		'#wpse-all-day': control(),
		'#wpse-end-date': control(),
		'#wpse-end-time': control(),
		'#wpse-event-url': control(),
		'#wpse-location-url': control(),
		'#wpse-start-date': control(),
		'#wpse-start-time': control(),
		'#wpse-status': control( 'scheduled' ),
		'#wpse-venue': control(),
	};
	const timeWrappers = [
		{
			hidden: false,
			querySelector: () => controls[ '#wpse-start-time' ],
		},
		{
			hidden: false,
			querySelector: () => controls[ '#wpse-end-time' ],
		},
	];
	const eventFields = {
		querySelector: ( selector ) => controls[ selector ] ?? null,
		querySelectorAll: ( selector ) =>
			selector === '[data-wpse-time-field]' ? timeWrappers : [],
	};
	const edits = [];
	let currentMeta = {
		_unrelated_plugin_meta: 'preserved',
		_wpse_address: '',
		_wpse_all_day: false,
		_wpse_end_local: '',
		_wpse_event_status: 'scheduled',
		_wpse_event_url: '',
		_wpse_location_url: '',
		_wpse_start_local: '',
		_wpse_timezone: 'Europe/Brussels',
		_wpse_venue: '',
	};
	const data = withEditorStore
		? {
			dispatch: ( storeName ) => {
				assert.equal( storeName, 'core/editor' );

				return {
					editPost( attributes ) {
						edits.push( structuredClone( attributes ) );
						currentMeta = attributes.meta;
					},
				};
			},
			select: ( storeName ) => {
				assert.equal( storeName, 'core/editor' );

				return {
					getEditedPostAttribute: ( attributeName ) =>
						attributeName === 'meta' ? currentMeta : undefined,
				};
			},
		}
		: undefined;

	runInNewContext( script, {
		document: {
			querySelector: ( selector ) =>
				selector === '[data-wpse-event-fields]' ? eventFields : null,
		},
		wp: data ? { data } : undefined,
	} );

	return { controls, edits, timeWrappers };
}

test( 'moves timed metabox values into the Gutenberg REST meta payload', () => {
	const { controls, edits } = editorFixture();

	controls[ '#wpse-start-date' ].value = '2026-07-19';
	controls[ '#wpse-start-time' ].value = '16:23';
	controls[ '#wpse-end-date' ].value = '2026-07-19';
	controls[ '#wpse-end-time' ].value = '21:24';
	controls[ '#wpse-venue' ].value = 'Casa Milan';
	controls[ '#wpse-address' ].value = 'Grote Markt 3, 2850 Boom';
	controls[ '#wpse-location-url' ].value =
		'https://maps.example.test/casa-milan';
	controls[ '#wpse-event-url' ].value = 'https://mime-online.be';
	controls[ '#wpse-start-date' ].fire( 'input' );

	assert.deepEqual( edits.at( -1 ), {
		meta: {
			_unrelated_plugin_meta: 'preserved',
			_wpse_address: 'Grote Markt 3, 2850 Boom',
			_wpse_all_day: false,
			_wpse_end_local: '2026-07-19T21:24',
			_wpse_event_status: 'scheduled',
			_wpse_event_url: 'https://mime-online.be',
			_wpse_location_url: 'https://maps.example.test/casa-milan',
			_wpse_start_local: '2026-07-19T16:23',
			_wpse_timezone: 'Europe/Brussels',
			_wpse_venue: 'Casa Milan',
		},
	} );
} );

test( 'uses date-only canonical values and disables time inputs for all-day events', () => {
	const { controls, edits, timeWrappers } = editorFixture();

	controls[ '#wpse-start-date' ].value = '2026-10-24';
	controls[ '#wpse-start-time' ].value = '10:00';
	controls[ '#wpse-end-date' ].value = '2026-10-25';
	controls[ '#wpse-end-time' ].value = '18:00';
	controls[ '#wpse-all-day' ].checked = true;
	controls[ '#wpse-all-day' ].fire( 'change' );

	assert.equal( edits.at( -1 ).meta._wpse_start_local, '2026-10-24' );
	assert.equal( edits.at( -1 ).meta._wpse_end_local, '2026-10-25' );
	assert.equal( edits.at( -1 ).meta._wpse_all_day, true );
	assert.ok( timeWrappers.every( ( wrapper ) => wrapper.hidden ) );
	assert.equal( controls[ '#wpse-start-time' ].disabled, true );
	assert.equal( controls[ '#wpse-end-time' ].disabled, true );
} );

test( 'keeps classic-editor time toggling safe when the Gutenberg store is absent', () => {
	const { controls, timeWrappers } = editorFixture( {
		withEditorStore: false,
	} );

	controls[ '#wpse-all-day' ].checked = true;
	assert.doesNotThrow( () =>
		controls[ '#wpse-all-day' ].fire( 'change' ),
	);
	assert.ok( timeWrappers.every( ( wrapper ) => wrapper.hidden ) );
} );
