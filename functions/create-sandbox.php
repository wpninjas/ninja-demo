<?php

function dwp_create_sandbox_actions( $blog_id ) {
    // This set's the option to discourage search engines from indexing sandboxes within a demo.
    update_blog_option( $blog_id, 'blog_public', 0 );
    wp_clear_auth_cookie();
    wp_set_auth_cookie( 5, true );
    wp_set_current_user( 5 );
}
add_action( 'dwp_create_sandbox', 'dwp_create_sandbox_actions' );
