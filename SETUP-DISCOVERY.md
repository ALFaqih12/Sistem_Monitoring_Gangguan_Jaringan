# Setup Guide: Discovery Client Devices via MikroTik

## Prerequisite

### 1. Install RouterOS API Library (SUDAH TERINSTALL)

```bash
composer require evilfreelancer/routeros-api-php
```

### 2. Setup MikroTik Core Router

#### Enable API Service

Login ke MikroTik via SSH atau Winbox Terminal:

```bash
# Enable API Service (port 8728)
/ip service enable api
/ip service set api port=8728
/ip service set api address=0.0.0.0/0

# Verifikasi API sudah enable
/ip service print
```

#### Create API User (Read-Only)

```bash
# Create user khusus untuk API monitoring
/user add name=api-monitoring password=StrongPassword123! group=read

# Verifikasi user sudah dibuat
/user print
```

**PENTING:** User dengan group=read hanya bisa membaca data, tidak bisa mengubah konfigurasi MikroTik.

#### (Opsional) Firewall Rule

Jika firewall MikroTik strict, tambahkan rule untuk allow API port dari server monitoring:

```bash
/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=SERVER_IP action=accept comment="Allow API from monitoring server"
```

Ganti `SERVER_IP` dengan IP server monitoring Anda.

### 3. Test Connection dari Server

```bash
# Test ping
ping 192.168.1.1

# Test telnet ke API port
telnet 192.168.1.1 8728
```

## Environment Configuration

File `.env` sudah dikonfigurasi dengan variable berikut:

```env
# SNMP Community String
SNMP_COMMUNITY_STRING=public
SNMP_TIMEOUT_MICROSECONDS=3000000

# MikroTik Core Router Settings
MIKROTIK_CORE_IP=
MIKROTIK_API_PORT=8728
MIKROTIK_API_SSL=false
```

## Build Frontend

Setelah semua file dibuat, build frontend:

```bash
npm run build
```

## Usage

1. Buka halaman **Manajemen Target Device**
2. Klik tab **"Discovery via Core Router"**
3. Input credentials MikroTik:
   - IP Core Router: `192.168.1.1`
   - Username: `api-monitoring`
   - Password: `StrongPassword123!`
4. Klik **"Test Connection"** untuk validasi
5. Jika berhasil, klik **"Connect & Discover"**
6. Sistem akan menampilkan list client dari DHCP leases
7. Klik **"+ Add"** pada client yang ingin ditambahkan
8. Input identitas pelanggan (Nama, Tanggal Pemasangan, Maps URL)
9. Klik **"Simpan"**
10. Device akan otomatis masuk monitoring

## Features

- Auto-detect device model via SNMP (priority) atau MAC lookup (fallback)
- Filter device yang sudah registered (tidak tampil duplikat)
- Search/filter di discovery list
- Auto-generate customer ID
- Auto-add ke Prometheus targets (SNMP + Blackbox)
- Secure: credentials tidak disimpan, hanya untuk satu kali query

## Troubleshooting

### Connection Refused

```bash
# Cek apakah API service running
/ip service print

# Enable jika disabled
/ip service enable api
```

### Authentication Failed

```bash
# Reset password jika lupa
/user set api-monitoring password=NewPassword123!
```

### DHCP Leases Empty

```bash
# Cek DHCP leases di MikroTik
/ip dhcp-server lease print

# Pastikan status = "bound"
```

### SNMP Extension Not Found

Pastikan extension SNMP PHP sudah aktif:

```bash
# Windows (php.ini)
extension=snmp

# Restart web server setelah enable
```

## Security Notes

1. **Credentials tidak disimpan** - User input setiap kali connect
2. **API User read-only** - Tidak bisa ubah config MikroTik
3. **Input validation** - Semua input divalidasi di backend
4. **SNMP timeout configurable** - Prevent DoS via .env

## Technical Details

**Backend:**
- `MikroTikService.php` - RouterOS API client wrapper
- `MacVendorService.php` - MAC to Vendor lookup (offline database)
- `DeviceDiscoveryService.php` - Discovery orchestrator

**Frontend:**
- Tab switching: Manual Add vs Discovery
- Test connection before discovery
- Real-time discovery with loading indicator
- Modal form for add client

**Routes:**
- `POST /devices/test-core-router` - Test connection
- `POST /devices/connect-core-router` - Discovery DHCP leases
- `POST /devices/add-client` - Add client from discovery
