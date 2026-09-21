{{-- Shared Ban Customer submit handler (order show + create/edit forms) --}}
<script>
(function () {
    'use strict';

    function banToast(message, type) {
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
        window.clearTimeout(banToast._t);
        banToast._t = window.setTimeout(function () { el.classList.add('hidden'); }, 2800);
    }

    function banCloseModal() {
        var modal = document.getElementById('banUserModal');
        if (modal) modal.classList.add('hidden');
    }

    function banCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function submitCustomerBan() {
        var modal = document.getElementById('banUserModal');
        var reasonEl = document.getElementById('banReason');
        var reasonError = document.getElementById('banReasonError');
        var confirmBtn = document.getElementById('confirmBanUserBtn');
        if (!modal) return;

        var banUrl = modal.getAttribute('data-ban-url') || '';
        var banMode = modal.getAttribute('data-ban-mode') || 'order';
        var reason = reasonEl ? String(reasonEl.value || '').trim() : '';

        if (reasonError) {
            reasonError.hidden = true;
            reasonError.textContent = '';
        }

        if (!banUrl) {
            banToast('Ban is unavailable. You may not have permission.', 'warning');
            return;
        }

        if (reason.length < 3) {
            if (reasonError) {
                reasonError.hidden = false;
                reasonError.className = 'fs-12 text-danger mt-1';
                reasonError.textContent = 'Please enter a ban reason (at least 3 characters).';
            } else {
                banToast('Please enter a ban reason (at least 3 characters).', 'warning');
            }
            if (reasonEl) reasonEl.focus();
            return;
        }

        var body = { reason: reason };

        if (banMode === 'catalog') {
            var marketChecked = document.querySelector('input[name="market_id"]:checked');
            var marketSelect = document.getElementById('marketId');
            var marketId = marketChecked
                ? marketChecked.value
                : (marketSelect ? marketSelect.value : '');
            var nameEl = document.getElementById('customerName') || document.getElementById('banCustomerName');
            var phone1El = document.getElementById('primaryPhone') || document.getElementById('banPhoneOne');
            var phone2El = document.getElementById('secondaryPhone') || document.getElementById('banPhoneTwo');

            if (!marketId) {
                banToast('Select a market before banning a customer.', 'warning');
                return;
            }
            if (!phone1El || !String(phone1El.value || '').trim()) {
                banToast('Phone 1 is required to ban a customer.', 'warning');
                return;
            }

            body.market_id = Number(marketId);
            body.customer_name = nameEl ? String(nameEl.value || '').trim() : '';
            body.primary_phone = String(phone1El.value || '').trim();
            body.secondary_phone = phone2El ? String(phone2El.value || '').trim() : '';
            if (!body.customer_name) {
                banToast('Customer name is required to ban.', 'warning');
                return;
            }
        }

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Banning...';
        }

        fetch(banUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': banCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (payload) {
                    return { ok: res.ok, status: res.status, payload: payload };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var msg = (result.payload && result.payload.message)
                        || (result.payload && result.payload.errors
                            ? Object.values(result.payload.errors).flat().join(' ')
                            : 'Unable to ban customer.');
                    throw new Error(msg);
                }

                banCloseModal();
                if (reasonEl) reasonEl.value = '';
                banToast(result.payload.message || 'Customer banned successfully.', 'success');
                window.setTimeout(function () { window.location.reload(); }, 700);
            })
            .catch(function (error) {
                banToast(error.message || 'Unable to ban customer.', 'danger');
            })
            .finally(function () {
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Ban customer';
                }
            });
    }

    var confirmBan = document.getElementById('confirmBanUserBtn');
    if (confirmBan && !confirmBan.dataset.banBound) {
        confirmBan.dataset.banBound = '1';
        confirmBan.addEventListener('click', submitCustomerBan);
    }
})();
</script>
