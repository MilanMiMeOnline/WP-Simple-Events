import { initializeCalendars } from './calendar.js';
import { namespacePreviewHtml } from './divi-editor-utils.mjs';

/* global WPSE_DIVI_COMPOSITE_MODULES, WPSE_DIVI_EVENT_TITLE_METADATA, WPSE_DIVI_MODULE_FIELDS, WPSE_DIVI_MODULE_METADATA */

const metadataSource = WPSE_DIVI_EVENT_TITLE_METADATA;
const moduleMetadataSource = WPSE_DIVI_MODULE_METADATA;
const moduleFields = WPSE_DIVI_MODULE_FIELDS;
const compositeModules = WPSE_DIVI_COMPOSITE_MODULES;

( () => {
	const React = window.vendor?.React;
	const addAction = window.vendor?.wp?.hooks?.addAction;
	const moduleApi = window.divi?.module;
	const registerModule = window.divi?.moduleLibrary?.registerModule;
	const useFetch = window.divi?.rest?.useFetch;

	if ( ! React || ! addAction || ! moduleApi || ! registerModule ) {
		return;
	}

	const data = window.WpseDiviModulesData ?? {
		current: null,
		events: {},
		labels: {},
	};
	const translateMetadata = ( value ) => {
		if ( Array.isArray( value ) ) {
			return value.map( translateMetadata );
		}

		if ( value && typeof value === 'object' ) {
			return Object.fromEntries(
				Object.entries( value ).map( ( [ key, child ] ) => [
					key,
					translateMetadata( child ),
				] ),
			);
		}

		if ( typeof value === 'string' && data.translations?.[ value ] ) {
			return data.translations[ value ];
		}

		return value;
	};
	// Module metadata is JSON-only. This clone works on every browser supported by
	// WordPress 6.9 without sharing mutable option state between registrations.
	const metadata = translateMetadata(
		JSON.parse( JSON.stringify( metadataSource ) ),
	);
	const eventOptions = {
		0: {
			label: data.labels?.currentEvent ?? 'Current event',
		},
	};

	Object.entries( data.events ?? {} ).forEach( ( [ id, event ] ) => {
		eventOptions[ id ] = {
			label: event?.title || `Event ${ id }`,
		};
	} );

	metadata.attributes.event.settings.innerContent.items.eventId.component.props.options =
		eventOptions;

	const readValue = ( attrs, key, fallback ) =>
		attrs?.event?.innerContent?.desktop?.value?.[ key ] ?? fallback;
	const enabled = ( attrs, key, fallback = false ) => {
		const value = readValue( attrs, key, fallback ? 'on' : 'off' );

		if ( value === 'on' ) {
			return true;
		}

		if ( value === 'off' ) {
			return false;
		}

		return fallback;
	};
	const fieldText = ( attrs, key ) => {
		const value = readValue( attrs, key, '' );

		return typeof value === 'string' ? value.slice( 0, 120 ) : '';
	};
	const selectedEvent = ( attrs ) => {
		const eventId = String( readValue( attrs, 'eventId', '0' ) );

		return eventId === '0' ? data.current : data.events?.[ eventId ];
	};

	const headingLevel = ( attrs ) => {
		const heading =
			attrs?.title?.decoration?.font?.font?.desktop?.value?.headingLevel ??
			'h2';

		return [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( heading )
			? heading
			: 'h2';
	};

	const ModuleStyles = ( props ) => {
		const {
			attrs,
			elements,
			settings,
			mode,
			state,
			noStyleTag,
		} = props;

		return React.createElement(
			moduleApi.StyleContainer,
			{ mode, state, noStyleTag },
			elements.style( {
				attrName: 'module',
				styleProps: {
					disabledOn: {
						disabledModuleVisibility:
							settings?.disabledModuleVisibility,
					},
					advancedStyles: [
						{
							componentName: 'divi/text',
							props: {
								selector: `${ props.orderClass } .wpse-single-event-title`,
								attr: attrs?.module?.advanced?.text ?? {},
							},
						},
					],
				},
			} ),
			elements.style( { attrName: 'title' } ),
		);
	};

	const ModuleScriptData = ( { elements } ) =>
		React.createElement(
			React.Fragment,
			null,
			elements.scriptData( { attrName: 'module' } ),
		);

	const EventTitleEdit = ( props ) => {
		const { attrs, elements, id, name } = props;
		const event = selectedEvent( attrs );
		const Heading = headingLevel( attrs );
		let title = event?.title ?? data.labels?.noEvent ?? 'No event available.';

		if ( event && readValue( attrs, 'linkTitle', 'off' ) === 'on' ) {
			title = React.createElement(
				'a',
				{
					href: event.url || '#',
					onClick: ( clickEvent ) => clickEvent.preventDefault(),
				},
				title,
			);
		}

		return React.createElement(
			moduleApi.ModuleContainer,
			{
				attrs,
				elements,
				id,
				name,
				stylesComponent: ModuleStyles,
				scriptDataComponent: ModuleScriptData,
			},
			elements.styleComponents( { attrName: 'module' } ),
			React.createElement( moduleApi.ElementComponents, {
				attrs: attrs?.module?.decoration ?? {},
				id,
			} ),
			React.createElement(
				Heading,
				{ className: 'wpse-single-event-title' },
				title,
			),
		);
	};

	const AtomicModuleStyles = ( props ) => {
		const { elements, settings, mode, state, noStyleTag } = props;

		return React.createElement(
			moduleApi.StyleContainer,
			{ mode, state, noStyleTag },
			elements.style( {
				attrName: 'module',
				styleProps: {
					disabledOn: {
						disabledModuleVisibility:
							settings?.disabledModuleVisibility,
					},
				},
			} ),
			elements.style( { attrName: 'field' } ),
		);
	};

	const CompositeModuleStyles = ( props ) => {
		const { elements, settings, mode, state, noStyleTag } = props;

		return React.createElement(
			moduleApi.StyleContainer,
			{ mode, state, noStyleTag },
			elements.style( {
				attrName: 'module',
				styleProps: {
					disabledOn: {
						disabledModuleVisibility:
							settings?.disabledModuleVisibility,
					},
				},
			} ),
			elements.style( { attrName: 'content' } ),
		);
	};

	const linkProps = ( url ) => ( {
		href: url || '#',
		onClick: ( clickEvent ) => clickEvent.preventDefault(),
	} );

	const labelledText = ( attrs, key, fallback ) =>
		fieldText( attrs, key ) || fallback;

	const renderTerms = ( attrs, event, key, className, fallbackLabel ) => {
		const terms = Array.isArray( event?.[ key ] ) ? event[ key ] : [];

		if ( terms.length === 0 ) {
			return null;
		}

		const children = [];

		if ( enabled( attrs, 'showLabel', true ) ) {
			children.push(
				React.createElement(
					'span',
					{ className: 'wpse-event-label', key: 'label' },
					labelledText( attrs, 'label', fallbackLabel ),
				),
				' ',
			);
		}

		terms.forEach( ( term, index ) => {
			if ( index > 0 ) {
				children.push(
					React.createElement(
						'span',
						{ 'aria-hidden': 'true', key: `separator-${ index }` },
						', ',
					),
				);
			}

			children.push(
				React.createElement(
					'a',
					{ ...linkProps( term?.url ), key: `${ term?.url }-${ index }` },
					term?.name ?? '',
				),
			);
		} );

		return React.createElement( 'p', { className }, children );
	};

	const renderAtomicField = ( field, attrs, event ) => {
		if ( ! event ) {
			return null;
		}

		switch ( field ) {
			case 'featured_image': {
				const requestedSize = fieldText( attrs, 'imageSize' ) || 'large';
				const image = event.images?.[ requestedSize ] ?? event.images?.large;

				if ( ! image?.url ) {
					return null;
				}

				let output = React.createElement( 'img', {
					src: image.url,
					alt:
						fieldText( attrs, 'altMode' ) === 'decorative'
							? ''
							: image.alt ?? '',
				} );

				if ( enabled( attrs, 'linkField' ) ) {
					output = React.createElement(
						'a',
						{ ...linkProps( event.url ), className: 'wpse-event-image-link' },
						output,
					);
				}

				return React.createElement(
					'div',
					{ className: 'wpse-single-event-image' },
					output,
				);
			}
			case 'date_time':
				return event.date
					? React.createElement(
						'p',
						{ className: 'wpse-event-date' },
						enabled( attrs, 'showLabel', true )
							? React.createElement(
								React.Fragment,
								null,
								React.createElement(
									'span',
									{ className: 'wpse-event-label' },
									labelledText( attrs, 'label', data.labels?.dateTime ),
								),
								' ',
							)
							: null,
						React.createElement(
							'time',
							{
								dateTime: event.date.startIso,
								'data-wpse-end': event.date.endIso,
							},
							event.date.label,
						),
						event.date.timezoneLabel
							? React.createElement(
								React.Fragment,
								null,
								' ',
								React.createElement(
									'span',
									{ className: 'wpse-event-timezone' },
									event.date.timezoneLabel,
								),
							)
							: null,
					)
					: null;
			case 'status':
				return event.status
					? React.createElement(
						'p',
						{
							className: `wpse-event-status wpse-event-status-${ event.status }`,
							role: 'status',
						},
						data.labels?.[ event.status ],
					)
					: null;
			case 'venue':
				return event.venue
					? React.createElement(
						'p',
						{ className: 'wpse-event-venue' },
						enabled( attrs, 'showLabel', true )
							? React.createElement(
								React.Fragment,
								null,
								React.createElement(
									'span',
									{ className: 'wpse-event-label' },
									labelledText( attrs, 'label', data.labels?.location ),
								),
								' ',
							)
							: null,
						event.venue,
					)
					: null;
			case 'address':
				return event.address
					? React.createElement(
						'address',
						{ className: 'wpse-event-address' },
						event.address.split( '\n' ).map( ( line, index ) =>
							React.createElement(
								React.Fragment,
								{ key: `${ line }-${ index }` },
								index > 0 ? React.createElement( 'br' ) : null,
								line,
							),
						),
					)
					: null;
			case 'location_action':
				return event.locationUrl
					? React.createElement(
						'p',
						{ className: 'wpse-event-location-link' },
						React.createElement(
							'a',
							{
								...linkProps( event.locationUrl ),
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							fieldText( attrs, 'linkText' ) || data.labels?.viewLocation,
						),
					)
					: null;
			case 'content':
				return event.contentPreview
					? React.createElement(
						'div',
						{ className: 'wpse-single-event-content' },
						event.contentPreview,
					)
					: null;
			case 'excerpt':
				return event.excerptPreview
					? React.createElement(
						'div',
						{ className: 'wpse-event-excerpt' },
						event.excerptPreview,
					)
					: null;
			case 'external_action':
				return event.eventUrl
					? React.createElement(
						'p',
						{ className: 'wpse-event-action' },
						React.createElement(
							'a',
							{
								...linkProps( event.eventUrl ),
								className: 'wpse-event-action-link',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							fieldText( attrs, 'linkText' ) ||
									event.eventUrlLabel ||
									data.labels?.moreInformation,
						),
					)
					: null;
			case 'categories':
				return renderTerms(
					attrs,
					event,
					'categories',
					'wpse-event-categories',
					data.labels?.categories,
				);
			case 'tags':
				return renderTerms(
					attrs,
					event,
					'tags',
					'wpse-event-tags',
					data.labels?.tags,
				);
			default:
				return null;
		}
	};

	const atomicEdit = ( field ) => ( props ) => {
		const { attrs, elements, id, name } = props;
		const event = selectedEvent( attrs );
		const output = renderAtomicField( field, attrs, event );

		return React.createElement(
			moduleApi.ModuleContainer,
			{
				attrs,
				elements,
				id,
				name,
				stylesComponent: AtomicModuleStyles,
				scriptDataComponent: ModuleScriptData,
			},
			elements.styleComponents( { attrName: 'module' } ),
			React.createElement( moduleApi.ElementComponents, {
				attrs: attrs?.module?.decoration ?? {},
				id,
			} ),
			output ??
				React.createElement(
					'div',
					{ className: 'wpse-divi-placeholder', role: 'status' },
					data.labels?.noEvent ?? 'No event value is available.',
				),
		);
	};

	const prepareMetadata = ( source ) => {
		const prepared = translateMetadata( JSON.parse( JSON.stringify( source ) ) );
		const items = prepared?.attributes?.event?.settings?.innerContent?.items;

		if ( items?.eventId?.component?.props ) {
			items.eventId.component.props.options = eventOptions;
		}

		if ( items?.categories?.component?.props ) {
			items.categories.component.props.options =
				data.taxonomyOptions?.categories ?? [];
		}

		if ( items?.tags?.component?.props ) {
			items.tags.component.props.options = data.taxonomyOptions?.tags ?? [];
		}

		return prepared;
	};

	const compositeEdit = ( component ) => ( props ) => {
		const { attrs, elements, id, name } = props;
		const serializedAttrs = JSON.stringify( attrs ?? {} );
		const [ html, setHtml ] = React.useState( '' );
		const [ failed, setFailed ] = React.useState( false );
		const previewRef = React.useRef( null );
		const request = useFetch ? useFetch() : null;
		const requestRef = React.useRef( request );
		requestRef.current = request;

		React.useEffect( () => {
			const activeRequest = requestRef.current;

			if ( ! activeRequest || ! data.editorPostId ) {
				setFailed( true );
				return undefined;
			}

			let active = true;
			setFailed( false );
			const timer = window.setTimeout( () => {
				activeRequest
					.fetch( {
						restRoute: '/wpse/v1/divi-preview',
						method: 'POST',
						data: {
							postId: data.editorPostId,
							module: component,
							attrs: JSON.parse( serializedAttrs ),
						},
						forceRequest: true,
					} )
					.then( ( response ) => {
						if ( ! active ) {
							return;
						}

						setHtml( namespacePreviewHtml( response?.html, id ) );
						setFailed( false );
					} )
					.catch( () => {
						if ( active ) {
							setFailed( true );
						}
					} );
			}, 180 );

			return () => {
				active = false;
				window.clearTimeout( timer );
				activeRequest.abort();
			};
		}, [ id, serializedAttrs ] );

		React.useEffect( () => {
			if (
				component === 'calendar' &&
				html &&
				previewRef.current
			) {
				initializeCalendars( previewRef.current );
			}
		}, [ html ] );

		let output;

		if ( failed ) {
			output = React.createElement(
				'div',
				{ className: 'wpse-divi-placeholder', role: 'status' },
				data.labels?.previewError ?? 'The event preview could not be loaded.',
			);
		} else if ( request?.isLoading && ! html ) {
			output = React.createElement(
				'div',
				{ className: 'wpse-divi-placeholder', role: 'status' },
				data.labels?.previewLoading ?? 'Updating event preview…',
			);
		} else if ( ! html ) {
			output = React.createElement(
				'div',
				{ className: 'wpse-divi-placeholder', role: 'status' },
				data.labels?.previewEmpty ?? 'No matching event content is available.',
			);
		} else {
			output = React.createElement( 'div', {
				className: 'wpse-divi-composite-preview',
				ref: previewRef,
				onClick: ( clickEvent ) => clickEvent.preventDefault(),
				dangerouslySetInnerHTML: { __html: html },
			} );
		}

		return React.createElement(
			moduleApi.ModuleContainer,
			{
				attrs,
				elements,
				id,
				name,
				stylesComponent: CompositeModuleStyles,
				scriptDataComponent: ModuleScriptData,
			},
			elements.styleComponents( { attrName: 'module' } ),
			React.createElement( moduleApi.ElementComponents, {
				attrs: attrs?.module?.decoration ?? {},
				id,
			} ),
			output,
		);
	};

	addAction(
		'divi.moduleLibrary.registerModuleLibraryStore.after',
		'mime-simple-events-calendar',
		() => {
			registerModule( metadata, {
				renderers: {
					edit: EventTitleEdit,
				},
			} );

			Object.entries( moduleFields ).forEach( ( [ slug, field ] ) => {
				const moduleMetadata = moduleMetadataSource?.[ slug ];

				if ( ! moduleMetadata ) {
					return;
				}

				registerModule( prepareMetadata( moduleMetadata ), {
					renderers: {
						edit: atomicEdit( field ),
					},
				} );
			} );

			Object.entries( compositeModules ).forEach( ( [ slug, component ] ) => {
				const moduleMetadata = moduleMetadataSource?.[ slug ];

				if ( ! moduleMetadata ) {
					return;
				}

				registerModule( prepareMetadata( moduleMetadata ), {
					renderers: {
						edit: compositeEdit( component ),
					},
				} );
			} );
		},
	);
} )();
