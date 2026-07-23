# Product Requirements Document

## Informasi Proyek

- Nama Proyek : SISTEM PAKAR MONITORING GANGGUAN JARINGAN MENGGUNAKAN METODE FORWARD CHAINING BERBASIS WEB PADA SIWA NETWORK

## Latar Belakang

- Otomatisasi proses monitoring gangguan jaringan menggunakan mesin inferensi yang berbasis logika forward chaining untuk mendeteksi gangguan jaringan secara otomatis melalui rule yang sudah di tentukan melalui forward chaining

- Alasan sistem ini dibuat yaitu, untuk mengotomatisasi proses monitoring gangguan jaringan sehingga meminimalisir terjadinya keterlambatan ataupun kelalaian dalam mendeteksi gangguan jaringan

## Tujuan

- Sistem ini bertujuan untuk penyedia layanan wifi dapat memonitoring gangguan jaringan secara otomatis agar memudahkan dalam mendeteksi serta mengelola gangguan pada jaringan yang di alami oleh customer

- Efisiensi dalam memonitoring dari yang sebelumnya mendeteksi dan mencari gangguan secara manual dengan sistem ini proses monitoring dalam mendeteksi gangguan jaringan menjadi otomatis dan lebih cepat dan tepat sasaran

## Target Pengguna

- admin Penyedia layanan WIFI

## Fitur / Modul

- Modul Login

- Modul Manajemen Target Device
    - Add Device Target
        - input IP ADDRESS target + button connect
        - input Model Perangkat (otomatis terisi jika ip target berhasil conect)
        - input Nama pelanggan
        - input id pelanggan (otomatis)
        - input tanggal pemasangan
        - URL Maps Lokasi
        - button submit
    - Table data target : Menamipilkan id pelanggan, nama pelanggan, ip address, model device, link lokasi, aksi edit & delete

- Modul Rule Forward Chaining
    - Add New Rule
        - input code rule (otomatis terisi beruurut misal : R01)
        - input Nama rule
        - Target Device (berbentuk dropdown yang mana ada pilihan pilihan ip address yang sudah terconnect di modul Manajemen Target Device dan ada pillihan All Device yang berarti mencakup semua ip address yang connect)
        - Rule Condition / IF condition (disinilah logika forward chaining (IF...AND...THEN) nya di implementasikan secara inputan bukan hardcode )
            - Dropdown parameter (yaitu ada pillihan Bandwidth, Lattency, Packet Loss)
            - Dropdown operator (yaitu ada pillihan >, <, >=, <=, =)
            - Inputan value / nilai (berbasis number / integer dan satuannya tergantung pillihan dari parameternya, misal jika parameter yang dipillih Bandwidth maka satuannya (mbps), jika Lattency (ms), dan jika packet loss (%) )

            - Add Condition (jika button ini di klik maka akan keluar inputan yang sama)
            - Dropdown parameter (yaitu ada pillihan Bandwidth, Lattency, Packet Loss)
            - Dropdown operator (yaitu ada pillihan >, <, >=, <=, =)
            - Inputan value / nilai (berbasis number / integer dan satuannya tergantung pillihan dari parameternya, misal jika parameter yang dipillih Bandwidth maka satuannya (mbps), jika Lattency (ms), dan jika packet loss (%) )

            note : jadi fitur add condition itu berguna jika rule mempunya condition bercabang bukan hanya 1 atau 2 parameter saja, jadi bisa menambahkan 3 parameter berbeda dengan condition berbeda tetapi tetap terhitung 1 rule contoh logikanya seperti IF condition1 AND condition2 AND condition3 THEN kesimpulan

        - Button save rule
        - Button cancel

    - Table data Rule : Menampilkan Code rule, nama rule, dan togle button on/off pada setiap data rule untuk mengaktifkan dan menonaktifkan rule tersebut

- Modul Log Gangguan (Modul ini untuk menyimpan gangguan gangguan yang match atau serasi dengan Rule Forward Chaining nya, jadi ketika ada ip address target yang data trafficnya match dengan rule yang ada di modul Rule Forward Chaining maka akan tersimpan dan tertampil di dalam table modul ini)
    - Table Log (Menampilkan ip address, model device, id pelanggan, nama pelanggan, code rule, nama rule)

    note : Jika ada data yang sama persis data tidak akan tersimpan dan tertampil lagi pada table itu hanya akan membuat spam

- Modul Manajemen Alert (di mdalam modul ini adalah settingan integrasi antara web dengan bot telegram, yang nantinya jika ada data traffic yang match pada forward chaining web akan mengirimkan pesan secara otomatis ke bot telegram )
    - isi pesan yang dikirimkan ke telegram
        - ip address, model device, id pelanggan, nama pelanggan, code rule, nama rule, URL Maps Lokasi

    note : Pesan yang dikirimkan dari web ke telegram tidak harus berbentuk spam atau mengirimkan pesan berkali kali padahal datanya sama persis, jika datanya sama persis pesan hanya dikirimkan sekali saja.

## User Flow / alur frontend

- User melakukan Login
- User masuk ke web sistem
- user masuk ke modul Manajemen Target Device
- User menambahkan Target Device dengan mengklik Add Device Target dan menginputkan pada formnya lalu klik submit
- User masuk ke Modul Rule Forward Chaining dan menambahkan rule dengan mengklik Add New Rule dan mengisikan form yang ada di dalamnya lalu klik save rule
- Pada Modul Log Gangguan periksa apakah ada gangguan yang sudah terdeteksi
- User melakukan integrasi ke telegram dengan masuk ke Modul manajemen alert dan mengisikan atau menyetting segala yang dibutuhkan, jika sudah terintegrasi ke bot telegram maka jika ada gangguan yang terdeteksi web akan otomatis mengirimkan pesan alert ke telegram
