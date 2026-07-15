<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Assets {

    /**
     * Initialize class hooks and register asset loaders.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('admin_enqueue_scripts', [$instance, 'atlt_enqueue_scripts']);
        add_action('admin_footer', [$instance, 'atlt_load_ytranslate_scripts'], 100);
        add_filter('admin_body_class', [$instance, 'atlt_add_custom_class']);
    }

    /**
     * Checks if the current request is for the Loco Translate file editor.
     *
     * @return bool
     */
    private function is_loco_file_editor(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking action parameter, no data processing
        $req_action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        return $req_action === 'file-edit';
    }

    /**
     * Injects block page translation disable script in the admin footer.
     *
     * @return void
     */
    public function atlt_load_ytranslate_scripts()
    {
        if ($this->is_loco_file_editor()) {
            // Secure inline script using WordPress best practices
            wp_add_inline_script(
                'loco-translate-admin',
                'document.getElementsByTagName("html")[0].setAttribute("translate", "no");'
            );
        }
    }

    /**
     * Adds the "notranslate" class to the admin body element to disable browser auto-translators.
     *
     * @param string $classes Current body classes.
     * @return string Updated body classes string.
     */
    public function atlt_add_custom_class($classes)
    {
        if ($this->is_loco_file_editor()) {
            return "$classes notranslate";
        }
        return $classes;
    }

    /**
     * Enqueues scripts, stylesheets, and localizes properties for admin dashboards and translation editors.
     *
     * @param string $hook The current admin page screen hook.
     * @return void
     */
    public function atlt_enqueue_scripts($hook)
    {
        // Load assets for the dashboard page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter, no data processing
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page === 'loco-atlt-dashboard') {
             wp_enqueue_style(
                'atlt-dashboard-style',
                ATLT_URL . 'admin/atlt-dashboard/css/admin-styles.css',
                [],
                ATLT_VERSION,
                'all'
            );
            wp_enqueue_script(
                'atlt-dashboard-script',
                ATLT_URL . 'admin/atlt-dashboard/js/atlt-data-share-setting.js',
                ['jquery'],
                ATLT_VERSION,
                true
            );

            wp_localize_script(
                'atlt-dashboard-script',
                'atltDashboard',
                [
                    'nonce'           => wp_create_nonce('atlt_dashboard_nonce'),
                    'ajax_url'        => admin_url('admin-ajax.php'),
                    'allowed_plugins' => ATLT_Plugin_Installer::get_allowed_plugins(),
                ]
            );
        }

        // Keep existing editor page scripts
        if ($this->is_loco_file_editor()) {
            $provider_toggles = get_option('atlt_dashboard_provider_toggles', []);
            $openai_enabled = ! isset($provider_toggles['openai']) || $provider_toggles['openai'] !== false;
            $yandex_enabled = ! isset($provider_toggles['yandex']) || $provider_toggles['yandex'] !== false;

            wp_register_script('loco-addon-custom', ATLT_URL . 'assets/js/custom.min.js', ['loco-translate-admin'], ATLT_VERSION, true);
            wp_register_style(
                'loco-addon-custom-css',
                ATLT_URL . 'assets/css/custom.min.css',
                null,
                ATLT_VERSION,
                'all'
            );

            $saved_openai_key              = ATLT_Settings_Service::atlt_get_saved_openai_api_key();

            if ($openai_enabled && $saved_openai_key) {
                wp_register_script('loco-addon-openai-flow', ATLT_URL . 'assets/js/openai-flow.js', ['loco-translate-admin'], ATLT_VERSION, true);
                wp_enqueue_script('loco-addon-openai-flow');
            }
            wp_enqueue_script('loco-addon-custom');
            if ($yandex_enabled) {
                wp_register_script('atlt-yandex-widget', ATLT_URL . 'assets/js/widget.js', ['loco-translate-admin'], ATLT_VERSION, true);
                wp_localize_script(
                    'atlt-yandex-widget',
                    'atltYandexWidgetConfig',
                    [
                        'widgetId'    => 'ytWidget',
                        'pageLang'    => 'en',
                        'widgetTheme' => 'light',
                        'autoMode'    => 'false',
                    ]
                );
                wp_enqueue_script('atlt-yandex-widget');
            }

            wp_enqueue_style('loco-addon-custom-css');


            $extraData['ajax_url']         = admin_url('admin-ajax.php');
            $extraData['nonce']            = wp_create_nonce('loco-addon-nonces');
            $extraData['ATLT_URL']         = ATLT_URL;
            $extraData['preloader_path']   = 'preloader.gif';
            $extraData['gt_preview']       = 'google.png';
            $extraData['dpl_preview']      = 'deepl.png';
            $extraData['yt_preview']       = 'yandex.png';
            $extraData['chatGPT_preview']  = 'chatgpt.png';
            $extraData['geminiAI_preview'] = 'gemini.png';
            $extraData['chromeAi_preview'] = 'chrome.png';
            $extraData['document_preview'] = 'document.svg';
            $extraData['openai_preview']   = 'openai.png';
            $extraData['error_preview']    = 'error-icon.svg';
            $extraData['extra_class']      = is_rtl() ? 'atlt-rtl' : '';
            $extraData['atlt_dashboard_provider_toggles'] = get_option('atlt_dashboard_provider_toggles', []);
            $extraData['loco_settings_url'] = admin_url('admin.php?page=loco-config&action=apis');
            $extraData['has_openai_api_key']    = ($saved_openai_key !== '') ? true : false;
            wp_localize_script('loco-addon-custom', 'extradata', $extraData);
            
            // copy object
            wp_add_inline_script(
                'loco-translate-admin',
                'var returnedTarget = JSON.parse(JSON.stringify(window.loco));
                window.locoConf=returnedTarget;'
            );
        }
    }
}
