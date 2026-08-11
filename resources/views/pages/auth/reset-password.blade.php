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
                                Reset Password
                            </h3>
                            <p class="text-secondary">
                                Enter your new password below.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('password.reset.update') }}">
                            @csrf

                            @if ($errors->any())
                                <div class="alert alert-danger mb-3">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <input type="hidden" name="phone" value="{{ request('phone') }}">
                            <div class="mb-20">
                                <label class="label fs-16 mb-2">
                                    New Password
                                </label>
                                <div class="form-floating">
                                    <input type="password" name="password" class="form-control"
                                        placeholder="Enter new password *">
                                    <label>
                                        Enter new password *
                                    </label>
                                </div>
                            </div>
                            <div class="mb-20">

                                <label class="label fs-16 mb-2">
                                    Confirm Password
                                </label>


                                <input type="password" name="password_confirmation" class="form-control"
                                    placeholder="Confirm password *">


                            </div>
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary fw-normal text-white w-100"
                                    style="padding-top:18px;padding-bottom:18px;">
                                    Update Password
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
