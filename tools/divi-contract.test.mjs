import { strict as assert } from 'node:assert';
import { readFile, readdir } from 'node:fs/promises';
import { test } from 'node:test';

import {
	atomicModuleDefinitions,
	compositeModuleDefinitions,
} from './divi-module-definitions.mjs';

const metadata = JSON.parse(
	await readFile(
		new URL( '../divi/modules/event-title/module.json', import.meta.url ),
		'utf8',
	),
);
const editorSource = await readFile(
	new URL( '../assets/src/js/divi-editor.js', import.meta.url ),
	'utf8',
);

test( 'keeps the native Divi Event Title metadata contract intact', () => {
	assert.equal(
		metadata.name,
		'mime-simple-events-calendar/event-title',
	);
	assert.equal( metadata.title, 'Event Title' );
	assert.equal(
		metadata.attributes.event.settings.innerContent.items.eventId.component
			.name,
		'divi/select',
	);
	assert.equal(
		metadata.attributes.event.settings.innerContent.items.linkTitle.component
			.name,
		'divi/toggle',
	);
	assert.equal( metadata.attributes.title.elementType, 'heading' );
	assert.equal( metadata.attributes.title.tagName, 'h2' );
	assert.equal(
		metadata.attributes.title.settings.decoration.font.item.component.props
			.fields.headingLevel.render,
		true,
	);
} );

test( 'keeps atomic choices local and composite previews on the Divi REST client', () => {
	assert.match( editorSource, /window\.WpseDiviModulesData/ );
	assert.match( editorSource, /component\.props\.options\s*=\s*eventOptions/ );
	assert.match( editorSource, /window\.divi\?\.rest\?\.useFetch/ );
	assert.match( editorSource, /restRoute: '\/wpse\/v1\/divi-preview'/ );
	assert.match( editorSource, /initializeEventFilters\( previewRef\.current \)/ );
	assert.match( editorSource, /initializeCalendars\( previewRef\.current \)/ );
	assert.doesNotMatch( editorSource, /window\.fetch/ );
	assert.doesNotMatch( editorSource, /structuredClone/ );
} );

test( 'exposes one idempotent calendar initializer for dynamic builder previews', async () => {
	const calendarSource = await readFile(
		new URL( '../assets/src/js/calendar.js', import.meta.url ),
		'utf8',
	);

	assert.match(
		calendarSource,
		/window\.wpseInitializeCalendars\s*=\s*initializeCalendars/,
	);
} );

test( 'keeps every native Divi module generated and namespaced', async () => {
	const moduleRoot = new URL( '../divi/modules/', import.meta.url );
	const folders = ( await readdir( moduleRoot ) ).sort();
	const expectedFolders = [
		'event-title',
		...atomicModuleDefinitions.map( ( definition ) => definition.slug ),
		...compositeModuleDefinitions.map( ( definition ) => definition.slug ),
	].sort();

	assert.deepEqual( folders, expectedFolders );

	for ( const folder of folders ) {
		const moduleMetadata = JSON.parse(
			await readFile( new URL( `${ folder }/module.json`, moduleRoot ), 'utf8' ),
		);

		assert.equal(
			moduleMetadata.name,
			`mime-simple-events-calendar/${ folder }`,
		);
		assert.equal( moduleMetadata.category, 'module' );
		const eventId =
			moduleMetadata.attributes.event.settings.innerContent.items.eventId;

		if ( eventId ) {
			assert.equal( eventId.component.name, 'divi/select' );
		}
	}
} );

test( 'keeps every module on the current Divi wrapper and preset contract', async () => {
	const moduleRoot = new URL( '../divi/modules/', import.meta.url );
	const folders = ( await readdir( moduleRoot ) ).sort();
	const requiredDecoration = [
		'animation',
		'attributes',
		'background',
		'border',
		'boxShadow',
		'conditions',
		'disabledOn',
		'filters',
		'interactions',
		'layout',
		'order',
		'overflow',
		'position',
		'scroll',
		'sizing',
		'spacing',
		'sticky',
		'transform',
		'transition',
		'zIndex',
	].sort();

	for ( const folder of folders ) {
		const moduleMetadata = JSON.parse(
			await readFile( new URL( `${ folder }/module.json`, moduleRoot ), 'utf8' ),
		);
		const moduleSettings = moduleMetadata.attributes.module.settings;

		assert.deepEqual(
			Object.keys( moduleSettings.decoration ).sort(),
			requiredDecoration,
			`${ folder } must expose the complete Divi 5 wrapper decoration contract`,
		);
		assert.deepEqual(
			Object.keys( moduleSettings.advanced ).sort(),
			[ 'elements', 'html', 'htmlAttributes', 'link', 'loop', 'text' ],
			`${ folder } must preserve legacy HTML attributes while exposing current Divi advanced groups`,
		);
		assert.ok( moduleSettings.meta.adminLabel );
		assert.ok( moduleSettings.meta.meta );
		assert.equal( moduleMetadata.settings.content, 'auto' );
		assert.equal( moduleMetadata.settings.design, 'auto' );
		assert.equal( moduleMetadata.settings.advanced, 'auto' );

		for ( const group of Object.values( moduleMetadata.settings.groups ?? {} ) ) {
			if ( group.component?.props?.clipboardCategory === 'style' ) {
				assert.equal(
					typeof group.component.props.presetGroup,
					'string',
					`${ folder } style groups must participate in Divi presets`,
				);
			}
		}
	}
} );

test( 'keeps composite query controls bounded and taxonomy-aware', async () => {
	const moduleRoot = new URL( '../divi/modules/', import.meta.url );
	const listMetadata = JSON.parse(
		await readFile( new URL( 'event-list/module.json', moduleRoot ), 'utf8' ),
	);
	const calendarMetadata = JSON.parse(
		await readFile( new URL( 'event-calendar/module.json', moduleRoot ), 'utf8' ),
	);

	assert.equal(
		listMetadata.attributes.event.settings.innerContent.items.categories.component
			.name,
		'divi/checkboxes',
	);
	assert.equal(
		calendarMetadata.attributes.event.settings.innerContent.items.initialDate
			.component.name,
		'divi/text',
	);
	assert.equal(
		calendarMetadata.attributes.event.default.innerContent.desktop.value
			.showNavigation,
		'on',
	);
} );
