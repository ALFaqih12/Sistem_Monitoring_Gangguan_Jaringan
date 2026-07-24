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
        Schema::create('rules', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Data Identitas Rule
            $table->string('code')->unique(); // Kode rule (auto-generated: R01, R02, dst)
            $table->string('name'); // Nama rule

            // Target Device Configuration
            $table->enum('target_type', ['specific', 'all'])->default('all'); // Tipe target: specific device atau all devices
            $table->unsignedBigInteger('target_device_id')->nullable(); // FK ke tabel devices (null jika target_type = 'all')
            
            // Status Rule
            $table->boolean('is_active')->default(true); // Toggle ON/OFF untuk mengaktifkan/nonaktifkan rule

            // Timestamps
            $table->timestamps();

            // Foreign Key Constraint
            $table->foreign('target_device_id')
                  ->references('id')
                  ->on('devices')
                  ->onDelete('cascade'); // Jika device dihapus, rule juga terhapus
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};