<?php
/**
 * Active Connections.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Connection objects from the active definitions, once per change.
 */
class Connection_Registry {

	/**
	 * Definitions.
	 *
	 * @var Connection_Repository
	 */
	private Connection_Repository $repository;

	/**
	 * Built connections by name, and the revision they were built for.
	 *
	 * @var array{0: string, 1: array<string, Connection>}|null
	 */
	private ?array $built = null;

	/**
	 * Constructor.
	 *
	 * @param Connection_Repository $repository Definitions.
	 */
	public function __construct( Connection_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Every active connection by name.
	 *
	 * @return array<string, Connection>
	 */
	public function all(): array {
		$active   = $this->repository->active();
		$revision = md5( (string) wp_json_encode( $active ) );

		if ( null !== $this->built && $this->built[0] === $revision ) {
			return $this->built[1];
		}

		$schema = $this->repository->schema();
		$list   = array();

		foreach ( $active as $item ) {
			$connection = new Connection( $schema->build_args( $item ) );

			if ( '' !== $connection->name() ) {
				$list[ $connection->name() ] = $connection;
			}
		}

		/**
		 * Filters the active Connections.
		 *
		 * @param array<string, Connection> $list Connections by name.
		 */
		$list        = (array) apply_filters( 'cfmb/connections/registered', $list ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$this->built = array( $revision, $list );

		return $list;
	}

	/**
	 * One connection.
	 *
	 * @param string $name Connection ID.
	 * @return Connection|null
	 */
	public function find( string $name ): ?Connection {
		$all = $this->all();

		return isset( $all[ $name ] ) && $all[ $name ] instanceof Connection ? $all[ $name ] : null;
	}

	/**
	 * Connections an object takes part in, with the sides it can be on.
	 *
	 * @param Object_Ref $ref Object.
	 * @return array<int, array{connection: Connection, side: string}>
	 */
	public function for_object( Object_Ref $ref ): array {
		$found = array();

		foreach ( $this->all() as $connection ) {
			if ( ! $connection instanceof Connection ) {
				continue;
			}

			foreach ( $connection->sides_for( $ref ) as $side ) {
				$found[] = array(
					'connection' => $connection,
					'side'       => $side,
				);
			}
		}

		return $found;
	}
}
