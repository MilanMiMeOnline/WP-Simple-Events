import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const phpstanConfig = await readFile(
	new URL( '../phpstan.neon.dist', import.meta.url ),
	'utf8',
);

test( 'keeps non-PHP dependency trees optional for PHP-only CI jobs', () => {
	assert.match( phpstanConfig, /^\s*- node_modules \(\?\)$/m );
} );
