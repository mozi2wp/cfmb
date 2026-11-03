<?php
/**
 * Icon library registry.
 *
 * @package CFMB
 */

namespace CFMB\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the icon libraries the Icon field offers.
 */
class Icon_Library_Registry {

	/**
	 * Libraries by ID.
	 *
	 * @var array<string, Icon_Library>
	 */
	private array $libraries = array();

	/**
	 * Whether core and add-on libraries are registered.
	 *
	 * @var bool
	 */
	private bool $ready = false;

	/**
	 * Add or replace a library.
	 *
	 * @param Icon_Library $library Library.
	 * @return void
	 */
	public function add( Icon_Library $library ): void {
		if ( preg_match( '/^[a-z0-9_-]+$/', $library->id() ) && ! in_array( $library->id(), array( 'media', 'url' ), true ) ) {
			$this->libraries[ $library->id() ] = $library;
		}
	}

	/**
	 * A library by ID.
	 *
	 * @param string $id Library ID.
	 * @return Icon_Library|null
	 */
	public function get( string $id ): ?Icon_Library {
		$this->load();

		return $this->libraries[ $id ] ?? null;
	}

	/**
	 * Every library.
	 *
	 * @return array<string, Icon_Library>
	 */
	public function all(): array {
		$this->load();

		return $this->libraries;
	}

	/**
	 * Register core libraries, then let add-ons register theirs.
	 *
	 * @return void
	 */
	private function load(): void {
		if ( $this->ready ) {
			return;
		}

		$this->ready = true;
		$this->add( new Dashicons() );
		$this->add( new WordPress_Icons() );

		// Font Awesome ships as a generated data file; without it there is no library to offer.
		if ( is_readable( Font_Awesome_Icons::file() ) ) {
			$this->add( new Font_Awesome_Icons() );
		}

		/**
		 * Fires when icon libraries can be registered.
		 *
		 * @param Icon_Library_Registry $registry Registry. Call ->add( $library ).
		 */
		do_action( 'cfmb/icons/register', $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}
}
