<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder untuk default admin user.
 * Username: SiwaNetwork
 * Password: Admin123
 */
class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'username' => 'SiwaNetwork',
            'password' => Hash::make('Admin123'),
        ]);
    }
}
