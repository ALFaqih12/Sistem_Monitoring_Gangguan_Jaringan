<template>
  <Head title="Rule Forward Chaining" />
  <div class="page-container">
    <div class="page-header">
      <h2>Rule Forward Chaining</h2>
      <Link href="/rules/create" class="btn btn-primary">+ Tambah Rule</Link>
    </div>

    <div v-if="page.props.flash?.success" class="alert alert-success">{{ page.props.flash.success }}</div>
    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Daftar Rule</h3>
        <div class="table-toolbar">
          <div class="search-form">
            <input
              v-model="searchText"
              class="form-input"
              placeholder="Cari Code atau Nama Rule..."
            />
            <button type="button" class="btn btn-primary btn-sm" @click="doSearch">Cari</button>
            <button v-if="searchText" type="button" class="btn btn-ghost btn-sm" @click="clearSearch">Reset</button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width: 90px;">Code</th>
              <th>Nama Rule</th>
              <th style="width: 160px;">Target Device</th>
              <th>Kondisi</th>
              <th style="width: 100px;">Status</th>
              <th style="width: 140px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rule in (rules?.data ?? [])" :key="rule.id">
              <td><span class="badge badge-code">{{ rule.code }}</span></td>
              <td class="cell-strong">{{ rule.name }}</td>
              <td>
                <!-- Jika target_type adalah 'all', tampilkan badge "All Devices" -->
                <span v-if="rule.target_type === 'all'" class="badge badge-info">All Devices</span>
                
                <!-- Jika specific device, cek apakah target_device_id ada DAN relasi target_device ter-load.
                     Bug fix: defensive check untuk memastikan device benar-benar tersedia sebelum display.
                     Kondisi double-check ini mencegah false positive "Device telah dihapus" ketika
                     data tersimpan dengan benar tetapi relasi tidak ter-load sempurna.
                     PENTING: Laravel mengirim relasi sebagai snake_case (target_device), bukan camelCase (targetDevice). -->
                <span v-else-if="rule.target_device_id && rule.target_device" class="badge">
                  {{ rule.target_device.ip_address }}
                </span>
                
                <!-- Fallback: jika target_device_id tidak ada atau device benar-benar sudah dihapus dari database -->
                <span v-else class="badge badge-danger">Device telah dihapus</span>
              </td>
              <td>
                <div class="conditions-list">
                  <div v-for="(cond, i) in rule.conditions" :key="cond.id ?? i">
                    <span v-if="i > 0" class="condition-and">AND</span>
                    {{ cond.parameter_label }} {{ cond.operator }} {{ cond.value }} {{ cond.unit }}
                  </div>
                </div>
              </td>
              <td>
                <label class="toggle-switch">
                  <input type="checkbox" :checked="rule.is_active" @change="onToggle(rule, $event)" />
                  <span class="toggle-slider"></span>
                </label>
              </td>
              <td>
                <div class="action-buttons">
                  <Link :href="`/rules/${rule.id}/edit`" class="btn btn-ghost btn-sm">Edit</Link>
                  <form @submit.prevent="remove(rule)">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
            <tr v-if="(rules?.data?.length ?? 0) === 0">
              <td colspan="6" class="empty-row">
                <template v-if="searchText">Data tidak ditemukan untuk pencarian "{{ searchText }}".</template>
                <template v-else>Belum ada rule. Klik "Tambah Rule" untuk memulai.</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="rules?.meta?.last_page > 1" class="pagination-wrapper">
        <div class="pagination-info">Halaman {{ rules?.meta?.current_page }} dari {{ rules?.meta?.last_page }} — Total {{ rules?.meta?.total }} rule</div>
        <ul class="pagination">
          <li v-for="link in (rules?.links ?? [])" :key="link.label">
            <Link v-if="link.url" :href="link.url" :class="{ active: link.active }">{{ link.label }}</Link>
            <span v-else :class="{ active: link.active }" class="pagination-disabled">{{ link.label }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  rules: { type: Object, default: () => ({}) },
  search: { type: String, default: '' },
});

const page = usePage();
const searchText = ref(props.search);

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

async function onToggle(rule, e) {
  const desired = e.target.checked;
  const prev = rule.is_active;
  rule.is_active = desired;
  try {
    const res = await fetch(`/rules/${rule.id}/toggle`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Gagal mengubah status rule.');
    rule.is_active = data.is_active;
  } catch (err) {
    rule.is_active = prev;
    e.target.checked = prev;
    alert('Error: ' + err.message);
  }
}

function remove(rule) {
  if (!confirm('Yakin ingin menghapus rule ini? Semua kondisi yang terkait juga akan terhapus.')) return;
  useForm({}).delete(`/rules/${rule.id}`);
}

function doSearch() {
  router.get('/rules', { search: searchText.value });
}
function clearSearch() {
  searchText.value = '';
  router.get('/rules');
}
</script>

<style scoped>
/* Layout override: Rules halaman ini pakai max-width 1100px */
.page-container {
  max-width: 1100px;
}

/* Badge khusus di tabel rule */
.badge-code {
  background: var(--color-primary);
  color: #ffffff;
  font-weight: var(--font-weight-bold);
  font-family: 'SF Mono', Consolas, monospace;
}
.conditions-list {
  font-size: var(--font-size-sm);
  color: #555;
  line-height: 1.8;
}
.conditions-list div {
  margin-bottom: 4px;
}
.condition-and {
  color: var(--color-primary);
  font-weight: var(--font-weight-bold);
  margin: 0 8px;
}

/* Zebra striping tabel rule */
tbody tr:nth-child(even) {
  background: rgba(248, 251, 252, 0.5);
}
tbody tr:nth-child(even):hover {
  background: #f8fbff;
}

/* Toggle switch khusus Rules (lebar sedikit berbeda) */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 22px;
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
  background-color: var(--color-text-muted);
  border-radius: 22px;
  transition: var(--transition);
}
.toggle-slider::before {
  content: "";
  position: absolute;
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: #ffffff;
  border-radius: 50%;
  transition: var(--transition);
}
.toggle-switch input:checked + .toggle-slider {
  background-color: var(--color-success);
}
.toggle-switch input:checked + .toggle-slider::before {
  transform: translateX(26px);
}
.toggle-slider:hover {
  opacity: 0.8;
}
</style>