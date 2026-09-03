<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    private string $baseUrl;
    private bool $simulationMode;

    public function __construct()
    {
        $this->baseUrl = env('PROMETHEUS_URL', 'http://localhost:9090');
        $this->simulationMode = env('PROMETHEUS_SIMULATION_MODE', false);

        // Log warning jika simulation mode aktif
        // Ini sangat penting agar admin tahu bahwa data adalah SIMULASI, bukan data real!
        if ($this->simulationMode) {
            Log::warning('[WARN] PROMETHEUS SIMULATION MODE ACTIVE! Data yang digunakan adalah FAKE/SIMULASI, bukan data real dari Prometheus.');
            Log::warning('Untuk menggunakan data real, set PROMETHEUS_SIMULATION_MODE=false di file .env');
        }
    }

    public function getBandwidth(string $ipAddress): ?float
    {
        try {
            $query = "rate(ifHCInOctets{instance=\"{$ipAddress}\"}[5m]) * 8 / 1000000";
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $query]);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get bandwidth for {$ipAddress}: " . $e->getMessage());
            return null;
        }
    }

    public function getLatency(string $ipAddress): ?float
    {
        try {
            $query = "probe_icmp_duration_seconds{instance=\"{$ipAddress}\",phase=\"rtt\"} * 1000";
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $query]);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
            $query = "probe_duration_seconds{instance=\"{$ipAddress}\"} * 1000";
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $query]);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
            if ($this->simulationMode) {
                $fakeLatency = rand(10, 150) + (rand(0, 99) / 100);
                Log::debug("Simulation mode: Generating fake latency {$fakeLatency}ms for {$ipAddress}");
                return $fakeLatency;
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Failed latency for {$ipAddress}: " . $e->getMessage());
            if ($this->simulationMode) {
                $fakeLatency = rand(10, 150) + (rand(0, 99) / 100);
                Log::debug("Simulation mode (error fallback): Generating fake latency {$fakeLatency}ms for {$ipAddress}");
                return $fakeLatency;
            }
            return null;
        }
    }

    public function getPacketLoss(string $ipAddress): ?float
    {
        try {
            $query = "(1 - probe_success{instance=\"{$ipAddress}\"}) * 100";
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $query]);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
            if ($this->simulationMode) {
                $fakePacketLoss = rand(0, 10) + (rand(0, 99) / 100);
                Log::debug("Simulation mode: Generating fake packet loss {$fakePacketLoss}% for {$ipAddress}");
                return $fakePacketLoss;
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Failed packet loss for {$ipAddress}: " . $e->getMessage());
            if ($this->simulationMode) {
                $fakePacketLoss = rand(0, 10) + (rand(0, 99) / 100);
                Log::debug("Simulation mode (error fallback): Generating fake packet loss {$fakePacketLoss}% for {$ipAddress}");
                return $fakePacketLoss;
            }
            return null;
        }
    }

    public function getAllMetrics(string $ipAddress): array
    {
        return [
            'bandwidth' => $this->getBandwidth($ipAddress),
            'latency' => $this->getLatency($ipAddress),
            'packet_loss' => $this->getPacketLoss($ipAddress),
        ];
    }

    /**
     * Ambil metrik untuk BANYAK IP dalam 1 batch query Prometheus.
     * Hanya 3 query (bandwidth, latency, packet_loss) untuk semua device.
     * 
     * @param array $ipAddresses Daftar IP
     * @return array ['ip' => ['bandwidth'=>null|float, 'latency'=>null|float, 'packet_loss'=>null|float]]
     */
    public function getAllMetricsBatch(array $ipAddresses): array
    {
        if (empty($ipAddresses)) return [];

        $results = [];
        foreach ($ipAddresses as $ip) {
            $results[$ip] = ['bandwidth' => null, 'latency' => null, 'packet_loss' => null];
        }

        // Regex pattern: instance=~"ip1|ip2|ip3"
        // Dot (.) di regex match karakter apapun, termasuk literal dot - jadi tidak perlu escape
        $pattern = implode('|', $ipAddresses);

        try {
            // 1. BANDWIDTH batch - sum by instance untuk total semua interface per device
            $q = "sum by (instance) (rate(ifHCInOctets{instance=~\"{$pattern}\"}[5m])) * 8 / 1000000";
            $r = Http::timeout(8)->get("{$this->baseUrl}/api/v1/query", ['query' => $q]);
            if ($r->successful()) {
                foreach ($r->json()['data']['result'] ?? [] as $res) {
                    $ip = $res['metric']['instance'] ?? null;
                    if ($ip && isset($results[$ip])) $results[$ip]['bandwidth'] = (float) ($res['value'][1] ?? 0);
                }
            }

            // 2. LATENCY batch - phase rtt untuk ICMP ping
            $q = "probe_icmp_duration_seconds{instance=~\"{$pattern}\",phase=\"rtt\"} * 1000";
            $r = Http::timeout(8)->get("{$this->baseUrl}/api/v1/query", ['query' => $q]);
            if ($r->successful()) {
                foreach ($r->json()['data']['result'] ?? [] as $res) {
                    $ip = $res['metric']['instance'] ?? null;
                    if ($ip && isset($results[$ip])) $results[$ip]['latency'] = (float) ($res['value'][1] ?? 0);
                }
            }

            // 3. PACKET LOSS batch
            $q = "(1 - probe_success{instance=~\"{$pattern}\"}) * 100";
            $r = Http::timeout(8)->get("{$this->baseUrl}/api/v1/query", ['query' => $q]);
            if ($r->successful()) {
                foreach ($r->json()['data']['result'] ?? [] as $res) {
                    $ip = $res['metric']['instance'] ?? null;
                    if ($ip && isset($results[$ip])) $results[$ip]['packet_loss'] = (float) ($res['value'][1] ?? 0);
                }
            }

            // 4. Simulation mode fallback
            if ($this->simulationMode) {
                Log::warning("[WARN] Simulation mode: Generating fake metrics for batch query (" . count($results) . " devices)");
                foreach ($results as $ip => $m) {
                    if ($m['bandwidth'] === null) $results[$ip]['bandwidth'] = rand(0, 100) + (rand(0, 99) / 100);
                    if ($m['latency'] === null) $results[$ip]['latency'] = rand(10, 150) + (rand(0, 99) / 100);
                    if ($m['packet_loss'] === null) $results[$ip]['packet_loss'] = rand(0, 5) + (rand(0, 99) / 100);
                }
            }

            Log::info("Prometheus batch: " . count($results) . " devices, " . count(array_filter(array_column($results, 'bandwidth'), fn($v) => $v !== null)) . " have bandwidth");
        } catch (\Exception $e) {
            Log::error("Batch query Prometheus failed: " . $e->getMessage());
        }

        return $results;
    }

    public function checkConnection(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/-/healthy");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Failed to connect to Prometheus: " . $e->getMessage());
            return false;
        }
    }
}
