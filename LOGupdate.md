## 2026-09-01 (Update #5)

### PERBAIKAN ISSUE #5 (Lanjutan): Validasi Duplicate Parameters pada Kondisi Rule

#### Deskripsi Issue
- Pada modul Rule Forward Chaining, user bisa menambahkan 2+ kondisi dengan parameter yang **SAMAKI** dalam 1 rule.
- Contoh: 2 kondisi dengan parameter `bandwidth` (misal: `> 100` dan `< 50`) - ini redundancy/confusion.
- Requirement: Setiap parameter hanya boleh muncul maksimal 1 kali dalam 1 rule.
- Note: Berbeda rule dengan parameter yang sama di-bolehkan (misal: R01 punya bandwidth, R02 punya bandwidth).

#### Root Cause
- Tidak ada validasi untuk mencegah duplicate parameters dalam 1 rule.
- Form Vue hanya mencegah duplicate parameter melalui UI (dropdown bisa dipilih berulang).

#### File yang Diperbaiki
1. **app/Http/Controllers/RuleController.php**
   - Tambah method `validateDuplicateParameters()` (private)
   - Tambah validasi di `store()` method
   - Tambah validasi di `update()` method

#### Perubahan Teknis

**Method baru: `validateDuplicateParameters(array $conditions): ?array`**
- Group conditions by parameter dan count occurrences
- Jika ada parameter dengan count > 1 → return error message
- Example error message: "Setiap parameter hanya boleh muncul maksimal 1 kali dalam rule yang sama. Parameter berulang: Bandwidth (2x)"

**Integrasi di `store()` method (setelah validasi Laravel):**
```php
// Validasi duplicate parameters (hanya boleh 1 kondisi per parameter)
$duplicateError = $this->validateDuplicateParameters($validated['conditions']);
if ($duplicateError) {
    return back()->withErrors($duplicateError)->withInput();
}
```

**Integrasi di `update()` method (setelah validasi Laravel):**
- Identik dengan store() - sama logic

#### Validation Flow (Setelah Fix)

**Backend Validation Order:**
1. Laravel Request Validation (required, enum, numeric, exists, dll)
2. Duplicate Parameters Validation (baru!)
3. Logical Contradiction Validation (sudah ada dari Update #4)
4. Database Transaction (save rule + conditions)

**Frontend UX:**
- User tidak bisa submit form dengan duplicate parameters
- Error message muncul di atas form: "Setiap parameter hanya boleh muncul maksimal 1 kali..."
- Form otomatis tetap terbuka dengan data yang sudah diisi (via `->withInput()`)

#### Contoh Validasi

**✅ VALID** (setiap parameter 1x):
```
Kondisi 1: bandwidth > 100 Mbps
Kondisi 2: latency > 50 ms
Kondisi 3: packet_loss > 5 %
→ ACCEPTED (3 parameter berbeda)
```

**✅ VALID** (1 parameter):
```
Kondisi 1: latency > 50 ms
→ ACCEPTED (hanya 1 kondisi)
```

**❌ INVALID** (duplicate parameter):
```
Kondisi 1: bandwidth > 100 Mbps
Kondisi 2: bandwidth < 50 Mbps
→ REJECTED: "Parameter berulang: Bandwidth (2x)"
```

**❌ INVALID** (triple duplicate):
```
Kondisi 1: packet_loss = 5 %
Kondisi 2: packet_loss > 3 %
Kondisi 3: packet_loss > 10 %
→ REJECTED: "Parameter berulang: Packet Loss (3x)"
```

#### Verifikasi
| Check | Hasil |
|-------|-------|
| `php -l` (RuleController.php) | ✅ No syntax errors |
| `php artisan test` | ✅ 2 passed (11 assertions), no regression |
| Form Create - Duplicate Parameter | ✅ Error message muncul |
| Form Edit - Duplicate Parameter | ✅ Error message muncul |
| Form Create - No Duplicate | ✅ Rule berhasil disimpan |
| Form Edit - No Duplicate | ✅ Rule berhasil diupdate |

#### Impact
- Risk: **LOW** (hanya tambah validation, tidak ubah logic existing)
- Data Integrity: **HIGH** (prevent duplicate parameters)
- UX: **HIGH** (informative error message, form tetap terbuka)
- Performance: **MINIMAL** (O(n) scan, negligible)
- Breaking Changes: **NONE** (rules existing yang sudah punya duplicate tidak terpengaruh, hanya validasi pada create/edit baru)

#### Testing Manual

**Test Case 1: Duplicate Parameter Rejection**
```
Steps:
1. Buka /rules/create
2. Nama Rule: Test Duplicate Parameter
3. Target Device: All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = bandwidth, Operator = <, Value = 50
7. Klik "Simpan Rule"

Expected:
❌ Error message: "Setiap parameter hanya boleh muncul maksimal 1 kali dalam rule yang sama. Parameter berulang: Bandwidth (2x)"
```

**Test Case 2: No Duplicate Acceptance**
```
Steps:
1. Buka /rules/create
2. Nama Rule: Test No Duplicate
3. Target Device: All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = latency, Operator = >, Value = 50
7. Klik "Simpan Rule"

Expected:
✅ Rule berhasil disimpan
✅ Muncul di halaman list dengan 2 kondisi
```

#### Catatan Tambahan
- Duplicate parameter validation diimplementasikan **SEBELUM** logical contradiction validation.
- Urutan validasi: Laravel validation → Duplicate check → Contradiction check → Save.
- Error message menggunakan parameter label yang user-friendly (Bandwidth, Latency, Packet Loss).
- Existing rules di database yang sudah punya duplicate parameters tidak akan divalidasi ulang (hanya validasi pada create/edit baru).

---

## 2026-09-01 (Update #4)

### PERBAIKAN ISSUE #5: Rule Forward Chaining - Validasi Logical Contradiction pada Kondisi

#### Deskripsi Issue
- User bisa membuat kondisi rule yang **bertentangan secara logika**, sehingga rule **TIDAK AKAN PERNAH MATCH**.
- Contoh contradiction:
  - `bandwidth > 100 Mbps AND bandwidth < 50 Mbps` (tidak ada nilai yang satisfy kedua kondisi)
  - `latency = 50 ms AND latency > 50 ms` (nilai tidak bisa equal dan greater bersamaan)
  - `packet_loss = 5% AND packet_loss = 10%` (tidak bisa 5 dan 10 bersamaan)
- Dampak: Rule dibuat user tapi tidak pernah trigger, user bingung kenapa rule tidak bekerja, resource terbuang sia-sia.

#### Root Cause
- Tidak ada validasi saat create/edit rule untuk mendeteksi logical contradiction pada kondisi.
- Backend hanya validasi basic (required, enum values, numeric), tidak ada business logic validation.
- User bisa submit form dengan kondisi yang impossible secara logika.

#### File yang Diperbaiki
1. **app/Http/Controllers/RuleController.php** (tambah 2 private methods + validasi di store & update)

#### Perubahan Teknis

**Tambah 2 Helper Methods:**

**Method 1: `validateLogicalContradiction(array $conditions): ?array`**
- Group conditions by parameter
- Check setiap kombinasi kondisi pada parameter yang sama
- Return error message jika ditemukan contradiction, null jika valid
- ~50 lines, well-documented

**Method 2: `checkContradiction(array $cond1, array $cond2, string $parameter): ?string`**
- Implementasi 4 rule deteksi contradiction:
  1. **Opposite Range**: `> A AND < B` (hanya valid jika A < B)
  2. **Equality dengan Greater**: `= A AND > B` (hanya valid jika A > B)
  3. **Equality dengan Less**: `= A AND < B` (hanya valid jika A < B)
  4. **Multiple Equality**: `= A AND = B` (hanya valid jika A == B)
- Error message yang informatif dengan unit dan parameter label
- ~100 lines, well-commented

**Validasi di method `store()` (baris ~182):**
```php
$contradictionError = $this->validateLogicalContradiction($validated['conditions']);
if ($contradictionError) {
    return back()->withErrors($contradictionError)->withInput();
}
```

**Validasi di method `update()` (baris ~267):**
```php
$contradictionError = $this->validateLogicalContradiction($validated['conditions']);
if ($contradictionError) {
    return back()->withErrors($contradictionError)->withInput();
}
```

#### Deteksi Contradiction Matrix

| Operator 1 | Operator 2 | Valid? | Keterangan |
|------------|------------|--------|------------|
| `>` | `<` | ⚠️ DEPENDS | Valid jika val1 < val2 (range check) |
| `=` | `>` | ❌ INVALID | Val tidak bisa equal dan greater |
| `=` | `<` | ❌ INVALID | Val tidak bisa equal dan less |
| `=` | `=` | ⚠️ DEPENDS | Valid hanya jika value sama |
| `>` | `>` | ✅ VALID | Redundant tapi valid |
| `<` | `<` | ✅ VALID | Redundant tapi valid |

#### Contoh Error Message

**Saat user buat rule dengan contradiction:**
```
Input:
- Kondisi 1: bandwidth > 100 Mbps
- Kondisi 2: bandwidth < 50 Mbps

Error yang ditampilkan:
"Kondisi bertentangan pada Bandwidth: tidak mungkin > 100 Mbps DAN < 50 Mbps secara bersamaan."
```

#### Display Comparison

**Sebelum Fix:**
```
User buat rule dengan: bandwidth > 100 AND bandwidth < 50
✅ Form submit berhasil (tidak ada warning)
❌ Rule tersimpan ke DB tapi tidak akan pernah match
❌ User bingung kenapa rule tidak bekerja
```

**Sesudah Fix:**
```
User buat rule dengan: bandwidth > 100 AND bandwidth < 50
❌ Form submit REJECTED dengan error message
✅ User tahu ada logical contradiction pada kondisi
✅ User harus fix kondisi sebelum bisa simpan
✅ Hanya kondisi yang valid yang tersimpan ke DB
```

#### Verifikasi
| Check | Hasil |
|-------|-------|
| `php -l` (RuleController.php) | ✅ No syntax errors |
| `php artisan test` | ✅ 2 passed (11 assertions), no regression |
| Test Case: Opposite Range Contradiction | ✅ bandwidth > 100 AND < 50 → ERROR |
| Test Case: Valid Range | ✅ bandwidth > 50 AND < 100 → OK |
| Test Case: Equality with Greater | ✅ latency = 50 AND > 50 → ERROR |
| Test Case: Different Equality | ✅ packet_loss = 5 AND = 10 → ERROR |
| Test Case: Different Parameters | ✅ bandwidth > 100 AND latency > 50 → OK |
| Test Case: Boundary Values | ✅ bandwidth >= 100 AND <= 100 → OK (valid) |

#### Impact
- Risk: **VERY LOW** (hanya tambah validasi, tidak mengubah existing logic)
- Data Integrity: **HIGH** (prevent impossible rules dari disimpan)
- UX: **HIGH** (user dapat feedback immediate saat create/edit)
- Code Complexity: **MEDIUM** (tambah ~150 lines well-documented code)
- Performance: **MINIMAL** (O(n²) per parameter, negligible untuk <10 conditions)
- Breaking Changes: **NONE** (rules existing tidak terpengaruh)

#### Catatan Tambahan
- Validasi berjalan di backend sebelum save ke database
- Rules yang sudah ada di database (mungkin ada yang bertentangan) tidak divalidasi ulang, dibiarkan as-is
- Error message mencakup unit (Mbps, ms, %) dan parameter label yang user-friendly
- Epsilon comparison (0.01) untuk equality check mengatasi floating-point precision issue

---

## 2026-09-01 (Update #3)

### PERBAIKAN BUG: Rule Forward Chaining - Unit & Parameter Label Tidak Muncul di Kondisi Rule

#### Deskripsi Bug
- Pada halaman list rule (/rules), kolom "Kondisi" hanya menampilkan nilai numerik tanpa satuan dan label parameter.
- User melaporkan: kondisi rule hanya tampil "latency > 10" tanpa unit "ms", seharusnya "Latency > 10 ms".
- Dampak: User kesulitan memahami kondisi rule karena tidak ada informasi tentang satuan (Mbps, ms, %) dan parameter tidak user-friendly (lowercase).

#### Root Cause
**Accessor Tidak Di-Serialize ke JSON Response**
- Model `RuleCondition` memiliki accessor `getUnitAttribute()` dan `getParameterLabelAttribute()` yang berfungsi dengan baik di PHP.
- **TETAPI** Laravel Eloquent **tidak otomatis meng-append accessor** ke JSON response kecuali explicitly di-define di `protected $appends`.
- Tanpa `$appends`, JSON response hanya include kolom database (`parameter`, `operator`, `value`), tidak include computed accessor.
- Vue Index.vue mencoba akses `cond.unit` dan `cond.parameter_label` → **undefined**.
- Hasil: kondisi tampil tanpa satuan dan label yang proper.

#### File yang Diperbaiki
1. **app/Models/RuleCondition.php** (baris 25-32)

#### Perubahan Teknis

**Tambah `protected $appends` di Model RuleCondition**
- **Sebelum**: 
  ```php
  protected $casts = [
      'value' => 'decimal:2',
  ];
  
  // Langsung ke relasi method, tidak ada $appends
  public function rule() { ... }
  ```
  - Accessor hanya tersedia di PHP, tidak di-serialize ke JSON
  - Inertia response tidak include `unit` dan `parameter_label`
  - Vue akses `cond.unit` → `undefined`
  
- **Sesudah**:
  ```php
  protected $casts = [
      'value' => 'decimal:2',
  ];
  
  // Append accessor ke JSON response agar Vue bisa akses unit dan parameter_label.
  // Tanpa $appends, accessor hanya tersedia di PHP, tidak di-serialize ke JSON.
  protected $appends = [
      'unit',
      'parameter_label',
  ];
  
  public function rule() { ... }
  ```
  - Accessor otomatis di-append ke setiap JSON serialization
  - Inertia response include `unit: "ms"` dan `parameter_label: "Latency"`
  - Vue bisa akses `cond.unit` dan `cond.parameter_label` dengan normal

#### Komentar Penjelasan yang Ditambahkan
- Komentar menjelaskan kenapa `$appends` diperlukan untuk Inertia/JSON response
- Komentar menjelaskan dampak jika tidak ada `$appends` (accessor tidak ter-include)
- Komentar sebagai bug fix documentation untuk future reference

#### Verifikasi
| Check | Hasil |
|-------|-------|
| **Debug Command** | ✅ JSON response include `"unit": "ms"` dan `"parameter_label": "Latency"` |
| `php artisan test` | ✅ 2 passed (11 assertions) |
| **Halaman Rules Index** | ✅ Kondisi tampil lengkap: "Latency > 10 ms" (sebelumnya: "latency > 10") |
| **Multiple Parameters** | ✅ "Bandwidth > 100 Mbps AND Latency > 50 ms AND Packet Loss > 5 %" |

#### Display Comparison

**Sebelum Fix:**
```
Kondisi: latency > 10          ❌ (tidak ada unit, parameter lowercase)
Kondisi: bandwidth > 100       ❌ (tidak ada unit Mbps)
Kondisi: packet_loss > 5       ❌ (tidak ada unit %, underscore tidak friendly)
```

**Sesudah Fix:**
```
Kondisi: Latency > 10 ms       ✅ (ada unit, parameter capitalized)
Kondisi: Bandwidth > 100 Mbps  ✅ (ada unit Mbps)
Kondisi: Packet Loss > 5 %     ✅ (ada unit %, label user-friendly dengan space)
```

#### Impact
- Risk: **VERY LOW** (hanya menambah property ke JSON, tidak mengubah business logic)
- Benefit: **HIGH** (readability & UX meningkat signifikan, informasi lebih jelas)
- Breaking Changes: **NONE** (backward compatible, property tambahan tidak break existing code)
- Performance: **MINIMAL** (accessor sudah di-cache oleh Eloquent, overhead negligible)

---

## 2026-09-01 (Update #2)

### PERBAIKAN BUG: Rule Forward Chaining - "Device Telah Dihapus" Muncul Padahal Device Masih Ada

#### Deskripsi Bug
- Pada halaman list rule (/rules), kolom "Target Device" menampilkan badge merah "Device telah dihapus" untuk semua rule dengan target_type = 'specific', padahal device masih ada di database dan belum dihapus.
- User melaporkan: setelah membuat rule baru dengan specific device, rule berhasil tersimpan (ada pesan sukses), tetapi di tabel list rule muncul "Device telah dihapus".
- Verifikasi menunjukkan: form edit rule dapat menampilkan device dengan benar di dropdown, membuktikan data tersimpan dengan benar dan relasi berfungsi.

#### Root Cause
1. **Type Mismatch (Preventive Fix)**: HTML `<option value="...">` selalu mengkonversi value menjadi string, sehingga saat user memilih device dengan id `1`, yang ter-bind adalah string `"1"` bukan number `1`. Meskipun Laravel validasi akan cast ke integer, best practice adalah memastikan type consistency sejak dari frontend.
2. **Naming Convention Mismatch (Critical Bug)**: Laravel Eloquent mengirim relasi dengan **snake_case** (`target_device`) dalam JSON response, tetapi Vue mengakses dengan **camelCase** (`targetDevice`). Ini menyebabkan `rule.targetDevice` selalu `undefined`, sehingga kondisi fallback ke "Device telah dihapus" meskipun data tersimpan dengan benar di database.
3. **Display Logic Issue (Additional Fix)**: Kondisi `v-else-if="rule.targetDevice"` di Index.vue terlalu generic dan tidak cukup defensive. Perlu double-check: apakah `target_device_id` ada DAN apakah relasi `target_device` ter-load.

#### File yang Diperbaiki
1. **resources/js/Pages/Rules/Create.vue** (baris 44)
2. **resources/js/Pages/Rules/Edit.vue** (baris 44)
3. **resources/js/Pages/Rules/Index.vue** (baris 46-50)

#### Perubahan Teknis

**Perbaikan 1: Type Consistency di Vue Form (Create.vue & Edit.vue)**
- **Sebelum**: `<select v-model="form.target_device_id" :required="...">`
  - Value dari dropdown adalah string (misal: `"1"`, `"2"`)
  - Bisa menyebabkan type mismatch antara frontend (string) dan backend (integer)
- **Sesudah**: `<select v-model.number="form.target_device_id" :required="...">`
  - Vue modifier `.number` otomatis cast value ke integer
  - Memastikan `form.target_device_id` selalu bertype number (misal: `1`, `2`)
  - Best practice untuk form yang mengirim numeric ID

**Perbaikan 2: Naming Convention Fix - snake_case vs camelCase (Index.vue)**
- **Sebelum**: 
  ```vue
  <span v-else-if="rule.targetDevice" class="badge">{{ rule.targetDevice.ip_address }}</span>
  ```
  - Mengakses relasi dengan camelCase `rule.targetDevice`
  - **BUG CRITICAL**: Laravel Eloquent mengirim JSON dengan snake_case `target_device`
  - `rule.targetDevice` selalu `undefined` karena property tidak ada
  - False positive: selalu fallback ke "Device telah dihapus"
- **Sesudah**:
  ```vue
  <span v-else-if="rule.target_device_id && rule.target_device" class="badge">
    {{ rule.target_device.ip_address }}
  </span>
  ```
  - Mengakses relasi dengan snake_case `rule.target_device` (sesuai Laravel convention)
  - Double-check: cek `rule.target_device_id` (apakah ada ID tersimpan) DAN `rule.target_device` (apakah relasi ter-load)
  - Lebih defensive dan explicit, mencegah false positive

#### Komentar Penjelasan yang Ditambahkan
- **Create.vue & Edit.vue**: Komentar menjelaskan fungsi `.number` modifier dan kenapa diperlukan untuk bug prevention
- **Index.vue**: Komentar menjelaskan logika defensive check, naming convention snake_case Laravel, dan tujuan mencegah false positive "Device telah dihapus"

#### Verifikasi
| Check | Hasil |
|-------|-------|
| `npm run build` | ✅ Success (629 modules, 231.67 kB JS gzip 81.32 kB) |
| `php artisan test` | ✅ 2 passed (11 assertions) |
| Form Create - Specific Device | ✅ Rule tersimpan, IP device muncul di list (tidak ada "Device telah dihapus") |
| Form Edit - Ubah Device | ✅ Perubahan tersimpan dengan benar |
| Halaman Index - Existing Rules | ✅ Semua rule specific device menampilkan IP address yang benar |
| Form Create - All Devices (Regression) | ✅ Tidak broken, badge "All Devices" muncul dengan benar |

#### Impact
- Risk: LOW (hanya perubahan type casting dan display logic, tidak mengubah backend atau database)
- Benefit: HIGH (bug critical yang membuat UI misleading sudah resolved, user tidak bingung lagi)
- Breaking Changes: NONE (backward compatible, semua fitur existing tetap berfungsi)

#### Catatan Tambahan
- Dropdown "Pilih Device" di form tambah/edit rule sudah benar sejak awal: hanya menampilkan device yang ada di database, device yang dihapus tidak muncul di dropdown.
- Bug terjadi karena **naming convention mismatch**: Laravel mengirim relasi sebagai `target_device` (snake_case) tetapi Vue mengakses sebagai `targetDevice` (camelCase).
- **Pembelajaran penting**: Laravel Eloquent secara default mengirim relasi dengan snake_case dalam JSON response. Vue harus mengakses dengan nama yang sama persis, tidak boleh diubah ke camelCase.
- Form edit dapat load device dengan benar karena menggunakan single model (bukan paginator), tetapi halaman index menggunakan paginator yang struktur JSONnya berbeda.
- Debug dilakukan dengan membuat command `php artisan debug:rule-relation` dan `php artisan debug:inertia-rule` untuk melihat struktur data actual dari backend.

---

## 2026-09-01 (Update #1)

### PERBAIKAN BUG: Rule Forward Chaining - Form "All Devices" Tidak Bisa Disimpan

#### Deskripsi Bug
- Pada form tambah rule (Rules/Create.vue) dan form edit rule (Rules/Edit.vue), ketika user memilih "Target Device: All Devices" dan klik tombol "Simpan Rule", form tidak ter-submit dan tidak ada feedback apapun (tidak ada pesan error atau sukses).
- Root cause: HTML5 validation attribute `required` pada dropdown "Pilih Device" masih aktif meskipun field disembunyikan dengan `v-show`. Browser memblokir form submit karena mendeteksi field required yang kosong, padahal field tersebut tidak diperlukan saat `target_type = 'all'`.

#### File yang Diperbaiki
1. **resources/js/Pages/Rules/Create.vue** (baris 44)
2. **resources/js/Pages/Rules/Edit.vue** (baris 44)

#### Perubahan Teknis
- **Sebelum**: `<select v-model="form.target_device_id" required>`
  - Attribute `required` bersifat static, selalu aktif
  - HTML5 validation memblokir submit ketika `target_type = 'all'` karena field `target_device_id` bernilai `null`/empty
- **Sesudah**: `<select v-model="form.target_device_id" :required="form.target_type === 'specific'">`
  - Attribute `required` bersifat conditional (Vue binding)
  - Hanya aktif jika `target_type === 'specific'`
  - Jika `target_type === 'all'`, attribute `required` tidak ada di DOM sehingga validasi browser di-skip dan form submit berhasil

#### Komentar Penjelasan yang Ditambahkan
```vue
<!-- Conditional required: hanya wajib jika target_type === 'specific'.
     Jika target_type === 'all', field ini tidak perlu diisi dan validasi browser di-skip.
     Bug fix: form tidak bisa submit saat pilih "All Devices" karena HTML5 validation
     masih aktif pada field yang disembunyikan dengan v-show. -->
```

#### Verifikasi
| Check | Hasil |
|-------|-------|
| `npm run build` | ✅ Success (629 modules, 231.67 kB JS gzip 81.31 kB) |
| `php artisan test` | ✅ 2 passed (11 assertions) |
| Form Create - All Devices | ✅ Form submit berhasil, rule tersimpan |
| Form Create - Specific Device (validasi) | ✅ Browser menampilkan "Please fill out this field" jika device tidak dipilih |
| Form Edit - Ubah ke All Devices | ✅ Update berhasil |

#### Impact
- Risk: LOW (hanya perubahan HTML attribute, tidak mengubah backend logic)
- Benefit: HIGH (bug critical yang menghalangi penambahan rule dengan target "All Devices" sudah resolved)

---

## 2026-08-31

### PERBAIKAN STYLING & UI (Style-Only) + BUG FIXES

#### 1. Refactoring CSS Global (`resources/css/app.css`)
- Tambahkan **CSS Variables (Design Tokens)**: warna (primary, success, warning, danger, info, netral), radius, shadow, font, spacing, transition, layout
- Ekstrak **shared styles** dari 7 file Vue ke global: `.btn*` (7 varian), `.card`, `.page-container`, `.page-header`, `.badge` (5 varian), `.table` + `.pagination`, `.form-group`, `.alert` (4 varian), `.toggle-switch`, `.status-message`, `.info-panel`, `.form-hint`/`.form-error`
- Hapus `body { display: flex }` → ganti `display: block` + `overflow-x: hidden` untuk mencegah overflow horizontal
- Body reset sudah include font-family, box-sizing, margin/padding 0

#### 2. Perbaikan Layout & Styling Semua Halaman (7 file Vue)
| File | Perubahan Utama |
|------|-----------------|
| `AppLayout.vue` | Sidebar: gradient bg, border-left accent 3px pada hover/active, transition smooth, flex gap 12px, CSS variables; Content: full-width (hapus max-width 1200px, hapus justify-content center), padding-top 32px |
| `Dashboard/Index.vue` | Stat cards: angka 36px bold, icon circle gradient bg, hover border-primary + elevation, hapus CSS duplikat; Toggle card: badge status modern |
| `Devices/Index.vue` | Hapus CSS duplikat; Zebra striping tabel (`nth-child(even)`); Table card disembunyikan saat form buka (`v-show="!isFormOpen"`); Layout override max-width 1100px |
| `Devices/Edit.vue` | Hanya sisa CSS spesifik: page-container max-width 760px + form-row flex |
| `Rules/Index.vue` | Hapus CSS duplikat; Zebra striping tabel; Toggle switch custom 48x22px; Badge code monospace |
| `Rules/Create.vue` & `Edit.vue` | Condition card pakai CSS variables; Grid 3 kolom (parameter/operator/value); Unit badge styling |
| `IssueLogs/Index.vue` | Metric display: colored bg + left border 3px (good=hijau, bad=merah); Zebra striping; Auto-refresh bar polished |

#### 3. Emoji Removal — Clean & Professional Output
| Target | Jumlah | Pengganti |
|--------|--------|-----------|
| Console Commands (7 file) | 7 file | `[INFO]`, `[OK]`, `[ERROR]`, `[WARN]`, `[LIST]`, `[DATA]`, `[TIME]`, `[SETUP]`, `[STOP]`, `[ALERT]`, `[TEST]`, `[CREATE]`, `[LOC]`, `[SIGNAL]`, `[TIPS]` |
| `PrometheusService.php` | 2 log line | `[WARN] PROMETHEUS SIMULATION MODE...` |
| Vue files | 0 (sudah bersih) | — |

#### 4. Bug Fixes
- **Overflow horizontal**: `body { display: block; overflow-x: hidden }` + `.main-content` full-width tanpa padding samping
- **Content menempel atas**: Tambah `padding-top: 32px` pada `.main-content`
- **Devices form + table overlap**: Table card `v-show="!isFormOpen"` — saat form buka hanya form yang tampil
- **PHP Syntax**: Semua 8 file PHP lolos `php -l` (7 commands + PrometheusService)

#### 5. Verifikasi
| Check | Hasil |
|-------|-------|
| `npm run build` | ✅ Success (629 modules, CSS 10.73 kB gzip 2.53 kB) |
| `php artisan test` | ✅ 2 passed (11 assertions) |
| `php -l` (8 file) | ✅ No syntax errors |
| `php artisan route:list` | ✅ 18 route utuh |
| Emoji di web | ✅ Clean |

---

## 2026-08-30

### MIGRASI FULL: Blade → Vue 3 + Inertia.js (SPA)

#### Setup Environment (Fase 0)
- **Composer**: `inertiajs/inertia-laravel` v3.3.1
- **NPM**: `vue@3`, `@vitejs/plugin-vue`, `@inertiajs/vue3`
- **vite.config.js**: Tambah plugin `vue()`; input `resources/css/app.css`, `resources/js/app.js`
- **resources/js/app.js**: `createInertiaApp` dengan `import.meta.glob('./Pages/**/*.vue')` async resolve, default layout `AppLayout.vue`, register global `Link` & `Head`, progress bar warna `#2ecc71`
- **resources/views/app.blade.php**: Root view Inertia (`@inertia`, `@inertiaHead`, `<title inertia>`, CSRF meta, `@vite`)
- **Middleware**: `HandleInertiaRequests` (`$rootView = 'app'`), share `flash` (success/error) + `old` input, terdaftar di `bootstrap/app.php` via `web(append:)`

#### Fase 1: Dashboard
- **Layout**: `resources/js/Pages/Layouts/AppLayout.vue` — sidebar fixed 280px, `flex-shrink: 0`, `main-content` flex center, `content-wrapper` max-width 1200px
- **Page**: `resources/js/Pages/Dashboard/Index.vue` — stat cards dengan SVG icon, badge status modern (success/warning/danger), toggle monitoring native fetch + `router.reload()`, worker health badge computed, format heartbeat Asia/Jakarta
- **Controller**: `DashboardController::index` → `Inertia::render('Dashboard/Index', compact(...))`
- **Test**: `tests/Feature/ExampleTest.php` → `assertInertia(component('Dashboard/Index').has('totalDevices').has('totalRules'))`
- **Cleanup**: Hapus `resources/views/dashboard/index.blade.php` + folder kosong

#### Fase 2: Devices (Index + Edit)
- **Index**: `Devices/Index.vue` — form tambah inline (v-show), Connect SNMP native fetch `/devices/ping`, table card modern, search/pagination Inertia `<Link>`, action buttons (Edit Link, Hapus useForm.delete)
- **Edit**: `Devices/Edit.vue` — form card lengkap, Connect SNMP, verified IP logic, useForm.put
- **Controller**: `index`, `edit` → `Inertia::render`; `store/update/destroy/pingDevice` tidak diubah
- **Cleanup**: Hapus `resources/views/devices/index.blade.php`, `edit.blade.php` + folder

#### Fase 3: Rules (Index + Create + Edit)
- **Index**: `Rules/Index.vue` — badge code monospace, toggle switch native fetch `/rules/{id}/toggle`, condition list rapi, search/pagination Inertia
- **Create**: `Rules/Create.vue` — form card, kondisi dinamis grid 3 kolom (parameter/operator/value), unit badge (Mbps/ms/%), add/remove kondisi
- **Edit**: `Rules/Edit.vue` — mirip Create, prepopulate data rule + conditions
- **Controller**: `index`, `create`, `edit` → `Inertia::render`; `store/update/destroy/toggle` tidak diubah
- **Cleanup**: Hapus `resources/views/rules/index.blade.php`, `create.blade.php`, `edit.blade.php` + folder

#### Fase 4: Issue Logs
- **Index**: `IssueLogs/Index.vue` — tabel metrics dengan badge warna (good/bad), auto-refresh fetch `/api/issue-logs` 60 detik + manual refresh + indicator dot animasi, filter tanggal, pagination, pagination info
- **Controller**: `index` → `Inertia::render`; `indexApi` tetap untuk auto-refresh
- **Cleanup**: Hapus `resources/views/issue_logs/index.blade.php`, `layouts/app.blade.php` + folder `layouts`

#### Styling & Layout Perbaikan
- **Global Reset**: `resources/css/app.css` — reset `*`, `body` (dipindah dari AppLayout scoped)
- **Layout**: `AppLayout.vue` — sidebar fixed 280px `flex-shrink: 0`, link text overflow ellipsis, `main-content` flex center, `content-wrapper` max-width 1200px, padding 32px 24px
- **Page Container**: Semua page — `max-width` disesuaikan per modul + `padding: 0 24px` (Dashboard/Devices/Index/Rules/Index: 1100px; Devices/Edit: 760px; Rules/Create/Edit: 860px; IssueLogs: 1200px)
- **Design System Konsisten**:
  - Card: radius 12px, shadow subtle, border `#e8edf3`, padding 28px
  - Input/Select: border `#d1d9e0`, focus ring `#3498db` 3px
  - Button Primary: `#3498db` → hover `#2980b9`; Secondary: `#95a5a6`; Ghost: transparent+border; Danger: `#e74c3c`
  - Badge: pill radius 999px, font-weight 600
  - Table: header `#f8fafc` + border-bottom 2px, row hover `#f8fbff`
  - Color: Primary `#3498db`, Success `#27ae60`/`#2ecc71`, Warning `#e67e22`/`#f39c12`, Danger `#e74c3c`

#### Backend Logic (TIDAK DIUBAH)
- Migrasi, Model, Service (PrometheusService, ForwardChainingService, PrometheusTargetService), Scheduler, Console Commands — **100% utuh**

#### Verifikasi Final
- `npm run build` ✅ Success (629 modules, 231 kB JS gzip 81 kB)
- `php artisan test` ✅ 2 passed (11 assertions)
- `php artisan serve` → HTTP 200 OK
- `php artisan route:list` → 18 route utuh
- Blade orphan terhapus: `dashboard/`, `devices/`, `rules/`, `issue_logs/`, `layouts/` — hanya `welcome.blade.php` tersisa

---

## 2026-08-22

### Update Modul Log Gangguan + Auto-Refresh UI

- **ForwardChainingService.php**: Perubahan logika penyimpanan issue log menjadi UPSERT.
    - Method `saveIssueLog()`: jika sudah ada IssueLog untuk kombinasi `device_id + rule_id` yang sama, UPDATE metrics + `detected_at` + `maps_url` (bukan buat baru). Jika belum ada, buat baris baru (hanya untuk rule berbeda pada device yang sama).
    - Kolom `maps_url` sekarang diisi otomatis dari device yang bersangkutan (sebelumnya tidak di-set).
    - Hapus referensi kolom `status` dan `notes` yang sudah di-drop migration.
    - Dipisahkan deduplikasi alert (cache/TTL) dari penyimpanan log — log selalu di-upsert tiap kali match, sedangkan alert Telegram hanya dikirim sekali per TTL window.

- **ForwardChainingService.php**: Penambahan cleanup stale logs.
    - Method baru `cleanupStaleLogs(array $matchedPairs)`: menghapus IssueLog yang tidak lagi match dengan rule manapun setelah setiap cycle evaluasi.
    - Jika tidak ada rule yang match → hapus semua issue logs.
    - Mencegah tabel log menumpuk data ketinggalan.

- **IssueLogController.php**: Penghapusan method yang tidak terpakai & penambahan API endpoint.
    - Hapus method `updateStatus()` dan `updateNotes()` (kolom status/notes sudah dihapus dari DB).
    - Hapus filter `status` di method `index()`.
    - Tambah method `indexApi()` — mengembalikan log gangguan sebagai JSON untuk konsumsi AJAX.

- **routes/web.php**: Update route.
    - Hapus route `POST /issue-logs/{log}/status` dan `POST /issue-logs/{log}/notes`.
    - Tambah route `GET /api/issue-logs` (name: `api.issue-logs.index`) untuk AJAX auto-refresh.

- **resources/views/issue_logs/index.blade.php**: Redesign tabel & auto-refresh.
    - Ganti kolom **Status** (badge new/ongoing/resolved) dengan kolom **Lokasi** (`maps_url` sebagai link "Buka Maps").
    - Hapus kolom **Catatan** dan kolom **Aksi**.
    - Hapus modal Update Status, modal Add Notes, dan seluruh JavaScript AJAX-nya.
    - Tambah auto-refresh UI: fetch ke `/api/issue-logs` setiap 60 detik via `setInterval`.
    - Tambah indicator "Terakhir update: HH:MM:SS", tombol "Refresh Manual", dan dot status hijau/orange.
    - JavaScript menggunakan `!= null` (loose equality) untuk mencegah `undefined` crash, null-safe access untuk `log.rule` & `log.device`, dan `parseFloat()` untuk perbandingan threshold.
    - Tambah `.catch()` handler dengan notifikasi error di indicator.

- **database/migrations/2026_08_22_000000_update_issue_logs_table.php**: Fix SQLite compatibility.
    - Tambah `dropIndex('issue_logs_status_index')` sebelum `dropColumn(['status', 'notes'])`, dilakukan secara conditional (hanya di SQLite driver) agar tidak error di MySQL.
    - Migration sudah dijalankan ke MySQL produksi — kolom `status` dan `notes` berhasil dihapus, kolom `maps_url` berhasil ditambahkan.

- **Interval penyelarasan timing**:
    - Worker monitoring: evaluasi tiap 30 detik.
    - Web UI auto-refresh: fetch tiap 60 detik (dijauhkan agar tidak bentrok dengan worker write cycle).

- **Verifikasi**:
    - `php -l` pada semua file PHP yang diubah: passed.
    - `php artisan test`: 2 passed (6 assertions).
    - `php artisan route:list`: hanya `issue-logs.index` (GET) dan `api.issue-logs.index` (GET) tersisa untuk modul ini.

## 2026-08-09

### Perbaikan Bug : PrometheusService Simulation Mode Warning Flag

- Menambahkan log warning saat PrometheusService diinisialisasi dengan PROMETHEUS_SIMULATION_MODE=true.
    - File: `app/Services/PrometheusService.php`.
    - Warning log di constructor: "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â PROMETHEUS SIMULATION MODE ACTIVE! Data yang digunakan adalah FAKE/SIMULASI, bukan data real dari Prometheus."
    - Memberikan instruksi untuk disable: "Untuk menggunakan data real, set PROMETHEUS_SIMULATION_MODE=false di file .env"
    - Tujuan: Agar admin/developer tidak salah interpretasi bahwa data adalah real padahal fake.

- Menambahkan debug log saat simulation mode generate fake data.
    - Method `getLatency()`: Log debug "Simulation mode: Generating fake latency {value}ms for {ip}"
    - Method `getLatency()` error fallback: Log debug "Simulation mode (error fallback): Generating fake latency..."
    - Method `getPacketLoss()`: Log debug "Simulation mode: Generating fake packet loss {value}% for {ip}"
    - Method `getPacketLoss()` error fallback: Log debug "Simulation mode (error fallback): Generating fake packet loss..."
    - Method `getAllMetricsBatch()`: Log warning "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Simulation mode: Generating fake metrics for batch query (X devices)"

- Alasan perbaikan bug ini:
    - Sebelumnya jika Prometheus server down, sistem otomatis switch ke simulation mode tanpa warning.
    - User/admin bisa membuat keputusan berdasarkan data fake tanpa sadar (BERBAHAYA!).
    - Dengan warning log yang jelas, admin akan segera tahu bahwa data bukan real.

- Verifikasi:
    - Syntax check passed: `php -l app/Services/PrometheusService.php`
    - File encoding fixed (UTF-8 without BOM).

- Impact:
    - Risk: LOW (hanya menambah logging, tidak mengubah logic).
    - Production safety: HIGH (mencegah decision making based on fake data).

### Perbaikan Bug : Batch Query Prometheus - Empty Result Handling

- Menambahkan handling untuk empty result pada batch query Prometheus.
    - File: `app/Services/PrometheusService.php`.
    - Method: `getAllMetricsBatch()`.
- Penambahan warning log untuk setiap metric yang return empty:
    - Bandwidth empty: "Prometheus batch query return EMPTY result for BANDWIDTH"
    - Latency empty: "Prometheus batch query return EMPTY result for LATENCY"
    - Packet Loss empty: "Prometheus batch query return EMPTY result for PACKET LOSS"
    - Semua empty: "ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â CRITICAL: All Prometheus batch queries return EMPTY!"
- Menambahkan possible causes di log untuk troubleshooting:
    - Bandwidth empty: Devices not scraped yet, SNMP exporter down, Wrong metric name
    - Latency empty: Blackbox exporter down, Targets not configured, Probe failed
    - Packet Loss empty: Blackbox exporter down, probe_success metric not available
- Menambahkan log error untuk HTTP status code yang tidak successful.
    - Log status code untuk debugging (misal: 500, 503, 400)
- Menambahkan detailed summary log setelah batch query:
    - Format: "Prometheus batch summary: X/Y bandwidth, X/Y latency, X/Y packet_loss"
    - Membantu admin monitoring berapa device yang berhasil/gagal di-query
- Menambahkan stack trace pada exception untuk troubleshooting lebih mudah.

- Alasan perbaikan bug ini:
    - Sebelumnya jika Prometheus return empty result (misal: network hiccup), tidak ada log warning.
    - Admin tidak tahu apakah query berhasil atau gagal.
    - Forward chaining bisa false negative (tidak detect gangguan yang sebenarnya ada).
    - Dengan warning log yang jelas, admin bisa segera troubleshoot masalah Prometheus.

- Verifikasi:
    - Syntax check passed: `php -l app/Services/PrometheusService.php`
    - File encoding: UTF-8 without BOM

- Impact:
    - Risk: LOW (hanya menambah logging, tidak mengubah logic).
    - Monitoring visibility: HIGH (admin tahu jika ada masalah di Prometheus).
    - Troubleshooting: Lebih mudah dengan possible causes di log.

# LOG Update Development

### Perbaikan Bug : Rule Code Generation - Tidak Handle Delete

- Memperbaiki logika auto-generate code rule di file `app/Models/Rule.php`.
    - Sebelumnya: Code baru selalu memakai nomor terakhir + 1, sehingga jika rule R05 dihapus maka ada gap (R03, R04 tidak pernah dipakai) dan nomor terus naik.
    - Sekarang: Code baru mengisi nomor terkecil yang BELUM dipakai (mengisi gap).
        - Contoh: code terpakai R01, R02, R05 -> rule baru mendapat R03 (bukan R06).
        - Contoh: semua rule dihapus -> rule baru mendapat R01 lagi.
        - Contoh: code terpakai R01, R02, R03 -> rule baru mendapat R04 (urutan normal).

- Cara kerja baru:
    1. Ambil semua code rule yang terpakai (R01, R02, ...).
    2. Ubah menjadi array angka [1, 2, ...].
    3. Urutkan dari terkecil.
    4. Cari nomor terkecil yang belum ada (isi gap).
    5. Generate code dengan padding dinamis (R01, R10, R100, dst).

- Tetap menggunakan `lockForUpdate()` untuk mencegah race condition ketika 2+ rule dibuat bersamaan.

- Verifikasi:
    - Syntax check passed: `php -l app/Models/Rule.php`
    - Test logika dengan 7 skenario (semua PASS): DB kosong, urutan normal, gap tengah, R01 dihapus, gap besar, semua dihapus.
    - File encoding: UTF-8 without BOM

- Impact:
    - Risk: LOW
    - Benefit: Code rule selalu berurutan tanpa nomor yang hilang, user tidak bingung "kenapa R03 tidak ada".

### Perbaikan Bug : SNMP Timeout Hardcoded di DeviceController

- Mengubah timeout SNMP dari hardcoded 1 detik (1000000 mikrodetik) menjadi configurable via .env.
    - File: `app/Http/Controllers/DeviceController.php` (method `pingDevice()`).
    - Sebelumnya: `@snmpget($ip, $community, "1.3.6.1.2.1.1.1.0", 1000000)` (1 detik - terlalu cepat untuk device slow/jauh).
    - Sekarang: `$snmpTimeout = env('SNMP_TIMEOUT_MICROSECONDS', 3000000)` (default 3 detik).
    - `@snmpget($ip, $community, "1.3.6.1.2.1.1.1.0", $snmpTimeout)`.

- Menambahkan variabel baru di `.env` dan `.env.example`.
    - `SNMP_TIMEOUT_MICROSECONDS=3000000` (3 detik default).
    - Disertai komentar penjelasan cara menggunakannya.

- Alasan perbaikan:
    - Timeout 1 detik terlalu cepat untuk perangkat jaringan yang lambat atau jauh.
    - Bisa menghasilkan "SNMP Timeout" padahal perangkat sebenarnya online.
    - Sekarang admin bisa menyesuaikan timeout sesuai kondisi jaringan tanpa mengubah kode.

- Verifikasi:
    - Syntax check passed: `php -l app/Http/Controllers/DeviceController.php`
    - File encoding: UTF-8 without BOM

- Impact:
    - Risk: LOW
    - Benefit: Koneksi Connect lebih toleran terhadap perangkat lambat/jauh; timeout bisa disetel dari .env tanpa edit kode.

### Perbaikan Bug : Batch Query Prometheus - Empty Result Handling

### Perbaikan Bug : No Monitoring untuk Worker Health

- Menambahkan mekanisme HEARTBEAT untuk memantau kesehatan worker monitoring.
    - File: `app/Console/Commands/MonitoringWorker.php`.
    - Setiap cycle evaluasi (setiap 30 detik), worker meng-update cache `worker_last_heartbeat` dengan timestamp terbaru.
    - Jika worker crash/macet diam-diam, heartbeat tidak ter-update lagi dan dashboard bisa mendeteksinya.

- Menambahkan deteksi worker MATI/MACET di dashboard.
    - File: `app/Http/Controllers/DashboardController.php` (method `index()`).
    - Menghitung umur heartbeat (`heartbeatAge`) = selisih waktu sekarang dengan heartbeat terakhir.
    - Worker dinyatakan SEHAT jika flag `monitoring_worker_running` = true DAN umur heartbeat <= 120 detik (2 menit).
    - Jika flag running true tapi heartbeat sudah basi (> 2 menit), berarti worker crash/macet.

- Menampilkan status kesehatan worker di view dashboard.
    - File: `resources/views/dashboard/index.blade.php`.
    - Badge worker baru:
        - Hijau "Berjalan (Sehat)" - worker hidup dan heartbeat fresh.
        - Merah tebal "MATI / MACET!" - worker crash/macet (heartbeat basi).
        - Merah "Berhenti" - monitoring nonaktif.
    - Menampilkan jam heartbeat terakhir dan peringatan jika worker mati.

- Alasan perbaikan:
    - Sebelumnya jika worker crash jam 2 pagi, admin tidak sadar sampai siang (10 jam downtime).
    - Gangguan jaringan tidak terdeteksi selama worker mati.
    - Sekarang dashboard menampilkan status kesehatan worker secara real-time.

- Verifikasi:
    - Syntax check passed untuk `MonitoringWorker.php` dan `DashboardController.php`.
    - Blade template compile OK (`php artisan view:cache`).
    - Test Laravel passed (2 tests).

- Impact:
    - Risk: LOW
    - Benefit: Admin bisa langsung tahu jika worker mati/macet sehingga downtime monitoring bisa segera diatasi.
- Menambahkan handling untuk empty result pada batch query Prometheus.
    - File: `app/Services/PrometheusService.php`.
    - Method: `getAllMetricsBatch()`.
- Penambahan warning log untuk setiap metric yang return empty:
    - Bandwidth empty: "Prometheus batch query return EMPTY result for BANDWIDTH"
    - Latency empty: "Prometheus batch query return EMPTY result for LATENCY"
    - Packet Loss empty: "Prometheus batch query return EMPTY result for PACKET LOSS"
    - Semua empty: "ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã‚Â¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã‚Â¯Ãƒâ€šÃ‚Â¸Ãƒâ€šÃ‚Â CRITICAL: All Prometheus batch queries return EMPTY!"
- Menambahkan possible causes di log untuk troubleshooting:
    - Bandwidth empty: Devices not scraped yet, SNMP exporter down, Wrong metric name
    - Latency empty: Blackbox exporter down, Targets not configured, Probe failed
    - Packet Loss empty: Blackbox exporter down, probe_success metric not available
- Menambahkan log error untuk HTTP status code yang tidak successful.
    - Log status code untuk debugging (misal: 500, 503, 400)
- Menambahkan detailed summary log setelah batch query:
    - Format: "Prometheus batch summary: X/Y bandwidth, X/Y latency, X/Y packet_loss"
    - Membantu admin monitoring berapa device yang berhasil/gagal di-query
- Menambahkan stack trace pada exception untuk troubleshooting lebih mudah.

- Alasan perbaikan bug ini:
    - Sebelumnya jika Prometheus return empty result (misal: network hiccup), tidak ada log warning.
    - Admin tidak tahu apakah query berhasil atau gagal.
    - Forward chaining bisa false negative (tidak detect gangguan yang sebenarnya ada).
    - Dengan warning log yang jelas, admin bisa segera troubleshoot masalah Prometheus.

- Verifikasi:
    - Syntax check passed: `php -l app/Services/PrometheusService.php`
    - File encoding: UTF-8 without BOM

- Impact:
    - Risk: LOW (hanya menambah logging, tidak mengubah logic).
    - Monitoring visibility: HIGH (admin tahu jika ada masalah di Prometheus).
    - Troubleshooting: Lebih mudah dengan possible causes di log.

## 2026-07-24

### Pembuatan Modul Rule Forward Chaining (Baru)

- Membuat tabel database baru.
    - File: `database/migrations/2026_07_24_122759_create_rules_table.php`.
    - File: `database/migrations/2026_07_24_122800_create_rule_conditions_table.php`.
    - Tabel `rules`: id, code (auto: R01, R02, dst), name, target_type (all/specific), target_device_id (FK ke devices), is_active, timestamps.
    - Tabel `rule_conditions`: id, rule_id (FK ke rules), parameter (bandwidth/latency/packet_loss), operator (>, <, >=, <=, =), value (decimal), timestamps.
    - Kedua tabel menggunakan foreign key dengan cascade delete.

- Membuat Model Eloquent baru.
    - File: `app/Models/Rule.php`.
        - Auto-generate code rule berformat R01, R02, R03, dst.
        - Relasi `targetDevice()` (belongsTo ke Device).
        - Relasi `conditions()` (hasMany ke RuleCondition).
        - Cast `is_active` sebagai boolean.
    - File: `app/Models/RuleCondition.php`.
        - Relasi `rule()` (belongsTo ke Rule).
        - Accessor `unit` untuk mendapatkan satuan parameter (Mbps, ms, %).
        - Accessor `parameter_label` untuk mendapatkan label user-friendly.
        - Cast `value` sebagai decimal.

- Membuat Controller baru.
    - File: `app/Http/Controllers/RuleController.php`.
    - Method `index()`: List rules dengan pagination 15 data dan search server-side, eager loading relasi targetDevice dan conditions.
    - Method `create()`: Menampilkan form tambah rule dengan dropdown device.
    - Method `store()`: Validasi input, simpan rule dan conditions dalam satu transaksi database.
    - Method `edit()`: Menampilkan form edit rule dengan data existing.
    - Method `update()`: Hapus semua kondisi lama, simpan kondisi baru dalam transaksi.
    - Method `toggle()`: AJAX toggle ON/OFF status rule.
    - Method `destroy()`: Hapus rule (conditions otomatis terhapus via cascade).

- Membuat View untuk Rule.
    - File: `resources/views/rules/index.blade.php`.
        - Tabel dengan kolom: Code, Nama Rule, Target Device, Kondisi, Status, Aksi.
        - Toggle switch CSS untuk ON/OFF dengan AJAX.
        - Menampilkan kondisi rule dengan format readable (parameter operator nilai unit).
        - Label "AND" di antara multiple conditions.
        - Badge khusus untuk code rule (biru) dan target device.
        - Pagination 15 data per halaman.
        - Search server-side.
    - File: `resources/views/rules/create.blade.php`.
        - Form dengan input dinamis untuk multiple conditions.
        - Dropdown target device (All Devices / Specific Device).
        - Button "Tambah Kondisi" untuk menambahkan kondisi baru secara dinamis.
        - Button "Hapus" untuk menghapus kondisi.
        - Auto-update satuan unit label (Mbps/ms/%) saat parameter diubah.
    - File: `resources/views/rules/edit.blade.php`.
        - Form edit dengan data conditions existing.
        - Mendukung tambah/hapus kondisi seperti di create.
        - Validasi minimal 1 kondisi.

- Menambahkan route untuk modul Rule.
    - 7 route baru di `routes/web.php`.
    - GET `/rules`, GET `/rules/create`, POST `/rules`, GET `/rules/{rule}/edit`, PUT `/rules/{rule}`, DELETE `/rules/{rule}`, POST `/rules/{rule}/toggle`.

- Memperbarui sidebar layout.
    - File: `resources/views/layouts/app.blade.php`.
    - Menambahkan menu "Rule Forward Chaining" dengan active state detection.
    - Total 15 route terdaftar di sistem.

### Pembuatan Service PrometheusService (Baru)

- File baru: `app/Services/PrometheusService.php`.
- Fungsi `getBandwidth($ipAddress)`: Query bandwidth dari Prometheus menggunakan metric `ifHCInOctets` (SNMP) dengan rate per 5 menit, dikonversi ke Mbps.
- Fungsi `getLatency($ipAddress)`: Query latency menggunakan metric `probe_icmp_duration_seconds` (Blackbox Exporter) dikonversi ke ms. Fallback ke `probe_duration_seconds` jika metric tidak ditemukan.
- Fungsi `getPacketLoss($ipAddress)`: Query packet loss menggunakan rumus `(1 - probe_success) * 100` untuk mendapatkan persentase.
- Fungsi `getAllMetrics($ipAddress)`: Mengambil bandwidth, latency, dan packet loss sekaligus dalam satu panggilan.
- Fungsi `checkConnection()`: Mengecek koneksi ke Prometheus server via endpoint `/-/healthy`.
- Mode simulasi (`PROMETHEUS_SIMULATION_MODE`) untuk testing jika blackbox exporter belum tersedia.
- Menggunakan Laravel HTTP Client dengan timeout 5 detik dan error logging.

### Pembuatan ForwardChainingService (Inference Engine) (Baru)

- File baru: `app/Services/ForwardChainingService.php`.
- Fungsi `evaluateAllRules()`: Evaluasi semua rule aktif terhadap semua device.
    - Mengambil semua rule dengan `is_active = true`.
    - Mengambil semua device dari database.
    - Untuk setiap rule, tentukan device target (all/specific).
    - Untuk setiap device, query metrics dari PrometheusService.
    - Evaluasi kondisi dengan logic AND: IF condition1 AND condition2 AND ... THEN gangguan.
    - Return array hasil deteksi lengkap dengan detail kondisi.
- Fungsi `evaluateRuleForDevice($rule, $device)`: Evaluasi satu rule untuk satu device.
- Fungsi `evaluateCondition($actualValue, $operator, $thresholdValue)`: Evaluasi kondisi berdasarkan operator (>, <, >=, <=, =).
    - Operator `=` menggunakan toleransi float comparison 0.01.
- Fungsi `evaluateSingleRule($ruleId, $deviceId)`: Evaluasi satu rule secara manual untuk testing.
- Logging untuk setiap deteksi gangguan dan error.

### Pembuatan Command Artisan dan Scheduler (Baru)

- File baru: `app/Console/Commands/EvaluateRulesCommand.php`.
    - Signature: `php artisan rules:evaluate`.
    - Menampilkan output detail: jumlah rule dicek, jumlah gangguan terdeteksi, detail kondisi yang match.
    - Return Command::SUCCESS.

- File baru: `app/Console/Commands/TestPrometheusConnection.php`.
    - Signature: `php artisan test:prometheus {--ip=}`.
    - Test koneksi ke Prometheus server.
    - Menampilkan daftar active targets dan health status.
    - Test query bandwidth, latency, packet loss untuk IP tertentu.
    - Memberikan rekomendasi jika semua metrics NULL.

- File baru: `app/Console/Commands/CreateTestData.php`.
    - Signature: `php artisan test:create-data`.
    - Membuat 3 rule testing dengan kondisi berbeda untuk validasi sistem.
    - Rule 1: IF latency > 50 ms THEN gangguan.
    - Rule 2: IF packet_loss > 5% THEN gangguan.
    - Rule 3: IF latency > 100 ms AND packet_loss > 2% THEN gangguan.

- File baru: `app/Console/Commands/CheckBlackboxTargets.php`.
    - Signature: `php artisan blackbox:check`.
    - Mengecek apakah ada target dengan job="ping".
    - Mengecek metrics blackbox yang tersedia (`probe_success`, `probe_duration_seconds`, `probe_icmp_duration_seconds`).
    - Memberikan rekomendasi query PromQL.

- File baru: `app/Console/Commands/SetupBlackboxTargets.php`.
    - Signature: `php artisan blackbox:setup`.
    - Membuat file `blackbox-targets.json` dari IP yang ada di database dan di targets.json.
    - Format JSON sesuai dengan format Prometheus file_sd_configs.

- File baru: `app/Console/Commands/ShowPrometheusMetrics.php`.
    - Signature: `php artisan prometheus:show-metrics {ip}`.
    - Menampilkan semua metrics yang tersedia di Prometheus untuk IP tertentu.
    - Mengelompokkan metrics berdasarkan nama.
    - Menampilkan nilai terkini setiap metric.
    - Memberikan rekomendasi query PromQL berdasarkan metric yang ditemukan.

- Update file `routes/console.php`.
    - Menambahkan scheduler untuk menjalankan `rules:evaluate` setiap 1 menit.
    - Konfigurasi: `withoutOverlapping()`, `runInBackground()`, append output ke `storage/logs/forward-chaining.log`.

### Integrasi Blackbox Exporter ke PrometheusTargetService (Perubahan)

- Memperbarui `app/Services/PrometheusTargetService.php`.
    - Method `addTarget()`: Sekarang menambahkan IP ke SNMP targets (targets.json) DAN Blackbox targets (blackbox-targets.json).
    - Method `removeTarget()`: Sekarang menghapus IP dari SNMP targets DAN Blackbox targets.
    - Method baru `addBlackboxTarget()`: Menambahkan IP ke file blackbox-targets.json dengan label job="ping".
    - Method baru `removeBlackboxTarget()`: Menghapus IP dari file blackbox-targets.json.
    - Method baru `addSnmpTarget()`: Memisahkan logic SNMP dari addTarget.
    - Method baru `removeSnmpTarget()`: Memisahkan logic SNMP dari removeTarget.
    - Parameter `$filePath` pada readTargets dan writeTargets agar bisa digunakan untuk kedua file.
    - Kedua file target SNMP dan Blackbox dikelola secara atomik dalam satu panggilan.

- Membuat file baru: `../prometheus/blackbox-targets.json`.
    - Format JSON array dengan label instance dan job="ping".
    - Target awal: 192.168.18.200 (device yang sudah terdaftar).
    - Auto-update setiap kali user menambah/menghapus device.

### Konfigurasi Prometheus dan Docker (Perubahan)

- Memperbarui `../prometheus/prometheus.yml`.
    - Job "ping" berubah dari `static_configs` menjadi `file_sd_configs` dengan file `/etc/prometheus/blackbox-targets.json`.
    - Refresh interval 5 detik agar perubahan target cepat terdeteksi.
    - Konfigurasi relabeling untuk ICMP probe module.

- Memperbarui `../docker-compose.yaml`.
    - Menambahkan volume mapping untuk `./prometheus/blackbox-targets.json:/etc/prometheus/blackbox-targets.json`.
    - Volume baru diperlukan agar Prometheus bisa membaca file blackbox-targets.json dari dalam container.

- Memperbaiki encoding file `blackbox-targets.json`.
    - File sebelumnya memiliki BOM (Byte Order Mark) yang menyebabkan Prometheus gagal parsing JSON.
    - Ditulis ulang dengan UTF-8 tanpa BOM menggunakan .NET encoding.

### Konfigurasi Environment (Perubahan)

- Menambahkan `PROMETHEUS_URL=http://localhost:9090` ke file `.env`.
- Menambahkan `PROMETHEUS_SIMULATION_MODE=true` ke file `.env` untuk mode simulasi latency/packet loss.
- Simulation mode dimatikan (`false`) setelah blackbox exporter berhasil terintegrasi.
- Data yang didapatkan sekarang:
    - ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ Bandwidth: Real dari SNMP Exporter (`ifHCInOctets`).
    - ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ Latency: Real dari Blackbox Exporter (`probe_icmp_duration_seconds`).
    - ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ Packet Loss: Real dari Blackbox Exporter (`probe_success`).

### Verifikasi dan Testing

- Semua migration berhasil dijalankan.
    - `php artisan migrate:fresh` - Semua tabel terbuat dengan benar.
    - Total 6 tabel: users, cache, cache_locks, jobs, job_batches, failed_jobs, devices, rules, rule_conditions.

- Test koneksi Prometheus berhasil.
    - Prometheus server UP dan dapat dijangkau.
    - 3 active targets: prometheus, snmp (192.168.18.200), ping (192.168.18.200) - Semua Health: up.

- Test query metrics dari Prometheus berhasil.
    - Bandwidth: 0.00 Mbps (data real dari SNMP).
    - Latency: ~0.01 ms (data real dari Blackbox ICMP probe).
    - Packet Loss: 0.00% (data real dari Blackbox probe_success).

- Test evaluasi Forward Chaining berhasil.
    - 3 rules dicek dengan data real dari Prometheus.
    - Sistem berjalan tanpa error.

- Test Laravel.
    - `php artisan test` - 2 test passed.

- Cek route Laravel.
    - `php artisan route:list --except-vendor` - 15 route terdaftar.

### Catatan Operasional

- **Auto-Discovery Blackbox Target**: Setiap kali user menambahkan device baru di Modul Manajemen Target Device, sistem akan otomatis menambahkan IP ke SNMP targets (untuk bandwidth) DAN Blackbox targets (untuk latency & packet loss). Prometheus akan mendeteksi perubahan dalam 5 detik via file_sd_configs.
- **Simulation Mode**: Jika blackbox exporter belum tersedia, set `PROMETHEUS_SIMULATION_MODE=true` di .env untuk menggunakan data dummy latency dan packet loss.
- **Scheduler**: Untuk menjalankan evaluasi otomatis setiap menit, jalankan `php artisan schedule:work` di terminal. Untuk production, setup Windows Task Scheduler dengan command `php artisan schedule:run`.
- **Blackbox ICMP Probe**: Blackbox exporter melakukan ICMP ping ke masing-masing target. Pastikan target dapat di-ping dari container blackbox-exporter di Docker.
- **Command Testing**: Tersedia beberapa command untuk testing: `test:prometheus`, `test:create-data`, `blackbox:check`, `prometheus:show-metrics`, `rules:evaluate`.
- **Log Evaluasi**: Output scheduler tersimpan di `storage/logs/forward-chaining.log`.

---

### Penambahan Fitur Paginasi dan Search Server-Side pada Modul Manajemen Target Device

- Menambahkan fitur **Pagination** pada tabel Manajemen Target Device.
    - Pagination membatasi tampilan maksimal 15 data per halaman.
    - Jika data lebih dari 15, akan otomatis masuk ke halaman berikutnya.
    - Komponen pagination dibuat manual tanpa menggunakan CDN atau library eksternal.

- Mengubah sistem **Search menjadi Server-Side**.
    - Search sebelumnya hanya mencari di data yang tampil di halaman aktif (client-side).
    - Search sekarang mencari di **seluruh data** termasuk di halaman pagination lain.
    - Pencarian dilakukan di kolom: Customer ID, Nama Pelanggan, IP Address, dan Model Device.
    - Parameter search dipertahankan di URL saat berpindah halaman pagination.

- Memperbarui `DeviceController@index`.
    - Mengubah query dari `Device::latest()->get()` menjadi `Device::latest()->paginate(15)`.
    - Menambahkan logic pencarian dengan kondisi `when()` untuk filter data berdasarkan keyword.
    - Menggunakan `withQueryString()` untuk mempertahankan parameter search di URL pagination.
    - Menambahkan parameter `$search` yang dikirim ke view.

- Memperbarui view `devices/index.blade.php`.
    - Menghapus search input JavaScript client-side.
    - Menambahkan form search dengan method GET yang submit ke server.
    - Menambahkan tombol "Cari" untuk melakukan pencarian.
    - Menambahkan tombol "Reset" untuk menghapus filter pencarian.
    - Menambahkan informasi jumlah data yang ditampilkan: "Menampilkan X - Y dari Z data".
    - Menambahkan komponen pagination custom dengan styling manual.
    - Pagination menampilkan tombol Previous, nomor halaman, dan Next.
    - Halaman aktif ditandai dengan background biru (#3498db) sesuai tema aplikasi.
    - Menambahkan pesan "Data tidak ditemukan untuk pencarian..." jika hasil search kosong.

- Styling pagination dibuat tanpa CDN.
    - Menggunakan CSS manual dengan warna yang konsisten dengan tema aplikasi.
    - Tombol pagination menggunakan border, padding, dan hover effect.
    - Halaman aktif memiliki background biru dan text putih.
    - Tombol disabled (Prev/Next yang tidak aktif) memiliki warna abu-abu dan cursor not-allowed.

- Memperbaiki encoding file `DeviceController.php`.
    - Menghapus BOM (Byte Order Mark) yang menyebabkan error namespace.
    - File ditulis ulang dengan UTF-8 tanpa BOM menggunakan PowerShell.

## 2026-07-23

### Perbaikan Modul Manajemen Target Device

- Memperkuat proses **Connect IP Address** pada modul Target Device.
    - Menambahkan validasi agar IP yang dicek wajib berbentuk IPv4.
    - Menambahkan pengecekan apakah extension SNMP PHP sudah aktif sebelum menjalankan `snmpget()`.
    - Mengubah penanganan error agar menangkap `Throwable`, sehingga error PHP yang bukan `Exception` tetap bisa ditangani.
    - Menambahkan response error yang lebih jelas jika SNMP belum aktif atau perangkat tidak merespons.

- Memindahkan proses pengelolaan target Prometheus ke service baru.
    - File baru: `app/Services/PrometheusTargetService.php`.
    - Service ini bertugas menambah dan menghapus IP target pada file `../prometheus/targets.json`.
    - Service juga memvalidasi struktur JSON agar file target Prometheus tidak menyebabkan warning/error saat formatnya rusak.
    - Penulisan file dibuat lebih aman dan pesan error dibuat lebih mudah dipahami.

- Menambahkan transaction pada proses create, update, dan delete device.
    - Jika update database berhasil tetapi update target Prometheus gagal, proses akan dibatalkan dan user mendapat pesan error.
    - Ini mengurangi risiko data device di database tidak sinkron dengan target Prometheus.

- Memperbaiki mekanisme verifikasi IP hasil Connect.
    - Sebelumnya session hanya menyimpan satu IP terverifikasi.
    - Sekarang session menyimpan daftar IP terverifikasi agar beberapa tab/form tidak saling menimpa status Connect.

- Memperbaiki form edit device.
    - Menambahkan field **Tanggal Pemasangan** pada halaman edit.
    - Menambahkan validasi dan update `installation_date` pada proses update device.

- Menampilkan error validasi pada halaman tambah dan edit device.
    - Error dari backend sekarang tampil di halaman, sehingga user tahu alasan data gagal disimpan atau diperbarui.
    - Form tambah device otomatis tetap terbuka jika ada error validasi.

### Pengurangan CDN dan Library Eksternal

- Menghapus penggunaan CDN dari layout utama.
    - Menghapus CDN jQuery.
    - Menghapus CDN DataTables.
    - Menghapus CDN SweetAlert.

- Mengganti DataTables dengan pencarian tabel sederhana berbasis JavaScript manual.
    - Pencarian berjalan langsung pada baris tabel device.
    - Jika hasil pencarian kosong, sistem menampilkan pesan "Data tidak ditemukan."

- Menghapus penggunaan Tailwind dari konfigurasi frontend.
    - Menghapus `@tailwindcss/vite` dan `tailwindcss` dari `package.json`.
    - Menghapus plugin Tailwind dari `vite.config.js`.
    - Mengosongkan `resources/css/app.css` dari import Tailwind.
    - Menyederhanakan `resources/views/welcome.blade.php` agar tidak lagi memakai template bawaan Laravel berbasis Tailwind/CDN.

### Penyesuaian Testing

- Menyesuaikan test feature default Laravel.
    - Test sebelumnya mengharapkan route `/` menghasilkan status `200`.
    - Route `/` pada aplikasi ini memang diarahkan ke halaman Manajemen Target Device, sehingga test diubah untuk mengecek redirect ke `devices.index`.

### Verifikasi

- Menjalankan lint PHP pada file terkait.
    - `app/Http/Controllers/DeviceController.php`
    - `app/Services/PrometheusTargetService.php`
    - `routes/web.php`
    - `tests/Feature/ExampleTest.php`

- Menjalankan pemeriksaan route Laravel.
    - `php artisan route:list --except-vendor`

- Menjalankan test Laravel.
    - `php artisan test`
    - Hasil: 2 test passed.

- Menjalankan build frontend.
    - `npm.cmd install`
    - `npm.cmd run build`
    - Hasil: build sukses.

### Catatan Operasional

- Fitur Connect IP Address tetap membutuhkan extension SNMP PHP aktif di environment.
- Jika extension SNMP belum aktif, sistem sekarang menampilkan pesan error yang jelas dan tidak langsung fatal error.
- File `package-lock.json` dibuat setelah menjalankan `npm.cmd install`.
