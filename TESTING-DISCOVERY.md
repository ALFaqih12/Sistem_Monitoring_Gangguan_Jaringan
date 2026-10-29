# Testing Checklist - Discovery Client Devices via MikroTik

## Pre-Testing Setup

### Backend Verification
- [ ] Syntax check semua file PHP baru:
  ```bash
  php -l app/Services/MikroTikService.php
  php -l app/Services/MacVendorService.php
  php -l app/Services/DeviceDiscoveryService.php
  php -l app/Http/Controllers/DeviceController.php
  ```

### Frontend Build
- [ ] Build frontend assets:
  ```bash
  npm run build
  ```
- [ ] Verify no build errors
- [ ] Check browser console for JavaScript errors

### Route Verification
- [ ] Check routes terdaftar:
  ```bash
  php artisan route:list --path=devices
  ```
- [ ] Verify 3 route baru muncul:
  - `POST /devices/test-core-router`
  - `POST /devices/connect-core-router`
  - `POST /devices/add-client`

### Environment Setup
- [ ] Copy environment variables dari `.env.example` ke `.env`
- [ ] Set `MIKROTIK_API_PORT=8728`
- [ ] Set `SNMP_COMMUNITY_STRING=public`

---

## MikroTik Setup (PENTING - Harus Dilakukan Dulu)

### API Service Setup
- [ ] Login ke MikroTik via Winbox atau SSH
- [ ] Enable API service:
  ```bash
  /ip service enable api
  /ip service set api port=8728
  ```
- [ ] Verify API running:
  ```bash
  /ip service print
  ```
  Expected: Status API = enabled, port = 8728

### User API Setup
- [ ] Create API user (read-only):
  ```bash
  /user add name=api-monitoring password=StrongPassword123! group=read
  ```
- [ ] Verify user created:
  ```bash
  /user print
  ```
  Expected: User `api-monitoring` with group=read

### Network Connectivity
- [ ] Test ping dari server ke Core Router:
  ```bash
  ping 192.168.1.1
  ```
- [ ] Test telnet ke API port:
  ```bash
  telnet 192.168.1.1 8728
  ```
  Expected: Connection success (Ctrl+C untuk keluar)

### DHCP Leases Check
- [ ] Verify ada DHCP leases di MikroTik:
  ```bash
  /ip dhcp-server lease print
  ```
  Expected: Minimal ada 1 client dengan status "bound"

---

## Unit Testing - Backend Services

### Test 1: MikroTikService::testConnection()

**Test Case: Valid Credentials**
```bash
php artisan tinker

use App\Services\MikroTikService;
$service = new MikroTikService();
$result = $service->testConnection('192.168.1.1', 'api-monitoring', 'StrongPassword123!');
dd($result);
```
- [ ] Expected: `['success' => true, 'message' => 'Koneksi berhasil...', 'identity' => '...']`

**Test Case: Invalid Credentials**
```bash
php artisan tinker

use App\Services\MikroTikService;
$service = new MikroTikService();
$result = $service->testConnection('192.168.1.1', 'wrong', 'wrong');
dd($result);
```
- [ ] Expected: `['success' => false, 'message' => 'Gagal connect...']`

### Test 2: MikroTikService::getDhcpLeases()

```bash
php artisan tinker

use App\Services\MikroTikService;
$service = new MikroTikService();
$clients = $service->getDhcpLeases('192.168.1.1', 'api-monitoring', 'StrongPassword123!');
dd($clients);
```
- [ ] Expected: Array of clients dengan keys: `ip`, `mac`, `hostname`, `status`
- [ ] Verify jumlah clients sesuai dengan DHCP leases di MikroTik

### Test 3: MacVendorService::lookupVendor()

```bash
php artisan tinker

use App\Services\MacVendorService;
$service = new MacVendorService();

// Test MikroTik MAC
echo $service->lookupVendor('00:0C:42:12:34:56'); // Expected: "MikroTik"

// Test Ubiquiti MAC
echo $service->lookupVendor('DC:2C:6E:12:34:56'); // Expected: "Ubiquiti"

// Test Unknown MAC
echo $service->lookupVendor('FF:FF:FF:12:34:56'); // Expected: "Unknown Device"
```
- [ ] MikroTik MAC return "MikroTik"
- [ ] Ubiquiti MAC return "Ubiquiti"
- [ ] Unknown MAC return "Unknown Device"

### Test 4: DeviceDiscoveryService::discoverClients()

```bash
php artisan tinker

use App\Services\DeviceDiscoveryService;
$service = app(DeviceDiscoveryService::class);
$clients = $service->discoverClients('192.168.1.1', [
    'username' => 'api-monitoring',
    'password' => 'StrongPassword123!',
]);
dd($clients);
```
- [ ] Expected: Array of clients dengan keys: `ip`, `mac`, `hostname`, `status`, `model`
- [ ] Verify `model` terisi (SNMP atau MAC lookup)
- [ ] Verify device yang sudah terdaftar tidak muncul

---

## Integration Testing - Controller Endpoints

### Test 5: POST /devices/test-core-router

**Via Postman/Insomnia:**
```
POST http://localhost/devices/test-core-router
Headers:
  Content-Type: application/json
  X-CSRF-TOKEN: <token dari meta tag>
Body:
{
  "core_router_ip": "192.168.1.1",
  "username": "api-monitoring",
  "password": "StrongPassword123!"
}
```
- [ ] Status: 200 OK
- [ ] Response: `{"success": true, "message": "...", "identity": "..."}`

### Test 6: POST /devices/connect-core-router

```
POST http://localhost/devices/connect-core-router
Headers:
  Content-Type: application/json
  X-CSRF-TOKEN: <token>
Body:
{
  "core_router_ip": "192.168.1.1",
  "username": "api-monitoring",
  "password": "StrongPassword123!"
}
```
- [ ] Status: 200 OK
- [ ] Response: `{"success": true, "clients": [...], "total": N}`
- [ ] Verify `clients` array tidak kosong
- [ ] Verify setiap client punya `ip`, `mac`, `model`

### Test 7: POST /devices/add-client

```
POST http://localhost/devices/add-client
Headers:
  Content-Type: application/json
  X-CSRF-TOKEN: <token>
Body:
{
  "ip_address": "192.168.1.100",
  "device_model": "MikroTik RB750",
  "customer_name": "Test Client",
  "installation_date": "2026-10-24",
  "maps_url": "https://maps.google.com"
}
```
- [ ] Status: 200 OK
- [ ] Response: `{"success": true, "message": "..."}`
- [ ] Verify device masuk database
- [ ] Verify IP masuk Prometheus targets

---

## UI Testing - Frontend

### Test 8: Tab Switching

- [ ] Buka halaman `/devices`
- [ ] Verify ada 2 tabs: "Manual Add Device" dan "Discovery via Core Router"
- [ ] Klik tab "Manual Add Device" → tab active, manual form tampil
- [ ] Klik tab "Discovery via Core Router" → tab active, discovery form tampil

### Test 9: Test Connection

- [ ] Klik tab "Discovery via Core Router"
- [ ] Input IP Core Router: `192.168.1.1`
- [ ] Input Username: `api-monitoring`
- [ ] Input Password: `StrongPassword123!`
- [ ] Klik "Test Connection"
- [ ] Verify loading indicator muncul
- [ ] Verify message success muncul (hijau)
- [ ] Verify button "Connect & Discover" jadi enabled

### Test 10: Connect & Discover

- [ ] Setelah test connection berhasil
- [ ] Klik "Connect & Discover"
- [ ] Verify loading indicator "Discovering..."
- [ ] Verify discovery list muncul dengan data clients
- [ ] Verify kolom: IP Address, Merk Device, MAC Address, Hostname, Status
- [ ] Verify setiap row ada button "+ Add"

### Test 11: Search/Filter Discovery

- [ ] Discovery list tampil dengan 5+ clients
- [ ] Input search: IP address salah satu client
- [ ] Verify hanya 1 client tampil
- [ ] Clear search
- [ ] Verify semua clients tampil lagi
- [ ] Input search: "MikroTik"
- [ ] Verify hanya MikroTik devices tampil

### Test 12: Add Client from Discovery

- [ ] Klik "+ Add" pada salah satu client
- [ ] Verify modal "Tambah Client Device" muncul
- [ ] Verify IP Address readonly dan terisi
- [ ] Verify Merk Device readonly dan terisi
- [ ] Verify ID Pelanggan readonly "Auto-generate"
- [ ] Input Nama Pelanggan: "Test Discovery Client"
- [ ] Input Tanggal Pemasangan: `2026-10-24`
- [ ] Input URL Maps: `https://maps.google.com`
- [ ] Klik "Simpan"
- [ ] Verify loading "Menyimpan..."
- [ ] Verify modal close
- [ ] Verify client hilang dari discovery list
- [ ] Verify success message muncul
- [ ] Klik tab "Manual Add Device"
- [ ] Verify device baru muncul di table

### Test 13: Device Already Registered

- [ ] Tambahkan 1 client via discovery
- [ ] Disconnect dan connect ulang discovery
- [ ] Verify client yang sudah ditambahkan tidak muncul lagi di list

### Test 14: Manual Add Still Works (Regression Test)

- [ ] Klik tab "Manual Add Device"
- [ ] Klik "+ Tambah Device"
- [ ] Input IP, Connect, Input identitas
- [ ] Klik "Simpan Device"
- [ ] Verify device masuk database
- [ ] Verify manual add tidak broken

---

## Error Handling Testing

### Test 15: Wrong Credentials

- [ ] Input IP Core Router benar
- [ ] Input Username: `wrong`
- [ ] Input Password: `wrong`
- [ ] Klik "Test Connection"
- [ ] Verify error message muncul (merah)
- [ ] Verify button "Connect & Discover" tetap disabled

### Test 16: Core Router Unreachable

- [ ] Input IP Core Router: `192.168.99.99` (IP tidak ada)
- [ ] Input credentials benar
- [ ] Klik "Test Connection"
- [ ] Verify error message "Connection timeout" atau similar

### Test 17: No DHCP Leases

- [ ] Connect ke MikroTik yang tidak ada DHCP leases
- [ ] Verify message "Tidak ada client baru ditemukan"

### Test 18: SNMP Extension Not Found

- [ ] Disable extension SNMP di php.ini
- [ ] Restart web server
- [ ] Discovery tetap jalan (fallback ke MAC lookup)
- [ ] Verify device model terdeteksi via MAC vendor
- [ ] Re-enable SNMP extension

---

## Database & Prometheus Verification

### Test 19: Database Integration

```sql
-- Check device added via discovery
SELECT * FROM devices ORDER BY id DESC LIMIT 1;
```
- [ ] Verify customer_id auto-generated (format: CUST-YYYYMM-XXX)
- [ ] Verify ip_address sesuai
- [ ] Verify device_model terisi
- [ ] Verify customer_name sesuai input
- [ ] Verify installation_date sesuai input
- [ ] Verify maps_url sesuai input

### Test 20: Prometheus Targets Integration

**SNMP Targets:**
```bash
cat ../prometheus/targets.json
```
- [ ] Verify IP client yang ditambahkan ada di list

**Blackbox Targets:**
```bash
cat ../prometheus/blackbox-targets.json
```
- [ ] Verify IP client yang ditambahkan ada di list

---

## Performance Testing

### Test 21: Discovery dengan Banyak Clients (50+)

- [ ] MikroTik dengan 50+ DHCP leases
- [ ] Discovery time < 60 detik
- [ ] UI tidak freeze
- [ ] Loading indicator jelas

### Test 22: SNMP Timeout Handling

- [ ] Client device yang SNMP timeout
- [ ] Fallback ke MAC lookup berjalan
- [ ] Discovery tidak crash
- [ ] Device tetap masuk list dengan model dari MAC

---

## Security Testing

### Test 23: Credentials Not Stored

- [ ] Input credentials MikroTik
- [ ] Test connection success
- [ ] Refresh halaman
- [ ] Verify form credentials kosong (tidak tersimpan)

### Test 24: CSRF Protection

- [ ] Request tanpa X-CSRF-TOKEN
- [ ] Verify response: 419 CSRF token mismatch

### Test 25: Input Validation

**Invalid IP:**
```
POST /devices/test-core-router
Body: {"core_router_ip": "999.999.999.999", ...}
```
- [ ] Verify: Validation error

**Missing Fields:**
```
POST /devices/add-client
Body: {"ip_address": "192.168.1.100"}
```
- [ ] Verify: Validation error untuk field required

---

## Final Checklist

### Documentation
- [ ] SETUP-DISCOVERY.md dibaca dan dipahami
- [ ] LOGupdate.md updated dengan detail implementasi

### Code Quality
- [ ] Semua file PHP syntax valid
- [ ] Semua function ada komentar penjelasan
- [ ] No emoji in code (sesuai konvensi)
- [ ] Error handling complete

### Production Ready
- [ ] `.env.example` updated
- [ ] Frontend build success
- [ ] No breaking changes pada existing features
- [ ] Manual add device still works

---

## Bug Report Template

Jika menemukan bug saat testing, report dengan format:

**Bug Title:** [Deskripsi singkat]

**Steps to Reproduce:**
1. ...
2. ...
3. ...

**Expected Result:** ...

**Actual Result:** ...

**Environment:**
- PHP Version: ...
- Browser: ...
- MikroTik RouterOS Version: ...

**Logs:**
```
[Paste error log dari storage/logs/laravel.log]
```

**Screenshots:** [If applicable]
