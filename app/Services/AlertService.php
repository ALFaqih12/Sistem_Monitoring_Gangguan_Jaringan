<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Rule;
use App\Models\AlertSetting;
use App\Models\AlertLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Kirim alert ke Telegram saat gangguan terdeteksi.
     * Method ini dipanggil dari ForwardChainingService setelah tryAlert() return true.
     * 
     * @param Device $device Device yang mengalami gangguan
     * @param Rule $rule Rule yang match
     * @param array $result Result dari evaluasi Forward Chaining
     * @return bool True jika berhasil terkirim, False jika gagal
     */
    public function sendAlert(Device $device, Rule $rule, array $result): bool
    {
        // 1. Cek apakah alert system aktif
        $config = AlertSetting::getConfig();
        
        if (!$config || !$config->is_active) {
            Log::debug("AlertService: Alert system tidak aktif, skip pengiriman.");
            return false;
        }

        // 2. Format pesan alert
        $message = $this->formatMessage($device, $rule, $result);

        // 3. Buat record di alert_logs dengan status 'pending'
        $alertLog = $this->createAlertLog($device, $rule, $result, $message);

        // 4. Kirim ke Telegram dengan retry mechanism
        $sent = $this->sendToTelegram(
            $config->bot_token,
            $config->chat_id,
            $message,
            $config->retry_attempts,
            $config->retry_delay,
            $alertLog
        );

        return $sent;
    }

    /**
     * Format pesan alert sesuai template yang diminta.
     * 
     * Format:
     * *R01 - Bandwidth Overload*
     * 
     * Nama Pelanggan: PT SIWA Network
     * IP: 192.168.1.100
     * Lokasi: [Buka Maps](url...)
     * 
     * Metric:
     * • Bandwidth: 120.5 Mbps (threshold: > 100 Mbps)
     * 
     * Waktu: Jumat, 18 Oktober 2026 | 22:45:30 WIB
     * 
     * @param Device $device
     * @param Rule $rule
     * @param array $result
     * @return string
     */
    public function formatMessage(Device $device, Rule $rule, array $result): string
    {
        $metrics = $result['metrics'];
        $conditions = $result['conditions'] ?? [];

        // Heading: Bold code + nama rule
        $message = "*{$rule->code} - {$rule->name}*\n\n";

        // Info pelanggan & device
        $message .= "Nama Pelanggan: {$device->customer_name}\n";
        $message .= "IP: {$device->ip_address}\n";
        
        // Lokasi (jika ada)
        if ($device->maps_url) {
            $message .= "Lokasi: [Buka Maps]({$device->maps_url})\n";
        }

        // Metrics: Hanya tampilkan yang relevan dengan rule conditions
        $message .= "\nMetric:\n";
        
        foreach ($conditions as $cond) {
            $param = $cond['parameter'];
            $actualValue = $cond['actual'];
            $threshold = $cond['threshold'];
            $operator = $cond['operator'];
            
            // Skip jika actual value null
            if ($actualValue === null) {
                continue;
            }

            // Get unit dan label
            $unit = match($param) {
                'bandwidth' => 'Mbps',
                'latency' => 'ms',
                'packet_loss' => '%',
                default => '',
            };

            $label = match($param) {
                'bandwidth' => 'Bandwidth',
                'latency' => 'Latency',
                'packet_loss' => 'Packet Loss',
                default => $param,
            };

            $message .= "• {$label}: {$actualValue} {$unit} (threshold: {$operator} {$threshold} {$unit})\n";
        }

        // Waktu gangguan terdeteksi
        $message .= "\nWaktu: " . $this->formatDetectedTime(now()) . "\n";

        return $message;
    }

    /**
     * Format waktu menjadi format Indonesia.
     * Format: Jumat, 18 Oktober 2026 | 22:45:30 WIB
     * 
     * @param \Illuminate\Support\Carbon $datetime
     * @return string
     */
    private function formatDetectedTime($datetime): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $date = $datetime->setTimezone(config('app.timezone', 'Asia/Jakarta'));
        $dayName = $days[$date->dayOfWeek];
        $monthName = $months[(int) $date->format('n')];

        return sprintf(
            '%s, %d %s %s | %s WIB',
            $dayName,
            $date->day,
            $monthName,
            $date->year,
            $date->format('H:i:s')
        );
    }

    /**
     * Buat record alert log di database dengan status 'pending'.
     * 
     * @param Device $device
     * @param Rule $rule
     * @param array $result
     * @param string $message
     * @return AlertLog
     */
    private function createAlertLog(Device $device, Rule $rule, array $result, string $message): AlertLog
    {
        $metrics = $result['metrics'];
        $conditions = $result['conditions'] ?? [];

        // Prepare rule conditions untuk disimpan sebagai JSON
        $ruleConditionsData = collect($conditions)->map(function ($cond) {
            return [
                'parameter' => $cond['parameter'],
                'operator' => $cond['operator'],
                'threshold' => $cond['threshold'],
                'unit' => match($cond['parameter']) {
                    'bandwidth' => 'Mbps',
                    'latency' => 'ms',
                    'packet_loss' => '%',
                    default => '',
                },
            ];
        })->toArray();

        return AlertLog::create([
            'issue_log_id' => null, // Bisa di-update nanti jika perlu link ke issue_log
            'device_id' => $device->id,
            'rule_id' => $rule->id,
            'device_ip' => $device->ip_address,
            'device_name' => $device->customer_name,
            'device_model' => $device->device_model,
            'customer_id' => $device->customer_id,
            'rule_code' => $rule->code,
            'rule_name' => $rule->name,
            'maps_url' => $device->maps_url,
            'bandwidth_actual' => $metrics['bandwidth'],
            'latency_actual' => $metrics['latency'],
            'packet_loss_actual' => $metrics['packet_loss'],
            'rule_conditions' => $ruleConditionsData,
            'message' => $message,
            'status' => 'pending',
            'detected_at' => now(),
        ]);
    }

    /**
     * Kirim pesan ke Telegram API dengan retry mechanism.
     * 
     * @param string $botToken
     * @param string $chatId
     * @param string $message
     * @param int $maxRetry
     * @param int $retryDelay (detik)
     * @param AlertLog $alertLog
     * @return bool
     */
    private function sendToTelegram(
        string $botToken,
        string $chatId,
        string $message,
        int $maxRetry,
        int $retryDelay,
        AlertLog $alertLog
    ): bool {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $attempt = 0;

        while ($attempt < $maxRetry) {
            $attempt++;

            try {
                $response = Http::timeout(10)->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => false, // Tampilkan preview link Maps
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Update alert_log sebagai 'sent'
                    $alertLog->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'telegram_message_id' => $data['result']['message_id'] ?? null,
                        'retry_count' => $attempt,
                    ]);

                    Log::info("AlertService: Alert berhasil terkirim ke Telegram.", [
                        'alert_log_id' => $alertLog->id,
                        'device_ip' => $alertLog->device_ip,
                        'rule_code' => $alertLog->rule_code,
                        'retry_count' => $attempt,
                    ]);

                    return true;
                } else {
                    // HTTP error (4xx, 5xx)
                    $error = $response->json()['description'] ?? $response->body();
                    
                    Log::warning("AlertService: Telegram API error (attempt {$attempt}/{$maxRetry}): {$error}");

                    // Update retry count
                    $alertLog->update(['retry_count' => $attempt]);

                    // Jika masih ada retry, tunggu sebelum retry
                    if ($attempt < $maxRetry) {
                        sleep($retryDelay);
                    } else {
                        // Max retry tercapai, mark as failed
                        $alertLog->update([
                            'status' => 'failed',
                            'error_message' => "Max retry reached. Last error: {$error}",
                        ]);

                        Log::error("AlertService: Alert gagal terkirim setelah {$maxRetry}x retry.", [
                            'alert_log_id' => $alertLog->id,
                            'device_ip' => $alertLog->device_ip,
                            'rule_code' => $alertLog->rule_code,
                            'error' => $error,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Network error atau exception lainnya
                Log::warning("AlertService: Exception saat kirim alert (attempt {$attempt}/{$maxRetry}): " . $e->getMessage());

                // Update retry count
                $alertLog->update(['retry_count' => $attempt]);

                // Jika masih ada retry, tunggu sebelum retry
                if ($attempt < $maxRetry) {
                    sleep($retryDelay);
                } else {
                    // Max retry tercapai, mark as failed
                    $alertLog->update([
                        'status' => 'failed',
                        'error_message' => "Exception after {$maxRetry}x retry: " . $e->getMessage(),
                    ]);

                    Log::error("AlertService: Alert gagal terkirim karena exception.", [
                        'alert_log_id' => $alertLog->id,
                        'device_ip' => $alertLog->device_ip,
                        'rule_code' => $alertLog->rule_code,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        return false;
    }

    /**
     * Test koneksi ke Telegram API.
     * Menggunakan method getMe untuk validasi bot token.
     * 
     * @param string $botToken
     * @return array ['success' => bool, 'message' => string, 'bot_info' => array|null]
     */
    public function testConnection(string $botToken): array
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/getMe";
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'message' => 'Koneksi berhasil! Bot ditemukan.',
                        'bot_info' => $data['result'],
                    ];
                }
            }

            $error = $response->json()['description'] ?? 'Unknown error';
            return [
                'success' => false,
                'message' => "Koneksi gagal: {$error}",
                'bot_info' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Exception: " . $e->getMessage(),
                'bot_info' => null,
            ];
        }
    }

    /**
     * Kirim test alert ke Telegram untuk validasi chat ID.
     * 
     * @param string $botToken
     * @param string $chatId
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendTestAlert(string $botToken, string $chatId): array
    {
        try {
            $testMessage = "🔔 *Test Alert - SIWA Network Monitor*\n\n";
            $testMessage .= "Ini adalah pesan test dari sistem monitoring.\n";
            $testMessage .= "Jika Anda menerima pesan ini, integrasi Telegram berhasil!\n\n";
            $testMessage .= "Waktu: " . $this->formatDetectedTime(now());

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'text' => $testMessage,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Test alert berhasil dikirim! Silakan cek grup Telegram Anda.',
                ];
            }

            $error = $response->json()['description'] ?? 'Unknown error';
            return [
                'success' => false,
                'message' => "Gagal mengirim test alert: {$error}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Exception: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim ulang alert yang failed.
     * 
     * @param AlertLog $alertLog
     * @return bool
     */
    public function resendAlert(AlertLog $alertLog): bool
    {
        $config = AlertSetting::getConfig();
        
        if (!$config || !$config->is_active) {
            Log::debug("AlertService: Alert system tidak aktif, skip resend.");
            return false;
        }

        // Reset status ke pending
        $alertLog->update([
            'status' => 'pending',
            'error_message' => null,
            'retry_count' => 0,
        ]);

        // Kirim ulang dengan retry mechanism
        return $this->sendToTelegram(
            $config->bot_token,
            $config->chat_id,
            $alertLog->message,
            $config->retry_attempts,
            $config->retry_delay,
            $alertLog
        );
    }
}
