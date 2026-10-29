<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    // 1. Daftarkan kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'customer_id',
        'customer_name',
        'ip_address',
        'device_model',
        'installation_date',
        'maps_url',
        'source',         // Sumber device: 'manual' atau 'discovery'
        'core_router_ip', // IP Core Router (untuk device dari discovery)
    ];

    // 2. Automasi pembuatan ID Pelanggan sebelum data disimpan
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Format: CUST-YYYYMM- (Contoh: CUST-202607-)
            $prefix = 'CUST-' . now()->format('Ym') . '-';
            
            // Cari data terakhir di bulan ini
            $latest = self::where('customer_id', 'like', $prefix . '%')->latest('id')->first();
            
            if ($latest) {
                // Jika sudah ada, ambil 3 digit terakhir, lalu tambah 1
                $number = (int) substr($latest->customer_id, -3);
                $model->customer_id = $prefix . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
            } else {
                // Jika belum ada data di bulan ini, mulai dari 001
                $model->customer_id = $prefix . '001';
            }
        });
    }
}