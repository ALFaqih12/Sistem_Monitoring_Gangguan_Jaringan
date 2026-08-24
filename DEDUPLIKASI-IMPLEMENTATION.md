# Implementasi Deduplikasi Alert - Forward Chaining Service

## Tanggal: 2026-08-08

---

## 🎯 Tujuan

Mencegah **SPAM ALERT** dengan cara:
1. Gangguan yang sama tidak di-alert berulang kali
2. Telegram bot tidak kena rate limit ban
3. Database log tidak membengkak

---

## 🔧 Implementasi

### 1. Mekanisme Deduplikasi

**File:** pp/Services/ForwardChainingService.php

**Cara Kerja:**

`
Gangguan terdeteksi
    ↓
Cek cache: apakah gangguan ini sudah pernah di-alert?
    ↓
├─ YA (cache exists) → SKIP alert (deduplikasi)
└─ TIDAK (cache empty) → KIRIM alert + simpan ke cache
`

**Contoh Timeline:**

`
T=0s    : Bandwidth 192.168.18.200 = 85 Mbps (> 80 Mbps threshold)
          → ALERT TELEGRAM ✅
          → Cache key disimpan dengan TTL 10 menit

T=30s   : Bandwidth masih 85 Mbps
          → Cache key masih ada
          → SKIP ALERT ❌ (deduplikasi)

T=60s   : Bandwidth masih 86 Mbps
          → Cache key masih ada
          → SKIP ALERT ❌ (deduplikasi)

T=5 menit : Bandwidth masih 85 Mbps
          → Cache key masih ada
          → SKIP ALERT ❌ (deduplikasi)

T=10 menit : Bandwidth masih 85 Mbps
          → Cache key EXPIRED
          → ALERT TELEGRAM ✅ (alert lagi setelah 10 menit)
          → Cache key disimpan lagi
`

---

## 🔑 Cache Key Generation

### Format Key:

`
alert_{device_id}_{rule_id}_{metrics_hash}_{time_window}
`

**Contoh:**
`
alert_1_3_a1b2c3d4e5f6_202608081800
`

**Komponen:**

1. **device_id**: ID device di database (1, 2, 3, ...)
2. **rule_id**: ID rule di database (1, 2, 3, ...)
3. **metrics_hash**: MD5 hash dari metrics yang di-bulatkan
4. **time_window**: Timestamp dalam format YmdHHmm dengan pembulatan 5 menit

---

## 📊 Metrics Hash (Toleransi Variance)

**Mengapa perlu hash?**
- Bandwidth 85.1 Mbps vs 85.2 Mbps → dianggap **SAMA** (variance kecil)
- Bandwidth 85 Mbps vs 90 Mbps → dianggap **BEDA** (perubahan signifikan)

**Pembulatan:**

| Parameter | Pembulatan | Contoh |
|-----------|------------|--------|
| Bandwidth | 1 desimal | 85.123 → 85.1 Mbps |
| Latency | Integer | 45.78 → 46 ms |
| Packet Loss | 1 desimal | 3.456 → 3.5% |

**Contoh Hash:**

`php
Bandwidth = 85.1 Mbps
Latency = 46 ms
Packet Loss = 3.5%

String: "85.1_46_3.5"
MD5: a1b2c3d4e5f6...
`

Jika metrics berubah sedikit (85.2, 47, 3.6) → hash SAMA
Jika metrics berubah banyak (90, 50, 5.0) → hash BEDA → ALERT BARU

---

## ⏱️ Time Window (5 Menit Rounding)

**Mengapa perlu time window?**
- Cache cleanup lebih terstruktur
- Key tidak terlalu banyak

**Format:** YmdHHmm dengan pembulatan ke kelipatan 5

**Contoh:**

| Waktu Real | Time Window |
|------------|-------------|
| 18:00 | 202608081800 |
| 18:02 | 202608081800 |
| 18:04 | 202608081800 |
| 18:05 | 202608081805 |
| 18:07 | 202608081805 |
| 18:10 | 202608081810 |

Jadi alert pada jam 18:00 - 18:04 dianggap **satu window yang sama**.

---

## 🔄 TTL (Time-to-Live)

**Default:** 600 detik (10 menit)

**Konfigurasi:** .env

`env
ALERT_DEDUPLICATION_TTL=600
`

**Rekomendasi:**

| TTL | Use Case |
|-----|----------|
| 300 (5 min) | Alert urgent, perlu respon cepat |
| 600 (10 min) | Monitoring normal (RECOMMENDED) |
| 900 (15 min) | Mengurangi alert frequency |
| 1800 (30 min) | Alert tidak terlalu penting |

---

## 📝 Log Behavior

### Debug Log (jika alert di-skip):

`
ForwardChaining: Gangguan terdeteksi tapi SKIP (deduplikasi)
- rule_code: R01
- device_ip: 192.168.18.200
`

### Info Log (jika alert dikirim):

`
ForwardChaining: Gangguan terdeteksi!
- rule_code: R01
- device_ip: 192.168.18.200
- device_name: Pelanggan A
- metrics: [bandwidth: 85.1, latency: 46, packet_loss: 3.5]
`

### Debug Log (mark alerted):

`
ForwardChaining: Alert marked (TTL: 600s)
- cache_key: alert_1_3_a1b2c3...
- device: 192.168.18.200
- rule: R01
`

---

## 🧪 Testing Deduplikasi

### Manual Test:

1. Buat rule dengan threshold rendah (misal: bandwidth > 1 Mbps)
2. Tambahkan device yang aktif
3. Start monitoring worker
4. Lihat log:

`ash
tail -f storage/logs/laravel.log | Select-String "ForwardChaining"
`

**Expected behavior:**

`
[18:00:00] ForwardChaining: Gangguan terdeteksi! (device: 192.168.18.200, rule: R01)
[18:00:30] ForwardChaining: Gangguan terdeteksi tapi SKIP (deduplikasi)
[18:01:00] ForwardChaining: Gangguan terdeteksi tapi SKIP (deduplikasi)
...
[18:10:00] ForwardChaining: Gangguan terdeteksi! (device: 192.168.18.200, rule: R01)
`

### Check Cache:

`ash
php artisan tinker
>>> Cache::get('alert_1_3_a1b2c3d4e5f6_202608081800')
`

---

## 🛡️ Benefits

### Before (Tanpa Deduplikasi):

`
Worker cycle setiap 30 detik × 24 jam = 2,880 cycles/hari
Gangguan persisten 1 jam = 120 alert/gangguan
10 device dengan gangguan = 1,200 alert/hari

❌ Database log: 1,200 rows/hari (36,000/bulan)
❌ Telegram: 1,200 message/hari → BOT BANNED!
`

### After (Dengan Deduplikasi TTL 10 menit):

`
Gangguan persisten 1 jam, alert setiap 10 menit = 6 alert/gangguan
10 device dengan gangguan = 60 alert/hari

✅ Database log: 60 rows/hari (1,800/bulan) → 95% reduction!
✅ Telegram: 60 message/hari → AMAN (rate limit 20/min)
`

---

## ⚠️ Edge Cases

### Case 1: Metrics Berubah Drastis

`
T=0: Bandwidth 85 Mbps → Alert
T=2 min: Bandwidth 95 Mbps → Metrics hash BEDA → Alert baru!
`

✅ Expected behavior: Alert baru karena kondisi memburuk

### Case 2: Gangguan Intermittent

`
T=0: Bandwidth 85 Mbps → Alert
T=5 min: Bandwidth 50 Mbps (normal) → No alert
T=6 min: Bandwidth 85 Mbps lagi → Metrics hash SAMA tapi time window BEDA
`

✅ Expected behavior: SKIP (masih dalam TTL 10 menit)

### Case 3: Cache Flush

`
T=0: Alert sent, cache set
T=5 min: Cache flush (restart Redis/memcached)
T=6 min: Alert akan terkirim lagi (cache hilang)
`

⚠️ Acceptable: Jarang terjadi, dan alert sekali lagi tidak masalah

---

## 🔧 Troubleshooting

### Alert Tidak Terkirim Sama Sekali

**Check:**
1. Cache driver working?
   `ash
   php artisan cache:clear
   php artisan tinker
   >>> Cache::put('test', 'value', 60)
   >>> Cache::get('test')
   `

2. TTL terlalu lama?
   `env
   ALERT_DEDUPLICATION_TTL=600 # Coba turunkan ke 300
   `

### Alert Terlalu Sering (TTL Tidak Efektif)

**Check:**
1. Metrics variance terlalu besar?
   - Cek pembulatan di generateDeduplicationKey()
   - Mungkin perlu bulatkan lebih kasar

2. Time window terlalu pendek?
   - Default: 5 menit rounding
   - Bisa ubah ke 10 menit rounding

---

## 📊 Monitoring

### Metrics to Track:

1. **Alert Sent Rate**: Berapa alert/jam
2. **Alert Skipped Rate**: Berapa kali dedupe terjadi
3. **Cache Hit Rate**: Efektivitas dedupe

### Query Log:

`ash
# Alert sent
grep "Gangguan terdeteksi!" storage/logs/laravel.log | wc -l

# Alert skipped
grep "SKIP (deduplikasi)" storage/logs/laravel.log | wc -l

# Ratio
echo "Skip Rate = (skipped / (sent + skipped)) * 100%"
`

**Expected ratio:** 90-95% skipped (artinya dedupe efektif!)

---

## ✅ Checklist Implementasi

- [x] Update ForwardChainingService.php
- [x] Tambah method shouldAlert()
- [x] Tambah method markAsAlerted()
- [x] Tambah method generateDeduplicationKey()
- [x] Tambah env variable ALERT_DEDUPLICATION_TTL
- [x] Tambah debug logging
- [x] Syntax check passed
- [ ] Manual testing (pending device active)
- [ ] Integration dengan Modul Log Gangguan (next phase)
- [ ] Integration dengan Telegram Alert (next phase)

---

Dibuat: 2026-08-08 18:00 WIB
Update: -

