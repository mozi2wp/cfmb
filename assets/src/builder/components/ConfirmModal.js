import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ConfirmModal( { title, children, confirmLabel, isDestructive = false, onConfirm, onCancel } ) {
	return (
		<Modal title={ title } onRequestClose={ onCancel } className="cfmb-confirm" size="small">
			<div className="cfmb-confirm__body">{ children }</div>
			<div className="cfmb-confirm__actions">
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'cfmb' ) }
				</Button>
				<Button variant="primary" isDestructive={ isDestructive } onClick={ onConfirm }>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
