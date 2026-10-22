# Summary - Bagian 2: UI & Fungsi Modul Manajemen Alert

## ✅ Apa yang Sudah Dibuat

### **1. Database (Migrations)**

#### File: `database/migrations/2026_10_18_164500_create_alert_settings_table.php`
- Tabel untuk menyimpan konfigurasi Telegram Bot
- Single-row config (hanya 1 konfigurasi aktif)
- Kolom:
  - `bot_token` (encrypted)
  - `chat_id`
  - `is_active` (toggle ON/OFF)
  - `deduplication_ttl` (default: 600 detik)
  - `retry_attempts` (default: 3)
  - `retry_delay` (default: 5 detik)

#### File: `database/migrations/2026_10_18_164501_create_alert_logs_table.php`
- Tabel untuk history alert yang terkirim
- Snapshot data lengkap (device, rule, metrics)
- Relasi nullable (data tetap ada meski device/rule dihapus)
- Status: pending, sent, failed
- Retry tracking
- Index untuk performa query report

---

### **2. Models (Eloquent)**

#### File: `app/Models/AlertSetting.php`
- Model untuk konfigurasi alert
- Auto encrypt/decrypt bot_token
- Static method `getConfig()` untuk singleton pattern
- Static method `isActive()` untuk cek status alert

#### File: `app/Models/AlertLog.php`
- Model untuk history alert
- Cast tipe data (datetime, array, decimal)
- Accessor `detected_at_formatted` (format Indonesia)
- Accessor `sent_at_formatted`
- Scope `dateRange()`, `sent()`, `failed()`
- Relasi ke Device, Rule, IssueLog

---

### **3. Service Layer**

#### File: `app/Services/AlertService.php`
- **Method `sendAlert()`**: Kirim alert otomatis saat gangguan terdeteksi
- **Method `formatMessage()`**: Format pesan sesuai template custom
  - Heading bold: `*R01 - Bandwidth Overload*`
  - Info device: nama pelanggan, IP, lokasi
  - Metrics: hanya yang relevan dengan rule
  - Waktu: format Indonesia lengkap
- **Method `sendToTelegram()`**: Kirim ke Telegram API dengan retry
  - Retry 3x dengan delay 5 detik
  - Update status: pending → sent/failed
  - Log error detail
- **Method `testConnection()`**: Validasi bot token
- **Method `sendTestAlert()`**: Kirim pesan test
- **Method `resendAlert()`**: Kirim ulang alert yang failed

---

### **4. Controller**

#### File: `app/Http/Controllers/AlertController.php`
- **Method `index()`**: Halaman utama
  - Form konfigurasi
  - History alert logs (paginated)
  - Filter & search
  - Statistik hari ini
- **Method `store()`**: Simpan/update konfigurasi
- **Method `toggle()`**: Toggle ON/OFF alert via AJAX
- **Method `testConnection()`**: Test bot token via AJAX
- **Method `sendTestAlert()`**: Kirim test alert via AJAX
- **Method `resend()`**: Kirim ulang alert yang failed
- **Method `show()`**: Detail alert log (untuk modal)

---

### **5. Routes**

#### File: `routes/web.php`
Tambahan routes:
```php
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
Route::post('/alerts/toggle', [AlertController::class, 'toggle'])->name('alerts.toggle');
Route::post('/alerts/test-connection', [AlertController::class, 'testConnection'])->name('alerts.test-connection');
Route::post('/alerts/test-alert', [AlertController::class, 'sendTestAlert'])->name('alerts.test-alert');
Route::post('/alerts/{alertLog}/resend', [AlertController::class, 'resend'])->name('alerts.resend');
Route::get('/alerts/{alertLog}', [AlertController::class, 'show'])->name('alerts.show');
```

---

### **6. Vue Component (UI)**

#### File: `resources/js/Pages/Alerts/Index.vue`

**Fitur:**
- Form konfigurasi dengan validasi
- Toggle show/hide bot token (👁️ button)
- Button "Test Koneksi" (validasi bot token)
- Button "Kirim Test Alert" (validasi chat ID)
- Button "Simpan Konfigurasi"
- Toggle switch ON/OFF alert (real-time AJAX)
- History alert logs dengan:
  - Filter: search, status, tanggal
  - Tabel dengan pagination
  - Badge status (sent/failed/pending)
  - Retry count indicator
  - Button "Detail" (👁️) → modal
  - Button "Resend" (🔄) untuk alert failed
- Statistik hari ini (total, sent, failed)
- Modal detail alert (lengkap)

**Styling:**
- Konsisten dengan design system existing
- Responsive
- Loading state untuk async action
- Error handling user-friendly

---

### **7. Navigation Update**

#### File: `resources/js/Pages/Layouts/AppLayout.vue`
- Tambah menu "Manajemen Alert" di sidebar
- Active state detection

---

### **8. Integration**

#### File: `app/Services/ForwardChainingService.php`
- Hook ke AlertService setelah `tryAlert()` return true
- Try-catch untuk error handling
- Log success/error pengiriman alert
- **Flow:**
  1. Rule match detected
  2. saveIssueLog() → UPSERT
  3. tryAlert() → cek cache deduplikasi
  4. Jika TRUE → **AlertService::sendAlert()**
  5. Alert terkirim ke Telegram + tersimpan di alert_logs

---

## 🎯 Cara Menggunakan (Quick Start)

### **Setup Awal:**

1. **Run Migration:**
   ```bash
   php artisan migrate
   ```

2. **Build Assets (jika perlu):**
   ```bash
   npm run build
   ```

3. **Akses Halaman:**
   ```
   http://localhost/alerts
   ```

4. **Input Konfigurasi:**
   - Bot Token: dari @BotFather
   - Chat ID: dari grup Telegram

5. **Test Koneksi & Test Alert**

6. **Simpan Konfigurasi**

7. **Aktifkan Toggle Alert**

8. **Done!** Alert otomatis terkirim saat gangguan terdeteksi.

---

## 📊 Flow Lengkap Alert Otomatis

```
MonitoringWorker (30 detik)
  → ForwardChainingService::evaluateAllRules()
    → Rule MATCH detected
      → saveIssueLog() (UPSERT ke issue_logs)
      → tryAlert() (deduplikasi cache)
        → return TRUE (belum ada dalam TTL)
          
          ✅ AlertService::sendAlert()
            ├─ 1. Cek alert aktif (is_active = true)
            ├─ 2. Format pesan (custom template)
            ├─ 3. Buat alert_log (status: pending)
            ├─ 4. Kirim ke Telegram API
            │    ├─ Retry 1: Success → status = sent ✅
            │    ├─ Retry 2: Failed → tunggu 5 detik
            │    └─ Retry 3: Failed → status = failed ❌
            └─ 5. Return success/fail

        → return FALSE (masih dalam TTL)
          ⏭️ Skip, tidak kirim alert
```

---

## 🔐 Security Features

1. **Bot Token Encryption**
   - Otomatis encrypted saat disimpan
   - Otomatis decrypted saat diambil
   - Menggunakan Laravel Crypt (AES-256)

2. **Input Validation**
   - Required fields
   - Format validation (numeric, range)
   - XSS protection (Laravel escape)

3. **Rate Limiting** (Recommended - belum implemented)
   - Limit test alert: 3x per menit
   - Limit toggle: 10x per menit

---

## 📈 Monitoring & Logging

### **Log Files:**
- `storage/logs/laravel.log`

### **Log Keywords:**
- `AlertService:` → semua log dari AlertService
- `Alert berhasil terkirim` → success
- `Alert gagal terkirim` → failed
- `Alert deduped` → deduplikasi aktif

### **Database Query:**
```sql
-- Total alert hari ini
SELECT COUNT(*) FROM alert_logs WHERE DATE(detected_at) = CURDATE();

-- Success rate
SELECT 
  status,
  COUNT(*) as total 
FROM alert_logs 
GROUP BY status;
```

---

## 🐛 Known Limitations & Future Improvements

### **Limitations:**
1. Hanya support 1 bot token (single config)
2. Hanya support 1 chat destination
3. Belum ada rate limiting untuk test alert
4. Belum ada email notification sebagai fallback

### **Future Improvements (Bagian 3 & 4):**
1. **Deduplikasi Advanced:**
   - Custom TTL per rule
   - Custom TTL per device
   - Deduplikasi berbasis severity
   
2. **Report PDF:**
   - Export mingguan/bulanan
   - Chart & statistik
   - Email report otomatis

3. **Multi-Channel:**
   - Multiple chat groups
   - Email notification
   - SMS gateway integration

4. **Alert Template:**
   - Custom message template per rule
   - Variable interpolation
   - Conditional formatting

---

## ✅ Testing Checklist

Pastikan semua test ini PASS:

- [ ] Migration berhasil (2 tabel baru: alert_settings, alert_logs)
- [ ] Halaman /alerts bisa diakses
- [ ] Form konfigurasi bisa diisi
- [ ] Test Koneksi berhasil (bot token valid)
- [ ] Test Alert berhasil (pesan muncul di Telegram)
- [ ] Simpan konfigurasi berhasil
- [ ] Toggle alert berfungsi (ON/OFF)
- [ ] Alert otomatis terkirim saat gangguan (end-to-end)
- [ ] Deduplikasi berfungsi (alert tidak duplikat dalam TTL)
- [ ] Retry mechanism berfungsi (3x retry jika error)
- [ ] History alert tercatat dengan benar
- [ ] Filter & search berfungsi
- [ ] Resend alert (failed) berfungsi
- [ ] Modal detail berfungsi

---

## 📚 File yang Dibuat/Diubah

### **Dibuat (9 files):**
1. `database/migrations/2026_10_18_164500_create_alert_settings_table.php`
2. `database/migrations/2026_10_18_164501_create_alert_logs_table.php`
3. `app/Models/AlertSetting.php`
4. `app/Models/AlertLog.php`
5. `app/Services/AlertService.php`
6. `app/Http/Controllers/AlertController.php`
7. `resources/js/Pages/Alerts/Index.vue`
8. `TESTING-ALERT.md` (dokumentasi testing)
9. `ALERT-SUMMARY.md` (file ini)

### **Diubah (3 files):**
1. `routes/web.php` → tambah 7 routes alert
2. `resources/js/Pages/Layouts/AppLayout.vue` → tambah menu sidebar
3. `app/Services/ForwardChainingService.php` → integrasi AlertService

---

## 🎉 Status: BAGIAN 2 SELESAI

Semua fitur UI dan fungsi integrasi Telegram sudah selesai diimplementasikan.

**Next:** 
- Bagian 3: Deduplikasi & Cooldown (Deep Dive)
- Bagian 4: Fitur Report PDF

Silakan test implementasi ini dan beri feedback jika ada yang perlu diperbaiki atau ditambahkan! 🚀
