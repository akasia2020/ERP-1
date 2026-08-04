<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            $validated['username'],
            $validated['password'],
            $validated['role']
        );

        if (!$result['success']) {
            return back()->withErrors([
                'login' => $result['message']
            ])->withInput();
        }

        $request->session()->regenerate();

        $role = $result['role'];
        $routes = [
            'masterdata' => 'masterdata.dashboard',
            'gudang' => 'gudang.dashboard',
            'produksi' => 'produksi.dashboard',
            'whf' => 'whf.dashboard'
        ];

        return redirect()->route($routes[$role] ?? 'login');
    }

    public function logout(Request $request)
    {
        $this->authService->logout(auth()->id());

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}