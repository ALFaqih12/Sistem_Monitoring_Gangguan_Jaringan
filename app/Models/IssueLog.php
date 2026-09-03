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

    // Pastikan accessor otomatis ada di JSON response (termasuk Inertia props dan API response).
    // detected_at_formatted: format waktu DD/MM/YYYY HH:MM.
    // thresholds: { bandwidth: {value, operator} | null, ... } — untuk label threshold di UI.
    // matched_metrics: { bandwidth: boolean, ... } — untuk styling warna di UI (merah/hijau).
    // Dengan demikian frontend tidak perlu menghitung operator sendiri — cukup ikut backend.
    protected $appends = [
        'detected_at_formatted',
        'thresholds',
        'matched_metrics',
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

    // Accessor: format detected_at menjadi DD/MM/YYYY HH:MM (misal: 01/09/2026 15:22).
    // Menggunakan timezone aplikasi (config('app.timezone')) agar konsisten dengan
    // format yang dikirimkan di endpoint API (indexApi) dan di Inertia response.
    // Accessor ini otomatis di-serialize ke JSON response — cukup akses $log->detected_at_formatted
    // di Inertia props atau API response.
    public function getDetectedAtFormattedAttribute()
    {
        return $this->detected_at ? $this->detected_at->setTimezone(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i') : '-';
    }

    // Accessor: kumpulkan threshold (nilai maksimum + operator) tiap parameter
    // dari rule conditions yang match. Setiap log diasosiasikan dengan satu rule,
    // sehingga threshold yang ditampilkan di kolom metrics frontend adalah dari
    // rule yang benar-benar match — bukan rule pertama.
    //
    // Format return:
    //   {
    //     bandwidth: { value: 100, operator: '>' } | null,
    //     latency: { value: 20, operator: '>' } | null,
    //     packet_loss: { value: 5, operator: '>' } | null
    //   }
    //
    // Logika penentuan nilai:
    // - Untuk operator '>' atau '>=': ambil nilai maksimum (threshold tertinggi).
    // - Untuk operator '<' atau '<=': ambil nilai minimum (threshold terendah).
    // - Untuk operator '=': nilai tungsgai, tidak perlu perbandingan.
    // - Jika tidak ada kondisi untuk parameter tertentu: null.
    public function getThresholdsAttribute()
    {
        if (!$this->relationLoaded('rule') || !$this->rule) {
            return ['bandwidth' => null, 'latency' => null, 'packet_loss' => null];
        }

        // Inisialisasi struktur dengan null untuk tiga parameter utama
        $thresholds = [
            'bandwidth' => null,
            'latency' => null,
            'packet_loss' => null,
        ];

        foreach ($this->rule->conditions as $condition) {
            $param = $condition->parameter;
            $value = $condition->value;
            $operator = $condition->operator;

            // Pastikan parameter valid
            if (!isset($thresholds[$param])) {
                continue;
            }

            // Jika belum ada threshold untuk parameter ini, set langsung
            if (is_null($thresholds[$param])) {
                $thresholds[$param] = ['value' => $value, 'operator' => $operator];
                continue;
            }

            // Logika pemilihan nilai:
            // - Untuk '>' atau '>=': ambil nilai maksimum (semakin ketat)
            // - Untuk '<' atau '<=': ambil nilai minimum (semakin ketat)
            $currentValue = $thresholds[$param]['value'];
            if ($operator === '>' || $operator === '>=') {
                if ($value > $currentValue) {
                    $thresholds[$param] = ['value' => $value, 'operator' => $operator];
                }
            } elseif ($operator === '<' || $operator === '<=') {
                if (is_null($currentValue) || $value < $currentValue) {
                    $thresholds[$param] = ['value' => $value, 'operator' => $operator];
                }
            } else {
                // Untuk operator '=' atau lainnya, set langsung (overwrite)
                $thresholds[$param] = ['value' => $value, 'operator' => $operator];
            }
        }

        return $thresholds;
    }

    // Accessor: tentukan apakah setiap parameter metrics memenuhi kondisi rule.
    // Digunakan di frontend agar tidak perlu menghitung operator sendiri.
    // Hanya mengembalikan boolean per parameter — jika metrics actual memenuhi
    // kondisi rule (apapun operatornya), maka dianggap gangguan.
    // Format return: { bandwidth: boolean, latency: boolean, packet_loss: boolean }
    // Ini memastikan konsistensi logika matching antara backend dan frontend.
    public function getMatchedMetricsAttribute(): array
    {
        if (!$this->relationLoaded('rule') || !$this->rule) {
            return ['bandwidth' => false, 'latency' => false, 'packet_loss' => false];
        }

        $actuals = [
            'bandwidth' => $this->bandwidth_actual,
            'latency' => $this->latency_actual,
            'packet_loss' => $this->packet_loss_actual,
        ];

        $matched = ['bandwidth' => false, 'latency' => false, 'packet_loss' => false];

        foreach ($this->rule->conditions as $condition) {
            $param = $condition->parameter;
            $actual = $actuals[$param] ?? null;
            if ($actual === null) continue;

            $matched[$param] = match($condition->operator) {
                '>'  => $actual > $condition->value,
                '<'  => $actual < $condition->value,
                '>=' => $actual >= $condition->value,
                '<=' => $actual <= $condition->value,
                '='  => abs($actual - $condition->value) < 0.01,
                default => false,
            };
        }

        return $matched;
    }
}
