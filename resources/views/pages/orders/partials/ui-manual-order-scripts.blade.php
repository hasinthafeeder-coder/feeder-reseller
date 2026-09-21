<script>
(function () {
    'use strict';

    @php
        $orderFormMode = $orderFormMode ?? 'create';
        $formDefaults = $formDefaults ?? [];
        $scriptEligibleCcas = $formEligibleCcas ?? ($eligibleCcas ?? collect());
    @endphp
    const pageBootstrap = {
        mode: @json($orderFormMode),
        markets: @json($markets ?? []),
        eligibleCcas: @json($scriptEligibleCcas),
        isCca: @json((bool) ($isCca ?? false)),
        routes: @json($catalogRoutes ?? []),
        duplicateOrders: @json($duplicateOrders ?? []),
        oldItems: @json($oldItems ?? []),
        csrf: @json(csrf_token()),
        lockMarket: @json(($orderFormMode ?? 'create') === 'edit'),
        lockDiscount: @json((bool) ($isCca ?? false) && ($orderFormMode ?? 'create') === 'edit'),
        canAssignCourier: @json((bool) ($canAssignCourier ?? false)),
        shipment: @json($shipmentBootstrap ?? null),
    };

    const oldDistrictName = @json(old('district_name', $formDefaults['district_name'] ?? ''));
    const oldCityName = @json(old('city_name', $formDefaults['city_name'] ?? ''));
    const oldCourierId = @json(old('courier_id', $formDefaults['courier_id'] ?? ''));
    const oldCourierCityId = @json(old('courier_city_id', $formDefaults['courier_city_id'] ?? ''));
    const oldMarketId = @json(old('market_id', $formDefaults['market_id'] ?? ''));

    const marketChecks = document.getElementById('marketChecks');
    const marketIdInput = document.getElementById('marketId');
    const courierSelect = document.getElementById('courierId');
    const courierDistrict = document.getElementById('courierDistrict');
    const courierCity = document.getElementById('courierCity');
    const districtNameValue = document.getElementById('districtNameValue');
    const cityNameValue = document.getElementById('cityNameValue');
    const courierCityIdValue = document.getElementById('courierCityIdValue');
    const linesContainer = document.getElementById('orderLines');
    const addLineBtn = document.getElementById('addLineBtn');
    const discountInput = document.getElementById('discountAmount');
    const afterHoursPanel = document.getElementById('afterHoursPanel');
    const customerRiskPanel = document.getElementById('customerRiskPanel');
    const primaryPhone = document.getElementById('primaryPhone');
    const secondaryPhone = document.getElementById('secondaryPhone');
    const customerName = document.getElementById('customerName');
    const addressLine1 = document.getElementById('addressLine1');
    const fullAddressText = document.getElementById('fullAddressText');
    const form = document.getElementById('manualOrderForm');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');
    const sendToCallCenterBtn = document.getElementById('sendToCallCenterBtn');
    const saveOrderChangesBtn = document.getElementById('saveOrderChangesBtn');
    const isEditMode = pageBootstrap.mode === 'edit';
    const reviewCustomerBlock = document.getElementById('reviewCustomerBlock');
    const reviewDeliveryBlock = document.getElementById('reviewDeliveryBlock');
    const reviewCourierBlock = document.getElementById('reviewCourierBlock');
    const customerHistoryPanel = document.getElementById('customerHistoryPanel');
    const customerExtraPanel = document.getElementById('customerExtraPanel');
    const orderTimelineList = document.getElementById('orderTimelineList');
    const supplierIdInput = document.getElementById('supplierId');
    const orderIntentInput = document.getElementById('orderIntent');
    const assignmentTargetInput = document.getElementById('assignmentTarget');
    const assignCcaIdInput = document.getElementById('assignCcaId');
    const duplicateWarningOverriddenInput = document.getElementById('duplicateWarningOverridden');
    const afterHoursWarningShownInput = document.getElementById('afterHoursWarningShown');
    const duplicatePanel = document.getElementById('duplicatePanel');
    const duplicateList = document.getElementById('duplicateList');
    const confirmDuplicateOverride = document.getElementById('confirmDuplicateOverride');
    const callCenterAssignModal = document.getElementById('callCenterAssignModal');
    const modalAssignCcaSelect = document.getElementById('modalAssignCcaSelect');
    const closeCallCenterAssignModal = document.getElementById('closeCallCenterAssignModal');
    const confirmSendToCallCenterBtn = document.getElementById('confirmSendToCallCenterBtn');
    const assignCourierBtn = document.getElementById('assignCourierBtn');
    const assignCourierError = document.getElementById('assignCourierError');
    const assignCourierDebug = document.getElementById('assignCourierDebug');
    const assignCourierDebugTitle = document.getElementById('assignCourierDebugTitle');
    const assignCourierDebugMeta = document.getElementById('assignCourierDebugMeta');
    const assignCourierDebugResponse = document.getElementById('assignCourierDebugResponse');
    const assignCourierDebugRequest = document.getElementById('assignCourierDebugRequest');
    const assignCourierHint = document.getElementById('assignCourierHint');
    const assignedCourierPanel = document.getElementById('assignedCourierPanel');
    const assignedCourierName = document.getElementById('assignedCourierName');
    const assignedCourierWaybill = document.getElementById('assignedCourierWaybill');

    let lineIndex = 0;
    let currencyCode = 'LKR';
    let courierFee = 0;
    let selectedMarketId = marketIdInput.value || null;
    let marketSuppliers = [];
    let lockedSupplierId = supplierIdInput.value || null;
    let createCouriers = [];
    let createCourierDistricts = [];
    let createCourierCities = [];
    let liveItemsSubtotal = 0;
    let liveTotalWeight = 0;
    let afterHoursData = null;
    let lastLookupResult = null;
    let duplicateCheckToken = 0;
    let timelineReady = false;
    let timelineEvents = [];
    let discountTimelineTimer = null;
    let orderHistoryRecords = [];
    let orderHistoryPage = 1;
    const ORDER_HISTORY_PAGE_SIZE = 10;

    function normalizePhone(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function money(amount, code) {
        const prefix = (code || currencyCode || '');
        return (prefix ? prefix + ' ' : '')
            + Number(amount || 0).toLocaleString('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function fetchCatalog(route, params) {
        const url = new URL(route, window.location.origin);

        Object.entries(params || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => url.searchParams.append(key + '[]', item));
                return;
            }

            url.searchParams.set(key, value);
        });

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Catalog request failed');
        }

        const payload = await response.json();
        return payload.data;
    }

    function setLockedSupplier(supplierId) {
        lockedSupplierId = supplierId ? String(supplierId) : null;
        supplierIdInput.value = lockedSupplierId || '';
        loadCouriers({ preferredCourierId: courierSelect.value });
    }

    async function loadSuppliersForMarket(marketId) {
        if (!marketId) {
            marketSuppliers = [];
            return;
        }

        marketSuppliers = await fetchCatalog(pageBootstrap.routes.suppliers, {
            market_id: marketId,
        });

        if (!lockedSupplierId && marketSuppliers.length === 1) {
            setLockedSupplier(marketSuppliers[0].id);
        }
    }

    function reviewText(value, placeholder) {
        const text = String(value || '').trim();
        return text || placeholder;
    }

    function setReviewBlock(el, lines, emptyPlaceholder) {
        const filled = lines.filter((line) => String(line || '').trim() !== '');
        if (!filled.length) {
            el.textContent = emptyPlaceholder;
            el.classList.add('is-placeholder');
            return;
        }
        el.textContent = filled.join('\n');
        el.classList.remove('is-placeholder');
    }

    function updateOrderReview() {
        setReviewBlock(reviewCustomerBlock, [
            reviewText(customerName.value, ''),
            reviewText(primaryPhone.value, ''),
            reviewText(secondaryPhone.value, ''),
        ], 'No customer details yet');

        const city = selectedCityName();
        const district = selectedDistrictName();
        const cityDistrict = [city, district].filter(Boolean).join(', ');

        setReviewBlock(reviewDeliveryBlock, [
            reviewText(addressLine1.value, ''),
            cityDistrict,
        ], 'No delivery details yet');

        const courier = selectedCourier();
        const booked = pageBootstrap.shipment && pageBootstrap.shipment.waybill
            ? [
                pageBootstrap.shipment.courier?.name || '',
                pageBootstrap.shipment.waybill,
            ].filter(Boolean).join('\n')
            : null;
        setReviewBlock(reviewCourierBlock, booked ? [booked] : [
            courier ? courier.name : '',
            district,
            city,
        ], 'No courier selected');
    }

    function statusBadgeClass(status) {
        const key = String(status || '').toLowerCase();
        if (key === 'delivered') return 'bg-success-subtle text-success';
        if (key === 'returned') return 'bg-warning-subtle text-warning';
        if (key === 'cancelled' || key === 'failed') return 'bg-danger-subtle text-danger';
        if (key === 'pending' || key === 'processing') return 'bg-primary-subtle text-primary';
        return 'bg-secondary-subtle text-secondary';
    }

    function buildOrderHistorySummary(history) {
        const counts = {
            total: history.length,
            delivered: 0,
            returned: 0,
            cancelled: 0,
            failed: 0,
            processing: 0,
            pending: 0,
            other: 0,
        };

        history.forEach((order) => {
            const key = String(order.status || '').toLowerCase();
            if (key === 'delivered') counts.delivered += 1;
            else if (key === 'returned') counts.returned += 1;
            else if (key === 'cancelled') counts.cancelled += 1;
            else if (key === 'failed') counts.failed += 1;
            else if (key === 'processing') counts.processing += 1;
            else if (key === 'pending') counts.pending += 1;
            else counts.other += 1;
        });

        return counts;
    }

    function renderOrderHistorySummary(counts) {
        const chips = [
            { label: 'Total', value: counts.total, className: '' },
            { label: 'Delivered', value: counts.delivered, className: 'is-delivered' },
            { label: 'Returned', value: counts.returned, className: 'is-returned' },
            { label: 'Cancelled', value: counts.cancelled, className: 'is-cancelled' },
            { label: 'Failed', value: counts.failed, className: 'is-failed' },
            { label: 'Processing', value: counts.processing, className: 'is-processing' },
            { label: 'Pending', value: counts.pending, className: 'is-pending' },
        ];

        if (counts.other > 0) {
            chips.push({ label: 'Other', value: counts.other, className: '' });
        }

        return `
            <div class="history-summary" aria-label="Order history summary">
                ${chips.map((chip) => `
                    <span class="history-summary-chip ${chip.className}">
                        ${escapeHtml(chip.label)}
                        <strong>${chip.value}</strong>
                    </span>
                `).join('')}
            </div>
        `;
    }

    function renderOrderHistoryRows(orders) {
        return orders.map((order) => {
            const amount = money(order.amount, order.currency || 'LKR');
            const resellerCompany = order.reseller_company || '—';
            const resellerName = order.reseller_name || '—';
            const courierName = order.courier_name || '—';
            const trackingNumber = order.tracking_number || '—';
            return `
                <tr>
                    <td>
                        <div class="history-order-no">${escapeHtml(order.order_number)}</div>
                        <div class="text-body" style="font-size:11px">${escapeHtml(order.date)}</div>
                    </td>
                    <td class="history-reseller">
                        <div class="history-reseller-company">${escapeHtml(resellerCompany)}</div>
                        <div class="history-reseller-name">${escapeHtml(resellerName)}</div>
                    </td>
                    <td class="history-items">
                        <div class="history-items-name">${escapeHtml(order.items)}</div>
                        <div class="history-items-amount">${escapeHtml(amount)}</div>
                    </td>
                    <td class="history-courier">
                        <div class="history-courier-name">${escapeHtml(courierName)}</div>
                        <div class="history-courier-tracking">${escapeHtml(trackingNumber)}</div>
                    </td>
                    <td>
                        <span class="badge badge-status ${statusBadgeClass(order.status)}">
                            ${escapeHtml(order.status)}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderOrderHistoryPagination(total, page, pageSize) {
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        const from = total === 0 ? 0 : ((page - 1) * pageSize) + 1;
        const to = Math.min(total, page * pageSize);

        return `
            <div class="history-pagination">
                <div class="history-pagination-meta">
                    Showing ${from}–${to} of ${total} · Page ${page} of ${totalPages}
                </div>
                <div class="history-pagination-actions">
                    <button type="button" class="btn btn-sm btn-light border" id="historyPrevPageBtn"
                        ${page <= 1 ? 'disabled' : ''}>
                        Previous
                    </button>
                    <button type="button" class="btn btn-sm btn-light border" id="historyNextPageBtn"
                        ${page >= totalPages ? 'disabled' : ''}>
                        Next
                    </button>
                </div>
            </div>
        `;
    }

    function paintOrderHistoryPage() {
        const total = orderHistoryRecords.length;
        if (!total) {
            customerHistoryPanel.innerHTML =
                '<p class="sidebar-empty">No previous orders found for this customer.</p>';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / ORDER_HISTORY_PAGE_SIZE));
        if (orderHistoryPage > totalPages) orderHistoryPage = totalPages;
        if (orderHistoryPage < 1) orderHistoryPage = 1;

        const start = (orderHistoryPage - 1) * ORDER_HISTORY_PAGE_SIZE;
        const pageOrders = orderHistoryRecords.slice(start, start + ORDER_HISTORY_PAGE_SIZE);
        const summary = buildOrderHistorySummary(orderHistoryRecords);

        customerHistoryPanel.innerHTML = `
            ${renderOrderHistorySummary(summary)}
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Reseller</th>
                            <th>Items</th>
                            <th>Courier</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${renderOrderHistoryRows(pageOrders)}</tbody>
                </table>
            </div>
            ${renderOrderHistoryPagination(total, orderHistoryPage, ORDER_HISTORY_PAGE_SIZE)}
        `;

        const prevBtn = document.getElementById('historyPrevPageBtn');
        const nextBtn = document.getElementById('historyNextPageBtn');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (orderHistoryPage <= 1) return;
                orderHistoryPage -= 1;
                paintOrderHistoryPage();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const maxPage = Math.max(1, Math.ceil(total / ORDER_HISTORY_PAGE_SIZE));
                if (orderHistoryPage >= maxPage) return;
                orderHistoryPage += 1;
                paintOrderHistoryPage();
            });
        }
    }

    function renderOrderHistory(customer) {
        orderHistoryRecords = customer?.order_history || [];
        orderHistoryPage = 1;
        paintOrderHistoryPage();
    }

    function refreshCustomerInsightPanels() {
        renderOrderHistory(lastLookupResult);
    }

    function formatTimelineClock(date) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    }

    function pushTimelineEvent({ title, description, type, key }) {
        if (!timelineReady && type !== 'created') {
            return;
        }

        const now = new Date();
        const entry = {
            key: key || null,
            time: formatTimelineClock(now),
            date: 'Today',
            title,
            description,
            type: type || 'created',
        };

        if (key) {
            const existingIndex = timelineEvents.findIndex((event) => event.key === key);
            if (existingIndex >= 0) {
                timelineEvents[existingIndex] = entry;
                renderTimeline();
                return;
            }
        }

        timelineEvents.push(entry);
        if (timelineEvents.length > 25) {
            timelineEvents = timelineEvents.slice(-25);
        }
        renderTimeline();
    }

    function renderTimeline() {
        if (!timelineEvents.length) {
            orderTimelineList.innerHTML =
                '<li class="sidebar-empty px-0">No timeline activity yet.</li>';
            return;
        }

        const newestFirst = timelineEvents.slice().reverse();
        orderTimelineList.innerHTML = newestFirst.map((event) => `
            <li class="order-timeline-item">
                <span class="order-timeline-dot type-${escapeHtml(event.type)}"></span>
                <div class="order-timeline-time">${escapeHtml(event.date)} · ${escapeHtml(event.time)}</div>
                <div class="order-timeline-title">${escapeHtml(event.title)}</div>
                <p class="order-timeline-desc">${escapeHtml(event.description)}</p>
            </li>
        `).join('');
    }

    function clearSelect(select, placeholder) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function selectedMarket() {
        return pageBootstrap.markets.find((m) => String(m.id) === String(selectedMarketId)) || null;
    }

    function selectedCourier() {
        return createCouriers.find((c) => String(c.id) === String(courierSelect.value)) || null;
    }

    function selectedOptionMeta(select, key) {
        const option = select && select.options[select.selectedIndex] ? select.options[select.selectedIndex] : null;
        if (!option || !option.value) {
            return '';
        }
        return option.dataset[key] || option.textContent || '';
    }

    function selectedDistrictName() {
        return selectedOptionMeta(courierDistrict, 'name');
    }

    function selectedCityName() {
        return selectedOptionMeta(courierCity, 'name');
    }

    function selectedCourierCityId() {
        if (!courierCity || !courierCity.value) {
            return null;
        }
        const id = Number(courierCity.value);
        return Number.isInteger(id) && id > 0 ? id : null;
    }

    function selectedCourierStateId() {
        if (!courierDistrict || !courierDistrict.value) {
            return null;
        }
        const id = Number(courierDistrict.value);
        return Number.isInteger(id) && id > 0 ? id : null;
    }

    function syncLocationHiddenFields() {
        if (districtNameValue) {
            districtNameValue.value = selectedDistrictName();
        }
        if (cityNameValue) {
            cityNameValue.value = selectedCityName();
        }
        if (courierCityIdValue) {
            const cityId = selectedCourierCityId();
            courierCityIdValue.value = cityId ? String(cityId) : '';
        }
    }

    function recalc() {
        let subtotal = 0;
        let weight = 0;

        linesContainer.querySelectorAll('.order-line-row').forEach((row) => {
            syncLinePricingDisplay(row);
            const qty = Number(row.querySelector('.line-qty')?.value || 0);
            const price = Number(row.querySelector('.line-price')?.value || 0);
            const unitWeight = Number(row.querySelector('.line-weight')?.value || 0);
            const lineTotal = qty * price;
            subtotal += lineTotal;
            weight += qty * unitWeight;
            const totalEl = row.querySelector('.line-total');
            if (totalEl) totalEl.textContent = money(lineTotal);
        });

        liveItemsSubtotal = subtotal;
        liveTotalWeight = weight;

        const discount = Number(discountInput.value || 0);
        const payable = Math.max(0, subtotal - discount + courierFee);

        document.getElementById('itemsSubtotalLabel').textContent = money(subtotal);
        document.getElementById('courierFeeLabel').textContent = money(courierFee);
        document.getElementById('customerPayableLabel').textContent = money(payable);
        document.getElementById('totalWeightLabel').textContent = weight.toFixed(3) + ' kg';
        updateOrderReview();
    }

    async function searchProducts(query) {
        if (!selectedMarketId) {
            return [];
        }

        const supplierIds = lockedSupplierId
            ? [lockedSupplierId]
            : marketSuppliers.map((supplier) => String(supplier.id));

        if (!supplierIds.length) {
            return [];
        }

        const q = String(query || '').trim();
        const merged = [];
        const seen = new Set();

        await Promise.all(supplierIds.map(async (supplierId) => {
            const products = await fetchCatalog(pageBootstrap.routes.products, {
                market_id: selectedMarketId,
                supplier_id: supplierId,
                search: q,
            });

            products.forEach((product) => {
                if (seen.has(product.id)) {
                    return;
                }

                seen.add(product.id);
                merged.push({
                    ...product,
                    supplier_id: Number(supplierId),
                });
            });
        }));

        return merged.slice(0, 8);
    }

    function collectSelectedVariantIds() {
        const ids = [];
        linesContainer.querySelectorAll('.line-variant').forEach((select) => {
            if (select.value) {
                ids.push(Number(select.value));
            }
        });
        return ids;
    }

    async function fetchAfterHours() {
        if (!selectedMarketId) {
            afterHoursPanel.classList.add('hidden');
            afterHoursPanel.textContent = '';
            afterHoursData = null;
            afterHoursWarningShownInput.value = '0';
            return;
        }

        try {
            afterHoursData = await fetchCatalog(pageBootstrap.routes.afterHours, {
                market_id: selectedMarketId,
            });

            afterHoursPanel.classList.remove('hidden');

            if (afterHoursData.after_hours) {
                afterHoursPanel.className = 'alert alert-warning mb-3';
                afterHoursPanel.innerHTML =
                    'After-hours order window. Local time '
                    + escapeHtml(afterHoursData.local_time)
                    + ' (' + escapeHtml(afterHoursData.timezone) + '). '
                    + 'If rejected/returned, penalty '
                    + escapeHtml(money(afterHoursData.after_hours_penalty_amount, afterHoursData.currency_code))
                    + ' may apply.';
                afterHoursWarningShownInput.value = '1';
            } else {
                afterHoursPanel.className = 'alert alert-info mb-3';
                afterHoursPanel.innerHTML =
                    'Within operating hours. Local time '
                    + escapeHtml(afterHoursData.local_time)
                    + ' (' + escapeHtml(afterHoursData.timezone) + ').';
                afterHoursWarningShownInput.value = '0';
            }
        } catch (error) {
            afterHoursPanel.classList.add('hidden');
            afterHoursData = null;
            afterHoursWarningShownInput.value = '0';
        }
    }

    async function setMarket(marketId, { resetLines = true, fromUser = false } = {}) {
        selectedMarketId = marketId ? String(marketId) : null;
        marketIdInput.value = selectedMarketId || '';

        marketChecks.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = String(input.value) === String(selectedMarketId);
        });

        const market = selectedMarket();
        currencyCode = market?.currency_code || '';

        if (selectedMarketId) {
            await loadSuppliersForMarket(selectedMarketId);
        } else {
            marketSuppliers = [];
        }

        await fetchAfterHours();

        if (resetLines) {
            linesContainer.innerHTML = '';
            lineIndex = 0;
            if (selectedMarketId) {
                addLine({ silent: true });
            }
        }

        refreshCustomerRiskFromLookup(lastLookupResult);
        scheduleDuplicateCheck();
        await loadCouriers({
            preferredCourierId: courierSelect.value || oldCourierId,
            preferredDistrict: selectedDistrictName() || oldDistrictName,
            preferredCityId: selectedCourierCityId() || oldCourierCityId,
            preferredCity: selectedCityName() || oldCityName,
        });
        recalc();

        if (fromUser) {
            pushTimelineEvent({
                key: 'market',
                type: 'market',
                title: market ? 'Market selected' : 'Market cleared',
                description: market
                    ? (market.name + ' (' + market.currency_code + ')')
                    : 'No market selected',
            });
        }
    }

    function loadMarkets() {
        marketChecks.innerHTML = '';
        pageBootstrap.markets.forEach((market) => {
            const wrap = document.createElement('div');
            wrap.className = 'form-check mb-0';

            const input = document.createElement('input');
            input.className = 'form-check-input';
            input.type = 'checkbox';
            input.id = 'marketCheck_' + market.id;
            input.value = String(market.id);
            input.name = 'market_checks[]';

            const label = document.createElement('label');
            label.className = 'form-check-label fs-14';
            label.htmlFor = input.id;
            label.textContent = market.name + ' (' + market.currency_code + ')';

            input.addEventListener('change', () => {
                if (input.checked) {
                    setMarket(market.id, { fromUser: true });
                } else if (String(selectedMarketId) === String(market.id)) {
                    setMarket(null, { fromUser: true });
                }
            });

            wrap.appendChild(input);
            wrap.appendChild(label);
            marketChecks.appendChild(wrap);
        });
    }

    async function loadCouriers({ preferredCourierId = null, preferredDistrict = null, preferredCityId = null, preferredCity = null } = {}) {
        clearSelect(courierSelect, 'Optional — select courier');
        createCouriers = [];
        resetCourierLocations();
        courierFee = 0;

        if (!selectedMarketId || !lockedSupplierId || !pageBootstrap.routes.couriers) {
            recalc();
            return;
        }

        createCouriers = await fetchCatalog(pageBootstrap.routes.couriers, {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
        });

        createCouriers.forEach((courier) => {
            const option = document.createElement('option');
            option.value = courier.id;
            option.textContent = courier.name;
            courierSelect.appendChild(option);
        });

        if (preferredCourierId && createCouriers.some((c) => String(c.id) === String(preferredCourierId))) {
            courierSelect.value = String(preferredCourierId);
            await loadDistrictsForCourier(preferredDistrict, preferredCityId, preferredCity);
            await refreshCourierFeePreview();
        }

        recalc();
    }

    function resetCourierLocations() {
        clearSelect(courierDistrict, 'Select district');
        clearSelect(courierCity, 'Select city');
        courierDistrict.disabled = true;
        courierCity.disabled = true;
        createCourierDistricts = [];
        createCourierCities = [];
        syncLocationHiddenFields();
    }

    async function loadDistrictsForCourier(preferredDistrict = null, preferredCityId = null, preferredCity = null) {
        clearSelect(courierDistrict, 'Select district');
        clearSelect(courierCity, 'Select city');
        createCourierDistricts = [];
        createCourierCities = [];
        syncLocationHiddenFields();

        const courier = selectedCourier();
        if (!courier || !selectedMarketId || !lockedSupplierId) {
            resetCourierLocations();
            return;
        }

        courierDistrict.disabled = false;
        createCourierDistricts = await fetchCatalog(pageBootstrap.routes.courierDistricts, {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
            courier_id: courier.id,
        });

        createCourierDistricts.forEach((row) => {
            const option = document.createElement('option');
            const stateId = row.id && Number(row.id) > 0 ? String(row.id) : '';
            const name = row.name || row.district || '';
            option.value = stateId || name;
            option.textContent = name;
            option.dataset.name = name;
            if (stateId) {
                option.dataset.stateId = stateId;
            }
            courierDistrict.appendChild(option);
        });

        const preferredState = createCourierDistricts.find((row) => {
            const name = row.name || row.district || '';
            return preferredDistrict && name === preferredDistrict;
        });
        if (preferredState) {
            const stateId = preferredState.id && Number(preferredState.id) > 0
                ? String(preferredState.id)
                : (preferredState.name || preferredState.district);
            courierDistrict.value = stateId;
            await loadCities(courierDistrict.value, preferredCityId, preferredCity);
        } else {
            courierCity.disabled = true;
            syncLocationHiddenFields();
        }
    }

    async function loadCities(stateIdOrDistrict, preferredCityId = null, preferredCity = null) {
        clearSelect(courierCity, 'Select city');
        createCourierCities = [];
        syncLocationHiddenFields();

        const courier = selectedCourier();
        if (!courier || !stateIdOrDistrict || !selectedMarketId || !lockedSupplierId) {
            courierCity.disabled = true;
            return;
        }

        courierCity.disabled = false;
        const params = {
            market_id: selectedMarketId,
            supplier_id: lockedSupplierId,
            courier_id: courier.id,
        };
        const numericStateId = Number(stateIdOrDistrict);
        if (Number.isInteger(numericStateId) && numericStateId > 0) {
            params.courier_state_id = numericStateId;
        } else {
            params.district = stateIdOrDistrict;
        }

        createCourierCities = await fetchCatalog(pageBootstrap.routes.courierCities, params);

        createCourierCities.forEach((city) => {
            const option = document.createElement('option');
            option.value = String(city.id);
            option.textContent = city.city_name;
            option.dataset.name = city.city_name;
            if (city.courier_state_id) {
                option.dataset.stateId = String(city.courier_state_id);
            }
            courierCity.appendChild(option);
        });

        const preferredById = preferredCityId
            && createCourierCities.some((c) => String(c.id) === String(preferredCityId));
        if (preferredById) {
            courierCity.value = String(preferredCityId);
        } else if (preferredCity && createCourierCities.some((c) => c.city_name === preferredCity)) {
            const match = createCourierCities.find((c) => c.city_name === preferredCity);
            courierCity.value = String(match.id);
        }

        syncLocationHiddenFields();
        syncAssignCourierButton();
    }

    async function refreshCourierFeePreview() {
        const courier = selectedCourier();
        if (!courier || !selectedMarketId || !lockedSupplierId || !pageBootstrap.routes.courierFeePreview) {
            courierFee = 0;
            recalc();
            return;
        }

        try {
            const preview = await fetchCatalog(pageBootstrap.routes.courierFeePreview, {
                market_id: selectedMarketId,
                supplier_id: lockedSupplierId,
                courier_id: courier.id,
                total_weight: liveTotalWeight,
                items_subtotal: liveItemsSubtotal,
                discount_amount: Number(discountInput.value || 0),
            });
            courierFee = Number(preview.courier_fee_amount || 0);
        } catch (e) {
            courierFee = 0;
        }

        recalc();
    }

    function refreshCustomerRiskFromLookup(lookup) {
        customerRiskPanel.classList.add('hidden');
        customerRiskPanel.innerHTML = '';
        customerExtraPanel.classList.add('hidden');
        customerExtraPanel.innerHTML = '';

        const messages = [];

        if (lookup?.is_banned) {
            const detailParts = [];
            if (lookup.display_name) detailParts.push(lookup.display_name);
            if (lookup.ban_reason) detailParts.push(lookup.ban_reason);
            messages.push({
                title: 'Warning: this customer is banned',
                detail: detailParts.length
                    ? detailParts.join(' · ')
                    : 'Primary phone matches a globally banned customer.',
            });
        }

        if (messages.length) {
            customerRiskPanel.classList.remove('hidden');
            customerRiskPanel.innerHTML = messages.map((message) => `
                <div>
                    <span class="banned-warning-title">${escapeHtml(message.title)}</span>
                    <span class="banned-warning-detail">${escapeHtml(message.detail)}</span>
                </div>
            `).join('');
        }

        const extraAlerts = [];

        if (lookup?.phone_conflict) {
            extraAlerts.push(`
                <div class="alert alert-danger mb-2" role="alert">
                    ${escapeHtml(lookup.phone_conflict.message || 'Primary and secondary phones belong to different customers.')}
                </div>
            `);
        }

        if (lookup?.crib) {
            const crib = lookup.crib;
            extraAlerts.push(`
                <div class="alert alert-warning mb-0" role="alert">
                    <strong>CRIB risk:</strong>
                    ${escapeHtml(crib.risk_level || '')}
                    ${crib.risk_code ? ' (' + escapeHtml(crib.risk_code) + ')' : ''}
                    ${crib.risk_summary ? ' — ' + escapeHtml(crib.risk_summary) : ''}
                </div>
            `);
        }

        if (extraAlerts.length) {
            customerExtraPanel.classList.remove('hidden');
            customerExtraPanel.innerHTML = extraAlerts.join('');
        }
    }

    async function lookupCustomerByPhone() {
        const market = selectedMarket();
        const phone = primaryPhone.value.trim();

        if (!phone || !market) {
            lastLookupResult = null;
            renderOrderHistory(null);
            refreshCustomerRiskFromLookup(null);
            return;
        }

        try {
            const lookup = await fetchCatalog(pageBootstrap.routes.customerLookup, {
                phone,
                country_id: market.country_id,
                market_id: market.id,
                secondary_phone: secondaryPhone.value.trim() || undefined,
            });

            lastLookupResult = lookup;
            renderOrderHistory(lookup);
            refreshCustomerRiskFromLookup(lookup);

            if (lookup.found && lookup.display_name && !lookup.is_banned && !customerName.value.trim()) {
                customerName.value = lookup.display_name;
            }

            if (timelineReady && normalizePhone(phone).length >= 9) {
                pushTimelineEvent({
                    key: 'customer-lookup',
                    type: 'customer',
                    title: lookup.is_banned ? 'Banned customer matched' : (lookup.found ? 'Customer identified' : 'Customer not found'),
                    description: lookup.found
                        ? ((lookup.display_name || 'Customer') + ' matched by phone number')
                        : 'No customer matched this phone number',
                });
            }
        } catch (error) {
            lastLookupResult = null;
            renderOrderHistory(null);
            refreshCustomerRiskFromLookup(null);
        }
    }

    function variantOptionLabel(variant) {
        if (variant.price_locked) {
            return variant.name + ' — ' + money(variant.customer_unit_price ?? variant.selling_price);
        }

        const min = variant.suggested_price_min;
        const max = variant.suggested_price_max;
        if (min !== null && min !== undefined && max !== null && max !== undefined) {
            return variant.name + ' — ' + money(min) + ' – ' + money(max);
        }

        return variant.name;
    }

    function applyVariantPricing(row, option, preferredSelectedPrice) {
        const priceInput = row.querySelector('.line-price');
        const selectedPriceInput = row.querySelector('.line-selected-price');
        const selectedPriceWrap = row.querySelector('.line-selected-price-wrap');
        const sellingPriceEl = row.querySelector('.line-selling-price');
        const customerPriceEl = row.querySelector('.line-customer-price');
        const profitEl = row.querySelector('.line-profit');

        if (!option || !option.value) {
            if (priceInput) priceInput.value = '';
            if (selectedPriceInput) {
                selectedPriceInput.value = '';
                selectedPriceInput.removeAttribute('name');
                selectedPriceInput.removeAttribute('min');
                selectedPriceInput.removeAttribute('max');
                selectedPriceInput.disabled = true;
            }
            if (selectedPriceWrap) selectedPriceWrap.classList.add('hidden');
            if (sellingPriceEl) sellingPriceEl.textContent = '—';
            if (customerPriceEl) customerPriceEl.textContent = money(0);
            if (profitEl) profitEl.textContent = money(0);
            return;
        }

        const priceLocked = option.dataset.priceLocked === '1';
        const sellingPrice = option.dataset.sellingPrice !== undefined && option.dataset.sellingPrice !== ''
            ? Number(option.dataset.sellingPrice)
            : null;
        const min = option.dataset.suggestedPriceMin !== undefined && option.dataset.suggestedPriceMin !== ''
            ? Number(option.dataset.suggestedPriceMin)
            : null;
        const max = option.dataset.suggestedPriceMax !== undefined && option.dataset.suggestedPriceMax !== ''
            ? Number(option.dataset.suggestedPriceMax)
            : null;
        const defaultCustomer = option.dataset.customerUnitPrice !== undefined && option.dataset.customerUnitPrice !== ''
            ? Number(option.dataset.customerUnitPrice)
            : (priceLocked ? sellingPrice : min);

        if (priceLocked) {
            if (sellingPriceEl) sellingPriceEl.textContent = money(sellingPrice);
            if (selectedPriceWrap) selectedPriceWrap.classList.add('hidden');
            if (selectedPriceInput) {
                selectedPriceInput.value = '';
                selectedPriceInput.removeAttribute('name');
                selectedPriceInput.removeAttribute('min');
                selectedPriceInput.removeAttribute('max');
                selectedPriceInput.disabled = true;
            }
            if (priceInput) priceInput.value = defaultCustomer != null ? String(defaultCustomer) : '';
        } else {
            if (sellingPriceEl) {
                sellingPriceEl.textContent = (min != null && max != null)
                    ? (money(min) + ' – ' + money(max))
                    : '—';
            }
            if (selectedPriceWrap) selectedPriceWrap.classList.remove('hidden');
            if (selectedPriceInput) {
                const index = row.dataset.index;
                selectedPriceInput.disabled = false;
                selectedPriceInput.name = 'items[' + index + '][selected_selling_price]';
                if (min != null) selectedPriceInput.min = String(min);
                if (max != null) selectedPriceInput.max = String(max);
                const preferred = preferredSelectedPrice != null && preferredSelectedPrice !== ''
                    ? Number(preferredSelectedPrice)
                    : defaultCustomer;
                selectedPriceInput.value = preferred != null ? Number(preferred).toFixed(2) : '';
            }
            if (priceInput) {
                priceInput.value = selectedPriceInput?.value || '';
            }
        }

        syncLinePricingDisplay(row);
    }

    function syncLinePricingDisplay(row) {
        const priceInput = row.querySelector('.line-price');
        const selectedPriceInput = row.querySelector('.line-selected-price');
        const variantSelect = row.querySelector('.line-variant');
        const option = variantSelect?.options[variantSelect.selectedIndex];
        const customerPriceEl = row.querySelector('.line-customer-price');
        const profitEl = row.querySelector('.line-profit');

        if (selectedPriceInput && !selectedPriceInput.disabled && selectedPriceInput.value !== '') {
            if (priceInput) priceInput.value = selectedPriceInput.value;
        }

        const customerPrice = Number(priceInput?.value || 0);
        const cost = Number(option?.dataset?.cost || 0);
        const commission = Number(option?.dataset?.companyCommission || 0);
        const profit = customerPrice - cost - commission;

        if (customerPriceEl) customerPriceEl.textContent = money(customerPrice);
        if (profitEl) profitEl.textContent = money(profit);
    }

    async function fillVariants(select, productId, preferredId, priceInput, weightInput, preferredSelectedPrice) {
        clearSelect(select, 'Select variant');
        priceInput.value = '';
        weightInput.value = '';
        const clearRow = select.closest('.order-line-row');
        applyVariantPricing(clearRow, null);

        if (!productId || !selectedMarketId || !lockedSupplierId) {
            recalc();
            return;
        }

        try {
            const variants = await fetchCatalog(pageBootstrap.routes.variants, {
                market_id: selectedMarketId,
                supplier_id: lockedSupplierId,
                product_id: productId,
            });

            variants.forEach((variant) => {
                const option = document.createElement('option');
                option.value = variant.id;
                option.textContent = variantOptionLabel(variant);
                option.dataset.priceLocked = variant.price_locked ? '1' : '0';
                option.dataset.cost = variant.cost ?? '0';
                option.dataset.companyCommission = variant.company_commission ?? '0';
                option.dataset.sellingPrice = variant.selling_price ?? '';
                option.dataset.suggestedPriceMin = variant.suggested_price_min ?? '';
                option.dataset.suggestedPriceMax = variant.suggested_price_max ?? '';
                option.dataset.customerUnitPrice = variant.customer_unit_price ?? '';
                option.dataset.weight = variant.weight;
                if (preferredId && String(preferredId) === String(variant.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (!preferredId && select.options.length > 1) {
                select.selectedIndex = 1;
            }

            const selected = select.options[select.selectedIndex];
            if (selected && selected.value) {
                weightInput.value = selected.dataset.weight || '';
                const row = select.closest('.order-line-row');
                applyVariantPricing(row, selected, preferredSelectedPrice);
            }
        } catch (error) {
            // Leave variant select empty when catalog lookup fails.
        }

        recalc();
        scheduleDuplicateCheck();
    }

    function renderDuplicateList(orders) {
        return orders.map((order) => {
            const items = (order.items || [])
                .map((item) => `${item.product} / ${item.variant} × ${item.quantity}`)
                .join('; ');

            return `
                <div class="border rounded p-2 mb-2 fs-14">
                    <strong>${escapeHtml(order.order_number || ('Order #' + order.id))}</strong>
                    <span class="badge badge-status ms-2 ${statusBadgeClass(order.status)}">${escapeHtml(order.status)}</span>
                    <div class="text-body fs-13 mt-1">${escapeHtml(order.created_at || '')}</div>
                    <div class="text-body fs-13">${escapeHtml(items)}</div>
                </div>
            `;
        }).join('');
    }

    function showDuplicatePanel(orders) {
        duplicatePanel.classList.remove('hidden');
        duplicateList.innerHTML = renderDuplicateList(orders);
        confirmDuplicateOverride.checked = false;
        duplicateWarningOverriddenInput.value = '0';
    }

    function hideDuplicatePanel() {
        duplicatePanel.classList.add('hidden');
        duplicateList.innerHTML = '';
        confirmDuplicateOverride.checked = false;
        duplicateWarningOverriddenInput.value = '0';
    }

    async function scheduleDuplicateCheck() {
        const market = selectedMarket();
        const phone = primaryPhone.value.trim();
        const variantIds = collectSelectedVariantIds();

        if (!phone || !market || !variantIds.length) {
            hideDuplicatePanel();
            return;
        }

        const token = ++duplicateCheckToken;

        try {
            const duplicates = await fetchCatalog(pageBootstrap.routes.duplicates, {
                phone,
                country_id: market.country_id,
                variant_ids: variantIds,
            });

            if (token !== duplicateCheckToken) {
                return;
            }

            if (duplicates.length) {
                showDuplicatePanel(duplicates);
            } else {
                hideDuplicatePanel();
            }
        } catch (error) {
            // Keep duplicate panel state unchanged on transient lookup failures.
        }
    }

    async function renderProductResults(row, resultsEl, query) {
        resultsEl.innerHTML = '';
        resultsEl.classList.remove('hidden');

        if (!selectedMarketId) {
            resultsEl.innerHTML = '<div class="product-search-empty">Select a market first.</div>';
            return;
        }

        if (!marketSuppliers.length && !lockedSupplierId) {
            resultsEl.innerHTML = '<div class="product-search-empty">No suppliers available for this market.</div>';
            return;
        }

        resultsEl.innerHTML = '<div class="product-search-empty">Searching...</div>';

        try {
            const products = await searchProducts(query);
            resultsEl.innerHTML = '';

            if (!products.length) {
                resultsEl.innerHTML = '<div class="product-search-empty">No products found.</div>';
                return;
            }

            products.forEach((product) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'product-search-option';
                button.textContent = product.name;
                button.dataset.productId = String(product.id);
                button.dataset.supplierId = String(product.supplier_id);
                button.addEventListener('click', () => {
                    selectProductForRow(row, product);
                    resultsEl.classList.add('hidden');
                });
                resultsEl.appendChild(button);
            });
        } catch (error) {
            resultsEl.innerHTML = '<div class="product-search-empty">Product search failed.</div>';
        }
    }

    function selectProductForRow(row, product) {
        const searchInput = row.querySelector('.line-product-search');
        const productIdInput = row.querySelector('.line-product-id');
        const variantSelect = row.querySelector('.line-variant');
        const priceInput = row.querySelector('.line-price');
        const weightInput = row.querySelector('.line-weight');

        if (product.supplier_id) {
            setLockedSupplier(product.supplier_id);
        }

        searchInput.value = product.name;
        productIdInput.value = product.id;
        fillVariants(variantSelect, product.id, null, priceInput, weightInput);

        const qty = Number(row.querySelector('.line-qty')?.value || 1);
        pushTimelineEvent({
            type: 'product',
            title: 'Product added',
            description: product.name + ' × ' + qty,
        });
    }

    function addLine(preset) {
        if (!selectedMarketId) {
            return;
        }

        const index = lineIndex++;
        const row = document.createElement('div');
        row.className = 'order-line-row';
        row.dataset.index = String(index);
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md">
                    <label class="line-field-label">Product</label>
                    <div class="product-search-wrap">
                        <input type="text" class="form-control line-control-height line-product-search" placeholder="Search product..."
                            autocomplete="off" name="items_ui_${index}_product_search">
                        <input type="hidden" class="line-product-id" name="items_ui_${index}_product" value="">
                        <div class="product-search-results hidden" role="listbox"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3">
                    <label class="line-field-label">Variant</label>
                    <select class="form-select form-control line-control-height line-variant" name="items[${index}][product_variant_id]">
                        <option value="">Select variant</option>
                    </select>
                </div>
                <div class="col-3 col-md-2 col-lg-1">
                    <label class="line-field-label">Qty</label>
                    <input type="number" min="1" class="form-control line-control-height line-qty" name="items[${index}][quantity]" value="${preset?.quantity || 1}">
                </div>
                <div class="col-3 col-md-2 col-lg-auto">
                    <label class="line-field-label">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-light border remove-line-btn line-control-height">Remove</button>
                    <input type="hidden" class="line-price" value="">
                    <input type="hidden" class="line-weight" value="">
                </div>
            </div>
            <div class="line-meta">
                <span class="line-meta-item">Selling Price: <strong class="line-selling-price">—</strong></span>
                <span class="line-meta-item line-selected-price-wrap hidden">Your Selling Price:
                    <input type="number" step="0.01" min="0" class="form-control line-selected-price" value="" disabled>
                </span>
                <span class="line-meta-item">Customer Price: <strong class="line-customer-price">0.00</strong></span>
                <span class="line-meta-item">Profit: <strong class="line-profit">0.00</strong></span>
                <span class="line-meta-item">Line total: <strong class="line-total">0.00</strong></span>
            </div>
        `;

        linesContainer.appendChild(row);

        const searchInput = row.querySelector('.line-product-search');
        const resultsEl = row.querySelector('.product-search-results');
        const productIdInput = row.querySelector('.line-product-id');
        const variantSelect = row.querySelector('.line-variant');
        const qtyInput = row.querySelector('.line-qty');
        const priceInput = row.querySelector('.line-price');
        const weightInput = row.querySelector('.line-weight');
        const selectedPriceInput = row.querySelector('.line-selected-price');

        let searchTimer = null;

        searchInput.addEventListener('focus', () => {
            renderProductResults(row, resultsEl, searchInput.value);
        });

        searchInput.addEventListener('input', () => {
            productIdInput.value = '';
            clearSelect(variantSelect, 'Select variant');
            priceInput.value = '';
            weightInput.value = '';
            applyVariantPricing(row, null);
            recalc();

            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                renderProductResults(row, resultsEl, searchInput.value);
            }, 180);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                resultsEl.classList.add('hidden');
            }
        });

        variantSelect.addEventListener('change', () => {
            const selected = variantSelect.options[variantSelect.selectedIndex];
            weightInput.value = selected?.dataset?.weight || '';
            applyVariantPricing(row, selected && selected.value ? selected : null);
            recalc();
            scheduleDuplicateCheck();
        });

        selectedPriceInput.addEventListener('input', () => {
            const min = selectedPriceInput.min !== '' ? Number(selectedPriceInput.min) : null;
            const max = selectedPriceInput.max !== '' ? Number(selectedPriceInput.max) : null;
            let value = Number(selectedPriceInput.value);
            if (selectedPriceInput.value !== '' && !Number.isNaN(value)) {
                if (min != null && value < min) {
                    selectedPriceInput.setCustomValidity('Selected selling price must be at least ' + min.toFixed(2));
                } else if (max != null && value > max) {
                    selectedPriceInput.setCustomValidity('Selected selling price must be at most ' + max.toFixed(2));
                } else {
                    selectedPriceInput.setCustomValidity('');
                }
            } else {
                selectedPriceInput.setCustomValidity('');
            }
            recalc();
        });

        qtyInput.addEventListener('input', recalc);

        row.querySelector('.remove-line-btn').addEventListener('click', () => {
            const productName = row.querySelector('.line-product-search')?.value || 'Line item';
            row.remove();
            recalc();
            pushTimelineEvent({
                type: 'product',
                title: 'Product line removed',
                description: productName,
            });
        });

        if (preset?.product_id) {
            productIdInput.value = preset.product_id;
            if (preset.product_name) {
                searchInput.value = preset.product_name;
            }
            fillVariants(
                variantSelect,
                preset.product_id,
                preset?.product_variant_id || null,
                priceInput,
                weightInput,
                preset?.selected_selling_price ?? null
            );
        }

        if (timelineReady && !preset?.silent) {
            pushTimelineEvent({
                type: 'product',
                title: 'Product line added',
                description: 'New empty order line ready for product selection',
            });
        }
    }

    function duplicatePanelVisible() {
        return !duplicatePanel.classList.contains('hidden');
    }

    function prepareSubmit(intent, assignmentTarget, assignCcaId) {
        orderIntentInput.value = intent;
        assignmentTargetInput.value = assignmentTarget || '';
        assignCcaIdInput.value = assignCcaId || '';

        if (duplicatePanelVisible() && !confirmDuplicateOverride.checked) {
            window.alert('Please review the duplicate orders and confirm Continue before submitting.');
            return false;
        }

        if (!supplierIdInput.value) {
            window.alert('Please select at least one product to determine the supplier.');
            return false;
        }

        if (duplicatePanelVisible() && confirmDuplicateOverride.checked) {
            duplicateWarningOverriddenInput.value = '1';
        }

        return true;
    }

    function submitForm() {
        syncLocationHiddenFields();
        form.submit();
    }

    function firstValidationError(errors) {
        if (!errors || typeof errors !== 'object') {
            return null;
        }
        const keys = Object.keys(errors);
        if (!keys.length) {
            return null;
        }
        const first = errors[keys[0]];
        if (Array.isArray(first) && first.length) {
            return String(first[0]);
        }
        return String(first || '');
    }

    function showAssignCourierError(message) {
        if (!assignCourierError) return;
        if (!message) {
            assignCourierError.classList.add('hidden');
            assignCourierError.textContent = '';
            return;
        }
        assignCourierError.textContent = message;
        assignCourierError.classList.remove('hidden');
    }

    function formatAssignCourierDebug(value) {
        if (value == null || value === '') {
            return '(empty)';
        }
        if (typeof value === 'string') {
            return value;
        }
        try {
            return JSON.stringify(value, null, 2);
        } catch (e) {
            return String(value);
        }
    }

    function showAssignCourierDebug(payload) {
        if (!assignCourierDebug) return;
        const debug = payload && payload.debug ? payload.debug : null;
        if (!debug) {
            assignCourierDebug.classList.add('hidden');
            if (assignCourierDebugTitle) assignCourierDebugTitle.textContent = '';
            if (assignCourierDebugMeta) assignCourierDebugMeta.textContent = '';
            if (assignCourierDebugResponse) assignCourierDebugResponse.textContent = '';
            if (assignCourierDebugRequest) assignCourierDebugRequest.textContent = '';
            return;
        }

        const courierName = (payload.courier && payload.courier.name) || 'Courier';
        const status = debug.http_status;
        const failureLabels = {
            http_error: 'HTTP error',
            connection: 'Connection failure',
            invalid_json: 'Invalid JSON response',
            application_rejection: 'Provider rejected the booking',
            authentication: 'Authentication failure',
        };
        const meta = [];
        if (status !== null && status !== undefined && status !== '') {
            meta.push('HTTP Status: ' + status);
        }
        if (debug.failure_type && failureLabels[debug.failure_type]) {
            meta.push(failureLabels[debug.failure_type]);
        }
        if (debug.exception && debug.exception.message) {
            meta.push(debug.exception.message);
        }
        if (debug.exception && debug.exception.class) {
            meta.push(debug.exception.class);
        }

        if (assignCourierDebugTitle) {
            assignCourierDebugTitle.textContent = courierName + ' Booking Failed';
        }
        if (assignCourierDebugMeta) {
            assignCourierDebugMeta.textContent = meta.join(' · ');
        }
        if (assignCourierDebugResponse) {
            assignCourierDebugResponse.textContent = formatAssignCourierDebug(debug.response);
        }
        if (assignCourierDebugRequest) {
            assignCourierDebugRequest.textContent = formatAssignCourierDebug(debug.request);
        }
        assignCourierDebug.classList.remove('hidden');
    }

    function applyShipmentBootstrap(shipment) {
        pageBootstrap.shipment = shipment || null;

        if (assignedCourierPanel && assignedCourierName && assignedCourierWaybill) {
            if (shipment && shipment.waybill) {
                assignedCourierName.textContent = shipment.courier?.name || 'Courier assigned';
                assignedCourierWaybill.textContent = 'Waybill: ' + shipment.waybill;
                assignedCourierPanel.classList.remove('hidden');
            } else {
                assignedCourierName.textContent = '';
                assignedCourierWaybill.textContent = '';
                assignedCourierPanel.classList.add('hidden');
            }
        }

        const booked = !!(shipment && shipment.waybill);
        if (booked) {
            courierSelect.disabled = true;
            courierDistrict.disabled = true;
            courierCity.disabled = true;
            if (assignCourierHint) {
                assignCourierHint.textContent = 'Courier already assigned. Waybill is locked after successful booking.';
            }
        }

        syncAssignCourierButton();
        updateOrderReview();
    }

    function syncAssignCourierButton() {
        if (!assignCourierBtn || !pageBootstrap.canAssignCourier) {
            return;
        }

        const booked = !!(pageBootstrap.shipment && pageBootstrap.shipment.waybill);
        const ready = !!(
            selectedCourier()
            && courierDistrict.value
            && courierCity.value
            && selectedCourierCityId()
            && supplierIdInput.value
            && selectedMarketId
        );

        assignCourierBtn.disabled = booked || !ready || assignCourierBtn.dataset.busy === '1';
        if (booked) {
            assignCourierBtn.textContent = 'Courier Assigned';
        } else if (assignCourierBtn.dataset.busy === '1') {
            assignCourierBtn.textContent = 'Assigning…';
        } else {
            assignCourierBtn.textContent = 'Assign Courier';
        }
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': pageBootstrap.csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.message || 'Request failed.');
            error.errors = payload.errors || null;
            error.payload = payload;
            throw error;
        }

        return payload;
    }

    function orderFormPayload(intent) {
        const items = [];
        linesContainer.querySelectorAll('.order-line-row').forEach((row) => {
            const variantId = row.querySelector('.line-variant')?.value;
            const quantity = row.querySelector('.line-qty')?.value;
            const selectedPrice = row.querySelector('.line-selected-price')?.value;
            if (!variantId) return;
            const item = {
                product_variant_id: Number(variantId),
                quantity: Number(quantity || 1),
            };
            if (selectedPrice !== '' && selectedPrice != null) {
                item.selected_selling_price = Number(selectedPrice);
            }
            items.push(item);
        });

        return {
            intent: intent || 'confirm',
            market_id: Number(selectedMarketId),
            supplier_id: Number(supplierIdInput.value),
            customer_name: customerName.value,
            primary_phone: primaryPhone.value,
            secondary_phone: secondaryPhone.value || null,
            address_line1: addressLine1.value,
            address_line2: '',
            city_name: selectedCityName() || null,
            district_name: selectedDistrictName() || null,
            full_address_text: (fullAddressText && fullAddressText.value) || addressLine1.value,
            discount_amount: Number(discountInput.value || 0),
            courier_id: courierSelect.value ? Number(courierSelect.value) : null,
            courier_city_id: selectedCourierCityId(),
            duplicate_warning_overridden: duplicateWarningOverriddenInput.value === '1',
            after_hours_warning_shown: afterHoursWarningShownInput.value === '1',
            assignment_target: assignmentTargetInput.value || 'unassigned',
            assign_cca_id: assignCcaIdInput.value ? Number(assignCcaIdInput.value) : null,
            items,
        };
    }

    async function persistOrderBeforeBooking() {
        if (isEditMode) {
            if (!prepareEditSubmit()) {
                throw new Error('Please fix order details before assigning a courier.');
            }
            const updateUrl = pageBootstrap.routes.update;
            if (!updateUrl) {
                throw new Error('Order update route is not available.');
            }
            return postJson(updateUrl, orderFormPayload(''));
        }

        if (!prepareSubmit('confirm', 'unassigned', '')) {
            throw new Error('Please complete required order details before assigning a courier.');
        }

        const storeUrl = pageBootstrap.routes.store;
        if (!storeUrl) {
            throw new Error('Order create route is not available.');
        }

        return postJson(storeUrl, orderFormPayload('confirm'));
    }

    async function assignCourier() {
        if (!pageBootstrap.canAssignCourier || !assignCourierBtn) {
            return;
        }

        showAssignCourierError('');
        showAssignCourierDebug(null);

        if (pageBootstrap.shipment && pageBootstrap.shipment.waybill) {
            showAssignCourierError('Courier already assigned for this order.');
            syncAssignCourierButton();
            return;
        }

        const courier = selectedCourier();
        const cityId = selectedCourierCityId();

        if (!courier || !courierDistrict.value || !courierCity.value || !cityId) {
            showAssignCourierError('Select courier, state/district, and city before assigning.');
            syncAssignCourierButton();
            return;
        }

        assignCourierBtn.dataset.busy = '1';
        syncAssignCourierButton();

        try {
            const saved = await persistOrderBeforeBooking();
            const bookUrl = saved?.data?.book_url || pageBootstrap.routes.bookShipment;
            if (!bookUrl) {
                throw new Error('Shipment booking route is not available.');
            }

            const booked = await postJson(bookUrl, {
                courier_id: Number(courier.id),
                courier_city_id: Number(cityId),
            });

            const shipment = booked?.data || null;
            applyShipmentBootstrap(shipment);

            pushTimelineEvent({
                key: 'courier-assigned',
                type: 'courier',
                title: 'Courier assigned',
                description: (shipment?.courier?.name || courier.name)
                    + (shipment?.waybill ? (' · ' + shipment.waybill) : ''),
            });

            if (!isEditMode && saved?.data?.show_url) {
                window.location.href = saved.data.show_url;
                return;
            }
        } catch (err) {
            const message = firstValidationError(err.errors)
                || (err.payload && err.payload.message)
                || err.message
                || 'Courier assignment failed.';
            showAssignCourierError(message);
            showAssignCourierDebug(err.payload || null);

            if (err.payload && Array.isArray(err.payload.duplicate_orders) && err.payload.duplicate_orders.length) {
                showDuplicatePanel(err.payload.duplicate_orders);
            }
        } finally {
            if (assignCourierBtn) {
                assignCourierBtn.dataset.busy = '0';
            }
            syncAssignCourierButton();
        }
    }

    function openCallCenterAssignModal() {
        modalAssignCcaSelect.innerHTML = '';
        pageBootstrap.eligibleCcas.forEach((cca) => {
            const option = document.createElement('option');
            option.value = String(cca.id);
            option.textContent = cca.name;
            modalAssignCcaSelect.appendChild(option);
        });

        document.getElementById('assignTargetUnassigned').checked = true;
        modalAssignCcaSelect.disabled = true;
        callCenterAssignModal.classList.remove('hidden');
    }

    function closeCallCenterModal() {
        callCenterAssignModal.classList.add('hidden');
    }

    document.querySelectorAll('input[name="modalAssignmentTarget"]').forEach((input) => {
        input.addEventListener('change', () => {
            modalAssignCcaSelect.disabled = input.value !== 'cca';
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.product-search-wrap')) {
            linesContainer.querySelectorAll('.product-search-results').forEach((el) => {
                el.classList.add('hidden');
            });
        }
    });

    courierSelect.addEventListener('change', async () => {
        const courier = selectedCourier();
        courierFee = 0;

        if (courier) {
            await loadDistrictsForCourier();
            await refreshCourierFeePreview();
        } else {
            resetCourierLocations();
            recalc();
        }

        syncAssignCourierButton();

        pushTimelineEvent({
            key: 'courier',
            type: 'courier',
            title: courier ? 'Courier draft selected' : 'Courier draft cleared',
            description: courier
                ? (courier.name + (pageBootstrap.shipment?.waybill ? '' : ' — select district/city to assign'))
                : 'No courier selected',
        });
    });

    courierDistrict.addEventListener('change', async () => {
        await loadCities(courierDistrict.value);
        syncLocationHiddenFields();
        updateOrderReview();
        syncAssignCourierButton();
        if (courierDistrict.value) {
            pushTimelineEvent({
                key: 'district',
                type: 'courier',
                title: 'District selected',
                description: selectedDistrictName(),
            });
        }
    });

    courierCity.addEventListener('change', () => {
        syncLocationHiddenFields();
        updateOrderReview();
        syncAssignCourierButton();
        if (courierCity.value) {
            pushTimelineEvent({
                key: 'city',
                type: 'courier',
                title: 'City selected',
                description: selectedCityName(),
            });
        }
    });

    if (assignCourierBtn) {
        assignCourierBtn.addEventListener('click', () => {
            assignCourier();
        });
    }

    addLineBtn.addEventListener('click', () => addLine());
    discountInput.addEventListener('input', () => {
        recalc();
        if (selectedCourier()) {
            refreshCourierFeePreview();
        }
        window.clearTimeout(discountTimelineTimer);
        discountTimelineTimer = window.setTimeout(() => {
            pushTimelineEvent({
                key: 'discount',
                type: 'discount',
                title: 'Discount updated',
                description: money(discountInput.value || 0),
            });
        }, 400);
    });

    let phoneTimer = null;
    primaryPhone.addEventListener('input', () => {
        window.clearTimeout(phoneTimer);
        phoneTimer = window.setTimeout(() => {
            lookupCustomerByPhone();
            scheduleDuplicateCheck();
            updateOrderReview();
        }, 350);
        updateOrderReview();
    });
    primaryPhone.addEventListener('blur', () => {
        lookupCustomerByPhone();
        scheduleDuplicateCheck();
    });
    secondaryPhone.addEventListener('input', () => {
        window.clearTimeout(phoneTimer);
        phoneTimer = window.setTimeout(() => {
            lookupCustomerByPhone();
            updateOrderReview();
        }, 350);
        updateOrderReview();
    });

    [customerName, addressLine1].forEach((el) => {
        el.addEventListener('input', () => {
            if (el === addressLine1 && fullAddressText) {
                fullAddressText.value = addressLine1.value;
            }
            updateOrderReview();
        });
    });

    confirmDuplicateOverride.addEventListener('change', () => {
        duplicateWarningOverriddenInput.value = confirmDuplicateOverride.checked ? '1' : '0';
    });

    form.addEventListener('submit', (event) => {
        if (!orderIntentInput.value) {
            event.preventDefault();
            window.alert('Choose Confirm Order or Send to Call Center.');
        }
    });

    function prepareEditSubmit() {
        if (duplicatePanelVisible() && !confirmDuplicateOverride.checked) {
            window.alert('Please review the duplicate orders and confirm Continue before submitting.');
            return false;
        }

        if (!supplierIdInput.value) {
            window.alert('Please select at least one product to determine the supplier.');
            return false;
        }

        if (duplicatePanelVisible() && confirmDuplicateOverride.checked) {
            duplicateWarningOverriddenInput.value = '1';
        }

        return true;
    }

    if (saveOrderChangesBtn) {
        saveOrderChangesBtn.addEventListener('click', () => {
            if (!prepareEditSubmit()) return;
            orderIntentInput.value = '';
            assignmentTargetInput.value = '';
            assignCcaIdInput.value = '';
            submitForm();
        });
    }

    if (confirmOrderBtn) confirmOrderBtn.addEventListener('click', () => {
        if (!prepareSubmit('confirm', '', '')) {
            return;
        }
        submitForm();
    });

    if (sendToCallCenterBtn) sendToCallCenterBtn.addEventListener('click', () => {
        if (pageBootstrap.isCca) {
            if (!prepareSubmit('send_to_call_center', 'unassigned', '')) {
                return;
            }
            submitForm();
            return;
        }

        openCallCenterAssignModal();
    });

    if (closeCallCenterAssignModal) closeCallCenterAssignModal.addEventListener('click', closeCallCenterModal);
    if (confirmSendToCallCenterBtn) confirmSendToCallCenterBtn.addEventListener('click', () => {
        const selectedTarget = document.querySelector('input[name="modalAssignmentTarget"]:checked');
        const target = selectedTarget ? selectedTarget.value : 'unassigned';
        const assignCcaId = target === 'cca' ? modalAssignCcaSelect.value : '';

        if (target === 'cca' && !assignCcaId) {
            window.alert('Please select a CCA.');
            return;
        }

        if (!prepareSubmit('send_to_call_center', target, assignCcaId)) {
            return;
        }

        closeCallCenterModal();
        submitForm();
    });

    const openBanUserBtn = document.getElementById('openBanUserBtn');
    const banUserModal = document.getElementById('banUserModal');
    if (openBanUserBtn && banUserModal) {
        openBanUserBtn.addEventListener('click', () => {
            const nameInput = document.getElementById('banCustomerName');
            const phone1Input = document.getElementById('banPhoneOne');
            const phone2Input = document.getElementById('banPhoneTwo');
            const reasonInput = document.getElementById('banReason');
            const reasonError = document.getElementById('banReasonError');
            if (nameInput) nameInput.value = customerName.value;
            if (phone1Input) phone1Input.value = primaryPhone.value;
            if (phone2Input) phone2Input.value = secondaryPhone.value;
            if (reasonInput) reasonInput.value = '';
            if (reasonError) {
                reasonError.hidden = true;
                reasonError.textContent = '';
            }
            banUserModal.classList.remove('hidden');
        });
        banUserModal.addEventListener('click', (event) => {
            if (event.target === banUserModal) {
                banUserModal.classList.add('hidden');
            }
        });
        banUserModal.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => banUserModal.classList.add('hidden'));
        });
    }

    if (callCenterAssignModal) {
        callCenterAssignModal.addEventListener('click', (event) => {
            if (event.target === callCenterAssignModal) {
                closeCallCenterModal();
            }
        });
    }

    async function initPage() {
        timelineReady = false;
        timelineEvents = [];
        pushTimelineEvent({
            type: 'created',
            title: isEditMode ? 'Order opened for editing' : 'Order started',
            description: isEditMode ? 'Existing order loaded into the create form' : 'Manual order entry initiated',
        });

        if (pageBootstrap.lockDiscount && discountInput) {
            discountInput.readOnly = true;
            discountInput.title = 'Call center agents cannot edit order discounts.';
        }

        loadMarkets();
        resetCourierLocations();

        if (oldMarketId) {
            await setMarket(oldMarketId, { resetLines: false });
        } else if (pageBootstrap.markets.length === 1) {
            await setMarket(pageBootstrap.markets[0].id, { resetLines: false });
        }

        if (pageBootstrap.lockMarket && marketChecks) {
            marketChecks.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                input.disabled = true;
            });
        }

        if (pageBootstrap.oldItems.length) {
            pageBootstrap.oldItems.forEach((item) => {
                addLine({
                    product_id: item.product_id,
                    product_name: item.product_name || '',
                    product_variant_id: item.product_variant_id,
                    quantity: item.quantity,
                    selected_selling_price: item.selected_selling_price ?? null,
                    silent: true,
                });
            });
        } else if (selectedMarketId && !isEditMode) {
            addLine({ silent: true });
        }

        await loadCouriers({
            preferredCourierId: oldCourierId,
            preferredDistrict: oldDistrictName,
            preferredCityId: oldCourierCityId,
            preferredCity: oldCityName,
        });

        applyShipmentBootstrap(pageBootstrap.shipment);

        if (pageBootstrap.duplicateOrders.length) {
            showDuplicatePanel(pageBootstrap.duplicateOrders);
        }

        await lookupCustomerByPhone();
        recalc();
        updateOrderReview();

        document.querySelectorAll('[data-create-payment-method]').forEach((input) => {
            input.addEventListener('change', () => {
                const bankHint = document.getElementById('createBankTransferHint');
                const codHint = document.getElementById('createCodPaymentHint');
                const isBank = input.value === 'bank' && input.checked;
                if (bankHint) bankHint.classList.toggle('hidden', !isBank);
                if (codHint) codHint.classList.toggle('hidden', isBank);
            });
        });

        timelineReady = true;
    }

    initPage();
})();
</script>
@include('pages.orders.partials.ui-ban-scripts')
