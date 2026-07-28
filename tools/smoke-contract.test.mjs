import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import {
	pluginActionUrl,
	pluginFileFromPath,
	themeActivationUrl,
} from './smoke-contract.mjs';

test( 'derives the mounted plugin file from source and release directories', () => {
	assert.equal(
		pluginFileFromPath( '/project/WordPress Event Plugin' ),
		'WordPress Event Plugin/mime-simple-events-calendar.php',
	);
	assert.equal(
		pluginFileFromPath( '/project/.release/mime-simple-events-calendar' ),
		'mime-simple-events-calendar/mime-simple-events-calendar.php',
	);
} );

test( 'finds only the requested nonce-protected theme activation URL', () => {
	const body = `
		<a href="themes.php?action=activate&#038;stylesheet=wpse-classic-shell&#038;_wpnonce=one">Activate classic</a>
		<a href="themes.php?action=activate&amp;stylesheet=other-theme&amp;_wpnonce=two">Activate other</a>
	`;
	const action = themeActivationUrl( body, 'wpse-classic-shell' );

	assert.equal( action?.searchParams.get( 'stylesheet' ), 'wpse-classic-shell' );
	assert.equal( action?.searchParams.get( '_wpnonce' ), 'one' );
	assert.equal( themeActivationUrl( body, 'missing-theme' ), null );
} );

test( 'finds only an action for the expected mounted plugin file', () => {
	const body = `
		<a href="plugins.php?action=activate&#038;plugin=WordPress%20Event%20Plugin%2Fmime-simple-events-calendar.php">Activate source</a>
		<a href="plugins.php?action=activate&amp;plugin=other%2Fother.php">Activate other</a>
	`;
	const action = pluginActionUrl(
		body,
		'activate',
		'WordPress Event Plugin/mime-simple-events-calendar.php',
	);

	assert.equal(
		action?.searchParams.get( 'plugin' ),
		'WordPress Event Plugin/mime-simple-events-calendar.php',
	);
	assert.equal(
		pluginActionUrl( body, 'deactivate', 'WordPress Event Plugin/mime-simple-events-calendar.php' ),
		null,
	);
	assert.equal(
		pluginActionUrl( body, 'activate', 'missing/missing.php' ),
		null,
	);
} );
