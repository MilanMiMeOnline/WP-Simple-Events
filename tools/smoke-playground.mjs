import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { join, resolve as resolvePath } from 'node:path';

import {
	pluginActionUrl,
	pluginFileFromPath,
} from './smoke-contract.mjs';

const projectDirectory = fileURLToPath( new URL( '..', import.meta.url ) );
const requestedCore = process.env.WPSE_SMOKE_CORE;
const smokeIdentifier = ( requestedCore ?? 'configured' ).replace(
	/[^a-z0-9.-]+/gi,
	'-',
);
const smokeWpEnvHome = join(
	tmpdir(),
	`wp-simple-events-smoke-wp-env-${ smokeIdentifier }`,
);
const smokeConfigDirectory = join(
	tmpdir(),
	`wp-simple-events-smoke-config-${ smokeIdentifier }`,
);
const smokePluginPath = resolvePath(
	projectDirectory,
	process.env.WPSE_SMOKE_PLUGIN_PATH ?? '.',
);
const smokePluginFile = pluginFileFromPath( smokePluginPath );
const wpEnvExecutable = fileURLToPath(
	new URL( '../node_modules/.bin/wp-env', import.meta.url ),
);

async function prepareSmokeConfiguration() {
	const configuration = JSON.parse(
		await readFile( join( projectDirectory, '.wp-env.json' ), 'utf8' ),
	);

	configuration.plugins = [ smokePluginPath ];

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

async function fetchHealthyPage( url, options = {} ) {
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

async function authenticateAdministrator() {
	const cookieJar = new Map();
	const loginUrl = 'http://localhost:8888/wp-login.php';
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

	const nonceResponse = await fetch(
		'http://localhost:8888/wp-admin/admin-ajax.php?action=rest-nonce',
		{ headers: { cookie: cookieHeader( cookieJar ) } },
	);
	const nonce = await nonceResponse.text();

	requireCondition(
		nonceResponse.ok && nonce.length >= 10,
		'The smoke test could not obtain a REST nonce.',
	);

	return { cookieJar, nonce };
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
	await fetchHealthyPage( 'http://localhost:8888/' );
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
			content: '<p>Single event body marker.</p>[wpse_event_details]',
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
		'A rejected REST update corrupted existing event data.',
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

	const categorizedEvent = await authenticatedRequest(
		session,
		`/wp-json/wp/v2/wpse_event/${ eventId }`,
		{
			method: 'POST',
			headers: { 'content-type': 'application/json' },
			body: JSON.stringify( {
				wpse_event_category: [ calendarCategory.data.id ],
			} ),
		},
	);
	requireCondition( categorizedEvent.response.ok, 'The event could not be assigned to its calendar category.' );

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

	const singleBody = await fetchHealthyPage( validCreate.data.link );
	const singleArticleStart = singleBody.indexOf( '<article class="wpse-single-event"' );
	const singleArticleEnd = singleBody.indexOf( '</article>', singleArticleStart );
	requireCondition(
		singleArticleStart >= 0 && singleArticleEnd > singleArticleStart,
		'The native single event article is unavailable.',
	);
	const singleArticle = singleBody.slice( singleArticleStart, singleArticleEnd );
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
	];

	for ( const [ resource, id ] of resources ) {
		await authenticatedRequest(
			session,
			`/wp-json/wp/v2/${ resource }/${ id }?force=true`,
			{ method: 'DELETE' },
		);
	}
} finally {
	await runWpEnv( [ 'stop' ], { allowFailure: true } );
	await rm( smokeConfigDirectory, { force: true, recursive: true } );
	await rm( smokeWpEnvHome, { force: true, recursive: true } );
}
