<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Loco_Translator {

    /**
     * Initialize class hooks and register Loco Translate API and save hooks.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_filter('loco_api_providers', [$instance, 'atlt_register_api'], 10, 1);
        add_action('loco_api_ajax', [$instance, 'atlt_ajax_init'], 0, 0);
        add_action('wp_ajax_atlt_save_all_translations', [$instance, 'atlt_save_translations_handler']);
    }

    /**
     * Register Loco Translate custom translator API provider.
     *
     * @param array $apis Array of registered Loco Translate APIs.
     * @return array Updated array containing Loco Translate APIs.
     */
    public function atlt_register_api(array $apis)
    {
        $apis[] = [
            'id'   => 'loco_auto',
            'key'  => '122343',
            'url'  => 'https://locoaddon.com/',
            'name' => 'Automatic Translate Addon',
        ];
        return $apis;
    }

    /**
     * Sets up dynamic batch translation filter callback hooks depending on active Loco version.
     *
     * @return void
     */
    public function atlt_ajax_init()
    {
        if (version_compare(loco_plugin_version(), '2.7', '>=')) {
            add_filter('loco_api_translate_loco_auto', [$this, 'loco_auto_translator_process_batch'], 0, 4);
        } else {
            add_filter('loco_api_translate_loco_auto', [$this, 'loco_auto_translator_process_batch_legacy'], 0, 3);
        }
    }

    /**
     * Wraps and maps the legacy batch translation layout for compatibility in Loco versions < 2.7.
     *
     * @param array       $sources Array of raw source translation texts.
     * @param Loco_Locale $locale  Target translation locale.
     * @param array       $config  Configuration properties.
     * @return string[] Array of translated outputs.
     */
    public function loco_auto_translator_process_batch_legacy(array $sources, Loco_Locale $locale, array $config)
    {
        $items = [];
        foreach ($sources as $text) {
            $items[] = ['source' => $text];
        }
        return $this->loco_auto_translator_process_batch([], $items, $locale, $config);
    }

    /**
     * Translates input source string items utilizing the cached transient database mapping.
     *
     * @param array       $targets Existing array of translation targets.
     * @param array       $items   Items payload containing source keys.
     * @param Loco_Locale $locale  Target translation locale.
     * @param array       $config  Configuration properties.
     * @return string[] Translated outputs mapping target languages.
     * @throws Loco_error_Exception If no cached translations are found in transients.
     */
    public function loco_auto_translator_process_batch(array $targets, array $items, Loco_Locale $locale, array $config)
    {
        // Extract domain from the referrer URL
        $referer    = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $url_data   = $this->atlt_parse_query($referer);
        $domain     = isset($url_data['domain']) && ! empty($url_data['domain']) ? sanitize_text_field($url_data['domain']) : 'temp';
        $lang       = sanitize_text_field($locale->lang);
        $region     = sanitize_text_field($locale->region);
        $project_id = $domain . '-' . $lang . '-' . $region;
        $atlt_translation_transient = get_transient('loco_current_translation');
        if ($domain === 'temp' && ! empty($atlt_translation_transient)) {
            $project_id = ! empty($atlt_translation_transient) ? $atlt_translation_transient : 'temp';
        }

        // Combine transient parts if available
        $allStrings = [];

        $i = 0;
        $max_batches = 1000; // Sane upper bound
        while ($i < $max_batches) {
            $transient_data = get_transient($project_id . '-part-' . $i);

            if (empty($transient_data)) {
                break;
            }

            if (isset($transient_data['strings'])) {
                $allStrings = array_merge($allStrings, $transient_data['strings']);
            }
            $i++;
        }

        if (! empty($allStrings)) {
            // Build a source => target lookup map to avoid O(n*m) complexity in the loop
            $stringMap = [];
            foreach ($allStrings as $cachedString) {
                if (isset($cachedString['source'], $cachedString['target'])) {
                    $stringMap[$cachedString['source']] = $cachedString['target'];
                }
            }

            foreach ($items as $i => $item) {
                if (isset($item['source']) && isset($stringMap[$item['source']])) {
                    $targets[$i] = sanitize_text_field($stringMap[$item['source']]);
                } else {
                    $targets[$i] = '';
                }
            }

            return $targets;
        } else {
            throw new Loco_error_Exception('Please translate strings using the Auto Translate addon button first.');
        }
    }

    /**
     * Parse query variables out of a URL block.
     *
     * @param string $var The URL to parse.
     * @return array Extracted query variables.
     */
    public function atlt_parse_query($var)
    {
        if (empty($var) || ! is_string($var)) {
            return [];
        }

        $var = wp_parse_url($var, PHP_URL_QUERY);
        if (empty($var)) {
            return [];
        }
        $var = html_entity_decode((string) $var);
        $var = explode('&', $var);
        $arr = [];

        foreach ($var as $val) {
            $x = explode('=', $val);
            if (isset($x[1])) {
                $arr[sanitize_text_field($x[0])] = sanitize_text_field($x[1]);
            }
        }
        unset($val, $x, $var);
        return $arr;
    }

    /**
     * AJAX handler to save translations in WordPress transients.
     *
     * @return void
     */
    public function atlt_save_translations_handler()
    {
        check_ajax_referer('loco-addon-nonces', 'wpnonce');

        // Capability check to restrict access to admins
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        if (isset($_POST['data']) && ! empty($_POST['data']) && isset($_POST['part'])) {

            // Secure JSON deserialization with proper error handling
            $raw_data   = sanitize_textarea_field(wp_unslash($_POST['data']));
            $allStrings = json_decode($raw_data, true);

            // Validate JSON parsing for main data
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error([
                    'error'   => 'Invalid JSON data provided.',
                    'details' => 'Translation data must be valid JSON format.',
                ], 400);
            }

            // Secure JSON deserialization for translation metadata (optional field)
            $translationData = null;
            if (isset($_POST['translation_data']) && ! empty($_POST['translation_data'])) {
                $raw_translation_data = sanitize_textarea_field(wp_unslash($_POST['translation_data']));
                $translationData      = json_decode($raw_translation_data, true);

                // Validate JSON parsing for translation metadata
                if (json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error([
                        'error'   => 'Invalid translation metadata JSON.',
                        'details' => 'Translation metadata must be valid JSON format.',
                    ], 400);
                }
            }

            // Validate that decoded data is actually an array and not empty
            if (empty($allStrings) || ! is_array($allStrings)) {
                wp_send_json_error(['error' => 'No valid translation data found in the request. Unable to save translations.'], 400);
            }

            // Determine the project ID based on the loop value
            $incoming_project = isset($_POST['project-id']) ? sanitize_key(wp_unslash($_POST['project-id'])) : '';
            $incoming_part    = isset($_POST['part']) ? sanitize_text_field(wp_unslash($_POST['part'])) : '';
            if (! preg_match('/^\-part\-\d+$/', $incoming_part)) {
                wp_send_json_error('Invalid part', 400);
            }
            $projectId = $incoming_project . $incoming_part;

            $dataToStore = [
                'strings' => $allStrings,
            ];

            // Save the combined data in transient
            set_transient('loco_current_translation', $incoming_project, 5 * MINUTE_IN_SECONDS);
            $rs            = set_transient($projectId, $dataToStore, 5 * MINUTE_IN_SECONDS);
            $response_data = [
                'message'  => 'Translations successfully stored in the cache.',
                'response' => $rs == true ? 'saved' : 'cache already exists',
            ];

            if ($incoming_part === '-part-0') {
                // Safely extract and sanitize translation metadata
                $metadata = [
                    'translation_provider' => isset($translationData['translation_provider']) ? sanitize_text_field($translationData['translation_provider']) : 'yandex',
                    'time_taken'           => isset($translationData['time_taken']) ? absint($translationData['time_taken']) : 6,
                    'pluginORthemeName'    => isset($translationData['pluginORthemeName']) ? sanitize_text_field($translationData['pluginORthemeName']) : 'automatic-translator-addon-for-loco-translate',
                    'target_language'      => isset($translationData['target_language']) ? sanitize_text_field($translationData['target_language']) : 'hi_IN',
                    'total_characters'     => isset($translationData['total_characters']) ? absint($translationData['total_characters']) : 0,
                    'total_strings'        => isset($translationData['total_strings']) ? absint($translationData['total_strings']) : 0,
                ];

                if (current_user_can('manage_options') && class_exists('Atlt_Dashboard')) {
                    Atlt_Dashboard::store_options(
                        'atlt',
                        'plugins_themes',
                        'update',
                        [
                            'plugins_themes'   => $metadata['pluginORthemeName'],
                            'service_provider' => $metadata['translation_provider'],
                            'source_language'  => 'en',
                            'target_language'  => $metadata['target_language'],
                            'time_taken'       => $metadata['time_taken'],
                            'string_count'     => $metadata['total_strings'],
                            'character_count'  => $metadata['total_characters'],
                            'date_time'        => gmdate('Y-m-d H:i:s'),
                            'version_type'     => 'free',
                        ]
                    );
                }
            }
            wp_send_json_success($response_data);
        } else {
            // Security check failed or missing parameters
            wp_send_json_error(['error' => 'Invalid request. Missing required parameters.'], 400);
        }
    }
}
