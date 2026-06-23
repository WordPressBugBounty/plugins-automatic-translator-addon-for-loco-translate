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
                    'Yandex Translate Widget Support' => [true, true],
                    'No API Key Required' => [true, true],
                    'Unlimited Translations' => [true, true],
                    'AI Translator Support (OpenAI)' => [true, true],
                    'Google Translate Widget Support' => [false, true],
                    'Chrome Built-in AI Support' => [false, true],
                    'AI Translator Support (Gemini)' => [false, true],
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