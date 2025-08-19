<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if (!class_exists('ALTLReviewNotice')) {
    class ALTLReviewNotice {
    /**
     * The Constructor
     */
    public function __construct() {
        // register actions
        
        if(is_admin()){
            add_action( 'admin_notices',array($this,'atlt_admin_notice_for_reviews'));
            add_action( 'wp_ajax_atlt_dismiss_notice',array($this,'atlt_dismiss_review_notice' ) );
        }
    }

    
    // ajax callback for review notice
    public function atlt_dismiss_review_notice(){
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
        if ( 'POST' !== $request_method ) {
            wp_send_json_error( 'Method Not Allowed', 405 );
        }
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        check_ajax_referer( 'atlt_dismiss_notice', 'security' );
        $rs=update_option( 'atlt-already-rated','yes' );
        wp_send_json_success();
    }
   // admin notice  
    public function atlt_admin_notice_for_reviews(){
        if( !current_user_can( 'update_plugins' ) ){
            return;
         }
      
         // get installation dates and rated settings (sanitized)
         $installation_date_option = get_option( 'atlt-installDate' );
         $installation_date = is_string( $installation_date_option ) ? sanitize_text_field( $installation_date_option ) : '';

         $already_rated_option = get_option( 'atlt-already-rated' );
         $alreadyRated = is_string( $already_rated_option ) ? strtolower( sanitize_text_field( $already_rated_option ) ) : 'no';
         // validate installation date to avoid errors with invalid option values
         if ( empty( $installation_date ) || false === strtotime( $installation_date ) ) {
             return;
         }
         // check user already rated 
        if( 'yes' === $alreadyRated ) {
             return;
           }
            
            // grab plugin installation date and compare it with current date
            $display_date = gmdate( 'Y-m-d h:i:s' );
            $install_date= new \DateTime( $installation_date );
            $current_date = new \DateTime( $display_date );
            $difference = $install_date->diff($current_date);
            $diff_days= $difference->days;
          
            // check if installation days is greator then week
			if (isset($diff_days) && $diff_days>=3) {
                echo $this->atlt_create_notice_content();
            }
       }  

       // generated review notice HTML
       function atlt_create_notice_content(){
        
        $ajax_url=admin_url( 'admin-ajax.php' );
        $ajax_callback='atlt_dismiss_notice';
        $wrap_cls="notice notice-info is-dismissible";
        $img_path=ATLT_URL.'assets/images/atlt-logo.png';
     ///   $plugin_info = get_plugin_data( ATLT_PATH , true, true );
        $p_name= 'LocoAI – Auto Translate for Loco Translate';
        $like_it_text='Rate Now! ★★★★★';
        $already_rated_text=esc_html__( 'I already rated it', 'atlt2' );
        $not_like_it_text=esc_html__( 'Not Interested', 'atlt2' );
        $p_link=esc_url('https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post');
        //$pro_url=esc_url('https://eventscalendartemplates.com/');
       
        $message = sprintf(
            'Thanks for using <b>%s</b> - WordPress plugin. We hope it has saved your valuable time and efforts! <br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href=\'https://coolplugins.net\' target=\'_blank\' rel=\'noopener noreferrer\'><strong>Cool Plugins</strong></a>!<br/>',
            esc_html( $p_name )
        );
        $message = wp_kses_post( $message );
      
        $html='<div data-ajax-url="%8$s"  data-ajax-callback="%9$s" class="atlt-feedback-notice-wrapper %1$s">
        <div class="logo_container"><a href="%5$s" target="_blank" rel="noopener noreferrer"><img src="%2$s" alt="%3$s" style="max-width:80px;"></a></div>
        <div class="message_container">%4$s
        <div class="callto_action">
        <ul>
            <li class="love_it"><a href="%5$s" class="like_it_btn button button-primary" target="_blank" rel="noopener noreferrer" title="%6$s">%6$s</a></li>
            <li class="already_rated"><a href="javascript:void(0);" class="already_rated_btn button atlt_dismiss_notice" title="%7$s">%7$s</a></li>  
            <li class="already_rated"><a href="javascript:void(0);" class="already_rated_btn button atlt_dismiss_notice" title="%10$s">%10$s</a></li>           
        </ul>
        <div class="clrfix"></div>
        </div>
        </div>
        </div>';

 $output= sprintf($html,
        esc_attr( $wrap_cls ),
        esc_url( $img_path ),
        esc_attr( $p_name ),
        $message,
        esc_url( $p_link ),
        esc_attr( $like_it_text ),
        esc_attr( $already_rated_text ),
        esc_url( $ajax_url ), // 8
        esc_attr( $ajax_callback ), // 9
        esc_attr( $not_like_it_text ) // 10
        );
    
        // Enqueue jQuery as dependency and add secure inline script
        wp_enqueue_script('jquery');
        
        // Create secure JavaScript with properly escaped nonce
        $script_data = array(
            'ajax_url' => $ajax_url,
            'nonce' => wp_create_nonce( 'atlt_dismiss_notice' )
        );
        
        wp_add_inline_script('jquery', 
            'window.atltReviewNotice = ' . wp_json_encode($script_data) . ';
            jQuery(document).ready(function ($) {
                $(".atlt_dismiss_notice").on("click", function (event) {
                    var $this = $(this);
                    var wrapper = $this.parents(".atlt-feedback-notice-wrapper");
                    var ajaxCallback = wrapper.data("ajax-callback");
                    
                    $.post(window.atltReviewNotice.ajax_url, { 
                        "action": ajaxCallback, 
                        "security": window.atltReviewNotice.nonce 
                    }, function( data ) {
                        wrapper.slideUp("fast");
                    }, "json");
                });
            });'
        );
    $styles='<style>
    .atlt-feedback-notice-wrapper.notice.notice-info.is-dismissible {
        padding: 5px;
        margin: 10px 20px 10px 0;
        border-left-color: #4aba4a;
    }
    
    .atlt-feedback-notice-wrapper .logo_container {
        width: 80px;
        display: inline-block;
        margin-right: 10px;
        vertical-align: top;
    }
    
    .atlt-feedback-notice-wrapper .logo_container img {
        width: 100%;
        height: auto;
    }
    
    .atlt-feedback-notice-wrapper .message_container {
        width: calc(100% - 120px);
        display: inline-block;
        margin: 0;
        vertical-align: top;
    }
    .atlt-feedback-notice-wrapper ul li {
        float: left;
        margin: 0px 5px;
    }
    
    .atlt-feedback-notice-wrapper ul li.already_rated a:before {
        color: #cc0000;
        content: "\f153";
        font: normal 16px/20px dashicons;
        display: inline-block;
        vertical-align: middle;
        margin-right: 4px;
        height: 22px;
    }
    .atlt-feedback-notice-wrapper ul li.already_rated a[title="Not Interested"] {
        position: absolute;
        right: 5px;
        top: 5px;
        z-index: 9;
    }
    .clrfix {
        clear: both;
    }
    </style>';
  return  wp_kses_post($output) . $styles;
}

    } //class end

} 



