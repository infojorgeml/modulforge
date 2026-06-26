/**
 * Thin AJAX layer over admin-ajax.php. All endpoints share the same nonce and
 * return WordPress's { success, data } envelope.
 */

const cfg = window.modulforgeCommentPins || {};

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
	return post( 'modulforge_get_comment_pins', { post_url: postUrl } );
}

export function createPin( data ) {
	return post( 'modulforge_save_comment_pin', data );
}

export function deletePin( pinId ) {
	return post( 'modulforge_delete_comment_pin', { pin_id: pinId } );
}

export function resolvePin( pinId, resolved ) {
	return post( 'modulforge_resolve_comment_pin', {
		pin_id: pinId,
		resolved: resolved ? '1' : '0',
	} );
}

export function fetchReplies( pinId ) {
	return post( 'modulforge_get_comment_replies', { pin_id: pinId } );
}

export function addReply( pinId, text ) {
	return post( 'modulforge_add_comment_reply', { pin_id: pinId, comment_text: text } );
}

export function deleteReply( replyId ) {
	return post( 'modulforge_delete_comment_reply', { reply_id: replyId } );
}
