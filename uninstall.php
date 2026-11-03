<?php
/**
 * Uninstall cleanup.
 *
 * Runs only when the user deletes the plugin. Field groups, post type,
 * taxonomy, options page and Connection definitions, Switch Assistant
 * history and backups, and settings are removed only if "Delete field
 * groups and CFMB settings" is on. Values on posts, terms, users and
 * options pages, Connection links, posts and terms of CFMB post types and
 * taxonomies, and Schema Sync files are never removed here: they are the
 * site's content, not plugin configuration.
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cfmb_settings = get_option( 'cfmb_settings', array() );

if ( ! is_array( $cfmb_settings ) || empty( $cfmb_settings['delete_data_on_uninstall'] ) ) {
	return;
}

$cfmb_group_ids = get_posts(
	array(
		'post_type'   => array( 'cfmb_group', 'cfmb_post_type', 'cfmb_taxonomy', 'cfmb_options_page', 'cfmb_connection' ),
		'post_status' => array( 'publish', 'draft', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $cfmb_group_ids as $cfmb_group_id ) {
	wp_delete_post( (int) $cfmb_group_id, true );
}

delete_option( 'cfmb_settings' );
delete_option( 'cfmb_db_version' );
delete_option( 'cfmb_flush_rewrite_rules' );
delete_option( 'cfmb_switch_runs' );
delete_option( 'cfmb_switch_option_ids' );
delete_option( 'cfmb_compat_switched_off' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfmb_switch_backup" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removes the plugin's own backup table on opt-in uninstall.
