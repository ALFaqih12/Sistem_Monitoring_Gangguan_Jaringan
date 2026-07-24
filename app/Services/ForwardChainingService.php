<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class ForwardChainingService
{
    /**
     * Service Prometheus untuk query data traffic
     */
    private PrometheusService $prometheus;

    /**
     * Constructor untuk inject PrometheusService
     */
    public function __construct(PrometheusService $prometheus)
    {
        $this->prometheus = $prometheus;
    }

    /**
     * Evaluasi semua rule yang aktif terhadap semua device.
     * Ini adalah main function yang akan dipanggil oleh scheduler.
     * 
     * @return array Array hasil evaluasi dengan format: ['detected' => [], 'checked' => int]
     */
    public function evaluateAllRules(): array
    {
        $detectedIssues = [];
        $rulesChecked = 0;

        // Ambil semua rule yang aktif
        $activeRules = Rule::where('is_active', true)
            ->with(['conditions', 'targetDevice'])
            ->get();

        if ($activeRules->isEmpty()) {
            Log::info('ForwardChaining: Tidak ada rule aktif untuk dievaluasi.');
            return ['detected' => [], 'checked' => 0];
        }

        // Ambil semua device yang akan dicek
        $devices = Device::all();

        if ($devices->isEmpty()) {
            Log::info('ForwardChaining: Tidak ada device untuk dicek.');
            return ['detected' => [], 'checked' => 0];
        }

        // Loop setiap rule
        foreach ($activeRules as $rule) {
            // Tentukan device mana yang akan dicek berdasarkan target_type
            $devicesToCheck = $this->getDevicesToCheck($rule, $devices);

            // Evaluasi rule untuk setiap device
            foreach ($devicesToCheck as $device) {
                $rulesChecked++;
                
                $result = $this->evaluateRuleForDevice($rule, $device);
                
                if ($result['match']) {
                    $detectedIssues[] = $result;
                    
                    Log::info("ForwardChaining: Gangguan terdeteksi!", [
                        'rule_code' => $rule->code,
                        'rule_name' => $rule->name,
                        'device_ip' => $device->ip_address,
                        'conditions' => $result['conditions']
                    ]);
                }
            }
        }

        Log::info("ForwardChaining: Evaluasi selesai. Rules checked: {$rulesChecked}, Issues detected: " . count($detectedIssues));

        return [
            'detected' => $detectedIssues,
            'checked' => $rulesChecked
        ];
    }

    /**
     * Tentukan device mana yang akan dicek berdasarkan target_type rule.
     * 
     * @param Rule $rule Rule yang akan dicek
     * @param \Illuminate\Database\Eloquent\Collection $allDevices Semua device yang ada
     * @return \Illuminate\Database\Eloquent\Collection Device yang akan dicek
     */
    private function getDevicesToCheck(Rule $rule, $allDevices)
    {
        if ($rule->target_type === 'all') {
            // Jika target_type = all, cek semua device
            return $allDevices;
        } else {
            // Jika target_type = specific, hanya cek device yang ditarget
            if ($rule->targetDevice) {
                return collect([$rule->targetDevice]);
            }
            return collect([]);
        }
    }

    /**
     * Evaluasi rule terhadap satu device.
     * Logic Forward Chaining: IF condition1 AND condition2 AND ... THEN gangguan terdeteksi
     * 
     * @param Rule $rule Rule yang akan dievaluasi
     * @param Device $device Device yang akan dicek
     * @return array Hasil evaluasi dengan format: ['match' => bool, 'rule' => Rule, 'device' => Device, 'metrics' => array, 'conditions' => array]
     */
    private function evaluateRuleForDevice(Rule $rule, Device $device): array
    {
        // Ambil metrics dari Prometheus untuk device ini
        $metrics = $this->prometheus->getAllMetrics($device->ip_address);

        // Jika semua metrics null (gagal ambil data), skip evaluasi
        if ($metrics['bandwidth'] === null && $metrics['latency'] === null && $metrics['packet_loss'] === null) {
            Log::warning("ForwardChaining: Gagal mendapatkan metrics untuk {$device->ip_address}. Skip evaluasi.");
            return [
                'match' => false,
                'rule' => $rule,
                'device' => $device,
                'metrics' => $metrics,
                'conditions' => []
            ];
        }

        // Evaluasi setiap kondisi (logic AND)
        $allConditionsMet = true;
        $conditionsResult = [];

        foreach ($rule->conditions as $condition) {
            $parameterValue = $metrics[$condition->parameter] ?? null;

            // Jika parameter value null, kondisi dianggap tidak terpenuhi
            if ($parameterValue === null) {
                $allConditionsMet = false;
                $conditionsResult[] = [
                    'parameter' => $condition->parameter,
                    'operator' => $condition->operator,
                    'threshold' => $condition->value,
                    'actual' => null,
                    'met' => false
                ];
                continue;
            }

            // Evaluasi kondisi berdasarkan operator
            $conditionMet = $this->evaluateCondition($parameterValue, $condition->operator, $condition->value);

            $conditionsResult[] = [
                'parameter' => $condition->parameter,
                'operator' => $condition->operator,
                'threshold' => $condition->value,
                'actual' => $parameterValue,
                'met' => $conditionMet
            ];

            // Jika salah satu kondisi tidak terpenuhi, maka rule tidak match (logic AND)
            if (!$conditionMet) {
                $allConditionsMet = false;
            }
        }

        return [
            'match' => $allConditionsMet,
            'rule' => $rule,
            'device' => $device,
            'metrics' => $metrics,
            'conditions' => $conditionsResult
        ];
    }

    /**
     * Evaluasi kondisi berdasarkan operator.
     * 
     * @param float $actualValue Nilai aktual dari metrics
     * @param string $operator Operator pembanding (>, <, >=, <=, =)
     * @param float $thresholdValue Nilai threshold dari kondisi rule
     * @return bool True jika kondisi terpenuhi, false jika tidak
     */
    private function evaluateCondition(float $actualValue, string $operator, float $thresholdValue): bool
    {
        return match($operator) {
            '>' => $actualValue > $thresholdValue,
            '<' => $actualValue < $thresholdValue,
            '>=' => $actualValue >= $thresholdValue,
            '<=' => $actualValue <= $thresholdValue,
            '=' => abs($actualValue - $thresholdValue) < 0.01, // Toleransi untuk float comparison
            default => false,
        };
    }

    /**
     * Evaluasi satu rule terhadap satu device secara manual (untuk testing).
     * 
     * @param int $ruleId ID rule yang akan dievaluasi
     * @param int $deviceId ID device yang akan dicek
     * @return array Hasil evaluasi
     */
    public function evaluateSingleRule(int $ruleId, int $deviceId): array
    {
        $rule = Rule::with('conditions')->find($ruleId);
        $device = Device::find($deviceId);

        if (!$rule || !$device) {
            return [
                'success' => false,
                'message' => 'Rule atau Device tidak ditemukan.'
            ];
        }

        if (!$rule->is_active) {
            return [
                'success' => false,
                'message' => 'Rule tidak aktif.'
            ];
        }

        $result = $this->evaluateRuleForDevice($rule, $device);

        return [
            'success' => true,
            'result' => $result
        ];
    }
}