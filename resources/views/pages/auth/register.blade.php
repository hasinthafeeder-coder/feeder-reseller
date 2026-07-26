<!DOCTYPE html>
<html lang="zxx">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Links Of CSS File -->
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-menu.css') }} ">
    <link rel="stylesheet" href="{{ asset('assets/css/simplebar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/prism.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/jsvectormap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">

    <style>
        .register-shell {
            min-height: 100vh;
        }

        .register-card {
            min-height: auto;
            height: auto;
            padding-top: 52px !important;
        }

        .register-content {
            min-height: auto;
            height: auto;
        }

        .wizard-tabs2 .nav-link {
            flex: 1 1 220px;
            padding: 0;
        }

        .mobile-step-summary {
            display: none;
        }

        .wizard-step {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .wizard-step:not(:last-child)::after {
            content: "";
            position: absolute;
            top: 24px;
            left: calc(100% + 6px);
            width: calc(100% - 12px);
            height: 3px;
            background: rgba(239, 73, 35, 0.16);
            z-index: 0;
        }

        .wizard-step.active-step:not(:last-child)::after,
        .wizard-step.completed-step:not(:last-child)::after {
            background: #EF4923;
        }

        .wizard-step .nav-link {
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .wizard-step .step-number {
            transition: all .2s ease;
            background: transparent !important;
            box-shadow: none !important;
        }

        .wizard-step.active-step .step-number,
        .wizard-step.completed-step .step-number {
            color: #EF4923 !important;
        }

        .wizard-step.active-step h4,
        .wizard-step.completed-step h4 {
            color: #EF4923;
        }

        .form-control-icon {
            padding-left: 3rem;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 18px;
            transform: translateY(-50%);
            color: #A9A9C8;
            font-size: 20px;
            pointer-events: none;
        }

        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #A9A9C8;
            padding: 0;
        }

        .upload-square {
            position: relative;
            border: 1px dashed rgba(239, 73, 35, 0.25);
            border-radius: 18px;
            background: #fff7f4;
            min-height: 250px;
            padding: 18px;
            position: relative;
            aspect-ratio: 1 / 1;
            width: 100%;
            border-radius: 16px;
            border: 1px solid rgba(239, 73, 35, 0.14);
            overflow: hidden;
            background: linear-gradient(180deg, rgba(239, 73, 35, 0.05), rgba(239, 73, 35, 0.01));
        }

        .upload-overlay {
            position: absolute;
            inset: 0;
            z-index: 2;
            padding: 14px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            pointer-events: none;
        }

        .upload-chip {
            position: relative;
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(239, 73, 35, 0.2);
            box-shadow: 0 10px 24px rgba(30, 41, 59, 0.08);
            color: #EF4923;
            font-weight: 600;
        }

        .upload-chip input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-square img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .upload-square.has-preview img {
            display: block;
        }

        .upload-square.has-preview .upload-empty-state {
            display: none;
        }

        .upload-square.has-preview .upload-overlay {
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.04), rgba(17, 24, 39, 0));
        }

        .upload-empty-state {
            min-height: 100%;
        }

        .btn-primary {
            background-color: #EF4923;
            border-color: #EF4923;
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: #d9411d !important;
            border-color: #d9411d !important;
            color: #fff !important;
            box-shadow: 0 0 0 .2rem rgba(239, 73, 35, 0.18) !important;
        }

        .btn-outline-secondary:hover,
        .btn-outline-secondary:focus,
        .btn-outline-secondary:active {
            background-color: rgba(239, 73, 35, 0.08) !important;
            border-color: #EF4923 !important;
            color: #EF4923 !important;
            box-shadow: 0 0 0 .2rem rgba(239, 73, 35, 0.14) !important;
        }

        .otp-step,
        .password-step,
        .step-next-wrap {
            display: none;
        }

        .otp-step.is-visible,
        .password-step.is-visible,
        .step-next-wrap.is-visible {
            display: block;
        }

        .step-actions {
            gap: 12px;
            justify-content: flex-end;
        }

        .completed-state .mobile-step-summary {
            display: none !important;
        }

        #myTabstep2Content .form-group.d-flex.gap-3 {
            justify-content: flex-end;
        }

        .auth-theme-toggle {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1050;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #EF4923;
            border: 0;
            box-shadow: 0 12px 24px rgba(239, 73, 35, 0.22);
            color: #fff;
            transition: transform .2s ease, background-color .2s ease, box-shadow .2s ease;
        }

        .auth-theme-toggle:hover,
        .auth-theme-toggle:focus {
            background: #d9411d;
            box-shadow: 0 14px 28px rgba(239, 73, 35, 0.28);
            transform: translateY(-1px);
            color: #fff;
        }

        .auth-theme-toggle .material-symbols-outlined {
            font-size: 26px;
            line-height: 1;
        }

        [data-theme="dark"] .auth-theme-toggle {
            background: #F4A261;
            box-shadow: 0 12px 24px rgba(244, 162, 97, 0.22);
        }

        [data-theme="dark"] .auth-theme-toggle:hover,
        [data-theme="dark"] .auth-theme-toggle:focus {
            background: #f08f3e;
            box-shadow: 0 14px 28px rgba(244, 162, 97, 0.28);
        }

        @media (max-width: 991px) {
            .register-card {
                min-height: auto;
            }

            .register-content {
                min-height: auto;
            }

            .wizard-tabs2 {
                display: none !important;
            }

            .mobile-step-summary {
                display: block;
                margin-bottom: 1rem;
            }

            .wizard-step:not(:last-child)::after {
                display: none;
            }

            .wizard-tabs2 .nav-link {
                flex: 1 1 100%;
            }

            .auth-theme-toggle {
                top: 16px;
                right: 16px;
                width: 48px;
                height: 48px;
            }

            .auth-theme-toggle .material-symbols-outlined {
                font-size: 22px;
            }

            .register-card {
                padding-top: 44px !important;
            }

            .step-actions .btn {
                width: 100%;
            }

            .upload-overlay {
                padding: 10px;
            }

            .upload-chip {
                width: 100%;
                justify-content: center;
            }

            #myTabstep2Content .row>[class*="col-"] {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
            }

            #myTabstep2Content .tab-pane {
                padding-top: 4px;
            }

            #myTabstep2Content .form-group.mb-4 {
                margin-bottom: 1rem !important;
            }

            .upload-square {
                min-height: 220px;
                padding: 14px;
            }

            .upload-square {
                max-height: 320px;
            }
        }

        @media (max-width: 575px) {
            .register-card {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }

            .register-content {
                padding-left: 0;
                padding-right: 0;
            }

            .text-center.mb-4 h2 {
                font-size: 20px;
                line-height: 1.35;
            }

            .text-center.mb-4 img {
                max-width: 150px !important;
            }

            .wizard-step .text-start.ms-3 {
                margin-left: 12px !important;
            }

            .wizard-step h4 {
                font-size: 16px;
            }

            .wizard-step p {
                font-size: 13px;
            }
        }
    </style>

    <!-- Title -->
    <title>Fila - Bootstrap 5 Admin Dashboard Template</title>
</head>

<body class="bg-body-bg">

    <!-- Start Preloader Area -->
    <div class="preloader" id="preloader">
        <div class="preloader">
            <div class="waviy position-relative">
                <span class="d-inline-block">F</span>
                <span class="d-inline-block">I</span>
                <span class="d-inline-block">L</span>
                <span class="d-inline-block">A</span>
            </div>
        </div>
    </div>
    <!-- End Preloader Area -->

    <div class="container-fluid register-shell">
        <div class="main-content d-flex flex-column p-0">
            <div class="m-lg-auto my-auto w-100 py-4" style="max-width: 1220px;">
                <div class="card bg-white border rounded-10 border-white py-100 pt-4 pb-4 px-md-5 register-card">
                    <div class="p-md-5 p-4 p-lg-0 register-content">
                        <div class="text-center mb-4">
                            <img src="{{ asset('assets/img/feeder.png') }}" alt="Feeder logo" class="img-fluid"
                                style="max-width: 190px; height: auto;">
                            <h2 class="fs-24 fw-semibold mt-3 mb-1">Welcome to Sri Lanka's biggest dropshipping platform
                            </h2>
                            <p class="text-gray-light mb-0">Create your account and complete the verification process.
                            </p>
                        </div>

                        <div class="mobile-step-summary">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-10"
                                style="background: rgba(239, 73, 35, 0.08); border: 1px solid rgba(239, 73, 35, 0.16);">
                                <span id="mobileStepNumber"
                                    class="fs-20 fw-bold text-primary wh-48 bg-primary bg-opacity-10 rounded-circle d-inline-block text-center flex-shrink-0">1</span>
                                <div class="text-start">
                                    <h4 id="mobileStepTitle" class="fs-18 fw-semibold mb-1">Login Verification</h4>
                                    <p id="mobileStepSubtitle" class="text-gray-light mb-0">Account access setup</p>
                                </div>
                            </div>
                        </div>

                        <ul class="nav nav-tabs justify-content-between border-0 mb-4 wizard-tabs2 flex-wrap gap-3"
                            id="myTabstep2" role="tablist">
                            <li class="nav-item wizard-step active-step" role="presentation">
                                <button class="nav-link p-0 d-flex align-items-center active" id="step1-tab"
                                    data-bs-toggle="tab" data-bs-target="#step1-tab-pane" type="button" role="tab"
                                    aria-controls="step1-tab-pane" aria-selected="true" data-step-index="1">
                                    <span
                                        class="fs-20 fw-bold text-primary wh-48 bg-primary bg-opacity-10 rounded-circle d-inline-block step-number">1</span>
                                    <div class="text-start ms-3">
                                        <h4 class="fs-18 fw-semibold">Login Verification</h4>
                                        <p class="text-gray-light mb-0">Account access setup</p>
                                    </div>
                                </button>
                            </li>
                            <li class="nav-item wizard-step" role="presentation">
                                <button class="nav-link p-0 d-flex align-items-center" id="step2-tab"
                                    data-bs-toggle="tab" data-bs-target="#step2-tab-pane" type="button" role="tab"
                                    aria-controls="step2-tab-pane" aria-selected="false" data-step-index="2">
                                    <span
                                        class="fs-20 fw-bold text-primary wh-48 bg-primary bg-opacity-10 rounded-circle d-inline-block step-number">2</span>
                                    <div class="text-start ms-3">
                                        <h4 class="fs-18 fw-semibold">Personal Details</h4>
                                        <p class="text-gray-light mb-0">Identity information</p>
                                    </div>
                                </button>
                            </li>
                            <li class="nav-item wizard-step" role="presentation">
                                <button class="nav-link p-0 d-flex align-items-center" id="step3-tab"
                                    data-bs-toggle="tab" data-bs-target="#step3-tab-pane" type="button" role="tab"
                                    aria-controls="step3-tab-pane" aria-selected="false" data-step-index="3">
                                    <span
                                        class="fs-20 fw-bold text-primary wh-48 bg-primary bg-opacity-10 rounded-circle d-inline-block step-number">3</span>
                                    <div class="text-start ms-3">
                                        <h4 class="fs-18 fw-semibold">Company Details</h4>
                                        <p class="text-gray-light mb-0">Business profile setup</p>
                                    </div>
                                </button>
                            </li>
                            <li class="nav-item wizard-step" role="presentation">
                                <button class="nav-link p-0 d-flex align-items-center" id="step4-tab"
                                    data-bs-toggle="tab" data-bs-target="#step4-tab-pane" type="button"
                                    role="tab" aria-controls="step4-tab-pane" aria-selected="false"
                                    data-step-index="4">
                                    <span
                                        class="fs-20 fw-bold text-primary wh-48 bg-primary bg-opacity-10 rounded-circle d-inline-block step-number">4</span>
                                    <div class="text-start ms-3">
                                        <h4 class="fs-18 fw-semibold">Finance Details</h4>
                                        <p class="text-gray-light mb-0">Banking information</p>
                                    </div>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="myTabstep2Content">
                            <div class="tab-pane fade show active" id="step1-tab-pane" role="tabpanel"
                                aria-labelledby="step1-tab" tabindex="0">
                                <h4 class="fs-18 fw-semibold mb-1">Personal Details</h4>
                                <p class="text-gray-light mb-4">Fields marked with * are required.</p>
                                <form id="register-step-1-form">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Contact Number *</label>
                                                <div class="position-relative">
                                                    <i class="ri-phone-line input-icon"></i>
                                                    <input id="contactNumber" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter 10 digit contact number" maxlength="10"
                                                        inputmode="numeric" autocomplete="tel">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-12 otp-step" id="otpStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Enter OTP *</label>
                                                <div class="position-relative">
                                                    <i class="ri-key-2-line input-icon"></i>
                                                    <input id="otpCode" type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter OTP" maxlength="6" inputmode="numeric">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 password-step" id="passwordStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Password *</label>
                                                <div class="position-relative">
                                                    <i class="ri-lock-password-line input-icon"></i>
                                                    <input id="passwordInput" type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Create password">
                                                    <button type="button" class="password-toggle-btn"
                                                        data-password-target="#passwordInput"
                                                        aria-label="Toggle password">
                                                        <i class="ri-eye-off-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 password-step" id="verifyPasswordStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Verify Password *</label>
                                                <div class="position-relative">
                                                    <i class="ri-shield-keyhole-line input-icon"></i>
                                                    <input id="verifyPasswordInput" type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Re-enter password">
                                                    <button type="button" class="password-toggle-btn"
                                                        data-password-target="#verifyPasswordInput"
                                                        aria-label="Toggle verify password">
                                                        <i class="ri-eye-off-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group d-flex step-actions flex-wrap">
                                                <button type="button" id="sendOtpBtn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white">Send
                                                    OTP</button>
                                                <button type="button" id="submitOtpBtn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white otp-step">Submit</button>
                                                <button type="button" id="activateStep2Btn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white step-next-wrap"
                                                    data-bs-toggle="tab" data-bs-target="#step2-tab-pane" disabled>
                                                    Next
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="step2-tab-pane" role="tabpanel"
                                aria-labelledby="step2-tab" tabindex="0">
                                <h4 class="fs-18 fw-semibold mb-1">Personal Details</h4>
                                <p class="text-gray-light mb-4">Fields marked with * are required.</p>
                                <form id="register-step-2-form">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">First Name *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-user-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter first name">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Last Name *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-user-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter last name">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Resident Address *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-map-pin-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter resident address">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">NIC No *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-id-card-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter NIC number">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Personal Contact No *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-phone-line input-icon"></i>
                                                            <input type="tel"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter personal contact number">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Personal Image Upload (1:1)
                                                            *</label>
                                                        <div class="upload-square" id="personalImageUploadBox">
                                                            <img id="personalImagePreview"
                                                                alt="Personal image preview">
                                                            <div class="upload-overlay">
                                                                <div class="upload-chip">
                                                                    <i class="ri-upload-2-line"></i>
                                                                    <span>Upload image</span>
                                                                    <input id="personalImageInput" type="file"
                                                                        accept="image/*">
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="upload-empty-state text-center p-3 position-absolute top-50 start-50 translate-middle w-100">
                                                                <i class="ri-image-add-line fs-32 d-block mb-2"
                                                                    style="color: #EF4923;"></i>
                                                                <h5 class="mb-1 fs-16 fw-semibold">Upload square
                                                                    image</h5>
                                                                <p class="mb-0 text-gray-light fs-14">Preview
                                                                    appears
                                                                    instantly after upload</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="form-group d-flex gap-3">
                                                <button type="button"
                                                    class="btn btn-primary bg-primary bg-opacity-10 text-primary py-3 px-5 fw-semibold border-0"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#step1-tab-pane">Back</button>
                                                <button type="button"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#step3-tab-pane">Next</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="step3-tab-pane" role="tabpanel"
                                aria-labelledby="step3-tab" tabindex="0">
                                <h4 class="fs-18 fw-semibold mb-1">Company Details</h4>
                                <p class="text-gray-light mb-4">Fields marked with * are required.</p>
                                <form id="register-step-3-form">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Company Name *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-building-4-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company name">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Company Address *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-map-pin-line input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company address">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Customer Care Number *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-customer-service-2-line input-icon"></i>
                                                            <input type="tel"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter customer care number">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Company Reg Number
                                                            (Optional)</label>
                                                        <div class="position-relative">
                                                            <i class="ri-hashtag input-icon"></i>
                                                            <input type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company registration number">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Upload BR (PDF) (Optional)</label>
                                                        <div class="position-relative">
                                                            <i class="ri-file-pdf-line input-icon"></i>
                                                            <input type="file"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                accept=".pdf,application/pdf">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Upload Company Logo (1:1) *</label>
                                                        <div class="upload-square" id="companyLogoUploadBox">
                                                            <img id="companyLogoPreview" alt="Company logo preview">
                                                            <div class="upload-overlay">
                                                                <div class="upload-chip">
                                                                    <i class="ri-upload-2-line"></i>
                                                                    <span>Upload logo</span>
                                                                    <input id="companyLogoInput" type="file"
                                                                        accept="image/*">
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="upload-empty-state text-center p-3 position-absolute top-50 start-50 translate-middle w-100">
                                                                <i class="ri-gallery-upload-line fs-32 d-block mb-2"
                                                                    style="color: #EF4923;"></i>
                                                                <h5 class="mb-1 fs-16 fw-semibold">Upload company
                                                                    logo</h5>
                                                                <p class="mb-0 text-gray-light fs-14">Square
                                                                    preview shown
                                                                    immediately</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>



                                        <div class="col-lg-12">
                                            <div class="form-group d-flex gap-3">
                                                <button type="button"
                                                    class="btn btn-primary bg-primary bg-opacity-10 text-primary py-3 px-5 fw-semibold border-0"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#step2-tab-pane">Back</button>
                                                <button type="button"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#step4-tab-pane">Next</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="step4-tab-pane" role="tabpanel"
                                aria-labelledby="step4-tab" tabindex="0">
                                <h4 class="fs-18 fw-semibold mb-1">Finance Details</h4>
                                <p class="text-gray-light mb-4">Fields marked with * are required.</p>
                                <form id="register-step-4-form">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Name *</label>
                                                <div class="position-relative">
                                                    <i class="ri-user-3-line input-icon"></i>
                                                    <input type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter account holder name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Banks Name *</label>
                                                <div class="position-relative">
                                                    <i class="ri-bank-line input-icon"></i>
                                                    <input type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter bank name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Branch Name *</label>
                                                <div class="position-relative">
                                                    <i class="ri-store-2-line input-icon"></i>
                                                    <input type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter branch name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Bank Code *</label>
                                                <div class="position-relative">
                                                    <i class="ri-barcode-box-line input-icon"></i>
                                                    <input type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter bank code">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Branch Code *</label>
                                                <div class="position-relative">
                                                    <i class="ri-code-s-slash-line input-icon"></i>
                                                    <input type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter branch code">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group d-flex gap-3">
                                                <button type="button"
                                                    class="btn btn-primary bg-primary bg-opacity-10 text-primary py-3 px-5 fw-semibold border-0"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#step3-tab-pane">Back</button>
                                                <button type="button" id="completeRegistrationBtn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white">Submit</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="completedMessage"
                            class="completed-message d-none align-items-center justify-content-center py-5">
                            <div class="text-center" style="max-width: 760px;">
                                <h2 class="display-6 fw-bold mb-3" style="color: #EF4923;">
                                    Congratulations! you're successfully registered to Feeder dropshipping platform
                                </h2>
                                <p class="fs-18 text-secondary mb-4" style="line-height: 1.9;">
                                    We will review your information and activate your account within 24 hrs and you will
                                    recieve a text message informing it.
                                </p>
                                <a href="{{ route('login') }}"
                                    class="btn btn-primary fw-semibold text-white px-4 py-3">
                                    Go to Login
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Link Of JS File -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/js/quill.min.js') }}"></script>
    <script src="{{ asset('assets/js/data-table.js') }}"></script>
    <script src="{{ asset('assets/js/prism.js') }}"></script>
    <script src="{{ asset('assets/js/clipboard.min.js') }}"></script>
    <script src="{{ asset('assets/js/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/js/echarts.min.js') }}"></script>
    <script src="{{ asset('assets/js/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/fullcalendar.main.js') }}"></script>
    <script src="{{ asset('assets/js/jsvectormap.min.js') }}"></script>
    <script src="{{ asset('assets/js/world-merc.js') }}"></script>
    <script src="{{ asset('assets/js/custom/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/js/custom/echarts.js') }}"></script>
    <script src="{{ asset('assets/js/custom/maps.js') }}"></script>
    <script src="{{ asset('assets/js/custom/custom.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleButton = document.getElementById('switch-toggle');
            const authThemeIcon = document.getElementById('authThemeIcon');
            const registerCard = document.querySelector('.register-card');
            const registerContent = document.querySelector('.register-content');
            const wizardTabs = document.getElementById('myTabstep2');
            const wizardContent = document.getElementById('myTabstep2Content');
            const completedMessage = document.getElementById('completedMessage');
            const mobileStepNumber = document.getElementById('mobileStepNumber');
            const mobileStepTitle = document.getElementById('mobileStepTitle');
            const mobileStepSubtitle = document.getElementById('mobileStepSubtitle');
            const stepButtons = Array.from(document.querySelectorAll('#myTabstep2 .nav-link'));
            const stepItems = Array.from(document.querySelectorAll('#myTabstep2 .wizard-step'));
            const stepPanes = Array.from(document.querySelectorAll('#myTabstep2Content .tab-pane'));
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const submitOtpBtn = document.getElementById('submitOtpBtn');
            const activateStep2Btn = document.getElementById('activateStep2Btn');
            const completeRegistrationBtn = document.getElementById('completeRegistrationBtn');
            const otpStep = document.getElementById('otpStep');
            const passwordStep = document.getElementById('passwordStep');
            const verifyPasswordStep = document.getElementById('verifyPasswordStep');
            const contactNumber = document.getElementById('contactNumber');
            const otpCode = document.getElementById('otpCode');
            const passwordInput = document.getElementById('passwordInput');
            const verifyPasswordInput = document.getElementById('verifyPasswordInput');
            const personalImageInput = document.getElementById('personalImageInput');
            const personalImagePreview = document.getElementById('personalImagePreview');
            const personalImageUploadBox = document.getElementById('personalImageUploadBox');
            const companyLogoInput = document.getElementById('companyLogoInput');
            const companyLogoPreview = document.getElementById('companyLogoPreview');
            const companyLogoUploadBox = document.getElementById('companyLogoUploadBox');

            function syncThemeIcon() {
                if (!themeToggleButton || !authThemeIcon) {
                    return;
                }

                authThemeIcon.textContent = document.body.getAttribute('data-theme') === 'dark' ? 'light_mode' :
                    'dark_mode';
            }

            function syncMobileSummary(activeIndex) {
                const stepButton = stepButtons[activeIndex - 1];
                const stepNumber = stepItems[activeIndex - 1]?.querySelector('.step-number')?.textContent?.trim() ||
                    String(activeIndex);
                const title = stepButton?.querySelector('h4')?.textContent?.trim() || '';
                const subtitle = stepButton?.querySelector('p')?.textContent?.trim() || '';

                if (mobileStepNumber) {
                    mobileStepNumber.textContent = stepNumber;
                }

                if (mobileStepTitle) {
                    mobileStepTitle.textContent = title;
                }

                if (mobileStepSubtitle) {
                    mobileStepSubtitle.textContent = subtitle;
                }
            }

            function syncContainerHeight() {
                if (!registerCard || !registerContent) {
                    return;
                }

                if (window.innerWidth < 992) {
                    registerCard.style.minHeight = 'auto';
                    registerContent.style.minHeight = 'auto';
                    return;
                }

                window.requestAnimationFrame(() => {
                    registerCard.style.minHeight = registerContent.scrollHeight + 'px';
                    registerContent.style.minHeight = 'auto';
                });
            }

            function setStepState(activeIndex) {
                stepItems.forEach((item, index) => {
                    item.classList.toggle('active-step', index + 1 === activeIndex);
                    item.classList.toggle('completed-step', index + 1 < activeIndex);
                });

                stepButtons.forEach((button, index) => {
                    const isActive = index + 1 === activeIndex;
                    button.classList.toggle('active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    if (isActive) {
                        bootstrap.Tab.getOrCreateInstance(button).show();
                    }
                });

                stepPanes.forEach((pane, index) => {
                    const visible = index + 1 === activeIndex;
                    pane.classList.toggle('show', visible);
                    pane.classList.toggle('active', visible);
                });

                syncMobileSummary(activeIndex);
                syncContainerHeight();
            }

            function updateStepOneControls() {
                const contactValid = /^\d{10}$/.test(contactNumber.value);
                const otpVisible = otpStep.classList.contains('is-visible');
                const passwordVisible = passwordStep.classList.contains('is-visible');

                sendOtpBtn.disabled = !contactValid;
                submitOtpBtn.disabled = !(/^[0-9]{4,6}$/.test(otpCode.value));
                activateStep2Btn.disabled = !(contactValid && otpVisible && passwordVisible && passwordInput.value
                    .length > 0 && verifyPasswordInput.value.length > 0 && passwordInput.value ===
                    verifyPasswordInput.value);
            }

            function updatePreview(input, preview, box) {
                const file = input.files && input.files[0];
                if (!file) {
                    preview.removeAttribute('src');
                    box.classList.remove('has-preview');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    box.classList.add('has-preview');
                };
                reader.readAsDataURL(file);
            }

            stepButtons.forEach((button) => {
                button.addEventListener('shown.bs.tab', function() {
                    const index = Number(button.getAttribute('data-step-index')) || 1;
                    setStepState(index);
                });
            });

            contactNumber.addEventListener('input', function() {
                contactNumber.value = contactNumber.value.replace(/\D/g, '').slice(0, 10);
                updateStepOneControls();
            });

            otpCode.addEventListener('input', function() {
                otpCode.value = otpCode.value.replace(/\D/g, '').slice(0, 6);
                updateStepOneControls();
            });

            [passwordInput, verifyPasswordInput].forEach((field) => {
                field.addEventListener('input', updateStepOneControls);
            });

            sendOtpBtn.addEventListener('click', function() {
                const contactValid = /^\d{10}$/.test(contactNumber.value);
                if (!contactValid) {
                    contactNumber.focus();
                    return;
                }

                otpStep.classList.add('is-visible');
                submitOtpBtn.classList.add('is-visible');
                sendOtpBtn.textContent = 'Resend OTP';
                updateStepOneControls();
            });

            submitOtpBtn.addEventListener('click', function() {
                if (!/^[0-9]{4,6}$/.test(otpCode.value)) {
                    otpCode.focus();
                    return;
                }

                contactNumber.disabled = true;
                otpCode.disabled = true;
                sendOtpBtn.classList.add('d-none');
                submitOtpBtn.classList.add('d-none');
                passwordStep.classList.add('is-visible');
                verifyPasswordStep.classList.add('is-visible');
                activateStep2Btn.classList.add('is-visible');
                updateStepOneControls();
            });

            activateStep2Btn.addEventListener('click', function() {
                if (activateStep2Btn.disabled) {
                    return;
                }

                bootstrap.Tab.getOrCreateInstance(document.getElementById('step2-tab')).show();
                setStepState(2);
            });

            function showCompletedState() {
                if (wizardTabs) {
                    wizardTabs.classList.add('d-none');
                }

                if (wizardContent) {
                    wizardContent.classList.add('d-none');
                }

                if (registerContent) {
                    registerContent.classList.add('completed-state');
                }

                if (mobileStepNumber || mobileStepTitle || mobileStepSubtitle) {
                    const completedTitle = 'Completed';
                    const completedSubtitle = 'Registration finished';

                    if (mobileStepNumber) {
                        mobileStepNumber.textContent = '4';
                    }

                    if (mobileStepTitle) {
                        mobileStepTitle.textContent = completedTitle;
                    }

                    if (mobileStepSubtitle) {
                        mobileStepSubtitle.textContent = completedSubtitle;
                    }
                }

                if (completedMessage) {
                    completedMessage.classList.remove('d-none');
                    completedMessage.classList.add('d-flex');
                }

                syncContainerHeight();
            }

            if (completeRegistrationBtn) {
                completeRegistrationBtn.addEventListener('click', function() {
                    showCompletedState();
                });
            }

            document.querySelectorAll('.password-toggle-btn').forEach((button) => {
                button.addEventListener('click', function() {
                    const target = document.querySelector(this.dataset.passwordTarget);
                    const icon = this.querySelector('i');
                    const isPassword = target.type === 'password';
                    target.type = isPassword ? 'text' : 'password';
                    icon.classList.toggle('ri-eye-line', isPassword);
                    icon.classList.toggle('ri-eye-off-line', !isPassword);
                });
            });

            personalImageInput.addEventListener('change', function() {
                updatePreview(personalImageInput, personalImagePreview, personalImageUploadBox);
            });

            companyLogoInput.addEventListener('change', function() {
                updatePreview(companyLogoInput, companyLogoPreview, companyLogoUploadBox);
            });

            window.addEventListener('resize', syncContainerHeight);

            setStepState(1);
            updateStepOneControls();
            syncThemeIcon();
            syncMobileSummary(1);
            syncContainerHeight();
        });
    </script>
</body>

</html>
