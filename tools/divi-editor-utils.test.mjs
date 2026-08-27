import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import { namespacePreviewHtml } from '../assets/src/js/divi-editor-utils.mjs';

test( 'namespaces every Divi preview ID and its local references', () => {
	const html = '<section id="wpse-calendar-1" aria-labelledby="wpse-calendar-1-title other"><h3 id="wpse-calendar-1-title">Events</h3><label for="wpse-calendar-1-filter">Filter</label><select id="wpse-calendar-1-filter" aria-describedby="wpse-calendar-1-help"></select><span id="wpse-calendar-1-help"></span><a href="#wpse-calendar-1-title">Jump</a></section>';
	const output = namespacePreviewHtml( html, 'module:42/value' );

	assert.match(
		output,
		/id="wpse-divi-module-42-value-[a-z0-9]+-wpse-calendar-1"/,
	);
	assert.match(
		output,
		/aria-labelledby="wpse-divi-module-42-value-[a-z0-9]+-wpse-calendar-1-title other"/,
	);
	assert.match(
		output,
		/for="wpse-divi-module-42-value-[a-z0-9]+-wpse-calendar-1-filter"/,
	);
	assert.match(
		output,
		/aria-describedby="wpse-divi-module-42-value-[a-z0-9]+-wpse-calendar-1-help"/,
	);
	assert.match(
		output,
		/href="#wpse-divi-module-42-value-[a-z0-9]+-wpse-calendar-1-title"/,
	);
	assert.doesNotMatch( output, /id="wpse-calendar-/ );
} );

test( 'keeps host identifiers distinct when their readable forms collide', () => {
	const html = '<div id="wpse-calendar-1"></div>';
	const first = namespacePreviewHtml( html, 'module:42' );
	const second = namespacePreviewHtml( html, 'module/42' );

	assert.notEqual( first, second );
} );

test( 'preserves empty HTML and markup without identifiers', () => {
	assert.equal( namespacePreviewHtml( '', 'module' ), '' );
	assert.equal(
		namespacePreviewHtml( '<p>Safe content</p>', 'module' ),
		'<p>Safe content</p>',
	);
} );
