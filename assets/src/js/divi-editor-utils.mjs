/**
 * Namespace server-rendered IDs inside one independently fetched Divi preview.
 *
 * Shared renderers already create unique IDs within a normal WordPress request.
 * Divi fetches each module preview separately, so two responses can both start
 * at instance one. This editor-only transform preserves every local reference
 * while preventing duplicate IDs when multiple previews share one canvas.
 *
 * @param {string} html      Escaped server-rendered component HTML.
 * @param {string} namespace Stable Divi module instance identifier.
 * @return {string} Namespaced HTML.
 */
export const namespacePreviewHtml = ( html, namespace ) => {
	if ( typeof html !== 'string' || ! html ) {
		return '';
	}

	const rawNamespace = String( namespace ?? '' );
	let namespaceHash = 0;

	for ( let index = 0; index < rawNamespace.length; index++ ) {
		namespaceHash =
			( ( namespaceHash * 131 ) + rawNamespace.charCodeAt( index ) ) %
			2147483647;
	}

	const safeNamespace = rawNamespace
		.replace( /[^A-Za-z0-9_-]+/g, '-' )
		.replace( /^[-_]+|[-_]+$/g, '' )
		.slice( 0, 56 ) || 'module';
	const uniqueNamespace = `${ safeNamespace }-${ namespaceHash.toString( 36 ) }`;
	const prefix = `wpse-divi-${ uniqueNamespace }-`;
	const idMap = new Map();

	for ( const match of html.matchAll( /\bid="([A-Za-z][A-Za-z0-9_:.-]*)"/g ) ) {
		idMap.set( match[ 1 ], `${ prefix }${ match[ 1 ] }` );
	}

	if ( idMap.size === 0 ) {
		return html;
	}

	return html
		.replace( /\bid="([A-Za-z][A-Za-z0-9_:.-]*)"/g, ( match, id ) =>
			idMap.has( id ) ? `id="${ idMap.get( id ) }"` : match,
		)
		.replace(
			/\b(for|aria-labelledby|aria-describedby|aria-controls)="([^"]*)"/g,
			( match, attribute, references ) => {
				const values = references.split( /\s+/ ).filter( Boolean );

				if ( ! values.some( ( value ) => idMap.has( value ) ) ) {
					return match;
				}

				return `${ attribute }="${ values
					.map( ( value ) => idMap.get( value ) ?? value )
					.join( ' ' ) }"`;
			},
		)
		.replace( /\bhref="#([A-Za-z][A-Za-z0-9_:.-]*)"/g, ( match, id ) =>
			idMap.has( id ) ? `href="#${ idMap.get( id ) }"` : match,
		);
};
