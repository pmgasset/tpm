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
    var PRICING_FIELDS = ['stay', 'cleaning', 'discount', 'taxes', 'total', 'deposit', 'balance'];

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
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function dispatchBubbledEvent(target, type) {
        if (!target || !type) {
            return;
        }

        var event;

        if (typeof window.Event === 'function') {
            try {
                event = new window.Event(type, { bubbles: true });
            } catch (error) {
                event = document.createEvent('Event');
                event.initEvent(type, true, false);
            }
        } else {
            event = document.createEvent('Event');
            event.initEvent(type, true, false);
        }

        target.dispatchEvent(event);
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

    function formatSuggestionRange(state, suggestion) {
        if (!suggestion || !suggestion.start || !suggestion.end) {
            return '';
        }

        var listingData = state ? state.listingData : null;
        var template = getText(listingData, 'availabilitySuggestion', 'Next available stay: %1$s to %2$s.');
        var startValue = toCalendarValue(suggestion.start);
        var endValue = toCalendarValue(suggestion.end);

        if (!startValue || !endValue) {
            return '';
        }

        var startLabel = formatDate(startValue);
        var endLabel = formatDate(endValue);

        return template.replace('%1$s', startLabel).replace('%2$s', endLabel);
    }

    function toCalendarValue(value) {
        if (!value) {
            return '';
        }

        if (value instanceof Date) {
            return toISODate(value);
        }

        if (typeof value === 'string') {
            return value.trim();
        }

        return '';
    }

    function setAvailabilityState(state, type, message, suggestionText, suggestionRange) {
        if (!state || !state.availability) {
            return;
        }

        var container = state.availability;
        var classes = ['is-available', 'is-unavailable', 'is-idle', 'is-checking', 'is-error'];

        for (var i = 0; i < classes.length; i += 1) {
            container.classList.remove(classes[i]);
        }

        if (type) {
            container.classList.add('is-' + type);
        }

        if (state.availabilityStatus) {
            state.availabilityStatus.textContent = message || '';
        }

        if (state.availabilitySuggestion) {
            state.availabilitySuggestion.textContent = suggestionText || '';
        }

        if (state.availabilityApply) {
            if (suggestionRange && suggestionRange.start && suggestionRange.end) {
                state.availabilityApply.hidden = false;
                state.availabilityApply.textContent = getText(state.listingData, 'availabilityApply', 'Use these dates');
            } else {
                state.availabilityApply.hidden = true;
            }
        }

        var suggestionStart = suggestionRange ? toCalendarValue(suggestionRange.start) : '';
        var suggestionEnd = suggestionRange ? toCalendarValue(suggestionRange.end) : '';

        state.suggestedRange = suggestionStart && suggestionEnd
            ? {
                  start: suggestionStart,
                  end: suggestionEnd
              }
            : null;

        state.availabilityStatusType = type || '';
    }

    function parseISODate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var normalized = value.trim();
        if (!normalized) {
            return null;
        }

        var separators = ['-', '/', '.'];
        var parts = null;
        var separator = '';

        for (var i = 0; i < separators.length; i += 1) {
            var token = separators[i];
            if (normalized.indexOf(token) !== -1) {
                parts = normalized.split(token);
                separator = token;
                break;
            }
        }

        if (!parts || parts.length < 3) {
            return null;
        }

        var year = 0;
        var month = 0;
        var day = 0;

        if (parts[0].length === 4) {
            year = Number(parts[0]);
            month = Number(parts[1]);
            day = Number(parts[2]);
        } else if (parts[2].length === 4) {
            year = Number(parts[2]);
            month = Number(parts[0]);
            day = Number(parts[1]);
        } else {
            return null;
        }

        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            return null;
        }

        if (separator === '.') {
            // European day.month.year ordering.
            var maybeYearFirst = parts[0].length === 4;
            if (!maybeYearFirst) {
                year = Number(parts[2]);
                month = Number(parts[1]);
                day = Number(parts[0]);

                if (isNaN(year) || isNaN(month) || isNaN(day)) {
                    return null;
                }
            }
        }

        month -= 1;

        var date = new Date(year, month, day);
        if (isNaN(date.getTime())) {
            return null;
        }

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month ||
            date.getDate() !== day
        ) {
            return null;
        }

        return date;
    }

    function formatDate(value) {
        var date = parseISODate(value);
        if (!date) {
            return value ? value : '—';
        }

        try {
            return date.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch (error) {
            return value || '—';
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

    function addDays(date, days) {
        if (!(date instanceof Date)) {
            return null;
        }

        var clone = new Date(date.getTime());
        clone.setDate(clone.getDate() + days);
        return clone;
    }

    function toISODate(date) {
        if (!(date instanceof Date)) {
            return '';
        }

        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');

        return date.getFullYear() + '-' + month + '-' + day;
    }

    function buildBlockedRanges(blocked) {
        if (!Array.isArray(blocked)) {
            return [];
        }

        var ranges = [];

        for (var i = 0; i < blocked.length; i += 1) {
            var windowItem = blocked[i];
            if (!windowItem) {
                continue;
            }

            var start = parseISODate(windowItem.start);
            var end = parseISODate(windowItem.end);

            if (!start || !end || !(start instanceof Date) || !(end instanceof Date)) {
                continue;
            }

            if (start.getTime() >= end.getTime()) {
                continue;
            }

            ranges.push({
                start: start,
                end: end
            });
        }

        ranges.sort(function (a, b) {
            return a.start.getTime() - b.start.getTime();
        });

        return ranges;
    }

    function buildBlockedDateMap(blockedRanges) {
        var map = Object.create(null);

        if (!Array.isArray(blockedRanges) || !blockedRanges.length) {
            return map;
        }

        for (var i = 0; i < blockedRanges.length; i += 1) {
            var range = blockedRanges[i];
            if (!range || !(range.start instanceof Date) || !(range.end instanceof Date)) {
                continue;
            }

            var cursor = new Date(range.start.getTime());

            while (cursor < range.end) {
                var key = toISODate(cursor);
                if (key) {
                    map[key] = true;
                }

                cursor = addDays(cursor, 1);
                if (!(cursor instanceof Date)) {
                    break;
                }
            }
        }

        return map;
    }

    function getCalendarWindow(data) {
        var windowInfo = data && data.window ? data.window : null;
        var start = windowInfo && parseISODate(windowInfo.start);
        var today = new Date();

        if (!(start instanceof Date)) {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else {
            start = new Date(start.getFullYear(), start.getMonth(), 1);
        }

        var months = 3;

        if (windowInfo && typeof windowInfo.months !== 'undefined') {
            var parsedMonths = Number(windowInfo.months);
            if (!isNaN(parsedMonths) && parsedMonths > 0) {
                months = parsedMonths;
            }
        } else if (windowInfo && windowInfo.end) {
            var end = parseISODate(windowInfo.end);
            if (end instanceof Date) {
                end = new Date(end.getFullYear(), end.getMonth(), 1);
                var diffMonths = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1;
                if (diffMonths > 0) {
                    months = diffMonths;
                }
            }
        }

        if (months > 12) {
            months = 12;
        } else if (months < 1) {
            months = 1;
        }

        return {
            start: start,
            months: months
        };
    }

    function getWeekdayNames() {
        var names = [];
        var reference = new Date(2020, 5, 7); // Sunday reference.

        for (var i = 0; i < 7; i += 1) {
            var date = new Date(reference.getTime());
            date.setDate(reference.getDate() + i);

            try {
                names.push(date.toLocaleDateString(undefined, { weekday: 'short' }));
            } catch (error) {
                var fallback = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                return fallback;
            }
        }

        return names;
    }

    function formatMonthLabel(date) {
        if (!(date instanceof Date)) {
            return '';
        }

        try {
            return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        } catch (error) {
            return date.getFullYear() + '-' + (date.getMonth() + 1).toString().padStart(2, '0');
        }
    }

    function createLegendItem(className, label) {
        var item = document.createElement('span');
        item.className = 'vrsp-calendar__legend-item';

        var swatch = document.createElement('span');
        swatch.className = 'vrsp-calendar__legend-swatch ' + className;
        swatch.setAttribute('aria-hidden', 'true');

        var text = document.createElement('span');
        text.className = 'vrsp-calendar__legend-label';
        text.textContent = label;

        item.appendChild(swatch);
        item.appendChild(text);

        return item;
    }

    function createCalendarMonth(state, year, month, blockedMap, today, labels) {
        var container = document.createElement('section');
        container.className = 'vrsp-calendar__month';

        var heading = document.createElement('h3');
        heading.className = 'vrsp-calendar__month-name';
        heading.textContent = formatMonthLabel(new Date(year, month, 1));
        container.appendChild(heading);

        var table = document.createElement('table');
        table.className = 'vrsp-calendar';
        table.setAttribute('role', 'grid');

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        var weekdays = getWeekdayNames();

        for (var i = 0; i < weekdays.length; i += 1) {
            var th = document.createElement('th');
            th.scope = 'col';
            th.textContent = weekdays[i];
            headRow.appendChild(th);
        }

        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var day = 1;

        for (var week = 0; week < 6; week += 1) {
            var row = document.createElement('tr');

            for (var dow = 0; dow < 7; dow += 1) {
                var cell = document.createElement('td');
                cell.className = 'vrsp-calendar__day';

                if ((week === 0 && dow < firstDay) || day > daysInMonth) {
                    cell.classList.add('is-empty');
                    cell.setAttribute('aria-hidden', 'true');
                    row.appendChild(cell);
                    continue;
                }

                var currentDate = new Date(year, month, day);
                var iso = toISODate(currentDate);
                var label = formatDate(iso);
                var isBlocked = !!blockedMap[iso];
                var midnightToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                var isPast = currentDate < midnightToday;
                var isDisabled = isBlocked || isPast;

                var number = document.createElement('span');
                number.className = 'vrsp-calendar__day-number';
                number.textContent = day.toString();
                cell.appendChild(number);

                if (isBlocked) {
                    var mark = document.createElement('span');
                    mark.className = 'vrsp-calendar__day-status';
                    mark.textContent = '×';
                    mark.setAttribute('aria-hidden', 'true');
                    cell.appendChild(mark);
                    cell.classList.add('is-blocked');
                    cell.setAttribute('aria-disabled', 'true');
                    cell.setAttribute('title', labels.unavailable + ' ' + label);
                    cell.setAttribute('aria-label', labels.unavailable + ' ' + label);
                } else if (isPast) {
                    cell.classList.add('is-past');
                    cell.setAttribute('aria-disabled', 'true');
                    cell.setAttribute('title', labels.unavailable + ' ' + label);
                    cell.setAttribute('aria-label', labels.unavailable + ' ' + label);
                } else {
                    cell.classList.add('is-available');
                    cell.setAttribute('role', 'button');
                    cell.tabIndex = 0;
                    cell.setAttribute('title', labels.available + ' ' + label);
                    cell.setAttribute('aria-label', labels.available + ' ' + label);
                }

                cell.dataset.date = iso;
                cell.setAttribute('data-calendar-day', iso);
                cell.setAttribute('data-calendar-disabled', isDisabled ? '1' : '0');
                state.calendarCells[iso] = cell;

                row.appendChild(cell);
                day += 1;
            }

            tbody.appendChild(row);

            if (day > daysInMonth) {
                break;
            }
        }

        table.appendChild(tbody);
        container.appendChild(table);

        return container;
    }

    function renderCalendar(state) {
        if (!state || !state.calendar) {
            return;
        }

        clearChildren(state.calendar);

        state.calendarCells = Object.create(null);

        var data = state.availabilityData || {};
        var blockedMap = buildBlockedDateMap(state.blockedRanges || []);
        state.blockedDateMap = blockedMap;

        var calendarWindow = getCalendarWindow(data);
        var start = calendarWindow.start instanceof Date ? calendarWindow.start : new Date();
        var months = calendarWindow.months || 3;
        var today = new Date();

        var legend = document.createElement('div');
        legend.className = 'vrsp-calendar__legend';

        legend.appendChild(
            createLegendItem(
                'is-available',
                getText(state.listingData, 'availabilityLegendAvailable', 'Available')
            )
        );
        legend.appendChild(
            createLegendItem(
                'is-blocked',
                getText(state.listingData, 'availabilityLegendUnavailable', 'Unavailable')
            )
        );

        state.calendar.appendChild(legend);

        var monthsWrapper = document.createElement('div');
        monthsWrapper.className = 'vrsp-calendar__months';

        var labels = {
            available: getText(state.listingData, 'availabilityDayAvailable', 'Available on'),
            unavailable: getText(state.listingData, 'availabilityDayUnavailable', 'Not available on')
        };

        for (var i = 0; i < months; i += 1) {
            var monthDate = new Date(start.getFullYear(), start.getMonth() + i, 1);
            monthsWrapper.appendChild(
                createCalendarMonth(state, monthDate.getFullYear(), monthDate.getMonth(), blockedMap, today, labels)
            );
        }

        state.calendar.appendChild(monthsWrapper);

        if (state.form) {
            updateCalendarSelection(state, readForm(state.form));
        }
    }

    function getCalendarCell(state, node) {
        if (!state || !node) {
            return null;
        }

        var root = state.calendar;
        if (!root) {
            return null;
        }

        var current = node;

        while (current && current !== root) {
            if (current.classList && current.classList.contains('vrsp-calendar__day')) {
                return current;
            }
            current = current.parentNode;
        }

        if (current && current.classList && current.classList.contains('vrsp-calendar__day')) {
            return current;
        }

        return null;
    }

    function isCalendarCellDisabled(cell) {
        if (!cell) {
            return true;
        }

        if (cell.getAttribute('data-calendar-disabled') === '1') {
            return true;
        }

        if (cell.classList && (cell.classList.contains('is-blocked') || cell.classList.contains('is-past'))) {
            return true;
        }

        return false;
    }

    function isCalendarRangeSelectable(state, arrivalDate, departureDate) {
        if (!state) {
            return false;
        }

        if (!(arrivalDate instanceof Date) || !(departureDate instanceof Date)) {
            return false;
        }

        if (departureDate.getTime() <= arrivalDate.getTime()) {
            return false;
        }

        var map = state.blockedDateMap || {};
        var cursor = new Date(arrivalDate.getTime());

        while (cursor < departureDate) {
            var key = toISODate(cursor);
            if (map && key && map[key]) {
                return false;
            }

            cursor = addDays(cursor, 1);
            if (!(cursor instanceof Date)) {
                return false;
            }
        }

        var departureKey = toISODate(departureDate);
        if (map && departureKey && map[departureKey]) {
            return false;
        }

        return true;
    }

    function commitCalendarSelection(state, arrivalISO, departureISO) {
        if (!state || !state.form) {
            return;
        }

        var arrivalValue = toCalendarValue(arrivalISO);
        var departureValue = toCalendarValue(departureISO);

        if (state.form.arrival) {
            state.form.arrival.value = arrivalValue;
        }

        if (state.form.departure) {
            state.form.departure.value = departureValue;
        }

        updateCalendarSelection(state, {
            arrival: arrivalValue,
            departure: departureValue
        });

        dispatchBubbledEvent(state.form, 'input');
        dispatchBubbledEvent(state.form, 'change');
    }

    function selectCalendarDate(state, isoDate) {
        if (!state || !isoDate) {
            return;
        }

        var cell = state.calendarCells ? state.calendarCells[isoDate] : null;
        if (!cell || isCalendarCellDisabled(cell)) {
            return;
        }

        var selectedDate = parseISODate(isoDate);
        if (!(selectedDate instanceof Date)) {
            return;
        }

        var payload = readForm(state.form);
        var arrival = parseISODate(payload.arrival);
        var departure = parseISODate(payload.departure);

        if (!arrival || (arrival && departure)) {
            commitCalendarSelection(state, isoDate, '');
            return;
        }

        if (selectedDate.getTime() <= arrival.getTime()) {
            commitCalendarSelection(state, isoDate, '');
            return;
        }

        if (!isCalendarRangeSelectable(state, arrival, selectedDate)) {
            commitCalendarSelection(state, isoDate, '');
            return;
        }

        commitCalendarSelection(state, toISODate(arrival), isoDate);
    }

    function handleCalendarInteraction(state, event) {
        if (!state || !event) {
            return;
        }

        if (event.type === 'click' && typeof event.button !== 'undefined' && event.button !== 0) {
            return;
        }

        var cell = getCalendarCell(state, event.target);
        if (!cell || isCalendarCellDisabled(cell)) {
            return;
        }

        var iso = cell.getAttribute('data-calendar-day') || cell.dataset.date;
        if (!iso) {
            return;
        }

        if (event.type === 'keydown') {
            var key = event.key || event.keyCode;
            if (key === 'Enter' || key === ' ' || key === 13 || key === 32) {
                event.preventDefault();
                selectCalendarDate(state, iso);
            }
            return;
        }

        selectCalendarDate(state, iso);
    }

    function updateCalendarSelection(state, payload) {
        if (!state || !state.calendarCells) {
            return;
        }

        var keys = Object.keys(state.calendarCells);
        for (var i = 0; i < keys.length; i += 1) {
            var key = keys[i];
            var cell = state.calendarCells[key];
            if (!cell) {
                continue;
            }
            cell.classList.remove('is-selected', 'is-selected-start', 'is-selected-end');
        }

        if (!payload) {
            return;
        }

        var arrival = parseISODate(payload.arrival);
        var departure = parseISODate(payload.departure);

        if (!(arrival instanceof Date)) {
            return;
        }

        var arrivalKey = toISODate(arrival);
        var arrivalCell = state.calendarCells[arrivalKey];

        if (arrivalCell) {
            arrivalCell.classList.add('is-selected', 'is-selected-start');
        }

        if (!(departure instanceof Date) || departure.getTime() < arrival.getTime()) {
            return;
        }

        var cursor = new Date(arrival.getTime());

        while (cursor.getTime() <= departure.getTime()) {
            var key = toISODate(cursor);
            var cell = state.calendarCells[key];

            if (cell) {
                cell.classList.add('is-selected');

                if (key === arrivalKey) {
                    cell.classList.add('is-selected-start');
                }

                if (cursor.getTime() === departure.getTime()) {
                    cell.classList.add('is-selected-end');
                }
            }

            cursor = addDays(cursor, 1);
            if (!(cursor instanceof Date)) {
                break;
            }
        }
    }

    function findNextAvailableRange(blockedRanges, arrival, departure) {
        if (!(arrival instanceof Date) || !(departure instanceof Date)) {
            return null;
        }

        var nights = differenceInDays(arrival, departure);
        if (nights === null || nights <= 0) {
            return null;
        }

        var blocked = Array.isArray(blockedRanges) ? blockedRanges : [];
        if (!blocked.length) {
            return null;
        }
        var maxIterations = 365;
        var candidateStart = new Date(arrival.getTime());

        for (var i = 0; i < maxIterations; i += 1) {
            var candidateEnd = addDays(candidateStart, nights);
            if (!(candidateEnd instanceof Date)) {
                break;
            }

            var conflict = false;
            var skipTo = null;

            for (var j = 0; j < blocked.length; j += 1) {
                var range = blocked[j];
                if (!range || !(range.start instanceof Date) || !(range.end instanceof Date)) {
                    continue;
                }

                if (candidateEnd <= range.start || candidateStart >= range.end) {
                    continue;
                }

                conflict = true;

                if (!skipTo || range.end > skipTo) {
                    skipTo = new Date(range.end.getTime());
                }
            }

            if (!conflict) {
                return {
                    start: candidateStart,
                    end: candidateEnd
                };
            }

            if (!skipTo) {
                skipTo = addDays(candidateStart, 1);
            }

            if (!(skipTo instanceof Date)) {
                break;
            }

            candidateStart = skipTo;
        }

        return null;
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
            if (state) {
                updateCalendarSelection(state, payload);
            }
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

        updateCalendarSelection(state, payload);
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

            if (targets.discount && targets.discount.parentNode && targets.discount.parentNode.style) {
                targets.discount.parentNode.style.display = 'none';
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

        var discount = Number(quote.discount || 0);
        if (!isFinite(discount)) {
            discount = 0;
        }
        discount = roundCurrency(discount);

        var preDiscount = Number(quote.pre_discount_subtotal || quote.preDiscountSubtotal || 0);
        if (!isFinite(preDiscount) || preDiscount === 0) {
            preDiscount = Number(quote.subtotal || 0);
            if (!isFinite(preDiscount)) {
                preDiscount = 0;
            }

            preDiscount += discount;
        }

        var cleaning = Number(quote.cleaning_fee || quote.cleaning || 0);
        if (!isFinite(cleaning)) {
            cleaning = 0;
        }

        var damage = Number(quote.damage_fee || 0);
        if (!isFinite(damage)) {
            damage = 0;
        }

        cleaning = roundCurrency(cleaning + damage);

        var stay = roundCurrency(preDiscount - cleaning);
        if (stay < 0) {
            stay = 0;
        }

        var taxes = Number(quote.taxes || 0);
        if (!isFinite(taxes)) {
            taxes = 0;
        }
        taxes = roundCurrency(taxes);

        var total = Number(quote.total || preDiscount - discount + taxes);
        if (!isFinite(total)) {
            total = preDiscount - discount + taxes;
        }
        total = roundCurrency(total);

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
            discount: discount,
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

            if (targets.discount) {
                var discountRow = targets.discount.parentNode;
                if (breakdown.discount > 0) {
                    if (discountRow && discountRow.style) {
                        discountRow.style.display = '';
                    }
                    targets.discount.textContent = '-' + state.formatCurrency(breakdown.discount);
                } else {
                    targets.discount.textContent = '—';
                    if (discountRow && discountRow.style) {
                        discountRow.style.display = 'none';
                    }
                }
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
        if (!state) {
            return;
        }

        var data = payload || {};
        state.availabilityData = data;
        state.blockedRanges = buildBlockedRanges(data.blocked || []);
        renderCalendar(state);

        if (!state.availabilityStatusType) {
            setAvailabilityState(
                state,
                'idle',
                getText(state.listingData, 'availabilityPrompt', 'Start by selecting your check-in and checkout dates.'),
                '',
                null
            );
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
            setAvailabilityState(
                state,
                'idle',
                getText(listingData, 'availabilityPrompt', 'Start by selecting your check-in and checkout dates.'),
                '',
                null
            );
            return;
        }

        setButtonDisabled(state.continueButton, true);
        writeMessage(state, 'info', getText(listingData, 'quoteLoading', 'Calculating pricing…'));
        setAvailabilityState(
            state,
            'checking',
            getText(listingData, 'availabilityChecking', 'Checking availability…'),
            '',
            null
        );

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
                return response
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        if (!response.ok) {
                            var message = (data && data.error)
                                ? data.error
                                : getText(listingData, 'genericError', 'Unable to process booking. Please try again.');
                            var error = new Error(message);
                            error.status = response.status;
                            error.data = data;
                            throw error;
                        }

                        return data;
                    });
            })
            .then(function (quote) {
                state.latestPayload = Object.assign({}, payload);
                state.latestQuote = quote;

                writePricing(state, payload, quote);
                setAvailabilityState(
                    state,
                    'available',
                    getText(listingData, 'availabilityAvailable', 'Great news! Your dates are available.'),
                    '',
                    null
                );

                if (quote && quote.coupon_error) {
                    setButtonDisabled(state.continueButton, true);
                    writeMessage(state, 'error', quote.coupon_error);
                    return;
                }

                var hasCheckout = hasCheckoutFields(payload);
                setButtonDisabled(state.continueButton, !hasCheckout);

                var baseType = hasCheckout ? 'success' : 'info';
                var baseText = hasCheckout
                    ? getText(listingData, 'quoteReady', 'Pricing updated! Review and continue to secure payment.')
                    : getText(listingData, 'checkoutDetails', 'Add guest contact details to continue to secure payment.');

                var couponMessage = '';
                if (quote && quote.coupon && quote.coupon.code) {
                    var template = getText(listingData, 'couponApplied', 'Coupon %s applied!');
                    couponMessage = template.replace('%s', quote.coupon.code);
                }

                if (couponMessage) {
                    baseType = 'success';
                    baseText = (couponMessage + ' ' + baseText).trim();
                }

                writeMessage(state, baseType, baseText);
            })
            .catch(function (error) {
                if (controller && error && error.name === 'AbortError') {
                    return;
                }

                state.latestPayload = Object.assign({}, payload);
                state.latestQuote = null;
                resetPricing(state);

                var fallback = getText(listingData, 'genericError', 'Unable to process booking. Please try again.');

                if (error && error.status === 409) {
                    var unavailableMessage = error.message || getText(
                        listingData,
                        'availabilityUnavailable',
                        'Those dates are unavailable. Please choose another stay.'
                    );

                    var arrivalDate = parseISODate(payload.arrival);
                    var departureDate = parseISODate(payload.departure);
                    var nextRange = findNextAvailableRange(state.blockedRanges, arrivalDate, departureDate);
                    var suggestionRange = null;
                    var suggestionText = '';

                    if (nextRange && nextRange.start && nextRange.end) {
                        suggestionRange = {
                            start: toISODate(nextRange.start),
                            end: toISODate(nextRange.end)
                        };
                        suggestionText = formatSuggestionRange(state, suggestionRange);
                    } else {
                        suggestionText = getText(
                            listingData,
                            'availabilityNoSuggestion',
                            "We'll follow up shortly with the next available dates."
                        );
                    }

                    setAvailabilityState(state, 'unavailable', unavailableMessage, suggestionText, suggestionRange);

                    var combinedMessage = suggestionText ? unavailableMessage + ' ' + suggestionText : unavailableMessage;
                    writeMessage(state, 'error', combinedMessage.trim());
                    setButtonDisabled(state.continueButton, true);
                    return;
                }

                setAvailabilityState(state, 'error', (error && error.message) || fallback, '', null);
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
        var availabilityStatus = availability ? availability.querySelector('[data-availability="status"]') : null;
        var availabilitySuggestion = availability ? availability.querySelector('[data-availability="suggestion"]') : null;
        var availabilityApply = availability ? availability.querySelector('[data-availability="apply"]') : null;
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
            availabilityStatus: availabilityStatus,
            availabilitySuggestion: availabilitySuggestion,
            availabilityApply: availabilityApply,
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
            quoteAbort: null,
            availabilityStatusType: '',
            blockedRanges: [],
            availabilityData: null,
            suggestedRange: null,
            calendarCells: Object.create(null),
            blockedDateMap: Object.create(null)
        };

        if (availabilityApply) {
            availabilityApply.addEventListener('click', function () {
                if (!state.suggestedRange) {
                    return;
                }

                commitCalendarSelection(state, state.suggestedRange.start, state.suggestedRange.end);
            });
        }

        if (calendar) {
            calendar.addEventListener('click', function (event) {
                handleCalendarInteraction(state, event);
            });

            calendar.addEventListener('keydown', function (event) {
                if (event && (event.key === 'Enter' || event.key === ' ' || event.keyCode === 13 || event.keyCode === 32)) {
                    handleCalendarInteraction(state, event);
                }
            });
        }

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
                setAvailabilityState(
                    state,
                    'idle',
                    getText(state.listingData, 'availabilityPrompt', 'Start by selecting your check-in and checkout dates.'),
                    '',
                    null
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
            setAvailabilityState(
                state,
                'checking',
                getText(state.listingData, 'availabilityChecking', 'Checking availability…'),
                '',
                null
            );
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
        setAvailabilityState(
            state,
            'idle',
            getText(state.listingData, 'availabilityPrompt', 'Start by selecting your check-in and checkout dates.'),
            '',
            null
        );
        renderCalendar(state);
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

        renderCalendar(state);
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
        version: '1.3.1'
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
