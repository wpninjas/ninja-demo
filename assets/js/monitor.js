jQuery(document).ready(function($) {
	// Create our namespace.
	var dwp_monitor = dwp_monitor || {};

	// Store our last interaction.
	dwp_monitor.last_activity = $.now();

	// Create our recording function.
	dwp_monitor.activity = function() {
		dwp_monitor.last_activity = $.now();
	}

	// Listen for a mouse click from our user and record the current time.
	$( document ).on( 'click', dwp_monitor.activity );

	// Listen for a keypress from our user and record the time.
	$( document ).on( 'keydown',  dwp_monitor.activity );

	// Listen for the heartbeat send.
	$(document).on( 'heartbeat-send', function( e, data ) {
        console.log( 'send' );
        data['dwp_active'] = 1;        
    });
});