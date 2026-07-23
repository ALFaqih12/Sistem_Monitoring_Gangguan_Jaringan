<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DeviceController extends Controller
{
    // 1. Menampilkan Halaman Utama (Tabel & Form)
    public function index()
    {
        $devices = Device::latest()->get();
        return view('devices.index', compact('devices'));
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

        if (session('verified_device_ip') !== $validated['ip_address']) {
            return back()
                ->withErrors(['ip_address' => 'IP target harus berhasil Connect sebelum data disimpan.'])
                ->withInput();
        }

        // Simpan data, (customer_id otomatis terisi lewat fungsi boot() di Model Device)
        $device = Device::create($validated);

        $this->updatePrometheusTargets($device->ip_address);
        $request->session()->forget('verified_device_ip');

        return redirect()->route('devices.index')->with('success', 'Data target device berhasil ditambahkan!');
    }

    // 3. Fungsi AJAX saat tombol "Connect" diklik
    public function pingDevice(Request $request)
    {
        $ip = $request->ip_address;
        $community = "public"; // Sesuaikan dengan community string di perangkat Anda

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

            $request->session()->put('verified_device_ip', $ip);

            return response()->json([
                'status' => 'success',
                'device_model' => $deviceModel,
                'message' => 'Koneksi berhasil! Model perangkat berhasil terdeteksi.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terkoneksi via SNMP: ' . $e->getMessage()
            ], 500);
        }
    }

    // 4. Fungsi Private untuk Menulis ke File targets.json
    private function updatePrometheusTargets($newIp)
    {
        /* 
         * Mencari file targets.json. 
         * Karena web-app berada di dalam folder monitoring (sejajar dengan prometheus),
         * kita menggunakan base_path('../') untuk mundur satu folder.
         */
        $filePath = base_path('../prometheus/targets.json');

        // Jika file belum ada, buat array kosong
        if (!File::exists($filePath)) {
            $targets = [];
        } else {
            // Baca file yang ada
            $jsonString = File::get($filePath);
            $targets = json_decode($jsonString, true) ?? [];
        }

        // Cek apakah IP sudah ada untuk mencegah duplikasi data di Prometheus
        $ipExists = false;
        foreach ($targets as $target) {
            if (in_array("{$newIp}", $target['targets'])) {
                $ipExists = true;
                break;
            }
        }

        // Jika IP baru, tambahkan ke dalam struktur JSON
        if (!$ipExists) {
            $targets[] = [
                'targets' => ["{$newIp}"], 
                'labels' => [
                    'instance' => $newIp,
                    'job' => 'snmp'
                ]
            ];

            // Tulis dan simpan kembali ke file targets.json
            File::put($filePath, json_encode($targets, JSON_PRETTY_PRINT));
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
            'maps_url' => 'nullable|url'
        ]);

        $oldIp = $device->ip_address;

        if ($oldIp !== $validated['ip_address'] && session('verified_device_ip') !== $validated['ip_address']) {
            return back()
                ->withErrors(['ip_address' => 'IP target baru harus berhasil Connect sebelum perubahan disimpan.'])
                ->withInput();
        }

        // Update data
        $device->update([
            'customer_name' => $validated['customer_name'],
            'ip_address' => $validated['ip_address'],
            'device_model' => $validated['device_model'] ?? null,
            'maps_url' => $validated['maps_url'] ?? null,
        ]);

        if ($oldIp !== $device->ip_address) {
            $this->removePrometheusTarget($oldIp);
        }

        $this->updatePrometheusTargets($device->ip_address);
        $request->session()->forget('verified_device_ip');

        return redirect()->route('devices.index')->with('success', 'Data device ' . $device->customer_name . ' berhasil diperbarui!');
    }

    // Menghapus data dari Database dan Prometheus
    public function destroy(Device $device)
    {
        $ip = $device->ip_address;
        $name = $device->customer_name;

        // 1. Hapus dari file Prometheus targets.json
        $this->removePrometheusTarget($ip);

        // 2. Hapus dari database MySQL
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Data device ' . $name . ' berhasil dihapus dari sistem dan Prometheus!');
    }

    // Fungsi Private untuk Menghapus IP dari file targets.json
    private function removePrometheusTarget($ipToRemove)
    {
        $filePath = base_path('../prometheus/targets.json');

        if (\Illuminate\Support\Facades\File::exists($filePath)) {
            $jsonString = \Illuminate\Support\Facades\File::get($filePath);
            $targets = json_decode($jsonString, true) ?? [];

            // Filter array: Buang target yang IP-nya cocok dengan yang akan dihapus
            $filteredTargets = array_filter($targets, function($target) use ($ipToRemove) {
                return !in_array("{$ipToRemove}", $target['targets']);
            });

            // Susun ulang index array (karena array_filter bisa membuat index bolong)
            $reindexedTargets = array_values($filteredTargets);

            // Simpan kembali ke file
            \Illuminate\Support\Facades\File::put($filePath, json_encode($reindexedTargets, JSON_PRETTY_PRINT));
        }
    }
}
