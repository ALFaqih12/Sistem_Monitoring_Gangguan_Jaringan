<?php

namespace App\Console\Commands;

use App\Services\ForwardChainingService;
use Illuminate\Console\Command;

class EvaluateRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Signature ini akan digunakan untuk menjalankan command via artisan atau scheduler.
     *
     * @var string
     */
    protected $signature = 'rules:evaluate';

    /**
     * The console command description.
     * Deskripsi command yang akan tampil saat php artisan list
     *
     * @var string
     */
    protected $description = 'Evaluasi semua rule Forward Chaining terhadap data traffic dari Prometheus';

    /**
     * Execute the console command.
     * Method ini akan dipanggil saat command dijalankan.
     */
    public function handle(ForwardChainingService $forwardChaining): int
    {
        $this->info('🔍 Memulai evaluasi Forward Chaining...');
        $this->info('⏰ Waktu: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // Jalankan evaluasi semua rule
        $result = $forwardChaining->evaluateAllRules();

        // Tampilkan hasil evaluasi
        $this->info("✅ Evaluasi selesai!");
        $this->info("📊 Total rule yang dicek: {$result['checked']}");
        $this->info("⚠️  Gangguan terdeteksi: " . count($result['detected']));
        
        // Tampilkan detail gangguan yang terdeteksi (jika ada)
        if (count($result['detected']) > 0) {
            $this->newLine();
            $this->warn('📋 Detail Gangguan yang Terdeteksi:');
            $this->newLine();

            foreach ($result['detected'] as $index => $issue) {
                $this->line("  " . ($index + 1) . ". Rule: {$issue['rule']->code} - {$issue['rule']->name}");
                $this->line("     Device: {$issue['device']->ip_address} ({$issue['device']->customer_name})");
                
                // Tampilkan kondisi yang terpenuhi
                $this->line("     Kondisi yang match:");
                foreach ($issue['conditions'] as $condition) {
                    if ($condition['met']) {
                        $actual = $condition['actual'] !== null ? number_format($condition['actual'], 2) : 'N/A';
                        $this->line("       - {$condition['parameter']} {$condition['operator']} {$condition['threshold']} (Aktual: {$actual})");
                    }
                }
                $this->newLine();
            }
        } else {
            $this->newLine();
            $this->info('✨ Tidak ada gangguan yang terdeteksi. Semua device dalam kondisi normal.');
        }

        $this->newLine();
        $this->info('✅ Command selesai dijalankan.');

        return Command::SUCCESS;
    }
}