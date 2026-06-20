/**
 * DOM anchoring.
 *
 * Pins are stored relative to a DOM element (a stable CSS selector) plus an
 * offset in percent within that element's box. This is what makes pins stay in
 * place across screen sizes, scroll and content reflow — the previous model
 * stored absolute page pixels, which drifted whenever the layout changed.
 */

const ROOT_SELECTOR = '#dtcp-root';

/**
 * True when the node belongs to our own UI (so we never anchor to it).
 * @param node
 */
export function isPluginNode( node ) {
	return !! ( node && node.closest && node.closest( ROOT_SELECTOR ) );
}

function cssEscape( value ) {
	if ( window.CSS && typeof window.CSS.escape === 'function' ) {
		return window.CSS.escape( value );
	}
	return String( value ).replace( /[^a-zA-Z0-9_-]/g, '\\$&' );
}

/**
 * Position among same-tag siblings (1-based), for :nth-of-type().
 * @param node
 */
function nthOfType( node ) {
	let index = 1;
	let sibling = node;
	while ( ( sibling = sibling.previousElementSibling ) ) {
		if ( sibling.tagName === node.tagName ) {
			index += 1;
		}
	}
	return index;
}

/**
 * Build a reasonably stable CSS selector for an element. Prefers an ancestor
 * id when available (and stops there); otherwise walks up using
 * tag:nth-of-type(), which survives class churn.
 * @param el
 */
export function generateSelector( el ) {
	if ( ! el || el.nodeType !== 1 ) {
		return '';
	}
	if ( el === document.body || el === document.documentElement ) {
		return 'body';
	}

	const parts = [];
	let node = el;

	while (
		node &&
		node.nodeType === 1 &&
		node !== document.body &&
		node !== document.documentElement
	) {
		if ( node.id ) {
			parts.unshift( '#' + cssEscape( node.id ) );
			return parts.join( ' > ' );
		}
		parts.unshift(
			node.tagName.toLowerCase() +
				':nth-of-type(' +
				nthOfType( node ) +
				')'
		);
		node = node.parentElement;
		if ( parts.length > 10 ) {
			break;
		}
	}

	return 'body > ' + parts.join( ' > ' );
}

/**
 * Resolve a stored selector back to an element, or null if it's gone.
 * @param selector
 */
export function resolveAnchor( selector ) {
	if ( ! selector ) {
		return null;
	}
	try {
		return document.querySelector( selector );
	} catch {
		return null;
	}
}

/**
 * Absolute page coordinates for a pin given its anchor element + offsets.
 * @param el
 * @param offsetX
 * @param offsetY
 */
export function pagePositionFromAnchor( el, offsetX, offsetY ) {
	const rect = el.getBoundingClientRect();
	return {
		x: rect.left + window.scrollX + ( offsetX / 100 ) * rect.width,
		y: rect.top + window.scrollY + ( offsetY / 100 ) * rect.height,
	};
}

/**
 * Convenience: resolve + position in one step. Returns null for orphans.
 * @param pin
 */
export function computePinPosition( pin ) {
	const el = resolveAnchor( pin.anchor_selector );
	if ( ! el ) {
		return null;
	}
	return pagePositionFromAnchor(
		el,
		Number( pin.offset_x ) || 0,
		Number( pin.offset_y ) || 0
	);
}

function clamp( value, min, max ) {
	return Math.max( min, Math.min( max, value ) );
}

/**
 * From a click point, derive the anchor element and relative offsets.
 * @param clientX
 * @param clientY
 */
export function anchorFromPoint( clientX, clientY ) {
	// Temporarily disable our overlay so elementFromPoint hits the page.
	const root = document.querySelector( ROOT_SELECTOR );
	const prev = root ? root.style.pointerEvents : null;
	if ( root ) {
		root.style.pointerEvents = 'none';
	}
	let el = document.elementFromPoint( clientX, clientY );
	if ( root ) {
		root.style.pointerEvents = prev || '';
	}

	if ( ! el || isPluginNode( el ) ) {
		el = document.body;
	}

	const rect = el.getBoundingClientRect();
	const offsetX = rect.width
		? ( ( clientX - rect.left ) / rect.width ) * 100
		: 50;
	const offsetY = rect.height
		? ( ( clientY - rect.top ) / rect.height ) * 100
		: 50;

	return {
		selector: generateSelector( el ),
		offsetX: clamp( offsetX, 0, 100 ),
		offsetY: clamp( offsetY, 0, 100 ),
	};
}
