/**
 * List of post types, taxonomies or options pages, with a trash view.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { Button, FormToggle, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	deleteContent,
	duplicateContent,
	listContent,
	restoreContent,
	syncPull,
	trashContent,
	updateContent,
} from '../../api';
import { errorSnackbar, snackbar } from '../../notices';
import ConfirmModal from '../ConfirmModal';
import SourceBadge from '../SourceBadge';
import { uiIcon } from '../../../shared/ui-icons';

const EMPTY_ICONS = {
	post_type: 'post-type',
	taxonomy: 'taxonomy',
	options_page: 'options-page',
	connection: 'connection',
};

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
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'The list could not be loaded.',
							'custom-fields-meta-box'
						)
				)
			);
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

	const replace = ( saved ) =>
		setItems( ( current ) =>
			current.map( ( item ) => ( item.key === saved.key ? saved : item ) )
		);

	const toggleActive = ( item ) =>
		run( item.key, async () => {
			const saved = await updateContent( kind.kind, {
				...item,
				active: ! item.active,
			} );
			replace( saved );
			snackbar(
				saved.active
					? /* translators: %s: title */ sprintf(
							__( '"%s" is active.', 'custom-fields-meta-box' ),
							saved.title
					  )
					: /* translators: %s: title */ sprintf(
							__( '"%s" is inactive.', 'custom-fields-meta-box' ),
							saved.title
					  )
			);
		} );

	const duplicate = ( item ) =>
		run( item.key, async () => {
			await duplicateContent( kind.kind, item.key );
			await load();
			snackbar(
				__(
					'Copy created. It stays inactive until you turn it on.',
					'custom-fields-meta-box'
				)
			);
		} );

	const importItem = ( item ) =>
		run( item.key, async () => {
			const result = await syncPull( [
				{ kind: kind.kind, key: item.key },
			] );
			if ( result.errors?.length ) {
				throw new Error( result.errors[ 0 ].message );
			}
			await load();
			/* translators: %s: title */
			snackbar(
				sprintf(
					/* translators: %s: item title */ __(
						'"%s" imported. You can edit it here now.',
						'custom-fields-meta-box'
					),
					item.title
				)
			);
		} );

	const trash = ( item ) =>
		run( item.key, async () => {
			await trashContent( kind.kind, item.key );
			setItems( ( current ) =>
				current.filter( ( entry ) => entry.key !== item.key )
			);
			snackbar(
				/* translators: %s: title */
				sprintf(
					/* translators: %s: item title */ __(
						'"%s" moved to the trash. Its content stays in the database.',
						'custom-fields-meta-box'
					),
					item.title
				),
				[
					{
						label: __( 'Undo', 'custom-fields-meta-box' ),
						onClick: () =>
							run( item.key, async () => {
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
			setItems( ( current ) =>
				current.filter( ( entry ) => entry.key !== item.key )
			);
			/* translators: %s: title */
			snackbar(
				sprintf(
					/* translators: %s: item title */ __(
						'"%s" restored.',
						'custom-fields-meta-box'
					),
					item.title
				)
			);
		} );

	const deleteForever = ( item ) =>
		run( item.key, async () => {
			setConfirmDelete( null );
			await deleteContent( kind.kind, item.key );
			setItems( ( current ) =>
				current.filter( ( entry ) => entry.key !== item.key )
			);
			/* translators: %s: title */
			snackbar(
				sprintf(
					/* translators: %s: item title */ __(
						'"%s" deleted.',
						'custom-fields-meta-box'
					),
					item.title
				)
			);
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

			{ ! items && ! loadError && (
				<div className="cfmb-list__loading">
					<Spinner /> { __( 'Loading…', 'custom-fields-meta-box' ) }
				</div>
			) }

			{ items && items.length === 0 && (
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
								{ uiIcon(
									EMPTY_ICONS[ kind.kind ] || 'groups'
								) }
							</span>
							<h2>{ names.empty_title }</h2>
							<p>{ names.empty_text }</p>
							<Button
								variant="primary"
								onClick={ () => navigate( 'new' ) }
							>
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
							<th scope="col" className="column-primary">
								{ names.title_label }
							</th>
							<th scope="col">{ names.name_label }</th>
							{ view !== 'trash' && (
								<th scope="col" className="cfmb-list__narrow">
									{ __( 'Active', 'custom-fields-meta-box' ) }
								</th>
							) }
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<tr
								key={ item.key }
								className={ busy === item.key ? 'is-busy' : '' }
							>
								<td className="column-primary">
									<strong>
										{ view === 'trash' ? (
											item.title
										) : (
											<a
												className="row-title"
												href={ `?page=${ new URL(
													window.location.href
												).searchParams.get(
													'page'
												) }&item=${ item.key }` }
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
												<Button
													variant="link"
													onClick={ () =>
														restore( item )
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
														setConfirmDelete( item )
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
														navigate( item.key )
													}
												>
													{ local( item )
														? __(
																'View',
																'custom-fields-meta-box'
														  )
														: __(
																'Edit',
																'custom-fields-meta-box'
														  ) }
												</Button>
												{ local( item ) && (
													<Button
														variant="link"
														onClick={ () =>
															importItem( item )
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
														duplicate( item )
													}
												>
													{ __(
														'Duplicate',
														'custom-fields-meta-box'
													) }
												</Button>
												{ ! local( item ) && (
													<Button
														variant="link"
														isDestructive
														onClick={ () =>
															trash( item )
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
									<code>{ item.name }</code>
								</td>
								{ view !== 'trash' && (
									<td className="cfmb-list__narrow">
										<FormToggle
											checked={ item.active }
											disabled={
												busy === item.key ||
												local( item )
											}
											aria-label={
												/* translators: %s: title */
												sprintf(
													/* translators: %s: item title */ __(
														'Turn "%s" on',
														'custom-fields-meta-box'
													),
													item.title
												)
											}
											onChange={ () =>
												toggleActive( item )
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
						'Delete permanently?',
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
							/* translators: %s: title */
							__(
								'"%s" will be deleted for good, along with its sync file in uploads. Posts, terms and values made with it stay in the database.',
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
