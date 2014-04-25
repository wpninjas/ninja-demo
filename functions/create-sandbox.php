<?php
add_filter( 'widget_text', 'do_shortcode' );
function dwp_add_shortcode_widget() {
    add_filter( 'widget_text', 'do_shortcode' );
}
//add_action( 'wp', 'dwp_add_shortcode_widget' );