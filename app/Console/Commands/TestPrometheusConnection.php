<?php

namespace App\Console\Commands;

use App\Services\PrometheusService;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestPrometheusConnection extends Command
{
    /**
     * Signature command untuk testing Prometheus
     */
    protected $signature = 'test:prometheus {--ip= : IP address untuk testing}';

    /**
     * Deskripsi command
     */
    protected $description = 'Test koneksi ke Prometheus dan query metrics';

    /**
     * Execute the console command.
     */
    public function handle(PrometheusService $prometheus): int
    {
        $this->info('[INFO] Testing Koneksi Prometheus');
        $this->info('================================');
        $this->newLine();

        // 1. Cek URL Prometheus dari environment
        $prometheusUrl = env('PROMETHEUS_URL', 'http://localhost:9090');
        $this->info("[LOC] Prometheus URL: {$prometheusUrl}");
        $this->newLine();

        // 2. Test koneksi ke Prometheus server
        $this->info('[WAIT] Testing koneksi ke Prometheus server...');
        
        if ($prometheus->checkConnection()) {
            $this->info('[OK] Prometheus server UP dan dapat dijangkau!');
        } else {
            $this->error('[ERROR] Prometheus server DOWN atau tidak dapat dijangkau!');
            $this->warn('[TIPS] Pastikan Prometheus running di: ' . $prometheusUrl);
            return Command::FAILURE;
        }
        
        $this->newLine();

        // 3. List semua target yang terdaftar di Prometheus
        $this->info('[LIST] Mengecek targets yang terdaftar di Prometheus...');
        try {
            $response = Http::timeout(5)->get("{$prometheusUrl}/api/v1/targets");
            
            if ($response->successful()) {
                $data = $response->json();
                $activeTargets = $data['data']['activeTargets'] ?? [];
                
                $this->info('[OK] Total active targets: ' . count($activeTargets));
                
                if (count($activeTargets) > 0) {
                    $this->newLine();
                    $this->info('[DATA] Daftar Active Targets:');
                    
                    foreach ($activeTargets as $index => $target) {
                        $instance = $target['labels']['instance'] ?? 'N/A';
                        $job = $target['labels']['job'] ?? 'N/A';
                        $health = $target['health'] ?? 'unknown';
                        
                        $healthIcon = $health === 'up' ? '[OK] ' : '[ERROR] ';
                        $this->line("  {$healthIcon} [{$job}] {$instance} - Health: {$health}");
                    }
                } else {
                    $this->warn('[WARN] Tidak ada active targets di Prometheus!');
                }
            }
        } catch (\Exception $e) {
            $this->error('[ERROR] Gagal mengambil daftar targets: ' . $e->getMessage());
        }

        $this->newLine();

        // 4. Test query metrics untuk IP tertentu
        $ipToTest = $this->option('ip');
        
        if (!$ipToTest) {
            // Jika tidak ada IP dari option, ambil device pertama dari database
            $device = Device::first();
            
            if ($device) {
                $ipToTest = $device->ip_address;
                $this->info("[TIPS] Menggunakan IP dari database: {$ipToTest}");
            } else {
                $this->warn('[WARN] Tidak ada device di database untuk testing.');
                $this->info('[TIPS] Anda bisa test dengan IP manual: php artisan test:prometheus --ip=192.168.1.1');
                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info("[INFO] Testing query metrics untuk IP: {$ipToTest}");
        $this->line('-------------------------------------------');

        // Test Bandwidth
        $this->info('[WAIT] Query Bandwidth...');
        $bandwidth = $prometheus->getBandwidth($ipToTest);
        if ($bandwidth !== null) {
            $this->info("[OK] Bandwidth: " . number_format($bandwidth, 2) . " Mbps");
        } else {
            $this->warn("[WARN] Bandwidth: Tidak ada data (query mungkin perlu disesuaikan)");
        }

        // Test Latency
        $this->info('[WAIT] Query Latency...');
        $latency = $prometheus->getLatency($ipToTest);
        if ($latency !== null) {
            $this->info("[OK] Latency: " . number_format($latency, 2) . " ms");
        } else {
            $this->warn("[WARN] Latency: Tidak ada data (query mungkin perlu disesuaikan)");
        }

        // Test Packet Loss
        $this->info('[WAIT] Query Packet Loss...');
        $packetLoss = $prometheus->getPacketLoss($ipToTest);
        if ($packetLoss !== null) {
            $this->info("[OK] Packet Loss: " . number_format($packetLoss, 2) . " %");
        } else {
            $this->warn("[WARN] Packet Loss: Tidak ada data (query mungkin perlu disesuaikan)");
        }

        $this->newLine();

        // 5. Kesimpulan dan saran
        if ($bandwidth === null && $latency === null && $packetLoss === null) {
            $this->newLine();
            $this->warn('[WARN] SEMUA METRICS NULL!');
            $this->newLine();
            $this->info('[TIPS] Kemungkinan penyebab:');
            $this->line('  1. Query PromQL di PrometheusService.php perlu disesuaikan');
            $this->line('  2. Metric name tidak sesuai dengan yang ada di Prometheus');
            $this->line('  3. Label instance tidak sesuai format');
            $this->newLine();
            $this->info('[SETUP] Cara cek metric yang tersedia:');
            $this->line("  1. Buka browser: {$prometheusUrl}");
            $this->line('  2. Klik tab "Graph"');
            $this->line('  3. Ketik nama metric (misal: ifInOctets, probe_duration_seconds)');
            $this->line('  4. Lihat metric dan label yang tersedia');
            $this->line('  5. Sesuaikan query di app/Services/PrometheusService.php');
        } else {
            $this->info('[OK] Berhasil mendapatkan data dari Prometheus!');
            $this->info('[OK] Sistem Forward Chaining siap digunakan!');
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}