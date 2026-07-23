<!-- Panggil Master Layout -->
@extends('layouts.app')

<!-- Tambahkan CSS khusus halaman ini ke dalam stack 'styles' di master layout -->
@stack('styles')
<style>
    /* CSS Khusus Form & Table Device */
    h2 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; }
    .btn-primary { background-color: #3498db; color: white; }
    .btn-success { background-color: #2ecc71; color: white; width: 100%; margin-top: 15px; }
    .btn-connect { background-color: #e67e22; color: white; margin-left: 10px; }
    
    .form-panel { background: white; padding: 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 15px; margin-bottom: 30px; display: none; }
    .form-group { margin-bottom: 15px; }
    .form-group-inline { display: flex; align-items: center; }
    .form-group-inline input { flex: 1; }
    label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
    input[type="text"], input[type="date"], input[type="url"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
    input:disabled, input[readonly] { background-color: #eaeded; }
    
    .table-panel { background: white; padding: 22px; border-radius: 6px; box-shadow: 0 6px 18px rgba(44,62,80,0.08); margin-top: 20px; border: 1px solid #e8edf3; }
    .table-panel h3 { margin-bottom: 16px; color: #2c3e50; font-size: 18px; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; }
    th, td { text-align: left; padding: 13px 14px; border-bottom: 1px solid #e8edf3; font-size: 14px; vertical-align: middle; white-space: nowrap; }
    th { background-color: #2c3e50; color: white; font-size: 13px; text-transform: uppercase; letter-spacing: 0; }
    th:first-child { border-top-left-radius: 6px; }
    th:last-child { border-top-right-radius: 6px; }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:hover { background-color: #f8fbff; }
    tbody tr:last-child td { border-bottom: none; }
    .cell-strong { color: #243447; }
    .cell-ip { font-family: Consolas, 'Courier New', monospace; color: #1f618d; font-weight: 600; }
    .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    .badge { display: inline-block; max-width: 260px; padding: 5px 9px; border-radius: 999px; font-size: 12px; background-color: #eef5fb; color: #2c3e50; border: 1px solid #d7e7f5; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
    .location-link { display: inline-block; padding: 6px 10px; border-radius: 4px; background: #edf7f2; color: #1e8449; text-decoration: none; font-size: 13px; font-weight: 700; }
    .location-link:hover { background: #d9f0e3; }

    .btn-warning { background-color: #f1c40f; color: #333; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; }
    .btn-warning:hover { background-color: #f39c12; }

    .btn-danger { background-color: #e74c3c; color: white; border: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
    .btn-danger:hover { background-color: #c0392b; }
    .action-buttons { display: flex; align-items: center; gap: 8px; } /* Agar tombol edit dan hapus bersebelahan */
    .action-buttons form { margin: 0; }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter { margin-bottom: 14px; }
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input { border: 1px solid #cfd8e3; border-radius: 4px; padding: 6px 8px; margin: 0 6px; }
    .dataTables_wrapper .dataTables_info { padding-top: 16px; color: #607080; font-size: 13px; }
    .dataTables_wrapper .dataTables_paginate { padding-top: 12px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 4px !important; border: 1px solid transparent !important; padding: 6px 10px !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #3498db !important; color: white !important; border-color: #3498db !important; }
</style>

<!-- Masukkan HTML konten ke dalam area 'content' di master layout -->
@section('content')
<div>
    <h2>Manajemen Target Device</h2>

    <button class="btn btn-primary" id="btnToggleForm">+ Add Device Target</button>

    <div class="form-panel" id="deviceFormPanel">
        <form action="{{ route('devices.store') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label>IP Address Target</label>
                <div class="form-group-inline">
                    <input type="text" name="ip_address" id="ip_address" placeholder="Contoh: 192.168.10.5" required>
                    <button type="button" class="btn btn-connect" id="btnConnect">Connect</button>
                </div>
                <small id="connectStatus" style="display:block; margin-top:5px; font-weight:bold;"></small>
                <small style="display:block; margin-top:4px; color:#607080;">Data hanya bisa disimpan setelah IP berhasil Connect.</small>
            </div>

            <div class="form-group">
                <label>Model Perangkat</label>
                <input type="text" name="device_model" id="device_model" placeholder="Akan terisi otomatis setelah Connect..." readonly>
            </div>

            <div class="form-group">
                <label>Nama Pelanggan</label>
                <input type="text" name="customer_name" required>
            </div>

            <div class="form-group">
                <label>ID Pelanggan</label>
                <input type="text" placeholder="Otomatis digenerate oleh sistem" disabled>
            </div>

            <div class="form-group">
                <label>Tanggal Pemasangan</label>
                <input type="date" name="installation_date" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="form-group">
                <label>URL Maps Lokasi</label>
                <input type="url" name="maps_url">
            </div>

            <button type="submit" class="btn btn-success" id="btnSubmit" disabled>Submit Data</button>
        </form>
    </div>

    <div class="table-panel">
        <h3>Daftar Target Device Terpantau</h3>
        <div class="table-responsive">
            <table id="deviceTable">
                <thead>
                    <tr>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>IP Address</th>
                        <th>Model</th>
                        <th>Lokasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        <tr>
                            <td><strong class="cell-strong">{{ $device->customer_id }}</strong></td>
                            <td>{{ $device->customer_name }}</td>
                            <td class="cell-ip">{{ $device->ip_address }}</td>
                            <td><span class="badge">{{ $device->device_model ?: 'Belum terdeteksi' }}</span></td>
                            <td>
                                @if($device->maps_url) <a href="{{ $device->maps_url }}" target="_blank" class="location-link">Location</a> @else - @endif
                            </td>
                            <td>
                                <!-- Bungkus tombol dalam div agar bersebelahan rapi -->
                                <div class="action-buttons">
                                    <!-- Tombol Edit -->
                                    <a href="{{ route('devices.edit', $device->id) }}" class="btn-warning">Edit</a>
                                    
                                    <!-- Tombol Delete (Harus menggunakan form karena method DELETE) -->
                                    <form action="{{ route('devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus target ini? Data IP juga akan dihapus dari antrean Prometheus.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

<!-- Masukkan Script khusus halaman ini ke bagian paling bawah (sebelum </body> di master layout) -->
@push('scripts')
<script>
    const btnToggleForm = document.getElementById('btnToggleForm');
    const formPanel = document.getElementById('deviceFormPanel');
    const btnConnect = document.getElementById('btnConnect');
    const btnSubmit = document.getElementById('btnSubmit');
    const ipInput = document.getElementById('ip_address');
    const modelInput = document.getElementById('device_model');
    const connectStatus = document.getElementById('connectStatus');
    let verifiedIpAddress = null;

    btnToggleForm.addEventListener('click', function() {
        if (formPanel.style.display === 'none' || formPanel.style.display === '') {
            formPanel.style.display = 'block';
            btnToggleForm.innerText = 'X Close Form';
            btnToggleForm.style.backgroundColor = '#e74c3c';
        } else {
            formPanel.style.display = 'none';
            btnToggleForm.innerText = '+ Add Device Target';
            btnToggleForm.style.backgroundColor = '#3498db';
        }
    });

    ipInput.addEventListener('input', function() {
        if (ipInput.value.trim() !== verifiedIpAddress) {
            verifiedIpAddress = null;
            modelInput.value = '';
            btnSubmit.disabled = true;
            connectStatus.innerText = '';
        }
    });

    btnConnect.addEventListener('click', function() {
        const ipAddress = ipInput.value.trim();
        if (ipAddress === '') return alert('Isi IP Address!');

        connectStatus.style.color = '#e67e22';
        connectStatus.innerText = 'Menghubungkan...';
        btnConnect.disabled = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch("{{ route('devices.ping') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ ip_address: ipAddress })
        })
        .then(async response => {
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || 'Gagal terhubung ke perangkat.');
            }

            return data;
        })
        .then(data => {
            if (data.status === 'success') {
                verifiedIpAddress = ipAddress;
                modelInput.value = data.device_model;
                connectStatus.style.color = '#2ecc71';
                connectStatus.innerText = data.message;
                btnSubmit.disabled = false;
            } else {
                throw new Error(data.message || 'Gagal terhubung ke perangkat.');
            }
        })
        .catch(error => {
            verifiedIpAddress = null;
            connectStatus.style.color = '#e74c3c';
            connectStatus.innerText = 'Gagal: ' + error.message;
            btnSubmit.disabled = true;
        })
        .finally(() => {
            btnConnect.disabled = false;
        });
    });


    @if(session('success'))
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end', // Muncul dari atas kanan
            showConfirmButton: false,
            timer: 3000, // Hilang otomatis dalam 3 detik
            timerProgressBar: true, // Ada bar progress yang menyusut
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: "{{ session('success') }}"
        });
    @endif

    $(document).ready(function() {
        $('#deviceTable').DataTable({
            "paging": true,          // Mengaktifkan pagination
            "lengthChange": true,    // Mengaktifkan pilihan jumlah baris per halaman
            "searching": true,       // Mengaktifkan kolom pencarian
            "ordering": true,        // Mengaktifkan sorting (klik header tabel)
            "info": true,            // Menampilkan informasi jumlah data
            "autoWidth": false,
            "language": {
                "search": "Cari Perangkat:", // Mengubah bahasa tombol cari
                "paginate": {
                    "previous": "Sebelumnya",
                    "next": "Selanjutnya"
                }
            }
        });
    });
</script>
@endpush
