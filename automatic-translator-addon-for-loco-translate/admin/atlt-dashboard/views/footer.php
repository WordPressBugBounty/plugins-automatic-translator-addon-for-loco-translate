<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-info">
    <div class="atlt-dashboard-info-links">
        <p>
            <?php 
            esc_html_e('Made with ❤️ by', 'automatic-translator-addon-for-loco-translate'); ?>
            <span class="logo">
                <a href="<?php echo esc_url('https://coolplugins.net/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=dashboard_logo'); ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/cool-plugins-logo-black.svg'); ?>" alt="<?php 
                    esc_attr_e('Cool Plugins Logo', 'automatic-translator-addon-for-loco-translate'); ?>">
                </a>
            </span>
        </p>
    </div>
</div>
