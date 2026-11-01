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
            // Query bandwidth dari SNMP interface counter (ifHCInOctets)
            $query = "rate(ifHCInOctets{instance=\"{$ipAddress}\"}[5m]) * 8 / 1000000";
            return $this->executeSingleQuery($query);
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



    /**
     * Ambil latency (RTT) device via Blackbox Exporter.
     *
     * Query utama: probe_icmp_duration_seconds{phase="rtt"} (RTT ICMP paling akurat).
     * Fallback: probe_duration_seconds (total durasi probe) jika RTT tidak tersedia.
     *
     * @param string $ipAddress IP address device target
     * @return float|null Latency dalam milidetik atau null
     */
    public function getLatency(string $ipAddress): ?float
    {
        try {
            // Query utama: RTT ICMP
            $rtt = $this->executeSingleQuery("probe_icmp_duration_seconds{instance=\"{$ipAddress}\",phase=\"rtt\"} * 1000");
            if ($rtt !== null) {
                return $rtt;
            }

            // Fallback: total durasi probe (dipakai jika metric RTT tidak ada)
            $duration = $this->executeSingleQuery("probe_duration_seconds{instance=\"{$ipAddress}\"} * 1000");
            if ($duration !== null) {
                return $duration;
            }

            // Simulation mode: nilai palsu jika data real tidak tersedia
            return $this->simulateValue('latency', $ipAddress);
        } catch (\Exception $e) {
            Log::error("Failed latency for {$ipAddress}: " . $e->getMessage());
            return $this->simulateValue('latency', $ipAddress);
        }
    }

    /**
     * Ambil packet loss device via Blackbox Exporter.
     *
     * Query: (1 - probe_success) * 100, karena probe_success = 0/1.
     *
     * @param string $ipAddress IP address device target
     * @return float|null Packet loss dalam persen atau null
     */
    public function getPacketLoss(string $ipAddress): ?float
    {
        try {
            // Query packet loss dari probe_success Blackbox
            $packetLoss = $this->executeSingleQuery("(1 - probe_success{instance=\"{$ipAddress}\"}) * 100");
            if ($packetLoss !== null) {
                return $packetLoss;
            }

            // Simulation mode: nilai palsu jika data real tidak tersedia
            return $this->simulateValue('packet_loss', $ipAddress);
        } catch (\Exception $e) {
            Log::error("Failed packet loss for {$ipAddress}: " . $e->getMessage());
            return $this->simulateValue('packet_loss', $ipAddress);
        }
    }

    /**
     * Helper simulation mode: generate nilai metrik palsu saat data real tidak tersedia.
     * Hanya aktif jika PROMETHEUS_SIMULATION_MODE=true.
     *
     * @param string $metricType Jenis metrik: 'bandwidth' | 'latency' | 'packet_loss'
     * @param string $ipAddress IP device (untuk keperluan log)
     * @return float|null Nilai simulasi, atau null jika simulation mode nonaktif
     */
    private function simulateValue(string $metricType, string $ipAddress): ?float
    {
        // Jika simulation mode nonaktif, kembalikan null (data real tidak tersedia)
        if (!$this->simulationMode) {
            return null;
        }

        // Range nilai simulasi berbeda per jenis metrik
        $value = match ($metricType) {
            'bandwidth' => rand(0, 100) + (rand(0, 99) / 100),
            'latency' => rand(10, 150) + (rand(0, 99) / 100),
            'packet_loss' => rand(0, 10) + (rand(0, 99) / 100),
            default => 0.0,
        };

        Log::debug("Simulation mode: Generating fake {$metricType} {$value} for {$ipAddress}");
        return (float) $value;
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
