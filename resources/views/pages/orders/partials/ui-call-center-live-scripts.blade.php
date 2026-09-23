    function initLiveCallCenterWorkspace(root, bootstrap) {
        var actor = bootstrap.actor || {};
        var filters = bootstrap.filters || {};
        var orders = bootstrap.orders || [];
        var selectedOrderIds = [];
        var activeTab = filters.tab || (actor.role === 'cca' ? 'assigned' : 'all');
        var statusFilter = filters.status !== '' && filters.status != null ? filters.status : 'all';
        var searchQuery = filters.search || '';
        var currentPage = (bootstrap.pagination && bootstrap.pagination.current_page) || 1;
        var productSearchTimer = null;
        var productSearchRequestId = 0;

        function isReseller() { return actor.role === 'reseller'; }
        function isCca() { return actor.role === 'cca'; }
        function canAssign() { return !!actor.can_assign; }
        function canTakeFromPool() { return !bootstrap.pool_lock; }

        function selectedProductId() {
            var productIdEl = document.getElementById('orderUiFilterProductId');
            return productIdEl && productIdEl.value ? String(productIdEl.value) : '';
        }

        function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        async function postJson(url, body) {
            var res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body || {}),
            });
            var payload = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                var msg = payload.message || (payload.errors ? Object.values(payload.errors).flat().join(' ') : 'Request failed');
                throw new Error(msg);
            }
            return payload;
        }

        function buildQuery(overrides) {
            overrides = overrides || {};
            var ccaEl = document.getElementById('orderUiFilterCca');
            var supplierEl = document.getElementById('orderUiFilterSupplier');
            var fromEl = document.getElementById('orderUiFilterDateFrom');
            var toEl = document.getElementById('orderUiFilterDateTo');
            var state = {
                workspace: 'call-center',
                search: overrides.search !== undefined ? overrides.search : searchQuery,
                status: overrides.status !== undefined ? overrides.status : (statusFilter === 'all' ? '' : statusFilter),
                tab: overrides.tab !== undefined ? overrides.tab : activeTab,
                cca_id: overrides.cca_id !== undefined ? overrides.cca_id : (ccaEl && ccaEl.value !== 'all' ? ccaEl.value : ''),
                supplier_id: overrides.supplier_id !== undefined ? overrides.supplier_id : (supplierEl && supplierEl.value !== 'all' ? supplierEl.value : ''),
                product_id: overrides.product_id !== undefined ? overrides.product_id : selectedProductId(),
                date_from: overrides.date_from !== undefined ? overrides.date_from : (fromEl ? fromEl.value : ''),
                date_to: overrides.date_to !== undefined ? overrides.date_to : (toEl ? toEl.value : ''),
                page: overrides.page !== undefined ? overrides.page : currentPage,
            };
            if (overrides.date_preset !== undefined && overrides.date_preset !== '') {
                state.date_preset = overrides.date_preset;
            }
            var params = new URLSearchParams();
            Object.keys(state).forEach(function (key) {
                var val = state[key];
                if (val !== '' && val !== null && val !== undefined) params.set(key, String(val));
            });
            return params.toString();
        }

        function indexPath() {
            var base = (bootstrap.routes && bootstrap.routes.index) ? bootstrap.routes.index : '/orders';
            // routes.index may already include ?workspace=call-center; strip it so we never append a second "?".
            return String(base).split('?')[0];
        }

        function navigate(overrides) {
            var qs = buildQuery(overrides || {});
            window.location.href = indexPath() + (qs ? '?' + qs : '');
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatMoney(amount, currency) {
            return (currency || 'LKR') + ' ' + Number(amount).toLocaleString('en-LK');
        }

        function formatDate(iso) {
            if (!iso) return '\u2014';
            return new Date(iso).toLocaleString('en-US', {
                month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true
            });
        }

        function statusCssClass(status) {
            return String(status || '').toLowerCase().replace(/_/g, '-');
        }

        function assignmentHtml(order) {
            if (order.assignment === 'pool') {
                return '<div class="assign-block"><span class="badge-assign is-pool">\u25CF Order Pool</span></div>';
            }
            if (order.assignment === 'unassigned') {
                return '<div class="assign-block"><span class="badge-assign is-unassigned">\u25CF Unassigned</span></div>';
            }
            if (order.isMine) {
                return '<div class="assign-block"><span class="badge-assign is-me">\u25CF Assigned to Me</span><div class="assign-role">CCA</div></div>';
            }
            return '<div class="assign-block"><span class="badge-assign is-other">\u25CF Assigned to CCA</span>' +
                '<div class="assign-name mt-1">' + escapeHtml(order.ccaName || '\u2014') + '</div><div class="assign-role">CCA</div></div>';
        }

        function tabDefs() {
            var c = bootstrap.counts || {};
            // Owners: All / Assigned / Unassigned / Order Pool.
            // CCAs: All / Assigned / Order Pool (no Unassigned). Default load filters Assigned to self.
            return [
                { id: 'all', label: 'All', count: c.all || 0 },
                { id: 'assigned', label: 'Assigned', count: c.assigned || 0 },
            ].concat(isReseller()
                ? [{ id: 'unassigned', label: 'Unassigned', count: c.unassigned || 0 }]
                : []
            ).concat([
                { id: 'pool', label: 'Order Pool', count: c.pool || 0 },
            ]);
        }

        function renderTabs() {
            var tabs = tabDefs();
            if (!tabs.some(function (t) { return t.id === activeTab; })) {
                activeTab = isCca() ? 'assigned' : 'all';
            }
            document.getElementById('orderUiWorkspaceTabs').innerHTML = tabs.map(function (tab) {
                return '<button type="button" class="workspace-tab ' + (activeTab === tab.id ? 'is-active' : '') + '" data-ui-tab="' + tab.id + '">' +
                    escapeHtml(tab.label) + '<span class="tab-count">' + tab.count + '</span></button>';
            }).join('');
        }

        function renderChips() {
            var statusCounts = bootstrap.status_counts || {};
            var chips = [{ value: 'all', label: 'All' }].concat(bootstrap.statuses || []);
            document.getElementById('orderUiStatusChips').innerHTML = chips.map(function (chip) {
                var value = chip.value;
                var active = statusFilter === value || (statusFilter === 'all' && value === 'all');
                var count = Number(statusCounts[value] != null ? statusCounts[value] : 0);
                return '<button type="button" class="filter-chip ' + (active ? 'is-active' : '') + '" data-ui-status="' + escapeHtml(value) + '">' +
                    escapeHtml(chip.label) + '<span class="tab-count">' + count + '</span></button>';
            }).join('');
        }

        function hideProductSuggestions() {
            var list = document.getElementById('orderUiProductSuggestions');
            var input = document.getElementById('orderUiFilterProduct');
            if (list) list.classList.add('hidden');
            if (input) input.setAttribute('aria-expanded', 'false');
        }

        function renderProductSuggestions(products) {
            var list = document.getElementById('orderUiProductSuggestions');
            var input = document.getElementById('orderUiFilterProduct');
            if (!list || !input) return;

            if (!products || !products.length) {
                hideProductSuggestions();
                return;
            }

            list.innerHTML = products.map(function (product) {
                return '<li role="option" tabindex="-1" data-ui-product-id="' + escapeHtml(product.id) + '"' +
                    ' data-ui-product-name="' + escapeHtml(product.name) + '">' +
                    '<span class="order-ui-suggest-name">' + escapeHtml(product.name) + '</span>' +
                    '<span class="order-ui-suggest-code">' + escapeHtml(product.supplier_name || '') + '</span></li>';
            }).join('');
            list.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }

        async function searchProducts(query) {
            var url = (bootstrap.routes && bootstrap.routes.filter_products)
                ? bootstrap.routes.filter_products
                : '/orders/filter-products';
            var params = new URLSearchParams();
            if (query) params.set('search', query);
            var requestId = ++productSearchRequestId;
            try {
                var res = await fetch(url + (params.toString() ? '?' + params.toString() : ''), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                var payload = await res.json().catch(function () { return {}; });
                if (requestId !== productSearchRequestId) return;
                if (!res.ok) {
                    hideProductSuggestions();
                    return;
                }
                renderProductSuggestions(payload.data || []);
            } catch (err) {
                if (requestId === productSearchRequestId) hideProductSuggestions();
            }
        }

        function scheduleProductSearch(query) {
            if (productSearchTimer) clearTimeout(productSearchTimer);
            productSearchTimer = setTimeout(function () {
                searchProducts(String(query || '').trim());
            }, 250);
        }

        function selectProduct(productId, productName) {
            var productIdEl = document.getElementById('orderUiFilterProductId');
            var productInput = document.getElementById('orderUiFilterProduct');
            if (productIdEl) productIdEl.value = String(productId);
            if (productInput) productInput.value = productName || '';
            hideProductSuggestions();
            selectedOrderIds = [];
            navigate({ product_id: String(productId), page: 1 });
        }

        function populateFilterSelects() {
            var ccaSelect = document.getElementById('orderUiFilterCca');
            var supplierSelect = document.getElementById('orderUiFilterSupplier');
            var bulkCcaSelect = document.getElementById('orderUiBulkAssignCcaSelect');
            var fromEl = document.getElementById('orderUiFilterDateFrom');
            var toEl = document.getElementById('orderUiFilterDateTo');
            var productIdEl = document.getElementById('orderUiFilterProductId');
            var productInput = document.getElementById('orderUiFilterProduct');
            var selectedProduct = bootstrap.selected_product || null;

            if (ccaSelect) {
                ccaSelect.innerHTML = '<option value="all">All CCAs</option>' + (bootstrap.ccas || []).map(function (cca) {
                    return '<option value="' + escapeHtml(cca.id) + '">' + escapeHtml(cca.name) + '</option>';
                }).join('');
                ccaSelect.value = filters.cca_id || 'all';
            }
            if (supplierSelect) {
                supplierSelect.innerHTML = '<option value="all">All suppliers</option>' + (bootstrap.suppliers || []).map(function (supplier) {
                    return '<option value="' + escapeHtml(supplier.id) + '">' + escapeHtml(supplier.name) + '</option>';
                }).join('');
                supplierSelect.value = filters.supplier_id || 'all';
            }
            if (bulkCcaSelect) {
                bulkCcaSelect.innerHTML = (bootstrap.ccas || []).map(function (cca) {
                    return '<option value="' + escapeHtml(cca.id) + '">' + escapeHtml(cca.name) + '</option>';
                }).join('');
            }
            if (productIdEl) {
                productIdEl.value = selectedProduct && selectedProduct.id
                    ? String(selectedProduct.id)
                    : (filters.product_id || '');
            }
            if (productInput) {
                productInput.value = selectedProduct && selectedProduct.name
                    ? selectedProduct.name
                    : '';
            }
            if (fromEl) fromEl.value = filters.date_from || '';
            if (toEl) toEl.value = filters.date_to || '';
        }

        function renderPagination() {
            var p = bootstrap.pagination || {};
            if (!p.total) return '';
            var from = (p.current_page - 1) * p.per_page + 1;
            var to = Math.min(p.current_page * p.per_page, p.total);
            return '<div class="orders-pagination">' +
                '<div class="orders-pagination-meta">Showing ' + from + '\u2013' + to + ' of ' + p.total + '</div>' +
                '<div class="orders-pagination-actions">' +
                '<button type="button" class="btn btn-sm btn-light border" data-ui-page-nav="prev"' + (p.current_page <= 1 ? ' disabled' : '') + '>Previous</button>' +
                '<span class="orders-pagination-meta">Page ' + p.current_page + ' of ' + p.last_page + '</span>' +
                '<button type="button" class="btn btn-sm btn-light border" data-ui-page-nav="next"' + (p.current_page >= p.last_page ? ' disabled' : '') + '>Next</button>' +
                '</div></div>';
        }

        function actionsCellHtml(order) {
            var viewBtn = '<a href="' + escapeHtml(order.showUrl) + '" class="btn btn-sm btn-light border">View</a>';
            if (isCca() && actor.can_claim && activeTab === 'pool' && order.assignment === 'pool') {
                var locked = !canTakeFromPool();
                return '<div class="d-flex flex-column gap-1 align-items-stretch">' +
                    '<button type="button" class="btn btn-primary btn-sm text-white" data-claim-order="' + order.id + '"' + (locked ? ' disabled' : '') + '>Assign to Me</button>' +
                    viewBtn + '</div>';
            }
            return viewBtn;
        }

        function renderPoolLockBanner() {
            var banner = document.getElementById('orderUiPoolLockBanner');
            if (!banner) return;
            var lock = bootstrap.pool_lock;
            if (!isCca() || !lock) {
                banner.classList.add('hidden');
                banner.innerHTML = '';
                return;
            }
            var showUrl = (bootstrap.routes.show || '/orders') + '/' + encodeURIComponent(lock.order_uuid);
            banner.classList.remove('hidden');
            banner.innerHTML =
                '<strong>Pool pickup locked</strong>' +
                'You already have an active pool claim (<span class="fw-semibold">' + escapeHtml(lock.order_number) + '</span>). ' +
                'Update that order status before claiming another pool order.' +
                '<div class="mt-2"><a href="' + escapeHtml(showUrl) + '" class="btn btn-sm btn-outline-warning">Open Order</a></div>';
        }

        function renderBulkToolbar() {
            var toolbar = document.getElementById('orderUiBulkToolbar');
            var countEl = document.getElementById('orderUiBulkSelectedCount');
            if (!toolbar || !countEl) return;
            if (!canAssign() || !selectedOrderIds.length) {
                toolbar.classList.add('hidden');
                return;
            }
            toolbar.classList.remove('hidden');
            countEl.textContent = String(selectedOrderIds.length);
        }

        function syncRowSelectionUi() {
            root.querySelectorAll('[data-ui-order-select]').forEach(function (input) {
                var id = input.getAttribute('data-ui-order-select');
                input.checked = selectedOrderIds.indexOf(String(id)) !== -1;
                var row = input.closest('tr');
                if (row) row.classList.toggle('is-selected', input.checked);
            });
            var selectAll = document.getElementById('orderUiSelectAll');
            if (selectAll) {
                var pageSelectable = root.querySelectorAll('[data-ui-order-select]:not(:disabled)');
                var checkedCount = 0;
                pageSelectable.forEach(function (input) { if (input.checked) checkedCount += 1; });
                selectAll.checked = pageSelectable.length > 0 && checkedCount === pageSelectable.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < pageSelectable.length;
            }
            renderBulkToolbar();
        }

        function renderTable() {
            var titleEl = document.getElementById('orderUiWorkspaceTitle');
            var metaEl = document.getElementById('orderUiResultsMeta');
            var userMeta = document.getElementById('orderUiUserContextMeta');
            var content = document.getElementById('orderUiWorkspaceContent');
            var titles = {
                all: 'All Orders', assigned: 'Assigned Orders', unassigned: 'Unassigned Orders',
                pool: 'Order Pool', my: 'My Orders', company: 'Company Orders',
            };
            var p = bootstrap.pagination || { total: 0 };
            titleEl.textContent = titles[activeTab] || 'Call Center Orders';
            metaEl.textContent = p.total + ' in this view Â· ' + ((bootstrap.counts && bootstrap.counts.all) || 0) + ' total in company';
            if (userMeta) {
                userMeta.textContent = (actor.name || '') + ' Â· ' + String(actor.role || '').toUpperCase() + ' Â· ' + (actor.company || '');
            }

            if (!orders.length) {
                var emptyCopy = activeTab === 'pool'
                    ? 'There are currently no orders waiting in the Order Pool.'
                    : (activeTab === 'assigned' && isCca()
                        ? 'Pick up an order from the Order Pool, or wait for reseller assignment.'
                        : 'There are no orders in this view.');
                content.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">shopping_cart</span><h4>No orders found</h4><p>' + emptyCopy + '</p></div>';
                renderBulkToolbar();
                return;
            }

            var selectHeader = canAssign()
                ? '<th class="order-ui-select-col"><input type="checkbox" id="orderUiSelectAll" class="form-check-input" aria-label="Select all on page"></th>'
                : '';

                content.innerHTML = '<div class="table-responsive"><table class="orders-table align-middle"><thead><tr>' +
                selectHeader +
                '<th>Order</th><th>Customer</th><th>Items / Amount</th><th>CCA</th><th>Supplier</th><th>Courier</th><th>Status</th><th>Created</th><th>Actions</th>' +
                '</tr></thead><tbody>' + orders.map(function (order) {
                    // Selectable whenever the actor can bulk-assign; backend still rejects invalid states
                    // (already-assigned / already-in-pool) with a clear error.
                    var selectable = canAssign() && order.isGlobal !== true;
                    var selectCell = '';
                    if (canAssign()) {
                        selectCell = '<td class="order-ui-select-col">' +
                            (selectable
                                ? '<input type="checkbox" class="form-check-input" data-ui-order-select="' + order.id + '" aria-label="Select order ' + escapeHtml(order.orderNumber) + '">'
                                : '<span class="order-ui-select-disabled" title="Global orders cannot be assigned from this view">\u2014</span>') +
                            '</td>';
                    }
                    return '<tr data-order-id="' + order.id + '">' +
                        selectCell +
                        '<td><div class="order-number">' + escapeHtml(order.orderNumber) + '</div></td>' +
                        '<td><div class="customer-name">' + escapeHtml(order.customerName) + '</div><div class="order-sub">' + escapeHtml(order.customerPhone) + '</div></td>' +
                        '<td><div class="amount-value">' + formatMoney(order.amount, order.currency) + '</div>' +
                        '<div class="order-sub">' + order.itemCount + ' item' + (order.itemCount === 1 ? '' : 's') + '</div>' +
                        '<div class="order-sub">' + escapeHtml(order.itemName || '') + '</div></td>' +
                        '<td>' + assignmentHtml(order) + '</td>' +
                        '<td>' + escapeHtml(order.supplierName || '\u2014') + '</td>' +
                        '<td>' + (order.courierName
                            ? ('<div class="customer-name">' + escapeHtml(order.courierName) + '</div>'
                                + (order.trackingId
                                    ? '<div class="order-sub">' + escapeHtml(order.trackingId) + '</div>'
                                    : ''))
                            : '<span class="order-sub">\u2014</span>') + '</td>' +
                        '<td><span class="badge-status is-' + escapeHtml(statusCssClass(order.status)) + '">' + escapeHtml(order.statusLabel || order.status) + '</span></td>' +
                        '<td><div class="order-sub">' + formatDate(order.createdAt) + '</div></td>' +
                        '<td>' + actionsCellHtml(order) + '</td>' +
                        '</tr>';
                }).join('') + '</tbody></table></div>' + renderPagination();

            syncRowSelectionUi();
        }

        function fillBulkOrderList(listId) {
            var list = document.getElementById(listId);
            if (!list) return;
            list.innerHTML = selectedOrderIds.map(function (id) {
                var order = orders.find(function (row) { return String(row.id) === String(id); });
                return '<li>' + escapeHtml(order ? order.orderNumber : ('#' + id)) + '</li>';
            }).join('');
        }

        async function claimOrder(orderId) {
            if (!canTakeFromPool()) {
                toast('Update your current pool order status before claiming another.', 'warning');
                return;
            }
            var order = orders.find(function (row) { return String(row.id) === String(orderId); });
            if (!order || order.assignment !== 'pool') return;
            var btn = root.querySelector('[data-claim-order="' + orderId + '"]');
            if (btn) btn.disabled = true;
            try {
                await postJson(order.claimUrl || ((bootstrap.routes.claim || '/orders') + '/' + encodeURIComponent(order.uuid) + '/cca/claim'), {});
                window.location.href = indexPath() + '?' + buildQuery({ tab: 'assigned', cca_id: String(actor.id || ''), page: 1 });
            } catch (err) {
                toast(err.message || 'Unable to claim order.', 'danger');
                if (btn) btn.disabled = false;
            }
        }

        async function confirmBulkAssign() {
            var ccaSelect = document.getElementById('orderUiBulkAssignCcaSelect');
            var ccaId = ccaSelect ? Number(ccaSelect.value) : 0;
            if (!ccaId || !selectedOrderIds.length) return;
            var btn = document.getElementById('orderUiConfirmBulkAssignBtn');
            if (btn) btn.disabled = true;
            try {
                await postJson(bootstrap.routes.bulk_assign, { order_ids: selectedOrderIds.map(Number), cca_id: ccaId });
                window.location.reload();
            } catch (err) {
                toast(err.message || 'Bulk assign failed.', 'danger');
                if (btn) btn.disabled = false;
            }
        }

        async function confirmBulkPool() {
            if (!selectedOrderIds.length) return;
            var btn = document.getElementById('orderUiConfirmBulkPoolBtn');
            if (btn) btn.disabled = true;
            try {
                await postJson(bootstrap.routes.bulk_pool, { order_ids: selectedOrderIds.map(Number) });
                window.location.reload();
            } catch (err) {
                toast(err.message || 'Move to pool failed.', 'danger');
                if (btn) btn.disabled = false;
            }
        }

        document.getElementById('orderUiWorkspaceTabs').addEventListener('click', function (e) {
            var tab = e.target.closest('[data-ui-tab]');
            if (!tab) return;
            selectedOrderIds = [];
            navigate({ tab: tab.getAttribute('data-ui-tab'), page: 1 });
        });

        document.getElementById('orderUiStatusChips').addEventListener('click', function (e) {
            var chip = e.target.closest('[data-ui-status]');
            if (!chip) return;
            selectedOrderIds = [];
            navigate({ status: chip.getAttribute('data-ui-status') === 'all' ? '' : chip.getAttribute('data-ui-status'), page: 1 });
        });

        document.getElementById('orderUiSearchBtn').addEventListener('click', function () {
            selectedOrderIds = [];
            navigate({ search: document.getElementById('orderUiSearchInput').value.trim(), page: 1 });
        });
        document.getElementById('orderUiSearchInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                selectedOrderIds = [];
                navigate({ search: e.target.value.trim(), page: 1 });
            }
        });
        document.getElementById('orderUiClearBtn').addEventListener('click', function () {
            selectedOrderIds = [];
            navigate({
                search: '',
                status: '',
                tab: isCca() ? 'assigned' : 'all',
                cca_id: isCca() ? String(actor.id || '') : '',
                supplier_id: '',
                product_id: '',
                date_from: '',
                date_to: '',
                page: 1
            });
        });

        ['orderUiFilterCca', 'orderUiFilterSupplier'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                selectedOrderIds = [];
                navigate({ page: 1 });
            });
        });
        ['orderUiFilterDateFrom', 'orderUiFilterDateTo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                selectedOrderIds = [];
                navigate({ date_preset: 'custom', page: 1 });
            });
        });

        var resetBtn = document.getElementById('orderUiResetFiltersBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                selectedOrderIds = [];
                navigate({
                    status: '',
                    cca_id: isCca() ? String(actor.id || '') : '',
                    supplier_id: '',
                    product_id: '',
                    date_from: '',
                    date_to: '',
                    page: 1
                });
            });
        }

        var productInput = document.getElementById('orderUiFilterProduct');
        var productList = document.getElementById('orderUiProductSuggestions');

        if (productInput) {
            productInput.addEventListener('input', function () {
                var productIdEl = document.getElementById('orderUiFilterProductId');
                // Typing invalidates a prior selection until a suggestion is chosen again.
                if (productIdEl && productIdEl.value) {
                    productIdEl.value = '';
                }
                scheduleProductSearch(productInput.value);
            });
            productInput.addEventListener('focus', function () {
                scheduleProductSearch(productInput.value);
            });
            productInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideProductSuggestions();
                if (e.key === 'Enter') {
                    e.preventDefault();
                    hideProductSuggestions();
                }
            });
        }

        if (productList) {
            productList.addEventListener('click', function (e) {
                var option = e.target.closest('[data-ui-product-id]');
                if (!option) return;
                selectProduct(
                    option.getAttribute('data-ui-product-id'),
                    option.getAttribute('data-ui-product-name')
                );
            });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.order-ui-autocomplete')) hideProductSuggestions();
        });

        document.getElementById('orderUiWorkspaceContent').addEventListener('click', function (e) {
            var pageBtn = e.target.closest('[data-ui-page-nav]');
            if (pageBtn && !pageBtn.disabled) {
                var p = bootstrap.pagination || {};
                var next = pageBtn.getAttribute('data-ui-page-nav') === 'next' ? (p.current_page + 1) : (p.current_page - 1);
                navigate({ page: next });
                return;
            }
            var claimBtn = e.target.closest('[data-claim-order]');
            if (claimBtn) {
                claimOrder(claimBtn.getAttribute('data-claim-order'));
            }
        });

        document.getElementById('orderUiWorkspaceContent').addEventListener('change', function (e) {
            if (e.target.id === 'orderUiSelectAll') {
                var pageIds = [];
                root.querySelectorAll('[data-ui-order-select]:not(:disabled)').forEach(function (input) {
                    pageIds.push(String(input.getAttribute('data-ui-order-select')));
                });
                if (e.target.checked) {
                    pageIds.forEach(function (id) {
                        if (selectedOrderIds.indexOf(id) === -1) selectedOrderIds.push(id);
                    });
                } else {
                    selectedOrderIds = selectedOrderIds.filter(function (id) { return pageIds.indexOf(id) === -1; });
                }
                syncRowSelectionUi();
                return;
            }
            var rowSelect = e.target.closest('[data-ui-order-select]');
            if (!rowSelect) return;
            var id = String(rowSelect.getAttribute('data-ui-order-select'));
            if (rowSelect.checked) {
                if (selectedOrderIds.indexOf(id) === -1) selectedOrderIds.push(id);
            } else {
                selectedOrderIds = selectedOrderIds.filter(function (selectedId) { return selectedId !== id; });
            }
            syncRowSelectionUi();
        });

        var bulkAssignBtn = document.getElementById('orderUiBulkAssignBtn');
        if (bulkAssignBtn) {
            bulkAssignBtn.addEventListener('click', function () {
                if (!selectedOrderIds.length) return;
                fillBulkOrderList('orderUiBulkAssignOrderList');
                openModal('orderUiBulkAssignModal');
            });
        }
        var bulkPoolBtn = document.getElementById('orderUiBulkPoolBtn');
        if (bulkPoolBtn) {
            bulkPoolBtn.addEventListener('click', function () {
                if (!selectedOrderIds.length) return;
                fillBulkOrderList('orderUiBulkPoolOrderList');
                openModal('orderUiBulkPoolModal');
            });
        }
        var bulkClearBtn = document.getElementById('orderUiBulkClearBtn');
        if (bulkClearBtn) {
            bulkClearBtn.addEventListener('click', function () {
                selectedOrderIds = [];
                syncRowSelectionUi();
            });
        }
        var confirmBulkAssignBtn = document.getElementById('orderUiConfirmBulkAssignBtn');
        if (confirmBulkAssignBtn) confirmBulkAssignBtn.addEventListener('click', confirmBulkAssign);
        var confirmBulkPoolBtn = document.getElementById('orderUiConfirmBulkPoolBtn');
        if (confirmBulkPoolBtn) confirmBulkPoolBtn.addEventListener('click', confirmBulkPool);

        populateFilterSelects();
        renderPoolLockBanner();
        renderTabs();
        renderChips();
        renderTable();
    }
