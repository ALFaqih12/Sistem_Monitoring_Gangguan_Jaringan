<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report Gangguan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #333; line-height: 1.3; }
        .header { text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
        .header h1 { font-size: 16pt; color: #333; margin-bottom: 3px; font-weight: bold; }
        .header .subtitle { font-size: 10pt; color: #666; margin-bottom: 5px; }
        .header .meta { font-size: 8pt; color: #999; }
        .info-section { margin-bottom: 12px; font-size: 8pt; color: #666; border-left: 3px solid #333; padding-left: 8px; }
        .info-section div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        thead { background: #333; color: white; }
        thead th { padding: 7px 5px; text-align: left; font-size: 9pt; font-weight: bold; border: 1px solid #333; }
        tbody td { padding: 5px; border: 1px solid #ddd; font-size: 8pt; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        .badge-code { background: #333; color: white; font-family: 'Courier New', monospace; }
        .col-no { width: 30px; text-align: center; }
        .col-waktu { width: 85px; }
        .col-pelanggan { width: 115px; }
        .col-ip { width: 85px; }
        .col-device { width: 75px; }
        .col-rule { width: 115px; }
        .col-metric { width: 105px; }
        .col-status { width: 60px; text-align: center; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .footer { position: fixed; bottom: 0; text-align: center; font-size: 7pt; color: #999; padding: 8px 0; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SIWA NETWORK</h1>
        <div class="subtitle">Report Gangguan Jaringan</div>
        <div class="meta">Dicetak: {{ $generatedAt }} WIB</div>
    </div>

    <div class="info-section">
        <div><strong>Periode:</strong> {{ $dateFrom }} s/d {{ $dateTo }} | <strong>Total:</strong> {{ $totalLogs }} data</div>
        @if ($search)
            <div><strong>Pencarian:</strong> {{ $search }}</div>
        @endif
    </div>

    @if ($logs->count() > 0)
        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-waktu">Waktu</th>
                    <th class="col-pelanggan">Pelanggan</th>
                    <th class="col-ip">IP</th>
                    <th class="col-device">Device</th>
                    <th class="col-rule">Rule</th>
                    <th class="col-metric">Metric</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $index => $log)
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td class="col-waktu">{{ $log->detected_at ? $log->detected_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}</td>
                        <td class="col-pelanggan">
                            <div>{{ $log->device_name ?? $log->device->customer_name ?? '-' }}</div>
                            <div style="font-size: 7pt; color: #666;">{{ $log->customer_id ?? '-' }}</div>
                        </td>
                        <td class="col-ip">{{ $log->device_ip ?? $log->device->ip_address ?? '-' }}</td>
                        <td class="col-device">{{ $log->device_model ?? $log->device->device_model ?? '-' }}</td>
                        <td class="col-rule">
                            <span class="badge badge-code">{{ $log->rule_code ?? $log->rule->code ?? '-' }}</span>
                            <div style="margin-top: 2px;">{{ $log->rule_name ?? $log->rule->name ?? '-' }}</div>
                        </td>
                        <td class="col-metric">
                            @if ($log->bandwidth_actual)<div>BW: {{ $log->bandwidth_actual }} Mbps</div>@endif
                            @if ($log->latency_actual)<div>Lat: {{ $log->latency_actual }} ms</div>@endif
                            @if ($log->packet_loss_actual)<div>PL: {{ $log->packet_loss_actual }}%</div>@endif
                            @if (!$log->bandwidth_actual && !$log->latency_actual && !$log->packet_loss_actual)-@endif
                        </td>
                        <td class="col-status">
                            @if ($log->status === 'sent')
                                <span class="badge badge-success">Terkirim</span>
                            @elseif($log->status === 'failed')
                                <span class="badge badge-danger">Gagal</span>
                            @else
                                <span class="badge badge-secondary">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">Tidak ada report untuk periode yang dipilih.</div>
    @endif

    <div class="footer">SIWA NETWORK - Sistem Monitoring Gangguan Jaringan</div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "{PAGE_NUM} / {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 25;
            $pdf->page_text($x, $y, $text, $font, $size, array(0, 0, 0), 2, -2);
        }
    </script>
</body>
</html>
