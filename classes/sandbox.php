<?php
/**
 * Ninja_Demo_Sandbox
 *
 * This class handles the creation, deletion, and interacting with Sandboxes.
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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Ninja_Demo_Sandbox {

	/**
	 * @var Store our database information.
	 */
	var $db_host = DB_HOST;
	var $db_name = DB_NAME;
	var $db_port = '';
	var $db_user = DB_USER;
	var $db_pass = DB_PASSWORD;

	/**
	 * @var Class Globals
	 */
	var $target_id = '';
	var $status = '';
	var $global_tables;
	var $site_address;

	/**
	 * Get things started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'purge' ) );
		add_action( 'nd_hourly', array( $this, 'check_purge' ) );
		add_action( 'init', array( $this, 'prevent_clone_check' ) );
		add_action( 'init', array( $this, 'deleted_site_check' ) );
		add_action( 'init', array( $this, 'reset_listen' ) );
		add_action( 'init', array( $this, 'update_state' ) );

		add_action( 'admin_bar_menu', array( $this, 'add_menu_bar_reset' ), 999 );

		//define which tables to skip by default when cloning root site
		$this->global_tables = apply_filters( 'nd_global_tables', array(
			'blogs','blog_versions','registration_log','signups','site','sitemeta', //default multisite tables
			'usermeta','users', //don't copy users
			'bp_.*', //buddypress tables
			'3wp_broadcast_.*', //3wp broadcast tables
			'demo_ip_lockout', // Ninja Demo IP lockout table
			'nd_ip_lockout',
		) );

		if ( strpos( $this->db_host, ':' ) ) {
			$server = explode( ':', $this->db_host );
			$this->db_host = $server[0];
			$this->db_port = $server[1];
		}
	}

	/**
	 * Check to see if we have disabled clone creation. If so, disable the main site, leaving sandboxes alone
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function prevent_clone_check() {
		$current_url = add_query_arg( array() );
		if ( Ninja_Demo()->settings['offline'] == 1 && ! Ninja_Demo()->is_admin_user() && ( ( ! Ninja_Demo()->is_sandbox() && strpos ( $current_url, '/wp-admin/' ) === false && strpos ( $current_url, 'wp-login.php' ) === false ) || Ninja_Demo()->is_sandbox() ) )
			wp_die( __( apply_filters( 'nd_offline_msg', 'The demo is currently offline.' ), 'ninja-demo' ) );
	}

	/**
	 * Check to see if any of our blogs have been marked as "deleted."
	 * If so, undelete them.
	 *
	 * @access public
	 * @since 1.0.7
	 * @return void
	 */
	public function deleted_site_check() {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE deleted = '1'"
	    );
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'nd_sandbox' ) != 1 ) {
	    		$wpdb->update( $wpdb->blogs, array( 'deleted' => '0' ) , array( 'blog_id' => $blog->blog_id ) );
	    	}
	    }
	}

	/**
	 * Count our current sandboxes
	 *
	 * @access public
	 * @since 1.0
	 * @return int $count
	 */
	public function count( $source_id = '' ) {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1"
	    );
	    $count = 0;
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'nd_sandbox' ) == 1 ) {
	    		if ( $source_id == '' ) {
	    			$count++;
	    		} else if ( $source_id == get_blog_option( $blog->blog_id, 'nd_source_id' ) ) {
	    			$count++;
	    		}

	    	}
	    }
		return $count;
	}

	/**
	 * Function to get our sandbox key (the random string associated with the sandbox)
	 *
	 * @access public
	 * @since 1.0.4
	 * @return string $id
	 */
	public function get_key( $blog_id = '' ) {
		if ( $blog_id == '' ) {
			if ( Ninja_Demo()->is_sandbox() ) {
				return get_option( 'nd_sandbox_id' );
			} else {
				return false;
			}
		} else {
			return get_blog_option( $blog_id, 'nd_sandbox_id' );
		}
	}

	/**
	 * Delete all sandboxes
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function delete_all( $source_id = '' ) {
		global $wpdb;
		// Get a list of all of our sandboxes
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1"
	    );
	    foreach ( $blogs as $blog ) {
	    	if ( get_blog_option( $blog->blog_id, 'nd_sandbox' ) == 1 ) {
	    		if ( $source_id != '' ) {
	    			if ( get_blog_option( $blog->blog_id, 'nd_source_id' ) == $source_id ) {
						$this->delete( $blog->blog_id );
					}
	    		} else {
	    			$this->delete( $blog->blog_id );
	    		}
	    	}
		}
	}

	/**
	 * Delete a sandbox
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function delete( $blog_id, $drop = true, $reset = false ) {
		global $wpdb;

		require_once ( ABSPATH . 'wp-admin/includes/ms.php' );

		// Make sure that our blog_id is an integer.
		$blog_id = intval( $blog_id );

		// Make sure that we're on a sandbox
		if ( get_blog_option( $blog_id, 'nd_sandbox' ) != 1 )
			return false;

		$source_id = get_option( 'nd_source_id' );

		$switch = false;
		if ( get_current_blog_id() != $blog_id ) {
			$switch = true;
			switch_to_blog( $blog_id );
		}

		// Grab all the tables that have our prefix.

		$blog = get_blog_details( $blog_id );
		/**
		 * Fires before a sandbox is deleted.
		 *
		 * @param int  $blog_id The blog ID.
		 * @param bool $drop    True if blog's table should be dropped. Default is false.
		 */
		do_action( 'nd_delete_sandbox', $blog_id );

		$users = get_users( array( 'blog_id' => $blog_id, 'fields' => 'ids' ) );

		// Remove users from this blog.
		if ( ! empty( $users ) ) {
			foreach ( $users as $user_id ) {
				wpmu_delete_user( $user_id );
			}
		}

		update_blog_status( $blog_id, 'deleted', 1 );

		$current_site = get_current_site();

		// Don't destroy the initial, main, or root blog.
		if ( $drop && ( 1 == $blog_id || is_main_site( $blog_id ) || ( $blog->path == $current_site->path && $blog->domain == $current_site->domain ) ) )
			$drop = false;

		if ( $drop ) {

	   		$drop_tables = array();
	   		$tables = $wpdb->get_results( 'SHOW TABLES LIKE "' . $wpdb->prefix .'%"', ARRAY_A );
	   		foreach( $tables as $table ) {
	   			foreach( $table as $name ) {
	   				$drop_tables[] = $name;
	   			}
	   		}

			/**
			 * Filter the tables to drop when the blog is deleted.
			 *
			 * @since 1.0
			 *
			 * @param array $tables  The blog tables to be dropped.
			 * @param int   $blog_id The ID of the blog to drop tables for.
			 */
			$drop_tables = apply_filters( 'wpmu_drop_tables', $drop_tables, $blog_id );

			foreach ( (array) $drop_tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
			}

			$wpdb->delete( $wpdb->blogs, array( 'blog_id' => $blog_id ) );

			// Clear out junk left in the wp_usermeta table related to this blog id.
			$wpdb->query( "DELETE FROM `" . $wpdb->usermeta . "` WHERE meta_key LIKE '" . $wpdb->prefix . "%'" );
			// Clear out the Registration log.
			$wpdb->query( $wpdb->prepare( "DELETE FROM `" . $wpdb->registration_log . "` WHERE blog_id = %d", $blog_id ) );

			$uploads = wp_upload_dir();
			/**
			 * Filter the upload base directory to delete when the blog is deleted.
			 *
			 * @since 1.0
			 *
			 * @param string $uploads['basedir'] Uploads path without subdirectory. @see wp_upload_dir()
			 * @param int    $blog_id            The blog ID.
			 */
			$dir = apply_filters( 'wpmu_delete_blog_upload_dir', $uploads['basedir'], $blog_id );
			$dir = rtrim( $dir, DIRECTORY_SEPARATOR );
			$top_dir = $dir;
			$stack = array($dir);
			$index = 0;

			while ( $index < count( $stack ) ) {
				# Get indexed directory from stack
				$dir = $stack[$index];

				$dh = @opendir( $dir );
				if ( $dh ) {
					while ( ( $file = @readdir( $dh ) ) !== false ) {
						if ( $file == '.' || $file == '..' )
							continue;

						if ( @is_dir( $dir . DIRECTORY_SEPARATOR . $file ) )
							$stack[] = $dir . DIRECTORY_SEPARATOR . $file;
						else if ( @is_file( $dir . DIRECTORY_SEPARATOR . $file ) )
							@unlink( $dir . DIRECTORY_SEPARATOR . $file );
					}
					@closedir( $dh );
				}
				$index++;
			}

			$stack = array_reverse( $stack ); // Last added dirs are deepest
			foreach( (array) $stack as $dir ) {
				if ( $dir != $top_dir)
				@rmdir( $dir );
			}
			@rmdir( $dir );

			clean_blog_cache( $blog );
		}

		// Delete our stored $_SESSION variable
		if ( isset( $_SESSION[ 'nd_sandbox_' . $source_id ] ) ) {
			unset( $_SESSION[ 'nd_sandbox_' . $source_id ] );
    	}

		// Logout our current user
		if ( ! $reset && ! Ninja_Demo()->is_admin_user() )
			wp_logout();

		if ( $switch )
			restore_current_blog();
	}

	/**
	 * Add our purge action for execution
	 *
	 * @access public
	 * @since 1.0.9
	 * @return void
	 */
	public function check_purge() {
		add_action( 'wp_footer', array( $this, 'purge' ) );
	}

	/**
	 * Check to see if any of our sandboxes need to be purged.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function purge() {
		global $wpdb;

		// Get a list of all of our sandboxes
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE blog_id != 1" );

	    $sites = array();
	    $redirect = false;
	    foreach ( $blogs as $blog ) {

	    	if ( get_blog_option( $blog->blog_id, 'nd_sandbox' ) == 1 ) {
		   		// If we've been alive longer than the lifespan, delete the sandbox.
		   		if ( apply_filters( 'nd_purge_sandbox', $this->has_expired( $blog->blog_id ), $blog->blog_id ) ) {
		   			// Check to see if we're currently looking at the blog to be deleted.
					if ( $blog->blog_id == get_current_blog_id() ) {
						$redirect = true;
						$source_id = get_blog_option( $blog->blog_id, 'nd_source_id' );
					}
					$this->delete( $blog->blog_id );
		   		}
	    	}

		}

		if ( $redirect ) {
			wp_redirect( get_blog_details( 1 )->siteurl );
			exit;
		}
	}

	/**
	 * Check to see if this sandbox has expired
	 *
	 * @access public
	 * @since 1.0
	 * @return bool;
	 */
	public function has_expired( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$idle_limit = apply_filters( 'nd_sandbox_lifespan', 900, $blog_id ); // 900 seconds = 15 minutes
		$idle_time = current_time( 'timestamp' ) - strtotime( get_blog_details( $blog_id )->last_updated );

		if ( $idle_time >= $idle_limit ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check to see if a sandbox is alive
	 *
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_active( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$details = get_blog_details( $blog_id );
		if ( $details !== false ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Add an item to the menu bar for non-network admin users that allows them to reset their sandbox
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_bar_reset( $wp_admin_bar ) {
		if ( Ninja_Demo()->is_sandbox() ) {
			$url = add_query_arg( array( 'reset_sandbox' => 1 ) );
			$wp_admin_bar->add_menu( array(
		        'id'   => 'reset-site',
		        'meta' => array(),
		        'title' => __( 'Reset Site Content', 'ninja-demo' ),
		        'href' => wp_nonce_url( $url, 'ninja_demo_reset_sandbox', 'ninja_demo_sandbox' ) ) );
		}
	}

	/**
	 * Update our sandbox active state when we load a page.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function update_state() {
		global $wpdb;
		if ( Ninja_Demo()->is_sandbox() )
			$wpdb->update( $wpdb->blogs, array( 'last_updated' => current_time( 'mysql' ) ), array( 'blog_id' => get_current_blog_id() ) );
	}

	/**
	 * Listen for the sandbox reset $_REQUEST data
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function reset_listen() {

		// Bail if our $_POST value isn't set.
		if ( ! isset ( $_GET['reset_sandbox'] ) || $_GET['reset_sandbox'] != 1 )
			return false;

		// Bail if we don't have a nonce
		if ( ! isset ( $_GET['ninja_demo_sandbox'] ) )
			return false;

		// Bail if our nonce isn't correct
		if ( ! wp_verify_nonce( $_GET['ninja_demo_sandbox'], 'ninja_demo_reset_sandbox' ) )
			return false;

		$this->reset();
	}

	/**
	 * "Reset" a user's sandbox by removing the current one and creating a new one.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function reset() {
		// Get our source id
		$source_id = get_option( 'nd_source_id' );
		// Delete our current sandbox
		$this->delete( get_current_blog_id(), true, true );
		// Switch to our source blog
		switch_to_blog( $source_id );
		// Create a new sandbox
		$this->create( $source_id );
	}

	/**
	 * Create our sandbox
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function create( $source_id, $target_site_name = '' ) {
		global $wpdb, $report, $count_tables_checked, $count_items_checked, $count_items_changed, $current_site, $wp_version;

		// Declare the locals that need to be available throughout the function:
		$target_id = '';
		$target_subd = '';
		$target_site = '';

		// Our login settings might not be based upon this blog.
		$nd_settings = get_blog_option( $source_id, 'ninja_demo' );

		//  Start TIMER
		//  -----------
		$stimer = explode( ' ', microtime() );
		$stimer = $stimer[1] + $stimer[0];
		//  -----------

		$target_site = get_blog_details( $source_id )->blogname;
		if ( $target_site_name == '' ) {
			$target_site_name = $this->generate_site_name();
		}

		/**
	     * Creating our user for this sandbox.
	     */

		$login_role = isset ( $nd_settings['login_role'] ) ? $nd_settings['login_role'] : 'administrator';

	    // Get our username.
	    $user_name = apply_filters( 'nd_user_name' , $login_role . '-' . $target_site_name );

	    // Get our user email address.
	    $user_email = apply_filters( 'nd_user_email' , $login_role . '@' . $target_site_name .'.com' );

	    // Generate a random password.
	    $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
		// Create our user.
		$user_id = wp_create_user( $user_name, $random_password, $user_email );

		// Do not allow duplicate users, this may be the case if we have added a filter on the username or email
		if( is_wp_error( $user_id ) ){
			wp_redirect(
				add_query_arg(
					array(
						'error' => 'true',
						'errorcode' => urlencode( $user_id->get_error_code() ),
						'errormsg' => urlencode( $user_id->get_error_message() ),
						'updated' => false
					),
					wp_get_referer()
				)
			);
			die;
		}

		if ( $login_role == 'administrator' ) {
			$owner_user_id = $user_id;
		} else {
			// Get our username.
		    $user_name = 'administrator-' . $target_site_name;
		    // Get our user email address.
		    $user_email = 'administrator@' . $target_site_name .'.com';
		    // Generate a random password.
		    $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
			// Create our user.
			$owner_user_id = wp_create_user( $user_name, $random_password, $user_email );
			remove_user_from_blog( $owner_user_id, $source_id );
		}

		// CREATE THE SITE

		// Create site
		$this->create_site( $target_site_name, $target_site, $source_id, $owner_user_id );

		// RUN THE CLONING
		Ninja_Demo()->logs->dlog( 'RUNNING NS Cloner version: ' . ND_PLUGIN_VERSION . ' <br /><br />' );

		// don't want the trailing slash in path just in case there are replacements that don't have it
		$source_subd = untrailingslashit( get_blog_details($source_id)->domain . get_blog_details($source_id)->path );

		$source_site = get_blog_details($source_id)->blogname;

		$target_id = $this->target_id;
		$target_subd = get_current_site()->domain . get_current_site()->path . $target_site_name;

		// prevent the source site name from being contained in the target domain / directory, since the search/replaces will wreak havoc in that scenario
		if( stripos($target_subd, $source_site) !== false ) {
				wp_redirect( add_query_arg(
					array('error' => 'true',
						'errorcode' => urlencode( $user_id->get_error_code() ),
						'errormsg' => urlencode( __( "The Source Site Name ($source_site) may not appear in the Target Site Domain ($target_subd) or data corruption will occur. You might need to edit the Source Site's Name in Settings > General, or double-check / change your field input values.", 'ns_cloner' ) ),
						'updated' => false),
					wp_get_referer() ) );
				die;
		} else{
			//configure all the properties
			$source_pre = $source_id==1? $wpdb->base_prefix : $wpdb->base_prefix . $source_id . '_';	// the wp id of the source database
			$target_pre = $wpdb->base_prefix . $target_id . '_';	// the wp id of the target database

			Ninja_Demo()->logs->dlog ( 'Source Prefix: <b>' . $source_pre . '</b><br />' );
			Ninja_Demo()->logs->dlog ( 'Target Prefix: <b>' . $target_pre . '</b><br />' );

			//clone
			$this->run_clone( $source_pre, $target_pre );
			//$this->insert_query( $this->insert_query );
		}

		// RUN THE STANDARD REPLACEMENTS
		$target_pre = $wpdb->base_prefix . $target_id . '_';	// the wp id of the target database

		//build replacement array
		//new-site-specific replacements
		$replace_array[$source_subd] = $target_subd;
		$replace_array[$source_site] = $target_site;

		// REPLACEMENTS FOR ROOT SITE CLONING
		// uploads location
		$main_uploads_target = '';
		if( 1 == $source_id ){
			switch_to_blog( 1 );
			$main_uploads_info = wp_upload_dir();
			restore_current_blog();
			//$main_uploads_dir = str_replace( get_site_url('/'), '', $main_uploads_info['baseurl'] );
			$main_uploads_dir = $main_uploads_info['baseurl'];
			$main_uploads_replace = '';

			$main_uploads_target = WP_CONTENT_DIR . '/uploads/sites/' . $target_id;
			$main_uploads_replace = $main_uploads_info['baseurl'] . '/sites/' . $target_id;

			$replace_array[$main_uploads_dir] = $main_uploads_replace;

			// debugging ----------------------------
			$report .= 'Search Source Dir: <b>' . $main_uploads_dir . '</b><br />';
			$report .= 'Replace Target Dir: <b>' . $main_uploads_replace . '</b><br />';
			// --------------------------------------
			//reset the option_name = wp_#_user_roles row in the wp_#_options table back to the id of the target site
			$replace_array[$wpdb->base_prefix . 'user_roles'] = $wpdb->base_prefix . $target_id . '_user_roles';
		} else {
			// REPLACEMENTS FOR NON-ROOT SITE CLONING
			// uploads location
			$replace_array['/sites/' . $source_id . '/'] = '/sites/' . $target_id . '/';
			//reset the option_name = wp_#_user_roles row in the wp_#_options table back to the id of the target site
			$replace_array[$wpdb->base_prefix . $source_id . '_user_roles'] = $wpdb->base_prefix . $target_id . '_user_roles';
		}

		//replace
		Ninja_Demo()->logs->dlog ( 'running replace on Target table prefix: ' . $target_pre . '<br />' );
		foreach( $replace_array as $search_for => $replace_with) {
			Ninja_Demo()->logs->dlog ( 'Replace: <b>' . $search_for . '</b> >> With >> <b>' . $replace_with . '</b><br />' );
		}

		$this->run_replace( $target_pre, $replace_array );

		// COPY ALL MEDIA FILES
		// get the right paths to use
		// handle for uploads location when cloning root site
		$src_blogs_dir = $this->get_upload_folder($source_id);

		if( 1 == $source_id ){
			$dst_blogs_dir = $main_uploads_target;
		} else {
			$dst_blogs_dir = $this->get_upload_folder($this->target_id);
		}

		//fix for paths on windows systems
		if (strpos($src_blogs_dir,'/') !== false && strpos($src_blogs_dir,'\\') !== false ) {
			$src_blogs_dir = str_replace('/', '\\', $src_blogs_dir);
			$dst_blogs_dir = str_replace('/', '\\', $dst_blogs_dir);
		}
		if (is_dir($src_blogs_dir)) {
			//--------------------------------------------------------------------------
			// dev and testing only, comment out when not in use:
			//$num_files = 0;
			//$report .= 'From: <b>' . $src_blogs_dir . '</b><br />';
			//$report .= 'To: <b>' . $dst_blogs_dir . '</b><br />';
			//--------------------------------------------------------------------------

			$num_files = $this->recursive_file_copy($src_blogs_dir, $dst_blogs_dir, 0);
			$report .= 'Copied: <b>' . $num_files . '</b> folders and files!<br />';
			Ninja_Demo()->logs->dlog ('Copied: <b>' . $num_files . '</b> folders and files!<br />');
			Ninja_Demo()->logs->dlog ('From: <b>' . $src_blogs_dir . '</b><br />');
			Ninja_Demo()->logs->dlog ('To: <b>' . $dst_blogs_dir . '</b><br />');
		}
		else {
			$report .= '<span class="warning-txt-title">Could not copy files</span><br />';
			$report .= 'From: <b>' . $src_blogs_dir . '</b><br />';
			$report .= 'To: <b>' . $dst_blogs_dir . '</b><br />';
		}
		// ---------------------------------------------------------------------------------------------------------------


		//Switch to our new blog.
		switch_to_blog( $this->target_id );

		$_SESSION[ 'nd_sandbox_' . $source_id ] = $this->target_id;

		// This sets the option to discourage search engines from indexing sandboxes within a demo.
	    update_blog_option( $this->target_id, 'blog_public', 0 );

	    // Set an option with our random site key
	    update_blog_option( $this->target_id, 'nd_sandbox_id', $target_site_name );

	    // Set an option marking this as a sandbox
	    update_blog_option( $this->target_id, 'nd_sandbox', 1 );

	    // Set an option that marks this sandbox's source id
	    update_blog_option( $this->target_id, 'nd_source_id', $source_id );

	    // Store our user name and password.
	    update_blog_option( $this->target_id, 'nd_user', $user_name );
	    update_blog_option( $this->target_id, 'nd_password', $random_password );

		// Login our user.
		add_user_to_blog( $this->target_id, $user_id, $login_role );
		remove_user_from_blog( $user_id, $source_id );
		wp_clear_auth_cookie();
	    wp_set_auth_cookie( $user_id, true );
	    wp_set_current_user( $user_id );

	    // Set our "last updated" time to the current time.
	    $wpdb->update( $wpdb->blogs, array( 'last_updated' => current_time( 'mysql' ) ), array( 'blog_id' => $this->target_id ) );

	    // Get a list of our active plugins.
	    $plugins = get_option( 'active_plugins' );

	    if ( ! empty( $plugins ) ) {
		    foreach( $plugins as $plugin ) {
			    if ( apply_filters( 'nd_activate_plugin', false, $plugin ) ) {
					deactivate_plugins( $plugin );
					activate_plugin( $plugin );
				}
		    }
	    }


	    // Add our IP Lockout for 10 minutes. This will prevent the user from creating a new sandbox until the lockout has expired.
	    // $time_expires = apply_filters( 'nd_create_lockout_time', strtotime( '+10 minutes', current_time( 'timestamp' ) ) );
	    // Ninja_Demo()->ip->lockout_ip( $_SERVER['REMOTE_ADDR'], $time_expires );

		do_action( 'nd_create_sandbox', $this->target_id );

		// Report

		//echo '<p style="margin:auto; text-align:center">';
		Ninja_Demo()->logs->dlog ( $report );

		//  End TIMER
		//  ---------
		$etimer = explode( ' ', microtime() );
		$etimer = $etimer[1] + $etimer[0];
		Ninja_Demo()->logs->log ( $target_subd . " cloned in " . ($etimer-$stimer) . " seconds."  );
		Ninja_Demo()->logs->dlog ( "Entire cloning process took: <strong>" . ($etimer-$stimer) . "</strong> seconds."  );
		//  ---------

		// Update our site url.
		update_blog_option( $this->target_id, 'siteurl', $this->site_address );
		update_blog_option( $this->target_id, 'home', $this->site_address );

		wp_redirect( apply_filters( 'nd_create_redirect', $this->site_address, $this->target_id ) );
		die();
	}

	/**
	 * Return a random alphanumeric string to serve as our site name.
	 *
	 * @access private
	 * @since 1.0
	 * @return string $key
	 */
	private function generate_site_name() {
		$key = Ninja_Demo()->random_string();

	    $site_id = get_id_from_blogname( $key );

	    if ( ! empty( $site_id ) ) {
	    	return $this->generate_site_name( $length );
	    } else {
	    	return $key;
	    }
	}

	/**
	 * Create a site for our sandbox
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function create_site( $sitename, $sitetitle, $source_id, $user_id ) {
		global $wpdb, $current_site;
		$current_user = wp_get_current_user();

		$base = PATH_CURRENT_SITE;

		$tmp_domain = strtolower( esc_html( $sitename ) );

		if( constant( 'VHOST' ) == 'yes' ) {
			$tmp_site_domain = $tmp_domain . '.' . $current_site->domain;
			$tmp_site_path = $base;
		} else {
			$tmp_site_domain = $current_site->domain;
			$tmp_site_path = $base . $tmp_domain . '/';
		}

		$create_site_name = $sitename;
		$create_site_title = $sitetitle;

		$site_id = get_id_from_blogname( $create_site_name );

		// create site and don't forget to make public:
		$meta['public'] = 1;
		$site_id = wpmu_create_blog( $tmp_site_domain, $tmp_site_path, $create_site_title, $user_id , $meta, $current_site->id );

		if( ! is_wp_error( $site_id ) ) {
			Ninja_Demo()->logs->log( 'Site: ' . $tmp_site_domain . $tmp_site_path . ' created!' );
			//assign target id for cloning and replacing
			$this->target_id = $site_id;
		} else {
			Ninja_Demo()->logs->log( 'Error creating site: ' . $tmp_site_domain . $tmp_site_path . ' - ' . $site_id->get_error_message() );
		}

		if ( is_ssl() ) {
			$protocol = 'https://';
		} else {
			$protocol = 'http://';
		}

		$this->site_address = $protocol . $tmp_site_domain . $tmp_site_path;

		// Update our site url.
		update_blog_option( $this->target_id, 'siteurl', $this->site_address );
		update_blog_option( $this->target_id, 'home', $this->site_address );

		// Our login settings might not be based upon this blog.
		$nd_settings = get_blog_option( $source_id, 'ninja_demo' );
		if ( isset ( $nd_settings['auto_login'] )  && isset ( $nd_settings ) ) {
			if ( $nd_settings['auto_login'] != '' && $nd_settings['login_role'] != '' ) {
				$user = $nd_settings['auto_login'];
				$role = $nd_settings['login_role'];
				add_user_to_blog( $this->target_id, $user, $role );
			}
		}
	}

	/**
	 * Clone the data from our main site to our newly created sandbox site
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */

	private function run_clone( $source_prefix, $target_prefix ) {
		global $report, $wpdb;

		//get list of source tables when cloning root
		if( $source_prefix == $wpdb->base_prefix ){
			$tables = $wpdb->get_results('SHOW TABLES');
			$global_table_pattern = "/^$wpdb->base_prefix(" .implode('|',$this->global_tables). ")$/";
			$table_names = array();
			foreach($tables as $table){
				$table = (array)$table;
				$table_name = array_pop( $table );
				$is_root_table = preg_match( "/$wpdb->prefix(?!\d+_)/", $table_name );
				if($is_root_table && !preg_match($global_table_pattern,$table_name)){
					array_push($table_names, $table_name);
				}
			}
			$SQL = "SHOW TABLES WHERE `Tables_in_" . $this->db_name . "` IN('" . implode( "','", $table_names ). "')";
		} else { //get list of source tables when cloning non-root
			// MUST ESCAPE '_' characters otherwise they will be interpreted as wildcard
			// single chars in LIKE statement and can really hose up the database
			$SQL = 'SHOW TABLES LIKE \'' . str_replace( '_', '\_', $source_prefix ) . '%\'';
		}

		$tables_list = $wpdb->get_results( $SQL, ARRAY_N );

		$num_tables = 0;

		if ( isset ( $tables_list[0] ) && ! empty ( $tables_list[0] ) ) {

			// Get a list of our current sandbox sites
			if ( function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {
				$sandboxes = get_sites();        // WP 4.6
			} else {
				$sandboxes = wp_get_sites();     // WP < 4.6
			}

			foreach ( $tables_list as $tables ) {
				$source_table = $tables[0];

				// Check to see if this table belongs to another clone.
				foreach ( $sandboxes as $s ) {
					if ( is_object( $s ) ) {
						$blog_id = $s->blog_id;     // WP 4.6
					} else {
						$blog_id = $s['blog_id'];   // WP < 4.6
					}
					if ( Ninja_Demo()->is_sandbox( $blog_id ) && strpos( $source_table, $wpdb->base_prefix . $blog_id ) !== false ) {
						continue 2;
					}
				}

				$pos = strpos( $source_table, $source_prefix );
				if ( $pos === 0 ) {
				    $target_table = substr_replace( $source_table, $target_prefix, $pos, strlen( $source_prefix ) );
				}

				$num_tables++;
				//run cloning on current table to target table
				if ($source_table != $target_table) {
					Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );
					Ninja_Demo()->logs->dlog ( 'Cloning source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') to Target table: <b>' . $target_table . '</b><br />' );
					Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );

					$this->clone_table( $source_table, $target_table );
					
				}
				else {
					Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
					Ninja_Demo()->logs->dlog ( 'Source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') and Target table: <b>' . $target_table . ' are the same! SKIPPING!!!</b><br />');
					Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
				}
			}

		}
		else {
			Ninja_Demo()->logs->dlog ( 'no data for sql - ' . $SQL );
		}

		if (isset($_POST['is_debug'])) { Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br /><br />'); }
		$report .= 'Cloned: <b>' .$num_tables . '</b> tables!<br/ >';
		Ninja_Demo()->logs->dlog('Cloned: <b>' .$num_tables . '</b> tables!<br/ >');
	}

	/**
	 * Add backqouotes to tables and db-names in SQL queries. Example from phpMyAdmin.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function backquote( $a_name ) {

		if (!empty($a_name) && $a_name != '*') {
			if (is_array($a_name)) {
				$result = array();
				reset($a_name);
				while(list($key, $val) = each($a_name)) {
					$result[$key] = '`' . $val . '`';
				}
				return $result;
			} else {
				return '`' . $a_name . '`';
			}
		} else {
			return $a_name;
		}
	}

	/**
	 * Better addslashes for SQL queries. Example from phpMyAdmin.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	function sql_addslashes($a_string = '', $is_like = FALSE) {

		if ($is_like) {
			$a_string = str_replace('\\', '\\\\\\\\', $a_string);
		} else {
			$a_string = str_replace('\\', '\\\\', $a_string);
		}
		$a_string = str_replace('\'', '\\\'', $a_string);

		return $a_string;
	}

	/**
	 * Reads the Database table in $source_table and executes SQL Statements for cloning it to $target_table.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function clone_table( $source_table, $target_table ) {
		global $wpdb;

		$query_count = Ninja_Demo()->settings['query_count'];

		$sql_statements = '';

		$query = "DROP TABLE IF EXISTS " . $this->backquote( $target_table );

		if ( isset( $_POST['is_debug'] ) )
			Ninja_Demo()->logs->dlog ( $query . '<br /><br />');

		$result = $wpdb->query( $query );
		if ( $result == false )
			Ninja_Demo()->logs->dlog ( '<b>ERROR</b> dropping table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );

		// Table structure - Get table structure
		$query = "SHOW CREATE TABLE " . $this->backquote( $source_table );
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( $result == false ) {
			Ninja_Demo()->logs->dlog ( '<b>ERROR</b> getting table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );
		} else {
			if ( ! empty ( $result ) ) {
				$sql_statements .= $result[ 'Create Table' ];
			}
		}

		// Create cloned table structure
		$query = str_replace( $source_table, $target_table, $sql_statements );
		if ( isset( $_POST['is_debug'] ) )
			Ninja_Demo()->logs->dlog ( $query . '<br /><br />');

		$result = $wpdb->query( $query );
		if ( $result == false )
			Ninja_Demo()->logs->dlog ( '<b>ERROR</b> creating table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );

		// Table data contents - Get table contents
		$query = "SELECT * FROM " . $this->backquote( $source_table );
		$result = $wpdb->get_results( $query, ARRAY_N );

		$fields_cnt = 0;
		if ( $result == false ) {
			Ninja_Demo()->logs->dlog ( '<b>ERROR</b> getting table contents with sql - ' . $query . '<br /><b>SQL Error</b> - ' . $wpdb->last_error . '<br />' );
		} else {
			$fields_cnt = count( $result[0] );
			$rows_cnt   = $wpdb->num_rows;
		}

		// Checks whether the field is an integer or not
		for ( $j = 0; $j < $fields_cnt; $j++ ) {
			$type = $wpdb->get_col_info( 'type', $j );
			// removed ||$type == 'timestamp' from this check because it's invalid - timestamp values need ' ' surrounding to insert successfully
			if ( $type == 'tinyint' || $type == 'smallint' || $type == 'mediumint' || $type == 'int' || $type == 'bigint') {
				$field_num[ $j ] = true;
			} else {
				$field_num[ $j ] = false;
			}
		} // end for

		// Sets the scheme
		$entries = 'INSERT INTO ' . $this->backquote($target_table) . ' VALUES (';
		$search	= array("\x00", "\x0a", "\x0d", "\x1a"); 	//\x08\\x09, not required
		$replace	= array('\0', '\n', '\r', '\Z');

		$table_query = '';
		$table_query_count = 0;

		foreach( $result as $row ) {

			// Tracks the _transient_feed_ and _transient_rss_ garbage for exclusion
			$is_trans = false;
			for ($j = 0; $j < $fields_cnt; $j++) {
				if ( ! isset($row[ $j ] ) ) {
					$values[]     = 'NULL';
				} else if ( $row[ $j ] == '0' || $row[ $j ] != '') {
					// a number
					if ($field_num[$j]) {
						$values[] = $row[$j];
					}
					else {
						// don't include _transient_feed_ bloat
						if ( !$is_trans && false === strpos($row[$j],'_transient_') ) {
							$row[$j] = str_replace( "&#039;", "'", $row[$j] );
							$values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $row[$j] ) ) . "'";
						}
						else {
							$values[]     = "''";
							$is_trans = false;
						}
						// set $is_trans for the next field based on the contents of the current field
						(strpos($row[$j],'_transient_') === false && strpos($row[$j],'_transient_timeout_') === false) ? $is_trans = false : $is_trans = true;

					} //if ($field_num[$j])
				} else {
					$values[]     = "''";
				} // if (!isset($row[$j]))
			} // for ($j = 0; $j < $fields_cnt; $j++)

			// Execute current insert row statement
			$current_query = $entries . implode(', ', $values) . ');';
			$table_query .= $current_query;
			$table_query_count++;

			unset( $values );

			if ( $table_query_count >= $query_count ) {
				$this->insert_query( $table_query );
				$table_query_count = 0;
				$table_query = '';
			}

		} // while ($row = mysql_fetch_row($result))
		

		if ( ! empty( $table_query ) ) {
			$this->insert_query( $table_query );
		}

	}

	/**
	 * Run our insert statement.
	 *
	 * @access private
	 * @since 1.0.9
	 * @return void
	 */
	private function insert_query( $query ) {

		if ( $this->db_port != '' ) {
			$insert = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port );
		} else {
			$insert = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );
		}

		mysqli_set_charset( $insert, DB_CHARSET );

		$results = mysqli_multi_query( $insert, $query );
		if ( $results == FALSE ) { Ninja_Demo()->logs->dlog ( '<b>ERROR</b> inserting into table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $insert ) . '<br />'); }
		mysqli_close( $insert );
	}

	/**
	 * Replace references to our main site within our newly cloned sandbox site.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function run_replace( $target_prefix, $replace_array ) {
		global $report, $count_tables_checked, $count_items_checked, $count_items_changed, $wpdb;

		// First, get a list of tables
		// MUST ESCAPE '_' characters otherwise they will be interpreted as wildcard
		// single chars in LIKE statement and can really hose up the database
		$SQL = 'SHOW TABLES LIKE \'' . str_replace('_','\_',$target_prefix) . '%\'';

		$tables_list = $wpdb->get_results( $SQL, ARRAY_N );

		$num_tables = 0;

		if ( isset ( $tables_list[0] ) && ! empty ( $tables_list[0] ) ) {
			foreach ( $tables_list as $table ) {

				$table = $table[0];

				$count_tables_checked++;
				Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
				Ninja_Demo()->logs->dlog ( 'Searching table: <b>' . $table . '</b><br />');  // we have tables!
				Ninja_Demo()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');

				// ---------------------------------------------------------------------------------------------------------------

				$SQL = "DESCRIBE ".$table ;    // fetch the table description so we know what to do with it
				$fields_list = $wpdb->get_results( $SQL, ARRAY_A );

				// Make a simple array of field column names

				/*------------------------------------------------------------------------------------------------------------------
				*/
				$index_fields = "";  // reset fields for each table.
				$column_name = "";
				$table_index = "";
				$i = 0;

				foreach ( $fields_list as $field_rows ) {
					$column_name[ $i++ ] = $field_rows['Field'];
					if ( $field_rows['Key'] == 'PRI')
						$table_index[] = $field_rows['Field'] ;
				}

				// skip if no primary key
				if( empty( $table_index) ) continue;

				//    print_r ($column_name);
				//    print_r ($table_index);

				// now let's get the data and do search and replaces on it...

				$SQL = "SELECT * FROM ".$table;     // fetch the table contents
				$data = $wpdb->get_results( $SQL, ARRAY_A );

				if ( ! isset ( $data[0] ) || empty ( $data[0] ) ) {
					Ninja_Demo()->logs->dlog ("<br /><b>ERROR:</b> " . $wpdb->last_error . "<br/>$SQL<br/>"); }

				foreach ( $data as $row ) {

					// Initialize the UPDATE string we're going to build, and we don't do an update for each column...

					$need_to_update = false;
					$UPDATE_SQL = 'UPDATE '.$table. ' SET ';
					$WHERE_SQL = ' WHERE ';
					foreach($table_index as $index){
						$WHERE_SQL .= "$index = '$row[$index]' AND ";
					}

					$j = 0;

					foreach ($column_name as $current_column) {

						// Thank you to hansbr for improved replacement logic
						$data_to_fix = $edited_data = $row[$current_column]; // set the same now - if they're different later we know we need to updated
						$j++; // keep track of index of current column

						// -- PROCESS THE SEARCH ARRAY --
						foreach( $replace_array as $search_for => $replace_with) {
							$count_items_checked++;
							//            echo "<br/>Current Column = $current_column";
							//            if ($current_column == $index_field) $index_value = $row[$current_column];    // if it's the index column, store it for use in the update
							if (is_serialized($data_to_fix)) {
								//                echo "<br/>unserialize OK - now searching and replacing the following array:<br/>";
								//                echo "<br/>$data_to_fix";
								$unserialized = unserialize($edited_data);
								//                print_r($unserialized);
								$this->recursive_array_replace($search_for, $replace_with, $unserialized);
								$edited_data = serialize($unserialized);
								//                echo "**Output of search and replace: <br/>";
								//                echo "$edited_data <br/>";
								//                print_r($unserialized);
								//                echo "---------------------------------<br/>";
							}
							elseif (is_string($data_to_fix)){
								$edited_data = str_replace($search_for,$replace_with,$edited_data) ;
							}
						}

						//-- SEARCH ARRAY COMPLETE ----------------------------------------------------------------------

						if ($data_to_fix != $edited_data) {   // If they're not the same, we need to add them to the update string
							$count_items_changed++;
							if ($need_to_update != false) $UPDATE_SQL = $UPDATE_SQL.',';  // if this isn't our first time here, add a comma
							$UPDATE_SQL = $UPDATE_SQL.' '.$current_column.' = "' . esc_sql( $edited_data ). '"';
							$need_to_update = true; // only set if we need to update - avoids wasted UPDATE statements
						}

					}

					if ($need_to_update) {
						$count_updates_run;
						$WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.
						$UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
						if (isset($_POST['is_debug'])) { Ninja_Demo()->logs->dlog ( $UPDATE_SQL.'<br/><br/>'); }
						$result = $wpdb->query( $UPDATE_SQL );
						if (!$result) Ninja_Demo()->logs->dlog (("<br /><b>ERROR: </b>" . $wpdb->last_error . "<br/>$UPDATE_SQL<br/>"));
					}
				}
				/*---------------------------------------------------------------------------------------------------------*/
			}
		}
	}

	/**
	 * Get the uploads folder for the target site
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function get_upload_folder( $id ) {
		switch_to_blog( $id );
		$src_upload_dir = wp_upload_dir();
		restore_current_blog();
		Ninja_Demo()->logs->dlog('Original basedir returned by wp_upload_dir() = <strong>'.$src_upload_dir['basedir'].'</strong><br />');
		// trim '/files' off the end of loction for sites < 3.5 with old blogs.dir format
		$folder = str_replace('/files', '', $src_upload_dir['basedir']);
		$content_dir = '';
		// validate the folder itself to handle cases where htaccess or themes alter wp_upload_dir() output
		if ( $id!=1 && (strpos($folder, '/'.$id) === false || !file_exists($folder)) ) {
			// we have a non-standard folder and the copy will probably not work unless we correct it
			// get the installation dir - we're using the internal WP constant which the codex says not to do
			// but at this point the wp_upload_dir() has failed and this is a last resort
			$content_dir = WP_CONTENT_DIR; //no trailing slash
			Ninja_Demo()->logs->dlog('Non-standard result from wp_upload_dir() detected. <br />');
			Ninja_Demo()->logs->dlog('Normalized content_dir = '.$content_dir.'<br />');
			// check for WP < 3.5 location
			$test_dir = $content_dir . '/blogs.dir/' . $id;
			if (file_exists($test_dir)) {
				Ninja_Demo()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
				return $test_dir;
			}
			// check for WP >= 3.5 location
			$test_dir = $content_dir . '/uploads/sites/' . $id;
			if (file_exists($test_dir)) {
				Ninja_Demo()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
				return $test_dir;
			}
		}
		// otherwise we have a standard folder OR could not find a normal folder and are stuck with
		// sending the original wp_upload_dir() back knowing the replace and copy should work
		return $folder;
	}

	/**
	 * Copy files and directories recursively and return number of copies executed.
	 *
	 * @access public
	 * @since 1.0
	 * @return int $num Number of items copied
	 */
	public function recursive_file_copy($src, $dst, $num) {
		$num = $num + 1;
		if ( is_dir( $src ) ) {
			if ( !file_exists ( $dst ) ) {
		        global $wp_filesystem;
		        if ( empty ( $wp_filesystem ) ) {
		            require_once ( ABSPATH . '/wp-admin/includes/file.php' );
		            WP_Filesystem();
		        }
		        mkdir($dst, 0777, true);
		    }
			$files = scandir( $src );
			foreach ( $files as $file )
				if ( $file != "." && $file != ".." && $file != 'sites') $num = $this->recursive_file_copy("$src/$file", "$dst/$file", $num);
		}
		else if ( file_exists ( $src ) ) copy( $src, $dst );
		return $num;
	}

	/**
	 * Replace the values within a multi-dimensional array
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function recursive_array_replace($find, $replace, &$data) {
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				// check for an array for recursion
				if (is_array($value)) {
					$this->recursive_array_replace($find, $replace, $data[$key]);
				} else {
					// have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
					if (is_string($value)) $data[$key] = str_replace($find, $replace, $value);
				}
			}
		} else {
			if (is_string($data)) $data = str_replace($find, $replace, $data);
		}
	}
} // End Ninja_Demo_Sandbox class
