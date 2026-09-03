# Issue #5 Manual Testing Guide

## Test Cases untuk Validasi Logical Contradiction

### Test Case 1: Opposite Range Contradiction (INVALID)
**Scenario:** User membuat rule dengan kondisi yang bertentangan
- Kondisi 1: bandwidth > 100 Mbps
- Kondisi 2: bandwidth < 50 Mbps

**Expected Result:** 
- Form submission FAILED
- Error message: "Kondisi bertentangan pada Bandwidth: tidak mungkin > 100 Mbps DAN < 50 Mbps secara bersamaan."

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Contradiction 1
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = bandwidth, Operator = <, Value = 50
7. Klik "Simpan Rule"
8. **Verify:** Error message muncul ❌

---

### Test Case 2: Valid Range (VALID)
**Scenario:** User membuat rule dengan range yang valid
- Kondisi 1: bandwidth > 50 Mbps
- Kondisi 2: bandwidth < 100 Mbps

**Expected Result:**
- Form submission SUCCESS
- Rule tersimpan dengan kedua kondisi
- Message: "Rule berhasil ditambahkan!"

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Valid Range
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 50
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = bandwidth, Operator = <, Value = 100
7. Klik "Simpan Rule"
8. **Verify:** Rule berhasil disimpan, redirect ke /rules ✅

---

### Test Case 3: Equality with Greater (INVALID)
**Scenario:** User membuat rule dengan equality dan greater
- Kondisi 1: latency = 50 ms
- Kondisi 2: latency > 50 ms

**Expected Result:**
- Form submission FAILED
- Error message: "Kondisi bertentangan pada Latency: nilai tidak bisa = 50 ms dan > 50 ms bersamaan."

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Equality Greater
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = latency, Operator = =, Value = 50
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = latency, Operator = >, Value = 50
7. Klik "Simpan Rule"
8. **Verify:** Error message muncul ❌

---

### Test Case 4: Multiple Different Equality (INVALID)
**Scenario:** User membuat rule dengan 2 equality values berbeda
- Kondisi 1: packet_loss = 5%
- Kondisi 2: packet_loss = 10%

**Expected Result:**
- Form submission FAILED
- Error message: "Kondisi bertentangan pada Packet Loss: nilai tidak bisa = 5% dan = 10% bersamaan."

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Multiple Equality
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = packet_loss, Operator = =, Value = 5
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = packet_loss, Operator = =, Value = 10
7. Klik "Simpan Rule"
8. **Verify:** Error message muncul ❌

---

### Test Case 5: Different Parameters (VALID)
**Scenario:** User membuat rule dengan 2 parameter berbeda (tidak ada contradiction)
- Kondisi 1: bandwidth > 100 Mbps
- Kondisi 2: latency > 50 ms

**Expected Result:**
- Form submission SUCCESS
- Rule tersimpan

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Different Params
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = latency, Operator = >, Value = 50
7. Klik "Simpan Rule"
8. **Verify:** Rule berhasil disimpan ✅

---

### Test Case 6: Boundary Values (VALID)
**Scenario:** User membuat rule dengan boundary values yang valid
- Kondisi 1: bandwidth >= 100 Mbps
- Kondisi 2: bandwidth <= 100 Mbps

**Expected Result:**
- Form submission SUCCESS (valid: x = 100 memenuhi kedua kondisi)
- Rule tersimpan

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Boundary
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = bandwidth, Operator = >=, Value = 100
5. Klik "+ Tambah Kondisi"
6. Kondisi 2: Parameter = bandwidth, Operator = <=, Value = 100
7. Klik "Simpan Rule"
8. **Verify:** Rule berhasil disimpan ✅

---

### Test Case 7: Edit Rule dengan Contradiction (INVALID)
**Scenario:** User edit existing rule dan ubah kondisi menjadi bertentangan
**Prerequisites:** Ada rule existing dengan kondisi valid

**Steps:**
1. Buka /rules
2. Klik Edit pada rule existing
3. Ubah Kondisi 1 menjadi: bandwidth > 100
4. Ubah Kondisi 2 menjadi: bandwidth < 50
5. Klik "Simpan Perubahan"
6. **Verify:** Error message muncul ❌

---

### Test Case 8: No Regression - Normal Flow (VALID)
**Scenario:** Pastikan validasi tidak break normal flow
- Kondisi 1: latency > 50 ms

**Expected Result:**
- Form submission SUCCESS
- Single condition rule berhasil disimpan

**Steps:**
1. Buka http://localhost:8000/rules/create
2. Isi "Nama Rule": Test Single Condition
3. Pilih "Target Device": All Devices
4. Kondisi 1: Parameter = latency, Operator = >, Value = 50
5. Klik "Simpan Rule"
6. **Verify:** Rule berhasil disimpan ✅

---

## Summary

| Test Case | Type | Expected | Status |
|-----------|------|----------|--------|
| 1 | Opposite Range | ❌ ERROR | Pending |
| 2 | Valid Range | ✅ OK | Pending |
| 3 | Equality+Greater | ❌ ERROR | Pending |
| 4 | Multiple Equality | ❌ ERROR | Pending |
| 5 | Different Params | ✅ OK | Pending |
| 6 | Boundary Values | ✅ OK | Pending |
| 7 | Edit Contradiction | ❌ ERROR | Pending |
| 8 | No Regression | ✅ OK | Pending |

Total: 8 test cases (4 should fail, 4 should pass)
