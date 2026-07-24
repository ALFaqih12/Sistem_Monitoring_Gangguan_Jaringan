<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Memastikan halaman root mengarah ke modul utama Manajemen Target Device.
     */
    public function test_the_application_redirects_to_device_management(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('devices.index'));
    }
}
