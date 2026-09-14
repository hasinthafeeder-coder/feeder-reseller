<script>
(function () {
    'use strict';

    function toast(message, type) {
        var el = document.getElementById('orderUiToast')
            || document.getElementById('orderUiListToast')
            || document.getElementById('orderImportToast')
            || document.getElementById('prototypeToast');
        if (!el) {
            window.alert(message);
            return;
        }
        el.className = 'alert prototype-toast alert-' + (type || 'success');
        el.textContent = message;
        el.classList.remove('hidden');
        window.clearTimeout(toast._t);
        toast._t = window.setTimeout(function () { el.classList.add('hidden'); }, 2800);
    }

    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) modal.classList.add('hidden');
    }

    document.querySelectorAll('[data-open-order-ui-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-open-order-ui-modal'));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.orders-proto-modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal(modal.id);
        });
    });

    document.querySelectorAll('[data-order-ui-toast]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toast(btn.getAttribute('data-order-ui-toast'), 'warning');
        });
    });

    document.querySelectorAll('input[name="modalAssignmentTarget"]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('.order-ui-choice-card').forEach(function (card) {
                card.classList.toggle('is-selected', card.getAttribute('for') === input.id);
            });
            var ccaSelect = document.getElementById('modalAssignCcaSelect');
            if (ccaSelect) ccaSelect.disabled = input.value !== 'cca';
        });
    });

    var confirmBan = document.getElementById('confirmBanUserBtn');
    if (confirmBan) {
        confirmBan.addEventListener('click', function () {
            closeModal('banUserModal');
            toast('Ban is visual only. CustomerBanService was not called.', 'warning');
        });
    }

    var confirmSend = document.getElementById('confirmSendToCallCenterBtn');
    if (confirmSend) {
        confirmSend.addEventListener('click', function () {
            closeModal('callCenterAssignModal');
            toast('Call-center assignment is visual only. No assignment was saved.', 'warning');
        });
    }

    var confirmBook = document.getElementById('orderUiConfirmBookBtn');
    if (confirmBook) {
        confirmBook.addEventListener('click', function () {
            var courier = document.getElementById('uiCourier');
            var district = document.getElementById('uiDistrict');
            var city = document.getElementById('uiCity');
            if (!courier || !courier.value || !district || !district.value || !city || !city.value) {
                toast('Courier, district, and city are required before confirmation.', 'danger');
                return;
            }
            toast('Courier booking is visual only. Success would become Confirmed; failure would become Hold.', 'warning');
        });
    }

    var importInput = document.getElementById('orderImportFile');
    var importName = document.getElementById('orderImportFileName');
    var dropzone = document.getElementById('orderImportDropzone');
    if (importInput && importName) {
        importInput.addEventListener('change', function () {
            importName.textContent = importInput.files[0] ? importInput.files[0].name : 'No file selected.';
        });
    }
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file && importName) importName.textContent = file.name;
            toast('File drop is visual only. No import processing ran.', 'warning');
        });
    }
    var importSubmit = document.getElementById('orderImportSubmitBtn');
    if (importSubmit) {
        importSubmit.addEventListener('click', function () {
            toast('Import is visual only. No rows were created.', 'warning');
        });
    }
    var importClear = document.getElementById('orderImportClearBtn');
    if (importClear && importInput && importName) {
        importClear.addEventListener('click', function () {
            importInput.value = '';
            importName.textContent = 'No file selected.';
        });
    }
    var importTemplate = document.getElementById('orderImportTemplateBtn');
    if (importTemplate) {
        importTemplate.addEventListener('click', function () {
            toast('Template download will be connected in Stage 2.', 'warning');
        });
    }

    initListWorkspace();

    function initListWorkspace() {
        var root = document.getElementById('orderUiListWorkspace');
        var bootstrapEl = document.getElementById('orderUiListMock');
        if (!root || !bootstrapEl) return;

        var data = JSON.parse(bootstrapEl.textContent);
        var orders = data.orders || [];
        var ccas = data.ccas || [];
        var suppliers = data.suppliers || [];
        var products = data.products || [];
        var workspace = data.workspace;
        var archive = data.archive || 'completed';
        var isCallCenter = workspace === 'call-center';
        var perPage = Number(data.perPage) || 5;
        var currentPage = 1;
        var searchQuery = '';
        var statusFilter = 'all';
        var assignmentFilter = workspace === 'archived' ? archive : 'all';
        var filterCca = 'all';
        var filterSupplier = 'all';
        var filterProduct = '';
        var filterDateFrom = '';
        var filterDateTo = '';
        var globalSearch = false;
        var selectedOrderIds = [];

        var assignmentTabs = [
            { id: 'all', label: 'All' },
            { id: 'assigned', label: 'Assigned' },
            { id: 'unassigned', label: 'Unassigned' },
            { id: 'pool', label: 'Order Pool' },
        ];
        var archiveTabs = [
            { id: 'completed', label: 'Completed' },
            { id: 'returned', label: 'Returned' },
            { id: 'expired', label: 'Expired' },
        ];
        var statusChips = [
            { value: 'all', label: 'All' },
            { value: 'pending', label: 'Pending' },
            { value: '1st-attempt', label: '1st Attempt' },
            { value: '2nd-attempt', label: '2nd Attempt' },
            { value: '3rd-attempt', label: '3rd Attempt' },
            { value: 'hold', label: 'Hold' },
            { value: 'confirmed', label: 'Confirmed' },
            { value: 'cancelled', label: 'Cancelled' },
            { value: 'expiring', label: 'Expiring' },
        ];

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
            if (!iso) return '—';
            return new Date(iso).toLocaleString('en-US', {
                month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true
            });
        }

        function orderDateKey(iso) {
            if (!iso) return '';
            var d = new Date(iso);
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return d.getFullYear() + '-' + month + '-' + day;
        }

        function viewUrl(order) {
            if (order.status === 'hold') return data.holdUrl;
            if (order.status === 'expiring') return data.expiringUrl;
            if (order.status === 'expired') return data.expiredUrl;
            if (order.status === 'confirmed') return data.confirmUrl;
            return data.showUrl;
        }

        function isSelectable(order) {
            return isCallCenter && !order.isGlobal && order.isEditable !== false;
        }

        function findOrder(id) {
            return orders.find(function (order) { return String(order.id) === String(id); });
        }

        function selectedOrders() {
            return selectedOrderIds.map(findOrder).filter(Boolean);
        }

        function populateFilterSelects() {
            if (!isCallCenter) return;

            var ccaSelect = document.getElementById('orderUiFilterCca');
            var supplierSelect = document.getElementById('orderUiFilterSupplier');
            var bulkCcaSelect = document.getElementById('orderUiBulkAssignCcaSelect');

            if (ccaSelect) {
                ccaSelect.innerHTML = '<option value="all">All CCAs</option>' + ccas.map(function (cca) {
                    return '<option value="' + escapeHtml(cca.id) + '">' + escapeHtml(cca.name) + '</option>';
                }).join('');
            }

            if (supplierSelect) {
                supplierSelect.innerHTML = '<option value="all">All suppliers</option>' + suppliers.map(function (supplier) {
                    return '<option value="' + escapeHtml(supplier.id) + '">' + escapeHtml(supplier.name) + '</option>';
                }).join('');
            }

            if (bulkCcaSelect) {
                bulkCcaSelect.innerHTML = ccas.map(function (cca) {
                    return '<option value="' + escapeHtml(cca.id) + '">' + escapeHtml(cca.name) + '</option>';
                }).join('');
            }
        }

        function scopedOrders() {
            return orders.filter(function (order) {
                if (workspace === 'call-center') {
                    if (order.workspace !== 'call-center') return false;
                    if (order.isGlobal && !globalSearch) return false;
                    return true;
                }
                return order.workspace === 'archived' && order.archive === archive;
            });
        }

        function filtered() {
            return scopedOrders().filter(function (order) {
                if (workspace === 'call-center' && assignmentFilter !== 'all' && order.assignment !== assignmentFilter) {
                    return false;
                }
                if (statusFilter !== 'all' && order.status !== statusFilter) {
                    return false;
                }
                if (isCallCenter && filterCca !== 'all' && order.ccaId !== filterCca) {
                    return false;
                }
                if (isCallCenter && filterSupplier !== 'all' && order.supplierId !== filterSupplier) {
                    return false;
                }
                if (isCallCenter && filterProduct) {
                    var productHay = [order.itemCode, order.itemName].join(' ').toLowerCase();
                    if (productHay.indexOf(filterProduct.toLowerCase()) === -1) return false;
                }
                if (isCallCenter && filterDateFrom) {
                    if (orderDateKey(order.createdAt) < filterDateFrom) return false;
                }
                if (isCallCenter && filterDateTo) {
                    if (orderDateKey(order.createdAt) > filterDateTo) return false;
                }
                if (searchQuery) {
                    var hay = [
                        order.orderNumber,
                        order.customerName,
                        order.customerPhone,
                        order.ccaName,
                        order.supplierName,
                        order.itemName,
                        order.itemCode,
                        order.resellerName,
                    ].join(' ').toLowerCase();
                    if (hay.indexOf(searchQuery) === -1) return false;
                }
                return true;
            });
        }

        function paginated(rows) {
            var total = rows.length;
            var lastPage = Math.max(1, Math.ceil(total / perPage));
            if (currentPage > lastPage) currentPage = lastPage;
            if (currentPage < 1) currentPage = 1;
            var start = (currentPage - 1) * perPage;
            return {
                rows: rows.slice(start, start + perPage),
                total: total,
                lastPage: lastPage,
                from: total === 0 ? 0 : start + 1,
                to: Math.min(start + perPage, total),
            };
        }

        function countFor(tabId) {
            return scopedOrders().filter(function (order) {
                if (workspace === 'archived') return order.archive === tabId;
                if (tabId === 'all') return true;
                return order.assignment === tabId;
            }).length;
        }

        function renderTabs() {
            var tabs = workspace === 'archived' ? archiveTabs : assignmentTabs;
            var active = assignmentFilter;
            document.getElementById('orderUiWorkspaceTabs').innerHTML = tabs.map(function (tab) {
                return '<a href="' + (workspace === 'archived'
                    ? (window.location.pathname + '?workspace=archived&archive=' + tab.id)
                    : '#') + '" class="workspace-tab ' + (active === tab.id ? 'is-active' : '') + '" data-ui-tab="' + tab.id + '">' +
                    escapeHtml(tab.label) + '<span class="tab-count">' + countFor(tab.id) + '</span></a>';
            }).join('');
        }

        function renderChips() {
            var row = document.getElementById('orderUiStatusChips');
            if (workspace === 'archived') {
                row.innerHTML = '';
                return;
            }
            row.innerHTML = statusChips.map(function (chip) {
                return '<button type="button" class="filter-chip ' + (statusFilter === chip.value ? 'is-active' : '') + '" data-ui-status="' + chip.value + '">' +
                    escapeHtml(chip.label) + '</button>';
            }).join('');
        }

        function assignmentHtml(order) {
            if (order.assignment === 'pool') {
                return '<div class="assign-block"><span class="badge-assign is-pool">● Order Pool</span></div>';
            }
            if (order.assignment === 'unassigned') {
                return '<div class="assign-block"><span class="badge-assign is-unassigned">● Unassigned</span></div>';
            }
            return '<div class="assign-block"><span class="badge-assign is-other">● Assigned to CCA</span>' +
                '<div class="assign-name mt-1">' + escapeHtml(order.ccaName || '—') + '</div><div class="assign-role">CCA</div></div>';
        }

        function orderMetaHtml(order) {
            var bits = '<div class="order-number">' + escapeHtml(order.orderNumber) + '</div>';
            if (order.isGlobal) {
                bits += '<div class="mt-1"><span class="badge-source is-global">Global</span></div>';
                bits += '<div class="order-sub">' + escapeHtml(order.resellerName || 'Other reseller') + '</div>';
            }
            return bits;
        }

        function renderPagination(page) {
            if (page.total === 0) return '';
            return '<div class="orders-pagination">' +
                '<div class="orders-pagination-meta">Showing ' + page.from + '–' + page.to + ' of ' + page.total + '</div>' +
                '<div class="orders-pagination-actions">' +
                '<button type="button" class="btn btn-sm btn-light border" data-ui-page-nav="prev"' + (currentPage <= 1 ? ' disabled' : '') + '>Previous</button>' +
                '<span class="orders-pagination-meta">Page ' + currentPage + ' of ' + page.lastPage + '</span>' +
                '<button type="button" class="btn btn-sm btn-light border" data-ui-page-nav="next"' + (currentPage >= page.lastPage ? ' disabled' : '') + '>Next</button>' +
                '</div></div>';
        }

        function renderBulkToolbar() {
            var toolbar = document.getElementById('orderUiBulkToolbar');
            var countEl = document.getElementById('orderUiBulkSelectedCount');
            if (!toolbar || !countEl) return;
            if (!selectedOrderIds.length) {
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
                pageSelectable.forEach(function (input) {
                    if (input.checked) checkedCount += 1;
                });
                selectAll.checked = pageSelectable.length > 0 && checkedCount === pageSelectable.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < pageSelectable.length;
            }
            renderBulkToolbar();
        }

        function renderTable() {
            var allRows = filtered();
            var page = paginated(allRows);
            var titleEl = document.getElementById('orderUiWorkspaceTitle');
            var metaEl = document.getElementById('orderUiResultsMeta');
            var content = document.getElementById('orderUiWorkspaceContent');
            var titles = {
                all: 'All Orders', assigned: 'Assigned Orders', unassigned: 'Unassigned Orders',
                pool: 'Order Pool', completed: 'Completed Orders', returned: 'Returned Orders', expired: 'Expired Orders',
            };
            titleEl.textContent = titles[assignmentFilter] || 'Orders';
            metaEl.textContent = allRows.length + ' in this view · example data only' +
                (globalSearch ? ' · including global orders' : '');

            if (!page.rows.length) {
                content.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">shopping_cart</span><h4>No orders found</h4><p>There are no example orders in this view.</p></div>';
                renderBulkToolbar();
                return;
            }

            var selectHeader = isCallCenter
                ? '<th class="order-ui-select-col"><input type="checkbox" id="orderUiSelectAll" class="form-check-input" aria-label="Select all on page"></th>'
                : '';

            content.innerHTML = '<div class="table-responsive"><table class="orders-table align-middle"><thead><tr>' +
                selectHeader +
                '<th>Order</th><th>Customer</th><th>Items / Amount</th><th>CCA</th><th>Supplier</th><th>Courier</th><th>Status</th><th>Created</th><th>Actions</th>' +
                '</tr></thead><tbody>' + page.rows.map(function (order) {
                    var selectable = isSelectable(order);
                    var selectCell = '';
                    if (isCallCenter) {
                        selectCell = '<td class="order-ui-select-col">' +
                            (selectable
                                ? '<input type="checkbox" class="form-check-input" data-ui-order-select="' + order.id + '" aria-label="Select order ' + escapeHtml(order.orderNumber) + '">'
                                : '<span class="order-ui-select-disabled" title="Global orders cannot be assigned from this view">—</span>') +
                            '</td>';
                    }

                    var actionLabel = order.isGlobal ? 'View details' : 'View';
                    var actionClass = order.isGlobal ? 'btn btn-sm btn-outline-secondary' : 'btn btn-sm btn-light border';
                    var rowClass = order.isGlobal ? ' is-global-order' : '';

                    return '<tr class="' + rowClass.trim() + '" data-order-id="' + order.id + '">' +
                        selectCell +
                        '<td>' + orderMetaHtml(order) + '</td>' +
                        '<td><div class="customer-name">' + escapeHtml(order.customerName) + '</div><div class="order-sub">' + escapeHtml(order.customerPhone) + '</div></td>' +
                        '<td><div class="amount-value">' + formatMoney(order.amount, order.currency) + '</div>' +
                        '<div class="order-sub">' + order.itemCount + ' item' + (order.itemCount === 1 ? '' : 's') + '</div>' +
                        '<div class="order-sub">' + escapeHtml(order.itemName || '') + '</div></td>' +
                        '<td>' + assignmentHtml(order) + '</td>' +
                        '<td>' + escapeHtml(order.supplierName) + '</td>' +
                        '<td>' + (order.courierName ? ('<div class="customer-name">' + escapeHtml(order.courierName) + '</div>') : '<span class="order-sub">—</span>') + '</td>' +
                        '<td><span class="badge-status is-' + escapeHtml(order.status) + '">' + escapeHtml(order.statusLabel) + '</span></td>' +
                        '<td><div class="order-sub">' + formatDate(order.createdAt) + '</div></td>' +
                        '<td><a href="' + escapeHtml(viewUrl(order)) + '" class="' + actionClass + '">' + actionLabel + '</a></td>' +
                        '</tr>';
                }).join('') + '</tbody></table></div>' + renderPagination(page);

            syncRowSelectionUi();
        }

        function render() {
            renderTabs();
            renderChips();
            renderTable();
        }

        function resetPageAndRender() {
            currentPage = 1;
            render();
        }

        function applySearch() {
            searchQuery = document.getElementById('orderUiSearchInput').value.trim().toLowerCase();
            resetPageAndRender();
        }

        function clearFilters() {
            document.getElementById('orderUiSearchInput').value = '';
            searchQuery = '';
            statusFilter = 'all';
            filterCca = 'all';
            filterSupplier = 'all';
            filterProduct = '';
            filterDateFrom = '';
            filterDateTo = '';
            globalSearch = false;

            var globalToggle = document.getElementById('orderUiGlobalSearch');
            if (globalToggle) globalToggle.checked = false;

            var ccaSelect = document.getElementById('orderUiFilterCca');
            var supplierSelect = document.getElementById('orderUiFilterSupplier');
            var productInput = document.getElementById('orderUiFilterProduct');
            var dateFrom = document.getElementById('orderUiFilterDateFrom');
            var dateTo = document.getElementById('orderUiFilterDateTo');
            if (ccaSelect) ccaSelect.value = 'all';
            if (supplierSelect) supplierSelect.value = 'all';
            if (productInput) productInput.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            hideProductSuggestions();
            selectedOrderIds = [];
            resetPageAndRender();
        }

        function hideProductSuggestions() {
            var list = document.getElementById('orderUiProductSuggestions');
            var input = document.getElementById('orderUiFilterProduct');
            if (list) list.classList.add('hidden');
            if (input) input.setAttribute('aria-expanded', 'false');
        }

        function renderProductSuggestions(query) {
            var list = document.getElementById('orderUiProductSuggestions');
            var input = document.getElementById('orderUiFilterProduct');
            if (!list || !input) return;

            var q = String(query || '').trim().toLowerCase();
            if (!q) {
                hideProductSuggestions();
                return;
            }

            var matches = products.filter(function (product) {
                return (product.name + ' ' + product.code).toLowerCase().indexOf(q) !== -1;
            }).slice(0, 6);

            if (!matches.length) {
                hideProductSuggestions();
                return;
            }

            list.innerHTML = matches.map(function (product) {
                return '<li role="option" tabindex="-1" data-ui-product-option="' + escapeHtml(product.name) + '">' +
                    '<span class="order-ui-suggest-name">' + escapeHtml(product.name) + '</span>' +
                    '<span class="order-ui-suggest-code">' + escapeHtml(product.code) + '</span></li>';
            }).join('');
            list.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }

        function fillBulkOrderList(listId) {
            var list = document.getElementById(listId);
            if (!list) return;
            list.innerHTML = selectedOrders().map(function (order) {
                return '<li>' + escapeHtml(order.orderNumber) + ' · ' + escapeHtml(order.customerName) + '</li>';
            }).join('');
        }

        populateFilterSelects();

        document.getElementById('orderUiWorkspaceTabs').addEventListener('click', function (e) {
            var tab = e.target.closest('[data-ui-tab]');
            if (!tab) return;
            if (workspace === 'archived') return;
            e.preventDefault();
            assignmentFilter = tab.getAttribute('data-ui-tab');
            selectedOrderIds = [];
            resetPageAndRender();
        });

        document.getElementById('orderUiStatusChips').addEventListener('click', function (e) {
            var chip = e.target.closest('[data-ui-status]');
            if (!chip) return;
            statusFilter = chip.getAttribute('data-ui-status');
            resetPageAndRender();
        });

        document.getElementById('orderUiSearchBtn').addEventListener('click', applySearch);
        document.getElementById('orderUiSearchInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applySearch();
            }
        });
        document.getElementById('orderUiClearBtn').addEventListener('click', clearFilters);

        var globalToggle = document.getElementById('orderUiGlobalSearch');
        if (globalToggle) {
            globalToggle.addEventListener('change', function () {
                globalSearch = globalToggle.checked;
                selectedOrderIds = selectedOrderIds.filter(function (id) {
                    var order = findOrder(id);
                    return order && isSelectable(order);
                });
                resetPageAndRender();
            });
        }

        var ccaFilter = document.getElementById('orderUiFilterCca');
        if (ccaFilter) {
            ccaFilter.addEventListener('change', function () {
                filterCca = ccaFilter.value;
                resetPageAndRender();
            });
        }

        var supplierFilter = document.getElementById('orderUiFilterSupplier');
        if (supplierFilter) {
            supplierFilter.addEventListener('change', function () {
                filterSupplier = supplierFilter.value;
                resetPageAndRender();
            });
        }

        var dateFromInput = document.getElementById('orderUiFilterDateFrom');
        if (dateFromInput) {
            dateFromInput.addEventListener('change', function () {
                filterDateFrom = dateFromInput.value;
                resetPageAndRender();
            });
        }

        var dateToInput = document.getElementById('orderUiFilterDateTo');
        if (dateToInput) {
            dateToInput.addEventListener('change', function () {
                filterDateTo = dateToInput.value;
                resetPageAndRender();
            });
        }

        var resetFiltersBtn = document.getElementById('orderUiResetFiltersBtn');
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function () {
                statusFilter = 'all';
                filterCca = 'all';
                filterSupplier = 'all';
                filterProduct = '';
                filterDateFrom = '';
                filterDateTo = '';
                if (ccaFilter) ccaFilter.value = 'all';
                if (supplierFilter) supplierFilter.value = 'all';
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                var productInput = document.getElementById('orderUiFilterProduct');
                if (productInput) productInput.value = '';
                hideProductSuggestions();
                resetPageAndRender();
            });
        }

        var productInput = document.getElementById('orderUiFilterProduct');
        var productList = document.getElementById('orderUiProductSuggestions');
        if (productInput) {
            productInput.addEventListener('input', function () {
                filterProduct = productInput.value.trim();
                renderProductSuggestions(filterProduct);
                resetPageAndRender();
            });
            productInput.addEventListener('focus', function () {
                renderProductSuggestions(productInput.value);
            });
            productInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideProductSuggestions();
            });
        }
        if (productList) {
            productList.addEventListener('click', function (e) {
                var option = e.target.closest('[data-ui-product-option]');
                if (!option || !productInput) return;
                productInput.value = option.getAttribute('data-ui-product-option');
                filterProduct = productInput.value.trim();
                hideProductSuggestions();
                resetPageAndRender();
            });
        }
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.order-ui-autocomplete')) hideProductSuggestions();
        });

        document.getElementById('orderUiWorkspaceContent').addEventListener('click', function (e) {
            var pageBtn = e.target.closest('[data-ui-page-nav]');
            if (pageBtn && !pageBtn.disabled) {
                currentPage += pageBtn.getAttribute('data-ui-page-nav') === 'next' ? 1 : -1;
                renderTable();
                return;
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
                    selectedOrderIds = selectedOrderIds.filter(function (id) {
                        return pageIds.indexOf(id) === -1;
                    });
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
                selectedOrderIds = selectedOrderIds.filter(function (selectedId) {
                    return selectedId !== id;
                });
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

        var confirmBulkAssign = document.getElementById('orderUiConfirmBulkAssignBtn');
        if (confirmBulkAssign) {
            confirmBulkAssign.addEventListener('click', function () {
                var count = selectedOrderIds.length;
                var ccaSelect = document.getElementById('orderUiBulkAssignCcaSelect');
                var ccaName = ccaSelect && ccaSelect.options[ccaSelect.selectedIndex]
                    ? ccaSelect.options[ccaSelect.selectedIndex].text
                    : 'CCA';
                closeModal('orderUiBulkAssignModal');
                selectedOrderIds = [];
                syncRowSelectionUi();
                toast('Assigned ' + count + ' order(s) to ' + ccaName + ' (visual only).', 'warning');
            });
        }

        var confirmBulkPool = document.getElementById('orderUiConfirmBulkPoolBtn');
        if (confirmBulkPool) {
            confirmBulkPool.addEventListener('click', function () {
                var count = selectedOrderIds.length;
                closeModal('orderUiBulkPoolModal');
                selectedOrderIds = [];
                syncRowSelectionUi();
                toast('Moved ' + count + ' order(s) to Order Pool (visual only).', 'warning');
            });
        }

        render();
    }
})();
</script>
