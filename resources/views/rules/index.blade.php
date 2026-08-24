<!-- Panggil Master Layout -->
@extends('layouts.app')

<!-- Tambahkan CSS khusus halaman ini ke dalam stack 'styles' di master layout -->
@push('styles')
<style>
    /* CSS Khusus Table Rules */
    h2 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s; }
    .btn-primary { background-color: #3498db; color: white; text-decoration: none;}
    .btn-primary:hover { background-color: #2980b9; }
    
    .table-panel { background: white; padding: 22px; border-radius: 6px; box-shadow: 0 6px 18px rgba(44,62,80,0.08); margin-top: 20px; border: 1px solid #e8edf3; }
    .table-panel h3 { margin-bottom: 16px; color: #2c3e50; font-size: 18px; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; }
    th, td { text-align: left; padding: 13px 14px; border-bottom: 1px solid #e8edf3; font-size: 14px; vertical-align: middle; }
    th { background-color: #2c3e50; color: white; font-size: 13px; text-transform: uppercase; letter-spacing: 0; white-space: nowrap; }
    th:first-child { border-top-left-radius: 6px; }
    th:last-child { border-top-right-radius: 6px; }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:hover { background-color: #f8fbff; }
    tbody tr:last-child td { border-bottom: none; }
    
    .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    .alert-error { background-color: #fdecea; color: #922b21; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    
    .badge-code { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 13px; background-color: #3498db; color: white; font-weight: 700; font-family: 'Courier New', monospace; }
    .badge-target { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 12px; background-color: #eef5fb; color: #2c3e50; border: 1px solid #d7e7f5; }
    .badge-target-warning { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 12px; background-color: #fdecea; color: #922b21; border: 1px solid #f5c6cb; font-weight: 600; }
    
    .btn-warning { background-color: #f1c40f; color: #333; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; }
    .btn-warning:hover { background-color: #f39c12; }
    
    .btn-danger { background-color: #e74c3c; color: white; border: none; display: inline-flex; align-items: center; justify-content: center; min-width: 64px; padding: 7px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
    .btn-danger:hover { background-color: #c0392b; }
    
    .action-buttons { display: flex; align-items: center; gap: 8px; }
    .action-buttons form { margin: 0; }
    
    .table-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .search-form { display: flex; gap: 8px; align-items: center; }
    .table-search { width: 280px; max-width: 100%; padding: 9px 10px; border: 1px solid #cfd8e3; border-radius: 4px; }
    .btn-search { background-color: #3498db; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
    .btn-search:hover { background-color: #2980b9; }
    .btn-clear { background-color: #95a5a6; color: white; padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; }
    .btn-clear:hover { background-color: #7f8c8d; }
    .empty-row { text-align: center; color: #607080; padding: 18px; }
    
    /* CSS untuk Pagination */
    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e8edf3; }
    .pagination-info { color: #607080; font-size: 13px; }
    .pagination { display: flex; list-style: none; gap: 5px; margin: 0; padding: 0; }
    .pagination li { display: inline-block; }
    .pagination a, .pagination span { display: inline-block; padding: 8px 12px; border: 1px solid #cfd8e3; border-radius: 4px; color: #2c3e50; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.2s; }
    .pagination a:hover { background-color: #3498db; color: white; border-color: #3498db; }
    .pagination .active span { background-color: #3498db; color: white; border-color: #3498db; }
    .pagination .disabled span { color: #95a5a6; cursor: not-allowed; border-color: #e8edf3; }
    .pagination .disabled span:hover { background-color: transparent; color: #95a5a6; }

    /* Toggle Switch CSS */
    .toggle-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #95a5a6; border-radius: 24px; transition: 0.3s; }
    .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: 0.3s; }
    input:checked + .toggle-slider { background-color: #2ecc71; }
    input:checked + .toggle-slider:before { transform: translateX(26px); }
    .toggle-slider:hover { opacity: 0.8; }

    .conditions-list { font-size: 13px; color: #555; line-height: 1.6; }
    .conditions-list div { margin-bottom: 3px; }
</style>
@endpush

<!-- Masukkan HTML konten ke dalam area 'content' di master layout -->
@section('content')
<div>
    <h2>Rule Forward Chaining</h2>

    @if(session('success'))
        <div class="alert-success alert-flash" id="successAlert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('rules.create') }}" class="btn btn-primary">+ Add New Rule</a>

    <div class="table-panel">
        <h3>Daftar Rule</h3>

        <!-- Toolbar Search dengan Form Submit Server-Side -->
        <div class="table-toolbar">
            <div class="pagination-info">
                Menampilkan {{ $rules->firstItem() ?? 0 }} - {{ $rules->lastItem() ?? 0 }} dari {{ $rules->total() }} rule
            </div>
            <form method="GET" action="{{ route('rules.index') }}" class="search-form">
                <input type="text" name="search" class="table-search" placeholder="Cari Code atau Nama Rule..." value="{{ $search ?? '' }}">
                <button type="submit" class="btn-search">Cari</button>
                @if($search)
                    <a href="{{ route('rules.index') }}" class="btn-clear">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table id="ruleTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nama Rule</th>
                        <th>Target Device</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td><span class="badge-code">{{ $rule->code }}</span></td>
                            <td style="font-weight: 600; color: #2c3e50;">{{ $rule->name }}</td>
                            <td>
                                @if($rule->target_type === 'all')
                                    <span class="badge-target">All Devices</span>
                                @elseif($rule->targetDevice)
                                    <span class="badge-target">{{ $rule->targetDevice->ip_address }}</span>
                                @else
                                    <span class="badge-target-warning">⚠ Device telah dihapus</span>
                                @endif
                            </td>
                            <td>
                                <div class="conditions-list">
                                    @foreach($rule->conditions as $index => $condition)
                                        <div>
                                            @if($index > 0) <strong>AND</strong> @endif
                                            {{ $condition->parameter_label }} {{ $condition->operator }} {{ $condition->value }} {{ $condition->unit }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <!-- Toggle Switch untuk ON/OFF -->
                                <label class="toggle-switch">
                                    <input type="checkbox" class="toggle-rule" data-rule-id="{{ $rule->id }}" data-toggle-url="{{ route('rules.toggle', $rule->id) }}" {{ $rule->is_active ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Tombol Edit -->
                                    <a href="{{ route('rules.edit', $rule->id) }}" class="btn-warning">Edit</a>
                                    
                                    <!-- Tombol Delete -->
                                    <form action="{{ route('rules.destroy', $rule->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus rule ini? Semua kondisi yang terkait juga akan terhapus.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-row">
                                @if($search)
                                    Data tidak ditemukan untuk pencarian "{{ $search }}".
                                @else
                                    Belum ada rule. Silakan tambahkan rule baru.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Komponen Pagination -->
        @if($rules->hasPages())
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Halaman {{ $rules->currentPage() }} dari {{ $rules->lastPage() }}
                </div>
                <ul class="pagination">
                    {{-- Previous Page Link --}}
                    @if ($rules->onFirstPage())
                        <li class="disabled"><span>&laquo; Prev</span></li>
                    @else
                        <li><a href="{{ $rules->previousPageUrl() }}">&laquo; Prev</a></li>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($rules->getUrlRange(1, $rules->lastPage()) as $page => $url)
                        @if ($page == $rules->currentPage())
                            <li class="active"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($rules->hasMorePages())
                        <li><a href="{{ $rules->nextPageUrl() }}">Next &raquo;</a></li>
                    @else
                        <li class="disabled"><span>Next &raquo;</span></li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection

<!-- Masukkan Script khusus halaman ini ke bagian paling bawah -->
@push('scripts')
<script>
    // Auto-hide alert success setelah 3 detik
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.opacity = '0';
        }, 3000);
    }

    // Handle toggle switch untuk mengaktifkan/nonaktifkan rule via AJAX
    const toggleSwitches = document.querySelectorAll('.toggle-rule');
    
    toggleSwitches.forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const ruleId = this.getAttribute('data-rule-id');
            const isChecked = this.checked;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Kirim request AJAX ke server
            fetch(this.getAttribute('data-toggle-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Gagal mengubah status rule.');
                }

                return data;
            })
            .then(data => {
                if (data.success) {
                    // Tampilkan notifikasi sukses (opsional)
                    console.log(data.message);
                } else {
                    throw new Error(data.message || 'Gagal mengubah status rule.');
                }
            })
            .catch(error => {
                // Kembalikan toggle ke posisi semula jika gagal
                toggle.checked = !isChecked;
                alert('Error: ' + error.message);
            });
        });
    });
</script>
@endpush

