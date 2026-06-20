import { useMemo } from '@wordpress/element';
import { computePinPosition } from '../anchor';
import { initials, avatarColor } from '../util';

/**
 * A single pin, positioned from its DOM anchor. Open pins show the author's
 * initials and are tinted by author; resolved pins show a check and render
 * muted. The `tick` prop changes on layout events to force a position
 * recompute. Orphaned pins (anchor gone) render nothing.
 */
export default function CommentPin( { pin, tick, isActive, onOpen } ) {
	const pos = useMemo( () => computePinPosition( pin ), [ pin, tick ] );

	if ( ! pos ) {
		return null;
	}

	const resolved = pin.status === 'resolved';

	return (
		<button
			type="button"
			className={
				'wpcp-pin' +
				( isActive ? ' is-active' : '' ) +
				( resolved ? ' is-resolved' : '' )
			}
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
			{ resolved ? (
				<svg
					className="wpcp-pin-check"
					width="14"
					height="14"
					viewBox="0 0 14 14"
					aria-hidden="true"
				>
					<path
						d="M3 7.4l2.6 2.6L11 4.4"
						stroke="currentColor"
						strokeWidth="1.8"
						strokeLinecap="round"
						strokeLinejoin="round"
						fill="none"
					/>
				</svg>
			) : (
				<span className="wpcp-pin-initial">
					{ initials( pin.display_name ) }
				</span>
			) }
		</button>
	);
}
