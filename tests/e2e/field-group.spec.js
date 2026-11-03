/**
 * Create a field group, then fill its field on a post.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Field groups', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'custom-fields-meta-box' );
	} );

	test( 'a text field saves on a post', async ( { admin, editor, page } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cfmb&group=new' );

		await page.getByLabel( 'Field group title' ).fill( 'E2E details' );
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page.getByLabel( 'Label', { exact: true } ).fill( 'Subtitle' );
		await expect( page.getByLabel( 'Name', { exact: true } ) ).toHaveValue( 'subtitle' );
		await page.getByRole( 'button', { name: 'Save field group' } ).click();
		await expect( page.getByText( 'Field group saved.' ) ).toBeVisible();

		await admin.createNewPost( { title: 'Has a subtitle' } );
		const field = page.getByLabel( 'Subtitle' );
		await field.fill( 'Hello from CFMB' );
		await editor.publishPost();
		await page.reload();

		await expect( page.getByLabel( 'Subtitle' ) ).toHaveValue( 'Hello from CFMB' );
	} );
} );
