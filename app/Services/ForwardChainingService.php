<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\Device;
use App\Models\IssueLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForwardChainingService
{
    private PrometheusService $prometheus;

    public function __construct(PrometheusService $prometheus)
    {
        $this->prometheus = $prometheus;
    }

    /**
     * Evaluasi semua rule aktif terhadap device.
     * OPTIMASI: 1 batch query Prometheus untuk SEMUA device, bukan per-device.
     * UPSERT: IssueLog di-update jika sudah ada untuk device+rule yang sama (tidak duplikat).
     * CLEANUP: IssueLog yang tidak match lagi otomatis dihapus pada akhir cycle.
     * DEDUPLIKASI ALERT: Telegram alert hanya dikirim sekali per TTL window (cache-based).
     */
    public function evaluateAllRules(): array
    {
        $detectedIssues = [];
        $rulesChecked = 0;
        // Tracking pasangan (device_id, rule_id) yang match pada cycle ini.
        // Digunakan untuk cleanup stale logs di akhir cycle.
        $matchedPairs = [];

        $activeRules = Rule::where('is_active', true)
            ->with(['conditions', 'targetDevice'])
            ->get();

        if ($activeRules->isEmpty()) {
            // Tidak ada rule aktif -> semua issue logs sudah tidak relevan lagi
            $this->cleanupStaleLogs([]);
            return ['detected' => [], 'checked' => 0];
        }

        $devices = Device::all();
        if ($devices->isEmpty()) {
            $this->cleanupStaleLogs([]);
            return ['detected' => [], 'checked' => 0];
        }

        // Kumpulkan semua IP yang diperlukan untuk batch query Prometheus
        $requiredIps = [];
        foreach ($activeRules as $rule) {
            if ($rule->target_type === 'all') {
                foreach ($devices as $device) {
                    $requiredIps[$device->ip_address] = true;
                }
            } else {
                if ($rule->targetDevice) {
                    $requiredIps[$rule->targetDevice->ip_address] = true;
                }
            }
        }

        // 1 batch query Prometheus untuk semua IP sekaligus
        $batchMetrics = $this->prometheus->getAllMetricsBatch(array_keys($requiredIps));

        foreach ($activeRules as $rule) {
            $devicesToCheck = $this->getDevicesToCheck($rule, $devices);

            foreach ($devicesToCheck as $device) {
                $rulesChecked++;
                
                // Switch query method berdasarkan device source:
                // - 'manual': query SNMP langsung ke device (method lama)
                // - 'discovery': query SNMP ke Core Router untuk Simple Queue counter
                if ($device->source === 'discovery' && $device->core_router_ip) {
                    // Device dari discovery: bandwidth via Simple Queue Core Router
                    $metrics = $this->prometheus->getAllMetricsWithSimpleQueue(
                        $device->ip_address,
                        $device->core_router_ip
                    );
                    
                    Log::debug("ForwardChaining: Metrics via Simple Queue Core Router", [
                        'device_ip' => $device->ip_address,
                        'core_router_ip' => $device->core_router_ip,
                        'metrics' => $metrics,
                    ]);
                } else {
                    // Device manual atau fallback: gunakan batch metrics (method lama)
                    $metrics = $batchMetrics[$device->ip_address] ?? [
                        'bandwidth' => null, 'latency' => null, 'packet_loss' => null,
                    ];
                }

                $result = $this->evaluateRuleForDevice($rule, $device, $metrics);

                if ($result['match']) {
                    // Catat pasangan (device_id, rule_id) ini sebagai match
                    $matchedPairs[] = $device->id . ':' . $rule->id;

                    // UPSERT: Selalu update log yang sudah ada atau buat baru.
                    // - Jika match dengan rule YANG SAMA berulang kali pada device yang sama,
                    //   update metrics + waktu terbaru pada baris yang sudah ada.
                    // - Jika match dengan rule YANG BERBEDA, buat baris baru.
                    $this->saveIssueLog($device, $rule, $result);

                    $detectedIssues[] = $result;

                    Log::info("ForwardChaining: Gangguan terdeteksi!", [
                        'rule_code' => $rule->code,
                        'rule_name' => $rule->name,
                        'device_ip' => $device->ip_address,
                        'device_name' => $device->customer_name,
                        'metrics' => $result['metrics'],
                    ]);

                    // Alert deduplication — hanya mengontrol apakah Telegram alert dikirim.
                    // Jika return false, berarti alert sudah pernah dikirim dalam TTL window.
                    // IssueLog tetap di-update (di atas) walau alert tidak dikirim.
                    if ($this->tryAlert($device, $rule, $result)) {
                        // Kirim alert ke Telegram via AlertService
                        try {
                            $alertService = app(\App\Services\AlertService::class);
                            $alertService->sendAlert($device, $rule, $result);
                        } catch (\Exception $e) {
                            Log::error("ForwardChaining: Gagal kirim alert ke Telegram - " . $e->getMessage(), [
                                'device_ip' => $device->ip_address,
                                'rule_code' => $rule->code,
                            ]);
                        }

                        Log::debug("ForwardChaining: Alert marked for device {$device->ip_address}, rule {$rule->code}", [
                            'rule_code' => $rule->code,
                            'device_ip' => $device->ip_address,
                        ]);
                    } else {
                        Log::debug("ForwardChaining: Alert deduped (within TTL) for device {$device->ip_address}, rule {$rule->code}", [
                            'rule_code' => $rule->code,
                            'device_ip' => $device->ip_address,
                        ]);
                    }
                }
            }
        }

        // Bersihkan log gangguan yang tidak lagi match dengan rule manapun.
        // Jika traffic berubah dan tidak memenuhi syarat rule, log otomatis dihapus.
        $this->cleanupStaleLogs($matchedPairs);

        return [
            'detected' => $detectedIssues,
            'checked' => $rulesChecked,
        ];
    }

    /**
     * Simpan (atau update) log gangguan ke database — UPSERT.
     *
     * Logika:
     * - Cari IssueLog yang sudah ada untuk kombinasi device_id + rule_id yang sama.
     * - Jika sudah ada: UPDATE metrics + detected_at + maps_url (jangan buat baru).
     *   Hal ini mencegah spam baris duplikat ketika rule yang sama match berulang kali.
     * - Jika belum ada: buat baris baru (hanya untuk kombinasi device+rule yang belum pernah tercatat).
     *
     * Kolom maps_url diisi otomatis dari device yang bersangkutan, sekaligus
     * mengganti kolom status/notes yang sudah dihapus dari tabel issue_logs.
     */
    private function saveIssueLog(Device $device, Rule $rule, array $result): void
    {
        try {
            DB::transaction(function () use ($device, $rule, $result) {
                // Cari log yang sudah ada untuk device + rule yang sama (dengan lock)
                $existingLog = IssueLog::where('device_id', $device->id)
                    ->where('rule_id', $rule->id)
                    ->lockForUpdate()
                    ->first();

                $logData = [
                    'bandwidth_actual' => $result['metrics']['bandwidth'],
                    'latency_actual' => $result['metrics']['latency'],
                    'packet_loss_actual' => $result['metrics']['packet_loss'],
                    'maps_url' => $device->maps_url,
                    'detected_at' => now(),
                ];

                if ($existingLog) {
                    // Update baris yang sudah ada — jangan buat baris baru
                    $existingLog->update($logData);
                    Log::debug("IssueLog updated (upsert) for device_id={$device->id}, rule_id={$rule->id}.", [
                        'device_ip' => $device->ip_address,
                        'rule_code' => $rule->code,
                    ]);
                } else {
                    // Buat baris baru hanya jika belum ada log untuk device+rule ini
                    IssueLog::create(array_merge([
                        'device_id' => $device->id,
                        'rule_id' => $rule->id,
                    ], $logData));
                    Log::debug("New IssueLog created for device_id={$device->id}, rule_id={$rule->id}.", [
                        'device_ip' => $device->ip_address,
                        'rule_code' => $rule->code,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error("Gagal simpan/update issue log: " . $e->getMessage(), [
                'device_id' => $device->id,
                'rule_id' => $rule->id,
                'device_ip' => $device->ip_address,
                'rule_code' => $rule->code,
            ]);
        }
    }

    /**
     * Hapus log gangguan yang tidak lagi match dengan rule manapun.
     *
     * Dipanggil setelah setiap cycle evaluasi. Jika traffic berubah dan
     * tidak memenuhi syarat rule lagi, baris IssueLog yang bersangkutan
     * akan dihapus secara otomatis agar tabel log tidak menumpuk data ketinggalan.
     *
     * @param array $matchedPairs Array of string "device_id:rule_id" yang masih match pada cycle ini.
     *                            Jika kosong, semua issue logs dianggap stale dan dihapus.
     */
    private function cleanupStaleLogs(array $matchedPairs): void
    {
        try {
            // Jika tidak ada pasangan yang match sama sekali, hapus semua issue logs
            if (empty($matchedPairs)) {
                $count = IssueLog::count();
                if ($count > 0) {
                    IssueLog::query()->delete();
                    Log::info("ForwardChaining: Cleaned up {$count} stale issue logs (tidak ada rule yang match).");
                }
                return;
            }

            // Kumpulkan ID log yang harus dihapus (device_id:rule_id tidak ada di matchedPairs)
            $staleIds = IssueLog::all(['id', 'device_id', 'rule_id'])
                ->filter(function ($log) use ($matchedPairs) {
                    return !in_array($log->device_id . ':' . $log->rule_id, $matchedPairs, true);
                })
                ->pluck('id')
                ->toArray();

            if (!empty($staleIds)) {
                $deleted = IssueLog::whereIn('id', $staleIds)->delete();
                Log::info("ForwardChaining: Cleaned up {$deleted} stale issue logs (traffic tidak match rule lagi).");
            }
        } catch (\Throwable $e) {
            Log::error("Gagal cleanup stale logs: " . $e->getMessage());
        }
    }

    /**
     * Coba tandai gangguan sebagai alert (atomic) untuk deduplikasi Telegram.
     * Menggunakan Cache::add() yang HANYA menambah jika key BELUM ada.
     * Ini mencegah race condition: 2 worker bersamaan tidak bisa lolup keduanya.
     *
     * NOTE: Method ini HANYA mengontrol apakah alert dikirim, bukan apakah
     * IssueLog disimpan. IssueLog selalu di-upsert (update atau create)
     * setiap kali match terdeteksi, sementara alert hanya dikirim sekali per TTL.
     *
     * @param Device $device
     * @param Rule $rule
     * @param array $result
     * @return bool True jika berhasil tandai (perlu kirim alert), False jika sudah pernah alert dalam TTL
     */
    private function tryAlert(Device $device, Rule $rule, array $result): bool
    {
        $cacheKey = $this->generateDeduplicationKey($device, $rule, $result);
        $ttl = env('ALERT_DEDUPLICATION_TTL', 600);

        $alertData = [
            'device_id' => $device->id,
            'device_ip' => $device->ip_address,
            'rule_id' => $rule->id,
            'rule_code' => $rule->code,
            'metrics' => $result['metrics'],
            'alerted_at' => now()->toDateTimeString(),
        ];

        // Cache::add() adalah ATOMIC: hanya true jika key belum ada
        // Jika true -> kita yang pertama, lanjutkan alert + simpan log
        // Jika false -> worker lain sudah tandai, skip
        $added = Cache::add($cacheKey, $alertData, $ttl);

        if ($added) {
            Log::debug("ForwardChaining: Alert marked ATOMIC (TTL: {$ttl}s)", [
                'cache_key' => $cacheKey,
                'device' => $device->ip_address,
                'rule' => $rule->code,
            ]);
        }

        return $added;
    }

    /**
     * Generate unique cache key untuk deduplikasi.
     *
     * Format key: alert_{device_id}_{rule_id}_{metrics_hash}_{time_window}
     *
     * Mengapa pakai metrics_hash?
     * - Metrics dibagi ke bucket agar perubahan kecil tidak membuat alert baru.
     * - Bandwidth 100-109 Mbps masuk bucket 100; 110-119 Mbps masuk bucket 110.
     * - Latency memakai bucket 10 ms dan packet loss memakai bucket 1 persen.
     * - Metrics masuk bucket berbeda menghasilkan hash berbeda dan alert baru.
     *
     * Mengapa pakai time_window?
     * - Agar key expire otomatis sesuai TTL
     * - Format: YmdHi (contoh: 202608081800 = 2026-08-08 18:00)
     *
     * @param Device $device
     * @param Rule $rule
     * @param array $result
     * @return string
     */
    private function generateDeduplicationKey(Device $device, Rule $rule, array $result): string
    {
        // Bucketing Phase 1 memakai toleransi tetap untuk mengabaikan noise metrics kecil.
        $bandwidthTolerance = 10; // Mbps
        $latencyTolerance = 10; // ms
        $packetLossTolerance = 1; // persen
        $bandwidth = $result['metrics']['bandwidth'] !== null
            ? floor($result['metrics']['bandwidth'] / $bandwidthTolerance) * $bandwidthTolerance // Bucket 10 Mbps
            : 'null';

        $latency = $result['metrics']['latency'] !== null
            ? floor($result['metrics']['latency'] / $latencyTolerance) * $latencyTolerance // Bucket 10 ms
            : 'null';

        $packetLoss = $result['metrics']['packet_loss'] !== null
            ? floor($result['metrics']['packet_loss'] / $packetLossTolerance) * $packetLossTolerance // Bucket 1 persen
            : 'null';

        // Hash metrics untuk key yang lebih pendek
        $metricsHash = md5("{$bandwidth}_{$latency}_{$packetLoss}");

        // Time window: per 5 menit (0, 5, 10, 15, ...)
        // Ini membantu cache cleanup lebih terstruktur
        $minute = (int) now()->format('i');
        $roundedMinute = floor($minute / 5) * 5; // 0, 5, 10, 15, 20, ...
        $timeWindow = now()->format('YmdH') . str_pad($roundedMinute, 2, '0', STR_PAD_LEFT);

        return "alert_{$device->id}_{$rule->id}_{$metricsHash}_{$timeWindow}";
    }

    private function getDevicesToCheck(Rule $rule, $allDevices)
    {
        if ($rule->target_type === 'all') {
            return $allDevices;
        }
        return $rule->targetDevice ? collect([$rule->targetDevice]) : collect([]);
    }

    /**
     * Evaluasi rule terhadap device dengan metrics yang sudah diberikan (dari batch query).
     * Mengembalikan thresholds dari rule conditions untuk digunakan di view.
     */
    private function evaluateRuleForDevice(Rule $rule, Device $device, array $metrics): array
    {
        if ($metrics['bandwidth'] === null && $metrics['latency'] === null && $metrics['packet_loss'] === null) {
            return [
                'match' => false, 'rule' => $rule, 'device' => $device,
                'metrics' => $metrics, 'conditions' => [],
                'thresholds' => [], // Issue #4: pass thresholds to view
            ];
        }

        $allMet = true;
        $conditionsResult = [];
        $thresholds = []; // Issue #4: collect thresholds per parameter

        foreach ($rule->conditions as $condition) {
            $actualValue = $metrics[$condition->parameter] ?? null;

            if ($actualValue === null) {
                $allMet = false;
                $conditionsResult[] = [
                    'parameter' => $condition->parameter,
                    'operator' => $condition->operator,
                    'threshold' => $condition->value,
                    'actual' => null, 'met' => false,
                ];
                // Still collect threshold for view
                $thresholds[$condition->parameter] = $condition->value;
                continue;
            }

            $met = match($condition->operator) {
                '>' => $actualValue > $condition->value,
                '<' => $actualValue < $condition->value,
                '>=' => $actualValue >= $condition->value,
                '<=' => $actualValue <= $condition->value,
                '=' => abs($actualValue - $condition->value) < 0.01,
                default => false,
            };

            $conditionsResult[] = [
                'parameter' => $condition->parameter,
                'operator' => $condition->operator,
                'threshold' => $condition->value,
                'actual' => $actualValue, 'met' => $met,
            ];

            // Collect threshold for view (use the max threshold if multiple conditions on same param)
            if (!isset($thresholds[$condition->parameter]) || $condition->value > $thresholds[$condition->parameter]) {
                $thresholds[$condition->parameter] = $condition->value;
            }

            if (!$met) $allMet = false;
        }

        return [
            'match' => $allMet,
            'rule' => $rule,
            'device' => $device,
            'metrics' => $metrics,
            'conditions' => $conditionsResult,
            'thresholds' => $thresholds, // Issue #4: pass to view
        ];
    }

    public function evaluateSingleRule(int $ruleId, int $deviceId): array
    {
        $rule = Rule::with('conditions')->find($ruleId);
        $device = Device::find($deviceId);

        if (!$rule || !$device) {
            return ['success' => false, 'message' => 'Rule atau Device tidak ditemukan.'];
        }
        if (!$rule->is_active) {
            return ['success' => false, 'message' => 'Rule tidak aktif.'];
        }

        $metrics = $this->prometheus->getAllMetrics($device->ip_address);
        $result = $this->evaluateRuleForDevice($rule, $device, $metrics);

        return ['success' => true, 'result' => $result];
    }
}
