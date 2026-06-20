/**
 * Thin AJAX layer over admin-ajax.php. All endpoints share the same nonce and
 * return WordPress's { success, data } envelope.
 */

const cfg = window.wpCommentPins || {};

function post( action, payload ) {
	const body = new URLSearchParams( {
		action,
		nonce: cfg.nonce,
		...payload,
	} );
	return fetch( cfg.ajax_url, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
		body: body.toString(),
		credentials: 'same-origin',
	} ).then( ( res ) => res.json() );
}

export function fetchPins( postUrl ) {
	return post( 'get_comment_pins', { post_url: postUrl } );
}

export function createPin( data ) {
	return post( 'save_comment_pin', data );
}

export function deletePin( pinId ) {
	return post( 'delete_comment_pin', { pin_id: pinId } );
}
