<?php
/**
 * Demo_WP_Heartbeat
 *
 * This class handles heartbeat responses.
 *
 *
 * @package     Demo WP PRO
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

class Demo_WP_Heartbeat {

	/**
	 * Get everything started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		// add admin menus
		add_filter( 'heartbeat_received', array( $this, 'receive' ), 10, 2 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'receive' ), 10, 2 );
	}

	/**
	 * Listen for our heartbeat and record the activity
	 * 
	 * @access public
	 * @since 1.0
	 * @return array $response
	 */
	public function receive( $response, $data ) {
		global $wpdb;

		if ( isset ( $data['dwp_active'] ) && $data['dwp_active'] == 1 ) {
			$response['dwp_response'] = $wpdb->update( $wpdb->blogs, array( 'last_updated' => current_time( 'mysql' ) ), array( 'blog_id' => get_current_blog_id() ) );
		}

		return $response;
	}
}