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
        <p class="sidebar-subtitle">Sistem Pakar Forward Chaining Jaringan</p>
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
        <li>
          <Link
            href="/reports"
            :className="isActive('Reports') ? 'active' : ''"
            >Report Gangguan</Link
          >
        </li>
        <li>
          <Link
            href="/admin/profile"
            :className="isActive('Admin') ? 'active' : ''"
            >Kelola Admin</Link
          >
        </li>
      </ul>
      
      <!-- Logout Button -->
      <div class="sidebar-footer">
        <button @click="handleLogout" class="btn-logout">
          Logout
        </button>
      </div>
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
import { usePage, router } from '@inertiajs/vue3';

function isActive(prefix) {
  const page = usePage();
  // usePage().component bisa undefined saat hydration awal; amanin dengan optional chaining
  return page.component?.startsWith(prefix) ?? false;
}

/**
 * Logout handler dengan monitoring safeguard.
 * Check monitoring status sebelum logout.
 */
async function handleLogout() {
  try {
    // Check monitoring status
    const response = await fetch('/monitoring/status');
    const data = await response.json();
    
    if (data.is_running) {
      // Monitoring masih aktif, tampilkan warning
      alert('Monitoring masih aktif. Harap matikan monitoring terlebih dahulu sebelum logout.');
      return; // Stay logged in
    }
    
    // Monitoring OFF, aman untuk logout
    router.post('/logout');
  } catch (error) {
    console.error('Error checking monitoring status:', error);
    // Fallback: tetap logout jika check gagal
    router.post('/logout');
  }
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
  color: whitesmoke;
  margin-top: 4px;
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
.sidebar-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px;
}
.btn-logout {
  width: 100%;
  padding: 12px;
  background: var(--color-danger);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  cursor: pointer;
  transition: var(--transition);
}
.btn-logout:hover {
  background: #c0392b;
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