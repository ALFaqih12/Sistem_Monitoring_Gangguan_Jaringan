<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class DeviceDiscoveryService
{
    public function __construct(
        private MikroTikService $mikrotikService,
        private MacVendorService $macVendorService
    ) {}

    /**
     * Main discovery orchestrator.
     * Method ini mengatur seluruh proses discovery dari awal sampai akhir:
     * 1. Query DHCP leases dari MikroTik
     * 2. Enrich setiap client dengan device model info (SNMP atau MAC lookup)
     * 3. Filter client yang sudah terdaftar di database
     * 
     * @param string $coreRouterIp IP Core Router MikroTik
     * @param array $credentials ['username' => ..., 'password' => ...]
     * @return array List client devices yang siap ditampilkan di UI
     * @throws \Exception Jika gagal connect atau query
     */
    public function discoverClients(string $coreRouterIp, array $credentials): array
    {
        // Step 1: Get DHCP leases dari MikroTik
        $dhcpClients = $this->mikrotikService->getDhcpLeases(
            $coreRouterIp,
            $credentials['username'],
            $credentials['password']
        );

        Log::info('DeviceDiscoveryService: Found DHCP clients from MikroTik', [
            'count' => count($dhcpClients),
            'core_router' => $coreRouterIp,
        ]);

        // Step 2: Enrich setiap client dengan device model info
        // Proses ini bisa memakan waktu karena SNMP query per device
        $enrichedClients = [];
        foreach ($dhcpClients as $client) {
            $enrichedClients[] = $this->enrichClientInfo($client);
        }

        Log::info('DeviceDiscoveryService: Enriched clients with device model', [
            'count' => count($enrichedClients),
        ]);

        // Step 3: Filter client yang sudah terdaftar di database
        $filteredClients = $this->filterExistingDevices($enrichedClients);

        Log::info('DeviceDiscoveryService: Filtered existing devices', [
            'before' => count($enrichedClients),
            'after' => count($filteredClients),
            'filtered_out' => count($enrichedClients) - count($filteredClients),
        ]);

        return array_values($filteredClients); // Re-index array after filter
    }

    /**
     * Enrich client info dengan auto-detect device model.
     * Priority: SNMP query dulu (lebih akurat), fallback ke MAC Vendor lookup.
     * 
     * SNMP query memberikan detail model device (misal: "MikroTik RB750Gr3")
     * MAC lookup hanya memberikan vendor (misal: "MikroTik")
     * 
     * @param array $client Data dari DHCP lease
     * @return array Client dengan tambahan field 'model'
     */
    private function enrichClientInfo(array $client): array
    {
        $model = 'Unknown Device';

        // Try 1: SNMP query untuk get device model detail
        // OID 1.3.6.1.2.1.1.1.0 = sysDescr (system description)
        // Ini biasanya berisi informasi lengkap tentang device
        try {
            if (function_exists('snmpget')) {
                $snmpCommunity = env('SNMP_COMMUNITY_STRING', 'public');
                $snmpTimeout = env('SNMP_TIMEOUT_MICROSECONDS', 1000000); // 1 detik default
                
                // Suppress error dengan @ karena SNMP bisa throw warning jika device unreachable
                $sysDescr = @snmpget($client['ip'], $snmpCommunity, "1.3.6.1.2.1.1.1.0", $snmpTimeout);

                if ($sysDescr !== false && !empty($sysDescr)) {
                    // Parse sysDescr untuk extract model name
                    $model = $this->parseSysDescr($sysDescr);
                    
                    Log::debug('DeviceDiscoveryService: SNMP query SUCCESS', [
                        'ip' => $client['ip'],
                        'model' => $model,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::debug('DeviceDiscoveryService: SNMP query FAILED, fallback to MAC lookup', [
                'ip' => $client['ip'],
                'error' => $e->getMessage(),
            ]);
        }

        // Try 2: MAC Vendor lookup jika SNMP gagal
        if ($model === 'Unknown Device' && !empty($client['mac'])) {
            $model = $this->macVendorService->lookupVendor($client['mac']);
            
            Log::debug('DeviceDiscoveryService: MAC lookup result', [
                'ip' => $client['ip'],
                'mac' => $client['mac'],
                'model' => $model,
            ]);
        }

        return [
            'ip' => $client['ip'],
            'mac' => $client['mac'],
            'hostname' => $client['hostname'],
            'status' => $client['status'],
            'model' => $model,
        ];
    }

    /**
     * Parse SNMP sysDescr untuk extract device model.
     * sysDescr format bervariasi per vendor, kita handle yang umum di Indonesia.
     * 
     * @param string $sysDescr Raw sysDescr dari SNMP
     * @return string Device model yang sudah di-parse
     */
    private function parseSysDescr(string $sysDescr): string
    {
        // Remove SNMP type prefix (STRING: ) dan quotes
        $sysDescr = preg_replace('/^STRING:\s*/', '', $sysDescr);
        $sysDescr = trim($sysDescr, '"');
        $sysDescr = trim($sysDescr);

        // Handle empty atau invalid sysDescr
        if (empty($sysDescr) || strlen($sysDescr) < 3) {
            return 'Unknown Device';
        }

        // Pattern 1: MikroTik - "RouterOS RB750Gr3" → "MikroTik RB750Gr3"
        if (stripos($sysDescr, 'RouterOS') !== false) {
            $model = trim(str_ireplace('RouterOS', '', $sysDescr));
            return 'MikroTik ' . $model;
        }

        // Pattern 2: Ubiquiti - biasanya langsung model name
        // Contoh: "NanoStation M5", "AirMax M5", "EdgeRouter X"
        if (stripos($sysDescr, 'NanoStation') !== false ||
            stripos($sysDescr, 'AirMax') !== false ||
            stripos($sysDescr, 'EdgeRouter') !== false ||
            stripos($sysDescr, 'UniFi') !== false) {
            return 'Ubiquiti ' . $sysDescr;
        }

        // Pattern 3: TP-Link - format varies
        // Contoh: "TL-WR841N", "Archer C7"
        if (stripos($sysDescr, 'TP-Link') !== false ||
            stripos($sysDescr, 'TL-') !== false ||
            stripos($sysDescr, 'Archer') !== false) {
            return stripos($sysDescr, 'TP-Link') !== false 
                ? $sysDescr 
                : 'TP-Link ' . $sysDescr;
        }

        // Pattern 4: Cisco - "Cisco IOS Software"
        if (stripos($sysDescr, 'Cisco') !== false) {
            // Extract model dari string panjang Cisco
            preg_match('/Cisco\s+([A-Z0-9\-]+)/i', $sysDescr, $matches);
            return $matches[0] ?? 'Cisco Device';
        }

        // Pattern 5: Huawei
        if (stripos($sysDescr, 'Huawei') !== false) {
            return $sysDescr;
        }

        // Pattern 6: D-Link
        if (stripos($sysDescr, 'D-Link') !== false) {
            return $sysDescr;
        }

        // Pattern 7: Tenda
        if (stripos($sysDescr, 'Tenda') !== false) {
            return $sysDescr;
        }

        // Default: return as-is dengan max length 100 char
        return substr($sysDescr, 0, 100);
    }

    /**
     * Filter client yang sudah registered di database.
     * Hide client yang IP-nya sudah ada agar tidak duplikat saat add.
     * 
     * Note: Filter berdasarkan IP address karena IP adalah unique key di database.
     * Jika IP sudah ada, berarti device sudah di-monitoring.
     * 
     * @param array $clients List client dari discovery
     * @return array Client yang belum registered (new clients only)
     */
    private function filterExistingDevices(array $clients): array
    {
        // Get semua IP yang sudah terdaftar di database
        // Query ini efisien karena hanya ambil 1 kolom (ip_address)
        $existingIps = Device::pluck('ip_address')->toArray();

        Log::debug('DeviceDiscoveryService: Existing devices in database', [
            'count' => count($existingIps),
            'ips' => $existingIps,
        ]);

        // Filter: hanya ambil client yang IP-nya belum ada di database
        $filtered = array_filter($clients, function($client) use ($existingIps) {
            return !in_array($client['ip'], $existingIps, true);
        });

        return $filtered;
    }

    /**
     * Validate apakah IP address bisa dijangkau (ping test).
     * Method ini opsional, bisa digunakan sebelum add device.
     * 
     * @param string $ipAddress IP yang akan di-test
     * @return bool True jika IP reachable
     */
    public function isIpReachable(string $ipAddress): bool
    {
        // Simple ping test (Windows & Linux compatible)
        $pingCommand = PHP_OS_FAMILY === 'Windows' 
            ? "ping -n 1 -w 1000 {$ipAddress}" 
            : "ping -c 1 -W 1 {$ipAddress}";

        exec($pingCommand, $output, $returnCode);

        return $returnCode === 0;
    }
}
