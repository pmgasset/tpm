(function () {
    const INIT_RETRY_LIMIT = 20;
    const INIT_RETRY_DELAY = 150;
    let initAttempts = 0;

    const setupWidget = (widget, listingData) => {
        if (!widget || widget.dataset.vrspReady === 'true') {
            return;
        }

        var selectors = {
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

        if (!form || !quotePanel || !continueButton || !availability) {
            return;
        }

        var currency = availability.getAttribute('data-currency') || listingData.currency || 'USD';

        var formatCurrency = (amount) => {
            const value = Number(amount || 0);
            return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
        };

        var renderBlocked = (blocked) => {
            if (!availabilityCalendarEl) {
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
            if (!rateListEl) {
                return;
            }

            rateListEl.innerHTML = '';
            if (!Array.isArray(rates) || rates.length === 0) {
                return;
            }

            rates.slice(0, 6).forEach((rate) => {
                const pill = document.createElement('span');
                pill.className = 'rate-pill';
                pill.textContent = `${rate.date}: ${formatCurrency(rate.amount)}`;
                rateListEl.appendChild(pill);
            });
        };

        var populateQuote = (quote) => {
            if (!quote) {
                quotePanel.hidden = true;
                return;
            }

            quotePanel.hidden = false;
            if (!quotePanel.hasAttribute('tabindex')) {
                quotePanel.setAttribute('tabindex', '-1');
            }
            widget.querySelector('[data-quote="nights"]').textContent = quote.nights;
            widget.querySelector('[data-quote="subtotal"]').textContent = formatCurrency(quote.subtotal);
            const taxes = Number(quote.taxes || 0) + Number(quote.cleaning_fee || 0) + Number(quote.damage_fee || 0);
            widget.querySelector('[data-quote="taxes"]').textContent = formatCurrency(taxes);
            widget.querySelector('[data-quote="total"]').textContent = formatCurrency(quote.total);
            widget.querySelector('[data-quote="deposit"]').textContent = formatCurrency(quote.deposit);

            const balanceRow = widget.querySelector('[data-quote="balance-row"]');
            if (balanceRow) {
                if (quote.deposit >= quote.total) {
                    balanceRow.style.display = 'none';
                    widget.querySelector('[data-quote="note"]').textContent = window.vrspListing?.i18n?.fullBalanceNote ||
                        'Your stay begins soon, so the full balance is due today.';
                } else {
                    balanceRow.style.display = '';
                    widget.querySelector('[data-quote="balance"]').textContent = formatCurrency(quote.balance);
                    widget.querySelector('[data-quote="note"]').textContent = window.vrspListing?.i18n?.depositNote ||
                        'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.';
                }
            }
        };

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
            if (!message) {
                return;
            }

            message.className = 'vrsp-message';
            message.textContent = '';
        };

        var setButtonState = (button, disabled) => {
            if (!button) {
                return;
            }

            button.disabled = !!disabled;
            if (disabled) {
                button.setAttribute('aria-disabled', 'true');
            } else {
                button.removeAttribute('aria-disabled');
            }
        };

        var getGenericError = () =>
            listingData?.i18n?.genericError || 'Unable to process booking. Please try again.';

        var loadAvailability = () => {
            if (!listingData.api) {
                return;
            }

            fetch(`${listingData.api}/availability`)
                .then((res) => res.json())
                .then((data) => {
                    if (!data || typeof data !== 'object') {
                        renderBlocked([]);
                        renderRates([]);
                        return;
                    }

                    renderBlocked(data.blocked || []);
                    renderRates(data.rates || []);
                })
                .catch(() => {
                    // Keep silent if availability fails.
                });
        };

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

                    latestPayload = null;
                    populateQuote(null);
                    setButtonState(continueButton, true);
                    if (message) {
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

            if (!listingData.api) {
                setButtonState(continueButton, false);
                setButtonState(submitButton, false);
                if (message) {
                    message.classList.add('error');
                    message.textContent = getGenericError();
                }
                return;
            }

            fetch(`${listingData.api}/booking`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(latestPayload),
            })
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

        loadAvailability();

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

        widget.dataset.vrspReady = 'true';
    };

    const init = () => {
        const listingData = window.vrspListing;
        const widgets = document.querySelectorAll('.vrsp-booking-widget');

        if (!widgets.length || typeof listingData === 'undefined') {
            if (initAttempts < INIT_RETRY_LIMIT) {
                initAttempts += 1;
                window.setTimeout(init, INIT_RETRY_DELAY);
            }
            return;
        }

        widgets.forEach((widget) => {
            setupWidget(widget, listingData);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
