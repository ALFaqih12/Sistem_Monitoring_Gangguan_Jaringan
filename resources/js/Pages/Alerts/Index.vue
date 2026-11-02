<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

// Props dari controller
const props = defineProps({
    config: Object,
});

// Form untuk konfigurasi alert
const configForm = useForm({
    bot_token: props.config?.bot_token || '',
    chat_id: props.config?.chat_id || '',
    deduplication_ttl: props.config?.deduplication_ttl || 600,
    retry_attempts: props.config?.retry_attempts || 3,
    retry_delay: props.config?.retry_delay || 5,
});

// State lokal status alert agar UI langsung berubah tanpa menunggu reload Inertia.
const isAlertActive = ref(props.config?.is_active ?? false);

// State untuk toggle show/hide bot token
const showToken = ref(false);

// State untuk test connection
const testingConnection = ref(false);
const testingAlert = ref(false);

// Computed: apakah sudah ada konfigurasi
const hasConfig = computed(() => props.config !== null);

// Submit form konfigurasi
const submitConfig = () => {
    configForm.post('/alerts', {
        onSuccess: () => {
            alert('Konfigurasi berhasil disimpan!');
        },
    });
};

// Toggle alert status (ON/OFF)
const toggleAlert = () => {
    if (!hasConfig.value) {
        alert('Simpan konfigurasi terlebih dahulu sebelum mengaktifkan alert.');
        return;
    }

    // Baca status lokal agar klik berulang selalu memakai nilai terbaru di UI.
    const newStatus = !isAlertActive.value;

    fetch('/alerts/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ active: newStatus }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update status lokal segera setelah server mengonfirmasi perubahan.
            isAlertActive.value = data.is_active;
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        alert('Gagal mengubah status alert: ' + err.message);
    });
};

// Test koneksi Telegram
const testConnection = () => {
    if (!configForm.bot_token) {
        alert('Bot Token wajib diisi.');
        return;
    }

    testingConnection.value = true;

    fetch('/alerts/test-connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ bot_token: configForm.bot_token }),
    })
    .then(res => res.json())
    .then(data => {
        testingConnection.value = false;
        if (data.success) {
            alert(`${data.message}\n\nBot Info:\n- Name: ${data.bot_info.first_name}\n- Username: @${data.bot_info.username}`);
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        testingConnection.value = false;
        alert('Error: ' + err.message);
    });
};

// Kirim test alert
const sendTestAlert = () => {
    if (!configForm.bot_token || !configForm.chat_id) {
        alert('Bot Token dan Chat ID wajib diisi.');
        return;
    }

    testingAlert.value = true;

    fetch('/alerts/test-alert', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ 
            bot_token: configForm.bot_token,
            chat_id: configForm.chat_id,
        }),
    })
    .then(res => res.json())
    .then(data => {
        testingAlert.value = false;
        alert(data.message);
    })
    .catch(err => {
        testingAlert.value = false;
        alert('Error: ' + err.message);
    });
};
</script>

<template>
    <div class="page-container" style="max-width: 1200px;">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Manajemen Alert</h2>
            </div>

            <!-- Flash Messages -->
            <div v-if="$page.props.flash.success" class="alert alert-success">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash.error" class="alert alert-danger">
                {{ $page.props.flash.error }}
            </div>
            <div v-if="$page.props.errors.config" class="alert alert-danger">
                {{ $page.props.errors.config }}
            </div>

            <!-- Konfigurasi Telegram Bot -->
            <div class="card" style="margin-bottom: 32px;">
                <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    Konfigurasi Telegram Bot
                </h3>

                <form @submit.prevent="submitConfig">
                    <!-- Status Toggle -->
                    <div v-if="hasConfig" class="form-group">
                        <label>Status Alert</label>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span 
                                class="badge" 
                                :class="isAlertActive ? 'badge-success' : 'badge-secondary'"
                                style="font-size: 14px; padding: 6px 12px;"
                            >
                                {{ isAlertActive ? 'ON' : 'OFF' }}
                            </span>
                            <button 
                                type="button" 
                                @click="toggleAlert" 
                                class="btn btn-sm"
                                :class="isAlertActive ? 'btn-danger' : 'btn-success'"
                            >
                                {{ isAlertActive ? 'Nonaktifkan' : 'Aktifkan' }} Alert
                            </button>
                        </div>
                    </div>

                    <!-- Bot Token -->
                    <div class="form-group">
                        <label for="bot_token">Bot Token</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input
                                id="bot_token"
                                v-model="configForm.bot_token"
                                :type="showToken ? 'text' : 'password'"
                                class="form-input"
                                style="flex: 1;"
                                placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
                                required
                            />
                            <button
                                type="button"
                                @click="showToken = !showToken"
                                class="btn btn-sm btn-ghost"
                            >
                                {{ showToken ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <small style="color: var(--color-text-muted); display: block; margin-top: 4px;">
                            Dapatkan dari <a href="https://t.me/BotFather" target="_blank">@BotFather</a>
                        </small>
                        <div v-if="configForm.errors.bot_token" class="error-message">
                            {{ configForm.errors.bot_token }}
                        </div>
                    </div>

                    <!-- Chat ID -->
                    <div class="form-group">
                        <label for="chat_id">Chat ID</label>
                        <input
                            id="chat_id"
                            v-model="configForm.chat_id"
                            type="text"
                            class="form-input"
                            placeholder="-1001234567890"
                            required
                        />
                        <small style="color: var(--color-text-muted); display: block; margin-top: 4px;">
                            ID grup Telegram tempat alert dikirim. Dapatkan dari <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>
                        </small>
                        <div v-if="configForm.errors.chat_id" class="error-message">
                            {{ configForm.errors.chat_id }}
                        </div>
                    </div>

                    <!-- Advanced Settings (3 columns) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <!-- Deduplication TTL -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="deduplication_ttl">Deduplication TTL (detik)</label>
                            <input
                                id="deduplication_ttl"
                                v-model.number="configForm.deduplication_ttl"
                                type="number"
                                class="form-input"
                                min="60"
                                max="3600"
                                placeholder="600"
                            />
                            <small style="color: var(--color-text-muted); display: block; margin-top: 4px;">
                                Jeda waktu untuk mencegah alert duplikat. Default: 600 detik.
                            </small>
                            <div v-if="configForm.errors.deduplication_ttl" class="error-message">
                                {{ configForm.errors.deduplication_ttl }}
                            </div>
                        </div>

                        <!-- Retry Attempts -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="retry_attempts">Retry Attempts</label>
                            <input
                                id="retry_attempts"
                                v-model.number="configForm.retry_attempts"
                                type="number"
                                class="form-input"
                                min="1"
                                max="10"
                                placeholder="3"
                            />
                            <small style="color: var(--color-text-muted); display: block; margin-top: 4px;">
                                Jumlah percobaan ulang jika gagal. Default: 3 kali.
                            </small>
                            <div v-if="configForm.errors.retry_attempts" class="error-message">
                                {{ configForm.errors.retry_attempts }}
                            </div>
                        </div>

                        <!-- Retry Delay -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="retry_delay">Retry Delay (detik)</label>
                            <input
                                id="retry_delay"
                                v-model.number="configForm.retry_delay"
                                type="number"
                                class="form-input"
                                min="1"
                                max="60"
                                placeholder="5"
                            />
                            <small style="color: var(--color-text-muted); display: block; margin-top: 4px;">
                                Jeda waktu antara percobaan ulang. Default: 5 detik.
                            </small>
                            <div v-if="configForm.errors.retry_delay" class="error-message">
                                {{ configForm.errors.retry_delay }}
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button 
                            type="submit" 
                            class="btn btn-primary"
                            :disabled="configForm.processing"
                        >
                            {{ configForm.processing ? 'Menyimpan...' : 'Simpan Konfigurasi' }}
                        </button>
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn btn-secondary"
                            :disabled="testingConnection || !configForm.bot_token"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="button" 
                            @click="sendTestAlert" 
                            class="btn btn-secondary"
                            :disabled="testingAlert || !configForm.bot_token || !configForm.chat_id"
                        >
                            {{ testingAlert ? 'Sending...' : 'Kirim Test Alert' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>

<style scoped>
/* Styles unchanged */
</style>
