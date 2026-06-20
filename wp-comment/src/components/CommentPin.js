import { useMemo } from '@wordpress/element';
import { computePinPosition } from '../anchor';
import { initials, avatarColor } from '../util';

/**
 * A single pin, positioned from its DOM anchor. Shows the author's initials and
 * is tinted by author. The `tick` prop changes on layout events to force a
 * position recompute. Orphaned pins (anchor gone) render nothing.
 */
export default function CommentPin( { pin, tick, isActive, onOpen } ) {
	const pos = useMemo( () => computePinPosition( pin ), [ pin, tick ] );

	if ( ! pos ) {
		return null;
	}

	return (
		<button
			type="button"
			className={ 'wpcp-pin' + ( isActive ? ' is-active' : '' ) }
			style={ {
				left: pos.x + 'px',
				top: pos.y + 'px',
				'--wpcp-pin-color': avatarColor( pin.display_name ),
			} }
			onClick={ ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				onOpen();
			} }
			aria-label={ pin.comment_text || '' }
		>
			<span className="wpcp-pin-initial">
				{ initials( pin.display_name ) }
			</span>
		</button>
	);
}
