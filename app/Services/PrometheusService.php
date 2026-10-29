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

    /**
     * Ambil bandwidth device via SNMP langsung ke device (method lama).
     * Digunakan untuk device yang di-add manual (bukan dari discovery).
     * 
     * Query: rate(ifHCInOctets) dari SNMP exporter per device.
     * Return: Bandwidth dalam Mbps atau null jika tidak tersedia.
     */
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

    /**
     * Ambil bandwidth client via Simple Queue di Core Router MikroTik.
     * Digunakan untuk device yang di-add dari discovery (non-SNMP client).
     * 
     * Query bandwidth per IP client dari MikroTik Simple Queue counter.
     * MikroTik export SNMP OID: mtxrQueueSimpleTable (.1.3.6.1.4.1.14988.1.1.2)
     * - Bytes In (Download): .1.3.6.1.4.1.14988.1.1.2.1.1.9
     * - Bytes Out (Upload): .1.3.6.1.4.1.14988.1.1.2.1.1.10
     * 
     * Prometheus metric (setelah SNMP exporter scrape):
     * - mikrotikQueueBytesIn{instance="CORE_ROUTER_IP", name="CLIENT_IP"}
     * - mikrotikQueueBytesOut{instance="CORE_ROUTER_IP", name="CLIENT_IP"}
     * 
     * Requirement: Simple Queue harus dibuat manual di WinBox dengan format:
     * - Queue name = IP client (e.g., 192.168.1.10)
     * - Target = IP client/32 (e.g., 192.168.1.10/32)
     * 
     * @param string $clientIp IP address client yang dicari bandwidthnya
     * @param string $coreRouterIp IP Core Router MikroTik (sebagai instance target)
     * @return float|null Bandwidth dalam Mbps (download + upload) atau null
     */
    public function getBandwidthFromSimpleQueue(string $clientIp, string $coreRouterIp): ?float
    {
        try {
            // Query download bandwidth (bytes in) dari Simple Queue counter
            // Filter by: instance = Core Router IP, name = Client IP (queue name)
            $queryDownload = "rate(mikrotikQueueBytesIn{instance=\"{$coreRouterIp}\",name=\"{$clientIp}\"}[5m]) * 8 / 1000000";
            
            // Query upload bandwidth (bytes out) dari Simple Queue counter
            $queryUpload = "rate(mikrotikQueueBytesOut{instance=\"{$coreRouterIp}\",name=\"{$clientIp}\"}[5m]) * 8 / 1000000";

            $download = $this->executeSingleQuery($queryDownload);
            $upload = $this->executeSingleQuery($queryUpload);

            // Jika kedua metric null, berarti simple queue belum dibuat atau client tidak ada traffic
            if ($download === null && $upload === null) {
                Log::warning("Bandwidth from Simple Queue: No data for client {$clientIp} via {$coreRouterIp}. Pastikan Simple Queue sudah dibuat di WinBox dengan name={$clientIp}");
                return null;
            }

            // Total bandwidth = download + upload
            $total = ($download ?? 0) + ($upload ?? 0);
            
            Log::debug("Bandwidth from Simple Queue for {$clientIp}: Download={$download} Mbps, Upload={$upload} Mbps, Total={$total} Mbps");
            
            return $total > 0 ? $total : null;

        } catch (\Exception $e) {
            Log::error("Failed to get bandwidth from Simple Queue {$coreRouterIp} for client {$clientIp}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper method untuk execute single Prometheus query dan extract value.
     * Mengurangi code duplication di method bandwidth.
     * 
     * @param string $query PromQL query string
     * @return float|null Value dari query result atau null
     */
    private function executeSingleQuery(string $query): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $query]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::debug("Query execution failed: {$query} - " . $e->getMessage());
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

    /**
     * Ambil semua metrik untuk device manual (query langsung ke device).
     * Method lama yang masih digunakan untuk device source = 'manual'.
     */
    public function getAllMetrics(string $ipAddress): array
    {
        return [
            'bandwidth' => $this->getBandwidth($ipAddress),
            'latency' => $this->getLatency($ipAddress),
            'packet_loss' => $this->getPacketLoss($ipAddress),
        ];
    }

    /**
     * Ambil semua metrik untuk device dari discovery (bandwidth via Simple Queue Core Router).
     * Bandwidth diambil dari Core Router Simple Queue counter.
     * Latency & packet loss tetap dari Blackbox (ping ke client langsung).
     * 
     * @param string $clientIp IP address client device
     * @param string $coreRouterIp IP Core Router MikroTik
     * @return array ['bandwidth' => float|null, 'latency' => float|null, 'packet_loss' => float|null]
     */
    public function getAllMetricsWithSimpleQueue(string $clientIp, string $coreRouterIp): array
    {
        return [
            'bandwidth' => $this->getBandwidthFromSimpleQueue($clientIp, $coreRouterIp),
            'latency' => $this->getLatency($clientIp),
            'packet_loss' => $this->getPacketLoss($clientIp),
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
