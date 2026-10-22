<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel ini menyimpan konfigurasi Telegram Bot untuk alert monitoring.
     * Design: Single-row config (hanya 1 konfigurasi bot aktif).
     */
    public function up(): void
    {
        Schema::create('alert_settings', function (Blueprint $table) {
            $table->id();
            
            // Konfigurasi Telegram Bot
            $table->text('bot_token'); // Encrypted di model, format: 1234567890:AAH...
            $table->string('chat_id'); // Format: -1001234567890 (grup) atau 1234567890 (private)
            
            // Status alert global
            $table->boolean('is_active')->default(false); // Toggle ON/OFF alert via UI
            
            // Konfigurasi deduplikasi (TTL dalam detik)
            // Mencegah alert duplicate untuk gangguan yang sama dalam waktu dekat
            $table->integer('deduplication_ttl')->default(600); // 10 menit
            
            // Konfigurasi retry mechanism
            $table->integer('retry_attempts')->default(3); // Berapa kali retry jika Telegram API error
            $table->integer('retry_delay')->default(5); // Delay antar retry (detik)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_settings');
    }
};
