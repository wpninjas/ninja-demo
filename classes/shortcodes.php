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
	}

	/**
	 * Shortcode function that outputs a button for users to try the demo/create a new sandbox.
	 * 
	 * @since 1.0
	 * @access public
	 * @return void
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
		
		?>
		<a id="demo-wp"></a>
		<div class="wrap">
			<?php
			// Check to 
			if ( ! $ip_lockout ) {
				?>
				<form action="#demo-wp" method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'demo_wp_create_sandbox','demo_wp_sandbox' ); ?>
					<input name="dwp_create_sandbox" type="hidden" value="1">
					<input name="tid" type="hidden" value="<?php echo $tid; ?>">
					<div>
						<label><?php echo $spam_q; ?><input name="spam_a"></label>
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
		$return = ob_get_clean();
		return $return;
	}

	/**
	 * Generate our anti-spam question and store the answer in a transient that will expire in thirty minutes
	 * 
	 * @since 1.0
	 * @access private
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
	 * @since 1.0
	 * @access private
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
	 * @since 1.0
	 * @access private
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
}