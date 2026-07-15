<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ATLT_Addons_Helper {

    public static function format_time_taken($time_taken) {
        if ($time_taken === 0) 
            return __('0', 'automatic-translator-addon-for-loco-translate');
        
        if ($time_taken < 60) {
            /* translators: %d: number of seconds */ 
            return sprintf(__('%d sec', 'automatic-translator-addon-for-loco-translate'), $time_taken);
        }
        if ($time_taken < 3600) {
            $min = floor($time_taken / 60);
            $sec = $time_taken % 60;
            
            /* translators: 1: number of minutes, 2: number of seconds */ 
            return sprintf(__('%1$d min %2$d sec', 'automatic-translator-addon-for-loco-translate'), $min, $sec);
        }
        $hours = floor($time_taken / 3600);
        $min = floor(($time_taken % 3600) / 60);
        
        /* translators: 1: number of hours, 2: number of minutes */ 
        return sprintf(__('%1$d hours %2$d min', 'automatic-translator-addon-for-loco-translate'), $hours, $min);
    }

    public static function is_plugin_installed( $plugin_slug, $variant = 'any', $plugins = [] ) {

        $addons = self::get_addon_definitions();
        $plugin_files = [];
        foreach ($addons as $addon) {
            $plugin_files[$addon['slug_free']] = [
                'free' => [ $addon['plugin_file_free'] ],
                'pro'  => [ $addon['plugin_file_pro'] ],
            ];
            if ( ! empty( $addon['display_slug'] ) && $addon['display_slug'] !== $addon['slug_free'] ) {
                 $plugin_files[$addon['display_slug']] = [
                     'free' => [ $addon['plugin_file_free'] ],
                     'pro'  => [ $addon['plugin_file_pro'] ],
                 ];
            }
            $plugin_files[$addon['slug_pro']] = [
                'pro' => [ $addon['plugin_file_pro'] ],
            ];
        }

        if ( isset( $plugin_files[ $plugin_slug ] ) ) {
            $variants = [];

            if ( 'pro' === $variant ) {
                $variants = $plugin_files[ $plugin_slug ]['pro'] ?? [];
            } elseif ( 'free' === $variant ) {
                $variants = $plugin_files[ $plugin_slug ]['free'] ?? [];
            } else {
                $variants = array_merge(
                    $plugin_files[ $plugin_slug ]['free'] ?? [],
                    $plugin_files[ $plugin_slug ]['pro'] ?? []
                );
            }

            foreach ( $variants as $plugin_file ) {
                if ( isset( $plugins[ $plugin_file ] ) ) {
                    return true;
                }

                $plugin_dir = trailingslashit( dirname( $plugin_file ) );
                foreach ( array_keys( $plugins ) as $candidate_file ) {
                    if ( 0 === strpos( (string) $candidate_file, $plugin_dir ) ) {
                        return true;
                    }
                }
            }

            return false;
        }

        // Generic: check any installed plugin file under "<slug>/".
        foreach ( array_keys( $plugins ) as $plugin_file ) {
            if ( strpos( $plugin_file, $plugin_slug . '/' ) === 0 ) {
                return true;
            }
        }

        return false;
    }

    public static function get_plugin_display_name($plugin_slug, $plugins = []) {

        $addons = self::get_addon_definitions();
        $plugin_paths = [];
        $atlt_pro_slug_keys = [];
        $atlt_slug_aliases = [];

        foreach ($addons as $addon) {
            $canonical = !empty($addon['display_slug']) ? $addon['display_slug'] : $addon['slug_free'];
            $plugin_paths[$canonical] = [
                'free'      => $addon['plugin_file_free'],
                'pro'       => $addon['plugin_file_pro'],
                'free_name' => $addon['free_name'] ?? '',
                'pro_name'  => $addon['pro_name'] ?? '',
            ];

            if (!empty($addon['slug_pro'])) {
                $atlt_pro_slug_keys[] = $addon['slug_pro'];
                $atlt_slug_aliases[$addon['slug_pro']] = $canonical;
            }
            if (!empty($addon['slug_free']) && $addon['slug_free'] !== $canonical) {
                $atlt_slug_aliases[$addon['slug_free']] = $canonical;
            }
        }

        $atlt_is_pro_slug   = in_array( $plugin_slug, $atlt_pro_slug_keys, true );
        $atlt_canonical_slug = $atlt_slug_aliases[ $plugin_slug ] ?? $plugin_slug;

        if ( $atlt_is_pro_slug && isset( $plugin_paths[ $atlt_canonical_slug ]['pro_name'] ) ) {
            return $plugin_paths[ $atlt_canonical_slug ]['pro_name'];
        }

        // If slug isn't mapped, try to read its real name from installed plugins
        if (!isset($plugin_paths[$atlt_canonical_slug])) {
            foreach ($plugins as $plugin_file => $plugin_data) {
                if (strpos($plugin_file, $plugin_slug . '/') === 0 && !empty($plugin_data['Name'])) {
                    return sanitize_text_field($plugin_data['Name']);
                }
            }
            return __('Unknown plugin', 'automatic-translator-addon-for-loco-translate');
        }

        $free_installed = isset($plugins[$plugin_paths[$atlt_canonical_slug]['free']]);
        $has_pro = isset($plugin_paths[$atlt_canonical_slug]['pro']);

        $pro_path = $has_pro ? (string) $plugin_paths[ $atlt_canonical_slug ]['pro'] : '';
        $pro_installed = ( '' !== $pro_path ) && isset( $plugins[ $pro_path ] );

        if ( ! $pro_installed && '' !== $pro_path ) {
            $pro_dir = trailingslashit( dirname( $pro_path ) );
            foreach ( array_keys( $plugins ) as $plugin_file ) {
                if ( 0 === strpos( (string) $plugin_file, $pro_dir ) ) {
                    $pro_installed = true;
                    $pro_path      = (string) $plugin_file;
                    break;
                }
            }
        }

        // Prefer showing the ACTIVE version (Pro > Free), otherwise fall back to installed (Pro > Free).
        $free_active = false;
        $pro_active  = false;
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( '' !== $pro_path ) {
            $pro_active = is_plugin_active( $pro_path );
        }
        if ( ! empty( $plugin_paths[ $atlt_canonical_slug ]['free'] ) ) {
            $free_active = is_plugin_active( $plugin_paths[ $atlt_canonical_slug ]['free'] );
        }

        if ( $pro_active && isset( $plugin_paths[ $atlt_canonical_slug ]['pro_name'] ) ) {
            return $plugin_paths[ $atlt_canonical_slug ]['pro_name'];
        }
        if ( $free_active && isset( $plugin_paths[ $atlt_canonical_slug ]['free_name'] ) ) {
            return $plugin_paths[ $atlt_canonical_slug ]['free_name'];
        }
        if ( $pro_installed && isset( $plugin_paths[ $atlt_canonical_slug ]['pro_name'] ) ) {
            return $plugin_paths[ $atlt_canonical_slug ]['pro_name'];
        }
        if ( $free_installed && isset( $plugin_paths[ $atlt_canonical_slug ]['free_name'] ) ) {
            return $plugin_paths[ $atlt_canonical_slug ]['free_name'];
        }
        
        return $plugin_paths[$atlt_canonical_slug]['free_name'] ?? __('Unknown plugin', 'automatic-translator-addon-for-loco-translate');
    }

    public static function format_number($number) {
        $formats = [
            1000000000 => __('B+', 'automatic-translator-addon-for-loco-translate'),
            1000000 => __('M+', 'automatic-translator-addon-for-loco-translate'),  
            1000 => __('K+', 'automatic-translator-addon-for-loco-translate')
        ];
        
        foreach ($formats as $threshold => $suffix) {
            if ($number >= $threshold) {
                return round($number / $threshold, 1) . $suffix;
            }
        }
        return $number;
    }

    public static function get_addon_definitions() {
        $addons = [
            [
                'slug_free'       => 'automatic-translations-for-polylang',
                'slug_pro'        => 'autopoly-ai-translation-for-polylang-pro',
                'plugin_file_free'=> 'automatic-translations-for-polylang/automatic-translation-for-polylang.php',
                'plugin_file_pro' => 'autopoly-ai-translation-for-polylang-pro/autopoly-ai-translation-for-polylang-pro.php',
                'desc'            => __( 'Translate your entire WordPress website faster than ever with AI-powered translation built for Polylang - Autopoly', 'automatic-translator-addon-for-loco-translate' ),
                'image'           => ATLT_URL . 'admin/atlt-dashboard/images/polylang-addon.png',
                'image_alt'       => __( 'Polylang Addon', 'automatic-translator-addon-for-loco-translate' ),
                'display_slug'    => 'automatic-translations-for-polylang',
                'parent_plugin'   => ['polylang', 'polylang-pro'],
                'free_name'       => __('AutoPoly - AI Translation For Polylang', 'automatic-translator-addon-for-loco-translate'),
                'pro_name'        => __('AutoPoly - AI Translation For Polylang (Pro)', 'automatic-translator-addon-for-loco-translate'),
            ],
            [
                'slug_free'       => 'automatic-translate-addon-for-translatepress',
                'slug_pro'        => 'automatic-translate-addon-pro-for-translatepress',
                'plugin_file_free'=> 'automatic-translate-addon-for-translatepress/automatic-translate-addon-for-translatepress.php',
                'plugin_file_pro' => 'automatic-translate-addon-pro-for-translatepress/automatic-translate-addon-for-translatepress-pro.php',
                'desc'            => __( 'Make WordPress translation faster, smarter, and easier with AI-powered automation for TranslatePress.', 'automatic-translator-addon-for-loco-translate' ),
                'image'           => ATLT_URL . 'admin/atlt-dashboard/images/translatepress-addon.png',
                'image_alt'       => __( 'TranslatePress Addon', 'automatic-translator-addon-for-loco-translate' ),
                'display_slug'    => 'automatic-translate-addon-for-translatepress',
                'parent_plugin'   => 'translatepress-multilingual',
                'free_name'       => __('AI Translation for TranslatePress', 'automatic-translator-addon-for-loco-translate'),
                'pro_name'        => __('AI Translation for TranslatePress (Pro)', 'automatic-translator-addon-for-loco-translate'),
            ],
            [
                'slug_free'       => 'wpml-translation-check',
                'slug_pro'        => 'automlp-ai-translation-for-wpml-pro',
                'plugin_file_free'=> 'wpml-translation-check/index.php',
                'plugin_file_pro' => 'automlp-pro/automlp-pro.php',
                'desc'            => __( 'Translate your entire WordPress website faster than ever with AI-powered translation built for WPML- AUTOMLP.', 'automatic-translator-addon-for-loco-translate' ),
                'image'           => ATLT_URL . 'admin/atlt-dashboard/images/automlp-ai-translation-for-wpml.png',
                'image_alt'       => __( 'AutoMLP – AI Translation for WPML', 'automatic-translator-addon-for-loco-translate' ),
                'display_slug'    => 'automlp-ai-translation-for-wpml',
                'parent_plugin'   => 'sitepress-multilingual-cms',
                'free_name'       => __('AutoMLP – AI Translation for WPML', 'automatic-translator-addon-for-loco-translate'),
                'pro_name'        => __('AutoMLP – AI Translation for WPML (Pro)', 'automatic-translator-addon-for-loco-translate'),
            ],
        ];

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $is_polylang_installed = false;
        foreach (array_keys($plugins) as $plugin_file) {
            if (strpos((string)$plugin_file, 'polylang/') === 0 || strpos((string)$plugin_file, 'polylang-pro/') === 0) {
                $is_polylang_installed = true;
                break;
            }
        }

        if (!$is_polylang_installed) {
            $addons[] = [
                'slug_free'       => 'translate-words',
                'slug_pro'        => '',
                'plugin_file_free'=> 'translate-words/translate-wp-words.php',
                'plugin_file_pro' => '',
                'desc'            => __( 'Translate your entire WordPress website faster than ever with AI-powered translation - Linguator.', 'automatic-translator-addon-for-loco-translate' ),
                'image'           => ATLT_URL . 'admin/atlt-dashboard/images/linguator-multilingual-ai-translation.png',
                'image_alt'       => __( 'Linguator Addon', 'automatic-translator-addon-for-loco-translate' ),
                'display_slug'    => 'translate-words',
                'parent_plugin'   => '',
                'free_name'       => __('Linguator Multilingual AI Translation', 'automatic-translator-addon-for-loco-translate'),
                'pro_name'        => '',
            ];
        }

        /**
         * Allow other plugins/addons to extend dashboard addon cards.
         *
         * Each addon should be an array with keys like:
         * slug_free, slug_pro, plugin_file_free, plugin_file_pro, desc, image, image_alt, display_slug, free_name, pro_name.
         */
        $addons = apply_filters( 'atlt_dashboard_addons', $addons, 'automatic-translator-addon-for-loco-translate' );

        if ( ! is_array( $addons ) ) {
            $addons = [];
        }
        
        return $addons;
    }
}
