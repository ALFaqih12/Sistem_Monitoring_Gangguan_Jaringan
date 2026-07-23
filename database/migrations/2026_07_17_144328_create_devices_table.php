<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            // Primary Key untuk relasi database (Tersembunyi dari user)
            $table->id();

            // Data Identitas Pelanggan (Ditampilkan ke Telegram/User)
            $table->string('customer_id')->unique();
            $table->string('customer_name');
            
            // Data Teknis Perangkat
            $table->string('ip_address')->unique();
            $table->string('device_model')->nullable();
            
            // Data Operasional & Lokasi
            $table->date('installation_date');
            $table->text('maps_url')->nullable();
            
            // Kolom otomatis created_at dan updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
