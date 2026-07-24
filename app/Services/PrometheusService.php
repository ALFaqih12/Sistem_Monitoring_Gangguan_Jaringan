<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    /**
     * Base URL Prometheus server
     */
    private string $baseUrl;

    /**
     * Mode simulasi untuk testing (jika metric tidak tersedia)
     */
    private bool $simulationMode;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->baseUrl = env('PROMETHEUS_URL', 'http://localhost:9090');
        $this->simulationMode = env('PROMETHEUS_SIMULATION_MODE', false);
    }

    /**
     * Query data bandwidth dari Prometheus menggunakan SNMP ifHCInOctets.
     * 
     * @param string $ipAddress IP target yang akan dicek
     * @return float|null Bandwidth dalam Mbps, atau null jika gagal
     */
    public function getBandwidth(string $ipAddress): ?float
    {
        try {
            // Query bandwidth menggunakan ifHCInOctets (SNMP)
            // rate() per 5 menit * 8 (bytes to bits) / 1000000 (bits to Mbps)
            $query = "rate(ifHCInOctets{instance=\"{$ipAddress}\"}[5m]) * 8 / 1000000";
            
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get bandwidth from Prometheus for {$ipAddress}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Query data latency dari Prometheus menggunakan Blackbox Exporter.
     * Menggunakan probe_icmp_duration_seconds untuk ICMP ping latency.
     * 
     * @param string $ipAddress IP target yang akan dicek
     * @return float|null Latency dalam ms, atau null jika gagal
     */
    public function getLatency(string $ipAddress): ?float
    {
        try {
            // Query latency dari blackbox exporter (ICMP probe)
            // probe_icmp_duration_seconds dalam seconds, dikali 1000 untuk konversi ke ms
            $query = "probe_icmp_duration_seconds{instance=\"{$ipAddress}\"} * 1000";
            
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }

            // Fallback: coba probe_duration_seconds jika probe_icmp tidak ada
            $query = "probe_duration_seconds{instance=\"{$ipAddress}\"} * 1000";
            
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }

            // Jika mode simulasi aktif dan metric tidak tersedia, return data dummy
            if ($this->simulationMode) {
                $simulatedLatency = rand(10, 150) + (rand(0, 99) / 100);
                Log::info("Simulation Mode: Generated latency {$simulatedLatency}ms for {$ipAddress}");
                return $simulatedLatency;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get latency from Prometheus for {$ipAddress}: " . $e->getMessage());
            
            if ($this->simulationMode) {
                $simulatedLatency = rand(10, 150) + (rand(0, 99) / 100);
                return $simulatedLatency;
            }
            
            return null;
        }
    }

    /**
     * Query data packet loss dari Prometheus menggunakan Blackbox Exporter.
     * Menggunakan probe_success (1=UP, 0=DOWN).
     * 
     * @param string $ipAddress IP target yang akan dicek
     * @return float|null Packet loss dalam %, atau null jika gagal
     */
    public function getPacketLoss(string $ipAddress): ?float
    {
        try {
            // Query packet loss dari blackbox exporter
            // probe_success = 1 (UP/no loss), probe_success = 0 (DOWN/100% loss)
            // (1 - probe_success) * 100 = packet loss percentage
            $query = "(1 - probe_success{instance=\"{$ipAddress}\"}) * 100";
            
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $query
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']['result'][0]['value'][1])) {
                    return (float) $data['data']['result'][0]['value'][1];
                }
            }

            // Jika mode simulasi aktif dan metric tidak tersedia, return data dummy
            if ($this->simulationMode) {
                $simulatedPacketLoss = rand(0, 10) + (rand(0, 99) / 100);
                Log::info("Simulation Mode: Generated packet loss {$simulatedPacketLoss}% for {$ipAddress}");
                return $simulatedPacketLoss;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get packet loss from Prometheus for {$ipAddress}: " . $e->getMessage());
            
            if ($this->simulationMode) {
                $simulatedPacketLoss = rand(0, 10) + (rand(0, 99) / 100);
                return $simulatedPacketLoss;
            }
            
            return null;
        }
    }

    /**
     * Ambil semua metrik sekaligus untuk IP target tertentu.
     * 
     * @param string $ipAddress IP target yang akan dicek
     * @return array Array dengan metrik: ['bandwidth' => float, 'latency' => float, 'packet_loss' => float]
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
     * Cek apakah Prometheus server dapat dijangkau.
     * 
     * @return bool True jika Prometheus server UP, false jika DOWN
     */
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