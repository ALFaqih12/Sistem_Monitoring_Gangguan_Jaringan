<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Command untuk check monitoring timeout.
 * Jika web tertutup >20 menit, monitoring auto stop.
 * Dijalankan scheduler setiap 5 menit.
 */
class CheckMonitoringTimeout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:check-timeout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check monitoring timeout dan auto stop jika web tertutup >20 menit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check apakah monitoring aktif
        $isActive = Cache::get('monitoring_active', false);
        
        if (!$isActive) {
            // Monitoring sudah OFF, skip check
            return;
        }
        
        // Ambil timestamp heartbeat terakhir
        $lastHeartbeat = Cache::get('monitoring_last_heartbeat');
        
        if (!$lastHeartbeat) {
            // Belum ada heartbeat data (baru start), skip
            return;
        }
        
        // Calculate idle time (menit)
        $idleMinutes = (now()->timestamp - $lastHeartbeat) / 60;
        
        // Force stop jika idle >20 menit
        if ($idleMinutes > 20) {
            // Clear monitoring cache
            Cache::forget('monitoring_active');
            Cache::forget('monitoring_last_heartbeat');
            
            // Log warning untuk tracking
            Log::warning('Monitoring force stopped - Web tertutup >20 menit', [
                'idle_minutes' => round($idleMinutes, 2),
                'stopped_at' => now(),
            ]);
            
            $this->warn('Monitoring force stopped (idle >20 minutes)');
        } else {
            $this->info("Monitoring active, idle: " . round($idleMinutes, 2) . " minutes");
        }
    }
}
