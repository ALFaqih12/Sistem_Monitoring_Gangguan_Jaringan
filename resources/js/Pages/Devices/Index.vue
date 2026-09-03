<template>
  <Head title="Manajemen Target Device" />
  <div class="page-container">
    <div class="page-header">
      <h2>Manajemen Target Device</h2>
      <button class="btn btn-primary" @click="toggleForm">
        {{ isFormOpen ? 'Tutup Form' : '+ Tambah Device' }}
      </button>
    </div>

    <div v-if="page.props.flash?.success" class="alert alert-success">{{ page.props.flash.success }}</div>
    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <div class="card" v-show="isFormOpen">
      <form @submit.prevent="submit" class="form">
        <h3 class="form-title">Tambah Device Target</h3>
        <div class="form-group">
          <label>IP Address Target</label>
          <div class="form-row">
            <input type="text" v-model="form.ip_address" required placeholder="Masukkan IP Address" />
            <button type="button" class="btn btn-warning" :disabled="isConnecting" @click="connect">
              <span v-if="isConnecting">Menghubungkan...</span>
              <span v-else>Connect</span>
            </button>
          </div>
          <small class="form-hint" :class="{ 'text-success': connectStatusColor === '#2ecc71', 'text-danger': connectStatusColor === '#e74c3c' }">{{ connectStatusText }}</small>
        </div>

        <div class="form-group">
          <label>Model Perangkat</label>
          <input type="text" v-model="form.device_model" readonly />
        </div>

        <div class="form-group">
          <label>Nama Pelanggan</label>
          <input type="text" v-model="form.customer_name" required placeholder="Nama pelanggan" />
          <small v-if="form.errors.customer_name" class="form-error">{{ form.errors.customer_name }}</small>
        </div>

        <div class="form-group">
          <label>Tanggal Pemasangan</label>
          <input type="date" v-model="form.installation_date" required />
          <small v-if="form.errors.installation_date" class="form-error">{{ form.errors.installation_date }}</small>
        </div>

        <div class="form-group">
          <label>URL Maps Lokasi</label>
          <input type="url" v-model="form.maps_url" placeholder="https://maps.google.com/..." />
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="!canSubmit">Simpan Device</button>
        </div>
      </form>
    </div>

    <div class="card" v-show="!isFormOpen">
      <div class="card-header">
        <h3>Daftar Target Device</h3>
        <div class="table-toolbar">
          <div class="search-form">
            <input
              type="text"
              v-model="searchText"
              class="form-input"
              placeholder="Cari ID, Nama, IP, Model..."
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
              <th>ID Pelanggan</th>
              <th>Nama Pelanggan</th>
              <th>IP Address</th>
              <th>Model Device</th>
              <th>Lokasi</th>
              <th style="width: 140px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="device in (devices?.data ?? [])" :key="device.id">
              <td><span class="badge">{{ device.customer_id }}</span></td>
              <td class="cell-strong">{{ device.customer_name }}</td>
              <td class="cell-ip">{{ device.ip_address }}</td>
              <td>{{ device.device_model ?? '-' }}</td>
              <td>
                <a
                  v-if="device.maps_url"
                  :href="device.maps_url"
                  target="_blank"
                  class="btn btn-ghost btn-sm"
                >Lihat Lokasi</a>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <div class="action-buttons">
                  <Link :href="`/devices/${device.id}/edit`" class="btn btn-ghost btn-sm">Edit</Link>
                  <form @submit.prevent="remove(device)">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
            <tr v-if="(devices?.data?.length ?? 0) === 0">
              <td colspan="6" class="empty-row">
                <template v-if="searchText">Data tidak ditemukan untuk pencarian "{{ searchText }}".</template>
                <template v-else>Belum ada data target device. Klik "Tambah Device" untuk memulai.</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="devices?.meta?.last_page > 1" class="pagination-wrapper">
        <div class="pagination-info">Halaman {{ devices?.meta?.current_page }} dari {{ devices?.meta?.last_page }} — Total {{ devices?.meta?.total }} data</div>
        <ul class="pagination">
          <li v-for="link in (devices?.links ?? [])" :key="link.label">
            <Link v-if="link.url" :href="link.url" :class="{ active: link.active }">{{ link.label }}</Link>
            <span v-else :class="{ active: link.active }" class="pagination-disabled">{{ link.label }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  devices: { type: Object, default: () => ({}) },
  search: { type: String, default: '' },
});

const page = usePage();

const form = useForm({
  customer_name: '',
  ip_address: '',
  device_model: '',
  installation_date: '',
  maps_url: '',
});

const isFormOpen = ref(false);
const searchText = ref(props.search);

const isConnecting = ref(false);
const connectStatusText = ref('');
const connectStatusColor = ref('');
const verifiedIp = ref(null);

const canSubmit = computed(() => {
  return verifiedIp.value === form.ip_address && form.ip_address && form.customer_name && form.installation_date;
});

function toggleForm() {
  isFormOpen.value = !isFormOpen.value;
}

watch(() => Object.keys(page.props.errors || {}).length, (n) => {
  if (n > 0) isFormOpen.value = true;
});

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

async function connect() {
  const ip = form.ip_address.trim();
  if (!ip) {
    connectStatusText.value = 'Isi IP Address!';
    connectStatusColor.value = '#e74c3c';
    return;
  }
  isConnecting.value = true;
  connectStatusText.value = 'Menghubungkan...';
  connectStatusColor.value = '#e67e22';
  try {
    const res = await fetch('/devices/ping', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ ip_address: ip }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Gagal terhubung ke perangkat.');
    form.device_model = data.device_model;
    verifiedIp.value = ip;
    connectStatusText.value = data.message;
    connectStatusColor.value = '#2ecc71';
  } catch (e) {
    verifiedIp.value = null;
    form.device_model = '';
    connectStatusText.value = 'Gagal: ' + e.message;
    connectStatusColor.value = '#e74c3c';
  } finally {
    isConnecting.value = false;
  }
}

function submit() {
  if (verifiedIp.value !== form.ip_address) {
    connectStatusText.value = 'Verifikasi IP (Connect) terlebih dahulu.';
    connectStatusColor.value = '#e74c3c';
    return;
  }
  form.post('/devices', {
    onSuccess: () => {
      form.reset();
      verifiedIp.value = null;
      form.device_model = '';
      connectStatusText.value = '';
      isFormOpen.value = false;
    },
  });
}

function remove(device) {
  if (!confirm('Yakin ingin menghapus target ini? Data IP juga akan dihapus dari antrean Prometheus.\n\nRule yang menarget device ini TIDAK akan ikut terhapus, namun target device pada rule akan menjadi tidak terikat (Device Tidak Ditemukan).')) {
    return;
  }
  useForm({}).delete(`/devices/${device.id}`);
}

function doSearch() {
  router.get('/devices', { search: searchText.value });
}
function clearSearch() {
  searchText.value = '';
  router.get('/devices');
}
</script>

<style scoped>
/* Layout override: Devices halaman ini pakai max-width 1100px */
.page-container {
  max-width: 1100px;
}

/* Form title khusus Devices */
.form-title {
  margin: 0 0 20px;
  color: var(--color-text);
  font-size: 18px;
  font-weight: var(--font-weight-semibold);
  padding-bottom: 16px;
  border-bottom: 1px solid var(--color-border);
}
.form-row {
  display: flex;
  gap: 12px;
  align-items: flex-end;
}
.form-row input {
  flex: 1;
}

/* Badge ID pelanggan (warna abu-abu lembut) */
.badge {
  background: #eef5fb;
  color: var(--color-text);
  border: 1px solid #d7e7f5;
}

/* Zebra striping tabel device */
tbody tr:nth-child(even) {
  background: rgba(248, 251, 252, 0.5);
}
tbody tr:nth-child(even):hover {
  background: #f8fbff;
}
</style>