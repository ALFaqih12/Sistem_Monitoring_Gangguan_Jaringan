<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckBlackboxTargets extends Command
{
    protected $signature = 'blackbox:check';
    protected $description = 'Cek konfigurasi blackbox exporter dan metrics yang tersedia';

    public function handle(): int
    {
        $prometheusUrl = env('PROMETHEUS_URL', 'http://localhost:9090');

        $this->info('[INFO] Memeriksa Blackbox Exporter Targets...');
        $this->info('[LOC] Prometheus URL: ' . $prometheusUrl);
        $this->newLine();

        // 1. Cek targets dengan job=ping
        $this->info('[LIST] STEP 1: Cek Targets dengan Job "ping"');
        $this->line(str_repeat('=', 80));

        try {
            $response = Http::timeout(5)->get("{$prometheusUrl}/api/v1/targets");
            
            if ($response->successful()) {
                $data = $response->json();
                $activeTargets = $data['data']['activeTargets'] ?? [];
                
                $pingTargets = array_filter($activeTargets, function($target) {
                    return ($target['labels']['job'] ?? '') === 'ping';
                });

                if (empty($pingTargets)) {
                    $this->warn('[WARN] Tidak ada targets dengan job="ping"');
                    $this->newLine();
                    $this->info('[TIPS] Anda perlu menambahkan konfigurasi blackbox di prometheus.yml:');
                    $this->line('');
                    $this->line('  - job_name: "ping"');
                    $this->line('    metrics_path: /probe');
                    $this->line('    params:');
                    $this->line('      module: [icmp]');
                    $this->line('    static_configs:');
                    $this->line('      - targets:');
                    $this->line('        - 192.168.18.200');
                    $this->line('    relabel_configs:');
                    $this->line('      - source_labels: [__address__]');
                    $this->line('        target_label: __param_target');
                    $this->line('      - source_labels: [__param_target]');
                    $this->line('        target_label: instance');
                    $this->line('      - target_label: __address__');
                    $this->line('        replacement: blackbox:9115');
                    return Command::FAILURE;
                }

                $this->info('[OK] Ditemukan ' . count($pingTargets) . ' target(s) dengan job="ping"');
                $this->newLine();

                foreach ($pingTargets as $target) {
                    $instance = $target['labels']['instance'] ?? 'N/A';
                    $health = $target['health'] ?? 'unknown';
                    $scrapeUrl = $target['scrapeUrl'] ?? 'N/A';
                    
                    $healthIcon = $health === 'up' ? '[OK] ' : '[ERROR] ';
                    $this->line("{$healthIcon} Instance: {$instance}");
                    $this->line("   Health: {$health}");
                    $this->line("   Scrape URL: {$scrapeUrl}");
                    $this->newLine();
                }

            } else {
                $this->error('[ERROR] Gagal connect ke Prometheus API');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('[ERROR] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // 2. Cek metrics yang tersedia dari blackbox
        $this->newLine();
        $this->info('[DATA] STEP 2: Cek Metrics Blackbox yang Tersedia');
        $this->line(str_repeat('=', 80));

        $testIp = '192.168.18.140'; // Dari output sebelumnya
        
        // Cek probe_success
        $this->info('[WAIT] Mengecek probe_success...');
        $query = "probe_success{instance=\"{$testIp}\"}";
        $result = $this->queryPrometheus($prometheusUrl, $query);
        
        if ($result !== null) {
            $this->info("[OK] probe_success: {$result} (1=UP, 0=DOWN)");
        } else {
            $this->warn("[WARN] probe_success: Tidak ada data");
        }

        // Cek probe_duration_seconds
        $this->info('[WAIT] Mengecek probe_duration_seconds...');
        $query = "probe_duration_seconds{instance=\"{$testIp}\"}";
        $result = $this->queryPrometheus($prometheusUrl, $query);
        
        if ($result !== null) {
            $latencyMs = $result * 1000;
            $this->info("[OK] probe_duration_seconds: {$result} seconds ({$latencyMs} ms)");
        } else {
            $this->warn("[WARN] probe_duration_seconds: Tidak ada data");
        }

        // Cek probe_icmp_duration_seconds
        $this->info('[WAIT] Mengecek probe_icmp_duration_seconds...');
        $query = "probe_icmp_duration_seconds{instance=\"{$testIp}\"}";
        $result = $this->queryPrometheus($prometheusUrl, $query);
        
        if ($result !== null) {
            $latencyMs = $result * 1000;
            $this->info("[OK] probe_icmp_duration_seconds: {$result} seconds ({$latencyMs} ms)");
        } else {
            $this->warn("[WARN] probe_icmp_duration_seconds: Tidak ada data");
        }

        $this->newLine();
        $this->line(str_repeat('=', 80));
        $this->newLine();

        // 3. Rekomendasi Query
        $this->info('[TIPS] Rekomendasi Query untuk PrometheusService.php:');
        $this->newLine();
        $this->line('[LOC] LATENCY:');
        $this->line('   $query = "probe_icmp_duration_seconds{instance=\"{$ipAddress}\"} * 1000";');
        $this->line('   atau');
        $this->line('   $query = "probe_duration_seconds{instance=\"{$ipAddress}\"} * 1000";');
        $this->newLine();
        $this->line('[LOC] PACKET LOSS:');
        $this->line('   $query = "(1 - probe_success{instance=\"{$ipAddress}\"}) * 100";');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function queryPrometheus(string $url, string $query): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$url}/api/v1/query", ['query' => $query]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
        
        return null;
    }
}