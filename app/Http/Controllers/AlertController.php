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
     * Berisi form konfigurasi untuk Telegram Bot.
     */
    public function index(Request $request)
    {
        // Ambil konfigurasi alert (jika ada)
        $config = AlertSetting::getConfig();

        return Inertia::render('Alerts/Index', compact('config'));
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
}
