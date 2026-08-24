<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration ini mengubah foreign key rules.target_device_id dari CASCADE menjadi SET NULL.
     * 
     * Sebelumnya: Jika device dihapus, rule yang menarget device spesifik ikut terhapus (CASCADE).
     * Sekarang:    Jika device dihapus, target_device_id pada rule menjadi NULL, rule tetap ada.
     * 
     * Alasan: User tidak ingin kehilangan rule ketika device dihapus.
     */
    public function up(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            // Drop foreign key lama yang menggunakan CASCADE
            $table->dropForeign(['target_device_id']);

            // Tambah foreign key baru dengan SET NULL agar rule tidak ikut terhapus
            $table->foreign('target_device_id')
                  ->references('id')
                  ->on('devices')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations: kembalikan ke CASCADE seperti semula.
     */
    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            // Drop foreign key SET NULL
            $table->dropForeign(['target_device_id']);

            // Kembalikan ke CASCADE
            $table->foreign('target_device_id')
                  ->references('id')
                  ->on('devices')
                  ->onDelete('cascade');
        });
    }
};
