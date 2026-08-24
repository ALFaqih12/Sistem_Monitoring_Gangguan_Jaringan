# Arsitektur Sistem Monitoring Gangguan Jaringan (Berbasis Web & Forward Chaining)

Dokumen ini berfungsi sebagai panduan teknis dan konseptual bagi AI Agent dalam mengembangkan sistem monitoring gangguan jaringan.

## 1. Teknologi yang Digunakan (Tech Stack)

- **Laravel**: Framework utama untuk membangun aplikasi web (backend & frontend). Bertugas mengelola _User Interface_, logika _Forward Chaining_, _database_, integrasi API, dan _alerting_.
- **Prometheus**: Bertindak sebagai _Data Collector_ yang menyimpan dan mengambil data _traffic_ (metrik) dari simulasi infrastruktur.
- **Grafana**: Berfungsi sebagai _dashboard_ visualisasi metrik monitoring secara _real-time_.
- **Docker**: Berperan sebagai _container_ untuk menjalankan ekosistem monitoring secara terisolasi (Prometheus, Grafana, snmp-exporter, blackbox-exporter).
- **PNETLab**: Platform simulasi infrastruktur topologi jaringan yang menjadi target pengawasan.
- **snmp-exporter**: Menjadi jembatan komunikasi data berbasis SNMP antara Prometheus dengan perangkat di dalam PNETLab.
- **blackbox-exporter**: Bertugas sebagai _ping probe_ (ICMP/TCP) untuk memeriksa ketersediaan dan latensi jaringan di PNETLab.
- **Bot Telegram**: Terintegrasi dengan Laravel untuk memberikan _alerting_ otomatis secara _real-time_ kepada _user_ atau teknisi jaringan.

## 2. Alur Arsitektur Sistem (System Flow)

### A. Autentikasi

- **User Login**: Pengguna utama sistem ini hanya untuk di peruntukan 1 user saja yaitu adalah **admin dari penyedia layanan WIFI**. Admin melakukan login ke portal web berbasis Laravel untuk mengakses _dashboard_ manajemen.

### B. Manajemen Target Device

- **Input Target**: Admin memasukkan alamat IP target perangkat yang ingin dimonitor, lalu menekan tombol **Connect**.
- **Validasi Prometheus**: Saat IP dicoba untuk dikoneksikan, Laravel mengirimkan permintaan (_query_) ke **Prometheus HTTP API** (contoh: endpoint `api/v1/targets`) untuk memastikan target tersedia pada sistem monitoring.
- **Status Evaluasi**: Jika IP target merespons dan statusnya aktif (UP) di Prometheus, maka IP dianggap valid dan dapat ditambahkan sebagai target device.
- **Auto Fill Data**: Jika target berhasil terkoneksi, sistem otomatis mengisi **model perangkat** berdasarkan data monitoring yang tersedia dan membuat **ID pelanggan** secara otomatis.
- **Data Tambahan Target**: Admin melengkapi nama pelanggan, tanggal pemasangan, dan URL Maps lokasi sebelum menyimpan data target device ke database.

### C. Manajemen Rule (Forward Chaining)

- **Input Dinamis**: Admin dapat membuat dan menyetting aturan (_rule_) Forward Chaining secara dinamis (tidak di-hardcode) melalui antarmuka web.
- **Struktur Rule**: Rule memiliki kode otomatis berurutan (contoh: `R01`), nama rule, target device tertentu atau pilihan **All Device**, serta status aktif/nonaktif.
- **Kondisi IF**: Setiap rule memiliki satu atau lebih kondisi berbasis parameter **Bandwidth**, **Latency**, dan **Packet Loss**. Setiap kondisi berisi parameter, operator (`>`, `<`, `>=`, `<=`, `=`), dan nilai numerik sesuai satuan parameternya.
- **Relasi Antar Kondisi**: Jika rule memiliki lebih dari satu kondisi, seluruh kondisi dihitung dengan relasi **AND**, misalnya `IF condition1 AND condition2 AND condition3 THEN gangguan terdeteksi`.
- **THEN / Hasil Rule**: Hasil dari rule yang match adalah status **gangguan terdeteksi** berdasarkan nama rule. Sistem kemudian menyimpan data tersebut ke Log Gangguan dan menyiapkan alert Telegram jika integrasi aktif.
- **Penyimpanan Rule**: Data rule disimpan dalam tabel relasional yang fleksibel di database, misalnya tabel `rules` dan `rule_conditions`.

### D. Aliran Data dan Evaluasi Logika

- **Data Polling**: Laravel menggunakan fitur **Task Scheduling** (Cron Job) yang berjalan secara periodik (misal setiap menit) untuk mengambil (_pull_) data metrik terbaru dari Prometheus HTTP API. Data ini _tidak otomatis disimpan_ ke dalam _database_.
- **Mesin Inferensi (Forward Chaining Engine)**: Data mentah yang diambil dari Prometheus akan dievaluasi dan dicocokkan (di-_match_) dengan _Rule Forward Chaining_ yang sudah disetting user.
- **Penyimpanan Log (Filtering)**: Jika data **MATCH** dengan rule aktif, maka data tersebut dianggap sebagai gangguan, disimpan ke database, dan ditampilkan pada menu Log Gangguan. Jika data **TIDAK MATCH**, data tersebut diabaikan dan tidak disimpan.
- **Isi Log Gangguan**: Log gangguan menyimpan IP address, model device, ID pelanggan, nama pelanggan, kode rule, nama rule, URL Maps lokasi, serta waktu terkini saat gangguan terdeteksi yang mencakup jam, tanggal, bulan, dan tahun.

### E. Manajemen Alert (Telegram) dan Deduplikasi

- **Integrasi Alert**: Di menu Manajemen Alert, user mensetting integrasi webhook/API antara Laravel dengan Bot Telegram.
- **Trigger Alert**: Saat _Forward Chaining Engine_ mendeteksi adanya _match_ (gangguan), sistem akan men-_trigger_ pengiriman pesan ke Telegram.
- **Isi Pesan Telegram**: Pesan Telegram mengambil data dari Log Gangguan dan berisi IP address, model device, nama pelanggan, nama rule, waktu terkini gangguan terdeteksi (jam, tanggal, bulan, dan tahun), serta URL Maps lokasi.
- **Mekanisme Anti-Spam (Deduplikasi)**: Sistem dilengkapi dengan algoritma deduplikasi berbasis kombinasi data gangguan dan waktu. Jika IP target, rule, data gangguan, dan waktu deteksi sangat berdekatan serta datanya sama persis, sistem tidak membuat log duplikat dan tidak mengirim pesan Telegram berulang.
- **Kunci Deduplikasi**: Rekomendasi kunci deduplikasi adalah kombinasi `ip_target`, `rule_id`, nilai kondisi yang match, dan rentang waktu deteksi. Log atau alert baru hanya dibuat jika target berbeda, rule berbeda, nilai gangguan berbeda, atau waktu deteksi sudah berada di luar rentang deduplikasi.

## 3. Rekomendasi Implementasi Teknis (Untuk AI Developer)

1. **Database Schema untuk Rule**: Rancang skema yang modular untuk sistem pakar. Gunakan tabel relasional yang memisahkan antara entitas _Rule_ dan _Premis/Kondisi_. Hasil rule yang match digunakan sebagai dasar pembuatan Log Gangguan dan trigger Telegram.
2. **API Client Class**: Buat _Service Class_ khusus (misal: `PrometheusService`) menggunakan Laravel HTTP Client untuk memusatkan dan merapikan semua komunikasi ke Prometheus API.
3. **State/Cache untuk Deduplikasi**: Gunakan **Redis** atau _Cache Store_ bawaan Laravel untuk menyimpan _flag_ deduplikasi alert. Contoh: berikan _Cache key_ berupa kombinasi `alert_{ip_target}_{rule_id}_{time_window}`. Jika kunci ini masih aktif dan data gangguan sama persis, Laravel akan melewati (_skip_) proses insert ke database log dan pengiriman Telegram.
