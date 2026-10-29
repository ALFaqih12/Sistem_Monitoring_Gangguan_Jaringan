<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\IssueLogController;
use App\Http\Controllers\AlertController;

// Route utama Dashboard (root /)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');

// Routes untuk Modul Target Device
Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
Route::get('/devices/create', function () {
    return redirect()->route('devices.index');
})->name('devices.create');
Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
Route::post('/devices/ping', [DeviceController::class, 'pingDevice'])->name('devices.ping');
Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

// Routes untuk Discovery via MikroTik Core Router
Route::post('/devices/test-core-router', [DeviceController::class, 'testCoreRouter'])->name('devices.test-core-router');
Route::post('/devices/connect-core-router', [DeviceController::class, 'connectCoreRouter'])->name('devices.connect-core-router');
Route::post('/devices/add-client', [DeviceController::class, 'addClientDevice'])->name('devices.add-client');

// Routes untuk Modul Rule Forward Chaining
Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
Route::get('/rules/create', [RuleController::class, 'create'])->name('rules.create');
Route::post('/rules', [RuleController::class, 'store'])->name('rules.store');
Route::get('/rules/{rule}/edit', [RuleController::class, 'edit'])->name('rules.edit');
Route::put('/rules/{rule}', [RuleController::class, 'update'])->name('rules.update');
Route::delete('/rules/{rule}', [RuleController::class, 'destroy'])->name('rules.destroy');
Route::post('/rules/{rule}/toggle', [RuleController::class, 'toggle'])->name('rules.toggle');

// Routes untuk Modul Log Gangguan
Route::get('/issue-logs', [IssueLogController::class, 'index'])->name('issue-logs.index');
// API endpoint untuk auto-refresh UI via AJAX (fetch data tiap 30 detik)
Route::get('/api/issue-logs', [IssueLogController::class, 'indexApi'])->name('api.issue-logs.index');

// Route AJAX untuk toggle monitoring di Dashboard
Route::post('/monitoring/toggle', [DashboardController::class, 'toggleMonitoring'])->name('monitoring.toggle');

// Routes untuk Modul Manajemen Alert
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
Route::post('/alerts/toggle', [AlertController::class, 'toggle'])->name('alerts.toggle');
Route::post('/alerts/test-connection', [AlertController::class, 'testConnection'])->name('alerts.test-connection');
Route::post('/alerts/test-alert', [AlertController::class, 'sendTestAlert'])->name('alerts.test-alert');
Route::get('/alerts/export-pdf', [AlertController::class, 'exportPdf'])->name('alerts.export-pdf');
Route::post('/alerts/{alertLog}/resend', [AlertController::class, 'resend'])->name('alerts.resend');
Route::get('/alerts/{alertLog}', [AlertController::class, 'show'])->name('alerts.show');
