<?php
/**
 * Field Blocks.
 *
 * @package CFMB
 */

namespace CFMB\Tests;

use CFMB\Blocks\Block_Registry;
use CFMB\Blocks\Block_Renderer;
use CFMB\Blocks\Block_Settings;
use CFMB\Blocks\Inner_Blocks_Tag;
use CFMB\Blocks\Template_Locator;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Storage\Store_Resolver;
use WP_Block;
use WP_UnitTestCase;

/**
 * Block settings, templates, rendering and the block store.
 */
class BlocksTest extends WP_UnitTestCase {

	/**
	 * Temporary theme folder for templates.
	 *
	 * @var string
	 */
	private string $folder = '';

	public function set_up(): void {
		parent::set_up();
		cfmb()->get( Group_Repository::class )->flush();
		cfmb()->get( Block_Registry::class )->flush();

		$this->folder = get_stylesheet_directory() . '/' . Template_Locator::FOLDER;
		wp_mkdir_p( $this->folder );
	}

	public function tear_down(): void {
		foreach ( (array) glob( $this->folder . '/*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'cfmb/hero' ) ) {
			unregister_block_type( 'cfmb/hero' );
		}

		parent::tear_down();
	}

	private function hero_group(): void {
		$clean = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Hero',
				'active'   => true,
				'fields'   => array(
					array( 'type' => 'text', 'label' => 'Heading', 'name' => 'heading' ),
					array(
						'type'   => 'repeater',
						'label'  => 'Items',
						'name'   => 'items',
						'fields' => array( array( 'type' => 'text', 'label' => 'Title', 'name' => 'title' ) ),
					),
				),
				'location' => array(),
				'settings' => array( 'block' => array( 'enabled' => true ) ),
			)
		);

		$this->assertIsArray( $clean );
		cfmb()->get( Group_Repository::class )->save( $clean );
		cfmb()->get( Group_Repository::class )->flush();
		cfmb()->get( Block_Registry::class )->flush();
	}

	public function test_block_name_comes_from_title_and_is_unique(): void {
		$this->hero_group();

		$this->assertNotNull( cfmb()->get( Block_Registry::class )->get( 'cfmb/hero' ) );

		$second = cfmb()->get( Group_Sanitizer::class )->sanitize(
			array(
				'title'    => 'Hero',
				'fields'   => array(),
				'settings' => array( 'block' => array( 'enabled' => true, 'name' => 'hero' ) ),
			)
		);

		$this->assertWPError( $second );
	}

	public function test_settings_are_cleaned(): void {
		$clean = Block_Settings::sanitize(
			array(
				'enabled'    => '1',
				'name'       => 'My Hero!',
				'icon'       => 'dashicons-star-filled',
				'mode'       => 'bogus',
				'template'   => '../../wp-config.php',
				'alignments' => array( 'wide', 'sideways' ),
				'styles'     => array( array( 'name' => 'Dark', 'label' => 'Dark' ), array( 'name' => 'dark', 'label' => 'Again' ) ),
			)
		);

		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( 'my-hero', $clean['name'] );
		$this->assertSame( 'preview', $clean['mode'] );
		$this->assertSame( '', $clean['template'] );
		$this->assertSame( array( 'wide' ), $clean['alignments'] );
		$this->assertCount( 1, $clean['styles'] );
		$this->assertSame( '', Block_Settings::slug( '123' ) );
	}

	public function test_template_outside_allowed_folders_is_refused(): void {
		$locator = cfmb()->get( Template_Locator::class );
		$outside = get_temp_dir() . 'cfmb-outside.php';
		file_put_contents( $outside, '<?php echo "x";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->assertFalse( $locator->is_allowed( $outside ) );
		$this->assertNotSame( '', $locator->locate( 'hero', $outside )['error'] );

		unlink( $outside );
	}

	public function test_inner_blocks_tag(): void {
		$html = '<div><InnerBlocks allowedBlocks=\'["core/paragraph"]\' /></div><InnerBlocks />';

		$this->assertSame( '<div><p>Inner</p></div>', Inner_Blocks_Tag::replace( $html, '<p>Inner</p>', false ) );
		$this->assertStringContainsString( '<cfmb-inner-blocks data-allowedblocks="[&quot;core/paragraph&quot;]"></cfmb-inner-blocks>', Inner_Blocks_Tag::replace( $html, '', true ) );
	}

	public function test_block_renders_its_own_values(): void {
		$this->hero_group();
		file_put_contents( $this->folder . '/hero.php', '<?php echo esc_html( cfmb_get_field( "heading" ) ) . "|" . count( (array) cfmb_get_field( "items" ) ) . "|" . ( $is_preview ? "p" : "f" );' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		cfmb()->get( Block_Registry::class )->register_blocks();

		$post = self::factory()->post->create( array( 'meta_input' => array( 'heading' => 'Post value' ) ) );
		$this->go_to( get_permalink( $post ) );

		$html = render_block(
			array(
				'blockName'    => 'cfmb/hero',
				'attrs'        => array(
					'data' => array(
						'heading'       => '<b>Block</b> value',
						'items'         => '2',
						'items_0_title' => 'One',
						'items_1_title' => 'Two',
						'stale'         => 'dropped',
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertSame( 'Block value|2|f', $html );
	}

	public function test_preview_renders_notice_when_template_is_missing(): void {
		$this->hero_group();
		cfmb()->get( Block_Registry::class )->register_blocks();

		$block = new WP_Block(
			array(
				'blockName'    => 'cfmb/hero',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertStringContainsString( 'cfmb-block-notice', cfmb()->get( Block_Renderer::class )->render_preview( $block ) );
		$this->assertSame( '', render_block( $block->parsed_block ) );
	}

	public function test_clean_instance_drops_unknown_values(): void {
		$this->hero_group();

		$renderer = cfmb()->get( Block_Renderer::class );
		$store    = cfmb()->get( Store_Resolver::class )->blocks();
		$ref      = $renderer->clean_instance( 'cfmb/hero', array( 'heading' => 'Hi', 'unknown' => 'x' ) );

		$this->assertSame( array( 'heading' => 'Hi' ), $store->data( $ref ) );
		$store->close( $ref );
	}
}
