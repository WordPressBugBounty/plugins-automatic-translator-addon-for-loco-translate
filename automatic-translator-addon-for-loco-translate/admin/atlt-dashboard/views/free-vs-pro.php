<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-free-vs-pro">
    <div class="atlt-dashboard-free-vs-pro-container">
    <div class="header">
        <h1><?php
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
        esc_html_e('Free VS Pro', $text_domain); ?></h1>
        <div class="atlt-dashboard-status">
            <span class="status"><?php 
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            esc_html_e('Inactive', $text_domain); ?></span>
            <a href="<?php echo esc_url('https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=freevspro'); ?>" class='atlt-dashboard-btn' target="_blank" rel="noopener noreferrer">
              <img src="<?php echo esc_url(ATLT_URL . 'admin/atlt-dashboard/images/upgrade-now.svg'); ?>" alt="<?php 
              // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
              echo esc_attr(__('Upgrade Now', $text_domain)); ?>">
                <?php
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_html(__('Upgrade Now', $text_domain)); ?>
            </a>
        </div>
    </div>
    
    <p><?php
    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
    echo esc_html(__('Compare the Free and Pro versions to choose the best option for your translation needs.', $text_domain)); ?></p>

    <table>
        <thead>
            <tr>
                <th><?php 
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_html(__('Dynamic Content', $text_domain)); ?></th>
                <th><?php 
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_html(__('Free', $text_domain)); ?></th>
                <th><?php 
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                echo esc_html(__('Pro', $text_domain)); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
                $atlt_features = [
                    'Yandex Translate Widget Support' => [true, true],
                    'No API Key Required' => [true, true],
                    'Unlimited Translations' => [true, true],
                    'Google Translate Widget Support' => [false, true],
                    'Chrome Built-in AI Support' => [false, true],
                    'AI Translator (Gemini/OpenAI) Support' => [false, true],
                    'ChatGPT Translator Support' => [false, true],
                    'DeepL Doc Translator Support' => [false, true],
                    'Premium Support' => [false, true],
                ];
             foreach ($atlt_features as $atlt_feature => $atlt_availability): ?>
                <tr>
                    <td><?php echo esc_html($atlt_feature); ?></td>
                    <td class="<?php echo esc_attr( $atlt_availability[0] ? 'check' : 'cross' ); ?>">
                        <?php echo esc_html( $atlt_availability[0] ? '✓' : '✗' ); ?>
                    </td>
                    <td class="<?php echo esc_attr( $atlt_availability[1] ? 'check' : 'cross' ); ?>">
                        <?php echo esc_html( $atlt_availability[1] ? '✓' : '✗' ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>