jQuery(function ($) {

    /* =========================
     * Settings: enable Save only on changes
     * ========================= */
    const $settingsForm = $('.atlt-dashboard-api-settings form').first();
    const $saveButton = $settingsForm
        .find('.atlt-dashboard-save-btn-container button[type="submit"]')
        .first();

    const isEditableField = ($field) => {
        if (!$field || !$field.length) return false;
        if ($field.prop('disabled')) return false;
        if (($field.attr('type') || '').toLowerCase() === 'hidden') return false;
        return true;
    };

    const readFieldValue = ($field) => {
        const type = (($field.attr('type') || '') + '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
            return $field.is(':checked') ? '1' : '0';
        }
        return ($field.val() ?? '').toString();
    };

    const getEditableFormState = ($form) => {
        const state = {};
        if (!$form || !$form.length) return state;

        $form.find('input[name], select[name], textarea[name]').each(function () {
            const $field = $(this);
            if (!isEditableField($field)) return;

            const name = $field.attr('name');
            if (!name) return;

            state[name] = readFieldValue($field);
        });

        return state;
    };

    const isEqualState = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    if ($settingsForm.length && $saveButton.length) {
        const initialState = getEditableFormState($settingsForm);

        const updateSaveButtonState = () => {
            const currentState = getEditableFormState($settingsForm);
            const hasChanges = !isEqualState(initialState, currentState);
            $saveButton.prop('disabled', !hasChanges);
        };

        // Save starts disabled and becomes enabled only after a change.
        $saveButton.prop('disabled', true);
        updateSaveButtonState();

        // Any edit toggles the "dirty" state.
        $settingsForm.on('input change', 'input[name], select[name], textarea[name]', updateSaveButtonState);

        // Prevent submitting the form when nothing changed (e.g. Enter key).
        $settingsForm.on('submit', function (e) {
            const currentState = getEditableFormState($settingsForm);
            const hasChanges = !isEqualState(initialState, currentState);

            // Allow non-save submits (e.g. "Reset" button).
            const $submitter = (e.originalEvent && e.originalEvent.submitter)
                ? $(e.originalEvent.submitter)
                : $(document.activeElement);
            const isSaveSubmit = $submitter && $submitter.length && $submitter.is($saveButton);

            if (!hasChanges && (isSaveSubmit || !$submitter || !$submitter.length)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    }

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
            'translate-words',
            'wpml-translation-check',
            'automlp-ai-translation-for-wpml-pro'
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
                    
                    errorMessage = 'Please activate TranslatePress Multilingual first.';

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
    

    /* =========================
     * Provider enable / disable
     * ========================= */
    $(document).on('change', '.atlt-provider-toggle', function () {
        const $toggle = $(this);
        const provider = $toggle.data('provider');

        if (!provider) {
            return;
        }

        // Skip if toggle is disabled (Pro providers).
        if ($toggle.prop('disabled')) {
            return;
        }

        const enabled = $toggle.is(':checked') ? 1 : 0;
        const nonce = (window.atltDashboard && window.atltDashboard.nonce) ? window.atltDashboard.nonce : '';
        const ajaxUrl = (window.atltDashboard && window.atltDashboard.ajax_url) ? window.atltDashboard.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

        if (!nonce || !ajaxUrl) {
            return;
        }

        $.post(ajaxUrl, {
            action: 'atlt_toggle_provider',
            nonce: nonce,
            provider: provider,
            enabled: enabled
        }).done(function (response) {
            if (!response || !response.success) {
                $toggle.prop('checked', !enabled);
            }
        }).fail(function () {
            $toggle.prop('checked', !enabled);
        });
    });

});
