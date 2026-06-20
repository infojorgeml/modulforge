import { useState, useEffect, useRef, useMemo } from '@wordpress/element';
import { computePinPosition } from '../anchor';
import { fetchReplies, addReply, deleteReply } from '../api';
import { t, relativeTime, initials, avatarColor } from '../util';

/**
 * The conversation shown when a pin is opened: the root comment plus a flat
 * list of replies and a reply composer, with resolve/reopen and delete actions.
 * Replies load lazily on open. All text is rendered via JSX (escaped).
 */
export default function CommentThread( {
	pin,
	tick,
	onClose,
	onDelete,
	onResolve,
	onReplyCountChange,
} ) {
	const ref = useRef( null );
	const pos = useMemo(
		() => ( pin ? computePinPosition( pin ) : null ),
		[ pin, tick ]
	);

	const [ replies, setReplies ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ replyText, setReplyText ] = useState( '' );
	const [ sending, setSending ] = useState( false );

	const pinId = pin ? pin.id : null;

	useEffect( () => {
		if ( ! pinId ) {
			return undefined;
		}
		let cancelled = false;
		setLoading( true );
		fetchReplies( pinId )
			.then( ( res ) => {
				if (
					! cancelled &&
					res &&
					res.success &&
					Array.isArray( res.data )
				) {
					setReplies( res.data );
				}
			} )
			.catch( () => {} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ pinId ] );

	useEffect( () => {
		if ( ref.current ) {
			ref.current.focus();
		}
	}, [] );

	if ( ! pin || ! pos ) {
		return null;
	}

	const name = pin.display_name || t( 'user' );
	const isResolved = pin.status === 'resolved';

	const submitReply = () => {
		const text = replyText.trim();
		if ( ! text || sending ) {
			return;
		}
		setSending( true );
		addReply( pinId, text )
			.then( ( res ) => {
				if ( res && res.success ) {
					setReplies( ( prev ) => [ ...prev, res.data ] );
					setReplyText( '' );
					if ( onReplyCountChange ) {
						onReplyCountChange( pinId, 1 );
					}
				} else {
					window.alert(
						( res && res.data && res.data.message ) ||
							t( 'save_error' )
					);
				}
			} )
			.catch( () => window.alert( t( 'connect_error' ) ) )
			.finally( () => setSending( false ) );
	};

	const removeReply = ( replyId ) => {
		if ( ! window.confirm( t( 'confirm_delete_reply' ) ) ) {
			return;
		}
		deleteReply( replyId )
			.then( ( res ) => {
				if ( res && res.success ) {
					setReplies( ( prev ) =>
						prev.filter(
							( r ) => String( r.id ) !== String( replyId )
						)
					);
					if ( onReplyCountChange ) {
						onReplyCountChange( pinId, -1 );
					}
				} else {
					window.alert(
						( res && res.data && res.data.message ) ||
							t( 'delete_error' )
					);
				}
			} )
			.catch( () => window.alert( t( 'connect_error' ) ) );
	};

	return (
		<div
			className={ 'wpcp-thread' + ( isResolved ? ' is-resolved' : '' ) }
			style={ { left: pos.x + 'px', top: pos.y + 18 + 'px' } }
			ref={ ref }
			tabIndex={ -1 }
		>
			<div className="wpcp-thread-head">
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
				{ isResolved && (
					<span className="wpcp-badge is-resolved">
						{ t( 'resolved' ) }
					</span>
				) }
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
			{ isResolved && pin.resolved_by_name && (
				<div className="wpcp-resolved-note">
					{ t( 'resolved_by' ).replace( '%s', pin.resolved_by_name ) }
				</div>
			) }

			<div className="wpcp-thread-actions">
				<button
					type="button"
					className="wpcp-text-btn"
					onClick={ () => onResolve( pinId, ! isResolved ) }
				>
					{ isResolved ? t( 'reopen' ) : t( 'resolve' ) }
				</button>
				{ pin.can_delete && (
					<button
						type="button"
						className="wpcp-text-btn is-danger"
						onClick={ () => onDelete( pinId ) }
					>
						{ t( 'delete' ) }
					</button>
				) }
			</div>

			{ ( loading || replies.length > 0 ) && (
				<div className="wpcp-replies">
					{ loading && (
						<div className="wpcp-loading">{ t( 'loading' ) }</div>
					) }
					{ ! loading &&
						replies.map( ( reply ) => {
							const rname = reply.display_name || t( 'user' );
							return (
								<div className="wpcp-reply" key={ reply.id }>
									<span
										className="wpcp-avatar is-sm"
										style={ {
											background: avatarColor( rname ),
										} }
										aria-hidden="true"
									>
										{ initials( rname ) }
									</span>
									<div className="wpcp-reply-body">
										<div className="wpcp-reply-head">
											<span className="wpcp-name">
												{ rname }
											</span>
											<span className="wpcp-date">
												{ relativeTime(
													reply.created_at
												) }
											</span>
											{ reply.can_delete && (
												<button
													type="button"
													className="wpcp-link-danger"
													onClick={ () =>
														removeReply( reply.id )
													}
												>
													{ t( 'delete' ) }
												</button>
											) }
										</div>
										<div className="wpcp-reply-text">
											{ reply.comment_text }
										</div>
									</div>
								</div>
							);
						} ) }
				</div>
			) }

			<div className="wpcp-reply-composer">
				<textarea
					className="wpcp-textarea is-sm"
					value={ replyText }
					rows={ 2 }
					placeholder={ t( 'reply_placeholder' ) }
					onChange={ ( e ) => setReplyText( e.target.value ) }
					onKeyDown={ ( e ) => {
						if ( e.ctrlKey && e.key === 'Enter' ) {
							submitReply();
						}
					} }
				/>
				<div className="wpcp-form-actions">
					<button
						type="button"
						className="wpcp-btn-primary"
						onClick={ submitReply }
						disabled={ ! replyText.trim() || sending }
					>
						{ t( 'reply' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
