/**
 * Recognize only WordPress' pre-bootstrap scheduled-maintenance response.
 *
 * Retrying this page is safe for a state-changing route because WordPress did
 * not load the REST API or execute the requested callback.
 *
 * @param {number} status      HTTP status.
 * @param {string} contentType Response Content-Type header.
 * @param {string} body        Response body.
 * @return {boolean} Whether the request stopped at WordPress maintenance mode.
 */
export function isTransientWordPressMaintenance( status, contentType, body ) {
	if ( status !== 503 || ! contentType.toLowerCase().includes( 'text/html' ) ) {
		return false;
	}

	return (
		/<title>\s*Maintenance\s*<\/title>/i.test( body ) ||
		/Briefly unavailable for scheduled maintenance/i.test( body )
	);
}
