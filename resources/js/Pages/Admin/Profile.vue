<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';

/**
 * Kelola Admin Page - Change password only.
 * Username fixed (tidak bisa diubah).
 */

// Props dari backend
const props = defineProps({
  admin: Object,
});

// Access flash messages
const page = usePage();

// Form untuk password only
const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

// Submit update password
function submit() {
  form.put('/admin/update', {
    preserveScroll: true,
    onSuccess: () => {
      // Reset form setelah success
      form.reset();
    },
  });
}
</script>

<template>
  <Head title="Kelola Admin" />
  
  <div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
      <h2>Kelola Admin</h2>
    </div>
    
    <!-- Success Message -->
    <div v-if="page.props.flash?.success" class="alert alert-success">
      {{ page.props.flash.success }}
    </div>
    
    <!-- Single Card -->
    <div class="card">
      <form @submit.prevent="submit">
        <!-- Section 1: Info Akun (readonly) -->
        <div class="form-section">
          <h3 class="section-title">Informasi Akun</h3>
          <div class="info-display">
            <span class="info-label">Username</span>
            <span class="info-value">{{ admin.username }}</span>
          </div>
        </div>
        
        <!-- Divider -->
        <div class="form-divider"></div>
        
        <!-- Section 2: Ubah Password -->
        <div class="form-section">
          <h3 class="section-title">Ubah Password</h3>
          
          <div class="form-group">
            <label>Password Lama</label>
            <input 
              type="password" 
              v-model="form.current_password" 
              required
              placeholder="Masukkan password saat ini"
              :disabled="form.processing"
            />
            <small v-if="form.errors.current_password" class="form-error">
              {{ form.errors.current_password }}
            </small>
          </div>
          
          <div class="form-group">
            <label>Password Baru</label>
            <input 
              type="password" 
              v-model="form.password" 
              required
              minlength="8"
              placeholder="Minimal 8 karakter"
              :disabled="form.processing"
            />
            <small v-if="form.errors.password" class="form-error">
              {{ form.errors.password }}
            </small>
          </div>
          
          <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <input 
              type="password" 
              v-model="form.password_confirmation" 
              required
              placeholder="Ketik ulang password baru"
              :disabled="form.processing"
            />
          </div>
        </div>
        
        <!-- Submit Button -->
        <div class="form-actions">
          <button 
            type="submit" 
            class="btn btn-primary" 
            :disabled="form.processing"
          >
            <span v-if="form.processing">Menyimpan...</span>
            <span v-else>Simpan Perubahan</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* Section title */
.section-title {
  margin: 0 0 20px 0;
  padding: 0;
  font-size: 18px;
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

/* Form section spacing */
.form-section {
  margin-bottom: 32px;
}

.form-section:last-of-type {
  margin-bottom: 0;
}

/* Info display (readonly username) */
.info-display {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: #f8f9fa;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.info-label {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-secondary);
  margin-right: 12px;
  min-width: 80px;
}

.info-value {
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-regular);
  color: var(--color-text);
  font-family: var(--font-family);
}

/* Divider between sections */
.form-divider {
  height: 1px;
  background: var(--color-border);
  margin: 32px 0;
}

/* Form group */
.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: var(--font-weight-semibold);
  font-size: var(--font-size-sm);
  color: var(--color-text);
}

/* Input fields - CONSISTENT style untuk semua password inputs */
.form-group input[type="password"] {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-md);
  color: var(--color-text);
  background: var(--color-card);
  font-family: var(--font-family);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input::placeholder {
  color: var(--color-text-muted);
}

.form-group input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group input:disabled {
  background: #f8f9fa;
  cursor: not-allowed;
  opacity: 0.6;
}

/* Error message */
.form-error {
  display: block;
  margin-top: 6px;
  color: var(--color-danger);
  font-size: var(--font-size-sm);
}

/* Form actions */
.form-actions {
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: flex-end;
}

.form-actions .btn {
  min-width: 180px;
}
</style>
