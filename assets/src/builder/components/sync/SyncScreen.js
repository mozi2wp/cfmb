/**
 * Schema Sync: the state of every definition, with pull, push, review and export.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import config from '../../config';
import { syncExport, syncPull, syncPush, syncStatus } from '../../api';
import { errorSnackbar, snackbar } from '../../notices';
import DiffModal from './DiffModal';

const KIND_LABELS = {
	group: __( 'Field group', 'custom-fields-meta-box' ),
	post_type: __( 'Post type', 'custom-fields-meta-box' ),
	taxonomy: __( 'Taxonomy', 'custom-fields-meta-box' ),
	options_page: __( 'Options page', 'custom-fields-meta-box' ),
	connection: __( 'Connection', 'custom-fields-meta-box' ),
};

const STATUS = {
	conflict: {
		label: __( 'Both changed', 'custom-fields-meta-box' ),
		help: __(
			'The database and the file changed since the last sync. Review to merge them.',
			'custom-fields-meta-box'
		),
		tone: 'error',
	},
	file_changed: {
		label: __( 'File is newer', 'custom-fields-meta-box' ),
		help: __(
			'The file changed, for example after a deploy. Pull it to update the database.',
			'custom-fields-meta-box'
		),
		tone: 'warning',
	},
	file_only: {
		label: __( 'Only in a file', 'custom-fields-meta-box' ),
		help: __(
			'Active from the file. Import it to edit it in the builder.',
			'custom-fields-meta-box'
		),
		tone: 'warning',
	},
	db_changed: {
		label: __( 'Database is newer', 'custom-fields-meta-box' ),
		help: __(
			'Saved since the file was written. Push to update the file.',
			'custom-fields-meta-box'
		),
		tone: 'warning',
	},
	no_file: {
		label: __( 'No file yet', 'custom-fields-meta-box' ),
		help: __( 'Push to write its file.', 'custom-fields-meta-box' ),
		tone: 'info',
	},
	synced: {
		label: __( 'In sync', 'custom-fields-meta-box' ),
		help: '',
		tone: 'success',
	},
	code: {
		label: __( 'Registered in code', 'custom-fields-meta-box' ),
		help: __(
			'Defined with a cfmb_register_*() call. Import it to edit it in the builder.',
			'custom-fields-meta-box'
		),
		tone: 'info',
	},
};

const ORDER = [
	'conflict',
	'file_changed',
	'file_only',
	'db_changed',
	'no_file',
	'code',
	'synced',
];

const download = ( name, text, type ) => {
	const url = URL.createObjectURL( new Blob( [ text ], { type } ) );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = name;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
};

const id = ( row ) => `${ row.kind }:${ row.key }`;

export default function SyncScreen() {
	const [ state, setState ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ busy, setBusy ] = useState( false );
	const [ reviewing, setReviewing ] = useState( null );
	const [ phpCode, setPhpCode ] = useState( '' );

	const load = useCallback( () => {
		setLoadError( '' );
		return syncStatus()
			.then( setState )
			.catch( ( error ) =>
				setLoadError(
					error.message ||
						__(
							'The sync state could not be loaded.',
							'custom-fields-meta-box'
						)
				)
			);
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const rows = useMemo(
		() =>
			( state?.rows || [] )
				.slice()
				.sort(
					( a, b ) =>
						ORDER.indexOf( a.status ) - ORDER.indexOf( b.status ) ||
						a.title.localeCompare( b.title )
				),
		[ state ]
	);
	const pending = rows.filter(
		( row ) => ! [ 'synced', 'code' ].includes( row.status )
	);

	const run = async ( task ) => {
		setBusy( true );
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
			setBusy( false );
		}
	};

	const apply = ( action, items ) =>
		run( async () => {
			const result = await ( action === 'pull'
				? syncPull( items )
				: syncPush( items ) );
			await load();
			setSelected( new Set() );
			if ( result.errors?.length ) {
				errorSnackbar(
					result.errors
						.map(
							( entry ) => `${ entry.key }: ${ entry.message }`
						)
						.join( ' ' )
				);
			}
			if ( result.rows?.length ) {
				snackbar(
					sprintf(
						/* translators: %d: number of items */
						_n(
							'%d item updated.',
							'%d items updated.',
							result.rows.length,
							'custom-fields-meta-box'
						),
						result.rows.length
					)
				);
			}
		} );

	const exportSelected = ( format ) =>
		run( async () => {
			const items = rows
				.filter( ( row ) => selected.has( id( row ) ) )
				.map( ( row ) => ( { kind: row.kind, key: row.key } ) );
			const result = await syncExport( items, format );
			if ( format === 'php' ) {
				setPhpCode( result.code );
				return;
			}
			if ( result.files.length === 1 ) {
				download(
					result.files[ 0 ].name,
					result.files[ 0 ].content,
					'application/json'
				);
				return;
			}
			download(
				'cfmb-export.json',
				JSON.stringify(
					result.files.map( ( file ) => JSON.parse( file.content ) ),
					null,
					2
				),
				'application/json'
			);
		} );

	const selectedItems = ( statuses ) =>
		rows
			.filter(
				( row ) =>
					selected.has( id( row ) ) && statuses.includes( row.status )
			)
			.map( ( row ) => ( { kind: row.kind, key: row.key } ) );

	const toggle = ( row, on ) =>
		setSelected( ( current ) => {
			const next = new Set( current );
			if ( on ) {
				next.add( id( row ) );
			} else {
				next.delete( id( row ) );
			}
			return next;
		} );

	const actionsFor = ( row ) => {
		const item = [ { kind: row.kind, key: row.key } ];
		switch ( row.status ) {
			case 'conflict':
				return [
					{
						label: __( 'Review', 'custom-fields-meta-box' ),
						onClick: () => setReviewing( row ),
						primary: true,
					},
				];
			case 'file_changed':
				return [
					{
						label: __( 'Pull from file', 'custom-fields-meta-box' ),
						onClick: () => apply( 'pull', item ),
						primary: true,
					},
					{
						label: __( 'Review', 'custom-fields-meta-box' ),
						onClick: () => setReviewing( row ),
					},
				];
			case 'db_changed':
				return [
					{
						label: __( 'Write file', 'custom-fields-meta-box' ),
						onClick: () => apply( 'push', item ),
						primary: true,
					},
					{
						label: __( 'Review', 'custom-fields-meta-box' ),
						onClick: () => setReviewing( row ),
					},
				];
			case 'no_file':
				return [
					{
						label: __( 'Write file', 'custom-fields-meta-box' ),
						onClick: () => apply( 'push', item ),
						primary: true,
					},
				];
			case 'file_only':
			case 'code':
				return [
					{
						label: __( 'Import', 'custom-fields-meta-box' ),
						onClick: () => apply( 'pull', item ),
						primary: row.status === 'file_only',
					},
				];
			default:
				return [];
		}
	};

	if ( loadError ) {
		return (
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
		);
	}

	if ( ! state ) {
		return (
			<div className="cfmb-list__loading">
				<Spinner />{ ' ' }
				{ __( 'Checking files…', 'custom-fields-meta-box' ) }
			</div>
		);
	}

	const pullable = selectedItems( [ 'file_changed', 'file_only', 'code' ] );
	const pushable = selectedItems( [ 'db_changed', 'no_file' ] );

	return (
		<div className="cfmb-list cfmb-sync">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">
					{ __( 'Sync', 'custom-fields-meta-box' ) }
				</h1>
			</div>

			<div className="cfmb-sync__intro">
				<p>
					{ __(
						'Field groups, post types, taxonomies and options pages are saved as JSON files too, so they can go into version control and move between sites.',
						'custom-fields-meta-box'
					) }
				</p>
				<p>
					{ __( 'Saved to:', 'custom-fields-meta-box' ) }{ ' ' }
					<code>{ state.saveDir }</code>
					{ state.loadDirs.length > 1 && (
						<>
							<br />
							{ __(
								'Also read from:',
								'custom-fields-meta-box'
							) }{ ' ' }
							{ state.loadDirs
								.filter( ( dir ) => dir.path !== state.saveDir )
								.map( ( dir ) => (
									<code key={ dir.path }>{ dir.path }</code>
								) ) }
						</>
					) }
				</p>
				{ ! state.autoWrite && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Files are not written when you save. Push changes here, or turn on "Write the file each time something is saved" in Settings.',
							'custom-fields-meta-box'
						) }{ ' ' }
						<a href={ config.settingsUrl }>
							{ __( 'Settings', 'custom-fields-meta-box' ) }
						</a>
					</Notice>
				) }
			</div>

			{ pending.length === 0 && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Everything is in sync.', 'custom-fields-meta-box' ) }
				</Notice>
			) }

			<div className="cfmb-sync__bulk">
				<Button
					variant="secondary"
					disabled={ busy || ! pullable.length }
					onClick={ () => apply( 'pull', pullable ) }
				>
					{ __(
						'Pull selected from files',
						'custom-fields-meta-box'
					) }
				</Button>
				<Button
					variant="secondary"
					disabled={ busy || ! pushable.length }
					onClick={ () => apply( 'push', pushable ) }
				>
					{ __( 'Write selected files', 'custom-fields-meta-box' ) }
				</Button>
				<Button
					variant="tertiary"
					disabled={ busy || ! selected.size }
					onClick={ () => exportSelected( 'json' ) }
				>
					{ __( 'Download JSON', 'custom-fields-meta-box' ) }
				</Button>
				<Button
					variant="tertiary"
					disabled={ busy || ! selected.size }
					onClick={ () => exportSelected( 'php' ) }
				>
					{ __( 'Get PHP code', 'custom-fields-meta-box' ) }
				</Button>
				{ busy && <Spinner /> }
			</div>

			<table className="wp-list-table widefat fixed striped cfmb-list__table cfmb-sync__table">
				<thead>
					<tr>
						<td className="check-column">
							<CheckboxControl
								__nextHasNoMarginBottom
								label={
									<span className="screen-reader-text">
										{ __(
											'Select all',
											'custom-fields-meta-box'
										) }
									</span>
								}
								checked={
									rows.length > 0 &&
									selected.size === rows.length
								}
								onChange={ ( on ) =>
									setSelected(
										on
											? new Set( rows.map( id ) )
											: new Set()
									)
								}
							/>
						</td>
						<th scope="col" className="column-primary">
							{ __( 'Title', 'custom-fields-meta-box' ) }
						</th>
						<th scope="col">
							{ __( 'State', 'custom-fields-meta-box' ) }
						</th>
						<th scope="col">
							{ __( 'File', 'custom-fields-meta-box' ) }
						</th>
						<th scope="col">
							{ __( 'Actions', 'custom-fields-meta-box' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => {
						const status = STATUS[ row.status ] || STATUS.synced;
						return (
							<tr key={ id( row ) }>
								<th scope="row" className="check-column">
									<CheckboxControl
										__nextHasNoMarginBottom
										label={
											<span className="screen-reader-text">
												{ row.title }
											</span>
										}
										checked={ selected.has( id( row ) ) }
										onChange={ ( on ) => toggle( row, on ) }
									/>
								</th>
								<td className="column-primary">
									<strong>{ row.title }</strong>
									<div className="cfmb-sync__meta">
										{ KIND_LABELS[ row.kind ] } ·{ ' ' }
										<code>{ row.key }</code>
									</div>
								</td>
								<td>
									<span
										className={ `cfmb-sync__status cfmb-sync__status--${ status.tone }` }
									>
										{ status.label }
									</span>
									{ status.help && (
										<div className="cfmb-sync__help">
											{ status.help }
										</div>
									) }
								</td>
								<td>
									{ row.file ? (
										<span title={ row.file.path }>
											{ row.file.location }
										</span>
									) : (
										'—'
									) }
								</td>
								<td className="cfmb-sync__actions">
									{ actionsFor( row ).map( ( action ) => (
										<Button
											key={ action.label }
											variant={
												action.primary
													? 'secondary'
													: 'link'
											}
											disabled={ busy }
											onClick={ action.onClick }
										>
											{ action.label }
										</Button>
									) ) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>

			{ reviewing && (
				<DiffModal
					row={ reviewing }
					onClose={ () => setReviewing( null ) }
					onMerged={ () => {
						setReviewing( null );
						load();
						snackbar(
							__(
								'Merged and saved to the database and the file.',
								'custom-fields-meta-box'
							)
						);
					} }
				/>
			) }

			{ phpCode && (
				<Modal
					title={ __( 'PHP code', 'custom-fields-meta-box' ) }
					onRequestClose={ () => setPhpCode( '' ) }
					size="large"
					className="cfmb-php-export"
				>
					<p>
						{ __(
							"Put this in your theme's functions.php or a plugin. Items registered in code show here as read-only. Remove them from the database (or keep them inactive) to avoid two copies.",
							'custom-fields-meta-box'
						) }
					</p>
					<textarea
						className="cfmb-php-export__code code"
						readOnly
						rows={ 18 }
						value={ phpCode }
						onFocus={ ( event ) => event.target.select() }
					/>
					<div className="cfmb-confirm__actions">
						<Button
							variant="secondary"
							onClick={ () =>
								download(
									'cfmb-definitions.php',
									phpCode,
									'text/x-php'
								)
							}
						>
							{ __( 'Download', 'custom-fields-meta-box' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () =>
								window.navigator.clipboard
									?.writeText( phpCode )
									.then( () =>
										snackbar(
											__(
												'Copied.',
												'custom-fields-meta-box'
											)
										)
									)
							}
						>
							{ __( 'Copy', 'custom-fields-meta-box' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
