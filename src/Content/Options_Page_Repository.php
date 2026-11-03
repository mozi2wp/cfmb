<?php
/**
 * Storage for options page definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Local_Definitions;

defined( 'ABSPATH' ) || exit;

/**
 * Options page definitions made under CFMB, from code or from Schema Sync files.
 */
class Options_Page_Repository extends Content_Repository {

	/**
	 * Constructor.
	 *
	 * @param Local_Definitions $local Definitions from code and files.
	 */
	public function __construct( Local_Definitions $local ) {
		parent::__construct( new Options_Page_Schema(), $local );
	}
}
