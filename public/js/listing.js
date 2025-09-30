(function (window, document) {
    'use strict';


    if (window.vrspBookingWidget && typeof window.vrspBookingWidget.refresh === 'function') {
        window.vrspBookingWidget.refresh();
        return;
    }

    const INIT_RETRY_LIMIT = 20;
    const INIT_RETRY_DELAY = 150;
    const SELECTOR_DEFAULTS = {
        form: '[data-vrsp="form"], .vrsp-form',
        quote: '[data-vrsp="quote"], .vrsp-quote',
        message: '[data-vrsp="message"], .vrsp-message',
        submit: '[data-vrsp="submit"], .vrsp-form__submit',
        continueButton: '[data-vrsp="continue"], .vrsp-form__continue',
        availability: '[data-vrsp="availability"], .vrsp-availability',
        availabilityCalendar: '[data-vrsp="calendar"], .vrsp-availability__calendar',
        rateList: '[data-vrsp="rate-list"], .vrsp-availability__rate-list',
    };

    let initAttempts = 0;
    const widgetState = new WeakMap();

    const getText = (listingData, key, fallback) => {
        if (listingData && listingData.i18n && listingData.i18n[key]) {
            return listingData.i18n[key];
        }

        return fallback;
    };

    const formatCurrencyFactory = (currency) => {
        return (amount) => {

    const setupWidget = (widget, listingData) => {
        if (!widget || widget.dataset.vrspReady === 'true') {
            return;
        }


        var selectors = {

        const selectors = {

            form: listingData?.selectors?.form || '.vrsp-form',
            quote: listingData?.selectors?.quote || '.vrsp-quote',
            message: listingData?.selectors?.message || '.vrsp-message',
            submit: listingData?.selectors?.submit || '.vrsp-form__submit',
            continueButton: listingData?.selectors?.continue || '.vrsp-form__continue',
            availability: listingData?.selectors?.availability || '.vrsp-availability',
            availabilityCalendar: listingData?.selectors?.availabilityCalendar || '.vrsp-availability__calendar',
            rateList: listingData?.selectors?.rateList || '.vrsp-availability__rate-list',
        };


        var form = widget.querySelector(selectors.form);
        var quotePanel = widget.querySelector(selectors.quote);
        var message = widget.querySelector(selectors.message);
        var submitButton = widget.querySelector(selectors.submit);
        var continueButton = widget.querySelector(selectors.continueButton);
        var availability = widget.querySelector(selectors.availability);
        var availabilityCalendarEl = widget.querySelector(selectors.availabilityCalendar);
        var rateListEl = widget.querySelector(selectors.rateList);


        const form = widget.querySelector(selectors.form);
        const quotePanel = widget.querySelector(selectors.quote);
        const message = widget.querySelector(selectors.message);
        const submitButton = widget.querySelector(selectors.submit);
        const continueButton = widget.querySelector(selectors.continueButton);
        const availability = widget.querySelector(selectors.availability);
        const availabilityCalendarEl = widget.querySelector(selectors.availabilityCalendar);
        const rateListEl = widget.querySelector(selectors.rateList);


        const availabilityCalendar = widget.querySelector(selectors.availabilityCalendar);
        const rateList = widget.querySelector(selectors.rateList);


        const form = widget.querySelector(selectors.form);
        const quotePanel = widget.querySelector(selectors.quote);
        const message = widget.querySelector(selectors.message);
        const submitButton = widget.querySelector(selectors.submit);
        const continueButton = widget.querySelector(selectors.continueButton);
        const availability = widget.querySelector(selectors.availability);
        const availabilityCalendarEl = widget.querySelector(selectors.availabilityCalendar);
        const rateListEl = widget.querySelector(selectors.rateList);


        if (!form || !quotePanel || !continueButton || !availability) {
            return;
        }


        var currency = availability.getAttribute('data-currency') || listingData.currency || 'USD';

        var formatCurrency = (amount) => {

        const currency = availability.getAttribute('data-currency') || listingData.currency || 'USD';

        const formatCurrency = (amount) => {


            const value = Number(amount || 0);
            return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
        };
    };


    const clearChildren = (node) => {
        if (!node) {
            return;
        }

        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    };

    const renderAvailability = (state, data) => {
        const { availabilityCalendar, rateList, listingData, formatCurrency } = state;

        if (availabilityCalendar) {
            clearChildren(availabilityCalendar);

            const blocked = Array.isArray(data?.blocked) ? data.blocked : [];

            if (blocked.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = getText(listingData, 'availabilityEmpty', 'Your preferred dates are open!');
                availabilityCalendar.appendChild(empty);
            } else {
                blocked.slice(0, 8).forEach((event) => {
                    const tag = document.createElement('span');
                    tag.className = 'vrsp-availability__tag';
                    tag.textContent = `${event.start} → ${event.end}`;
                    availabilityCalendar.appendChild(tag);
                });


        var renderBlocked = (blocked) => {
            if (!availabilityCalendarEl) {

        const renderBlocked = (blocked) => {
            if (!availabilityCalendarEl) {




            if (!availabilityCalendar) {



                return;
            }

            availabilityCalendarEl.innerHTML = '';

            if (!Array.isArray(blocked) || blocked.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = window.vrspListing?.i18n?.availabilityEmpty || 'Your preferred dates are open!';
                availabilityCalendarEl.appendChild(empty);
                return;
            }

            blocked.slice(0, 8).forEach((event) => {
                const tag = document.createElement('span');
                tag.className = 'vrsp-availability__tag';
                tag.textContent = `${event.start} → ${event.end}`;
                availabilityCalendarEl.appendChild(tag);
            });
        };


        var renderRates = (rates) => {

        const renderRates = (rates) => {

            if (!rateListEl) {
                return;
            }

            rateListEl.innerHTML = '';
            if (!Array.isArray(rates) || rates.length === 0) {
                return;

            }
        }

        if (rateList) {
            clearChildren(rateList);
            const rates = Array.isArray(data?.rates) ? data.rates : [];
            rates.slice(0, 6).forEach((rate) => {
                const pill = document.createElement('span');
                pill.className = 'rate-pill';
                pill.textContent = `${rate.date}: ${formatCurrency(rate.amount)}`;
                rateListEl.appendChild(pill);
            });
        }
    };


    const populateQuote = (state, quote) => {
        const { widget, quotePanel, formatCurrency, listingData } = state;

        var populateQuote = (quote) => {
            if (!quote) {
                quotePanel.hidden = true;
                return;
            }


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

        var collectPayload = () => ({
            arrival: form.arrival.value,
            departure: form.departure.value,
            guests: form.guests.value,
            coupon: form.coupon.value,
            first_name: form.first_name.value,
            last_name: form.last_name.value,
            email: form.email.value,
            phone: form.phone.value,
        });


        var resetMessage = () => {

        const resetMessage = () => {

            if (!message) {
                return;
            }


        const write = (attr, value) => {
            const target = widget.querySelector(`[data-quote="${attr}"]`);
            if (target) {
                target.textContent = value;
            }
        };


        write('nights', quote.nights);
        write('subtotal', formatCurrency(quote.subtotal));
        const taxes = Number(quote.taxes || 0) + Number(quote.cleaning_fee || 0) + Number(quote.damage_fee || 0);
        write('taxes', formatCurrency(taxes));
        write('total', formatCurrency(quote.total));
        write('deposit', formatCurrency(quote.deposit));

        const balanceRow = widget.querySelector('[data-quote="balance-row"]');
        const note = widget.querySelector('[data-quote="note"]');

        var setButtonState = (button, disabled) => {
            if (!button) {
                return;
            }


        if (balanceRow && note) {
            if (Number(quote.deposit || 0) >= Number(quote.total || 0)) {
                balanceRow.style.display = 'none';
                note.textContent = getText(listingData, 'fullBalanceNote', 'Your stay begins soon, so the full balance is due today.');
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
    };


    const setButtonState = (button, disabled) => {
        if (!button) {
            return;
        }

        button.disabled = !!disabled;

        var getGenericError = () =>
            listingData?.i18n?.genericError || 'Unable to process booking. Please try again.';

        var loadAvailability = () => {
            if (!listingData.api) {
                return;
            }


        if (disabled) {
            button.setAttribute('aria-disabled', 'true');
        } else {
            button.removeAttribute('aria-disabled');
        }
    };


    const resetMessage = (message) => {
        if (!message) {
            return;
        }

        message.className = 'vrsp-message';
        message.textContent = '';
    };

    const setMessage = (state, type, text) => {
        const { message } = state;

        if (!message) {
            return;
        }

        resetMessage(message);
        message.classList.add(type);
        message.textContent = text;
    };

    const collectPayload = (form) => ({
        arrival: form?.arrival?.value || '',
        departure: form?.departure?.value || '',
        guests: form?.guests?.value || '',
        coupon: form?.coupon?.value || '',
        first_name: form?.first_name?.value || '',
        last_name: form?.last_name?.value || '',
        email: form?.email?.value || '',
        phone: form?.phone?.value || '',
    });

    const hasQuoteRequirements = (payload) =>
        Boolean(payload.arrival && payload.departure && payload.first_name && payload.last_name && payload.email);

    const getGenericError = (listingData) => getText(listingData, 'genericError', 'Unable to process booking. Please try again.');

    const requestAvailability = (state) => {
        const { listingData } = state;

        if (!listingData?.api) {
            renderAvailability(state, {});
            return;
        }

        fetch(`${listingData.api}/availability`)
            .then((response) => response.json())
            .then((data) => {
                if (!data || typeof data !== 'object') {
                    renderAvailability(state, {});
                    return;
                }

                renderAvailability(state, data);
            })
            .catch(() => {
                renderAvailability(state, {});
            });
    };

    const handleQuoteResponse = (state, payload, currentId, quote) => {
        if (currentId !== state.quoteRequestId) {
            return;
        }

        if (quote?.error) {
            throw new Error(quote.error);
        }

        state.latestPayload = payload;
        populateQuote(state, quote);
        setButtonState(state.continueButton, false);
        setMessage(state, 'success', getText(state.listingData, 'quoteReady', 'Quote ready! Review the details before continuing to payment.'));
    };

    const requestQuote = (state) => {
        const { form, listingData } = state;

        if (state.quoteDebounceId) {
            window.clearTimeout(state.quoteDebounceId);
            state.quoteDebounceId = null;
        }

        const payload = collectPayload(form);

        resetMessage(state.message);

        if (!hasQuoteRequirements(payload)) {
            state.latestPayload = null;
            populateQuote(state, null);
            setButtonState(state.continueButton, true);
            setButtonState(state.submitButton, false);
            setMessage(state, 'info', getText(state.listingData, 'quotePrompt', 'Enter your trip details to see an instant quote.'));

            if (state.quoteController) {
                state.quoteController.abort();
                state.quoteController = null;
            }

            return;
        }

        if (!listingData?.api) {
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

        if (state.quoteController) {
            state.quoteController.abort();
        }

        state.quoteController = new AbortController();
        const currentId = ++state.quoteRequestId;

        fetch(`${listingData.api}/quote`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            signal: state.quoteController.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(getGenericError(listingData));
                }

                return response.json();
            })
            .then((quote) => {
                handleQuoteResponse(state, payload, currentId, quote);
            })
            .catch((error) => {
                if (error?.name === 'AbortError') {
                    return;


        var latestPayload = null;
        var quoteDebounceId = null;
        var quoteController = null;
        var quoteRequestId = 0;

        var hasQuoteRequirements = (payload) =>
            Boolean(payload.arrival && payload.departure && payload.first_name && payload.last_name && payload.email);

        var scheduleQuote = () => {
            if (quoteDebounceId) {
                window.clearTimeout(quoteDebounceId);
            }

            quoteDebounceId = window.setTimeout(() => {
                quoteDebounceId = null;
                requestQuote();
            }, 350);
        };

        var requestQuote = () => {
            if (quoteDebounceId) {
                window.clearTimeout(quoteDebounceId);
                quoteDebounceId = null;
            }

        let latestPayload = null;
        let quoteDebounceId = null;
        let quoteController = null;
        let quoteRequestId = 0;


        const hasQuoteRequirements = (payload) =>
            Boolean(payload.arrival && payload.departure && payload.first_name && payload.last_name && payload.email);

        const scheduleQuote = () => {
            if (quoteDebounceId) {
                window.clearTimeout(quoteDebounceId);
            }

            quoteDebounceId = window.setTimeout(() => {
                quoteDebounceId = null;
                requestQuote();
            }, 350);
        };


        const hasQuoteRequirements = (payload) =>
            Boolean(payload.arrival && payload.departure && payload.first_name && payload.last_name && payload.email);

        const scheduleQuote = () => {
            if (quoteDebounceId) {
                window.clearTimeout(quoteDebounceId);
            }

            quoteDebounceId = window.setTimeout(() => {
                quoteDebounceId = null;
                requestQuote();
            }, 350);
        };


        const requestQuote = () => {
            if (quoteDebounceId) {
                window.clearTimeout(quoteDebounceId);
                quoteDebounceId = null;
            }

            const payload = collectPayload();

            resetMessage();

            if (!hasQuoteRequirements(payload)) {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                setButtonState(submitButton, false);
                if (message) {
                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quotePrompt ||
                        'Enter your trip details to see an instant quote.';
                }
                if (quoteController) {
                    quoteController.abort();
                    quoteController = null;
                }
                return;
            }

            if (!listingData.api) {



            const payload = collectPayload();

            resetMessage();

            if (!hasQuoteRequirements(payload)) {

                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                setButtonState(submitButton, false);
                if (message) {
                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quotePrompt ||
                        'Enter your trip details to see an instant quote.';
                }
                if (quoteController) {
                    quoteController.abort();
                    quoteController = null;
                }
                return;
            }

            if (!listingData.api) {



                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                setButtonState(submitButton, false);
                if (message) {

                    message.classList.add('error');
                    message.textContent = getGenericError();
                }
                return;
            }

            setButtonState(continueButton, true);
            setButtonState(submitButton, true);

            if (message) {
                message.classList.add('info');
                message.textContent = listingData?.i18n?.quoteLoading || 'Fetching your quote…';
            }

            if (quoteController) {
                quoteController.abort();
            }

            quoteController = new AbortController();
            const currentRequestId = ++quoteRequestId;

            fetch(`${listingData.api}/quote`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: quoteController.signal,
            })
                .then((res) => {
                    if (!res.ok) {
                        throw new Error(getGenericError());
                    }

                    return res.json();
                })
                .then((quote) => {
                    if (currentRequestId !== quoteRequestId) {
                        return;
                    }

                    if (quote.error) {
                        throw new Error(quote.error);
                    }

                    populateQuote(quote);
                    latestPayload = payload;
                    setButtonState(continueButton, false);
                    if (message) {
                        resetMessage();
                        message.classList.add('success');
                        message.textContent = listingData?.i18n?.quoteReady ||
                            'Quote ready! Review the details before continuing to payment.';
                    }
                })
                .catch((error) => {
                    if (error?.name === 'AbortError') {
                        return;
                    }

                    if (currentRequestId !== quoteRequestId) {
                        return;
                    }



                    message.classList.add('error');
                    message.textContent = getGenericError();

                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quotePrompt ||
                        'Enter your trip details to see an instant quote.';
                }
                if (quoteController) {
                    quoteController.abort();
                    quoteController = null;

                }
                return;
            }



            if (!listingData.api) {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);

        const handleQuote = (event) => {
            event.preventDefault();
            resetMessage();
            latestPayload = null;

            setButtonState(continueButton, true);
            setButtonState(submitButton, true);

            if (message) {
                message.classList.add('info');
                message.textContent = listingData?.i18n?.quoteLoading || 'Fetching your quote…';
            }

            if (quoteController) {
                quoteController.abort();
            }

            quoteController = new AbortController();
            const currentRequestId = ++quoteRequestId;


            if (!listingData.api) {

                setButtonState(submitButton, false);
                if (message) {
                    message.classList.add('error');
                    message.textContent = getGenericError();

                }


                if (currentId !== state.quoteRequestId) {
                    return;
                }

                state.latestPayload = null;
                populateQuote(state, null);
                setButtonState(state.continueButton, true);
                setMessage(state, 'error', error?.message || getGenericError(listingData));
            })
            .finally(() => {
                if (currentId === state.quoteRequestId) {
                    state.quoteController = null;
                    setButtonState(state.submitButton, false);
                }
            });
    };

    const scheduleQuote = (state) => {
        if (state.quoteDebounceId) {
            window.clearTimeout(state.quoteDebounceId);
        }

        state.quoteDebounceId = window.setTimeout(() => {
            state.quoteDebounceId = null;
            requestQuote(state);
        }, 350);
    };

    const handleContinue = (state) => {
        const { latestPayload, listingData } = state;

        if (!latestPayload) {
            setMessage(state, 'info', getText(listingData, 'quoteRequired', 'Request a quote before continuing to secure payment.'));
            return;
        }

        setButtonState(state.continueButton, true);
        setButtonState(state.submitButton, true);
        setMessage(state, 'info', getText(listingData, 'checkoutPreparing', 'Preparing secure checkout…'));

        if (!listingData?.api) {
            setButtonState(state.continueButton, false);
            setButtonState(state.submitButton, false);
            setMessage(state, 'error', getGenericError(listingData));
            return;
        }

        fetch(`${listingData.api}/booking`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(latestPayload),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(getGenericError(listingData));


            setButtonState(continueButton, true);
            setButtonState(submitButton, true);

            if (message) {
                message.classList.add('info');
                message.textContent = listingData?.i18n?.quoteLoading || 'Fetching your quote…';
            }

            if (quoteController) {
                quoteController.abort();
            }

            quoteController = new AbortController();
            const currentRequestId = ++quoteRequestId;


            fetch(`${listingData.api}/quote`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),

                signal: quoteController.signal,
            })
                .then((res) => {
                    if (!res.ok) {
                        throw new Error(getGenericError());
                    }

                    return res.json();
                })
                .then((quote) => {
                    if (currentRequestId !== quoteRequestId) {
                        return;
                    }

                    if (quote.error) {
                        throw new Error(quote.error);

                    }

                    populateQuote(quote);
                    latestPayload = payload;
                    setButtonState(continueButton, false);
                    if (message) {
                        resetMessage();
                        message.classList.add('success');
                        message.textContent = listingData?.i18n?.quoteReady ||
                            'Quote ready! Review the details before continuing to payment.';
                    }
                })
                .catch((error) => {
                    if (error?.name === 'AbortError') {
                        return;
                    }

                    }

                    populateQuote(quote);
                    latestPayload = payload;
                    setButtonState(continueButton, false);
                    if (message) {

                        resetMessage();
                        message.classList.add('success');

                        message.classList.add('info');

                        message.textContent = listingData?.i18n?.quoteReady ||
                            'Quote ready! Review the details before continuing to payment.';
                    }
                })
                .catch((error) => {
                    if (error?.name === 'AbortError') {
                        return;
                    }


                    if (currentRequestId !== quoteRequestId) {
                        return;
                    }



                    latestPayload = null;
                    populateQuote(null);
                    setButtonState(continueButton, true);
                    if (message) {

                        resetMessage();


                        resetMessage();


                        resetMessage();




                        message.classList.add('error');
                        message.textContent = error.message || getGenericError();
                    }
                })
                .finally(() => {

                    if (currentRequestId === quoteRequestId) {
                        quoteController = null;
                        setButtonState(submitButton, false);
                    }



                    setButtonState(submitButton, false);


                });
        };

        var handleContinue = () => {
            if (!continueButton) {
                return;


            }

            if (!latestPayload) {
                resetMessage();
                if (message) {
                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quoteRequired ||

                        'We need to finish building your quote before continuing to secure payment.';


                        'We need to finish building your quote before continuing to secure payment.';

                        'Request a quote before continuing to secure payment.';


                }
                return;
            }

            resetMessage();
            setButtonState(continueButton, true);
            setButtonState(submitButton, true);
            if (message) {
                message.classList.add('info');
                message.textContent = listingData?.i18n?.checkoutPreparing || 'Preparing secure checkout…';

            }




            }

            if (!listingData.api) {
                setButtonState(continueButton, false);
                setButtonState(submitButton, false);
                if (message) {

                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quoteRequired ||
                        'We need to finish building your quote before continuing to secure payment.';

                    message.classList.add('error');
                    message.textContent = getGenericError();


                }
                return;
            }

            resetMessage();
            setButtonState(continueButton, true);
            setButtonState(submitButton, true);
            if (message) {
                message.classList.add('info');
                message.textContent = listingData?.i18n?.checkoutPreparing || 'Preparing secure checkout…';


            }

                return response.json();
            })
            .then((result) => {
                if (result?.error) {
                    throw new Error(result.error);
                }

                setMessage(state, 'success', getText(listingData, 'redirecting', 'Redirecting to secure checkout…'));

                if (result?.checkout_url) {
                    window.location.href = result.checkout_url;
                }
            })

            .catch((error) => {
                setButtonState(state.continueButton, false);
                setButtonState(state.submitButton, false);
                setMessage(state, 'error', error?.message || getGenericError(listingData));
            })
            .finally(() => {
                setButtonState(state.submitButton, false);

                if (state.latestPayload) {
                    setButtonState(state.continueButton, false);
                }
            });
    };

    const normalizeSelectors = (listingData) => {
        const overrides = (listingData && listingData.selectors) || {};
        const selectors = {};

        Object.keys(SELECTOR_DEFAULTS).forEach((key) => {
            selectors[key] = overrides[key] || SELECTOR_DEFAULTS[key];
        });

        return selectors;
    };

    const mountWidget = (widget, listingData) => {
        if (!widget || widgetState.has(widget)) {
            return;
        }

        const selectors = normalizeSelectors(listingData);

        const form = widget.querySelector(selectors.form);
        const quotePanel = widget.querySelector(selectors.quote);
        const message = widget.querySelector(selectors.message);
        const submitButton = widget.querySelector(selectors.submit);
        const continueButton = widget.querySelector(selectors.continueButton);
        const availability = widget.querySelector(selectors.availability);
        const availabilityCalendar = widget.querySelector(selectors.availabilityCalendar);
        const rateList = widget.querySelector(selectors.rateList);

        if (!form || !quotePanel || !continueButton || !availability) {
            return;
        }

        const currency = availability.getAttribute('data-currency') || listingData?.currency || 'USD';
        const formatCurrency = formatCurrencyFactory(currency);

        const state = {
            widget,
            listingData,
            selectors,
            form,
            quotePanel,
            message,
            submitButton,
            continueButton,
            availability,
            availabilityCalendar,
            rateList,
            formatCurrency,
            quoteController: null,
            quoteDebounceId: null,
            quoteRequestId: 0,
            latestPayload: null,

                .then((res) => {
                    if (!res.ok) {
                        throw new Error(getGenericError());
                    }

                    return res.json();
                })
                .then((response) => {
                    if (response.error) {
                        throw new Error(response.error);
                    }

                    if (message) {
                        message.classList.add('success');
                        message.textContent = listingData?.i18n?.redirecting ||
                            'Redirecting to secure checkout…';
                    }

                    if (response.checkout_url) {
                        window.location.href = response.checkout_url;
                    }
                })
                .catch((error) => {
                    setButtonState(continueButton, false);
                    setButtonState(submitButton, false);
                    if (message) {
                        message.classList.add('error');
                        message.textContent = error.message || getGenericError();
                    }
                })
                .finally(() => {
                    setButtonState(submitButton, false);
                    if (latestPayload) {
                        setButtonState(continueButton, false);
                    }
                });

        };

        widgetState.set(widget, state);


        requestAvailability(state);
        scheduleQuote(state);

        form.addEventListener('submit', (event) => {
            event.preventDefault();
        });

        const handleChange = () => {
            state.latestPayload = null;
            populateQuote(state, null);
            setButtonState(state.continueButton, true);
            resetMessage(state.message);
            scheduleQuote(state);
        };

        form.addEventListener('input', handleChange);
        form.addEventListener('change', handleChange);

        continueButton.addEventListener('click', () => handleContinue(state));

        if (form) {

            form.addEventListener('submit', (event) => {
                event.preventDefault();
            });
            form.addEventListener('input', () => {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                resetMessage();
                scheduleQuote();
            });
            form.addEventListener('change', () => {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                resetMessage();
                scheduleQuote();
            });
        }


        if (continueButton) {
            continueButton.addEventListener('click', handleContinue);
        }

        scheduleQuote();




            form.addEventListener('submit', (event) => {
                event.preventDefault();
            });
            form.addEventListener('input', () => {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                resetMessage();
                scheduleQuote();
            });
            form.addEventListener('change', () => {
                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                resetMessage();
                scheduleQuote();
            });
        }

        if (continueButton) {
            continueButton.addEventListener('click', handleContinue);
        }

        scheduleQuote();

            form.addEventListener('submit', handleQuote);
            form.addEventListener('input', () => {
                if (!latestPayload) {
                    return;
                }

                latestPayload = null;
                populateQuote(null);
                setButtonState(continueButton, true);
                resetMessage();
            });
        }


        if (continueButton) {
            continueButton.addEventListener('click', handleContinue);
        }


        scheduleQuote();





        widget.dataset.vrspReady = 'true';
    };

    const init = (isRefresh) => {
        const listingData = window.vrspListing;
        const widgets = document.querySelectorAll('[data-vrsp-widget], .vrsp-booking-widget');

        if (!widgets.length || typeof listingData === 'undefined') {
            if (!isRefresh && initAttempts < INIT_RETRY_LIMIT) {
                initAttempts += 1;
                window.setTimeout(() => init(false), INIT_RETRY_DELAY);
            }
            return;
        }

        widgets.forEach((widget) => {
            mountWidget(widget, listingData);
        });
    };


    window.vrspBookingWidget = {
        init,
        refresh() {
            init(true);
        },
    };


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(false), { once: true });
    } else {
        init(false);
    }
})(window, document);
