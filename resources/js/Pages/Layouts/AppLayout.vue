<template>
  <!-- AppLayout.vue — layout bersama pengganti Blade layouts/app.blade.php.
  Dipakai otomatis oleh app.js (resolve) sebagai wrapper default untuk tiap halaman.
  Menyediakan sidebar navigasi + slot konten utama.
  Catatan: tidak pakai Tailwind/Bootstrap/Filament — CSS hand-rolled (sesuai AgentGuide). -->
  <div class="app-wrapper">
    <!-- SIDEBAR KIRI -->
    <div class="sidebar">
      <div class="sidebar-header">
        <h3>Siwa Network</h3>
        <p class="sidebar-subtitle">Monitoring & Expert System</p>
      </div>
      <ul class="sidebar-menu">
        <li>
          <Link
            href="/"
            :className="isActive('Dashboard') ? 'active' : ''"
            >Dashboard</Link
          >
        </li>
        <li>
          <Link
            href="/devices"
            :className="isActive('Devices') ? 'active' : ''"
            >Manajemen Target Device</Link
          >
        </li>
        <li>
          <Link
            href="/rules"
            :className="isActive('Rules') ? 'active' : ''"
            >Rule Forward Chaining</Link
          >
        </li>
        <li>
          <Link
            href="/issue-logs"
            :className="isActive('IssueLogs') ? 'active' : ''"
            >Log Gangguan</Link
          >
        </li>
        <li>
          <Link
            href="/alerts"
            :className="isActive('Alerts') ? 'active' : ''"
            >Manajemen Alert</Link
          >
        </li>
      </ul>
    </div>

    <!-- KONTEN KANAN (Dinamis): halaman Inertia dirender di slot default -->
    <div class="main-content">
      <div class="content-wrapper">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';

function isActive(prefix) {
  const page = usePage();
  // usePage().component bisa undefined saat hydration awal; amanin dengan optional chaining
  return page.component?.startsWith(prefix) ?? false;
}
</script>

<style scoped>
.app-wrapper {
  display: flex;
  min-height: 100vh;
}
.sidebar {
  width: var(--sidebar-width);
  flex-shrink: 0;
  background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
  color: var(--color-sidebar-text);
  height: 100vh;
  padding-top: 24px;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
  position: sticky;
  top: 0;
}
.sidebar-header {
  text-align: center;
  padding-bottom: 24px;
  border-bottom: 1px solid #34495e;
  margin-bottom: 24px;
}
.sidebar-header h3 {
  color: var(--color-primary);
  font-size: 22px;
  font-weight: var(--font-weight-bold);
  letter-spacing: 0.5px;
}
.sidebar-subtitle {
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
  margin-top: 4px;
  opacity: 0.8;
}
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}
.sidebar-menu li {
  margin-bottom: 4px;
}
.sidebar-menu a {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--color-sidebar-text);
  text-decoration: none;
  padding: 12px 24px;
  transition: var(--transition);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-medium);
  border-left: 3px solid transparent;
  opacity: 0.9;
}
.sidebar-menu a:hover {
  background: rgba(52, 152, 219, 0.08);
  border-left: 3px solid var(--color-primary);
  opacity: 1;
}
.sidebar-menu a.active {
  background: rgba(52, 152, 219, 0.12);
  border-left: 3px solid var(--color-primary);
  color: var(--color-primary);
  font-weight: var(--font-weight-bold);
  opacity: 1;
}
.main-content {
  flex: 1;
  min-width: 0;
  background-color: var(--color-bg);
  padding: 2rem;
}
.content-wrapper {
  width: 100%;
}
</style>