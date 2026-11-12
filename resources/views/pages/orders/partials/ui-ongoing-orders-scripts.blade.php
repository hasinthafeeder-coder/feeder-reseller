<script>
(function () {
    'use strict';

    const page = document.getElementById('resellerOngoingOrdersPage');
    if (!page) {
        return;
    }

    const tableBody = document.getElementById('ongoingOrdersTableBody');
    const resultsMeta = document.getElementById('ongoingOrdersResultsMeta');
    const paginationHost = document.getElementById('ongoingOrdersPaginationHost');
    const filterForm = document.getElementById('ongoingOrdersFilterForm');
    const searchInput = document.getElementById('ongoingFilterSearch');
    const statusSelect = document.getElementById('ongoingFilterStatus');
    const productSelect = document.getElementById('ongoingFilterProduct');
    const courierSelect = document.getElementById('ongoingFilterCourier');
    const dateFromInput = document.getElementById('ongoingFilterDateFrom');
    const dateToInput = document.getElementById('ongoingFilterDateTo');
    const clearBtn = document.getElementById('ongoingOrdersClearFilters');
    const resetBtn = document.getElementById('ongoingOrdersResetFilters');
    const statusChips = Array.from(page.querySelectorAll('#ongoingStatusChips .filter-chip'));
    const summaryCards = Array.from(page.querySelectorAll('.ongoing-summary-card[data-status-filter]'));
    const alertHost = document.getElementById('ongoingOrdersAlertHost');

    let workspaceOrders = [];
    try {
        workspaceOrders = JSON.parse(page.dataset.orders || '[]');
    } catch (error) {
        workspaceOrders = [];
    }

    const state = {
        page: 1,
        perPage: parseInt(page.dataset.perPage || '25', 10) || 25,
        filters: {
            search: '',
            status: '',
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

    function statusBadge(status) {
        const value = String(status || '').toUpperCase();
        const css = value.toLowerCase().replace(/_/g, '-');
        return '<span class="badge-status is-' + escapeHtml(css) + '">' + escapeHtml(value || '—') + '</span>';
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

        if (hasSearch || hasProduct || hasCourier || hasDates) {
            return page.dataset.emptyMessageSearch || 'No ongoing orders match your filters.';
        }

        const status = String(state.filters.status || '').toUpperCase();
        if (status === 'PRINT') {
            return page.dataset.emptyMessagePrint || 'No orders are currently waiting for printing.';
        }
        if (status === 'PRINTED') {
            return page.dataset.emptyMessagePrinted || 'No printed orders are waiting for packing.';
        }
        if (status === 'PACKING') {
            return page.dataset.emptyMessagePacking || 'No orders are currently being packed.';
        }
        if (status === 'DISPATCHED') {
            return page.dataset.emptyMessageDispatched || 'No dispatched orders are currently in progress.';
        }

        return page.dataset.emptyMessageAll || 'No orders are currently in fulfillment.';
    }

    function readFiltersFromForm() {
        const formData = new FormData(filterForm);
        state.filters = {
            search: (formData.get('search') || '').toString().trim(),
            status: (formData.get('status') || '').toString(),
            product_id: (formData.get('product_id') || '').toString(),
            courier_id: (formData.get('courier_id') || '').toString(),
            date_from: (formData.get('date_from') || '').toString(),
            date_to: (formData.get('date_to') || '').toString(),
        };
    }

    function syncStatusUi() {
        const active = String(state.filters.status || '').toUpperCase();

        statusChips.forEach(function (chip) {
            const chipStatus = String(chip.dataset.statusChip || '').toUpperCase();
            const isActive = chipStatus === active;
            chip.classList.toggle('is-active', isActive);
        });

        summaryCards.forEach(function (card) {
            const cardStatus = String(card.dataset.statusFilter || '').toUpperCase();
            const isActive = active !== '' && cardStatus === active;
            card.classList.toggle('is-active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (statusSelect) {
            statusSelect.value = state.filters.status || '';
        }
    }

    function setStatusFilter(status) {
        state.filters.status = status || '';
        state.page = 1;
        syncStatusUi();
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
            ].join(' ').toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        if (state.filters.status && String(order.status).toUpperCase() !== state.filters.status.toUpperCase()) {
            return false;
        }

        if (state.filters.product_id && !orderMatchesId(order, state.filters.product_id, 'product_id', 'product_ids')) {
            return false;
        }

        if (state.filters.courier_id && !orderMatchesId(order, state.filters.courier_id, 'courier_id', null)) {
            return false;
        }

        const orderDate = order.updated_date || '';
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
                '<tr><td colspan="10" class="text-center text-muted py-5">' +
                '<div class="empty-state mb-0">' +
                '<span class="material-symbols-outlined">inventory_2</span>' +
                '<h4 class="fs-16 mt-2 mb-1">No ongoing orders</h4>' +
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
                '<td>' + statusBadge(order.status) + '</td>' +
                '<td>' + escapeHtml(order.updated_at || '—') + '</td>' +
                '<td class="text-center">' +
                '<div class="d-flex justify-content-center" style="gap: 12px;">' +
                '<button type="button" class="bg-transparent p-0 border-0 order-view-btn" ' +
                'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="View Order" ' +
                'data-order-number="' + escapeHtml(order.order_number || '') + '" ' +
                'data-detail-url="' + escapeHtml(order.detail_url || '') + '">' +
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
            resultsMeta.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' ongoing orders';
        }

        paginationHost.innerHTML =
            '<div class="orders-pagination">' +
            '<div class="orders-pagination-meta">Showing ' + from + '–' + to + ' of ' + total + ' ongoing orders</div>' +
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
        syncStatusUi();
    }

    function applyFormFilters(resetPage) {
        readFiltersFromForm();
        if (resetPage) {
            state.page = 1;
        }
        clearAlert();
        render();
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        applyFormFilters(true);
    });

    [statusSelect, productSelect, courierSelect, dateFromInput, dateToInput].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('change', function () {
            applyFormFilters(true);
        });
    });

    statusChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            setStatusFilter(chip.dataset.statusChip || '');
        });
    });

    summaryCards.forEach(function (card) {
        card.addEventListener('click', function () {
            const next = String(card.dataset.statusFilter || '');
            const current = String(state.filters.status || '');
            setStatusFilter(current === next ? '' : next);
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
                status: '',
                product_id: '',
                courier_id: '',
                date_from: '',
                date_to: '',
            };
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
        const detailUrl = btn.getAttribute('data-detail-url') || '';
        if (detailUrl) {
            window.location.assign(detailUrl);
            return;
        }
        showAlert('info', 'Order detail is unavailable.');
    });

    render();
})();
</script>
