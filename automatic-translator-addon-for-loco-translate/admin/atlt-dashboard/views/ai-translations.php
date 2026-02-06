<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-ai-translations">
    <div class="atlt-dashboard-ai-translations-container">
    <div class="header">
        <h1><?php
         // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        esc_html_e('AI Translations', 'loco-auto-translate'); ?></h1>
        <div class="atlt-dashboard-status">
            <span><?php
             // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
             esc_html_e('Inactive', 'loco-auto-translate'); ?></span>
            <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=ai_translations'); ?>" class='atlt-dashboard-btn' target="_blank" rel="noopener noreferrer">
                <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php
                 // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                esc_attr_e('Upgrade Now', 'loco-auto-translate'); ?>">
                <?php 
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                esc_html_e('Upgrade Now', 'loco-auto-translate'); ?>
            </a>
        </div>
    </div>
    <p class="description">
        <?php // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        esc_html_e('Experience the power of AI for faster, more accurate translations. Choose from multiple AI providers to translate your content efficiently.', 'loco-auto-translate'); ?>
    </p>
    <div class="atlt-dashboard-translations">
        <?php
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        $atlt_ai_translations = [
            [
                'logo' => 'chrome-built-in-ai-logo.png',
                'alt' => 'Chrome Built-in AI',
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'title' => __('Chrome Built-in AI', 'loco-auto-translate'),
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'description' => __('Utilize Chrome\'s built-in AI for seamless translation experience.', 'loco-auto-translate'),
                'icon' => 'chrome-ai-translate.png',
                'url' => 'https://locoaddon.com/docs/how-to-use-chrome-ai-auto-translations/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chrome_ai_translations'
            ],
            [
                'logo' => 'chatgpt-logo.png',
                'alt' => 'ChatGPT AI',
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'title' => __('ChatGPT Translations', 'loco-auto-translate'),
               // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'description' => __('Use OpenAI\'s ChatGPT for fast, natural, accurate, and fluent translations.', 'loco-auto-translate'),
                'icon' => 'chatgpt-translate.png',
                'url' => 'https://locoaddon.com/docs/chatgpt-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chatgpt_ai_translations'
            ],
            [
                'logo' => 'geminiai-logo.png',
                'alt' => 'Gemini',
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'title' => __('Gemini AI Translations', 'loco-auto-translate'),
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'description' => __('Leverage Gemini AI for seamless and context-aware translations.', 'loco-auto-translate'),
                'icon' => 'gemini-translate.png',
                'url' => 'https://locoaddon.com/docs/gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=gemini_ai_translations'
            ],
            [
                'logo' => 'openai-logo.png',
                'alt' => 'OpenAI',
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'title' => __('OpenAI Translations', 'loco-auto-translate'),
                // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
                'description' => __('Leverage OpenAI for seamless and context-aware translations.', 'loco-auto-translate'),
                'icon' => 'open-ai-translate.png',
                'url' => 'https://locoaddon.com/docs/gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=openai_ai_translations'
            ]
        ];

        foreach ($atlt_ai_translations as $atlt_translation) {
            $atlt_logo_filename = isset($atlt_translation['logo']) ? sanitize_file_name($atlt_translation['logo']) : '';
            $atlt_icon_filename = isset($atlt_translation['icon']) ? sanitize_file_name($atlt_translation['icon']) : '';
            $atlt_alt_text = isset($atlt_translation['alt']) ? $atlt_translation['alt'] : '';
            $atlt_title_text = isset($atlt_translation['title']) ? $atlt_translation['title'] : '';
            $atlt_description_text = isset($atlt_translation['description']) ? $atlt_translation['description'] : '';
            $atlt_link_url = isset($atlt_translation['url']) ? $atlt_translation['url'] : '';
            ?>
            <div class="atlt-dashboard-translation-card">
                <div class="logo">
                    <img src="<?php echo esc_url(ATLT_URL . 'assets/images/' . $atlt_logo_filename); ?>" 
                         alt="<?php echo esc_attr($atlt_alt_text); ?>">
                </div>
                <h3><?php echo esc_html($atlt_title_text); ?></h3>
                <p><?php echo esc_html($atlt_description_text); ?></p>
                <div class="play-btn-container">
                    <a href="<?php echo esc_url($atlt_link_url); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/' . $atlt_icon_filename); ?>" alt="<?php echo esc_attr($atlt_alt_text); ?>">
                    </a>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    </div>
</div>