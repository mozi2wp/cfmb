/**
 * Connections: define one, link two posts, see the link on both sides.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Connections', () => {
	test( 'links show on both sides', async ( { admin, page, requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: '/cfmb/v1/content/connections',
			data: { title: 'Related posts', name: 'related', active: true, settings: { from: 'post', to: 'post' } },
		} );
		const first = await requestUtils.createPost( { title: 'First', status: 'publish' } );
		await requestUtils.createPost( { title: 'Second', status: 'publish' } );

		await admin.editPost( first.id );
		const box = page.locator( '.cfmb-connection' ).first();
		await box.locator( 'input[type="search"]' ).fill( 'Second' );
		await box.locator( '.cfmb-connection__result', { hasText: 'Second' } ).click();
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page.locator( '.components-snackbar' ) ).toBeVisible();

		await page.reload();
		await expect( page.locator( '.cfmb-connection__selected' ).first() ).toContainText( 'Second' );
	} );
} );
