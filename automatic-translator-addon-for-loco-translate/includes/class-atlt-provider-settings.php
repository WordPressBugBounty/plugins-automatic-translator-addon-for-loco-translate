<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Provider_Settings {

    /**
     * Initialize class hooks and register AJAX and setting updates.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('wp_ajax_atlt_toggle_provider', [$instance, 'atlt_toggle_provider']);
        add_action('init', [$instance, 'atlt_ensure_provider_toggle_defaults']);
        add_action('init', [$instance, 'atlt_rating_option']);
        add_filter('plugin_action_links_' . plugin_basename(ATLT_FILE), [$instance, 'atlt_settings_page_link']);
    }

    /**
     * Toggles a translation provider status on/off via AJAX.
     *
     * @return void
     */
    public function atlt_toggle_provider()
    {
        check_ajax_referer('atlt_dashboard_nonce', 'nonce', true);
        
        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                [
                    'message' => __('Sorry, you are not allowed to do this action.', 'automatic-translator-addon-for-loco-translate'),
                ],
                403
            );
        }

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
        $enabled  = isset($_POST['enabled']) ? (int) sanitize_text_field(wp_unslash($_POST['enabled'])) : 0;

        if ($provider === '') {
            wp_send_json_error(
                [
                    'message' => __('Missing provider.', 'automatic-translator-addon-for-loco-translate'),
                ],
                400
            );
        }

        $allowed_providers = [
            'yandex',
            'openai',
            'chrome',
            'chatgpt',
            'gemini',
            'google',
            'deepl',
        ];

        if (! in_array($provider, $allowed_providers, true)) {
            wp_send_json_error(
                [
                    'message' => __('Invalid provider.', 'automatic-translator-addon-for-loco-translate'),
                ],
                400
            );
        }

        $settings = get_option('atlt_dashboard_provider_toggles', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $settings[$provider] = (bool) $enabled;
        update_option('atlt_dashboard_provider_toggles', $settings, false);

        wp_send_json_success(
            [
                'provider' => $provider,
                'enabled'  => (bool) $enabled,
            ]
        );
    }

    /**
     * Ensures dashboard provider toggle settings have default values configured.
     *
     * @return void
     */
    public function atlt_ensure_provider_toggle_defaults()
    {
        $defaults = [
            'openai' => true,
            'yandex' => true,
        ];

        $settings = get_option('atlt_dashboard_provider_toggles', null);

        if ($settings === null) {
            add_option('atlt_dashboard_provider_toggles', $defaults, '', false);
            return;
        }

        if (! is_array($settings)) {
            $settings = [];
        }

        $changed = false;
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $settings)) {
                $settings[$key] = $value;
                $changed        = true;
            }
        }

        if ($changed) {
            update_option('atlt_dashboard_provider_toggles', $settings, false);
        }
    }

    /**
     * Injects Buy Pro and Settings links into the plugins listing page links.
     *
     * @param string[] $links Array of existing action links for the plugin.
     * @return string[] Updated array of action links.
     */
    public function atlt_settings_page_link($links)
    {
        $links[] = '<a style="font-weight:bold" target="_blank" href="' . esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=plugins_list') . '">Buy PRO</a>';
        $links[] = '<a style="font-weight:bold" href="' . esc_url(get_admin_url(null, 'admin.php?page=loco-atlt-dashboard&tab=dashboard')) . '">Settings</a>';
        return $links;
    }

    /**
     * Performs option migration to remove deprecated rating Div settings.
     *
     * @return void
     */
    public function atlt_rating_option()
    {
        if (get_option('atlt-ratingDiv')) {
            update_option('atlt-already-rated', get_option('atlt-ratingDiv'));
            delete_option('atlt-ratingDiv');
        }
    }
}
