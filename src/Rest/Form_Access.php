<?php
/**
 * Lets add-ons open field control routes to front-end forms.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Schema\Field;
use CFMB\Schema\Group;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * The search, icons and rows routes serve the field controls on edit
 * screens, so they expect a signed-in editor. A front-end form (an add-on)
 * shows the same controls to people who may not be signed in. CFMB itself
 * never allows that; the filter below is how an add-on vouches for a request.
 */
final class Form_Access {

	/**
	 * Whether an add-on allows this request on behalf of a front-end form.
	 *
	 * @param string          $route  search, icons or rows.
	 * @param WP_REST_Request $request Request.
	 * @param Group|null      $group  Field Group, for search.
	 * @param Field|null      $field  Field, for search.
	 * @return bool
	 */
	public static function allows( string $route, WP_REST_Request $request, ?Group $group = null, ?Field $field = null ): bool {
		/**
		 * Filters whether a field control route serves a front-end form.
		 *
		 * When true the route answers visitors too. Term search then skips the
		 * assign_terms check for that field's taxonomy, and user search
		 * returns names only, the way it does for authors. Return true only
		 * after checking the request belongs to a form that shows this field.
		 *
		 * @since 0.14.0
		 *
		 * @param bool            $allowed Default false.
		 * @param string          $route   search, icons or rows.
		 * @param WP_REST_Request $request Request. Read its group, field and object parameters.
		 * @param Group|null      $group   Field Group (search only).
		 * @param Field|null      $field   Field (search only).
		 */
		return true === apply_filters( 'cfmb/rest/form_access', false, $route, $request, $group, $field ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}
}
