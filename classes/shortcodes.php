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
		add_shortcode( 'dwp_countdown', array( $this, 'countdown' ) );
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
	 * Display a countdown for the user, informing them how much time their sandbox has left.
	 * 
	 * @access public
	 * @since 1.0
	 * @return string $output
	 */
	public function countdown() {
		// Get the time that our sandbox expires.
		$end_time = Demo_WP()->sandbox->get_end_time();
		$end_time = date( 'Y-m-d g:i:a', $end_time );

		ob_start();
		?>
		<div class="countdown styled"></div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
		        $('.countdown.styled').countdown({
		          date: "<?php echo $end_time; ?>",
		          render: function(data) {
		            $(this.el).html("<div>" + this.leadingZeros(data.hours, 2) + " <span>hrs</span></div><div>" + this.leadingZeros(data.min, 2) + " <span>min</span></div><div>" + this.leadingZeros(data.sec, 2) + " <span>sec</span></div>");
		          }
		        });
			});
		</script>

		<?php
		$output = ob_get_clean();
		return $output;
	}
}
