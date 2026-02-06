jQuery(function ($) {

    /* =========================
     * Terms show / hide
     * ========================= */
    const $termsLink = $('.atlt-see-terms');
    const $termsBox  = $('#termsBox');

    $termsLink.on('click', function (e) {
        e.preventDefault();

        const isVisible = $termsBox.toggle().is(':visible');
        $(this).html(isVisible ? 'Hide Terms' : 'See terms');
    });


    /* =========================
     * Plugin install button
     * ========================= */
    $(document).on('click', '.atlt-install-plugin', function (e) {

        e.preventDefault();
    
        let button   = $(this);
        let $wrapper = button.closest('.atlt-dashboard-addon-l');
        let slug     = button.data('slug');
        let nonce    = button.data('nonce');
        let action   = button.data('action') || 'install'; // ✅ FIX
    
        $wrapper.find('.atlt-install-message').empty();
    
        if (!slug || !nonce || typeof ajaxurl === 'undefined') {
            $wrapper.find('.atlt-install-message')
                .text('Missing required data. Please reload the page.');
            return;
        }

        // Whitelist of allowed plugin slugs - Security validation
        const allowedPlugins = [
            'autopoly-ai-translation-for-polylang-pro',
            'automatic-translations-for-polylang',
            'automatic-translate-addon-pro-for-translatepress',
            'automatic-translate-addon-for-translatepress',
            'translate-words'
        ];

        // Validate that the plugin slug is in the whitelist
        if (allowedPlugins.indexOf(slug) === -1) {
            $wrapper.find('.atlt-install-message')
                .text('This plugin is not allowed to be installed via this interface.');
            return;
        }
    
        const originalText = button.text();
    
        // ✅ Proper text handling
        button.text(action === 'activate' ? 'Activating...' : 'Installing...');
        $('.atlt-install-plugin').prop('disabled', true);
    
        $.post(ajaxurl, {
            action: 'atlt_install_plugin',
            slug: slug,
            plugin_action: action,
            _wpnonce: nonce
        }, function (response) {
    
            if (response && response.success) {
    
                const $container = button.closest('.atlt-dashboard-addon-l');
                button.remove();
                $container.find('.atlt-install-message').remove();
    
                $container.append(`
                    <span class="installed">Activated</span>
                `);
    
            }else {
                let errorMessage = 'Activation failed. Please try again.';
            
                // Special case for TranslatePress addons
                if (
                    slug === 'automatic-translate-addon-for-translatepress' ||
                    slug === 'automatic-translate-addon-pro-for-translatepress'
                ) {

                    
                    // Check if TranslatePress main plugin is active
                    
                    errorMessage = 'This addon depends on the TranslatePress plugin. Please install and activate TranslatePress before activating this addon.';

                    button
                        .text('Activate')
                        .data('action', 'activate')
                        .prop('disabled', false);
                
                    $wrapper.find('.atlt-install-message').text(errorMessage);
                    $('.atlt-install-plugin').not(button).prop('disabled', false);
          
                    return; 
                    
                } else {
                    // Normal case: try to get message from response
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response.data.errorMessage) {
                            errorMessage = response.data.errorMessage;
                        }
                    }
                }
            
                // Show the notice and re-enable the button
                $wrapper.find('.atlt-install-message').text(errorMessage);
                button.text(originalText).prop('disabled', false);
            }
            
    
            $('.atlt-install-plugin').not(button).prop('disabled', false);
        });
    });
    

});
