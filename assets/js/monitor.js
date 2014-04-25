jQuery(document).ready(function($) {
	// Listen for the heartbeat send.
	$(document).on( 'heartbeat-send', function( e, data ) {
        data['dwp_active'] = 1;        
    });
});