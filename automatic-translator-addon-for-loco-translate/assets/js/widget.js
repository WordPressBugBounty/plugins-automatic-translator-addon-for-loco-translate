(function ($, win, doc, nav, params, namespace, undefined) {
    'use strict';

    var util = {
        loadScript: function (src, parent, callback) {
            var script = doc.createElement('script');
            script.src = src;

            script.addEventListener('load', function onLoad() {
                this.removeEventListener('load', onLoad, false);
                callback();
            }, false);

            parent.appendChild(script);
        },
        isSupportedBrowser: function () {
            return 'localStorage' in win &&
                'querySelector' in doc &&
                'addEventListener' in win &&
                'getComputedStyle' in win && doc.compatMode === 'CSS1Compat';
        }
    };

    var wrapper = doc.getElementById(params.widgetId);

    if (!wrapper || !util.isSupportedBrowser()) {
        return;
    }
    var initWidget = function () {
        util.loadScript('https://yastatic.net/s3/translate/v21.4.7/js/tr_page.js', wrapper, function () {
            function ensureFallbackWidgetMarkup() {
                if (wrapper.querySelector('#yt-widget')) {
                    return;
                }
                var fallbackMarkup =
                    '<div id="yt-widget" class="yt-widget yt-state_invalid" tabindex="0" style="display: none;">' +
                    '<button class="yt-button__icon yt-button__icon_type_left" type="button"></button>' +
                    '<button class="yt-button__icon yt-button__icon_type_right" type="button"></button>' +
                    '<button class="yt-button__icon yt-button__icon_type_close" type="button"></button>' +
                    '<form class="yt-listbox" hidden></form>' +
                    '</div>';
                wrapper.insertAdjacentHTML('beforeend', fallbackMarkup);
            }

            ensureFallbackWidgetMarkup();

            // Custom UI mode: we do NOT load Yandex widget.html.
            if (!namespace || !namespace.PageTranslator) {
                wrapper.innerHTML = '<div class="notice inline notice-warning">Yandex translator script did not initialize. Please check network/CSP blocking `yastatic.net`.</div>';
                return;
            }

            var translator = new namespace.PageTranslator({
                srv: 'tr-url-widget',
                url: 'https://translate.yandex.net/api/v1/tr.json/translate',
                autoSync: false,
                maxPortionLength: 600
            });

            // Prevent multiple translation runs from stacking
            var atltTranslating = false;
            var translationStartTime = null;
            var progressTickTimer = null;
            var translationUnlockTimer = null;
            var yandexTranslateApiUrl = 'https://translate.yandex.net/api/v1/tr.json/translate';
            var activeYandexRequests = [];

            function isYandexTranslateRequest(url) {
                return typeof url === 'string' && url.indexOf(yandexTranslateApiUrl) === 0;
            }

            function removeTrackedRequest(requestObj) {
                activeYandexRequests = activeYandexRequests.filter(function (activeReq) {
                    return activeReq !== requestObj;
                });
            }

            function trackRequest(requestObj) {
                if (!requestObj) return;
                activeYandexRequests.push(requestObj);
            }

            function abortTrackedYandexRequests() {
                var snapshot = activeYandexRequests.slice();
                activeYandexRequests = [];
                snapshot.forEach(function (requestObj) {
                    try {
                        if (requestObj && typeof requestObj.abort === 'function') {
                            requestObj.abort();
                        }
                    } catch (e) { }
                });
            }

            function setupYandexRequestTracking() {
                $(doc)
                    .off('ajaxSend.atltYandexTracker ajaxComplete.atltYandexTracker ajaxError.atltYandexTracker')
                    .on('ajaxSend.atltYandexTracker', function (event, jqXHR, ajaxOptions) {
                        if (!atltTranslating) return;
                        var url = ajaxOptions && ajaxOptions.url ? ajaxOptions.url : '';
                        if (isYandexTranslateRequest(url)) {
                            trackRequest(jqXHR);
                        }
                    })
                    .on('ajaxComplete.atltYandexTracker ajaxError.atltYandexTracker', function (event, jqXHR, ajaxOptions) {
                        var url = ajaxOptions && ajaxOptions.url ? ajaxOptions.url : '';
                        if (isYandexTranslateRequest(url)) {
                            removeTrackedRequest(jqXHR);
                        }
                    });

                if (win.XMLHttpRequest && !win.__atltYandexXhrTrackerInstalled) {
                    win.__atltYandexXhrTrackerInstalled = true;
                    var originalOpen = win.XMLHttpRequest.prototype.open;
                    var originalSend = win.XMLHttpRequest.prototype.send;

                    win.XMLHttpRequest.prototype.open = function (method, url) {
                        this.__atltYandexTranslateUrl = url;
                        return originalOpen.apply(this, arguments);
                    };

                    win.XMLHttpRequest.prototype.send = function () {
                        var xhr = this;
                        if (atltTranslating && isYandexTranslateRequest(xhr.__atltYandexTranslateUrl || '')) {
                            trackRequest(xhr);
                            var cleanup = function () {
                                removeTrackedRequest(xhr);
                                xhr.removeEventListener('loadend', cleanup);
                            };
                            xhr.addEventListener('loadend', cleanup);
                        }
                        return originalSend.apply(this, arguments);
                    };
                }
            }
            setupYandexRequestTracking();

            function formatNumberShort(num) {
                num = parseInt(num, 10);
                if (isNaN(num)) return num;
                if (num >= 1e6) return (num / 1e6).toFixed(1) + 'M';
                if (num >= 1e3) return (num / 1e3).toFixed(1) + 'K';
                return num;
            }

            function showYandexTranslationSuccess(container) {
                var charCount = container.find('.atlt_stats .totalChars').first().text().trim();
                var formattedCharCount = formatNumberShort(charCount);
                var message = 'Wahooo! You have saved your valuable time via auto translating ' + formattedCharCount + ' characters using Yandex Translator';
                if (!container.data('message-added')) {
                    container.data('message-added', true);
                    container.find(".atlt_translate_progress").append(message);
                }
            }

            function finalizeProgressBeforeHide(container, $scroller) {
                var progressBar = container.find(".progress-wrapper .progress-bar");
                var progressText = progressBar.find('#progressText');
                var scrollerEl = $scroller.get(0);
                var maxScroll = scrollerEl ? Math.max(0, scrollerEl.scrollHeight - scrollerEl.clientHeight) : 0;

                $scroller.stop(true).scrollTop(maxScroll);
                progressBar.css('width', '100%');
                progressText.text('');
            }

            function clearProgressTick() {
                if (progressTickTimer) {
                    win.clearInterval(progressTickTimer);
                    progressTickTimer = null;
                }
            }

            function cancelAtltTranslation() {
                atltTranslating = false;
                translationStartTime = null;
                clearProgressTick();
                abortTrackedYandexRequests();

                if (translationUnlockTimer) {
                    win.clearTimeout(translationUnlockTimer);
                    translationUnlockTimer = null;
                }

                try {
                    if (translator && typeof translator.abort === 'function') {
                        translator.abort();
                    } else if (translator && typeof translator.cancel === 'function') {
                        translator.cancel();
                    } else if (translator && typeof translator.stop === 'function') {
                        translator.stop();
                    }
                } catch (e) { }
            }

            function scheduleUnlock(delayMs) {
                if (translationUnlockTimer) {
                    win.clearTimeout(translationUnlockTimer);
                }
                translationUnlockTimer = win.setTimeout(function () {
                    atltTranslating = false;
                    clearProgressTick();
                }, delayMs);
            }

            function finishProgressUI(container, $scroller, options) {
                if (!container.length || container.data('atlt-progress-done')) {
                    return;
                }

                container.data('atlt-progress-done', true);
                finalizeProgressBeforeHide(container, $scroller);
                container.find(".atlt_save_strings").prop("disabled", false);
                container.find(".atlt_stats").fadeIn("slow");
                win.setTimeout(function () {
                    container.find(".atlt_translate_progress").fadeOut("slow");
                }, 1000);

                if (options && options.showSuccess !== false) {
                    showYandexTranslationSuccess(container);
                }

                if (translationStartTime) {
                    var endTime = new Date();
                    var timeTaken = Math.round((endTime - translationStartTime) / 1000);
                    container.data('translation-time', timeTaken);
                }
                container.data('translation-provider', 'yandex');

                if (!options || options.stopScroll !== false) {
                    $scroller.stop(true);
                }

                atltTranslating = false;
                clearProgressTick();
                if (translationUnlockTimer) {
                    win.clearTimeout(translationUnlockTimer);
                    translationUnlockTimer = null;
                }
            }

            function startProgressUI() {
                var container = $(".yandex-widget-container");
                if (!container.length) return;

                // reset + show progress UI
                container.data('message-added', false);
                container.data('atlt-progress-done', false);
                container.find(".atlt_actions > .atlt_save_strings").prop("disabled", true);
                container.find(".atlt_stats").hide();
                container.find(".atlt_translate_progress").fadeIn("slow");
                container.find(".progress-wrapper").show();

                var $scroller = container.find(".atlt_string_container");
                if (!$scroller.length) return;

                // reset scroll + handlers
                clearProgressTick();
                $scroller.stop(true);
                $scroller.off('scroll.atltYandexProgress');
                $scroller.scrollTop(0);

                var progressBar = container.find(".progress-wrapper .progress-bar");
                var progressText = progressBar.find('#progressText');

                function updateProgressFromElement(scrollerEl) {
                    var scrollTop = scrollerEl.scrollTop;
                    var sh = scrollerEl.scrollHeight;
                    var ch = scrollerEl.clientHeight;
                    var scrollPercentage = (scrollTop / Math.max(1, (sh - ch))) * 1000;
                    var normalizedPercentage = Math.min(Math.max(scrollPercentage, 0), 100);
                    progressBar.css('width', normalizedPercentage + '%');
                    progressText.text('');
                }

                $scroller.on('scroll.atltYandexProgress', function (e) {
                    container = $(".yandex-widget-container");
                    if (container.css('display') === 'none') {
                        clearProgressTick();
                        container.find(".atlt_translate_progress").fadeOut("slow");
                        $scroller.stop(true);
                        return;
                    }

                    var scrollerEl = e.target;
                    updateProgressFromElement(scrollerEl);
                });

                // Delayed start so DOM rows are present, then dynamically keep following bottom growth.
                win.setTimeout(function () {
                    container = $(".yandex-widget-container");
                    if (container.css('display') === 'none') {
                        container.find(".atlt_translate_progress").fadeOut();
                        return;
                    }

                    var scrollerEl = $scroller.get(0);
                    if (!scrollerEl) {
                        return;
                    }

                    var scrollHeight = scrollerEl.scrollHeight;
                    var scrollSpeed = 5000;
                    if (scrollHeight > scrollSpeed) {
                        scrollSpeed = scrollHeight;
                    }

                    // Old-style speed based smooth scrolling.
                    $scroller.animate({
                        scrollTop: scrollHeight + 2000
                    }, scrollSpeed * 2, 'linear');

                    progressTickTimer = win.setInterval(function () {
                        if (!atltTranslating) {
                            clearProgressTick();
                            return;
                        }

                        if (container.css('display') === 'none') {
                            clearProgressTick();
                            return;
                        }

                        scrollerEl = $scroller.get(0);
                        if (!scrollerEl) {
                            clearProgressTick();
                            return;
                        }

                        updateProgressFromElement(scrollerEl);
                    }, 120);
                }, 800);
            }

            // Hook progress/errors (best-effort; depends on PageTranslator implementation)
            try {
                translator.on('error', function () {
                    atltTranslating = false;
                    clearProgressTick();
                    wrapper.insertAdjacentHTML('afterbegin', '<div class="notice inline notice-warning">Yandex translation failed. Please retry or check network blocking.</div>');
                });
            } catch (e) { }
            try {
                translator.on('progress', function (progress) {
                    if (!atltTranslating) {
                        return;
                    }
                    if (progress === 100) {
                        var $container = $(".yandex-widget-container");
                        var $scroller = $container.find(".atlt_string_container");
                        if ($container.length && $scroller.length) {
                            finishProgressUI($container, $scroller);
                        } else {
                            scheduleUnlock(5000);
                        }
                        return;
                    }

                    if (progress >= 0 && progress < 100 && atltTranslating) {
                        // Keep lock alive while provider still reports progress.
                        scheduleUnlock(15000);
                    }
                });
            } catch (e) { }

            function getStoredTargetLang() {
                var code = win.localStorage.getItem('lang');
                if (code) return code;
                try {
                    var fromYt = win.JSON.parse(win.localStorage['yt-widget'] || '{}') || {};
                    if (fromYt.lang) return fromYt.lang;
                } catch (e) { }
                return '';
            }

            function resetTargetStrings($container) {
                $container.find('.atlt_strings_body tr').each(function () {
                    var $row = $(this);
                    var sourceText = $row.find('td.source').text() || '';
                    $row.find('td.target.translate').text(sourceText);
                });
            }

            function startAtltTranslation() {
                var targetLang = getStoredTargetLang();
                if (!targetLang) return;
                if (atltTranslating) return;

                // Only start when strings are rendered + modal is visible
                var $container = $('.yandex-widget-container');
                if (!$container.length || $container.css('display') === 'none') return;
                if (!$container.find('.atlt_strings_body tr').length) return;

                // Always restart from source text when user starts translation again.
                resetTargetStrings($container);
                atltTranslating = true;
                translationStartTime = new Date();

                // Persist for subsequent opens
                try {
                    var prev = win.JSON.parse(win.localStorage['yt-widget'] || '{}') || {};
                    prev.lang = targetLang;
                    win.localStorage['yt-widget'] = win.JSON.stringify(prev);
                } catch (e) {
                    try { win.localStorage['yt-widget'] = win.JSON.stringify({ lang: targetLang }); } catch (e2) { }
                }

                // Start translating the page/popup content
                startProgressUI();
                win.setTimeout(function () {
                    var currentContainer = $('.yandex-widget-container');
                    if (!atltTranslating || !currentContainer.length || currentContainer.css('display') === 'none') {
                        return;
                    }
                    try {
                        translator.translate(params.pageLang, targetLang);
                    } finally {
                        // Best-effort unlock fallback if provider callbacks are missed.
                        scheduleUnlock(30000);
                    }
                }, 1000);
            }

            // Auto-start translation when popup opens + strings are loaded (no button needed)
            $(doc).off('atlt:yandex-start.atltYandex').on('atlt:yandex-start.atltYandex', function () {
                ensureFallbackWidgetMarkup();
                startAtltTranslation();
            });
            $(doc).off('atlt:yandex-cancel.atltYandex').on('atlt:yandex-cancel.atltYandex', function () {
                cancelAtltTranslation();
            });

            // Expose for direct calling if needed
            win.atltStartYandexTranslation = startAtltTranslation;
            win.atltDestroyYandexTranslation = cancelAtltTranslation;
        });
    };

    if (doc.readyState === 'complete' || doc.readyState === 'interactive') {
        initWidget();
    } else {
        doc.addEventListener('DOMContentLoaded', initWidget, false);
    }
})(jQuery, this, this.document, this.navigator, { "pageLang": "en", "autoMode": "false", "widgetId": "ytWidget", "widgetTheme": "light" }, this.yt = this.yt || {});