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

    function prepareStatusConfirmModal(statusValue, statusLabel) {
        var modal = document.getElementById('orderStatusConfirmModal');
        var statusInput = document.getElementById('orderStatusConfirmValue');
        var copy = document.getElementById('orderStatusConfirmCopy');
        if (!modal || !statusInput) return false;

        statusInput.value = statusValue || '';
        if (copy) {
            var fromLabel = modal.getAttribute('data-current-status-label') || 'current status';
            var toLabel = statusLabel || statusValue || 'selected status';
            copy.innerHTML = 'Move this order from <strong></strong> to <strong></strong>.';
            var strongs = copy.querySelectorAll('strong');
            if (strongs[0]) strongs[0].textContent = fromLabel;
            if (strongs[1]) strongs[1].textContent = toLabel;
        }

        openModal('orderStatusConfirmModal');
        var note = document.getElementById('orderStatusConfirmNote');
        if (note) {
            window.setTimeout(function () { note.focus(); }, 0);
        }
        return true;
    }

    document.querySelectorAll('.order-status-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            prepareStatusConfirmModal(
                btn.getAttribute('data-status-value'),
                btn.getAttribute('data-status-label')
            );
        });
    });

    (function reopenStatusConfirmOnValidationError() {
        var modal = document.getElementById('orderStatusConfirmModal');
        if (!modal) return;
        var reopenStatus = modal.getAttribute('data-reopen-status');
        if (!reopenStatus) return;

        var matchingBtn = null;
        document.querySelectorAll('.order-status-action-btn').forEach(function (candidate) {
            if (!matchingBtn && candidate.getAttribute('data-status-value') === reopenStatus) {
                matchingBtn = candidate;
            }
        });
        var label = matchingBtn
            ? matchingBtn.getAttribute('data-status-label')
            : reopenStatus;
        prepareStatusConfirmModal(reopenStatus, label);
    })();

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

    var confirmSend = document.getElementById('confirmSendToCallCenterBtn');
    if (confirmSend && ! document.getElementById('orderSendToPoolForm')) {
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
    var importBootstrapEl = document.getElementById('orderImportBootstrap');
    var importState = {
        batchUuid: null,
        uploading: false,
        processing: false,
        urls: null,
        csrf: '',
        assignment: null,
        createdOrders: []
    };

    if (importBootstrapEl) {
        try {
            var importBootstrap = JSON.parse(importBootstrapEl.textContent);
            importState.urls = importBootstrap.urls || {};
            importState.csrf = importBootstrap.csrf || '';
            importState.assignment = importBootstrap.assignment || null;
        } catch (e) {
            importState.urls = null;
        }
    }

    function importEscape(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function importAssignmentSelectedIds() {
        var ids = [];
        document.querySelectorAll('[data-import-assign-order]:checked').forEach(function (input) {
            ids.push(Number(input.getAttribute('data-import-assign-order')));
        });
        return ids.filter(function (id) { return id > 0; });
    }

    function renderImportAssignPanel(payload) {
        var body = document.getElementById('orderImportAssignBody');
        var selectAll = document.getElementById('orderImportAssignSelectAll');
        var meta = document.getElementById('orderImportAssignMeta');
        var hint = document.getElementById('orderImportUnassignedHint');
        if (!body) return;

        var rows = ((payload && payload.rows) || []).filter(function (row) {
            return row.created_order_id;
        });
        importState.createdOrders = rows;

        if (meta) {
            var created = (payload && payload.summary && payload.summary.created_rows) || rows.length;
            meta.textContent = created
                ? (created + ' created order(s) ready for assignment.')
                : 'Create orders first, then assign selected rows, send them to the pool, or assign a random quantity of unassigned company orders.';
        }

        if (hint && importState.assignment) {
            var count = Number(importState.assignment.unassigned_count || 0);
            hint.textContent = count + ' unassigned company order(s) currently available for random assignment.';
        }

        if (!rows.length) {
            body.innerHTML = '<tr id="orderImportAssignEmptyRow"><td colspan="7" class="text-body">No created orders ready to assign yet.</td></tr>';
            if (selectAll) {
                selectAll.checked = false;
                selectAll.disabled = true;
            }
            return;
        }

        if (selectAll) selectAll.disabled = false;
        body.innerHTML = rows.map(function (row) {
            var itemName = row.item_name || '—';
            var itemQty = row.item_qty != null ? row.item_qty : (row.qty || '—');
            var itemAmount = row.item_amount
                ? ('LKR ' + row.item_amount)
                : (row.price ? ('LKR ' + row.price) : '—');
            var stock = row.existing_stock != null ? row.existing_stock : '—';

            return '<tr>'
                + '<td><input type="checkbox" class="form-check-input" data-import-assign-order="'
                + importEscape(row.created_order_id) + '"></td>'
                + '<td>' + importEscape(row.order_number || ('#' + row.created_order_id)) + '</td>'
                + '<td>' + importEscape(row.name || '—') + '</td>'
                + '<td><div>' + importEscape(row.tp_1 || '—') + '</div>'
                + '<div class="order-sub">' + importEscape(row.tp_2 || 'No secondary') + '</div></td>'
                + '<td><div class="customer-name">' + importEscape(itemName) + '</div>'
                + '<div class="order-sub">Qty ' + importEscape(itemQty)
                + ' · ' + importEscape(itemAmount) + '</div></td>'
                + '<td>' + importEscape(row.supplier_name || '—') + '</td>'
                + '<td>' + importEscape(stock) + '</td>'
                + '</tr>';
        }).join('');
    }

    function populateImportAssignCcas() {
        var select = document.getElementById('orderImportAssignCca');
        if (!select || !importState.assignment) return;
        var ccas = importState.assignment.ccas || [];
        select.innerHTML = '<option value="">Choose CCA</option>' + ccas.map(function (cca) {
            return '<option value="' + importEscape(cca.id) + '">' + importEscape(cca.name) + '</option>';
        }).join('');
    }

    function importPostJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': importState.csrf || (importState.assignment && importState.assignment.csrf) || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body || {}),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, status: response.status, json: json };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null };
            });
        });
    }

    function importFirstError(json, fallback) {
        if (json && json.message && (!json.errors || !Object.keys(json.errors).length)) {
            return json.message;
        }
        if (json && json.errors) {
            var first = Object.values(json.errors)[0];
            if (Array.isArray(first) && first[0]) return first[0];
        }
        if (json && json.message) return json.message;
        return fallback;
    }

    function renderImportPreview(payload) {
        var body = document.getElementById('orderImportPreviewBody');
        var summary = document.getElementById('orderImportSummary');
        if (!body) return;

        var rows = (payload && payload.rows) || [];
        var counts = (payload && payload.summary) || {};
        if (summary) {
            summary.textContent = 'Total '
                + (counts.total_rows || 0)
                + ' · Valid ' + (counts.valid_rows || 0)
                + ' · Banned ' + (counts.banned_rows || 0)
                + ' · Invalid ' + (counts.invalid_rows || 0)
                + ' · Created ' + (counts.created_rows || 0);
        }

        if (!rows.length) {
            body.innerHTML = '<tr id="orderImportEmptyRow"><td colspan="9" class="text-body">No import rows yet.</td></tr>';
            renderImportAssignPanel(payload);
            return;
        }

        body.innerHTML = rows.map(function (row) {
            var reason = row.reason ? '<div class="order-sub">' + importEscape(row.reason) + '</div>' : '';
            return '<tr>'
                + '<td>' + importEscape(row.row) + '</td>'
                + '<td><div class="customer-name">' + importEscape(row.name || '—') + '</div></td>'
                + '<td><div>' + importEscape(row.tp_1 || '—') + '</div>'
                + '<div class="order-sub">' + importEscape(row.tp_2 || 'No secondary') + '</div></td>'
                + '<td>' + importEscape(row.address || '—') + '</td>'
                + '<td class="amount-value">' + (row.price ? ('LKR ' + importEscape(row.price)) : '—') + '</td>'
                + '<td>' + importEscape(row.qty || '—') + '</td>'
                + '<td>' + importEscape(row.item_code || '—') + '</td>'
                + '<td>' + (row.delivery ? ('LKR ' + importEscape(row.delivery)) : '—') + '</td>'
                + '<td><span class="order-ui-import-state is-' + importEscape(row.state) + '">'
                + importEscape(row.stateLabel || row.state)
                + '</span>' + reason + '</td>'
                + '</tr>';
        }).join('');

        renderImportAssignPanel(payload);
    }

    function resetImportPreview() {
        importState.batchUuid = null;
        renderImportPreview({
            rows: [],
            summary: { total_rows: 0, valid_rows: 0, banned_rows: 0, invalid_rows: 0, created_rows: 0 }
        });
        var summary = document.getElementById('orderImportSummary');
        if (summary) summary.textContent = 'Upload a file to preview validation results';
    }

    populateImportAssignCcas();

    var importAssignSelectAll = document.getElementById('orderImportAssignSelectAll');
    if (importAssignSelectAll) {
        importAssignSelectAll.addEventListener('change', function () {
            document.querySelectorAll('[data-import-assign-order]').forEach(function (input) {
                input.checked = importAssignSelectAll.checked;
            });
        });
    }

    var importAssignSelectedBtn = document.getElementById('orderImportAssignSelectedBtn');
    if (importAssignSelectedBtn) {
        importAssignSelectedBtn.addEventListener('click', function () {
            if (!importState.assignment || !importState.assignment.routes) {
                toast('Assignment endpoints are not available.', 'danger');
                return;
            }
            var orderIds = importAssignmentSelectedIds();
            var ccaSelect = document.getElementById('orderImportAssignCca');
            var ccaId = ccaSelect ? Number(ccaSelect.value) : 0;
            if (!orderIds.length) {
                toast('Select at least one created order.', 'warning');
                return;
            }
            if (!ccaId) {
                toast('Select a call center agent.', 'warning');
                return;
            }
            importAssignSelectedBtn.disabled = true;
            importPostJson(importState.assignment.routes.bulk_assign, {
                order_ids: orderIds,
                cca_id: ccaId
            }).then(function (result) {
                if (!result.ok) {
                    toast(importFirstError(result.json, 'Assignment failed.'), 'danger');
                    return;
                }
                var count = (result.json && result.json.data && result.json.data.assigned_count)
                    || orderIds.length;
                toast((result.json && result.json.message) || (count + ' order(s) assigned.'), 'success');
                importState.createdOrders = importState.createdOrders.filter(function (row) {
                    return orderIds.indexOf(Number(row.created_order_id)) === -1;
                });
                if (importState.assignment) {
                    importState.assignment.unassigned_count = Math.max(
                        0,
                        Number(importState.assignment.unassigned_count || 0) - count
                    );
                }
                renderImportAssignPanel({
                    rows: importState.createdOrders,
                    summary: { created_rows: importState.createdOrders.length }
                });
            }).catch(function () {
                toast('Assignment failed.', 'danger');
            }).finally(function () {
                importAssignSelectedBtn.disabled = false;
            });
        });
    }

    var importPoolSelectedBtn = document.getElementById('orderImportPoolSelectedBtn');
    if (importPoolSelectedBtn) {
        importPoolSelectedBtn.addEventListener('click', function () {
            if (!importState.assignment || !importState.assignment.routes) {
                toast('Assignment endpoints are not available.', 'danger');
                return;
            }
            var orderIds = importAssignmentSelectedIds();
            if (!orderIds.length) {
                toast('Select at least one created order.', 'warning');
                return;
            }
            importPoolSelectedBtn.disabled = true;
            importPostJson(importState.assignment.routes.bulk_pool, {
                order_ids: orderIds
            }).then(function (result) {
                if (!result.ok) {
                    toast(importFirstError(result.json, 'Move to pool failed.'), 'danger');
                    return;
                }
                var count = (result.json && result.json.data && result.json.data.assigned_count)
                    || orderIds.length;
                toast((result.json && result.json.message) || (count + ' order(s) moved to the Order Pool.'), 'success');
                importState.createdOrders = importState.createdOrders.filter(function (row) {
                    return orderIds.indexOf(Number(row.created_order_id)) === -1;
                });
                if (importState.assignment) {
                    importState.assignment.unassigned_count = Math.max(
                        0,
                        Number(importState.assignment.unassigned_count || 0) - count
                    );
                }
                renderImportAssignPanel({
                    rows: importState.createdOrders,
                    summary: { created_rows: importState.createdOrders.length }
                });
            }).catch(function () {
                toast('Move to pool failed.', 'danger');
            }).finally(function () {
                importPoolSelectedBtn.disabled = false;
            });
        });
    }

    var importAssignRandomBtn = document.getElementById('orderImportAssignRandomBtn');
    if (importAssignRandomBtn) {
        importAssignRandomBtn.addEventListener('click', function () {
            if (!importState.assignment || !importState.assignment.routes) {
                toast('Assignment endpoints are not available.', 'danger');
                return;
            }
            openImportRandomAssignModal();
        });
    }

    function importRandomAssignRemaining() {
        return Math.max(0, Number((importState.assignment && importState.assignment.unassigned_count) || 0));
    }

    function updateImportRandomAssignTotals() {
        var remaining = importRandomAssignRemaining();
        var remainingEl = document.getElementById('orderImportRandomAssignRemaining');
        var totalHint = document.getElementById('orderImportRandomAssignTotalHint');
        var total = 0;

        document.querySelectorAll('[data-import-random-cca-qty]').forEach(function (input) {
            var value = Number(input.value || 0);
            if (value > 0) total += value;
        });

        if (remainingEl) {
            remainingEl.textContent = 'Remaining unassigned orders: ' + remaining;
        }
        if (totalHint) {
            var over = total > remaining;
            totalHint.textContent = 'Total selected: ' + total
                + (over ? ' (exceeds remaining ' + remaining + ')' : '');
            totalHint.classList.toggle('text-danger', over);
        }
    }

    function openImportRandomAssignModal() {
        var body = document.getElementById('orderImportRandomAssignCcaBody');
        if (!body || !importState.assignment) return;

        var ccas = importState.assignment.ccas || [];
        if (!ccas.length) {
            body.innerHTML = '<tr><td colspan="2" class="text-body">No eligible call center agents available.</td></tr>';
        } else {
            body.innerHTML = ccas.map(function (cca) {
                return '<tr>'
                    + '<td>' + importEscape(cca.name || ('CCA #' + cca.id)) + '</td>'
                    + '<td><input type="number" min="0" max="500" step="1" class="form-control" '
                    + 'data-import-random-cca-qty="' + importEscape(cca.id) + '" '
                    + 'placeholder="0" value=""></td>'
                    + '</tr>';
            }).join('');
        }

        updateImportRandomAssignTotals();
        openModal('orderImportRandomAssignModal');
    }

    document.addEventListener('input', function (event) {
        var target = event.target;
        if (!target || !target.hasAttribute || !target.hasAttribute('data-import-random-cca-qty')) {
            return;
        }
        updateImportRandomAssignTotals();
    });

    var importRandomAssignConfirmBtn = document.getElementById('orderImportRandomAssignConfirmBtn');
    if (importRandomAssignConfirmBtn) {
        importRandomAssignConfirmBtn.addEventListener('click', function () {
            if (!importState.assignment || !importState.assignment.routes) {
                toast('Assignment endpoints are not available.', 'danger');
                return;
            }

            var route = importState.assignment.routes.bulk_assign_random_allocations
                || importState.assignment.routes.bulk_assign_random;
            if (!route) {
                toast('Assignment endpoints are not available.', 'danger');
                return;
            }

            var remaining = importRandomAssignRemaining();
            var allocations = [];
            var total = 0;

            document.querySelectorAll('[data-import-random-cca-qty]').forEach(function (input) {
                var quantity = Number(input.value || 0);
                var ccaId = Number(input.getAttribute('data-import-random-cca-qty'));
                if (!ccaId || !quantity || quantity < 1) return;
                allocations.push({ cca_id: ccaId, quantity: quantity });
                total += quantity;
            });

            if (!allocations.length) {
                toast('Enter a quantity of at least 1 for one or more call center agents.', 'warning');
                return;
            }
            if (total > remaining) {
                toast('Total quantity exceeds remaining unassigned orders (' + remaining + ').', 'warning');
                return;
            }

            importRandomAssignConfirmBtn.disabled = true;
            importPostJson(route, { allocations: allocations }).then(function (result) {
                if (!result.ok) {
                    toast(importFirstError(result.json, 'Random assign failed.'), 'danger');
                    return;
                }

                var assigned = (result.json && result.json.data && result.json.data.assigned_count) || 0;
                toast((result.json && result.json.message) || (assigned + ' order(s) assigned.'), 'success');

                if (importState.assignment && result.json && result.json.data) {
                    var available = Number(result.json.data.available_count || remaining);
                    importState.assignment.unassigned_count = Math.max(0, available - assigned);
                }

                var assignedIds = ((result.json && result.json.data && result.json.data.orders) || []).map(function (order) {
                    return Number(order.id);
                });
                if (assignedIds.length) {
                    importState.createdOrders = importState.createdOrders.filter(function (row) {
                        return assignedIds.indexOf(Number(row.created_order_id)) === -1;
                    });
                }

                renderImportAssignPanel({
                    rows: importState.createdOrders,
                    summary: { created_rows: importState.createdOrders.length }
                });
                closeModal('orderImportRandomAssignModal');
            }).catch(function () {
                toast('Random assign failed.', 'danger');
            }).finally(function () {
                importRandomAssignConfirmBtn.disabled = false;
            });
        });
    }

    function uploadImportFile(file) {
        if (!file || !importState.urls || !importState.urls.upload) {
            toast('Import endpoints are not available.', 'danger');
            return;
        }
        if (importState.uploading) return;

        importState.uploading = true;
        if (importName) importName.textContent = file.name;

        var formData = new FormData();
        formData.append('file', file);

        fetch(importState.urls.upload, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': importState.csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, status: response.status, json: json };
            }).catch(function () {
                return { ok: response.ok, status: response.status, json: null };
            });
        }).then(function (result) {
            if (!result.ok || !result.json || !result.json.data) {
                var message = 'Import upload failed.';
                if (result.json && result.json.message) message = result.json.message;
                if (result.json && result.json.errors) {
                    var first = Object.values(result.json.errors)[0];
                    if (Array.isArray(first) && first[0]) message = first[0];
                }
                toast(message, 'danger');
                return;
            }

            importState.batchUuid = result.json.data.batch && result.json.data.batch.uuid
                ? result.json.data.batch.uuid
                : null;
            renderImportPreview(result.json.data);
            toast('File validated. Review the preview, then import valid rows.', 'success');
        }).catch(function () {
            toast('Import upload failed.', 'danger');
        }).finally(function () {
            importState.uploading = false;
        });
    }

    if (importInput && importName) {
        importInput.addEventListener('change', function () {
            var file = importInput.files[0];
            importName.textContent = file ? file.name : 'No file selected.';
            if (file) uploadImportFile(file);
            else resetImportPreview();
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
            if (!file) return;
            if (importInput) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    importInput.files = dt.files;
                } catch (err) {
                    // Some browsers block programmatic FileList assignment; upload still proceeds.
                }
            }
            if (importName) importName.textContent = file.name;
            uploadImportFile(file);
        });
    }
    var importSubmit = document.getElementById('orderImportSubmitBtn');
    if (importSubmit) {
        importSubmit.addEventListener('click', function () {
            if (!importState.batchUuid || !importState.urls || !importState.urls.process) {
                toast('Upload and validate a file before importing orders.', 'warning');
                return;
            }
            if (importState.processing) return;
            importState.processing = true;
            importSubmit.disabled = true;

            var url = String(importState.urls.process).replace(':batch', encodeURIComponent(importState.batchUuid));
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': importState.csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({}),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                }).catch(function () {
                    return { ok: response.ok, json: null };
                });
            }).then(function (result) {
                if (!result.ok || !result.json || !result.json.data) {
                    var message = 'Import processing failed.';
                    if (result.json && result.json.message) message = result.json.message;
                    if (result.json && result.json.errors) {
                        var first = Object.values(result.json.errors)[0];
                        if (Array.isArray(first) && first[0]) message = first[0];
                    }
                    toast(message, 'danger');
                    return;
                }

                renderImportPreview(result.json.data);
                var created = (result.json.data.summary && result.json.data.summary.created_rows) || 0;
                toast(created + ' order(s) created from valid rows.', 'success');
            }).catch(function () {
                toast('Import processing failed.', 'danger');
            }).finally(function () {
                importState.processing = false;
                importSubmit.disabled = false;
            });
        });
    }
    var importClear = document.getElementById('orderImportClearBtn');
    if (importClear && importInput && importName) {
        importClear.addEventListener('click', function () {
            importInput.value = '';
            importName.textContent = 'No file selected.';
            resetImportPreview();
        });
    }
    var importTemplate = document.getElementById('orderImportTemplateBtn');
    if (importTemplate) {
        importTemplate.addEventListener('click', function () {
            if (!importState.urls || !importState.urls.template) {
                toast('Template endpoint is not available.', 'danger');
                return;
            }
            window.location.href = importState.urls.template;
        });
    }

    @include('pages.orders.partials.ui-call-center-live-scripts')

    initListWorkspace();

    function initListWorkspace() {
        var root = document.getElementById('orderUiListWorkspace');
        var bootstrapEl = document.getElementById('orderUiListMock');
        if (!root || !bootstrapEl) return;

        var data = JSON.parse(bootstrapEl.textContent);
        if (data.live && data.workspace === 'call-center') {
            initLiveCallCenterWorkspace(root, data);
            return;
        }

        initMockListWorkspace(root, data);
    }

    function initMockListWorkspace(root, data) {
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
            { value: 'PENDING_APPROVAL', label: 'Pending Approval' },
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
                        '<td>' + (order.courierName
                            ? ('<div class="customer-name">' + escapeHtml(order.courierName) + '</div>'
                                + (order.trackingId
                                    ? '<div class="order-sub">' + escapeHtml(order.trackingId) + '</div>'
                                    : ''))
                            : '<span class="order-sub">—</span>') + '</td>' +
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
@include('pages.orders.partials.ui-ban-scripts')
