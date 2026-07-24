<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    use HasFactory;

    // 1. Daftarkan kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'code',
        'name',
        'target_type',
        'target_device_id',
        'is_active'
    ];

    // 2. Cast tipe data untuk kolom tertentu
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 3. Automasi pembuatan Code Rule sebelum data disimpan
    // Format: R01, R02, ..., R99, R100, R101, dst (padding dinamis)
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Gunakan lockForUpdate() untuk mencegah race condition
            // ketika dua user membuat rule secara bersamaan
            $latest = self::where('code', 'like', 'R%')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            
            if ($latest) {
                // Jika sudah ada data, ambil nomor terakhir lalu tambah 1
                $number = (int) substr($latest->code, 1) + 1;
                // Padding dinamis: jika angka < 10 -> 2 digit, jika > 99 -> 3 digit, dst
                $padding = max(2, strlen((string) $number));
                $model->code = 'R' . str_pad($number, $padding, '0', STR_PAD_LEFT);
            } else {
                // Jika belum ada data, mulai dari R01
                $model->code = 'R01';
            }
        });
    }

    // 4. Relasi ke tabel devices (belongsTo)
    // Satu rule bisa memiliki satu target device specific atau null (all devices)
    public function targetDevice()
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    // 5. Relasi ke tabel rule_conditions (hasMany)
    // Satu rule bisa memiliki banyak kondisi (IF condition1 AND condition2 AND ...)
    public function conditions()
    {
        return $this->hasMany(RuleCondition::class);
    }
}