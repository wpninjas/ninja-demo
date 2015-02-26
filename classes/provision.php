<?php
/**
 * Ninja_Demo_Provision
 *
 * This class handles the creation, deletion, and interacting with Sandboxes.
 *
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.1
 *
 * Portions of this file are derived from NS Cloner, which is released under the GPL2.
 * These unmodified sections are Copywritten 2012 Never Settle
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Ninja_Demo_Provision {

	/**
	 * Check to see how many sandboxes we have provisioned for the particular source id
	 * 
	 * @access public
	 * @since 1.1
	 * @param int $source_id
	 * @return int $count
	 */
	public function get_count( $source_id ) {
		$provisioned = Ninja_Demo()->plugin_settings['provisioned'];
		if ( isset ( $provisioned[ $source_id ] ) ) {
			return count( $provisioned[ $source_id ] );
		} else {
			return 0;
		}
	}
}