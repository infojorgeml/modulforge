import { t, relativeTime, initials, avatarColor, snippet } from '../util';

const FILTERS = [ 'all', 'open', 'resolved', 'mine' ];

function filterLabel( key ) {
	return {
		all: t( 'filter_all' ),
		open: t( 'filter_open' ),
		resolved: t( 'filter_resolved' ),
		mine: t( 'filter_mine' ),
	}[ key ];
}

function matches( pin, filter ) {
	if ( filter === 'open' ) {
		return pin.status !== 'resolved';
	}
	if ( filter === 'resolved' ) {
		return pin.status === 'resolved';
	}
	if ( filter === 'mine' ) {
		return !! pin.is_mine;
	}
	return true;
}

/**
 * Right-side panel listing every comment on the page, with filters and a
 * "show resolved on page" toggle. Clicking an item jumps to its pin.
 */
export default function CommentPanel( {
	pins,
	filter,
	onFilter,
	showResolved,
	onToggleResolved,
	onJump,
	onClose,
} ) {
	const list = pins.filter( ( pin ) => matches( pin, filter ) );

	return (
		<div className="wpcp-panel">
			<div className="wpcp-panel-head">
				<span className="wpcp-panel-title">{ t( 'comments' ) }</span>
				<button
					type="button"
					className="wpcp-icon-btn"
					onClick={ onClose }
					aria-label={ t( 'panel_close' ) }
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

			<div className="wpcp-panel-filters">
				{ FILTERS.map( ( key ) => (
					<button
						key={ key }
						type="button"
						className={
							'wpcp-chip' + ( filter === key ? ' is-active' : '' )
						}
						onClick={ () => onFilter( key ) }
					>
						{ filterLabel( key ) }
					</button>
				) ) }
			</div>

			<label
				className="wpcp-panel-showresolved"
				htmlFor="wpcp-show-resolved"
			>
				<input
					id="wpcp-show-resolved"
					type="checkbox"
					checked={ showResolved }
					onChange={ ( e ) => onToggleResolved( e.target.checked ) }
				/>
				{ t( 'show_resolved' ) }
			</label>

			<div className="wpcp-panel-list">
				{ list.length === 0 && (
					<div className="wpcp-panel-empty">
						{ pins.length === 0
							? t( 'no_comments' )
							: t( 'no_match' ) }
					</div>
				) }

				{ list.map( ( pin ) => {
					const name = pin.display_name || t( 'user' );
					const resolved = pin.status === 'resolved';
					return (
						<button
							key={ pin.id }
							type="button"
							className={
								'wpcp-panel-item' +
								( resolved ? ' is-resolved' : '' )
							}
							onClick={ () => onJump( pin ) }
						>
							<span
								className="wpcp-avatar is-sm"
								style={ { background: avatarColor( name ) } }
								aria-hidden="true"
							>
								{ initials( name ) }
							</span>
							<div className="wpcp-panel-item-body">
								<div className="wpcp-panel-item-head">
									<span className="wpcp-name">{ name }</span>
									<span className="wpcp-date">
										{ relativeTime( pin.created_at ) }
									</span>
								</div>
								<div className="wpcp-panel-item-text">
									{ snippet( pin.comment_text, 90 ) }
								</div>
								<div className="wpcp-panel-item-meta">
									{ resolved && (
										<span className="wpcp-badge is-resolved is-sm">
											{ t( 'resolved' ) }
										</span>
									) }
									{ pin.reply_count > 0 && (
										<span className="wpcp-reply-count">
											{ pin.reply_count === 1
												? t( 'one_reply' )
												: t( 'many_replies' ).replace(
														'%d',
														pin.reply_count
												  ) }
										</span>
									) }
								</div>
							</div>
						</button>
					);
				} ) }
			</div>
		</div>
	);
}
