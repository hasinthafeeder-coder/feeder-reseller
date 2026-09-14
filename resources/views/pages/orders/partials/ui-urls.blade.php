{{--
    Deprecated include wrapper — prefer extract(require ...) in the parent view.
    Kept only so any leftover @include still bootstraps via share for later views.
--}}
@php
    extract(require resource_path('views/pages/orders/partials/ui-urls-data.php'));
    view()->share(compact(
        'orderUiUrls',
        'orderUiWorkspace',
        'orderUiArchive',
        'orderUiScreen',
        'orderUiIsArchitectureList'
    ));
@endphp
