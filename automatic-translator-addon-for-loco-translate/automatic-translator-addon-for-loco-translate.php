<?php
/*
Plugin Name: LocoAI – Auto Translate for Loco Translate
Description: Auto translation addon for Loco Translate – translate plugin & theme strings using Yandex Translate.
Version: 2.5.2
License: GPL2
Text Domain: loco-auto-translate
Domain Path: languages
Author: Cool Plugins
Author URI: https://coolplugins.net/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'ATLT_FILE', __FILE__ );
define( 'ATLT_URL', plugin_dir_url( ATLT_FILE ) );
define( 'ATLT_PATH', plugin_dir_path( ATLT_FILE ) );
define( 'ATLT_VERSION', '2.5.2' );
!defined('ATLT_FEEDBACK_API') && define('ATLT_FEEDBACK_API',"https://feedback.coolplugins.net/");

/**
 * @package LocoAI – Auto Translate for Loco Translate
 * @version 2.5.1
 */

if ( ! class_exists( 'LocoAutoTranslateAddon' ) ) {

	/** Singleton ************************************/
	final class LocoAutoTranslateAddon {

		/**
		 * The unique instance of the plugin.
		 *
		 * @var LocoAutoTranslateAddon
		 */
		private static $instance;

		/**
		 * Gets an instance of plugin.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();

				// register all hooks
				self::$instance->register();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {

			// Initialize cron
			$this->init_cron();

			// Initialize feedback notice
			$this->init_feedback_notice();

			// Add CPT Dashboard initialization
			if (!class_exists('Atlt_Dashboard')) {
				require_once ATLT_PATH . 'admin/cpt_dashboard/cpt_dashboard.php';
				$dashboard = Atlt_Dashboard::instance();
			}

		}

		/**
		 * Registers our plugin with WordPress.
		 */
		public static function register() {
			$thisPlugin = self::$instance;
			register_activation_hook( ATLT_FILE, array( $thisPlugin, 'atlt_activate' ) );
			register_deactivation_hook( ATLT_FILE, array( $thisPlugin, 'atlt_deactivate' ) );

			add_action('admin_init', array($thisPlugin, 'atlt_do_activation_redirect'));

			// run actions and filter only at admin end.
			if ( is_admin() ) {
				add_action( 'plugins_loaded', array( $thisPlugin, 'atlt_check_required_loco_plugin' ) );
				add_action( 'init', array( $thisPlugin, 'atlt_load_textdomain' ) );
				// add notice to use latest loco translate addon
				add_action( 'init', array( $thisPlugin, 'atlt_verify_loco_version' ) );

				add_action( 'init', array( $thisPlugin, 'onInit' ) );

				/*** Plugin Setting Page Link inside All Plugins List */
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $thisPlugin, 'atlt_settings_page_link' ) );

				add_filter('plugin_row_meta', array( $thisPlugin,'atlt_add_docs_link_to_plugin_meta'), 10, 2);

				add_action( 'init', array( $thisPlugin, 'updateSettings' ) );

				add_action( 'plugins_loaded', array( $thisPlugin, 'atlt_include_files' ) );

				add_action( 'admin_enqueue_scripts', array( $thisPlugin, 'atlt_enqueue_scripts' ) );

				// Add the action to hide unrelated notices
				$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
				if ( $page === 'loco-atlt-dashboard' ){
					add_action('admin_print_scripts', array($thisPlugin, 'atlt_hide_unrelated_notices'));
				}

				/* since version 2.1 */
				add_filter( 'loco_api_providers', array( $thisPlugin, 'atlt_register_api' ), 10, 1 );
				add_action( 'loco_api_ajax', array( $thisPlugin, 'atlt_ajax_init' ), 0, 0 );
				add_action( 'wp_ajax_save_all_translations', array( $thisPlugin, 'atlt_save_translations_handler' ) );

				/*
				since version 2.0
				Yandex translate widget integration
				*/
				// add no translate attribute in html tag
				$req_action = isset($_REQUEST['action']) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
				if ( $req_action === 'file-edit' ) {
					add_action( 'admin_footer', array( $thisPlugin, 'atlt_load_ytranslate_scripts' ), 100 );
					add_filter( 'admin_body_class', array( $thisPlugin, 'atlt_add_custom_class' ) );
				}

				add_action( 'admin_menu', array( $thisPlugin, 'atlt_add_locotranslate_sub_menu' ), 101 );
			}
		}

		public function atlt_add_docs_link_to_plugin_meta($links, $file) {
			if (plugin_basename(__FILE__) === $file) {
				$docs_link = '<a href="' . esc_url( 'https://locoaddon.com/docs/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=plugins_list' ) . '" target="_blank" rel="noopener noreferrer">Docs</a>';
				$links[] = $docs_link;
			}
			return $links;
		}

		/*
		|----------------------------------------------------------------------
		| Register API Manager inside Loco Translate Plugin
		|----------------------------------------------------------------------
		*/
		function atlt_register_api( array $apis ) {
			$apis[] = array(
				'id'   => 'loco_auto',
				'key'  => '122343',
				'url'  => 'https://locoaddon.com/',
				'name' => 'Automatic Translate Addon',
			);
			return $apis;
		}

		/*
		|----------------------------------------------------------------------
		| Initialize cron
		|----------------------------------------------------------------------
		*/
		public function init_cron(){
			require_once ATLT_PATH . '/admin/feedback/cron/atlt-cron.php';
			$cron = new ATLT_cronjob();
			$cron->atlt_cron_init_hooks();
		}

		/*
		|----------------------------------------------------------------------
		| Initialize feedback notice
		|----------------------------------------------------------------------
		*/
		public function init_feedback_notice() {
			if (is_admin()) {

				if(!class_exists('CPFM_Feedback_Notice')){
					require_once ATLT_PATH . '/admin/feedback/cpfm-common-notice.php';
					
				}

			add_action('cpfm_register_notice', function () {
                if (!class_exists('CPFM_Feedback_Notice') || !current_user_can('manage_options')) {
                    return;
                }
				
                $notice = [
                    'title' => __('LocoAI – Auto Translate for Loco Translate', 'loco-auto-translate'),
                    'message' => __('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'loco-auto-translate'),
                    'pages' => ['loco-atlt-dashboard'],
                    'always_show_on' => ['loco-atlt-dashboard'], // This enables auto-show
                    'plugin_name'=>'atlt'
                ];
                CPFM_Feedback_Notice::cpfm_register_notice('cool_translations', $notice);
                    if (!isset($GLOBALS['cool_plugins_feedback'])) {
                        $GLOBALS['cool_plugins_feedback'] = [];
                    }
                    $GLOBALS['cool_plugins_feedback']['cool_translations'][] = $notice;
            });

            add_action('cpfm_after_opt_in_atlt', function($category) {
                if ($category === 'cool_translations') {
                    ATLT_cronjob::atlt_send_data();
					$options = get_option('atlt_feedback_opt_in');
					$options = 'yes';
					update_option('atlt_feedback_opt_in', $options);	
                }
            });
			}
		}
		
			

		/*
		|----------------------------------------------------------------------
		| Auto Translate Request handler
		|----------------------------------------------------------------------
		*/
		function atlt_ajax_init() {
			if( version_compare( loco_plugin_version(), '2.7', '>=' ) ){
				add_filter( 'loco_api_translate_loco_auto', array( self::$instance, 'loco_auto_translator_process_batch' ), 0, 4 );
			}
			else {
				add_filter('loco_api_translate_loco_auto',array( self::$instance, 'loco_auto_translator_process_batch_legacy' ), 0,3);
			}
		}
		
		public function loco_auto_translator_process_batch_legacy( array $sources, Loco_Locale $locale, array $config ) {
			$items = [];
			foreach( $sources as $text ){
				$items[] = [ 'source' => $text ];
			}
			return $this->loco_auto_translator_process_batch( [], $items, $locale, $config );
		}

		/**
		 * Hook fired as a filter for the "loco_auto" translation api
		 *
		 * @param string[] input strings
		 * @param Loco_Locale target locale for translations
		 * @param array our own api configuration
		 * @return string[] output strings
		 */
		function loco_auto_translator_process_batch(array $targets, array $items, Loco_Locale $locale, array $config) {
			// Extract domain from the referrer URL
			$referer    = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
			$url_data   = self::$instance->atlt_parse_query( $referer );
			$domain     = isset( $url_data['domain'] ) && ! empty( $url_data['domain'] ) ? sanitize_text_field( $url_data['domain'] ) : 'temp';
			$lang       = sanitize_text_field( $locale->lang );
			$region     = sanitize_text_field( $locale->region );
			$project_id = $domain . '-' . $lang . '-' . $region;
			if($domain === 'temp' && !empty(get_transient('loco_current_translation'))){
				$project_id = !empty(get_transient('loco_current_translation'))?get_transient('loco_current_translation'):'temp';
			}
			
			
			
			// Combine transient parts if available
			$allStrings = array();
			
			for ( $i = 0; $i <= 4; $i++ ) {
				$transient_data = get_transient( $project_id . '-part-' . $i );
				
				if ( ! empty( $transient_data ) ) {
					if (isset( $transient_data['strings'] )) {
						$allStrings = array_merge( $allStrings, $transient_data['strings'] );
					}
				}

			}

			if ( ! empty( $allStrings ) ) {
				foreach ( $items as $i => $item ) {
					// Find the index of the source string in the cached strings
					$index = array_search( $item['source'], array_column( $allStrings, 'source' ) );

					if (is_numeric($index) && isset($allStrings[$index]['target'])) {
						$targets[$i] = sanitize_text_field($allStrings[$index]['target']);
					} else {
						$targets[$i] = '';
					}
				}

				return $targets;
			} else {
				throw new Loco_error_Exception( 'Please translate strings using the Auto Translate addon button first.' );
			}
		}

		function atlt_parse_query( $var ) {
			/**
			 *  Use this function to parse out the query array element from
			 *  the output of parse_url().
			 */

			if ( empty( $var ) || ! is_string( $var ) ) {
				return array();
			}

			$var = parse_url( $var, PHP_URL_QUERY );
			$var = html_entity_decode( $var );
			$var = explode( '&', $var );
			$arr = array();

			foreach ( $var as $val ) {
				$x            = explode( '=', $val );
				if ( isset( $x[1] ) ) {
					$arr[ sanitize_text_field( $x[0] ) ] = sanitize_text_field( $x[1] );
				}
			}
			unset( $val, $x, $var );
			return $arr;
		}

		/*
		|----------------------------------------------------------------------
		| Save string translation inside cache for later use
		|----------------------------------------------------------------------
		*/
		// save translations inside transient cache for later use
		function atlt_save_translations_handler() {

			// Capability check to restrict access to admins
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized', 403 );
			}

			check_ajax_referer( 'loco-addon-nonces', 'wpnonce' );

			if ( isset( $_POST['data'] ) && ! empty( $_POST['data'] ) && isset( $_POST['part'] ) ) {

				// Secure JSON deserialization with proper error handling
				$raw_data = wp_unslash( $_POST['data'] );
				$allStrings = json_decode( $raw_data, true );
				
				// Validate JSON parsing for main data
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					wp_send_json_error( array( 
						'error' => 'Invalid JSON data provided.', 
						'details' => 'Translation data must be valid JSON format.' 
					), 400 );
				}
				
				// Secure JSON deserialization for translation metadata (optional field)
				$translationData = null;
				if ( isset($_POST['translation_data']) && !empty($_POST['translation_data']) ) {
					$raw_translation_data = wp_unslash( $_POST['translation_data'] );
					$translationData = json_decode( $raw_translation_data, true );
					
					// Validate JSON parsing for translation metadata
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						wp_send_json_error( array( 
							'error' => 'Invalid translation metadata JSON.', 
							'details' => 'Translation metadata must be valid JSON format.' 
						), 400 );
					}
				}

				// Validate that decoded data is actually an array and not empty
				if ( empty( $allStrings ) || !is_array( $allStrings ) ) {
					wp_send_json_error( array( 'error' => 'No valid translation data found in the request. Unable to save translations.' ), 400 );
				}

				// Determine the project ID based on the loop value
				$incoming_project = isset($_POST['project-id']) ? sanitize_key( wp_unslash( $_POST['project-id'] ) ) : '';
				$incoming_part = isset($_POST['part']) ? sanitize_text_field( wp_unslash( $_POST['part'] ) ) : '';
				if ( ! preg_match( '/^\-part\-\d+$/', $incoming_part ) ) {
					wp_send_json_error( 'Invalid part', 400 );
				}
				$projectId = $incoming_project . $incoming_part;
				
				$dataToStore = array(
					'strings' => $allStrings,
				);

				// Save the combined data in transient
				set_transient('loco_current_translation', $incoming_project, 5 * MINUTE_IN_SECONDS);
				$rs = set_transient( $projectId, $dataToStore, 5 * MINUTE_IN_SECONDS );
				$response_data = array(
					'message'  => 'Translations successfully stored in the cache.',
					'response' => $rs == true ? 'saved' : 'cache already exists',
				);

				if ( $incoming_part === '-part-0') {
					// Safely extract and sanitize translation metadata
					$metadata = array(
						'translation_provider' => isset($translationData['translation_provider']) ? sanitize_text_field($translationData['translation_provider']) : 'yandex',
						'time_taken' => isset($translationData['time_taken']) ? absint($translationData['time_taken']) : 6,
						'pluginORthemeName' => isset($translationData['pluginORthemeName']) ? sanitize_text_field($translationData['pluginORthemeName']) : 'automatic-translator-addon-for-loco-translate',
						'target_language' => isset($translationData['target_language']) ? sanitize_text_field($translationData['target_language']) : 'hi_IN',
						'total_characters' => isset($translationData['total_characters']) ? absint($translationData['total_characters']) : 0,
						'total_strings' => isset($translationData['total_strings']) ? absint($translationData['total_strings']) : 0
					);

						if ( current_user_can( 'manage_options' ) && class_exists('Atlt_Dashboard') ) {
							Atlt_Dashboard::store_options(
								'atlt',
								'plugins_themes',
								'update',
								array(
									'plugins_themes' => $metadata['pluginORthemeName'],
									'service_provider' => $metadata['translation_provider'],
									'source_language' => 'en',
									'target_language' => $metadata['target_language'],
									'time_taken' => $metadata['time_taken'],
									'string_count' => $metadata['total_strings'],
									'character_count' => $metadata['total_characters'],
									'date_time' => date('Y-m-d H:i:s'),
									'version_type' => 'free'
								)
							);
						}
				}
				wp_send_json_success( $response_data );
			} else {
				// Security check failed or missing parameters
				wp_send_json_error( array( 'error' => 'Invalid request. Missing required parameters.' ), 400 );
			}
		}

		/*
		|----------------------------------------------------------------------
		| Yandex Translate Widget Integrations
		| add no translate attribute in html tag
		|----------------------------------------------------------------------
		*/
		function atlt_load_ytranslate_scripts() {
			$req_action = isset($_REQUEST['action']) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( $req_action === 'file-edit' ) {
				// Secure inline script using WordPress best practices
				wp_add_inline_script(
					'loco-translate-admin', 	
					'document.getElementsByTagName("html")[0].setAttribute("translate", "no");'
		            );
			}
		}
		// add no translate class in admin body to disable whole page translation
		function atlt_add_custom_class( $classes ) {
			return "$classes notranslate";
		}

		/*
		|----------------------------------------------------------------------
		| check if required "Loco Translate" plugin is active
		| also register the plugin text domain
		|----------------------------------------------------------------------
		*/

		public function atlt_load_textdomain() {
			// load language files
			load_plugin_textdomain( 'loco-auto-translate', false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}
		
		public function atlt_check_required_loco_plugin() {
			if ( ! function_exists( 'loco_plugin_self' ) ) {
				add_action( 'admin_notices', array( self::$instance, 'atlt_plugin_required_admin_notice' ) );
			}
		}
		/*
		|----------------------------------------------------------------------
		| Notice to 'Admin' if "Loco Translate" is not active
		|----------------------------------------------------------------------
		*/
		public function atlt_plugin_required_admin_notice() {
			if ( current_user_can( 'activate_plugins' ) ) {
				$url         = 'plugin-install.php?tab=plugin-information&plugin=loco-translate&TB_iframe=true';
				$title       = 'Loco Translate';
				$plugin_info = get_plugin_data( __FILE__, true, true );
				echo '<div class="error"><p>' .
				sprintf(
					__(
						'In order to use <strong>%1$s</strong> plugin, please install and activate the latest version  of <a href="%2$s" class="thickbox" title="%3$s">%4$s</a>',
						'automatic-translator-addon-for-loco-translate'
					),
					esc_attr( $plugin_info['Name'] ),
					esc_url( $url ),
					esc_attr( $title ),
					esc_attr( $title )
				) . '.</p></div>';

				 deactivate_plugins( __FILE__ );
			}
		}

				/*
		|------------------------------------------------------------------------
		|  Hide unrelated notices
		|------------------------------------------------------------------------
		*/

		public function atlt_hide_unrelated_notices()
			{ // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded
				$cfkef_pages = false;

				if ( isset($_GET['page']) ) {
					$page_param = sanitize_key( wp_unslash( $_GET['page'] ) );
					if ( $page_param === 'loco-atlt-dashboard' ) {
						$cfkef_pages = true;
					}
				}
				
				if ($cfkef_pages) {
					global $wp_filter;
					// Define rules to remove callbacks.
					$rules = [
						'user_admin_notices' => [], // remove all callbacks.
						'admin_notices'      => [],
						'all_admin_notices'  => [],
						'admin_footer'       => [
							'render_delayed_admin_notices', // remove this particular callback.
						],
					];
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

				add_action( 'admin_notices', [ $this, 'atlt_admin_notices' ], PHP_INT_MAX );
			}

			function atlt_admin_notices() {
				do_action( 'atlt_display_admin_notices' );
			}

			function atlt_display_admin_notices() {
				// Check if user has already rated
				$alreadyRated = get_option('atlt-already-rated') != false ? get_option('atlt-already-rated') : "no";

				// Only show review notice if user hasn't rated yet
				if ($alreadyRated != "yes") {
					//  Display review notice
					if (class_exists('Atlt_Dashboard') && !defined('ATLT_PRO_VERSION')) {
						Atlt_Dashboard::review_notice(
							'atlt', // Required
							'LocoAI – Auto Translate for Loco Translate', // Required
							'https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post', // Required
							ATLT_URL . '/assets/images/atlt-logo.png' // Optional
						);
					}
				}
			}


		/*
		|----------------------------------------------------------------------
		| create 'settings' link in plugins page
		|----------------------------------------------------------------------
		*/
		public function atlt_settings_page_link( $links ) {
			$links[] = '<a style="font-weight:bold" target="_blank" href="' . esc_url( 'https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=plugins_list' ) . '">Buy PRO</a>';
			$links[] = '<a style="font-weight:bold" href="' . esc_url( get_admin_url( null, 'admin.php?page=loco-atlt-dashboard&tab=dashboard' ) ) . '">Settings</a>';
			return $links;
		}

		/*
		|----------------------------------------------------------------------
		| Update and remove old review settings
		|----------------------------------------------------------------------
		*/
		public function updateSettings() {
			if ( get_option( 'atlt-ratingDiv' ) ) {
				update_option( 'atlt-already-rated', get_option( 'atlt-ratingDiv' ) );
				delete_option( 'atlt-ratingDiv' );
			}
		}

		/*
		|----------------------------------------------------------------------
		| check User Status
		|----------------------------------------------------------------------
		*/
		public function atlt_verify_loco_version() {
			if ( function_exists( 'loco_plugin_version' ) ) {
				$locoV = loco_plugin_version();
				if ( version_compare( $locoV, '2.4.0', '<' ) ) {
					add_action( 'admin_notices', array( self::$instance, 'use_loco_latest_version_notice' ) );
				}
			}
		}
		/*
		|----------------------------------------------------------------------
		| Notice to use latest version of Loco Translate plugin
		|----------------------------------------------------------------------
		*/
		public function use_loco_latest_version_notice() {
			if ( current_user_can( 'activate_plugins' ) ) {
				$url         = 'plugin-install.php?tab=plugin-information&plugin=loco-translate&TB_iframe=true';
				$title       = 'Loco Translate';
				$plugin_info = get_plugin_data( __FILE__, true, true );
				echo '<div class="error"><p>' .
				sprintf(
					__(
						'In order to use <strong>%1$s</strong> (version <strong>%2$s</strong>), Please update <a href="%3$s" class="thickbox" title="%4$s">%5$s</a> official plugin to a latest version (2.4.0 or upper)',
						'automatic-translator-addon-for-loco-translate'
					),
					esc_attr( $plugin_info['Name'] ),
					esc_attr( $plugin_info['Version'] ),
					esc_url( $url ),
					esc_attr( $title ),
					esc_attr( $title )
				) . '.</p></div>';
			}
		}

		/*
		|----------------------------------------------------------------------
		| required php files
		|----------------------------------------------------------------------
		*/
		public function atlt_include_files() {
			if ( is_admin() ) {
				require_once ATLT_PATH . 'includes/Helpers/Helpers.php';

				$this->atlt_display_admin_notices();

				require_once ATLT_PATH . 'includes/Feedback/class.feedback-form.php';
				new ATLT_FeedbackForm();
			}
		}

		static function atlt_get_user_info() {
			global $wpdb;
			// Server and WP environment details
			$server_info = [
				'server_software'        => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field($_SERVER['SERVER_SOFTWARE']) : 'N/A',
				'mysql_version'          => ($wpdb && method_exists($wpdb, 'db_version')) ? sanitize_text_field( $wpdb->db_version() ) : 'N/A',
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
			$theme = wp_get_theme();
			$theme_data = [
				'name'      => sanitize_text_field($theme->get('Name')),
				'version'   => sanitize_text_field($theme->get('Version')),
				'theme_uri' => esc_url($theme->get('ThemeURI')),
			];
			// Ensure plugin functions are loaded
			if ( ! function_exists('get_plugins') ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			// Active plugins details
			$all_plugins = function_exists('get_plugins') ? get_plugins() : [];
			$active_plugins = get_option('active_plugins', []);
			$plugin_data = [];
			foreach ( $active_plugins as $plugin_path ) {
				$plugin_path = sanitize_text_field( $plugin_path );
				if ( ! isset( $all_plugins[ $plugin_path ] ) ) {
					continue;
				}
				$plugin_info = $all_plugins[ $plugin_path ];
				$author_url = ( isset( $plugin_info['AuthorURI'] ) && !empty( $plugin_info['AuthorURI'] ) ) ? esc_url( $plugin_info['AuthorURI'] ) : 'N/A';
				$plugin_url = ( isset( $plugin_info['PluginURI'] ) && !empty( $plugin_info['PluginURI'] ) ) ? esc_url( $plugin_info['PluginURI'] ) : '';
				$plugin_data[] = [
					'name'       => sanitize_text_field( $plugin_info['Name'] ),
					'version'    => sanitize_text_field( $plugin_info['Version'] ),
					'plugin_uri' => ! empty( $plugin_url ) ? $plugin_url : $author_url,
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

		/*
		|------------------------------------------------------------------------
		|  Enqueue required JS file
		|------------------------------------------------------------------------
		*/
		function atlt_enqueue_scripts($hook) {
			// Load assets for the dashboard page
			$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
			if ( $page === 'loco-atlt-dashboard') {
				wp_enqueue_style(
					'atlt-dashboard-style',
					ATLT_URL . 'admin/atlt-dashboard/css/admin-styles.css',
					array(),
					ATLT_VERSION,
					'all'
				);
			}

			if ( $page === 'loco-atlt-dashboard') {
				wp_enqueue_script(
					'atlt-dashboard-script',
					ATLT_URL . 'admin/atlt-dashboard/js/atlt-data-share-setting.js',
					array('jquery'),
					ATLT_VERSION,
					true
				);
			}
			// Keep existing editor page scripts
			$req_action = isset($_REQUEST['action']) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( $req_action === 'file-edit') {
				wp_register_script( 'loco-addon-custom', ATLT_URL . 'assets/js/custom.min.js', array( 'loco-translate-admin' ), ATLT_VERSION, true );
				wp_register_style(
					'loco-addon-custom-css',
					ATLT_URL . 'assets/css/custom.min.css',
					null,
					ATLT_VERSION,
					'all'
				);
				// load yandex widget
				wp_register_script( 'atlt-yandex-widget', ATLT_URL . 'assets/js/widget.js?widgetId=ytWidget&pageLang=en&widgetTheme=light&autoMode=false', array( 'loco-translate-admin' ), ATLT_VERSION, true );

				wp_enqueue_script( 'loco-addon-custom' );
				wp_enqueue_script( 'atlt-yandex-widget' );
				wp_enqueue_style( 'loco-addon-custom-css' );

				$extraData['ajax_url']        = admin_url( 'admin-ajax.php' );
				$extraData['nonce']           = wp_create_nonce( 'loco-addon-nonces' );
				$extraData['ATLT_URL']        = ATLT_URL;
				$extraData['preloader_path']  = 'preloader.gif';
				$extraData['gt_preview']      = 'google.png';
				$extraData['dpl_preview']     = 'deepl.png';
				$extraData['yt_preview']      = 'yandex.png';
				$extraData['chatGPT_preview'] = 'chatgpt.png';
				$extraData['geminiAI_preview']= 'gemini.png';
				$extraData['chromeAi_preview']      = 'chrome.png';
				$extraData['document_preview'] = 'document.svg';
				$extraData['openai_preview']    = 'openai.png';
				$extraData['error_preview']    = 'error-icon.svg';
				$extraData['extra_class']     = is_rtl() ? 'atlt-rtl' : '';

				$extraData['loco_settings_url'] = admin_url( 'admin.php?page=loco-config&action=apis' );

				wp_localize_script( 'loco-addon-custom', 'extradata', $extraData );
				// copy object
				wp_add_inline_script(
					'loco-translate-admin',
					'
            var returnedTarget = JSON.parse(JSON.stringify(window.loco));
            window.locoConf=returnedTarget;'
		            );

			}
		}

		/*
		|------------------------------------------------------
		|   show message if PRO has already active
		|------------------------------------------------------
		*/
		public function onInit() {
			if ( in_array(
				'loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php',
				apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
			) ) {

				if ( get_option( 'atlt-pro-version' ) != false &&
				  version_compare( get_option( 'atlt-pro-version' ), '1.4', '<' ) ) {

					  add_action( 'admin_notices', array( self::$instance, 'atlt_use_pro_latest_version' ) );
				} else {
					add_action( 'admin_notices', array( self::$instance, 'atlt_pro_already_active_notice' ) );
					return;
				}
			}
		}

		public function atlt_pro_already_active_notice() {
			echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p><strong>LocoAI – Auto Translate for Loco Translate (Pro)</strong> is already active so no need to activate free anymore.</p> </div>';
		}

		public function atlt_use_pro_latest_version() {
			echo '<div class="error loco-pro-missing" style="border:2px solid;border-color:#dc3232;"><p><strong>Please use <strong>LocoAI – Auto Translate for Loco Translate (Pro)</strong> latest version 1.4 or higher to use auto translate premium features.</p> </div>';
		}

		/*
		|------------------------------------------------------
		|    Plugin activation
		|------------------------------------------------------
		*/
		public function atlt_activate() {

			$active_plugins = get_option('active_plugins', array());
            if (!in_array("loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php", $active_plugins)) {
                add_option('atlt_do_activation_redirect', true);
            }

			update_option( 'atlt-version', ATLT_VERSION );
			update_option( 'atlt-installDate', gmdate( 'Y-m-d h:i:s' ) );
			update_option( 'atlt-type', 'free' );

			if(!get_option('atlt-install-date')) {
				add_option('atlt-install-date', gmdate('Y-m-d h:i:s'));
			}

			if (!get_option('atlt_initial_save_version')) {
				add_option('atlt_initial_save_version', ATLT_VERSION);
			}

			$get_opt_in = get_option('atlt_feedback_opt_in');

			if ($get_opt_in =='yes' && !wp_next_scheduled('atlt_extra_data_update')) {

				wp_schedule_event(time(), 'every_30_days', 'atlt_extra_data_update');
			}
			
		}

		/*
		|-------------------------------------------------------
		|    Redirect to plugin page after activation
		|-------------------------------------------------------
		*/
		public function atlt_do_activation_redirect() {
			if (get_option('atlt_do_activation_redirect', false)) {
				// Only redirect if not part of a bulk activation
				if (!isset($_GET['activate-multi'])) {
		
					// Check if required Loco Translate plugin is active (or required function exists)
					if (function_exists('loco_plugin_self')) {
						update_option('atlt_do_activation_redirect', false);
						wp_safe_redirect(admin_url('admin.php?page=loco-atlt-dashboard'));
						exit;
					}
				}
			}
			if(!get_option('atlt-install-date')) {
				add_option('atlt-install-date', gmdate('Y-m-d h:i:s'));
			}

			if (!get_option('atlt_initial_save_version')) {
				add_option('atlt_initial_save_version', ATLT_VERSION);
			}
		}	

		/*
		|-------------------------------------------------------
		|    Plugin deactivation
		|-------------------------------------------------------
		*/
		public function atlt_deactivate() {
			delete_option( 'atlt-version' );
			delete_option( 'atlt-installDate' );
			delete_option( 'atlt-type' );

			wp_clear_scheduled_hook('atlt_extra_data_update');
		}

		/*
		|-------------------------------------------------------
		|   LocoAI – Auto Translate for Loco Translate  admin page
		|-------------------------------------------------------
		*/
		function atlt_add_locotranslate_sub_menu() {
			// Only add submenu if Pro is NOT active
			if ( defined('ATLT_PRO_VERSION') ) {
				return;
			}
			add_submenu_page(
				'loco',
				'Loco Automatic Translate',
				'LocoAI',
				'manage_options',
				'loco-atlt-dashboard',
				array( self::$instance, 'atlt_dashboard_page' )
			);
		}


	/**
 * Render the dashboard page with dynamic text domain support
 * 
 * @param string $text_domain The text domain for translations (default: 'loco-auto-translate')
 */
	function atlt_dashboard_page() {

		$text_domain = 'loco-auto-translate';
		$file_prefix = 'admin/atlt-dashboard/views/';
		
		$valid_tabs = [
			'dashboard'       => __('Dashboard', $text_domain),
			'ai-translations' => __('AI Translations', $text_domain),
			'settings'        => __('Settings', $text_domain),
			'license'         => __('License', $text_domain),
			'free-vs-pro'     => __('Free vs Pro', $text_domain)
		];

		// Get current tab with fallback

		$tab 			= isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
		$current_tab 	= array_key_exists($tab, $valid_tabs) ? $tab : 'dashboard';
		
		// Action buttons configuration
		$buttons = [
			[
				'url'  => 'https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=dashboard_header',
				'img'  => 'upgrade-now.svg',
				'alt'  => __('premium', $text_domain),
				'text' => __('Unlock Pro Features', $text_domain)
			],
			[
				'url' => 'https://locoaddon.com/docs/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_header',
				'img' => 'document.svg',
				'alt' => __('document', $text_domain)
			],
			[
				'url' => 'https://locoaddon.com/support/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_header',
				'img' => 'contact.svg',
				'alt' => __('contact', $text_domain)
			]
		];

		// Start HTML output
		?>
		<div class="atlt-dashboard-wrapper">
			<div class="atlt-dashboard-header">
				<div class="atlt-dashboard-header-left">
					<img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/loco-addon-logo.svg'); ?>" 
						alt="<?php esc_attr_e('Loco Translate Logo', $text_domain); ?>">
					<div class="atlt-dashboard-tab-title">
						<span>↳</span> <?php echo esc_html($valid_tabs[$current_tab]); ?>
					</div>
				</div>
				<div class="atlt-dashboard-header-right">
					<span><?php esc_html_e('Auto translate plugins & themes.', $text_domain); ?></span>
					<?php foreach ($buttons as $button): ?>
						<a href="<?php echo esc_url($button['url']); ?>" 
						class="atlt-dashboard-btn" 
						target="_blank"
						aria-label="<?php echo isset($button['alt']) ? esc_attr($button['alt']) : ''; ?>">
							<img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/' . $button['img']); ?>" 
								alt="<?php echo esc_attr($button['alt']); ?>">
							<?php if (isset($button['text'])): ?>
								<span><?php echo esc_html($button['text']); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Dashboard navigation', $text_domain); ?>">
				<?php foreach ($valid_tabs as $tab_key => $tab_title): ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=loco-atlt-dashboard&tab=' . $tab_key ) ); ?>" 
					class="nav-tab <?php echo esc_attr($tab === $tab_key ? 'nav-tab-active' : ''); ?>">
						<?php echo esc_html($tab_title); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			
			<div class="tab-content">
				<?php
				// Secure file inclusion with strict whitelist validation
				$allowed_templates = [
					'dashboard'       => 'dashboard.php',
					'ai-translations' => 'ai-translations.php',
					'settings'        => 'settings.php',
					'license'         => 'license.php',
					'free-vs-pro'     => 'free-vs-pro.php'
				];
				
				// Double validation: check if current_tab exists in allowed templates
				if ( !array_key_exists( $current_tab, $allowed_templates ) ) {
					$current_tab = 'dashboard'; // Fallback to safe default
				}
				
				$template_filename = $allowed_templates[ $current_tab ];
				$template_file = ATLT_PATH . $file_prefix . $template_filename;
				
				// Additional security: ensure the resolved path is within the expected directory
				$real_template_path = realpath( $template_file );
				$expected_base_path = realpath( ATLT_PATH . $file_prefix );
				
				if ( $real_template_path && $expected_base_path && 
					 strpos( $real_template_path, $expected_base_path ) === 0 && 
					 file_exists( $template_file ) ) {
					require_once $template_file;
				} else {
					// Fallback to dashboard if template validation fails
					$fallback_template = ATLT_PATH . $file_prefix . 'dashboard.php';
					if ( file_exists( $fallback_template ) ) {
						require_once $fallback_template;
					}
				}
				
				// Include sidebar (with same security validation)
				$sidebar_file = ATLT_PATH . $file_prefix . 'sidebar.php';
				$real_sidebar_path = realpath( $sidebar_file );
				if ( $real_sidebar_path && $expected_base_path && 
					 strpos( $real_sidebar_path, $expected_base_path ) === 0 && 
					 file_exists( $sidebar_file ) ) {
					require_once $sidebar_file;
				}
				
				?>
			</div>
			
			<?php 
			// Secure footer inclusion
			$footer_file = ATLT_PATH . $file_prefix . 'footer.php';
			$real_footer_path = realpath( $footer_file );
			if ( $real_footer_path && $expected_base_path && 
				 strpos( $real_footer_path, $expected_base_path ) === 0 && 
				 file_exists( $footer_file ) ) {
				require_once $footer_file;
			}
			?>
		</div>
		<?php
	}

		/**
		 * Throw error on object clone.
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'loco-auto-translate' ), '2.3' );
		}

		/**
		 * Disable unserializing of the class.
		 */
		public function __wakeup() {
			// Unserializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'loco-auto-translate' ), '2.3' );
		}

	}

	function ATLT() {
		return LocoAutoTranslateAddon::get_instance();
	}
	ATLT();

}

