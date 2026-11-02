<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rule;
use App\Models\RuleCondition;
use App\Models\Device;

class RuleSeeder extends Seeder
{
    /**
     * Seeder untuk generate 20 sample rules dengan berbagai kondisi.
     * Digunakan untuk testing pagination di modul Rule Forward Chaining.
     */
    public function run(): void
    {
        // Ambil semua device untuk target specific (jika ada)
        $devices = Device::all();
        $deviceIds = $devices->pluck('id')->toArray();

        // Definisi sample rules dengan berbagai kondisi
        $sampleRules = [
            [
                'name' => 'High Bandwidth Usage',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 80],
                ],
            ],
            [
                'name' => 'Critical Network Latency',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 100],
                ],
            ],
            [
                'name' => 'Severe Packet Loss',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 10],
                ],
            ],
            [
                'name' => 'Moderate Latency with Packet Loss',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 50],
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 5],
                ],
            ],
            [
                'name' => 'Low Bandwidth Detection',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => false,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '<', 'value' => 10],
                ],
            ],
            [
                'name' => 'Network Congestion Alert',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 90],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 80],
                ],
            ],
            [
                'name' => 'High Latency Warning',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '>=', 'value' => 150],
                ],
            ],
            [
                'name' => 'Minor Packet Loss',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => false,
                'conditions' => [
                    ['parameter' => 'packet_loss', 'operator' => '>=', 'value' => 3],
                ],
            ],
            [
                'name' => 'Bandwidth Spike Detection',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 95],
                ],
            ],
            [
                'name' => 'Triple Metric Alert',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 70],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 60],
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 4],
                ],
            ],
            [
                'name' => 'Critical All Metrics',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => false,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 100],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 200],
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 15],
                ],
            ],
            [
                'name' => 'Moderate Bandwidth Usage',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>=', 'value' => 50],
                ],
            ],
            [
                'name' => 'Connection Quality Check',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 40],
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 2],
                ],
            ],
            [
                'name' => 'Heavy Traffic Alert',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 85],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 70],
                ],
            ],
            [
                'name' => 'Packet Loss Warning',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'packet_loss', 'operator' => '>=', 'value' => 7],
                ],
            ],
            [
                'name' => 'Network Stability Monitor',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => false,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '<', 'value' => 20],
                    ['parameter' => 'packet_loss', 'operator' => '<', 'value' => 1],
                ],
            ],
            [
                'name' => 'Peak Hour Congestion',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 75],
                ],
            ],
            [
                'name' => 'Latency Threshold Alert',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'latency', 'operator' => '>=', 'value' => 120],
                ],
            ],
            [
                'name' => 'Low Performance Detection',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '<', 'value' => 20],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 100],
                ],
            ],
            [
                'name' => 'Emergency Network Alert',
                'target_type' => 'all',
                'target_device_id' => null,
                'is_active' => true,
                'conditions' => [
                    ['parameter' => 'bandwidth', 'operator' => '>', 'value' => 98],
                    ['parameter' => 'latency', 'operator' => '>', 'value' => 150],
                    ['parameter' => 'packet_loss', 'operator' => '>', 'value' => 10],
                ],
            ],
        ];

        // Generate auto code dan insert rules dengan conditions
        foreach ($sampleRules as $index => $ruleData) {
            // Generate code R01, R02, dst
            $code = 'R' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);

            // Jika ada device dan ingin mix target specific, uncomment logic ini
            // $targetType = $index % 3 === 0 && !empty($deviceIds) ? 'specific' : 'all';
            // $targetDeviceId = $targetType === 'specific' ? $deviceIds[array_rand($deviceIds)] : null;

            // Create rule
            $rule = Rule::create([
                'code' => $code,
                'name' => $ruleData['name'],
                'target_type' => $ruleData['target_type'],
                'target_device_id' => $ruleData['target_device_id'],
                'is_active' => $ruleData['is_active'],
            ]);

            // Create conditions untuk rule ini
            foreach ($ruleData['conditions'] as $conditionData) {
                RuleCondition::create([
                    'rule_id' => $rule->id,
                    'parameter' => $conditionData['parameter'],
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'],
                ]);
            }
        }

        $this->command->info('✅ Successfully created 20 sample rules with conditions for pagination testing.');
    }
}
