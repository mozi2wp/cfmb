/**
 * Switch Assistant: bring an ACF or Secure Custom Fields site over, or a site
 * built with another plugin that registered itself as a source (CFMB Pro).
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, CheckboxControl, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { switchConvert, switchForget, switchImport, switchScan, switchUndo } from '../../api';
import { snackbar } from '../../notices';
import ConfirmModal from '../ConfirmModal';
import { initialPicks, runAll, scanSections, togglePick } from './picks';

const common = { __nextHasNoMarginBottom: true };

const SECTIONS = [
	{ list: 'groups', pick: 'groups', title: __( 'Field groups', 'custom-fields-meta-box' ) },
	{ list: 'postTypes', pick: 'post_types', title: __( 'Post types', 'custom-fields-meta-box' ) },
	{ list: 'taxonomies', pick: 'taxonomies', title: __( 'Taxonomies', 'custom-fields-meta-box' ) },
	{ list: 'optionsPages', pick: 'options_pages', title: __( 'Options pages', 'custom-fields-meta-box' ) },
];

const STATUS = {
	imported: __( 'Definitions imported. Values not converted yet.', 'custom-fields-meta-box' ),
	converting: __( 'Converting values…', 'custom-fields-meta-box' ),
	done: __( 'Done.', 'custom-fields-meta-box' ),
	undoing: __( 'Undoing…', 'custom-fields-meta-box' ),
	undone: __( 'Undone. Original values restored.', 'custom-fields-meta-box' ),
};

function Item( { item, picked, onToggle } ) {
	const [ open, setOpen ] = useState( false );
	const notes = item.notes || [];
	const detail = [];

	if ( item.fields !== undefined ) {
		/* translators: %d: number of fields */
		detail.push( sprintf( _n( '%d field', '%d fields', item.fields, 'custom-fields-meta-box' ), item.fields ) );
	}
	if ( item.name ) {
		detail.push( item.name );
	}

	return (
		<li className="cfmb-switch__item">
			<CheckboxControl
				{ ...common }
				label={ item.title || item.key }
				help={ item.imported ? __( 'Already imported', 'custom-fields-meta-box' ) : detail.join( ' · ' ) }
				checked={ ! item.imported && picked }
				disabled={ item.imported }
				onChange={ onToggle }
			/>
			{ notes.length > 0 && ! item.imported && (
				<div className="cfmb-switch__notes">
					<Button variant="link" aria-expanded={ open } onClick={ () => setOpen( ! open ) }>
						{ sprintf(
							/* translators: %d: number of notes */
							_n( '%d thing to check', '%d things to check', notes.length, 'custom-fields-meta-box' ),
							notes.length
						) }
					</Button>
					{ open && (
						<ul>
							{ notes.map( ( note ) => (
								<li key={ note }>{ note }</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</li>
	);
}

const ACF_LABEL = __( 'ACF or Secure Custom Fields', 'custom-fields-meta-box' );

function Run( { run, sourceActive, label, onChange } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ confirm, setConfirm ] = useState( '' );

	const loop = async ( kind ) => {
		setBusy( kind );
		setError( '' );
		try {
			const call = kind === 'undo' ? switchUndo : switchConvert;
			const finished = kind === 'undo' ? 'undone' : 'done';
			const result = await runAll( () => call( run.id ), finished, onChange );
			speak( STATUS[ result.status ] || '' );
			snackbar( kind === 'undo' ? __( 'Import undone.', 'custom-fields-meta-box' ) : __( 'Values converted.', 'custom-fields-meta-box' ) );
		} catch ( failure ) {
			setError( failure.message || __( 'That did not work. Try again.', 'custom-fields-meta-box' ) );
		} finally {
			setBusy( '' );
		}
	};

	const forget = async () => {
		setBusy( 'forget' );
		try {
			await switchForget( run.id );
			onChange( null );
		} catch ( failure ) {
			setError( failure.message || __( 'That did not work. Try again.', 'custom-fields-meta-box' ) );
			setBusy( '' );
		}
	};

	const canConvert = [ 'imported', 'converting' ].includes( run.status );
	const canUndo = run.status !== 'undone';

	return (
		<li className="cfmb-switch__run">
			<p className="cfmb-switch__run-status">
				<strong>{ new Date( run.started * 1000 ).toLocaleString() }</strong> · { STATUS[ run.status ] || run.status }
			</p>
			<p className="description">
				{ sprintf(
					/* translators: 1: definitions created, 2: values changed, 3: options page values moved, 4: rows kept for undo */
					__( '%1$d definitions created · %2$d values changed · %3$d options page values moved · %4$d originals kept for undo', 'custom-fields-meta-box' ),
					run.created,
					run.counts.converted,
					run.counts.moved,
					run.backedUp
				) }
			</p>
			{ run.counts.items > 0 && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of items */
						_n( '%d item created (posts from content types and connection links).', '%d items created (posts from content types and connection links).', run.counts.items, 'custom-fields-meta-box' ),
						run.counts.items
					) }
				</p>
			) }
			{ run.counts.kept > 0 && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of values */
						_n( '%d value was left alone because CFMB already had one or it could not be read.', '%d values were left alone because CFMB already had one or they could not be read.', run.counts.kept, 'custom-fields-meta-box' ),
						run.counts.kept
					) }
				</p>
			) }
			{ run.notes.length > 0 && (
				<details className="cfmb-switch__run-notes">
					<summary>{ __( 'Notes from the import', 'custom-fields-meta-box' ) }</summary>
					<ul>
						{ run.notes.map( ( note ) => (
							<li key={ note }>{ note }</li>
						) ) }
					</ul>
				</details>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="cfmb-switch__actions">
				{ canConvert && (
					<Button
						variant="primary"
						isBusy={ busy === 'convert' }
						disabled={ !! busy || sourceActive }
						accessibleWhenDisabled
						onClick={ () => loop( 'convert' ) }
					>
						{ busy === 'convert' ? __( 'Converting…', 'custom-fields-meta-box' ) : __( 'Convert values', 'custom-fields-meta-box' ) }
					</Button>
				) }
				{ canUndo && (
					<Button variant="secondary" isBusy={ busy === 'undo' } disabled={ !! busy } accessibleWhenDisabled onClick={ () => setConfirm( 'undo' ) }>
						{ busy === 'undo' ? __( 'Undoing…', 'custom-fields-meta-box' ) : __( 'Undo import', 'custom-fields-meta-box' ) }
					</Button>
				) }
				{ [ 'done', 'undone' ].includes( run.status ) && (
					<Button variant="tertiary" isDestructive disabled={ !! busy } accessibleWhenDisabled onClick={ () => setConfirm( 'forget' ) }>
						{ __( 'Remove from the list', 'custom-fields-meta-box' ) }
					</Button>
				) }
			</div>
			{ canConvert && sourceActive && (
				<p className="description">
					{ sprintf(
						/* translators: %s: plugin name */
						__( 'Deactivate %s first, then convert. Until then the imported field groups stay off.', 'custom-fields-meta-box' ),
						label
					) }
				</p>
			) }
			{ confirm === 'undo' && (
				<ConfirmModal
					title={ __( 'Undo this import?', 'custom-fields-meta-box' ) }
					confirmLabel={ __( 'Undo import', 'custom-fields-meta-box' ) }
					isDestructive
					onConfirm={ () => {
						setConfirm( '' );
						loop( 'undo' );
					} }
					onCancel={ () => setConfirm( '' ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: plugin name */
							__( 'Values go back exactly as %s saved them, and the definitions this import created move to the trash. Posts and links it created are deleted. Edits made since then to converted values are lost.', 'custom-fields-meta-box' ),
							label
						) }
					</p>
				</ConfirmModal>
			) }
			{ confirm === 'forget' && (
				<ConfirmModal
					title={ __( 'Remove this import from the list?', 'custom-fields-meta-box' ) }
					confirmLabel={ __( 'Remove', 'custom-fields-meta-box' ) }
					isDestructive
					onConfirm={ () => {
						setConfirm( '' );
						forget();
					} }
					onCancel={ () => setConfirm( '' ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: plugin name */
							__( 'The copies of the original %s values are deleted, so this import can no longer be undone. Your CFMB content is not touched.', 'custom-fields-meta-box' ),
							label
						) }
					</p>
				</ConfirmModal>
			) }
		</li>
	);
}

function SourcePicker( { sources, current, onPick } ) {
	const shown = sources.filter( ( source ) => source.found || source.id === current );

	if ( shown.length === 0 ) {
		return null;
	}

	const choices = [ { id: '', label: ACF_LABEL }, ...shown ];

	return (
		<div className="cfmb-switch__sources" role="group" aria-label={ __( 'Switch from', 'custom-fields-meta-box' ) }>
			<span className="cfmb-switch__sources-label">{ __( 'Switch from:', 'custom-fields-meta-box' ) }</span>
			{ choices.map( ( source ) => (
				<Button key={ source.id || 'acf' } variant={ source.id === current ? 'primary' : 'secondary' } isPressed={ source.id === current } onClick={ () => onPick( source.id ) }>
					{ source.label }
				</Button>
			) ) }
		</div>
	);
}

export default function SwitchScreen() {
	const [ source, setSource ] = useState( '' );
	const [ raw, setRaw ] = useState( null );
	const [ sources, setSources ] = useState( [] );
	const [ picks, setPicks ] = useState( {} );
	const [ error, setError ] = useState( '' );
	const [ importing, setImporting ] = useState( false );

	const { scan, sections } = scanSections( raw, SECTIONS );
	const label = source ? ( raw && raw.label ) || source : ACF_LABEL;

	const load = ( id = source ) =>
		switchScan( id )
			.then( ( result ) => {
				const next = scanSections( result, SECTIONS );
				setRaw( result );
				setSources( result.sources || [] );
				setPicks( initialPicks( next.scan, next.sections ) );
			} )
			.catch( ( failure ) => setError( failure.message || __( 'The data could not be read.', 'custom-fields-meta-box' ) ) );

	useEffect( () => {
		load( source );
	}, [ source ] );

	const pickSource = ( id ) => {
		if ( id === source ) {
			return;
		}
		setError( '' );
		setRaw( null );
		setSource( id );
	};

	if ( error && ! scan ) {
		return (
			<div className="cfmb-switch">
				<SourcePicker sources={ sources } current={ source } onPick={ pickSource } />
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			</div>
		);
	}

	if ( ! scan ) {
		return (
			<div className="cfmb-editor cfmb-editor--loading">
				<Spinner />{ ' ' }
				{ sprintf(
					/* translators: %s: plugin name */
					__( 'Reading %s data…', 'custom-fields-meta-box' ),
					label
				) }
			</div>
		);
	}

	const total = sections.reduce( ( sum, section ) => sum + ( scan[ section.list ] || [] ).length, 0 );
	const chosen = Object.values( picks ).reduce( ( sum, list ) => sum + list.length, 0 );

	const runImport = async () => {
		setImporting( true );
		setError( '' );
		try {
			const run = await switchImport( picks, source );
			/* translators: %d: number of definitions */
			snackbar( sprintf( _n( 'Imported %d definition.', 'Imported %d definitions.', run.created, 'custom-fields-meta-box' ), run.created ) );
			await load();
		} catch ( failure ) {
			setError( failure.message || __( 'The import did not finish. Try again; finished parts are skipped.', 'custom-fields-meta-box' ) );
		} finally {
			setImporting( false );
		}
	};

	const updateRun = ( id, next ) =>
		setRaw( ( current ) => ( {
			...current,
			runs: next ? current.runs.map( ( run ) => ( run.id === id ? next : run ) ) : current.runs.filter( ( run ) => run.id !== id ),
		} ) );

	return (
		<div className="cfmb-switch">
			<div className="cfmb-list__header">
				<h1 className="wp-heading-inline">{ __( 'Switch Assistant', 'custom-fields-meta-box' ) }</h1>
			</div>
			<SourcePicker sources={ sources } current={ source } onPick={ pickSource } />
			<p className="cfmb-switch__intro">
				{ sprintf(
					/* translators: %s: plugin name */
					__( 'Bring field groups, post types, taxonomies, options pages and their values over from %s. Every value that changes is kept, so you can undo the import.', 'custom-fields-meta-box' ),
					label
				) }
			</p>

			{ scan.sourceActive && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: plugin name */
						__( '%s is active. You can import now; the field groups stay off until you deactivate it and convert the values below. Post types and taxonomies it still registers wait until then too.', 'custom-fields-meta-box' ),
						label
					) }
				</Notice>
			) }

			{ ( scan.notes || [] ).length > 0 && (
				<Notice status="info" isDismissible={ false }>
					<ul className="cfmb-switch__scan-notes">
						{ scan.notes.map( ( note ) => (
							<li key={ note }>{ note }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{ total === 0 ? (
				<Card className="cfmb-editor__card">
					<CardBody>
						<p>
							{ sprintf(
								/* translators: %s: plugin name */
								__( 'No %s data was found on this site.', 'custom-fields-meta-box' ),
								label
							) }
						</p>
					</CardBody>
				</Card>
			) : (
				<Card className="cfmb-editor__card">
					<CardHeader>
						<h2 className="cfmb-editor__card-title">{ __( '1. Choose what to import', 'custom-fields-meta-box' ) }</h2>
					</CardHeader>
					<CardBody>
						{ sections.filter( ( section ) => ( scan[ section.list ] || [] ).length > 0 ).map( ( section ) => (
							<fieldset key={ section.list } className="cfmb-switch__section">
								<legend>{ section.title }</legend>
								<ul className="cfmb-switch__items">
									{ scan[ section.list ].map( ( item ) => (
										<Item
											key={ item.key }
											item={ item }
											picked={ ( picks[ section.pick ] || [] ).includes( item.key ) }
											onToggle={ ( checked ) => setPicks( togglePick( picks, section.pick, item.key, checked ) ) }
										/>
									) ) }
								</ul>
							</fieldset>
						) ) }
						<Button variant="primary" isBusy={ importing } disabled={ importing || chosen === 0 } accessibleWhenDisabled onClick={ runImport }>
							{ importing ? __( 'Importing…', 'custom-fields-meta-box' ) : __( 'Import selected', 'custom-fields-meta-box' ) }
						</Button>
					</CardBody>
				</Card>
			) }

			{ scan.runs.length > 0 && (
				<Card className="cfmb-editor__card">
					<CardHeader>
						<h2 className="cfmb-editor__card-title">{ __( '2. Convert values', 'custom-fields-meta-box' ) }</h2>
					</CardHeader>
					<CardBody>
						<p className="description">
							{ source
								? __( 'Values are rewritten into CFMB\'s format where they differ, content type items become posts and relationships become Connections. It runs in small batches, so leave this page open until it finishes.', 'custom-fields-meta-box' )
								: __( 'Most values already fit CFMB. Lists (checkboxes, relationships, galleries, flexible content) and dates are rewritten, and options page values move to CFMB\'s names. It runs in small batches, so leave this page open until it finishes.', 'custom-fields-meta-box' ) }
						</p>
						<ul className="cfmb-switch__runs">
							{ [ ...scan.runs ].reverse().map( ( run ) => (
								<Run key={ run.id } run={ run } sourceActive={ scan.sourceActive } label={ label } onChange={ ( next ) => updateRun( run.id, next ) } />
							) ) }
						</ul>
					</CardBody>
				</Card>
			) }
		</div>
	);
}
