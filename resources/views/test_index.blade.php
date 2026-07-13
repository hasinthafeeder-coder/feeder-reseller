@extends('layout_main.app')

@section('content')
    <div class="container-fluid">
        <div class="main-content d-flex flex-column">
            <!-- Start Header Area -->
            <header class="header-area bg-white mb-4 rounded-10 border border-white" id="header-area">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="left-header-content">
                            <ul
                                class="d-flex align-items-center ps-0 mb-0 list-unstyled justify-content-center justify-content-md-start">
                                <li class="d-xl-none">
                                    <button
                                        class="header-burger-menu bg-transparent p-0 border-0 position-relative top-3"
                                        id="header-burger-menu">
                                        <span class="border-1 d-block for-dark-burger" style="
                          border-bottom: 1px solid #475569;
                          height: 1px;
                          width: 25px;
                        "></span>
                                        <span class="border-1 d-block for-dark-burger" style="
                          border-bottom: 1px solid #475569;
                          height: 1px;
                          width: 25px;
                          margin: 6px 0;
                        "></span>
                                        <span class="border-1 d-block for-dark-burger" style="
                          border-bottom: 1px solid #475569;
                          height: 1px;
                          width: 25px;
                        "></span>
                                    </button>
                                </li>
                                <li>
                                    <form class="src-form position-relative">
                                        <input type="text" class="form-control" placeholder="Search here..." />
                                        <div
                                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                            <span class="material-symbols-outlined">search</span>
                                        </div>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="right-header-content mt-3 mt-md-0">
                            <ul
                                class="d-flex align-items-center justify-content-center justify-content-md-end ps-0 mb-0 list-unstyled">
                                <li class="header-right-item language-item">
                                    <div class="dropdown notifications language">
                                        <button class="btn btn-secondary dropdown-toggle border-0 p-0 position-relative"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined"
                                                style="font-size: 19px">translate</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 dropdown-menu-end">
                                            <span class="fw-medium fs-16 text-secondary d-block title"
                                                style="padding-top: 20px; padding-bottom: 20px">Choose Language</span>
                                            <div class="max-h-275" data-simplebar>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/usa.png') }}"
                                                                    class="wh-30 rounded-circle" alt="usa" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">English</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/australia.png') }}"
                                                                    class="wh-30 rounded-circle" alt="australia" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">Australia</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/spain.png') }}"
                                                                    class="wh-30 rounded-circle" alt="spain" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">Spanish</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/france.png') }}"
                                                                    class="wh-30 rounded-circle" alt="portugal" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">France</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu mb-0">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/germany.png') }}"
                                                                    class="wh-30 rounded-circle" alt="Germany" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">Spain</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item light-dark-item">
                                    <div class="light-dark">
                                        <button class="switch-toggle dark-btn p-0 bg-transparent lh-0 border-0"
                                            id="switch-toggle">
                                            <span class="dark"><i class="material-symbols-outlined">dark_mode</i></span>
                                            <span class="light"><i
                                                    class="material-symbols-outlined">light_mode</i></span>
                                        </button>
                                    </div>
                                </li>
                                <li class="header-right-item calendar-item">
                                    <div class="dropdown notifications">
                                        <a href="calendar.html"
                                            class="btn btn-secondary border-0 p-0 position-relative">
                                            <span class="material-symbols-outlined"
                                                style="font-size: 19px">calendar_today</span>
                                        </a>
                                    </div>
                                </li>
                                <li class="header-right-item messages-item">
                                    <div class="dropdown notifications noti messages">
                                        <button class="btn btn-secondary border-0 p-0 position-relative" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined">mail</span>
                                            <span class="count bg-primary">5</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 p-0 dropdown-menu-end">
                                            <div class="d-flex justify-content-between align-items-center title">
                                                <span class="fw-medium fs-16 text-secondary">Messages
                                                    <span class="fw-normal text-body fs-16">(03)</span></span>
                                                <button
                                                    class="p-0 m-0 bg-transparent border-0 fs-15 text-primary fw-medium">
                                                    Mark all as read
                                                </button>
                                            </div>

                                            <div style="max-height: 300px" data-simplebar>
                                                <div class="notification-menu unseen">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user1.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px" alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Jacob Liwiski
                                                                    <span class="fs-14 fw-normal text-body ms-2">35 min
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">Hey Victor! Could you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu unseen">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user2.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px" alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Angela Carter
                                                                    <span class="fs-14 fw-normal text-body ms-2">1 day
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">How are you Angela? Would
                                                                    you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user3.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px" alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Brad Traversy
                                                                    <span class="fs-14 fw-normal text-body ms-2">2 days
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">Hey Brad Traversy! Could
                                                                    you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>

                                            <a href="chat.html"
                                                class="dropdown-item text-center text-primary d-block view-all fw-medium rounded-bottom-3">
                                                <span>See All Messages</span>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item">
                                    <div class="dropdown notifications noti">
                                        <button class="btn btn-secondary border-0 p-0 position-relative" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined">notifications</span>
                                            <span class="count">3</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 p-0 dropdown-menu-end">
                                            <div class="d-flex justify-content-between align-items-center title">
                                                <span class="fw-medium fs-16 text-secondary">Notifications
                                                    <span class="fw-normal text-body fs-16">(03)</span></span>
                                                <button
                                                    class="p-0 m-0 bg-transparent border-0 fs-15 text-primary fw-medium">
                                                    Clear All
                                                </button>
                                            </div>

                                            <div style="max-height: 300px" data-simplebar>
                                                <div class="notification-menu unseen">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-primary">sms</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    You have requested to withdrawal
                                                                </p>
                                                                <span class="fs-14 fw-medium">2 hrs ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu unseen">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-info">person</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    A new user added in Fila
                                                                </p>
                                                                <span class="fs-14 fw-medium">3 hrs ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-success">mark_email_unread</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    You have requested to withdrawal
                                                                </p>
                                                                <span class="fs-14 fw-medium">1 day ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>

                                            <a href="notifications.html"
                                                class="dropdown-item text-center text-primary d-block view-all fw-medium rounded-bottom-3">
                                                <span>See All Notifications </span>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item">
                                    <div class="dropdown admin-profile">
                                        <div class="d-xxl-flex align-items-center bg-transparent border-0 text-start p-0 cursor dropdown-toggle"
                                            data-bs-toggle="dropdown">
                                            <div class="flex-shrink-0 position-relative">
                                                <img class="rounded-circle admin-img-width-for-mobile"
                                                    style="width: 40px; height: 40px" src="{{ asset('assets/images/admin.png') }}"
                                                    alt="admin" />
                                                <span
                                                    class="d-block bg-success-60 border border-2 border-white rounded-circle position-absolute end-0 bottom-0"
                                                    style="width: 11px; height: 11px"></span>
                                            </div>
                                        </div>

                                        <div class="dropdown-menu border-0 bg-white dropdown-menu-end">
                                            <div class="d-flex align-items-center info">
                                                <div class="flex-shrink-0">
                                                    <img class="rounded-circle admin-img-width-for-mobile"
                                                        style="width: 40px; height: 40px" src="{{ asset('assets/images/admin.png') }}"
                                                        alt="admin" />
                                                </div>
                                                <div class="flex-grow-1 ms-10">
                                                    <h3 class="fw-medium fs-17 mb-0">Mateo Luca</h3>
                                                    <span class="fs-15 fw-medium">Admin</span>
                                                </div>
                                            </div>
                                            <ul class="admin-link mb-0 list-unstyled">
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="my-profile.html">
                                                        <i class="material-symbols-outlined">person</i>
                                                        <span class="ms-2">My Profile</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="settings.html">
                                                        <i class="material-symbols-outlined">settings</i>
                                                        <span class="ms-2">Settings</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="tickets.html">
                                                        <i class="material-symbols-outlined">info</i>
                                                        <span class="ms-2">Support</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="logout.html">
                                                        <i class="material-symbols-outlined">logout</i>
                                                        <span class="ms-2">Logout</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <!-- End Header Area -->

            <div class="main-content-container overflow-hidden">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h3>Total Sales</h3>

                                <div class="dropdown select-dropdown without-border">
                                    <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                        data-bs-toggle="dropdown" aria-expanded="false">
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
                                            <h3 class="mb-10">Total Orders</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">20,705</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="bg-primary text-white text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 105px">
                                                <i class="material-symbols-outlined fs-40">shopping_basket</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 21px">
                                        <p class="mb-0 fs-14">
                                            4.75% Increase in orders last week
                                        </p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                            <span class="lh-1 fs-14 text-success">4.75%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Total Customers</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">84,127</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="bg-info text-white text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 105px">
                                                <i class="material-symbols-outlined fs-40">diversity_2</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 21px">
                                        <p class="mb-0 fs-14">
                                            Total visitors decreased by 1.25%
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
                        </div>
                    </div>

                    <div class="col-xl-12 col-xxl-3 col-xxxl-12">
                        <div class="row">
                            <div class="col-md-6 col-xxxl-6 col-xxl-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Total Revenue</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">$15,278</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="bg-warning text-white text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 116px">
                                                <i class="material-symbols-outlined fs-50">attach_money</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 23px">
                                        <p class="mb-0 fs-14">Revenue increases this month</p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                            <span class="lh-1 fs-14 text-success">3.15%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xxxl-6 col-xxl-12">
                                <div class="bg-primary-50 p-20 border rounded-10 border-primary-50 mb-4">
                                    <h3 class="text-white mb-12">Sales Overview</h3>
                                    <div class="d-flex flex-wrap gap-2 justify-content-between mb-14">
                                        <div>
                                            <span class="fs-14 text-white mb-1 d-block">Total Sales</span>
                                            <h2 class="fs-20 fw-medium lh-1 text-white mb-0">
                                                9,586
                                            </h2>
                                        </div>
                                        <div>
                                            <span class="fs-14 text-white mb-1 d-block">Monthly Sales</span>
                                            <h2 class="fs-20 fw-medium lh-1 text-white mb-0">
                                                3,507
                                            </h2>
                                        </div>
                                        <div>
                                            <span class="fs-14 text-white mb-1 d-block">Today’s Sales</span>
                                            <h2 class="fs-20 fw-medium lh-1 text-white mb-0">
                                                357
                                            </h2>
                                        </div>
                                    </div>
                                    <div class="progress rounded-0 mb-6" role="progressbar" aria-label="Basic example"
                                        aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"
                                        style="height: 3px; background-color: #6258cc">
                                        <div class="progress-bar rounded-0 bg-white" style="width: 80%; height: 3px">
                                        </div>
                                    </div>
                                    <span class="fs-14 text-white d-block" style="margin-bottom: -6px">20% Increase in
                                        last month</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xxl-6 col-xxxl-12">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div
                                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                        <h3>Profit</h3>

                                        <div class="dropdown select-dropdown without-border">
                                            <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                Last Month
                                            </button>

                                            <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                                data-simplebar>
                                                <li>
                                                    <button class="dropdown-item text-secondary">
                                                        Last Day
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-secondary">
                                                        Last Week
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-secondary">
                                                        Last Month
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-secondary">
                                                        Last Year
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <h2 class="lh-1 fs-26 fw-medium" style="margin-bottom: -10px">
                                        $359K
                                    </h2>

                                    <div id="profit_chart" style="margin-bottom: -17px"></div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div
                                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                        <h3>Average Daily Sales</h3>

                                        <span
                                            class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                            <span class="lh-1 fs-14 text-success">5.25%</span>
                                        </span>
                                    </div>

                                    <h2 class="lh-1 fs-26 fw-medium mb-0">$5K+</h2>

                                    <div id="average_daily_sales_chart" style="margin-bottom: -17px"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    class="card p-20 bg-light-40 rounded-10 border-light-40 mb-4 position-relative z-1">
                                    <h3 class="mb-20">Best Seller Of The Month</h3>
                                    <h3 class="mb-12 text-primary-50">Michael Marquez!</h3>
                                    <h2 class="lh-1 fs-26 fw-medium">
                                        $3.5K<span class="fs-16 text-body">(Sales)</span>
                                    </h2>
                                    <a href="#" class="fw-medium fs-16 text-secondary hover-text d-inline-block"
                                        style="margin-top: 84px">Details View</a>
                                    <img src="{{ asset('assets/images/man.png') }}" class="position-absolute bottom-0 end-0 pe-3"
                                        alt="man" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    class="card p-20 bg-white rounded-10 border border-white mb-4 position-relative z-1">
                                    <div
                                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                        <h3>New Customers This Month</h3>

                                        <span
                                            class="d-flex align-content-center gap-1 bg-success bg-opacity-10 border border-success"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-success">trending_up</i>
                                            <span class="lh-1 fs-14 text-success">2.75%</span>
                                        </span>
                                    </div>
                                    <h2 class="lh-1 fs-26 fw-medium">2,537</h2>
                                    <div style="margin-top: 55px">
                                        <span class="fs-16 text-body d-block mb-10">Join Today</span>
                                        <ul class="p-0 mb-0 list-unstyled d-flex last-child-none global-right-list">
                                            <li style="margin-right: -20px">
                                                <img src="{{ asset('assets/images/user12.jpg') }}"
                                                    class="border border-3 border-white rounded-circle"
                                                    style="width: 52px; height: 52px" alt="user12" />
                                            </li>
                                            <li style="margin-right: -20px">
                                                <img src="{{ asset('assets/images/user13.jpg') }}"
                                                    class="border border-3 border-white rounded-circle"
                                                    style="width: 52px; height: 52px" alt="user13" />
                                            </li>
                                            <li style="margin-right: -20px">
                                                <img src="{{ asset('assets/images/user14.jpg') }}"
                                                    class="border border-3 border-white rounded-circle"
                                                    style="width: 52px; height: 52px" alt="user14" />
                                            </li>
                                            <li style="margin-right: -20px">
                                                <img src="{{ asset('assets/images/user15.jpg') }}"
                                                    class="border border-3 border-white rounded-circle"
                                                    style="width: 52px; height: 52px" alt="user15" />
                                            </li>
                                            <li style="margin-right: -20px">
                                                <img src="{{ asset('assets/images/user16.jpg') }}"
                                                    class="border border-3 border-white rounded-circle"
                                                    style="width: 52px; height: 52px" alt="user16" />
                                            </li>
                                            <li class="border border-3 border-white rounded-circle bg-primary text-center"
                                                style="
                            margin-right: -20px;
                            width: 52px;
                            height: 52px;
                            line-height: 49px;
                          ">
                                                <span class="text-white fs-16 fw-medium">27</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6 col-xxxl-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>Top Selling Products</h3>

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

                            <div class="default-table-area without-header table-top-selling-products">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="text-body fw-medium">01.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product1.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product1" />
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
                                                <td class="text-body">Item: #ARP-1217</td>
                                                <td class="text-body">$90,954</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">02.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product2.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product2" />
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
                                                <td class="text-body">Item: #ARP-9513</td>
                                                <td class="text-body">$85,648</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">03.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product3.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product3" />
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
                                                <td class="text-body">Item: #ARP-7531</td>
                                                <td class="text-body">$79,852</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">04.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product4.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product4" />
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
                                                <td class="text-body">Item: #ARP-3579</td>
                                                <td class="text-body">$73,624</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">05.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product5.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product5" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">Headphones</h3>
                                                            <span class="fs-14 text-body fw-normal">693 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">Item: #ARP-4826</td>
                                                <td class="text-body">$65,973</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">06.</td>
                                                <td class="ps-0">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product6.png') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="product6" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">Hand Watch</h3>
                                                            <span class="fs-14 text-body fw-normal">654 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">Item: #ARP-1265</td>
                                                <td class="text-body">$42,455</td>
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
                    <div class="col-lg-6 col-xxl-4 col-xxxl-6 mb-4">
                        <div class="card bg-white p-20 rounded-10 border border-white">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>Order Summary</h3>

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

                            <div class="d-flex justify-content-between align-items-center mb-20">
                                <span class="fs-15 text-secondary">Completed 60%</span>
                                <span class="fs-15 text-secondary">New Order 30%</span>
                                <span class="fs-15 text-secondary">Pending 10%</span>
                            </div>

                            <div id="order_summary_chart"></div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xxl-4 col-xxxl-6">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>Top Sellers</h3>

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
                                                            <img src="{{ asset('assets/images/user6.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user6" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Mark Stjohn</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #76431</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(5)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">02.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user7.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user7" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Joan Stanley</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #64815</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-half-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(4.5)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">03.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user8.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user8" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Jacob Bell</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #34581</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-line text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(4)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">04.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user9.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user9" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Donald Bryan</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #67941</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(5)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium">05.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user10.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user10" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Kristina Blomquist</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #36985</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(5)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr class="last-child-border-none">
                                                <td class="text-body fw-medium">06.</td>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user11.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user11" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal">Jeffrey Morrison</h3>
                                                            <span class="fs-14 text-body fw-normal">Customer ID
                                                                #26985</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <ul class="p-0 mb-0 list-unstyled d-flex align-items-center justify-content-end"
                                                        style="gap: 2px">
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-half-fill text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <i class="ri-star-line text-warning fs-16"></i>
                                                        </li>
                                                        <li>
                                                            <span class="fs-14 text-body fw-normal">(3.5)</span>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12 col-xxl-4 col-xxxl-12">
                        <div class="row">
                            <div class="col-xxl-12 col-lg-6 col-xxxl-6">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <h3 class="mb-20">Top Sales Locations</h3>
                                    <div class="row align-items-center">
                                        <div class="col-lg-7 col-sm-6">
                                            <div class="text-center">
                                                <div id="sales_by_locations_map"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-5 col-sm-6">
                                            <ul class="p-0 mb-0 list-unstyled last-child-none mt-4 mt-sm-0">
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/usa.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="usa" />
                                                    <span class="fs-15">United States
                                                        <span class="text-secondary">85%</span></span>
                                                </li>
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/china.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="china" />
                                                    <span class="fs-15">China
                                                        <span class="text-secondary">60%</span></span>
                                                </li>
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/australia.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="australia" />
                                                    <span class="fs-15">Australia
                                                        <span class="text-secondary">85%</span></span>
                                                </li>
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/germany.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="germany" />
                                                    <span class="fs-15">Germany
                                                        <span class="text-secondary">75%</span></span>
                                                </li>
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/canada.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="canada" />
                                                    <span class="fs-15">Canada
                                                        <span class="text-secondary">80%</span></span>
                                                </li>
                                                <li class="d-flex align-items-center gap-2 mb-9">
                                                    <img src="{{ asset('assets/images/france.png') }}" class="rounded-circle"
                                                        style="width: 20px; height: 20px" alt="france" />
                                                    <span class="fs-15">France
                                                        <span class="text-secondary">65%</span></span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-12 col-lg-6 col-xxxl-6">
                                <div class="card bg-white p-20 rounded-10 border border border-white mb-4">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-0">
                                        <h3>Revenue</h3>

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

                                    <div id="revenue_chart" style="margin-bottom: -17px"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xxl-8 col-xxxxl-12">
                        <div class="card bg-white rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-20">
                                <h3>Recent Orders</h3>

                                <div class="d-flex align-items-center">
                                    <div class="dropdown select-dropdown without-border">
                                        <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Show All
                                        </button>

                                        <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                            data-simplebar>
                                            <li>
                                                <button class="dropdown-item text-secondary">
                                                    Shipped
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-secondary">
                                                    Confirmed
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-secondary">
                                                    Pending
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-secondary">
                                                    Rejected
                                                </button>
                                            </li>
                                        </ul>
                                    </div>

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
                                                <th scope="col" class="fw-medium">Order ID</th>
                                                <th scope="col" class="fw-medium">Customer</th>
                                                <th scope="col" class="fw-medium">Created</th>
                                                <th scope="col" class="fw-medium">Total</th>
                                                <th scope="col" class="fw-medium">Profit</th>
                                                <th scope="col" class="fw-medium">Status</th>
                                                <th scope="col" class="fw-medium">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-body">#ARP-1217</td>
                                                <td>
                                                    <a href="customers.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user1.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user1" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">
                                                                Carlos Daley
                                                            </h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">15 Nov, 2025</td>
                                                <td class="text-body">$9,095</td>
                                                <td class="text-body">$1,254</td>
                                                <td>
                                                    <span
                                                        class="text-primary bg-primary bg-opacity-10 fs-15 fw-normal d-inline-block default-badge">Shipped</span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-end" style="gap: 12px">
                                                        <button class="bg-transparent p-0 border-0"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="View">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                        </button>
                                                        <button class="bg-transparent p-0 border-0 hover-text-danger"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Delete">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body">#ARP-9513</td>
                                                <td>
                                                    <a href="customers.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user2.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user2" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">
                                                                Dorothy Young
                                                            </h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">14 Nov, 2025</td>
                                                <td class="text-body">$8,564</td>
                                                <td class="text-body">$973</td>
                                                <td>
                                                    <span
                                                        class="text-success bg-success bg-opacity-10 fs-15 fw-normal d-inline-block default-badge">Confirmed</span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-end" style="gap: 12px">
                                                        <button class="bg-transparent p-0 border-0"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="View">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                        </button>
                                                        <button class="bg-transparent p-0 border-0 hover-text-danger"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Delete">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body">#ARP-7513</td>
                                                <td>
                                                    <a href="customers.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user3.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user3" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">
                                                                Greg Woody
                                                            </h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">13 Nov, 2025</td>
                                                <td class="text-body">$7,985</td>
                                                <td class="text-body">$852</td>
                                                <td>
                                                    <span
                                                        class="text-warning bg-warning bg-opacity-10 fs-15 fw-normal d-inline-block default-badge">Pending</span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-end" style="gap: 12px">
                                                        <button class="bg-transparent p-0 border-0"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="View">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                        </button>
                                                        <button class="bg-transparent p-0 border-0 hover-text-danger"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Delete">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body">#ARP-3579</td>
                                                <td>
                                                    <a href="customers.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user4.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user4" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">
                                                                Deborah Rosol
                                                            </h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">12 Nov, 2025</td>
                                                <td class="text-body">$7,362</td>
                                                <td class="text-body">$793</td>
                                                <td>
                                                    <span
                                                        class="text-danger bg-danger bg-opacity-10 fs-15 fw-normal d-inline-block default-badge">Rejected</span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-end" style="gap: 12px">
                                                        <button class="bg-transparent p-0 border-0"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="View">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                        </button>
                                                        <button class="bg-transparent p-0 border-0 hover-text-danger"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Delete">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-body">#ARP-4826</td>
                                                <td>
                                                    <a href="customers.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user5.jpg') }}" class="rounded-circle"
                                                                style="width: 50px; height: 50px" alt="user1" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">
                                                                Kendall Allen
                                                            </h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">11 Nov, 2025</td>
                                                <td class="text-body">$6,597</td>
                                                <td class="text-body">$674</td>
                                                <td>
                                                    <span
                                                        class="text-primary bg-primary bg-opacity-10 fs-15 fw-normal d-inline-block default-badge">Shipped</span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-end" style="gap: 12px">
                                                        <button class="bg-transparent p-0 border-0"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="View">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-primary">visibility</i>
                                                        </button>
                                                        <button class="bg-transparent p-0 border-0 hover-text-danger"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Delete">
                                                            <i
                                                                class="material-symbols-outlined fs-16 fw-normal text-body">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
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
                    <div class="col-xxl-4 col-xxxxl-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3>Transactions History</h3>

                                <div class="dropdown select-dropdown without-border">
                                    <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        Last Month
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                        data-simplebar>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Last Day
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Last Week
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Last Month
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Last Year
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="default-table-area without-header table-transactions-history">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-primary text-center rounded-circle" style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #dbeafd;
                                  ">
                                                                <i
                                                                    class="material-symbols-outlined fs-24">settings_backup_restore</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Refund Bill payment</h3>
                                                            <span class="fs-14 text-body fw-normal">15 Nov 2025 -
                                                                11:40am</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-success fs-16">+$995</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-danger text-center rounded-circle" style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #fce4e2;
                                  ">
                                                                <i
                                                                    class="material-symbols-outlined fs-24">account_balance</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Bank Transfer</h3>
                                                            <span class="fs-14 text-body fw-normal">15 Nov 2025 -
                                                                8:20am</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-danger fs-16">-$1,550</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-primary-50 text-center rounded-circle"
                                                                style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #ebe9fe;
                                  ">
                                                                <i
                                                                    class="material-symbols-outlined fs-24">credit_card</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Master Card</h3>
                                                            <span class="fs-14 text-body fw-normal">14 Nov 2025 -
                                                                11:40am</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-success fs-16">+$862</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-info text-center rounded-circle" style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #daf7fb;
                                  ">
                                                                <i
                                                                    class="material-symbols-outlined fs-24">account_balance_wallet</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Wallet</h3>
                                                            <span class="fs-14 text-body fw-normal">10 Nov 2025 -
                                                                10:10am</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-success fs-16">+$974</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-warning text-center rounded-circle" style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #fff4e8;
                                  ">
                                                                <i
                                                                    class="material-symbols-outlined fs-24">attach_money</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Cash Withdrawal</h3>
                                                            <span class="fs-14 text-body fw-normal">09 Nov 2025 -
                                                                1:30pm</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-danger fs-16">-$250</td>
                                            </tr>
                                            <tr>
                                                <td class="ps-0">
                                                    <div class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <div class="text-success-60 text-center rounded-circle"
                                                                style="
                                    width: 50px;
                                    height: 50px;
                                    line-height: 62px;
                                    background-color: #e0f8ea;
                                  ">
                                                                <i class="material-symbols-outlined fs-24">payments</i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1 ms-15">
                                                            <h3 class="fw-normal">Payment</h3>
                                                            <span class="fs-14 text-body fw-normal">8 Nov 2025 -
                                                                12:34pm</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fw-normal text-danger fs-16">-$657</td>
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
            </div>

            <div class="flex-grow-1"></div>

            <!-- Start Footer Area -->
            <footer class="footer-area bg-white text-center rounded-10 rounded-bottom-0">
                <p class="fs-16 text-body">
                    © <span class="text-secondary">Fila</span> is Proudly Owned by
                    <a href="https://envytheme.com/" target="_blank"
                        class="text-decoration-none text-primary">EnvyTheme</a>
                </p>
            </footer>
            <!-- End Footer Area -->
        </div>
    </div>
@endsection
