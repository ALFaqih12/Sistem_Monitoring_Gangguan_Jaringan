# Instalasi DomPDF untuk Fitur Export PDF

## Dependency Baru

Fitur **Export to PDF** pada History Alert memerlukan library `barryvdh/laravel-dompdf`.

## Cara Install

Jalankan command berikut di terminal (di dalam direktori `web-app`):

```bash
composer require barryvdh/laravel-dompdf
```

## Verifikasi Instalasi

Setelah instalasi selesai, cek apakah package sudah terdaftar:

```bash
composer show barryvdh/laravel-dompdf
```

Output yang diharapkan:
```
name     : barryvdh/laravel-dompdf
descrip. : A DOMPDF Wrapper for Laravel
versions : * v3.x.x
...
```

## Test Fitur

1. Buka browser dan akses `/alerts`
2. Klik button **"Export PDF"**
3. Browser akan otomatis download file PDF dengan nama `report-gangguan-DD-MM-YYYY-to-DD-MM-YYYY.pdf`
4. Buka file PDF dan verifikasi isi data sesuai dengan filter yang aktif

## Troubleshooting

### Error: Class 'Barryvdh\DomPDF\Facade\Pdf' not found

**Penyebab:** Package belum terinstall.

**Solusi:** Jalankan `composer require barryvdh/laravel-dompdf`

### Error: Memory limit exceeded

**Penyebab:** Data terlalu banyak (lebih dari 1000 rows).

**Solusi:** Sistem sudah di-limit maksimal 1000 data. Jika tetap error, tingkatkan `memory_limit` di `php.ini` atau gunakan filter tanggal untuk mengurangi data.

### PDF kosong atau layout rusak

**Penyebab:** Font tidak tersedia atau CSS tidak ter-load.

**Solusi:** DomPDF sudah include font DejaVu Sans secara default. Pastikan tidak ada error di log Laravel (`storage/logs/laravel.log`).

### Timeout saat generate PDF

**Penyebab:** Data terlalu banyak dan server lambat.

**Solusi:** 
1. Gunakan filter tanggal untuk mengurangi data
2. Tingkatkan `max_execution_time` di `php.ini`
3. Atau ubah limit di `AlertController::exportPdf()` dari 1000 menjadi lebih kecil (misal: 500)

## Konfigurasi Tambahan (Opsional)

Jika ingin customize pengaturan DomPDF, publish config file:

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

File config akan dibuat di `config/dompdf.php`. Anda dapat mengubah:
- Default paper size
- Default orientation
- Font directory
- Memory limit
- Dan lain-lain

## Catatan

- Library ini kompatibel dengan Laravel 12
- Tidak memerlukan konfigurasi tambahan untuk penggunaan dasar
- Support UTF-8 dan karakter Indonesia
- PDF di-generate server-side (tidak perlu JavaScript di browser)
