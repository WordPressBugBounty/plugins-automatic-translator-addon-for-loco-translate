<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Settings_Service {
    public static function is_wp_ai_client_exist() {
        return function_exists( 'wp_ai_client_prompt' ) && function_exists( '_wp_register_default_connector_settings' );
    }

    public static function atlt_get_saved_openai_api_key() {
        $stored_credentials = get_option('wp_ai_client_provider_credentials', array());
        $stored_credentials = is_array($stored_credentials) ? $stored_credentials : array();

        if ( self::is_wp_ai_client_exist() ) {
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

    public static function atlt_mask_api_key($api_key) {
        $api_key = is_string($api_key) ? trim($api_key) : '';
        if ($api_key === '') {
            return '';
        }
        return str_repeat('*', 24) . substr($api_key, -4) . ' ✅';
    }

    public static function atlt_save_openai_api_key($openai_key) {
        $openai_key = is_string($openai_key) ? trim($openai_key) : '';

        if ( self::is_wp_ai_client_exist() ) {
            update_option('connectors_ai_openai_api_key', $openai_key, false);

            $legacy_credentials = get_option('wp_ai_client_provider_credentials', array());
            $legacy_credentials = is_array($legacy_credentials) ? $legacy_credentials : array();
            if (isset($legacy_credentials['openai'])) {
                unset($legacy_credentials['openai']);
            }

            if (! empty($legacy_credentials)) {
                update_option('wp_ai_client_provider_credentials', $legacy_credentials, false);
            } else {
                delete_option('wp_ai_client_provider_credentials');
            }
            return;
        }

        $credentials           = get_option('wp_ai_client_provider_credentials', array());
        $credentials           = is_array($credentials) ? $credentials : array();
        $credentials['openai'] = $openai_key;
        update_option('wp_ai_client_provider_credentials', $credentials, false);
        delete_option('connectors_ai_openai_api_key');
    }

    public static function atlt_delete_openai_api_key() {
        delete_option('connectors_ai_openai_api_key');

        $credentials = get_option('wp_ai_client_provider_credentials', array());
        $credentials = is_array($credentials) ? $credentials : array();
        if (isset($credentials['openai'])) {
            unset($credentials['openai']);
        }

        if (! empty($credentials)) {
            update_option('wp_ai_client_provider_credentials', $credentials, false);
        } else {
            delete_option('wp_ai_client_provider_credentials');
        }
    }

    public static function atlt_filtered_specific_models( $provider_id, $models ) {
        $provider_id = is_string( $provider_id ) ? strtolower( trim( $provider_id ) ) : '';
        $models      = is_array( $models ) ? $models : array();

        $preferred = array();
        if ( $provider_id === 'openai' ) {
            $preferred = array(
                'gpt-5.4'             => __( 'gpt-5.4 (Best Quality)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-5.4-pro'         => __( 'gpt-5.4-pro (Highest Accuracy)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-5.3-chat-latest' => __( 'gpt-5.3-chat-latest (Recommended)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-5.2'             => __( 'gpt-5.2 (Balanced)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-5-mini'          => __( 'gpt-5-mini (Fast)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-5-nano'          => __( 'gpt-5-nano (Cheapest)', 'automatic-translator-addon-for-loco-translate' ),
                'gpt-4o-mini'         => __( 'gpt-4o-mini (Fast & Cheap)', 'automatic-translator-addon-for-loco-translate' ),
            );
        }

        if ( empty( $preferred ) ) {
            return array();
        }

        if ( ! empty( $models ) ) {
            $preferred = array_intersect_key(
                $preferred,
                array_flip( array_values( $models ) )
            );
        }

        return $preferred;
    }

    public static function atlt_get_ai_model_list( $provider_id ) {
        $provider_id = is_string( $provider_id ) ? strtolower( trim( $provider_id ) ) : '';
        if ( $provider_id !== 'openai' ) {
            return array();
        }

        $cache_key = 'atlt_' . $provider_id . '_models';
        $models    = get_transient( $cache_key );

        if ( false !== $models && is_array( $models ) ) {
            return self::atlt_filtered_specific_models( $provider_id, $models );
        }

        try {
            if (
                class_exists( '\WordPress\AiClient\AiClient' ) &&
                class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) &&
                class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
            ) {
                $registry      = \WordPress\AiClient\AiClient::defaultRegistry();
                $requirements  = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
                    array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
                    array()
                );
                $models_meta   = $registry->findProviderModelsMetadataForSupport( $provider_id, $requirements );
                $models        = array_map(
                    static function ( $model ) {
                        /** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
                        return $model->getId();
                    },
                    is_array( $models_meta ) ? $models_meta : array()
                );

                $models = array_values(
                    array_filter(
                        array_map(
                            static function ( $id ) {
                                return is_string( $id ) ? trim( $id ) : '';
                            },
                            $models
                        )
                    )
                );

                set_transient( $cache_key, $models, 24 * HOUR_IN_SECONDS );

                return self::atlt_filtered_specific_models( $provider_id, $models );
            }
        } catch ( \Throwable $e ) {
            return array();
        }

        return array();
    }

    public static function atlt_get_ai_model_list_with_fallback( $provider_id, $has_saved_key = false ) {
        $models = self::atlt_get_ai_model_list( $provider_id );
        if ( $provider_id === 'openai' && $has_saved_key && empty( $models ) ) {
            $models = array(
                'gpt-4o-mini' => 'gpt-4o-mini',
            );
        }
        return $models;
    }

    private static function atlt_extract_openai_model_ids($model_metadata_list) {
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
        return $model_ids;
    }

    public static function atlt_validate_provider_api_key($provider_id, $api_key) {
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

        $lock_key = 'atlt_ai_test_lock_' . sanitize_key($provider_id . '_' . substr($api_key, -8));
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
                    'message' => __('Invalid API key. Please check your API key and try again.', 'automatic-translator-addon-for-loco-translate'),
                );
            }

            $model_metadata_directory = $provider_classname::modelMetadataDirectory();
            $model_metadata_list      = $model_metadata_directory->listModelMetadata();

            if ($provider_id === 'openai' && is_array($model_metadata_list)) {
                $model_ids = self::atlt_extract_openai_model_ids($model_metadata_list);
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
}
