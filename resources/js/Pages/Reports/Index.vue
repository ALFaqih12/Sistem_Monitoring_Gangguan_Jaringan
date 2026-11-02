<template>
  <Head title="Report Gangguan" />
  <div class="page-container">
    <div class="page-header">
      <h2>Report Gangguan</h2>
    </div>

    <div v-if="$page.props.flash?.success" class="alert alert-success">{{ $page.props.flash.success }}</div>
    <div v-if="$page.props.flash?.error" class="alert alert-error">{{ $page.props.flash.error }}</div>

    <!-- Statistics Card -->
    <div class="stats-wrapper">
      <div class="stat-card">
        <div class="stat-value">{{ todayStats.total }}</div>
        <div class="stat-label">Total Hari Ini</div>
      </div>
      <div class="stat-card stat-success">
        <div class="stat-value">{{ todayStats.sent }}</div>
        <div class="stat-label">Terkirim</div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-value">{{ todayStats.failed }}</div>
        <div class="stat-label">Gagal</div>
      </div>
    </div>

    <div class="filter-bar">
      <div class="filter-left">
        <input v-model="searchText" type="text" class="form-input search-input" placeholder="Cari Pelanggan" />
        <input v-model="dateFrom" type="date" class="form-input" />
        <input v-model="dateTo" type="date" class="form-input" />
        <select v-model="statusFilter" class="form-input">
          <option value="">Semua Status</option>
          <option value="sent">Terkirim</option>
          <option value="failed">Gagal Terkirim</option>
          <option value="-">Tidak Dikirim</option>
        </select>
        <button type="button" class="btn btn-secondary btn-sm" @click="applyFilters">Filter</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="clearFilters">Clear</button>
      </div>
      <div class="filter-right">
        <button type="button" class="btn btn-secondary" @click="exportPdf">Export PDF</button>
        <button type="button" class="btn btn-danger" @click="clearReports">Bersihkan Report</button>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Pelanggan / IP</th>
              <th>Device</th>
              <th>Rule</th>
              <th>Metrics</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="report in (reports?.data ?? [])" :key="report.id">
              <td>{{ report.detected_at_formatted ?? '-' }}</td>
              <td>
                <div class="cell-strong">{{ report.device_name ?? report.device?.customer_name ?? '-' }}</div>
                <div class="cell-ip">{{ report.device_ip ?? report.device?.ip_address ?? '-' }}</div>
              </td>
              <td>{{ report.device_model ?? report.device?.device_model ?? '-' }}</td>
              <td>
                <div><span class="badge badge-code">{{ report.rule_code ?? report.rule?.code ?? '-' }}</span></div>
                <div><small class="text-muted">{{ report.rule_name ?? report.rule?.name ?? '-' }}</small></div>
              </td>
              <td>
                <div v-if="report.bandwidth_actual != null">BW: {{ report.bandwidth_actual }} Mbps</div>
                <div v-if="report.latency_actual != null">Lat: {{ report.latency_actual }} ms</div>
                <div v-if="report.packet_loss_actual != null">PL: {{ report.packet_loss_actual }}%</div>
              </td>
              <td>
                <span class="badge" :class="statusClass(report.status)">{{ statusLabel(report.status) }}</span>
              </td>
            </tr>
            <tr v-if="(reports?.data?.length ?? 0) === 0">
              <td colspan="6" class="empty-row">Belum ada report gangguan.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="reports?.last_page > 1" class="pagination-wrapper">
        <div class="pagination-info">Halaman {{ reports.current_page }} dari {{ reports.last_page }} — Total {{ reports.total }} report</div>
        <ul class="pagination">
          <li v-for="link in (reports?.links ?? [])" :key="link.label">
            <Link v-if="link.url" :href="link.url" :class="{ active: link.active }">{{ link.label }}</Link>
            <span v-else class="pagination-disabled">{{ link.label }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  reports: { type: Object, default: () => ({}) },
  search: { type: String, default: '' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
});

const searchText = ref(props.search);
const dateFrom = ref(props.dateFrom);
const dateTo = ref(props.dateTo);
const statusFilter = ref('');
const todayStats = ref({ total: 0, sent: 0, failed: 0 });

function applyFilters() {
  router.get('/reports', { 
    search: searchText.value, 
    date_from: dateFrom.value, 
    date_to: dateTo.value,
    status: statusFilter.value
  });
}

function clearFilters() {
  searchText.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  statusFilter.value = '';
  router.get('/reports');
}

function exportPdf() {
  const params = {};
  if (searchText.value) params.search = searchText.value;
  if (dateFrom.value) params.date_from = dateFrom.value;
  if (dateTo.value) params.date_to = dateTo.value;
  if (statusFilter.value) params.status = statusFilter.value;
  
  const qs = new URLSearchParams(params);
  window.location.href = `/reports/export-pdf?${qs.toString()}`;
}

function clearReports() {
  if (!confirm('Semua data Report akan terhapus, Apakah anda yakin mau menghapusnya ?')) return;
  router.delete('/reports/clear-all');
}

function statusLabel(status) {
  if (status === 'sent' || status === 'Terkirim') return 'Terkirim';
  if (status === 'failed' || status === 'Gagal Terkirim') return 'Gagal Terkirim';
  return '-';
}

function statusClass(status) {
  if (status === 'sent' || status === 'Terkirim') return 'badge-success';
  if (status === 'failed' || status === 'Gagal Terkirim') return 'badge-danger';
  return 'badge-secondary';
}

// Fetch today stats on mount
onMounted(async () => {
  try {
    const res = await fetch('/api/reports/stats-today');
    if (res.ok) {
      const data = await res.json();
      todayStats.value = data;
    }
  } catch (e) {
    console.error('Failed to fetch stats:', e);
  }
});
</script>

<style scoped>
.page-header { margin-bottom: 24px; }
.page-header h2 { font-size: 24px; font-weight: 600; }
.stats-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 20px; text-align: center; box-shadow: var(--shadow); }
.stat-value { font-size: 32px; font-weight: 700; color: var(--color-primary); margin-bottom: 8px; }
.stat-label { font-size: 14px; color: var(--color-text-secondary); }
.stat-success .stat-value { color: var(--color-success); }
.stat-danger .stat-value { color: var(--color-danger); }
.filter-bar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.filter-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.search-input { width: 280px; }
.filter-bar .form-input { width: 160px; }
.badge-code { background: var(--color-primary); color: #fff; border-color: var(--color-primary); font-size: 10px; padding: 2px 6px; }
.badge-success { background: var(--color-success-bg); color: var(--color-success); }
.badge-danger { background: var(--color-danger-bg); color: var(--color-danger); }
.badge-secondary { background: #f4f6f9; color: var(--color-text-muted); }
</style>
