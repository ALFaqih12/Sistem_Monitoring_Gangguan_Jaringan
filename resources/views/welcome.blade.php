<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siwa Network</title>
    <style>
        body {
            align-items: center;
            background: #f4f6f9;
            color: #2c3e50;
            display: flex;
            font-family: Arial, sans-serif;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .welcome-panel {
            background: #ffffff;
            border: 1px solid #dce4ec;
            border-radius: 6px;
            padding: 24px;
            text-align: center;
        }

        .welcome-panel a {
            color: #ffffff;
            background: #3498db;
            border-radius: 4px;
            display: inline-block;
            margin-top: 16px;
            padding: 10px 14px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="welcome-panel">
        <h1>Siwa Network</h1>
        <p>Sistem Pakar Monitoring Gangguan Jaringan</p>
        <a href="{{ route('devices.index') }}">Masuk ke Manajemen Target Device</a>
    </div>
</body>
</html>
