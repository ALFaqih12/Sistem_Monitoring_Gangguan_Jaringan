<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use App\Models\RuleCondition;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class RuleController extends Controller
{
    /**
     * Menampilkan halaman utama dengan tabel list rule dan pagination.
     */
    public function index(Request $request)
    {
        // Ambil keyword pencarian dari request (jika ada)
        $search = $request->input('search');

        // Query data rule dengan pagination 15 per halaman
        $rules = Rule::query()
            ->with(['targetDevice', 'conditions']) // Eager loading relasi
            ->when($search, function ($query, $search) {
                // Pencarian server-side di kolom code dan name
                return $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString(); // Mempertahankan parameter search di URL pagination

        // Migrasi Blade -> Inertia: halaman rule dirender Vue Rules/Index.vue.
        // Props (rules paginator + search) dikirim sama; withQueryString() tetap.
        return Inertia::render('Rules/Index', compact('rules', 'search'));
    }

    /**
     * Menampilkan halaman form untuk membuat rule baru.
     */
    public function create()
    {
        // Ambil semua device untuk dropdown target device
        $devices = Device::orderBy('customer_name', 'asc')->get();

        // Migrasi Blade -> Inertia: form create rule dirender Vue Rules/Create.vue.
        return Inertia::render('Rules/Create', compact('devices'));
    }

    /**
     * Validasi duplicate parameters pada kondisi rule.
     * Setiap parameter hanya boleh muncul maksimal 1 kali dalam 1 rule.
     * 
     * Contoh:
     * - bandwidth > 100 AND latency > 50     → VALID (berbeda parameter)
     * - bandwidth > 100 AND bandwidth < 50   → INVALID (duplicate parameter bandwidth)
     * 
     * @param array $conditions Array of conditions dari form
     * @return array|null Return error message jika ada duplicate, null jika valid
     */
    private function validateDuplicateParameters(array $conditions): ?array
    {
        // Count parameter occurrences
        $paramCounts = collect($conditions)
            ->pluck('parameter')
            ->countBy();
        
        // Check for duplicates
        $duplicates = $paramCounts->filter(function ($count) {
            return $count > 1;
        });
        
        if ($duplicates->isNotEmpty()) {
            $duplicateList = $duplicates
                ->map(function ($count, $param) {
                    $label = match($param) {
                        'bandwidth' => 'Bandwidth',
                        'latency' => 'Latency',
                        'packet_loss' => 'Packet Loss',
                        default => $param,
                    };
                    return "{$label} ({$count}x)";
                })
                ->implode(', ');
            
            return [
                'conditions' => "Setiap parameter hanya boleh muncul maksimal 1 kali dalam rule yang sama. Parameter berulang: {$duplicateList}"
            ];
        }
        
        return null;
    }

    /**
     * Validasi logical contradiction pada kondisi rule.
     * Deteksi kondisi yang bertentangan secara logika dan tidak mungkin terpenuhi bersamaan.
     * 
     * Contoh contradiction:
     * - bandwidth > 100 AND bandwidth < 50 (tidak mungkin)
     * - latency = 50 AND latency > 50 (tidak mungkin)
     * - packet_loss = 5 AND packet_loss = 10 (tidak mungkin)
     * 
     * @param array $conditions Array of conditions dari form
     * @return array|null Return error message jika ada contradiction, null jika valid
     */
    private function validateLogicalContradiction(array $conditions): ?array
    {
        // Group conditions by parameter untuk check contradiction pada parameter yang sama
        $groupedByParam = collect($conditions)->groupBy('parameter');
        
        foreach ($groupedByParam as $parameter => $paramConditions) {
            // Skip jika hanya ada 1 kondisi untuk parameter ini
            if ($paramConditions->count() < 2) {
                continue;
            }
            
            // Check setiap kombinasi kondisi untuk parameter yang sama
            $condArray = $paramConditions->values()->toArray();
            for ($i = 0; $i < count($condArray); $i++) {
                for ($j = $i + 1; $j < count($condArray); $j++) {
                    $cond1 = $condArray[$i];
                    $cond2 = $condArray[$j];
                    
                    $contradiction = $this->checkContradiction($cond1, $cond2, $parameter);
                    if ($contradiction) {
                        return ['conditions' => $contradiction];
                    }
                }
            }
        }
        
        return null; // No contradiction found
    }

    /**
     * Check apakah 2 kondisi bertentangan secara logika.
     * 
     * @param array $cond1 Kondisi pertama
     * @param array $cond2 Kondisi kedua
     * @param string $parameter Nama parameter (untuk error message)
     * @return string|null Error message jika contradiction, null jika valid
     */
    private function checkContradiction(array $cond1, array $cond2, string $parameter): ?string
    {
        $op1 = $cond1['operator'];
        $op2 = $cond2['operator'];
        $val1 = (float) $cond1['value'];
        $val2 = (float) $cond2['value'];
        
        // Get unit for better error message
        $unit = match($parameter) {
            'bandwidth' => 'Mbps',
            'latency' => 'ms',
            'packet_loss' => '%',
            default => '',
        };
        
        $paramLabel = match($parameter) {
            'bandwidth' => 'Bandwidth',
            'latency' => 'Latency',
            'packet_loss' => 'Packet Loss',
            default => $parameter,
        };
        
        // Rule 1: Opposite range contradiction (> vs <, >= vs <=)
        if (($op1 == '>' || $op1 == '>=') && ($op2 == '<' || $op2 == '<=')) {
            if ($val1 >= $val2) {
                return "Kondisi bertentangan pada {$paramLabel}: tidak mungkin {$op1} {$val1} {$unit} DAN {$op2} {$val2} {$unit} secara bersamaan.";
            }
        }
        
        // Rule 1 (reverse): Check jika urutan terbalik
        if (($op1 == '<' || $op1 == '<=') && ($op2 == '>' || $op2 == '>=')) {
            if ($val2 >= $val1) {
                return "Kondisi bertentangan pada {$paramLabel}: tidak mungkin {$op1} {$val1} {$unit} DAN {$op2} {$val2} {$unit} secara bersamaan.";
            }
        }
        
        // Rule 2: Equality with greater
        if ($op1 == '=' && ($op2 == '>' || $op2 == '>=')) {
            if ($val1 <= $val2) {
                return "Kondisi bertentangan pada {$paramLabel}: nilai tidak bisa = {$val1} {$unit} dan {$op2} {$val2} {$unit} bersamaan.";
            }
        }
        
        // Rule 2 (reverse)
        if (($op1 == '>' || $op1 == '>=') && $op2 == '=') {
            if ($val2 <= $val1) {
                return "Kondisi bertentangan pada {$paramLabel}: nilai tidak bisa {$op1} {$val1} {$unit} dan = {$val2} {$unit} bersamaan.";
            }
        }
        
        // Rule 3: Equality with less
        if ($op1 == '=' && ($op2 == '<' || $op2 == '<=')) {
            if ($val1 >= $val2) {
                return "Kondisi bertentangan pada {$paramLabel}: nilai tidak bisa = {$val1} {$unit} dan {$op2} {$val2} {$unit} bersamaan.";
            }
        }
        
        // Rule 3 (reverse)
        if (($op1 == '<' || $op1 == '<=') && $op2 == '=') {
            if ($val2 >= $val1) {
                return "Kondisi bertentangan pada {$paramLabel}: nilai tidak bisa {$op1} {$val1} {$unit} dan = {$val2} {$unit} bersamaan.";
            }
        }
        
        // Rule 4: Different equality values
        if ($op1 == '=' && $op2 == '=') {
            if (abs($val1 - $val2) > 0.01) { // Use epsilon for float comparison
                return "Kondisi bertentangan pada {$paramLabel}: nilai tidak bisa = {$val1} {$unit} dan = {$val2} {$unit} bersamaan.";
            }
        }
        
        return null; // No contradiction
    }

    /**
     * Menyimpan rule baru beserta kondisi-kondisinya ke database.
     */
    public function store(Request $request)
    {
        // Validasi input form
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_type' => 'required|in:specific,all',
            'target_device_id' => 'required_if:target_type,specific|nullable|exists:devices,id',
            'conditions' => 'required|array|min:1', // Minimal 1 kondisi
            'conditions.*.parameter' => 'required|in:bandwidth,latency,packet_loss',
            'conditions.*.operator' => 'required|in:>,<,>=,<=,=',
            'conditions.*.value' => 'required|numeric|min:0',
        ]);

        // Validasi duplicate parameters (hanya boleh 1 kondisi per parameter)
        $duplicateError = $this->validateDuplicateParameters($validated['conditions']);
        if ($duplicateError) {
            return back()->withErrors($duplicateError)->withInput();
        }

        // Validasi logical contradiction pada kondisi
        // Deteksi kondisi yang bertentangan dan tidak mungkin terpenuhi bersamaan
        $contradictionError = $this->validateLogicalContradiction($validated['conditions']);
        if ($contradictionError) {
            return back()->withErrors($contradictionError)->withInput();
        }

        try {
            // Transaksi untuk menjaga konsistensi data rule dan conditions
            DB::transaction(function () use ($validated) {
                // Buat rule baru (code akan auto-generate di model)
                $rule = Rule::create([
                    'name' => $validated['name'],
                    'target_type' => $validated['target_type'],
                    'target_device_id' => $validated['target_type'] === 'specific' ? $validated['target_device_id'] : null,
                    'is_active' => true, // Default aktif saat pertama dibuat
                ]);

                // Simpan semua kondisi yang terkait dengan rule ini
                foreach ($validated['conditions'] as $condition) {
                    RuleCondition::create([
                        'rule_id' => $rule->id,
                        'parameter' => $condition['parameter'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'],
                    ]);
                }
            });
        } catch (Throwable $e) {
            return back()
                ->withErrors(['rule' => 'Data rule gagal disimpan: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('rules.index')->with('success', 'Rule berhasil ditambahkan!');
    }

    /**
     * Menampilkan halaman form untuk edit rule.
     */
    public function edit(Rule $rule)
    {
        // Load relasi conditions untuk ditampilkan di form edit (sama seperti Blade semula).
        $rule->load('conditions');

        // Ambil semua device untuk dropdown target device
        $devices = Device::orderBy('customer_name', 'asc')->get();

        // Migrasi Blade -> Inertia: form edit rule dirender Vue Rules/Edit.vue.
        return Inertia::render('Rules/Edit', compact('rule', 'devices'));
    }

    /**
     * Menyimpan perubahan rule dan kondisi-kondisinya ke database.
     */
    public function update(Request $request, Rule $rule)
    {
        // Validasi input form
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_type' => 'required|in:specific,all',
            'target_device_id' => 'required_if:target_type,specific|nullable|exists:devices,id',
            'conditions' => 'required|array|min:1', // Minimal 1 kondisi
            'conditions.*.parameter' => 'required|in:bandwidth,latency,packet_loss',
            'conditions.*.operator' => 'required|in:>,<,>=,<=,=',
            'conditions.*.value' => 'required|numeric|min:0',
        ]);

        // Validasi duplicate parameters (hanya boleh 1 kondisi per parameter)
        $duplicateError = $this->validateDuplicateParameters($validated['conditions']);
        if ($duplicateError) {
            return back()->withErrors($duplicateError)->withInput();
        }

        // Validasi logical contradiction pada kondisi
        // Deteksi kondisi yang bertentangan dan tidak mungkin terpenuhi bersamaan
        $contradictionError = $this->validateLogicalContradiction($validated['conditions']);
        if ($contradictionError) {
            return back()->withErrors($contradictionError)->withInput();
        }

        try {
            // Transaksi untuk menjaga konsistensi data rule dan conditions
            DB::transaction(function () use ($rule, $validated) {
                // Update data rule
                $rule->update([
                    'name' => $validated['name'],
                    'target_type' => $validated['target_type'],
                    'target_device_id' => $validated['target_type'] === 'specific' ? $validated['target_device_id'] : null,
                ]);

                // Hapus semua kondisi lama, lalu simpan kondisi baru
                $rule->conditions()->delete();

                // Simpan semua kondisi baru
                foreach ($validated['conditions'] as $condition) {
                    RuleCondition::create([
                        'rule_id' => $rule->id,
                        'parameter' => $condition['parameter'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'],
                    ]);
                }
            });
        } catch (Throwable $e) {
            return back()
                ->withErrors(['rule' => 'Data rule gagal diperbarui: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('rules.index')->with('success', 'Rule "' . $rule->name . '" berhasil diperbarui!');
    }

    /**
     * Toggle status aktif/nonaktif rule via AJAX.
     */
    public function toggle(Rule $rule)
    {
        try {
            // Toggle status is_active
            $rule->is_active = !$rule->is_active;
            $rule->save();

            return response()->json([
                'success' => true,
                'is_active' => $rule->is_active,
                'message' => 'Status rule berhasil diubah menjadi ' . ($rule->is_active ? 'AKTIF' : 'NONAKTIF'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus rule beserta semua kondisinya dari database.
     */
    public function destroy(Rule $rule)
    {
        $name = $rule->name;

        try {
            // Hapus rule (kondisi akan otomatis terhapus karena cascade di migration)
            $rule->delete();
        } catch (Throwable $e) {
            return back()->withErrors(['rule' => 'Data rule gagal dihapus: ' . $e->getMessage()]);
        }

        return redirect()->route('rules.index')->with('success', 'Rule "' . $name . '" berhasil dihapus!');
    }
}