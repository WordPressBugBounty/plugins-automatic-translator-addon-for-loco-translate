(function (window, $) {
    window.ATLT = window.ATLT || {};

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

    class OpenAITranslator {
        constructor(locale, container, openAISourceValues, ajaxUrl, nonce) {
            this.locale = locale;
            this.container = container;
            this.openAISourceValues = openAISourceValues;
            this.ajaxUrl = ajaxUrl;
            this.nonce = nonce;
            this.BATCH_SIZE = 15;
            this.DELAY = 0;
            this.selectedApi = 'openai';
            this.selectedStringsBatches = calculateOpenAITokensInBatches(openAISourceValues);

            this.state = {
                ajaxStore: [],
                totalSourceCount: Object.values(openAISourceValues).reduce((sum, str) => sum + str.length, 0),
                isModalAppended: false,
                translatedResponse: [],
                totalTranslatedCount: 0,
                currentIndex: 0,
                stopProcess: true,
                stopResponse: false,
                uiUpdated: false,
                startTime: new Date()
            };

            this.elements = {
                progressBar: container.find("#myProgressBar"),
                progressText: container.find("#progressText"),
                tbody: container.find(".atlt_strings_table > tbody.atlt_strings_body"),
                warningWrapper: container.find(".warning-massage-content"),
                warningMessage: container.find(".atlt_translate_warning-massage"),
                progressIndicator: container.find(".atlt_translate_progress"),
                stats: container.find('.atlt_stats')
            };

            // Bind methods
            this.stopOpenAITranslation = this.stopOpenAITranslation.bind(this);
            this.container.data('atlt-openai-stop-handler', this.stopOpenAITranslation);
        }

        start() {
            if (!this.selectedStringsBatches.length || !Object.keys(this.openAISourceValues || {}).length) {
                this.container.find(".notice-container")
                    .addClass('notice inline notice-warning atlt-modern-alert atlt-modern-alert--warning')
                    .show()
                    .html('No translatable strings found for OpenAI.');
                this.container.find(".atlt_string_container, .choose-lang, .translator-widget, .notice-info, .is-dismissible,.atlt_actions > .atlt_save_strings").hide();
                return;
            }

            this.initializeUI();
            this.processChunksInBatches().catch(error => {
                console.error('An error occurred during the AJAX processing:', error);
                this.elements.progressIndicator.fadeOut("slow");
            });
        }

        stopOpenAITranslation() {
            this.state.stopProcess = false;
            this.state.stopResponse = true;
            this.state.ajaxStore.forEach((item) => {
                if (item && typeof item.abort === 'function') {
                    item.abort();
                }
            });
        }

        initializeUI() {
            this.container.find(".notice-container").removeClass('notice notice-warning inline').empty();
            const stringContainer = this.container.find('.atlt_string_container');
            stringContainer.scrollTop(0);
            stringContainer.off('scroll');
            this.elements.warningWrapper.empty();
            this.elements.warningMessage.hide();
            this.elements.progressIndicator.fadeIn("slow");
            this.container.find('.progress-wrapper').show();
            this.elements.progressBar.css('width', '0%');
            this.elements.progressText.text('0%');
            this.elements.progressText.css('color', '#f3f3f3');
            this.container.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
            this.container.find(".atlt_stats").hide();
            this.setupEventListeners();
        }

        setupEventListeners() {
            this.container.find(".modal-header .close").off('click.atltOpenAI').on("click.atltOpenAI", () => {
                this.stopOpenAITranslation();
            });

            this.container.find('.close-button').off('click.atltOpenAI').on("click.atltOpenAI", () => {
                this.elements.warningMessage.fadeOut("slow");
            });
        }

        hasPartialTranslations() {
            return this.state.currentIndex > 0 || this.state.translatedResponse.some(Boolean);
        }

        showTranslationWarning(primaryMessage, isPartial) {
            this.elements.warningWrapper.empty();
            this.elements.warningWrapper.append($('<h2>').text(primaryMessage));
            if (isPartial) {
                this.elements.warningWrapper.append(
                    $('<p>').addClass('atlt-partial-translation-hint').text(
                        'Some strings were translated successfully. Click "Merge Translation" to save the translated strings, then run auto translate again for the remaining strings.'
                    )
                );
            }
            this.elements.warningMessage.fadeIn('slow');
            this.elements.progressIndicator.fadeOut('slow');
        }

        stopTranslationWithError(errorMessage) {
            const isPartial = this.hasPartialTranslations();
            this.state.stopProcess = false;
            this.state.stopResponse = true;
            this.showTranslationWarning(errorMessage, isPartial);
            this.state.ajaxStore.forEach((item) => {
                if (item && typeof item.abort === 'function') {
                    item.abort();
                }
            });
        }

        finalizePartialTranslationUI() {
            if (this.state.uiUpdated) {
                return;
            }

            this.state.uiUpdated = true;
            const progressValue = Math.min(
                100,
                Math.round((this.state.totalTranslatedCount / this.state.totalSourceCount) * 100)
            );
            this.elements.progressBar.css('width', `${progressValue}%`);
            this.elements.progressText.text(`${progressValue}%`);
            this.elements.progressIndicator.fadeOut('slow');
            this.container.find('.atlt_save_strings').prop('disabled', false);

            const endTime = new Date();
            const timeTaken = Math.round((endTime - this.state.startTime) / 1000);
            this.container.data('translation-time', timeTaken);
            this.container.data('translation-provider', 'openai');

            const partialStatsMsg = `Partial translation complete. <strong class="totalChars">${window.ATLT.formatNumberShort(this.state.totalTranslatedCount)}</strong> characters translated. Merge the translated strings, then translate the remaining strings again.`;
            const $body = this.elements.stats.find('.atlt-modern-alert-body');
            if ($body.length) {
                $body.html(partialStatsMsg);
            } else {
                this.elements.stats.html(partialStatsMsg);
            }
            this.elements.stats.fadeIn('slow');
            this.container.removeData('atlt-openai-stop-handler');
        }

        processTranslatedStrings(translatedStrings, metadata) {
            const regex = /(?:\\{1,2}u([0-9a-fA-F]{4})|\\u([0-9a-fA-F]{4}))/g;
            const source = [];
            const target = [];

            const batchIndex = metadata && metadata.batchIndex ? metadata.batchIndex : 0;
            const requestIndex = metadata && metadata.requestIndex ? metadata.requestIndex : 0;
            const globalIndex = (batchIndex * this.BATCH_SIZE) + requestIndex;
            const originalSource = this.selectedStringsBatches[globalIndex];

            function decodeUnicode(str) {
                if (Array.isArray(str)) {
                    str = str.join('');
                }
                return String(str).replace(regex, (match, p1, p2) => String.fromCharCode(parseInt(p1 || p2, 16)));
            }

            if (Array.isArray(translatedStrings) || this.selectedApi === 'deepl') {
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

        updateProgress() {
            const progressValue = Math.round((this.state.totalTranslatedCount / this.state.totalSourceCount) * 100);
            this.elements.progressBar.css('width', `${progressValue}%`);
            this.elements.progressText.text(`${progressValue}%`);
            this.elements.progressText.css('color', '#f3f3f3');
        }

        handleSuccessfulTranslation() {
            const message = this.state.totalTranslatedCount < this.state.totalSourceCount
                ? `Wahooo! You have saved your valuable time by using auto-translation. You have translated <strong class="totalChars">${window.ATLT.formatNumberShort(this.state.totalTranslatedCount)}</strong> characters Out of <strong class="totalChars">${window.ATLT.formatNumberShort(this.state.totalSourceCount)}</strong> characters using <strong><a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">LocoAI - Auto Translate for Loco Translate</a></strong>`
                : `Wahooo! You have saved your valuable time via auto translating <strong class="totalChars">${window.ATLT.formatNumberShort(this.state.totalTranslatedCount)}</strong> characters using <strong><a href="https://wordpress.org/support/plugin/automatic-translator-addon-for-loco-translate/reviews/#new-post" target="_new">LocoAI - Auto Translate for Loco Translate</a></strong>`;
            const $body = this.elements.stats.find('.atlt-modern-alert-body');
            if ($body.length) {
                $body.html(message);
            } else {
                this.elements.stats.html(message);
            }
        }

        getErrorMessageFromResponse(response) {
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

        getErrorMessageFromXhr(xhr) {
            let message = 'OpenAI translation failed. Please check your connection and try again.';
            if (!xhr) {
                return message;
            }

            try {
                const parsed = JSON.parse(xhr.responseText || '');
                const parsedMessage = this.getErrorMessageFromResponse(parsed);
                if (parsedMessage && parsedMessage !== 'OpenAI translation failed.') {
                    return parsedMessage;
                }
            } catch (parseError) {
                // Ignore invalid JSON bodies.
            }

            if (xhr.status === 500) {
                return 'OpenAI translation failed due to a server error. Please try again.';
            }

            return message;
        }

        makeAjaxRequest(chunk, batchIndex, requestIndex) {
            const requestLocale = {
                lang: this.locale && this.locale.lang ? String(this.locale.lang) : '',
                region: this.locale && this.locale.region ? String(this.locale.region) : '',
                label: this.locale && this.locale.label ? String(this.locale.label) : ''
            };

            const data = {
                action: 'atlt_openai_ajax_handler',
                nonce: this.nonce,
                source_data: {
                    locale: requestLocale,
                    source: chunk,
                    selectedApi: this.selectedApi
                },
                metadata: {
                    batchIndex: batchIndex,
                    requestIndex: requestIndex
                }
            };

            return new Promise((resolve, reject) => {
                this.state.ajaxStore.push($.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: data,
                    success: (response) => {
                        if (!this.state.stopResponse && !response.success) {
                            this.stopTranslationWithError(this.getErrorMessageFromResponse(response));
                            resolve();
                            return;
                        }

                        if (response.success && response.data && response.data.data) {
                            const result = this.processTranslatedStrings(response.data.data, response.data.metadata);
                            const { source, target } = result;
                            this.state.translatedResponse.push(Boolean(response.data.data));

                            const $rows = [];
                            for (let j = 0; j < source.length; j++) {
                                const $row = $('<tr></tr>').attr('id', String(this.state.currentIndex));
                                $row.append($('<td></td>').text(this.state.currentIndex + 1));
                                $row.append(
                                    $('<td></td>').addClass('notranslate source').text(source[j])
                                );
                                $row.append(
                                    $('<td></td>').addClass('target translate').text(target[j])
                                );
                                $rows.push($row);
                                this.state.currentIndex++;
                            }

                            this.state.totalTranslatedCount += source.reduce((sum, str) => sum + str.length, 0);
                            this.updateProgress();

                            if (!this.state.isModalAppended && $rows.length) {
                                this.elements.tbody.empty();
                                this.state.isModalAppended = true;
                            }

                            if ($rows.length) {
                                this.elements.tbody.append($rows);
                                const stringContainer = this.container.find('.atlt_string_container');
                                stringContainer.off('scroll').stop();
                                const el = stringContainer.get(0);
                                const maxScrollTop = el
                                    ? Math.max(0, el.scrollHeight - el.clientHeight)
                                    : 0;

                                // Auto-scroll while translating (match Yandex feel).
                                // Modal uses display:flex, so check !== 'none' (not === 'block').
                                if (maxScrollTop > 0 && this.container.css('display') !== 'none') {
                                    const scrollSpeed = Math.max(650, maxScrollTop);
                                    stringContainer.stop(true, false).animate(
                                        { scrollTop: maxScrollTop },
                                        scrollSpeed,
                                        'linear'
                                    );
                                }
                            } else {
                                this.handleEmptyResponse();
                            }
                        }
                        resolve();
                    },
                    error: (xhr) => {
                        if (!this.state.stopResponse) {
                            this.stopTranslationWithError(this.getErrorMessageFromXhr(xhr));
                        }
                        resolve();
                    }
                }));
            });
        }

        handleEmptyResponse() {
            this.state.stopProcess = false;
            this.state.stopResponse = true;
            if (!this.elements.warningWrapper.find("h2:contains('Translation Aborted.')").length) {
                this.elements.warningWrapper.append("<h2>Translation Aborted.</h2>");
            }
            this.elements.warningMessage.fadeIn("slow");
            this.elements.progressIndicator.fadeOut("slow");
            this.state.ajaxStore.forEach(item => item.abort());
            this.container.removeData('atlt-openai-stop-handler');
        }

        finalizeProgressUI() {
            const stringContainer = this.container.find('.atlt_string_container');
            const el = stringContainer.get(0);
            const maxScroll = el ? Math.max(0, el.scrollHeight - el.clientHeight) : 0;
            stringContainer.stop(true).scrollTop(maxScroll);
            this.elements.progressBar.css('width', '100%');
            this.elements.progressText.text('100%');
            this.elements.progressText.css('color', '#f3f3f3');
        }

        updateTranslationUI() {
            if (!this.state.uiUpdated) {
                this.finalizeProgressUI();
                this.elements.progressBar.css({
                    'background-image': 'none',
                    'animation': 'none',
                    'background-size': 'none'
                });
                this.state.uiUpdated = true;
                const endTime = new Date();
                const timeTaken = Math.round((endTime - this.state.startTime) / 1000);
                this.container.data('translation-time', timeTaken);
                this.container.data('translation-provider', 'openai');
                setTimeout(() => {
                    this.container.find(".atlt_save_strings").prop("disabled", false);
                    this.elements.stats.fadeIn("slow");
                    this.elements.progressIndicator.fadeOut("slow");
                    this.handleSuccessfulTranslation();
                    this.container.removeData('atlt-openai-stop-handler');
                }, 3000);
            }
        }

        async processChunksInBatches() {
            try {
                for (let i = 0; i < this.selectedStringsBatches.length; i += this.BATCH_SIZE) {
                    if (this.container.css('display') !== 'none' && !this.state.stopResponse) {
                        this.state.stopProcess = true;
                    }

                    if (this.state.stopProcess) {
                        const batch = this.selectedStringsBatches.slice(i, i + this.BATCH_SIZE);
                        const batchIndex = Math.floor(i / this.BATCH_SIZE);

                        await Promise.allSettled(
                            batch.map((chunk, requestIndex) =>
                                this.makeAjaxRequest(chunk, batchIndex, requestIndex)
                            )
                        );

                        if (i + this.BATCH_SIZE < this.selectedStringsBatches.length) {
                            await new Promise(resolve => setTimeout(resolve, this.DELAY));
                        }
                    } else {
                        break;
                    }
                }

                const hasTranslations = this.state.translatedResponse.some(Boolean) && this.state.currentIndex > 0;

                if (hasTranslations && !this.state.stopResponse) {
                    setTimeout(() => this.updateTranslationUI(), 0);
                } else if (hasTranslations && this.state.stopResponse) {
                    this.finalizePartialTranslationUI();
                } else {
                    this.elements.progressIndicator.fadeOut('slow');
                    if (!this.elements.warningWrapper.find('h2').length) {
                        this.handleEmptyResponse();
                    } else {
                        this.container.removeData('atlt-openai-stop-handler');
                    }
                }
            } catch (error) {
                console.error('An error occurred during the AJAX processing:', error);
                this.elements.progressIndicator.fadeOut("slow");
                this.container.removeData('atlt-openai-stop-handler');
            }
        }
    }

    function startOpenAITranslation(locale, container, openAISourceValues, ajaxUrl, nonce) {
        const translator = new OpenAITranslator(locale, container, openAISourceValues, ajaxUrl, nonce);
        translator.start();
    }

    window.ATLT.openaiFlow = {
        calculateOpenAITokensInBatches,
        startOpenAITranslation
    };
})(window, jQuery);
