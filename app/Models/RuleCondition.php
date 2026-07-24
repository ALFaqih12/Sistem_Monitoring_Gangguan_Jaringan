<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuleCondition extends Model
{
    use HasFactory;

    // 1. Daftarkan kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'rule_id',
        'parameter',
        'operator',
        'value'
    ];

    // 2. Cast tipe data untuk kolom tertentu
    protected $casts = [
        'value' => 'decimal:2',
    ];

    // 3. Relasi ke tabel rules (belongsTo)
    // Setiap kondisi hanya dimiliki oleh satu rule
    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }

    // 4. Accessor untuk mendapatkan satuan parameter
    // Satuan akan digunakan di form dan tampilan (mbps, ms, %)
    public function getUnitAttribute(): string
    {
        return match($this->parameter) {
            'bandwidth' => 'Mbps',
            'latency' => 'ms',
            'packet_loss' => '%',
            default => '',
        };
    }

    // 5. Accessor untuk mendapatkan label parameter yang user-friendly
    public function getParameterLabelAttribute(): string
    {
        return match($this->parameter) {
            'bandwidth' => 'Bandwidth',
            'latency' => 'Latency',
            'packet_loss' => 'Packet Loss',
            default => $this->parameter,
        };
    }
}