<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-license">
    <div class="atlt-dashboard-license-container">
    <div class="header">
        <h1><?php 
        esc_html_e('License Key', 'automatic-translator-addon-for-loco-translate'); ?></h1>
    </div>
    <p><?php
    esc_html_e('Your license key provides access to pro version updates and support.', 'automatic-translator-addon-for-loco-translate'); ?></p>
    
    <p>
        <?php 
        echo wp_kses( __( 'You\'re using <strong>LocoAI – Auto Translate for Loco Translate (free)</strong> - no license needed. Enjoy! 😊', 'automatic-translator-addon-for-loco-translate' ), array( 'strong' => array() ) ); ?>
    </p>

    <div class="atlt-dashboard-upgrade-box">
        <p>
            <?php 
            esc_html_e('To unlock more features, consider', 'automatic-translator-addon-for-loco-translate'); ?>
            <a href="<?php
            echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=license'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('upgrading to Pro', 'automatic-translator-addon-for-loco-translate'); ?></a>.
        </p>
        <em><?php 
        esc_html_e('As a valued user, you automatically receive an exclusive discount on the Annual License and an even greater discount on the POPULAR Lifetime License at checkout!', 'automatic-translator-addon-for-loco-translate'); ?></em>
    </div>
    </div>
</div>
