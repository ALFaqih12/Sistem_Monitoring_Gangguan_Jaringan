<!-- Panggil Master Layout -->
@extends('layouts.app')

<!-- Tambahkan CSS khusus halaman ini ke dalam stack 'styles' di master layout -->
@push('styles')
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
    .alert-error { background-color: #fdecea; color: #922b21; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    .badge { display: inline-block; max-width: 260px; padding: 5px 9px; border-radius: 999px; font-size: 12px; background-color: #eef5fb; color: #2c3e50; border: 1px solid #d7e7f5; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
    .location-link { display: inline-block; padding: 6px 10px; border-radius: 4px; background: #edf7f2; color: #1e8449; text-decoration: none; font-size: 13px; font-weight: 700; }
    .location-link:hover { background: #d9f0e3; }

    .btn-warning { background-color: #f1c40f; color: #333; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; }
    .btn-warning:hover { background-color: #f39c12; }

    .btn-danger { background-color: #e74c3c; color: white; border: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
    .btn-danger:hover { background-color: #c0392b; }
    .action-buttons { display: flex; align-items: center; gap: 8px; }
    .action-buttons form { margin: 0; }

    .table-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .search-form { display: flex; gap: 8px; align-items: center; }
    .table-search { width: 280px; max-width: 100%; padding: 9px 10px; border: 1px solid #cfd8e3; border-radius: 4px; }
    .btn-search { background-color: #3498db; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
    .btn-search:hover { background-color: #2980b9; }
    .btn-clear { background-color: #95a5a6; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; }
    .btn-clear:hover { background-color: #7f8c8d; }
    .empty-row { text-align: center; color: #607080; padding: 18px; }

    /* CSS untuk Pagination */
    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e8edf3; }
    .pagination-info { color: #607080; font-size: 13px; }
    .pagination { display: flex; list-style: none; gap: 5px; margin: 0; padding: 0; }
    .pagination li { display: inline-block; }
    .pagination a, .pagination span { display: inline-block; padding: 8px 12px; border: 1px solid #cfd8e3; border-radius: 4px; color: #2c3e50; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.2s; }
    .pagination a:hover { background-color: #3498db; color: white; border-color: #3498db; }
    .pagination .active span { background-color: #3498db; color: white; border-color: #3498db; }
    .pagination .disabled span { color: #95a5a6; cursor: not-allowed; border-color: #e8edf3; }
    .pagination .disabled span:hover { background-color: transparent; color: #95a5a6; }
</style>
@endpush

<!-- Masukkan HTML konten ke dalam area 'content' di master layout -->
@section('content')
<div>
    <h2>Manajemen Target Device</h2>

    @if(session('success'))
        <div class="alert-success alert-flash" id="successAlert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <button class="btn btn-primary" id="btnToggleForm">+ Add Device Target</button>

    <div class="form-panel" id="deviceFormPanel" @if($errors->any()) style="display:block;" @endif>
        <form action="{{ route('devices.store') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label>IP Address Target</label>
                <div class="form-group-inline">
                    <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address') }}" required>
                    <button type="button" class="btn btn-connect" id="btnConnect">Connect</button>
                </div>
                <small id="connectStatus" style="font-weight: bold; margin-top: 5px; display: block;"></small>
            </div>

            <div class="form-group">
                <label>Model Perangkat</label>
                <!-- Input ini akan diupdate via JS saat tombol Connect diklik -->
                <input type="text" name="device_model" id="device_model" value="{{ old('device_model') }}" readonly>
            </div>

            <div class="form-group">
                <label>Nama Pelanggan</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required>
            </div>

            <div class="form-group">
                <label>Tanggal Pemasangan</label>
                <input type="date" name="installation_date" value="{{ old('installation_date') }}" required>
            </div>

            <div class="form-group">
                <label>URL Maps Lokasi</label>
                <input type="url" name="maps_url" value="{{ old('maps_url') }}">
            </div>

            <button type="submit" class="btn btn-success" id="btnSubmit" disabled>Submit Data</button>
        </form>
    </div>

    <div class="table-panel">
        <h3>Daftar Target Device</h3>

        <!-- Toolbar Search dengan Form Submit Server-Side -->
        <div class="table-toolbar">
            <div class="pagination-info">
                Menampilkan {{ $devices->firstItem() ?? 0 }} - {{ $devices->lastItem() ?? 0 }} dari {{ $devices->total() }} data
            </div>
            <form method="GET" action="{{ route('devices.index') }}" class="search-form">
                <input type="text" name="search" class="table-search" placeholder="Cari ID, Nama, IP, Model..." value="{{ $search ?? '' }}">
                <button type="submit" class="btn-search">Cari</button>
                @if($search)
                    <a href="{{ route('devices.index') }}" class="btn-clear">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table id="deviceTable">
                <thead>
                    <tr>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>IP Address</th>
                        <th>Model Device</th>
                        <th>Lokasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td><span class="badge">{{ $device->customer_id }}</span></td>
                            <td class="cell-strong">{{ $device->customer_name }}</td>
                            <td class="cell-ip">{{ $device->ip_address }}</td>
                            <td>{{ $device->device_model ?? '-' }}</td>
                            <td>
                                @if($device->maps_url)
                                    <a href="{{ $device->maps_url }}" target="_blank" class="location-link">Lihat Lokasi</a>
                                @else
                                    <span style="color: #95a5a6;">-</span>
                                @endif
                            </td>
                            <td>
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
                    @empty
                        <tr>
                            <td colspan="6" class="empty-row">
                                @if($search)
                                    Data tidak ditemukan untuk pencarian "{{ $search }}".
                                @else
                                    Belum ada data target device.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Komponen Pagination -->
        @if($devices->hasPages())
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Halaman {{ $devices->currentPage() }} dari {{ $devices->lastPage() }}
                </div>
                <ul class="pagination">
                    {{-- Previous Page Link --}}
                    @if ($devices->onFirstPage())
                        <li class="disabled"><span>&laquo; Prev</span></li>
                    @else
                        <li><a href="{{ $devices->previousPageUrl() }}">&laquo; Prev</a></li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($devices->getUrlRange(1, $devices->lastPage()) as $page => $url)
                        @if ($page == $devices->currentPage())
                            <li class="active"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($devices->hasMorePages())
                        <li><a href="{{ $devices->nextPageUrl() }}">Next &raquo;</a></li>
                    @else
                        <li class="disabled"><span>Next &raquo;</span></li>
                    @endif
                </ul>
            </div>
        @endif
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

    // Toggle form Add Device
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

    // Reset verifikasi IP jika IP diubah oleh user
    ipInput.addEventListener('input', function() {
        if (ipInput.value.trim() !== verifiedIpAddress) {
            verifiedIpAddress = null;
            modelInput.value = '';
            btnSubmit.disabled = true;
            connectStatus.innerText = '';
        }
    });

    // Proses Connect ke perangkat via SNMP
    btnConnect.addEventListener('click', function() {
        const ipAddress = ipInput.value.trim();
        if (ipAddress === '') return alert('Isi IP Address!');

        connectStatus.style.color = '#e67e22';
        connectStatus.innerText = 'Menghubungkan...';
        btnConnect.disabled = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch("{{ route('devices.ping') }}", {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
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

    // Auto-hide alert success setelah 3 detik
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.opacity = '0';
        }, 3000);
    }
</script>
@endpush