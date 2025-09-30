;(function (window, document) {
    'use strict';

    var INIT_RETRY_LIMIT = 20;
    var INIT_RETRY_DELAY = 150;
    var QUOTE_DEBOUNCE = 350;

    var SELECTORS = {
        form: '[data-vrsp="form"], .vrsp-form',
        continueButton: '[data-vrsp="continue"], .vrsp-form__continue',
        message: '[data-vrsp="message"], .vrsp-message',
        availability: '[data-vrsp="availability"], .vrsp-availability',
        calendar: '[data-vrsp="calendar"], .vrsp-availability__calendar',
        payment: '[data-vrsp="payment"], .vrsp-form__payment'
    };

    var SUMMARY_FIELDS = ['arrival', 'departure', 'nights'];
    var PRICING_FIELDS = ['stay', 'cleaning', 'taxes', 'total', 'deposit', 'balance'];

    var stateByWidget = new WeakMap();
    var initAttempts = 0;
    var supportsAbortController = typeof window.AbortController === 'function';

    function getText(listingData, key, fallback) {
        if (listingData && listingData.i18n && listingData.i18n[key]) {
            return listingData.i18n[key];
        }

        return fallback;
    }

    function createFormatter(currencyCode) {
        var code = currencyCode || 'USD';

        try {
            var formatter = new window.Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: code
            });

            return function (value) {
                return formatter.format(Number(value || 0));
            };
        } catch (error) {
            return function (value) {
                var amount = Number(value || 0).toFixed(2);
                return code + ' ' + amount;
            };
        }
    }

    function clearChildren(node) {
        if (!node) {
            return;
        }
        return fallback;
    }

        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function readForm(form) {
        if (!form) {
            return {
                arrival: '',
                departure: '',
                guests: '',
                coupon: '',
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                payment_option: 'deposit'
            };
        }

        var paymentOption = 'deposit';
        if (form.payment_option) {
            try {
                paymentOption = form.payment_option.value || 'deposit';
            } catch (error) {
                paymentOption = 'deposit';
            }
        }

        return {
            arrival: form.arrival ? form.arrival.value : '',
            departure: form.departure ? form.departure.value : '',
            guests: form.guests ? form.guests.value : '',
            coupon: form.coupon ? form.coupon.value : '',
            first_name: form.first_name ? form.first_name.value : '',
            last_name: form.last_name ? form.last_name.value : '',
            email: form.email ? form.email.value : '',
            phone: form.phone ? form.phone.value : '',
            payment_option: paymentOption
        };
    }

    function hasQuoteFields(payload) {
        return payload && payload.arrival && payload.departure;
    }

    function hasCheckoutFields(payload) {
        return (
            payload &&
            payload.first_name &&
            payload.last_name &&
            payload.email
        );
    }

    function sameCoreQuoteFields(nextPayload, previousPayload) {
        if (!nextPayload || !previousPayload) {
            return false;
        }

        var keys = ['arrival', 'departure', 'guests', 'coupon'];
        for (var i = 0; i < keys.length; i += 1) {
            var key = keys[i];
            if ((nextPayload[key] || '') !== (previousPayload[key] || '')) {
                return false;
            }
        }

        return true;
    }

    function setButtonDisabled(button, disabled) {
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

    function writeMessage(state, type, text) {
        var node = state.message;

        if (!node) {
            return;
        }

        node.className = 'vrsp-message';

        if (!text) {
            node.textContent = '';
            return;
        }

        node.classList.add(type);
        node.textContent = text;
    }

    function parseISODate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var parts = value.split('-');
        if (parts.length < 3) {
            return null;
        }

        var year = Number(parts[0]);
        var month = Number(parts[1]) - 1;
        var day = Number(parts[2]);

        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            return null;
        }

        var date = new Date(year, month, day);
        if (isNaN(date.getTime())) {
            return null;
        }

        return date;
    }

    function formatDate(value) {
        var date = parseISODate(value);
        if (!date) {
            return '—';
        }

        try {
            return date.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch (error) {
            return value;
        }
    }

    function differenceInDays(fromDate, toDate) {
        if (!(fromDate instanceof Date) || !(toDate instanceof Date)) {
            return null;
        }

        var fromUTC = Date.UTC(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate());
        var toUTC = Date.UTC(toDate.getFullYear(), toDate.getMonth(), toDate.getDate());
        var diff = toUTC - fromUTC;

        return Math.round(diff / 86400000);
    }

    function computeNights(payload) {
        var arrival = parseISODate(payload && payload.arrival);
        var departure = parseISODate(payload && payload.departure);

        if (!arrival || !departure) {
            return null;
        }

        var diff = differenceInDays(arrival, departure);
        if (diff === null || diff <= 0) {
            return null;
        }

        return diff;
    }

    function roundCurrency(value) {
        var amount = Number(value || 0);
        if (!isFinite(amount)) {
            amount = 0;
        }

        return Math.round(amount * 100) / 100;
    }

    function updateSummary(state, payload) {
        var summary = state.summaryTargets;
        if (!summary) {
            return;
        }

        if (summary.arrival) {
            summary.arrival.textContent = formatDate(payload.arrival);
        }

        if (summary.departure) {
            summary.departure.textContent = formatDate(payload.departure);
        }

        if (summary.nights) {
            var nights = computeNights(payload);
            summary.nights.textContent = nights !== null ? nights : '—';
        }
    }

    function resetPricing(state) {
        var targets = state.pricingTargets;
        if (targets) {
            for (var i = 0; i < PRICING_FIELDS.length; i += 1) {
                var field = PRICING_FIELDS[i];
                if (targets[field]) {
                    targets[field].textContent = '—';
                }
            }
        }

        if (state.pricingNote) {
            state.pricingNote.textContent = '';
        }

        updatePaymentOptions(state, null);
        state.lastBreakdown = null;
    }

    function computeBreakdown(state, payload, quote) {
        if (!quote) {
            return null;
        }

        var nights = null;
        if (typeof quote.nights !== 'undefined' && quote.nights !== null && quote.nights !== '') {
            nights = Number(quote.nights);
            nights = isNaN(nights) ? null : nights;
        }

        if (nights === null) {
            nights = computeNights(payload);
        }

        var stay = Number(quote.subtotal || 0);
        if (!isFinite(stay)) {
            stay = 0;
        }

        var cleaning = Number(quote.cleaning_fee || quote.cleaning || 0);
        if (!isFinite(cleaning)) {
            cleaning = 0;
        }

        var taxFields = ['taxes', 'fees', 'service_fee', 'damage_fee'];
        var taxes = 0;
        for (var i = 0; i < taxFields.length; i += 1) {
            var value = Number(quote[taxFields[i]] || 0);
            if (isFinite(value)) {
                taxes += value;
            }
        }

        var total = Number(quote.total || stay + cleaning + taxes);
        if (!isFinite(total)) {
            total = stay + cleaning + taxes;
        }

        total = roundCurrency(total);
        stay = roundCurrency(stay);
        cleaning = roundCurrency(cleaning);
        taxes = roundCurrency(taxes);

        var rules = (state.listingData && state.listingData.rules) || {};
        var threshold = Number(rules.deposit_threshold);
        if (!isFinite(threshold)) {
            threshold = 7;
        }

        var percent = Number(rules.deposit_percent);
        if (!isFinite(percent) || percent <= 0 || percent >= 1) {
            percent = 0.5;
        }

        var arrivalDate = parseISODate(payload.arrival);
        var today = new Date();
        var daysUntilArrival = differenceInDays(today, arrivalDate);
        var requiresFull = daysUntilArrival === null ? false : daysUntilArrival <= threshold;

        var depositBase = roundCurrency(total * percent);
        if (depositBase > total) {
            depositBase = total;
        }

        var paymentOption = payload.payment_option === 'full' ? 'full' : 'deposit';
        if (requiresFull) {
            paymentOption = 'full';
        }

        var deposit = paymentOption === 'full' ? total : depositBase;
        deposit = roundCurrency(deposit);

        if (deposit > total) {
            deposit = total;
        }

        var balance = roundCurrency(total - deposit);
        if (balance < 0) {
            balance = 0;
        }

        var noteKey = paymentOption === 'full' ? 'fullBalanceNote' : 'depositNote';

        return {
            nights: nights,
            stay: stay,
            cleaning: cleaning,
            taxes: taxes,
            total: total,
            deposit: deposit,
            balance: balance,
            requiresFull: requiresFull,
            paymentOption: paymentOption,
            noteKey: noteKey
        };
    }

    function updatePaymentOptions(state, breakdown) {
        var payment = state.payment;
        if (!payment) {
            return;
        }

        var depositRadio = payment.deposit;
        var fullRadio = payment.full;

        if (!breakdown) {
            if (depositRadio) {
                depositRadio.disabled = false;
            }

            if (payment.note) {
                payment.note.textContent = '';
            }

            return;
        }

        if (depositRadio) {
            depositRadio.disabled = breakdown.requiresFull;
            if (breakdown.requiresFull) {
                depositRadio.checked = false;
            } else if (breakdown.paymentOption === 'deposit') {
                depositRadio.checked = true;
            }
        }

        if (fullRadio) {
            if (breakdown.paymentOption === 'full' || breakdown.requiresFull) {
                fullRadio.checked = true;
            } else if (depositRadio && depositRadio.checked) {
                fullRadio.checked = false;
            }
        }

        if (payment.note) {
            if (breakdown.requiresFull) {
                payment.note.textContent = getText(
                    state.listingData,
                    'paymentFullRequired',
                    'This stay begins within 7 days. Full payment is required today.'
                );
            } else {
                payment.note.textContent = getText(
                    state.listingData,
                    'paymentChoice',
                    'Pay a 50% deposit today or choose to pay in full.'
                );
            }
        }
    }

    function writePricing(state, payload, quote) {
        var breakdown = computeBreakdown(state, payload, quote);

        if (!breakdown) {
            resetPricing(state);
            return false;
        }

        state.lastBreakdown = breakdown;

        var targets = state.pricingTargets;
        if (targets) {
            if (targets.stay) {
                targets.stay.textContent = state.formatCurrency(breakdown.stay);
            }

            if (targets.cleaning) {
                targets.cleaning.textContent = state.formatCurrency(breakdown.cleaning);
            }

            if (targets.taxes) {
                targets.taxes.textContent = state.formatCurrency(breakdown.taxes);
            }

            if (targets.total) {
                targets.total.textContent = state.formatCurrency(breakdown.total);
            }

            if (targets.deposit) {
                targets.deposit.textContent = state.formatCurrency(breakdown.deposit);
            }

            if (targets.balance) {
                targets.balance.textContent = state.formatCurrency(breakdown.balance);
            }
        }

        if (state.pricingNote) {
            state.pricingNote.textContent = getText(
                state.listingData,
                breakdown.noteKey,
                breakdown.paymentOption === 'full'
                    ? 'Your stay begins soon, so the full balance is due today.'
                    : 'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.'
            );
        }

        updatePaymentOptions(state, breakdown);

        if (state.summaryTargets && state.summaryTargets.nights && breakdown.nights !== null) {
            state.summaryTargets.nights.textContent = breakdown.nights;
        }

        return true;
    }

    function renderAvailability(state, payload) {
        var calendar = state.calendar;

        if (calendar) {
            clearChildren(calendar);

            var blocked = (payload && payload.blocked) || [];

            if (!blocked.length) {
                var message = document.createElement('p');
                message.textContent = getText(state.listingData, 'availabilityEmpty', 'Your preferred dates are open!');
                calendar.appendChild(message);
            } else {
                var limit = Math.min(blocked.length, 8);
                for (var i = 0; i < limit; i += 1) {
                    var windowItem = blocked[i];
                    if (!windowItem) {
                        continue;
                    }

                    var tag = document.createElement('span');
                    tag.className = 'vrsp-availability__tag';
                    tag.textContent = windowItem.start + ' → ' + windowItem.end;
                    calendar.appendChild(tag);
                }
            }
        }
    }

    function fetchAvailability(state) {
        var listingData = state.listingData;

        if (!listingData || !listingData.api) {
            renderAvailability(state, {});
            return;
        }

        fetch(listingData.api + '/availability')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed');
                }
                return response.json();
            })
            .then(function (data) {
                renderAvailability(state, data || {});
            })
            .catch(function () {
                renderAvailability(state, {});
            });
    }

    function scheduleQuote(state) {
        if (state.quoteTimer) {
            window.clearTimeout(state.quoteTimer);
        }

        state.quoteTimer = window.setTimeout(function () {
            state.quoteTimer = null;
            requestQuote(state);
        }, QUOTE_DEBOUNCE);
    }

    function requestQuote(state) {
        var listingData = state.listingData;
        var payload = readForm(state.form);

        updateSummary(state, payload);

        if (!hasQuoteFields(payload)) {
            state.latestPayload = null;
            state.latestQuote = null;
            resetPricing(state);
            setButtonDisabled(state.continueButton, true);
            writeMessage(
                state,
                'info',
                getText(listingData, 'quotePrompt', 'Select arrival and departure dates to see pricing.')
            );
            return;
        }

        setButtonDisabled(state.continueButton, true);
        writeMessage(state, 'info', getText(listingData, 'quoteLoading', 'Calculating pricing…'));

        if (state.quoteAbort && supportsAbortController) {
            state.quoteAbort.abort();
        }

        var controller = supportsAbortController ? new window.AbortController() : null;
        state.quoteAbort = controller;

        var options = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        };

        if (controller) {
            options.signal = controller.signal;
        }

        fetch(listingData.api + '/quote', options)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(getText(listingData, 'genericError', 'Unable to process booking. Please try again.'));
                }
                return response.json();
            })
            .then(function (quote) {
                state.latestPayload = Object.assign({}, payload);
                state.latestQuote = quote;

                writePricing(state, payload, quote);

                if (hasCheckoutFields(payload)) {
                    setButtonDisabled(state.continueButton, false);
                    writeMessage(
                        state,
                        'success',
                        getText(listingData, 'quoteReady', 'Pricing updated! Review and continue to secure payment.')
                    );
                } else {
                    setButtonDisabled(state.continueButton, true);
                    writeMessage(
                        state,
                        'info',
                        getText(listingData, 'checkoutDetails', 'Add guest contact details to continue to secure payment.')
                    );
                }
            })
            .catch(function (error) {
                if (controller && error && error.name === 'AbortError') {
                    return;
                }

                state.latestPayload = Object.assign({}, payload);
                state.latestQuote = null;
                resetPricing(state);

                var fallback = getText(listingData, 'genericError', 'Unable to process booking. Please try again.');
                writeMessage(state, 'error', (error && error.message) || fallback);
                setButtonDisabled(state.continueButton, true);
            })
            .finally(function () {
                if (state.quoteAbort === controller) {
                    state.quoteAbort = null;
                }
            });
    }

    function continueToCheckout(state) {
        var listingData = state.listingData;
        var payload = readForm(state.form);

        if (!state.latestQuote) {
            writeMessage(
                state,
                'info',
                getText(listingData, 'quoteRequired', 'Request a quote before continuing to secure payment.')
            );
            return;
        }

        if (!sameCoreQuoteFields(payload, state.latestPayload || {})) {
            writeMessage(
                state,
                'info',
                getText(listingData, 'quoteRefresh', 'Your stay details changed. Updating pricing…')
            );
            state.latestQuote = null;
            state.latestPayload = null;
            resetPricing(state);
            scheduleQuote(state);
            return;
        }

        if (!hasCheckoutFields(payload)) {
            writeMessage(
                state,
                'info',
                getText(listingData, 'checkoutDetails', 'Add guest contact details to continue to secure payment.')
            );
            return;
        }

        setButtonDisabled(state.continueButton, true);
        writeMessage(state, 'info', getText(listingData, 'checkoutPreparing', 'Preparing secure checkout…'));

        fetch(listingData.api + '/booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(getText(listingData, 'genericError', 'Unable to process booking. Please try again.'));
                }
                return response.json();
            })
            .then(function (data) {
                if (data && data.error) {
                    throw new Error(data.error);
                }

                writeMessage(state, 'success', getText(listingData, 'redirecting', 'Redirecting to secure checkout…'));

                if (data && data.checkout_url) {
                    window.location.href = data.checkout_url;
                }
            })
            .catch(function (error) {
                setButtonDisabled(state.continueButton, false);
                var fallback = getText(listingData, 'genericError', 'Unable to process booking. Please try again.');
                writeMessage(state, 'error', (error && error.message) || fallback);
            });
    }

    function collectSummaryTargets(widget) {
        var targets = {};
        for (var i = 0; i < SUMMARY_FIELDS.length; i += 1) {
            var field = SUMMARY_FIELDS[i];
            targets[field] = widget.querySelector('[data-summary="' + field + '"]');
        }
        return targets;
    }

    function collectPricingTargets(widget) {
        var targets = {};
        for (var i = 0; i < PRICING_FIELDS.length; i += 1) {
            var field = PRICING_FIELDS[i];
            targets[field] = widget.querySelector('[data-pricing="' + field + '"]');
        }
        return targets;
    }

    function collectPaymentControls(widget) {
        var container = widget.querySelector(SELECTORS.payment);
        if (!container) {
            return null;
        }

        return {
            container: container,
            deposit: container.querySelector('[data-payment="deposit"]'),
            full: container.querySelector('[data-payment="full"]'),
            note: container.querySelector('[data-payment="note"]')
        };
    }

    function mountWidget(widget, listingData) {
        if (!widget) {
            return;
        }

        var form = widget.querySelector(SELECTORS.form);
        var continueButtons = widget.querySelectorAll(SELECTORS.continueButton);
        var continueButton = continueButtons.length ? continueButtons[0] : null;
        var message = widget.querySelector(SELECTORS.message);
        var availability = widget.querySelector(SELECTORS.availability);
        var calendar = widget.querySelector(SELECTORS.calendar);

        if (continueButtons.length > 1) {
            for (var i = 1; i < continueButtons.length; i += 1) {
                var duplicate = continueButtons[i];
                if (duplicate && duplicate.parentNode) {
                    duplicate.parentNode.removeChild(duplicate);
                }
            }
        }

        if (!form || !continueButton) {
            return;
        }

        var currency = 'USD';
        var baseRate = 0;

        if (availability) {
            var currencyAttr = availability.getAttribute('data-currency');
            if (currencyAttr) {
                currency = currencyAttr;
            } else if (listingData && listingData.currency) {
                currency = listingData.currency;
            }

            var baseAttr = availability.getAttribute('data-base-rate');
            if (baseAttr) {
                var parsedBase = parseFloat(baseAttr);
                baseRate = isNaN(parsedBase) ? 0 : parsedBase;
            }
        } else if (listingData && listingData.currency) {
            currency = listingData.currency;
        }

        var state = {
            widget: widget,
            listingData: listingData,
            form: form,
            continueButton: continueButton,
            message: message,
            availability: availability,
            calendar: calendar,
            baseRate: baseRate,
            formatCurrency: createFormatter(currency),
            summaryTargets: collectSummaryTargets(widget),
            pricingTargets: collectPricingTargets(widget),
            pricingNote: widget.querySelector('[data-pricing="note"]'),
            payment: collectPaymentControls(widget),
            latestPayload: null,
            latestQuote: null,
            lastBreakdown: null,
            quoteTimer: null,
            quoteAbort: null
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        var onFormChange = function () {
            var payload = readForm(form);
            updateSummary(state, payload);

            if (!hasQuoteFields(payload)) {
                if (state.quoteAbort && supportsAbortController) {
                    state.quoteAbort.abort();
                    state.quoteAbort = null;
                }

                state.latestPayload = null;
                state.latestQuote = null;
                resetPricing(state);
                setButtonDisabled(state.continueButton, true);
                writeMessage(
                    state,
                    'info',
                    getText(state.listingData, 'quotePrompt', 'Select arrival and departure dates to see pricing.')
                );
                return;
            }

            if (state.latestQuote && state.latestPayload && sameCoreQuoteFields(payload, state.latestPayload)) {
                state.latestPayload = Object.assign({}, state.latestPayload, payload);
                writePricing(state, payload, state.latestQuote);

                if (hasCheckoutFields(payload)) {
                    setButtonDisabled(state.continueButton, false);
                    writeMessage(
                        state,
                        'success',
                        getText(state.listingData, 'quoteReady', 'Pricing updated! Review and continue to secure payment.')
                    );
                } else {
                    setButtonDisabled(state.continueButton, true);
                    writeMessage(
                        state,
                        'info',
                        getText(state.listingData, 'checkoutDetails', 'Add guest contact details to continue to secure payment.')
                    );
                }

                return;
            }

            if (state.quoteAbort && supportsAbortController) {
                state.quoteAbort.abort();
                state.quoteAbort = null;
            }

            state.latestPayload = null;
            state.latestQuote = null;
            state.lastBreakdown = null;
            resetPricing(state);
            setButtonDisabled(state.continueButton, true);
            writeMessage(state, 'info', getText(state.listingData, 'quoteLoading', 'Calculating pricing…'));
            scheduleQuote(state);
        };

        form.addEventListener('input', onFormChange);
        form.addEventListener('change', onFormChange);

        continueButton.addEventListener('click', function () {
            continueToCheckout(state);
        });

        if (state.payment && state.payment.container) {
            state.payment.container.addEventListener('change', function (event) {
                if (event && event.target && event.target.name === 'payment_option') {
                    var payload = readForm(form);
                    if (state.latestQuote && state.latestPayload && sameCoreQuoteFields(payload, state.latestPayload)) {
                        writePricing(state, payload, state.latestQuote);
                    }
                }
            });
        }

        stateByWidget.set(widget, state);

        updateSummary(state, readForm(form));
        resetPricing(state);
        fetchAvailability(state);
        requestQuote(state);
    }

    function refreshState(widget, listingData) {
        var state = stateByWidget.get(widget);
        if (!state) {
            mountWidget(widget, listingData);
            return;
        }

        state.listingData = listingData;

        var availability = state.availability;
        var currency = listingData && listingData.currency ? listingData.currency : 'USD';
        var baseRate = state.baseRate;

        if (availability) {
            var currencyAttr = availability.getAttribute('data-currency');
            if (currencyAttr) {
                currency = currencyAttr;
            }

            var baseAttr = availability.getAttribute('data-base-rate');
            if (baseAttr) {
                var parsedBase = parseFloat(baseAttr);
                baseRate = isNaN(parsedBase) ? baseRate : parsedBase;
            }
        }

        state.baseRate = baseRate;
        state.formatCurrency = createFormatter(currency);

        fetchAvailability(state);
        requestQuote(state);
    }

    function init(refreshOnly) {
        var listingData = window.vrspListing;
        var widgets = document.querySelectorAll('[data-vrsp-widget], .vrsp-booking-widget');

        if (!widgets.length || typeof listingData === 'undefined') {
            if (!refreshOnly && initAttempts < INIT_RETRY_LIMIT) {
                initAttempts += 1;
                window.setTimeout(function () {
                    init(false);
                }, INIT_RETRY_DELAY);
            }
            return;
        }

        for (var i = 0; i < widgets.length; i += 1) {
            refreshState(widgets[i], listingData);
        }
    }

    window.vrspBookingWidget = {
        init: init,
        refresh: function () {
            init(true);
        },
        version: '1.3.0'
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            function () {
                init(false);
            },
            { once: true }
        );
    } else {
        init(false);
    }
})(window, document);
