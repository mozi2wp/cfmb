<?php
/**
 * Places Field Groups on post, term, user, comment, attachment, menu and widget screens.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Object_Ref;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;
use WP_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the groups for the current screen and prints them in the right spot.
 */
class Edit_Screens {

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Group output.
	 *
	 * @var Group_Renderer
	 */
	private Group_Renderer $renderer;

	/**
	 * Groups for the current term, user or menu screen.
	 *
	 * @var Group[]
	 */
	private array $groups = array();

	/**
	 * Menu being edited on Appearance > Menus.
	 *
	 * @var int
	 */
	private int $menu_id = 0;

	/**
	 * Asset loader, for the live location check. Optional.
	 *
	 * @var Assets|null
	 */
	private ?Assets $assets;

	/**
	 * Rules that depend on what is picked in the post form, so the result
	 * can change while the post is edited.
	 */
	public const FORM_RULES = array( 'page_template', 'post_format', 'post_status', 'post_term', 'product_type' );

	/**
	 * Constructor.
	 *
	 * @param Matcher        $matcher  Location matcher.
	 * @param Group_Renderer $renderer Group output.
	 * @param Assets|null    $assets   Asset loader, for the live location check.
	 */
	public function __construct( Matcher $matcher, Group_Renderer $renderer, ?Assets $assets = null ) {
		$this->matcher  = $matcher;
		$this->renderer = $renderer;
		$this->assets   = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_post_boxes' ), 10, 2 );
		add_action( 'load-edit-tags.php', array( $this, 'prepare_term_screen' ) );
		add_action( 'load-term.php', array( $this, 'prepare_term_screen' ) );
		add_action( 'load-profile.php', array( $this, 'prepare_user_screen' ) );
		add_action( 'load-user-edit.php', array( $this, 'prepare_user_screen' ) );
		add_action( 'load-user-new.php', array( $this, 'prepare_user_screen' ) );
		add_action( 'add_meta_boxes_comment', array( $this, 'add_comment_boxes' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'link_attachment_fields' ), 10, 2 );
		add_action( 'load-nav-menus.php', array( $this, 'prepare_menu_screen' ) );
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_menu_item' ), 10, 3 );
		add_action( 'load-widgets.php', array( $this, 'prepare_widget_screen' ) );
		add_action( 'in_widget_form', array( $this, 'render_widget' ), 10, 3 );
		add_action( 'wp_nav_menu_item_custom_fields_customize_template', array( $this, 'customizer_menu_item_note' ) );
	}

	/**
	 * Add a meta box per matching group. Works in both the block and classic editor.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      Post being edited.
	 * @return void
	 */
	public function add_post_boxes( $post_type, $post = null ): void {
		if ( ! $post instanceof WP_Post || Group_Repository::POST_TYPE === $post_type ) {
			return;
		}

		$context = Context::for_post( (int) $post->ID, (string) $post_type );
		$groups  = $this->matcher->groups_for( $context );
		$waiting = Object_Ref::POST === $context->kind() ? $this->waiting_groups( $groups, (string) $post_type ) : array();

		$this->renderer->enqueue( array_merge( $groups, $waiting ) );
		$this->watch_location( $post, array_merge( $groups, $waiting ) );

		$ref    = new Object_Ref( Object_Ref::POST, (int) $post->ID );
		$hidden = array_map( static fn( Group $group ) => $group->key(), $waiting );

		foreach ( array_merge( $groups, $waiting ) as $group ) {
			$position = (string) $group->setting( 'position' );

			// Boxes that may start to match once a template or term is picked wait hidden.
			if ( in_array( $group->key(), $hidden, true ) ) {
				add_filter(
					"postbox_classes_{$post_type}_cfmb-{$group->key()}",
					static function ( $classes ) {
						$classes   = is_array( $classes ) ? $classes : array();
						$classes[] = 'cfmb-box-waiting';
						return $classes;
					}
				);
			}

			/**
			 * Filters whether a group gets a meta box on a post screen. Integrations
			 * that place a group elsewhere (the WooCommerce Product data box) return false.
			 *
			 * @param bool    $show  Whether to add the meta box.
			 * @param Group   $group Field Group.
			 * @param WP_Post $post  Post being edited.
			 */
			if ( ! apply_filters( 'cfmb/admin/meta_box', true, $group, $post ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				continue;
			}

			add_meta_box(
				'cfmb-' . $group->key(),
				esc_html( $group->title() ),
				function () use ( $group, $ref ) {
					$this->renderer->render( $group, $ref, 'box' );
				},
				null,
				'side' === $position ? 'side' : 'normal',
				'high' === $position ? 'high' : 'default',
				array( '__block_editor_compatible_meta_box' => true )
			);
		}
	}

	/**
	 * Groups that don't match the post now but could, once a template,
	 * format, status, term or product type is picked in the form.
	 *
	 * @param Group[] $matching  Groups that match now.
	 * @param string  $post_type Post type.
	 * @return Group[]
	 */
	private function waiting_groups( array $matching, string $post_type ): array {
		$keys = array_map( static fn( Group $group ) => $group->key(), $matching );

		return array_values(
			array_filter(
				$this->matcher->groups_for_list( 'post', $post_type ),
				fn( Group $group ) => ! in_array( $group->key(), $keys, true ) && $this->uses_form_rules( $group )
			)
		);
	}

	/**
	 * Whether a group's location depends on what is picked in the post form.
	 *
	 * @param Group $group Group.
	 * @return bool
	 */
	private function uses_form_rules( Group $group ): bool {
		foreach ( $group->location() as $set ) {
			foreach ( (array) $set as $rule ) {
				if ( in_array( (string) ( $rule['param'] ?? '' ), self::FORM_RULES, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Load the script that re-checks location rules as the form changes,
	 * when any box on the screen depends on the form.
	 *
	 * @param WP_Post $post   Post being edited.
	 * @param Group[] $groups Groups with a box on the screen.
	 * @return void
	 */
	private function watch_location( WP_Post $post, array $groups ): void {
		$watched = array_values( array_filter( $groups, array( $this, 'uses_form_rules' ) ) );

		if ( ! $this->assets || ! $watched ) {
			return;
		}

		$this->assets->enqueue(
			'location-watch',
			'cfmbLocationWatch',
			array(
				'postId'   => (int) $post->ID,
				'postType' => (string) $post->post_type,
				'groups'   => array_map( static fn( Group $group ) => $group->key(), $watched ),
			)
		);
	}

	/**
	 * Add a meta box per matching group on the edit comment screen.
	 *
	 * @param WP_Comment $comment Comment being edited.
	 * @return void
	 */
	public function add_comment_boxes( $comment ): void {
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		$groups = $this->matcher->groups_for( Context::for_comment( (int) $comment->comment_ID ) );
		$this->renderer->enqueue( $groups );

		$ref = new Object_Ref( Object_Ref::COMMENT, (int) $comment->comment_ID );

		foreach ( $groups as $group ) {
			add_meta_box(
				'cfmb-' . $group->key(),
				esc_html( $group->title() ),
				function () use ( $group, $ref ) {
					$this->renderer->render( $group, $ref, 'box' );
				},
				'comment',
				'normal'
			);
		}
	}

	/**
	 * In the media modal, link to the attachment's edit screen when it has
	 * fields. The modal only takes plain inputs, so the fields live there.
	 *
	 * @param array<string, array<string, mixed>> $form_fields Modal fields.
	 * @param WP_Post                             $post        Attachment.
	 * @return array<string, array<string, mixed>>
	 */
	public function link_attachment_fields( $form_fields, $post ) {
		global $pagenow;

		if ( 'post.php' === $pagenow || ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $form_fields;
		}

		if ( ! $this->matcher->groups_for( Context::for_attachment( (int) $post->ID ) ) ) {
			return $form_fields;
		}

		$form_fields['cfmb_more_fields'] = array(
			'label'        => __( 'More fields', 'cfmb' ),
			'input'        => 'html',
			'html'         => sprintf(
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( (string) get_edit_post_link( $post->ID ) ),
				esc_html__( 'Edit them on the attachment page (opens in a new tab)', 'cfmb' )
			),
			'show_in_edit' => false,
		);

		return $form_fields;
	}

	/**
	 * Decide what to show on Appearance > Menus: groups for the menu itself,
	 * and assets for groups on its items.
	 *
	 * @return void
	 */
	public function prepare_menu_screen(): void {
		// Read-only screen context; saving is nonce-checked in Form_Saver.
		$menu_id = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $menu_id ) {
			$menu_id = (int) get_user_option( 'nav_menu_recently_edited' );
		}

		if ( ! $menu_id || ! is_nav_menu( $menu_id ) ) {
			return;
		}

		$this->menu_id = $menu_id;
		$this->groups  = $this->matcher->groups_for( Context::for_menu( $menu_id ) );
		$item_groups   = $this->matcher->groups_for( Context::for_menu_item( 0, $menu_id ) );

		$this->renderer->enqueue( array_merge( $this->groups, $item_groups ) );

		if ( $this->groups ) {
			add_action( 'admin_footer', array( $this, 'render_menu' ) );
		}
	}

	/**
	 * Print the menu's groups, then move them into the menu form, above
	 * Menu Settings. Core has no hook inside that form.
	 *
	 * @return void
	 */
	public function render_menu(): void {
		$ref = new Object_Ref( Object_Ref::TERM, $this->menu_id );

		echo '<div id="cfmb-menu-fields" class="cfmb-menu-fields" hidden>';

		foreach ( $this->groups as $group ) {
			echo '<div class="cfmb-menu-fields__group">';
			printf( '<h3 class="cfmb-menu-fields__title">%s</h3>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain' );
			echo '</div>';
		}

		echo '</div>';

		wp_print_inline_script_tag(
			'(function(){var box=document.getElementById("cfmb-menu-fields"),form=document.getElementById("update-nav-menu");' .
			'if(!box||!form){return;}var anchor=form.querySelector(".menu-settings"),body=form.querySelector("#post-body-content");' .
			'if(anchor){anchor.parentNode.insertBefore(box,anchor);}else if(body){body.appendChild(box);}else{form.appendChild(box);}box.hidden=false;})();'
		);
	}

	/**
	 * Fields inside one menu item's settings. Runs on page load and when
	 * items are added, which happens over AJAX.
	 *
	 * @param int     $item_id   Menu item post ID.
	 * @param WP_Post $menu_item Menu item.
	 * @param int     $depth     Nesting depth.
	 * @return void
	 */
	public function render_menu_item( $item_id, $menu_item = null, $depth = 0 ): void {
		$item_id = (int) $item_id;
		$groups  = $this->matcher->groups_for( Context::for_menu_item( $item_id, $this->menu_id, (int) $depth ) );

		if ( ! $groups ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, $item_id );

		echo '<div class="cfmb-menu-item-fields description-wide">';

		foreach ( $groups as $group ) {
			$this->renderer->render( $group, $ref, 'plain', Form_Saver::MENU_ITEMS_INPUT . '[' . $item_id . ']' );
		}

		echo '</div>';
	}

	/**
	 * Load the fields bundle on the widgets screen when any widget has fields.
	 * Works for both the classic screen and the block-based one.
	 *
	 * @return void
	 */
	public function prepare_widget_screen(): void {
		global $wp_widget_factory;

		if ( ! $wp_widget_factory instanceof \WP_Widget_Factory ) {
			return;
		}

		$groups = array();

		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( $widget instanceof WP_Widget ) {
				foreach ( $this->matcher->groups_for( Context::for_widget( (string) $widget->id_base ) ) as $group ) {
					$groups[ $group->key() ] = $group;
				}
			}
		}

		$this->renderer->enqueue( array_values( $groups ) );
	}

	/**
	 * Fields at the end of a classic widget's form. The input lives inside the
	 * widget's own form data, so the Legacy Widget block sends it too.
	 *
	 * @param WP_Widget            $widget   Widget.
	 * @param null|string          $return   Whether the form has fields of its own. Unused.
	 * @param array<string, mixed> $instance Widget settings. Unused.
	 * @return void
	 */
	public function render_widget( $widget, $return = null, $instance = array() ): void {
		if ( ! $widget instanceof WP_Widget ) {
			return;
		}

		$groups = $this->matcher->groups_for( Context::for_widget( (string) $widget->id_base, (string) $widget->id ) );

		if ( ! $groups ) {
			return;
		}

		// The Customizer previews widget changes before publishing them, but CFMB values
		// are saved straight away, so editing them there would skip the preview.
		if ( is_customize_preview() ) {
			$this->customizer_note( admin_url( 'widgets.php' ), __( 'This widget has more fields. Edit them on the Widgets screen.', 'cfmb' ) );
			return;
		}

		echo '<div class="cfmb-widget-fields">';

		if ( ! Form_Saver::is_saved_widget( $widget ) ) {
			printf( '<p class="cfmb-widget-fields__note description">%s</p>', esc_html__( 'Save the widget first, then its extra fields appear here.', 'cfmb' ) );
			echo '</div>';
			return;
		}

		$ref = new Object_Ref( Object_Ref::WIDGET, 0, (string) $widget->id );

		foreach ( $groups as $group ) {
			printf( '<h4 class="cfmb-widget-fields__title">%s</h4>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain', $widget->get_field_name( Form_Saver::WIDGET_INPUT ), false );
		}

		echo '</div>';
	}

	/**
	 * Note in the Customizer that points to the screen where fields are edited.
	 *
	 * @param string $url     Screen URL.
	 * @param string $message Message.
	 * @return void
	 */
	private function customizer_note( string $url, string $message ): void {
		printf(
			'<p class="cfmb-customizer-note description">%1$s <a href="%2$s" target="_top">%3$s</a></p>',
			esc_html( $message ),
			esc_url( $url ),
			esc_html__( 'Open', 'cfmb' )
		);
	}

	/**
	 * Note inside menu items in the Customizer, which has no hook for
	 * real fields. Only shown when some group targets menu items.
	 *
	 * @return void
	 */
	public function customizer_menu_item_note(): void {
		if ( ! $this->matcher->groups_for( Context::for_menu_item( 0 ) ) ) {
			return;
		}

		$this->customizer_note( admin_url( 'nav-menus.php' ), __( 'Menu items have more fields. Edit them on the Menus screen.', 'cfmb' ) );
	}

	/**
	 * Decide what to show on the add-term and edit-term screens.
	 *
	 * @return void
	 */
	public function prepare_term_screen(): void {
		// Read-only screen context; saving is nonce-checked in Form_Saver.
		$taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term_id  = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$this->groups = $this->matcher->groups_for( Context::for_term( $taxonomy, $term_id ) );

		if ( ! $this->groups ) {
			return;
		}

		$this->renderer->enqueue( $this->groups );

		add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add_term' ) );
		add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit_term' ) );
	}

	/**
	 * Groups on the add-term form.
	 *
	 * @return void
	 */
	public function render_add_term(): void {
		$ref = new Object_Ref( Object_Ref::TERM, 0 );

		foreach ( $this->groups as $group ) {
			echo '<div class="form-field cfmb-term-group">';
			printf( '<h2 class="cfmb-term-group__title">%s</h2>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain' );
			echo '</div>';
		}
	}

	/**
	 * Groups on the edit-term form, which is a table.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public function render_edit_term( $term ): void {
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::TERM, (int) $term->term_id );

		foreach ( $this->groups as $group ) {
			echo '<tr class="cfmb-term-group"><td colspan="2">';
			printf( '<h2 class="cfmb-term-group__title">%s</h2>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain' );
			echo '</td></tr>';
		}
	}

	/**
	 * Decide what to show on profile, edit-user and add-user screens.
	 *
	 * @return void
	 */
	public function prepare_user_screen(): void {
		global $pagenow;

		if ( 'user-new.php' === $pagenow ) {
			$user_id = 0;
			$form    = 'add';
		} elseif ( 'user-edit.php' === $pagenow ) {
			$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.
			$form    = 'edit';
		} else {
			$user_id = get_current_user_id();
			$form    = 'edit';
		}

		$this->groups = $this->matcher->groups_for( Context::for_user( $user_id, $form ) );

		if ( ! $this->groups ) {
			return;
		}

		$this->renderer->enqueue( $this->groups );

		if ( 'add' === $form ) {
			add_action( 'user_new_form', array( $this, 'render_add_user' ) );
			return;
		}

		add_action( 'show_user_profile', array( $this, 'render_edit_user' ) );
		add_action( 'edit_user_profile', array( $this, 'render_edit_user' ) );
	}

	/**
	 * Groups on the add-user form.
	 *
	 * @param string $form_type Core form type; only "add-new-user" is used.
	 * @return void
	 */
	public function render_add_user( $form_type = 'add-new-user' ): void {
		if ( 'add-new-user' !== $form_type ) {
			return;
		}

		$this->render_user_groups( new Object_Ref( Object_Ref::USER, 0 ) );
	}

	/**
	 * Groups on the profile and edit-user forms.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function render_edit_user( $user ): void {
		if ( $user instanceof WP_User ) {
			$this->render_user_groups( new Object_Ref( Object_Ref::USER, (int) $user->ID ) );
		}
	}

	/**
	 * Print user groups with section headings, like core profile sections.
	 *
	 * @param Object_Ref $ref User reference.
	 * @return void
	 */
	private function render_user_groups( Object_Ref $ref ): void {
		foreach ( $this->groups as $group ) {
			echo '<div class="cfmb-user-group">';
			printf( '<h2>%s</h2>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain' );
			echo '</div>';
		}
	}
}
