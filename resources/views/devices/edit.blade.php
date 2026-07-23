@extends('layouts.app')

@stack('styles')
<style>
    h2 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; }
    .btn-success { background-color: #2ecc71; color: white; width: 100%; margin-top: 15px; }
    .btn-secondary { background-color: #95a5a6; color: white; text-decoration: none; display: inline-block; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;}
    
    .form-panel { background: white; padding: 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
    input[type="text"], input[type="date"], input[type="url"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
    input:disabled, input[readonly] { background-color: #eaeded; }

    .form-group-inline { display: flex; align-items: center; gap: 10px; }
    .btn-connect { background-color: #e67e22; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
    .btn-connect:disabled { background-color: #bdc3c7; }
    #connectStatus { font-weight: bold; margin-top: 5px; display: block; }
</style>

@section('content')
<div>
    <h2>Edit Data Target Device</h2>
    
    <a href="{{ route('devices.index') }}" class="btn-secondary"> Kembali</a>

    <div class="form-panel">
        <form action="{{ route('devices.update', $device->id) }}" method="POST" id="editForm">
            @csrf
            @method('PUT')
            
            <div class="form-group">
                <label>IP Address Target</label>
                <div class="form-group-inline">
                    <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address', $device->ip_address) }}" required>
                    <button type="button" class="btn-connect" id="btnConnect">Connect</button>
                </div>
                <small id="connectStatus"></small>
                <small style="display:block; margin-top:4px; color:#607080;">Jika IP diubah, lakukan Connect terlebih dahulu sebelum menyimpan.</small>
            </div>

            <div class="form-group">
                <label>Model Perangkat</label>
                <!-- Input ini akan diupdate via JS saat tombol Connect diklik -->
                <input type="text" name="device_model" id="device_model" value="{{ $device->device_model }}" readonly>
            </div>

            <div class="form-group">
                <label>Nama Pelanggan</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $device->customer_name) }}" required>
            </div>

            <div class="form-group">
                <label>URL Maps Lokasi</label>
                <input type="url" name="maps_url" value="{{ old('maps_url', $device->maps_url) }}">
            </div>

            <button type="submit" class="btn btn-success" id="btnSubmit"> Simpan Perubahan</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const btnConnect = document.getElementById('btnConnect');
    const btnSubmit = document.getElementById('btnSubmit');
    const ipInput = document.getElementById('ip_address');
    const modelInput = document.getElementById('device_model');
    const connectStatus = document.getElementById('connectStatus');
    const originalIpAddress = "{{ $device->ip_address }}";
    let verifiedIpAddress = originalIpAddress;

    ipInput.addEventListener('input', function() {
        const currentIpAddress = ipInput.value.trim();

        if (currentIpAddress === originalIpAddress) {
            verifiedIpAddress = originalIpAddress;
            btnSubmit.disabled = false;
            connectStatus.innerText = '';
            return;
        }

        if (currentIpAddress !== verifiedIpAddress) {
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
</script>
@endpush
