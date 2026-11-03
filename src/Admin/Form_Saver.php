<?php
/**
 * Saves submitted field values from edit screens.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Content\Options_Pages;
use CFMB\Location\Conditions;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Handles cfmb_values[group_key] = JSON submissions on every screen.
 * Menu items post cfmb_menu_items[item_id][group_key]; widgets post inside
 * the widget's own form data, so they also work in the Legacy Widget block.
 *
 * A group is only saved if it matches the object being saved, so a crafted
 * request can't write fields from unrelated groups.
 */
class Form_Saver {

	public const NONCE_ACTION = 'cfmb_save_fields';
	public const NONCE_NAME   = '_cfmb_nonce';

	/**
	 * Posted array for menu items: cfmb_menu_items[item_id][group_key].
	 */
	public const MENU_ITEMS_INPUT = 'cfmb_menu_items';

	/**
	 * Key inside a widget's form data: widget-{id_base}[n][cfmb_values][group_key].
	 */
	public const WIDGET_INPUT = 'cfmb_values';

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Registered options pages.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Constructor.
	 *
	 * @param Matcher       $matcher Location matcher.
	 * @param Field_Values  $values  Value service.
	 * @param Options_Pages $pages   Options pages.
	 */
	public function __construct( Matcher $matcher, Field_Values $values, Options_Pages $pages ) {
		$this->matcher = $matcher;
		$this->values  = $values;
		$this->pages   = $pages;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'created_term', array( $this, 'save_term' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'save_term' ), 10, 3 );
		add_action( 'personal_options_update', array( $this, 'save_user' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user' ) );
		add_action( 'user_register', array( $this, 'save_user' ) );
		add_action( 'admin_post_cfmb_save_options', array( $this, 'save_options' ) );
		add_action( 'edit_attachment', array( $this, 'save_attachment' ) );
		add_action( 'edit_comment', array( $this, 'save_comment' ) );
		add_action( 'wp_update_nav_menu', array( $this, 'save_menu' ) );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_item' ), 10, 2 );
		add_filter( 'widget_update_callback', array( $this, 'save_widget' ), 10, 4 );
	}

	/**
	 * Save post fields. In the block editor this runs on the meta box request
	 * that follows the REST save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function save_post( $post_id, $post ): void {
		if ( ! $this->has_submission() || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Other code may insert posts while this request runs; only save the post the form was for.
		$form_post = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().
		if ( $form_post !== (int) $post_id || ! $this->can_save( current_user_can( 'edit_post', $post_id ) ) ) {
			return;
		}

		// Drafts are work in progress; required fields are enforced once the post goes live.
		$enforce = ! in_array( get_post_status( $post_id ), array( 'draft', 'auto-draft', 'pending' ), true );
		$ref     = new Object_Ref( Object_Ref::POST, (int) $post_id );

		Save_Errors::remember( $ref, $this->save( $ref, Context::for_post( (int) $post_id, $post->post_type ), $enforce ) );
	}

	/**
	 * Save term fields.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public function save_term( $term_id, $tt_id, $taxonomy ): void {
		if ( ! $this->has_submission() || ! $this->can_save( current_user_can( 'edit_term', $term_id ) ) ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::TERM, (int) $term_id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_term( (string) $taxonomy, (int) $term_id ) ) );
	}

	/**
	 * Save user fields.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_user( $user_id ): void {
		if ( ! $this->has_submission() || ! $this->can_save( current_user_can( 'edit_user', $user_id ) ) ) {
			return;
		}

		$form = 'user_register' === current_action() ? 'add' : 'edit';
		$ref  = new Object_Ref( Object_Ref::USER, (int) $user_id );

		Save_Errors::remember( $ref, $this->save( $ref, Context::for_user( (int) $user_id, $form ) ) );
	}

	/**
	 * Save attachment fields from the attachment edit screen. Attachments
	 * never fire save_post.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function save_attachment( $post_id ): void {
		$form_post = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().

		if ( ! $this->has_submission() || $form_post !== (int) $post_id || ! $this->can_save( current_user_can( 'edit_post', $post_id ) ) ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, (int) $post_id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_attachment( (int) $post_id ) ) );
	}

	/**
	 * Save comment fields from the edit comment screen.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function save_comment( $comment_id ): void {
		if ( ! $this->has_submission() || ! $this->can_save( current_user_can( 'edit_comment', $comment_id ) ) ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::COMMENT, (int) $comment_id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_comment( (int) $comment_id ) ) );
	}

	/**
	 * Save menu fields from Appearance > Menus.
	 *
	 * @param int $menu_id Menu term ID.
	 * @return void
	 */
	public function save_menu( $menu_id ): void {
		if ( ! $this->has_submission() || ! $this->can_save( current_user_can( 'edit_theme_options' ) ) ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::TERM, (int) $menu_id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_menu( (int) $menu_id ) ) );
	}

	/**
	 * Save one menu item's fields. Core calls this once per item when the menu is saved.
	 *
	 * @param int $menu_id Menu term ID.
	 * @param int $item_id Menu item post ID.
	 * @return void
	 */
	public function save_menu_item( $menu_id, $item_id ): void {
		// Each entry is a JSON string per group; values are sanitized per field in save().
		$posted = isset( $_POST[ self::MENU_ITEMS_INPUT ][ $item_id ] ) && is_array( $_POST[ self::MENU_ITEMS_INPUT ][ $item_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().
			? wp_unslash( $_POST[ self::MENU_ITEMS_INPUT ][ $item_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: null;

		if ( null === $posted || ! $this->can_save( current_user_can( 'edit_theme_options' ) ) ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, (int) $item_id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_menu_item( (int) $item_id, (int) $menu_id ), true, self::decode_groups( $posted ) ) );
	}

	/**
	 * Save widget fields. Values travel inside the widget's own form data, so
	 * core has already checked its nonce, on the classic screen and in the
	 * Legacy Widget block alike. The fields are taken out of the instance so
	 * they never end up in the widget's own settings.
	 *
	 * @param array<string, mixed>|false $instance     Instance the widget will save.
	 * @param array<string, mixed>       $new_instance Submitted instance.
	 * @param array<string, mixed>       $old_instance Previous instance.
	 * @param WP_Widget                  $widget       Widget.
	 * @return array<string, mixed>|false
	 */
	public function save_widget( $instance, $new_instance, $old_instance, $widget ) {
		if ( is_array( $instance ) ) {
			unset( $instance[ self::WIDGET_INPUT ] );
		}

		$posted = is_array( $new_instance ) && isset( $new_instance[ self::WIDGET_INPUT ] ) && is_array( $new_instance[ self::WIDGET_INPUT ] )
			? $new_instance[ self::WIDGET_INPUT ]
			: null;

		if ( null === $posted || ! $widget instanceof WP_Widget || ! self::is_saved_widget( $widget ) || ! current_user_can( 'edit_theme_options' ) ) {
			return $instance;
		}

		$ref = new Object_Ref( Object_Ref::WIDGET, 0, (string) $widget->id );
		Save_Errors::remember( $ref, $this->save( $ref, Context::for_widget( (string) $widget->id_base, (string) $widget->id ), true, self::decode_groups( $posted ) ) );

		return $instance;
	}

	/**
	 * Whether a widget instance has a real number. The classic template
	 * (__i__) and blocks not saved yet (-1) have none.
	 *
	 * @param WP_Widget $widget Widget.
	 * @return bool
	 */
	public static function is_saved_widget( WP_Widget $widget ): bool {
		return is_numeric( $widget->number ) && (int) $widget->number > 0;
	}

	/**
	 * Save an options page and go back to it.
	 *
	 * @return void
	 */
	public function save_options(): void {
		$id   = isset( $_POST['cfmb_options_page'] ) ? sanitize_key( wp_unslash( $_POST['cfmb_options_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in can_save().
		$page = $this->pages->get( $id );

		if ( ! $page || ! $this->can_save( current_user_can( $page['capability'] ) ) ) {
			wp_die( esc_html__( 'You are not allowed to change these options, or the page expired. Go back, reload and try again.', 'custom-fields-meta-box' ), 403 );
		}

		$ref    = new Object_Ref( Object_Ref::OPTION, 0, $id );
		$errors = $this->save( $ref, Context::for_options_page( $id ) );

		Save_Errors::remember( $ref, $errors );

		$args = $errors ? array( 'cfmb-errors' => 1 ) : array( 'cfmb-updated' => 1 );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page['menu_slug'] ) ) );
		exit;
	}

	/**
	 * Save every submitted group that matches the context.
	 *
	 * Values are sanitized first, so conditional logic is evaluated on what
	 * will actually be stored. Hidden fields keep their values (switching a
	 * condition back must not lose data) but skip the required check.
	 *
	 * @param Object_Ref                               $ref              Object being saved.
	 * @param Context                                  $context          Its context.
	 * @param bool                                     $enforce_required Whether required fields must have a value.
	 * @param array<string, array<string, mixed>>|null $submissions      Decoded values by group key; null reads cfmb_values from the request.
	 * @return array<string, string> Error messages keyed by field key, or by path
	 *                               (fld_a.2.fld_b) for fields inside rows.
	 */
	public function save( Object_Ref $ref, Context $context, bool $enforce_required = true, ?array $submissions = null ): array {
		$errors = array();

		foreach ( $submissions ?? $this->submitted_groups() as $group_key => $submitted ) {
			$group = $this->matcher->matching_group( $group_key, $context );

			if ( ! $group ) {
				continue;
			}

			$clean  = array();
			$locked = array();
			foreach ( $group->fields() as $field ) {
				$type = $this->values->type_of( $field );

				if ( ! $type || ! $type->stores_value() ) {
					continue;
				}

				/** This filter is documented in src/Admin/Group_Renderer.php */
				if ( ! apply_filters( 'cfmb/field/can_edit', true, $field, $ref, 'save' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
					$locked[ $field->key() ] = true;
				}

				$clean[ $field->key() ] = array_key_exists( $field->key(), $submitted ) && ! isset( $locked[ $field->key() ] )
					? $this->values->sanitize( $field, $submitted[ $field->key() ] )
					: $this->values->load( $field, $group, $ref, false );
			}

			foreach ( $group->fields() as $field ) {
				if ( ! array_key_exists( $field->key(), $submitted ) || ! array_key_exists( $field->key(), $clean ) || isset( $locked[ $field->key() ] ) ) {
					continue;
				}

				$check_required = $enforce_required && Conditions::visible( $field, $clean );
				$tree           = $this->values->validate_tree( $field, $clean[ $field->key() ], $check_required, $ref );

				// One bad value inside a Repeater keeps the whole field as it was, like any invalid value.
				if ( $tree ) {
					foreach ( $tree as $path => $error ) {
						$errors[ '' === $path ? $field->key() : $field->key() . '.' . $path ] = $error;
					}
					continue;
				}

				$this->values->store( $field, $group, $ref, $clean[ $field->key() ] );
			}
		}

		/**
		 * Fires after CFMB saved submitted values for an object.
		 *
		 * @param Object_Ref            $ref    Object.
		 * @param array<string, string> $errors Fields that were not saved, keyed by field key or path.
		 */
		do_action( 'cfmb/save/after', $ref, $errors ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $errors;
	}

	/**
	 * Decoded submissions keyed by group key. Groups whose input is empty
	 * (the script never ran) are skipped so nothing is overwritten.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function submitted_groups(): array {
		// Each entry is a JSON string; every value inside is sanitized per field in save().
		$raw = isset( $_POST['cfmb_values'] ) && is_array( $_POST['cfmb_values'] ) ? wp_unslash( $_POST['cfmb_values'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return self::decode_groups( $raw );
	}

	/**
	 * Decode posted JSON strings keyed by group key. Empty strings are skipped.
	 *
	 * @param array<mixed> $raw Posted strings.
	 * @return array<string, array<string, mixed>>
	 */
	public static function decode_groups( array $raw ): array {
		$out = array();

		foreach ( $raw as $group_key => $json ) {
			if ( ! is_string( $json ) || '' === $json ) {
				continue;
			}

			$decoded = json_decode( $json, true );

			if ( is_array( $decoded ) ) {
				$out[ sanitize_key( (string) $group_key ) ] = $decoded;
			}
		}

		return $out;
	}

	/**
	 * Whether the request carries CFMB values.
	 *
	 * @return bool
	 */
	private function has_submission(): bool {
		return isset( $_POST['cfmb_values'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only.
	}

	/**
	 * Nonce plus capability.
	 *
	 * @param bool $has_capability Result of the capability check for this object.
	 * @return bool
	 */
	private function can_save( bool $has_capability ): bool {
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		return $has_capability && false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}
}
