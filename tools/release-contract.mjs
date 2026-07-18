import { posix } from 'node:path';

export const PLUGIN_SLUG = 'wp-simple-events';

const REQUIRED_RELEASE_PATHS = [
	'wp-simple-events.php',
	'readme.txt',
	'vendor/autoload.php',
	'languages/wp-simple-events.pot',
	'src/',
	'templates/',
	'assets/src/css/',
	'assets/dist/js/calendar.min.js',
];

const FORBIDDEN_PATH_PARTS = new Set( [
	'.cache',
	'.git',
	'.github',
	'.idea',
	'.vscode',
	'coverage',
	'docs',
	'node_modules',
	'tests',
	'tools',
] );

const FORBIDDEN_FILES = new Set( [
	'.distignore',
	'.editorconfig',
	'.gitattributes',
	'.gitignore',
	'.stylelintrc.json',
	'.wp-env.json',
	'AGENTS.md',
	'ANALYSE-EN-BOUWSPECIFICATIE.md',
	'CONTRIBUTING.md',
	'composer.json',
	'composer.lock',
	'eslint.config.mjs',
	'package-lock.json',
	'package.json',
	'phpcs.xml.dist',
	'phpstan.neon.dist',
	'phpunit.xml.dist',
] );

const FORBIDDEN_SOURCE_FILES = new Set( [
	'assets/src/js/calendar.js',
	'assets/src/js/index.js',
] );

const ALLOWED_ROOT_FILES = new Set( [
	'CHANGELOG.md',
	'README.md',
	'SECURITY.md',
	'THIRD-PARTY-NOTICES.md',
	'readme.txt',
	'uninstall.php',
	'wp-simple-events.php',
] );

const ALLOWED_PATH_PREFIXES = [
	'assets/dist/js/',
	'assets/src/css/',
	'languages/',
	'src/',
	'templates/',
	'vendor/composer/',
];

const ALLOWED_NESTED_FILES = new Set( [
	'assets/src/js/admin-event.js',
	'vendor/autoload.php',
] );

function extractMatch( source, expression, label ) {
	const match = source.match( expression );

	if ( ! match ) {
		throw new Error( `Could not find ${ label }.` );
	}

	return match[ 1 ];
}

export function getReleaseVersion( {
	packageSource,
	pluginSource,
	readmeSource,
} ) {
	let packageVersion;

	try {
		packageVersion = JSON.parse( packageSource ).version;
	} catch ( error ) {
		throw new Error( 'Could not parse package.json.', { cause: error } );
	}

	const versions = {
		constant: extractMatch(
			pluginSource,
			/define\(\s*'WPSE_VERSION'\s*,\s*'([^']+)'\s*\)/,
			'WPSE_VERSION',
		),
		package: packageVersion,
		plugin: extractMatch(
			pluginSource,
			/^\s*\*\s*Version:\s*([^\s]+)\s*$/m,
			'plugin header version',
		),
		readme: extractMatch(
			readmeSource,
			/^Stable tag:\s*([^\s]+)\s*$/m,
			'readme stable tag',
		),
	};
	const uniqueVersions = new Set( Object.values( versions ) );

	if (
		uniqueVersions.size !== 1 ||
		typeof packageVersion !== 'string' ||
		! /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/.test( packageVersion )
	) {
		throw new Error(
			`Inconsistent release versions: ${ JSON.stringify( versions ) }`,
		);
	}

	return packageVersion;
}

export function parseChecksumRecord( source, archiveName ) {
	const match = source.match( /^([a-f0-9]{64})  ([^\r\n]+)\n$/ );

	if ( ! match || match[ 2 ] !== archiveName ) {
		throw new Error( 'The release checksum file has an invalid format.' );
	}

	return match[ 1 ];
}

function invalidArchive( message ) {
	throw new Error( `Invalid release archive: ${ message }` );
}

function hasAllowedFileType( relative ) {
	if ( relative.startsWith( 'src/' ) || relative.startsWith( 'templates/' ) ) {
		return relative.endsWith( '.php' );
	}

	if ( relative.startsWith( 'assets/src/css/' ) ) {
		return relative.endsWith( '.css' );
	}

	if ( relative.startsWith( 'assets/dist/js/' ) ) {
		return relative.endsWith( '.js' );
	}

	if ( relative.startsWith( 'languages/' ) ) {
		return /\.(?:json|mo|po|pot)$/.test( relative );
	}

	if ( relative.startsWith( 'vendor/composer/' ) ) {
		return relative.endsWith( '.php' ) || relative === 'vendor/composer/LICENSE';
	}

	return true;
}

export function assertReleaseEntries( entries ) {
	if ( entries.length === 0 ) {
		invalidArchive( 'archive is empty.' );
	}

	const root = `${ PLUGIN_SLUG }/`;
	const relativeEntries = [];
	const seenEntries = new Set();

	for ( const entry of entries ) {
		if ( seenEntries.has( entry ) ) {
			invalidArchive( `duplicate path ${ entry }.` );
		}

		seenEntries.add( entry );

		if ( entry.includes( '\\' ) || posix.isAbsolute( entry ) ) {
			invalidArchive( `unsafe path ${ entry }.` );
		}

		const normalized = posix.normalize( entry );

		if (
			normalized !== entry ||
			entry.includes( '../' ) ||
			! entry.startsWith( root )
		) {
			invalidArchive( `unsafe or unexpected root for ${ entry }.` );
		}

		const relative = entry.slice( root.length );
		const parts = relative.split( '/' ).filter( Boolean );
		const isAllowed =
			ALLOWED_ROOT_FILES.has( relative ) ||
			ALLOWED_NESTED_FILES.has( relative ) ||
			ALLOWED_PATH_PREFIXES.some( ( prefix ) =>
				relative.startsWith( prefix ),
			);

		if ( relative === '' || entry.endsWith( '/' ) || ! isAllowed ) {
			invalidArchive( `unexpected file ${ entry }.` );
		}

		if ( parts.some( ( part ) => part.startsWith( '.' ) ) ) {
			invalidArchive( `hidden path ${ entry }.` );
		}

		if ( ! hasAllowedFileType( relative ) ) {
			invalidArchive( `unexpected file type ${ entry }.` );
		}

		if ( parts.some( ( part ) => FORBIDDEN_PATH_PARTS.has( part ) ) ) {
			invalidArchive( `development path ${ entry }.` );
		}

		if (
			FORBIDDEN_FILES.has( relative ) ||
			FORBIDDEN_SOURCE_FILES.has( relative ) ||
			parts.includes( '.DS_Store' )
		) {
			invalidArchive( `development file ${ entry }.` );
		}

		relativeEntries.push( relative );
	}

	for ( const requiredPath of REQUIRED_RELEASE_PATHS ) {
		const exists = requiredPath.endsWith( '/' )
			? relativeEntries.some( ( entry ) => entry.startsWith( requiredPath ) )
			: relativeEntries.includes( requiredPath );

		if ( ! exists ) {
			throw new Error( `Missing required release path: ${ requiredPath }` );
		}
	}
}
