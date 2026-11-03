<?php
/**
 * Field type registry.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every available field type.
 */
class Registry {

	/**
	 * Types by key.
	 *
	 * @var array<string, Field_Type>
	 */
	private array $types = array();

	/**
	 * Whether core types and add-on types are registered.
	 *
	 * @var bool
	 */
	private bool $ready = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load' ), 5 );
	}

	/**
	 * Register core types, then let add-ons register theirs.
	 *
	 * @return void
	 */
	public function load(): void {
		if ( $this->ready ) {
			return;
		}

		$this->ready = true;

		$core = array(
			new Types\Text_Field(),
			new Types\Textarea_Field(),
			new Types\Number_Field(),
			new Types\Range_Field(),
			new Types\Email_Field(),
			new Types\Url_Field(),
			new Types\Password_Field(),
			new Types\Wysiwyg_Field(),
			new Types\Image_Field(),
			new Types\File_Field(),
			new Types\Select_Field(),
			new Types\Checkbox_Field(),
			new Types\Radio_Field(),
			new Types\Button_Group_Field(),
			new Types\Toggle_Field(),
			new Types\Message_Field(),
			new Types\Tab_Field(),
			new Types\Accordion_Field(),
			new Types\Oembed_Field(),
			new Types\Gallery_Field(),
			new Types\Link_Field(),
			new Types\Post_Field(),
			new Types\Page_Link_Field(),
			new Types\Relationship_Field(),
			new Types\Taxonomy_Field(),
			new Types\User_Field(),
			new Types\Date_Field(),
			new Types\Time_Field(),
			new Types\Datetime_Field(),
			new Types\Color_Field(),
			new Types\Icon_Field(),
			new Types\Map_Field(),
			new Types\Group_Field(),
			new Types\Repeater_Field(),
			new Types\Stacks_Field(),
			new Types\Preset_Field(),
		);

		foreach ( $core as $type ) {
			$this->add( $type );
		}

		/**
		 * Fires when field types can be registered.
		 *
		 * @param Registry $registry Field type registry. Call ->add( $type ).
		 */
		do_action( 'cfmb/fields/register', $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Add or replace a type.
	 *
	 * @param Field_Type $type Field type.
	 * @return void
	 */
	public function add( Field_Type $type ): void {
		$this->types[ $type->name() ] = $type;
	}

	/**
	 * Get a type by key.
	 *
	 * @param string $name Type key.
	 * @return Field_Type|null
	 */
	public function get( string $name ): ?Field_Type {
		$this->load();

		return $this->types[ $name ] ?? null;
	}

	/**
	 * All types.
	 *
	 * @return array<string, Field_Type>
	 */
	public function all(): array {
		$this->load();

		return $this->types;
	}
}
