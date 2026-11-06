<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk metrics monitoring display per device.
 * Data selalu UPDATE (tidak ada historical record).
 */
class DeviceMetric extends Model
{
    // Hanya updated_at, no created_at
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;
    
    protected $fillable = [
        'device_id',
        'bandwidth',
        'latency',
        'packet_loss',
        'updated_at',
    ];

    protected $casts = [
        'bandwidth' => 'float',
        'latency' => 'float',
        'packet_loss' => 'float',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke Device (many-to-one, tapi dalam praktek 1-to-1 karena unique constraint)
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
