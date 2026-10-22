<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AlertSetting extends Model
{
    use HasFactory;

    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'bot_token',
        'chat_id',
        'is_active',
        'deduplication_ttl',
        'retry_attempts',
        'retry_delay',
    ];

    // Cast tipe data
    protected $casts = [
        'is_active' => 'boolean',
        'deduplication_ttl' => 'integer',
        'retry_attempts' => 'integer',
        'retry_delay' => 'integer',
    ];

    /**
     * Encrypt bot_token sebelum disimpan ke database.
     * Bot token adalah credential sensitif yang harus dienkripsi.
     */
    public function setBotTokenAttribute($value)
    {
        $this->attributes['bot_token'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt bot_token saat diambil dari database.
     * Accessor ini otomatis dipanggil saat akses $setting->bot_token
     */
    public function getBotTokenAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Jika gagal decrypt (misal: key berubah), return null
            return null;
        }
    }

    /**
     * Get konfigurasi alert aktif (singleton pattern).
     * Hanya ada 1 konfigurasi bot dalam sistem.
     * 
     * @return AlertSetting|null
     */
    public static function getConfig(): ?AlertSetting
    {
        return self::first();
    }

    /**
     * Cek apakah alert system sudah dikonfigurasi dan aktif.
     * 
     * @return bool
     */
    public static function isActive(): bool
    {
        $config = self::getConfig();
        return $config && $config->is_active;
    }
}
