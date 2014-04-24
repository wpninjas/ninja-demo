<?php
/**
 * Demo_WP_Admin
 *
 * This class handles the output ans saving our admin settings
 *
 *
 * @package     Demo WP PRO
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

class Demo_WP_Admin {

	/**
	 * Get everything started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		// add admin menus
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
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
		$page = add_menu_page( "Demo WP PRO" , __( 'Demo WP PRO', 'demo-wp' ), apply_filters( 'dwp_admin_menu_capabilities', 'manage_network_options' ), "demo-wp", array( $this, "output_admin_page" ), "", "32.1337" );
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
		wp_enqueue_style( 'demo-wp-admin', DEMO_WP_URL .'assets/css/admin.css');
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
			<?php wp_nonce_field( 'demo_wp_save','demo_wp_admin_submit' ); ?>
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
							<?php
								$count = Demo_WP()->sandbox->count_sandboxes();
								if ( $count == 1 ) {
									$count_msg = __( 'Live Sandbox', 'demo-wp' );
								} else {
									$count_msg = __( 'Live Sandboxes', 'demo-wp' );
								}
								?>

							<h2><?php _e( 'Sandbox Settings', 'demo-wp' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span></h2>
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php _e( 'Offline Mode', 'demo-wp' ); ?>
									</th>
									<td>
										<fieldset>
											<input type="hidden" name="offline" value="0">
											<label for="offline"><input type="checkbox" name="offline" value="1" <?php checked( 1, Demo_WP()->settings['offline'] ); ?>> <?php _e( 'Delete current sandboxes and take demo completely offline', 'demo-wp' ); ?></label>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php _e( 'Pevent New Sandboxes', 'demo-wp' ); ?>
									</th>
									<td>
										<fieldset>
											<input type="hidden" name="prevent_clones" value="0">
											<label><input type="checkbox" name="prevent_clones" value="1" <?php checked( 1, Demo_WP()->settings['prevent_clones'] ); ?>> <?php _e( 'Keep current sandboxes, but prevent new sandboxes from being created', 'demo-wp' ); ?></label>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php _e( 'Sandbox Lifespan', 'demo-wp' ); ?>
									</th>
									<td>
										<fieldset>
											<?php
											$lifespans = apply_filters( 'dwp_lifespans', array(
												array( 'name' => __( 'One Hour', 'demo-wp' ), 'value' => 3600 ),
												array( 'name' => __( 'Two Hours', 'demo-wp' ), 'value' => 7200 ),
												array( 'name' => __( 'Four Hours', 'demo-wp' ), 'value' => 14400 ),
												array( 'name' => __( 'Six Hours', 'demo-wp' ), 'value' => 21600 ),
												array( 'name' => __( 'Eight Hours', 'demo-wp' ), 'value' => 28800 ),
											) );

											?>
											<select name="lifespan">
												<?php
												foreach( $lifespans as $lifespan ) {
													?>
													<option value="<?php echo $lifespan['value']; ?>" <?php selected( $lifespan['value'], Demo_WP()->settings['lifespan'] ); ?>><?php echo $lifespan['name']; ?></option>
													<?php
												}
												?>

											</select>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
									</th>
									<td>
										<fieldset>
											<input type="submit" class="button-secondary" id="delete_sandboxes" name="delete_sandboxes" value="<?php _e( 'Delete All Sandboxes', 'demo-wp' ); ?>">
										</fieldset>
									</td>
								</tr>
							</tbody>
							</table>

							<h2><?php _e( 'Restriction Settings', 'demo-wp' ); ?></h2>
							<h3><?php _e( 'Prevent users from accessing these pages', 'demo-wp' ); ?></h3>
							<div class="dwp-admin-restrict">
								<input type="hidden" name="demo_wp_parent_pages[]" value="">
								<input type="hidden" name="demo_wp_child_pages[]" value="">
							<?php
							$x = 0;
							foreach( $menu as $page ) {
								if ( $x == 0 ) {
									?>
										<ul class="dwp-parent-ul" style="float:left;">
									<?php
								}
								if ( isset ( $page[0] ) && $page[0] != '' && $page[2] != 'demo-wp' && $page[2] != 'plugins.php' ) {
									$parent_slug = $page[2];
									$class_name = str_replace( '.', '', $parent_slug );
									?>
									<li><label><input type="checkbox" name="demo_wp_parent_pages[]" value="<?php echo $page[2];?>" class="demo-wp-parent" <?php checked( in_array( $page[2], Demo_WP()->settings['parent_pages'] ) ); ?>> <?php echo $page[0]; ?></label>
									<?php
									if ( isset ( $submenu[ $parent_slug ] ) ) {
										?>
										<ul style="margin-left:30px;">
										<?php
										foreach( $submenu[ $parent_slug ] as $subpage ) {
											$found = false;
											foreach ( Demo_WP()->settings['child_pages'] as $child_page ) {
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
											<li><label><input type="checkbox" name="demo_wp_child_pages[]" value="<?php echo $subpage[2]; ?>" <?php echo $checked; ?>> <?php echo $subpage[0]; ?></label></li>
											<?php
										}
										?>
										</ul>
									</li>
										<?php
									}
								}

								if ( $x == 6 ) {
									?>
										</ul>
									<?php
									$x = 0;
								} else {
									$x++;
								}
							}
							?>
							</div>

							<h2><?php _e( 'Debug Settings', 'demo-wp' ); ?></h2>
							<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<?php _e( 'Enable Logging', 'demo-wp' ); ?>
									</th>
									<td>
										<fieldset>
											<input type="hidden" name="log" value="0">
											<label><input type="checkbox" name="log" value="1" <?php checked( 1, Demo_WP()->settings['log'] ); ?>> <?php _e( 'Create a log file every time a sandbox is created. (Useful for debugging, but can generate lots of files.)', 'demo-wp' ); ?></label>
										</fieldset>
									</td>
								</tr>
							</tbody>
							</table>
							<div>
								<input class="button-primary" name="demo_wp_settings" type="submit" value="<?php _e( 'Save', 'demo-wp' ); ?>" />
							</div>

						</div><!-- /#post-body-content -->
					</div><!-- /#post-body -->
				</div>
			</div>
		<!-- </div>/.wrap-->
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$( document ).on( 'click', '#delete_sandboxes', function( e ) {
					var answer = confirm( '<?php _e( 'Really delete all sanboxes?', 'demo-wp' ); ?>' );
					return answer;
				});
				$( document ).on( 'change', '.demo-wp-parent', function() {
					$( this ).parent().parent().find( 'ul input' ).attr( 'checked', this.checked );
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
		global $menu, $submenu;
		if ( Demo_WP()->is_admin_user() ) {
			if ( isset ( $_POST['demo_wp_admin_submit'] ) ) {
				$nonce = $_POST['demo_wp_admin_submit'];
			} else {
				$nonce = '';
			}

			if ( isset ( $_POST['demo_wp_submit'] ) && $_POST['demo_wp_submit'] == 1 && wp_verify_nonce( $nonce, 'demo_wp_save' ) ) {
				// Check to see if we've hit the freeze or thaw button
				if ( isset ( $_POST['demo_wp_settings'] ) ) {

					if ( isset ( $_POST['demo_wp_parent_pages'] ) ) {
						Demo_WP()->settings['parent_pages'] = $_POST['demo_wp_parent_pages'];
					}

					if ( isset ( $_POST['demo_wp_child_pages'] ) ) {
						$child_pages = array();
						foreach( $_POST['demo_wp_child_pages'] as $page ) {
							$key = Demo_WP()->recursive_array_search( $page, $submenu );
							$child_pages[] = array( 'parent' => $key, 'child' => $page );
						}
						Demo_WP()->settings['child_pages'] = $child_pages;
					}

					if ( isset ( $_POST['offline'] ) && $_POST['offline'] == 1 )
						Demo_WP()->sandbox->delete_all_sandboxes();

					Demo_WP()->settings['offline'] = $_POST['offline'];
					Demo_WP()->settings['prevent_clones'] = $_POST['prevent_clones'];
					Demo_WP()->settings['lifespan'] = $_POST['lifespan'];
					Demo_WP()->settings['log'] = $_POST['log'];

					Demo_WP()->update_settings( Demo_WP()->settings );

				} else if ( isset ( $_POST['delete_sandboxes'] ) ) {
					Demo_WP()->sandbox->delete_all_sandboxes();
				}
			}
		}
	}
}
