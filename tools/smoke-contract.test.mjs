import { strict as assert } from 'node:assert';
import { test } from 'node:test';

import {
	pluginActionUrl,
	pluginFileFromPath,
} from './smoke-contract.mjs';

test( 'derives the mounted plugin file from source and release directories', () => {
	assert.equal(
		pluginFileFromPath( '/project/WordPress Event Plugin' ),
		'WordPress Event Plugin/simple-events-by-mime.php',
	);
	assert.equal(
		pluginFileFromPath( '/project/.release/simple-events-by-mime' ),
		'simple-events-by-mime/simple-events-by-mime.php',
	);
} );

test( 'finds only an action for the expected mounted plugin file', () => {
	const body = `
		<a href="plugins.php?action=activate&#038;plugin=WordPress%20Event%20Plugin%2Fsimple-events-by-mime.php">Activate source</a>
		<a href="plugins.php?action=activate&amp;plugin=other%2Fother.php">Activate other</a>
	`;
	const action = pluginActionUrl(
		body,
		'activate',
		'WordPress Event Plugin/simple-events-by-mime.php',
	);

	assert.equal(
		action?.searchParams.get( 'plugin' ),
		'WordPress Event Plugin/simple-events-by-mime.php',
	);
	assert.equal(
		pluginActionUrl( body, 'deactivate', 'WordPress Event Plugin/simple-events-by-mime.php' ),
		null,
	);
	assert.equal(
		pluginActionUrl( body, 'activate', 'missing/missing.php' ),
		null,
	);
} );
