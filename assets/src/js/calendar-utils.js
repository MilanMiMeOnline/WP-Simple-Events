/**
 * Translate the stable public feed to FullCalendar 7 without changing its dates.
 *
 * @param {Object} event Public feed record.
 * @return {Object} FullCalendar event input.
 */
export const calendarEventInput = ( event ) => ( {
	...event,
	color: /^#[0-9a-f]{6}$/i.test( event.backgroundColor )
		? event.backgroundColor
		: undefined,
	contrastColor: /^#[0-9a-f]{6}$/i.test( event.textColor )
		? event.textColor
		: undefined,
} );

/**
 * Preserve WordPress hour/minute/meridiem choices independently of v7's compact
 * range formatter. Dates remain in the browser's floating local wall time.
 *
 * @param {string} locale  Site locale.
 * @param {Object} options Bounded options derived from the WordPress time format.
 * @return {Function} Localized single-time formatter.
 */
export const calendarTimeFormatter = ( locale, options ) => {
	const { meridiem, ...intlOptions } = options;
	const formatter = new Intl.DateTimeFormat( locale, intlOptions );

	return ( date ) => formatter.formatToParts( date ).map( ( part ) => {
		if ( part.type === 'dayPeriod' ) {
			if ( meridiem === false ) {
				return '';
			}
			return meridiem === 'lowercase'
				? part.value.toLocaleLowerCase( locale ) : part.value;
		}
		return part.value;
	} ).join( '' ).replace( /\s+/gu, ' ' ).trim();
};

/**
 * Label a timed multi-day list segment without changing all-day event labels.
 *
 * @param {Object}   argument   FullCalendar display information.
 * @param {Object}   strings    Translated component labels.
 * @param {Function} formatTime Format one boundary using the site's time format.
 * @return {string} Localized time or continuation text.
 */
export const calendarSegmentTime = ( argument, strings, formatTime ) => {
	const { event, timeText, isStart, isEnd } = argument;

	if ( event.allDay || ! event.start ) {
		return timeText;
	}
	if ( ! event.end ) {
		return formatTime( event.start );
	}
	if ( event.start.toDateString() === event.end.toDateString() || ( isStart && isEnd ) ) {
		return `${ formatTime( event.start ) } - ${ formatTime( event.end ) }`;
	}

	if ( isStart && ! isEnd ) {
		return strings.startsAt.replace( '%s', formatTime( event.start ) );
	}
	if ( ! isStart && isEnd ) {
		return strings.endsAt.replace( '%s', formatTime( event.end ) );
	}
	return ! isStart && ! isEnd ? strings.continues : timeText;
};
