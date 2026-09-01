<?php
/*
Plugin Name: LocoAI – Auto Translate for Loco Translate
Description: Auto translation addon for Loco Translate – translate plugin & theme strings using Yandex Translate.
Version: 2.7.6
License: GPL2
Text Domain: automatic-translator-addon-for-loco-translate
Author: Cool Plugins
Requires Plugins: loco-translate
Domain Path: /languages
Author URI: https://coolplugins.net/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
*/

    if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
    }

    define('ATLT_FILE', __FILE__);
    define('ATLT_URL', plugin_dir_url(ATLT_FILE));
    define('ATLT_PATH', plugin_dir_path(ATLT_FILE));
    define('ATLT_VERSION', '2.7.6');
    ! defined('ATLT_FEEDBACK_API') && define('ATLT_FEEDBACK_API', "https://feedback.coolplugins.net/");

    /**
     * @package LocoAI – Auto Translate for Loco Translate
     * @version 2.5.1
     */

    if (! class_exists('LocoAutoTranslateAddon')) {

    /** Singleton ************************************/
    final class LocoAutoTranslateAddon
    {

        /**
         * @var LocoAutoTranslateAddon The one true LocoAutoTranslateAddon
         */
        private static $instance;

        /**
         * Main LocoAutoTranslateAddon Instance.
         *
         * Insures that only one instance of LocoAutoTranslateAddon exists in memory at any one
         * time. Also prevents needing to define globals all over the place.
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();

                // register activation/deactivation hooks
                self::$instance->register();
            }

            return self::$instance;
        }

        /**
         * Constructor.
         */
        public function __construct()
        {

            // Initialize cron
            $this->init_cron();

            // Initialize feedback notice
            $this->init_feedback_notice();
            add_action( 'init', array( $this, 'atlt_register_ai_client' ) );
            // Add CPT Dashboard initialization
            if (! class_exists('Atlt_Dashboard')) {
                require_once ATLT_PATH . 'admin/cpt_dashboard/cpt_dashboard.php';
                Atlt_Dashboard::instance();
            }

            add_action('plugins_loaded', [$this, 'atlt_include_files']);
        }

        /**
         * Registers our plugin lifecycle hooks.
         */
        public static function register()
        {
            $thisPlugin = self::$instance;
            register_activation_hook(ATLT_FILE, [$thisPlugin, 'atlt_activate']);
            register_deactivation_hook(ATLT_FILE, [$thisPlugin, 'atlt_deactivate']);
        }

        /**
         * Initialize cron jobs for the plugin.
         *
         * @return void
         */
        public function init_cron()
        {
            require_once ATLT_PATH . '/admin/feedback/cron/atlt-cron.php';
            $cron = new ATLT_cronjob();
            $cron->atlt_cron_init_hooks();
        }

        /**
         * Initialize the telemetry/feedback request notice.
         *
         * @return void
         */
        public function init_feedback_notice()
        {
            if (is_admin()) {

                if (! class_exists('CPFM_Feedback_Notice')) {
                    require_once ATLT_PATH . '/admin/feedback/cpfm-common-notice.php';

                }

                add_action('cpfm_register_notice', function () {
                    if (! class_exists('CPFM_Feedback_Notice') || ! current_user_can('manage_options')) {
                        return;
                    }

                    $notice = [
                        'title'          => __('LocoAI – Auto Translate for Loco Translate', 'automatic-translator-addon-for-loco-translate'),
                        'message'        => __('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'automatic-translator-addon-for-loco-translate'),
                        'pages'          => ['loco-atlt-dashboard'],
                        'always_show_on' => ['loco-atlt-dashboard'], // This enables auto-show
                        'plugin_name'    => 'atlt',
                    ];
                    CPFM_Feedback_Notice::cpfm_register_notice('cool_translations', $notice);
                });

                add_action('cpfm_after_opt_in_atlt', function ($category) {
                    if ($category === 'cool_translations') {
                        ATLT_cronjob::atlt_send_data();
                        update_option('atlt_feedback_opt_in', 'yes');
                    }
                });
            }
        }

        /**
         * Include all modular service files.
         *
         * @return void
         */
        public function atlt_include_files()
        {
            require_once ATLT_PATH . 'includes/class-atlt-settings-service.php';
            require_once ATLT_PATH . 'includes/Feedback/class-feedback-form.php';
            new ATLT_FeedbackForm();

            // Load and initialize service classes
            require_once ATLT_PATH . 'includes/class-atlt-installer.php';
            ATLT_Plugin_Installer::init();

            require_once ATLT_PATH . 'includes/class-atlt-provider-settings.php';
            ATLT_Provider_Settings::init();

            require_once ATLT_PATH . 'includes/class-atlt-openai-translator.php';
            ATLT_OpenAI_Translator::init();

            require_once ATLT_PATH . 'includes/class-atlt-admin-notices.php';
            ATLT_Admin_Notices::init();

            require_once ATLT_PATH . 'includes/class-atlt-assets.php';
            ATLT_Assets::init();

            require_once ATLT_PATH . 'includes/class-atlt-loco-translator.php';
            ATLT_Loco_Translator::init();

            require_once ATLT_PATH . 'includes/class-atlt-admin-dashboard.php';
            ATLT_Admin_Dashboard::init();
            
            require_once ATLT_PATH . 'includes/class-atlt-addons-helper.php';
        }

        /**
         * Migrate existing legacy provider credentials to target WordPress settings keys.
         *
         * @return void
         */
        private function migrate_ai_credentials() {
            if ( ! class_exists( 'ATLT_Settings_Service' ) || ! ATLT_Settings_Service::is_wp_ai_client_exist() ) {
                return;
            }

            if ( get_option( 'atlt_ai_credentials_migrated_to_wp70' ) ) {
                return;
            }

            $credentials = get_option( 'wp_ai_client_provider_credentials', array() );
            if ( ! is_array( $credentials ) ) {
                $credentials = array();
            }

            $providers = array( 'openai' );
            foreach ( $providers as $provider ) {
                if ( empty( $credentials[ $provider ] ) || ! is_string( $credentials[ $provider ] ) ) {
                    continue;
                }

                $connector_key = 'connectors_ai_' . $provider . '_api_key';
                if ( empty( get_option( $connector_key, '' ) ) ) {
                    update_option( $connector_key, sanitize_text_field( $credentials[ $provider ] ), false );
                }

                unset( $credentials[ $provider ] );
            }

            if ( empty( $credentials ) ) {
                delete_option( 'wp_ai_client_provider_credentials' );
            } else {
                update_option( 'wp_ai_client_provider_credentials', $credentials, false );
            }

            update_option( 'atlt_ai_credentials_migrated_to_wp70', true );
        }

        /**
         * Register OpenAi provider dynamically with the WordPress AI Client.
         *
         * @return \WordPress\AiClient\Registry Registry instance.
         */
        private function register_ai_providers() {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( class_exists( 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider' )
				&& ! $registry->hasProvider( 'openai' )
			) {
				$registry->registerProvider( \WordPress\OpenAiAiProvider\Provider\OpenAiProvider::class );
			}
            return $registry;
        }

        /**
         * Register and configure the WP AI Client library/provider configurations.
         *
         * @return void
         */
        public function atlt_register_ai_client() {
            $is_wp70            = class_exists( 'ATLT_Settings_Service' ) && ATLT_Settings_Service::is_wp_ai_client_exist();
            $providers_autoload = ATLT_PATH . 'includes/wp-ai-providers/vendor/autoload.php';

            if ( ! $is_wp70 && ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
                $plugin_autoload = ATLT_PATH . 'vendor/wordpress/wp-ai-client/autoload.php';
                if ( file_exists( $plugin_autoload ) ) {
                    require_once $plugin_autoload;
                }

                if ( ! class_exists( \WordPress\AI_Client\AI_Client::class ) || ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
                    return;
                }
            }

            if ( $is_wp70 ) {
                $this->migrate_ai_credentials();
            }

            if ( file_exists( $providers_autoload ) ) {
                require_once $providers_autoload;
            }

            if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
                return;
            }

            $registry = $this->register_ai_providers();

            if ( ! $is_wp70 ) {
                \WordPress\AI_Client\AI_Client::init();

                try {
                    $http_transporter = \WordPress\AiClient\Providers\Http\HttpTransporterFactory::createTransporter();
                    $registry->setHttpTransporter( $http_transporter );
                } catch ( \Exception $e ) {
                }
            }
        }

        /**
         * Collects environment context, software versions, active themes, and plugins for usage reports.
         *
         * @return array Environment data telemetry details.
         */
        public static function atlt_get_user_info()
        {
            global $wpdb;
            // Server and WP environment details
            $server_info = [
                'server_software'        => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'N/A',
                'mysql_version'          => ($wpdb && method_exists($wpdb, 'db_version')) ? sanitize_text_field($wpdb->db_version()) : 'N/A',
                'php_version'            => sanitize_text_field(phpversion() ?: 'N/A'),
                'wp_version'             => sanitize_text_field(get_bloginfo('version') ?: 'N/A'),
                'wp_debug'               => (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled',
                'wp_memory_limit'        => sanitize_text_field(ini_get('memory_limit') ?: 'N/A'),
                'wp_max_upload_size'     => sanitize_text_field(ini_get('upload_max_filesize') ?: 'N/A'),
                'wp_permalink_structure' => sanitize_text_field(get_option('permalink_structure') ?: 'Default'),
                'wp_multisite'           => is_multisite() ? 'Enabled' : 'Disabled',
                'wp_language'            => sanitize_text_field(get_option('WPLANG') ?: get_locale()),
                'wp_prefix'              => isset($wpdb->prefix) ? sanitize_key($wpdb->prefix) : 'N/A',
            ];
            // Theme details
            $theme      = wp_get_theme();
            $theme_data = [
                'name'      => sanitize_text_field($theme->get('Name')),
                'version'   => sanitize_text_field($theme->get('Version')),
                'theme_uri' => esc_url($theme->get('ThemeURI')),
            ];
            // Ensure plugin functions are loaded
            if (! function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            // Active plugins details
            $all_plugins    = function_exists('get_plugins') ? get_plugins() : [];
            $active_plugins = get_option('active_plugins', []);
            $plugin_data    = [];
            foreach ($active_plugins as $plugin_path) {
                $plugin_path = sanitize_text_field($plugin_path);
                if (! isset($all_plugins[$plugin_path])) {
                    continue;
                }
                $plugin_info   = $all_plugins[$plugin_path];
                $author_url    = (isset($plugin_info['AuthorURI']) && ! empty($plugin_info['AuthorURI'])) ? esc_url($plugin_info['AuthorURI']) : 'N/A';
                $plugin_url    = (isset($plugin_info['PluginURI']) && ! empty($plugin_info['PluginURI'])) ? esc_url($plugin_info['PluginURI']) : '';
                $plugin_data[] = [
                    'name'       => sanitize_text_field($plugin_info['Name']),
                    'version'    => sanitize_text_field($plugin_info['Version']),
                    'plugin_uri' => ! empty($plugin_url) ? $plugin_url : $author_url,
                ];
            }
            return [
                'server_info'   => $server_info,
                'extra_details' => [
                    'wp_theme'       => $theme_data,
                    'active_plugins' => $plugin_data,
                ],
            ];
        }

        /**
         * Fire actions when the plugin is activated.
         *
         * @return void
         */
        public function atlt_activate()
        {

            $active_plugins = get_option('active_plugins', []);
            if (! in_array("loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php", $active_plugins)) {
                add_option('atlt_do_activation_redirect', true);
            }

            update_option('atlt-version', ATLT_VERSION);
            update_option('atlt-installDate', gmdate('Y-m-d H:i:s'));
            update_option('atlt-type', 'free');

            if (! get_option('atlt-install-date')) {
                add_option('atlt-install-date', gmdate('Y-m-d h:i:s'));
            }

            if (! get_option('atlt_initial_save_version')) {
                add_option('atlt_initial_save_version', ATLT_VERSION);
            }

            $get_opt_in = get_option('atlt_feedback_opt_in');

            if ($get_opt_in == 'yes' && ! wp_next_scheduled('atlt_extra_data_update')) {

                wp_schedule_event(time(), 'every_30_days', 'atlt_extra_data_update');
            }
        }

        /**
         * Clean up schedule hooks and properties on plugin deactivation.
         *
         * @return void
         */
        public function atlt_deactivate()
        {
            delete_option('atlt-version');
            delete_option('atlt-installDate');
            delete_option('atlt-type');

            wp_clear_scheduled_hook('atlt_extra_data_update');
        }

        /**
         * Throw error on object clone.
         */
        public function __clone()
        {
            _doing_it_wrong(__FUNCTION__, esc_html__('Cheatin&#8217; huh?', 'automatic-translator-addon-for-loco-translate'), '2.3');
        }

        /**
         * Disable unserializing of the class.
         */
        public function __wakeup()
        {
            _doing_it_wrong(__FUNCTION__, esc_html__('Cheatin&#8217; huh?', 'automatic-translator-addon-for-loco-translate'), '2.3');
        }

    }

    /**
     * Main instance helper to access plugin's singleton logic.
     *
     * @return LocoAutoTranslateAddon
     */
    function ATLT()
    {
        return LocoAutoTranslateAddon::get_instance();
    }
    ATLT();

}