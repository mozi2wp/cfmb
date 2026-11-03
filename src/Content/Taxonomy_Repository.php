<?php
/**
 * Storage for taxonomy definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Local_Definitions;

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy definitions made under CFMB, from code or from Schema Sync files.
 */
class Taxonomy_Repository extends Content_Repository {

	/**
	 * Constructor.
	 *
	 * @param Local_Definitions $local Definitions from code and files.
	 */
	public function __construct( Local_Definitions $local ) {
		parent::__construct( new Taxonomy_Schema(), $local );
	}
}
