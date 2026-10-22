<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel ini menyimpan history semua alert yang dikirim ke Telegram.
     * Digunakan untuk report mingguan/bulanan dan audit trail.
     */
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke tabel lain (nullable karena data bisa dihapus tapi log tetap ada)
            $table->foreignId('issue_log_id')->nullable()->constrained('issue_logs')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('rules')->nullOnDelete();
            
            // Snapshot data saat alert dikirim (tetap ada meski relasi dihapus)
            // Ini penting untuk historical report — data tidak boleh hilang meski device/rule dihapus
            $table->string('device_ip', 45);
            $table->string('device_name');
            $table->string('device_model')->nullable();
            $table->string('customer_id', 50);
            $table->string('rule_code', 10);
            $table->string('rule_name');
            $table->text('maps_url')->nullable();
            
            // Metrics snapshot (hanya yang relevan dengan rule)
            $table->decimal('bandwidth_actual', 10, 2)->nullable();
            $table->decimal('latency_actual', 10, 2)->nullable();
            $table->decimal('packet_loss_actual', 10, 2)->nullable();
            
            // Threshold conditions dari rule (JSON) — untuk tampil di report
            // Format: [{"parameter":"bandwidth","operator":">","value":100,"unit":"Mbps"}, ...]
            $table->json('rule_conditions');
            
            // Pesan lengkap yang dikirim ke Telegram (untuk audit/resend)
            $table->text('message');
            
            // Status pengiriman dengan retry tracking
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable(); // Error detail jika failed
            $table->integer('retry_count')->default(0); // Berapa kali sudah retry
            
            // Response dari Telegram API
            $table->string('telegram_message_id')->nullable(); // ID pesan di Telegram (untuk delete/edit nanti)
            
            // Timestamp
            $table->timestamp('sent_at')->nullable(); // Kapan berhasil terkirim
            $table->timestamp('detected_at'); // Kapan gangguan pertama kali terdeteksi
            $table->timestamps();
            
            // Index untuk query report (performa)
            $table->index('detected_at');
            $table->index(['status', 'detected_at']);
            $table->index('device_ip');
            $table->index('rule_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};
