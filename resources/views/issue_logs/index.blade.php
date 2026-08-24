<!-- Panggil Master Layout -->
@extends('layouts.app')

@push('styles')
<style>
    .table-panel { background: white; padding: 22px; border-radius: 6px; box-shadow: 0 6px 18px rgba(44,62,80,0.08); margin-top: 20px; border: 1px solid #e8edf3; }
    .table-panel h3 { margin-bottom: 16px; color: #2c3e50; font-size: 18px; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; }
    th, td { text-align: left; padding: 13px 14px; border-bottom: 1px solid #e8edf3; font-size: 14px; vertical-align: middle; white-space: nowrap; }
    th { background-color: #2c3e50; color: white; font-size: 13px; text-transform: uppercase; letter-spacing: 0; }
    th:first-child { border-top-left-radius: 6px; }
    th:last-child { border-top-right-radius: 6px; }
    tbody tr:hover { background-color: #f8fbff; }
    tbody tr:last-child td { border-bottom: none; }
    .cell-strong { color: #243447; }
    .cell-ip { font-family: Consolas, 'Courier New', monospace; color: #1f618d; font-weight: 600; }
    .badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .badge-code { display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #e8edf3; color: #2c3e50; font-size: 11px; font-weight: 600; }
    .metric-good { color: #1e844a; font-weight: 600; }
    .metric-bad { color: #c0392b; font-weight: 600; }
    .table-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
    .search-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .table-search { width: 260px; max-width: 100%; padding: 9px 10px; border: 1px solid #cfd8e3; border-radius: 4px; }
    .btn-search { background-color: #3498db; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
    .btn-search:hover { background-color: #2980b9; }
    .btn-clear { background-color: #95a5a6; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; }
    .btn-clear:hover { background-color: #7f8c8d; }
    .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filter-form select, .filter-form input[type="date"] { padding: 8px 10px; border: 1px solid #cdd5db; border-radius: 4px; font-size: 13px; }
    .filter-form button { background-color: #27ae60; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
    .filter-form button:hover { background-color: #219653; }
    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e8edf3; flex-wrap: wrap; gap: 10px; }
    .pagination-info { color: #607080; font-size: 13px; }
    .pagination { display: flex; list-style: none; }
    .pagination li { margin-left: 4px; }
    .pagination li a { display: block; padding: 8px 13px; background: #ecf0f1; color: #2c3e50; text-decoration: none; border-radius: 4px; font-size: 13px; }
    .pagination li a:hover { background: #d5dbdb; }
    .pagination li.active span { display: block; padding: 8px 13px; background: #2c3e50; color: white; border-radius: 4px; font-size: 13px; }
    .pagination li.disabled span { display: block; padding: 8px 13px; background: #f4f6f7; color: #95a5a6; border-radius: 4px; font-size: 13px; }
    .empty-row { text-align: center; color: #607080; padding: 18px; }
    .action-btn { background: none; border: none; cursor: pointer; padding: 5px 8px; font-size: 12px; color: #2c3e50; }
    .action-btn:hover { text-decoration: underline; }
    .maps-link { color: #1f618d; text-decoration: none; font-size: 12px; }
    .maps-link:hover { text-decoration: underline; }
    .no-maps { color: #95a5a6; font-size: 12px; }
    .auto-refresh-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 13px; color: #607080; }
    .auto-refresh-indicator { display: inline-flex; align-items: center; gap: 6px; }
    .refresh-dot { width: 8px; height: 8px; border-radius: 50%; background-color: #27ae60; display: inline-block; }
    .refresh-dot.updating { background-color: #f39c12; animation: pulse 1s infinite; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    .btn-refresh-manual { background: none; border: 1px solid #3498db; color: #3498db; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
    .btn-refresh-manual:hover { background-color: #3498db; color: white; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; }
    .modal-content { background: white; border-radius: 6px; width: 90%; max-width: 420px; margin: 100px auto; padding: 24px; box-shadow: 0 6px 18px rgba(44,62,80,0.2); }
    .modal h4 { margin-bottom: 15px; color: #2c3e50; }
    .modal textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; min-height: 100px; }
    .modal .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; }
    .modal .btn-cancel { background-color: #95a5a6; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
    .modal .btn-save { background-color: #27ae60; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .modal .btn-cancel:hover { background-color: #7f8c8d; }
    .modal .btn-save:hover { background-color: #219653; }
    .status-loading { display: none; color: #e67e22; font-size: 13px; margin-left: 10px; }
</style>
@endpush

@section('content')
<div class="table-panel">
    <div class="table-toolbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <h3 style="margin: 0;">Log Gangguan Terdeteksi</h3>
            <div class="search-form">
                <form method="GET" action="{{ route('issue-logs.index') }}">
                    <input type="text" name="search" class="table-search" placeholder="Cari: nama pelanggan, IP, kode rule..." value="{{ request('search') }}" onKeyPress="handleSearchEnter(event)">
                    @if (request('date_from'))
                        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
                    @endif
                    @if (request('date_to'))
                        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
                    @endif
                </form>
            </div>
        </div>
    </div>

    <!-- Auto-refresh indicator: data akan refresh otomatis tiap 30 detik -->
    <div class="auto-refresh-bar">
        <span class="refresh-dot" id="refreshDot"></span>
        <span>Auto-refresh setiap 60 detik</span>
        <span style="color: #bdc3c7;">|</span>
        <span>Terakhir update: <strong id="lastUpdated">-</strong></span>
        <button class="btn-refresh-manual" id="btnRefreshManual">Refresh Manual</button>
    </div>
    </div>

    <form method="GET" action="{{ route('issue-logs.index') }}" class="filter-form">
        @if (request('search'))
            <input type="hidden" name="search" value="{{ request('search') }}">
        @endif
        <input type="date" name="date_from" value="{{ request('date_from') }}">
        <input type="date" name="date_to" value="{{ request('date_to') }}">
        <button type="submit">Filter</button>
        <a href="{{ route('issue-logs.index') }}" class="btn-clear">Clear</a>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pelanggan / IP</th>
                    <th>Device</th>
                    <th>Rule</th>
                    <th>Metrics</th>
                    <th>Lokasi</th>
                </tr>
            </thead>
            <tbody id="issue-logs-tbody">
                @forelse ($logs as $log)
                <tr data-log-id="{{ $log->id }}">
                    <td class="cell-strong">{{ $log->detected_at->format('d M Y H:i') }}</td>
                    <td>
                        <div class="cell-strong">{{ $log->device->customer_name }}</div>
                        <div class="cell-ip">{{ $log->device->ip_address }}</div>
                    </td>
                    <td>{{ $log->device->device_model ?? '-' }}</td>
                    <td>
                        <span class="badge-code">{{ $log->rule->code }}</span>
                        <small style="color: #7f8c8d;">{{ strlen($log->rule->name) > 30 ? substr($log->rule->name, 0, 30) . "..." : $log->rule->name }}</small>
                    </td>
                    <td>
                        @php
                            // Ambil threshold dari rule conditions (dynamic, bukan hardcoded)
                            $thresholds = [];
                            foreach ($log->rule->conditions as $cond) {
                                if (!isset($thresholds[$cond->parameter]) || $cond->value > $thresholds[$cond->parameter]) {
                                    $thresholds[$cond->parameter] = $cond->value;
                                }
                            }
                            $bwThreshold = $thresholds["bandwidth"] ?? 80;
                            $latThreshold = $thresholds["latency"] ?? 50;
                            $plThreshold = $thresholds["packet_loss"] ?? 5;
                        @endphp
                        @if ($log->bandwidth_actual)
                            <div class="{{ $log->bandwidth_actual > $bwThreshold ? 'metric-bad' : 'metric-good' }}">
                                BW: {{ $log->bandwidth_actual }} Mbps @if($log->bandwidth_actual > $bwThreshold) (threshold: >{{ $bwThreshold }}) @endif
                            </div>
                        @endif
                        @if ($log->latency_actual)
                            <div class="{{ $log->latency_actual > $latThreshold ? 'metric-bad' : 'metric-good' }}">
                                Lat: {{ $log->latency_actual }} ms @if($log->latency_actual > $latThreshold) (threshold: >{{ $latThreshold }}) @endif
                            </div>
                        @endif
                        @if ($log->packet_loss_actual)
                            <div class="{{ $log->packet_loss_actual > $plThreshold ? 'metric-bad' : 'metric-good' }}">
                                PL: {{ $log->packet_loss_actual }}% @if($log->packet_loss_actual > $plThreshold) (threshold: >{{ $plThreshold }}) @endif
                            </div>
                        @endif
                        @if (!$log->bandwidth_actual && !$log->latency_actual && !$log->packet_loss_actual)
                            <span style="color: #95a5a6;">-</span>
                        @endif
                    </td>
                     <!-- Kolom Status & Catatan diganti dengan Lokasi (maps_url dari device) -->
                     <td>
                         @if ($log->maps_url)
                             <a href="{{ $log->maps_url }}" target="_blank" class="maps-link">Buka Maps</a>
                         @else
                             <span class="no-maps">Tidak ada lokasi</span>
                         @endif
                     </td>
                 </tr>
                @empty
                <tr>
                    <td colspan="6" class="empty-row">Belum ada log gangguan terdeteksi. Pastikan device dan rule sudah dikonfigurasi.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->lastPage() > 1)
    <div class="pagination-wrapper">
        <div class="pagination-info">Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</div>
        <ul class="pagination">
            @if ($logs->onFirstPage())
                <li class="disabled"><span>&laquo; Prev</span></li>
            @else
                <li><a href="{{ $logs->previousPageUrl() }}">&laquo; Prev</a></li>
            @endif
            @foreach ($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                @if ($page == $logs->currentPage())
                    <li class="active"><span>{{ $page }}</span></li>
                @else
                    <li><a href="{{ $url }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($logs->hasMorePages())
                <li><a href="{{ $logs->nextPageUrl() }}">Next &raquo;</a></li>
            @else
                <li class="disabled"><span>Next &raquo;</span></li>
            @endif
        </ul>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tbody = document.getElementById('issue-logs-tbody');
        const lastUpdatedEl = document.getElementById('lastUpdated');
        const refreshDot = document.getElementById('refreshDot');
        const btnRefreshManual = document.getElementById('btnRefreshManual');
        const REFRESH_INTERVAL = 60000; // 1 menit — dijauhkan dari worker interval (30 detik) biar tidak bentrok

        // Ambil nilai filter saat ini dari form
        function getCurrentFilters() {
            const filterForm = document.querySelector('.filter-form');
            const searchInput = document.querySelector('input[name="search"]');
            return {
                search: searchInput ? searchInput.value : '',
                date_from: filterForm.querySelector('input[name="date_from"]').value,
                date_to: filterForm.querySelector('input[name="date_to"]').value,
            };
        }

        // Build HTML untuk satu baris log (mengikuti struktur Blade template)
        function renderRow(log) {
            const rule = log.rule || { code: '-', name: '-', thresholds: {} };
            const device = log.device || { customer_name: '-', ip_address: '-', device_model: '-', maps_url: null };

            const thresholds = rule.thresholds || {};
            const bwThreshold = thresholds.bandwidth !== undefined ? thresholds.bandwidth : 80;
            const latThreshold = thresholds.latency !== undefined ? thresholds.latency : 50;
            const plThreshold = thresholds.packet_loss !== undefined ? thresholds.packet_loss : 5;

            // Metrics cells — gunakan != null (loose) agar catch null DAN undefined
            let metricsHtml = '';
            if (log.bandwidth_actual != null) {
                const isBad = parseFloat(log.bandwidth_actual) > bwThreshold;
                metricsHtml += '<div class="' + (isBad ? 'metric-bad' : 'metric-good') + '">BW: ' +
                    log.bandwidth_actual + ' Mbps' + (isBad ? ' (threshold: >' + bwThreshold + ')' : '') + '</div>';
            }
            if (log.latency_actual != null) {
                const isBad = parseFloat(log.latency_actual) > latThreshold;
                metricsHtml += '<div class="' + (isBad ? 'metric-bad' : 'metric-good') + '">Lat: '
                    + log.latency_actual + ' ms' + (isBad ? ' (threshold: >' + latThreshold + ')' : '') + '</div>';
            }
            if (log.packet_loss_actual != null) {
                const isBad = parseFloat(log.packet_loss_actual) > plThreshold;
                metricsHtml += '<div class="' + (isBad ? 'metric-bad' : 'metric-good') + '">PL: '
                    + log.packet_loss_actual + '%' + (isBad ? ' (threshold: >' + plThreshold + ')' : '') + '</div>';
            }
            if (log.bandwidth_actual == null && log.latency_actual == null && log.packet_loss_actual == null) {
                metricsHtml = '<span style="color: #95a5a6;">-</span>';
            }

            // Rule name (truncate to 30 chars)
            const ruleName = rule.name ? (rule.name.length > 30 ? rule.name.substring(0, 30) + '...' : rule.name) : '-';

            // Maps URL
            const mapsUrl = device.maps_url;
            const lokasiHtml = mapsUrl ? '<a href="' + mapsUrl + '" target="_blank" class="maps-link">Buka Maps</a>' : '<span class="no-maps">Tidak ada lokasi</span>';

            return '<tr data-log-id="' + log.id + '">' +
                '<td class="cell-strong">' + (log.detected_at || '-') + '</td>' +
                '<td><div class="cell-strong">' + (device.customer_name || '-') + '</div>' +
                '<div class="cell-ip">' + (device.ip_address || '-') + '</div></td>' +
                '<td>' + (device.device_model || '-') + '</td>' +
                '<td><span class="badge-code">' + (rule.code || '-') + '</span>' +
                '<small style="color: #7f8c8d;">' + ruleName + '</small></td>' +
                '<td>' + metricsHtml + '</td>' +
                '<td>' + lokasiHtml + '</td>' +
                '</tr>';
        }

        // Fetch data dari API dan update tabel
        function refreshIssueLogs() {
            const filters = getCurrentFilters();
            const params = new URLSearchParams();
            if (filters.search) params.set('search', filters.search);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);

            refreshDot.classList.add('updating');

            fetch('{{ route('api.issue-logs.index') }}?' + params.toString())
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                    return r.json();
                })
                .then(data => {
                    // Render rows
                    if (data.logs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Tidak ada log gangguan terdeteksi untuk filter ini.</td></tr>';
                    } else {
                        tbody.innerHTML = data.logs.map(renderRow).join('');
                    }

                    lastUpdatedEl.textContent = new Date(data.updated_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
                })
                .catch(err => {
                    console.error('Gagal fetch issue logs:', err);
                    lastUpdatedEl.textContent = 'Error: ' + err.message;
                })
                .finally(() => {
                    refreshDot.classList.remove('updating');
                });
        }

        // Manual refresh
        btnRefreshManual.addEventListener('click', refreshIssueLogs);

        // Auto-refresh tiap 60 detik (dijauhkan dari worker interval 30 detik)
        setInterval(refreshIssueLogs, REFRESH_INTERVAL);

        // Set timestamp dari server saat pertama load
        lastUpdatedEl.textContent = new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', second: '2-digit'});

        // Handle Enter key pada pencarian — gabungkan dengan filter tanggal
        window.handleSearchEnter = function(e) {
            if (e.key === 'Enter') {
                const input = e.target;
                const searchVal = input.value;
                const filterForm = document.querySelector('.filter-form');
                const dateFrom = filterForm.querySelector('input[name="date_from"]').value;
                const dateTo = filterForm.querySelector('input[name="date_to"]').value;
                const params = new URLSearchParams();
                if (searchVal) params.set('search', searchVal);
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);
                window.location.href = `{{ route('issue-logs.index') }}?${params.toString()}`;
            }
        };
    });
</script>
@endpush




