<?php
/**
 * Field choices for builder tag and loop pickers.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

use CFMB\Connections\Connection_Registry;
use CFMB\Display\Formatter;
use CFMB\Schema\Field;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Field_Values;

defined( 'ABSPATH' ) || exit;

/**
 * Lists fields grouped by Field Group, filtered by the kind of value a tag
 * returns. Sub-fields of Repeaters, Stacks and Groups are listed under
 * their parent with a dotted path ("items.title") for use inside a Field
 * Loop. A "Loop item" group adds the @ paths Tag_Values understands.
 *
 * Kinds: text, url, image, gallery, number, color, date, loop.
 */
class Field_Catalog {

	public const KINDS = array( 'text', 'url', 'image', 'gallery', 'number', 'color', 'date', 'loop' );

	/**
	 * Field types per kind. Text takes every type Dynamic Data can show.
	 */
	private const TYPES = array(
		'url'     => array( 'url', 'email', 'link', 'file', 'image', 'gallery', 'post', 'relationship', 'page_link', 'taxonomy', 'oembed' ),
		'image'   => array( 'image', 'gallery' ),
		'gallery' => array( 'gallery', 'image' ),
		'number'  => array( 'number', 'range' ),
		'color'   => array( 'color' ),
		'date'    => array( 'date', 'datetime', 'time' ),
	);

	/**
	 * Nesting depth listed below a top-level field.
	 */
	private const MAX_DEPTH = 4;

	/**
	 * Group storage.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Supported field check.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Loopable field check.
	 *
	 * @var Loop_Items
	 */
	private Loop_Items $loops;

	/**
	 * Connections, listed as loops.
	 *
	 * @var Connection_Registry|null
	 */
	private ?Connection_Registry $connections;

	/**
	 * Built lists per kind for this request.
	 *
	 * @var array<string, array<int, array{label: string, options: array<string, string>}>>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param Group_Repository         $groups    Group storage.
	 * @param Field_Values             $values    Value service.
	 * @param Formatter                $formatter Supported field check.
	 * @param Loop_Items               $loops       Loopable field check.
	 * @param Connection_Registry|null $connections Connections, listed as loops.
	 */
	public function __construct( Group_Repository $groups, Field_Values $values, Formatter $formatter, Loop_Items $loops, ?Connection_Registry $connections = null ) {
		$this->groups      = $groups;
		$this->values      = $values;
		$this->formatter   = $formatter;
		$this->loops       = $loops;
		$this->connections = $connections;
	}

	/**
	 * Choices for a kind, grouped.
	 *
	 * Each section also says which `object` its fields live on when that is
	 * fixed: "option:{page}" for a group shown only on one options page,
	 * otherwise empty. The "Loop item" section carries `item` => true.
	 *
	 * @param string $kind One of KINDS.
	 * @return array<int, array{label: string, options: array<string, string>, object?: string, item?: bool}>
	 */
	public function grouped( string $kind ): array {
		if ( isset( $this->cache[ $kind ] ) ) {
			return $this->cache[ $kind ];
		}

		$list = array();

		foreach ( $this->groups->active() as $group ) {
			if ( $group->preset_only() ) {
				continue;
			}

			$options = array();
			foreach ( $group->fields() as $field ) {
				$this->collect( $field, $field->name(), $field->label(), $kind, $options, 0 );
			}

			if ( $options ) {
				$list[] = array(
					'label'   => '' !== $group->title() ? $group->title() : $group->key(),
					'options' => $options,
					'object'  => self::options_object( $group->location() ),
				);
			}
		}

		if ( 'loop' === $kind && $this->connections ) {
			$options = array();

			foreach ( $this->connections->all() as $connection ) {
				$options[ Loop_Items::CONNECTION_PREFIX . $connection->name() ] = $connection->title();
			}

			if ( $options ) {
				$list[] = array(
					'label'   => __( 'Connections', 'custom-fields-meta-box' ),
					'options' => $options,
					'object'  => '',
				);
			}
		}

		$item = $this->item_options( $kind );

		if ( $item ) {
			$list[] = array(
				'label'   => __( 'Loop item', 'custom-fields-meta-box' ),
				'options' => $item,
				'object'  => '',
				'item'    => true,
			);
		}

		/**
		 * Filters the field choices builder tags and loops offer.
		 *
		 * @param array<int, array{label: string, options: array<string, string>, object?: string}> $list Groups of path => label.
		 * @param string                                                            $kind Kind of value: text, url, image, gallery, number, color, date or loop.
		 */
		$this->cache[ $kind ] = (array) apply_filters( 'cfmb/builders/fields', $list, $kind ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $this->cache[ $kind ];
	}

	/**
	 * Choices for a kind as one flat list, labels prefixed with the group title.
	 * For builders whose selects have no option groups.
	 *
	 * @param string $kind One of KINDS.
	 * @return array<string, string>
	 */
	public function flat( string $kind ): array {
		$flat = array();

		foreach ( $this->grouped( $kind ) as $group ) {
			foreach ( $group['options'] as $path => $label ) {
				/* translators: 1: Field Group title, 2: field label. */
				$flat[ $path ] = sprintf( __( '%1$s: %2$s', 'custom-fields-meta-box' ), $group['label'], $label );
			}
		}

		return $flat;
	}

	/**
	 * Whether a section from grouped() is the "Loop item" section.
	 *
	 * @param array<string, mixed> $section Section.
	 * @return bool
	 */
	public function is_item_section( array $section ): bool {
		return ! empty( $section['item'] );
	}

	/**
	 * The options page a location targets, as an `object` argument, when
	 * every rule group is "Options page is X" for the same X.
	 *
	 * @param array<int, array<int, array<string, string>>> $location Location rule groups.
	 * @return string option:{page}, or empty.
	 */
	public static function options_object( array $location ): string {
		$page = '';

		foreach ( $location as $rules ) {
			$found = '';

			foreach ( (array) $rules as $rule ) {
				if ( 'options_page' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '==' ) ) {
					$found = sanitize_key( (string) ( $rule['value'] ?? '' ) );
				}
			}

			if ( '' === $found || ( '' !== $page && $found !== $page ) ) {
				return '';
			}

			$page = $found;
		}

		return '' !== $page ? 'option:' . $page : '';
	}

	/**
	 * Object choices shared by every tag. "custom" means the Object text setting.
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array {
		return array(
			''       => __( 'Current post', 'custom-fields-meta-box' ),
			'term'   => __( 'Current term (archives)', 'custom-fields-meta-box' ),
			'author' => __( 'Author of the current post', 'custom-fields-meta-box' ),
			'user'   => __( 'Logged-in user', 'custom-fields-meta-box' ),
			'option' => __( 'Options page', 'custom-fields-meta-box' ),
			'custom' => __( 'Other (enter below)', 'custom-fields-meta-box' ),
		);
	}

	/**
	 * The `object` argument for a source choice.
	 *
	 * @param string $source Source choice.
	 * @param string $custom Object text, used with "custom" and as the page ID with "option".
	 * @return string
	 */
	public static function object_for( string $source, string $custom = '' ): string {
		$custom = trim( $custom );

		if ( 'custom' === $source ) {
			return $custom;
		}

		if ( 'option' === $source ) {
			return '' !== $custom ? 'option:' . sanitize_key( $custom ) : 'option';
		}

		return array_key_exists( $source, self::sources() ) ? $source : '';
	}

	/**
	 * Add a field and its sub-fields to the choices.
	 *
	 * @param Field                 $field   Field.
	 * @param string                $path    Path of the field.
	 * @param string                $label   Label with parent labels.
	 * @param string                $kind    Kind.
	 * @param array<string, string> $options Choices, by reference.
	 * @param int                   $depth   Nesting depth.
	 * @return void
	 */
	private function collect( Field $field, string $path, string $label, string $kind, array &$options, int $depth ): void {
		if ( '' === $field->name() ) {
			return;
		}

		$label = '' !== $label ? $label : $field->name();

		if ( $this->matches( $field, $kind ) ) {
			$options[ $path ] = $label;
		}

		if ( ! $this->values->is_structure( $field ) || $depth >= self::MAX_DEPTH ) {
			return;
		}

		foreach ( $this->sub_fields( $field ) as $sub ) {
			/* translators: 1: parent field label, 2: sub-field label. */
			$sub_label = sprintf( __( '%1$s › %2$s', 'custom-fields-meta-box' ), $label, '' !== $sub->label() ? $sub->label() : $sub->name() );
			$this->collect( $sub, $path . '.' . $sub->name(), $sub_label, $kind, $options, $depth + 1 );
		}
	}

	/**
	 * Sub-fields of a structure; for Stacks, those of every layout, once per name.
	 *
	 * @param Field $field Structure field.
	 * @return Field[]
	 */
	private function sub_fields( Field $field ): array {
		if ( 'stacks' !== $field->type() ) {
			return $field->sub_fields();
		}

		$subs = array();
		foreach ( $field->layouts() as $layout ) {
			foreach ( $layout['fields'] as $sub ) {
				if ( ! isset( $subs[ $sub->name() ] ) ) {
					$subs[ $sub->name() ] = $sub;
				}
			}
		}

		return array_values( $subs );
	}

	/**
	 * Whether a field fits a kind.
	 *
	 * @param Field  $field Field.
	 * @param string $kind  Kind.
	 * @return bool
	 */
	private function matches( Field $field, string $kind ): bool {
		if ( 'loop' === $kind ) {
			return $this->loops->can_loop( $field );
		}

		if ( ! $this->formatter->supports( $field ) ) {
			return false;
		}

		return 'text' === $kind || in_array( $field->type(), self::TYPES[ $kind ] ?? array(), true );
	}

	/**
	 * Loop item choices for a kind.
	 *
	 * @param string $kind Kind.
	 * @return array<string, string>
	 */
	private function item_options( string $kind ): array {
		switch ( $kind ) {
			case 'text':
				return array(
					'@index'         => __( 'Item number', 'custom-fields-meta-box' ),
					'@count'         => __( 'Item count', 'custom-fields-meta-box' ),
					'@title'         => __( 'Title of the linked post, term or user', 'custom-fields-meta-box' ),
					'@layout'        => __( 'Stacks layout name', 'custom-fields-meta-box' ),
					'@image_title'   => __( 'Image title', 'custom-fields-meta-box' ),
					'@image_alt'     => __( 'Image alt text', 'custom-fields-meta-box' ),
					'@image_caption' => __( 'Image caption', 'custom-fields-meta-box' ),
				);

			case 'number':
				return array(
					'@index' => __( 'Item number', 'custom-fields-meta-box' ),
					'@count' => __( 'Item count', 'custom-fields-meta-box' ),
				);

			case 'url':
				return array(
					'@image_url' => __( 'Image URL', 'custom-fields-meta-box' ),
					'@post_url'  => __( 'Post URL', 'custom-fields-meta-box' ),
					'@url'       => __( 'Link of the linked post, term or user', 'custom-fields-meta-box' ),
				);

			case 'image':
			case 'gallery':
				return array( '@image' => __( 'Image', 'custom-fields-meta-box' ) );
		}

		return array();
	}
}
