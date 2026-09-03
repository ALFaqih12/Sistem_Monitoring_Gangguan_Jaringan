# BUG MODUL RULE FORWARD CHAINING

# 🔴 CRITICAL - Harus Diperbaiki

## Bug 2: Toggle Status Tidak Update UI Optimistically

File: resources/js/Pages/Rules/Index.vue (baris 124-144)
Kode saat ini:
async function onToggle(rule, e) {
const desired = e.target.checked;
const prev = rule.is_active;
rule.is_active = desired; // Optimistic update
try {
const res = await fetch(`/rules/${rule.id}/toggle`, ...);
const data = await res.json();
if (!res.ok) throw new Error(data.message || 'Gagal mengubah status rule.');
rule.is_active = data.is_active; // Update dari server
} catch (err) {
rule.is_active = prev; // Rollback
e.target.checked = prev;
alert('Error: ' + err.message);
}
}
Masalah:

- Menggunakan alert() untuk error feedback (bad UX) ❌
- Tidak ada loading indicator saat request
- User tidak tahu apakah toggle sedang processing atau selesai
- alert() akan blocking UI thread
  Rekomendasi:
- Ganti alert() dengan toast notification atau inline error message
- Tambah loading state: disable toggle saat request berlangsung
- Tambah visual feedback (spinner/loading indicator)

## Bug 3: Delete Confirmation Menggunakan Browser confirm()

File: resources/js/Pages/Rules/Index.vue (baris 147-149)
Kode saat ini:
function remove(rule) {
if (!confirm('Yakin ingin menghapus rule ini? Semua kondisi yang terkait juga akan terhapus.')) return;
useForm({}).delete(`/rules/${rule.id}`);
}
Masalah:

- Menggunakan browser native confirm() (bad UX) ❌
- Tidak consistent dengan design system
- Tidak ada loading state setelah konfirmasi
- User bisa klik delete berkali-kali sebelum request selesai (race condition)
  Rekomendasi:
- Buat modal konfirmasi custom dengan styling consistent
- Tambah loading state setelah konfirmasi
- Disable tombol delete saat request berlangsung
- Tampilkan nama rule di konfirmasi: "Hapus rule R01 - Latency tinggi?"

# 🟡 MEDIUM - Perlu Ditingkatkan

# Issue 7: Search Tidak Mencari di Target Device

File: app/Http/Controllers/RuleController.php (baris 26-31)
Kode saat ini:
->when($search, function ($query, $search) {
    return $query->where(function ($q) use ($search) {
        $q->where('code', 'like', "%{$search}%")
->orWhere('name', 'like', "%{$search}%");
});
})
Masalah:

- Pencarian hanya di code dan name
- User tidak bisa search berdasarkan IP address device
- Misal user cari "192.168.18.200" → tidak ketemu, padahal ada rule untuk device tersebut
  Rekomendasi:
  ->when($search, function ($query, $search) {
    return $query->where(function ($q) use ($search) {
        $q->where('code', 'like', "%{$search}%")
  ->orWhere('name', 'like', "%{$search}%")
          ->orWhereHas('targetDevice', function($dq) use ($search) {
              $dq->where('ip_address', 'like', "%{$search}%")
  ->orWhere('customer_name', 'like', "%{$search}%");
  });
  });
  })

# 🔵 LOW - Nice to Have

## Issue 8: Tidak Ada Sorting pada Tabel Index

File: resources/js/Pages/Rules/Index.vue
Masalah:

- Tabel hanya sorting latest() (descending by created_at)
- User tidak bisa sort by code, name, atau status
- Untuk banyak rule, sulit mencari rule tertentu
  Rekomendasi:
- Tambah sortable column headers
- Klik header untuk toggle asc/desc
- Icon indicator (↑ ↓) untuk sorting direction

## Issue 9: Tidak Ada Bulk Actions

Masalah:

- User harus toggle ON/OFF satu-per-satu
- User harus delete satu-per-satu
- Tidak efisien untuk manage banyak rule
  Rekomendasi:
- Checkbox untuk select multiple rules
- Bulk toggle: "Aktifkan semua yang dipilih" / "Nonaktifkan semua yang dipilih"
- Bulk delete: "Hapus semua yang dipilih"

## Issue 10: Tidak Ada Rule Preview/Test

Masalah:

- User tidak bisa test rule sebelum save
- User tidak tahu apakah rule akan match atau tidak
- Trial and error untuk setting threshold
  Rekomendasi:
- Button "Test Rule" di form create/edit
- Input sample data (bandwidth, latency, packet_loss)
- Show preview: "Dengan data ini, rule AKAN / TIDAK AKAN match"

## Issue 11: Tidak Ada Export/Import Rules

Masalah:

- Jika ingin backup rules → manual copy dari database
- Jika ingin clone rule ke instance lain → manual re-create
- Tidak bisa share rule template
  Rekomendasi:
- Export rules ke JSON/CSV
- Import rules dari file
- Clone rule functionality (duplicate existing rule dengan nama berbeda)

## Issue 12: Value Input Tidak Ada Max Validation

File: resources/js/Pages/Rules/Create.vue & Edit.vue (baris 97)
Kode saat ini:
<input type="number" v-model="c.value" step="0.01" min="0" required placeholder="0" />
Masalah:

- Hanya ada min="0", tidak ada max
- User bisa input nilai tidak realistis: bandwidth > 999999999 Mbps
- Tidak ada validasi range sesuai parameter:
- Bandwidth: realistic max ~10000 Mbps (10 Gbps)
- Latency: realistic max ~5000 ms (5 detik)
- Packet Loss: max 100% (tidak masuk akal > 100%)
  Rekomendasi:
  <input 
    type="number" 
    v-model="c.value" 
    step="0.01" 
    min="0" 
    :max="maxFor(c.parameter)"
    required 
    placeholder="0" 
  />
  function maxFor(parameter) {
  return parameter === 'bandwidth' ? 10000 : parameter === 'latency' ? 5000 : 100;
  }
