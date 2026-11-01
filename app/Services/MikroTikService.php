<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Support\Facades\Log;

class MikroTikService
{
    /**
     * Helper: buat koneksi RouterOS API client.
     * Dipakai bersama oleh testConnection() dan getDhcpLeases() supaya konfigurasi
     * host/user/pass/port/timeout hanya didefinisikan di satu tempat.
     *
     * @param string $ip IP address Core Router MikroTik
     * @param string $username Username API
     * @param string $password Password user
     * @param int $timeout Timeout koneksi dalam detik
     * @return Client Instance RouterOS client
     */
    private function createClient(string $ip, string $username, string $password, int $timeout = 5): Client
    {
        return new Client([
            'host' => $ip,
            'user' => $username,
            'pass' => $password,
            'port' => (int) env('MIKROTIK_API_PORT', 8728),
            'timeout' => $timeout,
        ]);
    }

    /**
     * Test koneksi ke MikroTik RouterOS API.
     * Method ini digunakan untuk validasi credentials sebelum melakukan discovery.
     * 
     * @param string $ip IP address Core Router MikroTik
     * @param string $username Username untuk API (biasanya 'admin' atau user khusus)
     * @param string $password Password user
     * @return array ['success' => bool, 'message' => string, 'identity' => string|null]
     */
    public function testConnection(string $ip, string $username, string $password): array
    {
        try {
            // Create RouterOS API client dengan timeout 5 detik
            $client = $this->createClient($ip, $username, $password, 5);

            // Test query: ambil system identity untuk memastikan koneksi berhasil
            // Query ini sangat ringan dan hanya untuk validasi
            $query = new Query('/system/identity/print');
            $response = $client->query($query)->read();

            $identity = $response[0]['name'] ?? 'Unknown Router';

            Log::info('MikroTik connection test SUCCESS', [
                'ip' => $ip,
                'username' => $username,
                'identity' => $identity,
            ]);

            return [
                'success' => true,
                'message' => 'Koneksi berhasil ke MikroTik Router',
                'identity' => $identity,
            ];
        } catch (\Exception $e) {
            Log::error('MikroTik connection test FAILED', [
                'ip' => $ip,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal connect ke MikroTik: ' . $e->getMessage(),
                'identity' => null,
            ];
        }
    }

    /**
     * Query DHCP leases dari MikroTik Core Router.
     * Method ini mengambil semua client yang terdaftar di DHCP server.
     * 
     * @param string $ip IP address Core Router MikroTik
     * @param string $username Username untuk API
     * @param string $password Password user
     * @return array List of clients: [['ip' => ..., 'mac' => ..., 'hostname' => ..., 'status' => ...], ...]
     * @throws \Exception Jika gagal connect atau query
     */
    public function getDhcpLeases(string $ip, string $username, string $password): array
    {
        try {
            // Create RouterOS API client (timeout lebih lama untuk query DHCP)
            $client = $this->createClient($ip, $username, $password, 10);

            // Query DHCP leases: /ip/dhcp-server/lease/print
            // Ini akan return semua lease (bound, waiting, offered, dll)
            $query = new Query('/ip/dhcp-server/lease/print');
            $leases = $client->query($query)->read();

            $clients = [];
            foreach ($leases as $lease) {
                // Filter hanya yang punya IP address dan MAC address
                // Status bisa: bound, waiting, offered, expired
                // Kita ambil semua yang ada IP-nya (biasanya bound atau waiting)
                if (isset($lease['address']) && isset($lease['mac-address'])) {
                    $clients[] = [
                        'ip' => $lease['address'],
                        'mac' => $lease['mac-address'],
                        'hostname' => $lease['host-name'] ?? '',
                        'status' => $lease['status'] ?? 'unknown',
                        'server' => $lease['server'] ?? '',
                    ];
                }
            }

            Log::info('MikroTik DHCP leases query SUCCESS', [
                'ip' => $ip,
                'total_clients' => count($clients),
            ]);

            return $clients;
        } catch (\Exception $e) {
            Log::error('MikroTik DHCP leases query FAILED', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
