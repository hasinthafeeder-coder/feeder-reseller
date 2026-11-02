@php
    $cards = $cards ?? [];
    $currencyCode = strtoupper((string) ($currencyCode ?? ''));
    $marketCode = strtolower((string) ($marketCode ?? ''));
    $marketSummaries = is_array($marketSummaries ?? null) ? $marketSummaries : [];
    $marketSummary = is_array($marketSummaries[$marketCode] ?? null) ? $marketSummaries[$marketCode] : null;
@endphp

<div class="row g-3 mb-4">
    @foreach ($cards as $card)
        <div class="col-12 col-md-6 col-xxl-3">
            <article
                class="card bg-white p-20 rounded-10 border border-white h-100 finance-today-card"
                id="{{ $marketCode !== '' ? $marketCode.'-' : '' }}{{ $card['id'] }}"
                title="{{ $card['caption'] }}"
            >
                @php
                    $amountKey = is_string($card['amount_key'] ?? null) ? $card['amount_key'] : '';
                    $amount = $amountKey !== '' && is_array($marketSummary) ? ($marketSummary[$amountKey] ?? null) : null;
                    $formatted = \App\Support\StoredMoneyDisplay::format($amount);
                @endphp
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1 min-w-0">
                        <h3 class="mb-10">{{ $card['title'] }}</h3>
                        @if ($currencyCode !== '')
                            <div class="fs-12 text-uppercase text-secondary">{{ $currencyCode }}</div>
                        @endif
                        @if ($formatted !== null)
                            <div class="fs-16 fw-medium mb-0 lh-sm text-body">{{ $formatted }}</div>
                        @else
                            <div class="fs-16 fw-medium mb-0 lh-sm text-body">Pending integration</div>
                        @endif
                    </div>
                    <div class="flex-shrink-0 ms-3">
                        <div
                            class="text-center rounded-circle d-block finance-today-icon"
                            style="width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;"
                            aria-hidden="true"
                        >
                            <i class="material-symbols-outlined fs-40" style="color: #FFFFFF;">{{ $card['icon'] }}</i>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 finance-today-foot">
                    <p class="mb-0 fs-14 text-body">{{ $card['foot'] }}</p>
                    @if ($currencyCode !== '')
                        <span
                            class="d-flex align-content-center gap-1 bg-primary bg-opacity-10 border border-primary flex-shrink-0 finance-today-chip"
                            title="This card is {{ $currencyCode }} only."
                        >
                            <span class="lh-1 fs-14 text-primary">{{ $currencyCode }}</span>
                        </span>
                    @endif
                </div>
            </article>
        </div>
    @endforeach
</div>
