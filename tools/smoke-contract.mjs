import { basename, resolve as resolvePath } from 'node:path';

/**
 * Return the WordPress plugin file mounted by wp-env for a local directory.
 *
 * @param {string} pluginPath Absolute or relative plugin directory.
 * @return {string} WordPress plugin basename.
 */
export function pluginFileFromPath( pluginPath ) {
	const directory = basename( resolvePath( pluginPath ) );

	if ( ! directory ) {
		throw new TypeError( 'A plugin directory is required.' );
	}

	return `${ directory }/mime-simple-events-calendar.php`;
}

/**
 * Find one allowlisted plugin action URL in the WordPress plugins table.
 *
 * @param {string} body       Plugins screen HTML.
 * @param {string} action     Expected plugin action.
 * @param {string} pluginFile Expected WordPress plugin basename.
 * @return {URL|null} Matching URL or null.
 */
export function pluginActionUrl( body, action, pluginFile ) {
	for ( const match of body.matchAll( /href="([^"]+)"/g ) ) {
		const candidate = match[ 1 ]
			.replaceAll( '&#038;', '&' )
			.replaceAll( '&amp;', '&' );
		const url = new URL(
			candidate,
			'http://localhost:8888/wp-admin/plugins.php',
		);

		if (
			url.searchParams.get( 'action' ) === action &&
			url.searchParams.get( 'plugin' ) === pluginFile
		) {
			return url;
		}
	}

	return null;
}

/**
 * Find the nonce-protected activation URL for one allowlisted theme.
 *
 * @param {string} body       Themes screen HTML.
 * @param {string} stylesheet Expected theme stylesheet slug.
 * @return {URL|null} Matching activation URL or null.
 */
export function themeActivationUrl( body, stylesheet ) {
	for ( const match of body.matchAll( /href="([^"]+)"/g ) ) {
		const candidate = match[ 1 ]
			.replaceAll( '&#038;', '&' )
			.replaceAll( '&amp;', '&' );
		const url = new URL(
			candidate,
			'http://localhost:8888/wp-admin/themes.php',
		);

		if (
			url.searchParams.get( 'action' ) === 'activate' &&
			url.searchParams.get( 'stylesheet' ) === stylesheet
		) {
			return url;
		}
	}

	return null;
}

/**
 * Determine whether the allowlisted stylesheet is already active.
 *
 * @param {string} body       Themes screen HTML.
 * @param {string} stylesheet Expected theme stylesheet slug.
 * @return {boolean} Whether that exact theme card is active.
 */
export function themeIsActive( body, stylesheet ) {
	const bodyClasses = body.match( /<body\b[^>]*\bclass="([^"]+)"/ )?.[ 1 ]?.split( /\s+/ ) ?? [];

	if ( bodyClasses.includes( `wp-theme-${ stylesheet }` ) ) {
		return true;
	}

	for ( const match of body.matchAll( /<div\b[^>]*>/g ) ) {
		const tag = match[ 0 ];
		const slug = tag.match( /\bdata-slug="([^"]+)"/ )?.[ 1 ];
		const classes = tag.match( /\bclass="([^"]+)"/ )?.[ 1 ]?.split( /\s+/ ) ?? [];

		if ( slug === stylesheet && classes.includes( 'theme' ) && classes.includes( 'active' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Recognize the one transient fetch failure the isolated Playground may emit.
 *
 * @param {unknown} error Candidate request error.
 * @return {boolean} Whether one read-only retry is safe.
 */
export function isTransientFetchTimeout( error ) {
	return error instanceof Error && error.name === 'TimeoutError';
}
