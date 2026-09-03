<template>
  <Head title="Log Gangguan" />
  <div class="page-container">
    <div class="page-header">
      <h2>Log Gangguan Terdeteksi</h2>
      <div class="search-form">
        <input
          v-model="searchText"
          class="form-input"
          placeholder="Cari: nama pelanggan, IP, kode rule..."
          @keyup.enter="doSearch"
        />
        <button type="button" class="btn btn-primary btn-sm" @click="doSearch">Cari</button>
        <button v-if="searchText" type="button" class="btn btn-ghost btn-sm" @click="clearSearch">Reset</button>
      </div>
    </div>

    <div class="auto-refresh-bar">
      <span class="refresh-dot" :class="{ updating: isRefreshing }" />
      <span>Auto-refresh setiap 60 detik</span>
      <span class="separator">|</span>
      <span>Terakhir update: <strong>{{ lastUpdated }}</strong></span>
      <button class="btn btn-ghost btn-sm" @click="refreshLogs">Refresh Manual</button>
    </div>

    <div class="filter-form">
      <input v-model="dateFrom" type="date" class="form-input" style="width: 160px;" />
      <input v-model="dateTo" type="date" class="form-input" style="width: 160px;" />
      <button type="button" class="btn btn-secondary btn-sm" @click="applyDateFilter">Filter</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="clearDateFilter">Clear</button>
    </div>

    <div class="card">
      <div class="table-responsive">
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
            <tr v-for="log in (logs?.data ?? [])" :key="log.id">
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
                <!-- Kolom metrics: tampilkan nilai aktual + threshold per-log.
                     Setiap log memiliki threshold dan matched_metrics dari rule spesifiknya sendiri.
                     - matched_metrics: boolean apakah parameter ini match kondisi rule
                     - thresholds: { value, operator } untuk label threshold display
                     
                     Penting: v-if harus memastikan log.thresholds?.parameter itu sendiri ada
                     (bukan null), bukan hanya cek .value !== null. Ini karena ketika
                     threshold adalah null, maka log.thresholds?.parameter?.value akan
                     menghasilkan `undefined`, dan `undefined !== null` adalah `true`,
                     yang menyebabkan v-if lolos tapi akses ke .operator crash. -->
                <td>
                  <div v-if="log.bandwidth_actual != null" :class="['metric', log.matched_metrics?.bandwidth ? 'metric-bad' : 'metric-good']">
                    BW: {{ log.bandwidth_actual }} Mbps
                    <span v-if="log.matched_metrics?.bandwidth && log.thresholds?.bandwidth"> (threshold: {{ log.thresholds.bandwidth.operator }}{{ log.thresholds.bandwidth.value }})</span>
                  </div>
                  <div v-if="log.latency_actual != null" :class="['metric', log.matched_metrics?.latency ? 'metric-bad' : 'metric-good']">
                    Lat: {{ log.latency_actual }} ms
                    <span v-if="log.matched_metrics?.latency && log.thresholds?.latency"> (threshold: {{ log.thresholds.latency.operator }}{{ log.thresholds.latency.value }})</span>
                  </div>
                  <div v-if="log.packet_loss_actual != null" :class="['metric', log.matched_metrics?.packet_loss ? 'metric-bad' : 'metric-good']">
                    PL: {{ log.packet_loss_actual }}%
                    <span v-if="log.matched_metrics?.packet_loss && log.thresholds?.packet_loss"> (threshold: {{ log.thresholds.packet_loss.operator }}{{ log.thresholds.packet_loss.value }})</span>
                  </div>
                  <span v-if="log.bandwidth_actual == null && log.latency_actual == null && log.packet_loss_actual == null" class="text-muted">-</span>
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
            <tr v-if="(logs?.data?.length ?? 0) === 0">
              <td colspan="6" class="empty-row">
                Tidak ada log gangguan terdeteksi.
                <template v-if="searchText"> Pencarian: "{{ searchText }}".</template>
                <template v-else> Pastikan device dan rule sudah dikonfigurasi.</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="logs?.meta?.last_page > 1" class="pagination-wrapper">
        <div class="pagination-info">Halaman {{ logs?.meta?.current_page }} dari {{ logs?.meta?.last_page }} — Total {{ logs?.meta?.total }} log</div>
        <ul class="pagination">
          <li v-for="link in (logs?.links ?? [])" :key="link.label">
            <Link v-if="link.url" :href="link.url" :class="{ active: link.active }">{{ link.label }}</Link>
            <span v-else :class="{ active: link.active }" class="pagination-disabled">{{ link.label }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  logs: { type: Object, default: () => ({}) },
  search: { type: String, default: '' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
});

const page = usePage();

const searchText = ref(props.search);
const dateFrom = ref(props.dateFrom);
const dateTo = ref(props.dateTo);

const isRefreshing = ref(false);
const lastUpdated = ref('-');

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
    page.props.logs = data;
    lastUpdated.value = formatTime();
  } catch (e) {
    console.error('Gagal fetch issue logs:', e);
    lastUpdated.value = 'Error: ' + e.message;
  } finally {
    isRefreshing.value = false;
  }
}

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

/* Filter form (lebar khusus untuk input tanggal) */
.filter-form {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.form-input {
  width: 160px;
  padding: 8px 10px;
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