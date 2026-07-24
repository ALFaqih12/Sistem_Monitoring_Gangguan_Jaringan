<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler untuk menjalankan evaluasi Forward Chaining setiap 1 menit
Schedule::command('rules:evaluate')
    ->everyMinute()
    ->withoutOverlapping() // Mencegah command berjalan paralel jika yang sebelumnya belum selesai
    ->runInBackground() // Menjalankan di background agar tidak blocking
    ->appendOutputTo(storage_path('logs/forward-chaining.log')); // Log output ke file