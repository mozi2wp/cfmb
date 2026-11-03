<?php
/**
 * Schema Sync file storage.
 *
 * @package CFMB
 */

namespace CFMB\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the JSON files of Schema Sync.
 *
 * Files are saved to wp-content/uploads/cfmb-json/ and read from there
 * and from a cfmb-json/ folder in the child and parent theme, when those
 * exist. One file per definition, named after its key. When the same key
 * is in several folders, the file with the newest `modified` stamp counts.
 */
class Sync_Files {

	/**
	 * Folder name inside uploads and themes.
	 */
	public const FOLDER = 'cfmb-json';

	/**
	 * Kinds by key prefix.
	 */
	public const KINDS = array(
		'grp_' => 'group',
		'cpt_' => 'post_type',
		'tax_' => 'taxonomy',
		'opt_' => 'options_page',
		'con_' => 'connection',
	);

	/**
	 * Files read this request, by key.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $files = null;

	/**
	 * Kind of a definition from its key, or empty.
	 *
	 * @param string $key Definition key.
	 * @return string
	 */
	public static function kind_of( string $key ): string {
		$prefix = substr( $key, 0, 4 );

		return self::KINDS[ $prefix ] ?? '';
	}

	/**
	 * Folder new files are written to.
	 *
	 * @return string Absolute path without a trailing slash.
	 */
	public function save_dir(): string {
		$uploads = wp_get_upload_dir();
		$dir     = trailingslashit( (string) $uploads['basedir'] ) . self::FOLDER;

		/**
		 * Filters the folder Schema Sync writes JSON files to.
		 *
		 * @param string $dir Absolute path. Default wp-content/uploads/cfmb-json.
		 */
		return untrailingslashit( (string) apply_filters( 'cfmb/sync/save_path', $dir ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Folders JSON files are read from. Only folders that exist are returned.
	 *
	 * @return string[]
	 */
	public function load_dirs(): array {
		$dirs = array(
			trailingslashit( get_stylesheet_directory() ) . self::FOLDER,
			trailingslashit( get_template_directory() ) . self::FOLDER,
			$this->save_dir(),
		);

		/**
		 * Filters the folders Schema Sync reads JSON files from. Earlier folders
		 * win when two files have the same key and the same modified stamp.
		 *
		 * @param string[] $dirs Absolute paths: child theme, parent theme, then the save folder.
		 */
		$dirs = (array) apply_filters( 'cfmb/sync/load_paths', $dirs ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$unique = array();
		foreach ( $dirs as $dir ) {
			$dir = untrailingslashit( (string) $dir );

			if ( '' !== $dir && is_dir( $dir ) && ! in_array( $dir, $unique, true ) ) {
				$unique[] = $dir;
			}
		}

		return $unique;
	}

	/**
	 * Every readable file, by key: item, path, kind, modified.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		if ( null !== $this->files ) {
			return $this->files;
		}

		$this->files = array();
		$fs          = self::filesystem();

		foreach ( $this->load_dirs() as $dir ) {
			foreach ( (array) glob( $dir . '/*.json' ) as $path ) {
				$path = (string) $path;
				$data = json_decode( (string) $fs->get_contents( $path ), true );

				if ( ! is_array( $data ) || ! is_string( $data['key'] ?? null ) ) {
					continue;
				}

				$key  = $data['key'];
				$kind = self::kind_of( $key );

				if ( '' === $kind || basename( $path, '.json' ) !== $key ) {
					continue;
				}

				$modified = (int) ( $data['modified'] ?? 0 );

				if ( isset( $this->files[ $key ] ) && $this->files[ $key ]['modified'] >= $modified ) {
					continue;
				}

				unset( $data['modified'], $data['kind'] );

				$this->files[ $key ] = array(
					'key'      => $key,
					'kind'     => $kind,
					'item'     => $data,
					'path'     => $path,
					'modified' => $modified,
					'in_save'  => 0 === strpos( $path, $this->save_dir() . '/' ),
				);
			}
		}

		return $this->files;
	}

	/**
	 * One file by key, or null.
	 *
	 * @param string $key Definition key.
	 * @return array<string, mixed>|null
	 */
	public function find( string $key ): ?array {
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * Write a definition to the save folder.
	 *
	 * @param string               $kind Definition kind.
	 * @param array<string, mixed> $item Portable definition (no id or source).
	 * @return true|\WP_Error
	 */
	public function write( string $kind, array $item ) {
		$dir = $this->save_dir();

		if ( ! $this->prepare_dir( $dir ) ) {
			return new \WP_Error(
				'cfmb_sync_not_writable',
				sprintf(
					/* translators: %s: folder path */
					__( 'CFMB could not write to %s. Check that the folder exists and the web server can write to it.', 'custom-fields-meta-box' ),
					$dir
				)
			);
		}

		// The newest file wins, so a write must be newer than any copy in a theme folder.
		$current  = $this->find( (string) $item['key'] );
		$modified = $current ? max( time(), (int) $current['modified'] + 1 ) : time();

		$data = array_merge(
			array(
				'key'  => (string) $item['key'],
				'kind' => $kind,
			),
			$item,
			array( 'modified' => $modified )
		);

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $json ) || ! self::filesystem()->put_contents( $dir . '/' . $item['key'] . '.json', $json . "\n", self::file_mode() ) ) {
			return new \WP_Error( 'cfmb_sync_write_failed', __( 'The sync file could not be written.', 'custom-fields-meta-box' ) );
		}

		$this->files = null;

		return true;
	}

	/**
	 * Remove a definition's file from the save folder. Theme files are never touched.
	 *
	 * @param string $key Definition key.
	 * @return void
	 */
	public function delete( string $key ): void {
		$path = $this->save_dir() . '/' . $key . '.json';

		if ( self::kind_of( $key ) && file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		$this->files = null;
	}

	/**
	 * Forget files read this request.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->files = null;
	}

	/**
	 * Short label for where a file lives: Uploads, Child theme, Theme or the folder path.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	public function location_label( string $path ): string {
		$dir = dirname( $path );

		if ( $dir === $this->save_dir() ) {
			return __( 'Uploads', 'custom-fields-meta-box' );
		}

		if ( trailingslashit( get_stylesheet_directory() ) . self::FOLDER === $dir ) {
			return get_stylesheet() !== get_template() ? __( 'Child theme', 'custom-fields-meta-box' ) : __( 'Theme', 'custom-fields-meta-box' );
		}

		if ( trailingslashit( get_template_directory() ) . self::FOLDER === $dir ) {
			return __( 'Theme', 'custom-fields-meta-box' );
		}

		return $dir;
	}

	/**
	 * Create the save folder and keep it from being listed or served.
	 *
	 * @param string $dir Folder.
	 * @return bool Whether the folder is writable.
	 */
	private function prepare_dir( string $dir ): bool {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$fs = self::filesystem();

		if ( ! file_exists( $dir . '/index.html' ) ) {
			$fs->put_contents( $dir . '/index.html', '', self::file_mode() );
		}

		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			$fs->put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n", self::file_mode() );
		}

		return wp_is_writable( $dir );
	}

	/**
	 * Permissions for new files: core's FS_CHMOD_FILE when set.
	 *
	 * @return int
	 */
	private static function file_mode(): int {
		return defined( 'FS_CHMOD_FILE' ) ? (int) FS_CHMOD_FILE : 0644;
	}

	/**
	 * Direct filesystem access. Schema files are plugin data in uploads or
	 * the theme, written by the web server like uploads are.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private static function filesystem(): \WP_Filesystem_Direct {
		static $fs = null;

		if ( null === $fs ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			$fs = new \WP_Filesystem_Direct( null );
		}

		return $fs;
	}
}
