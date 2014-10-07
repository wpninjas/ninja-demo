<?php
/*
Plugin Name: Ninja Demo
Plugin URI: http://ninjademo.com
Description: Turn your WordPress installation into a demo site for your theme or plugin.
Version: 1.0.9
Author: The WP Ninjas
Author URI: http://wpninjas.com
Text Domain: ninja-demo
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

class Ninja_Demo {

	/**
	 * @var Ninja_Demo
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * @var Class Globals
	 */
	var $settings;
	var $version = '1.0.9';
	var $cached_source_id = '';

	/**
	 * Main Ninja_Demo Instance
	 *
	 * Insures that only one instance of Ninja_Demo exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.0
	 * @static
	 * @staticvar array $instance
	 * @return The highlander Ninja_Demo
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Ninja_Demo ) ) {
			self::$instance = new Ninja_Demo;
			self::$instance->setup_constants();
			self::$instance->includes();
			//self::$instance->theme = new Ninja_Demo_Theme();
			//self::$instance->toolbar = new Ninja_Demo_Toolbar();

			register_activation_hook( __FILE__, array( self::$instance, 'activation' ) );
			add_action( 'init', array( self::$instance, 'init' ), 5 );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_css' ), 999 );
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'display_js' ) );
					
			add_filter( 'widget_text', 'do_shortcode' );

			add_action( 'plugins_loaded', array( self::$instance, 'load_lang' ) );
		}

		return self::$instance;
	}

	public function init() {
		self::$instance->get_settings();
		self::$instance->admin_settings = new Ninja_Demo_Admin();
		self::$instance->sandbox = new Ninja_Demo_Sandbox();
		self::$instance->restrictions = new Ninja_Demo_Restrictions();
		self::$instance->heartbeat = new Ninja_Demo_Heartbeat();
		self::$instance->logs = new Ninja_Demo_Logs();
		self::$instance->shortcodes = new Ninja_Demo_Shortcodes();
		self::$instance->ip = new Ninja_Demo_IP_Lockout();

		// Get our license updating stuff going.
		$license = Ninja_Demo()->plugin_settings['license'];

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater( 'http://ninjademo.com', __FILE__, array(
		    'version'   => $this->version,     // current version number
		    'license'   => $license,  // license key (used get_option above to retrieve from DB)
		    'item_name'     => 'Ninja Demo',  // name of this plugin
		    'author'  => 'WP Ninjas',  // author of this plugin
		  )
		);
	}

	/**
	 * Add our language files.
	 * Load the text domain
	 * 
	 * @since 1.0.4
	 * @access public
	 * @return void/
	 */
	public function load_lang() {

		/** Set our unique textdomain string */
		$textdomain = 'ninja-demo';

		/** The 'plugin_locale' filter is also used by default in load_plugin_textdomain() */
		$locale = apply_filters( 'plugin_locale', get_locale(), $textdomain );

		/** Set filter for WordPress languages directory */
		$wp_lang_dir = apply_filters(
			'ninja_demo_wp_lang_dir',
			WP_LANG_DIR . '/ninja-demo/' . $textdomain . '-' . $locale . '.mo'
		);

		/** Translations: First, look in WordPress' "languages" folder = custom & update-secure! */
		load_textdomain( $textdomain, $wp_lang_dir );

		/** Translations: Secondly, look in plugin's "lang" folder = default */
		$plugin_dir = basename( dirname( __FILE__ ) );
		$lang_dir = apply_filters( 'ninja_demo_lang_dir', $plugin_dir . '/lang/' );
		load_plugin_textdomain( $textdomain, FALSE, $lang_dir );

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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'ninja-demo' ), '1.6' );
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'ninja-demo' ), '1.6' );
	}

	/**
	 * Setup plugin constants
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function setup_constants() {
		global $wpdb;

		// Plugin version
		if ( ! defined( 'ND_PLUGIN_VERSION' ) ) {
			define( 'ND_PLUGIN_VERSION', '1.0' );
		}

		// Plugin Folder Path
		if ( ! defined( 'ND_PLUGIN_DIR' ) ) {
			define( 'ND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL
		if ( ! defined( 'ND_PLUGIN_URL' ) ) {
			define( 'ND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File
		if ( ! defined( 'ND_PLUGIN_FILE' ) ) {
			define( 'ND_PLUGIN_FILE', __FILE__ );
		}

		// Lockout IP Table name
		if ( ! defined( 'ND_IP_LOCKOUT_TABLE' ) ) {
			switch_to_blog( 1 );
			define( 'ND_IP_LOCKOUT_TABLE', $wpdb->prefix . 'nd_ip_lockout' );
			restore_current_blog();
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
		// Get our sandbox's source id
		if ( self::$instance->source_id() ) {
			$settings = get_blog_option( self::$instance->source_id(), 'ninja_demo' );
		} else {
			$settings = get_option( 'ninja_demo' );
		}

		$plugin_settings = get_site_option( 'ninja_demo' );

		$settings['parent_pages'] = isset ( $settings['parent_pages'] ) ? $settings['parent_pages'] : array();
		$settings['child_pages'] = isset ( $settings['child_pages'] ) ? $settings['child_pages'] : array();
		$settings['log'] = isset ( $settings['log'] ) ? $settings['log'] : 0;
		$settings['prevent_clones'] = isset ( $settings['prevent_clones'] ) ? $settings['prevent_clones'] : '';
		$settings['offline'] = isset ( $settings['offline'] ) ? $settings['offline'] : '';
		$settings['show_toolbar'] = isset ( $settings['show_toolbar'] ) ? $settings['show_toolbar'] : 1;
		$settings['auto_login'] = isset ( $settings['auto_login'] ) ? $settings['auto_login'] : '';
		$settings['login_role'] = isset ( $settings['login_role'] ) ? $settings['login_role'] : '';
		$settings['theme_site'] = isset ( $settings['theme_site'] ) ? $settings['theme_site'] : '';
		
		// Check to see if we've upgraded from a version before plugin_settings was introduced.
		if ( isset ( $settings['admin_id'] ) ) {
			$plugin_settings['admin_id'] = $settings['admin_id'];
			unset( $settings['admin_id'] );
			self::$instance->update_settings( $settings );
			self::$instance->update_plugin_settings( $plugin_settings );
		}

		if ( isset ( $settings['license'] ) ) {
			$plugin_settings['license'] = $settings['license'];
			unset( $settings['license'] );
			self::$instance->update_settings( $settings );
			self::$instance->update_plugin_settings( $plugin_settings );
		}

		if ( isset ( $settings['license_status'] ) ) {
			$plugin_settings['license_status'] = $settings['license_status'];
			unset( $settings['license_status'] );
			self::$instance->update_settings( $settings );
			self::$instance->update_plugin_settings( $plugin_settings );
		}

	    self::$instance->settings = $settings;

	    // Get our plugin settings
	    
	    if ( isset ( $settings['license'] ) ) {

	    }
	    $plugin_settings['license'] = isset ( $plugin_settings['license'] ) ? $plugin_settings['license'] : '';
		$plugin_settings['license_status'] = isset ( $plugin_settings['license_status'] ) ? $plugin_settings['license_status'] : '';
		$plugin_settings['theme_sites'] = isset ( $plugin_settings['theme_sites'] ) ? $plugin_settings['theme_sites'] : array();

	    self::$instance->plugin_settings = $plugin_settings;
	}

	/**
	 * Include our Class files
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function includes() {
		require_once( ND_PLUGIN_DIR . 'classes/admin.php' );
		require_once( ND_PLUGIN_DIR . 'classes/sandbox.php' );
		require_once( ND_PLUGIN_DIR . 'classes/restrictions.php' );
		require_once( ND_PLUGIN_DIR . 'classes/logs.php' );
		require_once( ND_PLUGIN_DIR . 'classes/shortcodes.php' );
		require_once( ND_PLUGIN_DIR . 'classes/ip-lockout.php' );
		require_once( ND_PLUGIN_DIR . 'classes/heartbeat.php' );
		require_once( ND_PLUGIN_DIR . 'classes/toolbar.php' );
		require_once( ND_PLUGIN_DIR . 'classes/theme.php' );

		if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) )
			require_once( ND_PLUGIN_DIR . 'classes/EDD_SL_Plugin_Updater.php' );
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
		update_option( 'ninja_demo', $args );
	}	

	/**
	 * Update our plugin settings
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function update_plugin_settings( $args ) {
		self::$instance->plugin_settings = $args;
		update_site_option( 'ninja_demo', $args );
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
			wp_enqueue_script( 'ninja-demo-monitor', ND_PLUGIN_URL .'assets/js/monitor.js', array( 'jquery', 'heartbeat' ) );
		}

		if ( self::$instance->settings['show_toolbar'] == 1 ) {
			wp_enqueue_script( 'ninja_demo-modal', ND_PLUGIN_URL .'assets/js/jquery.modal.min.js', array( 'jquery', 'jquery-ui-core', 'jquery-effects-core', 'jquery-effects-fade' ) );
			wp_enqueue_script( 'ninja_demo-modal', ND_PLUGIN_URL .'assets/js/modernizr.custom.79639.js', array( 'jquery', 'jquery-ui-core', 'jquery-effects-core', 'jquery-effects-fade' ) );
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
		wp_enqueue_style( 'ninja-demo-admin', ND_PLUGIN_URL .'assets/css/display.css' );
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
		
		if ( get_site_option( 'ninja_demo' ) == false ) {
			$args = array(
				'admin_id' 			=> get_current_user_id(),
				'license'			=> '',
				'license_status'	=> '',
				'theme_sites'		=> array()
			);
			update_site_option( 'ninja_demo', $args );
		}

		if ( get_option( 'ninja_demo' ) == false ) {
			$args = array(
				'offline' 			=> 1,
				'prevent_clones' 	=> 0,
				'log'				=> 0,
				'auto_login'		=> '',
				'parent_pages'		=> array(),
				'child_pages'		=> array(),
				'show_toolbar'		=> 0
			);
			update_option( 'ninja_demo', $args );
		}


		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'nd_hourly' );
		$sql = "CREATE TABLE IF NOT EXISTS ". ND_IP_LOCKOUT_TABLE . " (
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
	 *
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_sandbox( $blog_id = '' ) {
		if ( $blog_id != '' ) {
			if ( get_blog_option( $blog_id, 'nd_sandbox' ) == 1 ) {
				return true;
			} else {
				return false;
			}
		} else {
			// Check to see if our sandbox option is set.
			if ( get_option( 'nd_sandbox' ) == 1 ) {
				return true;
			} else {
				return false;
			}			
		}

	}

	/**
	 * Return our source id if we are in a sandbox. Return false otherwise.
	 * 
	 * @access public
	 * @since 1.0.4
	 * @return $id or bool(false)
	 */
	public function source_id() {
		if ( self::$instance->is_sandbox() ) {
			return get_option( 'nd_source_id' );
		} else {
			return false;
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
 * The main function responsible for returning the one true Ninja_Demo
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $nd = Ninja_Demo(); ?>
 *
 * @since 1.0
 * @return object The highlander Ninja_Demo Instance
 */
function Ninja_Demo() {
	return Ninja_Demo::instance();
}

// Get Ninja_Demo Running
Ninja_Demo();
