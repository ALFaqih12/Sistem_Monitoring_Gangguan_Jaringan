<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupBlackboxTargets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blackbox:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup file blackbox-targets.json untuk auto-discovery target ping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Setup Blackbox Targets...');
        $this->newLine();

        // Path file blackbox-targets.json (di luar folder web-app)
        $filePath = base_path('../prometheus/blackbox-targets.json');

        // Ambil semua IP dari database
        $devices = \App\Models\Device::pluck('ip_address')->toArray();

        // Tambahkan IP yang sudah ada di targets.json (existing)
        $existingTargetsPath = base_path('../prometheus/targets.json');
        if (File::exists($existingTargetsPath)) {
            $existingTargets = json_decode(File::get($existingTargetsPath), true);
            if (is_array($existingTargets)) {
                foreach ($existingTargets as $target) {
                    if (isset($target['targets'])) {
                        foreach ($target['targets'] as $ip) {
                            if (!in_array($ip, $devices)) {
                                $devices[] = $ip;
                            }
                        }
                    }
                }
            }
        }

        if (empty($devices)) {
            $this->warn('⚠️  Tidak ada device ditemukan. File akan dibuat dengan array kosong.');
        } else {
            $this->info('✅ Ditemukan ' . count($devices) . ' IP address.');
        }

        // Buat array targets untuk blackbox
        $blackboxTargets = [];
        foreach ($devices as $ip) {
            $blackboxTargets[] = [
                'targets' => [$ip],
                'labels' => [
                    'instance' => $ip,
                    'job' => 'ping',
                ],
            ];
        }

        // Tulis ke file
        $json = json_encode($blackboxTargets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        try {
            $bytesWritten = File::put($filePath, $json);
            
            if ($bytesWritten === false) {
                $this->error('❌ Gagal menulis file: ' . $filePath);
                $this->info('💡 Coba jalankan terminal sebagai Administrator.');
                return Command::FAILURE;
            }
            
            $this->info('✅ File berhasil dibuat: ' . $filePath);
            $this->newLine();
            
            // Tampilkan isi file
            $this->line('📄 Isi file:');
            $this->line($json);
            $this->newLine();
            
            $this->info('✅ Berhasil!');
            $this->newLine();
            
            $this->info('⚙️  Langkah selanjutnya:');
            $this->line('  1. Tambahkan konfigurasi file_sd_configs di prometheus.yml');
            $this->line('  2. Restart Prometheus');
            $this->line('  3. Jalankan: php artisan test:prometheus --ip=IP_ANDA');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->info('💡 Coba jalankan terminal sebagai Administrator.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}