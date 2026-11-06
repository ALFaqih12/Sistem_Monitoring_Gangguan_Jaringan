<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

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

        // Migrasi Blade -> Inertia: halaman kini dirender lewat Vue Dashboard/Index.vue.
        // Data (props) dikirim persis sama — hanya engine presentasi yang berganti
        // dari Blade ke Inertia render. Logika backend (DB/cache query) tidak berubah.
        return Inertia::render('Dashboard/Index', compact(
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

    /**
     * Check monitoring status untuk logout safeguard.
     * Return JSON status monitoring aktif atau tidak.
     */
    public function checkStatus()
    {
        return response()->json([
            'is_running' => Cache::get('monitoring_active', false)
        ]);
    }

    /**
     * Heartbeat dari frontend untuk tracking web activity.
     * Update timestamp terakhir web masih terbuka.
     * Digunakan untuk auto stop monitoring jika web tertutup >20 menit.
     */
    public function heartbeat()
    {
        if (Cache::get('monitoring_active')) {
            Cache::put('monitoring_last_heartbeat', now()->timestamp);
        }
        
        return response()->json(['success' => true]);
    }

    /**
     * Get metrics untuk monitoring dashboard display.
     * Read dari device_metrics table yang di-populate oleh CRON setiap menit.
     * 
     * Return: List device dengan metrics + summary aggregated/average + matched rule info.
     * Response time: <100ms (DB query dengan JOIN)
     */
    public function getMetrics()
    {
        $devices = Device::with('latestMetrics')->get();
        
        // Calculate summary aggregated/average
        $totalBandwidth = 0;
        $sumLatency = 0;
        $sumPacketLoss = 0;
        $count = 0;
        
        foreach ($devices as $device) {
            if ($device->latestMetrics) {
                $totalBandwidth += $device->latestMetrics->bandwidth ?? 0;
                $sumLatency += $device->latestMetrics->latency ?? 0;
                $sumPacketLoss += $device->latestMetrics->packet_loss ?? 0;
                $count++;
            }
        }
        
        $avgLatency = $count > 0 ? $sumLatency / $count : 0;
        $avgPacketLoss = $count > 0 ? $sumPacketLoss / $count : 0;
        
        // Query dengan eager loading untuk optimize N+1 query
        $devicesData = $devices->map(function($device) {
            // Get all matched rules for this device
            $issueLogs = \App\Models\IssueLog::where('device_id', $device->id)
                ->with('rule')
                ->get();
            
            $hasIssue = $issueLogs->isNotEmpty();
            
            // Format rule text based on count
            $matchedRuleText = '';
            if ($issueLogs->count() === 1) {
                // Single rule: show code + name
                $rule = $issueLogs->first()->rule;
                $matchedRuleText = "Rule {$rule->code} {$rule->name}";
            } elseif ($issueLogs->count() > 1) {
                // Multiple rules: codes only
                $codes = $issueLogs->pluck('rule.code')->join(',');
                $matchedRuleText = "Rule {$codes}";
            }
            
            // NEW: Aggregate matched metrics from all IssueLog
            $matchedMetrics = [];
            foreach ($issueLogs as $log) {
                // Get raw JSON string from database and decode manually
                $rawParams = $log->getRawOriginal('matched_parameters');
                if ($rawParams) {
                    $params = json_decode($rawParams, true);
                    if (is_array($params)) {
                        $matchedMetrics = array_merge($matchedMetrics, $params);
                    }
                }
            }
            $matchedMetrics = array_values(array_unique($matchedMetrics));
            
            // NEW: Detect stale data (offline pattern ≥2 minutes)
            $isStale = false;
            if ($device->latestMetrics) {
                $metrics = $device->latestMetrics;
                
                // Check offline pattern: packet_loss 100%, bandwidth 0, latency 0
                if ($metrics->packet_loss == 100 
                    && $metrics->bandwidth == 0 
                    && $metrics->latency == 0) {
                    
                    // Check if this condition persists ≥2 minutes
                    $minutesOld = now()->diffInMinutes($metrics->updated_at);
                    if ($minutesOld >= 2) {
                        $isStale = true;
                    }
                }
            }
            
            return [
                'id' => $device->id,
                'customer_name' => $device->customer_name,
                'ip_address' => $device->ip_address,
                'device_model' => $device->device_model,
                'maps_url' => $device->maps_url,
                'has_issue' => $hasIssue, // Status untuk indicator (red/green)
                'matched_rule_text' => $matchedRuleText, // Formatted rule text
                'matched_metrics' => $matchedMetrics, // NEW: which metrics matched
                'is_stale' => $isStale, // NEW: stale data detection
                'metrics' => $device->latestMetrics ? [
                    'bandwidth' => $device->latestMetrics->bandwidth,
                    'latency' => $device->latestMetrics->latency,
                    'packet_loss' => $device->latestMetrics->packet_loss,
                    'updated_at' => $device->latestMetrics->updated_at->diffForHumans(), // "2 menit yang lalu"
                ] : null,
            ];
        });
        
        return response()->json([
            'summary' => [
                'aggregated_bandwidth' => round($totalBandwidth, 2),
                'average_latency' => round($avgLatency, 2),
                'average_packet_loss' => round($avgPacketLoss, 2),
            ],
            'devices' => $devicesData,
        ]);
    }
}
