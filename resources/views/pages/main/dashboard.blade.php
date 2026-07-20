@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="row">
            <div class="col-12">
                <div id="carouselExampleControlsNoTouching" class="carousel slide mb-4 position-relative"
                    data-bs-touch="false">
                    <a href="javascript:void(0);"
                        class="position-absolute top-0 end-0 d-inline-flex align-items-center justify-content-center rounded-circle text-white text-decoration-none"
                        style="width: 30px; height: 30px; background-color: #EF4923; margin: 12px; z-index: 3;"
                        aria-label="Close banner">
                        <i class="material-symbols-outlined fs-24"
                            style="font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 24;">close</i>
                    </a>
                    <div class="carousel-inner rounded-10">
                        <div class="carousel-item active" style="height: 170px;">
                            <img src="{{ asset('assets/images/product-details3.jpg') }}" class="d-block w-100 rounded-10"
                                alt="product-details" />
                        </div>
                        <div class="carousel-item" style="height: 170px;">
                            <img src="{{ asset('assets/images/product-details4.jpg') }}" class="d-block w-100 rounded-10"
                                alt="product-details" />
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControlsNoTouching"
                        data-bs-slide="prev" style="background: transparent; opacity: 1; width: 72px;">
                        <span class="d-flex align-items-center justify-content-center text-white" aria-hidden="true">
                            <i class="material-symbols-outlined fs-40"
                                style="font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 48;">chevron_left</i>
                        </span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControlsNoTouching"
                        data-bs-slide="next" style="background: transparent; opacity: 1; width: 72px;">
                        <span class="d-flex align-items-center justify-content-center text-white" aria-hidden="true">
                            <i class="material-symbols-outlined fs-40"
                                style="font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 48;">chevron_right</i>
                        </span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Orders Overview --}}
        <div class="row">
            {{-- Start Orders Overview --}}
            <div class="col-xxl-12 col-xxxxxl-12">
                <div class="card bg-white p-40 rounded-10 border-0 mb-4 position-relative z-1 quick-view-bg"
                    style="padding-top: 29px;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                        <h3 class="text-white fs-26">Orders Overview</h3>

                        <div class="dropdown action-opt text-center">
                            <button class="btn bg-transparent p-0" type="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="material-symbols-outlined fs-20 text-white">more_vert</i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow">
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Day
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Week
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Month
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:;">
                                        Last Year
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card bg-white rounded-10 border-0"
                        style="box-shadow: 0px 0px 10px 3px rgba(195, 195, 195, 0.5);">
                        <div class="row g-0">
                            <div class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Pending</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">2554</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-time-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-start border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Out Orders</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">5517</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-shopping-cart-2-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Printing
                                            </h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">5466</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-printer-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-end border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Packaging</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-archive-stack-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-end border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Dispatch
                                            </h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">212</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-truck-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-start border-border-color-90">
                                <div class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Out of Stock</h3>
                                            <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                            <div class="w-100 position-absolute top-50 translate-middle-y">
                                                <i class="ri-close-circle-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                    style="width: 70px; height: 70px; color: #FDE5E0; background-color: #EF4923;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- End Orders Overview --}}

            <div class="col-lg-6">
                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h3>Total Sales</h3>

                        <div class="dropdown select-dropdown without-border">
                            <button class="dropdown-toggle bg-transparent text-secondary fs-15" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                Year 2025
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                data-simplebar>
                                <li>
                                    <button class="dropdown-item text-secondary">
                                        Year 2025
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item text-secondary">
                                        Year 2025
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item text-secondary">
                                        Year 2023
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div id="total_sales_chart" style="margin-bottom: -16px; margin-top: -1.5px"></div>
                </div>
            </div>

            <div class="col-lg-6 col-xxl-3 col-xxxl-6">
                <div class="row">

                    <div class="col-md-6 col-lg-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <h3 class="mb-10">Total Sales Amount</h3>
                                    <h2 class="fs-26 fw-medium mb-0 lh-1">84,127</h2>
                                </div>
                                <div class="flex-shrink-0 ms-3">
                                    <div class="text-center rounded-circle d-block"
                                        style="width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;">
                                        <i class="material-symbols-outlined fs-40"
                                            style="color: #FFFFFF;">point_of_sale</i>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center" style="margin-top: 21px">
                                <p class="mb-0 fs-14">
                                    Total sales decreased by 1.25%
                                </p>
                                <span
                                    class="d-flex align-content-center gap-1 bg-danger bg-opacity-10 border border-danger"
                                    style="padding: 3px 5px">
                                    <i class="material-symbols-outlined fs-14 text-danger">trending_down</i>
                                    <span class="lh-1 fs-14 text-danger">1.25%</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <h3 class="mb-10">Ongoing Company Bonus</h3>
                                    <h2 class="fs-26 fw-medium mb-0 lh-1">20,705</h2>
                                </div>
                                <div class="flex-shrink-0 ms-3">
                                    <div class="text-center rounded-circle d-block"
                                        style="width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;">
                                        <i class="material-symbols-outlined fs-40" style="color: #FFFFFF;">payments</i>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center" style="margin-top: 21px">
                                <p class="mb-0 fs-15">
                                    475 Orders
                                </p>
                                <span
                                    class="d-flex align-content-center gap-1 bg-primary bg-opacity-10 border border-primary"
                                    style="padding: 3px 5px">
                                    <i class="material-symbols-outlined fs-14 text-primary">trending_up</i>
                                    <span class="lh-1 fs-14 text-primary">4.75%</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Target Progress --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="rounded-10" style="background:  #EF4923; margin-bottom: 16px; padding: 30px 30px;">
                    <div class="d-flex justify-content-between mb-10">
                        <h3 class="fs-20 text-white d-block">Complete 120 more orders to archieve the monthly
                            target</h3>
                    </div>
                    <div class="progress rounded-0" role="progressbar" aria-label="Basic example" aria-valuenow="75"
                        aria-valuemin="0" aria-valuemax="100" style="height: 10px; background-color: #D74422">
                        <div class="progress-bar rounded-0" style="width: 75%; height: 10px; background-color: #fff;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- End Monthly Target Progress --}}

        {{-- Best seller and items --}}
        <div class="row">
            <div class="col-md-6">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>Sellers Leaderboard</h3>

                                <div class="dropdown select-dropdown without-border">
                                    <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        This Week
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                        data-simplebar>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                This Day
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                This Week
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                This Month
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                This Year
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="default-table-area without-header table-top-sellers">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="text-body fw-medium">01.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user6.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="user6" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal mb-1">Mark Stjohn
                                                                <span class="fs-13 text-body">(#76431)</span>
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">342 Orders</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <h3 class="fs-16 fw-medium text-secondary mb-1">$128,400</h3>
                                                    <span
                                                        class="text-success bg-success bg-opacity-10 fs-13 fw-medium d-inline-block default-badge">Return:
                                                        1.8%</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">02.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user7.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="user7" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal mb-1">Joan Stanley
                                                                <span class="fs-13 text-body">(#64815)</span>
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">318 Orders</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <h3 class="fs-16 fw-medium text-secondary mb-1">$117,950</h3>
                                                    <span
                                                        class="text-success bg-success bg-opacity-10 fs-13 fw-medium d-inline-block default-badge">Return:
                                                        2.3%</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">03.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user8.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="user8" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal mb-1">Jacob Bell
                                                                <span class="fs-13 text-body">(#34581)</span>
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">286 Orders</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <h3 class="fs-16 fw-medium text-secondary mb-1">$104,720</h3>
                                                    <span
                                                        class="text-warning bg-warning bg-opacity-10 fs-13 fw-medium d-inline-block default-badge">Return:
                                                        3.1%</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">04.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user9.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="user9" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal mb-1">Donald Bryan
                                                                <span class="fs-13 text-body">(#67941)</span>
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">264 Orders</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <h3 class="fs-16 fw-medium text-secondary mb-1">$98,460</h3>
                                                    <span
                                                        class="text-warning bg-warning bg-opacity-10 fs-13 fw-medium d-inline-block default-badge">Return:
                                                        3.8%</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">05.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user10.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="user10" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal mb-1">Kristina Blomquist
                                                                <span class="fs-13 text-body">(#36985)</span>
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">239 Orders</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <h3 class="fs-16 fw-medium text-secondary mb-1">$90,315</h3>
                                                    <span
                                                        class="text-danger bg-danger bg-opacity-10 fs-13 fw-medium d-inline-block default-badge">Return:
                                                        4.6%</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pt-15">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 rounded-10"
                                        style="padding: 12px 14px; background: linear-gradient(90deg, rgba(239, 73, 35, 0.1), rgba(145, 154, 163, 0.14)); border: 1px solid rgba(239, 73, 35, 0.25);">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="ri-award-line fs-20" style="color: #EF4923;"></i>
                                            <span class="fs-15 fw-medium text-body">You're currently in <span
                                                    style="color: #EF4923;">34th rank</span> with <span
                                                    class="text-dark">345</span>
                                                orders and <span class="text-dark">128700</span> sales.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card p-20 bg-white rounded-10 border border-white mb-4 position-relative z-1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>New Arrivals</h3>

                                <span
                                    class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                    style="padding: 3px 5px">
                                    <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                    <span class="lh-1 fs-14 text-success">2.75%</span>
                                </span>
                            </div>
                            <div class="d-flex">
                                <h2 class="lh-1 fs-33 fw-medium"> 12</h2>
                            </div>
                            <div style="margin-top: 35px">
                                <ul class="p-0 mb-0 list-unstyled d-flex last-child-none global-right-list">
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user12.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user12" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user13.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user13" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user14.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user14" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user15.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user15" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user16.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user16" />
                                    </li>
                                    <li class="border border-3 border-white rounded-circle bg-primary text-center"
                                        style="margin-right: -20px;width: 60px;height: 60px;line-height: 49px; ">
                                        <span class="text-white fs-16 fw-medium">27</span>
                                    </li>
                                </ul>
                                <a href="/"><span class="fs-16 text-body d-block mt-2 mb-10">Check now</span></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="card p-20 bg-white rounded-10 border border-white mb-4 position-relative z-1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>New Stock</h3>

                                <span
                                    class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                    style="padding: 3px 5px">
                                    <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                    <span class="lh-1 fs-14 text-success">2.75%</span>
                                </span>
                            </div>
                            <div class="d-flex">
                                <h2 class="lh-1 fs-33 fw-medium"> 12</h2>
                            </div>
                            <div style="margin-top: 35px">
                                <ul class="p-0 mb-0 list-unstyled d-flex last-child-none global-right-list">
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user12.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user12" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user13.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user13" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user14.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user14" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user15.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user15" />
                                    </li>
                                    <li style="margin-right: -20px">
                                        <img src="{{ asset('assets/images/user16.jpg') }}"
                                            class="border border-3 border-white rounded-circle"
                                            style="width: 65px; height: 65px" alt="user16" />
                                    </li>
                                    <li class="border border-3 border-white rounded-circle bg-primary text-center"
                                        style="margin-right: -20px;width: 60px;height: 60px;line-height: 49px; ">
                                        <span class="text-white fs-16 fw-medium">27</span>
                                    </li>
                                </ul>
                                <a href="/"><span class="fs-16 text-body d-block mt-2 mb-10">Check now</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- End Best seller and items --}}

        {{-- Start My team card --}}
        <div class="row">
            <div class="col-12">
                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                    <h2 for="inviteLinkInput" class="d-block fs-20 fw-medium mb-4">My Team</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="rounded-10 p-12 h-100 align-items-center ">
                                <h4 class="mb-0 fs-35 fw-semibold">128</h4>
                                <span class="d-block fs-15 text-body mb-1">Team Members</span>

                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="rounded-10 p-12 h-100 align-items-center ">
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 fs-35 fw-semibold text-danger">96</h4>
                                    <a href="javascript:void(0);" class="text-danger" data-bs-toggle="modal"
                                        data-bs-target="#activeMembersModal" aria-label="View active members">
                                        <i class="ri-user-follow-line gap-3 fs-28"></i>
                                    </a>
                                </div>
                                <span class="d-block fs-15 text-body mb-1">Active Members</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="rounded-10 p-12 h-100 align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 fs-35 fw-semibold text-secondary">32</h4>
                                    <a href="javascript:void(0);" class="text-secondary" data-bs-toggle="modal"
                                        data-bs-target="#inactiveMembersModal" aria-label="View inactive members">
                                        <i class="ri-user-unfollow-line gap-3 fs-28"></i>
                                    </a>
                                </div>
                                <span class="d-block fs-15 text-body mb-1">Inactive Members</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-start">
                            <label for="inviteLinkInput" class="d-block fs-16 fw-medium">Add more members</label>
                        </div>
                        <div class="col-md-10 ">
                            <div class="input-group">
                                <input id="inviteLinkInput" type="text" class="form-control"
                                    value="{{ url('/register?invite=FEEDER2026') }}" readonly>
                                <button class="btn text-white" type="button" style="background-color: #EF4923;"
                                    onclick="navigator.clipboard.writeText(document.getElementById('inviteLinkInput').value)">
                                    Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- End My team card --}}

        <div class="row">
            <div class="col-xxl-6 col-xxxl-12">
                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap flex-md-nowrap gap-3 mb-20">
                        <h3 class="text-nowrap">Top Selling Products</h3>

                        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2 justify-content-start justify-content-md-end top-products-filter-controls"
                            style="width: 100%; flex: 1 1 auto;">
                            <style>
                                @media (max-width: 767px) {
                                    input[type="date"].form-control {
                                        width: 100% !important;
                                    }
                                }

                                @media (min-width: 768px) {
                                    input[type="date"].form-control {
                                        width: 160px;
                                    }

                                    input[type="text"].form-control {
                                        width: 220px;
                                    }
                                }
                            </style>
                            <form class="table-src-form position-relative">

                            </form>
                            <input type="text" class="form-control form-control-sm"
                                placeholder="Search products..." />
                            <input type="date" class="form-control form-control-sm"
                                aria-label="Top selling products start date" style="min-width: 140px;" />
                            <input type="date" class="form-control form-control-sm"
                                aria-label="Top selling products end date" style="min-width: 140px;" />
                            <button type="button" class="btn btn-primary text-white btn-sm top-products-filter-btn"
                                style="min-width: 88px;">Filter</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm top-products-reset-btn"
                                style="min-width: 88px;">Reset</button>
                        </div>
                    </div>

                    <div class="default-table-area without-header table-top-selling-products">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col" class="fw-medium text-nowrap"></th>
                                        <th scope="col" class="fw-medium text-nowrap">Name</th>
                                        <th scope="col" class="fw-medium text-nowrap">Delivered</th>
                                        <th scope="col" class="fw-medium text-nowrap">Returned</th>
                                        <th scope="col" class="fw-medium text-nowrap">Revenue</th>
                                        <th scope="col" class="fw-medium text-nowrap">Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap"
                                            style="padding-right: 0.35rem; width: 40px;">01.</td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product1.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product1" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">
                                                        Smart Watch
                                                    </h3>
                                                    <span class="fs-14 text-body fw-normal">953 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">953</td>
                                        <td class="text-body">45</td>
                                        <td class="text-body">$90,954</td>
                                        <td class="text-body">95%</td>
                                    </tr>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap" style="padding-right: 0.35rem;">02.
                                        </td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product2.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product2" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">
                                                        Mobile Phone
                                                    </h3>
                                                    <span class="fs-14 text-body fw-normal">876 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">876</td>
                                        <td class="text-body">34</td>
                                        <td class="text-body">$85,648</td>
                                        <td class="text-body">92%</td>
                                    </tr>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap" style="padding-right: 0.35rem;">03.
                                        </td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product3.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product3" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">
                                                        Laptop Device
                                                    </h3>
                                                    <span class="fs-14 text-body fw-normal">823 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">823</td>
                                        <td class="text-body">29</td>
                                        <td class="text-body">$79,852</td>
                                        <td class="text-body">90%</td>
                                    </tr>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap" style="padding-right: 0.35rem;">04.
                                        </td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product4.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product4" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">
                                                        Black T-Shirt
                                                    </h3>
                                                    <span class="fs-14 text-body fw-normal">743 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">743</td>
                                        <td class="text-body">27</td>
                                        <td class="text-body">$73,624</td>
                                        <td class="text-body">88%</td>
                                    </tr>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap" style="padding-right: 0.35rem;">05.
                                        </td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product5.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product5" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">Headphones</h3>
                                                    <span class="fs-14 text-body fw-normal">693 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">693</td>
                                        <td class="text-body">22</td>
                                        <td class="text-body">$65,973</td>
                                        <td class="text-body">85%</td>
                                    </tr>
                                    <tr>
                                        <td class="text-body fw-medium text-nowrap" style="padding-right: 0.35rem;">06.
                                        </td>
                                        <td class="text-start" style="padding-left: 0.35rem;">
                                            <a href="product-details.html"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/product6.png') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="product6" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-normal hover-text">Hand Watch</h3>
                                                    <span class="fs-14 text-body fw-normal">654 Items
                                                        Sold</span>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">693</td>
                                        <td class="text-body">22</td>
                                        <td class="text-body">$65,973</td>
                                        <td class="text-body">85%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15">
                            <span class="fs-15">Showing 1 to 5 of 50 entries</span>

                            <nav class="custom-pagination" aria-label="Page navigation example">
                                <ul class="pagination mb-0 justify-content-center">
                                    <li class="page-item">
                                        <button class="page-link icon" aria-label="Previous">
                                            <i class="material-symbols-outlined">west</i>
                                        </button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link active">1</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link">2</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link">3</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link icon" aria-label="Next">
                                            <i class="material-symbols-outlined">east</i>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xxl-8 col-xxxxl-12">
                <div class="card bg-white rounded-10 border border-white mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-20">
                        <h3>Couriers</h3>

                        <div class="d-flex align-items-center">

                            <form class="table-src-form position-relative">
                                <input type="text" class="form-control" placeholder="Search here..." />
                                <div
                                    class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                    <span class="material-symbols-outlined">search</span>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="default-table-area mx-minus-1 table-recent-orders">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col" class="fw-medium text-nowrap">Name</th>
                                        <th scope="col" class="fw-medium text-nowrap">Dispatch</th>
                                        <th scope="col" class="fw-medium text-nowrap">Return</th>
                                        <th scope="col" class="fw-medium text-nowrap">Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/user1.jpg') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="dhl" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-medium hover-text mb-0 fs-16">DHL Express</h3>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">40</td>
                                        <td class="text-body">3</td>
                                        <td class="text-body">85%</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/user2.jpg') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="fedex" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-medium hover-text mb-0 fs-16">FedEx</h3>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">36</td>
                                        <td class="text-body">4</td>
                                        <td class="text-body">86%</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/user3.jpg') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="ups" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-medium hover-text mb-0 fs-16">UPS</h3>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">33</td>
                                        <td class="text-body">2</td>
                                        <td class="text-body">88%</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/user4.jpg') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="usps" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-medium hover-text mb-0 fs-16">USPS</h3>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">30</td>
                                        <td class="text-body">3</td>
                                        <td class="text-body">87%</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="d-flex align-items-center text-decoration-none">
                                                <div class="flex-shrink-0">
                                                    <img src="{{ asset('assets/images/user5.jpg') }}"
                                                        class="rounded-circle" style="width: 50px; height: 50px"
                                                        alt="aramex" />
                                                </div>
                                                <div class="flex-grow-1 ms-12">
                                                    <h3 class="fw-medium hover-text mb-0 fs-16">Aramex</h3>
                                                </div>
                                            </a>
                                        </td>
                                        <td class="text-body">27</td>
                                        <td class="text-body">1</td>
                                        <td class="text-body">89%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                            <span class="fs-15">Showing 1 to 5 of 50 entries</span>

                            <nav class="custom-pagination" aria-label="Page navigation example">
                                <ul class="pagination mb-0 justify-content-center">
                                    <li class="page-item">
                                        <button class="page-link icon" aria-label="Previous">
                                            <i class="material-symbols-outlined">west</i>
                                        </button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link active">1</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link">2</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link">3</button>
                                    </li>
                                    <li class="page-item">
                                        <button class="page-link icon" aria-label="Next">
                                            <i class="material-symbols-outlined">east</i>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
@endsection
