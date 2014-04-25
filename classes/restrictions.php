<?php
/**
 * Demo_WP_Restrictions
 *
 * This class handles content restriction.
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

class Demo_WP_Restrictions {

	/**
	 * Get things started
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'offline_check' ) );
		add_action( 'init', array( $this, 'main_site_check' ) );
		add_action( 'admin_menu', array( $this, 'remove_pages' ), 999 );
		add_filter( 'show_password_fields', array( $this, 'disable_passwords' ) );
	    add_filter( 'allow_password_reset', array( $this, 'disable_passwords' ) );
	    add_action( 'personal_options_update', array( $this, 'disable_email_editing' ), 1 );
	    add_action( 'edit_user_profile_update', array( $this, 'disable_email_editing' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'remove_menu_bar_items' ), 999 );
	}

	/**
	 * Check to see if our "take demo completely offline" has been checked. If it has, display the offline message.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function offline_check() {
		if ( ! Demo_WP()->is_admin_user() && Demo_WP()->settings['offline'] == 1 )
			wp_die( 'The demo is currently offline.', 'demo-wp' );
	}

	/**
	 * These functions are designed to keep users from being logged-in on the main site unless they are the network admin.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function main_site_check() {
		// If this user already has a sandbox created and it exists, then redirect them to that sandbox
		if ( isset ( $_SESSION['demo_wp_sandbox'] ) && ! Demo_WP()->is_admin_user() ) {
			if ( Demo_WP()->sandbox->is_active( $_SESSION['demo_wp_sandbox'] ) ) {
				if ( is_main_site() ) {
					wp_redirect( get_blog_details( $_SESSION['demo_wp_sandbox'] )->siteurl );
					die;
				}
			} else {
				unset( $_SESSION['demo_wp_sandbox'] );
				wp_redirect( add_query_arg( array( 'expired' => 1 ), get_blog_details( 1 )->siteurl ) );
				die();
			}
		}

		// If this user is on the main blog and logged-in in a sandbox, then log them out.
		if ( is_user_logged_in() && ! Demo_WP()->is_sandbox() && ! Demo_WP()->is_admin_user() ) {
			wp_logout();
			wp_redirect( add_query_arg( array( 'expired' => 1 ), get_blog_details( 1 )->siteurl ) );
			die();
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

		if ( ! Demo_WP()->is_admin_user() ) {

			$pages = apply_filters( 'dwp_prevent_access', array( 'options.php', 'my-sites.php' ) );

			// Remove our menu links.
			Demo_WP()->settings['parent_pages'][] = 'plugins.php';
			Demo_WP()->settings['parent_pages'][] = 'demo-wp';
			$menu_links = apply_filters( 'dwp_hide_menu_pages', Demo_WP()->settings['parent_pages'] );

			$submenu_links = apply_filters( 'dwp_hide_submenu_pages', Demo_WP()->settings['child_pages'] );
			$submenu_links[] = array( 'parent' => 'index.php', 'child' => 'my-sites.php' );

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
		if ( ! Demo_WP()->is_admin_user() )
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
	public function disable_email_editing( $user_id ) {
		$user_info = get_userdata( $user_id );

		if ( ! Demo_WP()->is_admin_user() )
			$_POST['first_name'] = $user_info->user_firstname;
			$_POST['last_name'] = $user_info->user_lastname;
			$_POST['nickname'] = $user_info->nickname;
			$_POST['display_name'] = $user_info->display_name;
			$_POST['email'] = $user_info->user_email;
	}

	/**
	 * Remove items from our admin bar if the user isn't our network admin
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function remove_menu_bar_items( $wp_admin_bar ) {
		if ( ! Demo_WP()->is_admin_user() ) {
			$wp_admin_bar->remove_node('my-sites');
			$wp_admin_bar->remove_node('new-content');
			$wp_admin_bar->remove_node('comments');
		} else {
			// We do, however, want to add a straight link to the my-sites.php page.
			$elements = $wp_admin_bar->get_nodes();
			foreach( $elements as $element ) {

		        if ( $element->parent == 'my-sites-list' ) {
		        	if ( $element->id != 'blog-1' )
		        		$wp_admin_bar->remove_node( $element->id );
		        }
			}
		}
	}
}