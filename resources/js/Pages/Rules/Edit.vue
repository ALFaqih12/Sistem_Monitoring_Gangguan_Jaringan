<template>
  <!-- Rules/Edit.vue — form edit Rule (inertia).
  Migrasi dari resources/views/rules/edit.blade.php.
  Props: rule (dengan conditions), devices. Submit lewat useForm.put('/rules/{id}').
  Tanpa emoticon. -->
  <Head :title="`Edit Rule: ${rule.name}`" />
  <div class="page-container">
    <div class="page-header">
      <h2>Edit Rule: {{ rule.name }}</h2>
      <Link href="/rules" class="btn btn-secondary">Kembali</Link>
    </div>

    <div v-if="Object.keys(page.props.errors || {}).length" class="alert alert-error">
      <div v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</div>
    </div>

    <div class="card">
      <form @submit.prevent="submit" class="form">
        <div class="form-group">
          <label>Kode Rule</label>
          <input type="text" :value="rule.code" readonly />
        </div>

        <div class="form-group">
          <label>Nama Rule <span class="required">*</span></label>
          <input type="text" v-model="form.name" required placeholder="Contoh: Gangguan Bandwidth Tinggi" />
          <small v-if="form.errors.name" class="form-error">{{ form.errors.name }}</small>
        </div>

        <div class="form-group">
          <label>Target Device <span class="required">*</span></label>
          <select v-model="form.target_type" required>
            <option value="all">All Devices</option>
            <option value="specific">Specific Device</option>
          </select>
        </div>

        <div class="form-group" v-show="form.target_type === 'specific'">
          <label>Pilih Device <span class="required">*</span></label>
          <!-- Conditional required: hanya wajib jika target_type === 'specific'.
               Jika target_type === 'all', field ini tidak perlu diisi dan validasi browser di-skip.
               Bug fix #1: form tidak bisa submit saat pilih "All Devices" karena HTML5 validation
               masih aktif pada field yang disembunyikan dengan v-show.
               
               .number modifier: auto-cast value ke integer untuk menghindari type mismatch.
               Tanpa modifier ini, HTML select akan mengirim string "1" bukan number 1.
               Bug fix #2: memastikan target_device_id selalu bertype integer, mencegah
               masalah "Device telah dihapus" di halaman list rule. -->
          <select v-model.number="form.target_device_id" :required="form.target_type === 'specific'">
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
              v-for="(c, i) in form.conditions"
              :key="i"
              class="condition-card"
            >
              <div class="condition-header">
                <span class="condition-label">Kondisi {{ i + 1 }}</span>
                <button
                  v-if="form.conditions.length > 1"
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
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  rule: { type: Object, default: () => ({}) },
  devices: { type: Array, default: () => [] },
});

const page = usePage();

const form = useForm({
  name: props.rule.name ?? '',
  target_type: props.rule.target_type ?? 'all',
  target_device_id: props.rule.target_device_id ?? null,
  conditions: (props.rule.conditions ?? []).map((c) => ({
    parameter: c.parameter,
    operator: c.operator,
    value: c.value,
  })),
});

function unitFor(parameter) {
  return parameter === 'bandwidth' ? 'Mbps' : parameter === 'latency' ? 'ms' : '%';
}

function addCondition() {
  form.conditions.push({ parameter: 'bandwidth', operator: '>', value: '' });
}
function removeCondition(index) {
  form.conditions.splice(index, 1);
}

function submit() {
  form.put(`/rules/${props.rule.id}`);
}
</script>

<style scoped>
/* Layout override: form rule edit pakai max-width 860px */
.page-container {
  max-width: 860px;
}

/* Condition card styling khusus form rule */
.condition-card {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 20px;
  margin-bottom: 16px;
}
.condition-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
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
  gap: 16px;
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
</style>