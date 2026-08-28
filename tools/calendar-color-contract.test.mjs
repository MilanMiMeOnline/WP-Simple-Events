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

test( 'shows bounded colors as decorative dots on compact month events', async () => {
	const source = await readFile( new URL( '../assets/src/js/calendar.js', import.meta.url ), 'utf8' );
	const styles = await readFile( new URL( '../assets/src/css/frontend.css', import.meta.url ), 'utf8' );

	assert.match( source, /fc-daygrid-dot-event/ );
	assert.match( source, /wpse-calendar-daygrid-event-has-color/ );
	assert.match( styles, /\.fc-daygrid-dot-event::before/ );
	assert.match( styles, /var\(--wpse-event-color, var\(--wpse-calendar-event-background\)\)/ );
	assert.match( styles, /border-radius:\s*50%/ );
} );
