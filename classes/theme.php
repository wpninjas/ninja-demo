<?php
/**
 * Ninja_Demo_Theme
 *
 * This class handles theme switching.
 * It checks to see if we should serve a demo site for the theme or a simple theme switch.
 *
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Ninja_Demo_Theme {

	/**
	 * Get things started
	 * 
	 * @access public
	 * @since 1.0.4
	 * @return void
	 */
	public function __construct() {
		// Switch our theme.
        add_action( 'setup_theme', array( $this, 'switch_theme_listener' ) );
	}

	/**
	 * When we switch themes, check to see if we should switch themes or serve a subsite.
	 * 
	 * @access public
	 * @since 1.0.4
	 * @return void
	 */
	public function switch_theme( $new_name = '', $new_theme = '' ) {
		// We have to grab our plugin settings because the Ninja_Demo()->settings var hasn't been setup yet.
		$plugin_settings = get_site_option( 'ninja_demo' );

		if ( isset ( $plugin_settings['theme_sites'][ $new_theme ] ) && $plugin_settings['theme_sites'][ $new_theme ] != '' ) {
			$blog_id = $plugin_settings['theme_sites'][ $new_theme ];
			// Are we in a sandbox?
			if ( get_option( 'nd_sandbox' ) == 1 && $blog_id != get_current_blog_id() ) {
				// If we are in a sandbox, we need to delete our current sandbox and create a new clone of the target blog id
				// Add our init action to do that.
				add_action( 'init', array( $this, 'change_sandboxes' ), 999 );
				// Store our $blog_id
				Ninja_Demo()->cached_source_id = $blog_id;
			} else {
				// If we aren't in a sandbox, and we aren't on the target site, redirect.
				if ( $blog_id != get_current_blog_id() ) {
					wp_redirect( get_blog_details( $blog_id )->siteurl );
					die();					
				}
			}
		}
	}

	/**
     * Switch our theme
     * 
     * @access public
     * @since 1.0.4
     * @return void
     */
    public function switch_theme_listener() {
        if ( isset ( $_REQUEST['demo_theme'] ) ) {
        	$plugin_settings = get_site_option( 'ninja_demo' );
            $new_theme = $_REQUEST['demo_theme'];
            if ( ! isset ( $plugin_settings['theme_sites'][ $new_theme ] ) || $plugin_settings['theme_sites'][ $new_theme ] == '' ) {
				$settings = get_option( 'ninja_demo' );
				$theme_site = isset ( $settings['theme_site'] ) ? $settings['theme_site'] : '';
				if ( empty( $theme_site ) ) {
					switch_theme( $new_theme );
				}
            } else {
            	$this->switch_theme( '', $new_theme );
            }
        }
    }

    /**
     * Change sandboxes
     * 
     * @access public
     * @since 1.0.4
     * @return void
     */
    public function change_sandboxes() {
    	// Get our current sandbox name.
    	$key = Ninja_Demo()->sandbox->get_key();
    	// Delete our current sandbox
    	Ninja_Demo()->sandbox->delete( get_current_blog_id() );
    	// Create a new sandbox.
    	Ninja_Demo()->sandbox->create( Ninja_Demo()->cached_source_id, $key );
    }
}