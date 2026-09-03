<template>
  <!-- Devices/Edit.vue — halaman Edit Target Device (inertia).
  Migrasi dari resources/views/devices/edit.blade.php.
  Props: device (model). Submit lewat useForm -> PUT /devices/{id}.
  "Connect" pakai native fetch -> /devices/ping.
  Tanpa emoticon (AgentGuide). -->
  <Head title="Edit Data Target Device" />
  <div class="page-container">
    <div class="page-header">
      <h2>Edit Data Target Device</h2>
      <Link href="/devices" class="btn btn-secondary">Kembali</Link>
    </div>

    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <div class="card">
      <form @submit.prevent="submit" class="form">
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
          <small class="form-hint text-muted">Jika IP diubah, lakukan Connect terlebih dahulu sebelum menyimpan.</small>
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
          <button type="submit" class="btn btn-primary" :disabled="!canSubmit">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  device: { type: Object, default: () => ({}) },
});

const page = usePage();

const form = useForm({
  customer_name: props.device.customer_name ?? '',
  ip_address: props.device.ip_address ?? '',
  device_model: props.device.device_model ?? '',
  installation_date: props.device.installation_date ?? '',
  maps_url: props.device.maps_url ?? '',
});

const isConnecting = ref(false);
const connectStatusText = ref('');
const connectStatusColor = ref('');
const originalIp = props.device.ip_address ?? '';
const verifiedIp = ref(originalIp);

const canSubmit = computed(() => {
  const ipOk = verifiedIp.value === form.ip_address || form.ip_address === originalIp;
  return ipOk && form.customer_name && form.installation_date;
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
  if (form.ip_address !== originalIp && verifiedIp.value !== form.ip_address) {
    connectStatusText.value = 'Verifikasi IP (Connect) terlebih dahulu.';
    connectStatusColor.value = '#e74c3c';
    return;
  }
  form.put(`/devices/${props.device.id}`);
}
</script>

<style scoped>
/* Layout override: halaman edit device pakai max-width 760px */
.page-container {
  max-width: 760px;
}
.form-row {
  display: flex;
  gap: 12px;
  align-items: flex-end;
}
.form-row input {
  flex: 1;
}
</style>