<?php
/**
 * wp cfmb doctor
 *
 * @package CFMB
 */

namespace CFMB\Cli;

use CFMB\Blocks\Block_Registry;
use CFMB\Content\Registrar;
use CFMB\Core\Plugin;
use CFMB\Fields\Registry;
use CFMB\Location\Rule_Registry;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Sync\Sync_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Checks CFMB's setup and reports problems.
 */
class Doctor_Command {

	/**
	 * CFMB.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin CFMB.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Check field groups, definitions and Schema Sync.
	 *
	 * Looks for unknown field types and location rules, names too long for
	 * their nesting, presets that can't be resolved, field names used by
	 * several groups, Field Blocks without a template, post types or
	 * taxonomies whose name was taken, sync conflicts and an unwritable sync folder.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfmb doctor
	 *
	 * @return void
	 */
	public function __invoke(): void {
		$problems = array_merge( $this->group_problems(), $this->block_problems(), $this->content_problems(), $this->sync_problems() );

		if ( ! $problems ) {
			\WP_CLI::success( 'No problems found.' );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $problems, array( 'where', 'problem' ) );
		\WP_CLI::warning( count( $problems ) . ' problem(s) found.' );
	}

	/**
	 * Field Blocks whose template can't be found or loaded.
	 *
	 * @return array<int, array{where: string, problem: string}>
	 */
	private function block_problems(): array {
		$problems = array();
		$blocks   = $this->plugin->get( Block_Registry::class );

		foreach ( $blocks->all() as $name => $definition ) {
			$template = $blocks->template( $definition );

			if ( '' !== $template['error'] ) {
				$problems[] = array(
					'where'   => $name,
					'problem' => $template['error'],
				);
			}
		}

		return $problems;
	}

	/**
	 * Problems inside Field Groups.
	 *
	 * @return array<int, array{where: string, problem: string}>
	 */
	private function group_problems(): array {
		$problems  = array();
		$types     = $this->plugin->get( Registry::class );
		$rules     = $this->plugin->get( Rule_Registry::class );
		$sanitizer = $this->plugin->get( Group_Sanitizer::class );
		$names     = array();

		foreach ( $this->plugin->get( Group_Repository::class )->all() as $group ) {
			$where = $group->title() . ' (' . $group->key() . ')';

			foreach ( $this->walk( $group->stored_fields() ) as $field ) {
				if ( ! $types->get( (string) ( $field['type'] ?? '' ) ) ) {
					$problems[] = array(
						'where'   => $where,
						'problem' => 'Unknown field type "' . ( $field['type'] ?? '' ) . '". Is the plugin that adds it active?',
					);
				}
			}

			foreach ( $group->location() as $set ) {
				foreach ( (array) $set as $rule ) {
					if ( ! $rules->get( (string) ( $rule['param'] ?? '' ) ) ) {
						$problems[] = array(
							'where'   => $where,
							'problem' => 'Unknown location rule "' . ( $rule['param'] ?? '' ) . '".',
						);
					}
				}
			}

			foreach ( $this->walk( $group->stored_fields() ) as $field ) {
				if ( 'preset' === ( $field['type'] ?? '' ) && empty( $field['settings']['source'] ) ) {
					$problems[] = array(
						'where'   => $where,
						'problem' => 'Preset "' . ( $field['label'] ?? '' ) . '" has no source group.',
					);
				}
			}

			$clean = $sanitizer->sanitize( $group->to_array(), 'db' === $group->source() ? $group : null );
			if ( is_wp_error( $clean ) ) {
				$problems[] = array(
					'where'   => $where,
					'problem' => $clean->get_error_message(),
				);
			}

			// Values of a group used only as a Field Block live in each block, not on posts.
			if ( $group->active() && ! $group->preset_only() && ( '' === $group->block_name() || $group->location() ) ) {
				foreach ( $group->fields() as $field ) {
					if ( '' !== $field->name() ) {
						$names[ $field->name() ][] = $group->title();
					}
				}
			}
		}

		foreach ( $names as $name => $titles ) {
			if ( count( $titles ) > 1 ) {
				$problems[] = array(
					'where'   => implode( ', ', array_unique( $titles ) ),
					'problem' => "The name \"{$name}\" is used by several groups. If they show on the same screen, they share one value.",
				);
			}
		}

		return $problems;
	}

	/**
	 * Post types and taxonomies that couldn't be registered.
	 *
	 * @return array<int, array{where: string, problem: string}>
	 */
	private function content_problems(): array {
		$problems = array();

		foreach ( $this->plugin->get( Registrar::class )->skipped() as $kind => $names ) {
			foreach ( $names as $name ) {
				$problems[] = array(
					'where'   => $kind . ' ' . $name,
					'problem' => 'Not registered: the name is empty or already used by WordPress, the theme or another plugin.',
				);
			}
		}

		return $problems;
	}

	/**
	 * Sync conflicts and folder access.
	 *
	 * @return array<int, array{where: string, problem: string}>
	 */
	private function sync_problems(): array {
		$sync     = $this->plugin->get( Sync_Manager::class );
		$problems = array();
		$dir      = $sync->files()->save_dir();

		if ( is_dir( $dir ) ? ! wp_is_writable( $dir ) : ! wp_is_writable( dirname( $dir ) ) ) {
			$problems[] = array(
				'where'   => $dir,
				'problem' => 'The Schema Sync folder is not writable.',
			);
		}

		foreach ( $sync->rows() as $row ) {
			if ( Sync_Manager::CONFLICT === $row['status'] ) {
				$problems[] = array(
					'where'   => $row['title'] . ' (' . $row['key'] . ')',
					'problem' => 'The database and the file both changed. Review it under CFMB > Sync.',
				);
			}
		}

		return $problems;
	}

	/**
	 * Every field definition at any depth.
	 *
	 * @param array<int, mixed> $fields Field definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function walk( array $fields ): array {
		$out = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$out[] = $field;
			$out   = array_merge( $out, $this->walk( (array) ( $field['fields'] ?? array() ) ) );

			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$out = array_merge( $out, $this->walk( (array) ( $layout['fields'] ?? array() ) ) );
			}
		}

		return $out;
	}
}
