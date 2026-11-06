<template>
  <Head title="Dashboard" />
  <div class="page-container">
    <!-- 1. Header -->
    <h1 class="dashboard-title">Dashboard Monitoring</h1>

    <!-- 2. Deteksi Gangguan Container -->
    <div class="detection-card">
      <div class="detection-left">
        <div class="info-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
        </div>
        <div class="detection-content">
          <div class="detection-header">
            <span class="detection-title">Start Monitoring</span>
            <span 
              class="badge-status"
              :class="localActive ? 'badge-active' : 'badge-inactive'"
            >
              Status {{ localActive ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <p class="detection-subtitle">
            Deteksi metric dan evaluasi dengan Forward chaining dalam 1 menit
          </p>
        </div>
      </div>
      <div class="detection-right">
        <label class="toggle-switch">
          <input type="checkbox" :checked="localActive" @change="onToggleChange" />
          <span class="toggle-slider"></span>
        </label>
      </div>
    </div>

    <!-- 3. Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon yellow">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="20" x2="12" y2="10"/>
            <line x1="18" y1="20" x2="18" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="16"/>
          </svg>
        </div>
        <div class="summary-content">
          <div class="summary-value">
            {{ summary.aggregated_bandwidth }}
            <span class="summary-unit">Mbps</span>
          </div>
          <div class="summary-label">Aggregated Bandwidth</div>
        </div>
      </div>

      <div class="summary-card">
        <div class="summary-icon blue">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
            <polyline points="17 6 23 6 23 12"/>
          </svg>
        </div>
        <div class="summary-content">
          <div class="summary-value">
            {{ summary.average_latency }}
            <span class="summary-unit">Ms</span>
          </div>
          <div class="summary-label">Average ping latency</div>
        </div>
      </div>

      <div class="summary-card">
        <div class="summary-icon green">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <div class="summary-content">
          <div class="summary-value">
            {{ summary.average_packet_loss }}
            <span class="summary-unit">%</span>
          </div>
          <div class="summary-label">Average Packet Loss</div>
        </div>
      </div>
    </div>

    <!-- 4. Section Header with Search -->
    <div class="monitoring-header">
      <div class="monitoring-header-left">
        <div class="monitoring-title-row">
          <h2 class="monitoring-title">Monitoring Device Real-time</h2>
          <span class="badge-live">Live Real</span>
        </div>
        <p class="monitoring-subtitle">
          Metric visual per device (Bandwidth, Latency, Packet Loss)
        </p>
      </div>
      <div class="monitoring-header-right">
        <div class="search-wrapper">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
          </svg>
          <input 
            type="text" 
            class="search-input" 
            placeholder="Cari pelanggan / IP"
            v-model="searchQuery"
          />
        </div>
        <a href="#" class="status-link">Semua Status ({{ devices.length }})</a>
      </div>
    </div>

    <!-- 5. Device Cards Grid - Horizontal Scroll Wrapper -->
    <div class="device-grid-wrapper">
      <div class="device-grid">
        <div 
          v-for="device in filteredDevices" 
          :key="device.id"
          class="device-card"
          :class="getDeviceStatusClass(device)"
        >
        <div class="device-header">
          <h3 class="device-name">{{ device.customer_name }}</h3>
          <span 
            class="device-badge"
            :class="getDeviceBadgeClass(device)"
          >
            {{ getDeviceStatusText(device) }}
          </span>
        </div>

        <div class="device-info">
          <p class="device-ip">{{ device.ip_address }}</p>
          <p class="device-model">{{ device.device_model }}</p>
        </div>

        <div class="device-metrics">
          <div class="metric-row">
            <span class="metric-label">BANDWIDTH</span>
            <span 
              class="metric-value"
              :class="getMetricColorClass(device, 'bandwidth')"
            >
              {{ formatMetric(device.metrics?.bandwidth) }}
              <span class="metric-unit">Mbps</span>
            </span>
          </div>
          
          <div class="metric-row">
            <span class="metric-label">LATENCY</span>
            <span 
              class="metric-value"
              :class="getMetricColorClass(device, 'latency')"
            >
              {{ formatMetric(device.metrics?.latency) }}
              <span class="metric-unit">ms</span>
            </span>
          </div>
          
          <div class="metric-row">
            <span class="metric-label">LOSS</span>
            <span 
              class="metric-value"
              :class="getMetricColorClass(device, 'packet_loss')"
            >
              {{ formatMetric(device.metrics?.packet_loss) }}
              <span class="metric-unit">%</span>
            </span>
          </div>
        </div>

        <div class="device-footer">
          <span 
            v-if="device.matched_rule_text && device.metrics" 
            class="rule-text"
          >
            {{ device.matched_rule_text }}
          </span>
          <span class="time-text">{{ device.metrics?.updated_at || 'Belum ada data' }}</span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredDevices.length === 0 && !loadingMetrics" class="empty-state">
        <div class="empty-icon">📡</div>
        <p class="empty-text">
          {{ searchQuery ? 'Tidak ada device yang cocok dengan pencarian' : 'Belum ada device terdaftar untuk monitoring' }}
        </p>
        <a v-if="!searchQuery" href="/devices" class="btn-primary-link">Tambah Device</a>
      </div>
    </div>
    <!-- End device-grid -->
  </div>
  <!-- End device-grid-wrapper -->
</div>
<!-- End page-container -->
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

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

// Monitoring Metrics State
const summary = ref({
  aggregated_bandwidth: 0,
  average_latency: 0,
  average_packet_loss: 0,
});
const devices = ref([]);
const loadingMetrics = ref(false);
const searchQuery = ref('');
let metricsInterval = null;

/**
 * Fetch metrics dari API endpoint.
 * Called on mount dan setiap 60 detik (sync dengan CRON cycle).
 */
async function fetchMetrics() {
  loadingMetrics.value = true;
  try {
    const response = await fetch('/api/dashboard/metrics');
    if (response.ok) {
      const data = await response.json();
      summary.value = data.summary;
      devices.value = data.devices;
    }
  } catch (error) {
    console.error('Failed to fetch metrics:', error);
  } finally {
    loadingMetrics.value = false;
  }
}

/**
 * Format metric value dengan fallback "-" jika null.
 */
function formatMetric(value) {
  return value !== null && value !== undefined 
    ? value.toFixed(2) 
    : '-';
}

/**
 * Get device status class untuk card border.
 * Idle (gray) jika belum ada data atau stale (offline pattern ≥2 min), otherwise red/green.
 */
function getDeviceStatusClass(device) {
  if (!device.metrics || device.is_stale) return 'idle';
  return device.has_issue ? 'has-issue' : 'normal';
}

/**
 * Get badge class untuk status badge.
 */
function getDeviceBadgeClass(device) {
  if (!device.metrics || device.is_stale) return 'badge-idle';
  return device.has_issue ? 'badge-danger' : 'badge-success';
}

/**
 * Get status text untuk badge.
 */
function getDeviceStatusText(device) {
  if (!device.metrics || device.is_stale) return 'IDLE';
  return device.has_issue ? 'GANGGUAN' : 'NORMAL';
}

/**
 * Get metric color class.
 * Gray jika idle, red jika gangguan, black jika normal.
 */
function getMetricColorClass(device, metricName) {
  if (!device.metrics) return 'metric-idle';
  if (device.matched_metrics?.includes(metricName)) return 'metric-danger';
  return 'metric-normal';
}

/**
 * Filter devices berdasarkan search query (customer name atau IP)
 */
const filteredDevices = computed(() => {
  if (!searchQuery.value) return devices.value;
  
  const query = searchQuery.value.toLowerCase();
  return devices.value.filter(device => 
    device.customer_name.toLowerCase().includes(query) ||
    device.ip_address.toLowerCase().includes(query)
  );
});

// Toggle monitoring
async function onToggleChange(e) {
  if (isToggling.value) {
    e.target.checked = localActive.value;
    return;
  }

  const active = e.target.checked;
  localActive.value = active;
  isToggling.value = true;

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

    router.reload();
  } catch (err) {
    localActive.value = !active;
    e.target.checked = !active;
    alert('Error: ' + err.message);
  } finally {
    isToggling.value = false;
  }
}

// Lifecycle hooks untuk metrics
onMounted(() => {
  fetchMetrics();
  metricsInterval = setInterval(fetchMetrics, 60000); // Refresh every 60s
});

onUnmounted(() => {
  if (metricsInterval) {
    clearInterval(metricsInterval);
  }
});
</script>

<style scoped>
/* Global Container */
.page-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
  background: #f4f6f9;
}

/* 1. Dashboard Title */
.dashboard-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 24px 0;
}

/* 2. Deteksi Gangguan Card */
.detection-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  padding: 20px 24px;
  margin-bottom: 24px;
}

.detection-left {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  flex: 1;
}

.info-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(52, 152, 219, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3498db;
  flex-shrink: 0;
}

.detection-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detection-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.detection-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
}

.badge-status {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.badge-active {
  background: rgba(39, 174, 96, 0.1);
  color: #27ae60;
}

.badge-inactive {
  background: rgba(231, 76, 60, 0.1);
  color: #e74c3c;
}

.detection-subtitle {
  font-size: 13px;
  color: #6c7a89;
  margin: 0;
}

.detection-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.toggle-label {
  font-size: 14px;
  font-weight: 500;
  color: #2c3e50;
}

.toggle-switch {
  position: relative;
  width: 48px;
  height: 24px;
  display: inline-block;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e0;
  transition: 0.3s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: #3498db;
}

input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

/* 3. Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.summary-card {
  background: white;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.summary-icon.yellow {
  background: rgba(241, 196, 15, 0.15);
  color: #f1c40f;
}

.summary-icon.blue {
  background: rgba(52, 152, 219, 0.15);
  color: #3498db;
}

.summary-icon.green {
  background: rgba(39, 174, 96, 0.15);
  color: #27ae60;
}

.summary-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-value {
  font-size: 32px;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.summary-unit {
  font-size: 14px;
  font-weight: 400;
  color: #6c7a89;
  margin-left: 4px;
}

.summary-label {
  font-size: 13px;
  color: #6c7a89;
}

/* 4. Monitoring Header */
.monitoring-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e8edf3;
}

.monitoring-header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.monitoring-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.monitoring-title {
  font-size: 20px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.badge-live {
  padding: 4px 12px;
  background: rgba(6, 182, 212, 0.1);
  color: #06b6d4;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.monitoring-subtitle {
  font-size: 12px;
  color: #6c7a89;
  margin: 0;
}

.monitoring-header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #95a5a6;
}

.search-input {
  padding: 8px 12px 8px 36px;
  border: 1px solid #e8edf3;
  border-radius: 8px;
  font-size: 13px;
  width: 220px;
  outline: none;
  transition: all 0.2s;
}

.search-input:focus {
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.status-link {
  font-size: 13px;
  color: #3498db;
  text-decoration: none;
  font-weight: 500;
}

.status-link:hover {
  text-decoration: underline;
}

/* 5. Device Cards Grid - Scroll Snap (Magnet Effect) */
.device-grid-wrapper {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 16px;
  margin-top: 0;
  
  /* Smooth scroll behavior */
  scroll-behavior: smooth;
  
  /* CSS Scroll Snap - magnet effect */
  scroll-snap-type: x mandatory;
  
  /* Hide scrollbar */
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE/Edge */
}

.device-grid-wrapper::-webkit-scrollbar {
  display: none; /* Chrome/Safari/Opera */
}

.device-grid {
  display: grid;
  grid-template-rows: repeat(2, 1fr); /* 2 rows */
  grid-auto-flow: column; /* Flow horizontally (add columns as needed) */
  
  /* Dynamic column width: 3 columns fit viewport, rest scroll */
  grid-auto-columns: minmax(320px, calc((100% - 48px) / 3));
  
  gap: 20px 24px; /* row-gap 20px, column-gap 24px */
  width: 100%; /* Force full width */
  min-width: min-content;
}

.device-card {
  min-width: 320px;
  max-width: 100%;
  background: white;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s ease;
  position: relative;
  
  /* Snap to start of each card in row 1 (odd positions = column start) */
  scroll-snap-align: start;
  scroll-snap-stop: normal;
}

/* Force snap on column start (every 2 cards = 1 column for 2-row grid) */
.device-card:nth-child(odd) {
  scroll-snap-align: start;
  scroll-snap-stop: always;
}

.device-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.device-card.has-issue {
  border-left: 4px solid #e74c3c;
}

.device-card.normal {
  border-left: 4px solid #27ae60;
}

.device-card.idle {
  border-left: 4px solid #95a5a6;
}

.device-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.device-name {
  font-size: 15px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
  flex: 1;
}

.device-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.badge-danger {
  background: rgba(231, 76, 60, 0.1);
  color: #e74c3c;
}

.badge-success {
  background: rgba(39, 174, 96, 0.1);
  color: #27ae60;
}

.badge-idle {
  background: rgba(149, 165, 166, 0.1);
  color: #95a5a6;
}

.device-info {
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.device-ip {
  font-size: 13px;
  font-family: 'Courier New', monospace;
  color: #3498db;
  margin: 0 0 4px 0;
}

.device-model {
  font-size: 12px;
  color: #6c7a89;
  margin: 0;
}

.device-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.metric-row {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
}

.metric-label {
  font-size: 10px;
  font-weight: 600;
  color: #95a5a6;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric-value {
  font-size: 18px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.metric-value.metric-normal {
  color: #1a1a1a;
}

.metric-value.metric-danger {
  color: #e74c3c;
}

.metric-value.metric-idle {
  color: #95a5a6;
}

.metric-unit {
  font-size: 12px;
  font-weight: 400;
  color: #6c7a89;
}

.device-footer {
  padding-top: 12px;
  border-top: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.rule-text {
  font-size: 11px;
  color: #e74c3c;
  font-weight: 500;
}

.time-text {
  font-size: 11px;
  color: #95a5a6;
}

/* Empty State */
.empty-state {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  background: white;
  border: 1px dashed #e8edf3;
  border-radius: 12px;
  gap: 16px;
}

.empty-icon {
  font-size: 48px;
  opacity: 0.5;
}

.empty-text {
  font-size: 14px;
  color: #6c7a89;
  margin: 0;
  text-align: center;
}

.btn-primary-link {
  padding: 10px 20px;
  background: #3498db;
  color: white;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-primary-link:hover {
  background: #2980b9;
  box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

/* Responsive */
@media (max-width: 1024px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .monitoring-header {
    flex-direction: column;
    gap: 16px;
  }
  
  .monitoring-header-right {
    width: 100%;
    justify-content: space-between;
  }
}

@media (max-width: 768px) {
  .page-container {
    padding: 16px;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  /* Mobile: Single column, no scroll */
  .device-grid-wrapper {
    overflow-x: visible;
  }
  
  .device-grid {
    grid-template-rows: auto; /* Remove fixed 2 rows */
    grid-auto-flow: row; /* Stack vertically */
    grid-auto-columns: auto;
  }
  
  .device-card {
    min-width: auto;
    max-width: none;
  }
  
  .detection-card {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .detection-right {
    width: 100%;
    justify-content: space-between;
  }
  
  .search-input {
    width: 180px;
  }
  
  .device-metrics {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
</style>
