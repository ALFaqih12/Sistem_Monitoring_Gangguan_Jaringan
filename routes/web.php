<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;

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
