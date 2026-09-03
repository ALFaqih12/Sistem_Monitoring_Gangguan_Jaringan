<template>
  <Head title="Dashboard" />
  <div class="page-container">
    <h2 class="page-title">Dashboard Monitoring</h2>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ totalDevices }}</div>
          <div class="stat-label">Total Target Device</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 11l3 3L22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <div class="stat-info">
          <div class="stat-value">{{ totalRules }}</div>
          <div class="stat-label">Total Rule Forward Chaining</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Start Monitoring</h3>
          <p>Aktifkan atau nonaktifkan proses monitoring otomatis (Forward Chaining Engine)</p>
        </div>
        <label class="toggle-switch">
          <input type="checkbox" id="monitoringToggle" :checked="localActive" @change="onToggleChange" />
          <span class="toggle-slider"></span>
        </label>
      </div>

      <div class="status-badges">
        <span class="badge" :class="monitoringActive ? 'badge-success' : 'badge-danger'">
          {{ monitoringActive ? 'MONITORING AKTIF' : 'MONITORING NONAKTIF' }}
        </span>
        <span class="badge" :class="workerBadgeClass">
          Worker: {{ workerBadgeText }}
        </span>
      </div>

      <div v-if="toggleStatusText" class="status-message" :class="toggleStatusClass">{{ toggleStatusText }}</div>

      <div class="info-panel">
        <div class="info-row">
          <strong>Cara Kerja:</strong> Saat monitoring aktif, worker process berjalan di background dan mengeksekusi evaluasi Forward Chaining setiap <code>1 menit</code> sekali.
        </div>
        <div class="info-row">
          <strong>Log Worker:</strong> <code>storage/logs/monitoring-worker.log</code>
        </div>
        <div class="info-row">
          <strong>Status Worker:</strong>
          <span v-if="workerRunning && workerHealthy" class="text-success font-bold">SEHAT</span>
          <span v-else-if="workerRunning && !workerHealthy" class="text-danger font-bold">WORKER MATI / MACET!</span>
          <span v-else class="text-muted">Worker tidak berjalan.</span>
          <span v-if="workerRunning" class="worker-detail">
            (heartbeat terakhir: {{ lastHeartbeat > 0 ? formatHeartbeat(lastHeartbeat) : 'tidak pernah' }}
            <span v-if="!workerHealthy"> - sudah lebih dari 2 menit tidak merespons</span>)
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  totalDevices: { type: Number, default: 0 },
  totalRules: { type: Number, default: 0 },
  monitoringActive: { type: Boolean, default: false },
  workerRunning: { type: Boolean, default: false },
  workerHealthy: { type: Boolean, default: false },
  lastHeartbeat: { type: [Number, String], default: 0 },
  heartbeatAge: { type: Number, default: 0 },
});

const localActive = ref(props.monitoringActive);
watch(() => props.monitoringActive, (v) => { localActive.value = v; });

const isToggling = ref(false);
const toggleStatusText = ref('');

const workerBadgeClass = computed(() => {
  if (props.workerRunning && props.workerHealthy) return 'badge-success';
  if (props.workerRunning && !props.workerHealthy) return 'badge-danger';
  return 'badge-warning';
});
const workerBadgeText = computed(() => {
  if (props.workerRunning && props.workerHealthy) return 'Berjalan (Sehat)';
  if (props.workerRunning && !props.workerHealthy) return 'MATI / MACET!';
  return 'Berhenti';
});

const toggleStatusClass = computed(() => {
  if (toggleStatusText.value.startsWith('Error')) return 'text-danger';
  if (toggleStatusText.value.includes('aktif') || toggleStatusText.value.includes('dinonaktifkan')) return 'text-success';
  return 'text-info';
});

function formatHeartbeat(ts) {
  const t = Number(ts);
  if (!t || t <= 0) return '-';
  const d = new Date(t * 1000);
  return d.toLocaleTimeString('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
}

async function onToggleChange(e) {
  if (isToggling.value) {
    e.target.checked = localActive.value;
    return;
  }

  const active = e.target.checked;
  localActive.value = active;
  isToggling.value = true;
  toggleStatusText.value = active ? 'Mengaktifkan monitoring...' : 'Menonaktifkan monitoring...';

  try {
    const res = await fetch('/monitoring/toggle', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ active }),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Gagal.');

    toggleStatusText.value = data.message;
    router.reload();
  } catch (err) {
    localActive.value = !active;
    e.target.checked = !active;
    toggleStatusText.value = 'Error: ' + err.message;
  } finally {
    isToggling.value = false;
  }
}
</script>

<style scoped>
.page-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 var(--content-padding);
}
.page-title {
  margin: 0 0 24px;
  color: var(--color-text);
  font-size: 28px;
  font-weight: var(--font-weight-bold);
  padding-bottom: 16px;
  border-bottom: 2px solid var(--color-border);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--color-card);
  border-radius: var(--radius);
  padding: 24px;
  box-shadow: var(--shadow);
  border: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 20px;
  transition: var(--transition);
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-hover);
  border-color: var(--color-primary);
}
.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-icon.blue {
  background: linear-gradient(135deg, rgba(52,152,219,0.1), rgba(52,152,219,0.15));
  color: var(--color-primary);
}
.stat-icon.green {
  background: linear-gradient(135deg, rgba(39,174,96,0.1), rgba(39,174,96,0.15));
  color: var(--color-success);
}
.stat-icon svg {
  width: 28px;
  height: 28px;
}
.stat-value {
  font-size: 36px;
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  line-height: 1.2;
}
.stat-label {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  margin-top: 4px;
}

.card {
  background: var(--color-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--color-border);
  padding: 28px;
  margin-top: 24px;
}

.status-badges {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
</style>