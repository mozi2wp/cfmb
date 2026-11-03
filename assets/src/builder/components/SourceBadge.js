import { __ } from '@wordpress/i18n';

/**
 * Badge for definitions that don't live in the database.
 *
 * @param {Object} props        Props.
 * @param {string} props.source db, code or file.
 * @return {Element|null} Badge.
 */
export default function SourceBadge( { source } ) {
	if ( source === 'code' ) {
		return <span className="cfmb-badge cfmb-badge--code">{ __( 'Code', 'custom-fields-meta-box' ) }</span>;
	}
	if ( source === 'file' ) {
		return <span className="cfmb-badge cfmb-badge--file">{ __( 'Sync file', 'custom-fields-meta-box' ) }</span>;
	}
	return null;
}
