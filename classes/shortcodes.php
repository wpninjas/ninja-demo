<?php
/**
 * Demo_WP_Shortcodes
 *
 * This class handles outputting of our shortcodes:
 * 1) The "Try Demo Now" button
 * 2) Our "Time left" counter
 * 3) Our "Reset Demo" button
 *
 * @package     Demo WP PRO
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

class Demo_WP_Shortcodes {

	/**
	 * Get things started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'dwp_try_demo', array( $this, 'try_button' ) );
		add_action( 'init', array( $this, 'create_listen' ) );

		add_shortcode( 'dwp_reset_sandbox', array( $this, 'reset_button' ) );
		add_action( 'init', array( $this, 'reset_listen' ) );

		add_shortcode( 'is_sandbox', array( $this, 'is_sandbox' ) );
		add_shortcode( 'is_not_sandbox', array( $this, 'is_not_sandbox' ) );
	}

	/**
	 * Shortcode function that outputs a button for users to try the demo/create a new sandbox.
	 *
	 * @access public
	 * @since 1.0
	 * @return string $output
	 */
	public function try_button() {
		$spam_q = $this->get_spam_question();
		$spam_a = $this->get_spam_answer( $spam_q );
		$tid = $this->set_transient( $spam_a );
		// Check to see if our IP address is locked out.
		$ip = $_SERVER['REMOTE_ADDR'];
		$ip_lockout = Demo_WP()->ip->check_ip_lockout( $ip );
		// Get the number of tries we have left before we are locked out.
		if ( isset ( $_SESSION['demo_wp_failed'] ) ) {
			$tries = 4 - $_SESSION['demo_wp_failed'];
		}

		ob_start();
		if ( is_main_site() ) {
		?>
		<a id="demo-wp"></a>
		<div class="dwp-start-demo">
			<?php
			// Check to
			if ( ! $ip_lockout ) {
				?>
				<form action="#demo-wp" method="post" enctype="multipart/form-data" class="dwp-start-demo-form">
					<?php wp_nonce_field( 'demo_wp_create_sandbox','demo_wp_sandbox' ); ?>
					<input name="dwp_create_sandbox" type="hidden" value="1">
					<input name="tid" type="hidden" value="<?php echo $tid; ?>">
					<div>
						<label class="dwp-answer-field"><?php echo _e( 'What does ', 'demo-wp' ) . $spam_q; ?><input type="number" name="spam_a"></label>
						<?php
						if ( isset ( $_POST['spam_a'] ) && $_POST['spam_a'] != get_transient( $_POST['tid'] ) ) {
						?>
							<div>
								<?php _e( 'Incorrect answer. Please try again.', 'demo-wp' ); ?>
							</div>
							<div>
								<?php
								if ( $tries == 1 ) {
									printf( __( 'You have %d attempt left before your IP address is locked out for 20 minutes.', 'demo-wp' ), $tries );
								} else {
									printf( __( 'You have %d attempts left before your IP address is locked out for 20 minutes.', 'demo-wp' ), $tries );
								}
								?>
							</div>
						<?php
						}
					?>
					</div>
					<div class="demo-wp-hidden">
						<label>
							<?php
							_e( 'If you are a human and are seeing this field, please leave it blank.', 'ninja-forms' );
							?>
						</label>
						<input name="spamcheck" type="text" value="">
					</div>
					<p class="submit no-border">
						<input name="submit" value="<?php _e( 'Try the demo!', 'demo-wp' ) ?>" type="submit" /><br /><br />
					</p>
				</form>

				<?php
			} else {
				$expires = round( ( $ip_lockout - current_time( 'timestamp' ) ) / 60 );
				if ( $expires < 1 ) {
					$expires = 1;
				}
				printf( __( 'Your IP address is currently locked out for %d minute.', 'demo-wp' ), $expires );

				if ( $expires == 1 ) {
					printf( __( 'Your IP address is currently locked out for %d minute.', 'demo-wp' ), $expires );
				} else {
					printf( __( 'Your IP address is currently locked out for %d minutes.', 'demo-wp' ), $expires );
				}

				?>
				<h4><?php
				if ( $expires == 1 ) {
					printf( __( 'Your IP address is currently locked out for %d minute.', 'demo-wp' ), $expires );
				} else {
					printf( __( 'Your IP address is currently locked out for %d minutes.', 'demo-wp' ), $expires );
				}

				?></h4>
				<?php
			}
			?>
		</div>
		<?php
		} // End is_main_site check
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * Listen for our create sandbox button.
	 * If everything passes, call the Demo_WP()->sandbox->create() function.
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function create_listen() {
		// If this user already has a sandbox created and it exists, then redirect them to that sandbox
		if ( isset ( $_SESSION['demo_wp_sandbox'] ) && ! Demo_WP()->is_admin_user() ) {

			if ( Demo_WP()->sandbox->is_alive( $_SESSION['demo_wp_sandbox'] ) ) {
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

		// Bail if the "prevent_clones" has been set to 1
		if ( Demo_WP()->settings['prevent_clones'] == 1 )
			return false;

		// Bail if this user's IP is on our blocked list
		if ( Demo_WP()->ip->check_ip_lockout( $_SERVER['REMOTE_ADDR'] ) )
			return false;

		// Bail if we haven't clicked the tryout button
		if ( ! isset ( $_POST['dwp_create_sandbox'] ) || $_POST['dwp_create_sandbox'] != 1 )
			return false;

		// Bail if we don't have a nonce
		if ( ! isset ( $_POST['demo_wp_sandbox'] ) )
			return false;

		// Bail if our nonce isn't correct
		if ( ! wp_verify_nonce( $_POST['demo_wp_sandbox'], 'demo_wp_create_sandbox' ) )
			return false;

		// Bail if our honey-pot field has been filled in
		if ( isset ( $_POST['spamcheck'] ) && $_POST['spamcheck'] !== '' )
			return false;

		// Bail if we haven't sent an answer to the anti-spam question
		if ( ! isset( $_POST['spam_a'] ) || ! isset ( $_POST['tid'] ) )
			return false;

		// Bail if our anti-spam answer isn't correct
		if ( $_POST['spam_a'] != get_transient( $_POST['tid'] ) ) {
			// Add 1 to the number of times this user has failed to login
			if ( ! isset ( $_SESSION['demo_wp_failed'] ) ) {
				$_SESSION['demo_wp_failed'] = 1;
			} else {
				$_SESSION['demo_wp_failed']++;
			}

			if ( $_SESSION['demo_wp_failed'] >= 4 ) {
				// Add this user to the IP lockout table.
				Demo_WP()->ip->lockout_ip( $_SERVER['REMOTE_ADDR'] );
				$_SESSION['demo_wp_failed'] = 0;
			}
			// Remove our transient answer
			delete_transient( $_POST['tid'] );

			return false;
		}
		// Remove our transient answer
		delete_transient( $_POST['tid'] );

		Demo_WP()->sandbox->create();
	}

	/**
	 * Generate our anti-spam question
	 *
	 * @access private
	 * @since 1.0
	 * @return string $eq Our anti-spam equation
	 */
	private function get_spam_question() {
		$num = range( 1, 10 );
		$op = array( '+', '-' );
		$num1 = $num[ array_rand( $num ) ];
		$num2 = $num[ array_rand( $num ) ];
		$op = $op[ array_rand( $op ) ];
		if ( $num1 < $num2 ) {
			list( $num1, $num2 ) = array( $num2, $num1 );
		}
		$eq = $num1 . ' ' . $op . ' ' . $num2 . ' = ';
		return $eq;
	}

	/**
	 * Return the answer to our generated anti-spam question
	 *
	 * @access private
	 * @since 1.0
	 * @return string $answer
	 */
	private function get_spam_answer( $eq ) {
		$eq = trim($eq);     // trim white spaces
		$eq = str_replace( '=', '', $eq );
	    $eq = preg_replace ('[^0-9\+-\*\/\(\) ]', '', $eq);    // remove any non-numbers chars; exception for math operators

	    $compute = create_function("", "return (" . $eq . ");" );
	    return 0 + $compute();
	}

	/**
	 * Store our anti-spam answer in a transient and return the transient ID
	 *
	 * @access private
	 * @since 1.0
	 * @return string $tid Our transient ID
	 */
	private function set_transient( $value ) {
		// Get a random string
		$key = Demo_WP()->random_string();
		// Make sure that this key isn't already used in a transient
		if ( get_transient( $key ) !== false ) {
			return $this->set_transient( $value );
		} else {
			set_transient( $key, $value, 300 );
		}
		return $key;
	}

	/**
	 * Output a button for resetting the demo
	 * 
	 * @access public
	 * @since 1.0
	 * @return string $output
	 */
	public function reset_button() {
		// Bail if we aren't in a live sandbox
		if ( ! Demo_WP()->is_sandbox() || ! Demo_WP()->sandbox->is_alive() )
			return false;

		ob_start();
		?>
		<form action="" method="post" enctype="multipart/form-data" class="dwp-reset-demo-form">
			<input type="hidden" name="reset_sandbox" value="1">
			<?php wp_nonce_field( 'demo_wp_reset_sandbox','demo_wp_sandbox' ); ?> 
			<input type="submit" name="reset_sandbox_submit" value="<?php _e( 'Reset Sandbox Content', 'demo-wp' ); ?>">
		</form>
		<?php
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * Listen for our reset button
	 * 
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function reset_listen() {
		// Bail if our $_POST value isn't set.
		if ( ! isset ( $_POST['reset_sandbox'] ) || $_POST['reset_sandbox'] != 1 )
			return false;

		// Bail if our user doesn't have a current blog_id in their $_SESSION variable
		if ( ! isset ( $_SESSION['demo_wp_sandbox'] ) || empty ( $_SESSION['demo_wp_sandbox'] ) )
			return false;

		// Bail if we don't have a nonce
		if ( ! isset ( $_POST['demo_wp_sandbox'] ) )
			return false;

		// Bail if our nonce isn't correct
		if ( ! wp_verify_nonce( $_POST['demo_wp_sandbox'], 'demo_wp_reset_sandbox' ) )
			return false;

		Demo_WP()->sandbox->reset();
	}

	/**
	 * is_sandbox shortcode
	 * 
	 * @access public
	 * @since 1.0
	 * @return string $content or bool(false)
	 */
	public function is_sandbox( $atts, $content = null ) {
		if ( Demo_WP()->is_sandbox() ) {
			return $content;
		} else {
			return false;
		}
	}

	/**
	 * is_not_sandbox shortcode
	 * 
	 * @access public
	 * @since 1.0
	 * @return string $content or bool(false)
	 */
	public function is_not_sandbox( $atts, $content = null ) {
		if ( ! Demo_WP()->is_sandbox() ) {
			return $content;
		} else {
			return false;
		}
	}
}
