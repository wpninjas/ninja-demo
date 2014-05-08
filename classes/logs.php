<?php
/**
 * Ninja_Demo_Logs
 *
 * This class handles saving Sandbox creation logs if we have that option enabled.
 *
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 * 
 * Portions of this file are derived from NS Cloner, which is released under the GPL2.
 * These unmodified sections are Copywritten 2012 Never Settle
 */

class Ninja_Demo_Logs {

	/**
	 * @var logfile settings
	 */
	var $log_file = '';
	var $log_file_url = '';
	var $detail_log_file = '';
	var $detail_log_file_url = '';

	/**
	 * Get things started
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		if ( Ninja_Demo()->settings['log'] == 1 ) {
			// Create the directory to house our log files
			$log_dir = trailingslashit( WP_CONTENT_DIR . '/nd-logs' );
			$log_url = trailingslashit( WP_CONTENT_URL . '/nd-logs' );

			if ( ! is_dir( $log_dir ) )
				mkdir( $log_dir );
			$this->log_file = $log_dir . 'ns-cloner.log';
			$this->log_file_url = $log_url . 'ns-cloner.log';
			$this->detail_log_file = $log_dir . 'ns-cloner-' . date("Ymd-His", time()) . '.html';
			$this->detail_log_file_url = $log_url .'ns-cloner-' . date("Ymd-His", time()) . '.html';

			add_action( 'admin_notices', array( $this, 'check_logfile' ) );
		}
	}

	/**
	 * Create logfile or display error
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function check_logfile() {
		if( ! file_exists( $this->log_file ) && Ninja_Demo()->settings['log'] == 1 ) {
			$handle = fopen( $this->log_file, 'w' ) or printf( __( '<div class="error"><p>Unable to create log file %s. Is its parent directory writable by the server?</p></div>', 'ns-cloner' ), $this->log_file );
			fclose( $handle );
		}
	}

	/**
	 * Add message to log
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function log( $message ) {
		if ( Ninja_Demo()->settings['log'] ==1 )
			error_log( date_i18n( 'Y-m-d H:i:s' ) . " - $message\n", 3, $this->log_file );
	}
	
	/**
	 * Add a detailed log message
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	function dlog( $message ) {
		if ( Ninja_Demo()->settings['log'] ==1 )
			error_log( date_i18n( 'Y-m-d H:i:s' ) . " - $message\n", 3, $this->detail_log_file );
	}

}