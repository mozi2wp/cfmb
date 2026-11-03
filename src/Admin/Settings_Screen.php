<?php
/**
 * CFMB settings screen.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin-wide settings, stored in the cfmb_settings option.
 */
class Settings_Screen {

	/**
	 * Icon per settings section.
	 */
	private const SECTION_ICONS = array(
		'cfmb_fields'    => 'more-fields',
		'cfmb_display'   => 'display',
		'cfmb_sync'      => 'sync',
		'cfmb_switching' => 'acf',
		'cfmb_data'      => 'data',
	);

	public const SLUG   = 'cfmb-settings';
	public const OPTION = 'cfmb_settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::SLUG, array( $this, 'capability' ) );
	}

	/**
	 * Capability for saving through options.php; follows cfmb/admin/capability.
	 *
	 * @return string
	 */
	public function capability(): string {
		return cfmb_admin_capability();
	}

	/**
	 * Register the option and its fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SLUG,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'cfmb_fields', __( 'Fields', 'custom-fields-meta-box' ), '__return_false', self::SLUG );

		add_settings_field(
			'map_provider',
			__( 'Map fields', 'custom-fields-meta-box' ),
			array( $this, 'render_map_provider_field' ),
			self::SLUG,
			'cfmb_fields'
		);

		add_settings_field(
			'map_google_key',
			__( 'Google Maps API key', 'custom-fields-meta-box' ),
			array( $this, 'render_google_key_field' ),
			self::SLUG,
			'cfmb_fields',
			array( 'label_for' => 'cfmb-google-key' )
		);

		add_settings_field(
			'map_google_map_id',
			__( 'Google Map ID', 'custom-fields-meta-box' ),
			array( $this, 'render_google_map_id_field' ),
			self::SLUG,
			'cfmb_fields',
			array( 'label_for' => 'cfmb-google-map-id' )
		);

		add_settings_field(
			'allow_svg_uploads',
			__( 'SVG uploads', 'custom-fields-meta-box' ),
			array( $this, 'render_svg_field' ),
			self::SLUG,
			'cfmb_fields',
			array( 'label_for' => 'cfmb-allow-svg' )
		);

		add_settings_section( 'cfmb_display', __( 'Display', 'custom-fields-meta-box' ), '__return_false', self::SLUG );

		add_settings_field(
			'shortcode_enabled',
			__( 'Shortcode', 'custom-fields-meta-box' ),
			array( $this, 'render_shortcode_field' ),
			self::SLUG,
			'cfmb_display',
			array( 'label_for' => 'cfmb-shortcode' )
		);

		add_settings_section( 'cfmb_sync', __( 'Schema Sync', 'custom-fields-meta-box' ), array( $this, 'render_sync_intro' ), self::SLUG );

		add_settings_field(
			'sync_auto_write',
			__( 'Sync files', 'custom-fields-meta-box' ),
			array( $this, 'render_sync_field' ),
			self::SLUG,
			'cfmb_sync',
			array( 'label_for' => 'cfmb-sync-auto' )
		);

		add_settings_section( 'cfmb_switching', __( 'Coming from ACF', 'custom-fields-meta-box' ), array( $this, 'render_switching_intro' ), self::SLUG );

		add_settings_section( 'cfmb_data', __( 'Data', 'custom-fields-meta-box' ), '__return_false', self::SLUG );

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'When CFMB is deleted', 'custom-fields-meta-box' ),
			array( $this, 'render_uninstall_field' ),
			self::SLUG,
			'cfmb_data',
			array( 'label_for' => 'cfmb-delete-data' )
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'map_provider'             => 'none',
			'map_google_key'           => '',
			'map_google_map_id'        => '',
			'allow_svg_uploads'        => false,
			'sync_auto_write'          => true,
			'shortcode_enabled'        => true,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Sanitize the option.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$provider = (string) ( $input['map_provider'] ?? 'none' );

		return array(
			'map_provider'             => in_array( $provider, array( 'none', 'osm', 'google' ), true ) ? $provider : 'none',
			'map_google_key'           => (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input['map_google_key'] ?? '' ) ),
			'map_google_map_id'        => (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input['map_google_map_id'] ?? '' ) ),
			'allow_svg_uploads'        => ! empty( $input['allow_svg_uploads'] ),
			'sync_auto_write'          => ! empty( $input['sync_auto_write'] ),
			'shortcode_enabled'        => ! empty( $input['shortcode_enabled'] ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		);
	}

	/**
	 * Intro for the ACF section.
	 *
	 * @return void
	 */
	public function render_switching_intro(): void {
		printf(
			'<p>%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'The Switch Assistant brings field groups, post types, taxonomies, options pages and values over from ACF or Secure Custom Fields.', 'custom-fields-meta-box' ),
			esc_url( admin_url( 'admin.php?page=cfmb-switch' ) ),
			esc_html__( 'Open the Switch Assistant', 'custom-fields-meta-box' )
		);

		printf(
			'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Themes built for ACF can keep their get_field() and have_rows() calls with the separate CFMB Compatibility Mode plugin.', 'custom-fields-meta-box' ),
			esc_url( admin_url( 'plugin-install.php?s=cfmb-compatibility-mode&tab=search&type=term' ) ),
			esc_html__( 'Find it in Plugins', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Map provider choice. Nothing is loaded from a map service until one is picked.
	 *
	 * @return void
	 */
	public function render_map_provider_field(): void {
		$current = (string) self::current()['map_provider'];
		$choices = array(
			'none'   => __( 'No map. Editors type the address and coordinates.', 'custom-fields-meta-box' ),
			'osm'    => __( 'OpenStreetMap. Map tiles and address search load from openstreetmap.org.', 'custom-fields-meta-box' ),
			'google' => __( 'Google Maps. Loads from googleapis.com with your API key.', 'custom-fields-meta-box' ),
		);

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Map fields', 'custom-fields-meta-box' ) . '</legend>';

		foreach ( $choices as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[map_provider]" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}

		printf(
			'<p class="description">%s</p></fieldset>',
			esc_html__( 'Choosing a map service means editors\' browsers contact it when a map field is shown. Each map field can also pick its own service.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Google Maps key.
	 *
	 * @return void
	 */
	public function render_google_key_field(): void {
		printf(
			'<input type="text" class="regular-text code" id="cfmb-google-key" name="%1$s[map_google_key]" value="%2$s" autocomplete="off" />',
			esc_attr( self::OPTION ),
			esc_attr( (string) self::current()['map_google_key'] )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Needs the Maps JavaScript API and Geocoding API turned on. Restrict the key to your site\'s address in the Google Cloud console.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Google Map ID, for Google's current marker API.
	 *
	 * @return void
	 */
	public function render_google_map_id_field(): void {
		printf(
			'<input type="text" class="regular-text code" id="cfmb-google-map-id" name="%1$s[map_google_map_id]" value="%2$s" autocomplete="off" />',
			esc_attr( self::OPTION ),
			esc_attr( (string) self::current()['map_google_map_id'] )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Optional. With a Map ID from the Google Cloud console, map fields use Google\'s current pin (Advanced Markers). Without one they use the older pin, which Google has deprecated but still supports.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * SVG uploads checkbox.
	 *
	 * @return void
	 */
	public function render_svg_field(): void {
		printf(
			'<label><input type="checkbox" id="cfmb-allow-svg" name="%1$s[allow_svg_uploads]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( self::current()['allow_svg_uploads'] ), true, false ),
			esc_html__( 'Allow SVG files in the media library', 'custom-fields-meta-box' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Off by default. Every uploaded SVG is cleaned first: scripts, event handlers and outside references are removed. Only users who can upload files can add them.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Shortcode checkbox.
	 *
	 * @return void
	 */
	public function render_shortcode_field(): void {
		printf(
			'<label><input type="checkbox" id="cfmb-shortcode" name="%1$s[shortcode_enabled]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( self::current()['shortcode_enabled'] ), true, false ),
			esc_html__( 'Allow the [cfmb] shortcode', 'custom-fields-meta-box' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'On by default. [cfmb field="price"] shows a field value in content. It only shows values visitors may already see: no drafts, private posts, password fields, or other people\'s profile fields. Turn it off if authors should not print field values in their content.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Where Schema Sync files go.
	 *
	 * @return void
	 */
	public function render_sync_intro(): void {
		$files = cfmb()->get( \CFMB\Sync\Sync_Files::class );

		printf(
			'<p>%s</p>',
			esc_html__( 'Field groups, post types, taxonomies, options pages and Connections are also saved as JSON files, so you can keep them in version control and move them between sites.', 'custom-fields-meta-box' )
		);
		printf(
			'<p>%1$s <code>%2$s</code></p>',
			esc_html__( 'Files are saved to:', 'custom-fields-meta-box' ),
			esc_html( $files->save_dir() )
		);
		printf(
			'<p>%s</p>',
			esc_html__( 'They are also read from a cfmb-json folder in your theme or child theme, if there is one. Review changes under CFMB > Sync.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Automatic file writing checkbox.
	 *
	 * @return void
	 */
	public function render_sync_field(): void {
		printf(
			'<label><input type="checkbox" id="cfmb-sync-auto" name="%1$s[sync_auto_write]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( self::current()['sync_auto_write'] ), true, false ),
			esc_html__( 'Write the file each time something is saved', 'custom-fields-meta-box' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'On by default. A file that was changed outside CFMB is never overwritten; it shows under CFMB > Sync for review. When off, write files from CFMB > Sync or with wp cfmb sync push.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Uninstall checkbox.
	 *
	 * @return void
	 */
	public function render_uninstall_field(): void {
		$settings = self::current();

		printf(
			'<label><input type="checkbox" id="cfmb-delete-data" name="%1$s[delete_data_on_uninstall]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( $settings['delete_data_on_uninstall'] ), true, false ),
			esc_html__( 'Delete field groups and CFMB settings', 'custom-fields-meta-box' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Off by default. Values saved on your posts, terms and users are always kept, so your content stays intact if you reinstall.', 'custom-fields-meta-box' )
		);
	}

	/**
	 * Print the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( cfmb_admin_capability() ) ) {
			return;
		}

		echo '<div class="wrap cfmb-screen cfmb-settings">';
		echo '<div class="cfmb-screen__header">';
		printf( '<span class="cfmb-screen__mark">%s</span>', Ui_Icons::svg( 'settings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui_Icons::svg() runs wp_kses().
		printf( '<h1>%s</h1>', esc_html__( 'CFMB Settings', 'custom-fields-meta-box' ) );
		echo '</div>';
		echo '<hr class="wp-header-end">';
		echo '<form method="post" action="options.php">';
		settings_fields( self::SLUG );
		$this->render_sections();
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Print each settings section as a card. Same output as
	 * do_settings_sections(), with an icon and a wrapper per section.
	 *
	 * @return void
	 */
	private function render_sections(): void {
		global $wp_settings_sections, $wp_settings_fields;

		foreach ( (array) ( $wp_settings_sections[ self::SLUG ] ?? array() ) as $section ) {
			$id = (string) $section['id'];

			printf( '<section class="cfmb-card" aria-labelledby="%s-title">', esc_attr( $id ) );
			printf(
				'<h2 class="cfmb-card__title" id="%1$s-title"><span class="cfmb-icon">%2$s</span>%3$s</h2>',
				esc_attr( $id ),
				Ui_Icons::svg( self::SECTION_ICONS[ $id ] ?? 'settings', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui_Icons::svg() runs wp_kses().
				esc_html( (string) $section['title'] )
			);

			if ( is_callable( $section['callback'] ) ) {
				call_user_func( $section['callback'], $section );
			}

			if ( ! empty( $wp_settings_fields[ self::SLUG ][ $id ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( self::SLUG, $id );
				echo '</table>';
			}

			echo '</section>';
		}
	}
}
