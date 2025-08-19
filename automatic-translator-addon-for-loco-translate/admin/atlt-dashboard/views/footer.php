<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-info">
    <div class="atlt-dashboard-info-links">
        <p>
            <?php esc_html_e('Made with ❤️ by', $text_domain); ?>
            <span class="logo">
                <a href="<?php echo esc_url('https://coolplugins.net/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=dashboard_logo'); ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/cool-plugins-logo-black.svg'); ?>" alt="<?php esc_attr_e('Cool Plugins Logo', $text_domain); ?>">
                </a>
            </span>
        </p>
        <a href="<?php echo esc_url('https://locoaddon.com/support/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_footer'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Support', $text_domain); ?></a> |
        <a href="<?php echo esc_url('https://locoaddon.com/docs/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_footer'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Docs', $text_domain); ?></a>
        <div class="atlt-dashboard-social-icons">
            <?php
            $social_links = [
                ['https://www.facebook.com/coolplugins/', 'facebook.svg', __('Facebook', $text_domain)],
                ['https://linkedin.com/company/coolplugins', 'linkedin.svg', __('Linkedin', $text_domain)],
                ['https://x.com/cool_plugins', 'twitter.svg', __('Twitter', $text_domain)],
                ['https://www.youtube.com/@cool_plugins', 'youtube.svg', __('YouTube Channel', $text_domain)]
            ];
            foreach ($social_links as $link) {
                echo '<a href="' . esc_url($link[0]) . '" target="_blank" rel="noopener noreferrer">
                        <img src="' . esc_url(ATLT_URL . 'admin/atlt-dashboard/images/' . $link[1]) . '" alt="' . esc_attr($link[2]) . '">
                      </a>';
            }
            ?>
        </div>
    </div>
</div>
