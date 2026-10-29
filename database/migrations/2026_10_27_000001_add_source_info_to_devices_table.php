<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambah kolom untuk identifikasi sumber device dan info Core Router.
     * Diperlukan agar sistem bisa query bandwidth dengan method yang berbeda:
     * - Manual add: query SNMP langsung ke device
     * - Discovery: query SNMP ke Core Router (DHCP lease counter)
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Source device: 'manual' atau 'discovery'
            // Default 'manual' untuk backward compatibility (device existing)
            $table->enum('source', ['manual', 'discovery'])->default('manual')->after('maps_url');
            
            // IP Core Router (hanya diisi jika source = 'discovery')
            // Digunakan untuk query SNMP ke Core Router untuk ambil bandwidth client
            $table->string('core_router_ip', 45)->nullable()->after('source');
            
            // Index untuk query performance
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'core_router_ip']);
        });
    }
};
