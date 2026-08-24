# To do list yang akan kita kerjakan

## Memperbarui Modul Log Gangguan

- Jika data traffic pada perangkat, match dengan rule yang sama berulang kali, jangan buat baris baru pada tabel log gangguan cukup update data metric dan waktu terbarunya saja pada baris yang sudah terbuat sebelumnya, tetapi jika match dengan rule berbeda buat baris baru.

- Jika data traffic pada perangkat sudah tidak match lagi (data traffic berubah dan tidah memenuhi syarat pada rule) hapus baris tersebut dari log gangguan secara otomatis.

- ganti kolom status dengan data lokasi yang sudah ada pada perangkat
