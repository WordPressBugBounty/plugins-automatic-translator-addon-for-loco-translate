<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_OpenAI_Translator {

    /**
     * Initialize class hooks and register AJAX handler.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('wp_ajax_atlt_openai_ajax_handler', [$instance, 'atlt_openai_ajax_handler']);
    }

    /**
     * Build translation instructions prompt for OpenAI requests.
     *
     * @param string $locale_label   The display label of the target language.
     * @param string $encoded_source JSON-encoded source strings array.
     * @return string Compiled prompt.
     */
    private function build_openai_prompt($locale_label, $encoded_source) {
        return 'Instruction 1: [%s, %d, %S, %D, %s, %S, %d, %D, %س] These placeholders are special and should not be translated.' . "\n"
            . 'Instruction 2: Avoid repeating translations and skip any strings if necessary. If a string is skipped, maintain its original key. ' . "\n"
            . 'Instruction 3: The translation in the format of a JSON object with the keys being numeric values (matching the source keys), and the values being the translated strings' . "\n"
            . 'Instruction 4: Use "\\" to escape special characters like \" and " to ensure valid JSON format.' . "\n"
            . 'Instruction 5: Translate the provided JSON object into ' . $locale_label . ' language regardless of whether the values are the same. Ensure the JSON is well-formed and complete. Please ensure that the output follows the format: {"key(numeric value)": "(translations of the strings in ' . $locale_label . ' language)"}' . "\n"
            . 'Strings are: ' . $encoded_source;
    }

    /**
     * Parse and sanitize the text translation response from OpenAI.
     *
     * @param string $translated_text Raw translated output text block from OpenAI.
     * @return array|\WP_Error Array of sanitized translations or WP_Error on failure.
     */
    private function parse_openai_response($translated_text) {
        $clean_text = preg_replace('/(^```json\n|```$)/', '', (string) $translated_text);
        $decoded_data = json_decode((string) $clean_text, true);
        if (! is_array($decoded_data)) {
            return new \WP_Error('invalid_json', __('OpenAI returned invalid JSON output.', 'automatic-translator-addon-for-loco-translate'));
        }

        $sanitized_data = array();
        foreach ($decoded_data as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $sanitized_key = sanitize_key((string) $key);
            if ('' === $sanitized_key) {
                continue;
            }
            $sanitized_data[ $sanitized_key ] = sanitize_text_field((string) $value);
        }

        if (empty($sanitized_data)) {
            return new \WP_Error('empty_translation', __('OpenAI returned no usable translations.', 'automatic-translator-addon-for-loco-translate'));
        }

        return $sanitized_data;
    }

    /**
     * AJAX handler to process OpenAI translations.
     *
     * @return void
     */
    public function atlt_openai_ajax_handler() {
        try {
            check_ajax_referer('loco-addon-nonces', 'nonce');

            if (! current_user_can('manage_options')) {
                wp_send_json_error(__('Unauthorized request.', 'automatic-translator-addon-for-loco-translate'));
            }

            if (! isset($_POST['source_data']) || ! is_array($_POST['source_data'])) {
                wp_send_json_error(__('Invalid request payload.', 'automatic-translator-addon-for-loco-translate'));
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $source_data = wp_unslash($_POST['source_data']);
            if (! isset($source_data['source']) || ! is_array($source_data['source'])) {
                wp_send_json_error(__('Source strings are missing.', 'automatic-translator-addon-for-loco-translate'));
            }

            $source = array();
            foreach ($source_data['source'] as $key => $value) {
                $sanitized_key = sanitize_key((string) $key);
                $sanitized_value = sanitize_text_field((string) $value);
                if ($sanitized_key !== '' && $sanitized_value !== '') {
                    $source[$sanitized_key] = $sanitized_value;
                }
            }

            if (empty($source)) {
                wp_send_json_error(__('No valid source strings found.', 'automatic-translator-addon-for-loco-translate'));
            }

            $metadata = array(
                'batchIndex'   => 0,
                'requestIndex' => 0,
            );
            if (isset($_POST['metadata']) && is_array($_POST['metadata'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $request_metadata = wp_unslash($_POST['metadata']);
                if (isset($request_metadata['batchIndex'])) {
                    $metadata['batchIndex'] = max(0, absint($request_metadata['batchIndex']));
                }
                if (isset($request_metadata['requestIndex'])) {
                    $metadata['requestIndex'] = max(0, absint($request_metadata['requestIndex']));
                }
            }

            $locale_label = 'English';
            if (
                isset($source_data['locale']) &&
                is_array($source_data['locale']) &&
                isset($source_data['locale']['label'])
            ) {
                $locale_label = sanitize_text_field((string) $source_data['locale']['label']);
            }

            $selected_model = get_option('atlt_selected_openai_model', '');
            if (! is_string($selected_model) || trim($selected_model) === '') {
                $selected_model = 'gpt-4o-mini';
            }

            $encoded_source = wp_json_encode($source);
            if (false === $encoded_source) {
                wp_send_json_error(__('Unable to encode source strings.', 'automatic-translator-addon-for-loco-translate'));
            }
            
            $content = $this->build_openai_prompt($locale_label, $encoded_source);

            $translated_text = $this->atlt_generate_text_with_ai($content, 'openai', $selected_model, 120);
            if (is_wp_error($translated_text)) {
                wp_send_json_error($translated_text->get_error_message());
            }

            $sanitized_data = $this->parse_openai_response($translated_text);
            if (is_wp_error($sanitized_data)) {
                wp_send_json_error($sanitized_data->get_error_message());
            }

            wp_send_json_success(
                array(
                    'data'     => $sanitized_data,
                    'metadata' => $metadata,
                )
            );
        } catch (\Throwable $e) {
            wp_send_json_error(
                __('OpenAI translation failed.', 'automatic-translator-addon-for-loco-translate') . ' ' . sanitize_text_field($e->getMessage())
            );
        }
    }

    /**
     * Generate text using the configured WordPress AI Client and provider model.
     *
     * @param string $content        Prompt text content.
     * @param string $provider       Target provider slug (e.g. 'openai').
     * @param string $selected_model Target model slug (e.g. 'gpt-4o-mini').
     * @param int    $timeout        Request transport timeout seconds.
     * @return string|\WP_Error Generated text translation block or WP_Error on failure.
     */
    private function atlt_generate_text_with_ai($content, $provider, $selected_model, $timeout = 120) {
        if (! class_exists('\WordPress\AiClient\AiClient')) {
            return new \WP_Error('atlt_ai_client_missing', __('AI client is not available.', 'automatic-translator-addon-for-loco-translate'));
        }

        $timeout_filter = static function ($time) use ($timeout) {
            return (int) $timeout;
        };

        add_filter(
            'wp_ai_client_default_request_timeout',
            $timeout_filter,
            10,
            1
        );

        try {
            $is_anthropic = str_contains(strtolower((string) $provider), 'anthropic');
            $builder = null;

            /*
             * Prefer AiClient::prompt() across WP 6.9/7.x.
             * This avoids "RequestAuthenticationInterface instance not set" errors from mixed builders.
             */
            if (class_exists('\WordPress\AiClient\AiClient') && method_exists('\WordPress\AiClient\AiClient', 'prompt')) {
                $builder = \WordPress\AiClient\AiClient::prompt($content);
            } elseif (function_exists('wp_ai_client_prompt')) {
                $builder = wp_ai_client_prompt($content);
            } elseif (class_exists('\WordPress\AI_Client\AI_Client')) {
                if (! $is_anthropic && method_exists('\WordPress\AI_Client\AI_Client', 'prompt_with_wp_error')) {
                    $builder = \WordPress\AI_Client\AI_Client::prompt_with_wp_error($content);
                } else {
                    $builder = \WordPress\AI_Client\AI_Client::prompt($content);
                }
            }

            if (! is_object($builder)) {
                return new \WP_Error('atlt_prompt_builder_missing', __('Prompt builder is not available.', 'automatic-translator-addon-for-loco-translate'));
            }

            if (! $is_anthropic) {
                if (method_exists($builder, 'asJsonResponse')) {
                    $builder = $builder->asJsonResponse();
                } elseif (method_exists($builder, 'as_json_response')) {
                    $builder = $builder->as_json_response();
                }
            }

            if (method_exists($builder, 'usingProvider')) {
                $builder->usingProvider($provider);
            } elseif (method_exists($builder, 'using_provider')) {
                $builder->using_provider($provider);
            }

            // Explicitly set HTTP timeouts on the prompt to avoid default 5s transport timeout.
            $request_options_class = '\WordPress\AiClient\Providers\Http\DTO\RequestOptions';
            if (class_exists($request_options_class)) {
                $request_options = new $request_options_class();
                if (is_object($request_options)) {
                    if (method_exists($request_options, 'setTimeout')) {
                        $request_options->setTimeout((float) $timeout);
                    }
                    if (method_exists($request_options, 'setConnectTimeout')) {
                        $request_options->setConnectTimeout((float) min(20, max(5, (int) $timeout)));
                    }

                    if (method_exists($builder, 'usingRequestOptions')) {
                        $builder->usingRequestOptions($request_options);
                    } elseif (method_exists($builder, 'using_request_options')) {
                        $builder->using_request_options($request_options);
                    }
                }
            }

            if (! empty($selected_model) && is_string($selected_model)) {
                if (method_exists($builder, 'usingModelPreference')) {
                    // Let the prompt builder resolve/bind authenticated model internally.
                    $builder->usingModelPreference(array($provider, $selected_model));
                } elseif (method_exists($builder, 'using_model_preference')) {
                    $builder->using_model_preference(array($provider, $selected_model));
                } else {
                    $registry = \WordPress\AiClient\AiClient::defaultRegistry();
                    if (is_object($registry) && method_exists($registry, 'getProviderModel')) {
                        $model = $registry->getProviderModel($provider, $selected_model);
                        if (is_object($model)) {
                            if (method_exists($builder, 'usingModel')) {
                                $builder->usingModel($model);
                            } elseif (method_exists($builder, 'using_model')) {
                                $builder->using_model($model);
                            }
                        }
                    }
                }
            }

            if (method_exists($builder, 'generateText')) {
                $text = $builder->generateText();
            } else {
                $text = $builder->generate_text();
            }
            if (is_wp_error($text)) {
                return $text;
            }

            return is_string($text) ? $text : '';
        } catch (\Throwable $e) {
            return new \WP_Error(
                'atlt_openai_translate_error',
                __('Error during OpenAI translation.', 'automatic-translator-addon-for-loco-translate') . ' ' . sanitize_text_field($e->getMessage())
            );
        } finally {
            remove_filter('wp_ai_client_default_request_timeout', $timeout_filter, 10);
        }
    }
}
