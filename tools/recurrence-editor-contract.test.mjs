import { strict as assert } from 'node:assert';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

test( 'uses the current WordPress editor slot-fill package', async () => {
	const editorSource = await readFile(
		new URL( '../assets/src/js/recurrence-editor.js', import.meta.url ),
		'utf8',
	);
	const assetSource = await readFile(
		new URL( '../src/Admin/RecurrenceEditorAssets.php', import.meta.url ),
		'utf8',
	);

	assert.match( editorSource, /wp\.editor/ );
	assert.doesNotMatch( editorSource, /wp\.editPost/ );
	assert.match( assetSource, /'wp-editor'/ );
	assert.doesNotMatch( assetSource, /'wp-edit-post'/ );
} );
