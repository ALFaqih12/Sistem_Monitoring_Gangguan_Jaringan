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
        Schema::table('issue_logs', function (Blueprint $table) {
            $table->json('matched_parameters')->nullable()->after('packet_loss_actual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issue_logs', function (Blueprint $table) {
            $table->dropColumn('matched_parameters');
        });
    }
};
