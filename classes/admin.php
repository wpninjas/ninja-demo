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
		$page = add_menu_page( __( 'Demo WP PRO', 'demo-wp' ) , __( 'Demo WP PRO', 'demo-wp' ), apply_filters( 'dwp_admin_menu_capabilities', 'manage_network_options' ), 'demo-wp', array( $this, "output_admin_page" ), "", "32.1337" );
		add_action( 'admin_print_styles-' . $page, array( $this, 'admin_css' ) );

		$sub_page = add_submenu_page( 'demo-wp', __( 'Demo WP PRO', 'demo-wp' ) , __( 'Settings', 'demo-wp' ), apply_filters( 'dwp_admin_menu_capabilities', 'manage_network_options' ), 'demo-wp' );
		add_action( 'admin_print_styles-' . $sub_page, array( $this, 'admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_js' ) );

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
	 * Enqueue our admin JS script
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function admin_js() {

		if ( ! Demo_WP()->is_admin_user() && Demo_WP()->is_sandbox() ) {
			wp_enqueue_script( 'demo-wp-monitor', DEMO_WP_URL .'assets/js/monitor.js', array( 'jquery', 'heartbeat' ) );
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
		$sub_menu = Demo_WP()->html_entity_decode_deep( $submenu );
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
								$count = Demo_WP()->sandbox->count();
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
										<label for="offline"><?php _e( 'Offline Mode', 'demo-wp' ); ?></label>
									</th>
									<td>
										<fieldset>
											<input type="hidden" name="offline" value="0">
											<label><input type="checkbox" id="offline" name="offline" value="1" <?php checked( 1, Demo_WP()->settings['offline'] ); ?>> <?php _e( 'Delete current sandboxes and take demo completely offline', 'demo-wp' ); ?></label>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="prevent_clones"><?php _e( 'Pevent New Sandboxes', 'demo-wp' ); ?></label>
									</th>
									<td>
										<fieldset>
											<input type="hidden" name="prevent_clones" value="0">
											<label><input type="checkbox" id="prevent_clones" name="prevent_clones" value="1" <?php checked( 1, Demo_WP()->settings['prevent_clones'] ); ?>> <?php _e( 'Keep current sandboxes, but prevent new sandboxes from being created', 'demo-wp' ); ?></label>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="auto_login"><?php _e( 'Auto-Login Users As', 'demo-wp' ); ?></label>
									</th>
									<td>
										<fieldset>
											<select name="auto_login">
												<option value=""><?php _e( '- None', 'demo-wp' ); ?>
												<?php
												$users = get_users( 1 );
												if ( is_array( $users ) ) {
													foreach ( $users as $user ) {
														if ( ! user_can( $user->ID, 'manage_network_options' ) ) {
															?>
															<option value="<?php echo $user->ID; ?>" <?php selected( $user->ID, Demo_WP()->settings['auto_login'] ); ?> ><?php echo $user->user_login;?></option>
															<?php
														}
													}
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
											<input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete All Sandboxes', 'demo-wp' ); ?>">
										</fieldset>
									</td>
								</tr>
							</tbody>
							</table>

							<h2><?php _e( 'Restriction Settings', 'demo-wp' ); ?></h2>
							<h3><?php _e( 'Whitelist: allow users to accessing these pages', 'demo-wp' ); ?></h3>
							<div class="dwp-admin-restrict">
								<input type="hidden" name="demo_wp_parent_pages[]" value="">
								<input type="hidden" name="demo_wp_child_pages[]" value="">
							<?php
							foreach( $menu as $page ) {
								if ( isset ( $page[0] ) && $page[0] != '' && $page[2] != 'demo-wp' && $page[2] != 'plugins.php' ) {
									$parent_slug = $page[2];
									$class_name = str_replace( '.', '', $parent_slug );
									?>
									<div class="dwp-parent-div box">
										<h4><label><input type="checkbox" name="demo_wp_parent_pages[]" value="<?php echo $page[2];?>" class="demo-wp-parent" <?php checked( in_array( $page[2], Demo_WP()->settings['parent_pages'] ) ); if ( $parent_slug == 'index.php' ) { echo 'disabled="disabled"'; } ?> > <?php echo $page[0]; ?></label></h4>
									<?php
									if ( isset ( $sub_menu[ $parent_slug ] ) ) {
										?>
										<ul style="margin-left:30px;">
										<?php
										foreach( $sub_menu[ $parent_slug ] as $subpage ) {
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
											<li><label><input type="checkbox" name="demo_wp_child_pages[]" value="<?php echo $subpage[2]; ?>" <?php echo $checked; if ( $subpage[2] == 'index.php' ) { echo 'disabled="disabled"'; } ?>> <?php echo $subpage[0]; ?></label></li>
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
				$( document ).on( 'click', '#delete_all_sandboxes', function( e ) {
					var answer = confirm( '<?php _e( 'Really delete all sandboxes?', 'demo-wp' ); ?>' );
					return answer;
				});
				$( document ).on( 'change', '.demo-wp-parent', function() {
					$( this ).parent().parent().parent().find( 'ul input' ).attr( 'checked', this.checked );
				});
				$('.dwp-admin-restrict').masonry({
				  itemSelector: '.box',
				  columnWidth: 1,
				  gutterWidth: 5
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
		$sub_menu = Demo_WP()->html_entity_decode_deep( $submenu );
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
							$key = Demo_WP()->recursive_array_search( $page, $sub_menu );
							$child_pages[] = array( 'parent' => $key, 'child' => $page );
						}
						Demo_WP()->settings['child_pages'] = $child_pages;
					}

					if ( isset ( $_POST['offline'] ) && $_POST['offline'] == 1 )
						Demo_WP()->sandbox->delete_all();

					Demo_WP()->settings['offline'] = $_POST['offline'];
					Demo_WP()->settings['prevent_clones'] = $_POST['prevent_clones'];
					Demo_WP()->settings['log'] = $_POST['log'];
					Demo_WP()->settings['auto_login'] = $_POST['auto_login'];

					Demo_WP()->update_settings( Demo_WP()->settings );

				} else if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Demo_WP()->sandbox->delete_all();
				}
			}
		}
	}
}
