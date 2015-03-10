<?php
/**
 * Ninja_Demo_Shortcodes
 *
 * This class handles outputting of our shortcodes:
 * 1) The "Try Demo Now" button
 * 2) Our "Time left" counter
 * 3) Our "Reset Demo" button
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

class Ninja_Demo_Shortcodes {
	
	/*
	 * Check for errors during sandbox creation
	 */
	private $error = false; // Check for errors in sandbox creation

	/**
	 * Get things started
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'try_demo', array( $this, 'try_button' ) );
		add_shortcode( 'demo_login', array( $this, 'login_button' ) );
		add_action( 'init', array( $this, 'create_listen' ) );
		add_action( 'init', array( $this, 'logout_listen' ) );
		add_action( 'init', array( $this, 'login_listen' ) );

		add_shortcode( 'is_sandbox', array( $this, 'is_sandbox' ) );
		add_shortcode( 'is_not_sandbox', array( $this, 'is_not_sandbox' ) );

		add_shortcode( 'is_sandbox_expired', array( $this, 'is_sandbox_expired' ) );
	}

	/**
	 * Shortcode function that outputs a button for users to try the demo/create a new sandbox.
	 *
	 * @access public
	 * @since 1.0
	 * @return string $output
	 */
	public function try_button( $atts ) {

		if ( isset ( $atts['source_id'] ) ) {
			$source_id = $atts['source_id'];
		} else {
			$source_id = get_current_blog_id();
		}

		$spam_q = $this->get_spam_question();
		$spam_a = $this->get_spam_answer( $spam_q );
		$tid = $this->set_transient( $spam_a );
		// Check to see if our IP address is locked out.
		$ip = $_SERVER['REMOTE_ADDR'];

		Ninja_Demo()->ip->free_ip( $ip );

		// Get the number of tries we have left before we are locked out.
		if ( isset ( $_SESSION['ninja_demo_failed'] ) ) {
			$tries = 4 - $_SESSION['ninja_demo_failed'];
		}

		if ( ! Ninja_Demo()->is_sandbox() )
			$ip_lockout = Ninja_Demo()->ip->check_ip_lockout( $ip );

		$output = '';

		if ( ! Ninja_Demo()->is_sandbox() ) {

			ob_start();

			?>
			<a id="ninja-demo"></a>
			<div class="nd-start-demo">
				<?php
				// Check to
				if ( ! $ip_lockout ) {

					?>
					<form action="#ninja-demo" method="post" enctype="multipart/form-data" class="nd-start-demo-form">
						<?php wp_nonce_field( 'ninja_demo_create_sandbox','ninja_demo_sandbox' ); ?>
						<input name="nd_create_sandbox" type="hidden" value="1">
						<input name="tid" type="hidden" value="<?php echo $tid; ?>">
						<input name="source_id" type="hidden" value="<?php echo $source_id; ?>">
						<?php
							if ( isset ( $_GET['errormsg'] ) ) {
						?>
							<div>
								<?php echo $_GET['errormsg'];  ?>
							</div>
						<?php
							}
						?>
						<?php
						do_action( 'nd_before_anti_spam', $source_id );
						?>
						<div>
							<label class="nd-answer-field"><?php echo _e( 'What does ', 'ninja-demo' ) . $spam_q; ?><input type="text" name="spam_a"></label>
							<?php
							if ( 'expired' == $this->error ) {
							?>
								<div>
									<?php _e( 'Your demo has expired. Please try again', 'ninja-demo' ); ?>
								</div>
							<?php
							} else if ( 'failed' == $this->error ) {
							?>
								<div>
									<?php _e( 'Incorrect answer. Please try again.', 'ninja-demo' ); ?>
								</div>
								<div>
									<?php
									if ( $tries == 1 ) {
										printf( __( 'You have %d attempt left before your IP address is locked out for 20 minutes.', 'ninja-demo' ), $tries );
									} else {
										printf( __( 'You have %d attempts left before your IP address is locked out for 20 minutes.', 'ninja-demo' ), $tries );
									}
									?>
								</div>
							<?php
							}
						?>
						</div>
						<?php
						do_action( 'nd_after_anti_spam', $source_id );
						?>
						<div class="ninja-demo-hidden">
							<label>
								<?php
								_e( 'If you are a human and are seeing this field, please leave it blank.', 'ninja-demo' );
								?>
							</label>
							<input name="spamcheck" type="text" value="">
						</div>
						<p class="submit no-border">
							<input name="submit" value="<?php _e( 'Try the demo!', 'ninja-demo' ) ?>" type="submit" /><br /><br />
						</p>
					</form>

					<?php
				} else {
					$expires = round( ( $ip_lockout - current_time( 'timestamp' ) ) / 60 );

					if ( $expires < 1 ) {
						$expires = 1;
					}
					?>
					<h4><?php
					if ( $expires == 1 ) {
						printf( __( 'You are unable to create a sandbox for %d minute.', 'ninja-demo' ), $expires );
					} else {
						printf( __( 'You are unable to create a sandbox for %d minutes.', 'ninja-demo' ), $expires );
					}


					if ( isset ( $_POST['tid'] ) )
						delete_transient( $_POST['tid'] );
					?></h4>
					<?php
				}
				?>
			</div>
			<?php

			$output = ob_get_clean();
		} else { // If we are in a sandbox, show either a logout or login button.
			if ( is_user_logged_in() ) {
				// Show logout button.
				$logout_url = add_query_arg( array( 'nd_logout' => 1 ) );
				$output = '<a href="' . $logout_url	. '">' . __( 'Logout', 'ninja-demo' ) . '</a>';
			} else {
				// Show login button.
				$login_url = add_query_arg( array( 'nd_login' => 1 ) );
				$output = '<a href="' . $login_url . '">' . __( 'Login', 'ninja-demo' ) . '</a>';
			}

		} // End is_sandbox check

		return $output;
	}

	/**
	 * Output our login/logut button
	 *
	 * @access public
	 * @since 1.0.9
	 * @return void
	 */
	public function login_button( $atts ) {
		if ( is_user_logged_in() ) {
			// Show logout button.
			$logout_url = add_query_arg( array( 'nd_logout' => 1 ) );
			$output = '<a href="' . $logout_url	. '">' . __( 'Logout', 'ninja-demo' ) . '</a>';
		} else {
			// Show login button.
			$login_url = add_query_arg( array( 'nd_login' => 1 ) );
			$output = '<a href="' . $login_url . '">' . __( 'Login', 'ninja-demo' ) . '</a>';
		}

		return $output;
	}

	/**
	 * Listen for our create sandbox button.
	 * If everything passes, call the Ninja_Demo()->sandbox->create() function.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function create_listen() {

		// Bail if the "prevent_clones" has been set to 1
		if ( Ninja_Demo()->settings['prevent_clones'] == 1 )
			return false;

		// Bail if this user's IP is on our blocked list
		if ( Ninja_Demo()->ip->check_ip_lockout( $_SERVER['REMOTE_ADDR'] ) )
			return false;

		// Bail if we haven't clicked the tryout button
		if ( ! isset ( $_POST['nd_create_sandbox'] ) || $_POST['nd_create_sandbox'] != 1 )
			return false;

		// Bail if we don't have a nonce
		if ( ! isset ( $_POST['ninja_demo_sandbox'] ) )
			return false;

		// Bail if our nonce isn't correct
		if ( ! wp_verify_nonce( $_POST['ninja_demo_sandbox'], 'ninja_demo_create_sandbox' ) )
			return false;

		// Bail if our honey-pot field has been filled in
		if ( isset ( $_POST['spamcheck'] ) && $_POST['spamcheck'] !== '' )
			return false;

		// Bail if we haven't sent an answer to the anti-spam question
		if ( ! isset( $_POST['spam_a'] ) || ! isset ( $_POST['tid'] ) )
			return false;
		
		// Bail if we haven't sent an answer to the anti-spam question
		if ( false === get_transient( $_POST['tid'] ) ) {
			$this->error = 'expired';
			return false;
		}

		// Bail if our anti-spam answer isn't correct
		if ( $_POST['spam_a'] != get_transient( $_POST['tid'] ) ) {
			// Add 1 to the number of times this user has failed to login
			if ( ! isset ( $_SESSION['ninja_demo_failed'] ) ) {
				$_SESSION['ninja_demo_failed'] = 1;
			} else {
				$_SESSION['ninja_demo_failed']++;
			}

			if ( $_SESSION['ninja_demo_failed'] >= 4 ) {
				// Add this user to the IP lockout table.
				Ninja_Demo()->ip->lockout_ip( $_SERVER['REMOTE_ADDR'] );
				$_SESSION['ninja_demo_failed'] = 0;
			}
			// Remove our transient answer
			delete_transient( $_POST['tid'] );
			
			$this->error = 'failed';
			return false;
		}
		// Remove our transient answer
		delete_transient( $_POST['tid'] );

		Ninja_Demo()->sandbox->create( $_POST['source_id'] );
	}

	/**
	 * Listen for our logout click
	 *
	 * @access public
	 * @since 1.1.0
	 * @return void
	 */
	public function logout_listen() {
		// Bail if we aren't in a sandbox.
		if ( ! Ninja_Demo()->is_sandbox() )
			return false;
		// Bail if we our nd_logout querystring isn't set.
		if ( ! isset ( $_GET['nd_logout'] ) || $_GET['nd_logout'] != 1 )
			return false;
		// Log our user out.
		wp_logout();
		// Reload the page.
		wp_redirect( remove_query_arg( array( 'nd_logout' ) ) );
		die();
	}

	/**
	 * Listen for our login click
	 *
	 * @access public
	 * @since 1.1.0
	 * @return void
	 */
	public function login_listen() {
		// Bail if we aren't in a sandbox.
		if ( ! Ninja_Demo()->is_sandbox() )
			return false;
		// Bail if we our nd_logout querystring isn't set.
		if ( ! isset ( $_GET['nd_login'] ) || $_GET['nd_login'] != 1 )
			return false;

		// Get our user's credentials
		$user = get_option( 'nd_user' );
		$password = get_option( 'nd_password' );
		// Log our user in.
		wp_signon( array( 'user_login' => $user, 'user_password' => $password ) );
		// Reload this page.
		wp_redirect( remove_query_arg( array( 'nd_login' ) ) );
		die();
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
		$key = Ninja_Demo()->random_string();
		// Make sure that this key isn't already used in a transient
		if ( get_transient( $key ) !== false ) {
			return $this->set_transient( $value );
		} else {
			set_transient( $key, $value, 900 ); //15 minutes
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
		if ( ! Ninja_Demo()->is_sandbox() || ! Ninja_Demo()->sandbox->is_active() )
			return false;

		ob_start();
		?>
		<form action="" method="post" enctype="multipart/form-data" class="nd-reset-demo-form">
			<input type="hidden" name="reset_sandbox" value="1">
			<?php wp_nonce_field( 'ninja_demo_reset_sandbox','ninja_demo_sandbox' ); ?>
			<input type="submit" name="reset_sandbox_submit" value="<?php _e( 'Reset Sandbox Content', 'ninja-demo' ); ?>">
		</form>
		<?php
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * is_sandbox shortcode
	 *
	 * @access public
	 * @since 1.0
	 * @return string $content or bool(false)
	 */
	public function is_sandbox( $atts, $content = null ) {
		if ( Ninja_Demo()->is_sandbox() ) {
			return do_shortcode( $content );
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
		if ( ! Ninja_Demo()->is_sandbox() ) {
			return do_shortcode( $content );
		} else {
			return false;
		}
	}

	/**
	 * is_sandbox_expired shortcode
	 *
	 * @access public
	 * @since 1.0
	 * @return string $content or bool(false)
	 */
	public function is_sandbox_expired( $atts, $content = null ) {
		if ( isset ( $_REQUEST['sandbox_expired'] ) && $_REQUEST['sandbox_expired'] == 1 ) {
			return do_shortcode( $content );
		} else {
			return false;
		}
	}
}
