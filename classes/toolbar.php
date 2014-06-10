<?php
/**
 * Ninja_Demo_Toolbar
 *
 * This class handles outputting our front-end theme switcher
 *
 * @package     Ninja Demo
 * @copyright   Copyright (c) 2014, WP Ninjas
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.4
 */

class Ninja_Demo_Toolbar {
    
    /**
     * Get things started
     * 
     * @access public
     * @since 1.0.4
     * @return void
     */
    public function __construct() {
        add_action( 'wp_footer', array( $this, 'output_toolbar' ) );
        add_action( 'get_header', array( $this, 'filter_head' ) );
    }

    /**
     * Return a dropdwon list of our current themes.
     * 
     * @access public
     * @since 1.0.4
     * @return string $html
     */
    public function get_themes() {
        $args = array( 'errors' => false, 'allowed' => true );
        $themes = wp_get_themes( $args );

        if ( $themes ) {
            $current_theme = wp_get_theme()->stylesheet;
            $current_theme_name = wp_get_theme()->Name;
            $html = '<div id="dd" class="wrapper-dropdown-5" tabindex="1">' . $current_theme_name .'<ul class="dropdown">';
                    foreach ( $themes as $theme ) {
                        if ( $theme->stylesheet != $current_theme ) {
                           $html .= '<li><a href="' . add_query_arg( array( 'demo_theme' => $theme->stylesheet ) ) . '">' . $theme . '</a></li>'; 
                        }
                    }
            $html .= '</ul></div>';

            return $html;
        }
        return false;
    }

    /**
     * Output our front-end toolbar
     * 
     * @access public
     * @since 1.0.4
     * @return void
     */
    public function output_toolbar() {
        if ( Ninja_Demo()->settings['show_toolbar'] == 1 ) {
            echo '<div class="nd-toolbar">';
                echo $this->get_themes();
                echo '<div class="nd-toolbar-view">';
                    echo '<a class="nd-change-view nd-view-mobile" rel="nd-view-mobile:open" href="#ninja-demo-modal-mobile"></a>';
                    echo '<a class="nd-change-view nd-view-tablet" rel="nd-view-tablet:open" href="#ninja-demo-modal-tablet"></a>';
                    echo '<a class="nd-view-desktop" href="#"></a>';
                echo '</div>';
            echo '</div>';

            $modal = '<div id="ninja-demo-modal-mobile" class="nd-modal" style="display: none;background-image: url(' . ND_PLUGIN_URL . 'assets/images/mobile.png);background-size: 100%;
display: none;
height: 764px;
margin: 20px auto 0;
margin: 2rem auto 0;
width: 398px;" >';
                $modal .= '<iframe id="iframe1" src="' . add_query_arg( array( ) ) . '" style="background: #fff;
border: none;
height: 515px;
margin: 125px 42px;
overflow: scroll;
width: 320px;"></iframe>';
            $modal .= '</div>';            

            $modal .= '<div id="ninja-demo-modal-tablet" class="nd-modal" style="background-color: #000;
background-size: 100%;
border-left: 4px solid #fff;
border-right: 4px solid #fff;
display: none;
height: 100%;
left: 50%;
margin: 0 0 0 -500px;
width: 850;
padding-bottom: 10px;
position: absolute;" >';
                $modal .= '<iframe id="iframe2" src="' . add_query_arg( array( ) ) . '" style="background: #fff;
border: 2px solid #262626;
height: 100%;
margin: 35px 110px;
overflow: scroll;
padding: 3px;
padding-top: 10px;
width: 768px;"></iframe>';
            $modal .= '</div>';
            echo $modal;

            ?>
            <script type="text/javascript">

            jQuery( document ).ready(function($) {

                $( 'a.nd-change-view' ).on( 'click', function( e ) {
                    e.preventDefault();
                    $(this).modal({
                        closeText: '',
                        clickClose: false,
                        fadeDuration: 250,
                        opacity: .9,
                        zIndex: 999
                    });

                    return false;
                });

                if ( self !== top ) {
                    $( '.nd-toolbar' ).remove();
                    $( '#wpadminbar' ).remove();
                    $( 'html' ).attr( 'style', 'margin-top:0px !important; top:0px !important;' );
                }

                $( 'a.nd-view-desktop' ).on( 'click', function( e ) {
                    $.modal.close();
                });

                function DropDown(el) {
                    this.dd = el;
                    this.initEvents();
                }
                DropDown.prototype = {
                    initEvents : function() {
                        var obj = this;

                        obj.dd.on('click', function(event){
                            $(this).toggleClass('active');
                            event.stopPropagation();
                        }); 
                    }
                }

                $(function() {

                    var dd = new DropDown( $('#dd') );

                    $(document).click(function() {
                        // all dropdowns
                        $('.wrapper-dropdown-5').removeClass('active');
                    });

                });


             });
          
            </script>
            <?php
        }
    }

    /**
     * Remove admin_bar_bump_cb
     * 
     * @access public
     * @since 1.0.4
     * @return void
     */
    public function filter_head() {
        remove_action( 'wp_head', '_admin_bar_bump_cb' );
    }

} // End Class