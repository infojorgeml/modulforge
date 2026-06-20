import { useMemo, useEffect, useRef } from '@wordpress/element';
import { computePinPosition } from '../anchor';
import { t, relativeTime, initials, avatarColor } from '../util';

/**
 * The read view shown when a pin is clicked: a light card with an author header
 * (avatar + name + relative time), the comment body, and a delete action.
 * Text is rendered through JSX, so React escapes it — no markup injection.
 */
export default function CommentPopover( { pin, tick, onClose, onDelete } ) {
	const ref = useRef( null );
	const pos = useMemo(
		() => ( pin ? computePinPosition( pin ) : null ),
		[ pin, tick ]
	);

	useEffect( () => {
		if ( ref.current ) {
			ref.current.focus();
		}
	}, [] );

	if ( ! pin || ! pos ) {
		return null;
	}

	const name = pin.display_name || t( 'user' );

	return (
		<div
			className="wpcp-popover"
			style={ { left: pos.x + 'px', top: pos.y + 18 + 'px' } }
			ref={ ref }
			tabIndex={ -1 }
		>
			<div className="wpcp-popover-head">
				<span
					className="wpcp-avatar"
					style={ { background: avatarColor( name ) } }
					aria-hidden="true"
				>
					{ initials( name ) }
				</span>
				<div className="wpcp-meta">
					<span className="wpcp-name">{ name }</span>
					<span className="wpcp-date">
						{ relativeTime( pin.created_at ) }
					</span>
				</div>
				<button
					type="button"
					className="wpcp-icon-btn"
					onClick={ onClose }
					aria-label={ t( 'close' ) }
				>
					<svg
						width="14"
						height="14"
						viewBox="0 0 14 14"
						aria-hidden="true"
					>
						<path
							d="M3.5 3.5l7 7M10.5 3.5l-7 7"
							stroke="currentColor"
							strokeWidth="1.6"
							strokeLinecap="round"
						/>
					</svg>
				</button>
			</div>

			<div className="wpcp-comment">{ pin.comment_text }</div>

			{ pin.can_delete && (
				<div className="wpcp-popover-foot">
					<button
						type="button"
						className="wpcp-text-btn is-danger"
						onClick={ () => onDelete( pin.id ) }
					>
						{ t( 'delete' ) }
					</button>
				</div>
			) }
		</div>
	);
}
