<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Controller untuk Kelola Admin (change password only).
 */
class AdminController extends Controller
{
    /**
     * Tampilkan halaman Kelola Admin.
     */
    public function index()
    {
        return Inertia::render('Admin/Profile', [
            'admin' => Auth::user(),
        ]);
    }

    /**
     * Update password admin.
     * Username tidak bisa diubah (fixed).
     */
    public function update(Request $request)
    {
        $admin = Auth::user();

        // Validate password fields
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $admin->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama salah',
            ]);
        }

        // Update password
        $admin->password = Hash::make($request->password);
        $admin->save();

        return back()->with('success', 'Password berhasil diubah');
    }
}
