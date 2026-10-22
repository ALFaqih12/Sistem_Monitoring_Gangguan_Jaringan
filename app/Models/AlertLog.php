<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertLog extends Model
{
    use HasFactory;

    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'issue_log_id',
        'device_id',
        'rule_id',
        'device_ip',
        'device_name',
        'device_model',
        'customer_id',
        'rule_code',
        'rule_name',
        'maps_url',
        'bandwidth_actual',
        'latency_actual',
        'packet_loss_actual',
        'rule_conditions',
        'message',
        'status',
        'error_message',
        'retry_count',
        'telegram_message_id',
        'sent_at',
        'detected_at',
    ];

    // Cast tipe data
    protected $casts = [
        'detected_at' => 'datetime',
        'sent_at' => 'datetime',
        'rule_conditions' => 'array', // JSON → array otomatis
        'bandwidth_actual' => 'decimal:2',
        'latency_actual' => 'decimal:2',
        'packet_loss_actual' => 'decimal:2',
        'retry_count' => 'integer',
    ];

    // Append accessor ke JSON response agar Vue bisa akses detected_at_formatted dan sent_at_formatted.
    // Tanpa $appends, accessor hanya tersedia di PHP, tidak di-serialize ke JSON untuk Inertia.
    // Bug fix: kolom waktu di History Alert tidak muncul karena accessor tidak ter-include di response.
    protected $appends = [
        'detected_at_formatted',
        'sent_at_formatted',
    ];

    // Relasi ke tabel devices (belongsTo)
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    // Relasi ke tabel rules (belongsTo)
    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }

    // Relasi ke tabel issue_logs (belongsTo)
    public function issueLog()
    {
        return $this->belongsTo(IssueLog::class);
    }

    /**
     * Accessor: Format detected_at menjadi format Indonesia.
     * Format: Jumat, 18 Oktober 2026 | 22:45:30 WIB
     */
    public function getDetectedAtFormattedAttribute(): string
    {
        if (!$this->detected_at) {
            return '-';
        }

        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $date = $this->detected_at->setTimezone(config('app.timezone', 'Asia/Jakarta'));
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
     * Accessor: Format sent_at menjadi format sederhana.
     * Format: 18/10/2026 22:45
     */
    public function getSentAtFormattedAttribute(): string
    {
        if (!$this->sent_at) {
            return '-';
        }

        return $this->sent_at
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('d/m/Y H:i');
    }

    /**
     * Scope: Filter berdasarkan periode tanggal.
     * Digunakan untuk report mingguan/bulanan.
     */
    public function scopeDateRange($query, $from, $to)
    {
        if ($from) {
            $query->whereDate('detected_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('detected_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope: Filter hanya alert yang berhasil terkirim.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Filter alert yang gagal terkirim.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
