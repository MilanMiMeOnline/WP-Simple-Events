import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { calendarEventInput, calendarSegmentTime, calendarTimeFormatter } from '../assets/src/js/calendar-utils.js';

test( 'adapts stable feed colors without mutating dates, identity or metadata', () => {
	const event = {
		id: '42:2026-10-25',
		start: '2026-10-25T02:30:00',
		end: '2026-10-25T03:30:00',
		backgroundColor: '#336699',
		borderColor: '#336699',
		textColor: '#ffffff',
		extendedProps: { status: 'cancelled', timezone: 'Europe/Brussels' },
	};
	const input = calendarEventInput( event );
	assert.deepEqual( input, { ...event, color: '#336699', contrastColor: '#ffffff' } );
	assert.equal( event.color, undefined );
} );

test( 'keeps component defaults for absent or invalid feed colors', () => {
	for ( const value of [ undefined, '', 'red', '#fff', 'url(https://example.com)', '#123456;' ] ) {
		const input = calendarEventInput( { backgroundColor: value, textColor: value } );
		assert.equal( input.color, undefined );
		assert.equal( input.contrastColor, undefined );
	}
	assert.equal( calendarEventInput( { start: '2026-10-25', allDay: true } ).start, '2026-10-25' );
} );

test( 'labels first, middle and last timed segments, preserving one-day and all-day labels', () => {
	const strings = { startsAt: 'Starts at %s', endsAt: 'Ends at %s', continues: 'Continues' };
	const event = { start: new Date( 2026, 9, 24, 19 ), end: new Date( 2026, 9, 26, 21 ), allDay: false };
	const format = ( changes ) => calendarSegmentTime(
		{ event, timeText: '19:00', isStart: true, isEnd: false, ...changes },
		strings,
		( date ) => `${ date.getHours() }:00`,
	);
	assert.equal( format( {} ), 'Starts at 19:00' );
	assert.equal( format( { isStart: false } ), 'Continues' );
	assert.equal( format( { isStart: false, isEnd: true, timeText: '21:00' } ), 'Ends at 21:00' );
	assert.equal( format( { event: { ...event, allDay: true }, timeText: 'all-day' } ), 'all-day' );
	assert.equal( format( { event: { ...event, end: new Date( 2026, 9, 24, 21 ) } } ), '19:00 - 21:00' );
	assert.equal( format( { event: { ...event, end: null } } ), '19:00' );
	assert.equal( format( { event: { ...event, end: new Date( 2026, 9, 25, 0 ) }, isEnd: true } ), '19:00 - 0:00' );
} );

test( 'preserves midnight, 12-hour suffixes and formats without minutes or meridiem', () => {
	const midnight = new Date( 2026, 9, 25, 0, 30 );
	const noon = new Date( 2026, 9, 25, 12, 5 );
	const format = ( options ) => calendarTimeFormatter( 'en-US', options );
	assert.equal( format( { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', meridiem: false } )( midnight ), '00:30' );
	assert.equal( format( { hour: 'numeric', minute: '2-digit', hourCycle: 'h12', meridiem: 'lowercase' } )( midnight ), '12:30 am' );
	assert.equal( format( { hour: '2-digit', minute: '2-digit', hourCycle: 'h12', meridiem: true } )( noon ), '12:05 PM' );
	assert.equal( format( { hour: 'numeric', hourCycle: 'h12', meridiem: false } )( noon ), '12' );
	assert.equal( calendarTimeFormatter( 'nl-BE', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', meridiem: false } )( noon ), '12:05' );
} );
