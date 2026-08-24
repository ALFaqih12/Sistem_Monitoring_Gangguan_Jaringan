<?php

namespace App\Console\Commands;

use App\Services\ForwardChainingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringWorker extends Command
{
    /**
     * Nama dan signature command.
     * Digunakan untuk menjalankan: php artisan monitoring:worker
     */
    protected $signature = 'monitoring:worker';

    /**
     * Deskripsi command.
     */
    protected $description = 'Worker proses monitoring berkelanjutan. Berjalan terus-menerus selama monitoring aktif.';

    /**
     * Interval evaluasi dalam detik.
     * OPTIMIZED: 30 detik (sebelumnya 60 detik) untuk deteksi lebih cepat.
     */
    private const EVALUATION_INTERVAL = 30;

    /**
     * Ambang batas heartbeat worker dinyatakan MATI (dalam detik).
     * Jika selisih waktu sekarang dengan heartbeat terakhir > 2 menit,
     * maka worker dianggap crash/macet dan perlu dicek.
     */
    private const HEARTBEAT_STALE_THRESHOLD = 120;

    /**
     * Execute the console command.
     * Method ini berjalan dalam infinite loop selama monitoring aktif.
     * Setiap 30 detik akan menjalankan evaluasi Forward Chaining.
     */
    public function handle(ForwardChainingService $forwardChaining): int
    {
        $pid = getmypid();

        // -- 1. Tulis PID file SEGERA setelah worker mulai --
        //     Ini penting agar DashboardController bisa verifikasi worker berjalan
        $pidFile = storage_path('app/monitoring-worker.pid');
        file_put_contents($pidFile, $pid);
        $this->info("🔧 Monitoring Worker dimulai (PID: {$pid})");
        $this->line("   PID file: {$pidFile}");
        $this->line("   Interval evaluasi: " . self::EVALUATION_INTERVAL . " detik (OPTIMIZED)");

        // -- 2. Loop utama: berjalan terus sampai monitoring dimatikan --
        while (true) {
            // Cek status monitoring dari cache
            $isActive = Cache::get('monitoring_active', false);

            if (!$isActive) {
                $this->info("⏹  Monitoring dinonaktifkan. Worker berhenti.");
                Log::info("Monitoring Worker (PID: {$pid}): Berhenti karena monitoring_active = false.");
                break;
            }

            // -- 2a. Update HEARTBEAT setiap cycle --
            //     Heartbeat = penanda bahwa worker MASIH HIDUP.
            //     Dashboard memakai nilai ini untuk mendeteksi worker yang crash/macet
            //     (misal: proses mati diam-diam, maka heartbeat tidak ter-update).
            Cache::put('worker_last_heartbeat', now()->timestamp, 300);

            $this->info("🔍 Evaluasi Forward Chaining dimulai...");
            $this->line("   Waktu: " . now()->format('Y-m-d H:i:s'));

            // Track execution time untuk monitoring performance
            $startTime = microtime(true);

            try {
                // Jalankan evaluasi semua rule
                $result = $forwardChaining->evaluateAllRules();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2); // ms

                $this->info("✅ Evaluasi selesai!");
                $this->line("   Rule dicek: {$result['checked']}");
                $this->line("   Gangguan terdeteksi: " . count($result['detected']));
                $this->line("   Execution time: {$executionTime} ms");

                // Warning jika execution time > 50% dari interval (risk overlap)
                if ($executionTime > (self::EVALUATION_INTERVAL * 1000 * 0.5)) {
                    $this->warn("   ⚠️  Execution time terlalu lama! Pertimbangkan optimasi atau naikkan interval.");
                    Log::warning("MonitoringWorker: Execution time {$executionTime}ms mendekati interval " . self::EVALUATION_INTERVAL . "s");
                }

                if (count($result['detected']) > 0) {
                    foreach ($result['detected'] as $issue) {
                        $this->warn("   🚨 {$issue['rule']->code} - {$issue['rule']->name} pada {$issue['device']->ip_address}");
                    }
                }

                // Update cache untuk health check
                Cache::put('monitoring_last_execution', now()->timestamp, 300);

            } catch (\Throwable $e) {
                $this->error("❌ Error saat evaluasi: " . $e->getMessage());
                Log::error("Monitoring Worker: Error evaluasi - " . $e->getMessage());
            }

            // -- 3. Tunggu sebelum evaluasi berikutnya --
            $this->line("   ⏳ Menunggu " . self::EVALUATION_INTERVAL . " detik...");
            $this->newLine();
            sleep(self::EVALUATION_INTERVAL);
        }

        // -- 4. Bersihkan PID file saat worker berhenti --
        if (file_exists($pidFile)) {
            @unlink($pidFile);
            $this->info("🧹 PID file dihapus.");
        }

        return Command::SUCCESS;
    }
}
