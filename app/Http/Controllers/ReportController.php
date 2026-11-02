<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Menampilkan halaman Report Gangguan dengan pagination, search, dan date filter.
     * Logic cloned dari AlertController::index() untuk konsistensi penuh.
     */
    public function index(Request $request)
    {
        // Ambil search & filter dari request (exact sama dengan AlertController)
        $search = $request->input('search');
        $status = $request->input('status'); // 'sent', 'failed', atau null (semua)
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        // Query history alert logs dengan filter (exact clone dari AlertController)
        $reports = AlertLog::query()
            ->when($search, function ($query, $search) {
                // Search di nama pelanggan, IP, atau rule code
                $query->where(function ($q) use ($search) {
                    $q->where('device_name', 'like', "%{$search}%")
                      ->orWhere('device_ip', 'like', "%{$search}%")
                      ->orWhere('rule_code', 'like', "%{$search}%")
                      ->orWhere('rule_name', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
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
        
        // Build pagination links sebagai array agar tombol Vue bisa berpindah halaman.
        $links = [
            ['url' => $reports->previousPageUrl(), 'label' => 'Previous', 'active' => false],
        ];
        foreach (range(1, $reports->lastPage()) as $page) {
            $links[] = [
                'url' => $reports->url($page),
                'label' => (string) $page,
                'active' => $page === $reports->currentPage(),
            ];
        }
        $links[] = [
            'url' => $reports->nextPageUrl(),
            'label' => 'Next',
            'active' => false,
        ];

        $reportsData = [
            'data' => $reports->items(),
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
            'total' => $reports->total(),
            'links' => $links,
        ];

        // Render halaman Report Gangguan via Inertia
        return Inertia::render('Reports/Index', [
            'reports' => $reportsData,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
        ]);
    }
    
    /**
     * Hapus semua data report (truncate table).
     * High-risk action: irreversible data loss.
     * Require konfirmasi dari user sebelum execute.
     */
    public function clearAll()
    {
        // Truncate table (hapus semua data, reset auto-increment)
        AlertLog::truncate();
        
        // Redirect kembali dengan flash message success
        return redirect()->back()->with('success', 'Semua data report berhasil dihapus.');
    }

    /**
     * Export report gangguan ke PDF.
     * Logic cloned exact dari AlertController::exportPdf() untuk konsistensi penuh.
     */
    public function exportPdf(Request $request)
    {
        // Ambil filter dari request
        $search = $request->input('search');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Sanitize "null" string atau empty string jadi actual null
        if ($dateFrom === 'null' || $dateFrom === '') $dateFrom = null;
        if ($dateTo === 'null' || $dateTo === '') $dateTo = null;

        // Default: hanya hari ini jika tidak ada filter tanggal
        if (!$dateFrom && !$dateTo) {
            $dateFrom = today()->format('Y-m-d');
            $dateTo = today()->format('Y-m-d');
        }

        // Query history alert logs dengan filter yang sama seperti index
        // Tidak pakai pagination, ambil semua data yang match filter (maksimal 1000)
        $logs = AlertLog::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('device_name', 'like', "%{$search}%")
                      ->orWhere('device_ip', 'like', "%{$search}%")
                      ->orWhere('rule_code', 'like', "%{$search}%")
                      ->orWhere('rule_name', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('detected_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('detected_at', '<=', $dateTo);
            })
            ->latest('detected_at')
            ->limit(1000) // Maksimal 1000 data untuk performa
            ->get();

        // Generate nama file: report-gangguan-DD-MM-YYYY-to-DD-MM-YYYY.pdf
        $fromFormatted = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') : 'all';
        $toFormatted = $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d-m-Y') : 'all';
        $filename = "report-gangguan-{$fromFormatted}-to-{$toFormatted}.pdf";

        // Data untuk view PDF
        $data = [
            'logs' => $logs,
            'dateFrom' => $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '-',
            'dateTo' => $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '-',
            'generatedAt' => now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s'),
            'totalLogs' => $logs->count(),
            'search' => $search,
            'status' => $status,
        ];

        // Generate PDF dengan DomPDF
        // Paper A4 Landscape untuk kolom yang banyak
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('margin_top', 10)
            ->setOption('margin_bottom', 10)
            ->setOption('margin_left', 10)
            ->setOption('margin_right', 10);

        // Stream PDF untuk download langsung
        return $pdf->download($filename);
    }

    /**
     * API endpoint untuk statistik report hari ini.
     * Logic cloned dari AlertController::index() stats section.
     */
    public function statsToday()
    {
        $todayStats = [
            'total' => AlertLog::whereDate('detected_at', today())->count(),
            'sent' => AlertLog::whereDate('detected_at', today())->where('status', 'sent')->count(),
            'failed' => AlertLog::whereDate('detected_at', today())->where('status', 'failed')->count(),
        ];
        
        return response()->json($todayStats);
    }
}
