<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-license">
    <div class="atlt-dashboard-license-container">
    <div class="header">
        <h1><?php esc_html_e('License Key', $text_domain); ?></h1>
        <div class="atlt-dashboard-status">
            <span><?php esc_html_e('Free', $text_domain); ?></span>
            <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=license'); ?>" class='atlt-dashboard-btn' target="_blank" rel="noopener noreferrer">
              <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php esc_attr_e('Upgrade Now', $text_domain); ?>">
                <?php esc_html_e('Upgrade Now', $text_domain); ?>
            </a>
        </div>
    </div>
    <p><?php esc_html_e('Your license key provides access to pro version updates and support.', $text_domain); ?></p>
    
    <p>
        <?php echo wp_kses( __( 'You\'re using <strong>LocoAI – Auto Translate for Loco Translate (free)</strong> - no license needed. Enjoy! 😊', $text_domain ), array( 'strong' => array() ) ); ?>
    </p>

    <div class="atlt-dashboard-upgrade-box">
        <p>
            <?php esc_html_e('To unlock more features, consider', $text_domain); ?>
            <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=license'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('upgrading to Pro', $text_domain); ?></a>.
        </p>
        <em><?php esc_html_e('As a valued user, you automatically receive an exclusive discount on the Annual License and an even greater discount on the POPULAR Lifetime License at checkout!', $text_domain); ?></em>
    </div>
    </div>
</div>
