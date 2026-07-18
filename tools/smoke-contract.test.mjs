import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import {
	pluginActionUrl,
	pluginFileFromPath,
} from './smoke-contract.mjs';

test( 'derives the mounted plugin file from source and release directories', () => {
	assert.equal(
		pluginFileFromPath( '/project/WordPress Event Plugin' ),
		'WordPress Event Plugin/wp-simple-events.php',
	);
	assert.equal(
		pluginFileFromPath( '/project/.release/wp-simple-events' ),
		'wp-simple-events/wp-simple-events.php',
	);
} );

test( 'finds only an action for the expected mounted plugin file', () => {
	const body = `
		<a href="plugins.php?action=activate&#038;plugin=WordPress%20Event%20Plugin%2Fwp-simple-events.php">Activate source</a>
		<a href="plugins.php?action=activate&amp;plugin=other%2Fother.php">Activate other</a>
	`;
	const action = pluginActionUrl(
		body,
		'activate',
		'WordPress Event Plugin/wp-simple-events.php',
	);

	assert.equal(
		action?.searchParams.get( 'plugin' ),
		'WordPress Event Plugin/wp-simple-events.php',
	);
	assert.equal(
		pluginActionUrl( body, 'deactivate', 'WordPress Event Plugin/wp-simple-events.php' ),
		null,
	);
	assert.equal(
		pluginActionUrl( body, 'activate', 'missing/missing.php' ),
		null,
	);
} );
