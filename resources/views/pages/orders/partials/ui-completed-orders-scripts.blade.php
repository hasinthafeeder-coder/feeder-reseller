<script>
(function () {
    'use strict';

    const page = document.getElementById('resellerCompletedOrdersPage');
    if (!page) {
        return;
    }

    const tableBody = document.getElementById('completedOrdersTableBody');
    const resultsMeta = document.getElementById('completedOrdersResultsMeta');
    const paginationHost = document.getElementById('completedOrdersPaginationHost');
    const filterForm = document.getElementById('completedOrdersFilterForm');
    const searchInput = document.getElementById('completedFilterSearch');
    const outcomeSelect = document.getElementById('completedFilterOutcome');
    const settlementSelect = document.getElementById('completedFilterSettlement');
    const productSelect = document.getElementById('completedFilterProduct');
    const courierSelect = document.getElementById('completedFilterCourier');
    const dateFromInput = document.getElementById('completedFilterDateFrom');
    const dateToInput = document.getElementById('completedFilterDateTo');
    const clearBtn = document.getElementById('completedOrdersClearFilters');
    const resetBtn = document.getElementById('completedOrdersResetFilters');
    const outcomeChips = Array.from(page.querySelectorAll('#completedOutcomeChips .filter-chip'));
    const summaryCards = Array.from(page.querySelectorAll('.ongoing-summary-card[data-summary-filter]'));
    const alertHost = document.getElementById('completedOrdersAlertHost');
    const detailModal = document.getElementById('completedOrderDetailModal');
    const detailModalBody = document.getElementById('completedOrderDetailModalBody');
    const detailModalTitle = document.getElementById('completedOrderDetailModalTitle');

    let workspaceOrders = [];
    try {
        workspaceOrders = JSON.parse(page.dataset.orders || '[]');
    } catch (error) {
        workspaceOrders = [];
    }

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        summaryKey: '',
        filters: {
            search: '',
            outcome: '',
            settlement_status: '',
            product_id: '',
            courier_id: '',
            date_from: '',
            date_to: '',
        },
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(type, message) {
        if (!alertHost) {
            return;
        }
        alertHost.className = 'alert alert-' + type;
        alertHost.textContent = message;
        alertHost.classList.remove('d-none');
    }

    function clearAlert() {
        if (!alertHost) {
            return;
        }
        alertHost.classList.add('d-none');
        alertHost.textContent = '';
    }

    function outcomeBadge(outcome) {
        const value = String(outcome || '').toUpperCase();
        const css = value.toLowerCase().replace(/_/g, '-');
        return '<span class="badge-status is-' + escapeHtml(css) + '">' + escapeHtml(value || '—') + '</span>';
    }

    function settlementLabel(status) {
        const value = String(status || '');
        if (value === 'Settled' || value === 'Settlement Pending') {
            return value;
        }
        return '';
    }

    function settlementStatusBadge(status) {
        const label = settlementLabel(status);
        if (!label) {
            return '<span class="text-muted">—</span>';
        }

        const css = label === 'Settled' ? 'is-settled' : 'is-settlement-pending';
        return '<span class="badge-status ' + css + '">' + escapeHtml(label) + '</span>';
    }

    function settlementCell(order) {
        const outcome = String(order.outcome || '').toUpperCase();
        const statusBadge = settlementStatusBadge(order.settlement_status);

        if (outcome === 'RETURNED') {
            return '<div class="settlement-adjustment">' +
                '<div>' + statusBadge + '</div>' +
                '<div class="mt-1"><span class="badge-status is-settlement-adjustment">Settlement/Adjustment</span></div>' +
                '<div class="order-sub">No courier payment for returned orders</div>' +
                '</div>';
        }

        return statusBadge;
    }

    function formatCod(order) {
        const amount = order.cod_amount;
        if (amount === null || amount === undefined || amount === '') {
            return '—';
        }
        const code = order.currency_code ? (escapeHtml(order.currency_code) + ' ') : '';
        return code + escapeHtml(amount);
    }

    function emptyMessage() {
        const hasSearch = Boolean(state.filters.search);
        const hasProduct = Boolean(state.filters.product_id);
        const hasCourier = Boolean(state.filters.courier_id);
        const hasDates = Boolean(state.filters.date_from || state.filters.date_to);
        const outcome = String(state.filters.outcome || '').toUpperCase();
        const settlement = String(state.filters.settlement_status || '');
        const narrowed = hasSearch || hasProduct || hasCourier || hasDates;

        if (narrowed || (outcome && settlement)) {
            return page.dataset.emptyMessageSearch || 'No completed orders match your filters.';
        }

        if (outcome === 'DELIVERED') {
            return page.dataset.emptyMessageDelivered || 'No delivered orders found.';
        }
        if (outcome === 'RETURNED') {
            return page.dataset.emptyMessageReturned || 'No returned orders found.';
        }
        if (settlement === 'Settlement Pending') {
            return page.dataset.emptyMessageSettlementPending || 'No orders are currently settlement pending.';
        }
        if (settlement === 'Settled') {
            return page.dataset.emptyMessageSettled || 'No settled orders found.';
        }

        return page.dataset.emptyMessageAll || 'No delivered or returned orders yet.';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);

        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            outcome: (formData.get('outcome') || '').toString(),
            settlement_status: (formData.get('settlement_status') || '').toString(),
            product_id: (formData.get('product_id') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function syncSettlementFilter() {
        if (settlementSelect) {
            settlementSelect.value = state.filters.settlement_status || '';
        }
    }

    function syncOutcomeUi() {
        const active = String(state.filters.outcome || '').toUpperCase();

        outcomeChips.forEach(function (chip) {
            const chipOutcome = String(chip.dataset.outcomeChip || '').toUpperCase();
            const isActive = chipOutcome === active;
            chip.classList.toggle('is-active', isActive);
        });

        summaryCards.forEach(function (card) {
            const key = String(card.dataset.summaryFilter || '');
            const isActive = state.summaryKey !== '' && state.summaryKey === key;
            card.classList.toggle('is-active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (outcomeSelect) {
            outcomeSelect.value = state.filters.outcome || '';
        }

        syncSettlementFilter();
    }

    function applySummaryPreset(key) {
        const next = state.summaryKey === key ? '' : key;
        state.summaryKey = next;
        state.page = 1;

        if (next === 'delivered') {
            state.filters.outcome = 'DELIVERED';
            state.filters.settlement_status = '';
        } else if (next === 'returned') {
            state.filters.outcome = 'RETURNED';
            state.filters.settlement_status = '';
        } else if (next === 'settlement_pending') {
            state.filters.outcome = '';
            state.filters.settlement_status = 'Settlement Pending';
        } else if (next === 'settled') {
            state.filters.outcome = '';
            state.filters.settlement_status = 'Settled';
        } else {
            state.filters.outcome = '';
            state.filters.settlement_status = '';
        }

        if (outcomeSelect) {
            outcomeSelect.value = state.filters.outcome || '';
        }

        syncSettlementFilter();
        syncOutcomeUi();
        render();
    }

    function setOutcomeFilter(outcome) {
        state.filters.outcome = outcome || '';
        state.summaryKey = '';
        state.page = 1;

        syncOutcomeUi();
        render();
    }

    function matchesFilters(order) {
        const search = state.filters.search.toLowerCase();
        if (search) {
            const haystack = [
                order.order_number,
                order.customer_name,
                order.primary_phone,
                order.waybill,
                order.product,
                order.invoice_number,
            ].join(' ').toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        const outcome = String(order.outcome || '').toUpperCase();
        if (state.filters.outcome && outcome !== String(state.filters.outcome).toUpperCase()) {
            return false;
        }

        if (state.filters.settlement_status) {
            if (String(order.settlement_status || '') !== state.filters.settlement_status) {
                return false;
            }
        }

        if (state.filters.product_id && !orderMatchesId(order, state.filters.product_id, 'product_id', 'product_ids')) {
            return false;
        }

        if (state.filters.courier_id && !orderMatchesId(order, state.filters.courier_id, 'courier_id', null)) {
            return false;
        }

        const orderDate = order.completed_date || '';
        if (state.filters.date_from && orderDate && orderDate < state.filters.date_from) {
            return false;
        }
        if (state.filters.date_to && orderDate && orderDate > state.filters.date_to) {
            return false;
        }

        return true;
    }

    function orderMatchesId(order, wanted, field, listField) {
        const value = String(wanted || '');
        if (!value) {
            return true;
        }
        if (listField && Array.isArray(order[listField]) && order[listField].map(String).indexOf(value) !== -1) {
            return true;
        }
        return String(order[field] || '') === value;
    }

    function filteredOrders() {
        return workspaceOrders.filter(matchesFilters);
    }

    function renderRows(orders) {
        if (!orders.length) {
            tableBody.innerHTML =
                '<tr><td colspan="11" class="text-center text-muted py-5">' +
                '<div class="empty-state mb-0">' +
                '<span class="material-symbols-outlined">inventory_2</span>' +
                '<h4 class="fs-16 mt-2 mb-1">No completed orders</h4>' +
                '<p class="mb-0">' + escapeHtml(emptyMessage()) + '</p>' +
                '</div></td></tr>';
            return;
        }

        tableBody.innerHTML = orders.map(function (order) {
            return '<tr>' +
                '<td><div class="order-number">' + escapeHtml(order.order_number || '—') + '</div></td>' +
                '<td><div class="customer-name">' + escapeHtml(order.customer_name || '—') + '</div></td>' +
                '<td>' + escapeHtml(order.primary_phone || '—') + '</td>' +
                '<td>' + escapeHtml(order.product || '—') + '</td>' +
                '<td>' + escapeHtml(order.courier || '—') + '</td>' +
                '<td>' + escapeHtml(order.waybill || '—') + '</td>' +
                '<td><div class="amount-value">' + formatCod(order) + '</div></td>' +
                '<td>' + outcomeBadge(order.outcome) + '</td>' +
                '<td>' + settlementCell(order) + '</td>' +
                '<td>' + escapeHtml(order.completed_at || '—') + '</td>' +
                '<td class="text-end">' +
                '<button type="button" class="btn btn-sm btn-light border order-view-btn" ' +
                'data-order-number="' + escapeHtml(order.order_number || '') + '">View</button>' +
                '</td></tr>';
        }).join('');
    }

    function renderPagination(total, currentPage, perPage) {
        if (!paginationHost) {
            return;
        }

        if (total === 0) {
            paginationHost.innerHTML = '';
            if (resultsMeta) {
                resultsMeta.textContent = '';
            }
            return;
        }

        const lastPage = Math.max(1, Math.ceil(total / perPage));
        const safePage = Math.min(Math.max(currentPage, 1), lastPage);
        const from = ((safePage - 1) * perPage) + 1;
        const to = Math.min(safePage * perPage, total);

        if (resultsMeta) {
            resultsMeta.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' completed orders';
        }

        paginationHost.innerHTML =
            '<div class="orders-pagination">' +
            '<div class="orders-pagination-meta">Showing ' + from + '–' + to + ' of ' + total + ' completed orders</div>' +
            '<div class="orders-pagination-actions">' +
            '<button type="button" class="btn btn-sm btn-light border" data-page-nav="prev"' +
            (safePage <= 1 ? ' disabled' : '') + '>Previous</button>' +
            '<span class="orders-pagination-meta">Page ' + safePage + ' of ' + lastPage + '</span>' +
            '<button type="button" class="btn btn-sm btn-light border" data-page-nav="next"' +
            (safePage >= lastPage ? ' disabled' : '') + '>Next</button>' +
            '</div></div>';

        state.page = safePage;
    }

    function render() {
        const filtered = filteredOrders();
        const lastPage = Math.max(1, Math.ceil(filtered.length / state.perPage));
        if (state.page > lastPage) {
            state.page = lastPage;
        }

        const start = (state.page - 1) * state.perPage;
        const pageRows = filtered.slice(start, start + state.perPage);

        renderRows(pageRows);
        renderPagination(filtered.length, state.page, state.perPage);
        syncOutcomeUi();
    }

    function applyFormFilters(resetPage) {
        readFiltersFromForm();
        state.summaryKey = '';
        if (resetPage) {
            state.page = 1;
        }
        clearAlert();
        render();
    }

    function detailRow(label, valueHtml) {
        return '<div class="completed-detail-row">' +
            '<div class="completed-detail-label">' + escapeHtml(label) + '</div>' +
            '<div class="completed-detail-value">' + valueHtml + '</div>' +
            '</div>';
    }

    function openDetailModal(order) {
        if (!detailModal || !detailModalBody) {
            return;
        }

        const outcome = String(order.outcome || '').toUpperCase();
        const isReturned = outcome === 'RETURNED';

        if (detailModalTitle) {
            detailModalTitle.textContent = 'Order ' + (order.order_number || '');
        }

        let body = '';
        body += detailRow('Order', '<strong>' + escapeHtml(order.order_number || '—') + '</strong>');
        body += detailRow('Customer', escapeHtml(order.customer_name || '—') +
            '<div class="order-sub">' + escapeHtml(order.primary_phone || '—') + '</div>');
        body += detailRow('Product', escapeHtml(order.product || '—'));
        body += detailRow('Courier', escapeHtml(order.courier || '—'));
        body += detailRow('Waybill', escapeHtml(order.waybill || '—'));
        body += detailRow('Outcome', outcomeBadge(outcome));
        body += detailRow('COD Amount', '<div class="amount-value">' + formatCod(order) + '</div>');
        body += detailRow('Reseller Settlement', settlementStatusBadge(order.settlement_status));

        if (isReturned) {
            body += detailRow(
                'Settlement/Adjustment',
                '<span class="badge-status is-settlement-adjustment">Returned</span>'
            );
            body += detailRow('Completed At', escapeHtml(order.completed_at || '—'));
            body += '<div class="completed-detail-note mt-3">' +
                'Returned orders may include a reseller settlement adjustment. ' +
                'Settlement adjustments are handled separately and do not mean every return creates a financial loss. ' +
                'No courier payment for returned orders.' +
                '</div>';
        } else {
            body += detailRow('Completed At', escapeHtml(order.completed_at || '—'));
            body += '<div class="completed-detail-note mt-3">' +
                'Reseller settlement is handled separately by Admin.' +
                '</div>';
        }

        detailModalBody.innerHTML = body;
        detailModal.classList.remove('hidden');
    }

    function closeDetailModal() {
        if (!detailModal) {
            return;
        }
        detailModal.classList.add('hidden');
        if (detailModalBody) {
            detailModalBody.innerHTML = '';
        }
    }

    function findOrderByNumber(orderNumber) {
        return workspaceOrders.find(function (order) {
            return String(order.order_number || '') === String(orderNumber || '');
        }) || null;
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        applyFormFilters(true);
    });

    [outcomeSelect, settlementSelect, productSelect, courierSelect, dateFromInput, dateToInput].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('change', function () {
            applyFormFilters(true);
        });
    });

    outcomeChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            setOutcomeFilter(chip.dataset.outcomeChip || '');
        });
    });

    summaryCards.forEach(function (card) {
        card.addEventListener('click', function () {
            applySummaryPreset(card.dataset.summaryFilter || '');
        });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applyFormFilters(true);
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            filterForm.reset();
            state.filters = {
                search: '',
                outcome: '',
                settlement_status: '',
                product_id: '',
                courier_id: '',
                date_from: '',
                date_to: '',
            };
            state.summaryKey = '';
            state.page = 1;
            clearAlert();
            render();
        });
    }

    if (paginationHost) {
        paginationHost.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-page-nav]');
            if (!btn || btn.disabled) {
                return;
            }
            const dir = btn.getAttribute('data-page-nav');
            if (dir === 'prev') {
                state.page -= 1;
            } else if (dir === 'next') {
                state.page += 1;
            }
            render();
        });
    }

    tableBody.addEventListener('click', function (event) {
        const btn = event.target.closest('.order-view-btn');
        if (!btn) {
            return;
        }
        const orderNumber = btn.getAttribute('data-order-number') || '';
        const order = findOrderByNumber(orderNumber);
        if (!order) {
            showAlert('info', 'Order detail is unavailable.');
            return;
        }
        clearAlert();
        openDetailModal(order);
    });

    if (detailModal) {
        detailModal.addEventListener('click', function (event) {
            if (event.target === detailModal) {
                closeDetailModal();
            }
            const closeBtn = event.target.closest('[data-close-modal="completedOrderDetailModal"]');
            if (closeBtn) {
                closeDetailModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) {
            closeDetailModal();
        }
    });

    render();
})();
</script>
