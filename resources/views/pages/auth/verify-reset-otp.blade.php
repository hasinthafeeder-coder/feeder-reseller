<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-menu.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/simplebar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/prism.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/remixicon.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/jsvectormap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

    <title>FEEDER - Reset Password</title>
</head>


<body class="bg-body-bg">
    <div class="container-fluid">
        <div class="main-content d-flex flex-column p-0">
            <div class="m-lg-auto my-auto w-930 py-4">
                <div class="card bg-white border rounded-10 border-white py-100 px-130">
                    <div class="p-md-5 p-4 p-lg-0">
                        <div class="text-center mb-4">
                            <img src="{{ asset('assets/img/feeder.png') }}" alt="FEEDER" class="d-block mx-auto mb-3"
                                style="max-width:220px;width:100%;height:auto;">
                            <h3 class="fs-26 fw-medium">
                                Verify OTP
                            </h3>
                            <p class="text-secondary">
                                Enter the verification OTP sent to your registered phone number.
                            </p>
                        </div>

                        @if (config('app.debug') && session('debug_otp'))
                            <div class="alert alert-warning mb-4">
                                <strong>Development OTP:</strong>
                                {{ session('debug_otp') }}
                            </div>
                        @endif
                        <form method="POST" action="{{ route('password.reset.verify.submit') }}">
                            @csrf

                            <input type="hidden" name="phone" value="{{ request('phone') }}">

                            <div class="mb-20">
                                <label class="label fs-16 mb-2">
                                    Verification OTP
                                </label>
                                <div class="form-floating">
                                    <input type="text" name="otp" maxlength="6" class="form-control"
                                        placeholder="Enter verification OTP *">
                                    <label>
                                        Enter verification OTP *
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary fw-normal text-white w-100"
                                    style="padding-top:18px;padding-bottom:18px;">
                                    Verify OTP
                                </button>
                            </div>

                            <div class="text-center">
                                <a href="{{ route('login') }}" class="fs-16 text-primary text-decoration-none">
                                    Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
