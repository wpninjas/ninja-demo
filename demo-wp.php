<?php
/*
Plugin Name: Demo WP Pro
Plugin URI: http://demowp.pro
Description: Turn your WordPress installation into a demo site for your theme or plugin.
Version: 1.0
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
			
			

			//register_activation_hook( __FILE__, array( self::$instance, 'activation' ) );
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
	 * Add admin menu page
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_page() {
		$page = add_menu_page("Demo Site" , __( 'Demo Site', 'demo-wp' ), apply_filters( 'dwp_admin_menu_capabilities', 'manage_options' ), "demo-wp", array( self::$instance, "output_admin_page" ), "", "32.1337" );
	}

	/**
	 * Output the admin menu page
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function output_admin_page() {
		global $menu, $submenu;

		?>
		<form id="demo_wp_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="demo_wp_submit" value="1">
			<?php wp_nonce_field('demo_wp_save','demo_wp_admin_submit'); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<span class="nav-tab nav-tab-active"><?php _e( 'Settings', 'demo-wp' ); ?></span>
				</h2>
				<!--
				<div id="message" class="updated below-h2">
					<p>
						Updated
					</p>
				</div>
				-->
				<div id="poststuff">
					<div id="post-body">
						<div id="post-body-content">
							<div>
								Hello World!
							</div>
					
						</div><!-- /#post-body-content -->
					</div><!-- /#post-body -->
				</div>
			</div>
		<!-- </div>/.wrap-->
		</form>
		<?php
	}

	/**
	 * Save our admin page
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function save_admin_page() {
		global $menu, $submenu;
		if ( self::$instance->is_admin_user() ) {
			if ( isset ( $_POST['demo_wp_admin_submit'] ) ) {
				$nonce = $_POST['demo_wp_admin_submit'];
			} else {
				$nonce = '';
			}

			if ( isset ( $_POST['demo_wp_submit'] ) && $_POST['demo_wp_submit'] == 1 && wp_verify_nonce( $nonce, 'demo_wp_save' ) ) {
				// Check to see if we've hit the freeze or thaw button
				if ( isset ( $_POST['demo_wp_freeze'] ) ) {
					self::$instance->freeze();
				} else if ( isset ( $_POST['demo_wp_thaw'] ) ) {
					self::$instance->thaw();
				} else if ( isset ( $_POST['demo_wp_restore'] ) ) {
					// Purge our WP Engine Cache
					self::$instance->purge_wpengine_cache();
					self::$instance->restore_folders();
					self::$instance->restore_db();
				} else if ( isset ( $_POST['demo_wp_settings'] ) ) {
					// Thaw our db if it isn't already
					$frozen = false;
					if ( self::$instance->settings['state'] == 'frozen' ) {
						$frozen = true;
						self::$instance->thaw();
					}

					if ( isset ( $_POST['demo_wp_schedule'] ) ) {
						self::$instance->settings['schedule'] = $_POST['demo_wp_schedule'];
						// Remove our scheduled task that restores the database
						wp_clear_scheduled_hook( 'demo_wp_restore' );
						// Setup our scheduled task to restore the database
						wp_schedule_event( time(), self::$instance->settings['schedule'], 'demo_wp_restore' );
					}

					if ( isset ( $_POST['demo_wp_folders'] ) ) {
						$folders = $_POST['demo_wp_folders'];
						$folders = join( "\n", array_map( "trim", explode( "\n", $folders ) ) );
						self::$instance->settings['folders'] = $folders;
					}

					if ( isset ( $_POST['demo_wp_parent_pages'] ) ) {
						// if ( $_POST['demo_wp_parent_pages'] == '' ) {
						// 	$_POST['demo_wp_parent_pages'] = array();
						// }
						self::$instance->settings['parent_pages'] = $_POST['demo_wp_parent_pages'];
					}

					if ( isset ( $_POST['demo_wp_child_pages'] ) ) {
						$child_pages = array();
						foreach( $_POST['demo_wp_child_pages'] as $page ) {
							$key = self::$instance->recursive_array_search( $page, $submenu );
							$child_pages[] = array( 'parent' => $key, 'child' => $page );
						}
						self::$instance->settings['child_pages'] = $child_pages;
					}

					self::$instance->update_settings( self::$instance->settings );
	
					if ( $frozen )
						self::$instance->freeze();
				}
			}
		}
	}

	/**
	 * Clear out the contents of our watched folders
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function delete_folder_contents( $dir ) {
		// Bail if we aren't sent a directory
		if ( !is_dir( $dir ) )
			return false;
        foreach( scandir( $dir ) as $file ) {
            if ( '.' === $file || '..' === $file )
                continue;
            $dir = trailingslashit( $dir );
            if ( is_dir( $dir . $file ) ) {
               self::$instance->delete_folder_contents( $dir . $file );
            } else {
                unlink( $dir . $file );
            }
        }
        rmdir( $dir );
	}

	/**
	 * Upon activation, setup our super admin
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function activation() {
		if ( get_option( 'demo_wp' ) == false ) {
			
		}
	}

	/**
	 * Prevent the user from visiting various pages
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function remove_pages() {
		global $pagenow;

		if ( ! self::$instance->is_admin_user() ) {

			$pages = apply_filters( 'dwp_prevent_access', array( 'options.php' ) );

			// Remove our menu links.
			self::$instance->settings['parent_pages'][] = 'plugins.php';
			self::$instance->settings['parent_pages'][] = 'demo-wp';
			$menu_links = apply_filters( 'dwp_hide_menu_pages', self::$instance->settings['parent_pages'] );

			$submenu_links = apply_filters( 'dwp_hide_submenu_pages', self::$instance->settings['child_pages'] );

			foreach( $menu_links as $page ) {
				remove_menu_page( $page );
				$pages[] = $page;
			}

			foreach( $submenu_links as $page ) {
				remove_submenu_page( $page['parent'], $page['child'] );
				$pages[] = $page['child'];
			}

  			// If we are on any of these pages, then throw an error.
  			if ( in_array( $pagenow, $pages ) || ( isset ( $_REQUEST['page'] ) && in_array( $_REQUEST['page'], $pages ) ) )
  				wp_die( __( 'You do not have sufficient permissions to access this page.', 'demo-wp' ) );
		}
	}

	/**
	 * Disable the password field on our profile page if this isn't the admin user.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function disable_passwords() {
		if ( ! self::$instance->is_admin_user() )
			return false;
		return true;
	}

	/**
	 * Remove the email address from the profile page if this isn't the admin user.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function disable_email( $user_id ) {
		$user_info = get_userdata( $user_id );

		if ( ! self::$instance->is_admin_user() )
			$_POST['first_name'] = $user_info->user_firstname;
			$_POST['last_name'] = $user_info->user_lastname;
			$_POST['nickname'] = $user_info->nickname;
			$_POST['display_name'] = $user_info->display_name;
			$_POST['email'] = $user_info->user_email;
	}

	/**
	 * Check to see if the current user is our admin user
	 * 
	 * @access private
	 * @since 1.0
	 * @return bool
	 */
	private function is_admin_user() {
		$user_id = get_current_user_id();
		return self::$instance->settings['user'] == $user_id;
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
}

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