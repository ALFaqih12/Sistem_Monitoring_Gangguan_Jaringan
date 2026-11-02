<?php

namespace App\Http\Controllers;

use App\Models\IssueLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IssueLogController extends Controller
{
    /**
     * Menampilkan halaman Log Gangguan dengan pencarian dan scrollable table.
     *
     * Default filter: 7 hari terakhir jika tidak ada filter tanggal manual.
     * User bisa clear filter untuk lihat semua data atau set custom date range.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Default filter: 7 hari terakhir jika user tidak set date filter
        $isDefaultFilter = !$dateFrom && !$dateTo;
        if ($isDefaultFilter) {
            $dateFrom = now()->subDays(7)->format('Y-m-d');
        }

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
            ->get();

        // Total count untuk info di UI
        $total = $logs->count();

        // Migrasi pagination ke sticky scroll: return collection (bukan paginator).
        // UI Vue pakai fixed height table dengan scroll, no pagination buttons.
        return Inertia::render('IssueLogs/Index', [
            'logs' => $logs,
            'total' => $total,
            'search' => $search,
            'dateFrom' => $request->input('date_from'), // Original input (bukan yang di-override)
            'dateTo' => $dateTo,
            'isDefaultFilter' => $isDefaultFilter,
        ]);
    }

    /**
     * API endpoint — kembalikan log gangguan sebagai JSON untuk auto-refresh UI.
     *
     * Response berisi data log tanpa pagination (scrollable table).
     * Default filter: 7 hari terakhir jika tidak ada date filter.
     */
    public function indexApi(Request $request)
    {
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Default filter: 7 hari terakhir jika user tidak set date filter
        $isDefaultFilter = !$dateFrom && !$dateTo;
        if ($isDefaultFilter) {
            $dateFrom = now()->subDays(7)->format('Y-m-d');
        }

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
            ->get();

        // Format data untuk konsumsi JavaScript
        $formattedLogs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                // Format DD/MM/YYYY HH:MM (misal: 01/09/2026 15:22) — menggunakan timezone aplikasi.
                'detected_at_formatted' => $log->detected_at_formatted,
                'detected_at' => $log->detected_at_formatted,
                // Threshold per-log: nilai maksimum + operator setiap parameter dari rule conditions.
                'thresholds' => $log->thresholds ?? [
                    'bandwidth' => null,
                    'latency' => null,
                    'packet_loss' => null,
                ],
                'device' => [
                    'customer_name' => $log->device->customer_name ?? '-',
                    'ip_address' => $log->device->ip_address ?? '-',
                    'device_model' => $log->device->device_model ?? '-',
                    'maps_url' => $log->device->maps_url ?? null,
                ],
                'rule' => [
                    'code' => $log->rule->code ?? '-',
                    'name' => $log->rule->name ?? '-',
                    // Thresholds juga disertakan di nested rule untuk backward compatibility.
                    'thresholds' => collect($log->rule->conditions ?? [])
                        ->groupBy('parameter')
                        ->map(fn($items) => $items->max('value'))
                        ->toArray(),
                ],
                'bandwidth_actual' => $log->bandwidth_actual,
                'latency_actual' => $log->latency_actual,
                'packet_loss_actual' => $log->packet_loss_actual,
                // matched_metrics: boolean per parameter apakah metrics memenuhi kondisi rule.
                // Digunakan frontend untuk styling warna (merah=match, hijau=tidak).
                'matched_metrics' => $log->matched_metrics ?? [
                    'bandwidth' => false,
                    'latency' => false,
                    'packet_loss' => false,
                ],
            ];
        });

        // Response sederhana tanpa pagination meta (scrollable table)
        return response()->json([
            'data' => $formattedLogs->values()->all(),
            'total' => $logs->count(),
            'isDefaultFilter' => $isDefaultFilter,
            'filters' => [
                'search' => $search,
                'date_from' => $request->input('date_from'), // Original input
                'date_to' => $dateTo,
            ],
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
