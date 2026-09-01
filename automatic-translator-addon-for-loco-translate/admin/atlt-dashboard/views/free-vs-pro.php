<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="atlt-dashboard-free-vs-pro">
    <div class="atlt-dashboard-free-vs-pro-container">
    <div class="header">
        <h1><?php
        esc_html_e('Free VS Pro', 'automatic-translator-addon-for-loco-translate'); ?></h1>
    </div>
    
    <p><?php
    echo esc_html(__('Compare the Free and Pro versions to choose the best option for your translation needs.', 'automatic-translator-addon-for-loco-translate')); ?></p>

    <table>
        <thead>
            <tr>
                <th><?php 
                echo esc_html(__('Dynamic Content', 'automatic-translator-addon-for-loco-translate')); ?></th>
                <th><?php 
                echo esc_html(__('Free', 'automatic-translator-addon-for-loco-translate')); ?></th>
                <th><?php 
                echo esc_html(__('Pro', 'automatic-translator-addon-for-loco-translate')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
                $atlt_features = [
                    __('Yandex Translate Widget Support', 'automatic-translator-addon-for-loco-translate') => [true, true],
                    __('No API Key Required', 'automatic-translator-addon-for-loco-translate') => [true, true],
                    __('Unlimited Translations', 'automatic-translator-addon-for-loco-translate') => [true, true],
                    __('AI Translator Support (OpenAI)', 'automatic-translator-addon-for-loco-translate') => [true, true],
                    __('Google Translate Widget Support', 'automatic-translator-addon-for-loco-translate') => [false, true],
                    __('Chrome Built-in AI Support', 'automatic-translator-addon-for-loco-translate') => [false, true],
                    __('AI Translator Support (Gemini)', 'automatic-translator-addon-for-loco-translate') => [false, true],
                    __('ChatGPT Translator Support', 'automatic-translator-addon-for-loco-translate') => [false, true],
                    __('DeepL Doc Translator Support', 'automatic-translator-addon-for-loco-translate') => [false, true],
                    __('Premium Support', 'automatic-translator-addon-for-loco-translate') => [false, true],
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