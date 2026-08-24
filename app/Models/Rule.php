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
            // Ambil semua nomor code yang sudah terpakai (misal: R01,R02,R05 -> [1,2,5])
            $existingNumbers = self::where('code', 'like', 'R%')
                ->lockForUpdate() // Mencegah race condition saat 2+ rule dibuat bersamaan
                ->pluck('code')
                ->map(function ($code) {
                    return (int) substr($code, 1); // Ambil angka setelah huruf "R"
                })
                ->sort()
                ->values();

            // Cari nomor terkecil yang BELUM dipakai (isi gap akibat rule yang dihapus)
            // Contoh: code terpakai [1,2,5] -> nomor 3 belum dipakai -> rule baru dapat R03
            $number = 1;
            foreach ($existingNumbers as $existing) {
                if ($existing === $number) {
                    $number++;
                } else {
                    break; // Ada gap, nomor ini yang akan dipakai
                }
            }

            // Padding dinamis: jika angka < 10 -> 2 digit, jika > 99 -> 3 digit, dst
            $padding = max(2, strlen((string) $number));
            $model->code = 'R' . str_pad($number, $padding, '0', STR_PAD_LEFT);
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
