@extends('layouts.app')

@push('styles')
<style>
    h2 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(44,62,80,0.08);
        border: 1px solid #e8edf3;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(44,62,80,0.12);
    }
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        flex-shrink: 0;
    }
    .stat-icon.blue {
        background-color: #eef5fb;
        color: #3498db;
    }
    .stat-icon.green {
        background-color: #edf7f2;
        color: #2ecc71;
    }
    .stat-info h3 {
        font-size: 32px;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
        line-height: 1.2;
    }
    .stat-info p {
        font-size: 14px;
        color: #607080;
        margin: 4px 0 0 0;
    }
    .monitoring-panel {
        background: white;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(44,62,80,0.08);
        border: 1px solid #e8edf3;
        margin-top: 20px;
    }
    .monitoring-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .monitoring-info h4 {
        font-size: 18px;
        color: #2c3e50;
        margin: 0 0 4px 0;
    }
    .monitoring-info p {
        font-size: 13px;
        color: #607080;
        margin: 0;
    }
    .monitoring-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
    }
    .monitoring-status.active {
        background-color: #d4edda;
        color: #155724;
    }
    .monitoring-status.inactive {
        background-color: #fdecea;
        color: #922b21;
    }
    .worker-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
    .worker-status.running {
        background-color: #d4edda;
        color: #155724;
    }
    .worker-status.stopped {
        background-color: #fdecea;
        color: #922b21;
    }
    .worker-status.waiting {
        background-color: #fef3cd;
        color: #856404;
    }
    .worker-status.dead {
        background-color: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
        font-weight: 700;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 56px;
        height: 28px;
        flex-shrink: 0;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 28px;
    }
    .toggle-slider::before {
        content: "";
        position: absolute;
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }
    .toggle-switch input:checked + .toggle-slider {
        background-color: #2ecc71;
    }
    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(28px);
    }
    .schedule-info {
        margin-top: 16px;
        padding: 12px 16px;
        background-color: #f8f9fa;
        border-radius: 6px;
        font-size: 13px;
        color: #607080;
        border: 1px solid #e8edf3;
    }
    .schedule-info code {
        background: #e8edf3;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 12px;
        color: #2c3e50;
    }
    #toggleStatus {
        margin-top: 10px;
        font-size: 13px;
        font-weight: 600;
        min-height: 20px;
    }
    #toggleStatus.info {
        color: #2980b9;
    }
    #toggleStatus.success {
        color: #2ecc71;
    }
    #toggleStatus.error {
        color: #e74c3c;
    }
    .btn-refresh {
        background-color: #3498db;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        margin-top: 10px;
    }
    .btn-refresh:hover {
        background-color: #2980b9;
    }
</style>
@endpush

@section('content')
<div>
    <h2>Dashboard Monitoring</h2>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3>{{ $totalDevices }}</h3>
                <p>Total Target Device</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>{{ $totalRules }}</h3>
                <p>Total Rule Forward Chaining</p>
            </div>
        </div>
    </div>

    <div class="monitoring-panel">
        <div class="monitoring-header">
            <div class="monitoring-info">
                <h4> Start Monitoring</h4>
                <p>Aktifkan atau nonaktifkan proses monitoring otomatis (Forward Chaining Engine)</p>
                <div style="margin-top: 8px;">
                    <span class="monitoring-status {{ $monitoringActive ? 'active' : 'inactive' }}" id="monitoringStatusBadge">
                        {{ $monitoringActive ? 'MONITORING AKTIF' : 'MONITORING NONAKTIF' }}
                    </span>
                    <span class="worker-status
                        {{ $workerRunning && $workerHealthy ? 'running' : ($workerRunning ? 'dead' : 'stopped') }}"
                      id="workerStatusBadge">
                        Worker:
                        @if($workerRunning && $workerHealthy)
                            Berjalan (Sehat)
                        @elseif($workerRunning && !$workerHealthy)
                            MATI / MACET!
                        @else
                            Berhenti
                        @endif
                    </span>
                </div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="monitoringToggle" {{ $monitoringActive ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div id="toggleStatus"></div>

        <div class="schedule-info">
            <strong> Cara Kerja:</strong> Saat monitoring aktif, worker process berjalan di background
            dan mengeksekusi evaluasi Forward Chaining setiap <code>1 menit</code> sekali.
            <br><br>
            <strong> Log Worker:</strong> <code>storage/logs/monitoring-worker.log</code>
            <br><br>
            <strong> Status Worker:</strong>
            @if($workerRunning && $workerHealthy)
                <span style="color: #155724; font-weight: 700;">SEHAT</span>
                (heartbeat terakhir: {{ $lastHeartbeat > 0 ? \Carbon\Carbon::createFromTimestamp($lastHeartbeat)->format('H:i:s') : '-' }})
            @elseif($workerRunning && !$workerHealthy)
                <span style="color: #721c24; font-weight: 700;">⚠️ WORKER MATI / MACET!</span>
                (heartbeat terakhir: {{ $lastHeartbeat > 0 ? \Carbon\Carbon::createFromTimestamp($lastHeartbeat)->format('H:i:s') : 'tidak pernah' }} -
                sudah lebih dari 2 menit tidak merespons)
            @else
                Worker tidak berjalan.
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const monitoringToggle = document.getElementById('monitoringToggle');
    const monitoringStatusBadge = document.getElementById('monitoringStatusBadge');
    const workerStatusBadge = document.getElementById('workerStatusBadge');
    const toggleStatus = document.getElementById('toggleStatus');
    let isToggling = false;

    monitoringToggle.addEventListener('change', function() {
        if (isToggling) {
            this.checked = !this.checked;
            return;
        }

        isToggling = true;
        const active = this.checked;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        toggleStatus.className = 'info';
        toggleStatus.textContent = active ? ' Mengaktifkan monitoring...' : ' Menonaktifkan monitoring...';

        fetch("{{ route('monitoring.toggle') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ active: active })
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Gagal.');
            return data;
        })
        .then(data => {
            if (data.success) {
                toggleStatus.className = 'success';
                toggleStatus.textContent = data.message;

                if (active) {
                    // Update badge monitoring jadi AKTIF
                    monitoringStatusBadge.textContent = 'MONITORING AKTIF';
                    monitoringStatusBadge.className = 'monitoring-status active';
                    workerStatusBadge.textContent = 'Worker: Menunggu...';
                    workerStatusBadge.className = 'worker-status waiting';

                    // Sarankan refresh untuk cek status worker
                    toggleStatus.innerHTML += ' <button class="btn-refresh" onclick="location.reload()">Refresh</button>';
                } else {
                    monitoringStatusBadge.textContent = 'MONITORING NONAKTIF';
                    monitoringStatusBadge.className = 'monitoring-status inactive';
                    workerStatusBadge.textContent = 'Worker: Berhenti';
                    workerStatusBadge.className = 'worker-status stopped';
                }
            } else {
                throw new Error(data.message || 'Gagal.');
            }
        })
        .catch(error => {
            monitoringToggle.checked = !active;
            toggleStatus.className = 'error';
            toggleStatus.textContent = 'Error: ' + error.message;
        })
        .finally(() => {
            isToggling = false;
        });
    });
</script>
@endpush
