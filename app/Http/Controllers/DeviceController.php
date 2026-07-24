<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\PrometheusTargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeviceController extends Controller
{
    /**
     * Menyimpan service target Prometheus agar controller hanya fokus pada alur request device.
     */
    public function __construct(private readonly PrometheusTargetService $prometheusTargets)
    {
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

        return view('devices.index', compact('devices', 'search'));
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

        if (! function_exists('snmpget')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Extension SNMP PHP belum aktif, sehingga perangkat belum bisa dicek.',
            ], 500);
        }

        try {
            // Menggunakan snmpget untuk mengambil deskripsi sistem
            // Timeout diset ke 1 detik (1000000 mikrodetik) agar tidak menunggu terlalu lama
            $sysDescr = @snmpget($ip, $community, "1.3.6.1.2.1.1.1.0", 1000000);

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
        return view('devices.edit', compact('device'));
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
}