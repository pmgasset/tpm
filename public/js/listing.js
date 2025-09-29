(function () {
    const widget = document.querySelector('.vrsp-booking-widget');
    if (!widget || typeof vrspListing === 'undefined') {
        return;
    }

    const form = widget.querySelector('.vrsp-form');
    const quotePanel = widget.querySelector('.vrsp-quote');
    const message = widget.querySelector('.vrsp-message');
    const availabilityCalendar = widget.querySelector('.vrsp-availability__calendar');
    const rateList = widget.querySelector('.vrsp-availability__rate-list');

    const currency = widget.querySelector('.vrsp-availability').getAttribute('data-currency') || 'USD';
    const formatCurrency = (amount) => {
        const value = Number(amount || 0);
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value);
    };

    const renderBlocked = (blocked) => {
        availabilityCalendar.innerHTML = '';

        if (!Array.isArray(blocked) || blocked.length === 0) {
            const empty = document.createElement('p');
            empty.textContent = window.vrspListing.i18n?.availabilityEmpty || 'Your preferred dates are open!';
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
        widget.querySelector('[data-quote="nights"]').textContent = quote.nights;
        widget.querySelector('[data-quote="subtotal"]').textContent = formatCurrency(quote.subtotal);
        const taxes = Number(quote.taxes || 0) + Number(quote.cleaning_fee || 0) + Number(quote.damage_fee || 0);
        widget.querySelector('[data-quote="taxes"]').textContent = formatCurrency(taxes);
        widget.querySelector('[data-quote="total"]').textContent = formatCurrency(quote.total);
        widget.querySelector('[data-quote="deposit"]').textContent = formatCurrency(quote.deposit);

        const balanceRow = widget.querySelector('[data-quote="balance-row"]');
        if (quote.deposit >= quote.total) {
            balanceRow.style.display = 'none';
            widget.querySelector('[data-quote="note"]').textContent = window.vrspListing.i18n?.fullBalanceNote || 'Your stay begins soon, so the full balance is due today.';
        } else {
            balanceRow.style.display = '';
            widget.querySelector('[data-quote="balance"]').textContent = formatCurrency(quote.balance);
            widget.querySelector('[data-quote="note"]').textContent = window.vrspListing.i18n?.depositNote || 'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.';
        }
    };

    const loadAvailability = () => {
        fetch(`${vrspListing.api}/availability`)
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

    const handleSubmit = (event) => {
        event.preventDefault();
        message.className = 'vrsp-message';
        message.textContent = '';

        const payload = {
            arrival: form.arrival.value,
            departure: form.departure.value,
            guests: form.guests.value,
            coupon: form.coupon.value,
            first_name: form.first_name.value,
            last_name: form.last_name.value,
            email: form.email.value,
            phone: form.phone.value,
        };

        populateQuote(null);

        fetch(`${vrspListing.api}/quote`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then((res) => res.json())
            .then((quote) => {
                if (quote.error) {
                    throw new Error(quote.error);
                }

                populateQuote(quote);

                return fetch(`${vrspListing.api}/booking`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    throw new Error(response.error);
                }

                message.classList.add('success');
                message.textContent = window.vrspListing.i18n?.redirecting || 'Redirecting to secure checkout…';
                if (response.checkout_url) {
                    window.location.href = response.checkout_url;
                }
            })
            .catch((error) => {
                message.classList.add('error');
                message.textContent = error.message || window.vrspListing.i18n?.genericError || 'Unable to process booking. Please try again.';
            });
    };

    loadAvailability();

    if (form) {
        form.addEventListener('submit', handleSubmit);
    }
})();
