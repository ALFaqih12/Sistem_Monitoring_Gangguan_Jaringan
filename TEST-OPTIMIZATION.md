# Testing Checklist - Optimasi Scrape Interval

## Pre-Test Setup (Lakukan Sekarang)

### 1. Backup Konfigurasi Lama

- [x] MonitoringWorker.php sudah diupdate (60s → 30s)
- [x] Verify Prometheus running: docker ps | grep prometheus

### 2. Restart Monitoring Worker

- [x] Buka dashboard: http://localhost/
- [x] Toggle monitoring OFF (jika sedang ON)
- [x] Tunggu 5 detik
- [x] Toggle monitoring ON
- [x] Verify PID file created: storage/app/monitoring-worker.pid

---

## Test Phase 1: Immediate (0-30 menit pertama)

### Scrape Verification

- [x] Buka Prometheus: http://localhost:9090/targets
- [x] Verify SNMP job scraping setiap 30s (lihat "Last Scrape")
- [x] Verify Ping job scraping setiap 10s
- [x] Check error count = 0 pada semua targets

### Worker Verification

- [x] Check log: storage/logs/monitoring-worker.log
- [x] Verify interval "Menunggu 30 detik..." (bukan 60)
- [x] Verify execution time < 5 detik
- [x] Verify tidak ada error PHP

### Dashboard Check

- [x] Dashboard load < 2 detik
- [x] Card "Total Target Device" tampil
- [x] Card "Total Rule" tampil
- [x] Toggle monitoring working (ON/OFF)

---

## Test Phase 2: Short Term (1-2 jam)

### Performance Monitoring

- [ ] CPU usage Prometheus < 30%
- [ ] RAM usage Prometheus < 1GB
- [ ] CPU usage PHP worker < 20%
- [ ] No timeout errors di log

### Data Collection

- [ ] Query di Prometheus: up{job="snmp"}
    - Result: Harus ada data point setiap 30s
- [ ] Query: probe_success{job="ping"}
    - Result: Harus ada data point setiap 10s
- [ ] Check Prometheus storage: docker exec prometheus du -sh /prometheus
    - Expected: < 100MB untuk 2 jam

### Functional Test

- [ ] Tambah 1 device baru via dashboard
- [ ] Tambah 1 rule baru
- [ ] Verify rule evaluation berjalan setiap 30s (cek log)
- [ ] Trigger gangguan simulasi (jika ada device test)
- [ ] Verify detection time < 1 menit

---

## Test Phase 3: Medium Term (24 jam)

### Stability Check

- [ ] Worker masih running (check PID file)
- [ ] No memory leak (RAM usage stabil)
- [ ] No excessive log file (< 10MB/day)
- [ ] Prometheus storage growth: ~75MB/day

### Error Rate Monitoring

- [ ] SNMP timeout rate < 5%
    - Check: Prometheus → Status → Targets → Count failures
- [ ] Worker execution time average < 10 detik
    - Check: grep "Execution time" storage/logs/monitoring-worker.log
- [ ] No "too slow" warnings di log
- [ ] No database deadlock errors

### Data Quality

- [ ] Bandwidth data tidak ada gap > 1 menit
- [ ] Latency data tidak ada gap > 30 detik
- [ ] Packet loss calculation normal (0-100%)
- [ ] Forward chaining detection accurate

---

## Rollback Criteria (Jika Salah Satu Terjadi)

⚠️ **ROLLBACK ke 60s interval jika:**

1. SNMP timeout rate > 10%
2. Worker execution time > 20 detik consistently
3. Prometheus CPU usage > 60%
4. Device complain/overload (CPU spike di router)
5. Storage growth > 200MB/day
6. Memory leak detected (RAM +100MB/hour)

### Rollback Steps:

1. Edit MonitoringWorker.php: 30 → 60
2. Edit prometheus.yml: 30s → 60s, 10s → 15s
3. Restart Prometheus
4. Restart Worker (toggle OFF → ON)

---

## Success Criteria

✅ **Optimasi dianggap sukses jika dalam 24 jam:**

1. SNMP timeout rate < 5%
2. Worker execution time < 15 detik
3. Detection time: 30-60 detik (tested dengan simulasi)
4. No critical errors di log
5. Prometheus storage < 100MB/day
6. CPU/RAM usage normal
7. User tidak complain (jika production)

---

## Monitoring Commands (Copy-Paste)

### Check Prometheus Targets

\\\ash

# Browser

http://localhost:9090/targets
\\\

### Check Worker Log

\\\ash
Get-Content storage/logs/monitoring-worker.log -Tail 50
\\\

### Check Worker Execution Time

\\\ash
Select-String -Path storage/logs/monitoring-worker.log -Pattern "Execution time" | Select-Object -Last 10
\\\

### Check Prometheus Storage

\\\ash
docker exec prometheus du -sh /prometheus
\\\

### Check Worker PID

\\\ash
Get-Content storage/app/monitoring-worker.pid -ErrorAction SilentlyContinue
\\\

### Check SNMP Timeout Rate

\\\promql

# Paste di Prometheus query:

rate(up{job="snmp"}[5m]) < 1
\\\

---

## Notes

- **Test ini harus dilakukan SEBELUM production deployment**
- **Monitor aktif selama 24 jam pertama**
- **Jangan ragu rollback jika ada masalah**
- **Backup data sebelum test (optional tapi recommended)**

---

Tanggal Test: 2026-08-08
Tester: **\*\***\_\_\_\_**\*\***
Status: [ ] In Progress [ ] Success [ ] Rollback
