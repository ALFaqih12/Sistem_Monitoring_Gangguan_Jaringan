<template>
  <Head title="Manajemen Target Device" />
  <div class="page-container">
    <div class="page-header">
      <h2>Manajemen Target Device</h2>
      <button v-if="!discoveryMode" class="btn btn-primary" @click="toggleForm">
        {{ isFormOpen ? 'Tutup Form' : '+ Tambah Device' }}
      </button>
    </div>

    <div v-if="page.props.flash?.success" class="alert alert-success">{{ page.props.flash.success }}</div>
    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <!-- Tab Switcher: Manual Add vs Discovery -->
    <div class="tabs">
      <button
        @click="switchToManual"
        :class="{ active: !discoveryMode }"
        class="tab-button"
      >
        Manual Add Device
      </button>
      <button
        @click="switchToDiscovery"
        :class="{ active: discoveryMode }"
        class="tab-button"
      >
        Discovery via Core Router
      </button>
    </div>

    <!-- Manual Add Mode (existing) -->
    <div v-show="!discoveryMode">
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

    <!-- Discovery Mode (new) -->
    <div v-show="discoveryMode">
      <!-- Core Router Connection Form -->
      <div class="card">
        <h3 class="form-title">Connect to MikroTik Core Router</h3>
        <form @submit.prevent="testConnection">
          <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="form-group">
              <label>IP Core Router</label>
              <input
                v-model="coreRouterForm.core_router_ip"
                type="text"
                class="form-input"
                placeholder="192.168.1.1"
                required
              />
            </div>
            <div class="form-group">
              <label>Username</label>
              <input
                v-model="coreRouterForm.username"
                type="text"
                class="form-input"
                placeholder="admin"
                required
              />
            </div>
            <div class="form-group">
              <label>Password</label>
              <input
                v-model="coreRouterForm.password"
                type="password"
                class="form-input"
                required
              />
            </div>
          </div>

          <div style="display: flex; gap: 12px; margin-top: 16px;">
            <button type="submit" class="btn btn-secondary" :disabled="isTesting">
              {{ isTesting ? 'Testing...' : 'Test Connection' }}
            </button>
            <button
              type="button"
              @click="connectAndDiscover"
              :disabled="!connectionTested || isDiscovering"
              class="btn btn-primary"
            >
              {{ isDiscovering ? 'Discovering...' : 'Connect & Discover' }}
            </button>
          </div>

          <small v-if="connectionMessage" class="form-hint" :class="{ 'text-success': connectionSuccess, 'text-danger': !connectionSuccess }">
            {{ connectionMessage }}
          </small>
        </form>
      </div>

      <!-- Discovery Results -->
      <div v-if="discoveredClients.length > 0" class="card">
        <div class="card-header" style="margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3>Client Devices Discovered ({{ filteredClients.length }})</h3>
            <div style="display: flex; align-items: center; gap: 16px;">
              <!-- Last refresh time indicator -->
              <span v-if="lastRefreshTime" class="text-muted" style="font-size: 13px;">
                Last update: {{ lastRefreshTime }}
              </span>
              
              <!-- Refresh button -->
              <button
                type="button"
                @click="refreshDiscovery"
                :disabled="isRefreshing"
                class="btn btn-secondary btn-sm"
              >
                <span v-if="isRefreshing">🔄 Refreshing...</span>
                <span v-else>🔄 Refresh</span>
              </button>
            </div>
          </div>
          
          <!-- Search input -->
          <input
            v-model="searchDiscovery"
            type="text"
            placeholder="Search by IP or Merk..."
            class="form-input"
            style="max-width: 300px;"
          />
        </div>

        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>IP Address</th>
                <th>Merk Device</th>
                <th>MAC Address</th>
                <th>Hostname</th>
                <th>Status</th>
                <th style="width: 80px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="client in filteredClients" :key="client.ip">
                <td class="cell-ip">{{ client.ip }}</td>
                <td>{{ client.model }}</td>
                <td style="font-family: monospace; font-size: 12px;">{{ client.mac }}</td>
                <td>{{ client.hostname || '-' }}</td>
                <td><span class="badge badge-info">{{ client.status }}</span></td>
                <td>
                  <button
                    @click="openAddClientModal(client)"
                    class="btn btn-primary btn-sm"
                  >
                    + Add
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="discoveredClients.length === 0 && hasDiscovered" class="card">
        <p class="text-center text-muted" style="padding: 40px 0;">
          Tidak ada client baru ditemukan. Semua client sudah terdaftar di sistem monitoring.
        </p>
      </div>
    </div>

    <!-- Modal Add Client from Discovery -->
    <div
      v-if="showAddClientModal"
      class="modal-overlay"
      @click.self="closeAddClientModal"
    >
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tambah Client Device</h3>
          <button
            @click="closeAddClientModal"
            class="btn btn-ghost"
            type="button"
          >
            ✕
          </button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="submitAddClient">
            <div class="form-group">
              <label>IP Address</label>
              <input
                v-model="addClientForm.ip_address"
                type="text"
                class="form-input"
                readonly
              />
            </div>
            <div class="form-group">
              <label>Merk Device</label>
              <input
                v-model="addClientForm.device_model"
                type="text"
                class="form-input"
                readonly
              />
            </div>
            <div class="form-group">
              <label>ID Pelanggan</label>
              <input
                value="Auto-generate"
                type="text"
                class="form-input"
                readonly
              />
            </div>
            <div class="form-group">
              <label>Nama Pelanggan</label>
              <input
                v-model="addClientForm.customer_name"
                type="text"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label>Tanggal Pemasangan</label>
              <input
                v-model="addClientForm.installation_date"
                type="date"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label>URL Maps Lokasi</label>
              <input
                v-model="addClientForm.maps_url"
                type="url"
                class="form-input"
                required
              />
            </div>

            <div
              style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;"
            >
              <button
                type="button"
                @click="closeAddClientModal"
                class="btn btn-secondary"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="isAddingClient"
                class="btn btn-primary"
              >
                {{ isAddingClient ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
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

// Manual Add Form (existing)
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

// Discovery Mode State (new)
const discoveryMode = ref(false);
const discoveredClients = ref([]);
const searchDiscovery = ref('');
const isDiscovering = ref(false);
const isTesting = ref(false);
const connectionTested = ref(false);
const connectionMessage = ref('');
const connectionSuccess = ref(false);
const hasDiscovered = ref(false);
const lastRefreshTime = ref(null);
const isRefreshing = ref(false);

// Core Router Form (new)
const coreRouterForm = ref({
  core_router_ip: '',
  username: '',
  password: '',
});

// Add Client Form (new)
const addClientForm = ref({
  ip_address: '',
  device_model: '',
  customer_name: '',
  installation_date: '',
  maps_url: '',
  core_router_ip: '', // IP Core Router untuk query bandwidth via DHCP lease
});

const showAddClientModal = ref(false);
const selectedClient = ref(null);
const isAddingClient = ref(false);

// Computed: filtered discovery list (new)
const filteredClients = computed(() => {
  if (!searchDiscovery.value) return discoveredClients.value;

  return discoveredClients.value.filter(
    (client) =>
      client.ip.includes(searchDiscovery.value) ||
      client.model
        .toLowerCase()
        .includes(searchDiscovery.value.toLowerCase()),
  );
});

// Tab Switching Functions (new)
function switchToManual() {
  discoveryMode.value = false;
}

function switchToDiscovery() {
  discoveryMode.value = true;
  isFormOpen.value = false; // Close manual form if open
}

// Discovery Functions (new)
function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

async function testConnection() {
  if (!coreRouterForm.value.core_router_ip || !coreRouterForm.value.username || !coreRouterForm.value.password) {
    connectionMessage.value = 'Semua field harus diisi!';
    connectionSuccess.value = false;
    return;
  }

  isTesting.value = true;
  connectionMessage.value = 'Testing connection...';
  connectionSuccess.value = false;
  connectionTested.value = false;

  try {
    const res = await fetch('/devices/test-core-router', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(coreRouterForm.value),
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Gagal connect ke MikroTik');
    }

    connectionMessage.value = data.message + (data.identity ? ' (Identity: ' + data.identity + ')' : '');
    connectionSuccess.value = true;
    connectionTested.value = true;
  } catch (e) {
    connectionMessage.value = 'Error: ' + e.message;
    connectionSuccess.value = false;
    connectionTested.value = false;
  } finally {
    isTesting.value = false;
  }
}

async function connectAndDiscover() {
  isDiscovering.value = true;
  connectionMessage.value = 'Discovering clients...';

  try {
    const res = await fetch('/devices/connect-core-router', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(coreRouterForm.value),
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Gagal melakukan discovery');
    }

    discoveredClients.value = data.clients || [];
    hasDiscovered.value = true;
    
    // Set last refresh time
    lastRefreshTime.value = new Date().toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
    
    connectionMessage.value = data.message || 'Discovery berhasil! Ditemukan ' + data.total + ' client devices.';
    connectionSuccess.value = true;
  } catch (e) {
    connectionMessage.value = 'Discovery Error: ' + e.message;
    connectionSuccess.value = false;
    discoveredClients.value = [];
    hasDiscovered.value = true;
  } finally {
    isDiscovering.value = false;
  }
}

// Function untuk manual refresh discovery (NEW)
// Digunakan untuk detect client baru yang ditambahkan di MikroTik setelah discovery pertama
async function refreshDiscovery() {
  // Validasi: harus ada credentials
  if (!coreRouterForm.value.core_router_ip || 
      !coreRouterForm.value.username || 
      !coreRouterForm.value.password) {
    alert('Credentials tidak lengkap. Silakan connect ulang dengan input password.');
    return;
  }

  isRefreshing.value = true;
  const previousCount = discoveredClients.value.length;

  try {
    const res = await fetch('/devices/connect-core-router', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(coreRouterForm.value),
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Gagal refresh discovery');
    }

    // Update clients list
    discoveredClients.value = data.clients || [];
    
    // Update last refresh time
    lastRefreshTime.value = new Date().toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });

    // Calculate new clients
    const newCount = discoveredClients.value.length;
    const addedCount = newCount - previousCount;

    // Show notification
    if (addedCount > 0) {
      connectionMessage.value = `Discovery refreshed! Ditemukan ${addedCount} client baru.`;
      connectionSuccess.value = true;
    } else if (addedCount < 0) {
      connectionMessage.value = `Discovery refreshed! ${Math.abs(addedCount)} client sudah ditambahkan.`;
      connectionSuccess.value = true;
    } else {
      connectionMessage.value = 'Discovery refreshed! Tidak ada perubahan.';
      connectionSuccess.value = true;
    }

  } catch (e) {
    connectionMessage.value = 'Refresh Error: ' + e.message;
    connectionSuccess.value = false;
  } finally {
    isRefreshing.value = false;
  }
}

function openAddClientModal(client) {
  selectedClient.value = client;
  addClientForm.value.ip_address = client.ip;
  addClientForm.value.device_model = client.model;
  addClientForm.value.customer_name = '';
  addClientForm.value.installation_date = '';
  addClientForm.value.maps_url = '';
  // Simpan Core Router IP dari form discovery untuk dikirim ke backend
  // Diperlukan agar backend bisa query bandwidth via Core Router DHCP lease counter
  addClientForm.value.core_router_ip = coreRouterForm.value.core_router_ip;
  showAddClientModal.value = true;
}

function closeAddClientModal() {
  showAddClientModal.value = false;
  selectedClient.value = null;
}

async function submitAddClient() {
  isAddingClient.value = true;

  try {
    const res = await fetch('/devices/add-client', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(addClientForm.value),
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Gagal menambahkan device');
    }

    // Success: close modal, remove from discovery list, refresh page
    showAddClientModal.value = false;
    discoveredClients.value = discoveredClients.value.filter(
      (c) => c.ip !== addClientForm.value.ip_address,
    );

    // Show success message and reload data
    router.reload({ only: ['devices'], onSuccess: () => {
      connectionMessage.value = 'Client device berhasil ditambahkan!';
      connectionSuccess.value = true;
    }});
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    isAddingClient.value = false;
  }
}

// Manual Add Functions (existing)
function toggleForm() {
  isFormOpen.value = !isFormOpen.value;
}

watch(() => Object.keys(page.props.errors || {}).length, (n) => {
  if (n > 0) isFormOpen.value = true;
});

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

/* Tabs Styling */
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.tab-button {
  padding: 12px 24px;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: var(--color-text-muted);
  font-size: 14px;
  font-weight: var(--font-weight-medium);
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-button:hover {
  color: var(--color-text);
  background: rgba(52, 152, 219, 0.05);
}

.tab-button.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
  font-weight: var(--font-weight-semibold);
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
  display: grid;
  gap: 12px;
  grid-template-columns: 1fr auto;
  align-items: flex-end;
}

/* Badge ID pelanggan (warna abu-abu lembut) */
.badge {
  background: #eef5fb;
  color: var(--color-text);
  border: 1px solid #d7e7f5;
}

.badge-info {
  background: #e3f2fd;
  color: #1976d2;
  border: 1px solid #bbdefb;
}

/* Button small size */
.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}

.btn-secondary {
  background: #95a5a6;
  color: white;
  border: 1px solid #95a5a6;
}

.btn-secondary:hover:not(:disabled) {
  background: #7f8c8d;
  border-color: #7f8c8d;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Zebra striping tabel device */
tbody tr:nth-child(even) {
  background: rgba(248, 251, 252, 0.5);
}
tbody tr:nth-child(even):hover {
  background: #f8fbff;
}

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

.modal-body {
  padding: 24px;
}

.text-center {
  text-align: center;
}

.text-muted {
  color: var(--color-text-muted);
}
</style>
