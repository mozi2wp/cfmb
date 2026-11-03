<?php
/**
 * Storage for Connection definitions.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Content\Content_Repository;
use CFMB\Schema\Local_Definitions;

defined( 'ABSPATH' ) || exit;

/**
 * Connection definitions made under CFMB, from code or from Schema Sync files.
 * The links themselves live in Connection_Store.
 */
class Connection_Repository extends Content_Repository {

	/**
	 * Constructor.
	 *
	 * @param Local_Definitions $local Definitions from code and files.
	 */
	public function __construct( Local_Definitions $local ) {
		parent::__construct( new Connection_Schema(), $local );
	}
}
