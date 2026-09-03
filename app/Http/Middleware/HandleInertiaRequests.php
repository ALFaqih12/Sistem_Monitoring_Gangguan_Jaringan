<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // Flash message (success/error) hasil redirect — dibutuhkan agar
            // notifikasi sukses/error (mis. "Data target device berhasil ditambahkan!")
            // tampil di sisi klien Vue. Inertia tidak share flash secara default.
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],

            // Old input hasil withInput() — agar form kembali berisi data lama bila
            // validasi gagal via redirect-back. Meniru fungsi old() pada Blade.
            'old' => $request->old(),
        ];
    }
}
