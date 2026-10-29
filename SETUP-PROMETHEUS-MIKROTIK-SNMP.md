# Setup Prometheus SNMP Exporter untuk MikroTik DHCP Lease Bandwidth Monitoring

Dokumentasi ini menjelaskan cara setup Prometheus SNMP Exporter untuk query bandwidth client dari MikroTik Core Router via DHCP lease counter.

## Background

**Problem:** Client device dari DHCP leases (VPCS) tidak support SNMP, sehingga bandwidth tidak bisa di-query langsung.

**Solution:** Query bandwidth per IP client dari **MikroTik Core Router** via SNMP OID DHCP lease table.

## Arsitektur Query Bandwidth

### Device Manual (Method Lama)
```
[Client Device] ← SNMP Query ← [SNMP Exporter] ← [Prometheus] ← [Laravel]
Query: ifHCInOctets{instance="192.168.1.10"}
```

### Device Discovery (Method Baru)
```
[Client 192.168.1.10] ──┐
[Client 192.168.1.11] ──┼─→ [MikroTik Core Router] ← SNMP Query ← [SNMP Exporter] ← [Prometheus] ← [Laravel]
[Client 192.168.1.12] ──┘
Query: mikrotikDhcpLeaseBytesReceived{instance="CORE_ROUTER_IP", ip="192.168.1.10"}
```

## MikroTik SNMP OID Reference

### OID Table: mtxrDhcpLeaseTable
Base OID: `.1.3.6.1.4.1.14988.1.1.1.2` (MikroTik Enterprise MIB)

| OID | Description | Example Value |
|-----|-------------|---------------|
| `.1.3.6.1.4.1.14988.1.1.1.2.1.3.{index}` | IP Address | 192.168.1.10 |
| `.1.3.6.1.4.1.14988.1.1.1.2.1.2.{index}` | MAC Address | AA:BB:CC:DD:EE:01 |
| `.1.3.6.1.4.1.14988.1.1.1.2.1.10.{index}` | Bytes Received (Download) | 1500000000 |
| `.1.3.6.1.4.1.14988.1.1.1.2.1.11.{index}` | Bytes Sent (Upload) | 500000000 |

### Enable SNMP di MikroTik

```bash
# 1. Enable SNMP service
/snmp set enabled=yes

# 2. Set SNMP community string (default: public)
/snmp community add name=public addresses=0.0.0.0/0 read-access=yes

# 3. Verify SNMP running
/snmp print
```

**Security Note:** Untuk production, batasi `addresses` hanya ke IP server monitoring.

## Prometheus SNMP Exporter Configuration

### 1. Install SNMP Exporter (Docker)

File: `docker-compose.yml` (tambahkan service ini)

```yaml
services:
  snmp-exporter:
    image: prom/snmp-exporter:latest
    container_name: snmp-exporter
    ports:
      - "9116:9116"
    volumes:
      - ./snmp.yml:/etc/snmp_exporter/snmp.yml
    command:
      - '--config.file=/etc/snmp_exporter/snmp.yml'
    restart: unless-stopped
```

### 2. Configure SNMP Exporter Module

File: `prometheus/snmp.yml`

```yaml
# MikroTik module untuk query DHCP lease table
mikrotik:
  walk:
    # DHCP Lease IP Address
    - 1.3.6.1.4.1.14988.1.1.1.2.1.3
    # DHCP Lease MAC Address
    - 1.3.6.1.4.1.14988.1.1.1.2.1.2
    # DHCP Lease Bytes Received
    - 1.3.6.1.4.1.14988.1.1.1.2.1.10
    # DHCP Lease Bytes Sent
    - 1.3.6.1.4.1.14988.1.1.1.2.1.11
  
  lookups:
    # Index by IP address untuk label
    - source_indexes: [mtxrDhcpLeaseAddress]
      lookup: mtxrDhcpLeaseAddress
      drop_source_indexes: false
  
  overrides:
    # Override OID names untuk readability
    mtxrDhcpLeaseAddress:
      ignore: false
      type: DisplayString
    mtxrDhcpLeaseMacAddress:
      ignore: false
      type: PhysAddress48
    mtxrDhcpLeaseBytesReceived:
      ignore: false
      type: counter
    mtxrDhcpLeaseBytesSent:
      ignore: false
      type: counter

  version: 2  # SNMP version
  auth:
    community: public
```

### 3. Configure Prometheus Scrape Job

File: `prometheus/prometheus.yml`

```yaml
scrape_configs:
  # Existing jobs...
  
  # MikroTik SNMP scrape (DHCP lease counter)
  - job_name: 'mikrotik-snmp'
    static_configs:
      - targets:
          - 192.168.1.1  # IP MikroTik Core Router (sesuaikan!)
    metrics_path: /snmp
    params:
      module: [mikrotik]  # Gunakan module mikrotik dari snmp.yml
    relabel_configs:
      - source_labels: [__address__]
        target_label: __param_target
      - source_labels: [__param_target]
        target_label: instance
      - target_label: __address__
        replacement: snmp-exporter:9116  # Alamat SNMP exporter
```

### 4. Restart Services

```bash
# Restart SNMP Exporter dan Prometheus
docker-compose restart snmp-exporter prometheus

# Verify SNMP Exporter running
curl http://localhost:9116/metrics

# Verify Prometheus targets
# Buka: http://localhost:9090/targets
# Cek status job 'mikrotik-snmp' harus UP
```

## Verifikasi & Testing

### 1. Test SNMP Query Manual (Optional)

```bash
# Install snmpwalk (Ubuntu/Debian)
sudo apt-get install snmp snmp-mibs-downloader

# Query DHCP lease table dari MikroTik
snmpwalk -v2c -c public 192.168.1.1 .1.3.6.1.4.1.14988.1.1.1.2

# Output example:
# .1.3.6.1.4.1.14988.1.1.1.2.1.3.1 = STRING: "192.168.1.10"
# .1.3.6.1.4.1.14988.1.1.1.2.1.10.1 = Counter64: 1500000000
# .1.3.6.1.4.1.14988.1.1.1.2.1.11.1 = Counter64: 500000000
```

### 2. Verify Prometheus Metrics

Buka Prometheus UI: `http://localhost:9090/graph`

**Query 1: Check metric tersedia**
```promql
mikrotikDhcpLeaseBytesReceived
```

**Expected Result:**
```
mikrotikDhcpLeaseBytesReceived{instance="192.168.1.1", ip="192.168.1.10", mac="AA:BB:CC:DD:EE:01"} 1500000000
mikrotikDhcpLeaseBytesReceived{instance="192.168.1.1", ip="192.168.1.11", mac="AA:BB:CC:DD:EE:02"} 2300000000
```

**Query 2: Bandwidth per IP client (Mbps)**
```promql
rate(mikrotikDhcpLeaseBytesReceived{instance="192.168.1.1", ip="192.168.1.10"}[5m]) * 8 / 1000000
```

**Query 3: Total bandwidth (download + upload)**
```promql
(rate(mikrotikDhcpLeaseBytesReceived{ip="192.168.1.10"}[5m]) + 
 rate(mikrotikDhcpLeaseBytesSent{ip="192.168.1.10"}[5m])) * 8 / 1000000
```

### 3. Test dari Laravel

```bash
# Run monitoring worker
php artisan monitoring:worker

# Atau manual test query
php artisan tinker
>>> $prometheus = app(\App\Services\PrometheusService::class);
>>> $bandwidth = $prometheus->getBandwidthFromCoreRouter('192.168.1.10', '192.168.1.1');
>>> echo "Bandwidth: {$bandwidth} Mbps";
```

## Troubleshooting

### Issue 1: Metric tidak muncul di Prometheus

**Check:**
1. SNMP service enable di MikroTik: `/snmp print`
2. Community string benar (default: public)
3. Firewall allow SNMP (UDP port 161) dari Prometheus server
4. SNMP Exporter target status UP di Prometheus: `http://localhost:9090/targets`

**Fix:**
```bash
# MikroTik: Allow SNMP dari IP server
/ip firewall filter add chain=input protocol=udp dst-port=161 src-address=SERVER_IP action=accept
```

### Issue 2: Bandwidth tetap null di Laravel

**Check:**
1. Device `source` = 'discovery' dan `core_router_ip` tidak null di database
2. Metric name di Prometheus sesuai dengan query di `PrometheusService.php`
3. Label `instance` dan `ip` sesuai format

**Debug:**
```bash
# Check log Laravel
tail -f storage/logs/laravel.log | grep "Bandwidth from Core Router"

# Check metric name exact di Prometheus
curl -s http://localhost:9090/api/v1/label/__name__/values | grep -i dhcp
```

### Issue 3: OID tidak support atau metric format salah

**Solusi:** Download MikroTik MIB file dan generate SNMP exporter config

```bash
# 1. Download MikroTik MIB
wget https://download.mikrotik.com/routeros/7.15/mikrotik.mib

# 2. Generate snmp.yml dari MIB (advanced)
# Gunakan snmp_exporter generator
# Ref: https://github.com/prometheus/snmp_exporter/tree/main/generator
```

## Expected Behavior After Setup

### Manual Add Device
```
User → Add Device Manual → Input IP → Connect (SNMP) → Save
Device: source = 'manual', core_router_ip = NULL
Bandwidth Query: ifHCInOctets{instance="192.168.1.10"} ← SNMP langsung ke device
```

### Discovery Add Device
```
User → Discovery via Core Router → Connect MikroTik → List Clients → Add Client
Device: source = 'discovery', core_router_ip = '192.168.1.1'
Bandwidth Query: mikrotikDhcpLeaseBytesReceived{instance="192.168.1.1", ip="192.168.1.10"}
```

### Forward Chaining Evaluation
```
ForwardChainingService evaluasi device:
- Device source = 'manual' → PrometheusService::getBandwidth() → Query device langsung
- Device source = 'discovery' → PrometheusService::getBandwidthFromCoreRouter() → Query Core Router

Rule: Bandwidth > 10 Mbps
Device 192.168.1.10 (discovery): Bandwidth 15 Mbps → MATCH ✅
Device 192.168.1.11 (discovery): Bandwidth 8 Mbps → NOT MATCH ❌
```

## References

- MikroTik SNMP OID Documentation: https://help.mikrotik.com/docs/display/ROS/SNMP
- Prometheus SNMP Exporter: https://github.com/prometheus/snmp_exporter
- MikroTik Enterprise MIB: https://download.mikrotik.com/routeros/7.15/mikrotik.mib

## Catatan Penting

1. **Metric Name:** Nama metric `mikrotikDhcpLeaseBytesReceived` dan `mikrotikDhcpLeaseBytesSent` harus sesuai dengan konfigurasi SNMP exporter. Jika berbeda, update di `PrometheusService.php`.

2. **Core Router IP Dinamis:** IP Core Router disimpan per device (kolom `core_router_ip`), sehingga support multiple Core Router atau topologi kompleks.

3. **DHCP Lease Active:** Counter hanya tersedia untuk DHCP lease yang status "bound". Jika client disconnect, metric akan hilang.

4. **Performance:** Query SNMP ke Core Router lebih efisien daripada query per device (1 SNMP walk dapat data semua client).

5. **Security:** Production environment sebaiknya pakai SNMP v3 (auth + encryption) daripada v2c (plain text community string).
