<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler untuk menjalankan evaluasi Forward Chaining setiap 1 menit
// Hanya berjalan jika monitoring aktif (status disimpan di Cache, diatur dari Dashboard)
Schedule::command('rules:evaluate')
    ->everyMinute()
    ->when(function () {
        // Cek status monitoring dari cache.
        // Jika monitoring_active = false, scheduler tidak menjalankan evaluasi.
        return Cache::get('monitoring_active', false);
    })
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/forward-chaining.log'));
