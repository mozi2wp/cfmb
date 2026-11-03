<?php
/**
 * Registers Field Blocks with WordPress.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

use CFMB\Schema\Group_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Collects Field Blocks from Field Groups and from theme block.json files,
 * then registers each as a dynamic block rendered by Block_Renderer.
 */
class Block_Registry {

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Template finder.
	 *
	 * @var Template_Locator
	 */
	private Template_Locator $locator;

	/**
	 * Asset files next to templates.
	 *
	 * @var Block_Assets
	 */
	private Block_Assets $assets;

	/**
	 * Renderer.
	 *
	 * @var Block_Renderer
	 */
	private Block_Renderer $renderer;

	/**
	 * Blocks by full name, once collected.
	 *
	 * @var array<string, Block_Definition>|null
	 */
	private ?array $blocks = null;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups   Field Groups.
	 * @param Template_Locator $locator  Template finder.
	 * @param Block_Assets     $assets   Asset files.
	 * @param Block_Renderer   $renderer Renderer.
	 */
	public function __construct( Group_Repository $groups, Template_Locator $locator, Block_Assets $assets, Block_Renderer $renderer ) {
		$this->groups   = $groups;
		$this->locator  = $locator;
		$this->assets   = $assets;
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// After code and file definitions are in (init 1) and post types are registered (init 5).
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
	}

	/**
	 * Every Field Block by full name.
	 *
	 * @return array<string, Block_Definition>
	 */
	public function all(): array {
		if ( null !== $this->blocks ) {
			return $this->blocks;
		}

		$this->blocks = array();

		foreach ( $this->groups->active() as $group ) {
			$definition = Block_Definition::from_group( $group );

			if ( $definition ) {
				$this->add( $definition );
			}
		}

		foreach ( $this->metadata_files() as $file ) {
			$metadata   = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
			$definition = is_array( $metadata ) ? Block_Definition::from_metadata( dirname( $file ), $metadata ) : null;

			if ( $definition ) {
				$this->add( $definition );
			}
		}

		/**
		 * Fires when Field Blocks can be added in code.
		 *
		 * @param Block_Registry $registry Field Block registry. Call ->add( $definition ).
		 */
		do_action( 'cfmb/blocks/register', $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $this->blocks;
	}

	/**
	 * Forget collected blocks, so the next all() reads groups and files again.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->blocks = null;
	}

	/**
	 * Add a Field Block. The first block with a name wins.
	 *
	 * @param Block_Definition $definition Block.
	 * @return bool False when the name is taken.
	 */
	public function add( Block_Definition $definition ): bool {
		if ( null === $this->blocks ) {
			$this->all();
		}

		if ( isset( $this->blocks[ $definition->name() ] ) ) {
			return false;
		}

		$this->blocks[ $definition->name() ] = $definition;

		return true;
	}

	/**
	 * A Field Block by full name.
	 *
	 * @param string $name Block name.
	 * @return Block_Definition|null
	 */
	public function get( string $name ): ?Block_Definition {
		return $this->all()[ $name ] ?? null;
	}

	/**
	 * Template for a block.
	 *
	 * @param Block_Definition $definition Block.
	 * @return array{path: string, error: string}
	 */
	public function template( Block_Definition $definition ): array {
		return $this->locator->locate( $definition->slug(), $definition->template(), $definition->dir() );
	}

	/**
	 * Whether a block's template places inner blocks with an <InnerBlocks /> tag.
	 *
	 * @param Block_Definition $definition Block.
	 * @return bool
	 */
	public function uses_inner_blocks( Block_Definition $definition ): bool {
		$template = $this->template( $definition )['path'];

		if ( '' === $template ) {
			return false;
		}

		$source = (string) file_get_contents( $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local template file.

		return false !== stripos( $source, '<InnerBlocks' );
	}

	/**
	 * Register every Field Block that is not registered yet.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$types = \WP_Block_Type_Registry::get_instance();

		foreach ( $this->all() as $name => $definition ) {
			if ( $types->is_registered( $name ) ) {
				continue;
			}

			$template = $this->template( $definition )['path'];
			$args     = array_merge(
				$definition->settings(),
				'' !== $template ? $this->assets->register_for( $definition, $template ) : array(),
				array(
					'api_version'     => 3,
					'render_callback' => array( $this->renderer, 'render' ),
				)
			);

			$args['attributes'] = array_merge(
				array(
					'data' => array(
						'type'    => 'object',
						// An object, so the editor gets {} rather than [].
						'default' => (object) array(),
					),
					'mode' => array(
						'type' => 'string',
					),
				),
				$this->metadata_attributes( $definition )
			);

			$args['uses_context'] = array_values( array_unique( array_merge( array( 'postId', 'postType', 'queryId' ), (array) ( $args['uses_context'] ?? array() ) ) ) );

			/**
			 * Filters a Field Block's settings before it is registered.
			 *
			 * @param array<string, mixed> $args       Arguments for register_block_type().
			 * @param Block_Definition     $definition Block.
			 */
			$args = (array) apply_filters( 'cfmb/blocks/definition', $args, $definition ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			register_block_type( '' !== $definition->dir() ? $definition->dir() : $name, $args );
		}
	}

	/**
	 * Extra attributes a block.json declares, kept next to data and mode.
	 *
	 * @param Block_Definition $definition Block.
	 * @return array<string, mixed>
	 */
	private function metadata_attributes( Block_Definition $definition ): array {
		if ( '' === $definition->dir() ) {
			return array();
		}

		$metadata = json_decode( (string) file_get_contents( $definition->dir() . '/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.

		$attributes = is_array( $metadata ) && is_array( $metadata['attributes'] ?? null ) ? $metadata['attributes'] : array();
		unset( $attributes['data'], $attributes['mode'] );

		return $attributes;
	}

	/**
	 * block.json files in template folders: {folder}/{block}/block.json.
	 *
	 * @return string[]
	 */
	private function metadata_files(): array {
		$files = array();

		foreach ( $this->locator->folders() as $folder ) {
			$found = glob( $folder . '/*/block.json' );
			$files = array_merge( $files, is_array( $found ) ? $found : array() );
		}

		return array_values( array_unique( array_map( 'wp_normalize_path', $files ) ) );
	}
}
