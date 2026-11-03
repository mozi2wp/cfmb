/**
 * Taxonomy field shown as checkboxes or radio buttons, in term hierarchy order.
 */
import { useEffect, useMemo } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSearch } from './AsyncPicker';

export function ordered( terms ) {
	const children = new Map();
	terms.forEach( ( term ) => {
		const list = children.get( term.parent ) || [];
		list.push( term );
		children.set( term.parent, list );
	} );
	const known = new Set( terms.map( ( term ) => term.id ) );
	const out = [];
	const walk = ( parent, depth ) =>
		( children.get( parent ) || [] ).forEach( ( term ) => {
			out.push( { ...term, depth } );
			walk( term.id, depth + 1 );
		} );
	walk( 0, 0 );
	// Terms whose parent isn't in the list still show, at the top level.
	terms
		.filter( ( term ) => term.parent && ! known.has( term.parent ) )
		.forEach( ( term ) => {
			out.push( { ...term, depth: 0 } );
			walk( term.id, 1 );
		} );
	return out;
}

export default function TermChoices( {
	field,
	id,
	value,
	onChange,
	describedBy,
	multiple,
} ) {
	const results = useSearch( field );
	const selected = multiple
		? Array.isArray( value )
			? value
			: []
		: value
		? [ value ]
		: [];
	const chosenIds = new Set( selected.map( ( item ) => item.id ) );

	useEffect( () => {
		results.run( '', 1, { all: true } );
		// Load once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const terms = useMemo( () => ordered( results.items ), [ results.items ] );

	if ( results.loading && ! terms.length ) {
		return <Spinner />;
	}

	if ( results.error ) {
		return <p className="cfmb-field__error">{ results.error }</p>;
	}

	if ( ! terms.length ) {
		return (
			<p className="cfmb-field__instructions">
				{ __(
					'There are no terms to choose from yet.',
					'custom-fields-meta-box'
				) }
			</p>
		);
	}

	const toggle = ( term, checked ) => {
		if ( ! multiple ) {
			onChange( term );
			return;
		}
		onChange(
			checked
				? [ ...selected, term ]
				: selected.filter( ( item ) => item.id !== term.id )
		);
	};

	return (
		<ul
			className="cfmb-choice-list cfmb-choice-list--terms"
			aria-describedby={ describedBy }
		>
			{ terms.map( ( term, index ) => (
				<li
					key={ term.id }
					className="cfmb-choice-list__item"
					style={ { paddingInlineStart: `${ term.depth * 20 }px` } }
				>
					<label>
						<input
							id={ index === 0 ? id : undefined }
							type={ multiple ? 'checkbox' : 'radio' }
							name={ multiple ? undefined : id }
							checked={ chosenIds.has( term.id ) }
							onChange={ ( event ) =>
								toggle(
									{
										id: term.id,
										title: term.title,
										parent: term.parent,
									},
									event.target.checked
								)
							}
						/>
						{ term.title }
					</label>
				</li>
			) ) }
			{ ! multiple && selected.length > 0 && ! field.required && (
				<li className="cfmb-choice-list__item">
					<button
						type="button"
						className="button-link"
						onClick={ () => onChange( null ) }
					>
						{ __( 'Clear choice', 'custom-fields-meta-box' ) }
					</button>
				</li>
			) }
		</ul>
	);
}
