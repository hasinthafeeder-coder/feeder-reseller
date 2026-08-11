<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Feeder\Core\Enums\PortalCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly LoginService $loginService) {}

    public function create(): View
    {

        return view('pages.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->loginService->login($request, PortalCode::RESELLER);
        return redirect()->route('dashboard'); // Adjust the route as needed
    }
}
