<!DOCTYPE html>
<html lang="zxx">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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

        .field-error {
            display: none;
            margin-top: 8px;
            color: #dc3545;
            font-size: 14px;
            font-weight: 500;
        }

        .field-error.is-visible {
            display: block;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 .2rem rgba(220, 53, 69, 0.12);
        }

        .step-one-alert {
            display: none;
        }

        .step-one-alert.is-visible {
            display: block;
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

        .upload-square.has-saved-photo .upload-empty-state {
            opacity: 0.35;
        }

        .upload-saved-label {
            display: none;
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(239, 73, 35, 0.12);
            color: #EF4923;
            font-size: 13px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 999px;
            z-index: 2;
        }

        .upload-square.has-saved-photo .upload-saved-label {
            display: block;
        }

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
                                    data-bs-toggle="tab" data-bs-target="#step3-tab-pane" type="button"
                                    role="tab" aria-controls="step3-tab-pane" aria-selected="false"
                                    data-step-index="3">
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
                                    <div id="stepOneAlert" class="alert alert-warning step-one-alert mb-4"
                                        role="alert"></div>
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Contact Number *</label>
                                                <div class="position-relative">
                                                    <i class="ri-phone-line input-icon"></i>
                                                    <input id="contactNumber" name="phone" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter 10 digit contact number" maxlength="10"
                                                        inputmode="numeric" autocomplete="tel">
                                                </div>
                                                <div id="contactNumberError" class="field-error"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-12 otp-step" id="otpStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Enter OTP *</label>
                                                <div class="position-relative">
                                                    <i class="ri-key-2-line input-icon"></i>
                                                    <input id="otpCode" name="otp" type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter OTP" maxlength="6" inputmode="numeric">
                                                </div>
                                                <div id="otpCodeError" class="field-error"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 password-step" id="passwordStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Password *</label>
                                                <div class="position-relative">
                                                    <i class="ri-lock-password-line input-icon"></i>
                                                    <input id="passwordInput" name="password" type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Create password">
                                                    <button type="button" class="password-toggle-btn"
                                                        data-password-target="#passwordInput"
                                                        aria-label="Toggle password">
                                                        <i class="ri-eye-off-line"></i>
                                                    </button>
                                                </div>
                                                <div id="passwordInputError" class="field-error"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 password-step" id="verifyPasswordStep">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Verify Password *</label>
                                                <div class="position-relative">
                                                    <i class="ri-shield-keyhole-line input-icon"></i>
                                                    <input id="verifyPasswordInput" name="password_confirmation"
                                                        type="password"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Re-enter password">
                                                    <button type="button" class="password-toggle-btn"
                                                        data-password-target="#verifyPasswordInput"
                                                        aria-label="Toggle verify password">
                                                        <i class="ri-eye-off-line"></i>
                                                    </button>
                                                </div>
                                                <div id="verifyPasswordInputError" class="field-error"></div>
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
                                                            <input id="firstName" name="first_name" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter first name" maxlength="100"
                                                                autocomplete="given-name">
                                                        </div>
                                                        <div id="firstNameError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Last Name *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-user-line input-icon"></i>
                                                            <input id="lastName" name="last_name" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter last name" maxlength="100"
                                                                autocomplete="family-name">
                                                        </div>
                                                        <div id="lastNameError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Resident Address *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-map-pin-line input-icon"></i>
                                                            <input id="address" name="address" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter resident address" maxlength="500"
                                                                autocomplete="street-address">
                                                        </div>
                                                        <div id="addressError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">NIC No *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-id-card-line input-icon"></i>
                                                            <input id="nic" name="nic" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter NIC number" maxlength="12"
                                                                autocomplete="off">
                                                        </div>
                                                        <div id="nicError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Personal Contact No *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-phone-line input-icon"></i>
                                                            <input id="personalPhone" name="personal_phone"
                                                                type="tel" readonly
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
                                                                    <input id="personalImageInput" name="profile_photo"
                                                                        type="file" accept="image/jpeg,image/png,image/webp">
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
                                                            <span class="upload-saved-label">Photo saved</span>
                                                        </div>
                                                        <div id="personalImageInputError" class="field-error"></div>
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
                                                <button type="button" id="saveStep2Btn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white">Next</button>
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
                                                            <input id="companyName" name="name" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company name" maxlength="200"
                                                                autocomplete="organization">
                                                        </div>
                                                        <div id="companyNameError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Company Address *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-map-pin-line input-icon"></i>
                                                            <input id="companyAddress" name="address" type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company address" maxlength="500"
                                                                autocomplete="street-address">
                                                        </div>
                                                        <div id="companyAddressError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Customer Care Number *</label>
                                                        <div class="position-relative">
                                                            <i class="ri-customer-service-2-line input-icon"></i>
                                                            <input id="customerCarePhone" name="customer_care_phone"
                                                                type="tel"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter customer care number" maxlength="10"
                                                                autocomplete="tel">
                                                        </div>
                                                        <div id="customerCarePhoneError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Company Reg Number
                                                            (Optional)</label>
                                                        <div class="position-relative">
                                                            <i class="ri-hashtag input-icon"></i>
                                                            <input id="companyRegNumber" name="registration_number"
                                                                type="text"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                placeholder="Enter company registration number"
                                                                maxlength="100">
                                                        </div>
                                                        <div id="companyRegNumberError" class="field-error"></div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="form-group mb-4">
                                                        <label class="label fs-16">Upload BR (PDF) (Optional)</label>
                                                        <div class="position-relative">
                                                            <i class="ri-file-pdf-line input-icon"></i>
                                                            <input id="brPdfInput" name="business_reg_pdf" type="file"
                                                                class="form-control text-dark h-55 form-control-icon"
                                                                accept=".pdf,application/pdf">
                                                        </div>
                                                        <div id="brPdfInputError" class="field-error"></div>
                                                        <div id="brPdfSavedLabel" class="text-success fs-14 mt-2 d-none">BR document saved</div>
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
                                                                    <input id="companyLogoInput" name="logo" type="file"
                                                                        accept="image/jpeg,image/png,image/webp">
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
                                                            <span class="upload-saved-label">Logo saved</span>
                                                        </div>
                                                        <div id="companyLogoInputError" class="field-error"></div>
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
                                                <button type="button" id="saveStep3Btn"
                                                    class="btn btn-primary py-3 px-5 fw-semibold text-white">Next</button>
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
                                                <label class="label fs-16">Account Name *</label>
                                                <div class="position-relative">
                                                    <i class="ri-user-3-line input-icon"></i>
                                                    <input id="accountName" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter account holder name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Bank Name *</label>
                                                <div class="position-relative">
                                                    <i class="ri-bank-line input-icon"></i>
                                                    <input id="bankName" type="text"
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
                                                    <input id="branchName" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter branch name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Account Number *</label>
                                                <div class="position-relative">
                                                    <i class="ri-numbers-line input-icon"></i>
                                                    <input id="accountNumber" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter account number">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Bank Code (Optional)</label>
                                                <div class="position-relative">
                                                    <i class="ri-barcode-box-line input-icon"></i>
                                                    <input id="bankCode" type="text"
                                                        class="form-control text-dark h-55 form-control-icon"
                                                        placeholder="Enter bank code">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-4">
                                                <label class="label fs-16">Branch Code (Optional)</label>
                                                <div class="position-relative">
                                                    <i class="ri-code-s-slash-line input-icon"></i>
                                                    <input id="branchCode" type="text"
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
            const saveStep2Btn = document.getElementById('saveStep2Btn');
            const saveStep3Btn = document.getElementById('saveStep3Btn');
            const activateStep2Btn = document.getElementById('activateStep2Btn');
            const completeRegistrationBtn = document.getElementById('completeRegistrationBtn');
            const stepOneAlert = document.getElementById('stepOneAlert');
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
            const brPdfInput = document.getElementById('brPdfInput');
            const brPdfSavedLabel = document.getElementById('brPdfSavedLabel');
            const firstName = document.getElementById('firstName');
            const lastName = document.getElementById('lastName');
            const address = document.getElementById('address');
            const nic = document.getElementById('nic');
            const personalPhone = document.getElementById('personalPhone');
            const companyName = document.getElementById('companyName');
            const companyAddress = document.getElementById('companyAddress');
            const customerCarePhone = document.getElementById('customerCarePhone');
            const companyRegNumber = document.getElementById('companyRegNumber');
            const accountName = document.getElementById('accountName');
            const bankName = document.getElementById('bankName');
            const branchName = document.getElementById('branchName');
            const bankCode = document.getElementById('bankCode');
            const branchCode = document.getElementById('branchCode');
            const accountNumber = document.getElementById('accountNumber');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const referralCode = new URLSearchParams(window.location.search).get('ref') || '';
            const stepTwoTab = document.getElementById('step2-tab');

            let otpVerified = false;
            let userRegistered = false;
            let stepOneRequiresPassword = false;
            let profilePhotoUuid = null;
            let companyLogoUuid = null;
            let businessRegPdfUuid = null;
            let registeringUserUuid = localStorage.getItem('feeder_reseller_user_uuid') || null;

            function syncThemeIcon() {
                if (!themeToggleButton || !authThemeIcon) {
                    return;
                }

                authThemeIcon.textContent = document.body.getAttribute('data-theme') === 'dark' ? 'light_mode' : 'dark_mode';
            }

            function syncMobileSummary(activeIndex) {
                const stepButton = stepButtons[activeIndex - 1];
                const stepNumber = stepItems[activeIndex - 1]?.querySelector('.step-number')?.textContent?.trim() || String(activeIndex);
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

            function buildRequestError(payload) {
                const error = new Error(extractErrorMessage(payload));
                error.payload = payload;
                return error;
            }

            function extractErrorMessage(payload) {
                if (payload && typeof payload.message === 'string' && payload.message.length > 0) {
                    return payload.message;
                }

                if (payload && payload.errors && typeof payload.errors === 'object') {
                    const firstField = Object.keys(payload.errors)[0];
                    const firstFieldErrors = payload.errors[firstField];

                    if (Array.isArray(firstFieldErrors) && firstFieldErrors.length > 0) {
                        return firstFieldErrors[0];
                    }
                }

                return 'Request failed. Please try again.';
            }

            async function postRegistration(url, data) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(data),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw buildRequestError(payload);
                }

                return payload;
            }

            async function postRegistrationForm(url, formData) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw buildRequestError(payload);
                }

                return payload;
            }

            async function getRegistrationDraft(uuid) {
                const response = await fetch(`{{ url('/auth/register/draft') }}/${uuid}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw buildRequestError(payload);
                }

                return payload.draft;
            }

            function setFieldError(field, message) {
                const errorNode = document.getElementById(`${field.id}Error`);
                field.classList.add('is-invalid');

                if (errorNode) {
                    errorNode.textContent = message;
                    errorNode.classList.add('is-visible');
                }
            }

            function clearFieldError(field) {
                const errorNode = document.getElementById(`${field.id}Error`);
                field.classList.remove('is-invalid');

                if (errorNode) {
                    errorNode.textContent = '';
                    errorNode.classList.remove('is-visible');
                }
            }

            function clearStepOneValidation() {
                [contactNumber, otpCode, passwordInput, verifyPasswordInput].forEach(clearFieldError);
            }

            function showStepOneAlert(message) {
                if (!stepOneAlert || !message) {
                    return;
                }

                stepOneAlert.textContent = message;
                stepOneAlert.classList.add('is-visible');
            }

            function hideStepOneAlert() {
                if (!stepOneAlert) {
                    return;
                }

                stepOneAlert.textContent = '';
                stepOneAlert.classList.remove('is-visible');
            }

            function showOtpSection() {
                otpStep.classList.add('is-visible');
                submitOtpBtn.classList.add('is-visible');
                sendOtpBtn.textContent = 'Resend OTP';
            }

            function showPasswordSection() {
                passwordStep.classList.add('is-visible');
                verifyPasswordStep.classList.add('is-visible');
                activateStep2Btn.classList.add('is-visible');
            }

            function hidePasswordSection() {
                passwordStep.classList.remove('is-visible');
                verifyPasswordStep.classList.remove('is-visible');
                passwordInput.value = '';
                verifyPasswordInput.value = '';
                clearFieldError(passwordInput);
                clearFieldError(verifyPasswordInput);
            }

            function persistRegistrationUuid(uuid) {
                registeringUserUuid = uuid || null;

                if (registeringUserUuid) {
                    localStorage.setItem('feeder_reseller_user_uuid', registeringUserUuid);
                } else {
                    localStorage.removeItem('feeder_reseller_user_uuid');
                }
            }

            function validatePhoneField() {
                const normalized = contactNumber.value.replace(/\D/g, '').slice(0, 10);
                contactNumber.value = normalized;

                if (normalized.length === 0) {
                    setFieldError(contactNumber, 'Contact number is required.');
                    return false;
                }

                if (!/^\d{10}$/.test(normalized)) {
                    setFieldError(contactNumber, 'Enter a valid 10 digit contact number.');
                    return false;
                }

                clearFieldError(contactNumber);
                return true;
            }

            function validateOtpField() {
                otpCode.value = otpCode.value.replace(/\D/g, '').slice(0, 6);

                if (!otpStep.classList.contains('is-visible')) {
                    clearFieldError(otpCode);
                    return true;
                }

                if (otpCode.value.length === 0) {
                    setFieldError(otpCode, 'OTP is required.');
                    return false;
                }

                if (!/^\d{4,6}$/.test(otpCode.value)) {
                    setFieldError(otpCode, 'Enter a valid OTP.');
                    return false;
                }

                clearFieldError(otpCode);
                return true;
            }

            function validatePasswordField() {
                if (!stepOneRequiresPassword) {
                    clearFieldError(passwordInput);
                    return true;
                }

                if (passwordInput.value.length === 0) {
                    setFieldError(passwordInput, 'Password is required.');
                    return false;
                }

                if (passwordInput.value.length < 8) {
                    setFieldError(passwordInput, 'Password must be at least 8 characters.');
                    return false;
                }

                clearFieldError(passwordInput);
                return true;
            }

            function validatePasswordConfirmationField() {
                if (!stepOneRequiresPassword) {
                    clearFieldError(verifyPasswordInput);
                    return true;
                }

                if (verifyPasswordInput.value.length === 0) {
                    setFieldError(verifyPasswordInput, 'Please confirm your password.');
                    return false;
                }

                if (passwordInput.value !== verifyPasswordInput.value) {
                    setFieldError(verifyPasswordInput, 'Passwords do not match.');
                    return false;
                }

                clearFieldError(verifyPasswordInput);
                return true;
            }

            function applyStepOneServerErrors(payload) {
                const errors = payload?.errors;

                if (!errors || typeof errors !== 'object') {
                    return;
                }

                if (Array.isArray(errors.phone) && errors.phone[0]) {
                    setFieldError(contactNumber, errors.phone[0]);
                }

                if (Array.isArray(errors.otp) && errors.otp[0]) {
                    setFieldError(otpCode, errors.otp[0]);
                }

                if (Array.isArray(errors.password) && errors.password[0]) {
                    setFieldError(passwordInput, errors.password[0]);
                }

                if (Array.isArray(errors.password_confirmation) && errors.password_confirmation[0]) {
                    setFieldError(verifyPasswordInput, errors.password_confirmation[0]);
                }
            }

            function updateStepOneControls() {
                const phoneValid = /^\d{10}$/.test(contactNumber.value);
                const otpVisible = otpStep.classList.contains('is-visible');
                const otpValid = /^\d{4,6}$/.test(otpCode.value);
                const passwordValid = !stepOneRequiresPassword || (
                    passwordInput.value.length >= 8 &&
                    verifyPasswordInput.value.length > 0 &&
                    passwordInput.value === verifyPasswordInput.value
                );

                sendOtpBtn.disabled = !phoneValid || contactNumber.disabled;
                submitOtpBtn.disabled = !otpVisible || !otpValid || otpCode.disabled;
                activateStep2Btn.disabled = !(otpVerified && userRegistered) && !(otpVerified && stepOneRequiresPassword && passwordValid);
            }

            function moveToStepTwo() {
                if (!stepTwoTab) {
                    return;
                }

                bootstrap.Tab.getOrCreateInstance(stepTwoTab).show();
                setStepState(2);
            }

            function finalizeVerifiedStepOne(phone, options = {}) {
                const requiresPassword = Boolean(options.requiresPassword);

                contactNumber.value = phone;
                contactNumber.disabled = true;
                otpCode.disabled = true;
                otpVerified = true;
                stepOneRequiresPassword = requiresPassword;

                if (personalPhone) {
                    personalPhone.value = phone;
                }

                showOtpSection();
                sendOtpBtn.classList.add('d-none');
                submitOtpBtn.classList.add('d-none');
                activateStep2Btn.classList.add('is-visible');

                if (requiresPassword) {
                    userRegistered = false;
                    showPasswordSection();
                } else {
                    userRegistered = true;
                    hidePasswordSection();
                }

                updateStepOneControls();
            }

            function resetStepOneFlow() {
                otpVerified = false;
                userRegistered = false;
                stepOneRequiresPassword = false;
                persistRegistrationUuid(null);
                otpCode.value = '';
                otpCode.disabled = false;
                contactNumber.disabled = false;
                sendOtpBtn.classList.remove('d-none');
                submitOtpBtn.classList.remove('is-visible');
                showOtpSection();
                otpStep.classList.remove('is-visible');
                hidePasswordSection();
                activateStep2Btn.classList.remove('is-visible');
                sendOtpBtn.textContent = 'Send OTP';
                updateStepOneControls();
            }

            function applySavedProfilePhoto(uuid) {
                profilePhotoUuid = uuid;
                personalImageUploadBox.classList.add('has-saved-photo');
                clearFieldError(personalImageInput);
            }

            function applySavedCompanyLogo(uuid) {
                companyLogoUuid = uuid;
                companyLogoUploadBox.classList.add('has-saved-photo');
                clearFieldError(companyLogoInput);
            }

            function applySavedBusinessRegPdf(uuid) {
                businessRegPdfUuid = uuid || null;

                if (brPdfSavedLabel) {
                    brPdfSavedLabel.classList.toggle('d-none', !businessRegPdfUuid);
                }

                if (brPdfInput) {
                    clearFieldError(brPdfInput);
                }
            }

            function clearStepTwoValidation() {
                [firstName, lastName, address, nic, personalImageInput].forEach(clearFieldError);
            }

            function clearStepThreeValidation() {
                [companyName, companyAddress, customerCarePhone, companyRegNumber, companyLogoInput, brPdfInput]
                    .filter(Boolean)
                    .forEach(clearFieldError);
            }

            function validateFirstNameField() {
                const value = firstName.value.trim();
                firstName.value = value;

                if (value.length === 0) {
                    setFieldError(firstName, 'First name is required.');
                    return false;
                }

                clearFieldError(firstName);
                return true;
            }

            function validateLastNameField() {
                const value = lastName.value.trim();
                lastName.value = value;

                if (value.length === 0) {
                    setFieldError(lastName, 'Last name is required.');
                    return false;
                }

                clearFieldError(lastName);
                return true;
            }

            function validateAddressField() {
                const value = address.value.trim();
                address.value = value;

                if (value.length === 0) {
                    setFieldError(address, 'Residential address is required.');
                    return false;
                }

                clearFieldError(address);
                return true;
            }

            function validateNicField() {
                nic.value = nic.value.toUpperCase().replace(/[^0-9VX]/g, '').slice(0, 12);

                if (nic.value.length === 0) {
                    setFieldError(nic, 'NIC number is required.');
                    return false;
                }

                if (!/^([0-9]{9}[VX]|[0-9]{12})$/.test(nic.value)) {
                    setFieldError(nic, 'Enter a valid NIC number.');
                    return false;
                }

                clearFieldError(nic);
                return true;
            }

            function validateProfilePhotoField() {
                const hasNewPhoto = personalImageInput.files && personalImageInput.files[0];

                if (!hasNewPhoto && !profilePhotoUuid) {
                    setFieldError(personalImageInput, 'Profile photo is required.');
                    return false;
                }

                if (hasNewPhoto) {
                    const file = personalImageInput.files[0];
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!allowedTypes.includes(file.type)) {
                        setFieldError(personalImageInput, 'Profile photo must be a JPG, PNG, or WebP image.');
                        return false;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        setFieldError(personalImageInput, 'Maximum profile photo size is 5MB.');
                        return false;
                    }
                }

                clearFieldError(personalImageInput);
                return true;
            }

            function validateStepTwoForm() {
                const firstNameValid = validateFirstNameField();
                const lastNameValid = validateLastNameField();
                const addressValid = validateAddressField();
                const nicValid = validateNicField();
                const photoValid = validateProfilePhotoField();

                return firstNameValid && lastNameValid && addressValid && nicValid && photoValid;
            }

            function applyStepTwoServerErrors(payload) {
                const errors = payload?.errors;

                if (!errors || typeof errors !== 'object') {
                    return;
                }

                if (Array.isArray(errors.first_name) && errors.first_name[0]) {
                    setFieldError(firstName, errors.first_name[0]);
                }

                if (Array.isArray(errors.last_name) && errors.last_name[0]) {
                    setFieldError(lastName, errors.last_name[0]);
                }

                if (Array.isArray(errors.address) && errors.address[0]) {
                    setFieldError(address, errors.address[0]);
                }

                if (Array.isArray(errors.nic) && errors.nic[0]) {
                    setFieldError(nic, errors.nic[0]);
                }

                if (Array.isArray(errors.profile_photo) && errors.profile_photo[0]) {
                    setFieldError(personalImageInput, errors.profile_photo[0]);
                }

                if (Array.isArray(errors.profile_photo_uuid) && errors.profile_photo_uuid[0]) {
                    setFieldError(personalImageInput, errors.profile_photo_uuid[0]);
                }

                if (Array.isArray(errors.user_uuid) && errors.user_uuid[0]) {
                    alert(errors.user_uuid[0]);
                }
            }

            function fillPersonalDraft(personal) {
                if (!personal) {
                    return;
                }

                firstName.value = personal.first_name || '';
                lastName.value = personal.last_name || '';
                address.value = personal.address || '';
                nic.value = personal.nic || '';

                if (personal.profile_photo) {
                    applySavedProfilePhoto(personal.profile_photo);
                }
            }

            function fillCompanyDraft(company) {
                if (!company) {
                    return;
                }

                companyName.value = company.name || '';
                companyAddress.value = company.address || '';
                customerCarePhone.value = company.customer_care_phone || '';

                if (companyRegNumber) {
                    companyRegNumber.value = company.registration_number || '';
                }

                if (company.logo_uuid) {
                    applySavedCompanyLogo(company.logo_uuid);
                }

                if (company.business_reg_pdf_uuid) {
                    applySavedBusinessRegPdf(company.business_reg_pdf_uuid);
                }
            }

            function fillBankDraft(bank) {
                if (!bank) {
                    return;
                }

                accountName.value = bank.account_name || '';
                bankName.value = bank.bank_name || '';
                branchName.value = bank.branch_name || '';
                accountNumber.value = bank.account_number || '';

                if (bankCode) {
                    bankCode.value = bank.bank_code || '';
                }

                if (branchCode) {
                    branchCode.value = bank.branch_code || '';
                }
            }

            function validateCompanyNameField() {
                const value = companyName.value.trim();
                companyName.value = value;

                if (value.length === 0) {
                    setFieldError(companyName, 'Company name is required.');
                    return false;
                }

                clearFieldError(companyName);
                return true;
            }

            function validateCompanyAddressField() {
                const value = companyAddress.value.trim();
                companyAddress.value = value;

                if (value.length === 0) {
                    setFieldError(companyAddress, 'Company address is required.');
                    return false;
                }

                clearFieldError(companyAddress);
                return true;
            }

            function validateCustomerCarePhoneField() {
                const normalized = customerCarePhone.value.replace(/\D/g, '').slice(0, 10);
                customerCarePhone.value = normalized;

                if (normalized.length === 0) {
                    setFieldError(customerCarePhone, 'Customer care number is required.');
                    return false;
                }

                if (!/^\d{10}$/.test(normalized)) {
                    setFieldError(customerCarePhone, 'Enter a valid 10 digit customer care number.');
                    return false;
                }

                clearFieldError(customerCarePhone);
                return true;
            }

            function validateCompanyLogoField() {
                const hasNewLogo = companyLogoInput.files && companyLogoInput.files[0];

                if (!hasNewLogo && !companyLogoUuid) {
                    setFieldError(companyLogoInput, 'Company logo is required.');
                    return false;
                }

                if (hasNewLogo) {
                    const file = companyLogoInput.files[0];
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!allowedTypes.includes(file.type)) {
                        setFieldError(companyLogoInput, 'Company logo must be a JPG, PNG, or WebP image.');
                        return false;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        setFieldError(companyLogoInput, 'Maximum company logo size is 5MB.');
                        return false;
                    }
                }

                clearFieldError(companyLogoInput);
                return true;
            }

            function validateBusinessRegPdfField() {
                if (!brPdfInput) {
                    return true;
                }

                const hasNewPdf = brPdfInput.files && brPdfInput.files[0];

                if (!hasNewPdf) {
                    clearFieldError(brPdfInput);
                    return true;
                }

                const file = brPdfInput.files[0];

                if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                    setFieldError(brPdfInput, 'Business registration document must be a PDF.');
                    return false;
                }

                if (file.size > 10 * 1024 * 1024) {
                    setFieldError(brPdfInput, 'Maximum business registration document size is 10MB.');
                    return false;
                }

                clearFieldError(brPdfInput);
                return true;
            }

            function validateStepThreeForm() {
                const nameValid = validateCompanyNameField();
                const addressValid = validateCompanyAddressField();
                const phoneValid = validateCustomerCarePhoneField();
                const logoValid = validateCompanyLogoField();
                const brValid = validateBusinessRegPdfField();

                return nameValid && addressValid && phoneValid && logoValid && brValid;
            }

            function applyStepThreeServerErrors(payload) {
                const errors = payload?.errors;

                if (!errors || typeof errors !== 'object') {
                    return;
                }

                if (Array.isArray(errors.name) && errors.name[0]) {
                    setFieldError(companyName, errors.name[0]);
                }

                if (Array.isArray(errors.address) && errors.address[0]) {
                    setFieldError(companyAddress, errors.address[0]);
                }

                if (Array.isArray(errors.customer_care_phone) && errors.customer_care_phone[0]) {
                    setFieldError(customerCarePhone, errors.customer_care_phone[0]);
                }

                if (Array.isArray(errors.registration_number) && errors.registration_number[0] && companyRegNumber) {
                    setFieldError(companyRegNumber, errors.registration_number[0]);
                }

                if (Array.isArray(errors.logo) && errors.logo[0]) {
                    setFieldError(companyLogoInput, errors.logo[0]);
                }

                if (Array.isArray(errors.logo_uuid) && errors.logo_uuid[0]) {
                    setFieldError(companyLogoInput, errors.logo_uuid[0]);
                }

                if (Array.isArray(errors.business_reg_pdf) && errors.business_reg_pdf[0] && brPdfInput) {
                    setFieldError(brPdfInput, errors.business_reg_pdf[0]);
                }

                if (Array.isArray(errors.business_reg_pdf_uuid) && errors.business_reg_pdf_uuid[0] && brPdfInput) {
                    setFieldError(brPdfInput, errors.business_reg_pdf_uuid[0]);
                }

                if (Array.isArray(errors.user_uuid) && errors.user_uuid[0]) {
                    alert(errors.user_uuid[0]);
                }
            }

            async function resumeRegistrationProgress(preferredStep = null) {
                if (!registeringUserUuid) {
                    return;
                }

                const draft = await getRegistrationDraft(registeringUserUuid);

                if (draft.registration_submitted || draft.user?.status === 'PENDING') {
                    showCompletedState();
                    return;
                }

                if (draft.user?.phone) {
                    finalizeVerifiedStepOne(draft.user.phone, {
                        requiresPassword: false,
                    });
                }

                fillPersonalDraft(draft.personal);
                fillCompanyDraft(draft.company);
                fillBankDraft(draft.bank);

                const targetStep = Number(preferredStep || draft.current_step || 2);
                setStepState(Math.min(Math.max(targetStep, 2), 4));
            }

            async function loadRegistrationDraft() {
                if (!registeringUserUuid) {
                    return;
                }

                try {
                    await resumeRegistrationProgress();
                } catch (error) {
                    persistRegistrationUuid(null);
                    profilePhotoUuid = null;
                    companyLogoUuid = null;
                    businessRegPdfUuid = null;
                }
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
                hideStepOneAlert();
                validatePhoneField();
                otpVerified = false;
                userRegistered = false;
                stepOneRequiresPassword = false;
                otpCode.disabled = false;
                sendOtpBtn.classList.remove('d-none');
                submitOtpBtn.classList.remove('d-none');
                hidePasswordSection();
                activateStep2Btn.classList.remove('is-visible');
                updateStepOneControls();
            });

            contactNumber.addEventListener('blur', validatePhoneField);
            otpCode.addEventListener('input', function() {
                validateOtpField();
                updateStepOneControls();
            });
            otpCode.addEventListener('blur', validateOtpField);

            passwordInput.addEventListener('input', function() {
                validatePasswordField();
                validatePasswordConfirmationField();
                updateStepOneControls();
            });

            verifyPasswordInput.addEventListener('input', function() {
                validatePasswordConfirmationField();
                updateStepOneControls();
            });

            sendOtpBtn.addEventListener('click', async function() {
                hideStepOneAlert();
                clearStepOneValidation();

                if (!validatePhoneField()) {
                    contactNumber.focus();
                    updateStepOneControls();
                    return;
                }

                sendOtpBtn.disabled = true;
                otpVerified = false;
                userRegistered = false;
                stepOneRequiresPassword = false;

                try {
                    const response = await postRegistration('{{ route('reseller.registration.send-otp') }}', {
                        phone: contactNumber.value,
                    });

                    showOtpSection();

                    if (response.otp) {
                        alert('Development OTP: ' + response.otp);
                    }
                } catch (error) {
                    applyStepOneServerErrors(error.payload);
                    alert(error.message);
                } finally {
                    updateStepOneControls();
                }
            });

            submitOtpBtn.addEventListener('click', async function() {
                hideStepOneAlert();
                clearFieldError(otpCode);

                const phoneValid = validatePhoneField();
                const otpValid = validateOtpField();

                if (!phoneValid || !otpValid) {
                    if (!phoneValid) {
                        contactNumber.focus();
                    } else {
                        otpCode.focus();
                    }

                    updateStepOneControls();
                    return;
                }

                submitOtpBtn.disabled = true;

                try {
                    const response = await postRegistration('{{ route('reseller.registration.verify-otp') }}', {
                        phone: contactNumber.value,
                        otp: otpCode.value,
                    });

                    if (response.alert_message) {
                        showStepOneAlert(response.alert_message);
                    }

                    if (response.can_proceed === false) {
                        resetStepOneFlow();
                        return;
                    }

                    if (response.user_uuid) {
                        persistRegistrationUuid(response.user_uuid);
                    }

                    if (response.registration_submitted) {
                        showCompletedState();
                        return;
                    }

                    finalizeVerifiedStepOne(contactNumber.value, {
                        requiresPassword: !response.password_is_set,
                    });

                    if (response.password_is_set) {
                        try {
                            await resumeRegistrationProgress(response.current_step);
                        } catch (draftError) {
                            moveToStepTwo();
                        }
                    }
                } catch (error) {
                    otpVerified = false;
                    applyStepOneServerErrors(error.payload);
                    alert(error.message);
                } finally {
                    updateStepOneControls();
                }
            });

            activateStep2Btn.addEventListener('click', async function() {
                hideStepOneAlert();
                clearFieldError(passwordInput);
                clearFieldError(verifyPasswordInput);

                if (activateStep2Btn.disabled) {
                    return;
                }

                if (!stepOneRequiresPassword) {
                    try {
                        await resumeRegistrationProgress();
                    } catch (error) {
                        moveToStepTwo();
                    }
                    return;
                }

                const passwordValid = validatePasswordField();
                const confirmationValid = validatePasswordConfirmationField();

                if (!passwordValid || !confirmationValid) {
                    if (!passwordValid) {
                        passwordInput.focus();
                    } else {
                        verifyPasswordInput.focus();
                    }

                    updateStepOneControls();
                    return;
                }

                activateStep2Btn.disabled = true;

                try {
                    const response = await postRegistration('{{ route('reseller.registration.user') }}', {
                        phone: contactNumber.value,
                        password: passwordInput.value,
                        password_confirmation: verifyPasswordInput.value,
                        ref: referralCode,
                        referral_code: referralCode,
                    });

                    persistRegistrationUuid(response.user.uuid);
                    userRegistered = true;
                    stepOneRequiresPassword = false;

                    if (personalPhone) {
                        personalPhone.value = contactNumber.value;
                    }

                    moveToStepTwo();
                } catch (error) {
                    applyStepOneServerErrors(error.payload);
                    alert(error.message);
                } finally {
                    updateStepOneControls();
                }
            });

            [firstName, lastName, address].forEach((field) => {
                field.addEventListener('input', function() {
                    if (field === firstName) {
                        validateFirstNameField();
                    } else if (field === lastName) {
                        validateLastNameField();
                    } else {
                        validateAddressField();
                    }
                });

                field.addEventListener('blur', function() {
                    if (field === firstName) {
                        validateFirstNameField();
                    } else if (field === lastName) {
                        validateLastNameField();
                    } else {
                        validateAddressField();
                    }
                });
            });

            nic.addEventListener('input', validateNicField);
            nic.addEventListener('blur', validateNicField);

            if (saveStep2Btn) {
                saveStep2Btn.addEventListener('click', async function() {
                    clearStepTwoValidation();

                    if (!registeringUserUuid) {
                        alert('Please complete Step 1 first.');
                        return;
                    }

                    if (!validateStepTwoForm()) {
                        if (firstName.classList.contains('is-invalid')) {
                            firstName.focus();
                        } else if (lastName.classList.contains('is-invalid')) {
                            lastName.focus();
                        } else if (address.classList.contains('is-invalid')) {
                            address.focus();
                        } else if (nic.classList.contains('is-invalid')) {
                            nic.focus();
                        }

                        return;
                    }

                    saveStep2Btn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('user_uuid', registeringUserUuid);
                        formData.append('first_name', firstName.value.trim());
                        formData.append('last_name', lastName.value.trim());
                        formData.append('address', address.value.trim());
                        formData.append('nic', nic.value.trim().toUpperCase());

                        const hasNewPhoto = personalImageInput.files && personalImageInput.files[0];

                        if (hasNewPhoto) {
                            formData.append('profile_photo', personalImageInput.files[0]);
                        } else if (profilePhotoUuid) {
                            formData.append('profile_photo_uuid', profilePhotoUuid);
                        }

                        const response = await postRegistrationForm('{{ route('reseller.registration.personal') }}', formData);

                        if (response.profile?.profile_photo) {
                            applySavedProfilePhoto(response.profile.profile_photo);
                        }

                        bootstrap.Tab.getOrCreateInstance(document.getElementById('step3-tab')).show();
                        setStepState(3);
                    } catch (error) {
                        applyStepTwoServerErrors(error.payload);
                        alert(error.message);
                    } finally {
                        saveStep2Btn.disabled = false;
                    }
                });
            }

            if (saveStep3Btn) {
                saveStep3Btn.addEventListener('click', async function() {
                    clearStepThreeValidation();

                    if (!registeringUserUuid) {
                        alert('Please complete previous steps first.');
                        return;
                    }

                    if (!validateStepThreeForm()) {
                        if (companyName.classList.contains('is-invalid')) {
                            companyName.focus();
                        } else if (companyAddress.classList.contains('is-invalid')) {
                            companyAddress.focus();
                        } else if (customerCarePhone.classList.contains('is-invalid')) {
                            customerCarePhone.focus();
                        }

                        return;
                    }

                    saveStep3Btn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('user_uuid', registeringUserUuid);
                        formData.append('name', companyName.value.trim());
                        formData.append('address', companyAddress.value.trim());
                        formData.append('customer_care_phone', customerCarePhone.value.trim());

                        if (companyRegNumber && companyRegNumber.value.trim()) {
                            formData.append('registration_number', companyRegNumber.value.trim());
                        }

                        const hasNewLogo = companyLogoInput.files && companyLogoInput.files[0];
                        const hasNewBr = brPdfInput && brPdfInput.files && brPdfInput.files[0];

                        if (hasNewLogo) {
                            formData.append('logo', companyLogoInput.files[0]);
                        } else if (companyLogoUuid) {
                            formData.append('logo_uuid', companyLogoUuid);
                        }

                        if (hasNewBr) {
                            formData.append('business_reg_pdf', brPdfInput.files[0]);
                        } else if (businessRegPdfUuid) {
                            formData.append('business_reg_pdf_uuid', businessRegPdfUuid);
                        }

                        const response = await postRegistrationForm('{{ route('reseller.registration.company') }}', formData);

                        if (response.company?.logo_uuid) {
                            applySavedCompanyLogo(response.company.logo_uuid);
                        }

                        if (response.company?.business_reg_pdf_uuid) {
                            applySavedBusinessRegPdf(response.company.business_reg_pdf_uuid);
                        }

                        bootstrap.Tab.getOrCreateInstance(document.getElementById('step4-tab')).show();
                        setStepState(4);
                    } catch (error) {
                        applyStepThreeServerErrors(error.payload);
                        alert(error.message);
                    } finally {
                        saveStep3Btn.disabled = false;
                    }
                });
            }

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

                if (mobileStepNumber) {
                    mobileStepNumber.textContent = '4';
                }

                if (mobileStepTitle) {
                    mobileStepTitle.textContent = 'Completed';
                }

                if (mobileStepSubtitle) {
                    mobileStepSubtitle.textContent = 'Registration finished';
                }

                if (completedMessage) {
                    completedMessage.classList.remove('d-none');
                    completedMessage.classList.add('d-flex');
                }

                syncContainerHeight();
            }

            if (completeRegistrationBtn) {
                completeRegistrationBtn.addEventListener('click', async function() {
                    if (!registeringUserUuid) {
                        alert('Please complete previous steps first.');
                        return;
                    }

                    if (!accountName.value.trim() || !bankName.value.trim() || !branchName.value.trim() || !accountNumber.value.trim()) {
                        alert('Please fill out required finance details.');
                        return;
                    }

                    completeRegistrationBtn.disabled = true;
                    try {
                        await postRegistration('{{ route('reseller.registration.bank') }}', {
                            user_uuid: registeringUserUuid,
                            account_name: accountName.value.trim(),
                            bank_name: bankName.value.trim(),
                            branch_name: branchName.value.trim(),
                            bank_code: bankCode ? bankCode.value.trim() : null,
                            branch_code: branchCode ? branchCode.value.trim() : null,
                            account_number: accountNumber.value.trim(),
                        });

                        await postRegistration('{{ route('reseller.registration.submit') }}', {
                            user_uuid: registeringUserUuid,
                            ref: referralCode,
                            referral_code: referralCode,
                        });

                        showCompletedState();
                    } catch (error) {
                        alert(error.message);
                    } finally {
                        completeRegistrationBtn.disabled = false;
                    }
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
                validateProfilePhotoField();
            });

            [companyName, companyAddress].forEach((field) => {
                field.addEventListener('input', function() {
                    if (field === companyName) {
                        validateCompanyNameField();
                    } else {
                        validateCompanyAddressField();
                    }
                });

                field.addEventListener('blur', function() {
                    if (field === companyName) {
                        validateCompanyNameField();
                    } else {
                        validateCompanyAddressField();
                    }
                });
            });

            customerCarePhone.addEventListener('input', validateCustomerCarePhoneField);
            customerCarePhone.addEventListener('blur', validateCustomerCarePhoneField);

            companyLogoInput.addEventListener('change', function() {
                updatePreview(companyLogoInput, companyLogoPreview, companyLogoUploadBox);
                validateCompanyLogoField();
            });

            if (brPdfInput) {
                brPdfInput.addEventListener('change', validateBusinessRegPdfField);
            }

            window.addEventListener('resize', syncContainerHeight);

            setStepState(1);
            updateStepOneControls();
            syncThemeIcon();
            syncMobileSummary(1);
            syncContainerHeight();
            loadRegistrationDraft();
        });
    </script>
</body>

</html>
