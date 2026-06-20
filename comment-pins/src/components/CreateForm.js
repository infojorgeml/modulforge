import { useState, useRef, useEffect } from '@wordpress/element';
import { t } from '../util';

/** The inline composer for a new comment, shown as a card next to the draft pin. */
export default function CreateForm( { draft, onSave, onCancel } ) {
	const [ text, setText ] = useState( '' );
	const ref = useRef( null );

	useEffect( () => {
		if ( ref.current ) {
			ref.current.focus( { preventScroll: true } );
		}
	}, [] );

	const submit = () => {
		const value = text.trim();
		if ( value ) {
			onSave( value );
		}
	};

	return (
		<div
			className="dtcp-form"
			style={ { left: draft.pageX + 'px', top: draft.pageY + 18 + 'px' } }
		>
			<textarea
				ref={ ref }
				className="dtcp-textarea"
				value={ text }
				rows={ 3 }
				placeholder={ t( 'placeholder' ) }
				onChange={ ( e ) => setText( e.target.value ) }
				onKeyDown={ ( e ) => {
					if ( e.ctrlKey && e.key === 'Enter' ) {
						submit();
					}
				} }
			/>
			<div className="dtcp-form-actions">
				<button
					type="button"
					className="dtcp-text-btn"
					onClick={ onCancel }
				>
					{ t( 'cancel' ) }
				</button>
				<button
					type="button"
					className="dtcp-btn-primary"
					onClick={ submit }
					disabled={ ! text.trim() }
				>
					{ t( 'save' ) }
				</button>
			</div>
		</div>
	);
}
