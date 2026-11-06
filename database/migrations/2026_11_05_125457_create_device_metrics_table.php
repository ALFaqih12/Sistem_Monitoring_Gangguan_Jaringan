<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table untuk menyimpan metrics monitoring display per device.
     * Data selalu UPDATE (bukan INSERT), sehingga tidak ada historical data.
     */
    public function up(): void
    {
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->float('bandwidth')->nullable()->comment('Bandwidth dalam Mbps');
            $table->float('latency')->nullable()->comment('Latency dalam ms');
            $table->float('packet_loss')->nullable()->comment('Packet Loss dalam %');
            $table->timestamp('updated_at');
            
            // 1 row per device, always UPDATE
            $table->unique('device_id');
            
            // Index untuk performa query JOIN
            $table->index(['device_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_metrics');
    }
};
