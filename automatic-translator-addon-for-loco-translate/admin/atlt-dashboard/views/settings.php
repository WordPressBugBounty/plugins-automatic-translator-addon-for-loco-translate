<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    $atlt_text_domain = 'automatic-translator-addon-for-loco-translate';
    $atlt_settings_error_message   = '';
    $atlt_settings_success_message = '';


    $atlt_openai_saved_key  = ATLT_Settings_Service::atlt_get_saved_openai_api_key();
    $atlt_openai_masked_key = ATLT_Settings_Service::atlt_mask_api_key($atlt_openai_saved_key);
    $atlt_openai_models = ATLT_Settings_Service::atlt_get_ai_model_list_with_fallback( 'openai', $atlt_openai_saved_key !== '' );
    $atlt_stored_openai_model   = sanitize_text_field( (string) get_option( 'atlt_selected_openai_model', '' ) );
    $atlt_selected_openai_model = $atlt_stored_openai_model;

    // Resolve display fallback when selection is missing/invalid; avoid update_option on every GET.
    if ( $atlt_openai_saved_key !== '' ) {
        $atlt_is_valid_selected = ( '' !== $atlt_selected_openai_model ) && array_key_exists( $atlt_selected_openai_model, $atlt_openai_models );
        if ( ! $atlt_is_valid_selected ) {
            $atlt_fallback_model = array_key_exists( 'gpt-4o-mini', $atlt_openai_models ) ? 'gpt-4o-mini' : '';
            if ( '' === $atlt_fallback_model ) {
                $atlt_keys           = array_keys( $atlt_openai_models );
                $atlt_fallback_model = isset( $atlt_keys[0] ) ? (string) $atlt_keys[0] : '';
            }
            if ( '' !== $atlt_fallback_model ) {
                $atlt_selected_openai_model = $atlt_fallback_model;

                // One-time repair: persist only when a stale non-empty model was removed from the list.
                if (
                    '' !== $atlt_stored_openai_model
                    && ! array_key_exists( $atlt_stored_openai_model, $atlt_openai_models )
                ) {
                    update_option( 'atlt_selected_openai_model', $atlt_fallback_model );
                }
            }
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked in guarded POST branch below.
    $atlt_post_action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    $atlt_request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
    if ($atlt_request_method === 'POST' && $atlt_post_action === 'atlt_save_dashboard_settings') {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage settings.', 'automatic-translator-addon-for-loco-translate'));
        }

        check_admin_referer('atlt_save_dashboard_settings', 'atlt_settings_nonce');

        $atlt_did_save_any_setting      = false;
        $atlt_reset_openai_api_key      = isset($_POST['reset_openai_api_key']);
        $atlt_existing_openai_key       = $atlt_openai_saved_key;
        $atlt_existing_openai_masked_key = $atlt_openai_masked_key;

        if (get_option('cpfm_opt_in_choice_cool_translations')) {
            $atlt_feedback_previous = get_option('atlt_feedback_opt_in', 'no');
            $atlt_feedback_opt_in   = isset($_POST['atlt-dashboard-feedback-checkbox']) ? 'yes' : 'no';
            update_option('atlt_feedback_opt_in', $atlt_feedback_opt_in);
            if ($atlt_feedback_previous !== $atlt_feedback_opt_in) {
                $atlt_did_save_any_setting = true;
            }

            if ($atlt_feedback_opt_in === 'no' && wp_next_scheduled('atlt_extra_data_update')) {
                wp_clear_scheduled_hook('atlt_extra_data_update');
            }

            if ($atlt_feedback_opt_in === 'yes' && ! wp_next_scheduled('atlt_extra_data_update')) {
                wp_schedule_event(time(), 'every_30_days', 'atlt_extra_data_update');
                if (class_exists('ATLT_cronjob')) {
                    ATLT_cronjob::atlt_send_data();
                }
            }
        }

        if ($atlt_reset_openai_api_key) {
            if ($atlt_existing_openai_key !== '') {
                ATLT_Settings_Service::atlt_delete_openai_api_key();
                delete_option('atlt_openai_models');
                delete_option('atlt_selected_openai_model');
                delete_transient( 'atlt_openai_models' );
                $atlt_did_save_any_setting = true;
            }
            $atlt_settings_success_message = __('OpenAI API key has been removed.', 'automatic-translator-addon-for-loco-translate');
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $atlt_posted_credentials = isset($_POST['wp_ai_client_provider_credentials']) && is_array($_POST['wp_ai_client_provider_credentials'])
                ? wp_unslash($_POST['wp_ai_client_provider_credentials'])
                : null;

            if ($atlt_posted_credentials !== null && array_key_exists('openai', $atlt_posted_credentials)) {
                $atlt_posted_openai_key = sanitize_text_field((string) $atlt_posted_credentials['openai']);
                $atlt_posted_openai_key = trim($atlt_posted_openai_key);

                if (
                    $atlt_existing_openai_key !== ''
                    && $atlt_existing_openai_masked_key !== ''
                    && $atlt_posted_openai_key === $atlt_existing_openai_masked_key
                ) {
                    $atlt_posted_openai_key = $atlt_existing_openai_key;
                }

                $atlt_can_save_openai_key = true;
                if (
                    $atlt_posted_openai_key !== ''
                    && $atlt_posted_openai_key !== $atlt_existing_openai_key
                ) {
                    $atlt_validation_result = ATLT_Settings_Service::atlt_validate_provider_api_key('openai', $atlt_posted_openai_key);
                    if (is_array($atlt_validation_result) && ! empty($atlt_validation_result['message'])) {
                        $atlt_can_save_openai_key        = false;
                        $atlt_settings_error_message = sanitize_text_field((string) $atlt_validation_result['message']);
                    }
                }

                if ($atlt_can_save_openai_key) {
                    if ($atlt_posted_openai_key !== '' && $atlt_posted_openai_key !== $atlt_existing_openai_key) {
                        ATLT_Settings_Service::atlt_save_openai_api_key($atlt_posted_openai_key);
                        $atlt_did_save_any_setting = true;
                        $atlt_settings_success_message = __('OpenAI API key saved successfully.', 'automatic-translator-addon-for-loco-translate');
                        delete_transient( 'atlt_openai_models' );

                        // Auto-select a good default model on first key add.
                        // Prefer cheapest/fast options when available.
                        $atlt_current_selected_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));
                        $atlt_available_models       = ATLT_Settings_Service::atlt_get_ai_model_list_with_fallback( 'openai', true );

                        $atlt_default_model = array_key_exists( 'gpt-4o-mini', $atlt_available_models )
                            ? 'gpt-4o-mini'
                            : '';
                        if ( '' === $atlt_default_model ) {
                            $atlt_keys          = array_keys( $atlt_available_models );
                            $atlt_default_model = isset( $atlt_keys[0] ) ? (string) $atlt_keys[0] : '';
                        }

                        if (
                            '' !== $atlt_default_model
                            && (
                                '' === $atlt_current_selected_model
                                || ! array_key_exists( $atlt_current_selected_model, $atlt_available_models )
                            )
                        ) {
                            update_option( 'atlt_selected_openai_model', $atlt_default_model );
                        }
                    } elseif ($atlt_posted_openai_key === '' && $atlt_existing_openai_key !== '') {
                        ATLT_Settings_Service::atlt_delete_openai_api_key();
                        delete_option('atlt_openai_models');
                        delete_option('atlt_selected_openai_model');
                        delete_transient( 'atlt_openai_models' );
                        $atlt_did_save_any_setting = true;
                        $atlt_settings_success_message = __('OpenAI API key has been removed.', 'automatic-translator-addon-for-loco-translate');
                    }
                }
            }
        }

        // Save selected OpenAI model when a key is present.
        if (! $atlt_reset_openai_api_key && isset($_POST['atlt_selected_openai_model'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated above.
            $atlt_posted_selected_model = sanitize_text_field((string) wp_unslash($_POST['atlt_selected_openai_model']));
            $atlt_current_selected_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));
            $atlt_available_models = ATLT_Settings_Service::atlt_get_ai_model_list_with_fallback( 'openai', $atlt_openai_saved_key !== '' );

            if ($atlt_posted_selected_model === '' && $atlt_current_selected_model !== '') {
                delete_option('atlt_selected_openai_model');
                $atlt_did_save_any_setting = true;
            } elseif ($atlt_posted_selected_model !== '' && $atlt_openai_saved_key !== '') {
                if ( array_key_exists( $atlt_posted_selected_model, $atlt_available_models ) ) {
                    if ($atlt_posted_selected_model !== $atlt_current_selected_model) {
                        update_option('atlt_selected_openai_model', $atlt_posted_selected_model);
                        $atlt_did_save_any_setting = true;
                    }
                } elseif ($atlt_settings_error_message === '') {
                    $atlt_settings_error_message = __('Invalid OpenAI model selected.', 'automatic-translator-addon-for-loco-translate');
                }
            }
        }

        if ($atlt_settings_error_message === '' && $atlt_settings_success_message === '' && $atlt_did_save_any_setting) {
            $atlt_settings_success_message = __('Settings saved successfully.', 'automatic-translator-addon-for-loco-translate');
        }

        $atlt_openai_saved_key  = ATLT_Settings_Service::atlt_get_saved_openai_api_key();
        $atlt_openai_masked_key = ATLT_Settings_Service::atlt_mask_api_key($atlt_openai_saved_key);
        $atlt_openai_models = ATLT_Settings_Service::atlt_get_ai_model_list_with_fallback( 'openai', $atlt_openai_saved_key !== '' );
        $atlt_selected_openai_model = sanitize_text_field((string) get_option('atlt_selected_openai_model', ''));
    }
?>
    
    <div class="atlt-dashboard-settings">
        <div class="atlt-dashboard-settings-container">
            <?php
            do_action( 'atlt_display_admin_notices' );
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
                esc_html_e('LocoAI Settings', 'automatic-translator-addon-for-loco-translate'); ?></h1>
            </div>

            <p class="description">
                <?php
                esc_html_e('Configure your settings for the LocoAI to optimize your translation experience. Enter your API keys and manage your preferences for seamless integration.', 'automatic-translator-addon-for-loco-translate'); ?>
            </p>

            <div class="atlt-dashboard-api-settings-container">
                <div class="atlt-dashboard-api-settings">
                    <form method="post" action="">
                        <div class="atlt-dashboard-api-settings-form">
                        <?php wp_nonce_field('atlt_save_dashboard_settings', 'atlt_settings_nonce'); ?>
                        <input type="hidden" name="action" value="atlt_save_dashboard_settings">

                        <?php
                            $atlt_provider_toggles = get_option('atlt_dashboard_provider_toggles', []);
                            $atlt_provider_toggles = is_array($atlt_provider_toggles) ? $atlt_provider_toggles : [];

                             // Define all API-related settings in a single configuration array
                            $atlt_api_settings = [
                                'openai' => [
                                    'name' => 'OpenAI',
                                    'doc_url' => 'https://locoaddon.com/docs/how-to-generate-open-api-key/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=open_api_key',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                    'is_pro' => false,
                                    'input_name' => 'wp_ai_client_provider_credentials[openai]',
                                    'value' => $atlt_openai_masked_key,
                                    'enabled' => ($atlt_provider_toggles['openai'] ?? true) !== false
                                ],
                                'gemini' => [
                                    'name' => 'Gemini AI',
                                    'doc_url' => 'https://locoaddon.com/docs/how-to-generate-google-gemini-api-key/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=gemini_api_key',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                    'is_pro' => true,
                                    'enabled' => true
                                ],
                                'deepl' => [
                                    'name' => 'DeepL',
                                    'doc_url' => 'https://locoaddon.com/docs/generate-deepl-api-key-loco-ai/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=deepl_api_key',
                                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                                    'is_pro' => true,
                                    'enabled' => true
                                ],
                            ];

                        foreach ($atlt_api_settings as $atlt_key => $atlt_settings):
                            if ($atlt_key === 'openai' && empty($atlt_settings['enabled'])) {
                                continue;
                            }
                            $atlt_disable_api_input = ! empty($atlt_settings['is_pro']);
                            if ($atlt_key === 'openai' && ! empty($atlt_openai_saved_key)) {
                                $atlt_disable_api_input = true;
                            }
                        ?>
                            <label for="<?php echo esc_attr($atlt_key); ?>-api">
                                <?php 
                                
                                
                                echo wp_kses(
                                    sprintf(
                                        /* translators: %1$s: API provider name like OpenAI or Gemini, %2$s: Pro link */
                                        __( 'Add %1$s API key %2$s', 'automatic-translator-addon-for-loco-translate' ),
                                        esc_html( $atlt_settings['name'] ),
                                        ! empty( $atlt_settings['is_pro'] )
                                            ? '(<a href="' . esc_url( 'https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=api_key' ) . '" target="_blank" rel="noopener noreferrer">' .
                                                esc_html__( 'Pro', 'automatic-translator-addon-for-loco-translate' ) .
                                              '</a>)'
                                            : ''
                                    ),
                                    array(
                                        'a' => array(
                                            'href'   => array(),
                                            'target' => array(),
                                            'rel'    => array(),
                                        ),
                                    )
                                );
                                ?>
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
                                        esc_html_e('Reset', 'automatic-translator-addon-for-loco-translate');
                                        ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ( $atlt_key === 'openai' && ! empty($atlt_openai_saved_key) && ! empty($atlt_openai_models) ) : ?>
                                <div class="atlt-dashboard-api-settings-openai-model">
                                    <label for="atlt_selected_openai_model" class="api-settings-label">
                                        <?php
                                        esc_html_e('Select OpenAI Model', 'automatic-translator-addon-for-loco-translate');
                                        ?>
                                    </label>
                                    <select name="atlt_selected_openai_model" class="atlt-openai-model-select">
                                        <option value="">
                                            <?php
                                            esc_html_e('Select model', 'automatic-translator-addon-for-loco-translate');
                                            ?>
                                        </option>
                                        <?php foreach ( $atlt_openai_models as $atlt_openai_model_id => $atlt_openai_model_label ) : ?>
                                            <option value="<?php echo esc_attr( $atlt_openai_model_id ); ?>" <?php selected( $atlt_selected_openai_model, $atlt_openai_model_id ); ?>>
                                                <?php echo esc_html( $atlt_openai_model_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <?php
                            echo wp_kses(
                                sprintf(
                                     /* translators: %1$s: Click Here link, %2$s: API provider name like OpenAI or Gemini */  __('%1$s to See How to Generate %2$s API Key', 'automatic-translator-addon-for-loco-translate'),
                                    '<a href="' . esc_url($atlt_settings['doc_url']) . '" target="_blank" rel="noopener noreferrer">' . 
                                    esc_html__('Click Here', 'automatic-translator-addon-for-loco-translate') . '</a>',
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
                                echo wp_kses(
                                    sprintf(
                                        /* translators: %s: Pro link */
                                        __( 'Translation Context & Tone (%s)', 'automatic-translator-addon-for-loco-translate' ),
                                        '<a href="' . esc_url( 'https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=context_aware' ) . '" target="_blank" rel="noopener noreferrer">' .
                                        esc_html__( 'Pro', 'automatic-translator-addon-for-loco-translate' ) .
                                        '</a>'
                                    ),
                                    array(
                                        'a' => array(
                                            'href'   => array(),
                                            'target' => array(),
                                            'rel'    => array(),
                                        ),
                                    )
                                );
                                ?>
                            </label>
                            <textarea
                                id="atlt_context_aware"
                                name="atlt_context_aware"
                                class="atlt-context-aware-textarea"
                                placeholder="<?php
                                echo esc_attr__('Add your business context, tone, and audience details so translations match your brand voice and improve accuracy.', 'automatic-translator-addon-for-loco-translate');
                                ?>"
                                rows="6"
                                disabled
                            ></textarea>
                            <p class="api-settings-description" style="margin-block: 5px;">
                            <?php
                                esc_html_e('Example: We run a business website. Keep the tone simple and professional. Audience includes customers and business users. Focus on keywords like services, pricing, and solutions.', 'automatic-translator-addon-for-loco-translate');
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
                                      esc_html_e('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'automatic-translator-addon-for-loco-translate'); ?></p><a href="#" class="atlt-see-terms">[See terms]</a>
                                      
                                  </div>
                                  <div id="termsBox" style="display: none;padding-left: 20px; margin-top: 10px; font-size: 12px; color: #999;">
                                          <p><?php 
                                          echo esc_html__("Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect: ", 'automatic-translator-addon-for-loco-translate'); ?><a href="<?php echo esc_url('https://my.coolplugins.net/terms/usage-tracking/'); ?>" target="_blank" rel="noopener noreferrer"><?php 
                                          esc_html_e('Click here', 'automatic-translator-addon-for-loco-translate'); ?></a></p>
                                          <ul style="list-style-type:auto;">
                                              <li><?php 
                                              esc_html_e('Your website home URL and WordPress admin email.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                                              <li><?php 
                                              esc_html_e('To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.', 'automatic-translator-addon-for-loco-translate'); ?></li>
                                          </ul>
                                  </div>
                              </div>
                        <?php endif; ?>
                        <div class="atlt-dashboard-save-btn-container">
                        <button type="submit" class="button button-primary"><?php 
                        esc_html_e('Save', 'automatic-translator-addon-for-loco-translate'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
