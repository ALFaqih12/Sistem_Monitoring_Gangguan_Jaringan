<?php

namespace App\Services;

class MacVendorService
{
    /**
     * Mapping MAC Address prefix (OUI) ke vendor name.
     * Format: 'XX:XX:XX' => 'Vendor Name'
     *
     * List ini manual curated untuk vendor yang umum di Indonesia.
     * Data diambil dari IEEE OUI database dan disesuaikan dengan vendor populer.
     * Bisa ditambah sesuai kebutuhan saat ditemukan vendor baru.
     */
    private array $vendors = [
        // MikroTik
        '00:0C:42' => 'MikroTik',
        '4C:5E:0C' => 'MikroTik',
        '6C:3B:6B' => 'MikroTik',
        '48:8F:5A' => 'MikroTik',
        'D4:CA:6D' => 'MikroTik',
        'E4:8D:8C' => 'MikroTik',
        '74:4D:28' => 'MikroTik',
        'B8:69:F4' => 'MikroTik',
        '08:55:31' => 'MikroTik',

        // Ubiquiti Networks
        'DC:2C:6E' => 'Ubiquiti',
        '74:83:C2' => 'Ubiquiti',
        'F0:9F:C2' => 'Ubiquiti',
        '68:D7:9A' => 'Ubiquiti',
        '24:A4:3C' => 'Ubiquiti',
        '44:D9:E7' => 'Ubiquiti',
        '04:18:D6' => 'Ubiquiti',
        '78:8A:20' => 'Ubiquiti',
        '80:2A:A8' => 'Ubiquiti',
        'FC:EC:DA' => 'Ubiquiti',

        // TP-Link
        'B4:FB:E4' => 'TP-Link',
        '68:72:51' => 'TP-Link',
        'C4:E9:84' => 'TP-Link',
        '50:C7:BF' => 'TP-Link',
        'EC:08:6B' => 'TP-Link',
        '14:CC:20' => 'TP-Link',
        '1C:61:B4' => 'TP-Link',
        'A4:2B:B0' => 'TP-Link',
        '54:AF:97' => 'TP-Link',
        '98:DE:D0' => 'TP-Link',

        // Tenda
        'C8:3A:35' => 'Tenda',
        '00:B0:0C' => 'Tenda',
        'E8:61:7E' => 'Tenda',

        // D-Link
        '00:05:5D' => 'D-Link',
        '00:1B:11' => 'D-Link',
        'C8:BE:19' => 'D-Link',
        '14:D6:4D' => 'D-Link',
        '1C:7E:E5' => 'D-Link',
        '28:10:7B' => 'D-Link',

        // Cambium Networks
        '00:04:56' => 'Cambium',
        '58:C1:7A' => 'Cambium',
        '00:0F:C9' => 'Cambium',

        // Ruckus Wireless
        '2C:56:DC' => 'Ruckus',
        '54:3D:37' => 'Ruckus',
        '24:C9:A1' => 'Ruckus',

        // Huawei
        '00:E0:FC' => 'Huawei',
        '34:6B:D3' => 'Huawei',
        '70:72:3C' => 'Huawei',
        'AC:85:3D' => 'Huawei',

        // ZTE
        '00:1E:E5' => 'ZTE',
        '4C:09:B4' => 'ZTE',
        'A4:3B:FA' => 'ZTE',

        // Cisco
        '00:01:42' => 'Cisco',
        '00:01:43' => 'Cisco',
        '00:01:96' => 'Cisco',
        '00:1B:D5' => 'Cisco',

        // Linksys
        '00:06:25' => 'Linksys',
        '00:13:10' => 'Linksys',
        '00:14:BF' => 'Linksys',

        // Netgear
        '00:09:5B' => 'Netgear',
        '00:1B:2F' => 'Netgear',
        'A0:21:B7' => 'Netgear',

        // ASUS
        '00:0C:6E' => 'ASUS',
        '04:D4:C4' => 'ASUS',
        '1C:87:2C' => 'ASUS',
        '2C:4D:54' => 'ASUS',

        // Raspberry Pi (untuk IoT devices)
        'B8:27:EB' => 'Raspberry Pi',
        'DC:A6:32' => 'Raspberry Pi',
        'E4:5F:01' => 'Raspberry Pi',
    ];

    /**
     * Lookup vendor dari MAC Address.
     * Menggunakan OUI (Organizationally Unique Identifier) - 3 byte pertama dari MAC.
     * 
     * @param string $macAddress MAC Address format: AA:BB:CC:DD:EE:FF atau AA-BB-CC-DD-EE-FF
     * @return string Vendor name atau "Unknown Device" jika tidak ditemukan
     */
    public function lookupVendor(string $macAddress): string
    {
        // Normalize MAC Address: remove delimiter dan uppercase
        $mac = strtoupper(str_replace([':', '-', '.'], '', $macAddress));

        // Validasi panjang MAC address (harus 12 karakter hex)
        if (strlen($mac) < 6) {
            return 'Unknown Device';
        }

        // Get first 6 characters (OUI - 3 bytes)
        $oui = substr($mac, 0, 6);

        // Format dengan colon untuk matching (XX:XX:XX)
        $ouiFormatted = substr($oui, 0, 2) . ':' . substr($oui, 2, 2) . ':' . substr($oui, 4, 2);

        // Lookup di vendor list
        return $this->vendors[$ouiFormatted] ?? 'Unknown Device';
    }

    /**
     * Get semua vendor yang tersedia di database.
     * Digunakan untuk debugging atau statistik.
     * 
     * @return array List vendor yang di-support (unique)
     */
    public function getAvailableVendors(): array
    {
        return array_unique(array_values($this->vendors));
    }

    /**
     * Get total jumlah OUI yang terdaftar di database.
     * 
     * @return int Total OUI entries
     */
    public function getTotalOuiEntries(): int
    {
        return count($this->vendors);
    }

    /**
     * Check apakah vendor tertentu ada di database.
     * 
     * @param string $vendorName Nama vendor (case insensitive)
     * @return bool True jika vendor ada
     */
    public function hasVendor(string $vendorName): bool
    {
        $vendors = array_map('strtolower', array_values($this->vendors));
        return in_array(strtolower($vendorName), $vendors, true);
    }

    /**
     * Add vendor baru ke mapping (runtime only, tidak persist).
     * Untuk production, tambahkan langsung ke array $vendors di atas.
     * 
     * @param string $oui Format: 'XX:XX:XX'
     * @param string $vendorName Nama vendor
     * @return void
     */
    public function addVendor(string $oui, string $vendorName): void
    {
        $this->vendors[$oui] = $vendorName;
    }
}
