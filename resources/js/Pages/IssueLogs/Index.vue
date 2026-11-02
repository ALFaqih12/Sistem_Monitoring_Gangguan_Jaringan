<template>
  <Head title="Log Gangguan" />
  <div class="page-container">
    <div class="page-header">
      <h2>Log Gangguan Terdeteksi</h2>
    </div>

    <div class="auto-refresh-bar">
      <span class="refresh-dot" :class="{ updating: isRefreshing }" />
      <span>Auto-refresh setiap 60 detik</span>
      <span class="separator">|</span>
      <span>Terakhir update: <strong>{{ lastUpdated }}</strong></span>
      <button class="btn btn-ghost btn-sm" @click="refreshLogs">Refresh Manual</button>
    </div>

    <!-- Filter bar dengan date filter di kiri dan search di kanan -->
    <div class="filter-bar">
      <div class="filter-left">
        <input v-model="dateFrom" type="date" class="form-input" style="width: 160px;" />
        <input v-model="dateTo" type="date" class="form-input" style="width: 160px;" />
        <button type="button" class="btn btn-secondary btn-sm" @click="applyDateFilter">Filter</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="clearDateFilter">Clear</button>
      </div>
      <div class="filter-right">
        <input
          v-model="searchText"
          class="form-input"
          placeholder="Cari: nama pelanggan, IP, kode rule..."
          @keyup.enter="doSearch"
          style="width: 300px;"
        />
        <button type="button" class="btn btn-primary btn-sm" @click="doSearch">Cari</button>
        <button v-if="searchText" type="button" class="btn btn-ghost btn-sm" @click="clearSearch">Reset</button>
      </div>
    </div>

    <div class="card">
      <div class="table-scroll-container">
        <table>
          <thead>
            <tr>
              <th style="width: 140px;">Waktu</th>
              <th>Pelanggan / IP</th>
              <th style="width: 120px;">Device</th>
              <th>Rule</th>
              <th>Metrics</th>
              <th style="width: 130px;">Lokasi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in displayedLogs" :key="log.id">
               <!-- Format waktu DD/MM/YYYY HH:MM (misal: 01/09/2026 15:22).
                    Menggunakan accessor model detected_at_formatted yang sudah
                    diformat di backend dengan timezone aplikasi (config('app.timezone')).
                    Jika accessor tidak tersedia (misal: dari AJAX fetch), fallback ke detected_at. -->
               <td class="cell-strong">{{ log.detected_at_formatted ?? log.detected_at ?? '-' }}</td>
              <td>
                <div class="cell-strong">{{ log.device?.customer_name ?? '-' }}</div>
                <div class="cell-ip">{{ log.device?.ip_address ?? '-' }}</div>
              </td>
              <td>{{ log.device?.device_model ?? '-' }}</td>
              <td>
                <span class="badge badge-code">{{ log.rule?.code ?? '-' }}</span>
                <small class="text-muted">{{ truncate(log.rule?.name ?? '-', 30) }}</small>
              </td>
                <!-- Kolom metrics: tampilkan HANYA nilai yang match dengan rule.
                     Jika metric tidak match, tidak ditampilkan (filtered out).
                     Metric yang match = gangguan = warna MERAH. -->
                <td>
                  <div v-if="log.matched_metrics?.bandwidth && log.bandwidth_actual != null" class="metric metric-bad">
                    BW: {{ log.bandwidth_actual }} Mbps
                    <span v-if="log.thresholds?.bandwidth"> (threshold: {{ log.thresholds.bandwidth.operator }}{{ log.thresholds.bandwidth.value }})</span>
                  </div>
                  <div v-if="log.matched_metrics?.latency && log.latency_actual != null" class="metric metric-bad">
                    Lat: {{ log.latency_actual }} ms
                    <span v-if="log.thresholds?.latency"> (threshold: {{ log.thresholds.latency.operator }}{{ log.thresholds.latency.value }})</span>
                  </div>
                  <div v-if="log.matched_metrics?.packet_loss && log.packet_loss_actual != null" class="metric metric-bad">
                    PL: {{ log.packet_loss_actual }}%
                    <span v-if="log.thresholds?.packet_loss"> (threshold: {{ log.thresholds.packet_loss.operator }}{{ log.thresholds.packet_loss.value }})</span>
                  </div>
                  <span v-if="!log.matched_metrics?.bandwidth && !log.matched_metrics?.latency && !log.matched_metrics?.packet_loss" class="text-muted">-</span>
                </td>
              <td>
                <a
                  v-if="log.device?.maps_url"
                  :href="log.device.maps_url"
                  target="_blank"
                  class="btn btn-ghost btn-sm"
                >Buka Maps</a>
                <span v-else class="text-muted">Tidak ada lokasi</span>
              </td>
            </tr>
            <tr v-if="displayedLogs.length === 0">
              <td colspan="6" class="empty-row">
                Tidak ada log gangguan terdeteksi.
                <template v-if="searchText"> Pencarian: "{{ searchText }}".</template>
                <template v-else> Pastikan device dan rule sudah dikonfigurasi.</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  logs: { type: [Array, Object], default: () => [] },
  total: { type: Number, default: 0 },
  search: { type: String, default: '' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  isDefaultFilter: { type: Boolean, default: false },
});

const page = usePage();

const searchText = ref(props.search);
const dateFrom = ref(props.dateFrom);
const dateTo = ref(props.dateTo);
const total = ref(props.total);
const isDefaultFilter = ref(props.isDefaultFilter);

const isRefreshing = ref(false);
const lastUpdated = ref('-');

// Computed untuk handle data structure (array atau object dengan data property)
const displayedLogs = computed(() => {
  if (Array.isArray(page.props.logs)) {
    return page.props.logs;
  }
  return page.props.logs?.data ?? [];
});

function formatTime() {
  return new Date().toLocaleTimeString('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
}

function truncate(str, n) {
  return str.length > n ? str.slice(0, n) + '...' : str;
}

// Auto-refresh logs via AJAX (update data tanpa reload page)
async function refreshLogs() {
  isRefreshing.value = true;
  const params = new URLSearchParams();
  if (searchText.value) params.set('search', searchText.value);
  if (dateFrom.value) params.set('date_from', dateFrom.value);
  if (dateTo.value) params.set('date_to', dateTo.value);

  try {
    const res = await fetch(`/api/issue-logs?${params.toString()}`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    
    // Update page props dengan data baru
    page.props.logs = data.data;
    total.value = data.total;
    isDefaultFilter.value = data.isDefaultFilter;
    lastUpdated.value = formatTime();
  } catch (e) {
    console.error('Gagal fetch issue logs:', e);
    lastUpdated.value = 'Error: ' + e.message;
  } finally {
    isRefreshing.value = false;
  }
}

// Filter actions dengan Inertia router (full page reload dengan filter params)
function doSearch() {
  router.get('/issue-logs', { search: searchText.value, date_from: dateFrom.value, date_to: dateTo.value });
}
function clearSearch() {
  searchText.value = '';
  router.get('/issue-logs', { date_from: dateFrom.value, date_to: dateTo.value });
}
function applyDateFilter() {
  router.get('/issue-logs', { search: searchText.value, date_from: dateFrom.value, date_to: dateTo.value });
}
function clearDateFilter() {
  dateFrom.value = '';
  dateTo.value = '';
  router.get('/issue-logs', { search: searchText.value });
}

// Auto-refresh setup: refresh setiap 60 detik
let intervalId = null;
onMounted(() => {
  lastUpdated.value = formatTime();
  intervalId = setInterval(refreshLogs, 60000);
});
onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
});

lastUpdated.value = formatTime();
</script>

<style scoped>
/* Layout override: IssueLogs pakai max-width 1200px */
.page-container {
  max-width: 1200px;
}

/* Auto-refresh bar */
.auto-refresh-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  flex-wrap: wrap;
}
.separator {
  color: #bdc3c7;
}
.refresh-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-success);
  display: inline-block;
  flex-shrink: 0;
}
.refresh-dot.updating {
  background-color: var(--color-warning);
  animation: pulse 1s infinite;
}
@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.4; }
  100% { opacity: 1; }
}

/* Filter bar dengan date filter kiri dan search kanan */
.filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.filter-left {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.filter-right {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.form-input {
  padding: 8px 10px;
}

/* Scrollable table container dengan fixed height */
.table-scroll-container {
  max-height: 600px;
  overflow-y: auto;
  overflow-x: auto;
  position: relative;
}

/* Sticky table header */
.table-scroll-container table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.table-scroll-container thead {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--color-bg);
}

.table-scroll-container thead th {
  background: var(--color-bg);
  border-bottom: 2px solid var(--color-border);
  box-shadow: 0 1px 0 var(--color-border);
}

/* Smooth scroll behavior */
.table-scroll-container {
  scroll-behavior: smooth;
}

/* Custom scrollbar styling */
.table-scroll-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.table-scroll-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}
.table-scroll-container::-webkit-scrollbar-thumb {
  background: #bdc3c7;
  border-radius: 4px;
}
.table-scroll-container::-webkit-scrollbar-thumb:hover {
  background: #95a5a6;
}

/* Badge khusus IssueLogs (warna abu-abu, berbeda dari global) */
.badge-code {
  background: var(--color-border);
  color: var(--color-text);
  font-weight: var(--font-weight-medium);
}

/* Metric display dengan visual bar */
.metric {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-weight: var(--font-weight-semibold);
  font-size: var(--font-size-sm);
  padding: 6px 10px;
  border-radius: var(--radius-sm);
  border-left: 3px solid transparent;
}
.metric-good {
  color: var(--color-success);
  background: var(--color-success-bg);
  border-left-color: var(--color-success);
}
.metric-bad {
  color: var(--color-danger);
  background: var(--color-danger-bg);
  border-left-color: var(--color-danger);
}

/* Zebra striping tabel issue logs */
tbody tr:nth-child(even) {
  background: rgba(248, 251, 252, 0.5);
}
tbody tr:nth-child(even):hover {
  background: #f8fbff;
}
</style>