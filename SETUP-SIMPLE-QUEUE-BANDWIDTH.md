# Setup Bandwidth Detection via MikroTik Simple Queue

Dokumentasi ini menjelaskan cara setup bandwidth monitoring untuk client devices dari Discovery menggunakan MikroTik Simple Queue counter.

## Background

**Problem:** Client device dari discovery (VPCS, IoT) tidak support SNMP, sehingga bandwidth tidak bisa di-query langsung seperti manual device.

**Solution:** Query bandwidth per IP client dari **MikroTik Core Router** via SNMP OID Simple Queue table.

## Arsitektur Query Bandwidth

### Device Manual (Existing Method)
```
[Device SNMP-enabled] ← SNMP Query ← [SNMP Exporter] ← [Prometheus] ← [Laravel]
Query: ifHCInOctets{instance="192.168.1.100"}
```

### Device Discovery (New Method - Simple Queue)
```
[Client 192.168.1.10] ──→ Traffic ──→ [MikroTik Core Router] ← SNMP Query ← [SNMP Exporter] ← [Prometheus] ← [Laravel]
                                           │
                                      Simple Queue
                                      (track bandwidth)
                                           
Query: mikrotikQueueBytesIn{instance="192.168.1.1", name="192.168.1.10"}
```

## Cara Kerja

1. **Simple Queue Track Traffic:**
   - MikroTik Simple Queue track semua traffic yang lewat per client IP
   - Counter: `bytes-in` (download) dan `bytes-out` (upload)
   - Counter increment setiap ada packet dari/ke client

2. **SNMP Exporter Scrape:**
   - Query OID Simple Queue table dari Core Router
   - Label metric dengan queue name (IP client)

3. **Laravel Calculate:**
   - Query Prometheus: `rate(mikrotikQueueBytesIn[5m])` untuk download
   - Query Prometheus: `rate(mikrotikQueueBytesOut[5m])` untuk upload
   - Total bandwidth = download + upload (dalam Mbps)

## MikroTik SNMP OID Reference

### OID Table: mtxrQueueSimpleTable
Base OID: `.1.3.6.1.4.1.14988.1.1.2` (MikroTik Enterprise MIB)

| OID | Description | Example Value |
|-----|-------------|---------------|
| `.1.3.6.1.4.1.14988.1.1.2.1.1.2.{index}` | Queue Name | 192.168.1.10 |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.9.{index}` | Bytes In (Download) | 1500000000 |
| `.1.3.6.1.4.1.14988.1.1.2.1.1.10.{index}` | Bytes Out (Upload) | 500000000 |

## Setup Step-by-Step

### 1. Enable SNMP di MikroTik Core Router

```routeros
# Enable SNMP service
/snmp set enabled=yes

# Set community string (default: public)
/snmp community add name=public addresses=0.0.0.0/0 read-access=yes

# Verify SNMP running
/snmp print
```

**Security Best Practice (Production):**
```routeros
# Restrict SNMP hanya ke IP server monitoring
/snmp community add name=public addresses=192.168.1.100/32 read-access=yes
```

### 2. Create Simple Queue per Client IP (MANUAL SETUP)

**PENTING:** Simple Queue harus dibuat **MANUAL** di WinBox untuk setiap client device dari discovery.

#### Via WinBox GUI:

```
1. Buka WinBox → Connect ke Core Router
2. Menu: Queues → Simple Queues
3. Klik tombol [+] Add New
4. Isi form:
   - Name: 192.168.1.10 (IP client, HARUS sama persis dengan IP!)
   - Target: 192.168.1.10/32 (IP client dengan subnet /32)
   - Max Limit: 100M/100M (atau unlimited, terserah kebutuhan)
   - Burst: (optional, bisa dikosongkan)
5. Klik Apply → OK
6. Ulangi untuk setiap client device dari discovery
```

#### Via RouterOS Terminal (Bulk Create):

```routeros
# Create queue per client
/queue simple add name=192.168.1.10 target=192.168.1.10/32 max-limit=100M/100M
/queue simple add name=192.168.1.11 target=192.168.1.11/32 max-limit=100M/100M
/queue simple add name=192.168.1.12 target=192.168.1.12/32 max-limit=100M/100M
```

**Naming Convention (WAJIB):**
- Queue name **HARUS** sama dengan IP client
- Contoh: Client IP `192.168.1.10` → Queue name `192.168.1.10`
- Jika queue name berbeda, bandwidth tidak akan terdeteksi

**Max Limit:**
- Bisa set limit sesuai kebutuhan (misal: 100M/100M)
- Atau unlimited (misal: 10G/10G untuk no throttling)
- Queue hanya untuk tracking, bukan untuk throttling

### 3. Verify Simple Queue Counter

```routeros
# List semua simple queue
/queue simple print

# Check counter untuk queue tertentu
/queue simple print detail where name="192.168.1.10"

# Output:
#  bytes-in=1500000000  (download counter)
#  bytes-out=500000000  (upload counter)
```

Counter akan mulai increment setelah ada traffic dari/ke client.

### 4. Config SNMP Exporter Module (Already Done)

File: `monitoring/snmp_exporter/snmp.yml`

Module `mikrotik_queue` sudah di-configure untuk scrape Simple Queue OID.

### 5. Config Prometheus Scrape Job (Already Done)

File: `monitoring/prometheus/prometheus.yml`

Job `mikrotik_queue` sudah di-configure untuk scrape Core Router.

### 6. Add Core Router IP ke Targets (Already Done)

File: `monitoring/prometheus/mikrotik-targets.json`

Core Router IP `192.168.18.200` sudah ditambahkan.

**Jika Core Router IP berbeda, update file ini:**
```json
[
    {
        "targets": [
            "192.168.1.1"  // Ganti dengan IP Core Router
        ],
        "labels": {
            "instance": "192.168.1.1",
            "job": "mikrotik_queue"
        }
    }
]
```

### 7. Restart Docker Services

```bash
cd C:\laragon\www\monitoring
docker-compose restart prometheus snmp-exporter
```

Tunggu 30 detik agar Prometheus scrape pertama kali.

## Verification & Testing

### 1. Check SNMP Query Manual (Optional)

```bash
# Install snmpwalk (Ubuntu/Debian)
sudo apt-get install snmp snmp-mibs-downloader

# Query Simple Queue table dari MikroTik
snmpwalk -v2c -c public 192.168.1.1 .1.3.6.1.4.1.14988.1.1.2

# Output example:
# .1.3.6.1.4.1.14988.1.1.2.1.1.2.1 = STRING: "192.168.1.10"
# .1.3.6.1.4.1.14988.1.1.2.1.1.9.1 = Counter64: 1500000000
# .1.3.6.1.4.1.14988.1.1.2.1.1.10.1 = Counter64: 500000000
```

### 2. Verify Prometheus Metrics

Buka Prometheus UI: `http://localhost:9090/graph`

**Query 1: Check metric tersedia**
```promql
mikrotikQueueBytesIn
```

**Expected Result:**
```
mikrotikQueueBytesIn{instance="192.168.1.1", name="192.168.1.10"} 1500000000
mikrotikQueueBytesIn{instance="192.168.1.1", name="192.168.1.11"} 2300000000
```

**Query 2: Bandwidth per IP client (Mbps)**
```promql
rate(mikrotikQueueBytesIn{instance="192.168.1.1", name="192.168.1.10"}[5m]) * 8 / 1000000
```

**Query 3: Total bandwidth (download + upload)**
```promql
(rate(mikrotikQueueBytesIn{name="192.168.1.10"}[5m]) + 
 rate(mikrotikQueueBytesOut{name="192.168.1.10"}[5m])) * 8 / 1000000
```

### 3. Check Laravel Log

```bash
# Monitor log Laravel
tail -f storage/logs/laravel.log | grep "Simple Queue"

# Expected output saat Forward Chaining running:
# [debug] ForwardChaining: Metrics via Simple Queue Core Router
# [debug] Bandwidth from Simple Queue for 192.168.1.10: Download=15 Mbps, Upload=5 Mbps, Total=20 Mbps
```

### 4. Test dari Laravel Tinker

```bash
php artisan tinker

>>> $prometheus = app(\App\Services\PrometheusService::class);
>>> $bandwidth = $prometheus->getBandwidthFromSimpleQueue('192.168.1.10', '192.168.1.1');
>>> echo "Bandwidth: {$bandwidth} Mbps";
```

## Workflow User

### Add Client dari Discovery

1. User buka halaman **Manajemen Target Device**
2. Klik tab **"Discovery via Core Router"**
3. Input credentials Core Router → Connect & Discover
4. List client muncul (misal: 192.168.1.10, 192.168.1.11)
5. User klik **"+ Add"** pada client `192.168.1.10`
6. Input identitas pelanggan → Simpan
7. Device tersimpan di database dengan `source='discovery'` dan `core_router_ip='192.168.1.1'`

### Setup Simple Queue Manual

8. User buka **WinBox** → Connect ke Core Router
9. Menu **Queues → Simple Queues** → Klik **[+]**
10. Isi form:
    - Name: `192.168.1.10`
    - Target: `192.168.1.10/32`
    - Max Limit: `100M/100M`
11. Klik **Apply** → **OK**

### System Auto-Detect Bandwidth

12. Prometheus scrape Core Router setiap 30 detik
13. Laravel Forward Chaining query bandwidth client via Simple Queue
14. Bandwidth terdeteksi: 15 Mbps download + 5 Mbps upload = 20 Mbps total
15. Rule evaluate: Jika bandwidth > threshold → Alert

## Troubleshooting

### Issue 1: Bandwidth tetap null untuk discovery device

**Kemungkinan Penyebab:**
- Simple Queue belum dibuat di WinBox
- Queue name tidak sama dengan IP client
- Client belum ada traffic (counter masih 0)
- SNMP belum enable di Core Router

**Fix:**
```routeros
# Check queue exist
/queue simple print where name="192.168.1.10"

# Jika tidak ada, create queue
/queue simple add name=192.168.1.10 target=192.168.1.10/32

# Check counter
/queue simple print detail where name="192.168.1.10"
# Pastikan bytes-in dan bytes-out tidak 0
```

### Issue 2: Metric tidak muncul di Prometheus

**Check:**
1. SNMP service enable: `/snmp print`
2. SNMP Exporter target status UP: `http://localhost:9090/targets`
3. Community string benar (default: public)

**Fix:**
```bash
# Check Prometheus scrape status
curl http://localhost:9090/api/v1/targets | jq '.data.activeTargets[] | select(.job=="mikrotik_queue")'

# Check SNMP Exporter health
curl http://localhost:9116/snmp?target=192.168.1.1&module=mikrotik_queue
```

### Issue 3: Queue name salah

**Symptom:** Bandwidth null meski queue ada dan counter increment

**Root Cause:** Queue name tidak sama persis dengan IP client

**Fix:**
```routeros
# Rename queue
/queue simple set [find name="client-192.168.1.10"] name=192.168.1.10
```

## Device Source Comparison

| Aspek | Manual Device | Discovery Device |
|-------|--------------|------------------|
| **Setup** | Add manual via form | Discovery via Core Router |
| **Device Type** | Router/Switch SNMP | VPCS/IoT non-SNMP |
| **Bandwidth Query** | SNMP langsung ke device | Simple Queue via Core Router |
| **Simple Queue Required** | ❌ No | ✅ Yes (manual setup) |
| **Database Source** | 'manual' | 'discovery' |
| **Core Router IP** | NULL | IP Core Router |

## References

- MikroTik Simple Queue: https://help.mikrotik.com/docs/display/ROS/Queues
- MikroTik SNMP OID: https://help.mikrotik.com/docs/display/ROS/SNMP
- Prometheus SNMP Exporter: https://github.com/prometheus/snmp_exporter
- MikroTik Enterprise MIB: https://download.mikrotik.com/routeros/7.15/mikrotik.mib

## Important Notes

1. **Simple Queue Manual Setup:** Queue HARUS dibuat manual per client di WinBox. Laravel tidak auto-create queue.

2. **Queue Naming:** Queue name HARUS sama dengan IP client. Format: `192.168.1.10`, bukan `client-192.168.1.10` atau format lain.

3. **Traffic Requirement:** Counter hanya update jika ada traffic. Client idle = bandwidth 0 atau null.

4. **Max Limit:** Bisa set limit atau unlimited. Queue untuk tracking, bukan throttling.

5. **Security:** Production gunakan SNMP v3 atau restrict community string ke IP server monitoring saja.

6. **Performance:** Simple Queue ringan, tidak impact performa router. Aman untuk 100+ queue.

## Summary

**Tech Stack:** SNMP only (no additional library)

**User Action:**
- Create Simple Queue manual per client di WinBox
- Queue name = IP client
- Target = IP client/32

**System Action:**
- Scrape metrics dari Core Router
- Query bandwidth per client IP (dynamic)
- Calculate total bandwidth (upload + download)
- Evaluate Forward Chaining rules

**Advantages:**
- Simple Queue counter persistent & accurate
- Support non-SNMP client devices
- Reuse existing Docker stack (Prometheus + SNMP Exporter)
- No additional library/plugin needed
