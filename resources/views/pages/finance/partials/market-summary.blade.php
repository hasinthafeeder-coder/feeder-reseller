@php
    $overviewMarkets = $overviewMarkets ?? collect();
    $overviewMarketsUnavailable = $overviewMarketsUnavailable ?? false;
    $cards = $cards ?? [];
    $marketSummaries = $marketSummaries ?? [];
@endphp

@if ($overviewMarketsUnavailable)
    <div class="card bg-white p-20 rounded-10 border border-white mb-4" id="financialOverviewMarketsEmpty">
        <p class="mb-0 fs-15 text-body">Active markets could not be loaded. Summary cards are not shown.</p>
    </div>
@elseif ($overviewMarkets->isEmpty())
    <div class="card bg-white p-20 rounded-10 border border-white mb-4" id="financialOverviewMarketsEmpty">
        <p class="mb-0 fs-15 text-body">No eligible Sri Lanka or Malaysia market is available for your account. Summary cards are not shown.</p>
    </div>
@else
    @foreach ($overviewMarkets as $market)
        @php
            $marketCode = strtolower((string) $market->code);
            $currencyCode = strtoupper((string) ($market->currency->iso_code ?? ''));
        @endphp
        <section class="mb-1" id="financial-overview-market-{{ $marketCode }}" aria-labelledby="financial-overview-market-{{ $marketCode }}-heading">
            <h4 class="fs-16 mb-3" id="financial-overview-market-{{ $marketCode }}-heading">{{ $market->name }}</h4>
            @include('pages.finance.partials.summary-cards', [
                'cards' => $cards,
                'currencyCode' => $currencyCode,
                'marketCode' => $marketCode,
                'marketSummaries' => $marketSummaries,
            ])
            @php
                $marketSummary = is_array($marketSummaries[$marketCode] ?? null) ? $marketSummaries[$marketCode] : [];
                $notices = is_array($marketSummary['notices'] ?? null) ? $marketSummary['notices'] : [];
            @endphp
            @if ($notices !== [])
                <div class="mb-4" id="financial-overview-notices-{{ $marketCode }}">
                    @foreach ($notices as $notice)
                        @if (is_string($notice) && $notice !== '')
                            <p class="fs-13 text-body mb-2">{{ $notice }}</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
@endif
