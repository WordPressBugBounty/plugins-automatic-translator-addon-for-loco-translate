<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Plugin_Installer {

    /**
     * Initialize class hooks and register AJAX action.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('wp_ajax_atlt_install_plugin', [$instance, 'atlt_install_plugin']);
    }

    /**
     * Main entry point for the AJAX plugin install/activate request.
     *
     * @return void
     */
    public function atlt_install_plugin()
    {
        $plugin_slug = $this->validate_install_request();
        
        // Require necessary WordPress files for installation
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        if ($this->is_pro_plugin($plugin_slug)) {
            $this->handle_pro_plugin_activation($plugin_slug);
        } else {
            $this->handle_free_plugin_installation($plugin_slug);
        }
    }

    /**
     * Validates nonces, permissions, and plugin slug whitelist.
     *
     * @return string Validated plugin slug.
     */
    private function validate_install_request()
    {
        check_ajax_referer('alt_install_nonce', '_wpnonce', true);

        if (! current_user_can('install_plugins')) {
            wp_send_json_error([
                'errorMessage' => __('Sorry, you are not allowed to install plugins on this site.', 'automatic-translator-addon-for-loco-translate'),
            ]);
        }

        if (empty($_POST['slug'])) {
            wp_send_json_error([
                'slug'         => '',
                'errorCode'    => 'no_plugin_specified',
                'errorMessage' => __('No plugin specified.', 'automatic-translator-addon-for-loco-translate'),
            ]);
        }

        $plugin_slug = sanitize_key(wp_unslash($_POST['slug']));
        
        // Whitelist of allowed plugin slugs - Security fix to prevent arbitrary plugin installation
        $allowed_plugins = self::get_allowed_plugins();

        // Validate that the plugin slug is in the whitelist
        if (! in_array($plugin_slug, $allowed_plugins, true)) {
            wp_send_json_error([
                'slug'         => $plugin_slug,
                'errorCode'    => 'plugin_not_allowed',
                'errorMessage' => __('This plugin is not allowed to be installed via this interface.', 'automatic-translator-addon-for-loco-translate'),
            ]);
        }

        return $plugin_slug;
    }

    public static function get_allowed_plugins()
    {
        $pro_configs = self::get_pro_plugins_config();
        $pro_slugs   = array_keys($pro_configs);
        
        $free_slugs = [
            'automatic-translations-for-polylang',
            'automatic-translate-addon-for-translatepress',
            'translate-words',
            'wpml-translation-check',
        ];
        
        return array_merge($pro_slugs, $free_slugs);
    }

    /**
     * Helper to check if WPML is active.
     *
     * @return bool True if WPML is active, false otherwise.
     */
    private static function is_wpml_active()
    {
        $all_plugins = get_plugins();
        foreach (array_keys($all_plugins) as $plugin_file) {
            if (strpos($plugin_file, 'sitepress-multilingual-cms/') === 0 && is_plugin_active($plugin_file)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns configuration array for Pro Plugins.
     *
     * @return array Pro plugin configurations.
     */
    private static function get_pro_plugins_config()
    {
        return [
            'autopoly-ai-translation-for-polylang-pro' => [
                'dependency' => is_plugin_active('polylang/polylang.php'),
                'dependency_message' => 'Please activate Polylang plugin first.',
                'plugin_file' => 'autopoly-ai-translation-for-polylang-pro/autopoly-ai-translation-for-polylang-pro.php',
                'not_found_message' => __('Pro plugin not found on this site. Please upload/install the Polylang Pro addon ZIP, then click Activate again.', 'automatic-translator-addon-for-loco-translate'),
            ],

            'automatic-translate-addon-pro-for-translatepress' => [
                'dependency' => is_plugin_active('translatepress-multilingual/index.php'),
                'dependency_message' => 'Please activate TranslatePress first.',
                'plugin_file' => 'automatic-translate-addon-pro-for-translatepress/automatic-translate-addon-for-translatepress-pro.php',
                'not_found_message' => __('Pro plugin not found. Please upload/install the TranslatePress Pro addon ZIP, then click Activate again.', 'automatic-translator-addon-for-loco-translate'),
            ],

            'automlp-ai-translation-for-wpml-pro' => [
                'dependency' => self::is_wpml_active(),
                'dependency_message' => 'Please activate WPML plugin first.',
                'plugin_file' => 'automlp-pro/automlp-pro.php',
                'not_found_message' => __('Pro plugin not found on this site. Please upload/install the WPML Pro addon ZIP, then click Activate again.', 'automatic-translator-addon-for-loco-translate'),
                'dynamic_lookup' => true,
            ],
        ];
    }

    /**
     * Checks if the requested slug belongs to a Pro plugin.
     *
     * @param string $plugin_slug The slug of the plugin to check.
     * @return bool True if the plugin is a Pro plugin, false otherwise.
     */
    private function is_pro_plugin($plugin_slug)
    {
        $pro_plugins = self::get_pro_plugins_config();
        return isset($pro_plugins[$plugin_slug]);
    }

    /**
     * Handles activation for zip-uploaded Pro plugins.
     *
     * @param string $plugin_slug The slug of the Pro plugin.
     * @return void
     */
    private function handle_pro_plugin_activation($plugin_slug)
    {
        if (! current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $pro_plugins = self::get_pro_plugins_config();
        $config = $pro_plugins[$plugin_slug];

        if (! $config['dependency']) {
            wp_send_json_error(['message' => $config['dependency_message']]);
        }

        $plugin_file = $config['plugin_file'];

        // WPML Pro fallback lookup
        if (! empty($config['dynamic_lookup']) && ! file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $all_plugins = get_plugins();

            foreach (array_keys($all_plugins) as $candidate_file) {
                if (strpos($candidate_file, 'automlp-pro/') === 0) {
                    $plugin_file = $candidate_file;
                    break;
                }
            }
        }

        if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            $network_wide = is_multisite();
            $result = activate_plugin($plugin_file, '', $network_wide, true);

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                ]);
            }

            wp_send_json_success([
                'message' => 'Plugin activated successfully',
            ]);
        }

        wp_send_json_error([
            'message' => $config['not_found_message'],
        ]);
    }

    /**
     * Handles downloading and installing free plugins from the WordPress repository.
     *
     * @param string $plugin_slug The slug of the free plugin.
     * @return void
     */
    private function handle_free_plugin_installation($plugin_slug)
    {
        $status = [
            'install' => 'plugin',
            'slug'    => $plugin_slug,
        ];

        // Gate WPML free addon install/activate behind WPML activation.
        if ($plugin_slug === 'wpml-translation-check' && ! self::is_wpml_active()) {
            wp_send_json_error(['message' => 'Please activate WPML plugin first.']);
        }

        $api = plugins_api('plugin_information', [
            'slug'   => $plugin_slug,
            'fields' => [
                'sections' => false,
            ],
        ]);

        if (is_wp_error($api)) {
            $status['errorMessage'] = $api->get_error_message();
            wp_send_json_error($status);
        }

        $status['pluginName'] = $api->name;
        $skin                 = new WP_Ajax_Upgrader_Skin();
        $upgrader             = new Plugin_Upgrader($skin);
        $result               = $upgrader->install($api->download_link);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status['debug'] = $skin->get_upgrade_messages();
        }

        if (is_wp_error($result)) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif (is_wp_error($skin->result)) {
            if ($skin->result->get_error_message() === 'Destination folder already exists.') {
                // If it already exists, proceed to activate it
                $this->activate_installed_plugin($api, $status);
            } else {
                $status['errorCode']    = $skin->result->get_error_code();
                $status['errorMessage'] = $skin->result->get_error_message();
                wp_send_json_error($status);
            }
        } elseif ($skin->get_errors()->has_errors()) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error($status);
        } elseif (is_null($result)) {
            global $wp_filesystem;
            $status['errorCode'] = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __('Unable to connect to the filesystem. Please confirm your credentials.', 'automatic-translator-addon-for-loco-translate');
            if ($wp_filesystem instanceof WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()) {
                $status['errorMessage'] = esc_html($wp_filesystem->errors->get_error_message());
            }
            wp_send_json_error($status);
        }

        $this->activate_installed_plugin($api, $status);
    }

    /**
     * Shared logic to activate a standard repository plugin after a successful install (or if it exists already).
     *
     * @param object $api    Plugin information object from WordPress.org API.
     * @param array  $status Reference tracking status of the install/activation.
     * @return void
     */
    private function activate_installed_plugin($api, $status)
    {
        $install_status = install_plugin_install_status($api);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $pagenow        = isset($_POST['pagenow']) ? sanitize_key(wp_unslash($_POST['pagenow'])) : '';

        // Auto-activate the plugin right after successful install
        if (current_user_can('activate_plugin', $install_status['file']) && is_plugin_inactive($install_status['file'])) {
            $network_wide      = (is_multisite() && 'import' !== $pagenow);
            $activation_result = activate_plugin($install_status['file'], '', $network_wide, true);
            
            if (is_wp_error($activation_result)) {
                $status['errorCode']    = $activation_result->get_error_code();
                $status['errorMessage'] = $activation_result->get_error_message();
                wp_send_json_error($status);
            } else {
                $status['activated'] = true;
            }
        }

        wp_send_json_success($status);
    }
}
