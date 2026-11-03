<?php
/**
 * Storage for post type definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Local_Definitions;

defined( 'ABSPATH' ) || exit;

/**
 * Post type definitions made under CFMB, from code or from Schema Sync files.
 */
class Post_Type_Repository extends Content_Repository {

	/**
	 * Constructor.
	 *
	 * @param Local_Definitions $local Definitions from code and files.
	 */
	public function __construct( Local_Definitions $local ) {
		parent::__construct( new Post_Type_Schema(), $local );
	}
}
