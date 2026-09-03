<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Rule;
use App\Models\RuleCondition;
use Illuminate\Console\Command;

class CreateTestData extends Command
{
    /**
     * Signature command
     */
    protected $signature = 'test:create-data';

    /**
     * Deskripsi command
     */
    protected $description = 'Membuat data dummy untuk testing Forward Chaining';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('[SETUP] Membuat Data Dummy untuk Testing...');
        $this->newLine();

        // Cek apakah sudah ada device
        $device = Device::first();

        if (!$device) {
            $this->warn('[WARN] Tidak ada device di database.');
            $this->info('[TIPS] Silakan tambahkan device melalui Web UI: http://localhost:8000/devices');
            return Command::FAILURE;
        }

        $this->info("[OK] Device ditemukan: {$device->ip_address} - {$device->customer_name}");
        $this->newLine();

        // Buat 3 rule testing dengan kondisi berbeda
        $this->info('[CREATE] Membuat Rule Testing...');

        // Rule 1: Latency Tinggi
        $rule1 = Rule::create([
            'name' => 'Test - Latency Tinggi',
            'target_type' => 'all',
            'is_active' => true
        ]);

        RuleCondition::create([
            'rule_id' => $rule1->id,
            'parameter' => 'latency',
            'operator' => '>',
            'value' => 50
        ]);

        $this->line("  [OK] {$rule1->code}: {$rule1->name}");
        $this->line("     Kondisi: IF latency > 50 ms THEN gangguan");

        // Rule 2: Packet Loss Tinggi
        $rule2 = Rule::create([
            'name' => 'Test - Packet Loss Tinggi',
            'target_type' => 'all',
            'is_active' => true
        ]);

        RuleCondition::create([
            'rule_id' => $rule2->id,
            'parameter' => 'packet_loss',
            'operator' => '>',
            'value' => 5
        ]);

        $this->line("  [OK] {$rule2->code}: {$rule2->name}");
        $this->line("     Kondisi: IF packet_loss > 5 % THEN gangguan");

        // Rule 3: Gangguan Kompleks (Multiple Conditions)
        $rule3 = Rule::create([
            'name' => 'Test - Gangguan Kompleks',
            'target_type' => 'all',
            'is_active' => true
        ]);

        RuleCondition::create([
            'rule_id' => $rule3->id,
            'parameter' => 'latency',
            'operator' => '>',
            'value' => 100
        ]);

        RuleCondition::create([
            'rule_id' => $rule3->id,
            'parameter' => 'packet_loss',
            'operator' => '>',
            'value' => 2
        ]);

        $this->line("  [OK] {$rule3->code}: {$rule3->name}");
        $this->line("     Kondisi: IF latency > 100 ms AND packet_loss > 2 % THEN gangguan");

        $this->newLine();
        $this->info('[OK] Data dummy berhasil dibuat!');
        $this->newLine();
        $this->info('[TEST] Sekarang Anda bisa test evaluasi dengan command:');
        $this->line('   php artisan rules:evaluate');

        return Command::SUCCESS;
    }
}