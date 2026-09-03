# ISSUE #5 - Duplicate Parameters Testing Guide

## Test Cases untuk Validasi Duplicate Parameters

### Test Case 1: Duplicate Parameter Rejection (INVALID) ❌
**Scenario:** User membuat rule dengan 2 kondisi parameter yang sama

**Input:**
- Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
- Kondisi 2: Parameter = bandwidth, Operator = <, Value = 50

**Expected Result:**
- ❌ Form submission REJECTED
- Error message: "Setiap parameter hanya boleh muncul maksimal 1 kali dalam rule yang sama. Parameter berulang: Bandwidth (2x)"
- Form tetap terbuka dengan data yang sudah diisi
- **Rule TIDAK tersimpan ke database**

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Duplicate Parameter
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = bandwidth, Operator = <, Value = 50
7. Klik "Simpan Rule"
8. **Verify:** Error message muncul, rule tidak disimpan ❌

---

### Test Case 2: Triple Duplicate Parameter Rejection (INVALID) ❌
**Scenario:** User membuat rule dengan 3 kondisi parameter yang sama

**Input:**
- Kondisi 1: Parameter = packet_loss, Operator = =, Value = 5
- Kondisi 2: Parameter = packet_loss, Operator = >, Value = 3
- Kondisi 3: Parameter = packet_loss, Operator = >, Value = 10

**Expected Result:**
- ❌ Form submission REJECTED
- Error message: "Setiap parameter hanya boleh muncul maksimal 1 kali dalam rule yang sama. Parameter berulang: Packet Loss (3x)"

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Triple Duplicate
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = packet_loss, Operator = =, Value = 5
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = packet_loss, Operator = >, Value = 3
7. Klik "+ Tambah Kondisi"
8. Kondisi 3: Parameter = packet_loss, Operator = >, Value = 10
9. Klik "Simpan Rule"
10. **Verify:** Error message muncul ❌

---

### Test Case 3: No Duplicate Acceptance (VALID) ✅
**Scenario:** User membuat rule dengan parameter yang berbeda-beda

**Input:**
- Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
- Kondisi 2: Parameter = latency, Operator = >, Value = 50
- Kondisi 3: Parameter = packet_loss, Operator = >, Value = 5

**Expected Result:**
- ✅ Form submission SUCCESS
- Message: "Rule berhasil ditambahkan!"
- Rule muncul di halaman list dengan 3 kondisi

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test No Duplicate
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = latency, Operator = >, Value = 50
7. Klik "+ Tambah Kondisi"
8. Kondisi 3: Parameter = packet_loss, Operator = >, Value = 5
9. Klik "Simpan Rule"
10. **Verify:** Rule berhasil disimpan ✅

---

### Test Case 4: Single Condition Acceptance (VALID) ✅
**Scenario:** User membuat rule dengan hanya 1 kondisi

**Input:**
- Kondisi 1: Parameter = latency, Operator = >, Value = 50

**Expected Result:**
- ✅ Form submission SUCCESS
- Rule tersimpan dengan 1 kondisi

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Single Condition
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = latency, Operator = >, Value = 50
5. Klik "Simpan Rule"
6. **Verify:** Rule berhasil disimpan ✅

---

### Test Case 5: Edit Rule with Duplicate Parameter (INVALID) ❌
**Scenario:** User edit rule existing dan ubah menjadi duplicate

**Prerequisites:**
- Ada rule existing dengan kondisi: latency > 50 ms

**Steps:**
1. Buka /rules
2. Klik Edit pada rule existing
3. Hapus kondisi pertama
4. Klik "+ Tambah Kondisi" 2x
5. Kondisi 1: Parameter = latency, Operator = >, Value = 50
6. Kondisi 2: Parameter = latency, Operator = <, Value = 100
7. Klik "Simpan Perubahan"
8. **Verify:** Error message muncul, rule tidak diupdate ❌

---

### Test Case 6: Edit Rule without Duplicate (VALID) ✅
**Scenario:** User edit rule existing tetap valid

**Prerequisites:**
- Ada rule existing dengan kondisi: bandwidth > 100 Mbps

**Steps:**
1. Buka /rules
2. Klik Edit pada rule existing
3. Tambah kondisi baru: Parameter = latency, Operator = >, Value = 50
4. Klik "Simpan Perubahan"
5. **Verify:** Rule berhasil diupdate, 2 kondisi dengan parameter berbeda ✅

---

## Summary

| Test Case | Type | Expected | Status |
|-----------|------|----------|--------|
| 1 | Duplicate (2x) | ❌ ERROR | Pending |
| 2 | Triple Duplicate | ❌ ERROR | Pending |
| 3 | No Duplicate | ✅ OK | Pending |
| 4 | Single Condition | ✅ OK | Pending |
| 5 | Edit with Duplicate | ❌ ERROR | Pending |
| 6 | Edit without Duplicate | ✅ OK | Pending |

Total: 6 test cases (2 should fail, 4 should pass)

---

## ⚠️ IMPORTANT NOTE

**Rules existing di database yang sudah punya duplicate parameters tidak akan divalidasi ulang.** Validasi hanya berlaku untuk:
- **Create rule baru** → pastikan tidak ada duplicate
- **Edit/Update rule** → pastikan kondisi baru tidak ada duplicate

Existing rules dengan duplicate parameters akan tetap berfungsi (hanya data quality issue, bukan functional bug).
