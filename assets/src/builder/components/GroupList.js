/**
 * Field Group list with trash view.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, FormToggle, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { deleteGroup, duplicateGroup, listGroups, restoreGroup, syncPull, trashGroup, updateGroup } from '../api';
import { errorSnackbar, snackbar } from '../notices';
import { storesValue } from '../config';
import ConfirmModal from './ConfirmModal';
import locationSummary from './locationSummary';

/**
 * Where a group shows: its Field Block, its location rules, or both.
 *
 * @param {Object} group Group.
 * @return {string} Summary.
 */
function groupPlaces( group ) {
	const block = group.settings?.block;
	if ( ! block?.enabled ) {
		return locationSummary( group.location );
	}
	/* translators: %s: block name */
	const label = sprintf( __( 'Field Block cfmb/%s', 'cfmb' ), block.name );
	return group.location?.length ? `${ label }; ${ locationSummary( group.location ) }` : label;
}
import SourceBadge from './SourceBadge';

export default function GroupList( { navigate } ) {
	const [ view, setView ] = useState( 'any' );
	const [ groups, setGroups ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ confirmDelete, setConfirmDelete ] = useState( null );

	const load = useCallback( () => {
		setLoadError( '' );
		return listGroups( view )
			.then( setGroups )
			.catch( ( error ) => setLoadError( error.message || __( 'Field groups could not be loaded.', 'cfmb' ) ) );
	}, [ view ] );

	useEffect( () => {
		setGroups( null );
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

	const toggleActive = ( group ) =>
		run( group.key, async () => {
			const saved = await updateGroup( { ...group, active: ! group.active } );
			setGroups( ( current ) => current.map( ( item ) => ( item.key === saved.key ? saved : item ) ) );
			snackbar(
				saved.active
					? /* translators: %s: field group title */ sprintf( __( '"%s" is active.', 'cfmb' ), saved.title )
					: /* translators: %s: field group title */ sprintf( __( '"%s" is inactive.', 'cfmb' ), saved.title )
			);
		} );

	const duplicate = ( group ) =>
		run( group.key, async () => {
			await duplicateGroup( group.key );
			await load();
			snackbar( __( 'Copy created. It stays inactive until you turn it on.', 'cfmb' ) );
		} );

	const trash = ( group ) =>
		run( group.key, async () => {
			await trashGroup( group.key );
			setGroups( ( current ) => current.filter( ( item ) => item.key !== group.key ) );
			snackbar(
				/* translators: %s: field group title */
				sprintf( __( '"%s" moved to the trash.', 'cfmb' ), group.title ),
				[
					{
						label: __( 'Undo', 'cfmb' ),
						onClick: () => run( group.key, async () => {
							await restoreGroup( group.key );
							await load();
						} ),
					},
				]
			);
		} );

	const restore = ( group ) =>
		run( group.key, async () => {
			await restoreGroup( group.key );
			setGroups( ( current ) => current.filter( ( item ) => item.key !== group.key ) );
			/* translators: %s: field group title */
			snackbar( sprintf( __( '"%s" restored.', 'cfmb' ), group.title ) );
		} );

	const importGroup = ( group ) =>
		run( group.key, async () => {
			const result = await syncPull( [ { kind: 'group', key: group.key } ] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			await load();
			/* translators: %s: field group title */
			snackbar( sprintf( __( '"%s" imported. You can edit it here now.', 'cfmb' ), group.title ) );
		} );

	const deleteForever = ( group ) =>
		run( group.key, async () => {
			setConfirmDelete( null );
			await deleteGroup( group.key );
			setGroups( ( current ) => current.filter( ( item ) => item.key !== group.key ) );
			/* translators: %s: field group title */
			snackbar( sprintf( __( '"%s" deleted.', 'cfmb' ), group.title ) );
		} );

	const fieldCount = ( group ) => group.fields.filter( ( field ) => storesValue( field.type ) ).length;

	return (
		<div className="cfmb-list">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">{ __( 'Field Groups', 'cfmb' ) }</h1>
				<Button variant="primary" onClick={ () => navigate( 'new' ) }>
					{ __( 'Add field group', 'cfmb' ) }
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

			{ ! groups && ! loadError && (
				<div className="cfmb-list__loading">
					<Spinner /> { __( 'Loading field groups…', 'cfmb' ) }
				</div>
			) }

			{ groups && groups.length === 0 && (
				<div className="cfmb-empty">
					{ view === 'trash' ? (
						<p>{ __( 'The trash is empty.', 'cfmb' ) }</p>
					) : (
						<>
							<h2>{ __( 'Create your first field group', 'cfmb' ) }</h2>
							<p>
								{ __( 'A field group is a set of fields, like Price and Gallery, shown on the screens you choose, such as every Product or the Contact page.', 'cfmb' ) }
							</p>
							<Button variant="primary" onClick={ () => navigate( 'new' ) }>
								{ __( 'Add field group', 'cfmb' ) }
							</Button>
						</>
					) }
				</div>
			) }

			{ groups && groups.length > 0 && (
				<table className="wp-list-table widefat fixed striped cfmb-list__table">
					<thead>
						<tr>
							<th scope="col" className="column-primary">{ __( 'Title', 'cfmb' ) }</th>
							<th scope="col">{ __( 'Shown on', 'cfmb' ) }</th>
							<th scope="col" className="cfmb-list__narrow">{ __( 'Fields', 'cfmb' ) }</th>
							{ view !== 'trash' && <th scope="col" className="cfmb-list__narrow">{ __( 'Active', 'cfmb' ) }</th> }
						</tr>
					</thead>
					<tbody>
						{ groups.map( ( group ) => (
							<tr key={ group.key } className={ busy === group.key ? 'is-busy' : '' }>
								<td className="column-primary">
									<strong>
										{ view === 'trash' ? (
											group.title
										) : (
											<a
												className="row-title"
												href={ `?page=cfmb&group=${ group.key }` }
												onClick={ ( event ) => {
													event.preventDefault();
													navigate( group.key );
												} }
											>
												{ group.title }
											</a>
										) }
									</strong>
									{ group.settings?.preset_only && <span className="cfmb-badge">{ __( 'Preset', 'cfmb' ) }</span> }
									<SourceBadge source={ group.source } />
									<div className="cfmb-list__actions">
										{ view === 'trash' ? (
											<>
												<Button variant="link" onClick={ () => restore( group ) }>
													{ __( 'Restore', 'cfmb' ) }
												</Button>
												<Button variant="link" isDestructive onClick={ () => setConfirmDelete( group ) }>
													{ __( 'Delete permanently', 'cfmb' ) }
												</Button>
											</>
										) : (
											<>
												<Button variant="link" onClick={ () => navigate( group.key ) }>
													{ group.source && group.source !== 'db' ? __( 'View', 'cfmb' ) : __( 'Edit', 'cfmb' ) }
												</Button>
												{ group.source && group.source !== 'db' && (
													<Button variant="link" onClick={ () => importGroup( group ) }>
														{ __( 'Import to edit', 'cfmb' ) }
													</Button>
												) }
												<Button variant="link" onClick={ () => duplicate( group ) }>
													{ __( 'Duplicate', 'cfmb' ) }
												</Button>
												{ ( ! group.source || group.source === 'db' ) && (
													<Button variant="link" isDestructive onClick={ () => trash( group ) }>
														{ __( 'Trash', 'cfmb' ) }
													</Button>
												) }
											</>
										) }
									</div>
								</td>
								<td>{ group.settings?.preset_only ? __( 'Inserted into other groups', 'cfmb' ) : groupPlaces( group ) }</td>
								<td className="cfmb-list__narrow">
									{ sprintf(
										/* translators: %d: number of fields */
										_n( '%d field', '%d fields', fieldCount( group ), 'cfmb' ),
										fieldCount( group )
									) }
								</td>
								{ view !== 'trash' && (
									<td className="cfmb-list__narrow">
										<FormToggle
											checked={ group.active }
											disabled={ busy === group.key || ( group.source && group.source !== 'db' ) }
											aria-label={
												/* translators: %s: field group title */
												sprintf( __( 'Show "%s" on edit screens', 'cfmb' ), group.title )
											}
											onChange={ () => toggleActive( group ) }
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
					title={ __( 'Delete this field group?', 'cfmb' ) }
					confirmLabel={ __( 'Delete permanently', 'cfmb' ) }
					isDestructive
					onConfirm={ () => deleteForever( confirmDelete ) }
					onCancel={ () => setConfirmDelete( null ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: field group title */
							__( '"%s" will be deleted for good. Values already saved on your content stay in the database.', 'cfmb' ),
							confirmDelete.title
						) }
					</p>
				</ConfirmModal>
			) }
		</div>
	);
}
