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
		?>
		<form id="ninja_demo_admin" enctype="multipart/form-data" method="post" name="" action="">
			<input type="hidden" name="ninja_demo_submit" value="1">
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

							<h2><?php _e( 'Sandbox Settings', 'ninja-demo' ); ?> <span>( <?php echo $count . ' ' . $count_msg; ?> )</span></h2>
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
										<label for="prevent_clones"><?php _e( 'Pevent New Sandboxes', 'ninja-demo' ); ?></label>
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
											<select name="auto_login">
												<option value=""><?php _e( '- None', 'ninja-demo' ); ?>
												<?php
												$users = get_users( 1 );
												if ( is_array( $users ) ) {
													foreach ( $users as $user ) {
														if ( ! user_can( $user->ID, 'manage_network_options' ) ) {
															?>
															<option value="<?php echo $user->ID; ?>" <?php selected( $user->ID, Ninja_Demo()->settings['auto_login'] ); ?> ><?php echo $user->user_login;?></option>
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
											<input type="submit" class="button-secondary" id="delete_all_sandboxes" name="delete_all_sandboxes" value="<?php _e( 'Delete All Sandboxes', 'ninja-demo' ); ?>">
										</fieldset>
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
									?>
									<div class="nd-parent-div box">
										<h4><label><input type="checkbox" name="ninja_demo_parent_pages[]" value="<?php echo $page[2];?>" class="ninja-demo-parent" <?php checked( in_array( $page[2], Ninja_Demo()->settings['parent_pages'] ) ); if ( $parent_slug == 'index.php' ) { echo 'disabled="disabled" checked="checked"'; } ?> > <?php echo $page[0]; ?></label></h4>
									<?php
									if ( isset ( $sub_menu[ $parent_slug ] ) ) {
										?>
										<ul style="margin-left:30px;">
										<?php
										foreach( $sub_menu[ $parent_slug ] as $subpage ) {
											$found = false;
											foreach ( Ninja_Demo()->settings['child_pages'] as $child_page ) {
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

			if ( isset ( $_POST['ninja_demo_submit'] ) && $_POST['ninja_demo_submit'] == 1 && wp_verify_nonce( $nonce, 'ninja_demo_save' ) ) {
				// Check to see if we've hit the freeze or thaw button
				if ( isset ( $_POST['ninja_demo_settings'] ) ) {

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

					if ( isset ( $_POST['offline'] ) && $_POST['offline'] == 1 )
						Ninja_Demo()->sandbox->delete_all();

					Ninja_Demo()->settings['offline'] = $_POST['offline'];
					Ninja_Demo()->settings['prevent_clones'] = $_POST['prevent_clones'];
					Ninja_Demo()->settings['log'] = $_POST['log'];
					Ninja_Demo()->settings['auto_login'] = $_POST['auto_login'];

					Ninja_Demo()->update_settings( Ninja_Demo()->settings );

				} else if ( isset ( $_POST['delete_all_sandboxes'] ) ) {
					Ninja_Demo()->sandbox->delete_all();
				}
				Ninja_Demo()->purge_wpengine_cache();
			}
		}
	}
}
