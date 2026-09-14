<?php

/**
 * UI architecture URL / workspace bootstrap.
 * Returned array is extract()'d into the calling Blade view so variables stay in parent scope.
 * Blade @include cannot leak @php locals back to the parent.
 */

return [
    'orderUiUrls' => [
        'create' => route('orders.create'),
        'new' => route('orders.index', ['workspace' => 'new']),
        'import' => route('orders.index', ['workspace' => 'import']),
        'call_center' => route('orders.index', ['workspace' => 'call-center']),
        'ongoing' => route('orders.index'),
        'archived' => route('orders.index', ['workspace' => 'archived', 'archive' => 'completed']),
        'archived_completed' => route('orders.index', ['workspace' => 'archived', 'archive' => 'completed']),
        'archived_returned' => route('orders.index', ['workspace' => 'archived', 'archive' => 'returned']),
        'archived_expired' => route('orders.index', ['workspace' => 'archived', 'archive' => 'expired']),
        'confirm' => route('orders.create', ['ui_screen' => 'confirm']),
        'hold' => route('orders.create', ['ui_screen' => 'hold']),
        'expiring' => route('orders.create', ['ui_screen' => 'expiring']),
        'expired' => route('orders.create', ['ui_screen' => 'expired']),
        'view' => route('orders.create', ['ui_screen' => 'view']),
    ],
    'orderUiWorkspace' => request('workspace'),
    'orderUiArchive' => request('archive', 'completed'),
    'orderUiScreen' => request('ui_screen'),
    'orderUiIsArchitectureList' => in_array(request('workspace'), ['new', 'import', 'call-center', 'archived'], true),
];
