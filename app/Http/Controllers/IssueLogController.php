<?php

namespace App\Http\Controllers;

use App\Models\IssueLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        // Migrasi Blade -> Inertia: halaman log dirender Vue IssueLogs/Index.vue.
        // Props (logs paginator, search, dateFrom, dateTo) dikirim sama persis.
        // Format detected_at_formatted sudah tersedia via accessor di model IssueLog.
        // Auto-refresh UI tetap lewat endpoint AJAX /api/issue-logs (indexApi).
        return Inertia::render('IssueLogs/Index', compact('logs', 'search', 'dateFrom', 'dateTo'));
    }

    /**
     * API endpoint — kembalikan log gangguan sebagai JSON untuk auto-refresh UI.
     *
     * Response berisi data log + metadata pagination agar JavaScript
     * bisa update tabel secara parsial tanpa reload halaman penuh.
     * Endpoint ini DISIMPAN karena auto-refresh UI dimigrasi ke fetch AJAX
     * (rekuesti yang disetujui), bukan menjadi REST API publik.
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

        // Format data untuk konsumsi JavaScript — struktur disamakan dengan paginator Laravel
        // agar frontend Vue (IssueLogs/Index.vue) bisa memprosesnya tunjangkau.
        $formattedLogs = $logs->through(function ($log) {
            return [
                'id' => $log->id,
                // Gunakan accessor model detected_at_formatted agar konsisten dengan Inertia.
                // Format DD/MM/YYYY HH:MM (misal: 01/09/2026 15:22) — menggunakan timezone aplikasi.
                'detected_at' => $log->detected_at_formatted,
                // Threshold per-log: nilai maksimum + operator setiap parameter dari rule conditions.
                // Ini memastikan kolom metrics frontend menampilkan threshold dari rule yang
                // benar-benar match, bukan rule pertama di halaman.
                // Format: { bandwidth: {value, operator}, latency: {...}, ... }
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
                // Format: { bandwidth: true|false, latency: true|false, packet_loss: true|false }
                'matched_metrics' => $log->matched_metrics ?? [
                    'bandwidth' => false,
                    'latency' => false,
                    'packet_loss' => false,
                ],
            ];
        });

        // Response disamakan dengan struktur paginator Laravel agar frontend bisa pakai
        // logs.data, logs.meta.last_page, logs.links secara konsisten antara initial load
        // (Inertia) dan auto-refresh (AJAX fetch).
        return response()->json([
            'data' => $formattedLogs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->currentPage() * $logs->perPage() - $logs->perPage() + 1,
                'to' => min($logs->currentPage() * $logs->perPage(), $logs->total()),
            ],
            'links' => $logs->links(), // pagination links untuk navigasi (biasanya kosong di fetch)
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
