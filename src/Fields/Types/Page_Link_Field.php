<?php
/**
 * Page link field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Picks posts like the Post field but gives templates their URLs. Stores
 * IDs, so links keep working when a slug changes.
 */
class Page_Link_Field extends Post_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'page_link';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Page link', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->post_type_setting(),
			$this->setting( 'multiple', __( 'Allow several pages', 'cfmb' ), 'toggle', false ),
		);
	}

	/**
	 * The permalink.
	 *
	 * @param WP_Post $post  Post.
	 * @param Field   $field Field definition.
	 * @return mixed
	 */
	protected function format_post( WP_Post $post, Field $field ) {
		return (string) get_permalink( $post );
	}
}
