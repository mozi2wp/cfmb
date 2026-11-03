<?php
/**
 * Small service container.
 *
 * @package CFMB
 */

namespace CFMB\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Holds shared services, built on first use.
 */
class Container {

	/**
	 * Service factories keyed by id.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Built services keyed by id.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Register a factory. Replacing a service that was already built is not allowed.
	 *
	 * @param string   $id      Service id, usually the class name.
	 * @param callable $factory Receives the container, returns the service.
	 * @return void
	 */
	public function set( string $id, callable $factory ): void {
		if ( isset( $this->services[ $id ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "Service {$id} is already in use and can't be replaced." ), '0.1.0' );
			return;
		}

		$this->factories[ $id ] = $factory;
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Get a service, building it on first call.
	 *
	 * @param string $id Service id.
	 * @return object
	 * @throws \OutOfBoundsException When the service is unknown.
	 */
	public function get( string $id ): object {
		if ( isset( $this->services[ $id ] ) ) {
			return $this->services[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \OutOfBoundsException( esc_html( "Unknown CFMB service: {$id}" ) );
		}

		$this->services[ $id ] = ( $this->factories[ $id ] )( $this );

		return $this->services[ $id ];
	}
}
