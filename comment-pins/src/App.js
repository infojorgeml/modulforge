import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	Fragment,
} from '@wordpress/element';
import { fetchPins, createPin, deletePin, resolvePin } from './api';
import { anchorFromPoint, isPluginNode, resolveAnchor } from './anchor';
import { t } from './util';
import CommentPin from './components/CommentPin';
import CommentThread from './components/CommentThread';
import CommentPanel from './components/CommentPanel';
import CreateForm from './components/CreateForm';

const cfg = window.modulforgeCommentPins || {};
const TOGGLE_ID = 'wp-admin-bar-comment-pins-toggle';

export default function App() {
	const [ active, setActive ] = useState( false );
	const [ pins, setPins ] = useState( [] );
	const [ draft, setDraft ] = useState( null );
	const [ activeId, setActiveId ] = useState( null );
	const [ tick, setTick ] = useState( 0 );
	const [ panelOpen, setPanelOpen ] = useState( false );
	const [ filter, setFilter ] = useState( 'all' );
	const [ showResolved, setShowResolved ] = useState( false );

	// Load existing pins for this URL once.
	useEffect( () => {
		fetchPins( cfg.current_url )
			.then( ( res ) => {
				if ( res && res.success && Array.isArray( res.data ) ) {
					setPins( res.data );
				}
			} )
			.catch( () => {} );
	}, [] );

	// Toggle comment mode from the admin-bar button.
	useEffect( () => {
		const btn = document.getElementById( TOGGLE_ID );
		if ( ! btn ) {
			return undefined;
		}
		const onClick = ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			setActive( ( a ) => ! a );
		};
		btn.addEventListener( 'click', onClick );
		return () => btn.removeEventListener( 'click', onClick );
	}, [] );

	// Reflect active state on <body> and the toggle button.
	useEffect( () => {
		document.body.classList.toggle( 'dtcp-active', active );
		const btn = document.getElementById( TOGGLE_ID );
		if ( btn ) {
			btn.classList.toggle( 'dtcp-on', active );
		}
		if ( ! active ) {
			setDraft( null );
			setActiveId( null );
			setPanelOpen( false );
		}
	}, [ active ] );

	// Recompute pin positions when the layout changes.
	useEffect( () => {
		const bump = () => setTick( ( n ) => n + 1 );
		window.addEventListener( 'resize', bump );
		window.addEventListener( 'load', bump );
		let ro;
		if ( window.ResizeObserver ) {
			ro = new window.ResizeObserver( bump );
			ro.observe( document.body );
		}
		return () => {
			window.removeEventListener( 'resize', bump );
			window.removeEventListener( 'load', bump );
			if ( ro ) {
				ro.disconnect();
			}
		};
	}, [] );

	// While active: click the page to start a draft; Esc cancels.
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		const onClick = ( e ) => {
			if (
				isPluginNode( e.target ) ||
				( e.target.closest && e.target.closest( '#wpadminbar' ) )
			) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			setActiveId( null );
			setDraft( {
				anchor: anchorFromPoint( e.clientX, e.clientY ),
				pageX: e.pageX,
				pageY: e.pageY,
			} );
		};
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				setDraft( null );
				setActiveId( null );
			}
		};
		document.addEventListener( 'click', onClick, true );
		document.addEventListener( 'keydown', onKey );
		return () => {
			document.removeEventListener( 'click', onClick, true );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ active ] );

	const handleSave = useCallback(
		( text ) => {
			if ( ! draft ) {
				return;
			}
			const d = draft;
			setDraft( null );
			createPin( {
				post_url: cfg.current_url,
				anchor_selector: d.anchor.selector,
				offset_x: d.anchor.offsetX,
				offset_y: d.anchor.offsetY,
				comment_text: text,
			} )
				.then( ( res ) => {
					if ( res && res.success ) {
						setPins( ( prev ) => [
							...prev,
							{
								id: res.data.id,
								anchor_selector: d.anchor.selector,
								offset_x: d.anchor.offsetX,
								offset_y: d.anchor.offsetY,
								comment_text: text,
								display_name: t( 'you' ),
								created_at: res.data.created_at,
								can_delete: true,
								status: 'open',
								reply_count: 0,
								is_mine: true,
								resolved_by_name: null,
							},
						] );
					} else {
						window.alert(
							( res && res.data && res.data.message ) ||
								t( 'save_error' )
						);
					}
				} )
				.catch( () => window.alert( t( 'connect_error' ) ) );
		},
		[ draft ]
	);

	const handleDelete = useCallback( ( id ) => {
		if ( ! window.confirm( t( 'confirm_delete' ) ) ) {
			return;
		}
		deletePin( id )
			.then( ( res ) => {
				if ( res && res.success ) {
					setPins( ( prev ) =>
						prev.filter( ( p ) => String( p.id ) !== String( id ) )
					);
					setActiveId( null );
				} else {
					window.alert(
						( res && res.data && res.data.message ) ||
							t( 'delete_error' )
					);
				}
			} )
			.catch( () => window.alert( t( 'connect_error' ) ) );
	}, [] );

	const handleResolve = useCallback( ( id, resolved ) => {
		resolvePin( id, resolved )
			.then( ( res ) => {
				if ( res && res.success ) {
					setPins( ( prev ) =>
						prev.map( ( p ) =>
							String( p.id ) === String( id )
								? {
										...p,
										status: res.data.status,
										resolved_by_name:
											res.data.resolved_by_name,
								  }
								: p
						)
					);
				} else {
					window.alert(
						( res && res.data && res.data.message ) ||
							t( 'save_error' )
					);
				}
			} )
			.catch( () => window.alert( t( 'connect_error' ) ) );
	}, [] );

	const handleReplyCountChange = useCallback( ( id, delta ) => {
		setPins( ( prev ) =>
			prev.map( ( p ) =>
				String( p.id ) === String( id )
					? {
							...p,
							reply_count: Math.max(
								0,
								( p.reply_count || 0 ) + delta
							),
					  }
					: p
			)
		);
	}, [] );

	const jumpToPin = useCallback( ( pin ) => {
		const el = resolveAnchor( pin.anchor_selector );
		if ( el && el.scrollIntoView ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		setDraft( null );
		setActiveId( pin.id );
	}, [] );

	const activePin = useMemo(
		() =>
			pins.find( ( p ) => String( p.id ) === String( activeId ) ) || null,
		[ pins, activeId ]
	);

	// Resolved pins are hidden on the page unless "show resolved" is on or the
	// pin is currently open.
	const visiblePins = useMemo(
		() =>
			pins.filter(
				( p ) =>
					p.status !== 'resolved' ||
					showResolved ||
					String( p.id ) === String( activeId )
			),
		[ pins, showResolved, activeId ]
	);

	const openCount = useMemo(
		() => pins.filter( ( p ) => p.status !== 'resolved' ).length,
		[ pins ]
	);

	if ( ! active ) {
		return null;
	}

	return (
		<Fragment>
			<div className="dtcp-overlay" />

			{ visiblePins.map( ( pin ) => (
				<CommentPin
					key={ pin.id }
					pin={ pin }
					tick={ tick }
					isActive={ String( activeId ) === String( pin.id ) }
					onOpen={ () => {
						setDraft( null );
						setActiveId( pin.id );
					} }
				/>
			) ) }

			{ activePin && (
				<CommentThread
					key={ activePin.id }
					pin={ activePin }
					tick={ tick }
					onClose={ () => setActiveId( null ) }
					onDelete={ handleDelete }
					onResolve={ handleResolve }
					onReplyCountChange={ handleReplyCountChange }
				/>
			) }

			{ draft && (
				<Fragment>
					<div
						className="dtcp-pin is-draft"
						style={ {
							left: draft.pageX + 'px',
							top: draft.pageY + 'px',
						} }
					/>
					<CreateForm
						draft={ draft }
						onSave={ handleSave }
						onCancel={ () => setDraft( null ) }
					/>
				</Fragment>
			) }

			{ panelOpen && (
				<CommentPanel
					pins={ pins }
					filter={ filter }
					onFilter={ setFilter }
					showResolved={ showResolved }
					onToggleResolved={ setShowResolved }
					onJump={ jumpToPin }
					onClose={ () => setPanelOpen( false ) }
				/>
			) }

			<button
				type="button"
				className={
					'dtcp-panel-toggle' + ( panelOpen ? ' is-open' : '' )
				}
				onClick={ () => setPanelOpen( ( o ) => ! o ) }
				aria-label={
					panelOpen ? t( 'panel_close' ) : t( 'panel_open' )
				}
			>
				<svg
					width="18"
					height="18"
					viewBox="0 0 18 18"
					aria-hidden="true"
				>
					<path
						d="M3 5h12M3 9h12M3 13h7"
						stroke="currentColor"
						strokeWidth="1.6"
						strokeLinecap="round"
					/>
				</svg>
				{ openCount > 0 && (
					<span className="dtcp-panel-toggle-count">
						{ openCount }
					</span>
				) }
			</button>
		</Fragment>
	);
}
