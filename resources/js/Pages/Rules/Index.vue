<template>
  <Head title="Rule Forward Chaining" />
  <div class="page-container">
    <div class="page-header">
      <h2>Rule Forward Chaining</h2>
    </div>

    <div v-if="page.props.flash?.success" class="alert alert-success">{{ page.props.flash.success }}</div>
    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <!-- Form Tambah Rule (inline toggle) -->
    <div class="card" v-show="isFormOpen">
      <h3 class="form-title">Tambah Rule Baru</h3>
      <form @submit.prevent="submitCreate" class="form">
        <div class="form-group">
          <label>Kode Rule</label>
          <input type="text" value="Auto Generate (R01, R02, ...)" readonly />
        </div>

        <div class="form-group">
          <label>Nama Rule <span class="required">*</span></label>
          <input type="text" v-model="createForm.name" required placeholder="Contoh: Gangguan Bandwidth Tinggi" />
          <small v-if="createForm.errors.name" class="form-error">{{ createForm.errors.name }}</small>
        </div>

        <div class="form-group">
          <label>Target Device <span class="required">*</span></label>
          <select v-model="createForm.target_type" required>
            <option value="all">All Devices</option>
            <option value="specific">Specific Device</option>
          </select>
        </div>

        <div class="form-group" v-show="createForm.target_type === 'specific'">
          <label>Pilih Device <span class="required">*</span></label>
          <select v-model.number="createForm.target_device_id" :required="createForm.target_type === 'specific'">
            <option value="">-- Pilih Device --</option>
            <option v-for="d in devices" :key="d.id" :value="d.id">
              {{ d.ip_address }} - {{ d.customer_name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label>Kondisi Rule (IF Condition) <span class="required">*</span></label>
          <p class="form-hint text-muted">Minimal 1 kondisi. Jika lebih dari 1, kondisi akan dihubungkan dengan AND.</p>

          <div class="conditions-wrapper">
            <div
              v-for="(c, i) in createForm.conditions"
              :key="i"
              class="condition-card"
            >
              <div class="condition-header">
                <span class="condition-label">Kondisi {{ i + 1 }}</span>
                <button
                  v-if="createForm.conditions.length > 1"
                  type="button"
                  class="btn btn-ghost btn-sm"
                  @click="removeCondition(i)"
                >Hapus</button>
              </div>
              <div class="condition-fields">
                <div class="field-group">
                  <label>Parameter</label>
                  <select v-model="c.parameter" required>
                    <option value="bandwidth">Bandwidth</option>
                    <option value="latency">Latency</option>
                    <option value="packet_loss">Packet Loss</option>
                  </select>
                </div>
                <div class="field-group">
                  <label>Operator</label>
                  <select v-model="c.operator" required>
                    <option value=">">></option>
                    <option value="<"><</option>
                    <option value=">=">>=</option>
                    <option value="<="><=</option>
                    <option value="=">=</option>
                  </select>
                </div>
                <div class="field-group">
                  <label>Nilai <span class="unit-badge">{{ unitFor(c.parameter) }}</span></label>
                  <input type="number" v-model="c.value" step="0.01" min="0" required placeholder="0" />
                </div>
              </div>
            </div>
          </div>

          <button type="button" class="btn btn-secondary" @click="addCondition">+ Tambah Kondisi</button>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-secondary" @click="closeForm">Kembali</button>
          <button type="submit" class="btn btn-primary">Simpan Rule</button>
        </div>
      </form>
    </div>

    <!-- Table Daftar Rule -->
    <div class="card" v-show="!isFormOpen">
      <div class="card-header">
        <h3>Daftar Rule</h3>
      </div>
      <div class="table-toolbar">
        <button type="button" class="btn btn-primary" @click="openForm">+ Tambah Rule</button>
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
                <span v-if="rule.target_type === 'all'" class="badge badge-info">All Devices</span>
                <span v-else-if="rule.target_device_id && rule.target_device" class="badge">
                  {{ rule.target_device.ip_address }}
                </span>
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
  devices: { type: Array, default: () => [] },
  search: { type: String, default: '' },
});

const page = usePage();
const searchText = ref(props.search);
const isFormOpen = ref(false);

// Form untuk create rule baru
const createForm = useForm({
  name: '',
  target_type: 'all',
  target_device_id: null,
  conditions: [{ parameter: 'bandwidth', operator: '>', value: '' }],
});

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

// Toggle active/inactive rule via AJAX
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

// Delete rule
function remove(rule) {
  if (!confirm('Yakin ingin menghapus rule ini? Semua kondisi yang terkait juga akan terhapus.')) return;
  useForm({}).delete(`/rules/${rule.id}`);
}

// Search functionality
function doSearch() {
  router.get('/rules', { search: searchText.value });
}
function clearSearch() {
  searchText.value = '';
  router.get('/rules');
}

// Form toggle
function openForm() {
  isFormOpen.value = true;
}
function closeForm() {
  isFormOpen.value = false;
  createForm.reset();
  createForm.conditions = [{ parameter: 'bandwidth', operator: '>', value: '' }];
}

// Condition helpers
function unitFor(parameter) {
  return parameter === 'bandwidth' ? 'Mbps' : parameter === 'latency' ? 'ms' : '%';
}
function addCondition() {
  createForm.conditions.push({ parameter: 'bandwidth', operator: '>', value: '' });
}
function removeCondition(index) {
  createForm.conditions.splice(index, 1);
}

// Submit create form
function submitCreate() {
  createForm.post('/rules', {
    onSuccess: () => {
      closeForm();
    },
  });
}
</script>

<style scoped>
/* Layout override: Rules halaman ini pakai max-width 1100px */
.page-container {
  max-width: 1100px;
}

/* Constrain form and table cards to same width */
.card {
  max-width: 100%;
  width: 100%;
}

/* Form title styling (match Devices module, no border) */
.form-title {
  margin: 0 0 20px;
  color: var(--color-text);
  font-size: 18px;
  font-weight: var(--font-weight-semibold);
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

/* Form create rule inline styling */
.condition-card {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 16px;
  margin-bottom: 16px;
}
.condition-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border);
}
.condition-label {
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  font-size: var(--font-size-sm);
}
.condition-fields {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px;
}
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field-group label {
  margin-bottom: 4px;
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
}
.unit-badge {
  background: var(--color-border);
  color: var(--color-text);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  margin-left: 8px;
}
.required {
  color: var(--color-danger);
}
</style>