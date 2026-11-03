<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Controller untuk autentikasi admin (login/logout).
 */
class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Proses autentikasi admin.
     * Validasi username dan password, create session jika valid.
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Attempt login dengan guard 'web' dan table admins
        if (Auth::guard('web')->attempt($credentials)) {
            // Regenerate session untuk prevent fixation attack
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        // Login gagal
        return back()->withErrors([
            'username' => 'Username atau password salah',
        ]);
    }

    /**
     * Logout admin.
     * Hapus session dan redirect ke login page.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
