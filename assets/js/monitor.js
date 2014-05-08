jQuery(document).ready(function($) {
	// Listen for the heartbeat send.
	$(document).on( 'heartbeat-send', function( e, data ) {
        data['nd_active'] = 1;        
    });
});