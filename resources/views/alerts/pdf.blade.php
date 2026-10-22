<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Report Gangguan Jaringan</title>
    <style>
        /* Reset & Base - Styling sederhana dan profesional */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #333;
            line-height: 1.3;
        }

        /* Header - Simple & Clean */
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 16pt;
            color: #333;
            margin-bottom: 3px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .header .subtitle {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }

        .header .meta {
            font-size: 8pt;
            color: #999;
        }

        /* Info Section - Minimalis */
        .info-section {
            margin-bottom: 12px;
            font-size: 8pt;
            color: #666;
            border-left: 3px solid #333;
            padding-left: 8px;
        }

        .info-section div {
            margin: 2px 0;
        }

        /* Table - Konsisten & Professional */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        thead {
            background: #333;
            color: white;
        }

        /* Font thead dibuat konsisten untuk semua kolom: size, weight, family yang sama */
        thead th {
            padding: 7px 5px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
            font-family: 'Calibri';
            border: 1px solid #333;
            line-height: 1.2;
        }

        tbody td {
            padding: 5px 5px;
            border: 1px solid #ddd;
            font-size: 8pt;
            font-family: 'Calibri';
            vertical-align: top;
            line-height: 1.3;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        tbody tr:nth-child(odd) {
            background: white;
        }

        /* Badge - Simple */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
        }

        .badge-code {
            background: #333;
            color: white;
            font-family: 'Courier New', monospace;
            padding: 2px 5px;
        }

        /* Column Widths - Optimized */
        .col-no {
            width: 30px;
            text-align: center;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-waktu {
            width: 85px;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-pelanggan {
            width: 115px;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-ip {
            width: 85px;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-device {
            width: 75px;
            font-size: 9pt;
            font-weight: bold;
            font-family: 'Calibri';
        }

        .col-rule {
            width: 115px;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-metric {
            width: 105px;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        .col-status {
            width: 60px;
            text-align: center;
            font-size: 9pt;
            font-family: 'Calibri';
        }

        /* Footer - Minimal */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            padding: 8px 0;
            border-top: 1px solid #ddd;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 9pt;
        }

        /* Text Helpers */
        .text-muted {
            color: #999;
            font-size: 7pt;
        }

        .text-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1>SIWA NETWORK</h1>
        <div class="subtitle">Report History Alert Gangguan Jaringan</div>
        <div class="meta">Dicetak: {{ $generatedAt }} WIB</div>
    </div>

    <!-- Info Section -->
    <div class="info-section">
        <div><strong>Periode:</strong> {{ $dateFrom }} s/d {{ $dateTo }} | <strong>Total:</strong>
            {{ $totalLogs }} data</div>
        @if ($search || $status)
            <div>
                @if ($search)
                    <strong>Pencarian:</strong> {{ $search }}
                @endif
                @if ($search && $status)
                    |
                @endif
                @if ($status)
                    <strong>Status:</strong>
                    {{ $status === 'sent' ? 'Terkirim' : ($status === 'failed' ? 'Gagal' : ucfirst($status)) }}
                @endif
            </div>
        @endif
    </div>

    <!-- Table Data -->
    @if ($logs->count() > 0)
        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-waktu">Waktu</th>
                    <th class="col-pelanggan">Pelanggan</th>
                    <th class="col-ip">IP Address</th>
                    <th class="col-device">Merk Device</th>
                    <th class="col-rule">Rule</th>
                    <th class="col-metric">Metric</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $index => $log)
                    <tr>
                        <!-- No -->
                        <td class="col-no">{{ $index + 1 }}</td>

                        <!-- Waktu -->
                        <td class="col-waktu">
                            {{ $log->detected_at ? $log->detected_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}
                        </td>

                        <!-- Pelanggan -->
                        <td class="col-pelanggan">
                            <div>{{ $log->device_name }}</div>
                            <div style="font-size: 7pt; color: #333;">{{ $log->customer_id }}</div>
                        </td>

                        <!-- IP Address -->
                        <td class="col-ip" style="font-family: 'Calibri'; font-size: 9pt;">
                            {{ $log->device_ip }}</td>

                        <!-- Merk Device -->
                        <td class="col-device">{{ $log->device_model ?: '-' }}</td>

                        <!-- Rule -->
                        <td class="col-rule">
                            <span class="badge badge-code">{{ $log->rule_code }}</span>
                            <div style="margin-top: 2px;">{{ $log->rule_name }}</div>
                        </td>

                        <!-- Metric -->
                        <td class="col-metric">
                            @if ($log->bandwidth_actual)
                                <div>BW: {{ $log->bandwidth_actual }} Mbps</div>
                            @endif
                            @if ($log->latency_actual)
                                <div>Lat: {{ $log->latency_actual }} ms</div>
                            @endif
                            @if ($log->packet_loss_actual)
                                <div>PL: {{ $log->packet_loss_actual }} %</div>
                            @endif
                            @if (!$log->bandwidth_actual && !$log->latency_actual && !$log->packet_loss_actual)
                                -
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="col-status">
                            @if ($log->status === 'sent')
                                <span class="badge badge-success">Terkirim</span>
                            @elseif($log->status === 'failed')
                                <span class="badge badge-danger">Gagal</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            Tidak ada data alert untuk periode yang dipilih.
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        SIWA NETWORK - Sistem Monitoring Gangguan Jaringan
    </div>

    <!-- Page Number Script -->
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
