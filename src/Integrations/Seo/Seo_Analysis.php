<?php
/**
 * Yoast SEO and Rank Math content analysis.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Seo;

use CFMB\Admin\Assets;
use CFMB\Integrations\Field_Integrations;
use CFMB\Schema\Field;
use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * Lets Yoast SEO and Rank Math count text, rich text and image alt text
 * from CFMB fields, as if it were part of the post content. Nothing is
 * stored; the `seo` script reads the fields on the edit screen as they change.
 */
class Seo_Analysis {

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Asset loader.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'cfmb/admin/enqueue_fields', array( $this, 'enqueue' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * SEO plugins that are active: yoast, rankmath.
	 *
	 * @return string[]
	 */
	public static function plugins(): array {
		$plugins = array();

		if ( defined( 'WPSEO_VERSION' ) ) {
			$plugins[] = 'yoast';
		}

		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$plugins[] = 'rankmath';
		}

		return $plugins;
	}

	/**
	 * Load the analysis script on post and term edit screens with fields to read.
	 *
	 * @param Group[] $groups Groups on the screen.
	 * @return void
	 */
	public function enqueue( $groups ): void {
		$plugins = self::plugins();
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $plugins || ! $screen || ! in_array( $screen->base, array( 'post', 'term' ), true ) ) {
			return;
		}

		$keys = array();
		foreach ( (array) $groups as $group ) {
			if ( $group instanceof Group ) {
				$keys = array_merge( $keys, $this->field_keys( $group->fields() ) );
			}
		}

		/**
		 * Filters the keys of fields SEO plugins analyse on this screen.
		 *
		 * @param string[] $keys   Field keys, at any depth.
		 * @param Group[]  $groups Groups on the screen.
		 */
		$keys = array_values( array_unique( array_map( 'strval', (array) apply_filters( 'cfmb/seo/fields', $keys, $groups ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! $keys ) {
			return;
		}

		$this->assets->enqueue(
			'seo',
			'cfmbSeo',
			array(
				'plugins' => $plugins,
				'fields'  => $keys,
			)
		);
	}

	/**
	 * Keys of analysed fields at every depth. Structures are walked; their
	 * own setting only decides for themselves, not for their sub-fields.
	 *
	 * @param Field[] $fields Fields.
	 * @return string[]
	 */
	private function field_keys( array $fields ): array {
		$keys = array();

		foreach ( $fields as $field ) {
			if ( 'password' !== $field->type() && Field_Integrations::seo( $field ) ) {
				$keys[] = $field->key();
			}

			$keys = array_merge( $keys, $this->field_keys( $field->sub_fields() ) );

			foreach ( $field->layouts() as $layout ) {
				$keys = array_merge( $keys, $this->field_keys( $layout['fields'] ) );
			}
		}

		return $keys;
	}
}
