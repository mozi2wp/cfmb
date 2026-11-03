/**
 * Field Group list with trash view.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, FormToggle, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	deleteGroup,
	duplicateGroup,
	listGroups,
	restoreGroup,
	syncPull,
	trashGroup,
	updateGroup,
} from '../api';
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
	const label = sprintf(
		/* translators: %s: block name */ __( 'Field Block cfmb/%s', 'custom-fields-meta-box' ),
		block.name
	);
	return group.location?.length
		? `${ label }; ${ locationSummary( group.location ) }`
		: label;
}
import SourceBadge from './SourceBadge';
import { uiIcon } from '../../shared/ui-icons';

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
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'Field groups could not be loaded.',
							'custom-fields-meta-box'
						)
				)
			);
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
			errorSnackbar(
				error.message ||
					__(
						'That did not work. Please try again.',
						'custom-fields-meta-box'
					)
			);
		} finally {
			setBusy( '' );
		}
	};

	const toggleActive = ( group ) =>
		run( group.key, async () => {
			const saved = await updateGroup( {
				...group,
				active: ! group.active,
			} );
			setGroups( ( current ) =>
				current.map( ( item ) =>
					item.key === saved.key ? saved : item
				)
			);
			snackbar(
				saved.active
					? /* translators: %s: field group title */ sprintf(
							__( '"%s" is active.', 'custom-fields-meta-box' ),
							saved.title
					  )
					: /* translators: %s: field group title */ sprintf(
							__( '"%s" is inactive.', 'custom-fields-meta-box' ),
							saved.title
					  )
			);
		} );

	const duplicate = ( group ) =>
		run( group.key, async () => {
			await duplicateGroup( group.key );
			await load();
			snackbar(
				__(
					'Copy created. It stays inactive until you turn it on.',
					'custom-fields-meta-box'
				)
			);
		} );

	const trash = ( group ) =>
		run( group.key, async () => {
			await trashGroup( group.key );
			setGroups( ( current ) =>
				current.filter( ( item ) => item.key !== group.key )
			);
			snackbar(
				/* translators: %s: field group title */
				sprintf(
					/* translators: %s: group title */ __( '"%s" moved to the trash.', 'custom-fields-meta-box' ),
					group.title
				),
				[
					{
						label: __( 'Undo', 'custom-fields-meta-box' ),
						onClick: () =>
							run( group.key, async () => {
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
			setGroups( ( current ) =>
				current.filter( ( item ) => item.key !== group.key )
			);
			/* translators: %s: field group title */
			snackbar(
				sprintf(
					/* translators: %s: group title */ __( '"%s" restored.', 'custom-fields-meta-box' ),
					group.title
				)
			);
		} );

	const importGroup = ( group ) =>
		run( group.key, async () => {
			const result = await syncPull( [
				{ kind: 'group', key: group.key },
			] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			await load();
			/* translators: %s: field group title */
			snackbar(
				sprintf(
					/* translators: %s: group title */ __(
						'"%s" imported. You can edit it here now.',
						'custom-fields-meta-box'
					),
					group.title
				)
			);
		} );

	const deleteForever = ( group ) =>
		run( group.key, async () => {
			setConfirmDelete( null );
			await deleteGroup( group.key );
			setGroups( ( current ) =>
				current.filter( ( item ) => item.key !== group.key )
			);
			/* translators: %s: field group title */
			snackbar(
				sprintf(
					/* translators: %s: group title */ __( '"%s" deleted.', 'custom-fields-meta-box' ),
					group.title
				)
			);
		} );

	const fieldCount = ( group ) =>
		group.fields.filter( ( field ) => storesValue( field.type ) ).length;

	return (
		<div className="cfmb-list">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">
					{ __( 'Field Groups', 'custom-fields-meta-box' ) }
				</h1>
				<Button variant="primary" onClick={ () => navigate( 'new' ) }>
					{ __( 'Add field group', 'custom-fields-meta-box' ) }
				</Button>
			</div>

			<ul className="subsubsub cfmb-list__views">
				<li>
					<Button
						variant="link"
						className={ view === 'any' ? 'current' : '' }
						aria-current={ view === 'any' ? 'page' : undefined }
						onClick={ () => setView( 'any' ) }
					>
						{ __( 'All', 'custom-fields-meta-box' ) }
					</Button>
					{ ' | ' }
				</li>
				<li>
					<Button
						variant="link"
						className={ view === 'trash' ? 'current' : '' }
						aria-current={ view === 'trash' ? 'page' : undefined }
						onClick={ () => setView( 'trash' ) }
					>
						{ __( 'Trash', 'custom-fields-meta-box' ) }
					</Button>
				</li>
			</ul>

			{ loadError && (
				<Notice
					status="error"
					isDismissible={ false }
					actions={ [
						{
							label: __( 'Try again', 'custom-fields-meta-box' ),
							onClick: load,
						},
					] }
				>
					{ loadError }
				</Notice>
			) }

			{ ! groups && ! loadError && (
				<div className="cfmb-list__loading">
					<Spinner />{ ' ' }
					{ __( 'Loading field groups…', 'custom-fields-meta-box' ) }
				</div>
			) }

			{ groups && groups.length === 0 && (
				<div className="cfmb-empty">
					{ view === 'trash' ? (
						<p>
							{ __(
								'The trash is empty.',
								'custom-fields-meta-box'
							) }
						</p>
					) : (
						<>
							<span
								className="cfmb-icon cfmb-empty__icon"
								aria-hidden="true"
							>
								{ uiIcon( 'groups' ) }
							</span>
							<h2>
								{ __(
									'Create your first field group',
									'custom-fields-meta-box'
								) }
							</h2>
							<p>
								{ __(
									'A field group is a set of fields, like Price and Gallery, shown on the screens you choose, such as every Product or the Contact page.',
									'custom-fields-meta-box'
								) }
							</p>
							<Button
								variant="primary"
								onClick={ () => navigate( 'new' ) }
							>
								{ __(
									'Add field group',
									'custom-fields-meta-box'
								) }
							</Button>
						</>
					) }
				</div>
			) }

			{ groups && groups.length > 0 && (
				<table className="wp-list-table widefat fixed striped cfmb-list__table">
					<thead>
						<tr>
							<th scope="col" className="column-primary">
								{ __( 'Title', 'custom-fields-meta-box' ) }
							</th>
							<th scope="col">
								{ __( 'Shown on', 'custom-fields-meta-box' ) }
							</th>
							<th scope="col" className="cfmb-list__narrow">
								{ __( 'Fields', 'custom-fields-meta-box' ) }
							</th>
							{ view !== 'trash' && (
								<th scope="col" className="cfmb-list__narrow">
									{ __( 'Active', 'custom-fields-meta-box' ) }
								</th>
							) }
						</tr>
					</thead>
					<tbody>
						{ groups.map( ( group ) => (
							<tr
								key={ group.key }
								className={
									busy === group.key ? 'is-busy' : ''
								}
							>
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
									{ group.settings?.preset_only && (
										<span className="cfmb-badge">
											{ __(
												'Preset',
												'custom-fields-meta-box'
											) }
										</span>
									) }
									<SourceBadge source={ group.source } />
									<div className="cfmb-list__actions">
										{ view === 'trash' ? (
											<>
												<Button
													variant="link"
													onClick={ () =>
														restore( group )
													}
												>
													{ __(
														'Restore',
														'custom-fields-meta-box'
													) }
												</Button>
												<Button
													variant="link"
													isDestructive
													onClick={ () =>
														setConfirmDelete(
															group
														)
													}
												>
													{ __(
														'Delete permanently',
														'custom-fields-meta-box'
													) }
												</Button>
											</>
										) : (
											<>
												<Button
													variant="link"
													onClick={ () =>
														navigate( group.key )
													}
												>
													{ group.source &&
													group.source !== 'db'
														? __(
																'View',
																'custom-fields-meta-box'
														  )
														: __(
																'Edit',
																'custom-fields-meta-box'
														  ) }
												</Button>
												{ group.source &&
													group.source !== 'db' && (
														<Button
															variant="link"
															onClick={ () =>
																importGroup(
																	group
																)
															}
														>
															{ __(
																'Import to edit',
																'custom-fields-meta-box'
															) }
														</Button>
													) }
												<Button
													variant="link"
													onClick={ () =>
														duplicate( group )
													}
												>
													{ __(
														'Duplicate',
														'custom-fields-meta-box'
													) }
												</Button>
												{ ( ! group.source ||
													group.source === 'db' ) && (
													<Button
														variant="link"
														isDestructive
														onClick={ () =>
															trash( group )
														}
													>
														{ __(
															'Trash',
															'custom-fields-meta-box'
														) }
													</Button>
												) }
											</>
										) }
									</div>
								</td>
								<td>
									{ group.settings?.preset_only
										? __(
												'Inserted into other groups',
												'custom-fields-meta-box'
										  )
										: groupPlaces( group ) }
								</td>
								<td className="cfmb-list__narrow">
									{ sprintf(
										/* translators: %d: number of fields */
										_n(
											'%d field',
											'%d fields',
											fieldCount( group ),
											'custom-fields-meta-box'
										),
										fieldCount( group )
									) }
								</td>
								{ view !== 'trash' && (
									<td className="cfmb-list__narrow">
										<FormToggle
											checked={ group.active }
											disabled={
												busy === group.key ||
												( group.source &&
													group.source !== 'db' )
											}
											aria-label={
												/* translators: %s: field group title */
												sprintf(
													/* translators: %s: group title */ __(
														'Show "%s" on edit screens',
														'custom-fields-meta-box'
													),
													group.title
												)
											}
											onChange={ () =>
												toggleActive( group )
											}
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
					title={ __(
						'Delete this field group?',
						'custom-fields-meta-box'
					) }
					confirmLabel={ __(
						'Delete permanently',
						'custom-fields-meta-box'
					) }
					isDestructive
					onConfirm={ () => deleteForever( confirmDelete ) }
					onCancel={ () => setConfirmDelete( null ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: field group title */
							__(
								'"%s" will be deleted for good. Values already saved on your content stay in the database.',
								'custom-fields-meta-box'
							),
							confirmDelete.title
						) }
					</p>
				</ConfirmModal>
			) }
		</div>
	);
}
