/**
 * CPMMIS Responsive Shell Helpers
 * Additive only: it does not replace any page-level functions.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        // Mark shell pages so the shared CSS can reserve room for the menu button.
        if (document.getElementById('sidebar-toggle')) {
            document.body.classList.add('cp-has-sidebar');
        }

        /* ------------------------------------------------------------------
           Wide tables become labelled cards on phones.
           Labels are copied from the table header, so no markup changes.
           ------------------------------------------------------------------ */
        function getHeaders(table) {
            return Array.prototype.map.call(
                table.querySelectorAll('thead th'),
                function (th) { return (th.textContent || '').trim(); }
            );
        }

        function labelRows(table, headers) {
            Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
                Array.prototype.forEach.call(tr.children, function (cell, index) {
                    if (!cell || cell.tagName !== 'TD') { return; }
                    if (cell.hasAttribute('colspan')) {
                        cell.removeAttribute('data-label');
                        return;
                    }
                    var label = headers[index];
                    if (label) {
                        cell.setAttribute('data-label', label);
                    } else {
                        cell.removeAttribute('data-label');
                    }
                });
            });
        }

        function prepareTable(table) {
            if (table.dataset.cpResponsiveReady === '1') { return; }
            table.dataset.cpResponsiveReady = '1';

            var headers = getHeaders(table);
            // Only wide data tables benefit from the card treatment.
            if (headers.length < 3) { return; }

            table.classList.add('cp-card-table');
            labelRows(table, headers);

            var tbody = table.querySelector('tbody');
            if (tbody && window.MutationObserver) {
                var observer = new MutationObserver(function () {
                    labelRows(table, headers);
                });
                observer.observe(tbody, { childList: true, subtree: true });
            }
        }

        function prepareTables(root) {
            var scope = root || document;
            Array.prototype.forEach.call(scope.querySelectorAll('table'), prepareTable);
        }

        prepareTables(document);

        // Some pages add tables after an AJAX request.
        if (window.MutationObserver) {
            var pageObserver = new MutationObserver(function (mutations) {
                var hasNewTable = mutations.some(function (mutation) {
                    return Array.prototype.some.call(mutation.addedNodes, function (node) {
                        return node.nodeType === 1 &&
                            (node.tagName === 'TABLE' || (node.querySelector && node.querySelector('table')));
                    });
                });
                if (hasNewTable) { prepareTables(document); }
            });
            pageObserver.observe(document.body, { childList: true, subtree: true });
        }

        /* ------------------------------------------------------------------
           Visible-scroll indicator for horizontally scrollable tables.
           ------------------------------------------------------------------ */
        function markScrollers() {
            Array.prototype.forEach.call(document.querySelectorAll('.overflow-x-auto'), function (el) {
                if (el.scrollWidth > el.clientWidth + 4) {
                    el.classList.add('cp-scrollable');
                } else {
                    el.classList.remove('cp-scrollable');
                }
            });
        }

        markScrollers();
        window.addEventListener('load', markScrollers);
        window.addEventListener('resize', markScrollers);
        window.setTimeout(markScrollers, 400);

        /* ------------------------------------------------------------------
           Lock background scrolling while any modal/backdrop is visible.
           ------------------------------------------------------------------ */
        function syncModalLock() {
            var overlays = document.querySelectorAll('.fixed.inset-0, .sidebar-backdrop');
            var anyVisible = false;

            Array.prototype.forEach.call(overlays, function (el) {
                if (el.classList.contains('hidden')) { return; }
                var styles = window.getComputedStyle(el);
                if (styles.display !== 'none' && styles.visibility !== 'hidden') {
                    anyVisible = true;
                }
            });

            document.body.classList.toggle('cp-modal-open', anyVisible);
        }

        if (window.MutationObserver) {
            var overlayObserver = new MutationObserver(syncModalLock);
            Array.prototype.forEach.call(document.querySelectorAll('.fixed.inset-0, .sidebar-backdrop'), function (el) {
                overlayObserver.observe(el, {
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });
            });
        }
        syncModalLock();

        /* ------------------------------------------------------------------
           Keyboard support: Escape closes the drawer/popovers.
           ------------------------------------------------------------------ */
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }

            var openSidebar = document.querySelector('aside.sidebar.open');
            if (openSidebar && typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
            }

            if (typeof window.closeSettingsDrawer === 'function') {
                var drawer = document.getElementById('settings-drawer');
                if (drawer && drawer.classList.contains('open')) {
                    window.closeSettingsDrawer();
                }
            }

            ['notif-popover', 'settings-popover', 'notif-dropdown'].forEach(function (id) {
                var popover = document.getElementById(id);
                if (popover) { popover.classList.add('hidden'); }
            });
        });

        // Keep the scroll indicator correct after table content is replaced.
        window.setTimeout(markScrollers, 1200);
    });
})();
