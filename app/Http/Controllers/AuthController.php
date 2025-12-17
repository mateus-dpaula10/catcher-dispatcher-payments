<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('auth.index');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(
            [
                'email'    => ['required', 'email'],
                'password' => ['required', 'string', 'min:6']
            ],
            [
                'email.required'    => 'Informe seu e-mail.',
                'email.email'       => 'Digite um e-mail válido.',
                'password.required' => 'Informe sua senha.',
                'password.min'      => 'A senha deve ter pelo menos :min caracteres.',
            ]
        );
        
        $authCredentials = [
            'email'    => $credentials['email'],
            'password' => $credentials['password']
        ];

        if (Auth::attempt($authCredentials)) {
            $request->session()->regenerate();
            $request->session()->forget('url.intended');
            return redirect()->route('dashboard.index');
        }

        return back()
            ->withErrors(['email' => 'E-mail ou senha inválidos'])
            ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
