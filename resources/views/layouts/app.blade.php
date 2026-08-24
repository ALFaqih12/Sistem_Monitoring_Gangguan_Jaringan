<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siwa Network - Sistem Pakar & Monitoring</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            background-color: #f4f6f9;
            color: #333;
            min-height: 100vh;
        }

        /* --- STYLING SIDEBAR --- */
        .sidebar {
            width: 260px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        .sidebar-header h3 {
            color: #3498db;
            font-size: 22px;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        .sidebar-menu a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 20px;
            transition: background 0.3s;
            font-size: 15px;
        }
        .sidebar-menu a:hover {
            background-color: #34495e;
            border-left: 4px solid #3498db;
        }
        /* Penanda menu yang sedang aktif */
        .sidebar-menu a.active {
            background-color: #34495e;
            border-left: 4px solid #3498db;
            font-weight: bold;
        }

        /* --- STYLING MAIN CONTENT --- */
        .main-content {
            margin-left: 260px; /* Jarak agar tidak tertutup sidebar */
            padding: 30px;
            width: calc(100% - 260px);
        }

        /* Efek transisi halus */
        .alert-flash {
            transition: opacity 0.5s ease-out; /* Transisi memudar selama 0.5 detik */
            opacity: 1;
        }
    </style>

    <!-- Tempat untuk menyisipkan CSS khusus per-modul (jika ada) -->
    @stack('styles')
</head>
<body>

    <!-- SIDEBAR KIRI -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Siwa Network</h3>
            <p style="font-size: 12px; color: #bdc3c7;">Monitoring & Expert System</p>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                    Dashboard
                </a>
            </li>
            <li>
                <a href="{{ route('devices.index') }}" class="{{ request()->routeIs('devices.*') ? 'active' : '' }}">
                    Manajemen Target Device
                </a>
            </li>
            <li>
                <a href="{{ route('rules.index') }}" class="{{ request()->routeIs('rules.*') ? 'active' : '' }}">
                    Rule Forward Chaining
                </a>
            </li>
            <li>
                <a href="{{ route('issue-logs.index') }}" class="{{ request()->routeIs('issue-logs.*') ? 'active' : '' }}">
                    Log Gangguan
                </a>
            </li>
            <!-- Menu modul selanjutnya akan kita tambahkan di sini nanti -->
            <!-- <li><a href="#">Manajemen Alert</a></li> -->
        </ul>
    </div>

    <!-- KONTEN KANAN (Dinamis) -->
    <div class="main-content">
        <!-- Bagian ini akan otomatis terisi oleh modul yang sedang dibuka -->
        @yield('content')
    </div>

    <!-- Tempat untuk menyisipkan JavaScript khusus per-modul -->
    @stack('scripts')
</body>
</html>
