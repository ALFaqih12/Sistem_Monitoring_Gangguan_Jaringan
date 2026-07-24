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
        Schema::create('rule_conditions', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Relasi ke tabel rules
            $table->unsignedBigInteger('rule_id'); // FK ke tabel rules

            // Data Kondisi Forward Chaining (IF condition)
            $table->enum('parameter', ['bandwidth', 'latency', 'packet_loss']); // Parameter yang dicek
            $table->enum('operator', ['>', '<', '>=', '<=', '=']); // Operator pembanding
            $table->decimal('value', 10, 2); // Nilai threshold untuk kondisi

            // Timestamps
            $table->timestamps();

            // Foreign Key Constraint
            $table->foreign('rule_id')
                  ->references('id')
                  ->on('rules')
                  ->onDelete('cascade'); // Jika rule dihapus, semua kondisinya juga terhapus
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_conditions');
    }
};