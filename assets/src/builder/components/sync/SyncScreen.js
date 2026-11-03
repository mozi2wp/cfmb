/**
 * Schema Sync: the state of every definition, with pull, push, review and export.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, CheckboxControl, Modal, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import config from '../../config';
import { syncExport, syncPull, syncPush, syncStatus } from '../../api';
import { errorSnackbar, snackbar } from '../../notices';
import DiffModal from './DiffModal';

const KIND_LABELS = {
	group: __( 'Field group', 'cfmb' ),
	post_type: __( 'Post type', 'cfmb' ),
	taxonomy: __( 'Taxonomy', 'cfmb' ),
	options_page: __( 'Options page', 'cfmb' ),
	connection: __( 'Connection', 'cfmb' ),
};

const STATUS = {
	conflict: { label: __( 'Both changed', 'cfmb' ), help: __( 'The database and the file changed since the last sync. Review to merge them.', 'cfmb' ), tone: 'error' },
	file_changed: { label: __( 'File is newer', 'cfmb' ), help: __( 'The file changed, for example after a deploy. Pull it to update the database.', 'cfmb' ), tone: 'warning' },
	file_only: { label: __( 'Only in a file', 'cfmb' ), help: __( 'Active from the file. Import it to edit it in the builder.', 'cfmb' ), tone: 'warning' },
	db_changed: { label: __( 'Database is newer', 'cfmb' ), help: __( 'Saved since the file was written. Push to update the file.', 'cfmb' ), tone: 'warning' },
	no_file: { label: __( 'No file yet', 'cfmb' ), help: __( 'Push to write its file.', 'cfmb' ), tone: 'info' },
	synced: { label: __( 'In sync', 'cfmb' ), help: '', tone: 'success' },
	code: { label: __( 'Registered in code', 'cfmb' ), help: __( 'Defined with a cfmb_register_*() call. Import it to edit it in the builder.', 'cfmb' ), tone: 'info' },
};

const ORDER = [ 'conflict', 'file_changed', 'file_only', 'db_changed', 'no_file', 'code', 'synced' ];

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
			.catch( ( error ) => setLoadError( error.message || __( 'The sync state could not be loaded.', 'cfmb' ) ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const rows = useMemo( () => ( state?.rows || [] ).slice().sort( ( a, b ) => ORDER.indexOf( a.status ) - ORDER.indexOf( b.status ) || a.title.localeCompare( b.title ) ), [ state ] );
	const pending = rows.filter( ( row ) => ! [ 'synced', 'code' ].includes( row.status ) );

	const run = async ( task ) => {
		setBusy( true );
		try {
			await task();
		} catch ( error ) {
			errorSnackbar( error.message || __( 'That did not work. Please try again.', 'cfmb' ) );
		} finally {
			setBusy( false );
		}
	};

	const apply = ( action, items ) =>
		run( async () => {
			const result = await ( action === 'pull' ? syncPull( items ) : syncPush( items ) );
			await load();
			setSelected( new Set() );
			if ( result.errors?.length ) {
				errorSnackbar( result.errors.map( ( entry ) => `${ entry.key }: ${ entry.message }` ).join( ' ' ) );
			}
			if ( result.rows?.length ) {
				snackbar(
					sprintf(
						/* translators: %d: number of items */
						_n( '%d item updated.', '%d items updated.', result.rows.length, 'cfmb' ),
						result.rows.length
					)
				);
			}
		} );

	const exportSelected = ( format ) =>
		run( async () => {
			const items = rows.filter( ( row ) => selected.has( id( row ) ) ).map( ( row ) => ( { kind: row.kind, key: row.key } ) );
			const result = await syncExport( items, format );
			if ( format === 'php' ) {
				setPhpCode( result.code );
				return;
			}
			if ( result.files.length === 1 ) {
				download( result.files[ 0 ].name, result.files[ 0 ].content, 'application/json' );
				return;
			}
			download( 'cfmb-export.json', JSON.stringify( result.files.map( ( file ) => JSON.parse( file.content ) ), null, 2 ), 'application/json' );
		} );

	const selectedItems = ( statuses ) =>
		rows.filter( ( row ) => selected.has( id( row ) ) && statuses.includes( row.status ) ).map( ( row ) => ( { kind: row.kind, key: row.key } ) );

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
				return [ { label: __( 'Review', 'cfmb' ), onClick: () => setReviewing( row ), primary: true } ];
			case 'file_changed':
				return [
					{ label: __( 'Pull from file', 'cfmb' ), onClick: () => apply( 'pull', item ), primary: true },
					{ label: __( 'Review', 'cfmb' ), onClick: () => setReviewing( row ) },
				];
			case 'db_changed':
				return [
					{ label: __( 'Write file', 'cfmb' ), onClick: () => apply( 'push', item ), primary: true },
					{ label: __( 'Review', 'cfmb' ), onClick: () => setReviewing( row ) },
				];
			case 'no_file':
				return [ { label: __( 'Write file', 'cfmb' ), onClick: () => apply( 'push', item ), primary: true } ];
			case 'file_only':
			case 'code':
				return [ { label: __( 'Import', 'cfmb' ), onClick: () => apply( 'pull', item ), primary: row.status === 'file_only' } ];
			default:
				return [];
		}
	};

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false } actions={ [ { label: __( 'Try again', 'cfmb' ), onClick: load } ] }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! state ) {
		return (
			<div className="cfmb-list__loading">
				<Spinner /> { __( 'Checking files…', 'cfmb' ) }
			</div>
		);
	}

	const pullable = selectedItems( [ 'file_changed', 'file_only', 'code' ] );
	const pushable = selectedItems( [ 'db_changed', 'no_file' ] );

	return (
		<div className="cfmb-list cfmb-sync">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">{ __( 'Sync', 'cfmb' ) }</h1>
			</div>

			<div className="cfmb-sync__intro">
				<p>
					{ __( 'Field groups, post types, taxonomies and options pages are saved as JSON files too, so they can go into version control and move between sites.', 'cfmb' ) }
				</p>
				<p>
					{ __( 'Saved to:', 'cfmb' ) } <code>{ state.saveDir }</code>
					{ state.loadDirs.length > 1 && (
						<>
							<br />
							{ __( 'Also read from:', 'cfmb' ) }{ ' ' }
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
						{ __( 'Files are not written when you save. Push changes here, or turn on "Write the file each time something is saved" in Settings.', 'cfmb' ) }{ ' ' }
						<a href={ config.settingsUrl }>{ __( 'Settings', 'cfmb' ) }</a>
					</Notice>
				) }
			</div>

			{ pending.length === 0 && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Everything is in sync.', 'cfmb' ) }
				</Notice>
			) }

			<div className="cfmb-sync__bulk">
				<Button variant="secondary" disabled={ busy || ! pullable.length } onClick={ () => apply( 'pull', pullable ) }>
					{ __( 'Pull selected from files', 'cfmb' ) }
				</Button>
				<Button variant="secondary" disabled={ busy || ! pushable.length } onClick={ () => apply( 'push', pushable ) }>
					{ __( 'Write selected files', 'cfmb' ) }
				</Button>
				<Button variant="tertiary" disabled={ busy || ! selected.size } onClick={ () => exportSelected( 'json' ) }>
					{ __( 'Download JSON', 'cfmb' ) }
				</Button>
				<Button variant="tertiary" disabled={ busy || ! selected.size } onClick={ () => exportSelected( 'php' ) }>
					{ __( 'Get PHP code', 'cfmb' ) }
				</Button>
				{ busy && <Spinner /> }
			</div>

			<table className="wp-list-table widefat fixed striped cfmb-list__table cfmb-sync__table">
				<thead>
					<tr>
						<td className="check-column">
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ <span className="screen-reader-text">{ __( 'Select all', 'cfmb' ) }</span> }
								checked={ rows.length > 0 && selected.size === rows.length }
								onChange={ ( on ) => setSelected( on ? new Set( rows.map( id ) ) : new Set() ) }
							/>
						</td>
						<th scope="col" className="column-primary">{ __( 'Title', 'cfmb' ) }</th>
						<th scope="col">{ __( 'State', 'cfmb' ) }</th>
						<th scope="col">{ __( 'File', 'cfmb' ) }</th>
						<th scope="col">{ __( 'Actions', 'cfmb' ) }</th>
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
										label={ <span className="screen-reader-text">{ row.title }</span> }
										checked={ selected.has( id( row ) ) }
										onChange={ ( on ) => toggle( row, on ) }
									/>
								</th>
								<td className="column-primary">
									<strong>{ row.title }</strong>
									<div className="cfmb-sync__meta">
										{ KIND_LABELS[ row.kind ] } · <code>{ row.key }</code>
									</div>
								</td>
								<td>
									<span className={ `cfmb-sync__status cfmb-sync__status--${ status.tone }` }>{ status.label }</span>
									{ status.help && <div className="cfmb-sync__help">{ status.help }</div> }
								</td>
								<td>{ row.file ? <span title={ row.file.path }>{ row.file.location }</span> : '—' }</td>
								<td className="cfmb-sync__actions">
									{ actionsFor( row ).map( ( action ) => (
										<Button key={ action.label } variant={ action.primary ? 'secondary' : 'link' } disabled={ busy } onClick={ action.onClick }>
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
						snackbar( __( 'Merged and saved to the database and the file.', 'cfmb' ) );
					} }
				/>
			) }

			{ phpCode && (
				<Modal title={ __( 'PHP code', 'cfmb' ) } onRequestClose={ () => setPhpCode( '' ) } size="large" className="cfmb-php-export">
					<p>
						{ __( 'Put this in your theme\'s functions.php or a plugin. Items registered in code show here as read-only. Remove them from the database (or keep them inactive) to avoid two copies.', 'cfmb' ) }
					</p>
					<textarea className="cfmb-php-export__code code" readOnly rows={ 18 } value={ phpCode } onFocus={ ( event ) => event.target.select() } />
					<div className="cfmb-confirm__actions">
						<Button variant="secondary" onClick={ () => download( 'cfmb-definitions.php', phpCode, 'text/x-php' ) }>
							{ __( 'Download', 'cfmb' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () =>
								window.navigator.clipboard?.writeText( phpCode ).then( () => snackbar( __( 'Copied.', 'cfmb' ) ) )
							}
						>
							{ __( 'Copy', 'cfmb' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
