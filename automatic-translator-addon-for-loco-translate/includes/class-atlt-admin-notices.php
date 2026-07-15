<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Admin_Notices {

    /**
     * Initialize class hooks and register hooks to load/display notices.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('plugins_loaded', [$instance, 'atlt_check_required_loco_plugin']);
        add_action('init', [$instance, 'atlt_verify_loco_version']);
        add_action('admin_init', [$instance, 'atlt_init_review_notice']);
        add_action('init', [$instance, 'atlt_check_pro_conflict_notice']);
        add_action('admin_print_scripts', [$instance, 'atlt_hide_unrelated_notices']);
    }

    /**
     * Checks if the required Loco Translate plugin is active. If not, hooks notice.
     *
     * @return void
     */
    public function atlt_check_required_loco_plugin()
    {
        if (! function_exists('loco_plugin_self')) {
            add_action('admin_notices', [$this, 'atlt_plugin_required_admin_notice']);
        }
    }

    /**
     * Renders error notice on dashboard and deactivates this plugin if Loco is missing.
     *
     * @return void
     */
    public function atlt_plugin_required_admin_notice()
    {
        $plugin_info = get_plugin_data(ATLT_FILE, true, true);
        
        $this->render_loco_admin_notice(
            /* translators: 1: Current plugin name, 2: Plugin install URL, 3: Required plugin title attribute, 4: Required plugin name */
            __(
                'In order to use <strong>%1$s</strong> plugin, please install and activate the latest version  of <a href="%2$s" class="thickbox" title="%3$s">%4$s</a>',
                'automatic-translator-addon-for-loco-translate'
            ),
            [
                $plugin_info['Name'],
                'url',
                'title',
                'title'
            ]
        );

        if (current_user_can('activate_plugins')) {
            deactivate_plugins(plugin_basename(ATLT_FILE));
        }
    }

    /**
     * Build and render the Loco-Translate-related admin notices.
     *
     * @param string $message_template The translated message template containing placeholders.
     * @param array  $args             Arguments to pass to vsprintf.
     * @return void
     */
    private function render_loco_admin_notice($message_template, array $args)
    {
        if (current_user_can('activate_plugins')) {
            $url         = 'plugin-install.php?tab=plugin-information&plugin=loco-translate&TB_iframe=true';
            $title       = 'Loco Translate';
            
            // Map the token keywords to the actual localized/escaped values
            $replacements = array_map(function($item) use ($url, $title) {
                if ($item === 'url') {
                    return esc_url($url);
                }
                if ($item === 'title') {
                    return esc_attr($title);
                }
                return esc_attr((string) $item);
            }, $args);

            echo wp_kses_post('<div class="error"><p>' . vsprintf($message_template, $replacements) . '.</p></div>');
        }
    }

    /**
     * Checks compatibility conflicts (e.g. Pro plugin is already active) on WP init.
     *
     * @return void
     */
    public function atlt_check_pro_conflict_notice()
    {
        if (in_array(
            'loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php',
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter, not creating custom hook
            apply_filters('active_plugins', get_option('active_plugins'))
        )) {

            if (get_option('atlt-pro-version') !== false &&
                version_compare(get_option('atlt-pro-version'), '1.4', '<')) {

                add_action('admin_notices', [$this, 'atlt_use_pro_latest_version']);
            } else {
                add_action('admin_notices', [$this, 'atlt_pro_already_active_notice']);
                return;
            }
        }
    }

    /**
     * Cleans up third-party notices on the plugin dashboard page to prevent clutter.
     *
     * @return void
     */
    /**
     * Checks if the current page is the ATLT dashboard.
     *
     * @return bool
     */
    private function is_atlt_dashboard_page(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter, no data processing
        if (isset($_GET['page'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter, no data processing
            $page_param = sanitize_key(wp_unslash($_GET['page']));
            return $page_param === 'loco-atlt-dashboard';
        }
        return false;
    }

    /**
     * Removes specified notice callbacks from the global filters.
     *
     * @param array $rules Rules defining which callbacks to remove.
     * @return void
     */
    private function remove_notice_callbacks(array $rules)
    {
        global $wp_filter;
        $notice_types = array_keys($rules);
        foreach ($notice_types as $notice_type) {
            if (empty($wp_filter[$notice_type]->callbacks) || ! is_array($wp_filter[$notice_type]->callbacks)) {
                continue;
            }
            $remove_all_filters = empty($rules[$notice_type]);
            foreach ($wp_filter[$notice_type]->callbacks as $priority => $hooks) {
                foreach ($hooks as $name => $arr) {
                    if (is_object($arr['function']) && is_callable($arr['function'])) {
                        if ($remove_all_filters) {
                            unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                        }
                        continue;
                    }
                    $class = ! empty($arr['function'][0]) && is_object($arr['function'][0]) ? strtolower(get_class($arr['function'][0])) : '';
                    // Remove all callbacks except WPForms notices.
                    if ($remove_all_filters && strpos($class, 'wpforms') === false) {
                        unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                        continue;
                    }
                    $cb = is_array($arr['function']) ? $arr['function'][1] : $arr['function'];
                    // Remove a specific callback.
                    if (! $remove_all_filters) {
                        if (in_array($cb, $rules[$notice_type], true)) {
                            unset($wp_filter[$notice_type]->callbacks[$priority][$name]);
                        }
                        continue;
                    }
                }
            }
        }
    }

    public function atlt_hide_unrelated_notices()
    {
        if ($this->is_atlt_dashboard_page()) {
            // Define rules to remove callbacks.
            $rules = [
                'user_admin_notices' => [], // remove all callbacks.
                'admin_notices'      => [],
                'all_admin_notices'  => [],
                'admin_footer'       => [
                    'render_delayed_admin_notices', // remove this particular callback.
                ],
            ];
            
            $this->remove_notice_callbacks($rules);
            add_action('admin_notices', [$this, 'atlt_admin_notices'], PHP_INT_MAX);
        }
    }

    /**
     * Action callback proxy that triggers notice display filter hook.
     *
     * @return void
     */
    public function atlt_admin_notices()
    {
        do_action('atlt_display_admin_notices');
    }

    /**
     * Initialize review notice configuration early in the admin lifecycle.
     *
     * @return void
     */
    public function atlt_init_review_notice()
    {
        // Check if user has already rated
        $alreadyRated = get_option('atlt-already-rated') !== false ? get_option('atlt-already-rated') : "no";

        // Only show review notice if user hasn't rated yet
        if ($alreadyRated !== "yes") {
            // Register review notice hooks early
            if (class_exists('Atlt_Dashboard') && ! defined('ATLT_PRO_VERSION')) {
                Atlt_Dashboard::review_notice(
                    'atlt',
                    'LocoAI – Auto Translate for Loco Translate',
                    'https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post',
                );
            }
        }
    }

    /**
     * Verifies that the active Loco Translate plugin version is >= 2.4.0.
     *
     * @return void
     */
    public function atlt_verify_loco_version()
    {
        if (function_exists('loco_plugin_version')) {
            $locoV = loco_plugin_version();
            if (version_compare($locoV, '2.4.0', '<')) {
                add_action('admin_notices', [$this, 'atlt_use_loco_latest_version_notice']);
            }
        }
    }

    /**
     * Displays error notice if the user's Loco Translate plugin version is outdated.
     *
     * @return void
     */
    public function atlt_use_loco_latest_version_notice()
    {
        $plugin_info = get_plugin_data(ATLT_FILE, true, true);

        $this->render_loco_admin_notice(
            /* translators: %s: API provider name like OpenAI or Gemini */
            __(
                'In order to use <strong>%1$s</strong> (version <strong>%2$s</strong>), Please update <a href="%3$s" class="thickbox" title="%4$s">%5$s</a> official plugin to a latest version (2.4.0 or upper)',
                'automatic-translator-addon-for-loco-translate'
            ),
            [
                $plugin_info['Name'],
                $plugin_info['Version'],
                'url',
                'title',
                'title'
            ]
        );
    }

    /**
     * Renders notification indicating the Pro version is already active.
     *
     * @return void
     */
    public function atlt_pro_already_active_notice()
    {
        echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p><strong>LocoAI – Auto Translate for Loco Translate (Pro)</strong> is already active so no need to activate free anymore.</p> </div>';
    }

    /**
     * Prompts the user to update their Pro version to utilize new translation features.
     *
     * @return void
     */
    public function atlt_use_pro_latest_version()
    {
        echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p><strong>Please use <strong>LocoAI – Auto Translate for Loco Translate (Pro)</strong> latest version 1.4 or higher to use auto translate premium features.</p> </div>';
    }
}
