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
        Schema::create('issue_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->onDelete('cascade');
            $table->foreignId('rule_id')->constrained()->onDelete('cascade');
            $table->decimal('bandwidth_actual', 10, 2)->nullable();
            $table->decimal('latency_actual', 10, 2)->nullable();
            $table->decimal('packet_loss_actual', 10, 2)->nullable();
            $table->enum('status', ['new', 'ongoing', 'resolved'])->default('new');
            $table->text('notes')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index('status');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_logs');
    }
};
