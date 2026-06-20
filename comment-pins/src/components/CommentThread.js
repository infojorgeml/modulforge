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
			className={ 'dtcp-thread' + ( isResolved ? ' is-resolved' : '' ) }
			style={ { left: pos.x + 'px', top: pos.y + 18 + 'px' } }
			ref={ ref }
			tabIndex={ -1 }
		>
			<div className="dtcp-thread-head">
				<span
					className="dtcp-avatar"
					style={ { background: avatarColor( name ) } }
					aria-hidden="true"
				>
					{ initials( name ) }
				</span>
				<div className="dtcp-meta">
					<span className="dtcp-name">{ name }</span>
					<span className="dtcp-date">
						{ relativeTime( pin.created_at ) }
					</span>
				</div>
				{ isResolved && (
					<span className="dtcp-badge is-resolved">
						{ t( 'resolved' ) }
					</span>
				) }
				<button
					type="button"
					className="dtcp-icon-btn"
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

			<div className="dtcp-comment">{ pin.comment_text }</div>
			{ isResolved && pin.resolved_by_name && (
				<div className="dtcp-resolved-note">
					{ t( 'resolved_by' ).replace( '%s', pin.resolved_by_name ) }
				</div>
			) }

			<div className="dtcp-thread-actions">
				<button
					type="button"
					className="dtcp-text-btn"
					onClick={ () => onResolve( pinId, ! isResolved ) }
				>
					{ isResolved ? t( 'reopen' ) : t( 'resolve' ) }
				</button>
				{ pin.can_delete && (
					<button
						type="button"
						className="dtcp-text-btn is-danger"
						onClick={ () => onDelete( pinId ) }
					>
						{ t( 'delete' ) }
					</button>
				) }
			</div>

			{ ( loading || replies.length > 0 ) && (
				<div className="dtcp-replies">
					{ loading && (
						<div className="dtcp-loading">{ t( 'loading' ) }</div>
					) }
					{ ! loading &&
						replies.map( ( reply ) => {
							const rname = reply.display_name || t( 'user' );
							return (
								<div className="dtcp-reply" key={ reply.id }>
									<span
										className="dtcp-avatar is-sm"
										style={ {
											background: avatarColor( rname ),
										} }
										aria-hidden="true"
									>
										{ initials( rname ) }
									</span>
									<div className="dtcp-reply-body">
										<div className="dtcp-reply-head">
											<span className="dtcp-name">
												{ rname }
											</span>
											<span className="dtcp-date">
												{ relativeTime(
													reply.created_at
												) }
											</span>
											{ reply.can_delete && (
												<button
													type="button"
													className="dtcp-link-danger"
													onClick={ () =>
														removeReply( reply.id )
													}
												>
													{ t( 'delete' ) }
												</button>
											) }
										</div>
										<div className="dtcp-reply-text">
											{ reply.comment_text }
										</div>
									</div>
								</div>
							);
						} ) }
				</div>
			) }

			<div className="dtcp-reply-composer">
				<textarea
					className="dtcp-textarea is-sm"
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
				<div className="dtcp-form-actions">
					<button
						type="button"
						className="dtcp-btn-primary"
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
