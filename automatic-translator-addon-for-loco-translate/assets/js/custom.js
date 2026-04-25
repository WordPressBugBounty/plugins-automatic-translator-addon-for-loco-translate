const AutoTranslator = (function (window, $) {
    // get Loco Translate global object.  
    const locoConf = window.locoConf;
    // get plugin configuration object.
    const configData = window.extradata;
    const { ajax_url: ajaxUrl, nonce: nonce, ATLT_URL: ATLT_URL, extra_class: rtlClass, openai_api_key: openaiApiKey} = configData;
    let openAISourceValues = {};
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

            const filterstring = filterRawObject(allStrings, "plain");
            openAISourceValues = Object.fromEntries(
                filterstring.map((item, index) => [String(index + 1), String((item && item.source) || '').trim().replace(/\s+/g, ' ')])
            );
        }
    }

    function initialize() {

        const { conf } = locoConf;
        const { locale, project } = conf;
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
            onYandexTranslateClick(locale);
        });

        $("#atlt_openai_translate_btn").on("click", function () {
            onOpenAITranslateClick(locale);
        });

        // save string inside cache for later use
        $(".atlt_save_strings").on("click", onSaveClick);

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
        const plainStrArr = filterRawObject(allStrings, "plain");
        if (plainStrArr.length > 0) {
            printStringsInPopup(plainStrArr, type);
        } else {
            $("#ytWidget").hide();
            $(".notice-container")
                .addClass('notice inline notice-warning')
                .html("There is no plain string available for translations.");
            $(".atlt_string_container, .choose-lang, .translator-widget, .notice-info, .is-dismissible").hide();
        }
    }

    // create project id for later use inside ajax request.
    function generateProjectId(project, locale) {
        const { domain } = project || {};
        const { lang, region } = locale;
        return project ? `${domain}-${lang}-${region}` : `temp-${lang}-${region}`;
    }

    // Yandex click handler
    function onYandexTranslateClick(locale) {
        const defaultcode = locale.lang || null;
        const langugeName = locale.label || null;
        let defaultlang = '';

        const langMapping = {
            'bel': 'be',
            'snd': 'sd',
            'jv': 'jv',
            'nb': 'no',
            'nn': 'no'
            // Add more cases as needed
        };

        defaultlang = langMapping[defaultcode] || defaultcode;
        let modelContainer = $(`div#atlt_strings_model_yandex.yandex-widget-container`);

        modelContainer.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
        modelContainer.find(".atlt_stats").hide();

        localStorage.setItem("lang", defaultlang);
        localStorage.setItem("langName", langugeName);
        modelContainer.find(".yandex-translation-info").text(`Translating Strings into ${localStorage.getItem("langName") || 'Selected Language'} Using Yandex Translator.`);
        const supportedLanguages = ['kir', 'he', 'af', 'jv', 'no', 'am', 'ar', 'az', 'ba', 'be', 'bg', 'bn', 'bs', 'ca', 'ceb', 'cs', 'cy', 'da', 'de', 'el', 'en', 'eo', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gd', 'gl', 'gu', 'he', 'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'is', 'it', 'ja', 'jv', 'ka', 'kk', 'km', 'kn', 'ko', 'ky', 'la', 'lb', 'lo', 'lt', 'lv', 'mg', 'mhr', 'mi', 'mk', 'ml', 'mn', 'mr', 'mrj', 'ms', 'mt', 'my', 'ne', 'nl', 'no', 'pa', 'pap', 'pl', 'pt', 'ro', 'ru', 'si', 'sk', 'sl', 'sq', 'sr', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'tl', 'tr', 'tt', 'udm', 'uk', 'ur', 'uz', 'vi', 'xh', 'yi', 'zh'];

        if (!supportedLanguages.includes(defaultlang)) {
            $("#atlt-dialog").dialog("close");
            modelContainer.find(".notice-container")
                .addClass('notice inline notice-warning')
                .html("Yandex Automatic Translator Does not support this language.");
            modelContainer.find(".atlt_string_container, .choose-lang, .atlt_save_strings, #ytWidget, .translator-widget, .notice-info, .is-dismissible").hide();
            modelContainer.fadeIn("slow");
        } else {
            $("#atlt-dialog").dialog("close");
            modelContainer.fadeIn("slow", function () {
                // Start Yandex automatically once popup is visible
                $(document).trigger('atlt:yandex-start');
            });
        }


    }

    function onOpenAITranslateClick(locale) {
        const defaultcode = locale.lang || null;
        const langugeName = locale.label || null;
        const langMapping = {
            'bel': 'be',
            'snd': 'sd',
            'jv': 'jv',
            'nb': 'no',
            'nn': 'no'
        };
        const defaultlang = langMapping[defaultcode] || defaultcode;
        let modelContainer = $(`div#atlt_strings_model_openai.openai-widget-container`);
        modelContainer.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
        $("#atlt-dialog").dialog("close");
        modelContainer.find(".atlt_stats").hide();
        localStorage.setItem("lang", defaultlang);
        localStorage.setItem("langName", langugeName);
        modelContainer.find(".openai-translation-info").text(`Translating Strings into ${langugeName} Using OpenAI.`);
        modelContainer.fadeIn("slow", function () {
            startOpenAITranslation(locale, modelContainer);
        });

    }

    function calculateOpenAITokensInBatches(stringsObj) {
        const maxTokens = 500;
        const batches = [];
        let currentBatch = {};
        let totalTokensBatch = 0;
        const entries = Object.entries(stringsObj);

        for (let i = 0; i < entries.length; i++) {
            const [key, value] = entries[i];
            const strValue = String(value || '');
            const tokens = Math.ceil(strValue.length / 4);

            if (totalTokensBatch + tokens <= maxTokens) {
                currentBatch[key] = strValue;
                totalTokensBatch += tokens;
            } else {
                if (Object.keys(currentBatch).length > 0) {
                    batches.push(currentBatch);
                }
                currentBatch = { [key]: strValue };
                totalTokensBatch = tokens;
            }
        }

        if (Object.keys(currentBatch).length > 0) {
            batches.push(currentBatch);
        }

        return batches;
    }

    function startOpenAITranslation(locale, container) {
        const BATCH_SIZE = 15;
        const DELAY = 0;
        const selectedApi = 'openai';
        const selectedStringsBatches = calculateOpenAITokensInBatches(openAISourceValues);

        if (!selectedStringsBatches.length || !Object.keys(openAISourceValues || {}).length) {
            container.find(".notice-container")
                .addClass('notice inline notice-warning')
                .html('No translatable strings found for OpenAI.');
            return;
        }

        const state = {
            ajaxStore: [],
            totalSourceCount: Object.values(openAISourceValues).reduce((sum, str) => sum + str.length, 0),
            isModalAppended: false,
            isTbodyEmpty: false,
            translatedResponse: [],
            totalTranslatedCount: 0,
            totalTranslatedWords: 0,
            currentIndex: 0,
            stopProcess: true,
            stopResponse: false,
            uiUpdated: false,
            startTime: new Date()
        };

        function stopOpenAITranslation() {
            state.stopProcess = false;
            state.stopResponse = true;
            state.ajaxStore.forEach((item) => {
                if (item && typeof item.abort === 'function') {
                    item.abort();
                }
            });
        }

        container.data('atlt-openai-stop-handler', stopOpenAITranslation);

        const elements = {
            progressBar: container.find("#myProgressBar"),
            progressText: container.find("#progressText"),
            tbody: container.find(".atlt_strings_table > tbody.atlt_strings_body"),
            warningWrapper: container.find(".warning-massage-content"),
            warningMessage: container.find(".atlt_translate_warning-massage"),
            progressIndicator: container.find(".atlt_translate_progress"),
            stats: container.find('.atlt_stats')
        };

        function initializeUI() {
            container.find(".notice-container").removeClass('notice notice-warning inline').empty();
            const stringContainer = container.find('.atlt_string_container');
            stringContainer.scrollTop(0);
            stringContainer.off('scroll');
            elements.warningWrapper.empty();
            elements.warningMessage.hide();
            elements.progressIndicator.fadeIn("slow");
            container.find('.progress-wrapper').show();
            elements.progressBar.css('width', '0%');
            elements.progressText.text('0%');
            elements.progressText.css('color', '#f3f3f3');
            container.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
            container.find(".atlt_stats").hide();
            setupEventListeners();
        }

        function setupEventListeners() {
            container.find(".modal-header .close").off('click.atltOpenAI').on("click.atltOpenAI", () => {
                stopOpenAITranslation();
            });

            container.find('.close-button').off('click.atltOpenAI').on("click.atltOpenAI", () => {
                elements.warningMessage.fadeOut("slow");
            });
        }

        function processTranslatedStrings(translatedStrings, metadata, sourceValues, selectedProvider) {
            const regex = /(?:\\{1,2}u([0-9a-fA-F]{4})|\\u([0-9a-fA-F]{4}))/g;
            const source = [];
            const target = [];

            const batchIndex = metadata && metadata.batchIndex ? metadata.batchIndex : 0;
            const requestIndex = metadata && metadata.requestIndex ? metadata.requestIndex : 0;
            const globalIndex = (batchIndex * BATCH_SIZE) + requestIndex;
            const originalSource = sourceValues[globalIndex];

            function decodeUnicode(str) {
                if (Array.isArray(str)) {
                    str = str.join('');
                }
                return String(str).replace(regex, (match, p1, p2) => String.fromCharCode(parseInt(p1 || p2, 16)));
            }

            if (Array.isArray(translatedStrings) || selectedProvider === 'deepl') {
                if (originalSource && typeof originalSource === 'object') {
                    const orderedKeys = Object.keys(originalSource)
                        .map(k => parseInt(k, 10))
                        .sort((a, b) => a - b)
                        .map(n => String(n));

                    for (let i = 0; i < orderedKeys.length && i < translatedStrings.length; i++) {
                        const key = orderedKeys[i];
                        const val = translatedStrings[i];
                        if (typeof val === 'string' && val.trim()) {
                            source.push(originalSource[key]);
                            target.push(decodeUnicode(val).replace(/\\/g, ''));
                        }
                    }
                }
            } else if (translatedStrings && typeof translatedStrings === 'object' && originalSource && typeof originalSource === 'object') {
                const originalKeys = Object.keys(originalSource);
                const translatedKeys = Object.keys(translatedStrings);
                const hasMatchingKeys = translatedKeys.some(key => Object.prototype.hasOwnProperty.call(originalSource, key));

                if (hasMatchingKeys) {
                    translatedKeys.forEach((key) => {
                        if (Object.prototype.hasOwnProperty.call(originalSource, key)) {
                            const val = translatedStrings[key];
                            if (typeof val === 'string' && val.trim()) {
                                source.push(originalSource[key]);
                                target.push(decodeUnicode(val).replace(/\\/g, ''));
                            }
                        }
                    });
                } else {
                    translatedKeys.forEach((key, idx) => {
                        const originalKey = originalKeys[idx];
                        const val = translatedStrings[key];
                        if (typeof val === 'string' && val.trim() && typeof originalKey !== 'undefined') {
                            source.push(originalSource[originalKey]);
                            target.push(decodeUnicode(val).replace(/\\/g, ''));
                        }
                    });
                }
            }

            return { source, target };
        }

        function updateProgress() {
            const progressValue = Math.round((state.totalTranslatedCount / state.totalSourceCount) * 100);
            elements.progressBar.css('width', `${progressValue}%`);
            elements.progressText.text(`${progressValue}%`);
            elements.progressText.css('color', '#f3f3f3');
        }

        function handleSuccessfulTranslation() {
            const message = state.totalTranslatedCount < state.totalSourceCount
                ? `Wahooo! You have saved your valuable time by using auto-translation. You have translated <strong class="totalChars">${state.totalTranslatedCount}</strong> characters Out of <strong class="totalChars">${state.totalSourceCount}</strong> characters using <strong><a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">LocoAI - Auto Translate for Loco Translate (Pro)</a></strong>`
                : `Wahooo! You have saved your valuable time via auto translating <strong class="totalChars">${state.totalTranslatedCount}</strong> characters using <strong><a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">LocoAI - Auto Translate for Loco Translate (Pro)</a></strong>`;

            elements.stats.html(message);
        }

        function makeAjaxRequest(chunk, batchIndex, requestIndex) {
            function getErrorMessageFromResponse(response) {
                if (!response) {
                    return 'OpenAI translation failed.';
                }
                const responseData = response.data;
                if (typeof responseData === 'string' && responseData.trim() !== '') {
                    return responseData;
                }
                if (responseData && typeof responseData === 'object') {
                    if (typeof responseData.message === 'string' && responseData.message.trim() !== '') {
                        return responseData.message;
                    }
                    if (typeof responseData.error === 'string' && responseData.error.trim() !== '') {
                        return responseData.error;
                    }
                    if (typeof responseData.details === 'string' && responseData.details.trim() !== '') {
                        return responseData.details;
                    }
                }
                return 'OpenAI translation failed.';
            }

            const data = {
                action: 'atlt_openai_ajax_handler',
                nonce: nonce,
                source_data: {
                    locale: locale,
                    source: chunk,
                    selectedApi: selectedApi
                },
                metadata: {
                    batchIndex: batchIndex,
                    requestIndex: requestIndex
                }
            };

            return new Promise((resolve, reject) => {
                state.ajaxStore.push($.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: data,
                    success: function (response) {
                        if (!state.stopResponse && !response.success) {
                            state.stopProcess = false;
                            state.stopResponse = true;
                            elements.warningWrapper.html(`<h2>${getErrorMessageFromResponse(response)}</h2>`);
                            elements.warningMessage.fadeIn("slow");
                            elements.progressIndicator.fadeOut("slow");
                            state.ajaxStore.forEach(item => item.abort());
                            resolve();
                            return;
                        }

                        if (response.success && response.data && response.data.data) {
                            const result = processTranslatedStrings(response.data.data, response.data.metadata, selectedStringsBatches, selectedApi);
                            const { source, target } = result;
                            state.translatedResponse.push(Boolean(response.data.data));

                            let tbody = '';
                            for (let j = 0; j < source.length; j++) {
                                tbody += `<tr id="${state.currentIndex}"><td>${state.currentIndex + 1}</td><td class="notranslate source">${encodeHtmlEntity(source[j])}</td>`;
                                tbody += `<td class="target translate">${encodeHtmlEntity(target[j])}</td></tr>`;
                                state.currentIndex++;
                            }

                            state.totalTranslatedCount += source.reduce((sum, str) => sum + str.length, 0);
                            state.totalTranslatedWords += source.reduce((sum, str) => sum + str.trim().split(/\s+/).filter(word => word.length > 0).length, 0);
                            updateProgress();

                            if (!state.isModalAppended && tbody) {
                                elements.tbody.html('');
                                state.isModalAppended = true;
                            }

                            if (tbody) {
                                elements.tbody.append(tbody);
                                const stringContainer = container.find('.atlt_string_container');
                                stringContainer.off('scroll').stop();

                                const tbodyScrollHeight = stringContainer.find('.atlt_strings_table tbody').prop('scrollHeight');
                                const scrollSpeed = 3000;

                                if (tbodyScrollHeight > 100 && container.css('display') === 'block') {
                                    stringContainer.animate({
                                        scrollTop: tbodyScrollHeight
                                    }, scrollSpeed, 'linear');
                                }
                            } else {
                                handleEmptyResponse();
                            }
                        }
                        resolve();
                    },
                    error: reject
                }));
            });
        }

        function handleEmptyResponse() {
            state.isTbodyEmpty = true;
            state.stopProcess = false;
            state.stopResponse = true;
            if (!elements.warningWrapper.find("h2:contains('Translation Aborted.')").length) {
                elements.warningWrapper.append("<h2>Translation Aborted.</h2>");
            }
            elements.warningMessage.fadeIn("slow");
            elements.progressIndicator.fadeOut("slow");
            state.ajaxStore.forEach(item => item.abort());
            container.removeData('atlt-openai-stop-handler');
        }

        async function processChunksInBatches() {
            try {
                for (let i = 0; i < selectedStringsBatches.length; i += BATCH_SIZE) {
                    if (container.css('display') === 'block' && !state.stopResponse) {
                        state.stopProcess = true;
                    }

                    if (state.stopProcess) {
                        const batch = selectedStringsBatches.slice(i, i + BATCH_SIZE);
                        const batchIndex = Math.floor(i / BATCH_SIZE);

                        await Promise.allSettled(
                            batch.map((chunk, requestIndex) =>
                                makeAjaxRequest(chunk, batchIndex, requestIndex)
                            )
                        );

                        if (i + BATCH_SIZE < selectedStringsBatches.length) {
                            await new Promise(resolve => setTimeout(resolve, DELAY));
                        }
                    } else {
                        break;
                    }
                }

                function updateTranslationUI() {
                    if (!state.uiUpdated) {
                        elements.progressBar.css({
                            'background-image': 'none',
                            'animation': 'none',
                            'background-size': 'none'
                        });
                        state.uiUpdated = true;
                        const endTime = new Date();
                        const timeTaken = Math.round((endTime - state.startTime) / 1000);
                        container.data('translation-time', timeTaken);
                        container.data('translation-provider', 'openai');

                        function formatNumberShort(n) {
                            n = Number(n);
                            if (n >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                            if (n >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
                            return n.toString();
                        }
                        setTimeout(() => {
                            container.find(".atlt_save_strings").prop("disabled", false);
                            elements.stats.fadeIn("slow");
                            elements.progressIndicator.fadeOut("slow");
                            handleSuccessfulTranslation();
                            container.removeData('atlt-openai-stop-handler');
                        }, 3000);
                    }
                }

                if (state.translatedResponse.some(Boolean) && !state.isTbodyEmpty) {
                    const stringContainer = container.find('.atlt_string_container');
                    const scrollHeight = stringContainer[0].scrollHeight;
                    const offsetHeight = stringContainer[0].offsetHeight;

                    stringContainer.on('scroll', function () {
                        const currentScrollHeight = stringContainer[0].scrollHeight;
                        const scrollTop = stringContainer[0].scrollTop;
                        const clientHeight = stringContainer[0].clientHeight;
                        const tolerance = 5;
                        const isComplete = (Math.ceil(scrollTop + clientHeight) >= currentScrollHeight - tolerance);

                        if (isComplete) {
                            updateTranslationUI();
                        }
                    });

                    if (offsetHeight === scrollHeight) {
                        updateTranslationUI();
                    }
                } else {
                    elements.progressIndicator.fadeOut("slow");
                    handleEmptyResponse();
                }
            } catch (error) {
                console.error('An error occurred during the AJAX processing:', error);
                elements.progressIndicator.fadeOut("slow");
                container.removeData('atlt-openai-stop-handler');
            }
        }

        initializeUI();
        processChunksInBatches().catch(error => {
            console.error('An error occurred during the AJAX processing:', error);
            elements.progressIndicator.fadeOut("slow");
        });
    }
    // parse all translated strings and pass to save function
    function onSaveClick() {
        const container = $(this).closest('.atlt_custom_model');
        const tableRows = container.find(".atlt_strings_table tbody tr");

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

        let translatedObj = [];

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
            '% %':'%%'    
        };
        const regex = /(\%\s*\d+\s*\$?\s*[a-z0-9])/gi;
        
        tableRows.each(function () {
            const source = $(this).find("td.source").text();
            const target = $(this).find("td.target").text();

            const improvedTarget1 = strtr(target, rpl);
            const improvedSource1 = strtr(source, rpl);

            const improvedTarget = improvedTarget1.replace(regex, function(match) {
                return match.replace(/\s/g, '').toLowerCase();
            });

            const improvedSource = improvedSource1.replace(regex, function(match) {
                return match.replace(/\s/g, '').toLowerCase();
            });

            translatedObj.push({
                "source": improvedSource,
                "target": improvedTarget
            });
        });
        const time_taken = container.data('translation-time') || 0;
        const translation_provider = container.data('translation-provider');
        const { lang, region } = locoConf.conf.locale;
        const target_language = region ? `${lang}_${region}` : lang;
        const totalCharacters = translatedObj.reduce((sum, item) => sum + item.source.length, 0);
        const totalStrings = translatedObj.length;

        const translationData = {
            time_taken: time_taken,
            translation_provider: translation_provider,
            pluginORthemeName: pluginOrThemeName,
            target_language: target_language,
            total_characters: totalCharacters,
            total_strings: totalStrings,
        }

        var projectId = container.find("input[id='project_id']").val();

        //  Save Translated Strings
        saveTranslatedStrings(translatedObj, projectId, translationData);

        $(".atlt_custom_model").fadeOut("slow");

        $("html").addClass("merge-translations");
        updateLocoModel();
    }

    function onAutoTranslateClick(e) {
        if (e.originalEvent !== undefined) {
            var checkModal = setInterval(function () {
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
        var checkModal = setInterval(function () {
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
    function filterRawObject(rawArray, filterType) {
        return rawArray.filter((item) => {
            if (item.source && !item.target) {
                if (ValidURL(item.source) || isHTML(item.source) || isSpecialChars(item.source) || isEmoji(item.source) || item.source.includes('#')) {
                    return false;
                } else if (isPlacehodersChars(item.source)) {
                    return true;
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
    // allowed special chars in plain text
    function isPlacehodersChars(str) {
        var rgex = /%s|%d/g;
        return rgex.test(str);
    }
    // replace placeholders in strings
    function strtr(s, p, r) {
        return !!s && {
            2: function () {
                for (var i in p) {
                    s = strtr(s, i, p[i]);
                }
                return s;
            },
            3: function () {
                return s.replace(RegExp(p, 'g'), r);
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
            'action': 'save_all_translations',
            'data': JSON.stringify(stringData),
            'part': part,
            'project-id': projectId,
            'wpnonce': nonce,
            'translation_data': JSON.stringify(translationData)
        };

        jQuery.post(ajaxUrl, data, function (response) {
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
    // open settings model on auto translate button click
    function openSettingsModel() {
        $("#atlt-dialog").dialog({
            dialogClass: rtlClass,
            resizable: false,
            height: "auto",
            draggable: false,
            width: 400,
            modal: true,
            buttons: {
                Cancel: function () {
                    $(this).dialog("close");
                }
            }
        });
    }

    // String translate modal close handlers (works for Yandex + OpenAI modals)
    $(window).on('click', function (event) {
        const modal = event.target;
        if (modal && modal.classList && modal.classList.contains('atlt_custom_model')) {
            const $modal = $(modal);
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
    });

    $(document).on('click', '.atlt_custom_model .modal-header .close', function () {
        const $modal = $(this).closest('.atlt_custom_model');
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
    });


    function encodeHtmlEntity(str) {
        var buf = [];
        for (var i = str.length - 1; i >= 0; i--) {
            buf.unshift(['&#', str[i].charCodeAt(), ';'].join(''));
        }
        return buf.join('');
    }

    /* function encodeHtmlEntity(str) {
         return str
             .split('')
             .map(char => `&#${char.charCodeAt(0)};`)
             .join('');
     }*/

    // get object and append inside the popup
    function printStringsInPopup(jsonObj, type) {
        let html = '';
        let totalTChars = 0;
        let index = 1;

        if (jsonObj) {
            let wordCount = 0;
            for (const key in jsonObj) {
                if (jsonObj.hasOwnProperty(key)) {
                    const element = jsonObj[key];
                    const sourceText = element.source.trim();
                    wordCount += sourceText.trim().split(/\s+/).length;

                    if (sourceText !== '') {
                        if ((type === "yandex") || (key <= 2500)) {
                            html += `<tr id="${key}"><td>${index}</td><td class="notranslate source">${type === "yandex" ? encodeHtmlEntity(sourceText) : sourceText}</td>`;

                            if (type === "yandex") {
                                html += `<td translate="yes" class="target translate">${sourceText}</td></tr>`;
                            } else {
                                html += '<td class="target translate"></td></tr>';
                            }

                            index++;
                            totalTChars += sourceText.length;
                        }
                    }
                }
            }
            
            $(".atlt_stats").each(function () {                
                $(this).find(".totalChars").html(totalTChars);
            });
        }

        $(`.${type}-widget-container .atlt_strings_table > tbody.atlt_strings_body`).html(html);

    }

    function settingsModel() {
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
        const ATLT_IMG = (key) => ATLT_URL + 'assets/images/' + icons[key];
        const DOC_ICON = `<img src="${ATLT_IMG('docs')}" width="20" alt="Docs">`;
        const ERROR_ICON = `<img src="${ATLT_IMG('error')}" alt="error" style="height:16px; vertical-align:middle; margin-right:5px;">`;
        const rows = [
            {
                name: 'Yandex Translate',
                icon: 'yandex',
                info: 'https://translate.yandex.com/',
                btn: `<button id="atlt_yandex_translate_btn" class="atlt-provider-btn translate">Translate</button>`,
                doc: `${url}translate-plugin-theme-via-yandex-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_yandex`
            },
            {
                name: 'Google Translate',
                icon: 'google',
                info: 'https://translate.google.com/',
                btn: `<a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_google" target="_blank"><button id="atlt_google_translate_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}auto-translations-via-google-translate/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_google`
            },
            {
                name: 'Chrome Built-in AI',
                icon: 'chrome',
                info: 'https://developer.chrome.com/docs/ai/translator-api',
                btn: `<a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chrome" target="_blank"><button id="ChromeAiTranslator_settings_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}how-to-use-chrome-ai-auto-translations/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_chrome`
            },
            {
                name: 'ChatGPT Translate',
                icon: 'chatgpt',
                info: 'https://locoaddon.com/docs/chatgpt-ai-translations-wordpress/',
                btn: `<a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chatgpt" target="_blank"><button id="atlt_chatGPT_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}chatgpt-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_chatgpt`
            },
            {
                name: 'Gemini AI Translate',
                icon: 'gemini',
                info: 'https://locoaddon.com/docs/pro-plugin/how-to-use-gemini-ai-to-translate-plugins-or-themes/',
                btn: `<a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_gemini" target="_blank"><button id="atlt_geminiAI_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}gemini-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_gemini`
            },
            {
                name: 'OpenAI Translate',
                icon: 'openai',
                info: 'https://locoaddon.com/docs/pro-plugin/how-to-use-open-ai-to-translate-plugins-or-themes/',
                btn: `${openaiApiKey ? `<button id="atlt_openai_translate_btn" class="atlt-provider-btn translate">Translate</button>` : `<a href="admin.php?page=loco-atlt-dashboard&tab=settings" target="_blank"><button id="atlt_openai_btn" class="atlt-provider-btn error">${ERROR_ICON}Add API Key</button></a>`}`,
                doc: `${url}open-ai-translations-wordpress/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_openai`
            },
            {
                name: 'DeepL Translate',
                icon: 'deepl',
                info: 'https://www.deepl.com/en/translator',
                btn: `<a href="https://locoaddon.com/pricing/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_deepl" target="_blank"><button id="atlt_deepl_btn" class="atlt-provider-btn error">${ERROR_ICON}Buy Pro</button></a>`,
                doc: `${url}translate-via-deepl-doc-translator/?utm_source=atlt_plugin&utm_medium=inside&utm_campaign=docs&utm_content=popup_deepl`
            }
        ];
    
        const rowHTML = rows.map(row => `
            <tr>
                <td class="atlt-provider-name">
                    <a href="${row.info}" target="_blank">
                        <img src="${ATLT_IMG(row.icon)}" class="atlt-provider-icon" alt="${row.name}">
                    </a>
                    ${row.name}
                </td>
                <td>${row.btn}</td>
                <td>
                    <a href="${row.doc}" target="_blank" class="atlt-provider-docs-btn">${DOC_ICON}</a>
                </td>
            </tr>
        `).join('');
    
        const modelHTML = `
            <div class="atlt-provider-modal" id="atlt-dialog" title="Step 2 - Select Translation Provider" style="display:none;">
                <table class="atlt-provider-table">
                    <thead>
                        <tr><th>Name</th><th>Translate</th><th>Docs</th></tr>
                    </thead>
                    <tbody>${rowHTML}</tbody>
                </table>
            </div>
        `;
    
        $("body").append(modelHTML);
    }

    // modal to show strings
    function createStringsModal(projectId, widgetType) {
        // Set wrapper, header, and body classes based on widgetType
        let { wrapperCls, headerCls, bodyCls, footerCls } = getWidgetClasses(widgetType);
        let modelHTML = `
            <div id="atlt_strings_model_${widgetType}" class="modal atlt_custom_model  ${wrapperCls} ${rtlClass}">
                <div class="modal-content">
                    <input type="hidden" id="project_id" value="${projectId}"> 
                    ${modelHeaderHTML(widgetType, headerCls)}   
                    ${modelBodyHTML(widgetType, bodyCls)}   
                    ${modelFooterHTML(widgetType, footerCls)}   
            </div></div>`;

        $("body").append(modelHTML);
    }

    // Get widget classes based on widgetType
    function getWidgetClasses(widgetType) {
        let wrapperCls = '';
        let headerCls = '';
        let bodyCls = '';
        let footerCls = '';
        switch (widgetType) {
            case 'yandex':
                wrapperCls = 'yandex-widget-container';
                headerCls = 'yandex-widget-header';
                bodyCls = 'yandex-widget-body';
                footerCls = 'yandex-widget-footer';

                break;
            case 'openai':
                wrapperCls = 'openai-widget-container';
                headerCls = 'openai-widget-header';
                bodyCls = 'openai-widget-body';
                footerCls = 'openai-widget-footer';

                break;
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
            <div class="atlt_translate_progress">
                Automatic translation is in progress....<br/>
                It will take a few minutes, enjoy ☕ coffee in this time!<br/><br/>
                Please do not leave this window or browser tab while the translation is in progress...

                 <div class="progress-wrapper">
                    <div class="progress-container">
                        <div class="progress-bar" id="myProgressBar">
                            <span id="progressText">0%</span>
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
            <div class="atlt_string_container">
                <div class ="${widgetType}-translation-info">Translating Strings into ${localStorage.getItem("langName") || 'Selected Language'} Using ${widgetType === "yandex" ? "Yandex" : "OpenAI"}</div>
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
        <div class="modal-header  ${headerCls}">
                        <span class="close">&times;</span>
                        <h2 class="notranslate">Step 2 - Start Automatic Translation Process</h2>
                        <div class="atlt_actions">
                            <button class="notranslate atlt_save_strings button button-primary" disabled="true">Merge Translation</button>
                        </div>
                        <div style="display:none" class="atlt_stats hidden">
                            Wahooo! You have saved your valuable time via auto translating 
                            <strong class="totalChars"></strong> characters  using 
                            <strong>
                                <a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">
                                    LocoAI – Auto Translate for Loco Translate
                                </a>
                            </strong>
                        </div>
                    </div>
                    <div class="notice inline notice-info is-dismissible">
                        Plugin will not translate any strings with HTML or special characters because Yandex Translator currently does not support HTML and special characters translations.
                        You can edit translated strings inside Loco Translate Editor after merging the translations. Only special characters (%s, %d) fixed at the time of merging of the translations.
                    </div>
                    <div class="notice inline notice-info is-dismissible">
                        Machine translations are not 100% correct.
                        Please verify strings before using on the production website.
                    </div>`;
        return HTML;
    }
    function modelFooterHTML(widgetType, footerCls) {
        const HTML = ` <div class="modal-footer ${footerCls}">
        <div class="atlt_actions">
            <button class="notranslate atlt_save_strings button button-primary" disabled="true">Merge Translation</button>
        </div>
        <div style="display:none" class="atlt_stats">
            Wahooo! You have saved your valuable time via auto translating 
            <strong class="totalChars"></strong> characters  using 
            <strong>
                <a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">
                    LocoAI – Auto Translate for Loco Translate
                </a>
            </strong>
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


