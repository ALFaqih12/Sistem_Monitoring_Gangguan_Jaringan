<!-- Panggil Master Layout -->
@extends('layouts.app')

<!-- Tambahkan CSS khusus halaman ini -->
@push('styles')
<style>
    h2 { margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; transition: 0.2s; }
    .btn-success { background-color: #2ecc71; color: white; width: 100%; margin-top: 15px; }
    .btn-success:hover { background-color: #27ae60; }
    .btn-secondary { background-color: #95a5a6; color: white; text-decoration: none; display: inline-block; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;}
    .btn-secondary:hover { background-color: #7f8c8d; }
    .btn-add-condition { background-color: #3498db; color: white; padding: 8px 12px; font-size: 13px; margin-top: 10px; }
    .btn-add-condition:hover { background-color: #2980b9; }
    .btn-remove-condition { background-color: #e74c3c; color: white; padding: 6px 10px; font-size: 12px; }
    .btn-remove-condition:hover { background-color: #c0392b; }
    
    .form-panel { background: white; padding: 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    .form-group-inline { display: flex; gap: 10px; align-items: flex-start; }
    .form-group-inline > div { flex: 1; }
    label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
    input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    input:disabled, input[readonly] { background-color: #eaeded; }
    .alert-error { background-color: #fdecea; color: #922b21; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
    
    .condition-item { padding: 15px; background-color: #f8f9fa; border: 1px solid #e8edf3; border-radius: 4px; margin-bottom: 10px; position: relative; }
    .condition-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .condition-label { font-weight: 600; color: #2c3e50; font-size: 14px; }
    .conditions-wrapper { margin-top: 10px; }
</style>
@endpush

<!-- Konten Halaman -->
@section('content')
<div>
    <h2>Edit Rule: {{ $rule->name }}</h2>
    
    <a href="{{ route('rules.index') }}" class="btn-secondary">← Kembali</a>

    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="form-panel">
        <form action="{{ route('rules.update', $rule->id) }}" method="POST" id="ruleForm">
            @csrf
            @method('PUT')
            
            <div class="form-group">
                <label>Kode Rule</label>
                <input type="text" value="{{ $rule->code }}" readonly>
            </div>

            <div class="form-group">
                <label>Nama Rule <span style="color: red;">*</span></label>
                <input type="text" name="name" value="{{ old('name', $rule->name) }}" required placeholder="Contoh: Gangguan Bandwidth Tinggi">
            </div>

            <div class="form-group">
                <label>Target Device <span style="color: red;">*</span></label>
                <select name="target_type" id="targetType" required>
                    <option value="all" {{ old('target_type', $rule->target_type) === 'all' ? 'selected' : '' }}>All Devices</option>
                    <option value="specific" {{ old('target_type', $rule->target_type) === 'specific' ? 'selected' : '' }}>Specific Device</option>
                </select>
            </div>

            <div class="form-group" id="deviceSelectGroup" style="display: {{ old('target_type', $rule->target_type) === 'specific' ? 'block' : 'none' }};">
                <label>Pilih Device <span style="color: red;">*</span></label>
                <select name="target_device_id" id="targetDeviceId">
                    <option value="">-- Pilih Device --</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ old('target_device_id', $rule->target_device_id) == $device->id ? 'selected' : '' }}>
                            {{ $device->ip_address }} - {{ $device->customer_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Kondisi Rule (IF Condition) <span style="color: red;">*</span></label>
                <small style="color: #607080; display: block; margin-bottom: 10px;">
                    Minimal 1 kondisi. Jika lebih dari 1, kondisi akan dihubungkan dengan AND.
                </small>
                
                <div class="conditions-wrapper" id="conditionsWrapper">
                    @foreach($rule->conditions as $index => $condition)
                        <div class="condition-item">
                            <div class="condition-header">
                                <span class="condition-label">Kondisi {{ $index + 1 }}</span>
                                @if($index > 0)
                                    <button type="button" class="btn btn-remove-condition" onclick="removeCondition(this)">✕ Hapus</button>
                                @endif
                            </div>
                            <div class="form-group-inline">
                                <div>
                                    <label>Parameter</label>
                                    <select name="conditions[{{ $index }}][parameter]" class="condition-parameter" required onchange="updateUnitLabel(this)">
                                        <option value="bandwidth" {{ $condition->parameter === 'bandwidth' ? 'selected' : '' }}>Bandwidth</option>
                                        <option value="latency" {{ $condition->parameter === 'latency' ? 'selected' : '' }}>Latency</option>
                                        <option value="packet_loss" {{ $condition->parameter === 'packet_loss' ? 'selected' : '' }}>Packet Loss</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Operator</label>
                                    <select name="conditions[{{ $index }}][operator]" required>
                                        <option value=">" {{ $condition->operator === '>' ? 'selected' : '' }}>></option>
                                        <option value="<" {{ $condition->operator === '<' ? 'selected' : '' }}><</option>
                                        <option value=">=" {{ $condition->operator === '>=' ? 'selected' : '' }}>>=</option>
                                        <option value="<=" {{ $condition->operator === '<=' ? 'selected' : '' }}><=</option>
                                        <option value="=" {{ $condition->operator === '=' ? 'selected' : '' }}>=</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Nilai <span class="unit-label">({{ $condition->unit }})</span></label>
                                    <input type="number" name="conditions[{{ $index }}][value]" step="0.01" min="0" required placeholder="0" value="{{ $condition->value }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-add-condition" id="btnAddCondition">+ Tambah Kondisi</button>
            </div>

            <button type="submit" class="btn btn-success">💾 Simpan Perubahan</button>
        </form>
    </div>
</div>
@endsection

<!-- Script JavaScript -->
@push('scripts')
<script>
    let conditionCount = {{ count($rule->conditions) }}; // Mulai dari jumlah kondisi yang ada

    // Handle perubahan target type
    const targetType = document.getElementById('targetType');
    const deviceSelectGroup = document.getElementById('deviceSelectGroup');
    const targetDeviceId = document.getElementById('targetDeviceId');

    targetType.addEventListener('change', function() {
        if (this.value === 'specific') {
            deviceSelectGroup.style.display = 'block';
            targetDeviceId.required = true;
        } else {
            deviceSelectGroup.style.display = 'none';
            targetDeviceId.required = false;
            targetDeviceId.value = '';
        }
    });

    // Set required pada load jika target type adalah specific
    if (targetType.value === 'specific') {
        targetDeviceId.required = true;
    }

    // Handle tambah kondisi baru
    const btnAddCondition = document.getElementById('btnAddCondition');
    const conditionsWrapper = document.getElementById('conditionsWrapper');

    btnAddCondition.addEventListener('click', function() {
        const conditionItem = document.createElement('div');
        conditionItem.className = 'condition-item';
        conditionItem.innerHTML = `
            <div class="condition-header">
                <span class="condition-label">Kondisi ${conditionCount + 1}</span>
                <button type="button" class="btn btn-remove-condition" onclick="removeCondition(this)">✕ Hapus</button>
            </div>
            <div class="form-group-inline">
                <div>
                    <label>Parameter</label>
                    <select name="conditions[${conditionCount}][parameter]" class="condition-parameter" required onchange="updateUnitLabel(this)">
                        <option value="bandwidth">Bandwidth</option>
                        <option value="latency">Latency</option>
                        <option value="packet_loss">Packet Loss</option>
                    </select>
                </div>
                <div>
                    <label>Operator</label>
                    <select name="conditions[${conditionCount}][operator]" required>
                        <option value=">">></option>
                        <option value="<"><</option>
                        <option value=">=">>=</option>
                        <option value="<="><=</option>
                        <option value="=">=</option>
                    </select>
                </div>
                <div>
                    <label>Nilai <span class="unit-label">(Mbps)</span></label>
                    <input type="number" name="conditions[${conditionCount}][value]" step="0.01" min="0" required placeholder="0">
                </div>
            </div>
        `;

        conditionsWrapper.appendChild(conditionItem);
        conditionCount++;
    });

    // Fungsi untuk menghapus kondisi
    function removeCondition(button) {
        const conditionItem = button.closest('.condition-item');
        conditionItem.remove();
        
        // Update label kondisi
        updateConditionLabels();
    }

    // Update label kondisi setelah ada yang dihapus
    function updateConditionLabels() {
        const conditionItems = document.querySelectorAll('.condition-item');
        conditionItems.forEach((item, index) => {
            const label = item.querySelector('.condition-label');
            label.textContent = `Kondisi ${index + 1}`;
        });
    }

    // Update unit label berdasarkan parameter yang dipilih
    function updateUnitLabel(selectElement) {
        const unitLabel = selectElement.closest('.form-group-inline').querySelector('.unit-label');
        const parameter = selectElement.value;
        
        if (parameter === 'bandwidth') {
            unitLabel.textContent = '(Mbps)';
        } else if (parameter === 'latency') {
            unitLabel.textContent = '(ms)';
        } else if (parameter === 'packet_loss') {
            unitLabel.textContent = '(%)';
        }
    }
</script>
@endpush