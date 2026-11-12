<script>
(function () {
    'use strict';

    const page = document.getElementById('resellerArchivedOrdersPage');
    if (!page) {
        return;
    }

    const tableBody = document.getElementById('archivedOrdersTableBody');
    const resultsMeta = document.getElementById('archivedOrdersResultsMeta');
    const paginationHost = document.getElementById('archivedOrdersPaginationHost');
    const filterForm = document.getElementById('archivedOrdersFilterForm');
    const searchInput = document.getElementById('archivedFilterSearch');
    const outcomeSelect = document.getElementById('archivedFilterOutcome');
    const settlementSelect = document.getElementById('archivedFilterSettlement');
    const courierSelect = document.getElementById('archivedFilterCourier');
    const dateFromInput = document.getElementById('archivedFilterDateFrom');
    const dateToInput = document.getElementById('archivedFilterDateTo');
    const clearBtn = document.getElementById('archivedOrdersClearFilters');
    const resetBtn = document.getElementById('archivedOrdersResetFilters');
    const outcomeChips = Array.from(page.querySelectorAll('#archivedOutcomeChips .filter-chip'));
    const summaryCards = Array.from(page.querySelectorAll('.ongoing-summary-card[data-summary-filter]'));
    const alertHost = document.getElementById('archivedOrdersAlertHost');
    const detailModal = document.getElementById('archivedOrderDetailModal');
    const detailModalBody = document.getElementById('archivedOrderDetailModalBody');
    const detailModalTitle = document.getElementById('archivedOrderDetailModalTitle');

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
        const hasCourier = Boolean(state.filters.courier_id);
        const hasDates = Boolean(state.filters.date_from || state.filters.date_to);
        const outcome = String(state.filters.outcome || '').toUpperCase();
        const settlement = String(state.filters.settlement_status || '');
        const narrowed = hasSearch || hasCourier || hasDates;

        if (narrowed || (outcome && settlement)) {
            return page.dataset.emptyMessageSearch || 'No archived orders match your filters.';
        }

        if (outcome === 'DELIVERED') {
            return page.dataset.emptyMessageDelivered || 'No archived delivered orders found.';
        }
        if (outcome === 'RETURNED') {
            return page.dataset.emptyMessageReturned || 'No archived returned orders found.';
        }
        if (settlement === 'Settlement Pending') {
            return page.dataset.emptyMessageSettlementPending || 'No archived orders are currently settlement pending.';
        }
        if (settlement === 'Settled') {
            return page.dataset.emptyMessageSettled || 'No settled archived orders found.';
        }

        return page.dataset.emptyMessageAll || 'No archived orders found.';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);

        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            outcome: (formData.get('outcome') || '').toString(),
            settlement_status: (formData.get('settlement_status') || '').toString(),
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

        if (state.filters.courier_id && String(order.courier_id || '') !== String(state.filters.courier_id)) {
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

    function filteredOrders() {
        return workspaceOrders.filter(matchesFilters);
    }

    function renderRows(orders) {
        if (!orders.length) {
            tableBody.innerHTML =
                '<tr><td colspan="11" class="text-center text-muted py-5">' +
                '<div class="empty-state mb-0">' +
                '<span class="material-symbols-outlined">inventory_2</span>' +
                '<h4 class="fs-16 mt-2 mb-1">No archived orders</h4>' +
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
                '<td class="text-center">' +
                '<div class="d-flex justify-content-center" style="gap: 12px;">' +
                '<button type="button" class="bg-transparent p-0 border-0 order-view-btn" ' +
                'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View Order" ' +
                'data-order-number="' + escapeHtml(order.order_number || '') + '">' +
                '<i class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i></button>' +
                '</div></td></tr>';
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
            resultsMeta.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' archived orders';
        }

        paginationHost.innerHTML =
            '<div class="orders-pagination">' +
            '<div class="orders-pagination-meta">Showing ' + from + '–' + to + ' of ' + total + ' archived orders</div>' +
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
        body += detailRow('Order Amount', '<div class="amount-value">' + formatCod(order) + '</div>');
        body += detailRow('Reseller Settlement', settlementStatusBadge(order.settlement_status));
        body += detailRow('Archive Context', '<span class="text-muted">Historical completed-order archive (read-only)</span>');

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
                'This archived order is historical/read-only. Reseller settlement is handled separately by Admin.' +
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

    [outcomeSelect, settlementSelect, courierSelect, dateFromInput, dateToInput].forEach(function (el) {
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
            const closeBtn = event.target.closest('[data-close-modal="archivedOrderDetailModal"]');
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
