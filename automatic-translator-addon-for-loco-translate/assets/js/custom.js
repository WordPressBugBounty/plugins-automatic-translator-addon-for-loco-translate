(function (window, $) {
    // get Loco Translate global object.  
    const locoConf = window.locoConf;
    // get plugin configuration object.
    const configData = window.extradata;
    const { ajax_url: ajaxUrl, nonce: nonce, ATLT_URL: ATLT_URL, extra_class: rtlClass, has_openai_api_key: hasOpenAiKey, atlt_dashboard_provider_toggles: atltDashboardProviderToggles = {} } = configData || {};
    let openAISourceValues = {};

    window.ATLT = window.ATLT || {};
    window.ATLT.formatNumberShort = function (num) {
        num = parseInt(num, 10);
        if (isNaN(num)) return num;
        if (num >= 1e6) return (num / 1e6).toFixed(1) + 'M';
        if (num >= 1e3) return (num / 1e3).toFixed(1) + 'K';
        return num;
    };
    onLoad();
    function onLoad() {
        if (locoConf && locoConf.conf) {
            const { conf } = locoConf;
            // get all string from loco translate po data object
            //  const allStrings = conf.podata.slice(1);
            const allStrings = locoConf.conf.podata;
            allStrings.shift();
            const { locale, project } = conf;
            // create a project ID for later use in ajax request.
            const projectId = generateProjectId(project, locale);
            // create strings modal
            createStringsModal(projectId, 'yandex');
            createStringsModal(projectId, 'openai');
            addStringsInModal(allStrings, 'yandex');
            addStringsInModal(allStrings, 'openai');

            const filterstring = filterRawObject(allStrings);
            openAISourceValues = Object.fromEntries(
                filterstring.map((item, index) => [String(index + 1), String((item && item.source) || '').trim().replace(/\s+/g, ' ')])
            );
        }
    }

    function initialize() {

        const { conf } = locoConf;
        const { locale, project } = conf;
        const langMapping = {
            'bel': 'be',
            'snd': 'sd',
            'nb': 'no',
            'nn': 'no'
            // Add more cases as needed
        };
        // Embbed Auto Translate button inside Loco Translate editor
        if ($("#loco-editor nav").find("#cool-auto-translate-btn").length === 0) {
            addAutoTranslationBtn();
        }

        //append auto translate settings model
        settingsModel();

        // on auto translate button click settings model
        $("#cool-auto-translate-btn").on("click", openSettingsModel);


        $("button.icon-robot[data-loco='auto']").on("click", onAutoTranslateClick);

        $("#atlt_yandex_translate_btn").on("click", function () {
            onYandexTranslateClick(locale, langMapping);
        });

        $("#atlt_openai_translate_btn").on("click", function () {
            onOpenAITranslateClick(locale, langMapping);
        });

        // save string inside cache for later use
        $(".atlt_save_strings").on("click", onSaveClick);

        $(document).on('click.atltDismissAlert', '.atlt_custom_model .atlt-modern-alert-close', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.atlt-modern-alert').stop(true, true).fadeOut(150);
        });
    }

    function destroyYandexTranslator() {
        if (typeof window.atltDestroyYandexTranslation === 'function') {
            window.atltDestroyYandexTranslation();
        } else {
            $(document).trigger('atlt:yandex-cancel');
        }
        $('.yt-button__icon.yt-button__icon_type_right').trigger('click');
        $('.atlt_custom_model.yandex-widget-container').find('.atlt_string_container').scrollTop(0);

        const progressContainer = $('.modal-body.yandex-widget-body').find('.atlt_translate_progress');
        progressContainer.hide();
        progressContainer.find('.progress-wrapper').hide();
        progressContainer.find('#myProgressBar').css('width', '0');
        progressContainer.find('#progressText').text('0%');
    }

    function addStringsInModal(allStrings, type) {
        const plainStrArr = filterRawObject(allStrings);
        if (plainStrArr.length > 0) {
            printStringsInPopup(plainStrArr, type);
        } else {
            $("#ytWidget").hide();
            $(".notice-container")
                .addClass('notice inline notice-warning atlt-modern-alert atlt-modern-alert--warning')
                .show()
                .html("There is no plain string available for translations.");
            $(".atlt_string_container, .choose-lang, .translator-widget, .notice-info, .is-dismissible,.atlt_actions > .atlt_save_strings").hide();
        }
    }

    // create project id for later use inside ajax request.
    function generateProjectId(project, locale) {
        const { domain } = project || {};
        const { lang, region } = locale;
        return project ? `${domain}-${lang}-${region}` : `temp-${lang}-${region}`;
    }

    function prepareProviderModal(modelContainer, defaultlang, langugeName, providerClass, providerLabel) {
        modelContainer.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
        modelContainer.find(".atlt_stats").hide();
        localStorage.setItem("lang", defaultlang);
        localStorage.setItem("langName", langugeName);
        modelContainer.find(`.${providerClass}-translation-info`)
            .text(`Translating Strings into ${langugeName || 'Selected Language'} Using ${providerLabel}`);
    }

    // Yandex click handler
    function onYandexTranslateClick(locale, langMapping) {
        const defaultcode = locale.lang || null;
        const langugeName = locale.label || null;
        let defaultlang = '';
        defaultlang = langMapping[defaultcode] || defaultcode;
        let modelContainer = $(`div#atlt_strings_model_yandex.yandex-widget-container`);

        prepareProviderModal(modelContainer, defaultlang, langugeName, 'yandex', 'Yandex');
        const supportedLanguages = ['kir', 'he', 'af', 'jv', 'no', 'am', 'ar', 'az', 'ba', 'be', 'bg', 'bn', 'bs', 'ca', 'ceb', 'cs', 'cy', 'da', 'de', 'el', 'en', 'eo', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gd', 'gl', 'gu', 'he', 'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'is', 'it', 'ja', 'jv', 'ka', 'kk', 'km', 'kn', 'ko', 'ky', 'la', 'lb', 'lo', 'lt', 'lv', 'mg', 'mhr', 'mi', 'mk', 'ml', 'mn', 'mr', 'mrj', 'ms', 'mt', 'my', 'ne', 'nl', 'no', 'pa', 'pap', 'pl', 'pt', 'ro', 'ru', 'si', 'sk', 'sl', 'sq', 'sr', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'tl', 'tr', 'tt', 'udm', 'uk', 'ur', 'uz', 'vi', 'xh', 'yi', 'zh'];

        if (!supportedLanguages.includes(defaultlang)) {
            closeProviderModal();
            modelContainer.find(".notice-container")
                .addClass('notice inline notice-warning atlt-modern-alert atlt-modern-alert--warning')
                .show()
                .html("Yandex Automatic Translator Does not support this language.");
            modelContainer.find(".atlt_string_container, .choose-lang, .atlt_save_strings, #ytWidget, .translator-widget, .notice-info, .is-dismissible").hide();
            modelContainer.css("display", "flex").hide().fadeIn("slow");
        } else {
            closeProviderModal();
            modelContainer.css("display", "flex").hide().fadeIn("slow", function () {
                // Start Yandex automatically once popup is visible
                $(document).trigger('atlt:yandex-start');
            });
        }


    }

    function onOpenAITranslateClick(locale, langMapping) {
        const defaultcode = locale.lang || null;
        const langugeName = locale.label || null;
        const defaultlang = langMapping[defaultcode] || defaultcode;
        let modelContainer = $(`div#atlt_strings_model_openai.openai-widget-container`);

        closeProviderModal();
        prepareProviderModal(modelContainer, defaultlang, langugeName, 'openai', 'OpenAI');
        modelContainer.css("display", "flex").hide().fadeIn("slow", function () {
            window.ATLT.openaiFlow.startOpenAITranslation(locale, modelContainer, openAISourceValues, ajaxUrl, nonce);
        });

    }
    // parse all translated strings and pass to save function
    function collectTranslatedRows(container) {
        const tableRows = container.find(".atlt_strings_table tbody tr");
        const translatedObj = [];

        const rpl = {
            '"% s"': '"%s"',
            '"% d"': '"%d"',
            '"% S"': '"%s"',
            '"% D"': '"%d"',
            '% s': ' %s ',
            '% S': ' %s ',
            '% d': ' %d ',
            '% D': ' %d ',
            '٪ s': ' %s ',
            '٪ S': ' %s ',
            '٪ d': ' %d ',
            '٪ D': ' %d ',
            '٪ س': ' %s ',
            '%S': ' %s ',
            '%D': ' %d ',
            '% %': '%%'
        };
        const regex = /(\%\s*\d+\s*\$?\s*[a-z0-9])/gi;
        const normalizePlaceholders = (str) => str.replace(regex, match => match.replace(/\s/g, '').toLowerCase());

        tableRows.each(function () {
            const source = $(this).find("td.source").text();
            const target = $(this).find("td.target").text();

            const improvedTarget1 = strtr(target, rpl);
            const improvedSource1 = strtr(source, rpl);

            const improvedTarget = normalizePlaceholders(improvedTarget1);
            const improvedSource = normalizePlaceholders(improvedSource1);

            translatedObj.push({
                "source": improvedSource,
                "target": improvedTarget
            });
        });

        return translatedObj;
    }

    function buildTranslationMeta(container, translatedObj) {
        // Safely access nested properties without optional chaining
        let pluginOrTheme = '';
        let pluginOrThemeName = '';

        if (locoConf && locoConf.conf && locoConf.conf.project && locoConf.conf.project.bundle) {
            pluginOrTheme = locoConf.conf.project.bundle.split('.')[0];

            if (pluginOrTheme === 'theme') {
                pluginOrThemeName = locoConf.conf.project.domain || '';
            } else {
                const match = locoConf.conf.project.bundle.match(/^[^.]+\.(.*?)(?=\/)/);
                pluginOrThemeName = match ? match[1] : '';
            }
        }

        const time_taken = container.data('translation-time') || 0;
        const translation_provider = container.data('translation-provider');
        const { lang, region } = locoConf.conf.locale;
        const target_language = region ? `${lang}_${region}` : lang;
        const totalCharacters = translatedObj.reduce((sum, item) => sum + item.source.length, 0);
        const totalStrings = translatedObj.length;

        return {
            time_taken: time_taken,
            translation_provider: translation_provider,
            pluginORthemeName: pluginOrThemeName,
            target_language: target_language,
            total_characters: totalCharacters,
            total_strings: totalStrings,
        };
    }

    // parse all translated strings and pass to save function
    function onSaveClick() {
        const container = $(this).closest('.atlt_custom_model');
        const translatedObj = collectTranslatedRows(container);
        const translationData = buildTranslationMeta(container, translatedObj);
        var projectId = container.find("input[id='project_id']").val();

        //  Save Translated Strings
        saveTranslatedStrings(translatedObj, projectId, translationData);

        $(".atlt_custom_model").fadeOut("slow");

        $("html").addClass("merge-translations");
        updateLocoModel();
    }

    function onAutoTranslateClick(e) {
        if (e.originalEvent !== undefined) {
            var attempts = 0;
            var checkModal = setInterval(function () {
                attempts++;
                if (attempts >= 100) {
                    clearInterval(checkModal);
                    return;
                }
                var locoModal = $(".loco-modal");
                var locoBatch = locoModal.find("#loco-apis-batch");
                var locoTitle = locoModal.find(".ui-dialog-titlebar .ui-dialog-title");

                if (locoBatch.length && !locoModal.is(":hidden")) {
                    locoModal.removeClass("addtranslations");
                    locoBatch.find("select#auto-api").show();
                    locoBatch.find("a.icon-help, a.icon-group").show();
                    locoBatch.find("#loco-job-progress").show();
                    locoTitle.html("Auto-translate this file");
                    locoBatch.find("button.button-primary span").html("Translate");

                    var opt = locoBatch.find("select#auto-api option").length;

                    if (opt === 1) {
                        locoBatch.find(".noapiadded").remove();
                        locoBatch.removeClass("loco-alert");
                        locoBatch.find("form").hide();
                        locoBatch.addClass("loco-alert");
                        locoTitle.html("No translation APIs configured");
                        locoBatch.append(`<div class='noapiadded'>
                            <p>Add automatic translation services in the plugin settings.<br>or<br>Use <strong>Auto Translate</strong> addon button.</p>
                            <nav>
                                <a href="${(window.extradata && extradata.loco_settings_url) ? extradata.loco_settings_url : ''}" class='button button-link has-icon icon-cog'>Settings</a>
                                <a href='https://localise.biz/wordpress/plugin/manual/providers' class='button button-link has-icon icon-help' target='_blank'>Help</a>
                                <a href='https://localise.biz/wordpress/translation?l=de-DE' class='button button-link has-icon icon-group' target='_blank'>Need a human?</a>
                            </nav>
                        </div>`);
                    }
                    clearInterval(checkModal);
                }
            }, 100); // check every 100ms
        }
    }

    // update Loco Model after click on merge translation button
    function updateLocoModel() {
        var attempts = 0;
        var checkModal = setInterval(function () {
            attempts++;
            if (attempts >= 100) {
                clearInterval(checkModal);
                return;
            }
            var locoModel = $('.loco-modal');
            var locoModelApisBatch = $('.loco-modal #loco-apis-batch');
            if (locoModel.length && // model exists check
                locoModel.attr("style").indexOf("none") <= -1 && // has not display none
                locoModel.find('#loco-job-progress').length // element loaded 
            ) {
                $("html").removeClass("merge-translations");
                locoModelApisBatch.find("a.icon-help, a.icon-group, #loco-job-progress").hide();
                locoModelApisBatch.find("select#auto-api").hide();
                var currentState = $("select#auto-api option[value='loco_auto']").prop("selected", "selected");
                locoModelApisBatch.find("select#auto-api").val(currentState.val());
                locoModel.find(".ui-dialog-titlebar .ui-dialog-title").html("Step 3 - Add Translations into Editor and Save");
                locoModelApisBatch.find("button.button-primary span").html("Start Adding Process");
                locoModelApisBatch.find("button.button-primary").on("click", function () {
                    $(this).find('span').html("Adding...");
                });
                locoModel.addClass("addtranslations");
                $('.noapiadded').remove();
                locoModelApisBatch.find("form").show();
                locoModelApisBatch.removeClass("loco-alert");
                clearInterval(checkModal);
            }
        }, 200); // check every 200ms
    }
    // filter string based upon type
    function filterRawObject(rawArray) {
        return rawArray.filter((item) => {
            if (item.source && !item.target) {
                if (ValidURL(item.source) || isHTML(item.source) || isSpecialChars(item.source) || isEmoji(item.source) || item.source.includes('#')) {
                    return false;
                } else {
                    return true;
                }
            }
            return false;
        });
    }
    // detect String contain URL
    function ValidURL(str) {
        var pattern = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
        return pattern.test(str);
    }
    // detect Valid HTML in string
    function isHTML(str) {
        var rgex = /<(?=.*? .*?\/ ?>|br|hr|input|!--|wbr)[a-z]+.*?>|<([a-z]+).*?<\/\1>/i;
        return rgex.test(str);
    }
    //  check special chars in string
    function isSpecialChars(str) {
        var rgex = /[@^{}|<>]/g;
        return rgex.test(str);
    }
    //  check Emoji chars in string
    function isEmoji(str) {
        var ranges = [
            '(?:[\u2700-\u27bf]|(?:\ud83c[\udde6-\uddff]){2}|[\ud800-\udbff][\udc00-\udfff]|[\u0023-\u0039]\ufe0f?\u20e3|\u3299|\u3297|\u303d|\u3030|\u24c2|\ud83c[\udd70-\udd71]|\ud83c[\udd7e-\udd7f]|\ud83c\udd8e|\ud83c[\udd91-\udd9a]|\ud83c[\udde6-\uddff]|[\ud83c[\ude01-\ude02]|\ud83c\ude1a|\ud83c\ude2f|[\ud83c[\ude32-\ude3a]|[\ud83c[\ude50-\ude51]|\u203c|\u2049|[\u25aa-\u25ab]|\u25b6|\u25c0|[\u25fb-\u25fe]|\u00a9|\u00ae|\u2122|\u2139|\ud83c\udc04|[\u2600-\u26FF]|\u2b05|\u2b06|\u2b07|\u2b1b|\u2b1c|\u2b50|\u2b55|\u231a|\u231b|\u2328|\u23cf|[\u23e9-\u23f3]|[\u23f8-\u23fa]|\ud83c\udccf|\u2934|\u2935|[\u2190-\u21ff])' // U+1F680 to U+1F6FF
        ];
        return str.match(ranges.join('|'));
    }
    /**
     * Replace occurrences of strings within a string.
     * Can be called in two ways:
     * 1. strtr(input, replacements) - Replaces multiple substrings using an object of key-value pairs.
     * 2. strtr(input, search, replacement) - Replaces all occurrences of 'search' with 'replacement'.
     *
     * @param {string} input - The string being translated.
     * @param {Object|string} replacements - An object of replacements, or the search string.
     * @param {string} [replacement] - The replacement string if the second argument is a string.
     * @returns {string|undefined} The translated string.
     */
    function strtr(input, replacements, replacement) {
        return !!input && {
            2: function () {
                for (var key in replacements) {
                    input = strtr(input, key, replacements[key]);
                }
                return input;
            },
            3: function () {
                return input.replace(RegExp(replacements, 'g'), replacement);
            },
            0: function () {
                return;
            }
        }[arguments.length]();
    }

    // Save translated strings in the cache using ajax requests in parts.
    function saveTranslatedStrings(translatedStrings, projectId, translationData) {
        // Check if translatedStrings is not empty and has data
        if (translatedStrings && translatedStrings.length > 0) {
            // Define the batch size for ajax requests
            const batchSize = 2500;

            // Iterate over the translatedStrings in batches
            for (let i = 0; i < translatedStrings.length; i += batchSize) {
                // Extract the current batch
                const batch = translatedStrings.slice(i, i + batchSize);
                // Determine the part based on the batch position
                const part = `-part-${Math.ceil(i / batchSize)}`;
                // Send ajax request for the current batch
                sendBatchRequest(batch, projectId, part, translationData);

            }

        }
    }


    // send ajax request and save data.
    function sendBatchRequest(stringData, projectId, part, translationData) {
        const data = {
            'action': 'atlt_save_all_translations',
            'data': JSON.stringify(stringData),
            'part': part,
            'project-id': projectId,
            'wpnonce': nonce,
            'translation_data': JSON.stringify(translationData)
        };

        jQuery.post(ajaxUrl, data, function (response) {
            if (!response || !response.success) {
                console.error(response.data.message || 'Saving translations failed. Please retry.');
                return;
            }
            $('#loco-editor nav').find('button').each(function (i, el) {
                var id = el.getAttribute('data-loco');
                if (id == "auto") {
                    if ($(el).hasClass('model-opened')) {
                        $(el).removeClass('model-opened');
                    }
                    $(el).addClass('model-opened');
                    $(el).trigger("click");
                }
            });
        }).fail(function () {
            console.error('Request failed.');
        });
    }

    // integrates auto traslator button in editor
    function addAutoTranslationBtn() {
        // check if button already exists inside translation editor
        const existingBtn = $("#loco-editor nav").find("#cool-auto-translate-btn");
        if (existingBtn.length > 0) {
            existingBtn.remove();
        }
        const locoActions = $("#loco-editor nav").find("#loco-actions");
        const autoTranslateBtn = $('<fieldset><button id="cool-auto-translate-btn" class="button has-icon icon-translate">Auto Translate</button></fieldset>');
        // append custom created button.
        locoActions.append(autoTranslateBtn);
    }
    function isProviderModalOpen() {
        return $("#atlt-provider-overlay").is(":visible");
    }

    function isProviderCardSelectable($card) {
        if (!$card || !$card.length) {
            return false;
        }
        const attr = $card.attr('data-provider-selectable');
        if (attr === undefined || attr === null || attr === '') {
            return true;
        }
        return attr === '1' || attr === 'true';
    }

    function selectProviderCard($card) {
        if (!$card || !$card.length) {
            return;
        }
        if (!isProviderCardSelectable($card)) {
            return;
        }
        const $root = $("#atlt-dialog");
        $root.find(".atlt-provider-card").removeClass("is-selected").attr("aria-checked", "false");
        $card.addClass("is-selected").attr("aria-checked", "true");
    }

    function getProviderStartTarget($card) {
        if (!$card || !$card.length) {
            return null;
        }
        const key = $card.data("provider");
        const $wrap = $('#atlt-dialog .atlt-hidden-action[data-provider="' + key + '"]');
        if (!$wrap.length) {
            return null;
        }
        const $btn = $wrap.find("button").first();
        if ($btn.length) {
            // when a provider isn't actually usable (e.g. missing API key).
            if ($btn.prop("disabled") || $btn.attr("aria-disabled") === "true") {
                return null;
            }
            return { type: "click", $el: $btn };
        }
        return null;
    }

    function refreshProviderStartButtonState() {
        const $start = $("#atlt-provider-start");
        if (!$start.length) {
            return;
        }
        const $selected = $("#atlt-dialog").find(".atlt-provider-card.is-selected").first();
        if ($selected.length && !isProviderCardSelectable($selected)) {
            $start.prop("disabled", true);
            return;
        }
        const target = getProviderStartTarget($selected);
        $start.prop("disabled", !target);
    }

    function startSelectedProvider() {
        const $selected = $("#atlt-dialog").find(".atlt-provider-card.is-selected").first();
        if ($selected.length && !isProviderCardSelectable($selected)) {
            return;
        }
        const target = getProviderStartTarget($selected);
        if (!target) {
            return;
        }
        if (target.type === "click") {
            target.$el.trigger("click");
            return;
        }
        if (target.type === "href" && target.href) {
            if (target.target === "_blank") {
                window.open(target.href, "_blank", "noopener,noreferrer");
            } else {
                window.location.href = target.href;
            }
        }
    }

    function openProviderModal() {
        const $overlay = $("#atlt-provider-overlay");
        if (!$overlay.length) {
            return;
        }
        $("body").addClass("atlt-provider-modal-open");
        $overlay.css("display", "flex").hide().fadeIn(120);

        const $cards = $("#atlt-dialog .atlt-provider-card");
        $cards.removeClass("is-selected").attr("aria-checked", "false");
        const $selectableCards = $cards.filter(function () {
            return isProviderCardSelectable($(this));
        });
        const $y = $selectableCards.filter('[data-provider="yandex"]').first();
        const $pick = $y.length ? $y : $selectableCards.first();
        if ($pick.length) {
            selectProviderCard($pick);
        }
        refreshProviderStartButtonState();
    }

    function closeProviderModal() {
        const $overlay = $("#atlt-provider-overlay");
        if (!$overlay.length) {
            return;
        }
        $overlay.fadeOut(120);
        $("body").removeClass("atlt-provider-modal-open");
    }

    function bindProviderModalEvents() {
        $(document).off('click.atltProviderCard').on('click.atltProviderCard', '#atlt-dialog .atlt-provider-card', function (e) {
            const $interactiveTarget = $(e.target).closest('a, button, input, select, textarea');
            if ($interactiveTarget.length && !$interactiveTarget.is(this)) {
                return;
            }
            e.preventDefault();
            selectProviderCard($(this));
            refreshProviderStartButtonState();
        });

        $(document).off('keydown.atltProviderCard').on('keydown.atltProviderCard', '#atlt-dialog .atlt-provider-card', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectProviderCard($(this));
                refreshProviderStartButtonState();
            }
        });

        $(document).off('click.atltProviderStart').on('click.atltProviderStart', '#atlt-provider-start', function (e) {
            e.preventDefault();
            startSelectedProvider();
        });

        $(document).off('click.atltProviderClose').on('click.atltProviderClose', '#atlt-dialog .atlt-provider-selector__close', function (e) {
            e.preventDefault();
            closeProviderModal();
        });

        $(document).off('click.atltProviderOverlay').on('click.atltProviderOverlay', '#atlt-provider-overlay', function (e) {
            if (e.target && e.target.id === 'atlt-provider-overlay') {
                closeProviderModal();
            }
        });

        $(document).off('keydown.atltProviderEsc').on('keydown.atltProviderEsc', function (e) {
            if (e.key === 'Escape' && isProviderModalOpen()) {
                closeProviderModal();
            }
        });
    }

    // open settings model on auto translate button click
    function openSettingsModel() {
        openProviderModal();
    }

    function closeAtltModal($modal) {
        if ($modal.hasClass('yandex-widget-container')) {
            destroyYandexTranslator();
        }
        if ($modal.hasClass('openai-widget-container')) {
            const stopHandler = $modal.data('atlt-openai-stop-handler');
            if (typeof stopHandler === 'function') {
                stopHandler();
            }
        }
        $modal.fadeOut("slow");
    }

    // String translate modal close handlers (works for Yandex + OpenAI modals)
    $(window).on('click', function (event) {
        const modal = event.target;
        if (modal && modal.classList && modal.classList.contains('atlt_custom_model')) {
            closeAtltModal($(modal));
        }
    });

    $(document).on('click', '.atlt_custom_model .modal-header .close', function () {
        closeAtltModal($(this).closest('.atlt_custom_model'));
    });


    // get object and append inside the popup
    function printStringsInPopup(jsonObj, type) {
        let totalTChars = 0;
        let index = 1;
        const $modal = $(`.${type}-widget-container`);
        const $tbody = $modal.find('.atlt_strings_table > tbody.atlt_strings_body');
        $tbody.empty();

        if (jsonObj) {
            for (const key in jsonObj) {
                if (jsonObj.hasOwnProperty(key)) {
                    const element = jsonObj[key];
                    const sourceText = element.source.trim();

                    if (sourceText !== '') {
                        if ((type === "yandex") || (key <= 2500)) {
                            const $row = $('<tr></tr>').attr('id', String(key));
                            $row.append($('<td></td>').text(index));
                            $row.append(
                                $('<td></td>').addClass('notranslate source').text(sourceText)
                            );

                            if (type === "yandex") {
                                $row.append(
                                    $('<td></td>')
                                        .attr('translate', 'yes')
                                        .addClass('target translate')
                                        .text(sourceText)
                                );
                            } else {
                                $row.append($('<td></td>').addClass('target translate'));
                            }

                            $tbody.append($row);
                            index++;
                            totalTChars += sourceText.length;
                        }
                    }
                }
            }

            $modal.find('.atlt_stats .totalChars').text(window.ATLT.formatNumberShort(totalTChars));
        }
    }

    function settingsModel() {
        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const icons = {
            yandex: extradata['yt_preview'],
            google: extradata['gt_preview'],
            deepl: extradata['dpl_preview'],
            chatgpt: extradata['chatGPT_preview'],
            gemini: extradata['geminiAI_preview'],
            openai: extradata['openai_preview'],
            chrome: extradata['chromeAi_preview'],
            docs: extradata['document_preview'],
            error: extradata['error_preview'],
        };

        const url = 'https://locoaddon.com/docs/';
        const pricingUrl = 'https://locoaddon.com/pricing/';
        const ATLT_IMG = (key) => escapeHtml(ATLT_URL + 'assets/images/' + icons[key]);
        const DOC_ICON = `<img src="${ATLT_IMG('docs')}" width="20" alt="Docs">`;
        const ERROR_ICON = `<img src="${ATLT_IMG('error')}" alt="error" style="height:16px; vertical-align:middle; margin-right:5px;">`;
        const rows = [
            {
                key: 'yandex',
                name: 'Yandex Translate',
                icon: 'yandex',
                info: 'https://translate.yandex.com/',
                btn: `<button id="atlt_yandex_translate_btn" class="atlt-provider-btn translate">Translate</button>`,
                doc: `${url}translate-plugin-theme-via-yandex-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_yandex`,
                enabled: atltDashboardProviderToggles['yandex'] !== false,
                selectable: true,
                cta: ''
            },
            {
                key: 'openai',
                name: 'OpenAI Translate',
                icon: 'openai',
                info: 'https://locoaddon.com/docs/pro-plugin/how-to-use-open-ai-to-translate-plugins-or-themes/',
                btn: `${hasOpenAiKey
                    ? `<button id="atlt_openai_translate_btn" class="atlt-provider-btn translate">Translate</button>`
                    : `<button id="atlt_openai_btn" class="atlt-provider-btn error" disabled aria-disabled="true" title="Add an OpenAI API key to enable OpenAI translations">${ERROR_ICON}API Key Required</button>`}`,
                doc: `${url}open-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_openai`,
                enabled: atltDashboardProviderToggles['openai'] !== false,
                selectable: hasOpenAiKey,
                cta: hasOpenAiKey
                    ? ''
                    : '<a href="admin.php?page=loco-atlt-dashboard&tab=settings" target="_blank" class="atlt-provider-card__buypro atlt-provider-card__buypro--info" rel="noopener noreferrer">Add API Key</a>'
            },
            {
                key: 'google',
                name: 'Google Translate',
                icon: 'google',
                info: 'https://translate.google.com/',
                btn: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_google" target="_blank"><button id="atlt_google_translate_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}auto-translations-via-google-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_google`,
                enabled: true,
                selectable: false,
                cta: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_google" target="_blank" class="atlt-provider-card__buypro" rel="noopener noreferrer">Buy Pro</a>`
            },
            {
                key: 'chrome',
                name: 'Chrome Built-in AI',
                icon: 'chrome',
                info: 'https://developer.chrome.com/docs/ai/translator-api',
                btn: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chrome" target="_blank"><button id="ChromeAiTranslator_settings_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}how-to-use-chrome-ai-auto-translations/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_chrome`,
                enabled: true,
                selectable: false,
                cta: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chrome" target="_blank" class="atlt-provider-card__buypro" rel="noopener noreferrer">Buy Pro</a>`
            },
            {
                key: 'chatgpt',
                name: 'ChatGPT Translate',
                icon: 'chatgpt',
                info: 'https://locoaddon.com/docs/chatgpt-ai-translations-wordpress/',
                btn: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chatgpt" target="_blank"><button id="atlt_chatGPT_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}chatgpt-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_chatgpt`,
                enabled: true,
                selectable: false,
                cta: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chatgpt" target="_blank" class="atlt-provider-card__buypro" rel="noopener noreferrer">Buy Pro</a>`
            },
            {
                key: 'gemini',
                name: 'Gemini AI Translate',
                icon: 'gemini',
                info: 'https://locoaddon.com/docs/pro-plugin/how-to-use-gemini-ai-to-translate-plugins-or-themes/',
                btn: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_gemini" target="_blank"><button id="atlt_geminiAI_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_gemini`,
                enabled: true,
                selectable: false,
                cta: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_gemini" target="_blank" class="atlt-provider-card__buypro" rel="noopener noreferrer">Buy Pro</a>`
            },
            {
                key: 'deepl',
                name: 'DeepL Translate',
                icon: 'deepl',
                info: 'https://www.deepl.com/en/translator',
                btn: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_deepl" target="_blank"><button id="atlt_deepl_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}translate-via-deepl-doc-translator/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_deepl`,
                enabled: true,
                selectable: false,
                cta: `<a href="${pricingUrl}?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_deepl" target="_blank" class="atlt-provider-card__buypro" rel="noopener noreferrer">Buy Pro</a>`
            }
        ];

        const enabledRows = rows.filter((row) => row.enabled !== false);
        const hiddenActionsHtml = enabledRows.map((row) => `
            <div class="atlt-hidden-action" data-provider="${escapeHtml(row.key)}">${row.btn}</div>
        `).join('');

        const cardsHtml = enabledRows.map((row) => {
            const isSelectable = row.selectable !== false;
            const ctaHtml = row.cta || '';
            return `
            <div class="atlt-provider-card${isSelectable ? '' : ' atlt-provider-card--pro is-disabled'}" data-provider="${escapeHtml(row.key)}" role="radio" aria-checked="false" tabindex="${isSelectable ? '0' : '-1'}" aria-disabled="${isSelectable ? 'false' : 'true'}" data-provider-selectable="${isSelectable ? '1' : '0'}">
                <div class="atlt-provider-card__top">
                    <a class="atlt-provider-card__logo" href="${escapeHtml(row.info)}" target="_blank" rel="noopener noreferrer">
                        <img src="${ATLT_IMG(row.icon)}" class="atlt-provider-card__icon" alt="${escapeHtml(row.name)}">
                    </a>
                    <div class="atlt-provider-card__name">${escapeHtml(row.name)}</div>
                    <span class="atlt-provider-card__radio" aria-hidden="true"></span>
                </div>
                <div class="atlt-provider-card__footer">
                    <a class="atlt-provider-card__docs" href="${escapeHtml(row.doc)}" target="_blank" rel="noopener noreferrer">${DOC_ICON}<span>Docs</span></a>
                    ${ctaHtml}
                </div>
            </div>`;
        }).join('');

        const modelHTML = `
            <div class="atlt-provider-overlay ${rtlClass || ''}" id="atlt-provider-overlay" style="display:none;">
                <div class="atlt-provider-modal atlt-provider-selector" id="atlt-dialog" role="dialog" aria-modal="true" aria-label="Select Translation Provider">
                    <button type="button" class="atlt-provider-selector__close" aria-label="Close">&times;</button>
                    <div class="atlt-provider-selector__header">
                    <p class="atlt-provider-selector__step">Step 1 of 2</p>
                        <h2 class="atlt-provider-selector__title">Select Translation Provider</h2>
                        <p class="atlt-provider-selector__subtitle">Choose the translation provider you want to use for this translation batch.</p>
                    </div>
                    <div class="atlt-provider-selector__grid">${cardsHtml}</div>
                    <div class="atlt-provider-selector__footer">
                        <button type="button" class="button button-primary atlt-provider-selector__start" id="atlt-provider-start" disabled>Start Translation</button>
                    </div>
                    <div class="atlt-provider-hidden-actions" aria-hidden="true">${hiddenActionsHtml}</div>
                </div>
            </div>
        `;

        $("#atlt-provider-overlay").remove();
        $("#atlt-dialog").remove();
        $("body").append(modelHTML);
        bindProviderModalEvents();
    }

    // modal to show strings
    function createStringsModal(projectId, widgetType) {
        // Set wrapper, header, and body classes based on widgetType
        let { wrapperCls, headerCls, bodyCls, footerCls } = getWidgetClasses(widgetType);
        let modelHTML = `
            <div id="atlt_strings_model_${widgetType}" class="modal atlt_custom_model  ${wrapperCls} ${rtlClass}">
                <div class="modal-content">
                    ${modelHeaderHTML(widgetType, headerCls)}   
                    ${modelBodyHTML(widgetType, bodyCls)}   
                    ${modelFooterHTML(widgetType, footerCls)}   
            </div></div>`;

        const $modal = $(modelHTML);
        const $projectInput = $('<input>', { type: 'hidden', id: 'project_id' });
        $projectInput.val(projectId);
        $modal.find('.modal-content').prepend($projectInput);
        const langName = localStorage.getItem('langName') || 'Selected Language';
        const providerLabel = widgetType === 'yandex' ? 'Yandex' : 'OpenAI';
        $modal.find(`.${widgetType}-translation-info`)
            .text(`Translating Strings into ${langName} Using ${providerLabel}`);
        $('body').append($modal);
    }

    // Get widget classes based on widgetType
    function getWidgetClasses(widgetType) {
        let wrapperCls = '';
        let headerCls = '';
        let bodyCls = '';
        let footerCls = '';
        switch (widgetType) {
            case 'openai':
                wrapperCls = 'openai-widget-container';
                headerCls = 'openai-widget-header';
                bodyCls = 'openai-widget-body';
                footerCls = 'openai-widget-footer';

                break;
            case 'yandex':
            default:
                // Default class if widgetType doesn't match any case
                wrapperCls = 'yandex-widget-container';
                headerCls = 'yandex-widget-header';
                bodyCls = 'yandex-widget-body';
                footerCls = 'yandex-widget-footer';
                break;
        }
        return { wrapperCls, headerCls, bodyCls, footerCls };
    }
    function modelBodyHTML(widgetType, bodyCls) {
        const HTML = `
        <div class="modal-body  ${bodyCls}">
            <div class="atlt_translate_progress my_translate_progress">
                <div class="my_translate_progress_content">
                    <div class="atlt-progress-line">
                        Automatic translation is in progress<span class="atlt-animated-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
                    </div>
                    <div class="atlt-progress-line">
                        It will take a few minutes, enjoy ☕ coffee in this time!
                    </div>
                    <div class="atlt-progress-spacer" aria-hidden="true"></div>
                    <div class="atlt-progress-line">
                        Please do not leave this window or browser tab while the translation is in progress<span class="atlt-animated-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-container">
                            <div class="progress-bar" id="myProgressBar">
                                <span id="progressText">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="atlt_translate_warning-massage">
                <div class="warning-massage-wrapper">
                     <button class="close-button">&times;</button>
                     <div class="warning-massage-content"></div>
                </div>
            </div>
            ${translatorWidget(widgetType)}
            <div class="atlt_string_container string_container">
                <div class="${widgetType}-translation-info notranslate"></div>
                <table class="scrolldown atlt_strings_table">
                    <thead>
                        <th class="notranslate">S.No</th>
                        <th class="notranslate">Source Text</th>
                        <th class="notranslate">Translation</th>
                    </thead>
                    <tbody class="atlt_strings_body">
                    </tbody>
                </table>
            </div>
            <div class="notice-container"></div>
        </div>`;
        return HTML;
    }


    function modelHeaderHTML(widgetType, headerCls) {
        const HTML = `
        <div class="atlt-modern-header modal-header ${headerCls}">
            <div class="atlt-modern-header-top">
                <div class="atlt-modern-step">STEP 2 OF 2</div>
                <button type="button" class="close atlt-modern-close" aria-label="Close"></button>
            </div>
            <h2 class="notranslate atlt-modern-title">Start Automatic Translation Process</h2>
        </div>
        <div class="atlt-modern-alert atlt-modern-alert--warning notice inline notice-info is-dismissible">
            <div class="atlt-modern-alert-body">
                ⚠️ Machine translations are not 100% correct. Please verify strings before using on production website.
            </div>
            <button type="button" class="atlt-modern-alert-close notice-dismiss" aria-label="Dismiss"></button>
        </div>`;
        return HTML;
    }
    function modelFooterHTML(widgetType, footerCls) {
        const HTML = ` <div class="modal-footer ${footerCls}">
        <div class="atlt-modern-footer">
            <div style="display:none" class="atlt_stats atlt-modern-alert atlt-modern-alert--info">
                <div class="atlt-modern-alert-icon" aria-hidden="true">i</div>
                <div class="atlt-modern-alert-body">
                    <strong>Wahooo!</strong> You have saved your valuable time via auto translating
                    <strong class="totalChars"></strong> characters using
                    <a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">
                        LocoAI – Auto Translate for Loco Translate
                    </a>.
                </div>
            </div>
            <div class="atlt_actions save_btn_cont">
                <button class="notranslate atlt_save_strings button button-primary atlt-modern-primary" disabled="true">Merge Translation</button>
            </div>
        </div>
    </div>`;
        return HTML;
    }

    // Translator widget HTML
    function translatorWidget(widgetType) {
        if (widgetType === "yandex") {
            return `
                <div id="ytWidget" style="display:none"></div>`;
        }
        if (widgetType === "openai") {
            return `
                <div id="openaiWidget" style="display:none"></div>`;
        }
    }
    // oninit
    $(document).ready(function () {
        initialize();
    });


})(window, jQuery);


