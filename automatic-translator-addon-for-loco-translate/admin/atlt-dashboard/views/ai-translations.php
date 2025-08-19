<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-ai-translations">
    <div class="atlt-dashboard-ai-translations-container">
    <div class="header">
        <h1><?php esc_html_e('AI Translations', $text_domain); ?></h1>
        <div class="atlt-dashboard-status">
            <span><?php esc_html_e('Inactive', $text_domain); ?></span>
            <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=ai_translations'); ?>" class='atlt-dashboard-btn' target="_blank" rel="noopener noreferrer">
                <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php esc_attr_e('Upgrade Now', $text_domain); ?>">
                <?php esc_html_e('Upgrade Now', $text_domain); ?>
            </a>
        </div>
    </div>
    <p class="description">
        <?php esc_html_e('Experience the power of AI for faster, more accurate translations. Choose from multiple AI providers to translate your content efficiently.', $text_domain); ?>
    </p>
    <div class="atlt-dashboard-translations">
        <?php
        $ai_translations = [
            [
                'logo' => 'chrome-built-in-ai-logo.png',
                'alt' => 'Chrome Built-in AI',
                'title' => __('Chrome Built-in AI', $text_domain),
                'description' => __('Utilize Chrome\'s built-in AI for seamless translation experience.', $text_domain),
                'icon' => 'chrome-ai-translate.png',
                'url' => 'https://locoaddon.com/docs/how-to-use-chrome-ai-auto-translations/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chrome_ai_translations'
            ],
            [
                'logo' => 'chatgpt-logo.png',
                'alt' => 'ChatGPT AI',
                'title' => __('ChatGPT Translations', $text_domain),
                'description' => __('Use OpenAI\'s ChatGPT for fast, natural, accurate, and fluent translations.', $text_domain),
                'icon' => 'chatgpt-translate.png',
                'url' => 'https://locoaddon.com/docs/chatgpt-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=chatgpt_ai_translations'
            ],
            [
                'logo' => 'geminiai-logo.png',
                'alt' => 'Gemini',
                'title' => __('Gemini AI Translations', $text_domain),
                'description' => __('Leverage Gemini AI for seamless and context-aware translations.', $text_domain),
                'icon' => 'gemini-translate.png',
                'url' => 'https://locoaddon.com/docs/gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=gemini_ai_translations'
            ],
            [
                'logo' => 'openai-logo.png',
                'alt' => 'OpenAI',
                'title' => __('OpenAI Translations', $text_domain),
                'description' => __('Leverage OpenAI for seamless and context-aware translations.', $text_domain),
                'icon' => 'open-ai-translate.png',
                'url' => 'https://locoaddon.com/docs/gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=gemini_ai_translations'
            ]
        ];

        foreach ($ai_translations as $translation) {
            $logo_filename = isset($translation['logo']) ? sanitize_file_name($translation['logo']) : '';
            $icon_filename = isset($translation['icon']) ? sanitize_file_name($translation['icon']) : '';
            $alt_text = isset($translation['alt']) ? $translation['alt'] : '';
            $title_text = isset($translation['title']) ? $translation['title'] : '';
            $description_text = isset($translation['description']) ? $translation['description'] : '';
            $link_url = isset($translation['url']) ? $translation['url'] : '';
            ?>
            <div class="atlt-dashboard-translation-card">
                <div class="logo">
                    <img src="<?php echo esc_url(ATLT_URL . 'assets/images/' . $logo_filename); ?>" 
                         alt="<?php echo esc_attr($alt_text); ?>">
                </div>
                <h3><?php echo esc_html($title_text); ?></h3>
                <p><?php echo esc_html($description_text); ?></p>
                <div class="play-btn-container">
                    <a href="<?php echo esc_url($link_url); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/' . $icon_filename); ?>" alt="<?php echo esc_attr($alt_text); ?>">
                    </a>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    </div>
</div>