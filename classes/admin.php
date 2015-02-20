<?php
/**
 * Ninja_Demo_Admin
 *
 * This class handles the output ans saving our admin settings
 *
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Ninja_Demo_Admin {

	/**
	 * Get everything started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		// add admin menus
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 999 );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu_page' ), 999 );

		add_action( 'admin_init', array( $this, 'save_admin_page' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function add_menu_page() {
		$page = add_menu_page( __( 'Ninja Demo', 'ninja-demo' ) , __( 'Ninja Demo', 'ninja-demo' ), apply_filters( 'nd_admin_menu_capabilities', 'manage_network_options' ), 'ninja-demo', array( $this, 'output_admin_page' ), '', '32.1337' );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );

		$sub_page = add_submenu_page( 'ninja-demo', __( 'Ninja Demo', 'ninja-demo' ) , __( 'Settings', 'ninja-demo' ), apply_filters( 'nd_admin_menu_capabilities', 'manage_network_options' ), 'ninja-demo' );
		add_action( 'admin_print_styles-' . $sub_page, array( $this, 'admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );

	}

	/**
	 * Add our network admin menu page
	 * 
	 * @access public
	 * @since 1.0.4
	 * @return void
	 */
	public function add_network_menu_page() {
		$page = add_menu_page( __( 'Ninja Demo', 'ninja-demo' ) , __( 'Ninja Demo', 'ninja-demo' ), 'manage_network_options', 'ninja-demo', array( $this, 'output_network_admin_page' ), '', '32.1337' );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );
	}

	/**
	 * Enqueue our admin CSS script
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_css() {
		wp_enqueue_style( 'ninja-demo-admin', ND_PLUGIN_URL .'assets/css/admin.css');
	}

	/**
	 * Enqueue our admin JS script
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_js() {

		if ( ! Ninja_Demo()->is_admin_user() && Ninja_Demo()->is_sandbox() ) {
			wp_enqueue_script( 'ninja-demo-monitor', ND_PLUGIN_URL .'assets/js/monitor.js', array( 'jquery', 'heartbeat' ) );
		}
		
		wp_enqueue_script( 'jquery-masonry' );
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
		$sub_menu = Ninja_Demo()->html_entity_decode_deep( $submenu );
		$tabs = apply_filters( 'nd_settings_tabs', array( 
			'general' => __( 'General', 'ninja-demo' ), 
			//'theme' => __( 'Theme', 'ninja-demo' ) 
			) );
		
		if ( isset ( $_REQUEST['tab'] ) ) {
			$current_tab = $_REQUEST['tab'];
		} else {
			$current_tab = 'general';
		}

		?>
		<form id="ninja_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="ninja_demo_submit" value="1">
			<?php wp_nonce_field( 'ninja_demo_save','ninja_demo_admin_submit' ); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<?php
					foreach ( $tabs as $slug => $nicename ) {
						if ( $slug == $current_tab ) {
							?>
							<span class="nav-tab nav-tab-active"><?php echo $nicename; ?></span>
							<?php
						} else {
							?>
							<a href="<?php echo add_query_arg( array( 'tab' => $slug ) ); ?>" class="nav-tab"><?php echo $nicename; ?></a>
							<?php
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

							if ( $current_tab == 'general' ) {
								$count = Ninja_Demo()->sandbox->count( get_current_blog_id() );
								if ( $count == 1 ) {
									$count_msg = __( 'Live Sandbox', 'ninja-demo' );
								} else {
									$count_msg = __( 'Live Sandboxes', 'ninja-demo' );
								}
								?>

								<h2><?php _e( 'Sandbox Settings', 'ninja-demo' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span> <input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete Sandboxes', 'ninja-demo' ); ?>"></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<label for="offline"><?php _e( 'Offline Mode', 'ninja-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="offline" value="0">
												<label><input type="checkbox" id="offline" name="offline" value="1" <?php checked( 1, Ninja_Demo()->settings['offline'] ); ?>> <?php _e( 'Delete current sandboxes and take demo completely offline', 'ninja-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="prevent_clones"><?php _e( 'Prevent New Sandboxes', 'ninja-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="prevent_clones" value="0">
												<label><input type="checkbox" id="prevent_clones" name="prevent_clones" value="1" <?php checked( 1, Ninja_Demo()->settings['prevent_clones'] ); ?>> <?php _e( 'Keep current sandboxes, but prevent new sandboxes from being created', 'ninja-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="auto_login"><?php _e( 'Auto-Login Users As', 'ninja-demo' ); ?></label>
										</th>
										<td>
											<fieldset>
												<select name="login_role">
													<?php
													$roles = get_editable_roles();
													foreach ( $roles as $slug => $role ) {
														?>
														<option value="<?php echo $slug; ?>" <?php selected( $slug, Ninja_Demo()->settings['login_role'] ); ?>><?php echo $role['name']; ?></option>
														<?php
													}
													?>
												</select>
											</fieldset>
											<span class="howto"></span>
										</td>
									</tr>
								</tbody>
								</table>

								<h2><?php _e( 'Restriction Settings', 'ninja-demo' ); ?></h2>
								<h3><?php _e( 'Whitelist: allow users to access these pages', 'ninja-demo' ); ?></h3>
								<div class="nd-admin-restrict">
									<input type="hidden" name="ninja_demo_parent_pages[]" value="">
									<input type="hidden" name="ninja_demo_child_pages[]" value="">
								<?php
								foreach( $menu as $page ) {
									if ( isset ( $page[0] ) && $page[0] != '' && $page[2] != 'ninja-demo' && $page[2] != 'plugins.php' ) {
										$parent_slug = $page[2];
										$class_name = str_replace( '.', '', $parent_slug );
										$parent_pages = isset ( Ninja_Demo()->settings['parent_pages'] ) ? Ninja_Demo()->settings['parent_pages'] : array();
										$child_pages = isset ( Ninja_Demo()->settings['child_pages'] ) ? Ninja_Demo()->settings['child_pages'] : array();
										?>
										<div class="nd-parent-div box">
											<h4><label><input type="checkbox" name="ninja_demo_parent_pages[]" value="<?php echo $page[2];?>" class="ninja-demo-parent" <?php checked( in_array( $page[2], $parent_pages ) ); if ( $parent_slug == 'index.php' ) { echo 'disabled="disabled" checked="checked"'; } ?> > <?php echo $page[0]; ?></label></h4>
										<?php
										if ( isset ( $sub_menu[ $parent_slug ] ) ) {
											?>
											<ul style="margin-left:30px;">
											<?php
											foreach( $sub_menu[ $parent_slug ] as $subpage ) {
												$found = false;
												foreach ( $child_pages as $child_page ) {
													if ( $child_page['child'] == $subpage[2] ) {
														$found = true;
														break;
													}
												}

												if ( $found !== false ) {
													$checked = 'checked="checked"';
												} else {
													$checked = '';
												}
												?>
												<li><label><input type="checkbox" name="ninja_demo_child_pages[]" value="<?php echo $subpage[2]; ?>" <?php echo $checked; if ( $subpage[2] == 'index.php' ) { echo 'disabled="disabled" checked="checked"'; } ?>> <?php echo $subpage[0]; ?></label></li>
												<?php
											}
											?>
											</ul>
											<?php
										}
										?>
										</div>
										<?php
									}
								}
								?>
								</div>

								<h2><?php _e( 'Debug Settings', 'ninja-demo' ); ?></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<?php _e( 'Enable Logging', 'ninja-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="log" value="0">
												<label><input type="checkbox" name="log" value="1" <?php checked( 1, Ninja_Demo()->settings['log'] ); ?>> <?php _e( 'Create a log file every time a sandbox is created. (Useful for debugging, but can generate lots of files.)', 'ninja-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>									
									<tr>
										<th scope="row">
											<?php _e( 'Table Rows Inserted At Once', 'ninja-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<label><input type="number" name="query_count" value="<?php echo Ninja_Demo()->settings['query_count']; ?>"></label> <span class="howto"><?php _e( 'Ninja Demo will attempt to insert this many database rows at once when cloning a source. Higher numbers will result in faster sandbox creation, but lower numbers are less prone to failure. 4 is a good starting point.', 'ninja-demo' ); ?></span>
											</fieldset>
										</td>
									</tr>
								</tbody>
								</table>
								
								<div>
									<input class="button-primary" name="ninja_demo_settings" type="submit" value="<?php _e( 'Save', 'ninja-demo' ); ?>" />
								</div>
								<?php
							} else if ( $current_tab == 'theme' ) {
								?>
								<h2><?php _e( 'Toolbar Settings', 'ninja-demo' ); ?></h2>
								<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<?php _e( 'Show Theme Toolbar', 'ninja-demo' ); ?>
										</th>
										<td>
											<fieldset>
												<input type="hidden" name="show_toolbar" value="0">
												<label><input type="checkbox" name="show_toolbar" value="1" <?php checked( 1, Ninja_Demo()->settings['show_toolbar'] ); ?>> <?php _e( 'Show theme switcher toolbar on the front-end of your demo.', 'ninja-demo' ); ?></label>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<?php _e( 'Load this site whenever a user switches to the theme', 'ninja-demo' ); ?>
										</th>
										<td>
											<fieldset>						
												<select name="theme_site">
													<option value=""><?php _e( '- None', 'ninja-demo' ); ?></option>
													<?php
													
													$themes = wp_get_themes( array( 'errors' => null , 'allowed' => null ) );
													foreach( $themes as $slug => $theme ) {
														if ( ! isset ( Ninja_Demo()->plugin_settings['theme_sites'][ $slug ] ) || Ninja_Demo()->plugin_settings['theme_sites'][ $slug ] == get_current_blog_id() ) {
															?>
															<option value="<?php echo $slug; ?>" <?php selected( $slug, Ninja_Demo()->settings['theme_site'] ); ?>><?php echo $theme->get( 'Name' ); ?></option>
															<?php										
														}
													}
													?>	
												</select>
												<span class="howto"><?php _e( 'This setting allows you to create different content, widgets, and settings for each of your theme demos. When a user switches to the selected theme, rather than a simple theme switch, this subsite will be shown.', 'ninja-demo' ); ?></span>
											</fieldset>
										</td>
									</tr>
								</tbody>
								</table>
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
				$( document ).on( 'click', '#delete_all_sandboxes', function( e ) {
					var answer = confirm( '<?php _e( 'Really delete all sandboxes?', 'ninja-demo' ); ?>' );
					return answer;
				});
				$( document ).on( 'change', '.ninja-demo-parent', function() {
					$( this ).parent().parent().parent().find( 'ul input' ).attr( 'checked', this.checked );
				});
				$('.nd-admin-restrict').masonry({
				  itemSelector: '.box',
				  columnWidth: 1,
				  gutterWidth: 5
				});
			});
		</script>
		<?php
	}

	/**
	 * Output our network admin page
	 * 
	 * @access public
	 * @since 1.0.4
	 * @return void
	 */
	public function output_network_admin_page() {
		?>
		<form id="ninja_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="ninja_demo_network_submit" value="1">
			<?php wp_nonce_field( 'ninja_demo_save','ninja_demo_admin_submit' ); ?>
			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<span class="nav-tab nav-tab-active"><?php _e( 'Settings', 'ninja-demo' ); ?></span>
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
								$count = Ninja_Demo()->sandbox->count();
								if ( $count == 1 ) {
									$count_msg = __( 'Live Sandbox', 'ninja-demo' );
								} else {
									$count_msg = __( 'Live Sandboxes', 'ninja-demo' );
								}
								?>

							<h2><?php _e( 'Sandbox Settings', 'ninja-demo' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span> <input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete All Sandboxes', 'ninja-demo' ); ?>"></h2>
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
									</th>
									<td>
										<fieldset>
											<?php
											$sites = wp_get_sites();
											foreach ( $sites as $site ) {
												if ( ! Ninja_Demo()->is_sandbox( $site['blog_id'] ) ) {
													echo "<pre>";
													echo $site['path'];
													echo ": ";
													echo Ninja_Demo()->sandbox->count( $site['blog_id'] );
													echo "</pre>";													
												}

											}

											?>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php _e( 'License Key', 'ninja-demo' ); ?>
									</th>
									<td>
										<fieldset>
											<?php
											if ( Ninja_Demo()->plugin_settings['license_status'] == 'valid' ) {
												$img = 'yes.png';
												$valid = true;
											} else {
												$img = 'no.png';
												$valid = false;
											}
											?>
											<img src="<?php echo ND_PLUGIN_URL;?>assets/images/<?php echo $img; ?>"><input type="text" name="license" value="<?php echo Ninja_Demo()->plugin_settings['license']; ?>" class="regular-text">
											<?php
											if ( $valid ) {
											?>
												<input type="submit" class="button-secondary" name="deactivate_license" value="<?php _e( 'Deactivate License', 'ninja-demo' ); ?>">
											<?php
											}
										?>
										</fieldset>
									</td>
								</tr>
							</tbody>
							</table>
							<div>
								<input class="button-primary" name="ninja_demo_settings" type="submit" value="<?php _e( 'Save', 'ninja-demo' ); ?>" />
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
		$sub_menu = Ninja_Demo()->html_entity_decode_deep( $submenu );
		if ( Ninja_Demo()->is_admin_user() ) {
			if ( isset ( $_POST['ninja_demo_admin_submit'] ) ) {
				$nonce = $_POST['ninja_demo_admin_submit'];
			} else {
				$nonce = '';
			}

			if ( isset ( $_REQUEST['tab'] ) ) {
				$current_tab = $_REQUEST['tab'];
			} else {
				$current_tab = 'general';
			}

			if ( isset ( $_POST['ninja_demo_submit'] ) && $_POST['ninja_demo_submit'] == 1 && wp_verify_nonce( $nonce, 'ninja_demo_save' ) ) {
				// Check to see if we've hit the freeze or thaw button
				if ( isset ( $_POST['ninja_demo_settings'] ) ) {
					if ( $current_tab == 'general' ) {
						if ( isset ( $_POST['ninja_demo_parent_pages'] ) ) {
							Ninja_Demo()->settings['parent_pages'] = $_POST['ninja_demo_parent_pages'];
						}

						if ( isset ( $_POST['ninja_demo_child_pages'] ) ) {
							$child_pages = array();

							foreach( $_POST['ninja_demo_child_pages'] as $page ) {
								$key = Ninja_Demo()->recursive_array_search( $page, $sub_menu );
								$child_pages[] = array( 'parent' => $key, 'child' => $page );
							}
							Ninja_Demo()->settings['child_pages'] = $child_pages;
						}

						// If we've checked "offline," delete our sandboxes.
						if ( isset ( $_POST['offline'] ) && $_POST['offline'] == 1 )
							Ninja_Demo()->sandbox->delete_all( get_current_blog_id() );

						// Update our settings.
						Ninja_Demo()->settings['offline'] = $_POST['offline'];
						Ninja_Demo()->settings['prevent_clones'] = $_POST['prevent_clones'];
						Ninja_Demo()->settings['log'] = $_POST['log'];
						Ninja_Demo()->settings['login_role'] = $_POST['login_role'];
						Ninja_Demo()->settings['query_count'] = $_POST['query_count'];
						
					} else if ( $current_tab == 'theme' ) {
						Ninja_Demo()->settings['show_toolbar'] = $_POST['show_toolbar'];
						
						// If the theme_site setting has been selected, we save that value in the plugin settings as well as.
						if ( $_POST['theme_site'] == '' ) {
							$current_theme = Ninja_Demo()->settings['theme_site'];

							if ( $current_theme != '' ) {
								unset( Ninja_Demo()->plugin_settings['theme_sites'][ $current_theme ] );
								Ninja_Demo()->update_plugin_settings( Ninja_Demo()->plugin_settings );
							}
						} else {
							Ninja_Demo()->plugin_settings['theme_sites'][ $_POST['theme_site'] ] = get_current_blog_id();
							Ninja_Demo()->update_plugin_settings( Ninja_Demo()->plugin_settings );
						}
						Ninja_Demo()->settings['theme_site'] = $_POST['theme_site'];
					}

					Ninja_Demo()->update_settings( Ninja_Demo()->settings );
				} else if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Ninja_Demo()->sandbox->delete_all( get_current_blog_id() );
				}
				Ninja_Demo()->purge_wpengine_cache();
			} else if ( isset ( $_POST['ninja_demo_network_submit'] ) && $_POST['ninja_demo_network_submit'] == 1 && wp_verify_nonce( $nonce, 'ninja_demo_save' ) ) {
				if ( isset ( $_POST['ninja_demo_settings'] ) ) {
					// Update our license.
					if ( $_POST['license'] == '' && Ninja_Demo()->plugin_settings['license_status'] == 'valid' ) {
						$this->deactivate_license( Ninja_Demo()->plugin_settings['license'] );
					} else if ( $_POST['license'] != Ninja_Demo()->plugin_settings['license'] ) {
						$this->deactivate_license( Ninja_Demo()->plugin_settings['license'] );
						$this->activate_license( $_POST['license'] );
					} else if ( $_POST['license'] != '' && Ninja_Demo()->plugin_settings['license_status'] != 'valid' ) {
						$this->activate_license( $_POST['license'] );
					}
					Ninja_Demo()->plugin_settings['license'] = $_POST['license'];
				} else if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Ninja_Demo()->sandbox->delete_all();
				} else if ( isset ( $_POST['deactivate_license'] ) ) {
					$this->deactivate_license( Ninja_Demo()->plugin_settings['license'] );
					Ninja_Demo()->plugin_settings['license'] = '';
				}
		 		Ninja_Demo()->update_plugin_settings( Ninja_Demo()->plugin_settings );
			}
		}
	}

	/**
	 * Function that activates our license
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function activate_license( $license ) {
		// data to send in our API request
		$api_params = array( 
			'edd_action'=> 'activate_license', 
			'license' 	=> $license, 
			'item_name' => urlencode( 'Ninja Demo' ) // the name of our product in EDD
		);
 
		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, 'http://ninjademo.com/' ) );
 
		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;
 
		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "valid" or "invalid"
 		Ninja_Demo()->plugin_settings['license_status'] = $license_data->license;
	}

	/**
	 * Function that deactivates our license
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function deactivate_license( $license ) {
		// data to send in our API request
		$api_params = array( 
			'edd_action'=> 'deactivate_license', 
			'license' 	=> $license, 
			'item_name' => urlencode( 'Ninja Demo' ) // the name of our product in EDD
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, 'http://ninjademo.com' ), array( 'timeout' => 15, 'sslverify' => false ) );

 		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if( $license_data->license == 'deactivated' ) {
			// $license_data->license will be either "valid" or "invalid"
			Ninja_Demo()->plugin_settings['license_status'] = 'invalid';
		}
	}
}
