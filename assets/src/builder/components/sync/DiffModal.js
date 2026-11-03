/**
 * Side-by-side review of a definition whose database copy and file differ.
 * Each difference can keep the database copy or take the file's.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { syncDiff, syncMerge } from '../../api';

const STATUS = {
	changed: __( 'Changed', 'cfmb' ),
	only_db: __( 'Only in the database', 'cfmb' ),
	only_file: __( 'Only in the file', 'cfmb' ),
};

function Value( { value } ) {
	if ( value === null || value === undefined ) {
		return <em className="cfmb-diff__missing">{ __( 'Not there', 'cfmb' ) }</em>;
	}
	return <pre className="cfmb-diff__value">{ typeof value === 'string' ? value : JSON.stringify( value, null, 2 ) }</pre>;
}

export default function DiffModal( { row, onClose, onMerged } ) {
	const [ diff, setDiff ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ choices, setChoices ] = useState( {} );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		syncDiff( row.kind, row.key )
			.then( ( loaded ) => {
				setDiff( loaded );
				setChoices( Object.fromEntries( loaded.entries.map( ( entry ) => [ entry.id, row.status === 'db_changed' ? 'db' : 'file' ] ) ) );
			} )
			.catch( ( failure ) => setError( failure.message || __( 'The differences could not be loaded.', 'cfmb' ) ) );
	}, [ row ] );

	const pickAll = ( side ) => setChoices( ( current ) => Object.fromEntries( Object.keys( current ).map( ( id ) => [ id, side ] ) ) );

	const merge = async () => {
		setSaving( true );
		setError( '' );
		try {
			const updated = await syncMerge( row.kind, row.key, choices );
			onMerged( updated );
		} catch ( failure ) {
			setError( failure.message || __( 'The merge could not be saved.', 'cfmb' ) );
			setSaving( false );
		}
	};

	return (
		<Modal title={ row.title } onRequestClose={ onClose } className="cfmb-diff" size="large">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! diff && ! error && (
				<p>
					<Spinner /> { __( 'Comparing…', 'cfmb' ) }
				</p>
			) }

			{ diff && diff.entries.length === 0 && <p>{ __( 'No differences. Saving writes the file again.', 'cfmb' ) }</p> }

			{ diff && diff.entries.length > 0 && (
				<>
					<p className="cfmb-diff__intro">
						{ __( 'Choose which copy to keep for each difference. The result is saved to the database and written to the sync file.', 'cfmb' ) }
					</p>
					<div className="cfmb-diff__bulk">
						<Button variant="secondary" onClick={ () => pickAll( 'db' ) }>
							{ __( 'Keep the database for all', 'cfmb' ) }
						</Button>
						<Button variant="secondary" onClick={ () => pickAll( 'file' ) }>
							{ __( 'Use the file for all', 'cfmb' ) }
						</Button>
					</div>
					<ol className="cfmb-diff__entries">
						{ diff.entries.map( ( entry ) => (
							<li key={ entry.id } className="cfmb-diff__entry">
								<fieldset>
									<legend>
										<strong>{ entry.label }</strong> <span className="cfmb-diff__status">{ STATUS[ entry.status ] }</span>
									</legend>
									<div className="cfmb-diff__sides">
										{ [ 'db', 'file' ].map( ( side ) => (
											<label key={ side } className={ `cfmb-diff__side${ choices[ entry.id ] === side ? ' is-chosen' : '' }` }>
												<span className="cfmb-diff__side-head">
													<input
														type="radio"
														name={ `cfmb-diff-${ entry.id }` }
														checked={ choices[ entry.id ] === side }
														onChange={ () => setChoices( ( current ) => ( { ...current, [ entry.id ]: side } ) ) }
													/>{ ' ' }
													{ side === 'db' ? __( 'Database', 'cfmb' ) : __( 'File', 'cfmb' ) }
												</span>
												<Value value={ entry[ side ] } />
											</label>
										) ) }
									</div>
								</fieldset>
							</li>
						) ) }
					</ol>
				</>
			) }

			<div className="cfmb-confirm__actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'cfmb' ) }
				</Button>
				<Button variant="primary" isBusy={ saving } disabled={ ! diff || saving } onClick={ merge }>
					{ __( 'Save to both', 'cfmb' ) }
				</Button>
			</div>
		</Modal>
	);
}
