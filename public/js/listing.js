(function () {
    const INIT_RETRY_LIMIT = 20;
    const INIT_RETRY_DELAY = 150;
    let initAttempts = 0;

    const setupWidget = (widget, listingData) => {
        if (!widget || widget.dataset.vrspReady === 'true') {
            return;
        }

        const form = widget.querySelector('.vrsp-form');
        const quotePanel = widget.querySelector('.vrsp-quote');
        const message = widget.querySelector('.vrsp-message');
        const submitButton = widget.querySelector('.vrsp-form__submit');
        const continueButton = widget.querySelector('.vrsp-form__continue');
        const availability = widget.querySelector('.vrsp-availability');
        const availabilityCalendar = widget.querySelector('.vrsp-availability__calendar');
        const rateList = widget.querySelector('.vrsp-availability__rate-list');

        if (!form || !quotePanel || !submitButton || !continueButton || !availability) {
            return;
        }

        const currency = availability.getAttribute('data-currency') || listingData.currency || 'USD';

        const formatCurrency = (amount) => {
            const value = Number(amount || 0);
            return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
        };

        const renderBlocked = (blocked) => {
            if (!availabilityCalendar) {
                return;
            }

            availabilityCalendar.innerHTML = '';

            if (!Array.isArray(blocked) || blocked.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = window.vrspListing?.i18n?.availabilityEmpty || 'Your preferred dates are open!';
                availabilityCalendar.appendChild(empty);
                return;
            }

            blocked.slice(0, 8).forEach((event) => {
                const tag = document.createElement('span');
                tag.className = 'vrsp-availability__tag';
                tag.textContent = `${event.start} → ${event.end}`;
                availabilityCalendar.appendChild(tag);
            });
        };

        const renderRates = (rates) => {
            if (!rateList) {
                return;
            }

            rateList.innerHTML = '';
            if (!Array.isArray(rates) || rates.length === 0) {
                return;
            }

            rates.slice(0, 6).forEach((rate) => {
                const pill = document.createElement('span');
                pill.className = 'rate-pill';
                pill.textContent = `${rate.date}: ${formatCurrency(rate.amount)}`;
                rateList.appendChild(pill);
            });
        };

        const populateQuote = (quote) => {
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

        const collectPayload = () => ({
            arrival: form.arrival.value,
            departure: form.departure.value,
            guests: form.guests.value,
            coupon: form.coupon.value,
            first_name: form.first_name.value,
            last_name: form.last_name.value,
            email: form.email.value,
            phone: form.phone.value,
        });

        const payloadsMatch = (a, b) =>
            ['arrival', 'departure', 'guests', 'coupon', 'first_name', 'last_name', 'email', 'phone'].every(
                (key) => String(a[key] ?? '') === String(b[key] ?? '')
            );

        const resetMessage = () => {
            if (!message) {
                return;
            }

            message.className = 'vrsp-message';
            message.textContent = '';
        };

        const setButtonState = (button, disabled) => {
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

        const getGenericError = () =>
            listingData?.i18n?.genericError || 'Unable to process booking. Please try again.';

        const loadAvailability = () => {
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

        let latestPayload = null;

        const handleQuote = (event) => {
            event.preventDefault();
            resetMessage();
            latestPayload = null;
            setButtonState(continueButton, true);

            const payload = collectPayload();

            populateQuote(null);

            setButtonState(submitButton, true);

            if (!listingData.api) {
                setButtonState(submitButton, false);
                if (message) {
                    message.classList.add('error');
                    message.textContent = getGenericError();
                }
                return;
            }

            fetch(`${listingData.api}/quote`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then((res) => {
                    if (!res.ok) {
                        throw new Error(getGenericError());
                    }

                    return res.json();
                })
                .then((quote) => {
                    if (quote.error) {
                        throw new Error(quote.error);
                    }

                    const currentValues = collectPayload();
                    if (!payloadsMatch(payload, currentValues)) {
                        // Form changed while fetching a quote; ignore this response.
                        return;
                    }

                    populateQuote(quote);
                    latestPayload = payload;
                    setButtonState(continueButton, false);
                    if (!quotePanel.hidden) {
                        quotePanel.focus({ preventScroll: true });
                    }

                    if (message) {
                        message.classList.add('info');
                        message.textContent = listingData?.i18n?.quoteReady ||
                            'Quote ready! Review the details before continuing to payment.';
                    }
                })
                .catch((error) => {
                    latestPayload = null;
                    populateQuote(null);
                    setButtonState(continueButton, true);
                    if (message) {
                        message.classList.add('error');
                        message.textContent = error.message || getGenericError();
                    }
                })
                .finally(() => {
                    setButtonState(submitButton, false);
                });
        };

        const handleContinue = () => {
            if (!continueButton) {
                return;
            }

            if (!latestPayload) {
                resetMessage();
                if (message) {
                    message.classList.add('info');
                    message.textContent = listingData?.i18n?.quoteRequired ||
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
