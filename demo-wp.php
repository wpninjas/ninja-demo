<?php
/*
Plugin Name: Demo WP Pro
Plugin URI: http://demowp.pro
Description: Turn your WordPress installation into a demo site for your theme or plugin.
Version: 0.2
Author: The WP Ninjas
Author URI: http://wpninjas.com
Text Domain: demo-wp
Domain Path: /lang/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Portions of this plugin are derived from NS Cloner, which is released under the GPL2.
These unmodified sections are Copywritten 2012 Never Settle
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Demo_WP {

	/**
	 * @var Demo_WP
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * @var Class Globals
	 */
	var $settings;

	/**
	 * Main Demo_WP Instance
	 *
	 * Insures that only one instance of Demo_WP exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.0
	 * @static
	 * @staticvar array $instance
	 * @return The highlander Demo_WP
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Demo_WP ) ) {
			self::$instance = new Demo_WP;
			self::$instance->setup_constants();
			self::$instance->get_settings();
			self::$instance->includes();

			self::$instance->admin_settings = new Demo_WP_Admin();
			self::$instance->sandbox = new Demo_WP_Sandbox();
			self::$instance->restrictions = new Demo_WP_Restrictions();
			self::$instance->heartbeat = new Demo_WP_Heartbeat();
			self::$instance->logs = new Demo_WP_Logs();
			self::$instance->shortcodes = new Demo_WP_Shortcodes();
			self::$instance->ip = new Demo_WP_IP_Lockout();

			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_css' ) );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_js' ) );

			add_filter( 'widget_text', 'do_shortcode' );

			register_activation_hook( __FILE__, array( self::$instance, 'activation' ) );
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'demo-wp' ), '1.6' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'edd' ), '1.6' );
	}

	/**
	 * Setup plugin constants
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function setup_constants() {

		// Plugin version
		if ( ! defined( 'DEMO_WP_VERSION' ) ) {
			define( 'DEMO_WP_VERSION', '1.0' );
		}

		// Plugin Folder Path
		if ( ! defined( 'DEMO_WP_DIR' ) ) {
			define( 'DEMO_WP_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL
		if ( ! defined( 'DEMO_WP_URL' ) ) {
			define( 'DEMO_WP_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File
		if ( ! defined( 'DEMO_WP_FILE' ) ) {
			define( 'DEMO_WP_FILE', __FILE__ );
		}
	}

	/**
	 * Get our plugin settings
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function get_settings() {
		$settings = get_blog_option( 1, 'demo_wp' );
		self::$instance->settings = $settings;
	}

	/**
	 * Include our Class files
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function includes() {
		require_once( DEMO_WP_DIR . 'classes/admin.php' );
		require_once( DEMO_WP_DIR . 'classes/sandbox.php' );
		require_once( DEMO_WP_DIR . 'classes/restrictions.php' );
		require_once( DEMO_WP_DIR . 'classes/logs.php' );
		require_once( DEMO_WP_DIR . 'classes/shortcodes.php' );
		require_once( DEMO_WP_DIR . 'classes/ip-lockout.php' );
		require_once( DEMO_WP_DIR . 'classes/heartbeat.php' );
	}

	/**
	 * Update our plugin settings
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function update_settings( $args ) {
		self::$instance->settings = $args;
		update_option( 'demo_wp', $args );
	}

	/**
	 * Enqueue our display (front-end) JS
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function display_js() {
		if ( ! self::$instance->is_admin_user() && self::$instance->is_sandbox() ) {
			wp_enqueue_script( 'demo-wp-monitor', DEMO_WP_URL .'assets/js/monitor.js', array( 'jquery', 'heartbeat' ) );
		}
	}

	/**
	 * Enqueue our display (front-end) CSS
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function display_css() {
		wp_enqueue_style( 'demo-wp-admin', DEMO_WP_URL .'assets/css/display.css' );
	}

	/**
	 * Generate a random alphanumeric string
	 *
	 * @access public
	 * @since 1.0
	 * @return string $string
	 */
	public function random_string( $length = 15 ) {
		$string = '';
	    $keys = array_merge( range(0, 9), range('a', 'z') );

	    for ( $i = 0; $i < $length; $i++ ) {
	        $string .= $keys[ array_rand( $keys ) ];
	    }

	    $string = sanitize_title_with_dashes( $string );
	    return $string;
	}

	/**
	 * Upon activation, setup our super admin
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function activation() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		if ( get_option( 'demo_wp' ) == false ) {
			$args = array(
				'offline' 			=> 0,
				'prevent_clones' 	=> 0,
				'log'				=> 0,
				'parent_pages'		=> array(),
				'child_pages'		=> array(),
				'admin_id' 			=> get_current_user_id(),
			);
			update_option( 'demo_wp', $args );
		}
		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'dwp_hourly' );
		$sql = "CREATE TABLE IF NOT EXISTS ". $wpdb->prefix . "demo_wp_ip_lockout (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`ip` text NOT NULL,
			`time_set` int(255) NOT NULL,
			`time_expires` int(255) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
		
		dbDelta($sql);
	}

	/**
	 * Check to see if the current user is our admin user
	 *
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_admin_user() {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Purge WPengine cache when we restore the database.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function purge_wpengine_cache() {
		if ( class_exists( 'WpeCommon' ) ) {
			ob_start();
			WpeCommon::purge_memcached();
			WpeCommon::clear_maxcdn_cache();
			WpeCommon::purge_varnish_cache();  // refresh our own cache (after CDN purge, in case that needed to clear before we access new content)
			WpeCommon::empty_all_caches();
			$errors = ob_get_contents();
			ob_end_clean();
		}
	}

	/**
	 * Search an array recursively for a value
	 *
	 * @access public
	 * @since 1.0
	 * @return string $key
	 */
	public function recursive_array_search( $needle, $haystack ) {
	    foreach( $haystack as $key => $value ) {
	        $current_key = $key;
	        if( $needle === $value OR ( is_array( $value ) && self::$instance->recursive_array_search( $needle, $value ) !== false ) ) {
	            return $current_key;
	        }
	    }
	    return false;
	}

	/**
	 * Check to see if we are currently in a sandbox.
	 * Wrapper function for is_main_site()
	 *
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_sandbox() {
		if ( is_main_site() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Decode HTML entities within an array
	 * 
	 * @access public
	 * @since 1.0
	 * @return array $value
	 */
	public function html_entity_decode_deep( $value ) {
    	$value = is_array($value) ?
	        array_map( array( self::$instance, 'html_entity_decode_deep' ), $value ) :
	        html_entity_decode( $value );
    	return $value;
	}	

} // End Class

/**
 * The main function responsible for returning the one true Demo_WP
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $dwp = Demo_WP(); ?>
 *
 * @since 1.0
 * @return object The highlander Demo_WP Instance
 */
function Demo_WP() {
	return Demo_WP::instance();
}

// Get Demo_WP Running
Demo_WP();
