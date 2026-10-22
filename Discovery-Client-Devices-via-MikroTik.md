# Rencana Fitur: Discovery Client Devices via MikroTik Core Router

**Tanggal:** 21 Oktober 2026  
**Status:** Ready for Implementation  
**Estimasi Waktu:** 17-18 jam

---

## 📋 Ringkasan Fitur

### Tujuan

Mempermudah proses penambahan client devices untuk monitoring dengan cara:

- User hanya perlu input IP Core Router MikroTik
- Sistem otomatis discover semua client dari DHCP leases
- User tinggal pilih client yang ingin di-monitoring
- Proses lebih cepat dan efisien (tidak perlu input IP satu per satu)

### Flow Singkat

```
Input Core Router IP → Test Connection → Discover DHCP Leases →
Auto-detect IP Client and Merk Device → Tampilkan List → User Pilih Client →
Input Identitas → Save → Auto-add ke Monitoring
```

---

## 🔄 Flow Diagram Lengkap

### User Flow (Frontend)

```
┌─────────────────────────────────────────────────────────┐
│ 1. User buka Manajemen Target Device                   │
│    → Tab "Discovery via Core Router"                   │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 2. User input credentials Core Router:                 │
│    - IP Core Router: 192.168.1.1                       │
│    - Username: admin                                   │
│    - Password: ********                                │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 3. User klik "Test Connection"                         │
│    → Backend validasi koneksi ke MikroTik             │
│    → Success: Enable button "Connect & Discover"       │
│    → Failed: Tampil error message                      │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 4. User klik "Connect & Discover"                      │
│    → Loading indicator muncul                          │
│    → Backend query DHCP leases dari MikroTik           │
│    → Auto-detect merk device untuk setiap client       │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 5. Tampil Discovery Result List:                       │
│    ┌────────────┬────────────────┬──────────┬───────┐  │
│    │ IP         │ Merk Device    │ MAC      │ Aksi  │  │
│    ├────────────┼────────────────┼──────────┼───────┤  │
│    │ 192.168.1.10│ MikroTik RB750│ 00:0C... │ [+Add]│  │
│    │ 192.168.1.11│ TP-Link       │ B4:FB... │ [+Add]│  │
│    │ 192.168.1.12│ Ubiquiti      │ DC:2C... │ [+Add]│  │
│    └────────────┴────────────────┴──────────┴───────┘  │
│                                                         │
│    Search: [________] (filter by IP/merk)              │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 6. User klik button "+Add" pada client tertentu       │
│    → Modal "Tambah Client Device" muncul               │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 7. Modal Form Tambah Client:                           │
│    - IP Address: 192.168.1.10 (readonly)               │
│    - Merk Device: MikroTik RB750 (readonly)            │
│    - ID Pelanggan: CST-001 (auto-generate)             │
│    - Nama Pelanggan: [____________] (input required)   │
│    - Tanggal Pemasangan: [DD/MM/YYYY] (date picker)    │
│    - URL Maps Lokasi: [___________] (input required)   │
│                                                         │
│    [Batal]  [Simpan]                                   │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 8. User klik "Simpan"                                  │
│    → Backend validasi input                            │
│    → Save ke database                                  │
│    → Auto-add ke Prometheus targets (SNMP + Blackbox)  │
│    → Success notification                              │
│    → IP yang ditambahkan hilang dari discovery list    │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ 9. User bisa lanjut add client lain atau selesai      │
│    → Jika perlu refresh: Connect ulang                 │
│    → Device yang sudah ditambah: Tidak tampil lagi     │
└─────────────────────────────────────────────────────────┘
```

### Backend Flow (Technical)

```
┌─────────────────────────────────────────────────────────┐
│ REQUEST: POST /devices/test-core-router                │
├─────────────────────────────────────────────────────────┤
│ 1. DeviceController::testCoreRouter()                  │
│    ├─ Validate request (IP, username, password)        │
│    ├─ MikroTikService::testConnection()                │
│    │   ├─ Create RouterOS Client instance              │
│    │   ├─ Attempt connection                           │
│    │   └─ Return success/fail                          │
│    └─ Return JSON response                             │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ REQUEST: POST /devices/connect-core-router             │
├─────────────────────────────────────────────────────────┤
│ 2. DeviceController::connectCoreRouter()               │
│    ├─ Validate request                                 │
│    ├─ MikroTikService::getDhcpLeases()                 │
│    │   ├─ Connect to MikroTik                          │
│    │   ├─ Query: /ip/dhcp-server/lease/print           │
│    │   └─ Parse result: IP, MAC, Hostname              │
│    ├─ DeviceDiscoveryService::discoverClients()        │
│    │   ├─ For each DHCP lease:                         │
│    │   │   ├─ enrichClientInfo()                       │
│    │   │   │   ├─ Try SNMP query → sysDescr            │
│    │   │   │   └─ If fail → MacVendorService::lookup() │
│    │   │   └─ Build client data object                 │
│    │   └─ filterExistingDevices()                      │
│    │       ├─ Query database for existing IPs          │
│    │       └─ Remove IPs yang sudah registered         │
│    └─ Return JSON: array of client devices             │
└─────────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────────┐
│ REQUEST: POST /devices/add-client                      │
├─────────────────────────────────────────────────────────┤
│ 3. DeviceController::addClientDevice()                 │
│    ├─ Validate input                                   │
│    ├─ Generate customer_id (auto-increment)            │
│    ├─ Begin database transaction                       │
│    ├─ Create device record                             │
│    ├─ PrometheusTargetService::addTarget()             │
│    │   ├─ Add to targets.json (SNMP)                   │
│    │   └─ Add to blackbox-targets.json (Ping)          │
│    ├─ Commit transaction                               │
│    └─ Return success response                          │
└─────────────────────────────────────────────────────────┘
```

---

## 🛠️ Instalasi & Setup

### A. Instalasi Library PHP (Composer)

**Library yang Dibutuhkan:**

1. **RouterOS API Client**

    ```bash
    cd C:\laragon\www\monitoring\web-app
    composer require evilfreelancer/routeros-api-php
    ```

    **Fungsi:** Connect ke MikroTik dan query DHCP leases

2. **Verifikasi Instalasi:**

    ```bash
    composer show evilfreelancer/routeros-api-php
    ```

    **Expected Output:**

    ```
    name     : evilfreelancer/routeros-api-php
    descrip. : RouterOS API PHP client for interacting with MikroTik routers
    versions : * 1.x.x
    ```

---

### B. Setup MikroTik Core Router

**Prerequisites:**

- MikroTik RouterOS versi 6.x atau 7.x
- Akses ke terminal/Winbox MikroTik
- IP Core Router harus bisa diakses dari server monitoring

#### Step 1: Enable API Service

```bash
# Login ke MikroTik via SSH atau Winbox Terminal

# Enable API Service (port 8728)
/ip service enable api

# Set port (default 8728)
/ip service set api port=8728
/ip service set api address=0.0.0.0/0

# Verifikasi API sudah enable
/ip service print
```

**Expected Output:**

```
# NAME       PORT  ADDRESS          CERTIFICATE
0 api        8728  0.0.0.0/0
  ...
```

#### Step 2: Create API User (Read-Only)

```bash
# Create user khusus untuk API monitoring
/user add name=api-monitoring password=StrongPassword123! group=read

# Verifikasi user sudah dibuat
/user print
```

**Expected Output:**

```
# NAME            GROUP       ADDRESS
0 admin           full        0.0.0.0/0
1 api-monitoring  read        0.0.0.0/0
```

**Penjelasan:**

- `name=api-monitoring` → Username untuk connect dari web monitoring
- `password=StrongPassword123!` → Password yang aman (ganti sesuai kebutuhan)
- `group=read` → Read-only access (tidak bisa ubah config MikroTik)

#### Step 3: (Opsional) Enable API-SSL untuk Security

```bash
# Enable API-SSL (encrypted connection)
/ip service enable api-ssl
/ip service set api-ssl port=8729

# Generate self-signed certificate jika belum ada
/certificate add name=api-cert common-name=mikrotik.local
/certificate sign api-cert
/ip service set api-ssl certificate=api-cert
```

**Note:** Jika pakai API-SSL, ubah port di code dari 8728 ke 8729

#### Step 4: Test Connection dari Server

**Via Terminal/PowerShell:**

```bash
# Test ping ke Core Router
ping 192.168.1.1

# Test telnet ke API port (jika available)
telnet 192.168.1.1 8728
```

**Via PHP (Tinker):**

```bash
php artisan tinker

# Test RouterOS connection
use RouterOS\Client;

$client = new Client([
    'host' => '192.168.1.1',
    'user' => 'api-monitoring',
    'pass' => 'StrongPassword123!',
    'port' => 8728,
]);

echo "Connected!";
```

#### Troubleshooting MikroTik

**Problem 1: Connection Refused**

```bash
# Cek apakah API service running
/ip service print

# Jika disabled, enable lagi
/ip service enable api
```

**Problem 2: Authentication Failed**

```bash
# Cek user & password
/user print detail

# Reset password jika lupa
/user set api-monitoring password=NewPassword123!
```

**Problem 3: Firewall Blocking**

```bash
# Cek firewall rules
/ip firewall filter print

# Add rule untuk allow API port dari server monitoring
/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=192.168.1.100 action=accept comment="Allow API from monitoring server"
```

**Replace `192.168.1.100` dengan IP server monitoring Anda**

---

### C. Konfigurasi Environment (.env)

**Tambahkan variable baru ke file `.env`:**

```env
# MikroTik Core Router Settings
MIKROTIK_CORE_IP=192.168.1.1
MIKROTIK_API_PORT=8728
MIKROTIK_API_SSL=false

# Note: Username & Password TIDAK disimpan di .env
# User input setiap kali connect (security best practice)
```

**Penjelasan:**

- `MIKROTIK_CORE_IP` → IP default Core Router (opsional, bisa kosong)
- `MIKROTIK_API_PORT` → Port API (default 8728, 8729 jika pakai SSL)
- `MIKROTIK_API_SSL` → true/false untuk enable SSL connection

---

## 📂 Struktur File yang Akan Dibuat/Diubah

### Backend Files (New)

```
app/
├── Services/
│   ├── MikroTikService.php                 (NEW)
│   ├── DeviceDiscoveryService.php          (NEW)
│   └── MacVendorService.php                (NEW)
│
└── Http/
    └── Controllers/
        └── DeviceController.php             (MODIFIED)
```

### Frontend Files (Modified)

```
resources/
└── js/
    └── Pages/
        └── Devices/
            └── Index.vue                    (MODIFIED)
```

### Routes (Modified)

```
routes/
└── web.php                                  (MODIFIED)
```

---

## 📝 Detail Implementasi

### 1. Backend - MikroTikService.php

**Location:** `app/Services/MikroTikService.php`

**Methods:**

#### `testConnection($ip, $username, $password): array`

- Test koneksi ke MikroTik RouterOS API
- Return: `['success' => bool, 'message' => string]`

**Code Example:**

```php
public function testConnection(string $ip, string $username, string $password): array
{
    try {
        $client = new Client([
            'host' => $ip,
            'user' => $username,
            'pass' => $password,
            'port' => (int) config('mikrotik.api_port', 8728),
            'timeout' => 5,
        ]);

        // Test query identity untuk validasi connection
        $query = new Query('/system/identity/print');
        $response = $client->query($query)->read();

        return [
            'success' => true,
            'message' => 'Koneksi berhasil ke MikroTik Router',
            'identity' => $response[0]['name'] ?? 'Unknown',
        ];
    } catch (\Exception $e) {
        Log::error("MikroTik connection failed: " . $e->getMessage());

        return [
            'success' => false,
            'message' => 'Gagal connect ke MikroTik: ' . $e->getMessage(),
        ];
    }
}
```

#### `getDhcpLeases($ip, $username, $password): array`

- Query DHCP leases dari MikroTik
- Return: Array of clients `[['ip' => ..., 'mac' => ..., 'hostname' => ...], ...]`

**Code Example:**

```php
public function getDhcpLeases(string $ip, string $username, string $password): array
{
    try {
        $client = new Client([
            'host' => $ip,
            'user' => $username,
            'pass' => $password,
            'port' => (int) config('mikrotik.api_port', 8728),
        ]);

        // Query DHCP leases
        $query = new Query('/ip/dhcp-server/lease/print');
        $leases = $client->query($query)->read();

        $clients = [];
        foreach ($leases as $lease) {
            // Filter hanya yang bound (active)
            if (isset($lease['address']) && isset($lease['mac-address'])) {
                $clients[] = [
                    'ip' => $lease['address'],
                    'mac' => $lease['mac-address'],
                    'hostname' => $lease['host-name'] ?? '',
                    'status' => $lease['status'] ?? 'unknown',
                ];
            }
        }

        return $clients;
    } catch (\Exception $e) {
        Log::error("Failed to get DHCP leases: " . $e->getMessage());
        throw $e;
    }
}
```

---

### 2. Backend - MacVendorService.php

**Location:** `app/Services/MacVendorService.php`

**Methods:**

#### `lookupVendor($macAddress): string`

- Detect vendor/merk device dari MAC Address
- Return: Vendor name atau "Unknown Device"

**Code Example:**

```php
<?php

namespace App\Services;

class MacVendorService
{
    /**
     * Mapping MAC Address prefix (OUI) ke vendor name.
     * Format: 'XX:XX:XX' => 'Vendor Name'
     *
     * List ini manual curated untuk vendor yang umum di Indonesia.
     * Bisa ditambah sesuai kebutuhan.
     */
    private array $vendors = [
        // MikroTik
        '00:0C:42' => 'MikroTik',
        '4C:5E:0C' => 'MikroTik',
        '6C:3B:6B' => 'MikroTik',
        '48:8F:5A' => 'MikroTik',
        'D4:CA:6D' => 'MikroTik',
        'E4:8D:8C' => 'MikroTik',

        // Ubiquiti
        'DC:2C:6E' => 'Ubiquiti',
        '74:83:C2' => 'Ubiquiti',
        'F0:9F:C2' => 'Ubiquiti',
        '68:D7:9A' => 'Ubiquiti',
        '24:A4:3C' => 'Ubiquiti',
        '44:D9:E7' => 'Ubiquiti',

        // TP-Link
        'B4:FB:E4' => 'TP-Link',
        '68:72:51' => 'TP-Link',
        'C4:E9:84' => 'TP-Link',
        '50:C7:BF' => 'TP-Link',
        'EC:08:6B' => 'TP-Link',

        // Tenda
        'C8:3A:35' => 'Tenda',

        // D-Link
        '00:05:5D' => 'D-Link',
        '00:1B:11' => 'D-Link',
        'C8:BE:19' => 'D-Link',

        // Cambium Networks
        '00:04:56' => 'Cambium',
        '58:C1:7A' => 'Cambium',

        // Ruckus Wireless
        '2C:56:DC' => 'Ruckus',

        // Tambah vendor lain sesuai kebutuhan...
    ];

    /**
     * Lookup vendor dari MAC Address.
     *
     * @param string $macAddress MAC Address format: AA:BB:CC:DD:EE:FF atau AA-BB-CC-DD-EE-FF
     * @return string Vendor name atau "Unknown Device" jika tidak ditemukan
     */
    public function lookupVendor(string $macAddress): string
    {
        // Normalize MAC Address: remove delimiter dan uppercase
        $mac = strtoupper(str_replace([':', '-'], '', $macAddress));

        // Get first 6 characters (OUI)
        $oui = substr($mac, 0, 6);

        // Format dengan colon untuk matching (XX:XX:XX)
        $ouiFormatted = substr($oui, 0, 2) . ':' . substr($oui, 2, 2) . ':' . substr($oui, 4, 2);

        // Lookup di vendor list
        return $this->vendors[$ouiFormatted] ?? 'Unknown Device';
    }

    /**
     * Get semua vendor yang tersedia di database.
     * Digunakan untuk debugging atau statistik.
     *
     * @return array List vendor yang di-support
     */
    public function getAvailableVendors(): array
    {
        return array_unique(array_values($this->vendors));
    }
}
```

---

### 3. Backend - DeviceDiscoveryService.php

**Location:** `app/Services/DeviceDiscoveryService.php`

**Methods:**

#### `discoverClients($coreRouterIp, $credentials): array`

- Orchestrator utama untuk discovery process
- Menggabungkan semua service (MikroTik, MacVendor, SNMP)

#### `enrichClientInfo($client): array`

- Enrich data client dengan auto-detect merk device
- Priority: SNMP query → MAC Vendor lookup

#### `filterExistingDevices($clients): array`

- Filter client yang sudah registered di database

**Code Example:**

```php
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
     *
     * @param string $coreRouterIp IP Core Router MikroTik
     * @param array $credentials ['username' => ..., 'password' => ...]
     * @return array List client devices yang siap ditampilkan
     */
    public function discoverClients(string $coreRouterIp, array $credentials): array
    {
        // Step 1: Get DHCP leases dari MikroTik
        $dhcpClients = $this->mikrotikService->getDhcpLeases(
            $coreRouterIp,
            $credentials['username'],
            $credentials['password']
        );

        Log::info("DeviceDiscoveryService: Found {count} DHCP clients", [
            'count' => count($dhcpClients)
        ]);

        // Step 2: Enrich setiap client dengan device model info
        $enrichedClients = [];
        foreach ($dhcpClients as $client) {
            $enrichedClients[] = $this->enrichClientInfo($client);
        }

        // Step 3: Filter client yang sudah terdaftar di database
        $filteredClients = $this->filterExistingDevices($enrichedClients);

        Log::info("DeviceDiscoveryService: {filtered} new clients after filtering", [
            'filtered' => count($filteredClients)
        ]);

        return $filteredClients;
    }

    /**
     * Enrich client info dengan auto-detect device model.
     * Priority: SNMP query dulu, fallback ke MAC Vendor lookup.
     *
     * @param array $client Data dari DHCP lease
     * @return array Client dengan tambahan field 'model'
     */
    private function enrichClientInfo(array $client): array
    {
        $model = 'Unknown Device';

        // Try 1: SNMP query untuk get device model detail
        try {
            $snmpCommunity = 'public'; // Default SNMP community
            $sysDescr = @snmpget($client['ip'], $snmpCommunity, "1.3.6.1.2.1.1.1.0", 1000000);

            if ($sysDescr !== false && !empty($sysDescr)) {
                // Parse sysDescr untuk extract model name
                // Format MikroTik: "RouterOS RB750Gr3"
                // Format Ubiquiti: "NanoStation M5"
                $model = $this->parseSysDescr($sysDescr);
            }
        } catch (\Exception $e) {
            Log::debug("SNMP query failed for {ip}, fallback to MAC lookup", [
                'ip' => $client['ip'],
                'error' => $e->getMessage()
            ]);
        }

        // Try 2: MAC Vendor lookup jika SNMP gagal
        if ($model === 'Unknown Device' && !empty($client['mac'])) {
            $model = $this->macVendorService->lookupVendor($client['mac']);
        }

        return [
            'ip' => $client['ip'],
            'mac' => $client['mac'],
            'hostname' => $client['hostname'],
            'model' => $model,
        ];
    }

    /**
     * Parse SNMP sysDescr untuk extract device model.
     *
     * @param string $sysDescr Raw sysDescr dari SNMP
     * @return string Device model yang sudah di-parse
     */
    private function parseSysDescr(string $sysDescr): string
    {
        // Remove SNMP type prefix (STRING: )
        $sysDescr = preg_replace('/^STRING:\s*/', '', $sysDescr);
        $sysDescr = trim($sysDescr, '"');

        // Extract model dari common patterns
        // MikroTik: "RouterOS RB750Gr3" → "MikroTik RB750Gr3"
        if (stripos($sysDescr, 'RouterOS') !== false) {
            return 'MikroTik ' . trim(str_ireplace('RouterOS', '', $sysDescr));
        }

        // Ubiquiti: biasanya langsung model name
        if (stripos($sysDescr, 'NanoStation') !== false ||
            stripos($sysDescr, 'AirMax') !== false ||
            stripos($sysDescr, 'EdgeRouter') !== false) {
            return 'Ubiquiti ' . $sysDescr;
        }

        // TP-Link, Tenda, dll: return as-is
        return $sysDescr;
    }

    /**
     * Filter client yang sudah registered di database.
     * Hide client yang IP-nya sudah ada agar tidak duplikat.
     *
     * @param array $clients List client dari discovery
     * @return array Client yang belum registered
     */
    private function filterExistingDevices(array $clients): array
    {
        // Get semua IP yang sudah terdaftar di database
        $existingIps = Device::pluck('ip_address')->toArray();

        // Filter: hanya ambil client yang IP-nya belum ada
        return array_filter($clients, function($client) use ($existingIps) {
            return !in_array($client['ip'], $existingIps);
        });
    }
}
```

---

### 4. Backend - DeviceController.php (Modified)

**Location:** `app/Http/Controllers/DeviceController.php`

**New Methods:**

#### `testCoreRouter(Request $request)`

- Endpoint untuk test connection ke Core Router
- Route: `POST /devices/test-core-router`

#### `connectCoreRouter(Request $request)`

- Endpoint untuk discovery DHCP leases
- Route: `POST /devices/connect-core-router`

#### `addClientDevice(Request $request)`

- Endpoint untuk tambah client dari discovery
- Route: `POST /devices/add-client`

**Code Example:**

```php
/**
 * Test koneksi ke MikroTik Core Router.
 * Endpoint: POST /devices/test-core-router
 */
public function testCoreRouter(Request $request)
{
    // Validasi input
    $validated = $request->validate([
        'core_router_ip' => 'required|ip',
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    // Test connection via MikroTikService
    $result = $this->mikrotikService->testConnection(
        $validated['core_router_ip'],
        $validated['username'],
        $validated['password']
    );

    return response()->json($result);
}

/**
 * Connect ke Core Router dan discover DHCP clients.
 * Endpoint: POST /devices/connect-core-router
 */
public function connectCoreRouter(Request $request)
{
    // Validasi input
    $validated = $request->validate([
        'core_router_ip' => 'required|ip',
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    try {
        // Discovery clients via DeviceDiscoveryService
        $clients = $this->discoveryService->discoverClients(
            $validated['core_router_ip'],
            [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ]
        );

        return response()->json([
            'success' => true,
            'clients' => $clients,
            'total' => count($clients),
        ]);
    } catch (\Exception $e) {
        Log::error("Discovery failed: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Gagal melakukan discovery: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Tambah client device dari hasil discovery.
 * Endpoint: POST /devices/add-client
 */
public function addClientDevice(Request $request)
{
    // Validasi input
    $validated = $request->validate([
        'ip_address' => 'required|ip|unique:devices,ip_address',
        'device_model' => 'required|string',
        'customer_name' => 'required|string|max:255',
        'installation_date' => 'required|date',
        'maps_url' => 'required|url|max:500',
    ]);

    try {
        DB::beginTransaction();

        // Generate customer_id otomatis
        $lastDevice = Device::orderBy('id', 'desc')->first();
        $lastNumber = $lastDevice ? (int) substr($lastDevice->customer_id, 4) : 0;
        $customerId = 'CST-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        // Create device record
        $device = Device::create([
            'customer_id' => $customerId,
            'customer_name' => $validated['customer_name'],
            'ip_address' => $validated['ip_address'],
            'device_model' => $validated['device_model'],
            'installation_date' => $validated['installation_date'],
            'maps_url' => $validated['maps_url'],
        ]);

        // Add to Prometheus targets (SNMP + Blackbox)
        $this->prometheusTargetService->addTarget($validated['ip_address']);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Client device berhasil ditambahkan!',
            'device' => $device,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to add client device: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Gagal menambahkan device: ' . $e->getMessage(),
        ], 500);
    }
}
```

---

### 5. Frontend - Index.vue (Modified)

**Location:** `resources/js/Pages/Devices/Index.vue`

**New UI Components:**

1. **Discovery Mode Section**
    - Form input Core Router credentials
    - Test Connection button
    - Connect & Discover button
    - Discovery result list
    - Search/filter functionality

2. **Modal Add Client**
    - Display discovered client info (readonly)
    - Form input identitas pelanggan
    - Save button

**Code Structure:**

```vue
<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

// State untuk discovery mode
const discoveryMode = ref(false);
const discoveredClients = ref([]);
const searchDiscovery = ref("");
const isDiscovering = ref(false);
const connectionTested = ref(false);

// Form untuk Core Router credentials
const coreRouterForm = useForm({
    core_router_ip: "",
    username: "",
    password: "",
});

// Form untuk tambah client dari discovery
const addClientForm = useForm({
    ip_address: "",
    device_model: "",
    customer_name: "",
    installation_date: "",
    maps_url: "",
});

// Modal state
const showAddClientModal = ref(false);
const selectedClient = ref(null);

// Test connection ke Core Router
const testConnection = () => {
    // POST to /devices/test-core-router
    // Set connectionTested = true jika success
};

// Connect & Discover
const connectAndDiscover = () => {
    // POST to /devices/connect-core-router
    // Set discoveredClients dengan hasil
};

// Open modal add client
const openAddClientModal = (client) => {
    selectedClient.value = client;
    addClientForm.ip_address = client.ip;
    addClientForm.device_model = client.model;
    showAddClientModal.value = true;
};

// Submit add client
const submitAddClient = () => {
    addClientForm.post("/devices/add-client", {
        onSuccess: () => {
            showAddClientModal.value = false;
            // Remove client dari discovery list
            discoveredClients.value = discoveredClients.value.filter(
                (c) => c.ip !== addClientForm.ip_address,
            );
        },
    });
};

// Computed: filtered discovery list
const filteredClients = computed(() => {
    if (!searchDiscovery.value) return discoveredClients.value;

    return discoveredClients.value.filter(
        (client) =>
            client.ip.includes(searchDiscovery.value) ||
            client.model
                .toLowerCase()
                .includes(searchDiscovery.value.toLowerCase()),
    );
});
</script>

<template>
    <div class="page-container">
        <!-- Tab switcher: Manual Add vs Discovery -->
        <div class="tabs">
            <button
                @click="discoveryMode = false"
                :class="{ active: !discoveryMode }"
            >
                Manual Add Device
            </button>
            <button
                @click="discoveryMode = true"
                :class="{ active: discoveryMode }"
            >
                Discovery via Core Router
            </button>
        </div>

        <!-- Manual Add Mode (existing) -->
        <div v-show="!discoveryMode">
            <!-- Existing manual add form -->
        </div>

        <!-- Discovery Mode (new) -->
        <div v-show="discoveryMode">
            <!-- Core Router Connection Form -->
            <div class="card">
                <h3>Connect to MikroTik Core Router</h3>

                <form @submit.prevent="testConnection">
                    <div class="form-row">
                        <div class="form-group">
                            <label>IP Core Router</label>
                            <input
                                v-model="coreRouterForm.core_router_ip"
                                type="text"
                                class="form-input"
                                placeholder="192.168.1.1"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input
                                v-model="coreRouterForm.username"
                                type="text"
                                class="form-input"
                                placeholder="admin"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input
                                v-model="coreRouterForm.password"
                                type="password"
                                class="form-input"
                                required
                            />
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-secondary">
                            Test Connection
                        </button>
                        <button
                            type="button"
                            @click="connectAndDiscover"
                            :disabled="!connectionTested || isDiscovering"
                            class="btn btn-primary"
                        >
                            {{
                                isDiscovering
                                    ? "Discovering..."
                                    : "Connect & Discover"
                            }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Discovery Results -->
            <div v-if="discoveredClients.length > 0" class="card">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;"
                >
                    <h3>
                        Client Devices Discovered ({{ filteredClients.length }})
                    </h3>
                    <input
                        v-model="searchDiscovery"
                        type="text"
                        placeholder="Search by IP or Merk..."
                        class="form-input"
                        style="max-width: 300px;"
                    />
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Merk Device</th>
                            <th>MAC Address</th>
                            <th>Hostname</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="client in filteredClients" :key="client.ip">
                            <td style="font-family: monospace;">
                                {{ client.ip }}
                            </td>
                            <td>{{ client.model }}</td>
                            <td
                                style="font-family: monospace; font-size: 12px;"
                            >
                                {{ client.mac }}
                            </td>
                            <td>{{ client.hostname || "-" }}</td>
                            <td>
                                <button
                                    @click="openAddClientModal(client)"
                                    class="btn btn-sm btn-primary"
                                >
                                    + Add
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Add Client -->
        <div
            v-if="showAddClientModal"
            class="modal-overlay"
            @click.self="showAddClientModal = false"
        >
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Tambah Client Device</h3>
                    <button
                        @click="showAddClientModal = false"
                        class="btn btn-ghost"
                    >
                        ✕
                    </button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="submitAddClient">
                        <div class="form-group">
                            <label>IP Address</label>
                            <input
                                v-model="addClientForm.ip_address"
                                type="text"
                                class="form-input"
                                readonly
                            />
                        </div>
                        <div class="form-group">
                            <label>Merk Device</label>
                            <input
                                v-model="addClientForm.device_model"
                                type="text"
                                class="form-input"
                                readonly
                            />
                        </div>
                        <div class="form-group">
                            <label>ID Pelanggan</label>
                            <input
                                value="Auto-generate"
                                type="text"
                                class="form-input"
                                readonly
                            />
                        </div>
                        <div class="form-group">
                            <label>Nama Pelanggan</label>
                            <input
                                v-model="addClientForm.customer_name"
                                type="text"
                                class="form-input"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label>Tanggal Pemasangan</label>
                            <input
                                v-model="addClientForm.installation_date"
                                type="date"
                                class="form-input"
                                required
                            />
                        </div>
                        <div class="form-group">
                            <label>URL Maps Lokasi</label>
                            <input
                                v-model="addClientForm.maps_url"
                                type="url"
                                class="form-input"
                                required
                            />
                        </div>

                        <div
                            style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;"
                        >
                            <button
                                type="button"
                                @click="showAddClientModal = false"
                                class="btn btn-secondary"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="addClientForm.processing"
                                class="btn btn-primary"
                            >
                                {{
                                    addClientForm.processing
                                        ? "Menyimpan..."
                                        : "Simpan"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
```

---

### 6. Routes (Modified)

**Location:** `routes/web.php`

**New Routes:**

```php
// Discovery Routes - MikroTik Core Router
Route::post('/devices/test-core-router', [DeviceController::class, 'testCoreRouter'])->name('devices.test-core-router');
Route::post('/devices/connect-core-router', [DeviceController::class, 'connectCoreRouter'])->name('devices.connect-core-router');
Route::post('/devices/add-client', [DeviceController::class, 'addClientDevice'])->name('devices.add-client');
```

---

## 🧪 Testing Plan

### Unit Testing

**Test Cases:**

1. **MikroTikService::testConnection()**
    - Valid credentials → Success
    - Invalid credentials → Fail dengan error message
    - Wrong IP → Timeout/connection refused

2. **MikroTikService::getDhcpLeases()**
    - Router dengan leases → Return array clients
    - Router tanpa leases → Return empty array
    - Connection fail → Throw exception

3. **MacVendorService::lookupVendor()**
    - MAC MikroTik → Return "MikroTik"
    - MAC Unknown → Return "Unknown Device"
    - Invalid MAC format → Handle gracefully

4. **DeviceDiscoveryService::discoverClients()**
    - Full flow → Return enriched + filtered clients
    - SNMP success → Use SNMP model
    - SNMP fail → Fallback to MAC lookup
    - Existing devices → Filtered out

### Integration Testing

**Scenarios:**

1. **Happy Path - Full Discovery**

    ```
    Steps:
    1. Input Core Router IP + credentials
    2. Test Connection → Success
    3. Connect & Discover → List muncul dengan 10 clients
    4. Klik +Add pada 1 client
    5. Input identitas pelanggan
    6. Submit → Success
    7. Verify: Device masuk database + Prometheus targets
    8. Verify: Client hilang dari discovery list
    ```

2. **SNMP Fallback to MAC Lookup**

    ```
    Steps:
    1. Discovery dengan client yang SNMP timeout
    2. Verify: Model terdeteksi via MAC lookup
    3. Display: "TP-Link" (bukan "Unknown Device")
    ```

3. **Duplicate Prevention**

    ```
    Steps:
    1. Discovery list tampil 5 clients
    2. Add 2 clients ke database
    3. Refresh discovery (connect ulang)
    4. Verify: List hanya 3 clients (2 yang sudah ditambah hidden)
    ```

4. **Search/Filter Discovery**
    ```
    Steps:
    1. Discovery list 10 clients
    2. Search "192.168.1.1" → Hanya tampil 1 client
    3. Search "MikroTik" → Tampil semua MikroTik device
    4. Clear search → Tampil semua lagi
    ```

### Manual Testing Checklist

- [ ] Install RouterOS API library
- [ ] Setup MikroTik Core Router (enable API, create user)
- [ ] Test connection dari server via telnet port 8728
- [ ] Test connection via PHP Tinker
- [ ] Build frontend (npm run build)
- [ ] Test discovery UI
- [ ] Verify DHCP leases muncul
- [ ] Verify auto-detect merk device
- [ ] Test add client
- [ ] Verify device masuk database
- [ ] Verify Prometheus targets updated
- [ ] Test search/filter
- [ ] Test duplicate prevention
- [ ] Test error handling (wrong credentials, network down, dll)

---

## 📊 Estimasi Waktu Implementasi

| Task                                  | Estimasi      | Complexity |
| ------------------------------------- | ------------- | ---------- |
| **Phase 1: Backend Foundation**       |               |            |
| Install RouterOS API library          | 30 min        | LOW        |
| Create MikroTikService                | 2 jam         | MEDIUM     |
| Create MacVendorService               | 1 jam         | LOW        |
| Create DeviceDiscoveryService         | 2 jam         | MEDIUM     |
| Test via Artisan Tinker               | 30 min        | LOW        |
| **Subtotal Phase 1**                  | **6 jam**     |            |
|                                       |               |            |
| **Phase 2: Backend Endpoints**        |               |            |
| DeviceController::testCoreRouter()    | 1 jam         | LOW        |
| DeviceController::connectCoreRouter() | 1 jam         | LOW        |
| DeviceController::addClientDevice()   | 1 jam         | LOW        |
| Routes configuration                  | 15 min        | LOW        |
| **Subtotal Phase 2**                  | **3 jam**     |            |
|                                       |               |            |
| **Phase 3: Frontend UI**              |               |            |
| Tab switcher (Manual vs Discovery)    | 30 min        | LOW        |
| Core Router connection form           | 1 jam         | LOW        |
| Test connection button logic          | 30 min        | LOW        |
| Discovery result list + styling       | 2 jam         | MEDIUM     |
| Search/filter functionality           | 1 jam         | LOW        |
| Modal Add Client                      | 2 jam         | MEDIUM     |
| Error handling + notifications        | 1 jam         | LOW        |
| **Subtotal Phase 3**                  | **8 jam**     |            |
|                                       |               |            |
| **Phase 4: Testing & Polish**         |               |            |
| Unit testing services                 | 1 jam         | LOW        |
| Integration testing full flow         | 2 jam         | MEDIUM     |
| Bug fixes                             | 2 jam         | MEDIUM     |
| UI/UX refinement                      | 1 jam         | LOW        |
| Documentation                         | 30 min        | LOW        |
| **Subtotal Phase 4**                  | **6.5 jam**   |            |
|                                       |               |            |
| **TOTAL ESTIMASI**                    | **23-24 jam** | **MEDIUM** |

**Note:** Estimasi bisa lebih cepat jika tidak ada blocker dan semua setup MikroTik sudah siap.

---

## 🚨 Potensi Masalah & Solusi

### Problem 1: SNMP Extension PHP Tidak Terinstall

**Symptom:**

```
Call to undefined function snmpget()
```

**Solution:**

```bash
# Windows (XAMPP/Laragon)
1. Edit php.ini
2. Uncomment: extension=snmp
3. Restart web server

# Verify
php -m | grep snmp
```

### Problem 2: RouterOS API Connection Timeout

**Symptom:**

```
Connection refused on port 8728
```

**Solution:**

1. Cek firewall MikroTik: `/ip firewall filter print`
2. Enable API: `/ip service enable api`
3. Test dari server: `telnet 192.168.1.1 8728`

### Problem 3: DHCP Leases Empty Result

**Symptom:**
Discovery return 0 clients padahal ada client yang connect

**Solution:**

1. Cek DHCP Server di MikroTik: `/ip dhcp-server lease print`
2. Pastikan leases status = "bound"
3. Verify API user punya read permission

### Problem 4: MAC Vendor Lookup Return Unknown

**Symptom:**
Semua device terdeteksi sebagai "Unknown Device"

**Solution:**

1. Check MAC Address format dari MikroTik
2. Tambahkan MAC prefix vendor ke `MacVendorService::$vendors`
3. Debug dengan log actual MAC yang di-query

### Problem 5: Frontend Build Error

**Symptom:**

```
npm run build failed
```

**Solution:**

```bash
# Clear cache
rm -rf node_modules
npm install

# Build lagi
npm run build
```

---

## 📚 Resources & References

### Documentation

1. **MikroTik RouterOS API**
    - Official: https://wiki.mikrotik.com/wiki/Manual:API
    - RouterOS API PHP Library: https://github.com/EvilFreelancer/routeros-api-php

2. **MAC Address OUI Database**
    - IEEE OUI Database: https://standards.ieee.org/products-programs/regauth/
    - MAC Vendor Lookup: https://www.macvendorlookup.com/

3. **SNMP PHP**
    - PHP Manual: https://www.php.net/manual/en/book.snmp.php
    - Common OIDs: https://www.oidview.com/

### Support & Help

**Jika menemukan masalah:**

1. Check log Laravel: `storage/logs/laravel.log`
2. Check log MikroTik: `/log print`
3. Test manual via Tinker: `php artisan tinker`
4. Konsultasi dokumentasi di atas

---

## ✅ Checklist Implementasi

**Pre-Implementation:**

- [ ] Backup database
- [ ] Backup existing code
- [ ] Setup MikroTik Core Router (enable API, create user)
- [ ] Test MikroTik API connection dari server
- [ ] Verify SNMP extension PHP active

**Phase 1: Backend Foundation**

- [ ] Install `evilfreelancer/routeros-api-php` via Composer
- [ ] Create `MikroTikService.php`
- [ ] Create `MacVendorService.php`
- [ ] Create `DeviceDiscoveryService.php`
- [ ] Test services via Tinker

**Phase 2: Backend Endpoints**

- [ ] Add methods ke `DeviceController.php`
- [ ] Add routes ke `web.php`
- [ ] Test endpoints via Postman/Insomnia

**Phase 3: Frontend UI**

- [ ] Modify `Devices/Index.vue`
- [ ] Add discovery mode UI
- [ ] Add modal add client
- [ ] Build frontend: `npm run build`
- [ ] Test UI di browser

**Phase 4: Testing**

- [ ] Test full flow: connect → discover → add
- [ ] Test error scenarios
- [ ] Test search/filter
- [ ] Test duplicate prevention
- [ ] Test manual add (existing flow)

**Post-Implementation:**

- [ ] Update dokumentasi user manual
- [ ] Update `LOGupdate.md`
- [ ] Deploy ke production (jika applicable)
- [ ] Monitor log untuk errors

---

## 🎯 Success Criteria

Fitur dianggap **berhasil** jika:

1. ✅ User bisa connect ke Core Router dengan credentials
2. ✅ Discovery menampilkan list DHCP clients dari MikroTik
3. ✅ Merk device terdeteksi otomatis (SNMP atau MAC lookup)
4. ✅ User bisa add client dari discovery list
5. ✅ Device yang ditambah masuk database + Prometheus targets
6. ✅ Device yang sudah ditambah tidak muncul lagi di discovery list
7. ✅ Search/filter berfungsi dengan baik
8. ✅ Manual add (existing flow) tetap berfungsi
9. ✅ Tidak ada breaking changes pada fitur existing
10. ✅ Error handling yang baik (network error, wrong credentials, dll)

---

## 📝 Notes & Recommendations

### Security Best Practices

1. **Credentials tidak disimpan:**
    - User input credentials setiap kali connect
    - Password tidak disimpan di database atau session
    - Hanya untuk satu kali query, lalu discarded

2. **API User read-only:**
    - MikroTik API user group=read
    - Tidak bisa ubah config MikroTik dari web monitoring

3. **Input validation:**
    - Validate IP format
    - Sanitize user input
    - Prevent SQL injection (sudah handled Laravel Eloquent)

### Performance Optimization

1. **SNMP timeout:**
    - Set timeout 1-2 detik per device
    - Parallel query jika banyak device (future enhancement)

2. **Discovery cache:**
    - Saat ini no cache (query fresh)
    - Bisa tambah cache 5-10 menit jika perlu (future)

3. **Limit discovery result:**
    - Max 100 clients per discovery (prevent overload)
    - Pagination jika perlu (future)

### Future Enhancements (Post-MVP)

1. **Batch Add Multiple Clients**
    - Checkbox select multiple
    - Add selected button
    - Bulk insert ke database

2. **Auto-Refresh Discovery**
    - Refresh button
    - Auto-refresh timer (optional)

3. **Multiple Core Router Support**
    - Dropdown pilih router
    - Save router list di database

4. **Enhanced Auto-Detect**
    - Parallel SNMP query untuk speed
    - Online MAC vendor API fallback
    - User-defined vendor mapping

5. **Discovery History**
    - Log discovery sessions
    - View past discoveries
    - Compare changes

---

**END OF DOCUMENT**

Dokumen ini adalah **master plan lengkap** untuk implementasi fitur Discovery Client Devices via MikroTik Core Router. Semua detail teknis, flow, dan setup sudah terdokumentasi.

**Status:** Ready for Implementation  
**Next Step:** Switch ke coding agent untuk mulai implementasi
