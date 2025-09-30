;(function (window, document) {
    'use strict';

    var INIT_RETRY_LIMIT = 20;
    var INIT_RETRY_DELAY = 150;
    var QUOTE_DEBOUNCE = 350;

    var SELECTORS = {
        form: '[data-vrsp="form"], .vrsp-form',
        continueButton: '[data-vrsp="continue"], .vrsp-form__continue',
        message: '[data-vrsp="message"], .vrsp-message',
        quotePanel: '[data-vrsp="quote"], .vrsp-quote',
        availability: '[data-vrsp="availability"], .vrsp-availability',
        calendar: '[data-vrsp="calendar"], .vrsp-availability__calendar',
        rateList: '[data-vrsp="rate-list"], .vrsp-availability__rate-list'
    };

    var stateByWidget = new WeakMap();
    var initAttempts = 0;
    var supportsAbortController = typeof window.AbortController === 'function';

    function getText(listingData, key, fallback) {
        if (listingData && listingData.i18n && listingData.i18n[key]) {
            return listingData.i18n[key];
        }
        return fallback;
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

    function hasRequiredQuoteFields(payload) {
        return (
            payload &&
            payload.arrival &&
            payload.departure &&
            payload.first_name &&
            payload.last_name &&
            payload.email
        );
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

    function updateQuote(state, quote) {
        var panel = state.quotePanel;
        var formatCurrency = state.formatCurrency;

        if (!panel) {
            return;
        }

        if (!quote) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;

        var write = function (key, value) {
            var target = state.quoteTargets[key];
            if (target) {
                target.textContent = value;
            }
        };

        write('nights', quote.nights || '—');
        write('subtotal', formatCurrency(quote.subtotal || 0));

        var taxes = 0;
        taxes += Number(quote.taxes || 0);
        taxes += Number(quote.cleaning_fee || 0);
        taxes += Number(quote.damage_fee || 0);
        write('taxes', formatCurrency(taxes));

        write('total', formatCurrency(quote.total || 0));
        write('deposit', formatCurrency(quote.deposit || 0));

        if (quote.deposit && quote.total && Number(quote.deposit) < Number(quote.total)) {
            state.balanceRow.style.display = '';
            write('balance', formatCurrency(quote.balance || 0));
            state.note.textContent = getText(
                state.listingData,
                'depositNote',
                'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.'
            );
        } else {
            state.balanceRow.style.display = 'none';
            state.note.textContent = getText(
                state.listingData,
                'fullBalanceNote',
                'Your stay begins soon, so the full balance is due today.'
            );
        }
    }

    function renderAvailability(state, payload) {
        var calendar = state.calendar;
        var rateList = state.rateList;
        var formatCurrency = state.formatCurrency;

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

        if (rateList) {
            clearChildren(rateList);

            var rates = (payload && payload.rates) || [];

            if (!rates.length) {
                var fallback = document.createElement('span');
                fallback.className = 'rate-pill';
                var pattern = getText(state.listingData, 'rateFallback', 'Nightly from %s');
                fallback.textContent = pattern.replace('%s', formatCurrency(state.baseRate || 0));
                rateList.appendChild(fallback);
                return;
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

        if (!hasRequiredQuoteFields(payload)) {
            state.latestPayload = null;
            state.latestQuote = null;
            if (state.quoteAbort && supportsAbortController) {
                state.quoteAbort.abort();
            }
            state.quoteAbort = null;
            updateQuote(state, null);
            setButtonDisabled(state.continueButton, true);
            writeMessage(
                state,
                'info',
                getText(listingData, 'quotePrompt', 'Enter your trip details to see an instant quote.')
            );
            return;
        }

        if (!listingData || !listingData.api) {
            state.latestPayload = null;
            state.latestQuote = null;
            updateQuote(state, null);
            setButtonDisabled(state.continueButton, true);
            writeMessage(state, 'error', getText(listingData, 'genericError', 'Unable to process booking. Please try again.'));
            return;
        }

        setButtonDisabled(state.continueButton, true);
        writeMessage(state, 'info', getText(listingData, 'quoteLoading', 'Fetching your quote…'));

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
                state.latestPayload = payload;
                state.latestQuote = quote;
                updateQuote(state, quote);
                setButtonDisabled(state.continueButton, false);
                writeMessage(
                    state,
                    'success',
                    getText(listingData, 'quoteReady', 'Quote ready! Review the details before continuing to payment.')
                );
            })
            .catch(function (error) {
                if (controller && error && error.name === 'AbortError') {
                    return;
                }

                state.latestPayload = null;
                state.latestQuote = null;
                updateQuote(state, null);
                setButtonDisabled(state.continueButton, true);
                var fallback = getText(listingData, 'genericError', 'Unable to process booking. Please try again.');
                writeMessage(state, 'error', (error && error.message) || fallback);
            })
            .finally(function () {
                if (state.quoteAbort === controller) {
                    state.quoteAbort = null;
                }
            });
    }

    function continueToCheckout(state) {
        var listingData = state.listingData;
        var payload = state.latestPayload;

        if (!payload) {
            writeMessage(
                state,
                'info',
                getText(listingData, 'quoteRequired', 'Request a quote before continuing to secure payment.')
            );
            return;
        }

        if (!listingData || !listingData.api) {
            writeMessage(state, 'error', getText(listingData, 'genericError', 'Unable to process booking. Please try again.'));
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

    function collectQuoteTargets(widget) {
        var fields = ['nights', 'subtotal', 'taxes', 'total', 'deposit', 'balance'];
        var targets = {};

        for (var i = 0; i < fields.length; i += 1) {
            var field = fields[i];
            targets[field] = widget.querySelector('[data-quote="' + field + '"]');
        }

        return targets;
    }

    function mountWidget(widget, listingData) {
        if (!widget) {
            return;
        }

        var form = widget.querySelector(SELECTORS.form);
        var continueButtons = widget.querySelectorAll(SELECTORS.continueButton);
        var continueButton = continueButtons.length ? continueButtons[0] : null;
        var quotePanel = widget.querySelector(SELECTORS.quotePanel);
        var message = widget.querySelector(SELECTORS.message);
        var availability = widget.querySelector(SELECTORS.availability);
        var calendar = widget.querySelector(SELECTORS.calendar);
        var rateList = widget.querySelector(SELECTORS.rateList);

        if (continueButtons.length > 1) {
            for (var i = 1; i < continueButtons.length; i += 1) {
                var duplicate = continueButtons[i];
                if (duplicate && duplicate.parentNode) {
                    duplicate.parentNode.removeChild(duplicate);
                }
            }
        }

        if (!form || !continueButton || !quotePanel) {
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
            quotePanel: quotePanel,
            availability: availability,
            calendar: calendar,
            rateList: rateList,
            baseRate: baseRate,
            formatCurrency: createFormatter(currency),
            quoteTargets: collectQuoteTargets(widget),
            balanceRow: widget.querySelector('[data-quote="balance-row"]') || document.createElement('div'),
            note: widget.querySelector('[data-quote="note"]') || document.createElement('p'),
            latestPayload: null,
            latestQuote: null,
            quoteTimer: null,
            quoteAbort: null
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        var onFormChange = function () {
            state.latestPayload = null;
            state.latestQuote = null;
            updateQuote(state, null);
            setButtonDisabled(state.continueButton, true);
            writeMessage(state, '', '');
            if (state.quoteAbort && supportsAbortController) {
                state.quoteAbort.abort();
                state.quoteAbort = null;
            }
            scheduleQuote(state);
        };

        form.addEventListener('input', onFormChange);
        form.addEventListener('change', onFormChange);

        continueButton.addEventListener('click', function () {
            continueToCheckout(state);
        });

        stateByWidget.set(widget, state);

        setButtonDisabled(state.continueButton, true);
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
        version: '1.2.0'
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