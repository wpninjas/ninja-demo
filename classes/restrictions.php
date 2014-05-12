<?php
/**
 * Ninja_Demo_Restrictions
 *
 * This class handles content restriction.
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

class Ninja_Demo_Restrictions {

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
		add_action( 'current_screen', array( $this, 'remove_pages' ), 999 );
		add_filter( 'show_password_fields', array( $this, 'disable_passwords' ) );
	    add_filter( 'allow_password_reset', array( $this, 'disable_passwords' ) );
	    add_action( 'personal_options_update', array( $this, 'disable_email_editing' ), 1 );
	    add_action( 'edit_user_profile_update', array( $this, 'disable_email_editing' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'remove_menu_bar_items' ), 999 );
		add_action( 'delete_blog', array( $this, 'prevent_delete_blog' ), 10, 2 );
	}

	/**
	 * Check to see if our "take demo completely offline" has been checked. If it has, display the offline message.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function offline_check() {
		$current_url = add_query_arg( array() );
		if ( Ninja_Demo()->settings['offline'] == 1 && ! Ninja_Demo()->is_admin_user() && ( ( ! Ninja_Demo()->is_sandbox() && strpos ( $current_url, '/wp-admin/' ) === false && strpos ( $current_url, 'wp-login.php' ) === false ) || Ninja_Demo()->is_sandbox() ) )
			wp_die( 'The demo is currently offline.', 'ninja-demo' );
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
		if ( isset ( $_SESSION['ninja_demo_sandbox'] ) && ! Ninja_Demo()->is_admin_user() ) {
			if ( Ninja_Demo()->sandbox->is_active( $_SESSION['ninja_demo_sandbox'] ) ) {
				if ( is_main_site() ) {
					wp_redirect( get_blog_details( $_SESSION['ninja_demo_sandbox'] )->siteurl );
					die;
				}
			} else {
				unset( $_SESSION['ninja_demo_sandbox'] );
				wp_redirect( add_query_arg( array( 'sandbox_expired' => 1 ), get_blog_details( 1 )->siteurl ) );
				die();
			}
		}

		// If this user is on the main blog and logged-in in a sandbox, then log them out.
		if ( is_user_logged_in() && ! Ninja_Demo()->is_sandbox() && ! Ninja_Demo()->is_admin_user() ) {
			wp_logout();
			wp_redirect( add_query_arg( array( 'sandbox_expired' => 1 ), get_blog_details( 1 )->siteurl ) );
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
		global $menu, $pagenow, $submenu;
		
		if ( ! Ninja_Demo()->is_admin_user() && is_admin() ) {
			$sub_menu = Ninja_Demo()->html_entity_decode_deep( $submenu );
			$allowed_pages = apply_filters( 'nd_allowed_pages', array( 'options.php', 'index.php' ) );
			$allowed_cpts = array();
			$allowed_cts = array();
			Ninja_Demo()->settings['parent_pages'][] = 'index.php';

			$allowed_menu_links = apply_filters( 'nd_show_menu_pages', Ninja_Demo()->settings['parent_pages'] );
			$allowed_submenu_links = apply_filters( 'nd_show_submenu_pages', Ninja_Demo()->settings['child_pages'] );
			
			foreach ( $menu as $item ) {
				$parent_slug = $item[2];
				if ( ! in_array( $parent_slug, $allowed_menu_links ) ) {
					remove_menu_page( $parent_slug );
				} else {
					$allowed_pages[] = $parent_slug;
				}

				if ( isset ( $sub_menu[ $parent_slug ] ) ) {
					foreach( $sub_menu[ $parent_slug ] as $sub_item ) {
						$child_slug = $sub_item[2];
						$found = false;
						foreach ( $allowed_submenu_links as $allowed_submenu ) {
							if ( $allowed_submenu['parent'] == $parent_slug && $allowed_submenu['child'] == $child_slug ) {
								if ( strpos( $allowed_submenu['child'], 'post_type=' ) !== false ) {
									// Get our post type from our string.
									$start = strpos( $allowed_submenu['child'], 'post_type=' ) + 10;
									$end = strpos( $allowed_submenu['child'], '&', $start );
									$length = $end - $start;
									if ( $end !== false ) {
										$substr = substr( $allowed_submenu['child'], $start, $length );
									} else {
										$substr = substr( $allowed_submenu['child'], $start );
									}
									
									$post_type = $substr;

								} else {
									// Default to the 'post' post_type.
									$post_type = 'post';
								}

								// Check to see if we also have a taxonomy in the string.
								if ( strpos( $allowed_submenu['child'], 'taxonomy=' ) !== false ) {
									// Get our custom taxonomy from our string.
									$start = strpos( $allowed_submenu['child'], 'taxonomy=' ) + 9;
									$end = strpos( $allowed_submenu['child'], '&', $start );
									$length = $end - $start;
									if ( $end !== false ) {
										$substr = substr( $allowed_submenu['child'], $start, $length );
									} else {
										$substr = substr( $allowed_submenu['child'], $start );
									}
									
									$taxonomy = $substr;
								}

								if ( strpos( $allowed_submenu['child'], 'edit.php' ) !== false ) {
									$allowed_cpts[ $post_type ]['edit'] = 1;
								} else if ( strpos( $allowed_submenu['child'], 'post-new.php' ) !== false ) {
									$allowed_cpts[ $post_type ]['new'] = 1;
								} else if ( strpos( $allowed_submenu['child'], 'edit-tags.php' ) !== false ) {
									$allowed_cts[ $post_type ][ $taxonomy ]['edit'] = 1;
								}

								$found = true;
							}
						}

						if ( $found ) {
							$allowed_pages[] = $child_slug;							
						} else {
							remove_submenu_page( htmlentities( $parent_slug ), htmlentities( $child_slug ) );
						}				
					}
				}
			}

			// Filter our allowed list of custom post types.
			$allowed_cpts = apply_filters( 'nd_allowed_cpts', $allowed_cpts );			

			// Filter our allowed list of custom taxonomies.
			$allowed_cts = apply_filters( 'nd_allowed_cts', $allowed_cts );

			// Get our current post type.
			if ( ! isset ( $_REQUEST['post_type'] ) ) {
				if ( isset ( $_REQUEST['post'] ) ) {
					$post_type = get_post_type( $_REQUEST['post'] );
				} else {
					$post_type = 'post';
				}
			} else {
				$post_type = $_REQUEST['post_type'];
			}			

			// Get our current taxonomy.
			if ( isset ( $_REQUEST['taxonomy'] ) ) {
				$taxonomy = $_REQUEST['taxonomy'];
			}
 			
			if ( $pagenow == 'edit.php' || $pagenow == 'post.php' ) {

				if ( ! isset ( $allowed_cpts[ $post_type ]['edit'] ) || $allowed_cpts[ $post_type ]['edit'] != 1 ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
				}

			} else if ( $pagenow == 'post-new.php' ) {

				if ( ! isset ( $allowed_cpts[ $post_type ]['new'] ) || $allowed_cpts[ $post_type ]['new'] != 1 ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
				}

			} else if ( $pagenow == 'edit-tags.php' ) {

				if ( ! isset ( $allowed_cts[ $post_type ][ $taxonomy ]['edit'] ) || $allowed_cts[ $post_type ][ $taxonomy ]['edit'] != 1 ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
				}

			} else if ( $pagenow == 'admin.php' || $pagenow == 'index.php' ) {
				$screen = get_current_screen();
				if ( $screen->id == 'dashboard' ) {
					$found = true;
				} else {
					$found = false;					
				}

				foreach ( $allowed_pages as $page ) {
					if ( preg_match( "/". $page . "$/", $screen->id ) !== 0 ) {
						$found = true;
						break;
					}
				}

				if ( ! $found )
	  				wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
			} else {

	  			$page_now = basename( add_query_arg( array() ) );

	  			if ( ! in_array( $page_now, $allowed_pages ) && $page_now != 'wp-admin' )
	  				wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );				
			}
			
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
		if ( ! Ninja_Demo()->is_admin_user() )
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

		if ( ! Ninja_Demo()->is_admin_user() )
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
		if ( ! Ninja_Demo()->is_admin_user() ) {
			$wp_admin_bar->remove_node( 'appearance' );
			$wp_admin_bar->remove_node( 'my-sites' );
			$wp_admin_bar->remove_node( 'new-content' );
			$wp_admin_bar->remove_node( 'comments' );
		} else {
			// We do, however, want to add a straight link to the my-sites.php page.
			$elements = $wp_admin_bar->get_nodes();
			if ( is_array ( $elements ) ) {
				foreach( $elements as $element ) {

			        if ( $element->parent == 'my-sites-list' ) {
			        	if ( $element->id != 'blog-1' )
			        		$wp_admin_bar->remove_node( $element->id );
			        }
				}				
			}
		}
	}

	/**
	 * Prevent a user from deleting the main blog
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function prevent_delete_blog( $blog_id, $drop ) {
		if ( $blog_id == 1 && ! Ninja_Demo()->is_admin_user() )
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'ninja-demo' ) );
	} 
}