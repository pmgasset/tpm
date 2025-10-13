(function () {
    function normalizeText(value) {
        return value ? value.replace(/\s+/g, ' ').trim().toLowerCase() : '';
    }

    function findHeadingByText(text) {
        var match = normalizeText(text);
        if (!match) {
            return null;
        }

        var headings = document.querySelectorAll('h2, h3, h4');
        for (var i = 0; i < headings.length; i++) {
            if (normalizeText(headings[i].textContent) === match) {
                return headings[i];
            }
        }

        return null;
    }

    function findModuleRoot(element) {
        if (!element) {
            return null;
        }

        var selectors = [
            '[data-vrsp-card]',
            '.vrsp-card',
            '.vrsp-module',
            '.automation-card',
            '.automation-module',
            '.journey-module',
            '.card',
            'section',
            'article',
            '.postbox'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var root = element.closest(selectors[i]);
            if (root) {
                return root;
            }
        }

        return element.parentElement;
    }

    function moveModules() {
        if (!document.body.classList.contains('post-type-vrsp_booking')) {
            return;
        }

        var moduleNames = [
            'Guest Portal Invitation',
            'Guest Directory',
            'Communications Feed',
            'Guest Portal Promotions',
            'Access Code Delivery',
            'Welcome Touchpoint',
            'Platform Reservation',
            'Reservation details'
        ];

        var grid;
        var gridParent;

        moduleNames.forEach(function (name) {
            var heading = findHeadingByText(name);
            if (!heading) {
                return;
            }

            var root = findModuleRoot(heading);
            if (!root || root.dataset.vrspReservationProcessed === '1') {
                return;
            }

            if (!grid) {
                gridParent = root.parentElement;
                if (!gridParent) {
                    return;
                }

                grid = document.createElement('div');
                grid.className = 'vrsp-reservation-editor__grid';
                grid.setAttribute('data-vrsp-reservation-modules', 'true');
                gridParent.insertBefore(grid, root);
            }

            root.dataset.vrspReservationProcessed = '1';
            root.classList.add('vrsp-reservation-editor__module');
            grid.appendChild(root);
        });

        if (!grid || !grid.children.length) {
            return;
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', moveModules);
    } else {
        moveModules();
    }
})();
