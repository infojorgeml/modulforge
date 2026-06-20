import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

/** Mount the React overlay into a dedicated root appended to <body>. */
function mount() {
	if ( ! window.wpCommentPins || document.getElementById( 'wpcp-root' ) ) {
		return;
	}
	const root = document.createElement( 'div' );
	root.id = 'wpcp-root';
	document.body.appendChild( root );
	createRoot( root ).render( <App /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
