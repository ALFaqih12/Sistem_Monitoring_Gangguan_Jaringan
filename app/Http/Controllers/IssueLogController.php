<?php

namespace App\Http\Controllers;

use App\Models\IssueLog;
use Illuminate\Http\Request;

class IssueLogController extends Controller
{
    /**
     * Menampilkan halaman Log Gangguan dengan pencarian dan pagination.
     *
     * Kolom status dan notes sudah dihapus dari tabel issue_logs (migration 2026_08_22).
     * Sekarang hanya menampilkan: waktu, pelanggan/IP, device, rule, metrics, lokasi, aksi.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $logs = IssueLog::with(['device', 'rule.conditions'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('device', function ($dq) use ($search) {
                        $dq->where('customer_name', 'like', "%{$search}%")
                           ->orWhere('ip_address', 'like', "%{$search}%");
                    })->orWhereHas('rule', function ($rq) use ($search) {
                        $rq->where('code', 'like', "%{$search}%")
                           ->orWhere('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('detected_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('detected_at', '<=', $dateTo);
            })
            ->latest('detected_at')
            ->paginate(15)
            ->withQueryString();

        return view('issue_logs.index', compact('logs', 'search', 'dateFrom', 'dateTo'));
    }

    /**
     * API endpoint — kembalikan log gangguan sebagai JSON untuk auto-refresh UI.
     *
     * Response berisi data log + metadata pagination agar JavaScript
     * bisa update tabel secara parsial tanpa reload halaman penuh.
     */
    public function indexApi(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $logs = IssueLog::with(['device', 'rule.conditions'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('device', function ($dq) use ($search) {
                        $dq->where('customer_name', 'like', "%{$search}%")
                           ->orWhere('ip_address', 'like', "%{$search}%");
                    })->orWhereHas('rule', function ($rq) use ($search) {
                        $rq->where('code', 'like', "%{$search}%")
                           ->orWhere('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('detected_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('detected_at', '<=', $dateTo);
            })
            ->latest('detected_at')
            ->paginate(15)
            ->withQueryString();

        // Format data untuk konsumsi JavaScript
        $formattedLogs = $logs->through(function ($log) {
            return [
                'id' => $log->id,
                'detected_at' => $log->detected_at ? $log->detected_at->format('d M Y H:i') : '-',
                'device' => [
                    'customer_name' => $log->device->customer_name ?? '-',
                    'ip_address' => $log->device->ip_address ?? '-',
                    'device_model' => $log->device->device_model ?? '-',
                    'maps_url' => $log->device->maps_url ?? null,
                ],
                'rule' => [
                    'code' => $log->rule->code ?? '-',
                    'name' => $log->rule->name ?? '-',
                    // Thresholds diambil dari rule conditions (dynamic)
                    'thresholds' => collect($log->rule->conditions ?? [])
                        ->groupBy('parameter')
                        ->map(fn($items) => $items->max('value'))
                        ->toArray(),
                ],
                'bandwidth_actual' => $log->bandwidth_actual,
                'latency_actual' => $log->latency_actual,
                'packet_loss_actual' => $log->packet_loss_actual,
            ];
        });

        return response()->json([
            'logs' => $formattedLogs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
