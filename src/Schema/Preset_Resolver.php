<?php
/**
 * Expands Presets into the fields they stand for.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * A Preset field points at another Field Group (all of its fields, or some).
 * At runtime the preset is replaced by copies of those fields, so edits to
 * the source group show up everywhere the preset is used.
 *
 * With plain names the copies sit in the list as if they were added there.
 * With prefixed names they become one Group field named after the preset,
 * so values are stored as hero_title, hero_image.
 *
 * Copies get derived keys ({preset key}__{source key}), so the same preset
 * can be used twice in one group without clashing.
 */
final class Preset_Resolver {

	/**
	 * Groups by key that presets may point at.
	 *
	 * @var array<string, Group>
	 */
	private array $groups;

	/**
	 * Constructor.
	 *
	 * @param array<string, Group> $groups Groups by key.
	 */
	public function __construct( array $groups ) {
		$this->groups = $groups;
	}

	/**
	 * Resolve every group.
	 *
	 * @return array<string, Group>
	 */
	public function resolve_all(): array {
		$resolved = array();

		foreach ( $this->groups as $key => $group ) {
			$resolved[ $key ] = $this->resolve( $group );
		}

		return $resolved;
	}

	/**
	 * Copy of a group with its presets expanded.
	 *
	 * @param Group $group Group.
	 * @return Group
	 */
	public function resolve( Group $group ): Group {
		if ( ! self::uses_source( $group->stored_fields(), null ) ) {
			return $group;
		}

		return $group->with_resolved_fields( $this->expand_list( $group->stored_fields(), array( $group->key() ) ) );
	}

	/**
	 * Whether field definitions contain a preset, optionally one pointing at a given group.
	 *
	 * @param array<int, mixed> $definitions Field definitions.
	 * @param string|null       $source      Source group key, or null for any preset.
	 * @return bool
	 */
	public static function uses_source( array $definitions, ?string $source ): bool {
		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			if ( 'preset' === ( $definition['type'] ?? '' ) && ( null === $source || ( $definition['settings']['source'] ?? '' ) === $source ) ) {
				return true;
			}

			$lists = array( (array) ( $definition['fields'] ?? array() ) );
			foreach ( (array) ( $definition['layouts'] ?? array() ) as $layout ) {
				$lists[] = (array) ( $layout['fields'] ?? array() );
			}

			foreach ( $lists as $list ) {
				if ( self::uses_source( $list, $source ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Expand presets in a list, at any depth.
	 *
	 * @param array<int, mixed> $definitions Field definitions.
	 * @param string[]          $stack       Group keys being expanded, to stop cycles.
	 * @return array<int, array<string, mixed>>
	 */
	private function expand_list( array $definitions, array $stack ): array {
		$out = array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			if ( 'preset' !== ( $definition['type'] ?? '' ) ) {
				$out[] = $this->expand_children( $definition, $stack );
				continue;
			}

			$fields = $this->expand_preset( $definition, $stack );

			if ( null === $fields ) {
				// Source missing or pointing back at itself: the preset stays and shows a notice.
				$out[] = $definition;
				continue;
			}

			if ( 'prefixed' === ( $definition['settings']['naming'] ?? 'plain' ) ) {
				$out[] = array(
					'key'          => (string) $definition['key'],
					'name'         => (string) ( $definition['name'] ?? '' ),
					'label'        => (string) ( $definition['label'] ?? '' ),
					'type'         => 'group',
					'instructions' => (string) ( $definition['instructions'] ?? '' ),
					'required'     => false,
					'wrapper'      => $definition['wrapper'] ?? array(),
					'conditions'   => $definition['conditions'] ?? array(),
					'settings'     => array(
						'display' => 'plain',
						'preset'  => (string) ( $definition['settings']['source'] ?? '' ),
					),
					'fields'       => $fields,
				);
				continue;
			}

			foreach ( $fields as $field ) {
				$field['conditions'] = self::combine_conditions( (array) ( $definition['conditions'] ?? array() ), (array) ( $field['conditions'] ?? array() ) );
				$out[]               = $field;
			}
		}

		return $out;
	}

	/**
	 * Expand presets inside a field's sub-fields and layouts.
	 *
	 * @param array<string, mixed> $definition Field definition.
	 * @param string[]             $stack      Group keys being expanded.
	 * @return array<string, mixed>
	 */
	private function expand_children( array $definition, array $stack ): array {
		if ( ! empty( $definition['fields'] ) && is_array( $definition['fields'] ) ) {
			$definition['fields'] = $this->expand_list( $definition['fields'], $stack );
		}

		if ( ! empty( $definition['layouts'] ) && is_array( $definition['layouts'] ) ) {
			foreach ( $definition['layouts'] as $index => $layout ) {
				$definition['layouts'][ $index ]['fields'] = $this->expand_list( (array) ( $layout['fields'] ?? array() ), $stack );
			}
		}

		return $definition;
	}

	/**
	 * Field definitions a preset stands for, or null when it can't be resolved.
	 *
	 * @param array<string, mixed> $preset Preset field definition.
	 * @param string[]             $stack  Group keys being expanded.
	 * @return array<int, array<string, mixed>>|null
	 */
	private function expand_preset( array $preset, array $stack ): ?array {
		$source_key = (string) ( $preset['settings']['source'] ?? '' );
		$source     = $this->groups[ $source_key ] ?? null;

		if ( ! $source || in_array( $source_key, $stack, true ) ) {
			return null;
		}

		$picked = array_map( 'strval', (array) ( $preset['settings']['fields'] ?? array() ) );
		$chosen = array_values(
			array_filter(
				$source->stored_fields(),
				static fn( $field ) => ! $picked || in_array( (string) ( $field['key'] ?? '' ), $picked, true )
			)
		);

		$stack[] = $source_key;
		$fields  = self::derive_keys( $this->expand_list( $chosen, $stack ), (string) $preset['key'] );

		/**
		 * Filters the fields a preset expands to.
		 *
		 * @param array<int, array<string, mixed>> $fields Field definitions, keys already derived.
		 * @param array<string, mixed>             $preset Preset field definition.
		 * @param Group                            $source Source Field Group.
		 */
		$filtered = apply_filters( 'cfmb/presets/resolve', $fields, $preset, $source ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $filtered ) ? array_values( $filtered ) : $fields;
	}

	/**
	 * Give copied fields keys derived from the preset key, at every depth.
	 *
	 * @param array<int, array<string, mixed>> $fields Field definitions.
	 * @param string                           $prefix Preset field key.
	 * @return array<int, array<string, mixed>>
	 */
	private static function derive_keys( array $fields, string $prefix ): array {
		$map = array();

		foreach ( $fields as $index => $field ) {
			$old                     = (string) ( $field['key'] ?? '' );
			$new                     = $prefix . '__' . preg_replace( '/^fld_/', '', $old );
			$map[ $old ]             = $new;
			$fields[ $index ]['key'] = $new;

			if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$fields[ $index ]['fields'] = self::derive_keys( $field['fields'], $prefix );
			}

			foreach ( (array) ( $field['layouts'] ?? array() ) as $l => $layout ) {
				$fields[ $index ]['layouts'][ $l ]['fields'] = self::derive_keys( (array) ( $layout['fields'] ?? array() ), $prefix );
			}
		}

		// Rules pointing at fields the preset left out are dropped, or the field would never show.
		foreach ( $fields as $index => $field ) {
			$sets = array();

			foreach ( (array) ( $field['conditions'] ?? array() ) as $rules ) {
				$set = array();

				foreach ( (array) $rules as $rule ) {
					$target = (string) ( $rule['field'] ?? '' );

					if ( isset( $map[ $target ] ) ) {
						$set[] = array_merge( (array) $rule, array( 'field' => $map[ $target ] ) );
					}
				}

				if ( $set ) {
					$sets[] = $set;
				}
			}

			$fields[ $index ]['conditions'] = $sets;
		}

		return $fields;
	}

	/**
	 * Conditions that pass only when both lists pass (each is OR of AND).
	 *
	 * @param array<int, mixed> $outer Preset conditions.
	 * @param array<int, mixed> $inner Field conditions.
	 * @return array<int, mixed>
	 */
	private static function combine_conditions( array $outer, array $inner ): array {
		if ( ! $outer ) {
			return $inner;
		}

		if ( ! $inner ) {
			return $outer;
		}

		$combined = array();
		foreach ( $outer as $a ) {
			foreach ( $inner as $b ) {
				$combined[] = array_merge( (array) $a, (array) $b );
			}
		}

		return $combined;
	}
}
