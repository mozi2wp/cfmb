<?php
/**
 * WPML and Polylang support.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

use CFMB\Integrations\Field_Integrations;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Each field has a translation mode (see Field_Integrations):
 *
 * - translate: every language has its own value. WPML lists it for
 *   translators; Polylang fills a new translation with the source value once.
 * - copy: the value is copied to every translation and kept in sync.
 * - copy_once: copied when a translation is created, then edited separately.
 * - ignore: never copied.
 *
 * IDs of images, linked posts and terms are swapped for their translations
 * when copied. Options page values in translate and copy_once mode are kept
 * per language; the default language uses the usual option name, so sites
 * that add a language later lose nothing.
 */
class Translation {

	/**
	 * How long WPML's list of row keys is kept, in seconds.
	 */
	private const ROW_KEYS_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Transient with row keys found in the database.
	 */
	public const ROW_KEYS_TRANSIENT = 'cfmb_translation_row_keys';

	/**
	 * Most row keys listed per field shape, to keep WPML's config small.
	 */
	private const ROW_KEYS_LIMIT = 2000;

	/**
	 * Key map.
	 *
	 * @var Field_Keys
	 */
	private Field_Keys $keys;

	/**
	 * ID mapping.
	 *
	 * @var Id_Mapper
	 */
	private Id_Mapper $ids;

	/**
	 * WPML.
	 *
	 * @var Wpml_Plugin
	 */
	private Wpml_Plugin $wpml;

	/**
	 * Polylang.
	 *
	 * @var Polylang_Plugin
	 */
	private Polylang_Plugin $polylang;

	/**
	 * Constructor.
	 *
	 * @param Field_Keys           $keys     Key map.
	 * @param Id_Mapper            $ids      ID mapping.
	 * @param Wpml_Plugin|null     $wpml     WPML adapter.
	 * @param Polylang_Plugin|null $polylang Polylang adapter.
	 */
	public function __construct( Field_Keys $keys, Id_Mapper $ids, ?Wpml_Plugin $wpml = null, ?Polylang_Plugin $polylang = null ) {
		$this->keys     = $keys;
		$this->ids      = $ids;
		$this->wpml     = $wpml ? $wpml : new Wpml_Plugin();
		$this->polylang = $polylang ? $polylang : new Polylang_Plugin();
	}

	/**
	 * Register hooks. Callbacks do nothing unless their plugin is active,
	 * so they can be added before WPML or Polylang loads.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wpml_config_array', array( $this, 'wpml_config' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.
		add_filter( 'wpml_sync_custom_field_copied_value', array( $this, 'wpml_copied_value' ), 10, 4 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's hook.

		add_filter( 'pll_copy_post_metas', array( $this, 'polylang_post_metas' ), 10, 5 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Polylang's hook.
		add_filter( 'pll_copy_term_metas', array( $this, 'polylang_term_metas' ), 10, 5 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Polylang's hook.
		add_filter( 'pll_translate_post_meta', array( $this, 'polylang_translate_value' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Polylang's hook.
		add_filter( 'pll_translate_term_meta', array( $this, 'polylang_translate_value' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Polylang's hook.

		add_filter( 'cfmb/storage/option_language', array( $this, 'option_language' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		add_action( 'cfmb/save/after', array( $this, 'forget_row_keys' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_action( 'save_post_' . \CFMB\Schema\Group_Repository::POST_TYPE, array( $this, 'forget_row_keys' ) );
	}

	/**
	 * The multilingual plugin in use, or null.
	 *
	 * @return Language_Plugin|null
	 */
	public function plugin(): ?Language_Plugin {
		if ( $this->wpml->active() ) {
			return $this->wpml;
		}

		return $this->polylang->active() ? $this->polylang : null;
	}

	/**
	 * Whether a multilingual plugin is active, for the builder.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'pll_get_post' ) || defined( 'POLYLANG_VERSION' );
	}

	/**
	 * Add CFMB keys to WPML's language configuration, as if they were in a
	 * wpml-config.xml file. WPML has no wildcards, so row keys
	 * (items_0_title) are listed as found in the database.
	 *
	 * @param mixed $config Parsed configuration.
	 * @return mixed
	 */
	public function wpml_config( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}

		$post_keys = array();
		$term_keys = array();

		foreach ( $this->keys->entries() as $entry ) {
			if ( $entry['exact'] ) {
				$post_keys[ $entry['root'] ] = $entry['mode'];
				$term_keys[ $entry['root'] ] = $entry['mode'];
			}
		}

		foreach ( $this->row_keys() as $table => $keys ) {
			foreach ( $keys as $key ) {
				$mode = $this->keys->mode( $key );

				if ( '' === $mode ) {
					continue;
				}

				if ( 'term' === $table ) {
					$term_keys[ $key ] = $mode;
				} else {
					$post_keys[ $key ] = $mode;
				}
			}
		}

		$config['wpml-config'] = is_array( $config['wpml-config'] ?? null ) ? $config['wpml-config'] : array();
		$config['wpml-config'] = $this->wpml_section( $config['wpml-config'], 'custom-fields', 'custom-field', $post_keys );
		$config['wpml-config'] = $this->wpml_section( $config['wpml-config'], 'custom-term-fields', 'custom-term-field', $term_keys );

		return $config;
	}

	/**
	 * Append entries to one section of WPML's configuration.
	 *
	 * @param array<string, mixed>  $root    The wpml-config array.
	 * @param string                $section Section, e.g. custom-fields.
	 * @param string                $item    Item tag, e.g. custom-field.
	 * @param array<string, string> $keys   Mode by key.
	 * @return array<string, mixed>
	 */
	private function wpml_section( array $root, string $section, string $item, array $keys ): array {
		if ( ! $keys ) {
			return $root;
		}

		$block = is_array( $root[ $section ] ?? null ) ? $root[ $section ] : array();
		$items = $block[ $item ] ?? array();

		// One item parsed from XML arrives unwrapped.
		if ( is_array( $items ) && isset( $items['value'] ) ) {
			$items = array( $items );
		}

		$items = is_array( $items ) ? $items : array();
		$taken = array();

		foreach ( $items as $existing ) {
			if ( is_array( $existing ) && isset( $existing['value'] ) ) {
				$taken[ (string) $existing['value'] ] = true;
			}
		}

		$actions = array(
			Field_Integrations::TRANSLATE => 'translate',
			Field_Integrations::COPY      => 'copy',
			Field_Integrations::COPY_ONCE => 'copy-once',
			Field_Integrations::IGNORE    => 'ignore',
		);

		foreach ( $keys as $key => $mode ) {
			// A theme or another plugin's own setting for the key wins.
			if ( isset( $taken[ $key ] ) ) {
				continue;
			}

			$items[] = array(
				'value' => (string) $key,
				'attr'  => array( 'action' => $actions[ $mode ] ?? 'copy' ),
			);
		}

		$block[ $item ]   = $items;
		$root[ $section ] = $block;

		return $root;
	}

	/**
	 * Row keys stored in post and term meta, found by the literal start of
	 * each row key shape. Cached, and forgotten when values or groups change.
	 *
	 * @return array{post: string[], term: string[]}
	 */
	public function row_keys(): array {
		$cached = get_transient( self::ROW_KEYS_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['post'], $cached['term'] ) ) {
			return $cached;
		}

		global $wpdb;

		$roots = array();
		foreach ( $this->keys->entries() as $entry ) {
			if ( ! $entry['exact'] && '' !== $entry['root'] ) {
				$roots[ $entry['root'] ] = true;
			}
		}

		$found = array(
			'post' => array(),
			'term' => array(),
		);

		foreach ( array_keys( $roots ) as $root ) {
			$like = $wpdb->esc_like( $root ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached in a transient above.
			$posts = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s LIMIT %d", $like, self::ROW_KEYS_LIMIT ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached in a transient above.
			$terms = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->termmeta} WHERE meta_key LIKE %s LIMIT %d", $like, self::ROW_KEYS_LIMIT ) );

			$found['post'] = array_merge( $found['post'], array_map( 'strval', (array) $posts ) );
			$found['term'] = array_merge( $found['term'], array_map( 'strval', (array) $terms ) );
		}

		$found['post'] = array_values( array_unique( $found['post'] ) );
		$found['term'] = array_values( array_unique( $found['term'] ) );

		set_transient( self::ROW_KEYS_TRANSIENT, $found, self::ROW_KEYS_TTL );

		return $found;
	}

	/**
	 * Drop cached row keys so new rows reach WPML's settings.
	 *
	 * @return void
	 */
	public function forget_row_keys(): void {
		if ( $this->available() ) {
			delete_transient( self::ROW_KEYS_TRANSIENT );
		}

		$this->keys->reset();
	}

	/**
	 * WPML copies a value to a translation: point IDs at translated objects.
	 *
	 * @param mixed  $value    Value being copied.
	 * @param int    $from     Source post ID.
	 * @param int    $to       Target post ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public function wpml_copied_value( $value, $from = 0, $to = 0, $meta_key = '' ) {
		$found = $this->keys->find( (string) $meta_key );

		if ( ! $found || ! Id_Mapper::maps( $found['field'] ) || ! $this->wpml->active() ) {
			return $value;
		}

		$language = $this->wpml->post_language( (int) $to );

		return $this->ids->map( $found['field'], $value, $language, $this->wpml );
	}

	/**
	 * Which post meta Polylang copies to a translation.
	 *
	 * @param mixed  $metas Meta keys Polylang will copy.
	 * @param bool   $sync  False when a translation is created, true when an existing one is synchronized.
	 * @param int    $from  Source post ID.
	 * @param int    $to    Target post ID.
	 * @param string $lang  Target language.
	 * @return mixed
	 */
	public function polylang_post_metas( $metas, $sync = false, $from = 0, $to = 0, $lang = '' ) {
		return $this->polylang_metas( $metas, (bool) $sync, array_keys( (array) get_post_meta( (int) $from ) ) );
	}

	/**
	 * Which term meta Polylang copies to a translation.
	 *
	 * @param mixed  $metas Meta keys Polylang will copy.
	 * @param bool   $sync  False when a translation is created, true when synchronizing.
	 * @param int    $from  Source term ID.
	 * @param int    $to    Target term ID.
	 * @param string $lang  Target language.
	 * @return mixed
	 */
	public function polylang_term_metas( $metas, $sync = false, $from = 0, $to = 0, $lang = '' ) {
		return $this->polylang_metas( $metas, (bool) $sync, array_keys( (array) get_term_meta( (int) $from ) ) );
	}

	/**
	 * Adjust Polylang's list of copied keys for CFMB fields.
	 *
	 * @param mixed    $metas  Keys Polylang will copy.
	 * @param bool     $sync   Whether an existing translation is synchronized.
	 * @param string[] $source Keys stored on the source object.
	 * @return mixed
	 */
	private function polylang_metas( $metas, bool $sync, array $source ) {
		if ( ! is_array( $metas ) ) {
			return $metas;
		}

		// Polylang passes a list of keys; keep anything that is not ours as it was.
		$list = array_values( array_filter( $metas, static fn( $key ) => is_string( $key ) && '' !== $key ) );
		$keep = array();

		foreach ( $list as $key ) {
			if ( '' === $this->keys->mode( $key ) ) {
				$keep[] = $key;
			}
		}

		foreach ( $source as $key ) {
			$key  = (string) $key;
			$mode = $this->keys->mode( $key );

			if ( Field_Integrations::COPY === $mode ) {
				$keep[] = $key;
			} elseif ( ! $sync && in_array( $mode, array( Field_Integrations::COPY_ONCE, Field_Integrations::TRANSLATE ), true ) ) {
				// New translations start from the source value; editors then change it.
				$keep[] = $key;
			}
		}

		return array_values( array_unique( $keep ) );
	}

	/**
	 * Polylang copies a value to a translation: point IDs at translated objects.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Meta key.
	 * @param string $lang  Target language.
	 * @return mixed
	 */
	public function polylang_translate_value( $value, $key = '', $lang = '' ) {
		$found = $this->keys->find( (string) $key );

		if ( ! $found || ! Id_Mapper::maps( $found['field'] ) || ! $this->polylang->active() ) {
			return $value;
		}

		return $this->ids->map( $found['field'], $value, (string) $lang, $this->polylang );
	}

	/**
	 * Language an options page value is stored for. Empty means the shared
	 * option, which is also the default language's.
	 *
	 * @param string     $language Language from earlier filters.
	 * @param Object_Ref $ref      Options page.
	 * @param string     $name     Field name.
	 * @return string
	 */
	public function option_language( $language, $ref = null, $name = '' ) {
		if ( '' !== (string) $language || ! $ref instanceof Object_Ref || Object_Ref::OPTION !== $ref->kind() ) {
			return (string) $language;
		}

		$plugin = $this->plugin();

		if ( ! $plugin ) {
			return '';
		}

		$current = $plugin->current_language();

		if ( '' === $current || $current === $plugin->default_language() ) {
			return '';
		}

		$mode = $this->keys->mode( (string) $name );

		return in_array( $mode, array( Field_Integrations::TRANSLATE, Field_Integrations::COPY_ONCE ), true ) ? sanitize_key( $current ) : '';
	}
}
