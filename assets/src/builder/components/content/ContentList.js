/**
 * List of post types, taxonomies or options pages, with a trash view.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, FormToggle, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { deleteContent, duplicateContent, listContent, restoreContent, syncPull, trashContent, updateContent } from '../../api';
import { errorSnackbar, snackbar } from '../../notices';
import ConfirmModal from '../ConfirmModal';
import SourceBadge from '../SourceBadge';

export default function ContentList( { kind, navigate } ) {
	const names = kind.names;
	const [ view, setView ] = useState( 'any' );
	const [ items, setItems ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ confirmDelete, setConfirmDelete ] = useState( null );

	const load = useCallback( () => {
		setLoadError( '' );
		return listContent( kind.kind, view )
			.then( setItems )
			.catch( ( error ) => setLoadError( error.message || __( 'The list could not be loaded.', 'cfmb' ) ) );
	}, [ kind.kind, view ] );

	useEffect( () => {
		setItems( null );
		load();
	}, [ load ] );

	const run = async ( key, task ) => {
		setBusy( key );
		try {
			await task();
		} catch ( error ) {
			errorSnackbar( error.message || __( 'That did not work. Please try again.', 'cfmb' ) );
		} finally {
			setBusy( '' );
		}
	};

	const replace = ( saved ) => setItems( ( current ) => current.map( ( item ) => ( item.key === saved.key ? saved : item ) ) );

	const toggleActive = ( item ) =>
		run( item.key, async () => {
			const saved = await updateContent( kind.kind, { ...item, active: ! item.active } );
			replace( saved );
			snackbar(
				saved.active
					? /* translators: %s: title */ sprintf( __( '"%s" is active.', 'cfmb' ), saved.title )
					: /* translators: %s: title */ sprintf( __( '"%s" is inactive.', 'cfmb' ), saved.title )
			);
		} );

	const duplicate = ( item ) =>
		run( item.key, async () => {
			await duplicateContent( kind.kind, item.key );
			await load();
			snackbar( __( 'Copy created. It stays inactive until you turn it on.', 'cfmb' ) );
		} );

	const importItem = ( item ) =>
		run( item.key, async () => {
			const result = await syncPull( [ { kind: kind.kind, key: item.key } ] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			await load();
			/* translators: %s: title */
			snackbar( sprintf( __( '"%s" imported. You can edit it here now.', 'cfmb' ), item.title ) );
		} );

	const trash = ( item ) =>
		run( item.key, async () => {
			await trashContent( kind.kind, item.key );
			setItems( ( current ) => current.filter( ( entry ) => entry.key !== item.key ) );
			snackbar(
				/* translators: %s: title */
				sprintf( __( '"%s" moved to the trash. Its content stays in the database.', 'cfmb' ), item.title ),
				[
					{
						label: __( 'Undo', 'cfmb' ),
						onClick: () => run( item.key, async () => {
							await restoreContent( kind.kind, item.key );
							await load();
						} ),
					},
				]
			);
		} );

	const restore = ( item ) =>
		run( item.key, async () => {
			await restoreContent( kind.kind, item.key );
			setItems( ( current ) => current.filter( ( entry ) => entry.key !== item.key ) );
			/* translators: %s: title */
			snackbar( sprintf( __( '"%s" restored.', 'cfmb' ), item.title ) );
		} );

	const deleteForever = ( item ) =>
		run( item.key, async () => {
			setConfirmDelete( null );
			await deleteContent( kind.kind, item.key );
			setItems( ( current ) => current.filter( ( entry ) => entry.key !== item.key ) );
			/* translators: %s: title */
			snackbar( sprintf( __( '"%s" deleted.', 'cfmb' ), item.title ) );
		} );

	const local = ( item ) => item.source && item.source !== 'db';

	return (
		<div className="cfmb-list">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">{ names.plural }</h1>
				<Button variant="primary" onClick={ () => navigate( 'new' ) }>
					{ names.add }
				</Button>
			</div>

			<ul className="subsubsub cfmb-list__views">
				<li>
					<Button variant="link" className={ view === 'any' ? 'current' : '' } aria-current={ view === 'any' ? 'page' : undefined } onClick={ () => setView( 'any' ) }>
						{ __( 'All', 'cfmb' ) }
					</Button>
					{ ' | ' }
				</li>
				<li>
					<Button variant="link" className={ view === 'trash' ? 'current' : '' } aria-current={ view === 'trash' ? 'page' : undefined } onClick={ () => setView( 'trash' ) }>
						{ __( 'Trash', 'cfmb' ) }
					</Button>
				</li>
			</ul>

			{ loadError && (
				<Notice status="error" isDismissible={ false } actions={ [ { label: __( 'Try again', 'cfmb' ), onClick: load } ] }>
					{ loadError }
				</Notice>
			) }

			{ ! items && ! loadError && (
				<div className="cfmb-list__loading">
					<Spinner /> { __( 'Loading…', 'cfmb' ) }
				</div>
			) }

			{ items && items.length === 0 && (
				<div className="cfmb-empty">
					{ view === 'trash' ? (
						<p>{ __( 'The trash is empty.', 'cfmb' ) }</p>
					) : (
						<>
							<h2>{ names.empty_title }</h2>
							<p>{ names.empty_text }</p>
							<Button variant="primary" onClick={ () => navigate( 'new' ) }>
								{ names.add }
							</Button>
						</>
					) }
				</div>
			) }

			{ items && items.length > 0 && (
				<table className="wp-list-table widefat fixed striped cfmb-list__table">
					<thead>
						<tr>
							<th scope="col" className="column-primary">{ names.title_label }</th>
							<th scope="col">{ names.name_label }</th>
							{ view !== 'trash' && <th scope="col" className="cfmb-list__narrow">{ __( 'Active', 'cfmb' ) }</th> }
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<tr key={ item.key } className={ busy === item.key ? 'is-busy' : '' }>
								<td className="column-primary">
									<strong>
										{ view === 'trash' ? (
											item.title
										) : (
											<a
												className="row-title"
												href={ `?page=${ new URL( window.location.href ).searchParams.get( 'page' ) }&item=${ item.key }` }
												onClick={ ( event ) => {
													event.preventDefault();
													navigate( item.key );
												} }
											>
												{ item.title }
											</a>
										) }
									</strong>
									<SourceBadge source={ item.source } />
									<div className="cfmb-list__actions">
										{ view === 'trash' ? (
											<>
												<Button variant="link" onClick={ () => restore( item ) }>
													{ __( 'Restore', 'cfmb' ) }
												</Button>
												<Button variant="link" isDestructive onClick={ () => setConfirmDelete( item ) }>
													{ __( 'Delete permanently', 'cfmb' ) }
												</Button>
											</>
										) : (
											<>
												<Button variant="link" onClick={ () => navigate( item.key ) }>
													{ local( item ) ? __( 'View', 'cfmb' ) : __( 'Edit', 'cfmb' ) }
												</Button>
												{ local( item ) && (
													<Button variant="link" onClick={ () => importItem( item ) }>
														{ __( 'Import to edit', 'cfmb' ) }
													</Button>
												) }
												<Button variant="link" onClick={ () => duplicate( item ) }>
													{ __( 'Duplicate', 'cfmb' ) }
												</Button>
												{ ! local( item ) && (
													<Button variant="link" isDestructive onClick={ () => trash( item ) }>
														{ __( 'Trash', 'cfmb' ) }
													</Button>
												) }
											</>
										) }
									</div>
								</td>
								<td>
									<code>{ item.name }</code>
								</td>
								{ view !== 'trash' && (
									<td className="cfmb-list__narrow">
										<FormToggle
											checked={ item.active }
											disabled={ busy === item.key || local( item ) }
											aria-label={
												/* translators: %s: title */
												sprintf( __( 'Turn "%s" on', 'cfmb' ), item.title )
											}
											onChange={ () => toggleActive( item ) }
										/>
									</td>
								) }
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ confirmDelete && (
				<ConfirmModal
					title={ __( 'Delete permanently?', 'cfmb' ) }
					confirmLabel={ __( 'Delete permanently', 'cfmb' ) }
					isDestructive
					onConfirm={ () => deleteForever( confirmDelete ) }
					onCancel={ () => setConfirmDelete( null ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: title */
							__( '"%s" will be deleted for good, along with its sync file in uploads. Posts, terms and values made with it stay in the database.', 'cfmb' ),
							confirmDelete.title
						) }
					</p>
				</ConfirmModal>
			) }
		</div>
	);
}
