<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Gunakan RefreshDatabase agar migrasi berjalan di database SQLite :memory:
     * sehingga query ke tabel devices dan rules bisa berjalan di test.
     */
    use RefreshDatabase;

    /**
     * Memastikan halaman root (Dashboard) dapat diakses dengan sukses.
     * Dashboard adalah halaman utama yang menampilkan statistik dan toggle monitoring.
     */
    public function test_the_application_returns_dashboard_successfully(): void
    {
        $response = $this->get('/');

        // Dashboard harus mengembalikan status 200
        $response->assertStatus(200);

        // Pastikan halaman mengandung elemen khas Dashboard
        $response->assertSee('Dashboard Monitoring');
        $response->assertSee('Total Target Device');
        $response->assertSee('Total Rule Forward Chaining');
        $response->assertSee('Start Monitoring');
    }
}
