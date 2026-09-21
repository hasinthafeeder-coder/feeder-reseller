@php
    $banName = $banName ?? '';
    $banPhone1 = $banPhone1 ?? '';
    $banPhone2 = $banPhone2 ?? '';
    $banUrl = $banUrl ?? '';
    $banMode = $banMode ?? 'order'; // order | catalog
@endphp
<div id="banUserModal"
    class="orders-proto-modal modal-backdrop-proto hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="banUserModalTitle"
    data-ban-url="{{ $banUrl }}"
    data-ban-mode="{{ $banMode }}">
    <div class="modal-panel-proto">
        <div class="modal-head">
            <h5 class="mb-0 fs-16" id="banUserModalTitle">Ban this Customer</h5>
        </div>
        <div class="modal-body">
            <p class="fs-14 text-body mb-3">
                This creates a global ban on the customer phone number(s). Future orders with these numbers will be blocked.
            </p>
            <div class="mb-3">
                <label class="label fs-14 mb-2" for="banCustomerName">Customer name</label>
                <input type="text" class="form-control" id="banCustomerName" value="{{ $banName }}" readonly>
            </div>
            <div class="mb-3">
                <label class="label fs-14 mb-2" for="banPhoneOne">Phone 1</label>
                <input type="text" class="form-control" id="banPhoneOne" value="{{ $banPhone1 }}" readonly>
            </div>
            <div class="mb-3">
                <label class="label fs-14 mb-2" for="banPhoneTwo">Phone 2</label>
                <input type="text" class="form-control" id="banPhoneTwo" value="{{ $banPhone2 }}" readonly>
            </div>
            <div class="mb-0">
                <label class="label fs-14 mb-2" for="banReason">Reason / description</label>
                <textarea class="form-control" id="banReason" rows="3" maxlength="1000"
                    placeholder="Why this customer should be banned" required></textarea>
                <div class="fs-12 text-body mt-1" id="banReasonError" hidden></div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-light border" data-close-modal="banUserModal">Cancel</button>
            <button type="button" class="btn btn-outline-danger" id="confirmBanUserBtn">Ban customer</button>
        </div>
    </div>
</div>
