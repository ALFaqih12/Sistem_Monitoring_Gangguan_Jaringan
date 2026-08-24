# Planning Modul Log Gangguan - Hasil Diskusi

## Tanggal Diskusi: 2026-08-09 01:16 WIB

---

## 🎯 Tujuan Modul

Menyimpan dan menampilkan history gangguan jaringan yang terdeteksi oleh Forward Chaining Engine untuk:
- Analisis gangguan
- Tracking status penanganan
- Reference untuk teknisi
- Reporting ke management

---

## 📋 Fitur yang Disepakati

### ✅ Fitur yang AKAN Dibuat

1. **Data Gangguan Dasar**
   - Waktu kejadian (detected_at)
   - Rule code & rule name
   - IP address device
   - Device model
   - Customer name & ID
   - Maps URL (lokasi device)

2. **Metrics Actual (Nilai saat gangguan)**
   - Bandwidth actual (Mbps)
   - Latency actual (ms)
   - Packet Loss actual (%)
   - Alasan: Untuk analisis severity dan troubleshooting

3. **Status Gangguan** ✅
   - Status: new / ongoing / resolved
   - Admin bisa update status manual
   - Default: 'new' saat pertama terdeteksi
   - Alasan: Tracking gangguan yang masih aktif vs sudah selesai

4. **Notes/Comment Manual** ✅
   - Field text untuk catatan admin
   - Nullable (opsional)
   - Contoh: "Sudah di-site visit, kabel diganti"
   - Alasan: Dokumentasi tindakan yang sudah dilakukan

5. **UI Features**
   - Table view dengan pagination (15 rows/page)
   - Search (by customer name, IP, rule code)
   - Filter by status (new/ongoing/resolved)
   - Filter by date range
   - Responsive design (tanpa Bootstrap/Tailwind)

### ❌ Fitur yang TIDAK Dibuat (Skip)

1. **Severity Level** ❌
   - Auto-calculate (Critical/High/Medium/Low)
   - Alasan: Tidak diperlukan untuk use case saat ini

2. **Resolved By** ❌
   - Tracking siapa yang resolve
   - Alasan: Single admin, tidak perlu tracking user

3. **Durasi Gangguan** ❌
   - Auto-calculate duration
   - Alasan: Nice to have, bisa ditambahkan nanti

4. **Alert Sent Flag** ❌
   - Boolean tracking Telegram sent
   - Alasan: Akan diimplementasi di fase Telegram integration

5. **Export CSV/Excel** ❌
   - Export data untuk reporting
   - Alasan: Future enhancement

---

## 🗄️ Database Schema

### Tabel: issue_logs

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| id | bigint UNSIGNED | NO | AUTO | Primary key |
| device_id | bigint UNSIGNED | NO | - | FK to devices.id |
| rule_id | bigint UNSIGNED | NO | - | FK to rules.id |
| bandwidth_actual | decimal(10,2) | YES | NULL | Bandwidth saat gangguan (Mbps) |
| latency_actual | decimal(10,2) | YES | NULL | Latency saat gangguan (ms) |
| packet_loss_actual | decimal(10,2) | YES | NULL | Packet loss saat gangguan (%) |
| status | enum | NO | 'new' | Status: new, ongoing, resolved |
| notes | text | YES | NULL | Catatan manual dari admin |
| detected_at | timestamp | NO | - | Waktu gangguan terdeteksi |
| created_at | timestamp | NO | CURRENT | Record created |
| updated_at | timestamp | NO | CURRENT | Record updated |

**Indexes:**
- PRIMARY KEY (id)
- INDEX (device_id)
- INDEX (rule_id)
- INDEX (status)
- INDEX (detected_at)
- FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
- FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE

---

## 🏗️ Struktur Implementasi

### 1. Migration

**File:** database/migrations/2026_08_09_xxxxxx_create_issue_logs_table.php

`php
Schema::create('issue_logs', function (Blueprint ) {
    ->id();
    ->foreignId('device_id')->constrained()->onDelete('cascade');
    ->foreignId('rule_id')->constrained()->onDelete('cascade');
    ->decimal('bandwidth_actual', 10, 2)->nullable();
    ->decimal('latency_actual', 10, 2)->nullable();
    ->decimal('packet_loss_actual', 10, 2)->nullable();
    ->enum('status', ['new', 'ongoing', 'resolved'])->default('new');
    ->text('notes')->nullable();
    ->timestamp('detected_at');
    ->timestamps();
    
    ->index('status');
    ->index('detected_at');
});
`

---

### 2. Model

**File:** pp/Models/IssueLog.php

**Features:**
- Relasi belongsTo ke Device dan Rule
- Accessor untuk format metrics dengan unit
- Accessor untuk format detected_at readable
- Cast status sebagai enum
- Cast metrics sebagai decimal

---

### 3. Controller

**File:** pp/Http/Controllers/IssueLogController.php

**Methods:**

| Method | Route | Description |
|--------|-------|-------------|
| index() | GET /logs | List logs dengan pagination, search, filter |
| show() | GET /logs/{log} | Detail log (opsional) |
| updateStatus() | POST /logs/{log}/status | Update status via AJAX |
| updateNotes() | POST /logs/{log}/notes | Update notes via AJAX |
| destroy() | DELETE /logs/{log} | Hapus log (soft delete?) |

**Features:**
- Pagination 15 rows/page
- Search: customer_name, ip_address, rule_code
- Filter: status (new/ongoing/resolved)
- Filter: date_from, date_to
- Eager loading: device, rule
- Order by: detected_at DESC (terbaru di atas)

---

### 4. Views

**File:** esources/views/logs/index.blade.php

**Layout:**
`
┌─────────────────────────────────────────────────────┐
│ Header: "Log Gangguan Jaringan"                    │
├─────────────────────────────────────────────────────┤
│ Filter Bar:                                         │
│ [Search: ____] [Status: All▼] [From: __] [To: __]  │
│ [Button: Cari] [Button: Reset]                     │
├─────────────────────────────────────────────────────┤
│ Table:                                              │
│ | Waktu | Customer | IP | Rule | Metrics | Status  │
│ | | | | | | [Actions] │
│ |-------|----------|----|----- |---------|---------|
│ | ...   | ...      |... | ...  | ...     | ...     │
├─────────────────────────────────────────────────────┤
│ Pagination: « 1 2 3 4 5 »                          │
└─────────────────────────────────────────────────────┘
`

**Table Columns:**
1. Waktu (detected_at) - Format: "09 Aug 2026, 13:45"
2. Customer (customer_name + customer_id)
3. IP Address
4. Rule (code + name)
5. Metrics (bandwidth, latency, packet_loss dengan warna)
6. Status (badge: new=kuning, ongoing=biru, resolved=hijau)
7. Actions (button: Update Status, Add Notes, Detail)

**Modal untuk Update Status:**
- Radio buttons: New / Ongoing / Resolved
- Button: Save via AJAX

**Modal untuk Add/Edit Notes:**
- Textarea untuk notes
- Button: Save via AJAX

---

### 5. Integration dengan ForwardChainingService

**File:** pp/Services/ForwardChainingService.php

**Update method evaluateAllRules():**

`php
if (['match']) {
    // Cek deduplikasi
    if (->shouldAlert(, , )) {
        // TAMBAHAN BARU: Simpan ke database
        ->saveIssueLog(, , );
        
        // Existing code
        [] = ;
        Log::info("Gangguan terdeteksi!");
        ->markAsAlerted(, , );
    }
}
`

**Method baru saveIssueLog():**

`php
private function saveIssueLog(Device , Rule , array ): void
{
    IssueLog::create([
        'device_id' => ->id,
        'rule_id' => ->id,
        'bandwidth_actual' => ['metrics']['bandwidth'],
        'latency_actual' => ['metrics']['latency'],
        'packet_loss_actual' => ['metrics']['packet_loss'],
        'status' => 'new',
        'detected_at' => now(),
    ]);
}
`

---

### 6. Routes

**File:** outes/web.php

`php
// Routes untuk Modul Log Gangguan
Route::get('/logs', [IssueLogController::class, 'index'])->name('logs.index');
Route::get('/logs/{log}', [IssueLogController::class, 'show'])->name('logs.show');
Route::post('/logs/{log}/status', [IssueLogController::class, 'updateStatus'])->name('logs.updateStatus');
Route::post('/logs/{log}/notes', [IssueLogController::class, 'updateNotes'])->name('logs.updateNotes');
Route::delete('/logs/{log}', [IssueLogController::class, 'destroy'])->name('logs.destroy');
`

---

### 7. Sidebar Menu

**File:** esources/views/layouts/app.blade.php

**Tambahan menu:**
`html
<a href="{{ route('logs.index') }}" class="{{ request()->routeIs('logs.*') ? 'active' : '' }}">
    📋 Log Gangguan
</a>
`

---

## 🎨 UI/UX Design

### Color Scheme (Konsisten dengan existing)

- Primary: #3498db (biru)
- Success: #2ecc71 (hijau)
- Warning: #f39c12 (kuning/orange)
- Danger: #e74c3c (merah)
- Text: #2c3e50 (dark gray)
- Background: #ecf0f1 (light gray)

### Status Badge Colors

`css
.status-badge.new {
    background: #fff3cd;
    color: #856404;
}

.status-badge.ongoing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.resolved {
    background: #d4edda;
    color: #155724;
}
`

### Metrics Display

`
Bandwidth: 85.5 Mbps (warna merah jika >80)
Latency: 45 ms (warna merah jika >50)
Packet Loss: 3.5% (warna merah jika >5)
`

---

## 🔄 User Flow

### 1. Viewing Logs

`
User → Menu "Log Gangguan" 
     → Table dengan list gangguan (newest first)
     → Pagination & search
`

### 2. Filtering Logs

`
User → Input search "Pelanggan A" 
     → Select status "New"
     → Select date range
     → Click "Cari"
     → Table filtered
`

### 3. Update Status

`
User → Click "Update Status" button pada row
     → Modal muncul dengan radio buttons
     → Select "Resolved"
     → Click "Save"
     → AJAX update
     → Badge berubah jadi hijau "Resolved"
`

### 4. Add Notes

`
User → Click "Notes" button pada row
     → Modal muncul dengan textarea
     → Input: "Kabel sudah diganti, normal kembali"
     → Click "Save"
     → AJAX update
     → Notes tersimpan (tooltip atau column tambahan)
`

---

## 🧪 Testing Checklist

### Functional Testing

- [ ] Migration berhasil dijalankan
- [ ] Model IssueLog relasi working
- [ ] ForwardChainingService save log otomatis saat gangguan detected
- [ ] Deduplikasi working (1 gangguan = 1 log per 10 menit)
- [ ] Table view tampil dengan data correct
- [ ] Pagination working (15 rows/page)
- [ ] Search by customer name working
- [ ] Search by IP working
- [ ] Search by rule code working
- [ ] Filter by status working
- [ ] Filter by date range working
- [ ] Update status via AJAX working
- [ ] Update notes via AJAX working
- [ ] Delete log working (jika ada)
- [ ] Sidebar menu "Log Gangguan" active state working

### UI/UX Testing

- [ ] Responsive di mobile & desktop
- [ ] Status badge color correct
- [ ] Metrics color (red jika threshold breach)
- [ ] Modal update status working
- [ ] Modal add notes working
- [ ] Loading state saat AJAX
- [ ] Success/error message tampil
- [ ] Empty state (jika belum ada log)

### Integration Testing

- [ ] Start monitoring → gangguan terdeteksi → log tersimpan
- [ ] Deduplikasi prevent spam log
- [ ] Foreign key constraint working (delete device → cascade delete logs)

---

## ⚠️ Notes & Considerations

### 1. Deduplikasi Integration

Log hanya dibuat jika shouldAlert() return 	rue.
Artinya: 1 gangguan persisten = 1 log per 10 menit (sesuai TTL dedupe).

**Timeline:**
`
T=0: Gangguan detected → Log created ✅
T=30s: Gangguan masih ada → Skip (dedupe) ❌
T=1min: Gangguan masih ada → Skip (dedupe) ❌
T=10min: Gangguan masih ada → Log created ✅ (new record)
`

### 2. Status Update Logic

**Status workflow:**
`
new → ongoing → resolved
`

Admin bisa skip "ongoing" dan langsung ke "resolved" jika gangguan cepat diatasi.

### 3. Data Retention

Pertimbangan untuk future:
- Auto-delete logs older than 3 months?
- Archive logs ke table terpisah?
- Export to CSV sebelum delete?

**For now:** Keep all logs (no auto-delete).

### 4. Performance

Dengan 10 device dan gangguan persisten 24 jam:
`
10 device × 6 logs/hour × 24 hours = 1,440 logs/hari
Per bulan: ~43,000 logs
`

**Optimasi:**
- Index pada detected_at untuk query cepat
- Pagination prevent load all data
- Eager loading prevent N+1 query

---

## 📊 Estimasi Waktu

| Task | Estimasi |
|------|----------|
| Database migration | 15 menit |
| Model IssueLog | 15 menit |
| Controller (index, show, update) | 30 menit |
| View (table, modal, filter) | 45 menit |
| Integration ForwardChainingService | 15 menit |
| Routes & sidebar menu | 10 menit |
| Testing & bug fixes | 20 menit |
| **TOTAL** | **2 jam 30 menit** |

---

## 🚀 Implementation Order

1. ✅ Migration (create table)
2. ✅ Model (dengan relasi & accessor)
3. ✅ Update ForwardChainingService (save log)
4. ✅ Controller (index method first)
5. ✅ View (table sederhana dulu)
6. ✅ Test basic flow (gangguan → log tersimpan → tampil di table)
7. ✅ Tambahkan filter & search
8. ✅ Tambahkan update status (AJAX)
9. ✅ Tambahkan update notes (AJAX)
10. ✅ Polish UI & testing lengkap

---

## 📝 Additional Notes dari Diskusi

1. **Single Admin Use Case**
   - Tidak perlu esolved_by field
   - Tidak perlu user authentication tracking
   - Simplified workflow

2. **No Severity Auto-Calculate**
   - User decision: tidak perlu severity level
   - Metrics actual sudah cukup untuk analisis

3. **Future Enhancements (Fase 3)**
   - Export CSV/Excel
   - Grafik trend gangguan
   - Email notification (selain Telegram)
   - Integration dengan ticketing system

---

## ✅ Ready to Implement

**Next Step (Besok):**
1. Baca planning ini
2. Mulai dari migration
3. Follow implementation order
4. Testing setiap step

**Estimated completion:** 2.5 jam dari start

---

Dibuat: 2026-08-09 01:16 WIB
Status: Ready for Implementation
Developer: AI Agent + User

