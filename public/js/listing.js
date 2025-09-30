;(function (window, document) {
    'use strict';

    if (window.vrspBookingWidget && typeof window.vrspBookingWidget.refresh === 'function') {
        window.vrspBookingWidget.refresh();
        return;
    }

    var INIT_RETRY_LIMIT = 20;
    var INIT_RETRY_DELAY = 150;
    var SELECTOR_DEFAULTS = {
        form: '[data-vrsp="form"], .vrsp-form',
        quote: '[data-vrsp="quote"], .vrsp-quote',
        message: '[data-vrsp="message"], .vrsp-message',
        submit: '[data-vrsp="submit"], .vrsp-form__submit',
        continueButton: '[data-vrsp="continue"], .vrsp-form__continue',
        availability: '[data-vrsp="availability"], .vrsp-availability',
        availabilityCalendar: '[data-vrsp="calendar"], .vrsp-availability__calendar',
        rateList: '[data-vrsp="rate-list"], .vrsp-availability__rate-list'
    };

    var initAttempts = 0;
    var widgetState = new WeakMap();
    var supportsAbortController = typeof window.AbortController !== 'undefined';

    function getText(listingData, key, fallback) {
        if (listingData && listingData.i18n && listingData.i18n[key]) {
            return listingData.i18n[key];
        }
        return fallback;
    }

        return fallback;
    }

    function formatCurrencyFactory(currency) {
        return function formatCurrency(amount) {
            var value = Number(amount || 0);
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(value);
        };
    }

    function clearChildren(node) {
        if (!node) {
            return;
        }

        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function renderAvailability(state, data) {
        var availabilityCalendar = state.availabilityCalendar;
        var rateList = state.rateList;
        var listingData = state.listingData;
        var formatCurrency = state.formatCurrency;

        if (availabilityCalendar) {
            clearChildren(availabilityCalendar);

            var blocked = [];
            if (data && Array.isArray(data.blocked)) {
                blocked = data.blocked;
            }
        }

            if (blocked.length === 0) {
                var empty = document.createElement('p');
                empty.textContent = getText(listingData, 'availabilityEmpty', 'Your preferred dates are open!');
                availabilityCalendar.appendChild(empty);
            } else {
                var limit = Math.min(blocked.length, 8);
                for (var i = 0; i < limit; i += 1) {
                    var event = blocked[i];
                    if (!event) {
                        continue;
                    }
                    var tag = document.createElement('span');
                    tag.className = 'vrsp-availability__tag';
                    tag.textContent = event.start + ' → ' + event.end;
                    availabilityCalendar.appendChild(tag);
                }
            }
        }

        if (rateList) {
            clearChildren(rateList);
            var rates = [];
            if (data && Array.isArray(data.rates)) {
                rates = data.rates;
            }
        }
    }

            var maxRates = Math.min(rates.length, 6);
            for (var j = 0; j < maxRates; j += 1) {
                var rate = rates[j];
                if (!rate) {
                    continue;
                }
                var pill = document.createElement('span');
                pill.className = 'rate-pill';
                pill.textContent = rate.date + ': ' + formatCurrency(rate.amount);
                rateList.appendChild(pill);
            }
        }
    }

    function populateQuote(state, quote) {
        var widget = state.widget;
        var quotePanel = state.quotePanel;
        var formatCurrency = state.formatCurrency;
        var listingData = state.listingData;

        if (!quotePanel || !widget) {
            return;
        }

        if (!quote) {
            quotePanel.hidden = true;
            return;
        }

        quotePanel.hidden = false;

        if (!quotePanel.hasAttribute('tabindex')) {
            quotePanel.setAttribute('tabindex', '-1');
        }

        var write = function (attr, value) {
            var selector = '[data-quote="' + attr + '"]';
            var target = widget.querySelector(selector);
            if (target) {
                target.textContent = value;
            }
        };

        write('nights', quote.nights);
        write('subtotal', formatCurrency(quote.subtotal));
        var taxes = Number(quote.taxes || 0) + Number(quote.cleaning_fee || 0) + Number(quote.damage_fee || 0);
        write('taxes', formatCurrency(taxes));
        write('total', formatCurrency(quote.total));
        write('deposit', formatCurrency(quote.deposit));

        var balanceRow = widget.querySelector('[data-quote="balance-row"]');
        var note = widget.querySelector('[data-quote="note"]');

        if (balanceRow && note) {
            if (Number(quote.deposit || 0) >= Number(quote.total || 0)) {
                balanceRow.style.display = 'none';
                note.textContent = getText(
                    listingData,
                    'fullBalanceNote',
                    'Your stay begins soon, so the full balance is due today.'
                );
            } else {
                balanceRow.style.display = '';
                write('balance', formatCurrency(quote.balance));
                note.textContent = getText(
                    listingData,
                    'depositNote',
                    'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.'
                );
            }
        }
    }

    function setButtonState(button, disabled) {
        if (!button) {
            return;
        }

        button.disabled = !!disabled;

        if (disabled) {
            button.setAttribute('aria-disabled', 'true');
        } else {
            button.removeAttribute('aria-disabled');
        }
    }

    function resetMessage(message) {
        if (!message) {
            return;
        }

        message.className = 'vrsp-message';
        message.textContent = '';
    }

    function setMessage(state, type, text) {
        var message = state.message;

        if (!message) {
            return;
        }

        resetMessage(message);
        message.classList.add(type);
        message.textContent = text;
    }

    function collectPayload(form) {
        if (!form) {
            return {
                arrival: '',
                departure: '',
                guests: '',
                coupon: '',
                first_name: '',
                last_name: '',
                email: '',
                phone: ''
            };
        }

        return {
            arrival: form.arrival ? form.arrival.value : '',
            departure: form.departure ? form.departure.value : '',
            guests: form.guests ? form.guests.value : '',
            coupon: form.coupon ? form.coupon.value : '',
            first_name: form.first_name ? form.first_name.value : '',
            last_name: form.last_name ? form.last_name.value : '',
            email: form.email ? form.email.value : '',
            phone: form.phone ? form.phone.value : ''
        };
    }

    function hasQuoteRequirements(payload) {
        return Boolean(
            payload &&
                payload.arrival &&
                payload.departure &&
                payload.first_name &&
                payload.last_name &&
                payload.email
        );
    }

    function getGenericError(listingData) {
        return getText(listingData, 'genericError', 'Unable to process booking. Please try again.');
    }

    function requestAvailability(state) {
        var listingData = state.listingData;

        if (!listingData || !listingData.api) {
            renderAvailability(state, {});
            return;
        }

        fetch(listingData.api + '/availability')
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    renderAvailability(state, {});
                    return;
                }

                renderAvailability(state, data);
            })
            .catch(function () {
                renderAvailability(state, {});
            });
    }

    function handleQuoteResponse(state, payload, currentId, quote) {
        if (currentId !== state.quoteRequestId) {
            return;
        }

        if (quote && quote.error) {
            throw new Error(quote.error);
        }

        state.latestPayload = payload;
        populateQuote(state, quote);
        setButtonState(state.continueButton, false);
        setMessage(
            state,
            'success',
            getText(
                state.listingData,
                'quoteReady',
                'Quote ready! Review the details before continuing to payment.'
            )
        );
    }

    function requestQuote(state) {
        var form = state.form;
        var listingData = state.listingData;

        if (state.quoteDebounceId) {
            window.clearTimeout(state.quoteDebounceId);
            state.quoteDebounceId = null;
        }

        var payload = collectPayload(form);

        resetMessage(state.message);

        if (!hasQuoteRequirements(payload)) {
            state.latestPayload = null;
            populateQuote(state, null);
            setButtonState(state.continueButton, true);
            setButtonState(state.submitButton, false);
            setMessage(
                state,
                'info',
                getText(
                    state.listingData,
                    'quotePrompt',
                    'Enter your trip details to see an instant quote.'
                )
            );

            if (state.quoteController && supportsAbortController) {
                state.quoteController.abort();
            }

            state.quoteController = null;
            return;
        }

        if (!listingData || !listingData.api) {
            state.latestPayload = null;
            populateQuote(state, null);
            setButtonState(state.continueButton, true);
            setButtonState(state.submitButton, false);
            setMessage(state, 'error', getGenericError(listingData));
            return;
        }

        setButtonState(state.continueButton, true);
        setButtonState(state.submitButton, true);
        setMessage(state, 'info', getText(listingData, 'quoteLoading', 'Fetching your quote…'));

        if (state.quoteController && supportsAbortController) {
            state.quoteController.abort();
        }

        var signal = null;
        if (supportsAbortController) {
            state.quoteController = new AbortController();
            signal = state.quoteController.signal;
        } else {
            state.quoteController = null;
        }

        state.quoteRequestId += 1;
        var currentId = state.quoteRequestId;

        var fetchOptions = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        };

        if (signal) {
            fetchOptions.signal = signal;
        }

        fetch(listingData.api + '/quote', fetchOptions)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(getGenericError(listingData));
                }

                return response.json();
            })
            .then(function (quote) {
                handleQuoteResponse(state, payload, currentId, quote);
            })
            .catch(function (error) {
                if (supportsAbortController && error && error.name === 'AbortError') {
                    return;
                }

                if (currentId !== state.quoteRequestId) {
                    return;
                }

                state.latestPayload = null;
                populateQuote(state, null);
                setButtonState(state.continueButton, true);
                setMessage(state, 'error', error && error.message ? error.message : getGenericError(listingData));
            })
            .finally(function () {
                if (currentId === state.quoteRequestId) {
                    if (supportsAbortController) {
                        state.quoteController = null;
                    }
                    setButtonState(state.submitButton, false);
                }
            });
    }

    function scheduleQuote(state) {
        if (state.quoteDebounceId) {
            window.clearTimeout(state.quoteDebounceId);
        }

        state.quoteDebounceId = window.setTimeout(function () {
            state.quoteDebounceId = null;
            requestQuote(state);
        }, 350);
    }

    function handleContinue(state) {
        var latestPayload = state.latestPayload;
        var listingData = state.listingData;

        if (!latestPayload) {
            setMessage(
                state,
                'info',
                getText(
                    listingData,
                    'quoteRequired',
                    'Request a quote before continuing to secure payment.'
                )
            );
            return;
        }

        setButtonState(state.continueButton, true);
        setButtonState(state.submitButton, true);
        setMessage(state, 'info', getText(listingData, 'checkoutPreparing', 'Preparing secure checkout…'));

        if (!listingData || !listingData.api) {
            setButtonState(state.continueButton, false);
            setButtonState(state.submitButton, false);
            setMessage(state, 'error', getGenericError(listingData));
            return;
        }

        fetch(listingData.api + '/booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(latestPayload)
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(getGenericError(listingData));
                }

                return response.json();
            })
            .then(function (result) {
                if (result && result.error) {
                    throw new Error(result.error);
                }

                setMessage(state, 'success', getText(listingData, 'redirecting', 'Redirecting to secure checkout…'));

                if (result && result.checkout_url) {
                    window.location.href = result.checkout_url;
                }
            })
            .catch(function (error) {
                setButtonState(state.continueButton, false);
                setButtonState(state.submitButton, false);
                setMessage(state, 'error', error && error.message ? error.message : getGenericError(listingData));
            })
            .finally(function () {
                setButtonState(state.submitButton, false);

                if (state.latestPayload) {
                    setButtonState(state.continueButton, false);
                }
            });
    }

    function normalizeSelectors(listingData) {
        var overrides = (listingData && listingData.selectors) || {};
        var selectors = {};

        for (var key in SELECTOR_DEFAULTS) {
            if (!Object.prototype.hasOwnProperty.call(SELECTOR_DEFAULTS, key)) {
                continue;
            }

            selectors[key] = overrides[key] || SELECTOR_DEFAULTS[key];
        }

        return selectors;
    }

    function mountWidget(widget, listingData) {
        if (!widget || widgetState.has(widget)) {
            return;
        }

        var selectors = normalizeSelectors(listingData);

        var form = widget.querySelector(selectors.form);
        var quotePanel = widget.querySelector(selectors.quote);
        var message = widget.querySelector(selectors.message);
        var submitButton = widget.querySelector(selectors.submit);
        var continueButtons = widget.querySelectorAll(selectors.continueButton);
        var continueButton = continueButtons.length > 0 ? continueButtons[0] : null;
        var availability = widget.querySelector(selectors.availability);
        var availabilityCalendar = widget.querySelector(selectors.availabilityCalendar);
        var rateList = widget.querySelector(selectors.rateList);

        if (continueButtons.length > 1) {
            for (var i = 1; i < continueButtons.length; i += 1) {
                var extra = continueButtons[i];
                if (extra && extra.parentNode) {
                    extra.parentNode.removeChild(extra);
                }
            }
        }

        if (!form || !quotePanel || !continueButton || !availability) {
            return;
        }

        var currency = availability.getAttribute('data-currency') || (listingData && listingData.currency) || 'USD';
        var formatCurrency = formatCurrencyFactory(currency);

        var state = {
            widget: widget,
            listingData: listingData,
            selectors: selectors,
            form: form,
            quotePanel: quotePanel,
            message: message,
            submitButton: submitButton,
            continueButton: continueButton,
            availability: availability,
            availabilityCalendar: availabilityCalendar,
            rateList: rateList,
            formatCurrency: formatCurrency,
            quoteController: null,
            quoteDebounceId: null,
            quoteRequestId: 0,
            latestPayload: null
        };

        widgetState.set(widget, state);

        requestAvailability(state);
        scheduleQuote(state);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        var handleChange = function () {
            state.latestPayload = null;
            populateQuote(state, null);
            setButtonState(state.continueButton, true);
            resetMessage(state.message);
            scheduleQuote(state);
        };

        form.addEventListener('input', handleChange);
        form.addEventListener('change', handleChange);

        continueButton.addEventListener('click', function () {
            handleContinue(state);
        });

        widget.dataset.vrspReady = 'true';
    }

    function init(isRefresh) {
        var listingData = window.vrspListing;
        var widgets = document.querySelectorAll('[data-vrsp-widget], .vrsp-booking-widget');

        if (!widgets.length || typeof listingData === 'undefined') {
            if (!isRefresh && initAttempts < INIT_RETRY_LIMIT) {
                initAttempts += 1;
                window.setTimeout(function () {
                    init(false);
                }, INIT_RETRY_DELAY);
            }
            return;
        }

        for (var i = 0; i < widgets.length; i += 1) {
            mountWidget(widgets[i], listingData);
        }
    }

    window.vrspBookingWidget = {
        init: init,
        refresh: function refresh() {
            init(true);
        },
        version: '1.1.1'
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(false);
        }, { once: true });
    } else {
        init(false);
    }
})(window, document);