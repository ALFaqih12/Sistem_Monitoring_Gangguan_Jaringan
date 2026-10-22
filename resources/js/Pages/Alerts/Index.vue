<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

// Props dari controller
const props = defineProps({
    config: Object,
    logs: Object,
    search: String,
    status: String,
    dateFrom: String,
    dateTo: String,
    todayStats: Object,
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

// State untuk modal detail
const showDetailModal = ref(false);
const selectedLog = ref(null);

// Computed: apakah sudah ada konfigurasi
const hasConfig = computed(() => props.config !== null);

// Computed: status badge class
const getStatusBadge = (status) => {
    if (status === 'sent') return 'badge badge-success';
    if (status === 'failed') return 'badge badge-danger';
    return 'badge badge-warning';
};

// Computed: status text
const getStatusText = (status) => {
    if (status === 'sent') return 'Terkirim';
    if (status === 'failed') return 'Gagal';
    return 'Pending';
};

// Submit konfigurasi
const submitConfig = () => {
    configForm.post('/alerts', {
        preserveScroll: true,
        onSuccess: () => {
            // Success message sudah dari flash session
        },
    });
};

// Toggle alert system ON/OFF
const toggleAlert = () => {
    if (!hasConfig.value) {
        alert('Silakan simpan konfigurasi terlebih dahulu.');
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

// Resend alert yang failed
const resendAlert = (logId) => {
    if (!confirm('Kirim ulang alert ini?')) return;

    router.post(`/alerts/${logId}/resend`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['logs'] });
        },
    });
};

// Show detail modal
const showDetail = (log) => {
    selectedLog.value = log;
    showDetailModal.value = true;
};

// Filter form
const filterForm = useForm({
    search: props.search || '',
    status: props.status || '',
    date_from: props.dateFrom || '',
    date_to: props.dateTo || '',
});

const applyFilter = () => {
    filterForm.get('/alerts', {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilter = () => {
    filterForm.reset();
    router.get('/alerts');
};

// Export to PDF
// Build query string dengan filter aktif untuk dikirim ke backend
const exportPdf = () => {
    const params = new URLSearchParams();
    
    if (filterForm.search) {
        params.append('search', filterForm.search);
    }
    if (filterForm.status) {
        params.append('status', filterForm.status);
    }
    if (filterForm.date_from) {
        params.append('date_from', filterForm.date_from);
    }
    if (filterForm.date_to) {
        params.append('date_to', filterForm.date_to);
    }
    
    // Redirect ke route export PDF dengan query string
    window.location.href = `/alerts/export-pdf?${params.toString()}`;
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
                                :class="isAlertActive ? 'btn-secondary' : 'btn-primary'"
                            >
                                {{ isAlertActive ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                        <p class="form-hint">
                            Toggle ini untuk mengaktifkan/menonaktifkan pengiriman alert ke Telegram.
                        </p>
                    </div>

                    <!-- Bot Token -->
                    <div class="form-group">
                        <label for="bot_token">Bot Token <span style="color: var(--color-danger);">*</span></label>
                        <div style="display: flex; gap: 8px;">
                            <input
                                id="bot_token"
                                v-model="configForm.bot_token"
                                :type="showToken ? 'text' : 'password'"
                                class="form-input"
                                placeholder="1234567890:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw"
                                required
                            />
                            <button 
                                type="button" 
                                @click="showToken = !showToken"
                                class="btn btn-secondary"
                                style="padding: 0 16px;"
                            >
                                {{ showToken ? 'Sembunyikan' : 'Lihat' }}
                            </button>
                        </div>
                        <p class="form-hint">
                            Dapatkan dari @BotFather di Telegram. Pastikan token aman dan jangan share ke siapapun.
                        </p>
                        <p v-if="configForm.errors.bot_token" class="form-error">
                            {{ configForm.errors.bot_token }}
                        </p>
                    </div>

                    <!-- Chat ID -->
                    <div class="form-group">
                        <label for="chat_id">Chat ID <span style="color: var(--color-danger);">*</span></label>
                        <input
                            id="chat_id"
                            v-model="configForm.chat_id"
                            type="text"
                            class="form-input"
                            placeholder="-1001234567890"
                            required
                        />
                        <p class="form-hint">
                            ID grup Telegram tujuan alert. Biasanya dimulai dengan tanda minus (-) untuk grup.
                        </p>
                        <p v-if="configForm.errors.chat_id" class="form-error">
                            {{ configForm.errors.chat_id }}
                        </p>
                    </div>

                    <!-- Advanced Settings -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                        <div class="form-group">
                            <label for="deduplication_ttl">Deduplikasi TTL (detik)</label>
                            <input
                                id="deduplication_ttl"
                                v-model.number="configForm.deduplication_ttl"
                                type="number"
                                class="form-input"
                                min="60"
                                max="3600"
                            />
                            <p class="form-hint">60 - 3600 detik</p>
                        </div>

                        <div class="form-group">
                            <label for="retry_attempts">Retry Attempts</label>
                            <input
                                id="retry_attempts"
                                v-model.number="configForm.retry_attempts"
                                type="number"
                                class="form-input"
                                min="1"
                                max="10"
                            />
                            <p class="form-hint">1 - 10 kali</p>
                        </div>

                        <div class="form-group">
                            <label for="retry_delay">Retry Delay (detik)</label>
                            <input
                                id="retry_delay"
                                v-model.number="configForm.retry_delay"
                                type="number"
                                class="form-input"
                                min="1"
                                max="60"
                            />
                            <p class="form-hint">1 - 60 detik</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px;">
                        <button 
                            type="button" 
                            @click="testConnection"
                            :disabled="testingConnection || !configForm.bot_token"
                            class="btn btn-secondary"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Koneksi' }}
                        </button>

                        <button 
                            type="button" 
                            @click="sendTestAlert"
                            :disabled="testingAlert || !configForm.bot_token || !configForm.chat_id"
                            class="btn btn-secondary"
                        >
                            {{ testingAlert ? 'Sending...' : 'Kirim Test Alert' }}
                        </button>

                        <button 
                            type="submit" 
                            :disabled="configForm.processing"
                            class="btn btn-primary"
                        >
                            {{ configForm.processing ? 'Menyimpan...' : 'Simpan Konfigurasi' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- History Alert Logs -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>History Alert</h3>
                    <div v-if="todayStats" style="display: flex; gap: 16px; font-size: 13px;">
                        <span>Hari ini: <strong>{{ todayStats.total }}</strong></span>
                        <span class="badge badge-success">{{ todayStats.sent }} Sent</span>
                        <span class="badge badge-danger">{{ todayStats.failed }} Failed</span>
                    </div>
                </div>

                <!-- Filter -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
                        <div>
                            <label for="search">Search</label>
                            <input
                                id="search"
                                v-model="filterForm.search"
                                type="text"
                                class="form-input"
                                placeholder="Nama, IP, Rule..."
                                @keyup.enter="applyFilter"
                            />
                        </div>

                        <div>
                            <label for="status">Status</label>
                            <select id="status" v-model="filterForm.status" class="form-input">
                                <option value="">Semua</option>
                                <option value="sent">Terkirim</option>
                                <option value="failed">Gagal</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <div>
                            <label for="date_from">Dari Tanggal</label>
                            <input
                                id="date_from"
                                v-model="filterForm.date_from"
                                type="date"
                                class="form-input"
                            />
                        </div>

                        <div>
                            <label for="date_to">Sampai Tanggal</label>
                            <input
                                id="date_to"
                                v-model="filterForm.date_to"
                                type="date"
                                class="form-input"
                            />
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button @click="applyFilter" class="btn btn-primary">
                                Filter
                            </button>
                            <button @click="resetFilter" class="btn btn-secondary">
                                Reset
                            </button>
                            <button @click="exportPdf" class="btn btn-secondary" title="Export ke PDF">
                                Export PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Waktu</th>
                                <th>Pelanggan</th>
                                <th style="width: 120px;">IP</th>
                                <th style="width: 80px;">Rule</th>
                                <th style="width: 180px;">Metric</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                    Belum ada history alert.
                                </td>
                            </tr>
                            <tr v-for="log in logs.data" :key="log.id">
                                <td style="font-size: 12px;">{{ log.sent_at_formatted || log.detected_at_formatted }}</td>
                                <td>
                                    <div style="font-weight: 600;">{{ log.device_name }}</div>
                                    <div style="font-size: 12px; color: var(--color-text-muted);">
                                        {{ log.customer_id }}
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 12px;">{{ log.device_ip }}</td>
                                <td>
                                    <span class="badge badge-code">{{ log.rule_code }}</span>
                                </td>
                                <td style="font-size: 12px;">
                                    <div v-if="log.bandwidth_actual">BW: {{ log.bandwidth_actual }} Mbps</div>
                                    <div v-if="log.latency_actual">Lat: {{ log.latency_actual }} ms</div>
                                    <div v-if="log.packet_loss_actual">PL: {{ log.packet_loss_actual }} %</div>
                                </td>
                                <td>
                                    <span :class="getStatusBadge(log.status)">
                                        {{ getStatusText(log.status) }}
                                    </span>
                                    <div v-if="log.retry_count > 0" style="font-size: 11px; color: var(--color-text-muted); margin-top: 4px;">
                                        Retry: {{ log.retry_count }}x
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button 
                                            @click="showDetail(log)" 
                                            class="btn btn-sm btn-ghost"
                                            title="Detail"
                                        >
                                            Lihat
                                        </button>
                                        <button 
                                            v-if="log.status === 'failed'"
                                            @click="resendAlert(log.id)" 
                                            class="btn btn-sm btn-ghost"
                                            title="Kirim Ulang"
                                        >
                                            Kirim
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length > 3" class="pagination">
                    <template v-for="(link, index) in logs.links" :key="index">
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="page-link"
                            :class="{ active: link.active }"
                            v-html="link.label"
                            @click.prevent="router.get(link.url)"
                        ></a>
                        <span v-else class="page-link disabled" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal Detail -->
        <div v-if="showDetailModal" class="modal-overlay" @click.self="showDetailModal = false">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3>Detail Alert</h3>
                    <button @click="showDetailModal = false" class="btn btn-ghost">✕</button>
                </div>
                <div class="modal-body" v-if="selectedLog">
                    <div class="info-panel">
                        <h4>Informasi Device</h4>
                        <p><strong>Nama Pelanggan:</strong> {{ selectedLog.device_name }}</p>
                        <p><strong>Customer ID:</strong> {{ selectedLog.customer_id }}</p>
                        <p><strong>IP Address:</strong> {{ selectedLog.device_ip }}</p>
                        <p><strong>Device Model:</strong> {{ selectedLog.device_model || '-' }}</p>
                        <p v-if="selectedLog.maps_url">
                            <strong>Lokasi:</strong> 
                            <a :href="selectedLog.maps_url" target="_blank" style="color: var(--color-primary);">
                                Buka Maps
                            </a>
                        </p>
                    </div>

                    <div class="info-panel">
                        <h4>Rule yang Match</h4>
                        <p><strong>Code:</strong> {{ selectedLog.rule_code }}</p>
                        <p><strong>Nama:</strong> {{ selectedLog.rule_name }}</p>
                    </div>

                    <div class="info-panel">
                        <h4>Pesan yang Dikirim</h4>
                        <pre style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 12px; white-space: pre-wrap;">{{ selectedLog.message }}</pre>
                    </div>

                    <div class="info-panel">
                        <h4>Status Pengiriman</h4>
                        <p><strong>Status:</strong> <span :class="getStatusBadge(selectedLog.status)">{{ getStatusText(selectedLog.status) }}</span></p>
                        <p><strong>Waktu Deteksi:</strong> {{ selectedLog.detected_at_formatted }}</p>
                        <p v-if="selectedLog.sent_at_formatted"><strong>Waktu Terkirim:</strong> {{ selectedLog.sent_at_formatted }}</p>
                        <p v-if="selectedLog.retry_count > 0"><strong>Retry Count:</strong> {{ selectedLog.retry_count }}x</p>
                        <p v-if="selectedLog.error_message" style="color: var(--color-danger);">
                            <strong>Error:</strong> {{ selectedLog.error_message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
</template>

<style scoped>
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
    z-index: 9999;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    width: 100%;
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
}

.modal-body {
    padding: 24px;
}

.info-panel {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
}

.info-panel:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.info-panel h4 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
}

.info-panel p {
    margin: 8px 0;
    font-size: 14px;
}
</style>
