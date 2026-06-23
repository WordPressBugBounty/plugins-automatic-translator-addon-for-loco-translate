<?php
    if (! defined('ABSPATH')) {
    exit;
    }
?>

        <!-- Welcome Section -->
        <div class="atlt-dashboard-welcome">
            <div class="atlt-dashboard-welcome-text">
                <h2 class="atlt-dashboard-welcome-headline"><?php
                    echo esc_html__('Automate the Translation Process', 'automatic-translator-addon-for-loco-translate'); ?></h2>
                <div class="atlt-dashboard-welcome-subhead-container"><strong class="atlt-dashboard-welcome-subhead"><?php
                    echo esc_html__('Welcome to LocoAI — AI translation for Loco Translate', 'automatic-translator-addon-for-loco-translate'); ?></strong></div>
                <p class="atlt-dashboard-welcome-lead"><?php
                    echo esc_html__('Open Loco Translate from the links below, pick a plugin or theme, and translate thousands of strings in one workflow — at no extra cost.', 'automatic-translator-addon-for-loco-translate'); ?></p>
                
                <div class="atlt-dashboard-how-it-works-title-container"><strong class="atlt-dashboard-how-it-works-title"><?php
                    echo esc_html__('How it works:', 'automatic-translator-addon-for-loco-translate'); ?></strong></div>
                <ul class="atlt-dashboard-steps">
                    <li><strong><?php
                        echo esc_html__('Step 1:', 'automatic-translator-addon-for-loco-translate'); ?></strong> <?php
                        echo esc_html__('Select the plugin or theme you want to translate.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                    <li><strong><?php
                        echo esc_html__('Step 2:', 'automatic-translator-addon-for-loco-translate'); ?></strong> <?php
                        echo esc_html__('Choose the language for translation.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                    <li><strong><?php
                        echo esc_html__('Step 3:', 'automatic-translator-addon-for-loco-translate'); ?></strong> <?php
                        echo esc_html__('Click the Auto Translate button and choose your preferred translation provider.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                    <li><strong><?php
                        echo esc_html__('Step 4:', 'automatic-translator-addon-for-loco-translate'); ?></strong> <?php
                        echo esc_html__('Review the translated content and click Save to apply the changes.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                </ul>
                <div class="atlt-dashboard-btns-row">
                    <a href="<?php
                        echo esc_url(admin_url('admin.php?page=loco-plugin')); ?>" class="atlt-dashboard-btn primary"><?php
                        echo esc_html__('Translate Plugins', 'automatic-translator-addon-for-loco-translate'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=loco-theme')); ?>" class="atlt-dashboard-btn atlt-dashboard-btn-secondary"><?php
                        echo esc_html__('Translate Themes', 'automatic-translator-addon-for-loco-translate'); ?></a>
                </div>
                <a class="atlt-dashboard-docs" href="<?php
                echo esc_url('https://locoaddon.com/docs/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard'); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/document.svg'); ?>" alt=""> <span><?php echo esc_html__('Read Plugin Docs', 'automatic-translator-addon-for-loco-translate'); ?></span></a>
            </div>
            <div class="atlt-dashboard-welcome-video" role="presentation">
                <div class="atlt-dashboard-welcome-video-panel">
                    <a href="<?php echo esc_url('https://locoaddon.com/docs/translate-plugin-theme-via-yandex-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_video'); ?>" target="_blank" rel="noopener noreferrer" class="atlt-dashboard-video-link">
                        <img decoding="async" src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/video.svg'); ?>" class="play-icon" alt="">
                        <picture>
                            <source srcset="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/loco-addon-video.avifs'); ?>" type="image/avif">
                            <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/loco-addon-video.jpg'); ?>" class="loco-video" alt="<?php
                                echo esc_attr__('LocoAI — watch the tutorial', 'automatic-translator-addon-for-loco-translate'); ?>">
                        </picture>
                    </a>
                </div>
            </div>
        </div>

        <!-- AI Translations -->
        <div class="atlt-dashboard-translation-providers atlt-dashboard-ai-translations">
            <h3><?php
                esc_html_e('AI Translations', 'automatic-translator-addon-for-loco-translate'); ?></h3>
            <div class="atlt-dashboard-providers-grid">

            <?php

                $atlt_provider_settings = get_option('atlt_dashboard_provider_toggles', []);
                $atlt_provider_settings = is_array($atlt_provider_settings) ? $atlt_provider_settings : [];

                $atlt_ai_translations = [
                    [
                        'key'         => 'yandex',
                        'logo'        => 'yandex-translate-logo.png',
                        'alt'         => 'Yandex',
                        'title'       => __('Yandex Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Instant translations without API key.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'yandex-translate.png',
                        'is_pro'      => false,
                        'url'         => 'https://locoaddon.com/docs/translate-plugin-theme-via-yandex-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=yandex_ai_translations',
                    ],
                    [
                        'key'         => 'openai',
                        'logo'        => 'openai-logo.png',
                        'alt'         => 'OpenAI',
                        'title'       => __('OpenAI Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Smart and fast AI translations.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'open-ai-translate.png',
                        'is_pro'      => false,
                        'url'         => 'https://locoaddon.com/docs/open-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=openai_ai_translations',
                    ],
                    [
                        'key'         => 'chrome',
                        'logo'        => 'chrome-built-in-ai-logo.png',
                        'alt'         => 'Chrome Built-in AI',
                        'title'       => __('Chrome Built-in AI', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Real-time translations using Chrome AI.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'chrome-ai-translate.png',
                        'is_pro'      => true,
                        'url'         => 'https://locoaddon.com/docs/how-to-use-chrome-ai-auto-translations/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chrome_ai_translations',
                    ],
                    [
                        'key'         => 'chatgpt',
                        'logo'        => 'chatgpt-logo.png',
                        'alt'         => 'ChatGPT AI',
                        'title'       => __('ChatGPT Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Fast and accurate ChatGPT translations.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'chatgpt-translate.png',
                        'is_pro'      => true,
                        'url'         => 'https://locoaddon.com/docs/chatgpt-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chatgpt_ai_translations',
                    ],
                    [
                        'key'         => 'gemini',
                        'logo'        => 'geminiai-logo.png',
                        'alt'         => 'Gemini',
                        'title'       => __('Gemini AI Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Quick and accurate Gemini translations.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'gemini-translate.png',
                        'is_pro'      => true,
                        'url'         => 'https://locoaddon.com/docs/gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=gemini_ai_translations',
                    ],
                    [
                        'key'         => 'google',
                        'logo'        => 'google-translate-logo.png',
                        'alt'         => 'Google',
                        'title'       => __('Google Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('Instant translations using Google tools.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'google-translate.png',
                        'is_pro'      => true,
                        'url'         => 'https://locoaddon.com/docs/auto-translations-via-google-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=google_ai_translations',
                    ],
                    [
                        'key'         => 'deepl',
                        'logo'        => 'deepl-translate-logo.png',
                        'alt'         => 'DeepL',
                        'title'       => __('DeepL Translations', 'automatic-translator-addon-for-loco-translate'),
                        'description' => __('High-quality and accurate DeepL translations.', 'automatic-translator-addon-for-loco-translate'),
                        'icon'        => 'deepl-translate.png',
                        'is_pro'      => true,
                        'url'         => 'https://locoaddon.com/docs/translate-via-deepl-translator/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=deepl_ai_translations',
                    ]
                ];

                foreach ($atlt_ai_translations as $atlt_translation) {
                    $atlt_logo_filename    = isset($atlt_translation['logo']) ? sanitize_file_name($atlt_translation['logo']) : '';
                    $atlt_icon_filename    = isset($atlt_translation['icon']) ? sanitize_file_name($atlt_translation['icon']) : '';
                    $atlt_alt_text         = isset($atlt_translation['alt']) ? $atlt_translation['alt'] : '';
                    $atlt_title_text       = isset($atlt_translation['title']) ? $atlt_translation['title'] : '';
                    $atlt_description_text = isset($atlt_translation['description']) ? $atlt_translation['description'] : '';
                    $atlt_link_url         = isset($atlt_translation['url']) ? $atlt_translation['url'] : '';
                    $atlt_is_pro           = ! empty($atlt_translation['is_pro']);
                    $atlt_provider_key     = isset($atlt_translation['key']) ? sanitize_key($atlt_translation['key']) : '';
                    $atlt_is_enabled       = array_key_exists($atlt_provider_key, $atlt_provider_settings)
                        ? (bool) $atlt_provider_settings[$atlt_provider_key]
                        : true;

                    $atlt_pro_badge_url = add_query_arg(
                        [
                            'utm_source'   => 'atlt_plugin',
                            'utm_medium'   => 'inside',
                            'utm_campaign' => 'get_pro',
                            'utm_content'  => ($atlt_provider_key ? $atlt_provider_key : 'unknown') . '_pro_badge',
                        ],
                        'https://locoaddon.com/pricing/'
                    );
                ?>
                <div class="atlt-dashboard-translation-card">
                    <div class="atlt-dashboard-translation-card-header">
                        <div class="logo">
                            <img src="<?php echo esc_url(ATLT_URL . 'assets/images/' . $atlt_logo_filename); ?>"
                                alt="<?php echo esc_attr($atlt_alt_text); ?>">
                        </div>
                            <div class="atlt-switch-container">
                                <?php if ($atlt_is_pro) { ?>
                                    <a
                                        class="atlt-pro-toggle-link"
                                        href="<?php echo esc_url($atlt_pro_badge_url); ?>"
                                        data-atlt-tooltip="<?php echo esc_attr__('Pro', 'automatic-translator-addon-for-loco-translate'); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                <?php } ?>
                                <label
                                    class="atlt-switch-label <?php echo esc_attr($atlt_is_pro ? 'is-disabled' : ''); ?>"
                                >
                                    <input
                                        type="checkbox"
                                        class="atlt-input-toggle atlt-provider-toggle"
                                        data-provider="<?php echo esc_attr($atlt_provider_key); ?>"
                                        <?php checked($atlt_is_enabled); ?>
                                        <?php echo $atlt_is_pro ? 'disabled="disabled"' : ''; ?>
                                    >
                                    <span class="atlt-switch-slider"></span>
                                </label>
                                <?php if ($atlt_is_pro) { ?>
                                    </a>
                                <?php } ?>
                            </div>
                    </div>
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

