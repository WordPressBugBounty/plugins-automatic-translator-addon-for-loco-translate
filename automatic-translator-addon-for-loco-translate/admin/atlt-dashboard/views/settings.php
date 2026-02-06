<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    $atlt_text_domain = 'loco-translate-addon';

    // Process form submission
    if (
        isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
        && current_user_can('manage_options')
    ) {
        check_admin_referer('atlt_save_optin_settings', 'atlt_optin_nonce');

        $atlt_feedback_opt_in = null;

        // Handle feedback checkbox
        if ( get_option('cpfm_opt_in_choice_cool_translations') ) {
            $atlt_feedback_opt_in = isset($_POST['atlt-dashboard-feedback-checkbox']) ? 'yes' : 'no';
            update_option('atlt_feedback_opt_in', $atlt_feedback_opt_in);
        }

        // If user opted out, remove the cron job
        if ( $atlt_feedback_opt_in === 'no' && wp_next_scheduled('atlt_extra_data_update') ){
            wp_clear_scheduled_hook('atlt_extra_data_update');
        }

        if ( $atlt_feedback_opt_in === 'yes' && ! wp_next_scheduled('atlt_extra_data_update') ) {
            wp_schedule_event( time(), 'every_30_days', 'atlt_extra_data_update' );   
            if ( class_exists('ATLT_cronjob') ) {
                ATLT_cronjob::atlt_send_data();
            }
        }
    }
    ?>
    
    <div class="atlt-dashboard-settings">
        <div class="atlt-dashboard-settings-container">
            <?php
            if ( isset($GLOBALS['atlt_admin_notices']) ) {
                foreach ( $GLOBALS['atlt_admin_notices'] as $atlt_notice ) {
                    echo wp_kses_post( $atlt_notice );
                }
            }
            ?>
            <div class="header">
                
                <h1><?php 
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_html_e('LocoAI Settings', $atlt_text_domain); ?></h1>
                <div class="atlt-dashboard-status">
                    <span><?php 
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                    esc_html_e('Inactive', $atlt_text_domain); ?></span>
                    <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=settings'); ?>" class='atlt-dashboard-btn' target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php 
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        esc_attr_e('Upgrade Now', $atlt_text_domain); ?>">
                        <?php 
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        esc_html_e('Upgrade Now', $atlt_text_domain); ?>
                    </a>
                </div>
            </div>

            <p class="description">
                <?php
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_html_e('Configure your settings for the LocoAI to optimize your translation experience. Enter your API keys and manage your preferences for seamless integration.', $atlt_text_domain); ?>
            </p>

            <div class="atlt-dashboard-api-settings-container">
                <div class="atlt-dashboard-api-settings">
                    <form method="post">
                        <div class="atlt-dashboard-api-settings-form">
                        <?php wp_nonce_field('atlt_save_optin_settings', 'atlt_optin_nonce'); ?>

                        <?php
                             // Define all API-related settings in a single configuration array
                            $atlt_api_settings = [
                                'gemini' => [
                                    'name' => 'Gemini AI',
                                    'doc_url' => 'https://locoaddon.com/docs/pro-plugin/how-to-use-gemini-ai-to-translate-plugins-or-themes/generate-gemini-api-key/',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                                ],
                                'openai' => [
                                    'name' => 'OpenAI',
                                    'doc_url' => 'https://locoaddon.com/docs/how-to-generate-open-api-key/',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
                                ]
                            ];

                        foreach ($atlt_api_settings as $atlt_key => $atlt_settings): ?>
                            <label for="<?php echo esc_attr($atlt_key); ?>-api">
                                <?php 
                                
                                
                                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                               /* translators: %s: API provider name like OpenAI or Gemini */ printf( esc_html__('Add %s API key', $atlt_text_domain), esc_html($atlt_settings['name']) ); ?>
                            </label>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr($atlt_key); ?>-api" 
                                placeholder="<?php echo esc_attr($atlt_settings['placeholder']); ?>" 
                                disabled
                            >
                            <?php
                            echo wp_kses(
                                sprintf(
                                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                     /* translators: %1$s: Click Here link, %2$s: API provider name like OpenAI or Gemini */  __('%1$s to See How to Generate %2$s API Key', $atlt_text_domain),
                                    '<a href="' . esc_url($atlt_settings['doc_url']) . '" target="_blank" rel="noopener noreferrer">' . 
                                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                    esc_html__('Click Here', $atlt_text_domain) . '</a>',
                                    esc_html($atlt_settings['name'])
                                ),
                                array(
                                    'a' => array(
                                        'href' => array(),
                                        'target' => array(),
                                        'rel' => array(),
                                    ),
                                )
                            );
                        endforeach; ?>
                        </div>
                        <!-- Feedback Opt-In -->
                        <?php if (get_option('cpfm_opt_in_choice_cool_translations')) : ?>
                              
                              <div class="atlt-dashboard-feedback-container">
                                  <div class="feedback-row">
                                      <input type="checkbox" 
                                          id="atlt-dashboard-feedback-checkbox" 
                                          name="atlt-dashboard-feedback-checkbox"
                                          <?php checked(get_option('atlt_feedback_opt_in'), 'yes'); ?>>
                                      <p><?php 
                                      // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                      esc_html_e('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', $atlt_text_domain); ?></p>
                                      <a href="#" class="atlt-see-terms">[See terms]</a>
                                  </div>
                                  <div id="termsBox" style="display: none;padding-left: 20px; margin-top: 10px; font-size: 12px; color: #999;">
                                          <p><?php 
                                          // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                          echo esc_html__("Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect: ", $atlt_text_domain); ?><a href="<?php echo esc_url('https://my.coolplugins.net/terms/usage-tracking/'); ?>" target="_blank" rel="noopener noreferrer"><?php
                                           // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain ?>
                                          <ul style="list-style-type:auto;">
                                              <li><?php 
                                              // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                              esc_html_e('Your website home URL and WordPress admin email.', $atlt_text_domain); ?></li>
                                              <li><?php 
                                              // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                              esc_html_e('To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.',$atlt_text_domain); ?></li>
                                          </ul>
                                  </div>
                              </div>
                        <?php endif; ?>
                        <div class="atlt-dashboard-save-btn-container">
                        <button type="submit" class="button button-primary"<?php if ( ! get_option('cpfm_opt_in_choice_cool_translations') ) echo ' disabled="disabled"'; ?>><?php 
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        esc_html_e('Save', $atlt_text_domain); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
