<?php
/**
 * Block Bindings in the block editor.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Bindings;

use CFMB\Admin\Assets;
use CFMB\Content\Options_Pages;
use CFMB\Display\Formatter;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the editor side of the cfmb/field source: values for bound blocks,
 * editing on the canvas, and the list of fields the bindings panel offers.
 * The editor side needs WordPress 6.7 or later; on 6.6 bound blocks still
 * show their values on the front end.
 */
class Editor_Assets {

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Value formatting, for which fields can be bound.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Options pages.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Script loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Matcher       $matcher   Location matcher.
	 * @param Formatter     $formatter Value formatting.
	 * @param Options_Pages $pages     Options pages.
	 * @param Assets        $assets    Script loader.
	 */
	public function __construct( Matcher $matcher, Formatter $formatter, Options_Pages $pages, Assets $assets ) {
		$this->matcher   = $matcher;
		$this->formatter = $formatter;
		$this->pages     = $pages;
		$this->assets    = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the bindings script.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		$this->assets->enqueue( 'bindings', 'cfmbBindings', $this->config() );
	}

	/**
	 * Fields the bindings panel can offer, per post type and per options page.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		$post_types = array();

		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $post_type ) {
			$fields = $this->fields( $this->matcher->groups_for_list( 'post', (string) $post_type ) );

			if ( $fields ) {
				$post_types[ $post_type ] = $fields;
			}
		}

		$options = array();

		foreach ( $this->pages->all() as $id => $page ) {
			$fields = $this->fields( $this->matcher->groups_for( Context::for_options_page( (string) $id ) ) );

			if ( $fields ) {
				$options[] = array(
					'object' => 'option:' . $id,
					'title'  => (string) $page['page_title'],
					'fields' => $fields,
				);
			}
		}

		return array(
			'editable'  => Editor_Values::editable_types(),
			'postTypes' => (object) $post_types,
			'options'   => $options,
		);
	}

	/**
	 * Bindable top-level fields of some groups.
	 *
	 * @param Group[] $groups Groups.
	 * @return array<int, array<string, string>>
	 */
	private function fields( array $groups ): array {
		$fields = array();
		$seen   = array();

		foreach ( $groups as $group ) {
			foreach ( $group->fields() as $field ) {
				if ( '' === $field->name() || isset( $seen[ $field->name() ] ) || ! $this->formatter->supports( $field ) ) {
					continue;
				}

				$seen[ $field->name() ] = true;
				$fields[]               = $this->describe( $field );
			}
		}

		return $fields;
	}

	/**
	 * A field for the bindings panel.
	 *
	 * @param Field $field Field.
	 * @return array<string, string>
	 */
	private function describe( Field $field ): array {
		return array(
			'name'  => $field->name(),
			'label' => '' !== $field->label() ? $field->label() : $field->name(),
			'type'  => $field->type(),
		);
	}
}
