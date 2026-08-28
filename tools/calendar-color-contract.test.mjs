import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

test( 'keeps list accents bounded and independent from event text', async () => {
	const source = await readFile( new URL( '../assets/src/js/calendar.js', import.meta.url ), 'utf8' );

	assert.match( source, /\^#\[0-9a-f\]\{6\}\$/i );
	assert.match( source, /wpse-calendar-list-event-has-color/ );
	assert.match( source, /--wpse-event-color/ );
	assert.match( source, /title\.textContent = argument\.event\.title/ );
} );
