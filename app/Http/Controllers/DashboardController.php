<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Dashboard utama — RINGAN dan CEPAT.
     * Hanya query DB + baca cache. Tidak ada system call.
     */
    public function index()
    {
        $totalDevices = Device::count();
        $totalRules = Rule::count();
        $monitoringActive = Cache::get('monitoring_active', false);
        $workerRunning = Cache::get('monitoring_worker_running', false);

        // Ambil heartbeat terakhir dari worker.
        // Heartbeat di-update oleh MonitoringWorker setiap cycle (30 detik).
        $lastHeartbeat = Cache::get('worker_last_heartbeat', 0);

        // Hitung umur heartbeat (berapa detik sejak worker terakhir "menunjukkan tanda hidup").
        $heartbeatAge = $lastHeartbeat > 0 ? now()->timestamp - (int) $lastHeartbeat : PHP_INT_MAX;

        // Status kesehatan worker:
        // - Worker dinyatakan SEHAT jika flag running = true DAN heartbeat masih fresh
        //   (umur heartbeat <= 120 detik = 2 menit, sesuai ambang batas di MonitoringWorker).
        // - Jika flag running true tapi heartbeat sudah basi, berarti worker crash/macet
        //   dan dashboard harus memberi peringatan agar admin segera mengecek.
        $workerHealthy = $workerRunning && $heartbeatAge <= 120;

        return view('dashboard.index', compact(
            'totalDevices',
            'totalRules',
            'monitoringActive',
            'workerRunning',
            'workerHealthy',
            'lastHeartbeat',
            'heartbeatAge'
        ));
    }

    /**
     * Toggle ON/OFF monitoring via AJAX.
     */
    public function toggleMonitoring(Request $request)
    {
        $request->validate(['active' => 'required|boolean']);
        $active = $request->boolean('active');

        if ($active) {
            if (Cache::get('monitoring_worker_running', false)) {
                Cache::forever('monitoring_active', true);
                return response()->json(['success' => true, 'message' => 'Monitoring sudah aktif.', 'active' => true]);
            }

            Cache::forever('monitoring_active', true);
            $this->startWorkerAsync();

            return response()->json(['success' => true, 'message' => 'Monitoring sedang diaktifkan...', 'active' => true]);
        } else {
            $this->stopWorker();
            Cache::forever('monitoring_active', false);
            Cache::forever('monitoring_worker_running', false);

            return response()->json(['success' => true, 'message' => 'Monitoring dinonaktifkan.', 'active' => false]);
        }
    }

    /**
     * Start worker di background — popen() + pclose() = non-blocking.
     * Terbukti: return dalam 0.1 detik, process tetap jalan di background.
     */
    private function startWorkerAsync(): void
    {
        try {
            $artisanPath = base_path('artisan');
            $logPath = storage_path('logs/monitoring-worker.log');
            $pidFile = storage_path('app/monitoring-worker.pid');

            if (file_exists($pidFile)) @unlink($pidFile);

            // start /B = background process, redirect output ke log
            $cmd = 'start /B php "' . $artisanPath . '" monitoring:worker > "' . $logPath . '" 2>&1';

            // popen() return segera (0.1 detik), pclose() tidak blocking
            $handle = popen($cmd, 'r');
            if ($handle !== false) {
                pclose($handle);
            }

            Cache::forever('monitoring_worker_running', true);
            Log::info('Dashboard: Worker dimulai via popen()');
        } catch (\Throwable $e) {
            Log::error('Dashboard: Gagal startWorkerAsync - ' . $e->getMessage());
            Cache::forever('monitoring_worker_running', true);
        }
    }

    /**
     * Hentikan worker process.
     */
    private function stopWorker(): void
    {
        $pidFile = storage_path('app/monitoring-worker.pid');
        Cache::forever('monitoring_active', false);

        if (!file_exists($pidFile)) return;

        try {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0) {
                if (PHP_OS_FAMILY === 'Windows') {
                    exec("taskkill /PID {$pid} /F 2>NUL");
                } else {
                    exec("kill -9 {$pid} 2>/dev/null");
                }
            }
            if (file_exists($pidFile)) @unlink($pidFile);
        } catch (\Throwable $e) {
            Log::error('Dashboard: Gagal stopWorker - ' . $e->getMessage());
        }
    }
}
