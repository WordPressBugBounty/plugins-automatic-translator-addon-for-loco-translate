<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @package LocoAI – Auto Translate for Loco Translate
 */
class Helpers{
    public static function proInstalled(){
        return defined('ATLT_PRO_FILE');
    }
    // return user type
    public static function userType(){
        $option_value = get_option('atlt-type', 'free');
        $sanitized_type = sanitize_key($option_value);
        if ($sanitized_type === 'pro') {
            return 'pro';
        }
        return 'free';
    }
}
