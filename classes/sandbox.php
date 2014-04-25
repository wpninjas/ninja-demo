<?php
/**
 * Demo_WP_Sandbox
 *
 * This class handles the creation, deletion, and interacting with Sandboxes.
 *
 *
 * @package     Demo WP PRO
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

class Demo_WP_Sandbox {

	/**
	 * @var Store our database information.
	 */
	var $db_host = DB_HOST;
	var $db_name = DB_NAME;
	var $db_user = DB_USER;
	var $db_pass = DB_PASSWORD;

	/**
	 * @var Class Globals
	 */
	var $target_id = '';
	var $status = '';
	var $global_tables;

	/**
	 * Get things started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'dwp_hourly', array( $this, 'purge' ) );
		add_action( 'init', array( $this, 'prevent_clone_check' ) );

		//define which tables to skip by default when cloning root site
		$this->global_tables = apply_filters( 'dwp_global_tables', array(
			'blogs','blog_versions','registration_log','signups','site','sitemeta', //default multisite tables
			'usermeta','users', //don't copy users
			'bp_.*', //buddypress tables
			'3wp_broadcast_.*', //3wp broadcast tables
			'demo_wp_ip_lockout' // Demo WP IP lockout table
		) );
	}

	/**
	 * Check to see if we have disabled clone creation. If so, disable the main site, leaving sandboxes alone
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function prevent_clone_check() {
		if ( ! Demo_WP()->is_admin_user() && Demo_WP()->settings['prevent_clones'] == 1 && is_main_site() )
			wp_die( 'The demo is currently offline.', 'demo-wp' );
	}

	/**
	 * Count our current sandboxes
	 *
	 * @access public
	 * @since 1.0
	 * @return int $count
	 */
	public function count() {
		global $wpdb;
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE site_id = '1'
	        AND spam = '0'
	        AND deleted = '0'
	        AND archived = '0'
	        AND blog_id != 1"
	    );
	    $count = count( $blogs );
		return $count;
	}

	/**
	 * Delete all sandboxes
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function delete_all() {
		global $wpdb;
		// Get a list of all of our sandboxes
		$blogs = $wpdb->get_results("
	        SELECT blog_id
	        FROM $wpdb->blogs
	        WHERE site_id = '1'
	        AND spam = '0'
	        AND deleted = '0'
	        AND archived = '0'
	        AND blog_id != 1"
	    );
	    foreach ( $blogs as $blog ) {
	    	$this->delete( $blog->blog_id );
		}
	}

	/**
	 * Delete a sandbox
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function delete( $blog_id, $drop = true ) {
		global $wpdb;

		$switch = false;
		if ( get_current_blog_id() != $blog_id ) {
			$switch = true;
			switch_to_blog( $blog_id );
		}			   		// Grab all the tables that have our prefix.

		$blog = get_blog_details( $blog_id );
		/**
		 * Fires before a blog is deleted.
		 *
		 * @since MU
		 *
		 * @param int  $blog_id The blog ID.
		 * @param bool $drop    True if blog's table should be dropped. Default is false.
		 */
		do_action( 'delete_blog', $blog_id, $drop );

		$users = get_users( array( 'blog_id' => $blog_id, 'fields' => 'ids' ) );

		// Remove users from this blog.
		if ( ! empty( $users ) ) {
			foreach ( $users as $user_id ) {
				remove_user_from_blog( $user_id, $blog_id );
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
		unset( $_SESSION['demo_wp_sandbox'] );

		if ( $switch )
			restore_current_blog();
	}

	/**
	 * Check to see if any of our sandboxes needs to be purged.
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
	        WHERE site_id = '1'
	        AND spam = '0'
	        AND deleted = '0'
	        AND archived = '0'
	        AND blog_id != 1" );

	    $sites = array();
	    $redirect = false;
	    foreach ( $blogs as $blog ) {

	   		// If we've been alive longer than the lifespan, delete the sandbox.
	   		if ( ! $this->get_time_left( $blog->blog_id ) ) {
	   			// Check to see if we're currently looking at the blog to be deleted.
				if ( $blog->blog_id == get_current_blog_id() )
					$redirect = true;
				$this->delete( $blog->blog_id );
	   		}
		}

		if ( $redirect ) {
			wp_redirect( get_blog_details( 1 )->siteurl );
			exit;
		}
	}

	/**
	 * Get how much longer a sandbox should live
	 * Return the remaining time as a timestamp or false if the sandbox has expired.
	 * 
	 * @access public
	 * @since 1.0
	 * @return int $remaining_life
	 */
	public function get_time_left( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$lifespan = apply_filters( 'dwp_sandbox_lifespan', Demo_WP()->settings['lifespan'], $blog_id );
		$life = current_time( 'timestamp' ) - strtotime( get_blog_details( $blog_id )->registered );

		$remaining_life = $lifespan - $life;
		if ( $remaining_life >= 0 ) {
			return $remaining_life;
		} else {
			return false;
		}
	}

	/**
	 * Get the end time for our sandbox
	 * 
	 * @access public
	 * @since 1.0
	 * @return int $end_time;
	 */
	public function get_end_time( $blog_id = '' ) {
		if ( $blog_id == '' )
			$blog_id = get_current_blog_id();

		$lifespan = apply_filters( 'dwp_sandbox_lifespan', Demo_WP()->settings['lifespan'], $blog_id );
		$end_time = strtotime( get_blog_details( $blog_id )->registered ) + $lifespan;

		return $end_time;
	}

	/**
	 * Check to see if a sandbox is alive
	 * 
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_alive( $blog_id = '' ) {
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
	 * "Reset" a user's sandbox by removing the current one and creating a new one.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function reset() {
		// Delete our current sandbox
		$this->delete( get_current_blog_id() );
		// Switch to our main blog
		switch_to_blog( 1 );
		// Create a new sandbox
		$this->create();
	}

	/**
	 * Create our sandbox
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function create() {
		global $wpdb, $report, $count_tables_checked, $count_items_checked, $count_items_changed, $current_site, $wp_version;

		// Declare the locals that need to be available throughout the function:
		$target_id = '';
		$target_subd = '';
		$target_site = '';


		//  Start TIMER
		//  -----------
		$stimer = explode( ' ', microtime() );
		$stimer = $stimer[1] + $stimer[0];
		//  -----------

		$source_id = 1;
		$target_site = get_blog_details( $source_id )->blogname;
		$target_site_name = $this->generate_site_name();

		// CREATE THE SITE

		// Create site
		$this->create_site( $target_site_name, $target_site );

		// Start compiling data for success message
		$site_address = get_blog_details( $this->target_id )->siteurl;
		// $this->status = $this->status . 'Created site <a href="'.$site_address.'" target="_blank">';
		// $this->status = $this->status . '<b>'.$site_address.'</b></a> with ID: <b>' . $this->target_id . '</b><br />';


		// RUN THE CLONING
		Demo_WP()->logs->dlog( 'RUNNING NS Cloner version: ' . DEMO_WP_VERSION . ' <br /><br />' );

		// don't want the trailing slash in path just in case there are replacements that don't have it
		$source_subd = untrailingslashit( get_blog_details($source_id)->domain . get_blog_details($source_id)->path );

		$source_site = get_blog_details($source_id)->blogname;

		$target_id = $this->target_id;
		$target_subd = get_current_site()->domain . get_current_site()->path . $target_site_name;

		if ( $source_id == '' || $source_subd == '' || $source_site == '' || $target_id == '' || $target_subd == '' || $target_site == '') {
			// Clear the querystring and add the results
			wp_redirect( add_query_arg(
				array('error' => 'true',
					  'errormsg' => urlencode( __( 'You must fill out all fields in Cloning section. Otherwise unsafe operation.', 'ns_cloner' ) ),
					  'updated' => false),
				wp_get_referer() ) );
			die;
		}
		// prevent the source site name from being contained in the target domain / directory, since the search/replaces will wreak havoc in that scenario
		elseif( stripos($target_subd, $source_site) !== false ) {
				wp_redirect( add_query_arg(
					array('error' => 'true',
						  'errormsg' => urlencode( __( "The Source Site Name ($source_site) may not appear in the Target Site Domain ($target_subd) or data corruption will occur. You might need to edit the Source Site's Name in Settings > General, or double-check / change your field input values.", 'ns_cloner' ) ),
						  'updated' => false),
					wp_get_referer() ) );
				die;
		} else{
			//configure all the properties
			$source_pre = $source_id==1? $wpdb->base_prefix : $wpdb->base_prefix . $source_id . '_';	// the wp id of the source database
			$target_pre = $wpdb->base_prefix . $target_id . '_';	// the wp id of the target database

			Demo_WP()->logs->dlog ( 'Source Prefix: <b>' . $source_pre . '</b><br />' );
			Demo_WP()->logs->dlog ( 'Target Prefix: <b>' . $target_pre . '</b><br />' );

			// Add support for ThreeWP Broadcast plugin
			// Thank you John @ propanestudio.com and Aamir
			// getting already added broad cast id of source id from database
			$myrows = $wpdb->get_results( 'SELECT * FROM '.$wpdb->base_prefix.'_3wp_broadcast_broadcastdata where blog_id='.$source_id.'',ARRAY_A );
			// loop to each data row
			foreach($myrows as $r){
				if($r['blog_id'] != ""){ // if blog id not empty
					$dd=unserialize(base64_decode($r['data'])); // decode the data and unserilize this and store into varibale
					if($dd['linked_parent']['blog_id'] != ""){ // verify this is parnet or child broad cast
						$pushdata = $dd['linked_parent']['blog_id']	; // if its parnet then store its id and make a dataabse request and fetch data of that id
						$myrow = $wpdb->get_results( 'SELECT * FROM '.$wpdb->base_prefix.'_3wp_broadcast_broadcastdata where blog_id='.$pushdata.'', ARRAY_A);
						$enc=unserialize(base64_decode($myrow[0]['data'])); // unserilize and decode data
						Demo_WP()->logs->dlog ( 'Adding ThreeWPBroadcast data: <b>' . print_r($enc,true) . '</b><br />' ); //log data
						$enc['linked_children'][$target_id]=$r['post_id']; // merge newly added site id and post id unserlize data
						$enc=base64_encode(serialize($enc)); // again serlize this and decode this and save into db
						$wpdb->query('UPDATE '.$wpdb->base_prefix.'_3wp_broadcast_broadcastdata SET data="'.$enc.'" where blog_id='.$pushdata.'');
					}
					// add child elemnts of broad cast for new site id
					$wpdb->query('INSERT into '.$wpdb->base_prefix.'_3wp_broadcast_broadcastdata SET blog_id='.$target_id.',post_id='.$r['post_id'].',data="'.$r['data'].'"');
				}
			}

			//clone
			$this->run_clone($source_pre, $target_pre);
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
		if($source_id==1){
			switch_to_blog(1);
			$main_uploads_info = wp_upload_dir();
			restore_current_blog();
			$main_uploads_dir = str_replace( get_site_url('/'), '', $main_uploads_info['baseurl'] );
			$main_uploads_replace = '';
			// can't do it this way because the condition should NOT just be based on WP version.
			// it has to be checked against what the ORIGINAL version of wpmu was installed then upgraded
			// our get_upload_folder() does this
			//---
			//$main_uploads_target = $wp_version < 3.5? "$main_uploads_dir/blogs.dir/$target_id" : "$main_uploads_dir/sites/$target_id";
			//---
			// detect if this is an older network and set the destination accordingly
			$test_dir = WP_CONTENT_DIR . '/blogs.dir';
			if (file_exists($test_dir)) {
				$main_uploads_target = WP_CONTENT_DIR . '/blogs.dir/' . $target_id;
				$main_uploads_replace = '/wp-content/blogs.dir/' . $target_id;
			}
			else {
				$main_uploads_target = WP_CONTENT_DIR . '/uploads/sites/' . $target_id;
				$main_uploads_replace = '/wp-content/uploads/sites/' . $target_id;
			}
			$replace_array[$main_uploads_dir] = $main_uploads_replace;
			// debugging ----------------------------
			//$report .= 'Search Source Dir: <b>' . $main_uploads_dir . '</b><br />';
			//$report .= 'Replace Target Dir: <b>' . $main_uploads_replace . '</b><br />';
			// --------------------------------------
			//reset the option_name = wp_#_user_roles row in the wp_#_options table back to the id of the target site
			$replace_array[$wpdb->base_prefix . 'user_roles'] = $wpdb->base_prefix . $target_id . '_user_roles';
		}

		//replace
		Demo_WP()->logs->dlog ( 'running replace on Target table prefix: ' . $target_pre . '<br />' );
		foreach( $replace_array as $search_for => $replace_with) {
			Demo_WP()->logs->dlog ( 'Replace: <b>' . $search_for . '</b> >> With >> <b>' . $replace_with . '</b><br />' );
		}
		$this->run_replace($target_pre, $replace_array);

		// COPY ALL MEDIA FILES
		// get the right paths to use
		// handle for uploads location when cloning root site
		$src_blogs_dir = $this->get_upload_folder($source_id);
		if($source_id==1){
			$dst_blogs_dir = $main_uploads_target;
		}
		else {
			$dst_blogs_dir = $this->get_upload_folder($this->target_id);
		}

		// Fix file dir when cloning root directory
		// Fix some instances where physical paths have numbers in them
		// Thank you, Christian for the catch!
		//$dst_blogs_dir = str_replace($source_id, $target_id, $src_blogs_dir );
		//$dst_blogs_dir = str_replace( '/' . $source_id, '/' . $target_id, $src_blogs_dir );
		// moved up in the conditional
		//$dst_blogs_dir = $this->get_upload_folder($this->target_id);

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
			Demo_WP()->logs->dlog ('Copied: <b>' . $num_files . '</b> folders and files!<br />');
			Demo_WP()->logs->dlog ('From: <b>' . $src_blogs_dir . '</b><br />');
			Demo_WP()->logs->dlog ('To: <b>' . $dst_blogs_dir . '</b><br />');
		}
		else {
			$report .= '<span class="warning-txt-title">Could not copy files</span><br />';
			$report .= 'From: <b>' . $src_blogs_dir . '</b><br />';
			$report .= 'To: <b>' . $dst_blogs_dir . '</b><br />';
		}
		// ---------------------------------------------------------------------------------------------------------------
		// Report

		//echo '<p style="margin:auto; text-align:center">';
		//Demo_WP()->logs->dlog ( $report );

		//  End TIMER
		//  ---------
		$etimer = explode( ' ', microtime() );
		$etimer = $etimer[1] + $etimer[0];
		Demo_WP()->logs->log ( $target_subd . " cloned in " . ($etimer-$stimer) . " seconds."  );
		Demo_WP()->logs->dlog ( "Entire cloning process took: <strong>" . ($etimer-$stimer) . "</strong> seconds."  );
		//echo '</p>';
		//  ---------

		// Report on what was accomplished
		// $this->status = $this->status . $report . "Entire cloning process took: <strong>" . number_format(($etimer-$stimer), 4) . "</strong> seconds... <br />";
		// $this->status = $this->status . '<a href="' . Demo_WP()->logs->log_file_url . '" target="_blank">Historical Log</a> || ';
		// $this->status = $this->status . '<a href="' . Demo_WP()->logs->detail_log_file_url . '" target="_blank">Detailed Log</a> ';

		$_SESSION['demo_wp_sandbox'] = $this->target_id;

		// This sets the option to discourage search engines from indexing sandboxes within a demo.
	    update_blog_option( $this->target_id, 'blog_public', 0 );
		
		// Auto-login our user if we aren't the super admin
	    if ( Demo_WP()->settings['auto_login'] !== '' && ! Demo_WP()->is_admin_user() ) {
		    wp_clear_auth_cookie();
		    wp_set_auth_cookie( Demo_WP()->settings['auto_login'], true );
		    wp_set_current_user( Demo_WP()->settings['auto_login'] );	    	
	    }

		do_action( 'dwp_create_sandbox', $this->target_id );

		wp_redirect( $site_address );
		die;
	}

	/**
	 * Return a random alphanumeric string to serve as our site name.
	 *
	 * @access private
	 * @since 1.0
	 * @return string $key
	 */
	private function generate_site_name() {
		$key = Demo_WP()->random_string();

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
	private function create_site( $sitename, $sitetitle ) {
		global $wpdb, $current_site, $current_user;
		get_currentuserinfo();

		$blog_id = '';
		$user_id = '';
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

		$user_id = Demo_WP()->settings['admin_id'];

		$site_id = get_id_from_blogname( $create_site_name );

		// create site and don't forget to make public:
		$meta['public'] = 1;
		$site_id = wpmu_create_blog( $tmp_site_domain, $tmp_site_path, $create_site_title, $user_id , $meta, $current_site->id );

		if( ! is_wp_error( $site_id ) ) {
			//send email
			//wpmu_welcome_notification( $site_id, $user_id, $create_user_pass, esc_html( $create_site_title ), '' );
			Demo_WP()->logs->log( 'Site: ' . $tmp_site_domain . $tmp_site_path . ' created!' );
			//assign target id for cloning and replacing
			$this->target_id = $site_id;
		} else {
			Demo_WP()->logs->log( 'Error creating site: ' . $tmp_site_domain . $tmp_site_path . ' - ' . $site_id->get_error_message() );
		}

		$users = get_users( 1 );
		if ( is_array( $users ) ) {
			foreach ( $users as $user ) {
				add_user_to_blog( $this->target_id, $user->ID, $user->roles[0] );
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

		$cid = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );

		mysqli_set_charset( $cid, DB_CHARSET );

		//get list of source tables when cloning root
		if($source_prefix==$wpdb->base_prefix){
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
			$SQL = "SHOW TABLES WHERE `Tables_in_" . $this->db_name . "` IN('" . implode("','",$table_names). "')";
		}
		//get list of source tables when cloning non-root
		else{
			// MUST ESCAPE '_' characters otherwise they will be interpreted as wildcard
			// single chars in LIKE statement and can really hose up the database
			$SQL = 'SHOW TABLES LIKE \'' . str_replace('_','\_',$source_prefix) . '%\'';
		}

		$tables_list = mysqli_query( $cid, $SQL );

		$num_tables = 0;

		if ($tables_list != false) {
			while ( $tables = mysqli_fetch_array( $tables_list ) ) {
				$source_table = $tables[0];
				$target_table = str_replace( $source_prefix, $target_prefix, $source_table );

				$num_tables++;
				//run cloning on current table to target table
				if ($source_table != $target_table) {
					Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );
					Demo_WP()->logs->dlog ( 'Cloning source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') to Target table: <b>' . $target_table . '</b><br />' );
					Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />' );
					$this->clone_table($source_table, $target_table);
				}
				else {
					Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
					Demo_WP()->logs->dlog ( 'Source table: <b>' . $source_table . '</b> (table #' . $num_tables . ') and Target table: <b>' . $target_table . ' are the same! SKIPPING!!!</b><br />');
					Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
				}
			}
		}
		else {
			Demo_WP()->logs->dlog ( 'no data for sql - ' . $SQL );
		}

		if (isset($_POST['is_debug'])) { Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br /><br />'); }
		$report .= 'Cloned: <b>' .$num_tables . '</b> tables!<br/ >';
		Demo_WP()->logs->dlog('Cloned: <b>' .$num_tables . '</b> tables!<br/ >');

		mysqli_close($cid);
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
		$sql_statements = '';

		$cid = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );
		mysqli_set_charset( $cid, DB_CHARSET );

		$query = "DROP TABLE IF EXISTS " . $this->backquote( $target_table );

		if ( isset( $_POST['is_debug'] ) )
			Demo_WP()->logs->dlog ( $query . '<br /><br />');

		$result = mysqli_query( $cid, $query );
		if ( $result == false )
			Demo_WP()->logs->dlog ( '<b>ERROR</b> dropping table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $cid ) . '<br />' );

		// Table structure - Get table structure
		$query = "SHOW CREATE TABLE " . $this->backquote( $source_table );
		$result = mysqli_query( $cid, $query );
		if ( $result == false ) {
			Demo_WP()->logs->dlog ( '<b>ERROR</b> getting table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $cid ) . '<br />' );
		} else {
			if ( mysqli_num_rows( $result ) > 0 ) {
				$sql_create_arr = mysqli_fetch_array( $result );
				$sql_statements .= $sql_create_arr[1];
			}
			mysqli_free_result( $result );
		}

		// Create cloned table structure
		$query = str_replace( $source_table, $target_table, $sql_statements );
		if ( isset( $_POST['is_debug'] ) )
			Demo_WP()->logs->dlog ( $query . '<br /><br />');

		$result = mysqli_query( $cid, $query );
		if ( $result == false )
			Demo_WP()->logs->dlog ( '<b>ERROR</b> creating table structure with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $cid ) . '<br />' );

		// Table data contents - Get table contents
		$query = "SELECT * FROM " . $this->backquote( $source_table );
		$result = mysqli_query( $cid, $query );
		if ( $result == false ) {
			Demo_WP()->logs->dlog ( '<b>ERROR</b> getting table contents with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $cid ) . '<br />' );
		} else {
			$fields_cnt = mysqli_num_fields( $result );
			$rows_cnt   = mysqli_num_rows( $result );
		}

		// Checks whether the field is an integer or not
		for ( $j = 0; $j < $fields_cnt; $j++ ) {
			$field_result = mysqli_fetch_field_direct( $result, $j );
			$field_set[$j] = $this->backquote( $field_result->name );
			$type = $field_result->type;
			// removed ||$type == 'timestamp' from this check because it's invalid - timestamp values need ' ' surrounding to insert successfully
			if ( $type == 'tinyint' || $type == 'smallint' || $type == 'mediumint' || $type == 'int' || $type == 'bigint') {
				$field_num[$j] = true;
			} else {
				$field_num[$j] = false;
			}
		} // end for

		// Sets the scheme
		$entries = 'INSERT INTO ' . $this->backquote($target_table) . ' VALUES (';
		$search	= array("\x00", "\x0a", "\x0d", "\x1a"); 	//\x08\\x09, not required
		$replace	= array('\0', '\n', '\r', '\Z');
		$current_row	= 0;

		while ( $row = mysqli_fetch_row( $result ) ) {
			$current_row++;
			// Tracks the _transient_feed_ and _transient_rss_ garbage for exclusion
			$is_trans = false;
			for ($j = 0; $j < $fields_cnt; $j++) {
				if (!isset($row[$j])) {
					$values[]     = 'NULL';
				} else if ($row[$j] == '0' || $row[$j] != '') {
					// a number
					if ($field_num[$j]) {
						$values[] = $row[$j];
					}
					else {
						// don't include _transient_feed_ bloat
						if (!$is_trans) {
							$values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes($row[$j] ) ) . "'";
						}
						else {
							$values[]     = "''";
							$is_trans = false;
						}
						// set $is_trans for the next field based on the contents of the current field
						(strpos($row[$j],'_transient_feed_') === false && strpos($row[$j],'_transient_rss_') === false) ? $is_trans = false : $is_trans = true;

					} //if ($field_num[$j])
				} else {
					$values[]     = "''";
				} // if (!isset($row[$j]))
			} // for ($j = 0; $j < $fields_cnt; $j++)

			// Execute current insert row statement
			$query = $entries . implode(', ', $values) . ')';
			if (isset($_POST['is_debug'])) { Demo_WP()->logs->dlog ( $query . '<br />'); }
			// Have to separate this into its own function otherwise it interfers with current mysql connection / results
			$this->insert_query($query);

			unset($values);
		} // while ($row = mysql_fetch_row($result))
		mysqli_free_result( $result );
	}

	/**
	 * Insert our data
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function insert_query($query) {

		$insert = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );
		mysqli_set_charset( $insert, DB_CHARSET );

		$results = mysqli_query( $insert, $query );
		if ($results == FALSE) { Demo_WP()->logs->dlog ( '<b>ERROR</b> inserting into table with sql - ' . $query . '<br /><b>SQL Error</b> - ' . mysqli_error( $insert ) . '<br />'); }
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
		global $report, $count_tables_checked, $count_items_checked, $count_items_changed;

		$cid = mysqli_connect( $this->db_host, $this->db_user, $this->db_pass, $this->db_name );
		mysqli_set_charset( $cid, DB_CHARSET );

		if (!$cid) { Demo_WP()->logs->dlog ("Connecting to DB Error: " . mysqli_error() . "<br/>"); }

		// First, get a list of tables
		// MUST ESCAPE '_' characters otherwise they will be interpreted as wildcard
		// single chars in LIKE statement and can really hose up the database
		$SQL = 'SHOW TABLES LIKE \'' . str_replace('_','\_',$target_prefix) . '%\'';

		$tables_list = mysqli_query( $cid, $SQL );

		if (!$tables_list) {
		Demo_WP()->logs->dlog ("ERROR: " . mysqli_error() . "<br/>$SQL<br/>"); }

		// Loop through the tables

		while ( $table_rows = mysqli_fetch_array( $tables_list ) ) {

			$table = $table_rows[0];

			$count_tables_checked++;
			Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');
			Demo_WP()->logs->dlog ( 'Searching table: <b>' . $table . '</b><br />');  // we have tables!
			Demo_WP()->logs->dlog ( '-----------------------------------------------------------------------------------------------------------<br />');

			// ---------------------------------------------------------------------------------------------------------------

			$SQL = "DESCRIBE ".$table ;    // fetch the table description so we know what to do with it
			$fields_list = mysqli_query( $cid, $SQL );

			// Make a simple array of field column names

			/*------------------------------------------------------------------------------------------------------------------
			*/
			$index_fields = "";  // reset fields for each table.
			$column_name = "";
			$table_index = "";
			$i = 0;

			while ( $field_rows = mysqli_fetch_array( $fields_list ) ) {
				$column_name[$i++] = $field_rows['Field'];
				if ($field_rows['Key'] == 'PRI') $table_index[] = $field_rows['Field'] ;
			}

			// skip if no primary key
			if( empty($table_index) ) continue;

			//    print_r ($column_name);
			//    print_r ($table_index);

			// now let's get the data and do search and replaces on it...

			$SQL = "SELECT * FROM ".$table;     // fetch the table contents
			$data = mysqli_query( $cid, $SQL );

			if (!$data) {
			Demo_WP()->logs->dlog ("<br /><b>ERROR:</b> " . mysqli_error() . "<br/>$SQL<br/>"); }

			while ( $row = mysqli_fetch_array( $data ) ) {

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
						$UPDATE_SQL = $UPDATE_SQL.' '.$current_column.' = "' . mysqli_real_escape_string( $cid, $edited_data ). '"';
						$need_to_update = true; // only set if we need to update - avoids wasted UPDATE statements
					}

				}

				if ($need_to_update) {
					$count_updates_run;
					$WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.
					$UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
					if (isset($_POST['is_debug'])) { Demo_WP()->logs->dlog ( $UPDATE_SQL.'<br/><br/>'); }
					$result = mysqli_query( $cid,$UPDATE_SQL );
					if (!$result) Demo_WP()->logs->dlog (("<br /><b>ERROR: </b>" . mysqli_error() . "<br/>$UPDATE_SQL<br/>"));
				}
			}
			/*---------------------------------------------------------------------------------------------------------*/
		}
		mysqli_close( $cid );
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
		Demo_WP()->logs->dlog('Original basedir returned by wp_upload_dir() = <strong>'.$src_upload_dir['basedir'].'</strong><br />');
		// trim '/files' off the end of loction for sites < 3.5 with old blogs.dir format
		$folder = str_replace('/files', '', $src_upload_dir['basedir']);
		$content_dir = '';
		// validate the folder itself to handle cases where htaccess or themes alter wp_upload_dir() output
		if ( $id!=1 && (strpos($folder, '/'.$id) === false || !file_exists($folder)) ) {
			// we have a non-standard folder and the copy will probably not work unless we correct it
			// get the installation dir - we're using the internal WP constant which the codex says not to do
			// but at this point the wp_upload_dir() has failed and this is a last resort
			$content_dir = WP_CONTENT_DIR; //no trailing slash
			Demo_WP()->logs->dlog('Non-standard result from wp_upload_dir() detected. <br />');
			Demo_WP()->logs->dlog('Normalized content_dir = '.$content_dir.'<br />');
			// check for WP < 3.5 location
			$test_dir = $content_dir . '/blogs.dir/' . $id;
			if (file_exists($test_dir)) {
				Demo_WP()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
				return $test_dir;
			}
			// check for WP >= 3.5 location
			$test_dir = $content_dir . '/uploads/sites/' . $id;
			if (file_exists($test_dir)) {
				Demo_WP()->logs->dlog('Found actual uploads folder at '.$test_dir.'<br />');
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
		if (is_dir($src)) {
			if (!file_exists($dst)) {
				mkdir($dst);
			}
			$files = scandir($src);
			foreach ($files as $file)
				if ($file != "." && $file != ".." && $file != 'sites') $num = $this->recursive_file_copy("$src/$file", "$dst/$file", $num);
		}
		else if (file_exists($src)) copy($src, $dst);
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

} // End Demo_WP_Sandbox class
