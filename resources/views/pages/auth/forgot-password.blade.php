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
                                Forgot Password
                            </h3>
                            <p class="text-secondary">
                                Enter your registered phone number
                                to receive an OTP.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('password.send') }}">
                            @csrf

                            @if ($errors->any())
                                <div class="alert alert-danger mb-3">
                                    {{ $errors->first() }}
                                </div>
                            @endif
                            <div class="mb-20">
                                <label class="label fs-16 mb-2">
                                    Phone Number
                                </label>
                                <div class="form-floating">
                                    <input type="text" name="phone" value="{{ old('phone') }}"
                                        class="form-control" placeholder="Enter phone number *">
                                    <label>
                                        Enter phone number *
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary fw-normal text-white w-100"
                                    style="padding-top:18px;padding-bottom:18px;">
                                    Send OTP
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
