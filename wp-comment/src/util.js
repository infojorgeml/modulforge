/** Small shared helpers: localised strings, dates and avatars. */

const cfg = window.wpCommentPins || {};

/** Localised string by key, falling back to the key itself. */
export function t( key ) {
	return ( cfg.i18n && cfg.i18n[ key ] ) || key;
}

/** Parse a MySQL datetime ("YYYY-MM-DD HH:MM:SS") to a Date, or null. */
function parseDate( value ) {
	if ( ! value ) {
		return null;
	}
	const date = new Date( String( value ).replace( ' ', 'T' ) );
	return isNaN( date.getTime() ) ? null : date;
}

/** Absolute formatted date for display. */
export function formatDate( value ) {
	const date = parseDate( value );
	if ( ! date ) {
		return value || '';
	}
	return date.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

/** Relative time ("2h ago", "yesterday"); absolute for anything older than a week. */
export function relativeTime( value ) {
	const date = parseDate( value );
	if ( ! date ) {
		return value || '';
	}

	const hasRtf = typeof Intl !== 'undefined' && Intl.RelativeTimeFormat;
	if ( ! hasRtf ) {
		return formatDate( value );
	}

	const rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	const seconds = Math.round( ( Date.now() - date.getTime() ) / 1000 );

	if ( seconds < 30 ) {
		return rtf.format( 0, 'second' );
	}

	const ranges = [
		[ 60, 'second', 1 ],
		[ 3600, 'minute', 60 ],
		[ 86400, 'hour', 3600 ],
		[ 604800, 'day', 86400 ],
	];
	for ( const [ limit, unit, divisor ] of ranges ) {
		if ( seconds < limit ) {
			return rtf.format( -Math.round( seconds / divisor ), unit );
		}
	}

	return formatDate( value );
}

/** 1–2 uppercase initials from a display name. */
export function initials( name ) {
	const clean = String( name || '' ).trim();
	if ( ! clean ) {
		return '?';
	}
	const parts = clean.split( /\s+/ );
	if ( parts.length === 1 ) {
		return parts[ 0 ].slice( 0, 2 ).toUpperCase();
	}
	return ( parts[ 0 ][ 0 ] + parts[ parts.length - 1 ][ 0 ] ).toUpperCase();
}

const AVATAR_COLORS = [
	'#6366f1',
	'#8b5cf6',
	'#ec4899',
	'#f43f5e',
	'#f59e0b',
	'#10b981',
	'#06b6d4',
	'#3b82f6',
];

/** A stable color for a name (so each author keeps the same hue). */
export function avatarColor( name ) {
	const str = String( name || '' );
	let hash = 0;
	for ( let i = 0; i < str.length; i++ ) {
		hash = ( hash * 31 + str.charCodeAt( i ) ) % 2147483647;
	}
	return AVATAR_COLORS[ hash % AVATAR_COLORS.length ];
}

/** Truncated single-line snippet for list views. */
export function snippet( text, max = 80 ) {
	const clean = String( text || '' )
		.replace( /\s+/g, ' ' )
		.trim();
	return clean.length > max ? clean.slice( 0, max - 1 ) + '…' : clean;
}
