<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use App\Models\RuleCondition;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('rules.index', compact('rules', 'search'));
    }

    /**
     * Menampilkan halaman form untuk membuat rule baru.
     */
    public function create()
    {
        // Ambil semua device untuk dropdown target device
        $devices = Device::orderBy('customer_name', 'asc')->get();

        return view('rules.create', compact('devices'));
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
        // Load relasi conditions untuk ditampilkan di form edit
        $rule->load('conditions');

        // Ambil semua device untuk dropdown target device
        $devices = Device::orderBy('customer_name', 'asc')->get();

        return view('rules.edit', compact('rule', 'devices'));
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