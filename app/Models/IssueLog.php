<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssueLog extends Model
{
    use HasFactory;

    // Kolom yang boleh diisi (Mass Assignment)
    // Kolom status dan notes sudah dihapus, diganti dengan maps_url
    protected $fillable = [
        'device_id',
        'rule_id',
        'bandwidth_actual',
        'latency_actual',
        'packet_loss_actual',
        'maps_url',
        'detected_at',
    ];

    // Cast tipe data untuk kolom numerik dan timestamp
    protected $casts = [
        'detected_at' => 'datetime',
        'bandwidth_actual' => 'decimal:2',
        'latency_actual' => 'decimal:2',
        'packet_loss_actual' => 'decimal:2',
    ];

    // Relasi ke tabel devices (belongsTo)
    // Setiap log gangguan milik satu device
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    // Relasi ke tabel rules (belongsTo)
    // Setiap log gangguan terkait dengan satu rule yang match
    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }
}
