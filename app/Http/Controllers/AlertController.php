<?php

namespace App\Http\Controllers;

use App\Models\AlertSetting;
use App\Models\AlertLog;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class AlertController extends Controller
{
    public function __construct(private readonly AlertService $alertService)
    {
    }

    /**
     * Menampilkan halaman utama Manajemen Alert.
     * Berisi form konfigurasi + history alert logs.
     */
    public function index(Request $request)
    {
        // Ambil konfigurasi alert (jika ada)
        $config = AlertSetting::getConfig();

        // Ambil search & filter dari request
        $search = $request->input('search');
        $status = $request->input('status'); // 'sent', 'failed', atau null (semua)
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Query history alert logs dengan filter
        $logs = AlertLog::query()
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

        // Statistik hari ini
        $todayStats = [
            'total' => AlertLog::whereDate('detected_at', today())->count(),
            'sent' => AlertLog::whereDate('detected_at', today())->where('status', 'sent')->count(),
            'failed' => AlertLog::whereDate('detected_at', today())->where('status', 'failed')->count(),
        ];

        return Inertia::render('Alerts/Index', compact(
            'config',
            'logs',
            'search',
            'status',
            'dateFrom',
            'dateTo',
            'todayStats'
        ));
    }

    /**
     * Simpan atau update konfigurasi alert.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'bot_token' => 'required|string',
            'chat_id' => 'required|string',
            'deduplication_ttl' => 'nullable|integer|min:60|max:3600', // 1 menit - 1 jam
            'retry_attempts' => 'nullable|integer|min:1|max:10',
            'retry_delay' => 'nullable|integer|min:1|max:60',
        ], [
            'bot_token.required' => 'Bot Token wajib diisi.',
            'chat_id.required' => 'Chat ID wajib diisi.',
            'deduplication_ttl.min' => 'Deduplikasi TTL minimal 60 detik.',
            'deduplication_ttl.max' => 'Deduplikasi TTL maksimal 3600 detik (1 jam).',
        ]);

        try {
            // Cek apakah sudah ada config (update) atau buat baru (create)
            $config = AlertSetting::getConfig();

            if ($config) {
                // Update existing config
                $config->update([
                    'bot_token' => $validated['bot_token'],
                    'chat_id' => $validated['chat_id'],
                    'deduplication_ttl' => $validated['deduplication_ttl'] ?? 600,
                    'retry_attempts' => $validated['retry_attempts'] ?? 3,
                    'retry_delay' => $validated['retry_delay'] ?? 5,
                ]);

                return redirect()->route('alerts.index')
                    ->with('success', 'Konfigurasi alert berhasil diperbarui!');
            } else {
                // Create new config
                AlertSetting::create([
                    'bot_token' => $validated['bot_token'],
                    'chat_id' => $validated['chat_id'],
                    'is_active' => false, // Default nonaktif, user harus toggle manual
                    'deduplication_ttl' => $validated['deduplication_ttl'] ?? 600,
                    'retry_attempts' => $validated['retry_attempts'] ?? 3,
                    'retry_delay' => $validated['retry_delay'] ?? 5,
                ]);

                return redirect()->route('alerts.index')
                    ->with('success', 'Konfigurasi alert berhasil disimpan! Aktifkan toggle untuk mulai menerima alert.');
            }
        } catch (\Exception $e) {
            Log::error("AlertController: Gagal simpan config - " . $e->getMessage());
            
            return back()
                ->withErrors(['config' => 'Gagal menyimpan konfigurasi: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Toggle ON/OFF alert system via AJAX.
     */
    public function toggle(Request $request)
    {
        $request->validate(['active' => 'required|boolean']);
        $active = $request->boolean('active');

        try {
            $config = AlertSetting::getConfig();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Konfigurasi alert belum disetup. Silakan isi Bot Token dan Chat ID terlebih dahulu.',
                ], 400);
            }

            // Update status is_active
            $config->update(['is_active' => $active]);

            Log::info("AlertController: Alert system " . ($active ? 'diaktifkan' : 'dinonaktifkan'));

            return response()->json([
                'success' => true,
                'is_active' => $active,
                'message' => 'Alert system berhasil ' . ($active ? 'diaktifkan' : 'dinonaktifkan') . '.',
            ]);
        } catch (\Exception $e) {
            Log::error("AlertController: Gagal toggle alert - " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status alert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test koneksi ke Telegram API (validasi bot token).
     */
    public function testConnection(Request $request)
    {
        $request->validate(['bot_token' => 'required|string']);

        $result = $this->alertService->testConnection($request->input('bot_token'));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'bot_info' => $result['bot_info'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
    }

    /**
     * Kirim test alert ke Telegram (validasi chat ID).
     */
    public function sendTestAlert(Request $request)
    {
        $request->validate([
            'bot_token' => 'required|string',
            'chat_id' => 'required|string',
        ]);

        $result = $this->alertService->sendTestAlert(
            $request->input('bot_token'),
            $request->input('chat_id')
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
    }

    /**
     * Kirim ulang alert yang failed.
     */
    public function resend(AlertLog $alertLog)
    {
        // Validasi: hanya bisa resend alert yang failed
        if ($alertLog->status !== 'failed') {
            return back()->withErrors(['alert' => 'Hanya alert yang gagal yang bisa dikirim ulang.']);
        }

        try {
            $sent = $this->alertService->resendAlert($alertLog);

            if ($sent) {
                return back()->with('success', 'Alert berhasil dikirim ulang!');
            } else {
                return back()->withErrors(['alert' => 'Gagal mengirim ulang alert. Silakan cek konfigurasi Telegram.']);
            }
        } catch (\Exception $e) {
            Log::error("AlertController: Gagal resend alert - " . $e->getMessage());
            
            return back()->withErrors(['alert' => 'Gagal mengirim ulang alert: ' . $e->getMessage()]);
        }
    }

    /**
     * Tampilkan detail alert log dalam modal atau halaman terpisah.
     */
    public function show(AlertLog $alertLog)
    {
        // Load relasi untuk detail lengkap
        $alertLog->load(['device', 'rule']);

        return response()->json([
            'success' => true,
            'log' => $alertLog,
        ]);
    }

    /**
     * Export history alert ke PDF.
     * Format: report-gangguan-DD-MM-YYYY-to-DD-MM-YYYY.pdf
     * Default: hari ini, maksimal 1000 data.
     */
    public function exportPdf(Request $request)
    {
        // Ambil filter dari request
        $search = $request->input('search');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

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
        $pdf = Pdf::loadView('alerts.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('margin_top', 10)
            ->setOption('margin_bottom', 10)
            ->setOption('margin_left', 10)
            ->setOption('margin_right', 10);

        // Stream PDF untuk download langsung
        return $pdf->download($filename);
    }
}
