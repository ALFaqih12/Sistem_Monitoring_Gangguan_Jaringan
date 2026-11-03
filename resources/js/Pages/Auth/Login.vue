<script setup>
import { Head, useForm } from '@inertiajs/vue3';

/**
 * Login Page untuk admin authentication.
 * Single user: SiwaNetwork / Admin123
 */

// Form state dengan Inertia form helper
const form = useForm({
  username: '',
  password: '',
});

// Submit login form
function submit() {
  form.post('/login', {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head title="Login" />
  
  <div class="auth-container">
    <div class="auth-card">
      <!-- Header -->
      <div class="auth-header">
        <h1>SIWA Network</h1>
        <p class="subtitle">Monitoring Gangguan Jaringan</p>
      </div>
      
      <!-- Error message -->
      <div v-if="form.errors.username" class="alert alert-error">
        {{ form.errors.username }}
      </div>
      
      <!-- Login Form -->
      <form @submit.prevent="submit" class="auth-form">
        <!-- Username with icon -->
        <div class="form-group">
          <label>Username</label>
          <div class="input-wrapper">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <input 
              type="text" 
              v-model="form.username" 
              required 
              placeholder="Masukkan username"
              :disabled="form.processing"
            />
          </div>
        </div>
        
        <!-- Password with icon -->
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrapper">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input 
              type="password" 
              v-model="form.password" 
              required 
              placeholder="Masukkan password"
              :disabled="form.processing"
            />
          </div>
        </div>
        
        <button 
          type="submit" 
          class="btn btn-primary btn-full" 
          :disabled="form.processing"
        >
          <span v-if="form.processing" class="btn-loader"></span>
          <span>{{ form.processing ? 'Memproses' : 'Login' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* Background - match sidebar gradient */
.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
  padding: 24px;
}

/* Card - larger, stronger shadow */
.auth-card {
  width: 100%;
  max-width: 440px;
  background: var(--color-card);
  border-radius: var(--radius);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.24);
  border: none;
  padding: 48px 40px;
}

/* Header - larger title */
.auth-header {
  text-align: center;
  margin-bottom: 36px;
}

.auth-header h1 {
  margin: 0 0 10px 0;
  font-size: 32px;
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  letter-spacing: -0.5px;
}

.subtitle {
  margin: 0;
  font-size: var(--font-size-md);
  color: var(--color-text-secondary);
  font-weight: var(--font-weight-regular);
}

/* Form */
.auth-form {
  margin-top: 28px;
}

/* Form group spacing */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: var(--font-weight-semibold);
  font-size: var(--font-size-sm);
  color: var(--color-text);
}

/* Input wrapper untuk icon positioning */
.input-wrapper {
  position: relative;
}

/* Icon prefix */
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: var(--color-text-secondary);
  pointer-events: none;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: color 0.2s ease;
}

/* Input fields - consistency untuk text & password */
.input-wrapper input[type="text"],
.input-wrapper input[type="password"] {
  width: 100%;
  height: 48px;
  padding: 12px 16px 12px 44px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-md);
  color: var(--color-text);
  background: var(--color-card);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  font-family: var(--font-family);
}

.input-wrapper input::placeholder {
  color: var(--color-text-muted);
}

/* Focus state - strong visual feedback */
.input-wrapper input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.input-wrapper input:focus ~ .input-icon {
  color: var(--color-primary);
}

/* Disabled state */
.input-wrapper input:disabled {
  background: #f8f9fa;
  cursor: not-allowed;
  opacity: 0.6;
}

/* Button - gradient, larger */
.btn-full {
  width: 100%;
  height: 48px;
  padding: 0 24px;
  font-size: 15px;
  font-weight: var(--font-weight-semibold);
  margin-top: 12px;
  background: linear-gradient(90deg, var(--color-primary) 0%, #5dade2 100%);
  border: none;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-full:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-full:active:not(:disabled) {
  transform: translateY(0);
}

.btn-full:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* Loading spinner - clean */
.btn-loader {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Alert spacing */
.alert {
  margin-bottom: 24px;
}

.alert-error {
  background: var(--color-danger-bg);
  color: var(--color-danger);
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(231, 76, 60, 0.2);
  font-size: var(--font-size-sm);
}
</style>
