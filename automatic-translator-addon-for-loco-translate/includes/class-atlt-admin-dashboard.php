<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

class ATLT_Admin_Dashboard {

    /**
     * Initialize class hooks and register menu creation and post-activation redirects.
     *
     * @return void
     */
    public static function init() {
        $instance = new self();
        add_action('admin_menu', [$instance, 'atlt_add_locotranslate_sub_menu'], 101);
        add_action('admin_init', [$instance, 'atlt_do_activation_redirect']);
    }

    /**
     * Register the Loco Automatic Translate submenu under Loco Translate admin parent menu.
     *
     * @return void
     */
    public function atlt_add_locotranslate_sub_menu()
    {
        // Only add submenu if Pro is NOT active
        if (defined('ATLT_PRO_VERSION')) {
            return;
        }
        add_submenu_page(
            'loco',
            'Loco Automatic Translate',
            'LocoAI',
            'manage_options',
            'loco-atlt-dashboard',
            [$this, 'atlt_dashboard_page']
        );
    }

    /**
     * Handles redirecting the user to the plugin dashboard page upon activation.
     *
     * @return void
     */
    public function atlt_do_activation_redirect()
    {
        if (get_option('atlt_do_activation_redirect', false)) {
            // Only redirect if not part of a bulk activation
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking for bulk activation parameter during plugin activation, no data processing
            if (! isset($_GET['activate-multi'])) {

                // Check if required Loco Translate plugin is active (or required function exists)
                if (function_exists('loco_plugin_self')) {
                    update_option('atlt_do_activation_redirect', false);
                    wp_safe_redirect(admin_url('admin.php?page=loco-atlt-dashboard'));
                    exit;
                }
            }
        }
        if (! get_option('atlt-install-date')) {
            add_option('atlt-install-date', gmdate('Y-m-d h:i:s'));
        }

        if (! get_option('atlt_initial_save_version')) {
            add_option('atlt_initial_save_version', ATLT_VERSION);
        }
    }

    /**
     * Render the admin dashboard configuration and settings page.
     *
     * @return void
     */
    public function atlt_dashboard_page()
    {
        $file_prefix = 'admin/atlt-dashboard/views/';

        $valid_tabs = [
            'dashboard'       => __('Dashboard', 'automatic-translator-addon-for-loco-translate'),
            'settings'        => __('Settings', 'automatic-translator-addon-for-loco-translate'),
            'license'         => __('License', 'automatic-translator-addon-for-loco-translate'),
            'free-vs-pro'     => __('Free vs Pro', 'automatic-translator-addon-for-loco-translate'),
        ];

        // Get current tab with fallback
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab         = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
        $current_tab = array_key_exists($tab, $valid_tabs) ? $tab : 'dashboard';

        // Action buttons configuration
        $buttons = [
            [
                'url' => 'https://locoaddon.com/docs/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_header',
                'img' => 'document.svg',
                'alt' => __('document', 'automatic-translator-addon-for-loco-translate'),
            ],
            [
                'url' => 'https://locoaddon.com/support/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_header',
                'img' => 'contact.svg',
                'alt' => __('contact', 'automatic-translator-addon-for-loco-translate'),
                'text' => __('Support', 'automatic-translator-addon-for-loco-translate'),
            ],
        ];

        // Start HTML output
        ?>
<div class="atlt-dashboard-wrapper">
    <div class="atlt-dashboard-header">
        <div class="atlt-dashboard-header-left">
            <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/loco-addon-logo.svg'); ?>" alt="<?php
            esc_attr_e('Loco Translate Logo', 'automatic-translator-addon-for-loco-translate'); ?>">
            <div class="atlt-dashboard-tab-title">
                <span>↳</span> <?php echo esc_html($valid_tabs[$current_tab]); ?>
            </div>
        </div>
        <div class="atlt-dashboard-header-right">
            <span><?php
            esc_html_e('Auto translate plugins & themes.', 'automatic-translator-addon-for-loco-translate'); ?></span>
            <?php foreach ($buttons as $button): ?>
            <a href="<?php echo esc_url($button['url']); ?>" class="atlt-dashboard-btn" target="_blank"
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

    <nav class="nav-tab-wrapper" aria-label="<?php
        esc_attr_e('Dashboard navigation', 'automatic-translator-addon-for-loco-translate'); ?>">
        <?php foreach ($valid_tabs as $tab_key => $tab_title): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=loco-atlt-dashboard&tab=' . $tab_key)); ?>"
            class="nav-tab <?php echo esc_attr($tab === $tab_key ? 'nav-tab-active' : ''); ?>">
            <?php echo esc_html($tab_title); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="tab-content">
        <div class="atlt-dashboard-left-section">
        <?php
            // Secure file inclusion with strict whitelist validation
            $allowed_templates = [
                'dashboard'       => 'dashboard.php',
                'settings'        => 'settings.php',
                'license'         => 'license.php',
                'free-vs-pro'     => 'free-vs-pro.php',
            ];

            // Double validation: check if current_tab exists in allowed templates
            if (! array_key_exists($current_tab, $allowed_templates)) {
                $current_tab = 'dashboard'; // Fallback to safe default
            }

            $template_filename = $allowed_templates[$current_tab];
            $template_file     = ATLT_PATH . $file_prefix . $template_filename;

            // Additional security: ensure the resolved path is within the expected directory
            $real_template_path = realpath($template_file);
            $expected_base_path = realpath(ATLT_PATH . $file_prefix);

            if ($real_template_path && $expected_base_path &&
                strpos($real_template_path, $expected_base_path) === 0 &&
                file_exists($template_file)) {
                require_once $template_file;
            } else {
                // Fallback to dashboard if template validation fails
                $fallback_template = ATLT_PATH . $file_prefix . 'dashboard.php';
                if (file_exists($fallback_template)) {
                    require_once $fallback_template;
                }
            }

            if (class_exists('Atlt_Dashboard')) {
                Atlt_Dashboard::render_footer();
            }

            ?>
        </div>
        <?php
            // Include sidebar (with same security validation)
            $sidebar_file      = ATLT_PATH . $file_prefix . 'sidebar.php';
            $real_sidebar_path = realpath($sidebar_file);
            if ($real_sidebar_path && $expected_base_path &&
                strpos($real_sidebar_path, $expected_base_path) === 0 &&
                file_exists($sidebar_file)) {
                require_once $sidebar_file;
            }

        ?>
    </div>
</div>
<?php
    }
}
