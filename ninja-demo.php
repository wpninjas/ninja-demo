<?php
/*
Plugin Name: Demo Site
Plugin URI: http://wpninjas.com
Description: Turn your WordPress installation into a demo site for your theme or plugin.
Version: 1.0
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
	 * @var upload directory
	 * @since 1.0
	 */
	private $upload_dir;

	/**
	 * @var file folders to watch for changes
	 * @since 1.0
	 */
	private $watched_folders;

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
			self::$instance->setup_upload_dir();
			self::$instance->setup_watched_folders();

			add_action( 'init', array( self::$instance, 'purge_wpengine_cache' ) );
			add_action( 'init', array( self::$instance, 'maintenance_mode' ) );

			add_action( 'admin_menu', array( self::$instance, 'add_menu_page' ) );
			add_action( 'admin_init', array( self::$instance, 'save_admin_page' ) );
			add_action( 'admin_init', array( self::$instance, 'remove_pages' ) );
			add_action( 'admin_init', array( self::$instance, 'check_querystring' ) );
			
			add_filter( 'auto_update_plugin', '__return_true' );

			add_action( 'upgrader_pre_install', array( self::$instance, 'before_update' ), 10, 2 );
			add_action( 'upgrader_post_install', array( self::$instance, 'after_update' ), 10, 3 );
			add_action( 'ninja_demo_restore', array( self::$instance, 'restore_db' ) );
			add_action( 'admin_notices', array( self::$instance, 'admin_notice' ) );
			

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
		if ( ! defined( 'NINJA_DEMO_VERSION' ) ) {
			define( 'NINJA_DEMO_VERSION', '1.0' );
		}

		// Plugin Folder Path
		if ( ! defined( 'NINJA_DEMO_DIR' ) ) {
			define( 'NINJA_DEMO_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL
		if ( ! defined( 'NINJA_DEMO_URL' ) ) {
			define( 'NINJA_DEMO_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File
		if ( ! defined( 'NINJA_DEMO_FILE' ) ) {
			define( 'NINJA_DEMO_FILE', __FILE__ );
		}
	}

	/**
	 * Setup upload directory
	 * 
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function setup_upload_dir() {
		$upload_dir = NINJA_DEMO_DIR . 'file-backup/';
		if ( !is_dir( $upload_dir ) )
			mkdir( $upload_dir );
		self::$instance->upload_dir = trailingslashit( $upload_dir );
	}

	/**
	 * Setup watched folders
	 * 
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function setup_watched_folders() {
		$uploads_dir = wp_upload_dir();
		$uploads_dir = $uploads_dir['basedir'];
		self::$instance->watched_folders = apply_filters( 'nd_watched_folders', array( $uploads_dir ) );
	}

	/**
	 * Add admin menu page
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_page() {
		if ( self::$instance->is_admin_user() ) {
			$page = add_menu_page("Demo Site" , __( 'Demo Site', 'ninja-demo' ), apply_filters( 'nd_admin_menu_capabilities', 'manage_options' ), "ninja-demo", array( self::$instance, "output_admin_page" ), "", "32.1337" );
		}
	}

	/**
	 * Output the admin menu page
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function output_admin_page() {
		global $menu, $submenu, $_registered_pages, $_parent_pages;

		$current_state = get_option( 'ninja_demo_state' );
		$restore_schedule = get_option( 'ninja_demo_schedule' );
		$tabs = apply_filters( 'nd_tabs' , array( array( 'db' => __( 'Data Protection', 'ninja-demo' ) ), array( 'admin_pages' => __( 'Admin Pages', 'ninja-demo' ) ) ) );
		if ( isset ( $_REQUEST['tab'] ) ) {
			$current_tab = $_REQUEST['tab'];
		} else {
			$current_tab = array_keys( $tabs[0] );
			$current_tab = $current_tab[0];
		}

		?>
		<form id="ninja_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="ninja_demo_submit" value="1">
			<?php wp_nonce_field('ninja_demo_save','ninja_demo_admin_submit'); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<?php
					foreach( $tabs as $tab ) {
						foreach ( $tab as $slug => $nicename ) {
							if ( $slug == $current_tab ) {
								?>
								<span class="nav-tab nav-tab-active"><?php echo $nicename; ?></span>
								<?php
							} else {
								?>
								<?php $tab_link = add_query_arg( array( 'tab' => $slug ) ); ?>
								<a href="<?php echo $tab_link; ?>" class="nav-tab"><?php echo $nicename; ?></a>
								<?php
							}							
						}
					}
					?>					
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
							<?php
							if ( $current_tab == 'admin_pages' ) {
								$x = 0;
								foreach( $menu as $page ) {
									if ( $x == 0 ) {
										?>
											<ul style="float:left;">
										<?php
									}
									if ( isset ( $page[0] ) && $page[0] != '' ) {
										$parent_slug = $page[2];
										$class_name = str_replace( '.', '', $parent_slug );
										?>
										<li><label><input type="checkbox" name="" value="<?php echo $page[2];?>" class="ninja-demo-parent"> <?php echo $page[0]; ?></label></li>
										<?php
										if ( isset ( $submenu[ $parent_slug ] ) ) {
											?>
											<ul style="margin-left:30px;" class="demo-admin-<?php echo $parent_slug; ?>">
											<?php
											foreach( $submenu[ $parent_slug ] as $subpage ) {
												?>
												<li><label><input type="checkbox" name="" value="<?php echo $subpage[2]; ?>"> <?php echo $subpage[0]; ?></label></li>
												<?php
											}
											?>
											</ul>
											<?php
										}
									}

									if ( $x == 8 ) {
										?>
											</ul>
										<?php
										$x = 0;
									} else {
										$x++;
									}
									
								}

							} else if ( $current_tab == 'db' ) {
								if ( $current_state == 'frozen' ) {
									_e( 'Your site is currently <strong>frozen</strong>. In this state, any changes to the database will be reverted every hour.', 'ninja-demo' );
									?>
									<div>
										<input class="button-secondary" name="ninja_demo_thaw" type="submit" value="<?php _e( 'Thaw Site', 'ninja-demo' ); ?>" />
									</div>
									<?php
								} else {
									_e( 'Your site is currently <strong>thawed</strong>. In this state, any changes to the database will be retained.', 'ninja-demo' );
									?>
									<div>
										<input class="button-secondary" name="ninja_demo_freeze" type="submit" value="<?php _e( 'Freeze Site', 'ninja-demo' ); ?>" />
									</div>
									<?php
								}
								?>
								<div>
									<input class="button-secondary" name="ninja_demo_restore" type="submit" value="<?php _e( 'Restore Site', 'ninja-demo' ); ?>" />
								</div>
								<div>
									<?php
									$intervals = wp_get_schedules();
									?>
									<?php _e( 'I would like to reset the database:', 'ninja-demo' ); ?>
									<select name="ninja_demo_schedule">
										<?php
										foreach( $intervals as $key => $int ) {
											?>
											<option value="<?php echo $key; ?>" <?php selected( $restore_schedule, $key ); ?>><?php echo $int['display']; ?></option>
											<?php
										}
										?>
									</select>
								</div>
								<div>
									<input class="button-primary" name="ninja_demo_settings" type="submit" value="<?php _e( 'Save', 'ninja-demo' ); ?>" />
								</div>
								<?php
							}
							?>							
						</div><!-- /#post-body-content -->
					</div><!-- /#post-body -->
				</div>
			</div>
		<!-- </div>/.wrap-->
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$( document ).on( 'change', '.ninja-demo-parent', function() {
					console.log( $( '.demo-admin-' + this.value ) );
					$( '.demo-admin-' + this.value + ' input' ).attr( 'checked', this.checked );
				});
			});
		</script>
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
		if ( self::$instance->is_admin_user() ) {
			if ( isset ( $_POST['ninja_demo_admin_submit'] ) ) {
				$nonce = $_POST['ninja_demo_admin_submit'];
			} else {
				$nonce = '';
			}

			if ( isset ( $_POST['ninja_demo_submit'] ) && $_POST['ninja_demo_submit'] == 1 && wp_verify_nonce( $nonce, 'ninja_demo_save' ) ) {
				// Check to see if we've hit the freeze or thaw button
				if ( isset ( $_POST['ninja_demo_freeze'] ) ) {
					self::$instance->freeze();
				} else if ( isset ( $_POST['ninja_demo_thaw'] ) ) {
					self::$instance->thaw();
				} else if ( isset ( $_POST['ninja_demo_restore'] ) ) {
					// Purge our WP Engine Cache
					self::$instance->purge_wpengine_cache();
					self::$instance->restore_folders();
					self::$instance->restore_db();
				} else if ( isset ( $_POST['ninja_demo_settings'] ) ) {
					if ( isset ( $_POST['ninja_demo_schedule'] ) ) {
						// Thaw our db if it isn't already
						$thaw = false;
						if ( get_option( 'ninja_demo_state' ) == 'frozen' ) {
							$thaw = true;
							self::$instance->thaw();
						}
							
						update_option( 'ninja_demo_schedule', $_POST['ninja_demo_schedule'] );
						// Remove our scheduled task that restores the database
						wp_clear_scheduled_hook( 'ninja_demo_restore' );
						// Setup our scheduled task to restore the database
						wp_schedule_event( time(), get_option( 'ninja_demo_schedule' ), 'ninja_demo_restore' );
						self::$instance->freeze();
					}
				}
			}
		}
	}

	/**
	 * Check for our querystring action
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function check_querystring() {
		global $pagenow;
		if ( self::$instance->is_admin_user() && $pagenow == 'admin.php' && isset ( $_REQUEST['page'] ) && $_REQUEST['page'] == 'ninja-demo' ) {
			if ( isset ( $_REQUEST['action'] ) && $_REQUEST['action'] != '' ) {
				if ( $_REQUEST['action'] == 'thaw' ) {
					// Thaw our site
					self::$instance->thaw();
				} else if ( $_REQUEST['action'] == 'freeze' ) {
					// Freeze our site
					self::$instance->freeze();
				} else if ( $_REQUEST['action'] == 'restore' ) {
					// Restore the files in our watched folders
					self::$instance->restore_folders();
					// Restore our database to it's pristine condition
					self::$instance->restore_db();
				}
				wp_redirect( urldecode( $_REQUEST['redirect'] ) );
				exit;
			}
		}
	}

	/**
	 * Freeze our database
	 * 
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function freeze() {
		// Set our current state to frozen
		update_option( 'ninja_demo_state', 'frozen' );

		// Purge our WP Engine Cache
		self::$instance->purge_wpengine_cache();

		// Export the db as it is
		self::$instance->export_db();

		// Backup our files
		self::$instance->backup_folders();

		// Setup our scheduled task to restore the database
		wp_schedule_event( time(), get_option( 'ninja_demo_schedule' ), 'ninja_demo_restore' );
	}

	/**
	 * Thaw our database
	 * 
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function thaw(){
		// Set our current state to thawed
		update_option( 'ninja_demo_state', 'thawed' );

		// Purge our WP Engine Cache
		self::$instance->purge_wpengine_cache();

		// Restore the files in our watched folders
		self::$instance->restore_folders();

		// Restore our database to it's pristine condition
		self::$instance->restore_db();

		// Remove our scheduled task that restores the database
		wp_clear_scheduled_hook( 'ninja_demo_restore' );
	}

	/**
	 * Create an export of our database as it is now.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function export_db() {
		global $wpdb;

		$cron_row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "cron"', ARRAY_A );
		$wpdb->query( 'DELETE FROM ' . $wpdb->options .' WHERE option_name = "cron"' );

		$current_state = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_state"', ARRAY_A );
		$wpdb->query( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_state"' );

		$schedule = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_schedule"', ARRAY_A );
		$wpdb->query( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_schedule"' );

		$link = mysqli_connect( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		
		$tables = array();
		$result = mysqli_query( $link, 'SHOW TABLES' );
		while( $row = mysqli_fetch_row( $result ) )	{
			$tables[] = $row[0];
		}
		
		$return = '';
		
		//cycle through
		foreach( $tables as $table ) {
			$result = mysqli_query( $link, 'SELECT * FROM '.$table);
			$num_fields = mysqli_field_count( $link );
			
			$return.= 'DROP TABLE '.$table.';';
			$row2 = mysqli_fetch_row( mysqli_query( $link, 'SHOW CREATE TABLE '.$table ) ) ;
			$return.= "\n\n".$row2[1].";\n\n";
			
			for ($i = 0; $i < $num_fields; $i++) {
				while( $row = mysqli_fetch_row( $result ) )	{
					$return.= 'INSERT INTO '.$table.' VALUES(';
					for($j=0; $j<$num_fields; $j++) 
					{
						$row[$j] = addslashes($row[$j]);
						$row[$j] = str_replace("\n","\\n",$row[$j]);
						if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
						if ($j<($num_fields-1)) { $return.= ','; }
					}
					$return.= ");\n";
				}
			}
			$return.="\n\n\n";
		}
		mysqli_close( $link );
		//save file

		$handle = fopen( trailingslashit( NINJA_DEMO_DIR ) . 'backup.sql ' , 'w+' );
		fwrite( $handle, $return );
		fclose( $handle );

		$wpdb->insert( $wpdb->options, $cron_row );
		$wpdb->insert( $wpdb->options, $current_state );
		$wpdb->insert( $wpdb->options, $schedule );
	}

	/**
	 * Restore our database from our backup.sql file
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function restore_db() {
		global $wpdb;
		$cron_row = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "cron"', ARRAY_A );
		$current_state = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_state"', ARRAY_A );
		$schedule = $wpdb->get_row( 'SELECT * FROM ' . $wpdb->options . ' WHERE option_name = "ninja_demo_schedule"', ARRAY_A );

		$filename = trailingslashit( NINJA_DEMO_DIR ) . 'backup.sql ';
		// Temporary variable, used to store current query
		$templine = '';
		// Read in entire file
		$lines = file( $filename );
		$pass = true;
		// Loop through each line
		foreach ( $lines as $line ){
		    // Skip it if it's a comment
		    if ( substr( $line, 0, 2) == '--' || $line == '' )
		        continue;

		    // Add this line to the current segment
		    $templine .= $line;
		    // If it has a semicolon at the end, it's the end of the query
		    if ( substr( trim( $line ), -1, 1 ) == ';'){
		    	// Make sure that any % signs in our query have been double escaped
		    	$templine = str_replace( '%', '%%', $templine );
		        // Perform the query
		        $wpdb->query( $wpdb->prepare( $templine, '' ) );
		        // Reset temp variable to empty
		        $templine = '';
		    }
		}

		$wpdb->insert( $wpdb->options, $cron_row );
		$wpdb->insert( $wpdb->options, $current_state );
		$wpdb->insert( $wpdb->options, $schedule );
	}

	/**
	 * Backup our file folders
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function backup_folders() {
		// Clean out our current backup folder.
		self::$instance->delete_folder_contents( self::$instance->upload_dir );
		self::$instance->setup_upload_dir();

		// Loop through our watched folders and back each one up.
		foreach( self::$instance->watched_folders as $folder ) {
			// Copy our watched folder to our backup directory.
			self::$instance->copy_folder( $folder, self::$instance->upload_dir . basename( $folder ) );
		}
	}

	/**
	 * Copy folder and contents to the backup directory
	 * 
	 * @access private
	 * @since 1.0
	 * @param string $source  Source path
	 * @param string $dest    Destination path
	 * @return void
	 */
	private function copy_folder( $source, $dest ) {
		$source = trailingslashit( $source );
		$dest = trailingslashit( $dest );

	    if ( $handle = opendir( $source ) ) {
	    	if ( !is_dir( $dest ) )
	    		mkdir( $dest, 0755 );
	        /* This is the correct way to loop over the directory. */
		    while (false !== ($entry = readdir($handle))) {
		    	if ( $entry != '.' && $entry != '..' && $entry != '.DS_Store' ) {
		    		if ( ! is_dir( $source . $entry ) ) {
		    			copy( $source . $entry, $dest . $entry );
		    		} else {
		    			$tmp_src = $source . $entry;
		    			$tmp_dst = $dest . $entry;
		    			self::$instance->copy_folder( $tmp_src, $tmp_dst );
		    		}
		    	}
		    }
		}
	    closedir( $handle ); 
	}

	/**
	 * Restore our file folders
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function restore_folders() {
		// Loop through our watched folders and back each one up.
		foreach( self::$instance->watched_folders as $folder ) {
			// Delete the current contents of our folders.
			self::$instance->delete_folder_contents( $folder );
			// Copy the files from our backup directory
			self::$instance->copy_folder( self::$instance->upload_dir . basename( $folder ), $folder );
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
		$user_id = get_current_user_id();
		update_option( 'ninja_demo_user', $user_id );
		update_option( 'ninja_demo_state', 'thawed' );
		update_option( 'ninja_demo_schedule', 'hourly' );
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

			$pages = apply_filters( 'nd_prevent_access', array(
				'themes.php',
			) );

			// Remove our menu links.
			$menu_links = apply_filters( 'nd_hide_menu_pages', array(
				'plugins.php',
				'users.php',
				'tools.php',
				'options-general.php',
				'options.php',
			) );

			$submenu_links = apply_filters( 'nd_hide_submenu_pages', array(
				array( 'parent' => 'index.php', 'child' => 'update-core.php' ),

				array( 'parent' => 'themes.php', 'child' => 'theme-editor.php' ),
				array( 'parent' => 'themes.php', 'child' => 'customize.php' ),
				array( 'parent' => 'themes.php', 'child' => 'nav-menus.php' ),
				array( 'parent' => 'themes.php', 'child' => 'custom-header' ),
				array( 'parent' => 'themes.php', 'child' => 'custom-background' ),

				array( 'parent' => 'users.php', 'child' => 'add-new.php' ),
				array( 'parent' => 'users.php', 'child' => 'profile.php' ),

				array( 'parent' => 'options-general.php', 'child' => 'options-writing.php' ),
				array( 'parent' => 'options-general.php', 'child' => 'options-reading.php' ),
				array( 'parent' => 'options-general.php', 'child' => 'options-discussion.php' ),
				array( 'parent' => 'options-general.php', 'child' => 'options-media.php' ),
				array( 'parent' => 'options-general.php', 'child' => 'options-permalink.php' ),
				array( 'parent' => 'options-general.php', 'child' => 'limit-login-attempts' ),
			) );

			foreach( $menu_links as $page ) {
				remove_menu_page( $page );
				$pages[] = $page;
			}

			foreach( $submenu_links as $page ) {
				remove_submenu_page( $page['parent'], $page['child'] );
				$pages[] = $page['child'];
			}

  			// If we are on any of these pages, then throw an error.
  			if ( in_array( $pagenow, $pages ) )
  				wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
		}
	}

	/**
	 * Put up a maintenance error if we are thawed and not the administrator
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function maintenance_mode() {
		$current_state = get_option( 'ninja_demo_state' );
		if ( $current_state == 'thawed' && ! self::$instance->is_admin_user() )
			wp_die( __( 'This demo site is currently in maintenance mode. Please return soon.', 'ninja-demo' ) );
	}

	/**
	 * Check to see if the current user is our admin user
	 * 
	 * @access private
	 * @since 1.0
	 * @return bool
	 */
	private function is_admin_user() {
		$admin_id = get_option( 'ninja_demo_user' );
		$user_id = get_current_user_id();
		return $admin_id == $user_id;
	}

	/**
	 * Thaw our db before an update
	 * 
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function before_update( $bool, $hook_extra ) {
		self::$instance->thaw();
		return true;
	}

	/**
	 * Re-freeze our db after an update
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function after_update( $bool, $hook_extra, $result ) {
		self::$instance->freeze();
		return true;
	}

	/**
	 * Display a notice with our current database state
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_notice() {
		global $pagenow;
		
		// Make sure we aren't already looking at the ninja-demo plugin
		if ( isset ( $_REQUEST['page'] ) && $_REQUEST['page'] == 'ninja-demo' )
			return false;
		if ( self::$instance->is_admin_user() ) {
			$current_state = get_option( 'ninja_demo_state' );
			$current_url = urlencode( add_query_arg( array() ) );
			if ( $current_state == 'frozen' ) {
				$msg = __( 'Your site is currently <strong>frozen</strong>. No changes will be retained.', 'ninja-demo' );
				$msg .= ' <a href="' . add_query_arg( array( 'tab' => 'db', 'action' => 'thaw', 'redirect' => $current_url ), menu_page_url( 'ninja-demo', false ) ) . '" class="button-secondary" name="ninja_demo_thaw">' . __( 'Thaw Site', 'ninja-demo' ) . '</a>';
			} else {
				$msg = __( 'Your site is currently <strong>thawed</strong>. All changes will be retained.', 'ninja-demo' );
				$msg .= ' <a href="' . add_query_arg( array( 'tab' => 'db', 'action' => 'freeze', 'redirect' => $current_url ), menu_page_url( 'ninja-demo', false ) ) . '" class="button-secondary" name="ninja_demo_freeze">'. __( 'Freeze Site', 'ninja-demo' ) .'</a>';
			}
			$url = add_query_arg( array( 'tab' => 'db' ), menu_page_url( 'ninja-demo', false ) );

			$msg .= ' <a href="' . add_query_arg( array( 'tab' => 'db', 'action' => 'restore', 'redirect' => $current_url ), menu_page_url( 'ninja-demo', false ) ) . '" class="button-secondary" name="ninja_demo_restore">' . __( 'Restore Database', 'ninja-demo' ) .'</a>';
			?>
			<div class="updated">
	       		<p><?php echo $msg; ?></p>
		    </div>
		    <?php			
		}
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
 * The main function responsible for returning the one true Ninja_Demo
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd = EDD(); ?>
 *
 * @since 1.0
 * @return object The highlander Ninja_Demo Instance
 */
function Ninja_Demo() {
	return Ninja_Demo::instance();
}

// Get EDD Running
Ninja_Demo();