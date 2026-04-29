<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    $atlt_text_domain = 'automatic-translator-addon-for-loco-translate';
    $atlt_settings_error_message   = '';
    $atlt_settings_success_message = '';

    function atlt_get_saved_openai_api_key() {
        $stored_credentials = get_option('wp_ai_client_provider_credentials', array());
        $stored_credentials = is_array($stored_credentials) ? $stored_credentials : array();

        if (function_exists('_wp_register_default_connector_settings')) {
            $connector_key = get_option('connectors_ai_openai_api_key', '');
            if (is_string($connector_key) && trim($connector_key) !== '') {
                return trim($connector_key);
            }
        }

        if (isset($stored_credentials['openai']) && is_string($stored_credentials['openai'])) {
            return trim($stored_credentials['openai']);
        }

        return '';
    }

    function atlt_mask_api_key($api_key) {
        $api_key = is_string($api_key) ? trim($api_key) : '';
        if ($api_key === '') {
            return '';
        }
        return substr($api_key, 0, 8) . str_repeat('*', 24) . substr($api_key, -8) . ' ✅';
    }

    function atlt_save_openai_api_key($openai_key) {
        $openai_key = is_string($openai_key) ? trim($openai_key) : '';

        if (function_exists('_wp_register_default_connector_settings')) {
            update_option('connectors_ai_openai_api_key', $openai_key);

            $legacy_credentials = get_option('wp_ai_client_provider_credentials', array());
            $legacy_credentials = is_array($legacy_credentials) ? $legacy_credentials : array();
            if (isset($legacy_credentials['openai'])) {
                unset($legacy_credentials['openai']);
            }

            if (! empty($legacy_credentials)) {
                update_option('wp_ai_client_provider_credentials', $legacy_credentials);
            } else {
                delete_option('wp_ai_client_provider_credentials');
            }
            return;
        }

        $credentials           = get_option('wp_ai_client_provider_credentials', array());
        $credentials           = is_array($credentials) ? $credentials : array();
        $credentials['openai'] = $openai_key;
        update_option('wp_ai_client_provider_credentials', $credentials);
        delete_option('connectors_ai_openai_api_key');
    }

    function atlt_delete_openai_api_key() {
        delete_option('connectors_ai_openai_api_key');

        $credentials = get_option('wp_ai_client_provider_credentials', array());
        $credentials = is_array($credentials) ? $credentials : array();
        if (isset($credentials['openai'])) {
            unset($credentials['openai']);
        }

        if (! empty($credentials)) {
            update_option('wp_ai_client_provider_credentials', $credentials);
        } else {
            delete_option('wp_ai_client_provider_credentials');
        }
    }

    function atlt_get_openai_models() {
        $models = get_option('atlt_openai_models', array());
        if (! is_array($models)) {
            return array();
        }

        $models = array_values(
            array_filter(
                array_map(
                    static function ($model) {
                        return is_string($model) ? sanitize_text_field($model) : '';
                    },
                    $models
                )
            )
        );

        return array_values(array_unique($models));
    }

    function atlt_validate_provider_api_key($provider_id, $api_key) {
        $provider_id = is_string($provider_id) ? trim($provider_id) : '';
        $api_key     = is_string($api_key) ? trim($api_key) : '';

        if ($provider_id === '' || $api_key === '') {
            return array(
                'message' => __('Provider and API key are required.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        if (! class_exists('\WordPress\AiClient\AiClient')) {
            return array(
                'message' => __('AI client is not available.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        if (! $registry->hasProvider($provider_id)) {
            return array(
                'message' => __('Invalid AI provider.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        $lock_key = 'atlt_ai_test_lock_' . md5($provider_id . '|' . $api_key);
        if (get_transient($lock_key)) {
            return array(
                'message' => __('Please wait a few seconds before testing again.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        $auth_class = '\WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication';
        if (! class_exists($auth_class)) {
            return array(
                'message' => __('AI authentication class is not available.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        $registry->setProviderRequestAuthentication(
            $provider_id,
            new $auth_class($api_key)
        );
        set_transient($lock_key, 1, 5);

        try {
            $provider_classname    = $registry->getProviderClassName($provider_id);
            $provider_availability = $provider_classname::availability();

            if (! $provider_availability->isConfigured()) {
                return array(
                    'message' => __('API key is not configured for this provider.', 'automatic-translator-addon-for-loco-translate'),
                );
            }

            $model_metadata_directory = $provider_classname::modelMetadataDirectory();
            $model_metadata_list      = $model_metadata_directory->listModelMetadata();

            if ($provider_id === 'openai' && is_array($model_metadata_list)) {
                $model_ids = array();
                foreach ($model_metadata_list as $model_meta) {
                    $model_id = '';
                    if (is_object($model_meta) && method_exists($model_meta, 'getId')) {
                        $model_id = (string) $model_meta->getId();
                    } elseif (is_array($model_meta) && isset($model_meta['id'])) {
                        $model_id = (string) $model_meta['id'];
                    } elseif (is_string($model_meta)) {
                        $model_id = $model_meta;
                    }

                    $model_id = trim($model_id);
                    if ($model_id === '') {
                        continue;
                    }

                    if (
                        (str_starts_with($model_id, 'gpt-') || str_starts_with($model_id, 'o1-'))
                        && ! str_contains($model_id, '-instruct')
                        && ! str_contains($model_id, '-realtime')
                        && ! str_contains($model_id, '-audio')
                        && ! str_contains($model_id, '-tts')
                        && ! str_contains($model_id, '-transcribe')
                        && ! str_contains($model_id, '-image')
                        && $model_id !== 'o1-pro'
                        && $model_id !== 'o1-pro-2025-03-19'
                    ) {
                        $model_ids[] = $model_id;
                    }
                }

                $model_ids = array_values(array_unique($model_ids));
                sort($model_ids, SORT_STRING);
                update_option('atlt_openai_models', $model_ids);
            }
        } catch (\Exception $e) {
            $message = is_string($e->getMessage()) ? strtolower($e->getMessage()) : '';
            if (strpos($message, '429') !== false) {
                return array(
                    'message' => __('Rate limit exceeded. Please try again later.', 'automatic-translator-addon-for-loco-translate'),
                );
            }

            return array(
                'message' => __('Invalid API key. Please check your credentials.', 'automatic-translator-addon-for-loco-translate'),
            );
        }

        return true;
    }

    $atlt_openai_saved_key  = atlt_get_saved_openai_api_key();
    $atlt_openai_masked_key = atlt_mask_api_key($atlt_openai_saved_key);
    $atlt_openai_models = atlt_get_openai_models();
    if ($atlt_openai_saved_key !== '' && empty($atlt_openai_models)) {
        $atlt_openai_models = array('gpt-4o-mini');
    }
    $atlt_selected_openai_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in guarded POST branch below.
    $atlt_post_action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $atlt_post_action === 'atlt_save_dashboard_settings') {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'automatic-translator-addon-for-loco-translate'));
        }

        check_admin_referer('atlt_save_dashboard_settings', 'atlt_settings_nonce');

        $did_save_any_setting      = false;
        $reset_openai_api_key      = isset($_POST['reset_openai_api_key']);
        $existing_openai_key       = $atlt_openai_saved_key;
        $existing_openai_masked_key = $atlt_openai_masked_key;

        if (get_option('cpfm_opt_in_choice_cool_translations')) {
            $feedback_previous = get_option('atlt_feedback_opt_in', 'no');
            $feedback_opt_in   = isset($_POST['atlt-dashboard-feedback-checkbox']) ? 'yes' : 'no';
            update_option('atlt_feedback_opt_in', $feedback_opt_in);
            if ($feedback_previous !== $feedback_opt_in) {
                $did_save_any_setting = true;
            }

            if ($feedback_opt_in === 'no' && wp_next_scheduled('atlt_extra_data_update')) {
                wp_clear_scheduled_hook('atlt_extra_data_update');
            }

            if ($feedback_opt_in === 'yes' && ! wp_next_scheduled('atlt_extra_data_update')) {
                wp_schedule_event(time(), 'every_30_days', 'atlt_extra_data_update');
                if (class_exists('ATLT_cronjob')) {
                    ATLT_cronjob::atlt_send_data();
                }
            }
        }

        if ($reset_openai_api_key) {
            if ($existing_openai_key !== '') {
                atlt_delete_openai_api_key();
                delete_option('atlt_openai_models');
                delete_option('atlt_selected_openai_model');
                $did_save_any_setting = true;
            }
            $atlt_settings_success_message = __('OpenAI API key has been removed.', 'automatic-translator-addon-for-loco-translate');
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated above.
            $posted_credentials = isset($_POST['wp_ai_client_provider_credentials']) && is_array($_POST['wp_ai_client_provider_credentials'])
                ? wp_unslash($_POST['wp_ai_client_provider_credentials'])
                : null;

            if ($posted_credentials !== null && array_key_exists('openai', $posted_credentials)) {
                $posted_openai_key = sanitize_text_field((string) $posted_credentials['openai']);
                $posted_openai_key = trim($posted_openai_key);

                if (
                    $existing_openai_key !== ''
                    && $existing_openai_masked_key !== ''
                    && $posted_openai_key === $existing_openai_masked_key
                ) {
                    $posted_openai_key = $existing_openai_key;
                }

                $can_save_openai_key = true;
                if (
                    $posted_openai_key !== ''
                    && $posted_openai_key !== $existing_openai_key
                ) {
                    $validation_result = atlt_validate_provider_api_key('openai', $posted_openai_key);
                    if (is_array($validation_result) && ! empty($validation_result['message'])) {
                        $can_save_openai_key        = false;
                        $atlt_settings_error_message = sanitize_text_field((string) $validation_result['message']);
                    }
                }

                if ($can_save_openai_key) {
                    if ($posted_openai_key !== '' && $posted_openai_key !== $existing_openai_key) {
                        atlt_save_openai_api_key($posted_openai_key);
                        $did_save_any_setting = true;
                        $atlt_settings_success_message = __('OpenAI API key saved successfully.', 'automatic-translator-addon-for-loco-translate');
                    } elseif ($posted_openai_key === '' && $existing_openai_key !== '') {
                        atlt_delete_openai_api_key();
                        delete_option('atlt_openai_models');
                        delete_option('atlt_selected_openai_model');
                        $did_save_any_setting = true;
                        $atlt_settings_success_message = __('OpenAI API key has been removed.', 'automatic-translator-addon-for-loco-translate');
                    }
                }
            }
        }

        // Save selected OpenAI model when a key is present.
        if (! $reset_openai_api_key && isset($_POST['atlt_selected_openai_model'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated above.
            $posted_selected_model = sanitize_text_field((string) wp_unslash($_POST['atlt_selected_openai_model']));
            $current_selected_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));
            $available_models = atlt_get_openai_models();
            if ($atlt_openai_saved_key !== '' && empty($available_models)) {
                $available_models = array('gpt-4o-mini');
            }

            if ($posted_selected_model === '' && $current_selected_model !== '') {
                delete_option('atlt_selected_openai_model');
                $did_save_any_setting = true;
            } elseif ($posted_selected_model !== '' && $atlt_openai_saved_key !== '') {
                if (in_array($posted_selected_model, $available_models, true)) {
                    if ($posted_selected_model !== $current_selected_model) {
                        update_option('atlt_selected_openai_model', $posted_selected_model);
                        $did_save_any_setting = true;
                    }
                } elseif ($atlt_settings_error_message === '') {
                    $atlt_settings_error_message = __('Invalid OpenAI model selected.', 'automatic-translator-addon-for-loco-translate');
                }
            }
        }

        if ($atlt_settings_error_message === '' && $atlt_settings_success_message === '' && $did_save_any_setting) {
            $atlt_settings_success_message = __('Settings saved successfully.', 'automatic-translator-addon-for-loco-translate');
        }

        $atlt_openai_saved_key  = atlt_get_saved_openai_api_key();
        $atlt_openai_masked_key = atlt_mask_api_key($atlt_openai_saved_key);
        $atlt_openai_models = atlt_get_openai_models();
        if ($atlt_openai_saved_key !== '' && empty($atlt_openai_models)) {
            $atlt_openai_models = array('gpt-4o-mini');
        }
        $atlt_selected_openai_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));
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
            if ( $atlt_settings_error_message !== '' ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($atlt_settings_error_message); ?></p>
                </div>
                <?php
            }
            if ( $atlt_settings_success_message !== '' ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($atlt_settings_success_message); ?></p>
                </div>
                <?php
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
                    <form method="post" action="">
                        <div class="atlt-dashboard-api-settings-form">
                        <?php wp_nonce_field('atlt_save_dashboard_settings', 'atlt_settings_nonce'); ?>
                        <input type="hidden" name="action" value="atlt_save_dashboard_settings">

                        <?php
                             // Define all API-related settings in a single configuration array
                            $atlt_api_settings = [
                                'openai' => [
                                    'name' => 'OpenAI',
                                    'doc_url' => 'https://locoaddon.com/docs/how-to-generate-open-api-key/',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                    'is_pro' => false,
                                    'input_name' => 'wp_ai_client_provider_credentials[openai]',
                                    'value' => $atlt_openai_masked_key
                                ],
                                'gemini' => [
                                    'name' => 'Gemini AI',
                                    'doc_url' => 'https://locoaddon.com/docs/pro-plugin/how-to-use-gemini-ai-to-translate-plugins-or-themes/generate-gemini-api-key/',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                    'is_pro' => true
                                ]
                            ];

                        foreach ($atlt_api_settings as $atlt_key => $atlt_settings):
                            $atlt_disable_api_input = ! empty($atlt_settings['is_pro']);
                            if ($atlt_key === 'openai' && ! empty($atlt_openai_saved_key)) {
                                $atlt_disable_api_input = true;
                            }
                        ?>
                            <label for="<?php echo esc_attr($atlt_key); ?>-api">
                                <?php 
                                
                                
                                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                               /* translators: %s: API provider name like OpenAI or Gemini */ printf( esc_html__('Add %s API key %s', $atlt_text_domain), esc_html($atlt_settings['name']), esc_html($atlt_settings['is_pro'] ? '(Pro)' : '') ); ?>
                            </label>
                            <div class="input-group">
                                <input 
                                    type="text" 
                                    id="<?php echo esc_attr($atlt_key); ?>-api" 
                                    name="<?php echo isset($atlt_settings['input_name']) ? esc_attr($atlt_settings['input_name']) : ''; ?>"
                                    value="<?php echo isset($atlt_settings['value']) ? esc_attr($atlt_settings['value']) : ''; ?>"
                                    placeholder="<?php echo esc_attr($atlt_settings['placeholder']); ?>" 
                                    <?php if ( $atlt_disable_api_input ) { ?>
                                        disabled
                                    <?php } ?>
                                >
                                <?php if ( $atlt_key === 'openai' && ! empty($atlt_openai_saved_key) ) : ?>
                                    <button type="submit" name="reset_openai_api_key" class="button button-primary">
                                        <?php
                                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                        esc_html_e('Reset', $atlt_text_domain);
                                        ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ( $atlt_key === 'openai' && ! empty($atlt_openai_saved_key) && ! empty($atlt_openai_models) ) : ?>
                                <div class="atlt-dashboard-api-settings-openai-model">
                                    <label for="atlt_selected_openai_model" class="api-settings-label">
                                        <?php
                                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                        esc_html_e('Select OpenAI Model', $atlt_text_domain);
                                        ?>
                                    </label>
                                    <select name="atlt_selected_openai_model" class="atlt-openai-model-select">
                                        <option value="">
                                            <?php
                                            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                            esc_html_e('Select model', $atlt_text_domain);
                                            ?>
                                        </option>
                                        <?php foreach ($atlt_openai_models as $atlt_openai_model): ?>
                                            <option value="<?php echo esc_attr($atlt_openai_model); ?>" <?php selected($atlt_selected_openai_model, $atlt_openai_model); ?>>
                                                <?php echo esc_html($atlt_openai_model); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
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
                            <label for="atlt_context_aware" class="api-settings-label">
                                <?php
                                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                esc_html_e('Translation Context & Tone (Pro)', $atlt_text_domain);
                                ?>
                            </label>
                            <textarea
                                id="atlt_context_aware"
                                name="atlt_context_aware"
                                class="atlt-context-aware-textarea"
                                placeholder="<?php
                                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                echo esc_attr__('Add your business context, tone, and audience details so translations match your brand voice and improve accuracy.', $atlt_text_domain);
                                ?>"
                                rows="4"
                                disabled
                            ></textarea>
                            <p class="api-settings-description" style="margin-block: 5px;">
                                <?php
                                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                esc_html_e('Example: We run a business website. Keep the tone simple and professional. Audience includes customers and business users. Focus on keywords like services, pricing, and solutions.', $atlt_text_domain);
                                ?>
                            </p>
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
                                          // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                                          esc_html_e('Click here', $atlt_text_domain); ?></a></p>
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
                        <button type="submit" class="button button-primary"><?php 
                        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        esc_html_e('Save', $atlt_text_domain); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
