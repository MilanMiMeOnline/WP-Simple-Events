import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const stylesheet = await readFile(
	new URL( '../assets/src/css/frontend.css', import.meta.url ),
	'utf8',
);

test( 'consumes opt-in component variables for practical presentation controls', () => {
	for ( const variable of [
		'--wpse-card-background',
		'--wpse-card-padding',
		'--wpse-grid-row-gap',
		'--wpse-grid-column-gap',
		'--wpse-filter-background',
		'--wpse-filter-padding',
		'--wpse-filter-panel-background',
		'--wpse-filter-panel-border',
		'--wpse-filter-panel-radius',
		'--wpse-filter-trigger-background',
		'--wpse-filter-trigger-border',
		'--wpse-filter-trigger-padding',
		'--wpse-filter-options-max-height',
		'--wpse-filter-checkbox-size',
		'--wpse-filter-chip-background',
		'--wpse-filter-action-background',
		'--wpse-control-background',
		'--wpse-pagination-background',
		'--wpse-pagination-padding',
		'--wpse-calendar-background',
		'--wpse-calendar-event-background',
		'--wpse-calendar-button-hover-background',
		'--wpse-summary-padding',
		'--wpse-action-padding',
		'--wpse-single-image-ratio',
	] ) {
		assert.match( stylesheet, new RegExp( `var\\(\\s*${ variable.replaceAll( '-', '\\-' ) }` ) );
	}
} );

test( 'uses event component width for card columns with a legacy viewport fallback', () => {
	assert.match( stylesheet, /container-type:\s*inline-size/ );
	assert.match( stylesheet, /@container\s+wpse-events\s+\(max-width:\s*599px\)/ );
	assert.match( stylesheet, /@container\s+wpse-events\s+\(min-width:\s*600px\)/ );
	assert.match(
		stylesheet,
		/\.wpse-events\.wpse-events\s+\.wpse-events-view-grid\.wpse-events-columns-3/,
	);
	assert.doesNotMatch( stylesheet, /!important/ );
	assert.match( stylesheet, /@supports\s+not\s+\(container-type:\s*inline-size\)/ );
} );

test( 'keeps selectors scoped to plugin components', () => {
	assert.doesNotMatch( stylesheet, /(^|\n)\s*(button|select|a|img|ul|article)\s*\{/ );
} );
