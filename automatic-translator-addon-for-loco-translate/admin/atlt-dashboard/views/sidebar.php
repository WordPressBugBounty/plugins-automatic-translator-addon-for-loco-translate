<?php
 if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Right Sidebar -->
<div class="atlt-dashboard-sidebar">
    <div class="atlt-dashboard-status">
        <h3><?php 
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('Auto Translation status', $text_domain); ?></h3>
        <div class="atlt-dashboard-sts-top">
            <?php

            $atlt_all_data = get_option('cpt_dashboard_data', array());

            if (!is_array($atlt_all_data) || !isset($atlt_all_data['atlt'])) {

                $atlt_all_data['atlt'] = []; // Ensure $atlt_all_data['atlt'] is an array

            }

            $totals = array_reduce($atlt_all_data['atlt'] ?? [], function($carry, $atlt_translation) {
                // Ensure $atlt_translation['string_count'] is numeric
                $carry['string_count'] += intval($atlt_translation['string_count'] ?? 0);
                $carry['character_count'] += intval($atlt_translation['character_count'] ?? 0);
                $carry['time_taken'] += intval($atlt_translation['time_taken'] ?? 0);
                $plugin_theme = sanitize_key($atlt_translation['plugins_themes'] ?? ''); // Sanitize plugin/theme key
                $carry['plugins_themes'][$plugin_theme] = 1; // Ensure this is sanitized
                return $carry;
            }, ['string_count' => 0, 'character_count' => 0, 'time_taken' => 0, 'plugins_themes' => []]);
            // Update the time taken string using the new function
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            $atlt_time_taken_str = atlt_format_time_taken($totals['time_taken'] ,$text_domain);
            ?>
            <span><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            echo esc_html(atlt_format_number($totals['string_count'], $text_domain)); ?></span>
            <span><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e('Total Strings Translated!', $text_domain); ?></span>
        </div>
        <ul class="atlt-dashboard-sts-btm">
            <li><span><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e('Total Characters', $text_domain); ?></span> <span><?php echo esc_html(atlt_format_number($totals['character_count'], $text_domain)); ?></span></li>
            <li><span><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e('Total Plugins / Themes', $text_domain); ?></span> <span><?php echo esc_html(count($totals['plugins_themes'])); ?></span></li>
            <li><span><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e('Time Taken', $text_domain); ?></span> <span><?php echo esc_html($atlt_time_taken_str); ?></span></li>
        </ul>
    </div>

    <div class="atlt-dashboard-translate-full">
        <h3><?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('Automatically Translate Full Webpage', $text_domain); ?></h3>
        <div class="atlt-dashboard-addon first">
        <div class="atlt-dashboard-addon-l">
    <strong><?php 
     // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    echo esc_html( atlt_get_plugin_display_name( 'automatic-translations-for-polylang', $text_domain ) ); ?></strong>

    <span class="addon-desc">
        <?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e( 'Polylang addon to translate webpages.', $text_domain ); ?>
    </span>

    <?php
    // Safety
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();

    $atlt_ap_free = 'automatic-translations-for-polylang/automatic-translation-for-polylang.php';
    $atlt_ap_pro  = 'autopoly-ai-translation-for-polylang-pro/autopoly-ai-translation-for-polylang-pro.php';

    $atlt_ap_pro_installed  = isset( $plugins[ $atlt_ap_pro ] );
    $atlt_ap_free_installed = isset( $plugins[ $atlt_ap_free ] );

    $atlt_ap_any_installed = $atlt_ap_pro_installed || $atlt_ap_free_installed;

    $atlt_ap_pro_active  = is_plugin_active( $atlt_ap_pro );
    $atlt_ap_free_active = is_plugin_active( $atlt_ap_free );
    ?>

    <?php if ( $atlt_ap_pro_active || $atlt_ap_free_active ) :   ?>
        
        <span class="installed"><?php 
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e( 'Activated', $text_domain ); ?></span>

    <?php else : ?>

        <button
    type="button"
    class="atlt-dashboard-btn atlt-install-plugin"
    data-slug="<?php echo esc_attr(
        $atlt_ap_pro_installed ? 'autopoly-ai-translation-for-polylang-pro' : 'automatic-translations-for-polylang'
    ); ?>"
    data-action="<?php echo esc_attr(
        ($atlt_ap_pro_installed || $atlt_ap_free_installed) ? 'activate' : 'install'
    ); ?>"
    data-nonce="<?php echo esc_attr( wp_create_nonce( 'alt_install_nonce' ) ); ?>"
>
        <?php
            if ( $atlt_ap_any_installed ) {
             // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_html_e( 'Activate', $text_domain );
            } else {
                 // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_html_e( 'Install', $text_domain );
            }
?>
        </button>

        <div class="atlt-install-message" aria-live="polite" style="margin-top:8px;"></div>

    <?php endif; ?>
</div>
            <div class="atlt-dashboard-addon-r">
                <img src="<?php 
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/polylang-addon.png'); ?>" alt="<?php esc_attr_e('Polylang Addon', $text_domain); ?>">
            </div>
        </div>
        <div class="atlt-dashboard-addon">
        <div class="atlt-dashboard-addon-l">
    <strong><?php 
    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    echo esc_html( atlt_get_plugin_display_name( 'automatic-translate-addon-for-translatepress', $text_domain ) ); ?></strong>

    <span class="addon-desc">
        <?php 
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e( 'TranslatePress addon to translate webpages.', $text_domain ); ?>
    </span>

    <?php
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();

    $atlt_tp_free = 'automatic-translate-addon-for-translatepress/automatic-translate-addon-for-translatepress.php';
    $atlt_tp_pro  = 'automatic-translate-addon-pro-for-translatepress/automatic-translate-addon-for-translatepress-pro.php';

    $atlt_tp_free_installed = isset( $plugins[ $atlt_tp_free ] );
    $atlt_tp_pro_installed  = isset( $plugins[ $atlt_tp_pro ] );

    $atlt_tp_any_installed = $atlt_tp_free_installed || $atlt_tp_pro_installed;

    $atlt_tp_free_active = is_plugin_active( $atlt_tp_free );
    $atlt_tp_pro_active  = is_plugin_active( $atlt_tp_pro );
    ?>

    <?php if ( $atlt_tp_free_active || $atlt_tp_pro_active ) : ?>

        <span class="installed"><?php 
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e( 'Activated', $text_domain ); ?></span>

    <?php else : ?>

        <button
            type="button"
            class="atlt-dashboard-btn atlt-install-plugin"
            data-slug="<?php echo esc_attr(
                $atlt_tp_pro_installed
                    ? 'automatic-translate-addon-pro-for-translatepress'
                    : 'automatic-translate-addon-for-translatepress'
            ); ?>"
            data-action="<?php echo esc_attr( $atlt_tp_any_installed ? 'activate' : 'install' ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'alt_install_nonce' ) ); ?>"
        >
            <?php
            
            echo esc_html(
               
                $atlt_tp_any_installed
                 // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                    ? __( 'Activate', $text_domain )
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                    : __( 'Install', $text_domain )
            );
            ?>
        </button>

        <div class="atlt-install-message" aria-live="polite" style="margin-top:8px;"></div>

    <?php endif; ?>
</div>

            <div class="atlt-dashboard-addon-r">
                <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/translatepress-addon.png'); ?>" alt="<?php 
                 // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_attr_e('TranslatePress Addon', $text_domain); ?>">
            </div>
        </div>

        <div class="atlt-dashboard-addon">
            <div class="atlt-dashboard-addon-l">
                <strong><?php echo esc_html(atlt_get_plugin_display_name('translate-words', $text_domain)); ?></strong>
                <span class="addon-desc"><?php 
                 // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                esc_html_e('Create a Multilingual WordPress Website 10X Faster – Powered by AI.', $text_domain); ?></span>
                <?php
if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$atlt_tw_slug        = 'translate-words';
$atlt_tw_plugin_file = 'translate-words/translate-wp-words.php';

$atlt_tw_installed = atlt_is_plugin_installed( $atlt_tw_slug );
$atlt_tw_active = is_plugin_active( $atlt_tw_plugin_file );
?>

<?php if ( $atlt_tw_installed && $atlt_tw_active) : ?>
    <span class="installed"><?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e( 'Activated', $text_domain ); ?></span>
<?php else : ?>
    <button
        type="button"
        class="atlt-dashboard-btn atlt-install-plugin"
        data-slug="translate-words"
        data-action="<?php echo esc_attr( $atlt_tw_installed ? 'activate' : 'install' ); ?>"
        data-nonce="<?php echo esc_attr( wp_create_nonce( 'alt_install_nonce' ) ); ?>"
    >
        <?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        echo esc_html( $atlt_tw_installed ? __( 'Activate', $text_domain ) : __( 'Install', $text_domain ) ); ?>
    </button>
    <div class="atlt-install-message" aria-live="polite" style="margin-top:8px;"></div>
<?php endif; ?>

            </div>
            <div class="atlt-dashboard-addon-r">
                <img src="<?php 
                 // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/linguator-multilingual-ai-translation.png'); ?>" alt="<?php esc_attr_e('Linguator Multilingual AI Translation', $text_domain); ?>">
            </div>
        </div>
    </div>

    <div class="atlt-dashboard-rate-us">
        <h3><?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('Rate Us ⭐⭐⭐⭐⭐', $text_domain); ?></h3>
        <p><?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('We\'d love your feedback! Hope this addon made auto-translations easier for you.', $text_domain); ?></p>
        <a href="<?php echo esc_url('https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post'); ?>" class="review-link" target="_blank" rel="noopener noreferrer"><?php 
         // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('Submit a Review →', $text_domain); ?></a>
    </div>
</div>

<?php

function atlt_format_time_taken($time_taken, $text_domain) {
    
    if ($time_taken === 0) 
    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    return __('0', $text_domain);
    
    if ($time_taken < 60) {
        
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        /* translators: %d: number of seconds */ return sprintf(__('%d sec', $text_domain), $time_taken);
    }
    if ($time_taken < 3600) {
        $min = floor($time_taken / 60);
        $sec = $time_taken % 60;
        
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        /* translators: 1: number of minutes, 2: number of seconds */ return sprintf(__('%1$d min %2$d sec', $text_domain), $min, $sec);
    }
    $hours = floor($time_taken / 3600);
    $min = floor(($time_taken % 3600) / 60);
    
    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    /* translators: 1: number of hours, 2: number of minutes */ return sprintf(__('%1$d hours %2$d min', $text_domain), $hours, $min);
}

function atlt_is_plugin_installed($plugin_slug) {
    $plugins = get_plugins();
    // Check if the plugin is installed
    if ($plugin_slug === 'automatic-translate-addon-for-translatepress') {
        return isset($plugins['automatic-translate-addon-for-translatepress/automatic-translate-addon-for-translatepress.php']) || isset($plugins['automatic-translate-addon-pro-for-translatepress/automatic-translate-addon-for-translatepress-pro.php']);
    } elseif ($plugin_slug === 'automatic-translations-for-polylang') {
        return isset($plugins['automatic-translations-for-polylang/automatic-translation-for-polylang.php']) ||isset($plugins['automatic-translations-for-polylang-pro/automatic-translation-for-polylang.php']);
    } elseif ($plugin_slug === 'translate-words') {
        return isset($plugins['translate-words/translate-wp-words.php']);
    }

    // Generic: check any installed plugin file under "<slug>/"
    foreach (array_keys($plugins) as $plugin_file) {
        if (strpos($plugin_file, $plugin_slug . '/') === 0) {
            return true;
        }
    }

    return false; // Return false if no match found
}

function atlt_get_plugin_display_name($plugin_slug, $text_domain) {
    $plugins = get_plugins();

    // Define free and pro plugin paths
    $plugin_paths = [
        'automatic-translations-for-polylang' => [
            'free' => 'automatic-translations-for-polylang/automatic-translation-for-polylang.php',
            'pro'  => 'autopoly-ai-translation-for-polylang-pro/autopoly-ai-translation-for-polylang-pro.php',
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'free_name' => __('AutoPoly - AI Translation For Polylang', $text_domain),
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'pro_name'  => __('AutoPoly - AI Translation For Polylang (Pro)', $text_domain),
        ],
        'automatic-translate-addon-for-translatepress' => [
            'free' => 'automatic-translate-addon-for-translatepress/automatic-translate-addon-for-translatepress.php',
            'pro'  => 'automatic-translate-addon-pro-for-translatepress/automatic-translate-addon-for-translatepress-pro.php',
           // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'free_name' => __('AI Translation for TranslatePress', $text_domain),
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'pro_name'  => __('AI Translation for TranslatePress (Pro)', $text_domain),
        ],
        'translate-words' => [
            'free' => 'translate-words/translate-wp-words.php',
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            'free_name' => __('Linguator Multilingual AI Translation', $text_domain),
        ],
     
    ];

    // If slug isn't mapped, try to read its real name from installed plugins
    if (!isset($plugin_paths[$plugin_slug])) {
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (strpos($plugin_file, $plugin_slug . '/') === 0 && !empty($plugin_data['Name'])) {
                return sanitize_text_field($plugin_data['Name']);
            }
        }
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        return __('Unknown plugin', $text_domain);
    }

    $free_installed = isset($plugins[$plugin_paths[$plugin_slug]['free']]);
    $has_pro = isset($plugin_paths[$plugin_slug]['pro']);
    $pro_installed = $has_pro && isset($plugins[$plugin_paths[$plugin_slug]['pro']]);

    // Determine which version is installed
    if ($pro_installed && isset($plugin_paths[$plugin_slug]['pro_name'])) {
        return $plugin_paths[$plugin_slug]['pro_name'];
    }

    if ($free_installed && isset($plugin_paths[$plugin_slug]['free_name'])) {
        return $plugin_paths[$plugin_slug]['free_name'];
    }
    
   // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    return $plugin_paths[$plugin_slug]['free_name'] ?? __('Unknown plugin', $text_domain);
}

function atlt_format_number($number, $text_domain) {
    $formats = [
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        1000000000 => __('B+', $text_domain),
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        1000000 => __('M+', $text_domain),  
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        1000 => __('K+', $text_domain)
    ];
    
    foreach ($formats as $threshold => $suffix) {
        if ($number >= $threshold) {
            return floor($number / $threshold * 10) / 10 . $suffix;
        }
    }
    return $number;
}

