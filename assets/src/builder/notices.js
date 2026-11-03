/**
 * Snackbar helpers on the core notices store.
 */
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';
import config from './config';

export function snackbar( message, actions = [] ) {
	dispatch( noticesStore ).createSuccessNotice( message, { type: 'snackbar', actions } );
	speak( message );
}

export function errorSnackbar( message ) {
	dispatch( noticesStore ).createErrorNotice( message, { type: 'snackbar' } );
	speak( message, 'assertive' );
}

/**
 * Tell the user when a save didn't update its Schema Sync file.
 *
 * @param {string} note "conflict", an error message, or empty.
 */
export function syncNoteSnackbar( note ) {
	if ( ! note ) {
		return;
	}
	if ( note === 'conflict' ) {
		dispatch( noticesStore ).createWarningNotice(
			__( 'Saved. Its sync file was changed outside CFMB, so it was not overwritten. Review it under Sync.', 'cfmb' ),
			{
				type: 'snackbar',
				explicitDismiss: true,
				actions: config.pages?.sync ? [ { label: __( 'Open Sync', 'cfmb' ), url: config.pages.sync } ] : [],
			}
		);
		speak( __( 'Saved, but its sync file was not overwritten.', 'cfmb' ), 'assertive' );
		return;
	}
	dispatch( noticesStore ).createWarningNotice( note, { type: 'snackbar', explicitDismiss: true } );
	speak( note, 'assertive' );
}
