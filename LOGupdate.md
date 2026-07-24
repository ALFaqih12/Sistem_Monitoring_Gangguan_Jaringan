# LOG Update Development

## 2026-07-24

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

### Verifikasi

- Menjalankan test Laravel.
  - `php artisan test`
  - Hasil: 2 test passed.

- Menjalankan pemeriksaan route Laravel.
  - `php artisan route:list --except-vendor`
  - Semua route berfungsi normal.

### Catatan Operasional

- Fitur search sekarang lebih powerful karena mencari di seluruh database, bukan hanya di halaman aktif.
- Pagination otomatis menyesuaikan jumlah halaman berdasarkan total data.
- Jika tidak ada data atau hasil search kosong, tabel akan menampilkan pesan yang informatif.
- Parameter search tetap tersimpan di URL sehingga user bisa bookmark atau share URL dengan filter tertentu.

---

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