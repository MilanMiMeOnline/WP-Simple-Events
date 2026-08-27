import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { join, resolve as resolvePath } from 'node:path';

import {
	pluginActionUrl,
	pluginFileFromPath,
	themeActivationUrl,
} from './smoke-contract.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const requestedCore = process.env.WPSE_SMOKE_CORE;
const requestedPhp = process.env.WPSE_SMOKE_PHP;
const keepFailedEnvironment = process.env.WPSE_SMOKE_KEEP_ENV === '1';
const smokeIdentifier = `${ requestedCore ?? 'configured' }-${
	requestedPhp ?? 'configured'
}`.replace(
	/[^a-z0-9.-]+/gi,
	'-',
);
const smokeWpEnvHome = join(
	tmpdir(),
	`mime-simple-events-calendar-smoke-wp-env-${ smokeIdentifier }`,
);
const smokeConfigDirectory = join(
	tmpdir(),
	`mime-simple-events-calendar-smoke-config-${ smokeIdentifier }`,
);
const smokePluginPath = resolvePath(
	projectDirectory,
	process.env.WPSE_SMOKE_PLUGIN_PATH ?? '.',
);
const smokePluginFile = pluginFileFromPath( smokePluginPath );
const smokeThemePaths = [
	'wpse-classic-shell',
	'wpse-hybrid-shell',
	'wpse-hybrid-override',
	'wpse-block-shell',
	'wpse-block-override',
].map( ( theme ) =>
	resolvePath( projectDirectory, 'tests', 'Fixtures', 'themes', theme ),
);
const wpEnvExecutable = fileURLToPath(
	new URL( '../node_modules/.bin/wp-env', import.meta.url ),
);

async function prepareSmokeConfiguration() {
	const configuration = JSON.parse(
		await readFile( join( projectDirectory, '.wp-env.json' ), 'utf8' ),
	);

	configuration.plugins = [ smokePluginPath ];
	configuration.themes = smokeThemePaths;
	configuration.config = {
		...configuration.config,
	};

	if ( requestedPhp ) {
		configuration.phpVersion = requestedPhp;
	}

	await rm( smokeConfigDirectory, { force: true, recursive: true } );
	await mkdir( smokeConfigDirectory, { recursive: true } );
	await writeFile(
		join( smokeConfigDirectory, '.wp-env.json' ),
		`${ JSON.stringify( configuration, null, 2 ) }\n`,
		'utf8',
	);
}

function runWpEnv(
	argumentsList,
	{ allowFailure = false, silent = false } = {},
) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( wpEnvExecutable, argumentsList, {
			cwd: smokeConfigDirectory,
			env: {
				...process.env,
				WP_ENV_HOME: smokeWpEnvHome,
				...( requestedCore ? { WP_ENV_CORE: requestedCore } : {} ),
			},
			stdio: silent ? 'ignore' : 'inherit',
		} );

		child.once( 'error', reject );
		child.once( 'exit', ( code ) => {
			if ( code === 0 || allowFailure ) {
				resolve();
				return;
			}

			reject( new Error( `wp-env exited with code ${ code }.` ) );
		} );
	} );
}

async function requestPage( url, options = {} ) {
	let response = await fetch( url, { ...options, redirect: 'manual' } );
	let redirectTarget = response.headers.get( 'location' );

	if ( redirectTarget ) {
		const cookies = response.headers
			.getSetCookie()
			.map( ( cookie ) => cookie.split( ';', 1 )[ 0 ] )
			.join( '; ' );

		response = await fetch( new URL( redirectTarget, url ), {
			...options,
			headers: { ...options.headers, cookie: cookies },
			redirect: 'manual',
		} );
		redirectTarget = response.headers.get( 'location' );
	}

	const body = await response.text();

	return { body, redirectTarget, response };
}

async function fetchHealthyPage( url, options = {} ) {
	const { body, redirectTarget, response } = await requestPage( url, options );

	if ( redirectTarget ) {
		throw new Error( `${ url } redirected to ${ redirectTarget }.` );
	}

	if ( ! response.ok ) {
		throw new Error( `${ url } returned HTTP ${ response.status }.` );
	}

	if ( body.includes( 'There has been a critical error' ) ) {
		throw new Error( `${ url } contains a WordPress critical error.` );
	}

	return body;
}

async function waitForHealthyPage( url, options = {} ) {
	let lastError;

	for ( let attempt = 0; attempt < 20; attempt += 1 ) {
		try {
			return await fetchHealthyPage( url, options );
		} catch ( error ) {
			lastError = error;
		}

		if ( attempt < 19 ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
		}
	}

	throw lastError;
}

async function fetchHealthyJson( url, options = {} ) {
	const body = await fetchHealthyPage( url, options );

	try {
		return JSON.parse( body );
	} catch {
		throw new Error( `${ url } did not return valid JSON.` );
	}
}

async function requestJson( url, options = {} ) {
	let response = await fetch( url, { ...options, redirect: 'manual' } );
	const redirectTarget = response.headers.get( 'location' );

	if ( redirectTarget ) {
		const cookies = response.headers
			.getSetCookie()
			.map( ( cookie ) => cookie.split( ';', 1 )[ 0 ] )
			.join( '; ' );

		response = await fetch( new URL( redirectTarget, url ), {
			...options,
			headers: { ...options.headers, cookie: cookies },
			redirect: 'manual',
		} );
	}

	if ( response.headers.get( 'location' ) ) {
		throw new Error( `${ url } returned an unexpected redirect.` );
	}

	let data = null;

	try {
		data = JSON.parse( await response.text() );
	} catch {
		throw new Error( `${ url } did not return valid JSON.` );
	}

	return { response, data };
}

function requireCondition( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

function requireExactlyOnce( body, marker, message ) {
	requireCondition( body.split( marker ).length - 1 === 1, message );
}

async function activateSmokeTheme( session, theme ) {
	const themesUrl = 'http://localhost:8888/wp-admin/themes.php';
	const body = await fetchHealthyPage( themesUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	const activationUrl = themeActivationUrl( body, theme );

	requireCondition(
		activationUrl,
		`The smoke theme ${ theme } is unavailable for activation.`,
	);

	const response = await fetch( activationUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
		redirect: 'manual',
	} );

	requireCondition(
		response.status === 302,
		`The protected activation for ${ theme } did not redirect.`,
	);
}

async function assertEventThemeShell(
	{
		theme,
		headerMarker,
		footerMarker,
		singleOverrideMarker = '',
		archiveOverrideMarker = '',
		taxonomyOverrideMarker = '',
	},
	session,
	singleUrl,
) {
	await activateSmokeTheme( session, theme );

	const archivePage = await fetchHealthyPage(
		'http://localhost:8888/events/',
	);
	const taxonomyPage = await fetchHealthyPage(
		'http://localhost:8888/event-category/archive-smoke/',
	);
	const singlePage = await fetchHealthyPage( singleUrl );

	for ( const [ pageName, body ] of [
		[ 'archive', archivePage ],
		[ 'taxonomy archive', taxonomyPage ],
		[ 'single', singlePage ],
	] ) {
		requireExactlyOnce(
			body,
			headerMarker,
			`${ theme } rendered an invalid ${ pageName } header shell.`,
		);
		requireExactlyOnce(
			body,
			footerMarker,
			`${ theme } rendered an invalid ${ pageName } footer shell.`,
		);
	}

	requireCondition(
		archivePage.includes( 'wpse-event-archive' ) &&
			archivePage.includes( 'Future smoke event' ),
		`${ theme } lost the native event archive inside its theme shell.`,
	);
	requireCondition(
		taxonomyPage.includes( 'wpse-event-archive' ) &&
			taxonomyPage.includes( 'Future smoke event' ) &&
			taxonomyPage.includes( 'Events in “Archive smoke category”' ) &&
			! taxonomyPage.includes( '&lt;span&gt;' ),
		`${ theme } lost the event taxonomy archive inside its theme shell.`,
	);
	requireExactlyOnce(
		singlePage,
		'class="wpse-single-event"',
		`${ theme } did not render exactly one native single event.`,
	);
	requireCondition(
		singlePage.includes( 'Future smoke event' ),
		`${ theme } lost the event title inside its theme shell.`,
	);

	if ( singleOverrideMarker ) {
		requireExactlyOnce(
			singlePage,
			singleOverrideMarker,
			`${ theme } did not honor its single-event override.`,
		);
	}

	if ( archiveOverrideMarker ) {
		requireExactlyOnce(
			archivePage,
			archiveOverrideMarker,
			`${ theme } did not honor its event-archive override.`,
		);
	}

	if ( taxonomyOverrideMarker ) {
		requireExactlyOnce(
			taxonomyPage,
			taxonomyOverrideMarker,
			`${ theme } did not honor its event-taxonomy archive override.`,
		);
	}
}

function adminPostNonce( body, action ) {
	const actionMarker = `value="${ action }"`;
	const actionOffset = body.indexOf( actionMarker );

	if ( actionOffset < 0 ) {
		return null;
	}

	const formStart = body.lastIndexOf( '<form', actionOffset );
	const formEnd = body.indexOf( '</form>', actionOffset );

	if ( formStart < 0 || formEnd < 0 ) {
		return null;
	}

	return body
		.slice( formStart, formEnd )
		.match( /name="_wpnonce" value="([^"]+)"/ )?.[ 1 ] ?? null;
}

function storeResponseCookies( cookieJar, response ) {
	response.headers.getSetCookie().forEach( ( cookie ) => {
		const [ nameValue ] = cookie.split( ';', 1 );
		const separator = nameValue.indexOf( '=' );

		if ( separator > 0 ) {
			cookieJar.set(
				nameValue.slice( 0, separator ),
				nameValue.slice( separator + 1 ),
			);
		}
	} );
}

function cookieHeader( cookieJar ) {
	return [ ...cookieJar.entries() ]
		.map( ( [ name, value ] ) => `${ name }=${ value }` )
		.join( '; ' );
}

async function editorRestNonce( cookieJar ) {
	const response = await fetch(
		'http://localhost:8888/wp-admin/post-new.php?post_type=post',
		{
			headers: { cookie: cookieHeader( cookieJar ) },
			redirect: 'manual',
		},
	);

	if ( ! response.ok ) {
		return '';
	}

	const body = await response.text();
	const settings = body.match( /var wpApiSettings = (\{[^;]+\});/ );

	if ( ! settings ) {
		return '';
	}

	try {
		const nonce = JSON.parse( settings[ 1 ] ).nonce;

		return typeof nonce === 'string' ? nonce : '';
	} catch {
		return '';
	}
}

async function authenticateAdministrator( loginAttempt = 0 ) {
	const cookieJar = new Map();
	const loginUrl = 'http://localhost:8888/wp-login.php';
	let lastNonceBody = '';
	let lastNonceStatus = 0;
	let response = await fetch( loginUrl, { redirect: 'manual' } );

	storeResponseCookies( cookieJar, response );

	const form = new URLSearchParams( {
		log: 'admin',
		pwd: 'password',
		'wp-submit': 'Log In',
		redirect_to: 'http://localhost:8888/wp-admin/',
		testcookie: '1',
	} );

	response = await fetch( loginUrl, {
		method: 'POST',
		headers: {
			'content-type': 'application/x-www-form-urlencoded',
			cookie: cookieHeader( cookieJar ),
		},
		body: form,
		redirect: 'manual',
	} );
	storeResponseCookies( cookieJar, response );

	requireCondition(
		[ ...cookieJar.keys() ].some( ( name ) =>
			name.startsWith( 'wordpress_logged_in_' ),
		),
		'The smoke test could not authenticate the WordPress administrator.',
	);
	const editorNonce = await editorRestNonce( cookieJar );

	if ( editorNonce.length >= 10 ) {
		return { cookieJar, nonce: editorNonce };
	}

	for ( let attempt = 0; attempt < 10; attempt += 1 ) {
		const nonceResponse = await fetch(
			'http://localhost:8888/wp-admin/admin-ajax.php?action=rest-nonce',
			{ headers: { cookie: cookieHeader( cookieJar ) } },
		);
		const nonce = await nonceResponse.text();
		lastNonceBody = nonce;
		lastNonceStatus = nonceResponse.status;

		if ( nonceResponse.ok && nonce.length >= 10 ) {
			return { cookieJar, nonce };
		}

		if ( attempt < 9 ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
		}
	}

	if ( loginAttempt < 2 ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );

		return authenticateAdministrator( loginAttempt + 1 );
	}

	throw new Error(
		`The smoke test could not obtain a REST nonce (HTTP ${ lastNonceStatus }, body ${ JSON.stringify( lastNonceBody ) }).`,
	);
}

async function ensurePackagedPluginIsActive( session ) {
	const pluginsUrl = 'http://localhost:8888/wp-admin/plugins.php';
	let body = await fetchHealthyPage( pluginsUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );

	if ( pluginActionUrl( body, 'deactivate', smokePluginFile ) ) {
		return;
	}

	const activationUrl = pluginActionUrl( body, 'activate', smokePluginFile );

	requireCondition(
		activationUrl,
		'The configured plugin is neither active nor available for activation.',
	);

	const activationResponse = await fetch( activationUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
		redirect: 'manual',
	} );

	requireCondition(
		activationResponse.status === 302,
		'The protected WordPress plugin activation did not redirect.',
	);

	body = await fetchHealthyPage( pluginsUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	requireCondition(
		pluginActionUrl( body, 'deactivate', smokePluginFile ),
		'The configured plugin did not become active.',
	);
}

async function authenticatedRequest( session, path, options = {} ) {
	const response = await fetch( `http://localhost:8888${ path }`, {
		...options,
		headers: {
			...options.headers,
			cookie: cookieHeader( session.cookieJar ),
			'X-WP-Nonce': session.nonce,
		},
		redirect: 'manual',
	} );
	let data = null;

	try {
		data = JSON.parse( await response.text() );
	} catch {
		throw new Error( `${ path } did not return valid JSON.` );
	}

	return { response, data };
}

function localDate( dayOffset ) {
	const date = new Date( Date.now() + ( dayOffset * 86_400_000 ) );
	const parts = new Intl.DateTimeFormat( 'en-CA', {
		timeZone: 'Europe/Brussels',
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
	} ).formatToParts( date );
	const values = Object.fromEntries(
		parts.map( ( part ) => [ part.type, part.value ] ),
	);

	return `${ values.year }-${ values.month }-${ values.day }`;
}

function offsetIsoDate( value, dayOffset ) {
	const date = new Date( `${ value }T12:00:00Z` );

	date.setUTCDate( date.getUTCDate() + dayOffset );

	return date.toISOString().slice( 0, 10 );
}

async function createPublishedEvent(
	session,
	{
		title,
		startOffset,
		endOffset,
		password = '',
		content = '',
		venue = 'Town Hall',
		address = '',
		locationUrl = '',
		eventUrl = '',
		eventUrlLabel = '',
		eventStatus = 'scheduled',
	},
) {
	return authenticatedRequest( session, '/wp-json/wp/v2/wpse_event', {
		method: 'POST',
		headers: { 'content-type': 'application/json' },
		body: JSON.stringify( {
			title,
			content,
			status: 'publish',
			password,
			meta: {
				_wpse_start_local: `${ localDate( startOffset ) }T09:30`,
				_wpse_end_local: `${ localDate( endOffset ) }T11:00`,
				_wpse_all_day: false,
				_wpse_timezone: 'Europe/Brussels',
				_wpse_venue: venue,
				_wpse_address: address,
				_wpse_location_url: locationUrl,
				_wpse_event_url: eventUrl,
				_wpse_event_url_label: eventUrlLabel,
				_wpse_event_status: eventStatus,
			},
		} ),
	} );
}

const smokeStartedOn = localDate( 0 );

await prepareSmokeConfiguration();

try {
	// Recreate isolated smoke data so interrupted runs cannot leak fixtures or settings.
	await runWpEnv( [ 'stop' ], { allowFailure: true, silent: true } );
	await runWpEnv( [ 'destroy', '--force' ], {
		allowFailure: true,
		silent: true,
	} );
	await rm( smokeWpEnvHome, { force: true, recursive: true } );
	await runWpEnv( [ 'start', '--runtime=playground' ] );
	await waitForHealthyPage( 'http://localhost:8888/' );
	const session = await authenticateAdministrator();
	await ensurePackagedPluginIsActive( session );
	const siteTimezoneUpdate = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/settings',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { timezone: 'Europe/Brussels' } ),
		},
	);
	requireCondition(
		siteTimezoneUpdate.response.ok &&
			siteTimezoneUpdate.data.timezone === 'Europe/Brussels',
		'The smoke site could not establish its deterministic WordPress timezone.',
	);
	await fetchHealthyPage( 'http://localhost:8888/events/' );

	const restRoot = await fetchHealthyJson( 'http://localhost:8888/wp-json/' );
	const eventType = await fetchHealthyJson(
		'http://localhost:8888/wp-json/wp/v2/types/wpse_event',
	);
	const category = await fetchHealthyJson(
		'http://localhost:8888/wp-json/wp/v2/taxonomies/wpse_event_category',
	);
	const tag = await fetchHealthyJson(
		'http://localhost:8888/wp-json/wp/v2/taxonomies/wpse_event_tag',
	);
	const eventRoute = await fetchHealthyJson(
		'http://localhost:8888/wp-json/wp/v2/wpse_event',
		{ method: 'OPTIONS' },
	);
	const metaSchema = eventRoute.schema?.properties?.meta?.properties ?? {};

	requireCondition( restRoot.namespaces.includes( 'wp/v2' ), 'The WordPress REST API is unavailable.' );
	requireCondition( restRoot.namespaces.includes( 'wpse/v1' ), 'The calendar REST namespace is unavailable.' );
	requireCondition( eventType.slug === 'wpse_event', 'The event post type is not registered in REST.' );
	requireCondition( category.slug === 'wpse_event_category', 'The event category taxonomy is not registered.' );
	requireCondition( tag.slug === 'wpse_event_tag', 'The event tag taxonomy is not registered.' );
	requireCondition( '_wpse_start_local' in metaSchema, 'Editable event metadata is missing from REST.' );
	requireCondition( '_wpse_event_url_label' in metaSchema, 'The external event link label is missing from REST.' );
	requireCondition(
		metaSchema._wpse_event_url_label.maxLength === 120,
		'The external event link label REST schema is not bounded to 120 characters.',
	);
	requireCondition( ! ( '_wpse_start_utc' in metaSchema ), 'Internal UTC metadata leaked into core REST.' );
	requireCondition(
		! ( '_wpse_recurrence_definition' in metaSchema ),
		'The internal recurrence aggregate leaked into core REST.',
	);

	const editorResponse = await fetch(
		'http://localhost:8888/wp-admin/post-new.php?post_type=wpse_event',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	const editorBody = await editorResponse.text();

	requireCondition( editorResponse.ok, 'The event editor is unavailable.' );
	requireCondition( editorBody.includes( 'menu-posts-wpse_event' ), 'The Events admin menu is unavailable.' );
	requireCondition( editorBody.includes( 'wpse-event-details' ), 'The native event details panel is unavailable.' );
	requireCondition(
		editorBody.includes( 'id="wpse-event-url-label"' ) &&
			editorBody.includes( 'name="wpse_event[event_url_label]"' ) &&
			editorBody.includes( 'maxlength="120"' ),
		'The bounded external event link label control is unavailable.',
	);
	requireCondition(
		editorBody.includes( 'event-fields-editor.min.js' ) &&
			editorBody.includes( 'wpseEventFieldBlocks' ),
		'The shared atomic event-field block editor adapter is unavailable.',
	);
	requireCondition(
		editorBody.includes( 'recurrence-editor.min.js' ) &&
			editorBody.includes( 'wpseRecurrenceEditor' ) &&
			/"horizonDays":(?:540|"540")/.test( editorBody ) &&
			/"maxRows":(?:1000|"1000")/.test( editorBody ),
		'The bounded Gutenberg recurrence editor adapter is unavailable.',
	);
	const publicHomeBody = await fetchHealthyPage( 'http://localhost:8888/' );
	requireCondition(
		! publicHomeBody.includes( 'event-fields-editor.min.js' ),
		'The block editor adapter leaked onto a public page.',
	);
	requireCondition(
		! publicHomeBody.includes( 'recurrence-editor.min.js' ),
		'The recurrence editor adapter leaked onto a public page.',
	);
	const registeredBlocks = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/block-types?namespace=wpse&context=edit',
	);
	const atomicBlockNames = [
		'wpse/event-title',
		'wpse/event-featured-image',
		'wpse/event-date-time',
		'wpse/event-status',
		'wpse/event-venue',
		'wpse/event-address',
		'wpse/event-location-link',
		'wpse/event-content',
		'wpse/event-excerpt',
		'wpse/event-external-action',
		'wpse/event-categories',
		'wpse/event-tags',
	];
	requireCondition(
		registeredBlocks.response.ok && Array.isArray( registeredBlocks.data ),
		'The WordPress block-type registry is unavailable.',
	);
	for ( const blockName of atomicBlockNames ) {
		requireCondition(
			registeredBlocks.data.some( ( block ) => block.name === blockName ),
			`The atomic block ${ blockName } is not registered.`,
		);
	}

	const settingsResponse = await fetch(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	const settingsBody = await settingsResponse.text();
	requireCondition( settingsResponse.ok, 'The event settings page is unavailable.' );
	requireCondition(
		settingsBody.includes( 'name="wpse_structured_data_enabled"' ),
		'The structured-data setting is unavailable.',
	);
	requireCondition(
		settingsBody.includes( '<code>Europe/Brussels</code>' ) &&
			settingsBody.includes( 'options-general.php' ) &&
			settingsBody.includes( 'New events capture this timezone' ),
		'The settings page does not report or explain the authoritative WordPress timezone.',
	);
	requireCondition(
		settingsBody.includes( 'id="wpse_show_event_timezone"' ) &&
			! /<input[^>]+id="wpse_show_event_timezone"[^>]+checked=/.test( settingsBody ),
		'The public timezone setting is unavailable or not disabled by default.',
	);
	requireCondition(
		settingsBody.includes( 'name="wpse_archive_slug"' ) &&
			settingsBody.includes( 'name="wpse_archive_per_page"' ) &&
			settingsBody.includes( 'name="wpse_archive_default_period"' ),
		'The native archive settings are unavailable.',
	);
	requireCondition(
		settingsBody.includes( 'name="wpse_delete_data_on_uninstall"' ) &&
			settingsBody.includes( 'This cannot be undone.' ),
		'The uninstall retention control or its destructive warning is unavailable.',
	);
	requireCondition(
		settingsBody.includes( 'Repair event capabilities' ) &&
			settingsBody.includes( 'Rebuild event date indexes' ),
		'The event maintenance tools are unavailable.',
	);
	const settingsNonce = settingsBody.match( /name="_wpnonce" value="([^"]+)"/ )?.[ 1 ];
	requireCondition( settingsNonce, 'The event settings form omitted its WordPress nonce.' );
	const capabilityRepairNonce = adminPostNonce(
		settingsBody,
		'wpse_repair_event_capabilities',
	);
	const reindexNonce = adminPostNonce(
		settingsBody,
		'wpse_reindex_event_dates',
	);
	requireCondition(
		capabilityRepairNonce && reindexNonce,
		'The event maintenance forms omitted their action-specific nonces.',
	);

	const invalidCreate = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Invalid published smoke event',
				status: 'publish',
			} ),
		},
	);

	requireCondition( invalidCreate.response.status === 400, 'An event without a start was published through REST.' );
	requireCondition( invalidCreate.data.code === 'wpse_invalid_event', 'The invalid event returned the wrong REST error.' );
	requireCondition(
		invalidCreate.data.message ===
			'Enter a start date before publishing this event.',
		'The editor did not receive the actionable missing-start message.',
	);

	const validCreate = await createPublishedEvent(
		session,
		{
			title: 'Future smoke event',
			startOffset: 3,
			endOffset: 3,
			content: '<p>Single event body marker.</p><!-- wp:wpse/event-venue /-->[wpse_event_details]',
			address: 'Main Square 1',
			locationUrl: 'https://example.com/location',
			eventUrl: 'https://example.com/event',
			eventUrlLabel: '<b>Register</b> <script>alert(1)</script> now',
			eventStatus: 'postponed',
		},
	);

	requireCondition( validCreate.response.status === 201, 'A valid event could not be published through REST.' );
	const eventId = validCreate.data.id;
	requireCondition( Number.isInteger( eventId ), 'The valid REST event has no numeric ID.' );
	requireCondition(
		validCreate.data.meta._wpse_start_local === `${ localDate( 3 ) }T09:30:00`,
		'The valid REST event start was not canonicalized.',
	);
	requireCondition(
		validCreate.data.meta._wpse_event_url_label === 'Register now',
		`The external event link label was not sanitized through REST: ${ JSON.stringify( validCreate.data.meta._wpse_event_url_label ) }`,
	);

	const unauthorizedLabelUpdate = await requestJson(
		`http://localhost:8888/wp-json/wp/v2/wpse_event/${ eventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				meta: { _wpse_event_url_label: 'Unauthorized change' },
			} ),
		},
	);
	requireCondition(
		[ 401, 403 ].includes( unauthorizedLabelUpdate.response.status ),
		'An unauthenticated REST request changed the external event link label.',
	);

	const invalidUpdate = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ eventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				meta: { _wpse_event_url: 'javascript:alert(1)' },
			} ),
		},
	);

	requireCondition( invalidUpdate.response.status === 400, 'An unsafe event URL was accepted through REST.' );
	requireCondition(
		invalidUpdate.data.message ===
			'Enter a valid HTTP or HTTPS event URL.',
		'The editor did not receive the actionable invalid-URL message.',
	);

	const unchangedEvent = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ eventId }?context=edit`,
	);
	requireCondition( unchangedEvent.response.ok, 'The valid event could not be read after a rejected update.' );
	requireCondition(
		unchangedEvent.data.meta._wpse_start_local === `${ localDate( 3 ) }T09:30:00`,
		`A rejected REST update corrupted existing event data: ${ JSON.stringify( unchangedEvent.data.meta._wpse_start_local ) }.`,
	);

	const ongoingCreate = await createPublishedEvent(
		session,
		{
			title: 'Ongoing smoke event',
			startOffset: -1,
			endOffset: 1,
			eventUrl: 'https://example.com/fallback',
		},
	);
	const pastCreate = await createPublishedEvent(
		session,
		{
			title: 'Past smoke event',
			startOffset: -3,
			endOffset: -2,
			eventUrlLabel: 'Orphaned label must stay hidden',
		},
	);
	const protectedCreate = await createPublishedEvent(
		session,
		{
			title: 'Protected smoke event',
			startOffset: 4,
			endOffset: 4,
			password: 'smoke-secret',
		},
	);

	for ( const created of [ ongoingCreate, pastCreate, protectedCreate ] ) {
		requireCondition( created.response.status === 201, 'A query fixture event could not be published.' );
	}

	const protectedCoreRest = await requestJson(
		`http://localhost:8888/wp-json/wp/v2/wpse_event/${ protectedCreate.data.id }`,
	);
	requireCondition(
		protectedCoreRest.response.ok && ! ( 'meta' in protectedCoreRest.data ),
		'The public core REST response exposed event metadata for a password-protected event.',
	);
	const protectedEditorRest = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ protectedCreate.data.id }?context=edit`,
	);
	requireCondition(
		protectedEditorRest.response.ok &&
			protectedEditorRest.data.meta._wpse_venue === 'Town Hall',
		'An authorized editor could not read protected event metadata in edit context.',
	);

	const draftCreate = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Incomplete draft smoke event',
				status: 'draft',
			} ),
		},
	);
	requireCondition( draftCreate.response.status === 201, 'An incomplete event draft could not be saved.' );

	const calendarCategory = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event_category',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				name: 'Calendar smoke category',
				slug: 'calendar-smoke',
			} ),
		},
	);
	requireCondition( calendarCategory.response.status === 201, 'The calendar category fixture could not be created.' );
	const archiveCategory = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event_category',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				name: 'Archive smoke category',
				slug: 'archive-smoke',
			} ),
		},
	);
	requireCondition( archiveCategory.response.status === 201, 'The archive category fixture could not be created.' );

	const categorizedEvent = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ eventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				wpse_event_category: [ calendarCategory.data.id, archiveCategory.data.id ],
			} ),
		},
	);
	requireCondition( categorizedEvent.response.ok, 'The event could not be assigned to its calendar category.' );
	for ( const event of [ ongoingCreate.data, pastCreate.data, protectedCreate.data, draftCreate.data ] ) {
		const categorizedFixture = await authenticatedRequest(
			session,
			`/wp-json/wp/v2/wpse_event/${ event.id }`,
			{
				method: 'POST',
				headers: { 'content-type': 'application/json' },
				body: JSON.stringify( {
					wpse_event_category: [ archiveCategory.data.id ],
				} ),
			},
		);
		requireCondition( categorizedFixture.response.ok, 'A taxonomy archive fixture could not be categorized.' );
	}
	const blockTag = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event_tag',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { name: 'Block smoke tag', slug: 'block-smoke' } ),
		},
	);
	requireCondition( blockTag.response.status === 201, 'The atomic block tag fixture could not be created.' );
	const blockReadyEvent = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ eventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				excerpt: 'Atomic event excerpt.',
				wpse_event_tag: [ blockTag.data.id ],
			} ),
		},
	);
	requireCondition( blockReadyEvent.response.ok, 'The atomic block event fixture could not be completed.' );
	const serializedAtomicBlocks = atomicBlockNames.map( ( blockName, index ) => {
		const attributes = index === 0
			? {
				eventId,
				style: {
					color: { text: '#123456' },
					spacing: { margin: { bottom: '2rem' } },
				},
			}
			: { eventId };

		return `<!-- wp:${ blockName } ${ JSON.stringify( attributes ) } /-->`;
	} ).join( '' );
	const atomicPage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Atomic event fields smoke page',
				slug: 'atomic-event-fields-smoke',
				status: 'publish',
				content: serializedAtomicBlocks,
			} ),
		},
	);
	requireCondition( atomicPage.response.status === 201, 'The explicit-source atomic block page could not be published.' );
	const atomicPageBody = await fetchHealthyPage( atomicPage.data.link );
	for ( const marker of [
		'wpse-single-event-title',
		'wpse-event-date',
		'wpse-event-status-postponed',
		'wpse-event-venue',
		'wpse-event-address',
		'wpse-event-location-link',
		'wpse-single-event-content',
		'Atomic event excerpt.',
		'wpse-event-action',
		'Calendar smoke category',
		'Block smoke tag',
	] ) {
		requireCondition( atomicPageBody.includes( marker ), `The atomic block page omitted ${ marker }.` );
	}
	requireCondition(
		! atomicPageBody.includes( 'wpse-event-field-block-event-featured-image' ),
		'The empty featured-image block emitted a public wrapper.',
	);
	requireCondition(
		atomicPageBody.includes( 'has-text-color' ) &&
			atomicPageBody.includes( 'margin-bottom:2rem' ),
		'Native color or spacing block supports were not applied to atomic output.',
	);
	requireCondition(
		! atomicPageBody.includes( 'event-fields-editor.min.js' ),
		'The block editor adapter was enqueued on an atomic public page.',
	);

	const strictSourcePage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Strict atomic source smoke page',
				slug: 'strict-atomic-source-smoke',
				status: 'publish',
				content: `<!-- wp:wpse/event-title ${ JSON.stringify( { eventId: draftCreate.data.id } ) } /-->`,
			} ),
		},
	);
	requireCondition( strictSourcePage.response.status === 201, 'The strict atomic source page could not be published.' );
	const strictSourceBody = await fetchHealthyPage( strictSourcePage.data.link );
	requireCondition(
		! strictSourceBody.includes( 'Incomplete draft smoke event' ) &&
			! strictSourceBody.includes( 'wpse-event-field-block-event-title' ),
		'An explicit draft source leaked or fell back on a public atomic block page.',
	);

	const eventsAdminUrl = 'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event';
	const eventsAdminBody = await fetchHealthyPage( eventsAdminUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	const renderedAdminColumns = [ ...eventsAdminBody.matchAll( /<th[^>]+id=['"]([^'"]+)['"]/g ) ]
		.map( ( match ) => match[ 1 ] )
		.join( ', ' );
	for ( const column of [
		'column-wpse_start',
		'column-wpse_end',
		'column-wpse_all_day',
		'column-wpse_location',
		'column-wpse_event_status',
		'column-wpse_publication_status',
	] ) {
		requireCondition(
			eventsAdminBody.includes( column ),
			`The Events overview omitted column ${ column }; rendered columns: ${ renderedAdminColumns || 'none' }.`,
		);
	}
	requireCondition( eventsAdminBody.includes( 'name="wpse_admin_view"' ), 'The event timing/status filter is missing.' );
	requireCondition(
		eventsAdminBody.includes( 'wpse-event-category-filter' ),
		'The event-category admin filter is missing.',
	);

	const upcomingAdminUrl = new URL( eventsAdminUrl );
	upcomingAdminUrl.searchParams.set( 'wpse_admin_view', 'upcoming' );
	const upcomingAdminBody = await fetchHealthyPage( upcomingAdminUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	requireCondition( upcomingAdminBody.includes( 'Ongoing smoke event' ), 'The admin upcoming filter excluded an active event.' );
	requireCondition( upcomingAdminBody.includes( 'Future smoke event' ), 'The admin upcoming filter excluded a future event.' );
	requireCondition( ! upcomingAdminBody.includes( 'Past smoke event' ), 'The admin upcoming filter included a past event.' );
	requireCondition(
		upcomingAdminBody.indexOf( 'Ongoing smoke event' ) < upcomingAdminBody.indexOf( 'Future smoke event' ),
		'The admin upcoming filter did not sort by ascending start.',
	);

	const pastAdminUrl = new URL( eventsAdminUrl );
	pastAdminUrl.searchParams.set( 'wpse_admin_view', 'past' );
	const pastAdminBody = await fetchHealthyPage( pastAdminUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	requireCondition( pastAdminBody.includes( 'Past smoke event' ), 'The admin past filter excluded a past event.' );
	requireCondition( ! pastAdminBody.includes( 'Future smoke event' ), 'The admin past filter included a future event.' );

	const postponedAdminUrl = new URL( eventsAdminUrl );
	postponedAdminUrl.searchParams.set( 'wpse_admin_view', 'postponed' );
	const postponedAdminBody = await fetchHealthyPage( postponedAdminUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	requireCondition( postponedAdminBody.includes( 'Future smoke event' ), 'The admin postponed filter omitted its event.' );
	requireCondition( ! postponedAdminBody.includes( 'Ongoing smoke event' ), 'The admin postponed filter included a scheduled event.' );

	const categoryAdminUrl = new URL( eventsAdminUrl );
	categoryAdminUrl.searchParams.set( 'wpse_event_category', 'calendar-smoke' );
	const categoryAdminBody = await fetchHealthyPage( categoryAdminUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
	} );
	requireCondition( categoryAdminBody.includes( 'Future smoke event' ), 'The admin category filter omitted its event.' );
	requireCondition( ! categoryAdminBody.includes( 'Ongoing smoke event' ), 'The admin category filter included an unrelated event.' );

	const duplicateLinkMatch = eventsAdminBody.match(
		new RegExp( `href="([^"]*action=wpse_duplicate_event[^"]*post=${ eventId }[^"]*)"` ),
	);
	requireCondition( duplicateLinkMatch?.[ 1 ], 'The authorized duplicate row action is missing.' );
	const duplicateLink = duplicateLinkMatch[ 1 ]
		.replaceAll( '&#038;', '&' )
		.replaceAll( '&amp;', '&' );
	const invalidDuplicateUrl = new URL( duplicateLink );
	invalidDuplicateUrl.searchParams.set( '_wpnonce', 'forged-smoke-nonce' );
	const invalidDuplicateResponse = await fetch( invalidDuplicateUrl, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
		redirect: 'manual',
	} );
	requireCondition(
		invalidDuplicateResponse.status === 403,
		'The event duplication endpoint accepted a forged nonce.',
	);

	const duplicateResponse = await fetch( duplicateLink, {
		headers: { cookie: cookieHeader( session.cookieJar ) },
		redirect: 'manual',
	} );
	const duplicateEditorLocation = duplicateResponse.headers.get( 'location' );
	const duplicateResponseBody = await duplicateResponse.text();
	requireCondition(
		duplicateResponse.status === 302 && duplicateEditorLocation,
		`The protected duplicate action did not redirect to a new draft editor (status ${ duplicateResponse.status }, location ${ duplicateEditorLocation || 'none' }, response ${ duplicateResponseBody.replace( /\s+/g, ' ' ).slice( 0, 240 ) }).`,
	);
	const duplicatedEventId = Number.parseInt(
		new URL( duplicateEditorLocation, eventsAdminUrl ).searchParams.get( 'post' ),
		10,
	);
	requireCondition( Number.isInteger( duplicatedEventId ), 'The duplicate redirect omitted the new event ID.' );

	const duplicatedEvent = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ duplicatedEventId }?context=edit`,
	);
	requireCondition( duplicatedEvent.response.ok, 'The duplicated event draft could not be read.' );
	requireCondition( duplicatedEvent.data.status === 'draft', 'The duplicated event was not created as a draft.' );
	requireCondition( duplicatedEvent.data.title.raw === 'Future smoke event — Copy', 'The duplicate title marker is missing.' );
	requireCondition(
		duplicatedEvent.data.meta._wpse_start_local === `${ localDate( 3 ) }T09:30:00`,
		'The duplicate did not copy its canonical start.',
	);
	requireCondition( duplicatedEvent.data.meta._wpse_venue === 'Town Hall', 'The duplicate did not copy its venue.' );
	requireCondition(
		duplicatedEvent.data.meta._wpse_location_url === 'https://example.com/location',
		'The duplicate did not copy its route/location URL.',
	);
	requireCondition(
		duplicatedEvent.data.meta._wpse_event_url === '',
		'The duplicate incorrectly copied its external commercial event URL.',
	);
	requireCondition(
		duplicatedEvent.data.meta._wpse_event_url_label === '',
		'The duplicate incorrectly copied its external event link label.',
	);
	requireCondition(
		duplicatedEvent.data.wpse_event_category.includes( calendarCategory.data.id ),
		'The duplicate did not copy its event category.',
	);

	const duplicatedEditorBody = await fetchHealthyPage(
		new URL( duplicateEditorLocation, eventsAdminUrl ),
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		duplicatedEditorBody.includes( 'Review the copied start and end date before publishing this event.' ),
		'The duplicated event editor omitted its date-review warning.',
	);

	const confirmDuplicatedDates = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ duplicatedEventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { title: 'Confirmed copied smoke event' } ),
		},
	);
	requireCondition( confirmDuplicatedDates.response.ok, 'The duplicated event could not be validly saved.' );
	const confirmedEditorBody = await fetchHealthyPage(
		`http://localhost:8888/wp-admin/post.php?post=${ duplicatedEventId }&action=edit`,
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		! confirmedEditorBody.includes( 'Review the copied start and end date before publishing this event.' ),
		'The copied-date review warning remained after a valid save.',
	);

	const forgedReindexResponse = await fetch(
		'http://localhost:8888/wp-admin/admin-post.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				action: 'wpse_reindex_event_dates',
				_wpnonce: 'forged-maintenance-nonce',
				wpse_page: '1',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		forgedReindexResponse.status === 403,
		'The event date-index maintenance endpoint accepted a forged nonce.',
	);

	const capabilityRepairResponse = await fetch(
		'http://localhost:8888/wp-admin/admin-post.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				action: 'wpse_repair_event_capabilities',
				_wpnonce: capabilityRepairNonce,
			} ),
			redirect: 'manual',
		},
	);
	const capabilityRepairLocation = capabilityRepairResponse.headers.get( 'location' );
	requireCondition(
		capabilityRepairResponse.status === 302 &&
			capabilityRepairLocation?.includes( 'wpse_maintenance=capabilities_repaired' ),
		'The capability repair action did not return protected success feedback.',
	);
	const capabilityRepairBody = await fetchHealthyPage(
		new URL( capabilityRepairLocation, 'http://localhost:8888/wp-admin/' ),
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		capabilityRepairBody.includes( 'Event capabilities were restored for administrators and editors.' ),
		'The capability repair success notice is unavailable.',
	);

	const reindexResponse = await fetch(
		'http://localhost:8888/wp-admin/admin-post.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				action: 'wpse_reindex_event_dates',
				_wpnonce: reindexNonce,
				wpse_page: '1',
				wpse_processed: '0',
				wpse_changed: '0',
				wpse_skipped: '0',
				wpse_failed: '0',
			} ),
			redirect: 'manual',
		},
	);
	const reindexLocation = reindexResponse.headers.get( 'location' );
	requireCondition(
		reindexResponse.status === 302 &&
			/reindex_(progress|complete)/.test( reindexLocation ?? '' ) &&
			/[?&]wpse_processed=[1-9][0-9]*/.test( reindexLocation ?? '' ),
		'The bounded event reindex action did not return valid progress counters.',
	);
	const reindexFeedbackBody = await fetchHealthyPage(
		new URL( reindexLocation, 'http://localhost:8888/wp-admin/' ),
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		reindexFeedbackBody.includes( 'Date index maintenance inspected' ),
		'The event reindex action omitted its privacy-safe progress feedback.',
	);

	const missingCalendarWindow = await requestJson(
		'http://localhost:8888/wp-json/wpse/v1/events',
	);
	requireCondition( missingCalendarWindow.response.status === 400, 'The calendar feed accepted a missing date window.' );

	const oversizedCalendarUrl = new URL( 'http://localhost:8888/wp-json/wpse/v1/events' );
	oversizedCalendarUrl.searchParams.set( 'start', '2024-01-01T00:00:00Z' );
	oversizedCalendarUrl.searchParams.set( 'end', '2026-01-01T00:00:00Z' );
	const oversizedCalendarWindow = await requestJson( oversizedCalendarUrl );
	requireCondition( oversizedCalendarWindow.response.status === 400, 'The calendar feed accepted an unbounded date window.' );

	const calendarFeedUrl = new URL( 'http://localhost:8888/wp-json/wpse/v1/events' );
	calendarFeedUrl.searchParams.set( 'start', `${ localDate( -5 ) }T00:00:00Z` );
	calendarFeedUrl.searchParams.set( 'end', `${ localDate( 6 ) }T00:00:00Z` );
	calendarFeedUrl.searchParams.set( 'per_page', '100' );
	const calendarFeed = await requestJson( calendarFeedUrl );
	requireCondition( calendarFeed.response.ok, 'The bounded public calendar feed is unavailable.' );
	requireCondition( Array.isArray( calendarFeed.data ), 'The calendar feed is not a collection.' );

	const calendarTitles = calendarFeed.data.map( ( event ) => event.title );
	requireCondition( calendarTitles.includes( 'Past smoke event' ), 'The calendar feed excluded an overlapping past event.' );
	requireCondition( calendarTitles.includes( 'Ongoing smoke event' ), 'The calendar feed excluded an ongoing event.' );
	requireCondition( calendarTitles.includes( 'Future smoke event' ), 'The calendar feed excluded an upcoming event.' );
	requireCondition( ! calendarTitles.includes( 'Protected smoke event' ), 'The calendar feed exposed a password-protected event.' );
	requireCondition( ! calendarTitles.includes( 'Incomplete draft smoke event' ), 'The calendar feed exposed a draft event.' );

	const futureFeedEvent = calendarFeed.data.find( ( event ) => event.id === eventId );
	requireCondition( futureFeedEvent?.status === 'postponed', 'The calendar feed omitted the visible event status.' );
	requireCondition( futureFeedEvent?.extendedProps?.venue === 'Town Hall', 'The calendar feed omitted its public venue.' );
	requireCondition(
		futureFeedEvent?.extendedProps?.categories?.includes( 'calendar-smoke' ),
		'The calendar feed omitted its category slug.',
	);
	requireCondition( ! JSON.stringify( calendarFeed.data ).includes( '_wpse_' ), 'The calendar feed leaked private metadata keys.' );
	requireCondition(
		Number.parseInt( calendarFeed.response.headers.get( 'X-WP-TotalPages' ), 10 ) >= 1,
		'The calendar feed omitted its pagination headers.',
	);

	const filteredCalendarUrl = new URL( calendarFeedUrl );
	filteredCalendarUrl.searchParams.set( 'categories', 'calendar-smoke' );
	const filteredCalendarFeed = await requestJson( filteredCalendarUrl );
	requireCondition( filteredCalendarFeed.response.ok, 'The filtered calendar feed is unavailable.' );
	requireCondition(
		filteredCalendarFeed.data.length === 1 && filteredCalendarFeed.data[ 0 ].id === eventId,
		'The calendar category filter returned unrelated events.',
	);

	const archiveBody = await fetchHealthyPage( 'http://localhost:8888/events/' );
	requireCondition( archiveBody.includes( 'Ongoing smoke event' ), 'The archive excluded an active event.' );
	requireCondition( archiveBody.includes( 'Future smoke event' ), 'The archive excluded an upcoming event.' );
	requireCondition( ! archiveBody.includes( 'Past smoke event' ), 'The default archive exposed a past event.' );
	requireCondition( ! archiveBody.includes( 'Protected smoke event' ), 'The archive exposed a password-protected event.' );
	requireCondition( ! archiveBody.includes( 'Incomplete draft smoke event' ), 'The archive exposed a draft event.' );
	requireCondition(
		archiveBody.indexOf( 'Ongoing smoke event' ) < archiveBody.indexOf( 'Future smoke event' ),
		'The archive did not order active and upcoming events by ascending start.',
	);
	requireCondition( archiveBody.includes( 'wpse-event-archive' ), 'The native archive fallback did not render.' );
	requireCondition( archiveBody.includes( 'wpse-event-archive-filters' ), 'The native archive filters did not render.' );
	requireCondition(
		! archiveBody.includes( '"@type":"Event"' ),
		'The event archive incorrectly emitted singular Event structured data.',
	);

	const pastArchiveUrl = new URL( 'http://localhost:8888/events/' );
	pastArchiveUrl.searchParams.set( 'wpse_period', 'past' );
	const pastArchiveBody = await fetchHealthyPage( pastArchiveUrl );
	requireCondition( pastArchiveBody.includes( 'Past smoke event' ), 'The native archive past filter did not show past events.' );
	requireCondition( ! pastArchiveBody.includes( 'Future smoke event' ), 'The native archive past filter exposed future events.' );

	const categoryArchiveBody = await fetchHealthyPage(
		'http://localhost:8888/event-category/archive-smoke/',
	);
	requireCondition( categoryArchiveBody.includes( 'wpse-event-archive' ), 'The event category archive did not use event presentation.' );
	requireCondition(
		categoryArchiveBody.includes( 'Events in “Archive smoke category”' ) &&
			! categoryArchiveBody.includes( '&lt;span&gt;' ),
		'The event category archive omitted its plain-text term title.',
	);
	requireCondition( categoryArchiveBody.includes( 'frontend.css' ), 'The event category archive omitted component styling.' );
	requireCondition( categoryArchiveBody.includes( 'Past smoke event' ), 'The event category archive omitted a public past event.' );
	requireCondition( categoryArchiveBody.includes( 'Ongoing smoke event' ), 'The event category archive omitted a public active event.' );
	requireCondition( categoryArchiveBody.includes( 'Future smoke event' ), 'The event category archive omitted a public future event.' );
	requireCondition( ! categoryArchiveBody.includes( 'Protected smoke event' ), 'The event category archive exposed a password-protected event.' );
	requireCondition( ! categoryArchiveBody.includes( 'Incomplete draft smoke event' ), 'The event category archive exposed a draft event.' );
	requireCondition( ! categoryArchiveBody.includes( 'wpse-event-archive-filters' ), 'The fixed event category archive exposed cross-archive filters.' );
	requireCondition(
		categoryArchiveBody.indexOf( 'Past smoke event' ) < categoryArchiveBody.indexOf( 'Ongoing smoke event' ) &&
			categoryArchiveBody.indexOf( 'Ongoing smoke event' ) < categoryArchiveBody.indexOf( 'Future smoke event' ),
		'The event category archive did not order events by ascending start.',
	);
	const tagArchiveBody = await fetchHealthyPage(
		'http://localhost:8888/event-tag/block-smoke/',
	);
	requireCondition( tagArchiveBody.includes( 'wpse-event-archive' ), 'The event tag archive did not use event presentation.' );
	requireCondition(
		tagArchiveBody.includes( 'Events tagged “Block smoke tag”' ) &&
			! tagArchiveBody.includes( '&lt;span&gt;' ),
		'The event tag archive omitted its plain-text term title.',
	);
	requireCondition( tagArchiveBody.includes( 'Future smoke event' ), 'The event tag archive omitted its public event.' );
	requireCondition( ! tagArchiveBody.includes( 'wpse-event-archive-filters' ), 'The fixed event tag archive exposed cross-archive filters.' );

	const singleBody = await fetchHealthyPage( validCreate.data.link );
	const singleArticleStart = singleBody.indexOf( '<article class="wpse-single-event"' );
	const singleArticleEnd = singleBody.indexOf( '</article>', singleArticleStart );
	requireCondition(
		singleArticleStart >= 0 && singleArticleEnd > singleArticleStart,
		'The native single event article is unavailable.',
	);
	const singleArticle = singleBody.slice( singleArticleStart, singleArticleEnd );
	requireCondition(
		( singleArticle.match( /wpse-event-venue/g ) ?? [] ).length >= 2,
		'The current-context venue block did not render from event post context.',
	);
	requireCondition(
		! singleArticle.includes( 'wpse-event-timezone' ),
		'The backward-compatible default unexpectedly exposed an event timezone.',
	);
	const singleSections = [
		'Future smoke event',
		'wpse-event-date',
		'Postponed',
		'Town Hall',
		'Single event body marker.',
		'Register now',
	];
	for ( const section of singleSections ) {
		requireCondition( singleArticle.includes( section ), `The native single event omitted ${ section }.` );
	}
	for ( let index = 1; index < singleSections.length; index += 1 ) {
		requireCondition(
			singleArticle.indexOf( singleSections[ index - 1 ] ) < singleArticle.indexOf( singleSections[ index ] ),
			`The native single event rendered ${ singleSections[ index ] } out of order.`,
		);
	}
	requireCondition( singleBody.includes( 'Main Square 1' ), 'The native single event omitted its address.' );
	requireCondition( singleBody.includes( 'https://example.com/location' ), 'The native single event omitted its location link.' );
	requireCondition(
		! singleArticle.includes( '<script' ) && ! singleArticle.includes( '<b>Register</b>' ),
		'The custom external event link label rendered submitted markup.',
	);

	const fallbackSingleBody = await fetchHealthyPage( ongoingCreate.data.link );
	requireCondition(
		fallbackSingleBody.includes( 'More event information' ),
		'An event without a custom external link label lost the translated fallback.',
	);
	const noActionSingleBody = await fetchHealthyPage( pastCreate.data.link );
	requireCondition(
		! noActionSingleBody.includes( 'wpse-event-action' ) &&
			! noActionSingleBody.includes( 'Orphaned label must stay hidden' ),
		'An external event label rendered without an external URL.',
	);
	requireCondition(
		singleBody.includes( '<script type="application/ld+json">' ) &&
			singleBody.includes( '"@type":"Event"' ) &&
			singleBody.includes( '"eventStatus":"https://schema.org/EventPostponed"' ),
		'The individual event omitted its Event JSON-LD or public status.',
	);
	requireCondition(
		/"startDate":"[^"]+\+0[12]:00"/.test( singleBody ),
		'The timed Event JSON-LD omitted its local UTC offset.',
	);
	requireCondition(
		singleBody.includes( '"location":{"@type":"Place","name":"Town Hall","address":"Main Square 1"}' ),
		'The Event JSON-LD omitted its visible location.',
	);
	requireCondition(
		( singleBody.match( /class="wpse-single-event"/g ) ?? [] ).length === 1,
		'The event-details shortcode recursively duplicated the current event.',
	);

	const themeShellMatrix = [
		{
			theme: 'wpse-classic-shell',
			headerMarker: 'id="wpse-test-classic-header"',
			footerMarker: 'id="wpse-test-classic-footer"',
		},
		{
			theme: 'wpse-hybrid-shell',
			headerMarker: 'id="wpse-test-classic-header"',
			footerMarker: 'id="wpse-test-classic-footer"',
		},
		{
			theme: 'wpse-hybrid-override',
			headerMarker: 'id="wpse-test-classic-header"',
			footerMarker: 'id="wpse-test-classic-footer"',
			singleOverrideMarker: 'id="wpse-test-php-single-override"',
			archiveOverrideMarker: 'id="wpse-test-php-archive-override"',
			taxonomyOverrideMarker: 'id="wpse-test-php-archive-override"',
		},
		{
			theme: 'wpse-block-shell',
			headerMarker: 'id="wpse-test-block-header"',
			footerMarker: 'id="wpse-test-block-footer"',
		},
		{
			theme: 'wpse-block-override',
			headerMarker: 'id="wpse-test-block-header"',
			footerMarker: 'id="wpse-test-block-footer"',
			singleOverrideMarker: 'id="wpse-test-block-single-override"',
			archiveOverrideMarker: 'id="wpse-test-block-archive-override"',
			taxonomyOverrideMarker: 'id="wpse-test-block-taxonomy-override"',
		},
	];

	for ( const themeContract of themeShellMatrix ) {
		await assertEventThemeShell(
			themeContract,
			session,
			validCreate.data.link,
		);
	}

	await activateSmokeTheme( session, 'wpse-classic-shell' );
	const occurrenceParity = await authenticatedRequest(
		session,
		`/wp-json/wpse-smoke/v1/occurrence-parity?${ new URLSearchParams( {
			event_id: String( eventId ),
			protected_event_id: String( protectedCreate.data.id ),
			draft_event_id: String( draftCreate.data.id ),
			window_start: localDate( -10 ),
			window_end: localDate( 10 ),
		} ) }`,
	);
	requireCondition(
		occurrenceParity.response.ok,
		`The administrator-only occurrence parity probe failed: ${ JSON.stringify( occurrenceParity.data ) }`,
	);
	requireCondition(
		occurrenceParity.data.health.generation > 0 &&
			occurrenceParity.data.health.uid_valid === true &&
			occurrenceParity.data.health.row_count === 1 &&
			occurrenceParity.data.health.exact_public_identity === true,
		`A canonical event save did not activate exactly one occurrence projection: ${ JSON.stringify( occurrenceParity.data.health ) }`,
	);
	requireCondition(
		JSON.stringify( occurrenceParity.data.page_one.legacy_ids ) ===
			JSON.stringify( occurrenceParity.data.page_one.occurrence_ids ) &&
			occurrenceParity.data.page_one.legacy_total ===
				occurrenceParity.data.page_one.occurrence_total &&
			occurrenceParity.data.page_one.legacy_total_pages ===
				occurrenceParity.data.page_one.occurrence_total_pages,
		'Occurrence period ordering or pagination differs from the qualified one-off query.',
	);
	requireCondition(
		JSON.stringify( occurrenceParity.data.page_two.legacy_ids ) ===
			JSON.stringify( occurrenceParity.data.page_two.occurrence_ids ),
		'Occurrence page-two membership differs from the qualified one-off query.',
	);
	requireCondition(
		JSON.stringify( occurrenceParity.data.filtered.legacy_ids ) ===
			JSON.stringify( [ eventId ] ) &&
			JSON.stringify( occurrenceParity.data.filtered.occurrence_ids ) ===
				JSON.stringify( [ eventId ] ),
		'Occurrence category-and-tag filtering differs from the qualified one-off query.',
	);
	requireCondition(
		JSON.stringify( occurrenceParity.data.window.legacy_ids ) ===
			JSON.stringify( occurrenceParity.data.window.occurrence_ids ) &&
			occurrenceParity.data.window.occurrence_ids.length === 3 &&
			occurrenceParity.data.window.protected_parent_excluded === true &&
			occurrenceParity.data.window.draft_parent_excluded === true,
		'Occurrence calendar overlap or public-parent filtering differs from the qualified one-off query.',
	);
	requireCondition(
		occurrenceParity.data.ready === true,
		'The occurrence readiness gate rejected a complete and healthy one-off index.',
	);
	requireCondition(
		occurrenceParity.data.recurrence_meta.registered_as_string === true &&
			occurrenceParity.data.recurrence_meta.rest_hidden === true &&
			occurrenceParity.data.recurrence_meta.revisioned === true,
		'The canonical recurrence metadata is not protected and revision-enabled in WordPress.',
	);

	const recurrenceDraft = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/wpse_event',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Private recurrence projection fixture',
				status: 'draft',
				content: '<!-- wp:wpse/event-title /--><!-- wp:wpse/event-date-time /-->',
				meta: {
					_wpse_start_local: `${ localDate( 30 ) }T09:30`,
					_wpse_end_local: `${ localDate( 30 ) }T11:00`,
					_wpse_all_day: false,
					_wpse_timezone: 'Europe/Brussels',
					_wpse_event_status: 'scheduled',
				},
			} ),
		},
	);
	requireCondition(
		recurrenceDraft.response.status === 201,
		'A private recurrence projection fixture could not be created.',
	);
	const unauthorizedRecurrenceContext = await requestJson(
		`http://localhost:8888/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence`,
	);
	requireCondition(
		[ 401, 403 ].includes( unauthorizedRecurrenceContext.response.status ),
		'An unauthenticated request could read recurrence editor state.',
	);
	const recurrenceContext = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence`,
	);
	requireCondition(
		recurrenceContext.response.ok &&
			recurrenceContext.data.recurring === false &&
			typeof recurrenceContext.data.revision === 'string' &&
			recurrenceContext.data.revision.length === 64 &&
			recurrenceContext.data.aggregate?.segments?.length === 1,
		'The authorized recurrence context did not bootstrap complete one-off state.',
	);
	const recurringProposal = structuredClone(
		recurrenceContext.data.aggregate,
	);
	recurringProposal.segments[ 0 ].definition = {
		type: 'rule',
		frequency: 'daily',
		interval: 1,
		end: { mode: 'count', count: 3 },
	};
	const recurrenceMutation = {
		aggregate: recurringProposal,
		scope: 'complete_series',
		target: '',
		revision: recurrenceContext.data.revision,
		from_date: localDate( 30 ),
		through_date: localDate( 34 ),
		max_rows: 10,
	};
	const recurrencePreview = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/preview`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( recurrenceMutation ),
		},
	);
	requireCondition(
		recurrencePreview.response.ok &&
			recurrencePreview.data.impact?.scope === 'complete_series' &&
			recurrencePreview.data.impact?.added === 2 &&
			recurrencePreview.data.impact?.removed === 0 &&
			typeof recurrencePreview.data.confirmation === 'string' &&
			recurrencePreview.data.confirmation.length === 64,
		`The recurrence editor preview was incomplete: ${ JSON.stringify( recurrencePreview.data ) }`,
	);
	const recurrenceSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				...recurrenceMutation,
				confirmation: recurrencePreview.data.confirmation,
			} ),
		},
	);
	requireCondition(
		recurrenceSave.response.ok &&
			recurrenceSave.data.changed === true &&
			recurrenceSave.data.context?.recurring === true &&
			recurrenceSave.data.context?.revision !==
				recurrenceContext.data.revision,
		`The confirmed recurrence editor save failed: ${ JSON.stringify( recurrenceSave.data ) }`,
	);
	const staleRecurrenceSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				...recurrenceMutation,
				confirmation: recurrencePreview.data.confirmation,
			} ),
		},
	);
	requireCondition(
		staleRecurrenceSave.response.status === 409 &&
			staleRecurrenceSave.data.code === 'wpse_recurrence_stale_revision',
		'A stale recurrence preview was accepted after a newer save.',
	);
	const recurrenceProjection = await authenticatedRequest(
		session,
		'/wp-json/wpse-smoke/v1/recurrence-projection',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { event_id: recurrenceDraft.data.id } ),
		},
	);
	requireCondition(
		recurrenceProjection.response.ok,
		`The complete recurrence projection probe failed: ${ JSON.stringify( recurrenceProjection.data ) }`,
	);
	const recurrenceRows = recurrenceProjection.data.rows;
	requireCondition(
		recurrenceProjection.data.first_successful === true &&
			recurrenceProjection.data.first_changed === true &&
			recurrenceProjection.data.second_successful === true &&
			recurrenceProjection.data.second_changed === false &&
			recurrenceProjection.data.generation_unchanged === true &&
			recurrenceProjection.data.healthy === true &&
			recurrenceProjection.data.aggregate_loaded === true,
		'Canonical-first recurrence save or clean no-op coordination failed.',
	);
	requireCondition(
		Array.isArray( recurrenceRows ) &&
			recurrenceRows.length === 5 &&
			recurrenceRows.every( ( row ) => /^[a-f0-9]{32}$/.test( row.public_key ) ) &&
			recurrenceRows.filter( ( row ) => row.source === 'manual' ).length === 1 &&
			recurrenceRows.filter( ( row ) => row.event_status === 'postponed' ).length === 1 &&
			recurrenceRows.filter( ( row ) => row.event_status === 'cancelled' ).length === 1 &&
			recurrenceRows.some(
				( row ) =>
					row.recurrence_id === `${ localDate( 32 ) }T09:30:00` &&
					row.start_local === `${ localDate( 32 ) }T12:00:00`,
			),
		'Recurring skip, move, cancellation or manual rows did not reconcile in the real projection table.',
	);
	requireCondition(
		recurrenceProjection.data.coverage_from === localDate( 30 ) &&
			recurrenceProjection.data.coverage_through === localDate( 34 ) &&
			recurrenceProjection.data.coverage_generation > 0,
		`The recurring projection did not record its exact initial coverage window: ${ JSON.stringify( recurrenceProjection.data ) }`,
	);
	const privateOccurrenceRest = await requestJson(
		`http://localhost:8888/wp-json/wpse/v2/events/${ recurrenceDraft.data.id }/occurrences/${ recurrenceRows[ 0 ].public_key }`,
	);
	requireCondition(
		privateOccurrenceRest.response.status === 404 &&
			privateOccurrenceRest.data.code === 'wpse_occurrence_not_found',
		'A draft parent was exposed through the public occurrence REST leaf.',
	);
	const publishedRecurrence = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ recurrenceDraft.data.id }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { status: 'publish' } ),
		},
	);
	requireCondition(
		publishedRecurrence.response.ok &&
			publishedRecurrence.data.status === 'publish',
		'A recurring draft could not be published through the ordinary WordPress save path.',
	);
	const recurringOrdinarySaveHealth = await authenticatedRequest(
		session,
		`/wp-json/wpse-smoke/v1/recurrence-health?event_id=${ recurrenceDraft.data.id }`,
	);
	requireCondition(
		recurringOrdinarySaveHealth.response.ok &&
			recurringOrdinarySaveHealth.data.generation > 0 &&
			recurringOrdinarySaveHealth.data.dirty === false &&
			[ smokeStartedOn, localDate( 0 ) ].includes(
				recurringOrdinarySaveHealth.data.coverage_from,
			) &&
			recurringOrdinarySaveHealth.data.coverage_through ===
				offsetIsoDate(
					recurringOrdinarySaveHealth.data.coverage_from,
					540,
				) &&
			recurringOrdinarySaveHealth.data.coverage_generation ===
				recurringOrdinarySaveHealth.data.generation &&
			recurringOrdinarySaveHealth.data.row_count === 10 &&
			recurringOrdinarySaveHealth.data.first_public_key ===
				recurrenceRows[ 0 ].public_key &&
			recurringOrdinarySaveHealth.data.exact_found === true &&
			recurringOrdinarySaveHealth.data.aggregate_loaded === true,
		`Publishing did not establish a complete recurring production projection: ${ JSON.stringify( recurringOrdinarySaveHealth.data ) }`,
	);
	const narrowedRecurrence = await authenticatedRequest(
		session,
		'/wp-json/wpse-smoke/v1/recurrence-repair-needed',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { event_id: recurrenceDraft.data.id } ),
		},
	);
	requireCondition(
		narrowedRecurrence.response.ok &&
			narrowedRecurrence.data.marked === true,
		'The isolated smoke fixture could not prepare a protected manual-repair candidate.',
	);

	const repairSettingsBody = await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	const occurrenceRepairNonce = adminPostNonce(
		repairSettingsBody,
		'wpse_repair_occurrence_index',
	);
	requireCondition(
		repairSettingsBody.includes( 'Repair needed' ) && occurrenceRepairNonce,
		'The settings screen did not surface the insufficient recurring projection window or its protected repair action.',
	);
	const forgedOccurrenceRepair = await fetch(
		'http://localhost:8888/wp-admin/admin-post.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				action: 'wpse_repair_occurrence_index',
				_wpnonce: 'forged-occurrence-repair-nonce',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		forgedOccurrenceRepair.status === 403,
		'The occurrence index repair endpoint accepted a forged nonce.',
	);
	const occurrenceRepair = await fetch(
		'http://localhost:8888/wp-admin/admin-post.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				action: 'wpse_repair_occurrence_index',
				_wpnonce: occurrenceRepairNonce,
				wpse_occurrence_offset: '0',
				wpse_occurrence_processed: '0',
				wpse_occurrence_indexed: '0',
				wpse_occurrence_invalid: '0',
				wpse_occurrence_failed: '0',
			} ),
			redirect: 'manual',
		},
	);
	const occurrenceRepairLocation = occurrenceRepair.headers.get( 'location' );
	requireCondition(
		occurrenceRepair.status === 302 &&
			occurrenceRepairLocation?.includes(
				'wpse_maintenance=occurrence_repair_complete',
			) &&
			/[?&]wpse_occurrence_processed=1(?:&|$)/.test(
				occurrenceRepairLocation,
			) &&
			/[?&]wpse_occurrence_indexed=1(?:&|$)/.test(
				occurrenceRepairLocation,
			),
		'The bounded occurrence repair did not return privacy-safe completion counters.',
	);
	const occurrenceRepairFeedback = await fetchHealthyPage(
		new URL(
			occurrenceRepairLocation,
			'http://localhost:8888/wp-admin/',
		),
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		occurrenceRepairFeedback.includes(
			'Occurrence index maintenance inspected 1 events: 1 repaired, 0 invalid and 0 failed.',
		) && occurrenceRepairFeedback.includes( 'Healthy' ),
		'The occurrence repair did not return a healthy, privacy-safe administrator result.',
	);
	const repairedRecurrenceHealth = await authenticatedRequest(
		session,
		`/wp-json/wpse-smoke/v1/recurrence-health?event_id=${ recurrenceDraft.data.id }`,
	);
	requireCondition(
		repairedRecurrenceHealth.response.ok &&
			repairedRecurrenceHealth.data.dirty === false &&
			repairedRecurrenceHealth.data.aggregate_loaded === true &&
			[ smokeStartedOn, localDate( 0 ) ].includes(
				repairedRecurrenceHealth.data.coverage_from,
			) &&
			repairedRecurrenceHealth.data.coverage_through ===
				offsetIsoDate( repairedRecurrenceHealth.data.coverage_from, 540 ) &&
			repairedRecurrenceHealth.data.coverage_generation ===
				repairedRecurrenceHealth.data.generation &&
			repairedRecurrenceHealth.data.row_count === 10 &&
			repairedRecurrenceHealth.data.exact_found === true,
		`Type-aware administrator repair did not rebuild the recurring production horizon: ${ JSON.stringify( repairedRecurrenceHealth.data ) }`,
	);
	const bufferedRenewal = await authenticatedRequest(
		session,
		'/wp-json/wpse-smoke/v1/recurrence-renewal',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { event_id: recurrenceDraft.data.id } ),
		},
	);
	requireCondition(
		bufferedRenewal.response.ok &&
			bufferedRenewal.data.ready_before === true &&
			bufferedRenewal.data.processed === 1 &&
			bufferedRenewal.data.indexed === 1 &&
			bufferedRenewal.data.invalid === 0 &&
			bufferedRenewal.data.failed === 0 &&
			[ smokeStartedOn, localDate( 0 ) ].includes(
				bufferedRenewal.data.coverage_from,
			) &&
			bufferedRenewal.data.coverage_through ===
				offsetIsoDate( bufferedRenewal.data.coverage_from, 540 ) &&
			bufferedRenewal.data.coverage_generation > 0,
		`Buffered recurring projection renewal failed or disabled still-valid public coverage: ${ JSON.stringify( bufferedRenewal.data ) }`,
	);
	const generationCleanup = await authenticatedRequest(
		session,
		'/wp-json/wpse-smoke/v1/generation-cleanup',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( { event_id: recurrenceDraft.data.id } ),
		},
	);
	requireCondition(
		generationCleanup.response.ok &&
			generationCleanup.data.inactive_before >= 1 &&
			generationCleanup.data.dirty_removed === 0 &&
			generationCleanup.data.dirty_retained ===
				generationCleanup.data.inactive_before &&
			generationCleanup.data.clean_removed ===
				Math.min( 100, generationCleanup.data.inactive_before ) &&
			generationCleanup.data.inactive_after ===
				generationCleanup.data.inactive_before -
					generationCleanup.data.clean_removed &&
			generationCleanup.data.active_after >= 500 &&
			generationCleanup.data.active_after <= 1_000,
		`Inactive-generation cleanup was not bounded by age, active generation and dirty state: ${ JSON.stringify( generationCleanup.data ) }`,
	);
	const sitemapIndex = await fetchHealthyPage(
		'http://localhost:8888/wp-sitemap.xml',
	);
	requireCondition(
		sitemapIndex.includes( 'wp-sitemap-occurrences-1.xml' ),
		'The WordPress Core sitemap index omitted the occurrence provider.',
	);
	const occurrenceSitemap = await fetchHealthyPage(
		'http://localhost:8888/wp-sitemap-occurrences-1.xml',
	);
	requireCondition(
		recurrenceRows.every( ( row ) =>
			occurrenceSitemap.includes( row.public_key ),
		) &&
			! occurrenceSitemap.includes( '_wpse_recurrence_aggregate' ) &&
			! occurrenceSitemap.includes( '<lastmod>' ),
		'The bounded occurrence sitemap omitted active leaves or exposed protected/unsupported data.',
	);
	const occurrenceLeafUrl = new URL( publishedRecurrence.data.link );
	occurrenceLeafUrl.searchParams.set(
		'wpse_occurrence',
		recurrenceRows[ 0 ].public_key,
	);
	const occurrenceLeafRequest = await requestPage( occurrenceLeafUrl );
	const occurrenceLeaf = occurrenceLeafRequest.response;
	requireCondition(
		occurrenceLeaf.status === 200 &&
			! occurrenceLeafRequest.redirectTarget &&
			occurrenceLeaf.headers.get( 'cache-control' )?.includes( 'no-store' ),
		`An ordinary recurring-event save replaced or redirected its exact occurrence route: ${ JSON.stringify( {
			status: occurrenceLeaf.status,
			location: occurrenceLeafRequest.redirectTarget,
			redirectedBy: occurrenceLeaf.headers.get( 'x-redirect-by' ),
			cacheControl: occurrenceLeaf.headers.get( 'cache-control' ),
			url: occurrenceLeafUrl.toString(),
		} ) }`,
	);
	requireCondition(
		occurrenceLeafRequest.body.includes( 'class="wpse-single-event"' ) &&
			occurrenceLeafRequest.body.includes( 'class="wpse-event-date"' ) &&
			occurrenceLeafRequest.body.includes( 'wpse-event-field-block-event-title' ) &&
			occurrenceLeafRequest.body.includes( 'wpse-event-field-block-event-date-time' ) &&
			occurrenceLeafRequest.body.includes( '<script type="application/ld+json">' ) &&
			occurrenceLeafRequest.body.includes( 'rel="canonical"' ) &&
			occurrenceLeafRequest.body.includes( recurrenceRows[ 0 ].public_key ),
		`The exact occurrence leaf did not render native details, schema and its occurrence canonical: ${ JSON.stringify( {
			details: occurrenceLeafRequest.body.includes( 'class="wpse-single-event"' ),
			date: occurrenceLeafRequest.body.includes( 'class="wpse-event-date"' ),
			blockTitle: occurrenceLeafRequest.body.includes( 'wpse-event-field-block-event-title' ),
			blockDate: occurrenceLeafRequest.body.includes( 'wpse-event-field-block-event-date-time' ),
			schema: occurrenceLeafRequest.body.includes( '<script type="application/ld+json">' ),
			canonical: occurrenceLeafRequest.body.includes( 'rel="canonical"' ),
			key: occurrenceLeafRequest.body.includes( recurrenceRows[ 0 ].public_key ),
		} ) }`,
	);
	const upcomingOccurrenceArchive = await fetchHealthyPage(
		'http://localhost:8888/events/?wpse_period=upcoming',
	);
	const visibleArchiveRows = recurrenceRows.filter(
		( row ) => row.event_status !== 'cancelled',
	);
	const cancelledArchiveRows = recurrenceRows.filter(
		( row ) => row.event_status === 'cancelled',
	);
	requireCondition(
		visibleArchiveRows.length > 1 &&
			visibleArchiveRows.every( ( row ) =>
				upcomingOccurrenceArchive.includes( row.public_key ),
			) &&
			cancelledArchiveRows.every( ( row ) =>
				! upcomingOccurrenceArchive.includes( row.public_key ),
			),
		'The native upcoming archive collapsed repeated series occurrences or exposed a cancelled occurrence.',
	);
	for ( const seoPlugin of [ 'yoast', 'rank-math', 'aioseo' ] ) {
		const marker = new RegExp(
			`name="wpse-smoke-${ seoPlugin }-canonical" content="([^"]+)"`,
		).exec( occurrenceLeafRequest.body );

		requireCondition(
			marker?.[ 1 ]?.includes( recurrenceRows[ 0 ].public_key ),
			`The ${ seoPlugin } canonical filter did not use the exact occurrence URL.`,
		);
	}
	const occurrenceRest = await requestJson(
		`http://localhost:8888/wp-json/wpse/v2/events/${ recurrenceDraft.data.id }/occurrences/${ recurrenceRows[ 0 ].public_key }`,
	);
	const occurrenceRestShape = {
		response_ok: occurrenceRest.response.ok,
		schema_version: occurrenceRest.data.schema_version === 1,
		event_id: occurrenceRest.data.event_id === recurrenceDraft.data.id,
		occurrence_key:
			occurrenceRest.data.occurrence_key === recurrenceRows[ 0 ].public_key,
		canonical:
			typeof occurrenceRest.data.canonical_url === 'string' &&
			occurrenceRest.data.canonical_url.includes( recurrenceRows[ 0 ].public_key ),
		title:
			occurrenceRest.data.title === 'Private recurrence projection fixture',
		start_local:
			occurrenceRest.data.date?.start_local === recurrenceRows[ 0 ].start_local,
		timezone: occurrenceRest.data.date?.timezone === 'Europe/Brussels',
		status: occurrenceRest.data.status === recurrenceRows[ 0 ].event_status,
		optional_values:
			occurrenceRest.data.featured_image === null &&
			occurrenceRest.data.external_action === null,
	};
	requireCondition(
		Object.values( occurrenceRestShape ).every( Boolean ),
		`The exact occurrence REST leaf diverged from its public presentation (${ JSON.stringify( occurrenceRestShape ) }): ${ JSON.stringify( occurrenceRest.data ) }`,
	);
	const occurrenceRestJson = JSON.stringify( occurrenceRest.data );
	requireCondition(
		! occurrenceRestJson.includes( 'recurrence_id' ) &&
			! occurrenceRestJson.includes( 'generation' ) &&
			! occurrenceRestJson.includes( 'segment_id' ) &&
			! occurrenceRestJson.includes( '_wpse_' ) &&
			! occurrenceRestJson.includes( 'aggregate' ),
		'The public occurrence REST leaf exposed protected recurrence internals.',
	);
	const missingOccurrenceUrl = new URL( publishedRecurrence.data.link );
	missingOccurrenceUrl.searchParams.set(
		'wpse_occurrence',
		'ffffffffffffffffffffffffffffffff',
	);
	const missingOccurrenceRequest = await requestPage( missingOccurrenceUrl );
	const missingOccurrence = missingOccurrenceRequest.response;
	requireCondition(
		missingOccurrence.status === 404 &&
			! missingOccurrenceRequest.redirectTarget,
		'An unknown occurrence identity did not remain a non-redirecting 404.',
	);
	const missingOccurrenceRest = await requestJson(
		`http://localhost:8888/wp-json/wpse/v2/events/${ recurrenceDraft.data.id }/occurrences/ffffffffffffffffffffffffffffffff`,
	);
	requireCondition(
		missingOccurrenceRest.response.status === 404 &&
			missingOccurrenceRest.data.code === 'wpse_occurrence_not_found',
		'An unknown occurrence REST identity did not return the generic public 404.',
	);
	const occurrenceEditQuery = new URLSearchParams( {
		from_date: localDate( 30 ),
		through_date: localDate( 34 ),
		max_rows: '10',
		target: `${ localDate( 32 ) }T09:30:00`,
	} );
	const unauthorizedOccurrenceEdit = await requestJson(
		`http://localhost:8888/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/occurrence?${ occurrenceEditQuery.toString() }`,
	);
	requireCondition(
		[ 401, 403 ].includes( unauthorizedOccurrenceEdit.response.status ),
		'An unauthenticated request could read occurrence edit state.',
	);
	const occurrenceEditContext = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/occurrence?${ occurrenceEditQuery.toString() }`,
	);
	const occurrenceEditChecks = {
		response_ok: occurrenceEditContext.response.ok,
		target:
			occurrenceEditContext.data.target === `${ localDate( 32 ) }T09:30:00`,
		current_start:
			occurrenceEditContext.data.current?.start_local ===
			`${ localDate( 32 ) }T12:00:00`,
		current_status:
			occurrenceEditContext.data.current?.status === 'postponed',
		inherited_start:
			occurrenceEditContext.data.inherited?.start_local ===
			`${ localDate( 32 ) }T09:30:00`,
		inherited_status:
			occurrenceEditContext.data.inherited?.status === 'scheduled',
		override_start:
			occurrenceEditContext.data.override_fields?.date_range?.start_local ===
			`${ localDate( 32 ) }T12:00:00`,
		override_status:
			occurrenceEditContext.data.override_fields?.status === 'postponed',
		inherited_title:
			occurrenceEditContext.data.inherited_fields?.title ===
			'Private recurrence projection fixture',
		inherited_note:
			occurrenceEditContext.data.inherited_fields?.note === '',
		inherited_featured_image:
			occurrenceEditContext.data.inherited_fields?.featured_image_id === 0,
		inherited_location:
			occurrenceEditContext.data.inherited_fields?.venue === '' &&
			occurrenceEditContext.data.inherited_fields?.address === '' &&
			occurrenceEditContext.data.inherited_fields?.location_url === '' &&
			occurrenceEditContext.data.inherited_fields?.event_url === '' &&
			occurrenceEditContext.data.inherited_fields?.event_url_label === '',
		exclusion_action: occurrenceEditContext.data.exclusion_action === null,
		revision_type:
			typeof occurrenceEditContext.data.context?.revision === 'string',
		revision_length:
			occurrenceEditContext.data.context?.revision?.length === 64,
	};
	requireCondition(
		Object.values( occurrenceEditChecks ).every( Boolean ),
		`The occurrence edit context was incomplete (${ JSON.stringify( occurrenceEditChecks ) }): ${ JSON.stringify( occurrenceEditContext.data ) }`,
	);
	const onlyThisProposal = structuredClone(
		occurrenceEditContext.data.context.aggregate,
	);
	const onlyThisOverride = onlyThisProposal.overrides.find(
		( override ) => override.recurrence_id === occurrenceEditContext.data.target,
	);
	requireCondition(
		onlyThisOverride?.fields,
		'The occurrence edit fixture omitted its target override.',
	);
	onlyThisOverride.fields.title = 'Occurrence-only smoke title';
	const onlyThisMutation = {
		aggregate: onlyThisProposal,
		scope: 'only_this',
		target: occurrenceEditContext.data.target,
		revision: occurrenceEditContext.data.context.revision,
		...occurrenceEditContext.data.window,
	};
	const onlyThisPreview = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/preview`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( onlyThisMutation ),
		},
	);
	requireCondition(
		onlyThisPreview.response.ok &&
			onlyThisPreview.data.impact?.scope === 'only_this' &&
			onlyThisPreview.data.impact?.target === occurrenceEditContext.data.target &&
			onlyThisPreview.data.impact?.exception_affected === 1 &&
			onlyThisPreview.data.impact?.items?.length === 1 &&
			typeof onlyThisPreview.data.confirmation === 'string',
		`The occurrence-only preview was incomplete: ${ JSON.stringify( onlyThisPreview.data ) }`,
	);
	const onlyThisSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				...onlyThisMutation,
				confirmation: onlyThisPreview.data.confirmation,
			} ),
		},
	);
	requireCondition(
		onlyThisSave.response.ok &&
			onlyThisSave.data.changed === true &&
			onlyThisSave.data.context?.revision !== occurrenceEditContext.data.context.revision,
		`The confirmed occurrence-only save failed: ${ JSON.stringify( onlyThisSave.data ) }`,
	);
	const savedOccurrenceEditContext = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/occurrence?${ occurrenceEditQuery.toString() }`,
	);
	requireCondition(
		savedOccurrenceEditContext.response.ok &&
			savedOccurrenceEditContext.data.override_fields?.title === 'Occurrence-only smoke title' &&
			savedOccurrenceEditContext.data.current?.start_local === `${ localDate( 32 ) }T12:00:00` &&
			savedOccurrenceEditContext.data.context?.revision === onlyThisSave.data.context.revision,
		`The saved occurrence-only state could not be reloaded: ${ JSON.stringify( savedOccurrenceEditContext.data ) }`,
	);
	const followingTarget = `${ localDate( 33 ) }T09:30:00`;
	const followingMutation = {
		target: followingTarget,
		revision: onlyThisSave.data.context.revision,
		from_date: localDate( 30 ),
		through_date: localDate( 37 ),
		max_rows: 10,
		replacement: {
			template: {
				start_local: `${ localDate( 33 ) }T10:30:00`,
				end_local: `${ localDate( 33 ) }T12:00:00`,
				all_day: false,
			},
			definition: {
				type: 'rule',
				frequency: 'daily',
				interval: 2,
				end: { mode: 'count', count: 2 },
			},
		},
	};
	const unauthorizedFollowingPreview = await requestJson(
		`http://localhost:8888/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/following/preview`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( followingMutation ),
		},
	);
	requireCondition(
		[ 401, 403 ].includes( unauthorizedFollowingPreview.response.status ),
		'An unauthenticated request could preview a following-scope schedule split.',
	);
	const followingPreview = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/following/preview`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( followingMutation ),
		},
	);
	requireCondition(
		followingPreview.response.ok &&
			followingPreview.data.impact?.scope === 'this_and_following' &&
			followingPreview.data.impact?.target === followingTarget &&
			followingPreview.data.proposal?.segments?.length === 2 &&
			followingPreview.data.proposal?.overrides?.some(
				( override ) =>
					override.recurrence_id === occurrenceEditContext.data.target &&
					override.fields?.title === 'Occurrence-only smoke title',
			) &&
			followingPreview.data.proposal?.manuals?.some(
				( manual ) =>
					manual.recurrence_id === `${ localDate( 34 ) }T09:30:00` &&
					manual.status === 'scheduled',
			) &&
			followingPreview.data.proposal?.exclusions?.some(
				( exclusion ) =>
					exclusion.recurrence_id === `${ localDate( 34 ) }T09:30:00` &&
					exclusion.action === 'cancel',
			) &&
			typeof followingPreview.data.confirmation === 'string',
		`The server-built following preview was incomplete: ${ JSON.stringify( followingPreview.data ) }`,
	);
	const followingSaveMutation = {
		aggregate: followingPreview.data.proposal,
		scope: 'this_and_following',
		target: followingTarget,
		revision: followingMutation.revision,
		from_date: followingMutation.from_date,
		through_date: followingMutation.through_date,
		max_rows: followingMutation.max_rows,
		confirmation: followingPreview.data.confirmation,
	};
	const followingSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( followingSaveMutation ),
		},
	);
	requireCondition(
		followingSave.response.ok &&
			followingSave.data.changed === true &&
			followingSave.data.context?.revision !== followingMutation.revision,
		`The confirmed following-scope save failed: ${ JSON.stringify( followingSave.data ) }`,
	);
	const staleFollowingSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( followingSaveMutation ),
		},
	);
	requireCondition(
		staleFollowingSave.response.status === 409 &&
			staleFollowingSave.data.code === 'wpse_recurrence_stale_revision',
		'A saved following-scope confirmation could be replayed.',
	);
	const disableContext = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence`,
	);
	const occurrenceQuery = new URLSearchParams( {
		from_date: localDate( 30 ),
		through_date: localDate( 34 ),
		max_rows: '10',
	} );
	const survivorChoices = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/occurrences?${ occurrenceQuery.toString() }`,
	);
	const survivor = survivorChoices.data.occurrences?.[ 0 ];
	requireCondition(
		disableContext.response.ok &&
			disableContext.data.recurring === true &&
			survivorChoices.response.ok &&
			typeof survivor?.recurrence_id === 'string',
		`The recurrence-disable survivor choices were incomplete: ${ JSON.stringify( survivorChoices.data ) }`,
	);
	const disableMutation = {
		target: survivor.recurrence_id,
		revision: disableContext.data.revision,
		from_date: localDate( 30 ),
		through_date: localDate( 34 ),
		max_rows: 10,
	};
	const disablePreview = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/disable/preview`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( disableMutation ),
		},
	);
	requireCondition(
		disablePreview.response.ok &&
			disablePreview.data.survivor?.recurrence_id === survivor.recurrence_id &&
			disablePreview.data.impact?.outside_window_removed === true &&
			disablePreview.data.impact?.source_changed === 1 &&
			typeof disablePreview.data.confirmation === 'string',
		`The recurrence-disable preview was incomplete: ${ JSON.stringify( disablePreview.data ) }`,
	);
	const disableSave = await authenticatedRequest(
		session,
		`/wp-json/wpse/v1/events/${ recurrenceDraft.data.id }/recurrence/disable/save`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				...disableMutation,
				confirmation: disablePreview.data.confirmation,
			} ),
		},
	);
	requireCondition(
		disableSave.response.ok &&
			disableSave.data.changed === true &&
			disableSave.data.context?.recurring === false &&
			disableSave.data.context?.aggregate?.segments?.[ 0 ]?.template
				?.start_local === survivor.start_local,
		`The confirmed recurrence-disable conversion failed: ${ JSON.stringify( disableSave.data ) }`,
	);
	const recurrenceDelete = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ recurrenceDraft.data.id }?force=true`,
		{ method: 'DELETE' },
	);
	requireCondition(
		recurrenceDelete.response.ok,
		'The private recurrence projection fixture could not be removed.',
	);

	const protectedSingleBody = await fetchHealthyPage( protectedCreate.data.link );
	requireCondition(
		protectedSingleBody.includes( 'post-password-form' ),
		`A protected event did not render its password form (title: ${ protectedSingleBody.includes( 'Protected smoke event' ) }, summary: ${ protectedSingleBody.includes( 'wpse-event-summary' ) }, login: ${ protectedSingleBody.includes( 'wp-login.php' ) }).`,
	);
	requireCondition( ! protectedSingleBody.includes( 'wpse-event-summary' ), 'A protected event leaked event metadata.' );
	requireCondition(
		! protectedSingleBody.includes( '"@type":"Event"' ),
		'A protected event leaked Event structured data.',
	);

	const enableTimezoneDisplay = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'events',
				wpse_archive_per_page: '10',
				wpse_archive_default_period: 'upcoming',
				wpse_show_event_timezone: '1',
				wpse_structured_data_enabled: '1',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		enableTimezoneDisplay.status === 302,
		'The public timezone setting could not be enabled through the protected settings form.',
	);
	const timezoneSingleBody = await fetchHealthyPage( validCreate.data.link );
	requireCondition(
		timezoneSingleBody.includes( 'class="wpse-event-timezone"' ) &&
			timezoneSingleBody.includes( 'Europe/Brussels (UTC+02:00)' ),
		'Enabled timed event details omitted their captured timezone or event-date offset.',
	);
	requireCondition(
		/"startDate":"[^"]+\+02:00"/.test( timezoneSingleBody ),
		'The visual timezone setting changed or removed the structured-data machine instant.',
	);

	const disableStructuredData = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'events',
				wpse_archive_per_page: '10',
				wpse_archive_default_period: 'upcoming',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		disableStructuredData.status === 302,
		'The structured-data setting could not be saved through the protected settings form.',
	);
	const disabledSchemaBody = await fetchHealthyPage( validCreate.data.link );
	requireCondition(
		! disabledSchemaBody.includes( '"@type":"Event"' ),
		'Disabling structured data did not suppress the Event JSON-LD.',
	);
	requireCondition(
		! disabledSchemaBody.includes( 'wpse-event-timezone' ),
		'Disabling public timezone presentation did not restore the previous event output.',
	);

	const shortcodePage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Event shortcode smoke page',
				status: 'publish',
				content: '[wpse_events view="list" period="upcoming" limit="10" filters="true" show_image="false"]',
			} ),
		},
	);
	requireCondition( shortcodePage.response.status === 201, 'The shortcode smoke page could not be created.' );

	const shortcodeBody = await fetchHealthyPage( shortcodePage.data.link );
	requireCondition( shortcodeBody.includes( 'wpse-events-1' ), 'The event shortcode did not render its stable instance.' );
	requireCondition( shortcodeBody.includes( 'wpse-events-filters' ), 'The event shortcode filters did not render.' );
	requireCondition( shortcodeBody.includes( 'Ongoing smoke event' ), 'The shortcode excluded an active event.' );
	requireCondition( shortcodeBody.includes( 'Future smoke event' ), 'The shortcode excluded an upcoming event.' );
	requireCondition( ! shortcodeBody.includes( 'Past smoke event' ), 'The upcoming shortcode exposed a past event.' );
	requireCondition( ! shortcodeBody.includes( 'Protected smoke event' ), 'The shortcode exposed a password-protected event.' );
	requireCondition( shortcodeBody.includes( 'wpse-frontend-css' ), 'The shortcode stylesheet was not enqueued.' );

	const pastUrl = new URL( shortcodePage.data.link );
	pastUrl.searchParams.set( 'wpse_1_period', 'past' );
	const pastShortcodeBody = await fetchHealthyPage( pastUrl );
	requireCondition( pastShortcodeBody.includes( 'Past smoke event' ), 'The past filter did not show past events.' );
	requireCondition( ! pastShortcodeBody.includes( 'Ongoing smoke event' ), 'The past filter exposed an active event.' );

	const emptyUrl = new URL( shortcodePage.data.link );
	emptyUrl.searchParams.set( 'wpse_1_category', 'missing-smoke-category' );
	const emptyShortcodeBody = await fetchHealthyPage( emptyUrl );
	requireCondition(
		emptyShortcodeBody.includes( 'No events match your selection.' ),
		'The shortcode did not render its empty state.',
	);

	const multiShortcodePage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Multiple event shortcodes smoke page',
				status: 'publish',
				content: '[wpse_events limit="1" pagination="true"][wpse_events period="past" limit="1" pagination="true"]',
			} ),
		},
	);
	requireCondition( multiShortcodePage.response.status === 201, 'The multiple-shortcode smoke page could not be created.' );
	const multiShortcodeBody = await fetchHealthyPage( multiShortcodePage.data.link );
	requireCondition( multiShortcodeBody.includes( 'wpse-events-1' ), 'The first shortcode instance is missing.' );
	requireCondition( multiShortcodeBody.includes( 'wpse-events-2' ), 'The second shortcode instance is missing.' );
	requireCondition( multiShortcodeBody.includes( 'wpse_1_page' ), 'The first shortcode pagination is not namespaced.' );

	const detailsShortcodePage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Event details shortcode smoke page',
				status: 'publish',
				content: `[wpse_event_details id="${ eventId }"][wpse_event_details id="${ eventId }"][wpse_event_details id="${ protectedCreate.data.id }"][wpse_event_details id="${ draftCreate.data.id }"][wpse_event_details id="1 OR 1=1"]`,
			} ),
		},
	);
	requireCondition( detailsShortcodePage.response.status === 201, 'The details-shortcode smoke page could not be created.' );
	const detailsShortcodeBody = await fetchHealthyPage( detailsShortcodePage.data.link );
	requireCondition( detailsShortcodeBody.includes( 'Future smoke event' ), 'The details shortcode omitted a public event.' );
	requireCondition(
		( detailsShortcodeBody.match( /class="wpse-single-event"/g ) ?? [] ).length === 2,
		'Multiple details shortcodes did not render as isolated instances.',
	);
	requireCondition(
		detailsShortcodeBody.includes( `id="wpse-event-title-${ eventId }-1"` ) &&
			detailsShortcodeBody.includes( `id="wpse-event-title-${ eventId }-2"` ),
		'Multiple details shortcodes reused a heading ID.',
	);
	requireCondition( ! detailsShortcodeBody.includes( 'Protected smoke event' ), 'The details shortcode exposed a protected event.' );
	requireCondition( ! detailsShortcodeBody.includes( 'Incomplete draft smoke event' ), 'The details shortcode exposed a draft event.' );
	requireCondition( detailsShortcodeBody.includes( 'wpse-frontend-css' ), 'The details shortcode stylesheet was not enqueued.' );

	const calendarPage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Event calendar smoke page',
				status: 'publish',
				content: '[wpse_calendar filters="true"][wpse_calendar initial_view="list" category="calendar-smoke"]',
			} ),
		},
	);
	requireCondition( calendarPage.response.status === 201, 'The calendar smoke page could not be created.' );
	const calendarBody = await fetchHealthyPage( calendarPage.data.link );
	requireCondition( calendarBody.includes( 'wpse-calendar-1' ), 'The first calendar instance is missing.' );
	requireCondition( calendarBody.includes( 'wpse-calendar-2' ), 'The second calendar instance is missing.' );
	requireCondition( calendarBody.includes( 'data-wpse-calendar' ), 'The calendar enhancement configuration is missing.' );
	requireCondition( calendarBody.includes( 'wpse-calendar-fallback' ), 'The no-JavaScript event fallback is missing.' );
	requireCondition( calendarBody.includes( 'Future smoke event' ), 'The calendar fallback excluded an upcoming event.' );
	requireCondition( ! calendarBody.includes( 'Protected smoke event' ), 'The calendar fallback exposed a protected event.' );
	requireCondition( calendarBody.includes( 'wpse-calendar-js' ), 'The local calendar bundle was not enqueued.' );
	requireCondition( calendarBody.includes( 'assets/dist/js/calendar.min.js' ), 'The calendar did not use its local production bundle.' );
	requireCondition(
		( calendarBody.match( /id=["']wpse-calendar-js["']/g ) ?? [] ).length === 1,
		'Multiple calendars enqueued duplicate bundles.',
	);

	const conflictingArchivePage = await authenticatedRequest(
		session,
		'/wp-json/wp/v2/pages',
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				title: 'Archive conflict smoke page',
				slug: 'event-page-conflict',
				status: 'publish',
				content: '<p>Archive conflict page marker.</p>',
			} ),
		},
	);
	requireCondition(
		conflictingArchivePage.response.status === 201,
		'The archive conflict fixture page could not be created.',
	);

	const conflictingArchiveSave = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'event-page-conflict',
				wpse_archive_per_page: '2',
				wpse_archive_default_period: 'all',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		conflictingArchiveSave.status === 302,
		'The conflicting archive configuration could not be saved.',
	);
	const conflictSettingsBody = await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		conflictSettingsBody.includes(
			'The event archive and an existing WordPress page both use /event-page-conflict/.',
		),
		'The settings page did not diagnose the archive/page slug conflict.',
	);

	const customArchiveSave = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'community-events',
				wpse_archive_per_page: '1',
				wpse_archive_default_period: 'all',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		customArchiveSave.status === 302,
		'The custom archive configuration could not be saved.',
	);
	await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	const customArchiveBody = await fetchHealthyPage(
		'http://localhost:8888/community-events/',
	);
	requireCondition(
		customArchiveBody.includes( 'Past smoke event' ) &&
			! customArchiveBody.includes( 'Future smoke event' ) &&
			customArchiveBody.includes( 'wpse-events-pagination' ),
		'The custom archive did not apply its all-events default and one-event page size.',
	);
	requireCondition(
		/<option\s+value=['"]all['"][^>]*\sselected(?:=['"]selected['"])?[^>]*>/.test(
			customArchiveBody,
		),
		'The archive filter did not reflect the configured all-events default.',
	);
	const customUpcomingBody = await fetchHealthyPage(
		'http://localhost:8888/community-events/?wpse_period=upcoming',
	);
	requireCondition(
		! customUpcomingBody.includes( 'Past smoke event' ),
		'An explicit upcoming filter did not override the configured archive default.',
	);

	const restoreArchiveSave = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'events',
				wpse_archive_per_page: '10',
				wpse_archive_default_period: 'upcoming',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		restoreArchiveSave.status === 302,
		'The default archive configuration could not be restored.',
	);
	await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	await fetchHealthyPage( 'http://localhost:8888/events/' );

	if ( process.argv.includes( '--pause-for-browser' ) ) {
		process.stdout.write( `Browser fixture ready at ${ calendarPage.data.link }\nPress Enter to clean up and stop WordPress.\n` );
		await new Promise( ( resolve ) => process.stdin.once( 'data', resolve ) );
		process.stdin.pause();
	}

	const enableUninstallCleanup = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'events',
				wpse_archive_per_page: '10',
				wpse_archive_default_period: 'upcoming',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '1',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		enableUninstallCleanup.status === 302,
		'The uninstall cleanup preference could not be saved through the protected settings form.',
	);
	const armedSettingsBody = await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		/<input[^>]+id="wpse_delete_data_on_uninstall"[^>]+checked=['"]checked['"]/.test( armedSettingsBody ),
		'The settings page did not reflect the explicit uninstall cleanup opt-in.',
	);

	const disableUninstallCleanup = await fetch(
		'http://localhost:8888/wp-admin/options.php',
		{
			method: 'POST',
			headers: {
				'content-type': 'application/x-www-form-urlencoded',
				cookie: cookieHeader( session.cookieJar ),
			},
			body: new URLSearchParams( {
				option_page: 'wpse_settings',
				action: 'update',
				_wpnonce: settingsNonce,
				_wp_http_referer: '/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
				wpse_archive_slug: 'events',
				wpse_archive_per_page: '10',
				wpse_archive_default_period: 'upcoming',
				wpse_show_event_timezone: '0',
				wpse_structured_data_enabled: '0',
				wpse_delete_data_on_uninstall: '0',
			} ),
			redirect: 'manual',
		},
	);
	requireCondition(
		disableUninstallCleanup.status === 302,
		'The uninstall cleanup preference could not be disabled again.',
	);
	const disarmedSettingsBody = await fetchHealthyPage(
		'http://localhost:8888/wp-admin/edit.php?post_type=wpse_event&page=wpse-settings',
		{ headers: { cookie: cookieHeader( session.cookieJar ) } },
	);
	requireCondition(
		! /<input[^>]+id="wpse_delete_data_on_uninstall"[^>]+checked=['"]checked['"]/.test( disarmedSettingsBody ),
		'The settings page retained the destructive opt-in after it was disabled.',
	);
	const retainedEventBody = await fetchHealthyPage( validCreate.data.link );
	requireCondition(
		retainedEventBody.includes( 'Future smoke event' ),
		'Changing the future uninstall preference altered current event content.',
	);

	const resources = [
		[ 'wpse_event', eventId ],
		[ 'wpse_event', ongoingCreate.data.id ],
		[ 'wpse_event', pastCreate.data.id ],
		[ 'wpse_event', protectedCreate.data.id ],
		[ 'wpse_event', draftCreate.data.id ],
		[ 'wpse_event', duplicatedEventId ],
		[ 'pages', shortcodePage.data.id ],
		[ 'pages', multiShortcodePage.data.id ],
		[ 'pages', detailsShortcodePage.data.id ],
		[ 'pages', calendarPage.data.id ],
		[ 'pages', conflictingArchivePage.data.id ],
		[ 'wpse_event_category', calendarCategory.data.id ],
		[ 'wpse_event_category', archiveCategory.data.id ],
		[ 'wpse_event_tag', blockTag.data.id ],
	];

	for ( const [ resource, id ] of resources ) {
		await authenticatedRequest(
			session,
			`/wp-json/wp/v2/${ resource }/${ id }?force=true`,
			{ method: 'DELETE' },
		);
	}
} finally {
	if ( keepFailedEnvironment ) {
		process.stderr.write( `Retained smoke environment at ${ smokeWpEnvHome }.\n` );
	} else {
		await runWpEnv( [ 'stop' ], { allowFailure: true } );
		await rm( smokeConfigDirectory, { force: true, recursive: true } );
		await rm( smokeWpEnvHome, { force: true, recursive: true } );
	}
}
