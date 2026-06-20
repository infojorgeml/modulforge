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
		<div className="dtcp-panel">
			<div className="dtcp-panel-head">
				<span className="dtcp-panel-title">{ t( 'comments' ) }</span>
				<button
					type="button"
					className="dtcp-icon-btn"
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

			<div className="dtcp-panel-filters">
				{ FILTERS.map( ( key ) => (
					<button
						key={ key }
						type="button"
						className={
							'dtcp-chip' + ( filter === key ? ' is-active' : '' )
						}
						onClick={ () => onFilter( key ) }
					>
						{ filterLabel( key ) }
					</button>
				) ) }
			</div>

			<label
				className="dtcp-panel-showresolved"
				htmlFor="dtcp-show-resolved"
			>
				<input
					id="dtcp-show-resolved"
					type="checkbox"
					checked={ showResolved }
					onChange={ ( e ) => onToggleResolved( e.target.checked ) }
				/>
				{ t( 'show_resolved' ) }
			</label>

			<div className="dtcp-panel-list">
				{ list.length === 0 && (
					<div className="dtcp-panel-empty">
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
								'dtcp-panel-item' +
								( resolved ? ' is-resolved' : '' )
							}
							onClick={ () => onJump( pin ) }
						>
							<span
								className="dtcp-avatar is-sm"
								style={ { background: avatarColor( name ) } }
								aria-hidden="true"
							>
								{ initials( name ) }
							</span>
							<div className="dtcp-panel-item-body">
								<div className="dtcp-panel-item-head">
									<span className="dtcp-name">{ name }</span>
									<span className="dtcp-date">
										{ relativeTime( pin.created_at ) }
									</span>
								</div>
								<div className="dtcp-panel-item-text">
									{ snippet( pin.comment_text, 90 ) }
								</div>
								<div className="dtcp-panel-item-meta">
									{ resolved && (
										<span className="dtcp-badge is-resolved is-sm">
											{ t( 'resolved' ) }
										</span>
									) }
									{ pin.reply_count > 0 && (
										<span className="dtcp-reply-count">
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
