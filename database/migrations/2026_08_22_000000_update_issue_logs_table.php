<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Modifikasi tabel issue_logs:
     * - Hapus kolom status (enum new/ongoing/resolved) karena diganti dengan maps_url
     * - Hapus kolom notes karena tidak lagi diperlukan
     * - Tambah kolom maps_url (text nullable) untuk menyimpan URL Maps lokasi perangkat
     */
    public function up(): void
    {
        Schema::table('issue_logs', function (Blueprint $table) {
            // Hapus index status hanya di SQLite (test environment).
            // Di MySQL index mungkin tidak ada karena migrasi pertama mungkin tidak pernah
            // membuat index, atau MySQL handle otomatis saat dropColumn.
            // Di SQLite, dropColumn gagal jika masih ada index yang merujuk kolom itu.
            if (DB::getDriverName() === 'sqlite') {
                $table->dropIndex('issue_logs_status_index');
            }
            // Hapus kolom status dan notes yang tidak lagi dipakai
            $table->dropColumn(['status', 'notes']);
            // Tambah kolom maps_url untuk lokasi perangkat (diisi otomatis dari device saat log dibuat)
            $table->text('maps_url')->nullable()->after('packet_loss_actual');
        });
    }

    /**
     * Reverse the migrations.
     * Kembalikan kolom status, notes dan hapus maps_url
     */
    public function down(): void
    {
        Schema::table('issue_logs', function (Blueprint $table) {
            // Kembalikan kolom status dan notes
            $table->enum('status', ['new', 'ongoing', 'resolved'])->default('new');
            $table->text('notes')->nullable();
            // Hapus kolom maps_url
            $table->dropColumn('maps_url');
        });
    }
};
