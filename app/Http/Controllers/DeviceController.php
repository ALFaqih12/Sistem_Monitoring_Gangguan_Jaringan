<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\PrometheusTargetService;
use App\Services\MikroTikService;
use App\Services\DeviceDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class DeviceController extends Controller
{
    /**
     * Menyimpan service target Prometheus agar controller hanya fokus pada alur request device.
     * Tambahan: MikroTikService dan DeviceDiscoveryService untuk fitur discovery.
     */
    public function __construct(
        private readonly PrometheusTargetService $prometheusTargets,
        private readonly MikroTikService $mikrotikService,
        private readonly DeviceDiscoveryService $discoveryService
    ) {
    }

    /**
     * Menampilkan Halaman Utama dengan Tabel, Form, dan Pagination.
     * Mendukung pencarian server-side yang mencakup semua data di seluruh halaman pagination.
     */
    public function index(Request $request)
    {
        // Ambil keyword pencarian dari request (jika ada)
        $search = $request->input('search');

        // Query data device dengan pagination 15 per halaman
        $devices = Device::query()
            ->when($search, function ($query, $search) {
                // Pencarian server-side di semua kolom yang relevan
                return $query->where(function ($q) use ($search) {
                    $q->where('customer_id', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhere('device_model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString(); // Mempertahankan parameter search di URL pagination

        // Migrasi Blade -> Inertia: halaman device dirender Vue Devices/Index.vue.
        // Props (devices paginator + search) dikirim sama; withQueryString() tetap
        // dipertahankan supaya parameter search kekal di URL pagination.
        return Inertia::render('Devices/Index', compact('devices', 'search'));
    }

    // 2. Menyimpan Data ke Database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'ip_address' => 'required|ipv4|unique:devices,ip_address',
            'device_model' => 'nullable|string',
            'installation_date' => 'required|date',
            'maps_url' => 'nullable|url'
        ]);

        if (! $this->isVerifiedDeviceIp($validated['ip_address'])) {
            return back()
                ->withErrors(['ip_address' => 'IP target harus berhasil Connect sebelum data disimpan.'])
                ->withInput();
        }

        try {
            // Transaksi menjaga data database batal tersimpan jika update target Prometheus gagal.
            DB::transaction(function () use ($validated) {
                $device = Device::create($validated);

                $this->prometheusTargets->addTarget($device->ip_address);
            });

            $this->forgetVerifiedDeviceIp($validated['ip_address']);
        } catch (Throwable $e) {
            return back()
                ->withErrors(['ip_address' => 'Data gagal disimpan karena target Prometheus tidak dapat diperbarui: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('devices.index')->with('success', 'Data target device berhasil ditambahkan!');
    }

    // 3. Fungsi AJAX saat tombol "Connect" diklik
    public function pingDevice(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ipv4',
        ]);

        $ip = $validated['ip_address'];
        $community = "public"; // Sesuaikan dengan community string di perangkat Anda

        // Timeout SNMP diambil dari .env agar bisa disesuaikan dengan kondisi jaringan.
        // Default: 3000000 mikrodetik = 3 detik.
        // Sebelumnya hardcoded 1000000 (1 detik) yang terlalu cepat untuk device slow/jauh.
        $snmpTimeout = env('SNMP_TIMEOUT_MICROSECONDS', 3000000);

        if (! function_exists('snmpget')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Extension SNMP PHP belum aktif, sehingga perangkat belum bisa dicek.',
            ], 500);
        }

        try {
            // Menggunakan snmpget untuk mengambil deskripsi sistem
            // Timeout bisa dikonfigurasi via .env (SNMP_TIMEOUT_MICROSECONDS)
            $sysDescr = @snmpget($ip, $community, "1.3.6.1.2.1.1.1.0", $snmpTimeout);

            if ($sysDescr) {
                // Hasilnya biasanya string panjang, kita bersihkan
                $deviceModel = str_replace('STRING: ', '', $sysDescr);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SNMP Timeout atau Community String salah.'
                ], 500);
            }

            $this->rememberVerifiedDeviceIp($ip);

            return response()->json([
                'status' => 'success',
                'device_model' => $deviceModel,
                'message' => 'Koneksi berhasil! Model perangkat berhasil terdeteksi.'
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terkoneksi via SNMP: ' . $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan halaman Edit
    public function edit(Device $device)
    {
        // Migrasi Blade -> Inertia: form edit device dirender Vue Devices/Edit.vue.
        return Inertia::render('Devices/Edit', compact('device'));
    }

    // Menyimpan perubahan data ke Database
    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            // Validasi unik dikecualikan untuk ID device yang sedang diedit
            'ip_address' => 'required|ipv4|unique:devices,ip_address,' . $device->id,
            'device_model' => 'nullable|string',
            'installation_date' => 'required|date',
            'maps_url' => 'nullable|url'
        ]);

        $oldIp = $device->ip_address;

        if ($oldIp !== $validated['ip_address'] && ! $this->isVerifiedDeviceIp($validated['ip_address'])) {
            return back()
                ->withErrors(['ip_address' => 'IP target baru harus berhasil Connect sebelum perubahan disimpan.'])
                ->withInput();
        }

        try {
            // Transaksi menjaga perubahan device batal jika sinkronisasi target Prometheus gagal.
            DB::transaction(function () use ($device, $validated, $oldIp) {
                $device->update([
                    'customer_name' => $validated['customer_name'],
                    'ip_address' => $validated['ip_address'],
                    'device_model' => $validated['device_model'] ?? null,
                    'installation_date' => $validated['installation_date'],
                    'maps_url' => $validated['maps_url'] ?? null,
                ]);

                if ($oldIp !== $device->ip_address) {
                    $this->prometheusTargets->removeTarget($oldIp);
                }

                $this->prometheusTargets->addTarget($device->ip_address);
            });

            $this->forgetVerifiedDeviceIp($validated['ip_address']);
        } catch (Throwable $e) {
            return back()
                ->withErrors(['ip_address' => 'Data gagal diperbarui karena target Prometheus tidak dapat diperbarui: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('devices.index')->with('success', 'Data device ' . $device->customer_name . ' berhasil diperbarui!');
    }

    // Menghapus data dari Database dan Prometheus
    public function destroy(Device $device)
    {
        $ip = $device->ip_address;
        $name = $device->customer_name;

        try {
            // Transaksi menjaga data device tetap ada jika target Prometheus gagal dihapus.
            DB::transaction(function () use ($device, $ip) {
                $this->prometheusTargets->removeTarget($ip);

                $device->delete();
            });
        } catch (Throwable $e) {
            return back()->withErrors(['device' => 'Data gagal dihapus karena target Prometheus tidak dapat diperbarui: ' . $e->getMessage()]);
        }

        return redirect()->route('devices.index')->with('success', 'Data device ' . $name . ' berhasil dihapus dari sistem dan Prometheus!');
    }

    /**
     * Mengecek apakah IP sudah pernah lolos tombol Connect pada session browser ini.
     */
    private function isVerifiedDeviceIp(string $ipAddress): bool
    {
        return in_array($ipAddress, session('verified_device_ips', []), true);
    }

    /**
     * Menyimpan daftar IP yang sudah lolos Connect agar beberapa tab tidak saling menimpa.
     */
    private function rememberVerifiedDeviceIp(string $ipAddress): void
    {
        $verifiedIps = session('verified_device_ips', []);
        $verifiedIps[] = $ipAddress;

        session(['verified_device_ips' => array_values(array_unique($verifiedIps))]);
    }

    /**
     * Menghapus IP dari daftar verifikasi setelah data berhasil disimpan atau diperbarui.
     */
    private function forgetVerifiedDeviceIp(string $ipAddress): void
    {
        $verifiedIps = array_filter(session('verified_device_ips', []), function (string $verifiedIp) use ($ipAddress): bool {
            return $verifiedIp !== $ipAddress;
        });

        session(['verified_device_ips' => array_values($verifiedIps)]);
    }

    /**
     * Test koneksi ke MikroTik Core Router.
     * Endpoint: POST /devices/test-core-router
     * 
     * Method ini digunakan untuk validasi credentials sebelum discovery.
     * User input IP, username, password → test connection → enable button discovery.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testCoreRouter(Request $request)
    {
        // Validasi input: IP harus valid IPv4, username & password required
        $validated = $request->validate([
            'core_router_ip' => 'required|ip',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
        ]);

        // Test connection via MikroTikService
        $result = $this->mikrotikService->testConnection(
            $validated['core_router_ip'],
            $validated['username'],
            $validated['password']
        );

        // Return JSON response untuk AJAX
        // Frontend akan enable button "Connect & Discover" jika success = true
        return response()->json($result);
    }

    /**
     * Connect ke Core Router dan discover DHCP clients.
     * Endpoint: POST /devices/connect-core-router
     * 
     * Method ini melakukan discovery lengkap:
     * 1. Query DHCP leases dari MikroTik
     * 2. Auto-detect merk device (SNMP atau MAC lookup)
     * 3. Filter device yang sudah registered
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function connectCoreRouter(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'core_router_ip' => 'required|ip',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
        ]);

        try {
            // Discovery clients via DeviceDiscoveryService
            // Service ini akan handle semua logic: query DHCP, enrich, filter
            $clients = $this->discoveryService->discoverClients(
                $validated['core_router_ip'],
                [
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                ]
            );

            Log::info('DeviceController: Discovery SUCCESS', [
                'core_router' => $validated['core_router_ip'],
                'total_clients' => count($clients),
            ]);

            return response()->json([
                'success' => true,
                'clients' => $clients,
                'total' => count($clients),
                'message' => 'Discovery berhasil! Ditemukan ' . count($clients) . ' client devices.',
            ]);
        } catch (\Exception $e) {
            Log::error('DeviceController: Discovery FAILED', [
                'core_router' => $validated['core_router_ip'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan discovery: ' . $e->getMessage(),
                'clients' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Tambah client device dari hasil discovery.
     * Endpoint: POST /devices/add-client
     * 
     * Method ini digunakan setelah user pilih client dari discovery list.
     * Data IP dan device_model sudah auto-detect, user hanya input identitas pelanggan.
     * 
     * PENTING: Device dari discovery akan disimpan dengan:
     * - source = 'discovery' (untuk identifikasi query method bandwidth)
     * - core_router_ip = IP Core Router (untuk query SNMP DHCP lease counter)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addClientDevice(Request $request)
    {
        // Validasi input: IP harus unique, semua field required kecuali maps_url
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:devices,ip_address',
            'device_model' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'installation_date' => 'required|date',
            'maps_url' => 'required|url|max:500',
            'core_router_ip' => 'required|ip', // Core Router IP dari discovery session
        ]);

        try {
            DB::transaction(function () use ($validated) {
                // Create device record dengan source = 'discovery' dan core_router_ip
                // customer_id akan auto-generate via model boot method (CUST-YYYYMM-XXX)
                $device = Device::create([
                    'customer_name' => $validated['customer_name'],
                    'ip_address' => $validated['ip_address'],
                    'device_model' => $validated['device_model'],
                    'installation_date' => $validated['installation_date'],
                    'maps_url' => $validated['maps_url'],
                    'source' => 'discovery',  // Identifikasi source device
                    'core_router_ip' => $validated['core_router_ip'], // Simpan Core Router IP
                ]);

                // Add to Prometheus targets (SNMP + Blackbox)
                // SNMP target = Core Router IP (bukan client IP, karena query via router)
                // Blackbox target = Client IP (untuk ping latency & packet loss)
                $this->prometheusTargets->addTarget($validated['ip_address']);
                
                // Tambahkan Core Router ke mikrotik-targets.json
                // Agar Prometheus scrape DHCP lease counter dari Core Router
                // Method ini idempotent: tidak duplikat jika Core Router sudah terdaftar
                $this->prometheusTargets->addMikrotikTarget($validated['core_router_ip']);

                Log::info('DeviceController: Client device added via discovery', [
                    'device_id' => $device->id,
                    'customer_id' => $device->customer_id,
                    'ip_address' => $device->ip_address,
                    'customer_name' => $device->customer_name,
                    'source' => 'discovery',
                    'core_router_ip' => $device->core_router_ip,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Client device berhasil ditambahkan ke monitoring!',
            ]);
        } catch (\Exception $e) {
            Log::error('DeviceController: Failed to add client device', [
                'ip_address' => $validated['ip_address'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan device: ' . $e->getMessage(),
            ], 500);
        }
    }
}
