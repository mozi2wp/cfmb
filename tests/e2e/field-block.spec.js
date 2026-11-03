/**
 * Turn a group into a Field Block and add it to a post. The theme needs a
 * template; without one, the editor shows the "Template missing" notice.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Field Block', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'cfmb' );
	} );

	test( 'block appears in the inserter and keeps its value', async ( { admin, editor, page } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=cfmb&group=new' );
		await page.getByLabel( 'Field group title' ).fill( 'E2E Banner' );
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page.getByLabel( 'Label', { exact: true } ).fill( 'Heading' );
		await page.getByLabel( 'Use as a Field Block' ).check();
		await page.getByRole( 'button', { name: 'Save field group' } ).click();
		await expect( page.getByText( 'Field group saved.' ) ).toBeVisible();

		await admin.createNewPost( { title: 'Has a banner' } );
		await editor.insertBlock( { name: 'cfmb/e2e-banner' } );
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'region', { name: 'Editor settings' } ).getByLabel( 'Heading' ).fill( 'Hello' );

		// The value is cleaned on the server before it lands in the block.
		await expect.poll( async () => ( await editor.getBlocks() )[ 0 ]?.attributes?.data?.heading ).toBe( 'Hello' );
		await editor.publishPost();
	} );
} );
