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
        document.querySelectorAll('.card, .content-card, .card-body, .table-wrap, .admin-table-wrap').forEach(function (block) {
            if (block.querySelector('table')) block.classList.add('has-mobile-table');
        });
    }

    function run() {
        enhanceTables();
        markScrollableBlocks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
