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

	return `${ directory }/simple-events-by-mime.php`;
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
