import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

import { build } from 'esbuild';

import {
	atomicModuleDefinitions,
	buildAtomicModuleMetadata,
	buildCompositeModuleMetadata,
	compositeModuleDefinitions,
} from './divi-module-definitions.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const metadataPath = fileURLToPath(
	new URL( '../divi/modules/event-title/module.json', import.meta.url ),
);
const metadata = JSON.parse( await readFile( metadataPath, 'utf8' ) );
const moduleMetadata = {
	'event-title': metadata,
};
const moduleFields = {};
const compositeModules = {};

for ( const definition of atomicModuleDefinitions ) {
	const generatedMetadata = buildAtomicModuleMetadata( definition );
	const moduleDirectory = `${ projectDirectory }/divi/modules/${ definition.slug }`;

	await mkdir( moduleDirectory, { recursive: true } );
	await writeFile(
		`${ moduleDirectory }/module.json`,
		`${ JSON.stringify( generatedMetadata, null, 2 ) }\n`,
		'utf8',
	);
	moduleMetadata[ definition.slug ] = generatedMetadata;
	moduleFields[ definition.slug ] = definition.field;
}

for ( const definition of compositeModuleDefinitions ) {
	const generatedMetadata = buildCompositeModuleMetadata( definition );
	const moduleDirectory = `${ projectDirectory }/divi/modules/${ definition.slug }`;

	await mkdir( moduleDirectory, { recursive: true } );
	await writeFile(
		`${ moduleDirectory }/module.json`,
		`${ JSON.stringify( generatedMetadata, null, 2 ) }\n`,
		'utf8',
	);
	moduleMetadata[ definition.slug ] = generatedMetadata;
	compositeModules[ definition.slug ] = definition.component;
}

await build( {
	entryPoints: [ `${ projectDirectory }/assets/src/js/divi-editor.js` ],
	bundle: true,
	define: {
		WPSE_DIVI_EVENT_TITLE_METADATA: JSON.stringify( metadata ),
		WPSE_DIVI_MODULE_METADATA: JSON.stringify( moduleMetadata ),
		WPSE_DIVI_MODULE_FIELDS: JSON.stringify( moduleFields ),
		WPSE_DIVI_COMPOSITE_MODULES: JSON.stringify( compositeModules ),
	},
	legalComments: 'external',
	minify: true,
	outfile: `${ projectDirectory }/assets/dist/js/divi-editor.min.js`,
	target: 'es2022',
} );
