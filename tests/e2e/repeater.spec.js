/**
 * Build a Repeater, add rows on a post, and check they survive a reload.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Repeater', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'cfmb' );
	} );

	test( 'rows save in order', async ( { admin, editor, page } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cfmb&group=new' );

		await page.getByLabel( 'Field group title' ).fill( 'E2E rows' );
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page.getByLabel( 'Label', { exact: true } ).fill( 'Steps' );
		await page.getByLabel( 'Type', { exact: true } ).selectOption( 'repeater' );
		await page.getByRole( 'button', { name: 'Add sub-field' } ).click();
		await page.getByLabel( 'Label', { exact: true } ).last().fill( 'Step' );
		await page.getByRole( 'button', { name: 'Save field group' } ).click();
		await expect( page.getByText( 'Field group saved.' ) ).toBeVisible();

		await admin.createNewPost( { title: 'Has steps' } );
		const steps = page.locator( '.cfmb-rows--repeater' );
		await steps.getByRole( 'button', { name: 'Add row' } ).click();
		await steps.getByLabel( 'Step' ).last().fill( 'First' );
		await steps.getByRole( 'button', { name: 'Add row' } ).click();
		await steps.getByLabel( 'Step' ).last().fill( 'Second' );
		await editor.publishPost();
		await page.reload();

		await expect( page.locator( '.cfmb-rows--repeater .cfmb-row__title' ) ).toHaveText( [ 'First', 'Second' ] );
	} );
} );
