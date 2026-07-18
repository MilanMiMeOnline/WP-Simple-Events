/**
 * Keep timed controls understandable when an event is marked as all day.
 */
const eventFields = document.querySelector( '[data-wpse-event-fields]' );

if ( eventFields ) {
	const allDay = eventFields.querySelector( '#wpse-all-day' );
	const timeFields = eventFields.querySelectorAll( '[data-wpse-time-field]' );
	const fields = {
		address: eventFields.querySelector( '#wpse-address' ),
		endDate: eventFields.querySelector( '#wpse-end-date' ),
		endTime: eventFields.querySelector( '#wpse-end-time' ),
		eventUrl: eventFields.querySelector( '#wpse-event-url' ),
		locationUrl: eventFields.querySelector( '#wpse-location-url' ),
		startDate: eventFields.querySelector( '#wpse-start-date' ),
		startTime: eventFields.querySelector( '#wpse-start-time' ),
		status: eventFields.querySelector( '#wpse-status' ),
		venue: eventFields.querySelector( '#wpse-venue' ),
	};

	const canonicalLocal = ( date, time, isAllDay ) => {
		if ( isAllDay || ! time ) {
			return date;
		}

		return date ? `${ date }T${ time }` : `T${ time }`;
	};

	/**
	 * Make event data part of Gutenberg's authoritative REST save request.
	 *
	 * The legacy metabox request remains available for the classic editor, but
	 * it is not ordered atomically with Gutenberg's post request.
	 */
	const syncEditorMeta = () => {
		if ( typeof wp === 'undefined' || ! wp.data ) {
			return;
		}

		const editor = wp.data.select( 'core/editor' );
		const actions = wp.data.dispatch( 'core/editor' );

		if (
			! editor ||
			! actions ||
			typeof editor.getEditedPostAttribute !== 'function' ||
			typeof actions.editPost !== 'function'
		) {
			return;
		}

		const currentMeta = editor.getEditedPostAttribute( 'meta' );

		if ( ! currentMeta || typeof currentMeta !== 'object' ) {
			return;
		}

		const isAllDay = allDay.checked;
		const eventMeta = {
			_wpse_address: fields.address.value,
			_wpse_all_day: isAllDay,
			_wpse_end_local: canonicalLocal(
				fields.endDate.value,
				fields.endTime.value,
				isAllDay,
			),
			_wpse_event_status: fields.status.value,
			_wpse_event_url: fields.eventUrl.value,
			_wpse_location_url: fields.locationUrl.value,
			_wpse_start_local: canonicalLocal(
				fields.startDate.value,
				fields.startTime.value,
				isAllDay,
			),
			_wpse_venue: fields.venue.value,
		};
		const changed = Object.entries( eventMeta ).some(
			( [ key, value ] ) => currentMeta[ key ] !== value,
		);

		if ( changed ) {
			actions.editPost( {
				meta: { ...currentMeta, ...eventMeta },
			} );
		}
	};

	const syncTimeFields = () => {
		const isAllDay = allDay.checked;

		timeFields.forEach( ( wrapper ) => {
			wrapper.hidden = isAllDay;
			const input = wrapper.querySelector( 'input' );

			if ( input ) {
				input.disabled = isAllDay;
			}
		} );
	};

	Object.values( fields ).forEach( ( field ) => {
		field.addEventListener( 'input', syncEditorMeta );
	} );
	allDay.addEventListener( 'input', syncEditorMeta );
	allDay.addEventListener( 'change', () => {
		syncTimeFields();
		syncEditorMeta();
	} );
	syncTimeFields();
}
