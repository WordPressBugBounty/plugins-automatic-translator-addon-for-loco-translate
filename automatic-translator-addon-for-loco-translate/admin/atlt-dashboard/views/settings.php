<div class="atlt-dashboard-settings">
    <div class="atlt-dashboard-settings-container">
    <div class="header">
        <h1><?php _e('Loco Addon Settings', $text_domain); ?></h1>
        <div class="atlt-dashboard-status">
            <span><?php _e('Inactive', $text_domain); ?></span>
            <a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=settings" class='atlt-dashboard-btn' target="_blank">
                <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php esc_attr_e('Upgrade Now', $text_domain); ?>">
                <?php _e('Upgrade Now', $text_domain); ?>
            </a>
        </div>
    </div>
    
    <!-- Add the description here -->
    <p class="description">
        <?php _e('Configure your settings for the Loco Addon to optimize your translation experience. Enter your API keys and manage your preferences for seamless integration.', $text_domain); ?>
    </p>

    <div class="atlt-dashboard-api-settings-container">
        <div class="atlt-dashboard-api-settings">
            <?php 
            $apis = [
                'gemini' => 'Gemini',
                'openai' => 'OpenAI'
            ];
            foreach ($apis as $key => $name): ?>
                <label for="<?php echo esc_attr($key); ?>-api"><?php printf(__('Add %s API key', $text_domain), esc_html($name)); ?></label>
                <input type="text" id="<?php echo esc_attr($key); ?>-api" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" disabled>
             <?php
                    printf(
            __('%s to See How to Generate %s API Key', $text_domain),
            '<a href="https://locoaddon.com/docs/pro-plugin/how-to-use-gemini-ai-to-translate-plugins-or-themes/generate-gemini-api-key/" target="_blank">' . esc_html__('Click Here', $text_domain) . '</a>',
            esc_html($name)
        );
             endforeach; ?>

            <div class="atlt-dashboard-save-btn-container">
                <button class="atlt-dashboard-save-btn"><?php _e('Save', $text_domain); ?></button>
            </div>
        </div>
    </div>
    </div>
</div>
