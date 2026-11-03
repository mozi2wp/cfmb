/**
 * Image and file fields, using the WordPress media modal.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useRef } from '@wordpress/element';
import { speak } from '@wordpress/a11y';

function toEditorValue( attachment, previewSize ) {
	const sizes = attachment.sizes || {};
	const preview =
		sizes[ previewSize ] || sizes.medium || sizes.thumbnail || sizes.full;

	return {
		id: attachment.id,
		url: attachment.url,
		preview: preview ? preview.url : attachment.url,
		title: attachment.title,
		filename: attachment.filename,
		filesize: attachment.filesizeHumanReadable || '',
		icon: attachment.icon,
		alt: attachment.alt || '',
	};
}

export default function Media( {
	field,
	id,
	value,
	onChange,
	describedBy,
	objectId,
} ) {
	const isImage = field.type === 'image';
	const settings = field.settings || {};
	const frameRef = useRef();
	const buttonRef = useRef();

	const openModal = () => {
		const media = window.wp?.media;
		if ( ! media ) {
			return;
		}

		if ( ! frameRef.current ) {
			const library = {};
			if ( isImage ) {
				library.type = 'image';
			}
			if ( settings.library === 'uploaded' && objectId ) {
				library.uploadedTo = objectId;
			}

			frameRef.current = media( {
				title: isImage
					? __( 'Choose an image', 'custom-fields-meta-box' )
					: __( 'Choose a file', 'custom-fields-meta-box' ),
				button: {
					text: isImage
						? __( 'Use this image', 'custom-fields-meta-box' )
						: __( 'Use this file', 'custom-fields-meta-box' ),
				},
				library,
				multiple: false,
			} );

			frameRef.current.on( 'select', () => {
				const attachment = frameRef.current
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				onChange(
					toEditorValue(
						attachment,
						settings.preview_size || 'medium'
					)
				);
			} );

			frameRef.current.on( 'close', () => buttonRef.current?.focus() );
		}

		frameRef.current.open();
	};

	if ( ! value ) {
		return (
			<div
				className="cfmb-media cfmb-media--empty"
				aria-describedby={ describedBy }
			>
				<Button
					id={ id }
					ref={ buttonRef }
					variant="secondary"
					onClick={ openModal }
				>
					{ isImage
						? __( 'Add image', 'custom-fields-meta-box' )
						: __( 'Add file', 'custom-fields-meta-box' ) }
				</Button>
			</div>
		);
	}

	return (
		<div
			className={ `cfmb-media cfmb-media--${ field.type }` }
			aria-describedby={ describedBy }
		>
			{ isImage ? (
				<img
					className="cfmb-media__preview"
					src={ value.preview || value.url }
					alt={ value.alt || '' }
				/>
			) : (
				<div className="cfmb-media__file">
					{ value.icon && (
						<img
							className="cfmb-media__icon"
							src={ value.icon }
							alt=""
						/>
					) }
					<div className="cfmb-media__details">
						<a
							href={ value.url }
							target="_blank"
							rel="noreferrer"
							className="cfmb-media__name"
						>
							{ value.title || value.filename }
						</a>
						<span className="cfmb-media__meta">
							{ [ value.filename, value.filesize ]
								.filter( Boolean )
								.join( ' · ' ) }
						</span>
					</div>
				</div>
			) }
			<div className="cfmb-media__actions">
				<Button
					id={ id }
					ref={ buttonRef }
					variant="secondary"
					size="compact"
					onClick={ openModal }
				>
					{ __( 'Replace', 'custom-fields-meta-box' ) }
				</Button>
				<Button
					variant="tertiary"
					size="compact"
					isDestructive
					onClick={ () => {
						onChange( null );
						speak(
							/* translators: %s: field label */
							sprintf(
								/* translators: %s: field label */ __( '%s removed.', 'custom-fields-meta-box' ),
								field.label
							)
						);
					} }
				>
					{ __( 'Remove', 'custom-fields-meta-box' ) }
				</Button>
			</div>
		</div>
	);
}
