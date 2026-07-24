<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\RuleController;

Route::get('/', function () {
    return redirect()->route('devices.index');
});

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

// Routes untuk Modul Rule Forward Chaining
Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
Route::get('/rules/create', [RuleController::class, 'create'])->name('rules.create');
Route::post('/rules', [RuleController::class, 'store'])->name('rules.store');
Route::get('/rules/{rule}/edit', [RuleController::class, 'edit'])->name('rules.edit');
Route::put('/rules/{rule}', [RuleController::class, 'update'])->name('rules.update');
Route::delete('/rules/{rule}', [RuleController::class, 'destroy'])->name('rules.destroy');
Route::post('/rules/{rule}/toggle', [RuleController::class, 'toggle'])->name('rules.toggle');