<!DOCTYPE html>
    <html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF token: dibaca Vue (native fetch) untuk request POST state-mutation
         (monitoring toggle dsb.). Di halaman Inertia ini. -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- inertia pada <title> menandakan target jududul yang akan diupdate Inertia Head. -->
    <title inertia>Siwa Network</title>

    <!-- Suntungkan <head> yang dideklarasikan komponen Vue (meta, title, css). -->
    @inertiaHead

    <!-- Manifest + CSS/JS aplikasi yang dibangun Vite. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Komponen halaman Inertia dirender di dalam #app secaraSPA. -->
    @inertia
</body>
</html>
