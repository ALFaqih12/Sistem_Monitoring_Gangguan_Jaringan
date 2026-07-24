# LOG Update Development

## 2026-07-24

### Pembuatan Modul Rule Forward Chaining (Baru)

- Membuat tabel database baru.
    - File: `database/migrations/2026_07_24_122759_create_rules_table.php`.
    - File: `database/migrations/2026_07_24_122800_create_rule_conditions_table.php`.
    - Tabel `rules`: id, code (auto: R01, R02, dst), name, target_type (all/specific), target_device_id (FK ke devices), is_active, timestamps.
    - Tabel `rule_conditions`: id, rule_id (FK ke rules), parameter (bandwidth/latency/packet_loss), operator (>, <, >=, <=, =), value (decimal), timestamps.
    - Kedua tabel menggunakan foreign key dengan cascade delete.

- Membuat Model Eloquent baru.
    - File: `app/Models/Rule.php`.
        - Auto-generate code rule berformat R01, R02, R03, dst.
        - Relasi `targetDevice()` (belongsTo ke Device).
        - Relasi `conditions()` (hasMany ke RuleCondition).
        - Cast `is_active` sebagai boolean.
    - File: `app/Models/RuleCondition.php`.
        - Relasi `rule()` (belongsTo ke Rule).
        - Accessor `unit` untuk mendapatkan satuan parameter (Mbps, ms, %).
        - Accessor `parameter_label` untuk mendapatkan label user-friendly.
        - Cast `value` sebagai decimal.

- Membuat Controller baru.
    - File: `app/Http/Controllers/RuleController.php`.
    - Method `index()`: List rules dengan pagination 15 data dan search server-side, eager loading relasi targetDevice dan conditions.
    - Method `create()`: Menampilkan form tambah rule dengan dropdown device.
    - Method `store()`: Validasi input, simpan rule dan conditions dalam satu transaksi database.
    - Method `edit()`: Menampilkan form edit rule dengan data existing.
    - Method `update()`: Hapus semua kondisi lama, simpan kondisi baru dalam transaksi.
    - Method `toggle()`: AJAX toggle ON/OFF status rule.
    - Method `destroy()`: Hapus rule (conditions otomatis terhapus via cascade).

- Membuat View untuk Rule.
    - File: `resources/views/rules/index.blade.php`.
        - Tabel dengan kolom: Code, Nama Rule, Target Device, Kondisi, Status, Aksi.
        - Toggle switch CSS untuk ON/OFF dengan AJAX.
        - Menampilkan kondisi rule dengan format readable (parameter operator nilai unit).
        - Label "AND" di antara multiple conditions.
        - Badge khusus untuk code rule (biru) dan target device.
        - Pagination 15 data per halaman.
        - Search server-side.
    - File: `resources/views/rules/create.blade.php`.
        - Form dengan input dinamis untuk multiple conditions.
        - Dropdown target device (All Devices / Specific Device).
        - Button "Tambah Kondisi" untuk menambahkan kondisi baru secara dinamis.
        - Button "Hapus" untuk menghapus kondisi.
        - Auto-update satuan unit label (Mbps/ms/%) saat parameter diubah.
    - File: `resources/views/rules/edit.blade.php`.
        - Form edit dengan data conditions existing.
        - Mendukung tambah/hapus kondisi seperti di create.
        - Validasi minimal 1 kondisi.

- Menambahkan route untuk modul Rule.
    - 7 route baru di `routes/web.php`.
    - GET `/rules`, GET `/rules/create`, POST `/rules`, GET `/rules/{rule}/edit`, PUT `/rules/{rule}`, DELETE `/rules/{rule}`, POST `/rules/{rule}/toggle`.

- Memperbarui sidebar layout.
    - File: `resources/views/layouts/app.blade.php`.
    - Menambahkan menu "Rule Forward Chaining" dengan active state detection.
    - Total 15 route terdaftar di sistem.

### Pembuatan Service PrometheusService (Baru)

- File baru: `app/Services/PrometheusService.php`.
- Fungsi `getBandwidth($ipAddress)`: Query bandwidth dari Prometheus menggunakan metric `ifHCInOctets` (SNMP) dengan rate per 5 menit, dikonversi ke Mbps.
- Fungsi `getLatency($ipAddress)`: Query latency menggunakan metric `probe_icmp_duration_seconds` (Blackbox Exporter) dikonversi ke ms. Fallback ke `probe_duration_seconds` jika metric tidak ditemukan.
- Fungsi `getPacketLoss($ipAddress)`: Query packet loss menggunakan rumus `(1 - probe_success) * 100` untuk mendapatkan persentase.
- Fungsi `getAllMetrics($ipAddress)`: Mengambil bandwidth, latency, dan packet loss sekaligus dalam satu panggilan.
- Fungsi `checkConnection()`: Mengecek koneksi ke Prometheus server via endpoint `/-/healthy`.
- Mode simulasi (`PROMETHEUS_SIMULATION_MODE`) untuk testing jika blackbox exporter belum tersedia.
- Menggunakan Laravel HTTP Client dengan timeout 5 detik dan error logging.

### Pembuatan ForwardChainingService (Inference Engine) (Baru)

- File baru: `app/Services/ForwardChainingService.php`.
- Fungsi `evaluateAllRules()`: Evaluasi semua rule aktif terhadap semua device.
    - Mengambil semua rule dengan `is_active = true`.
    - Mengambil semua device dari database.
    - Untuk setiap rule, tentukan device target (all/specific).
    - Untuk setiap device, query metrics dari PrometheusService.
    - Evaluasi kondisi dengan logic AND: IF condition1 AND condition2 AND ... THEN gangguan.
    - Return array hasil deteksi lengkap dengan detail kondisi.
- Fungsi `evaluateRuleForDevice($rule, $device)`: Evaluasi satu rule untuk satu device.
- Fungsi `evaluateCondition($actualValue, $operator, $thresholdValue)`: Evaluasi kondisi berdasarkan operator (>, <, >=, <=, =).
    - Operator `=` menggunakan toleransi float comparison 0.01.
- Fungsi `evaluateSingleRule($ruleId, $deviceId)`: Evaluasi satu rule secara manual untuk testing.
- Logging untuk setiap deteksi gangguan dan error.

### Pembuatan Command Artisan dan Scheduler (Baru)

- File baru: `app/Console/Commands/EvaluateRulesCommand.php`.
    - Signature: `php artisan rules:evaluate`.
    - Menampilkan output detail: jumlah rule dicek, jumlah gangguan terdeteksi, detail kondisi yang match.
    - Return Command::SUCCESS.

- File baru: `app/Console/Commands/TestPrometheusConnection.php`.
    - Signature: `php artisan test:prometheus {--ip=}`.
    - Test koneksi ke Prometheus server.
    - Menampilkan daftar active targets dan health status.
    - Test query bandwidth, latency, packet loss untuk IP tertentu.
    - Memberikan rekomendasi jika semua metrics NULL.

- File baru: `app/Console/Commands/CreateTestData.php`.
    - Signature: `php artisan test:create-data`.
    - Membuat 3 rule testing dengan kondisi berbeda untuk validasi sistem.
    - Rule 1: IF latency > 50 ms THEN gangguan.
    - Rule 2: IF packet_loss > 5% THEN gangguan.
    - Rule 3: IF latency > 100 ms AND packet_loss > 2% THEN gangguan.

- File baru: `app/Console/Commands/CheckBlackboxTargets.php`.
    - Signature: `php artisan blackbox:check`.
    - Mengecek apakah ada target dengan job="ping".
    - Mengecek metrics blackbox yang tersedia (`probe_success`, `probe_duration_seconds`, `probe_icmp_duration_seconds`).
    - Memberikan rekomendasi query PromQL.

- File baru: `app/Console/Commands/SetupBlackboxTargets.php`.
    - Signature: `php artisan blackbox:setup`.
    - Membuat file `blackbox-targets.json` dari IP yang ada di database dan di targets.json.
    - Format JSON sesuai dengan format Prometheus file_sd_configs.

- File baru: `app/Console/Commands/ShowPrometheusMetrics.php`.
    - Signature: `php artisan prometheus:show-metrics {ip}`.
    - Menampilkan semua metrics yang tersedia di Prometheus untuk IP tertentu.
    - Mengelompokkan metrics berdasarkan nama.
    - Menampilkan nilai terkini setiap metric.
    - Memberikan rekomendasi query PromQL berdasarkan metric yang ditemukan.

- Update file `routes/console.php`.
    - Menambahkan scheduler untuk menjalankan `rules:evaluate` setiap 1 menit.
    - Konfigurasi: `withoutOverlapping()`, `runInBackground()`, append output ke `storage/logs/forward-chaining.log`.

### Integrasi Blackbox Exporter ke PrometheusTargetService (Perubahan)

- Memperbarui `app/Services/PrometheusTargetService.php`.
    - Method `addTarget()`: Sekarang menambahkan IP ke SNMP targets (targets.json) DAN Blackbox targets (blackbox-targets.json).
    - Method `removeTarget()`: Sekarang menghapus IP dari SNMP targets DAN Blackbox targets.
    - Method baru `addBlackboxTarget()`: Menambahkan IP ke file blackbox-targets.json dengan label job="ping".
    - Method baru `removeBlackboxTarget()`: Menghapus IP dari file blackbox-targets.json.
    - Method baru `addSnmpTarget()`: Memisahkan logic SNMP dari addTarget.
    - Method baru `removeSnmpTarget()`: Memisahkan logic SNMP dari removeTarget.
    - Parameter `$filePath` pada readTargets dan writeTargets agar bisa digunakan untuk kedua file.
    - Kedua file target SNMP dan Blackbox dikelola secara atomik dalam satu panggilan.

- Membuat file baru: `../prometheus/blackbox-targets.json`.
    - Format JSON array dengan label instance dan job="ping".
    - Target awal: 192.168.18.200 (device yang sudah terdaftar).
    - Auto-update setiap kali user menambah/menghapus device.

### Konfigurasi Prometheus dan Docker (Perubahan)

- Memperbarui `../prometheus/prometheus.yml`.
    - Job "ping" berubah dari `static_configs` menjadi `file_sd_configs` dengan file `/etc/prometheus/blackbox-targets.json`.
    - Refresh interval 5 detik agar perubahan target cepat terdeteksi.
    - Konfigurasi relabeling untuk ICMP probe module.

- Memperbarui `../docker-compose.yaml`.
    - Menambahkan volume mapping untuk `./prometheus/blackbox-targets.json:/etc/prometheus/blackbox-targets.json`.
    - Volume baru diperlukan agar Prometheus bisa membaca file blackbox-targets.json dari dalam container.

- Memperbaiki encoding file `blackbox-targets.json`.
    - File sebelumnya memiliki BOM (Byte Order Mark) yang menyebabkan Prometheus gagal parsing JSON.
    - Ditulis ulang dengan UTF-8 tanpa BOM menggunakan .NET encoding.

### Konfigurasi Environment (Perubahan)

- Menambahkan `PROMETHEUS_URL=http://localhost:9090` ke file `.env`.
- Menambahkan `PROMETHEUS_SIMULATION_MODE=true` ke file `.env` untuk mode simulasi latency/packet loss.
- Simulation mode dimatikan (`false`) setelah blackbox exporter berhasil terintegrasi.
- Data yang didapatkan sekarang:
    - ✅ Bandwidth: Real dari SNMP Exporter (`ifHCInOctets`).
    - ✅ Latency: Real dari Blackbox Exporter (`probe_icmp_duration_seconds`).
    - ✅ Packet Loss: Real dari Blackbox Exporter (`probe_success`).

### Verifikasi dan Testing

- Semua migration berhasil dijalankan.
    - `php artisan migrate:fresh` - Semua tabel terbuat dengan benar.
    - Total 6 tabel: users, cache, cache_locks, jobs, job_batches, failed_jobs, devices, rules, rule_conditions.

- Test koneksi Prometheus berhasil.
    - Prometheus server UP dan dapat dijangkau.
    - 3 active targets: prometheus, snmp (192.168.18.200), ping (192.168.18.200) - Semua Health: up.

- Test query metrics dari Prometheus berhasil.
    - Bandwidth: 0.00 Mbps (data real dari SNMP).
    - Latency: ~0.01 ms (data real dari Blackbox ICMP probe).
    - Packet Loss: 0.00% (data real dari Blackbox probe_success).

- Test evaluasi Forward Chaining berhasil.
    - 3 rules dicek dengan data real dari Prometheus.
    - Sistem berjalan tanpa error.

- Test Laravel.
    - `php artisan test` - 2 test passed.

- Cek route Laravel.
    - `php artisan route:list --except-vendor` - 15 route terdaftar.

### Catatan Operasional

- **Auto-Discovery Blackbox Target**: Setiap kali user menambahkan device baru di Modul Manajemen Target Device, sistem akan otomatis menambahkan IP ke SNMP targets (untuk bandwidth) DAN Blackbox targets (untuk latency & packet loss). Prometheus akan mendeteksi perubahan dalam 5 detik via file_sd_configs.
- **Simulation Mode**: Jika blackbox exporter belum tersedia, set `PROMETHEUS_SIMULATION_MODE=true` di .env untuk menggunakan data dummy latency dan packet loss.
- **Scheduler**: Untuk menjalankan evaluasi otomatis setiap menit, jalankan `php artisan schedule:work` di terminal. Untuk production, setup Windows Task Scheduler dengan command `php artisan schedule:run`.
- **Blackbox ICMP Probe**: Blackbox exporter melakukan ICMP ping ke masing-masing target. Pastikan target dapat di-ping dari container blackbox-exporter di Docker.
- **Command Testing**: Tersedia beberapa command untuk testing: `test:prometheus`, `test:create-data`, `blackbox:check`, `prometheus:show-metrics`, `rules:evaluate`.
- **Log Evaluasi**: Output scheduler tersimpan di `storage/logs/forward-chaining.log`.

---

### Penambahan Fitur Paginasi dan Search Server-Side pada Modul Manajemen Target Device

- Menambahkan fitur **Pagination** pada tabel Manajemen Target Device.
    - Pagination membatasi tampilan maksimal 15 data per halaman.
    - Jika data lebih dari 15, akan otomatis masuk ke halaman berikutnya.
    - Komponen pagination dibuat manual tanpa menggunakan CDN atau library eksternal.

- Mengubah sistem **Search menjadi Server-Side**.
    - Search sebelumnya hanya mencari di data yang tampil di halaman aktif (client-side).
    - Search sekarang mencari di **seluruh data** termasuk di halaman pagination lain.
    - Pencarian dilakukan di kolom: Customer ID, Nama Pelanggan, IP Address, dan Model Device.
    - Parameter search dipertahankan di URL saat berpindah halaman pagination.

- Memperbarui `DeviceController@index`.
    - Mengubah query dari `Device::latest()->get()` menjadi `Device::latest()->paginate(15)`.
    - Menambahkan logic pencarian dengan kondisi `when()` untuk filter data berdasarkan keyword.
    - Menggunakan `withQueryString()` untuk mempertahankan parameter search di URL pagination.
    - Menambahkan parameter `$search` yang dikirim ke view.

- Memperbarui view `devices/index.blade.php`.
    - Menghapus search input JavaScript client-side.
    - Menambahkan form search dengan method GET yang submit ke server.
    - Menambahkan tombol "Cari" untuk melakukan pencarian.
    - Menambahkan tombol "Reset" untuk menghapus filter pencarian.
    - Menambahkan informasi jumlah data yang ditampilkan: "Menampilkan X - Y dari Z data".
    - Menambahkan komponen pagination custom dengan styling manual.
    - Pagination menampilkan tombol Previous, nomor halaman, dan Next.
    - Halaman aktif ditandai dengan background biru (#3498db) sesuai tema aplikasi.
    - Menambahkan pesan "Data tidak ditemukan untuk pencarian..." jika hasil search kosong.

- Styling pagination dibuat tanpa CDN.
    - Menggunakan CSS manual dengan warna yang konsisten dengan tema aplikasi.
    - Tombol pagination menggunakan border, padding, dan hover effect.
    - Halaman aktif memiliki background biru dan text putih.
    - Tombol disabled (Prev/Next yang tidak aktif) memiliki warna abu-abu dan cursor not-allowed.

- Memperbaiki encoding file `DeviceController.php`.
    - Menghapus BOM (Byte Order Mark) yang menyebabkan error namespace.
    - File ditulis ulang dengan UTF-8 tanpa BOM menggunakan PowerShell.

## 2026-07-23

### Perbaikan Modul Manajemen Target Device

- Memperkuat proses **Connect IP Address** pada modul Target Device.
    - Menambahkan validasi agar IP yang dicek wajib berbentuk IPv4.
    - Menambahkan pengecekan apakah extension SNMP PHP sudah aktif sebelum menjalankan `snmpget()`.
    - Mengubah penanganan error agar menangkap `Throwable`, sehingga error PHP yang bukan `Exception` tetap bisa ditangani.
    - Menambahkan response error yang lebih jelas jika SNMP belum aktif atau perangkat tidak merespons.

- Memindahkan proses pengelolaan target Prometheus ke service baru.
    - File baru: `app/Services/PrometheusTargetService.php`.
    - Service ini bertugas menambah dan menghapus IP target pada file `../prometheus/targets.json`.
    - Service juga memvalidasi struktur JSON agar file target Prometheus tidak menyebabkan warning/error saat formatnya rusak.
    - Penulisan file dibuat lebih aman dan pesan error dibuat lebih mudah dipahami.

- Menambahkan transaction pada proses create, update, dan delete device.
    - Jika update database berhasil tetapi update target Prometheus gagal, proses akan dibatalkan dan user mendapat pesan error.
    - Ini mengurangi risiko data device di database tidak sinkron dengan target Prometheus.

- Memperbaiki mekanisme verifikasi IP hasil Connect.
    - Sebelumnya session hanya menyimpan satu IP terverifikasi.
    - Sekarang session menyimpan daftar IP terverifikasi agar beberapa tab/form tidak saling menimpa status Connect.

- Memperbaiki form edit device.
    - Menambahkan field **Tanggal Pemasangan** pada halaman edit.
    - Menambahkan validasi dan update `installation_date` pada proses update device.

- Menampilkan error validasi pada halaman tambah dan edit device.
    - Error dari backend sekarang tampil di halaman, sehingga user tahu alasan data gagal disimpan atau diperbarui.
    - Form tambah device otomatis tetap terbuka jika ada error validasi.

### Pengurangan CDN dan Library Eksternal

- Menghapus penggunaan CDN dari layout utama.
    - Menghapus CDN jQuery.
    - Menghapus CDN DataTables.
    - Menghapus CDN SweetAlert.

- Mengganti DataTables dengan pencarian tabel sederhana berbasis JavaScript manual.
    - Pencarian berjalan langsung pada baris tabel device.
    - Jika hasil pencarian kosong, sistem menampilkan pesan "Data tidak ditemukan."

- Menghapus penggunaan Tailwind dari konfigurasi frontend.
    - Menghapus `@tailwindcss/vite` dan `tailwindcss` dari `package.json`.
    - Menghapus plugin Tailwind dari `vite.config.js`.
    - Mengosongkan `resources/css/app.css` dari import Tailwind.
    - Menyederhanakan `resources/views/welcome.blade.php` agar tidak lagi memakai template bawaan Laravel berbasis Tailwind/CDN.

### Penyesuaian Testing

- Menyesuaikan test feature default Laravel.
    - Test sebelumnya mengharapkan route `/` menghasilkan status `200`.
    - Route `/` pada aplikasi ini memang diarahkan ke halaman Manajemen Target Device, sehingga test diubah untuk mengecek redirect ke `devices.index`.

### Verifikasi

- Menjalankan lint PHP pada file terkait.
    - `app/Http/Controllers/DeviceController.php`
    - `app/Services/PrometheusTargetService.php`
    - `routes/web.php`
    - `tests/Feature/ExampleTest.php`

- Menjalankan pemeriksaan route Laravel.
    - `php artisan route:list --except-vendor`

- Menjalankan test Laravel.
    - `php artisan test`
    - Hasil: 2 test passed.

- Menjalankan build frontend.
    - `npm.cmd install`
    - `npm.cmd run build`
    - Hasil: build sukses.

### Catatan Operasional

- Fitur Connect IP Address tetap membutuhkan extension SNMP PHP aktif di environment.
- Jika extension SNMP belum aktif, sistem sekarang menampilkan pesan error yang jelas dan tidak langsung fatal error.
- File `package-lock.json` dibuat setelah menjalankan `npm.cmd install`.
