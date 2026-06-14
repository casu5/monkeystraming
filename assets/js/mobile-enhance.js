(function () {
    function enhanceTables() {
        var tables = document.querySelectorAll('table:not([data-mobile-enhanced])');

        tables.forEach(function (table) {
            var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
                return (th.textContent || '').trim().replace(/\s+/g, ' ');
            });

            if (!headers.length) {
                var firstRow = table.querySelector('tr');
                headers = firstRow ? Array.prototype.map.call(firstRow.children, function (cell) {
                    return (cell.textContent || '').trim().replace(/\s+/g, ' ');
                }) : [];
            }

            if (!headers.length) return;

            table.dataset.mobileEnhanced = '1';
            table.classList.add('mobile-card-table');

            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.prototype.forEach.call(row.children, function (cell, index) {
                    if (!cell.hasAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index] || '');
                    }
                });
            });
        });
    }

    function markScrollableBlocks() {
        document.querySelectorAll('.card, .content-card, .card-body, .table-wrap, .admin-table-wrap, .table-responsive, .profile-card').forEach(function (block) {
            if (block.querySelector('table')) block.classList.add('has-mobile-table');
        });
    }

    function markMobileSections() {
        document.querySelectorAll('form').forEach(function (form) {
            form.classList.add('mobile-form-ready');
        });

        document.querySelectorAll('.footer a, footer a, .card-link, .btn, button, input[type="submit"], input[type="button"], [role="button"]').forEach(function (el) {
            if (el.classList && el.classList.contains('mobile-nav-backdrop')) return;
            el.classList.add('mobile-touch-ready');
        });

        document.querySelectorAll('.action-buttons, .actions, .actions-row, .rowBtns, .pager, .pagination, .header-buttons, .form-actions, .card-actions').forEach(function (group) {
            group.classList.add('mobile-actions-ready');
        });

        document.querySelectorAll('form[style*="inline"], form[style*="flex"], form[style*="grid"], .actions form, .mobile-actions-ready form').forEach(function (form) {
            form.classList.add('mobile-inline-form-ready');
        });

        document.querySelectorAll('.alert, .msgBox, .empty-state, .whatsapp-info, details').forEach(function (block) {
            block.classList.add('mobile-readable-ready');
        });
    }

    function syncDynamicChanges() {
        var observer = new MutationObserver(function (mutations) {
            var shouldRun = mutations.some(function (mutation) {
                return Array.prototype.some.call(mutation.addedNodes, function (node) {
                    return node.nodeType === 1;
                });
            });

            if (shouldRun) {
                enhanceTables();
                markScrollableBlocks();
                markMobileSections();
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function run() {
        enhanceTables();
        markScrollableBlocks();
        markMobileSections();
        syncDynamicChanges();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
