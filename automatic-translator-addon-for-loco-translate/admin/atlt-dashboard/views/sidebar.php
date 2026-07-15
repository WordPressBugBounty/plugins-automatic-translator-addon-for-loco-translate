<?php
 if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Right Sidebar -->
<div class="atlt-dashboard-sidebar">
    <div class="atlt-dashboard-status">
        <h3><?php 
        esc_html_e('Auto Translation status', 'automatic-translator-addon-for-loco-translate'); ?></h3>
        <div class="atlt-dashboard-sts-top">
            <?php

            $atlt_all_data = get_option('cpt_dashboard_data', array());

            if (!is_array($atlt_all_data) || !isset($atlt_all_data['atlt'])) {

                $atlt_all_data['atlt'] = []; // Ensure $atlt_all_data['atlt'] is an array

            }

            $totals = array_reduce( $atlt_all_data['atlt'] ?? [], function( $carry, $atlt_translation ) {
                $carry['string_count']    += intval( $atlt_translation['string_count'] ?? 0 );
                $carry['character_count'] += intval( $atlt_translation['character_count'] ?? 0 );
                $carry['time_taken']      += intval( $atlt_translation['time_taken'] ?? 0 );

                $service_provider_raw = $atlt_translation['service_provider'] ?? '';
                $service_provider_key = sanitize_key( (string) $service_provider_raw );

                // Keep a unique set of providers for display counts.
                if ( '' !== $service_provider_key ) {
                    $carry['service_providers'][ $service_provider_key ] = 1;
                }

                // Also track characters by provider (keyed and sanitized).
                if ( '' !== $service_provider_key ) {
                    if ( ! isset( $carry['provider_character_count'][ $service_provider_key ] ) ) {
                        $carry['provider_character_count'][ $service_provider_key ] = 0;
                    }
                    $carry['provider_character_count'][ $service_provider_key ] += intval( $atlt_translation['character_count'] ?? 0 );
                }

                $plugin_theme = sanitize_key( $atlt_translation['plugins_themes'] ?? '' );
                if ( '' !== $plugin_theme ) {
                    $carry['plugins_themes'][ $plugin_theme ] = 1;
                }

                return $carry;
            }, [ 'string_count' => 0, 'character_count' => 0, 'time_taken' => 0, 'plugins_themes' => [], 'service_providers' => [], 'provider_character_count' => [] ] );

            $atlt_service_provider_keys = array_keys( $totals['service_providers'] ?? [] );
            $atlt_service_provider_label_map = [
                'google'  => 'Google',
                'openai'  => 'OpenAI',
                'deepl'   => 'DeepL',
                'geminiai'  => 'Gemini AI',
                'yandex'  => 'Yandex',
                'chrome'  => 'Chrome AI',
                'chatgpt' => 'ChatGPT',
            ];
             $atlt_service_provider_labels = array_map( static function( $key ) use ( $atlt_service_provider_label_map ) {
                if ( isset( $atlt_service_provider_label_map[ $key ] ) ) {
                    return $atlt_service_provider_label_map[ $key ];
                }
                $label = str_replace( [ '-', '_' ], ' ', (string) $key );
                return ucwords( $label );
            }, $atlt_service_provider_keys );
            $atlt_translated_by_display = empty( $atlt_service_provider_labels ) ? __( '—', 'automatic-translator-addon-for-loco-translate' ) : '';
            // Update the time taken string using the new function
            $atlt_time_taken_str = ATLT_Addons_Helper::format_time_taken($totals['time_taken']);
            ?>
            <span><?php 
            echo esc_html(ATLT_Addons_Helper::format_number($totals['string_count'])); ?></span>
            <span><?php 
            esc_html_e('Total Strings Translated!', 'automatic-translator-addon-for-loco-translate'); ?></span>
        </div>
        <ul class="atlt-dashboard-sts-btm">
            <li><span><?php 
            esc_html_e('Total Characters', 'automatic-translator-addon-for-loco-translate'); ?></span> <span><?php echo esc_html(ATLT_Addons_Helper::format_number($totals['character_count'])); ?></span></li>
            <li><span><?php 
            esc_html_e('Total Plugins / Themes', 'automatic-translator-addon-for-loco-translate'); ?></span> <span><?php echo esc_html(count($totals['plugins_themes'] ?? [])); ?></span></li>
            <li><span><?php 
            esc_html_e('Time Taken', 'automatic-translator-addon-for-loco-translate'); ?></span> <span><?php echo esc_html($atlt_time_taken_str); ?></span></li>
            <li><span><?php 
            esc_html_e('Translated By', 'automatic-translator-addon-for-loco-translate'); ?></span>
                <span class="atlt-dashboard-provider-labels">
                    <?php if ( empty( $atlt_service_provider_labels ) ) : ?>
                        <?php echo esc_html( $atlt_translated_by_display ); ?>
                    <?php else : ?>
                        <?php foreach ( $atlt_service_provider_labels as $atlt_provider_label ) : ?>
                            <span class="atlt-dashboard-provider-label"><?php echo esc_html( $atlt_provider_label ); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </span>
            </li>
        </ul>
    </div>

        <?php
        // Safety.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $atlt_installed_plugins = get_plugins();

        $atlt_addons = ATLT_Addons_Helper::get_addon_definitions();

        $atlt_parent_exists = static function( $parent ) use ( $atlt_installed_plugins ) {
            if ( empty( $parent ) ) {
                return true;
            }

            $parents = is_array( $parent ) ? $parent : [ $parent ];
            foreach ( $parents as $parent_slug ) {
                $parent_slug = sanitize_key( (string) $parent_slug );
                if ( '' === $parent_slug ) {
                    continue;
                }

                // Fast path: any installed plugin file inside "<slug>/" means parent exists.
                $parent_dir = trailingslashit( $parent_slug );
                foreach ( array_keys( $atlt_installed_plugins ) as $plugin_file ) {
                    if ( 0 === strpos( (string) $plugin_file, $parent_dir ) ) {
                        return true;
                    }
                }
            }
            return false;
        };

        // Only show cards whose parent plugin exists (installed).
        $atlt_addons = array_values( array_filter( $atlt_addons, static function( $addon ) use ( $atlt_parent_exists ) {
            $addon = is_array( $addon ) ? $addon : [];
            return $atlt_parent_exists( $addon['parent_plugin'] ?? '' );
        } ) );
        ?>

        <?php if ( ! empty( $atlt_addons ) ) : ?>
    <div class="atlt-dashboard-translate-full">
        <?php foreach ( $atlt_addons as $atlt_addon ) : ?>
            <?php
            $atlt_addon = is_array( $atlt_addon ) ? $atlt_addon : [];

            $atlt_slug_free        = sanitize_key( (string) ( $atlt_addon['slug_free'] ?? '' ) );
            $atlt_slug_pro         = sanitize_key( (string) ( $atlt_addon['slug_pro'] ?? '' ) );
            $atlt_plugin_file_free = (string) ( $atlt_addon['plugin_file_free'] ?? '' );
            $atlt_plugin_file_pro  = (string) ( $atlt_addon['plugin_file_pro'] ?? '' );
            $atlt_desc             = (string) ( $atlt_addon['desc'] ?? '' );
            $atlt_image            = (string) ( $atlt_addon['image'] ?? '' );
            $atlt_image_alt        = (string) ( $atlt_addon['image_alt'] ?? '' );
            $atlt_display_slug     = sanitize_key( (string) ( $atlt_addon['display_slug'] ?? $atlt_slug_free ) );

            $atlt_free_installed = ( '' !== $atlt_plugin_file_free ) && isset( $atlt_installed_plugins[ $atlt_plugin_file_free ] );
            // Pro detection (robust): prefer exact main file, fallback to any file within "<slug_pro>/" folder.
            $atlt_pro_installed = ( '' !== $atlt_plugin_file_pro ) && isset( $atlt_installed_plugins[ $atlt_plugin_file_pro ] );
            $atlt_pro_file_real = $atlt_pro_installed ? $atlt_plugin_file_pro : '';

            if ( ! $atlt_pro_installed && '' !== $atlt_plugin_file_pro ) {
                $atlt_pro_dir = trailingslashit( dirname( $atlt_plugin_file_pro ) );
                foreach ( array_keys( $atlt_installed_plugins ) as $atlt_candidate_file ) {
                    if ( 0 === strpos( (string) $atlt_candidate_file, $atlt_pro_dir ) ) {
                        $atlt_pro_installed = true;
                        $atlt_pro_file_real = (string) $atlt_candidate_file;
                        break;
                    }
                }
            }

            // Final fallback: rely on slug-specific installed check (pro-only for pro slugs).
            if ( ! $atlt_pro_installed && '' !== $atlt_slug_pro && method_exists( 'ATLT_Addons_Helper', 'is_plugin_installed' ) ) {
                $atlt_pro_installed = (bool) ATLT_Addons_Helper::is_plugin_installed( $atlt_slug_pro, 'pro', $atlt_installed_plugins );
            }

            // If installed but we don't know the exact main file, pick the first file in that folder for active checks.
            if ( $atlt_pro_installed && '' === $atlt_pro_file_real && '' !== $atlt_plugin_file_pro ) {
                $atlt_pro_dir = trailingslashit( dirname( $atlt_plugin_file_pro ) );
                foreach ( array_keys( $atlt_installed_plugins ) as $atlt_candidate_file ) {
                    if ( 0 === strpos( (string) $atlt_candidate_file, $atlt_pro_dir ) ) {
                        $atlt_pro_file_real = (string) $atlt_candidate_file;
                        break;
                    }
                }
            }

            $atlt_pro_active = ( $atlt_pro_installed && '' !== $atlt_pro_file_real )
                ? is_plugin_active( $atlt_pro_file_real )
                : false;

            $atlt_any_installed  = $atlt_free_installed || $atlt_pro_installed;

            $atlt_free_active = ( '' !== $atlt_plugin_file_free ) && is_plugin_active( $atlt_plugin_file_free );

            $atlt_show_pro_activate = $atlt_pro_installed && ! $atlt_pro_active && '' !== $atlt_slug_pro;
            $atlt_show_activated    = $atlt_pro_active || ( $atlt_free_active && ! $atlt_show_pro_activate );

            $atlt_action = $atlt_any_installed ? 'activate' : 'install';
            $atlt_btn_slug = ( $atlt_pro_installed && '' !== $atlt_slug_pro ) ? $atlt_slug_pro : $atlt_slug_free;
            ?>

            <div class="atlt-dashboard-addon">
                <div class="atlt-dashboard-addon-l">
                        <strong>
                            <?php
                            if ( $atlt_pro_active && '' !== $atlt_slug_pro ) {
                                $atlt_name_slug = $atlt_slug_pro;
                            } elseif ( $atlt_show_pro_activate || ( '' !== $atlt_slug_pro && $atlt_btn_slug === $atlt_slug_pro ) ) {
                                $atlt_name_slug = $atlt_slug_pro;
                            } elseif ( $atlt_show_activated ) {
                                $atlt_name_slug = $atlt_display_slug;
                            } else {
                                $atlt_name_slug = $atlt_btn_slug ?: $atlt_display_slug;
                            }
                            echo esc_html( ATLT_Addons_Helper::get_plugin_display_name( $atlt_name_slug, $atlt_installed_plugins ) );
                            ?>
                        </strong>

                        <?php if ( '' !== $atlt_desc ) : ?>
                            <span class="addon-desc"><?php echo esc_html( $atlt_desc ); ?></span>
                        <?php endif; ?>

                        <?php if ( $atlt_show_pro_activate ) : ?>
                            <button
                                type="button"
                                class="atlt-dashboard-btn atlt-install-plugin"
                                data-slug="<?php echo esc_attr( $atlt_slug_pro ); ?>"
                                data-action="activate"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'alt_install_nonce' ) ); ?>"
                            >
                                <?php esc_html_e( 'Activate', 'automatic-translator-addon-for-loco-translate' ); ?>
                            </button>
                            <div class="atlt-install-message" aria-live="polite"></div>
                        <?php elseif ( $atlt_show_activated ) : ?>
                            <span class="installed"><?php
                                esc_html_e( 'Activated', 'automatic-translator-addon-for-loco-translate' );
                            ?></span>
                        <?php elseif ( '' !== $atlt_btn_slug ) : ?>
                            <button
                                type="button"
                                class="atlt-dashboard-btn atlt-install-plugin"
                                data-slug="<?php echo esc_attr( $atlt_btn_slug ); ?>"
                                data-action="<?php echo esc_attr( $atlt_action ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'alt_install_nonce' ) ); ?>"
                            >
                                <?php
                                echo esc_html( $atlt_any_installed ? __( 'Activate', 'automatic-translator-addon-for-loco-translate' ) : __( 'Install', 'automatic-translator-addon-for-loco-translate' ) );
                                ?>
                            </button>
                            <div class="atlt-install-message" aria-live="polite"></div>
                        <?php endif; ?>
                </div>

                <?php if ( '' !== $atlt_image ) : ?>
                    <div class="atlt-dashboard-addon-r">
                        <img src="<?php echo esc_url( $atlt_image ); ?>" alt="<?php echo esc_attr( $atlt_image_alt ); ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
        <?php endif; ?>

    <div class="atlt-dashboard-support">
        <h3><?php 
        esc_html_e('Need Help? 🤝', 'automatic-translator-addon-for-loco-translate'); ?></h3>
        <p><?php 
        esc_html_e('Facing any issue with AI translation? Create a support thread and our team will assist you.', 'automatic-translator-addon-for-loco-translate'); ?></p>
        <a href="<?php echo esc_url('https://locoaddon.com/support/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_support'); ?>" class="support-link atlt-dashboard-btn primary" target="_blank" rel="noopener noreferrer"><?php 
        esc_html_e('Get Support →', 'automatic-translator-addon-for-loco-translate'); ?></a>
    </div>

    <div class="atlt-dashboard-rate-us">
        <h3><?php 
        esc_html_e('Happy with LocoAI? ✨', 'automatic-translator-addon-for-loco-translate'); ?></h3>
        <p><?php 
        esc_html_e('We\'d love your feedback! Hope this addon made auto-translations easier for you.', 'automatic-translator-addon-for-loco-translate'); ?></p>
        <a href="<?php echo esc_url('https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post'); ?>" class="review-link atlt-dashboard-btn" target="_blank" rel="noopener noreferrer"><?php 
        esc_html_e('Leave a Review ★★★★★', 'automatic-translator-addon-for-loco-translate'); ?></a>
    </div>
</div>
