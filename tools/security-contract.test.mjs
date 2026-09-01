import { strict as assert } from 'node:assert';
import { readFile, readdir } from 'node:fs/promises';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const repositoryRoot = fileURLToPath( new URL( '..', import.meta.url ) );

async function filesUnder( relativeDirectory, extension ) {
	const root = path.join( repositoryRoot, relativeDirectory );
	const files = [];

	async function visit( directory ) {
		for ( const entry of await readdir( directory, { withFileTypes: true } ) ) {
			const absolute = path.join( directory, entry.name );

			if ( entry.isDirectory() ) {
				await visit( absolute );
			} else if ( absolute.endsWith( extension ) ) {
				files.push( absolute );
			}
		}
	}

	await visit( root );

	return files.sort();
}

async function sourceMap( relativeDirectory, extension ) {
	const sources = new Map();

	for ( const file of await filesUnder( relativeDirectory, extension ) ) {
		const relative = path.relative( repositoryRoot, file );
		sources.set( relative, await readFile( file, 'utf8' ) );
	}

	return sources;
}

const phpSources = await sourceMap( 'src', '.php' );
const runtimePhpSources = new Map( phpSources );

for ( const file of [ 'mime-simple-events-calendar.php', 'uninstall.php' ] ) {
	runtimePhpSources.set(
		file,
		await readFile( path.join( repositoryRoot, file ), 'utf8' ),
	);
}

const authoredJavaScript = await sourceMap( 'assets/src/js', '.js' );
const publicReadme = await readFile(
	new URL( '../readme.txt', import.meta.url ),
	'utf8',
);

test( 'keeps every custom REST route behind an explicit permission callback', () => {
	const routeSources = [ ...phpSources.entries() ].filter( ( [ , source ] ) =>
		source.includes( 'register_rest_route(' ),
	);

	assert.deepEqual(
		routeSources.map( ( [ file ] ) => file ),
		[
			'src/Divi/DiviPreviewController.php',
			'src/Rest/CalendarFeedController.php',
			'src/Rest/OccurrenceRestController.php',
			'src/Rest/RecurrenceEditorController.php',
		],
	);

	for ( const [ file, source ] of routeSources ) {
		const routes = [ ...source.matchAll( /register_rest_route\s*\(/g ) ].length;
		const callbacks = [ ...source.matchAll( /'permission_callback'\s*=>/g ) ]
			.length;

		assert.equal(
			callbacks,
			routes,
			`${ file } must declare one permission callback per route.`,
		);
	}

	const publicRoutes = routeSources
		.filter( ( [ , source ] ) => source.includes( "'__return_true'" ) )
		.map( ( [ file ] ) => file );

	assert.deepEqual( publicRoutes, [
		'src/Rest/CalendarFeedController.php',
		'src/Rest/OccurrenceRestController.php',
	] );
} );

test( 'confines direct database access to reviewed occurrence adapters', () => {
	const directDatabaseFiles = [ ...runtimePhpSources.entries() ]
		.filter( ( [ , source ] ) => source.includes( '$wpdb' ) )
		.map( ( [ file ] ) => file );

	assert.deepEqual( directDatabaseFiles, [
		'src/Occurrence/OccurrenceReadRepository.php',
		'src/Occurrence/OccurrenceTable.php',
		'src/Occurrence/WordPressOccurrenceGenerationCleaner.php',
		'src/Occurrence/WordPressOccurrenceProjectionStore.php',
		'src/Occurrence/WordPressOccurrenceReadGateway.php',
	] );
} );

test( 'keeps prohibited runtime capabilities out of authored PHP', () => {
	const prohibited = [
		/\beval\s*\(/,
		/\bexec\s*\(/,
		/\bshell_exec\s*\(/,
		/\bsystem\s*\(/,
		/\bpassthru\s*\(/,
		/\bunserialize\s*\(/,
		/\bmaybe_unserialize\s*\(/,
		/\bsetcookie\s*\(/,
		/\berror_log\s*\(/,
		/\bwp_remote_(?:get|post|request|head)\s*\(/,
		/\bwp_ajax_nopriv_\b/,
	];

	for ( const [ file, source ] of runtimePhpSources ) {
		for ( const pattern of prohibited ) {
			assert.doesNotMatch( source, pattern, `${ file } matches ${ pattern }` );
		}
	}
} );

test( 'keeps authored browser code free of tracking and visitor storage', () => {
	const prohibited = [
		/document\.cookie/,
		/\blocalStorage\b/,
		/\bsessionStorage\b/,
		/\bsendBeacon\s*\(/,
	];

	for ( const [ file, source ] of authoredJavaScript ) {
		for ( const pattern of prohibited ) {
			assert.doesNotMatch( source, pattern, `${ file } matches ${ pattern }` );
		}
	}
} );

test( 'keeps external-service boundaries explicit and source available', () => {
	const remoteUrlFiles = [ ...phpSources.entries() ]
		.filter( ( [ , source ] ) => /https?:\/\//.test( source ) )
		.map( ( [ file ] ) => file );

	assert.deepEqual( remoteUrlFiles, [
		'src/CalendarExport/CalendarProviderUrlBuilder.php',
		'src/Seo/EventSchemaBuilder.php',
	] );
	assert.match( publicReadme, /does not create visitor cookies/i );
	assert.match( publicReadme, /collect analytics or telemetry/i );
	assert.match(
		publicReadme,
		/complete source, build instructions and security policy are available at https:\/\/github\.com\/MilanMiMeOnline\/WP-Simple-Events/,
	);
} );
