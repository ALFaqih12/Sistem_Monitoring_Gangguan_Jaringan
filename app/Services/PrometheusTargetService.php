<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class PrometheusTargetService
{
    /**
     * Path file target untuk SNMP exporter
     */
    private function snmpTargetFilePath(): string
    {
        return base_path('../prometheus/targets.json');
    }

    /**
     * Path file target untuk Blackbox exporter (ping/latency)
     */
    private function blackboxTargetFilePath(): string
    {
        return base_path('../prometheus/blackbox-targets.json');
    }

    /**
     * Path file target untuk MikroTik SNMP exporter (DHCP lease bandwidth)
     * File ini berisi IP Core Router MikroTik, bukan IP client.
     */
    private function mikrotikTargetFilePath(): string
    {
        return base_path('../prometheus/mikrotik-targets.json');
    }

    /**
     * Menambahkan IP target ke SNMP exporter dan Blackbox exporter sekaligus.
     * Method ini dipanggil saat user submit device baru.
     * Dilengkapi backup-restore untuk menjaga konsistensi kedua file.
     */
    public function addTarget(string $ipAddress): void
    {
        // Backup kedua file sebelum operasi untuk antisipasi rollback
        $snmpBackup = $this->backupFile($this->snmpTargetFilePath());
        $blackboxBackup = $this->backupFile($this->blackboxTargetFilePath());

        try {
            $this->addSnmpTarget($ipAddress);
            $this->addBlackboxTarget($ipAddress);
        } catch (Throwable $e) {
            // Restore kedua file ke kondisi awal jika salah satu operasi gagal
            $this->restoreFile($this->snmpTargetFilePath(), $snmpBackup);
            $this->restoreFile($this->blackboxTargetFilePath(), $blackboxBackup);
            throw $e;
        }
    }

    /**
     * Menghapus IP target dari SNMP exporter dan Blackbox exporter sekaligus.
     * Method ini dipanggil saat user delete device.
     * Dilengkapi backup-restore untuk menjaga konsistensi kedua file.
     */
    public function removeTarget(string $ipAddress): void
    {
        // Backup kedua file sebelum operasi untuk antisipasi rollback
        $snmpBackup = $this->backupFile($this->snmpTargetFilePath());
        $blackboxBackup = $this->backupFile($this->blackboxTargetFilePath());

        try {
            $this->removeSnmpTarget($ipAddress);
            $this->removeBlackboxTarget($ipAddress);
        } catch (Throwable $e) {
            // Restore kedua file ke kondisi awal jika salah satu operasi gagal
            $this->restoreFile($this->snmpTargetFilePath(), $snmpBackup);
            $this->restoreFile($this->blackboxTargetFilePath(), $blackboxBackup);
            throw $e;
        }
    }

    /**
     * Membaca konten file sebagai backup sebelum dimodifikasi.
     * Return null jika file belum ada.
     */
    private function backupFile(string $filePath): ?string
    {
        if (File::exists($filePath)) {
            return File::get($filePath);
        }
        return null;
    }

    /**
     * Mengembalikan file ke kondisi backup jika operasi gagal di tengah jalan.
     */
    private function restoreFile(string $filePath, ?string $backup): void
    {
        if ($backup !== null) {
            File::put($filePath, $backup);
        } else {
            // Jika file asli tidak ada (null backup), hapus file yang sudah terlanjur dibuat
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }
    }

    /**
     * Menambahkan IP target ke file SNMP targets.json
     */
    private function addSnmpTarget(string $ipAddress): void
    {
        $targets = $this->readTargets($this->snmpTargetFilePath());

        // Cek apakah IP sudah ada
        foreach ($targets as $target) {
            if (in_array($ipAddress, $target['targets'], true)) {
                return; // IP sudah ada, skip
            }
        }

        // Tambahkan target SNMP baru
        $targets[] = [
            'targets' => [$ipAddress],
            'labels' => [
                'instance' => $ipAddress,
                'job' => 'snmp',
            ],
        ];

        $this->writeTargets($this->snmpTargetFilePath(), $targets);
    }

    /**
     * Menambahkan IP target ke file Blackbox targets.json
     */
    private function addBlackboxTarget(string $ipAddress): void
    {
        $targets = $this->readTargets($this->blackboxTargetFilePath());

        // Cek apakah IP sudah ada
        foreach ($targets as $target) {
            if (in_array($ipAddress, $target['targets'], true)) {
                return; // IP sudah ada, skip
            }
        }

        // Tambahkan target Blackbox baru untuk ICMP probe (ping)
        $targets[] = [
            'targets' => [$ipAddress],
            'labels' => [
                'instance' => $ipAddress,
                'job' => 'ping',
            ],
        ];

        $this->writeTargets($this->blackboxTargetFilePath(), $targets);
    }

    /**
     * Menghapus IP target dari file SNMP targets.json
     */
    private function removeSnmpTarget(string $ipAddress): void
    {
        $targets = $this->readTargets($this->snmpTargetFilePath());

        $filteredTargets = array_values(array_filter($targets, function (array $target) use ($ipAddress): bool {
            return ! in_array($ipAddress, $target['targets'], true);
        }));

        $this->writeTargets($this->snmpTargetFilePath(), $filteredTargets);
    }

    /**
     * Menghapus IP target dari file Blackbox targets.json
     */
    private function removeBlackboxTarget(string $ipAddress): void
    {
        $targets = $this->readTargets($this->blackboxTargetFilePath());

        $filteredTargets = array_values(array_filter($targets, function (array $target) use ($ipAddress): bool {
            return ! in_array($ipAddress, $target['targets'], true);
        }));

        $this->writeTargets($this->blackboxTargetFilePath(), $filteredTargets);
    }

    /**
     * Menambahkan IP Core Router ke file mikrotik-targets.json
     * Dipanggil saat device dari discovery ditambahkan.
     * File ini digunakan Prometheus untuk scrape DHCP lease counter dari MikroTik.
     * 
     * @param string $coreRouterIp IP MikroTik Core Router
     */
    public function addMikrotikTarget(string $coreRouterIp): void
    {
        $targets = $this->readTargets($this->mikrotikTargetFilePath());

        // Cek apakah Core Router IP sudah ada (hindari duplikat)
        foreach ($targets as $target) {
            if (in_array($coreRouterIp, $target['targets'], true)) {
                return; // Sudah ada, skip
            }
        }

        // Tambahkan Core Router sebagai target SNMP MikroTik
        $targets[] = [
            'targets' => [$coreRouterIp],
            'labels' => [
                'instance' => $coreRouterIp,
                'job' => 'mikrotik_dhcp',
            ],
        ];

        $this->writeTargets($this->mikrotikTargetFilePath(), $targets);
    }

    /**
     * Menghapus IP Core Router dari file mikrotik-targets.json
     * Dipanggil saat tidak ada lagi device yang menggunakan Core Router tersebut.
     * 
     * @param string $coreRouterIp IP MikroTik Core Router
     */
    public function removeMikrotikTarget(string $coreRouterIp): void
    {
        $targets = $this->readTargets($this->mikrotikTargetFilePath());

        $filteredTargets = array_values(array_filter($targets, function (array $target) use ($coreRouterIp): bool {
            return ! in_array($coreRouterIp, $target['targets'], true);
        }));

        $this->writeTargets($this->mikrotikTargetFilePath(), $filteredTargets);
    }

    /**
     * Membaca file targets.json dan menolak struktur JSON yang tidak sesuai format Prometheus.
     *
     * @param string $filePath Path ke file target JSON
     * @return array<int, array{targets: array<int, string>, labels?: array<string, string>}>
     */
    private function readTargets(string $filePath): array
    {
        if (! File::exists($filePath)) {
            return [];
        }

        $decodedTargets = json_decode(File::get($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decodedTargets)) {
            throw new RuntimeException("File {$filePath} tidak berisi JSON array yang valid.");
        }

        foreach ($decodedTargets as $target) {
            if (! is_array($target) || ! isset($target['targets']) || ! is_array($target['targets'])) {
                throw new RuntimeException("Struktur {$filePath} tidak sesuai format target Prometheus.");
            }
        }

        return $decodedTargets;
    }

    /**
     * Menulis targets.json secara atomic agar file tidak mudah rusak saat proses gagal di tengah jalan.
     *
     * @param string $filePath Path ke file target JSON
     * @param array $targets Array targets yang akan ditulis
     */
    private function writeTargets(string $filePath, array $targets): void
    {
        $directory = dirname($filePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = json_encode($targets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException("Gagal mengubah data target Prometheus menjadi JSON untuk {$filePath}.");
        }

        if (File::put($filePath, $json) === false) {
            throw new RuntimeException("Gagal menulis file {$filePath}.");
        }
    }
}