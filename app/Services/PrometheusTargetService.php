<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PrometheusTargetService
{
    /**
     * Menentukan lokasi file target Prometheus yang dipakai oleh file_sd_config.
     */
    private function targetFilePath(): string
    {
        return base_path('../prometheus/targets.json');
    }

    /**
     * Menambahkan IP target ke file targets.json setelah struktur JSON dipastikan valid.
     */
    public function addTarget(string $ipAddress): void
    {
        $targets = $this->readTargets();

        foreach ($targets as $target) {
            if (in_array($ipAddress, $target['targets'], true)) {
                return;
            }
        }

        $targets[] = [
            'targets' => [$ipAddress],
            'labels' => [
                'instance' => $ipAddress,
                'job' => 'snmp',
            ],
        ];

        $this->writeTargets($targets);
    }

    /**
     * Menghapus IP target dari file targets.json tanpa mengubah target lain.
     */
    public function removeTarget(string $ipAddress): void
    {
        $targets = $this->readTargets();

        $filteredTargets = array_values(array_filter($targets, function (array $target) use ($ipAddress): bool {
            return ! in_array($ipAddress, $target['targets'], true);
        }));

        $this->writeTargets($filteredTargets);
    }

    /**
     * Membaca file targets.json dan menolak struktur JSON yang tidak sesuai format Prometheus.
     *
     * @return array<int, array{targets: array<int, string>, labels?: array<string, string>}>
     */
    private function readTargets(): array
    {
        $filePath = $this->targetFilePath();

        if (! File::exists($filePath)) {
            return [];
        }

        $decodedTargets = json_decode(File::get($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decodedTargets)) {
            throw new RuntimeException('File targets.json tidak berisi JSON array yang valid.');
        }

        foreach ($decodedTargets as $target) {
            if (! is_array($target) || ! isset($target['targets']) || ! is_array($target['targets'])) {
                throw new RuntimeException('Struktur targets.json tidak sesuai format target Prometheus.');
            }
        }

        return $decodedTargets;
    }

    /**
     * Menulis targets.json secara atomic agar file tidak mudah rusak saat proses gagal di tengah jalan.
     */
    private function writeTargets(array $targets): void
    {
        $filePath = $this->targetFilePath();
        $directory = dirname($filePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = json_encode($targets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Gagal mengubah data target Prometheus menjadi JSON.');
        }

        if (File::put($filePath, $json) === false) {
            throw new RuntimeException('Gagal menulis file targets.json Prometheus.');
        }
    }
}
