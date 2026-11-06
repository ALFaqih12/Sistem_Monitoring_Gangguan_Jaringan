# Hasil Testing

## saya sudah melakukan testing dan menemukan beberapa bug baru

1. Metric bandwidth pada card client (yang diambil dari discovery via core router) tidak muncul/ tampilannya "-" tetapi pada card core router SIWA NETWORK (yang di ambil dari manual target device) bandwidthnya tampil, kemungkinan query untuk simple queue (metode pendekatan untuk mengambil bandwidth dari ip client yang di tambahkan lewat discovery via core router) pada card client monitoring dashboard belum di panggil/belum dibuat
2. Saya menambahkan Rule Lattency > 5.00ms, pada card client monitoring di dashboard ada beberapa card yang match dengan rule tersebut namun pada metric lattencynya berwarna hitam seharusnya berwarna merah karena match dengan kondisi thresholdnya yaitu lattency lebih besar dari 5.00ms
