(function(){
const root = document.querySelector('.vrsp-listing');
if (!root || typeof vrspListing === 'undefined') {
return;
}

const form = root.querySelector('.vrsp-form');
const quoteBox = root.querySelector('.vrsp-quote');
const message = root.querySelector('.vrsp-message');
const availability = root.querySelector('.vrsp-availability');

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: vrspListing.currency }).format(amount || 0);

const loadAvailability = () => {
fetch(vrspListing.api + '/availability')
.then((res) => res.json())
.then((events) => {
if (!Array.isArray(events)) {
return;
}
availability.innerHTML = '';
events.forEach((event) => {
const span = document.createElement('span');
span.className = 'booked';
span.textContent = `${event.arrival} → ${event.departure}`;
availability.appendChild(span);
});
})
.catch(() => {});
};

loadAvailability();

const submit = (e) => {
e.preventDefault();
message.textContent = '';
message.className = 'vrsp-message';

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

fetch(vrspListing.api + '/quote', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(payload),
})
.then((res) => res.json())
.then((quote) => {
if (quote.error) {
throw new Error(quote.error);
}
quoteBox.textContent = `${formatCurrency(quote.total)} — ${quote.nights} nights`;

return fetch(vrspListing.api + '/booking', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(payload),
});
})
.then((res) => res.json())
.then((resp) => {
if (resp.error) {
throw new Error(resp.error);
}
message.classList.add('success');
message.textContent = 'Redirecting to secure checkout…';
if (resp.checkout_url) {
window.location.href = resp.checkout_url;
}
})
.catch((err) => {
message.classList.add('error');
message.textContent = err.message || 'Unable to process booking. Please try again.';
});
};

if (form) {
form.addEventListener('submit', submit);
}
})();
