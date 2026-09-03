<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShowPrometheusMetrics extends Command
{
    /**
     * Signature command
     */
    protected $signature = 'prometheus:show-metrics {ip : IP address untuk dicek}';

    /**
     * Deskripsi command
     */
    protected $description = 'Menampilkan semua metrics yang tersedia di Prometheus untuk IP tertentu';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ip = $this->argument('ip');
        $prometheusUrl = env('PROMETHEUS_URL', 'http://localhost:9090');

        $this->info("[INFO] Mencari metrics untuk IP: {$ip}");
        $this->info("[LOC] Prometheus URL: {$prometheusUrl}");
        $this->newLine();

        try {
            // Query untuk mendapatkan semua series yang ada untuk IP ini
            $query = "{instance=\"{$ip}\"}";
            
            $response = Http::timeout(10)->get("{$prometheusUrl}/api/v1/series", [
                'match[]' => $query
            ]);

            if (!$response->successful()) {
                $this->error('[ERROR] Gagal query Prometheus API');
                return Command::FAILURE;
            }

            $data = $response->json();
            $series = $data['data'] ?? [];

            if (empty($series)) {
                $this->warn("[WARN] Tidak ada metrics ditemukan untuk IP: {$ip}");
                $this->newLine();
                $this->info('[TIPS] Kemungkinan:');
                $this->line('  1. IP belum terdaftar sebagai target di Prometheus');
                $this->line('  2. Format label instance berbeda (coba tambah port, misal: 192.168.18.200:161)');
                $this->line('  3. Target belum berhasil di-scrape');
                return Command::FAILURE;
            }

            $this->info("[OK] Ditemukan " . count($series) . " metrics untuk IP ini");
            $this->newLine();

            // Kelompokkan metrics berdasarkan nama metric
            $metricsByName = [];
            foreach ($series as $serie) {
                $metricName = $serie['__name__'] ?? 'unknown';
                
                if (!isset($metricsByName[$metricName])) {
                    $metricsByName[$metricName] = [];
                }
                
                $metricsByName[$metricName][] = $serie;
            }

            $this->info("[DATA] Daftar Metrics yang Tersedia:");
            $this->line(str_repeat('=', 80));

            foreach ($metricsByName as $metricName => $metrics) {
                $this->line("");
                $this->line("[LOC] Metric: {$metricName}");
                
                // Tampilkan beberapa contoh label
                $sampleMetric = $metrics[0];
                unset($sampleMetric['__name__']);
                
                $labels = [];
                foreach ($sampleMetric as $key => $value) {
                    $labels[] = "{$key}=\"{$value}\"";
                }
                
                $this->line("   Labels: " . implode(', ', $labels));
                
                // Coba query nilai terkini untuk metric ini
                $this->queryMetricValue($prometheusUrl, $metricName, $ip);
            }

            $this->newLine();
            $this->line(str_repeat('=', 80));
            $this->newLine();

            // Rekomendasi berdasarkan metric yang ditemukan
            $this->info("[TIPS] Rekomendasi Query untuk PrometheusService.php:");
            $this->newLine();

            $this->suggestQueries($metricsByName);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('[ERROR] Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Query nilai terkini dari metric
     */
    private function queryMetricValue(string $prometheusUrl, string $metricName, string $ip): void
    {
        try {
            $query = "{$metricName}{instance=\"{$ip}\"}";
            
            $response = Http::timeout(5)->get("{$prometheusUrl}/api/v1/query", [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['data']['result'] ?? [];
                
                if (!empty($results)) {
                    $value = $results[0]['value'][1] ?? 'N/A';
                    $this->line("   Nilai Terkini: {$value}");
                }
            }
        } catch (\Exception $e) {
            // Silent fail, tidak perlu tampilkan error
        }
    }

    /**
     * Berikan rekomendasi query berdasarkan metric yang ditemukan
     */
    private function suggestQueries(array $metricsByName): void
    {
        $metricNames = array_keys($metricsByName);

        // Cari metric untuk Bandwidth
        $bandwidthMetrics = array_filter($metricNames, function($name) {
            return stripos($name, 'octets') !== false || 
                   stripos($name, 'bytes') !== false ||
                   stripos($name, 'traffic') !== false;
        });

        if (!empty($bandwidthMetrics)) {
            $this->line("[SIGNAL] BANDWIDTH - Metric yang bisa digunakan:");
            foreach ($bandwidthMetrics as $metric) {
                $this->line("   *  {$metric}");
                $this->line("     Query: rate({$metric}{instance=\"{{ipAddress}}\"}[5m]) * 8 / 1000000");
            }
            $this->newLine();
        }

        // Cari metric untuk Latency
        $latencyMetrics = array_filter($metricNames, function($name) {
            return stripos($name, 'duration') !== false || 
                   stripos($name, 'latency') !== false ||
                   stripos($name, 'rtt') !== false ||
                   stripos($name, 'ping') !== false;
        });

        if (!empty($latencyMetrics)) {
            $this->line("[TIME] LATENCY - Metric yang bisa digunakan:");
            foreach ($latencyMetrics as $metric) {
                $this->line("   *  {$metric}");
                $this->line("     Query: {$metric}{instance=\"{{ipAddress}}\"} * 1000");
            }
            $this->newLine();
        }

        // Cari metric untuk Packet Loss
        $packetLossMetrics = array_filter($metricNames, function($name) {
            return stripos($name, 'loss') !== false || 
                   stripos($name, 'success') !== false ||
                   stripos($name, 'probe') !== false ||
                   stripos($name, 'error') !== false;
        });

        if (!empty($packetLossMetrics)) {
            $this->line("[GRAPH]  PACKET LOSS - Metric yang bisa digunakan:");
            foreach ($packetLossMetrics as $metric) {
                $this->line("   *  {$metric}");
                if (stripos($metric, 'success') !== false) {
                    $this->line("     Query: (1 - {$metric}{instance=\"{{ipAddress}}\"}) * 100");
                } else {
                    $this->line("     Query: {$metric}{instance=\"{{ipAddress}}\"}");
                }
            }
            $this->newLine();
        }

        if (empty($bandwidthMetrics) && empty($latencyMetrics) && empty($packetLossMetrics)) {
            $this->warn("[WARN] Tidak ditemukan metric yang umum untuk bandwidth/latency/packet loss");
            $this->line("   Anda mungkin perlu menyesuaikan query secara manual");
        }
    }
}