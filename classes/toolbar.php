<?php

function ninja_demo_get_themes() {
    $args = array( 'errors' => false, 'allowed' => true );
    $themes = wp_get_themes( $args );

    if ( $themes ) {
        echo '<form name="demo-theme-switcher" method="put">';
            echo '<select name="demo_theme" onchange="this.form.submit()">';
                echo '<option value="">Select a Theme</option>';
                foreach ( $themes AS $theme ) {
                    echo '<option value="' . $theme->stylesheet . '">' . $theme . '</option>';
                }
            echo '</select>';
        echo '</form>';
    }
    // if( current_user_can( 'administrator' ) ) {
    //     echo '<pre>';
    //     print_r( $themes );
    //     echo '</pre>';
    // }
}


function ninja_demo_switch_theme() {
    if ( $_REQUEST['demo_theme'] ) {
        $theme = $_REQUEST['demo_theme'];
        switch_theme( $theme );
    }
}
add_action( 'setup_theme', 'ninja_demo_switch_theme' );

function ninja_demo_toolbar() {
    echo '<div class="nd-toolbar">';
        ninja_demo_get_themes();
        echo '<div class="nd-toolbar-view">';
            echo '<a class="nd-view-mobile" href="">Mobile</a>';
            echo '<a class="nd-view-tablet" href="">Tablet</a>';
            echo '<a class="nd-view-desktop" href="">Desktop</a>';
        echo '</div>';

    echo '</div>';
}
add_action( 'wp_footer', 'ninja_demo_toolbar' );


function ninja_demo_filter_head() {
    remove_action( 'wp_head', '_admin_bar_bump_cb' );
}
add_action( 'get_header', 'ninja_demo_filter_head' );

function ninja_demo_viewport() {
    echo '<meta name="viewport" content="width=400" />';
}
//add_action( 'wp_head', 'ninja_demo_viewport' );
