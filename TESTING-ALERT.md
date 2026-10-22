# Testing Guide - Modul Manajemen Alert

## 📋 Checklist Pre-Testing

Sebelum testing, pastikan:
- [x] Migration sudah dijalankan (`php artisan migrate`)
- [x] Bot Telegram sudah dibuat via @BotFather
- [x] Bot sudah di-invite ke grup dan dijadikan admin
- [x] Bot Token dan Chat ID sudah didapat
- [x] Test manual via Postman/browser sudah berhasil

---

## 🧪 Skenario Testing

### **Test 1: Konfigurasi Alert (Pertama Kali)**

**Langkah:**
1. Akses: `http://localhost/alerts`
2. Form konfigurasi akan kosong (belum ada config)
3. Input:
   - Bot Token: `7123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw` (ganti dengan token Anda)
   - Chat ID: `-1001234567890` (ganti dengan chat ID Anda)
   - Biarkan field lain default
4. Klik **"🔗 Test Koneksi"**

**Expected Result:**
- ✅ Alert muncul: "Koneksi berhasil! Bot ditemukan."
- ✅ Bot info (name, username) ditampilkan

**Jika Gagal:**
- ❌ "Unauthorized" → Bot Token salah, cek lagi di @BotFather
- ❌ Timeout → Koneksi internet bermasalah

---

### **Test 2: Test Alert ke Telegram**

**Langkah:**
1. Masih di halaman yang sama
2. Klik **"🔔 Kirim Test Alert"**

**Expected Result:**
- ✅ Alert muncul: "Test alert berhasil dikirim! Silakan cek grup Telegram Anda."
- ✅ Pesan muncul di grup Telegram:
  ```
  🔔 Test Alert - SIWA Network Monitor
  
  Ini adalah pesan test dari sistem monitoring.
  Jika Anda menerima pesan ini, integrasi Telegram berhasil!
  
  Waktu: Jumat, 18 Oktober 2026 | 22:45:30 WIB
  ```

**Jika Gagal:**
- ❌ "Bad Request: chat not found" → Chat ID salah atau bot belum di-invite ke grup
- ❌ "Bad Request: not enough rights" → Bot belum dijadikan admin grup

---

### **Test 3: Simpan Konfigurasi**

**Langkah:**
1. Setelah test berhasil, klik **"💾 Simpan Konfigurasi"**

**Expected Result:**
- ✅ Halaman refresh
- ✅ Flash message: "Konfigurasi alert berhasil disimpan! Aktifkan toggle untuk mulai menerima alert."
- ✅ Toggle switch "Status Alert" muncul dengan badge **"⚫ Nonaktif"**
- ✅ Form terisi dengan data yang disimpan

---

### **Test 4: Aktifkan Alert System**

**Langkah:**
1. Klik toggle switch "Status Alert"
2. Badge berubah dari **"⚫ Nonaktif"** menjadi **"🟢 Aktif"**

**Expected Result:**
- ✅ Badge berubah warna hijau
- ✅ Alert system sekarang aktif
- ✅ Gangguan yang terdeteksi akan otomatis dikirim ke Telegram

---

### **Test 5: Simulasi Gangguan (End-to-End)**

**Langkah:**
1. Pastikan monitoring worker aktif (Dashboard → Toggle Monitoring ON)
2. Pastikan ada device aktif di Manajemen Target Device
3. Pastikan ada rule aktif di Rule Forward Chaining
4. Tunggu 30 detik (interval worker)
5. Jika ada rule yang match → alert akan otomatis terkirim

**Expected Result:**
- ✅ Pesan alert muncul di grup Telegram:
  ```
  *R01 - Bandwidth Overload*
  
  Nama Pelanggan: PT SIWA Network
  IP: 192.168.1.100
  Lokasi: [Buka Maps](https://maps.google.com/...)
  
  Metric:
  • Bandwidth: 120.5 Mbps (threshold: > 100 Mbps)
  
  Waktu: Jumat, 18 Oktober 2026 | 22:45:30 WIB
  ```
- ✅ Alert tercatat di history (tabel History Alert)
- ✅ Status: **"Terkirim"** dengan badge hijau

**Monitoring Log:**
Cek log Laravel di `storage/logs/laravel.log`:
```
[INFO] ForwardChaining: Gangguan terdeteksi!
[INFO] AlertService: Alert berhasil terkirim ke Telegram.
```

---

### **Test 6: Deduplikasi Alert**

**Langkah:**
1. Biarkan monitoring worker berjalan
2. Rule yang sama match lagi dalam waktu < 10 menit (TTL default)

**Expected Result:**
- ✅ IssueLog tetap di-update (metrics terbaru)
- ❌ Alert **TIDAK** dikirim lagi ke Telegram (deduplikasi)
- ✅ Log: "ForwardChaining: Alert deduped (within TTL)"

**Setelah TTL Expire (> 10 menit):**
- ✅ Alert dikirim lagi ke Telegram (TTL sudah habis)

---

### **Test 7: Retry Mechanism (Simulasi Error)**

**Langkah:**
1. Matikan koneksi internet sebentar
2. Tunggu worker mendeteksi gangguan
3. Alert akan gagal terkirim → retry 3x dengan delay 5 detik
4. Nyalakan koneksi internet sebelum retry ke-3

**Expected Result:**
- ✅ Alert berhasil terkirim setelah retry
- ✅ History alert menampilkan **"Retry: 2x"** (contoh)
- ✅ Status: **"Terkirim"**

**Jika Tetap Gagal (3x retry):**
- ❌ Status: **"Gagal"** dengan badge merah
- ❌ Error message tersimpan: "Max retry reached. Last error: ..."
- ✅ Tombol **"🔄 Kirim Ulang"** muncul di kolom Aksi

---

### **Test 8: Resend Alert yang Failed**

**Langkah:**
1. Cari alert dengan status **"Gagal"** di history
2. Klik tombol **"🔄"** (Kirim Ulang)
3. Confirm dialog

**Expected Result:**
- ✅ Alert dikirim ulang
- ✅ Status berubah jadi **"Terkirim"**
- ✅ Pesan muncul di Telegram

---

### **Test 9: Filter & Search History**

**Langkah:**
1. Gunakan filter:
   - Search: Ketik nama pelanggan / IP / rule code
   - Status: Pilih "Terkirim" / "Gagal"
   - Tanggal: Pilih range tanggal
2. Klik **"🔍 Filter"**

**Expected Result:**
- ✅ Tabel update sesuai filter
- ✅ Pagination tetap berfungsi
- ✅ URL parameter tersimpan (refresh tetap filtered)

---

### **Test 10: Detail Alert Modal**

**Langkah:**
1. Klik tombol **"👁️"** di kolom Aksi
2. Modal detail muncul

**Expected Result:**
- ✅ Modal menampilkan:
  - Informasi Device (nama, IP, model, lokasi)
  - Rule yang Match (code, nama)
  - Pesan yang Dikirim (preview lengkap)
  - Status Pengiriman (waktu, retry count, error jika ada)
- ✅ Klik X atau area luar modal → modal tertutup

---

## 🐛 Troubleshooting

### **Problem 1: Alert tidak terkirim meski monitoring aktif**

**Kemungkinan Penyebab:**
1. Toggle Alert masih **Nonaktif** (⚫)
   - **Fix:** Aktifkan toggle di halaman Manajemen Alert
2. Tidak ada rule yang match
   - **Fix:** Cek rule di Rule Forward Chaining, pastikan threshold sesuai
3. Bot token/chat ID salah
   - **Fix:** Test ulang koneksi, update konfigurasi

**Debug:**
```bash
# Cek log Laravel
tail -f storage/logs/laravel.log | grep "AlertService"
```

---

### **Problem 2: Alert terkirim tapi tidak tercatat di history**

**Kemungkinan Penyebab:**
- Database error saat insert ke `alert_logs`

**Debug:**
```bash
# Cek log error
tail -f storage/logs/laravel.log | grep "ERROR"

# Cek tabel alert_logs
php artisan tinker
>>> App\Models\AlertLog::count();
```

---

### **Problem 3: Telegram error "Unauthorized"**

**Penyebab:**
- Bot token salah atau expired

**Fix:**
1. Cek token di @BotFather: `/mybots` → pilih bot → API Token
2. Update token di form konfigurasi
3. Test koneksi ulang

---

### **Problem 4: Telegram error "Chat not found"**

**Penyebab:**
- Chat ID salah
- Bot belum di-invite ke grup
- Bot sudah di-remove dari grup

**Fix:**
1. Invite ulang bot ke grup
2. Dapatkan Chat ID ulang via @getidsbot
3. Update Chat ID di form konfigurasi

---

### **Problem 5: Alert spam (kirim berulang padahal sama)**

**Kemungkinan Penyebab:**
- Deduplikasi cache tidak berfungsi

**Debug:**
```bash
php artisan tinker
>>> Cache::get('alert_1_1_...');  // Cek apakah cache key ada
```

**Fix:**
- Pastikan cache driver aktif (cek `.env`: `CACHE_DRIVER=file`)
- Cek TTL deduplikasi di konfigurasi (minimal 60 detik)

---

### **Problem 6: Retry tidak jalan**

**Kemungkinan Penyebab:**
- Max retry = 0 atau terlalu kecil

**Fix:**
- Update konfigurasi: Retry Attempts minimal 1, recommended 3

---

## 📊 Monitoring Performance

### **Log yang Harus Dimonitor:**

1. **Alert Success:**
   ```
   [INFO] AlertService: Alert berhasil terkirim ke Telegram.
   ```

2. **Alert Failed:**
   ```
   [ERROR] AlertService: Alert gagal terkirim setelah 3x retry.
   ```

3. **Alert Deduped:**
   ```
   [DEBUG] ForwardChaining: Alert deduped (within TTL)
   ```

### **Metrik Penting:**

Query untuk report:
```sql
-- Total alert hari ini
SELECT COUNT(*) FROM alert_logs WHERE DATE(detected_at) = CURDATE();

-- Success rate
SELECT 
  status,
  COUNT(*) as total,
  ROUND(COUNT(*) * 100 / (SELECT COUNT(*) FROM alert_logs), 2) as percentage
FROM alert_logs
GROUP BY status;

-- Top 5 device bermasalah
SELECT 
  device_name,
  device_ip,
  COUNT(*) as total_alerts
FROM alert_logs
WHERE DATE(detected_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY device_id
ORDER BY total_alerts DESC
LIMIT 5;
```

---

## ✅ Checklist Testing Selesai

Setelah semua test berhasil:
- [x] Konfigurasi tersimpan
- [x] Toggle alert aktif
- [x] Test alert berhasil
- [x] Alert otomatis terkirim saat gangguan
- [x] Deduplikasi berfungsi
- [x] Retry mechanism berfungsi
- [x] History tercatat dengan benar
- [x] Filter & search berfungsi
- [x] Resend alert berfungsi
- [x] Modal detail berfungsi

**Status: BAGIAN 2 SELESAI ✅**

---

## 🎯 Next Steps (Bagian 3 & 4)

Setelah Bagian 2 berhasil, kita akan lanjut ke:

**Bagian 3: Deduplikasi & Cooldown (Deep Dive)**
- Analisis mekanisme deduplikasi yang sudah ada
- Optimasi cache strategy
- Custom cooldown per rule/device
- Anti-spam advanced features

**Bagian 4: Fitur Report PDF**
- Report mingguan/bulanan
- Export ke PDF dengan tabel
- Statistik & chart
- Email report otomatis (opsional)

---

Silakan lanjutkan dengan testing manual. Beri tahu saya jika ada error atau butuh bantuan troubleshooting!
