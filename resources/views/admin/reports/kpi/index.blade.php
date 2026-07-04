@extends('layouts.admin')

@section('title', 'KPI Master & Score')
@section('page_title', 'KPI Master & Score')

@section('content')
<ul class="nav nav-tabs nav-line-tabs mb-6 fs-6" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#kpi_master_tab" type="button">KPI Master</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi_assignment_tab" type="button">Assignment</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpi_snapshot_tab" type="button">Score Snapshot</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="kpi_master_tab">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title"><h3 class="fw-bolder mb-0">Master KPI</h3></div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" id="btn_add_definition">Tambah KPI</button>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kpi_definitions_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Role/Jabatan</th>
                                <th>KPI</th>
                                <th>Target</th>
                                <th>Bobot</th>
                                <th>Periode</th>
                                <th>Sumber</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="kpi_assignment_tab">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title"><h3 class="fw-bolder mb-0">Assignment KPI Karyawan</h3></div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" id="btn_add_assignment">Assign KPI</button>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kpi_assignments_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Karyawan</th>
                                <th>Role</th>
                                <th>KPI</th>
                                <th>Periode Berlaku</th>
                                <th>Target Override</th>
                                <th>Bobot Override</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="kpi_snapshot_tab">
        <div class="card mb-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title"><h3 class="fw-bolder mb-0">Score Snapshot</h3></div>
                <div class="card-toolbar">
                    <div class="d-flex align-items-end gap-3 flex-wrap">
                        <div class="w-150px">
                            <label class="text-muted fs-7 mb-1">Dari</label>
                            <input type="text" class="form-control form-control-solid" id="snapshot_period_start" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="w-150px">
                            <label class="text-muted fs-7 mb-1">Sampai</label>
                            <input type="text" class="form-control form-control-solid" id="snapshot_period_end" placeholder="YYYY-MM-DD">
                        </div>
                        <button type="button" class="btn btn-primary" id="btn_create_kpi_snapshot">Buat Snapshot</button>
                    </div>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kpi_snapshots_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th class="text-end">Item</th>
                                <th class="text-end">Rata-rata Skor</th>
                                <th>Catatan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h3 class="fw-bolder mb-1">Detail Score</h3>
                    <div class="text-muted fs-7" id="score_detail_title">Pilih snapshot untuk melihat detail.</div>
                </div>
            </div>
            <div class="card-body py-6">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kpi_score_items_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Karyawan</th>
                                <th>Role</th>
                                <th>KPI</th>
                                <th>Target</th>
                                <th class="text-end">Actual</th>
                                <th class="text-end">Achievement</th>
                                <th class="text-end">Bobot</th>
                                <th class="text-end">Skor Akhir</th>
                                <th>Sumber</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="definition_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="definition_form">
            <div class="modal-header">
                <h5 class="modal-title" id="definition_modal_title">Tambah KPI</h5>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="definition_id">
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Role/Jabatan</label>
                        <input type="text" class="form-control" name="role_name" id="definition_role_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Nama KPI</label>
                        <input type="text" class="form-control" name="metric_name" id="definition_metric_name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="definition_description" rows="2"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Operator</label>
                        <select class="form-select" name="target_operator" id="definition_target_operator" required>
                            <option value=">=">>=</option>
                            <option value="&lt;=">&lt;=</option>
                            <option value="=">=</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Target</label>
                        <input type="number" step="0.0001" min="0" class="form-control" name="target_value" id="definition_target_value" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <input type="text" class="form-control" name="unit" id="definition_unit" placeholder="%, pcs, menit">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Bobot</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control" name="weight" id="definition_weight" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Periode</label>
                        <select class="form-select" name="period_type" id="definition_period_type" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Sumber</label>
                        <select class="form-select" name="source_type" id="definition_source_type" required>
                            <option value="manual">Manual</option>
                            <option value="auto">Auto</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Formula Key</label>
                        <input type="text" class="form-control" name="formula_key" id="definition_formula_key">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select class="form-select" name="is_active" id="definition_is_active" required>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="assignment_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="assignment_form">
            <div class="modal-header">
                <h5 class="modal-title" id="assignment_modal_title">Assign KPI</h5>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="assignment_id">
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Karyawan</label>
                        <select class="form-select" name="employee_id" id="assignment_employee_id" required>
                            <option value="">Pilih karyawan</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">KPI</label>
                        <select class="form-select" name="kpi_definition_id" id="assignment_kpi_definition_id" required>
                            <option value="">Pilih KPI</option>
                            @foreach($definitions as $definition)
                                <option value="{{ $definition->id }}">{{ $definition->role_name }} - {{ $definition->metric_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Berlaku Dari</label>
                        <input type="text" class="form-control" name="effective_from" id="assignment_effective_from" placeholder="YYYY-MM-DD" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Berlaku Sampai</label>
                        <input type="text" class="form-control" name="effective_until" id="assignment_effective_until" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Override</label>
                        <input type="number" step="0.0001" min="0" class="form-control" name="target_value" id="assignment_target_value">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bobot Override</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control" name="weight" id="assignment_weight">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select class="form-select" name="is_active" id="assignment_is_active" required>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="score_item_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="score_item_form">
            <div class="modal-header">
                <h5 class="modal-title">Input Actual KPI</h5>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="score_item_id">
                <div class="mb-5">
                    <label class="form-label required">Actual</label>
                    <input type="number" step="0.0001" min="0" class="form-control" name="actual_value" id="score_item_actual_value" required>
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" name="note" id="score_item_note" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const kpiUrls = {
        definitionsData: @json($definitionsDataUrl),
        definitionStore: @json($definitionStoreUrl),
        definitionUpdate: @json($definitionUpdateUrlTpl),
        definitionDelete: @json($definitionDeleteUrlTpl),
        assignmentsData: @json($assignmentsDataUrl),
        assignmentStore: @json($assignmentStoreUrl),
        assignmentUpdate: @json($assignmentUpdateUrlTpl),
        assignmentDelete: @json($assignmentDeleteUrlTpl),
        snapshotsData: @json($snapshotsDataUrl),
        snapshotStore: @json($snapshotStoreUrl),
        snapshotItems: @json($snapshotItemsUrlTpl),
        snapshotLock: @json($snapshotLockUrlTpl),
        scoreItemUpdate: @json($scoreItemUpdateUrlTpl),
    };
    const csrfToken = @json(csrf_token());

    document.addEventListener('DOMContentLoaded', () => {
        const state = { selectedSnapshotId: null, selectedSnapshotLocked: false };
        const definitionModal = new bootstrap.Modal(document.getElementById('definition_modal'));
        const assignmentModal = new bootstrap.Modal(document.getElementById('assignment_modal'));
        const scoreItemModal = new bootstrap.Modal(document.getElementById('score_item_modal'));

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const number = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
        const route = (tpl, id) => tpl.replace(':id', id);
        const badge = (active) => active ? '<span class="badge badge-light-success">Aktif</span>' : '<span class="badge badge-light-danger">Nonaktif</span>';
        const statusBadge = (status) => status === 'locked'
            ? '<span class="badge badge-light-primary">Locked</span>'
            : '<span class="badge badge-light-warning">Draft</span>';

        const request = (url, method, payload = {}) => fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: method === 'GET' ? undefined : JSON.stringify(payload),
        }).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Terjadi kesalahan.');
                throw new Error(errors);
            }
            return data;
        });

        const notify = (message, icon = 'success') => {
            if (window.Swal) {
                Swal.fire({ text: message, icon, buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
            } else {
                alert(message);
            }
        };

        const serializeForm = (form) => {
            const data = {};
            new FormData(form).forEach((value, key) => {
                data[key] = value === '' ? null : value;
            });
            return data;
        };

        const definitionsTable = $('#kpi_definitions_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: kpiUrls.definitionsData,
            columns: [
                { data: 'role_name', render: escapeHtml },
                { data: 'metric_name', render: (data, type, row) => `<div class="fw-bold">${escapeHtml(data)}</div><div class="text-muted fs-7">${escapeHtml(row.description)}</div>` },
                { data: null, render: (row) => `${escapeHtml(row.target_operator)} ${number(row.target_value)} ${escapeHtml(row.unit)}` },
                { data: 'weight', className: 'text-end', render: (data) => `${number(data)}%` },
                { data: 'period_type', render: escapeHtml },
                { data: null, render: (row) => `${escapeHtml(row.source_type)}${row.formula_key ? `<div class="text-muted fs-7">${escapeHtml(row.formula_key)}</div>` : ''}` },
                { data: 'is_active', render: badge },
                { data: null, orderable: false, className: 'text-end', render: (row) => `
                    <button type="button" class="btn btn-sm btn-light-primary btn-edit-definition" data-row='${escapeHtml(JSON.stringify(row))}'>Edit</button>
                    <button type="button" class="btn btn-sm btn-light-danger btn-delete-definition" data-id="${row.id}">Hapus</button>
                ` },
            ],
        });

        const assignmentsTable = $('#kpi_assignments_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: kpiUrls.assignmentsData,
            columns: [
                { data: 'employee', render: escapeHtml },
                { data: 'role_name', render: escapeHtml },
                { data: 'metric_name', render: escapeHtml },
                { data: null, render: (row) => `${escapeHtml(row.effective_from)} s/d ${escapeHtml(row.effective_until || '-')}` },
                { data: null, render: (row) => row.target_value === null ? `<span class="text-muted">Default ${number(row.default_target_value)}</span>` : number(row.target_value) },
                { data: null, render: (row) => row.weight === null ? `<span class="text-muted">Default ${number(row.default_weight)}%</span>` : `${number(row.weight)}%` },
                { data: 'is_active', render: badge },
                { data: null, orderable: false, className: 'text-end', render: (row) => `
                    <button type="button" class="btn btn-sm btn-light-primary btn-edit-assignment" data-row='${escapeHtml(JSON.stringify(row))}'>Edit</button>
                    <button type="button" class="btn btn-sm btn-light-danger btn-delete-assignment" data-id="${row.id}">Hapus</button>
                ` },
            ],
        });

        const snapshotsTable = $('#kpi_snapshots_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: kpiUrls.snapshotsData,
            columns: [
                { data: 'code', render: escapeHtml },
                { data: null, render: (row) => `${escapeHtml(row.period_start)} s/d ${escapeHtml(row.period_end)}` },
                { data: 'status', render: statusBadge },
                { data: 'items_count', className: 'text-end', render: number },
                { data: 'average_score', className: 'text-end', render: (data) => number(data) },
                { data: 'note', render: escapeHtml },
                { data: null, orderable: false, className: 'text-end', render: (row) => `
                    <button type="button" class="btn btn-sm btn-light-primary btn-view-snapshot" data-id="${row.id}" data-code="${escapeHtml(row.code)}" data-status="${escapeHtml(row.status)}">Detail</button>
                    ${row.status === 'locked' ? '' : `<button type="button" class="btn btn-sm btn-light-success btn-lock-snapshot" data-id="${row.id}">Lock</button>`}
                ` },
            ],
        });

        const scoreItemsTable = $('#kpi_score_items_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: (data, callback) => {
                if (!state.selectedSnapshotId) {
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                    return;
                }
                $.get(route(kpiUrls.snapshotItems, state.selectedSnapshotId), data, callback);
            },
            columns: [
                { data: 'employee', render: escapeHtml },
                { data: 'role_name', render: escapeHtml },
                { data: 'metric_name', render: escapeHtml },
                { data: null, render: (row) => `${escapeHtml(row.target_operator)} ${number(row.target_value)}` },
                { data: 'actual_value', className: 'text-end', render: (data) => data === null ? '<span class="text-muted">Belum diisi</span>' : number(data) },
                { data: 'achievement_percent', className: 'text-end', render: (data) => `${number(data)}%` },
                { data: 'weight', className: 'text-end', render: (data) => `${number(data)}%` },
                { data: 'weighted_score', className: 'text-end', render: number },
                { data: null, render: (row) => `${escapeHtml(row.source_type)}${row.formula_key ? `<div class="text-muted fs-7">${escapeHtml(row.formula_key)}</div>` : ''}` },
                { data: null, orderable: false, className: 'text-end', render: (row) => row.snapshot_status === 'locked' ? '-' : `
                    <button type="button" class="btn btn-sm btn-light-primary btn-edit-score-item" data-row='${escapeHtml(JSON.stringify(row))}'>Input</button>
                ` },
            ],
        });

        const resetDefinitionForm = () => {
            document.getElementById('definition_form').reset();
            document.getElementById('definition_id').value = '';
            document.getElementById('definition_weight').value = '100';
            document.getElementById('definition_target_value').value = '0';
            document.getElementById('definition_modal_title').textContent = 'Tambah KPI';
        };

        document.getElementById('btn_add_definition').addEventListener('click', () => {
            resetDefinitionForm();
            definitionModal.show();
        });

        $('#kpi_definitions_table').on('click', '.btn-edit-definition', function () {
            const row = JSON.parse(this.dataset.row);
            resetDefinitionForm();
            document.getElementById('definition_modal_title').textContent = 'Edit KPI';
            for (const key of ['id', 'role_name', 'metric_name', 'description', 'target_operator', 'target_value', 'unit', 'weight', 'period_type', 'source_type', 'formula_key']) {
                const el = document.getElementById(`definition_${key}`);
                if (el) el.value = row[key] ?? '';
            }
            document.getElementById('definition_is_active').value = row.is_active ? '1' : '0';
            definitionModal.show();
        });

        document.getElementById('definition_form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = document.getElementById('definition_id').value;
            const payload = serializeForm(event.target);
            try {
                await request(id ? route(kpiUrls.definitionUpdate, id) : kpiUrls.definitionStore, id ? 'PUT' : 'POST', payload);
                definitionModal.hide();
                definitionsTable.ajax.reload(null, false);
                notify('Data KPI berhasil disimpan.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        $('#kpi_definitions_table').on('click', '.btn-delete-definition', async function () {
            if (!confirm('Hapus KPI ini?')) return;
            try {
                await request(route(kpiUrls.definitionDelete, this.dataset.id), 'DELETE');
                definitionsTable.ajax.reload(null, false);
                notify('KPI berhasil dihapus.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        const resetAssignmentForm = () => {
            document.getElementById('assignment_form').reset();
            document.getElementById('assignment_id').value = '';
            document.getElementById('assignment_modal_title').textContent = 'Assign KPI';
            document.getElementById('assignment_effective_from').value = new Date().toISOString().slice(0, 10);
        };

        document.getElementById('btn_add_assignment').addEventListener('click', () => {
            resetAssignmentForm();
            assignmentModal.show();
        });

        $('#kpi_assignments_table').on('click', '.btn-edit-assignment', function () {
            const row = JSON.parse(this.dataset.row);
            resetAssignmentForm();
            document.getElementById('assignment_modal_title').textContent = 'Edit Assignment KPI';
            document.getElementById('assignment_id').value = row.id;
            document.getElementById('assignment_employee_id').value = row.employee_id;
            document.getElementById('assignment_kpi_definition_id').value = row.kpi_definition_id;
            document.getElementById('assignment_effective_from').value = row.effective_from || '';
            document.getElementById('assignment_effective_until').value = row.effective_until || '';
            document.getElementById('assignment_target_value').value = row.target_value ?? '';
            document.getElementById('assignment_weight').value = row.weight ?? '';
            document.getElementById('assignment_is_active').value = row.is_active ? '1' : '0';
            assignmentModal.show();
        });

        document.getElementById('assignment_form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = document.getElementById('assignment_id').value;
            const payload = serializeForm(event.target);
            try {
                await request(id ? route(kpiUrls.assignmentUpdate, id) : kpiUrls.assignmentStore, id ? 'PUT' : 'POST', payload);
                assignmentModal.hide();
                assignmentsTable.ajax.reload(null, false);
                notify('Assignment KPI berhasil disimpan.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        $('#kpi_assignments_table').on('click', '.btn-delete-assignment', async function () {
            if (!confirm('Hapus assignment ini?')) return;
            try {
                await request(route(kpiUrls.assignmentDelete, this.dataset.id), 'DELETE');
                assignmentsTable.ajax.reload(null, false);
                notify('Assignment KPI berhasil dihapus.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        const setDefaultSnapshotPeriod = () => {
            const now = new Date();
            const first = new Date(now.getFullYear(), now.getMonth(), 1);
            const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            document.getElementById('snapshot_period_start').value = first.toISOString().slice(0, 10);
            document.getElementById('snapshot_period_end').value = last.toISOString().slice(0, 10);
        };
        setDefaultSnapshotPeriod();

        document.getElementById('btn_create_kpi_snapshot').addEventListener('click', async () => {
            try {
                const data = await request(kpiUrls.snapshotStore, 'POST', {
                    period_start: document.getElementById('snapshot_period_start').value,
                    period_end: document.getElementById('snapshot_period_end').value,
                });
                snapshotsTable.ajax.reload(null, false);
                notify(`${data.message} Total item: ${data.items_count}`);
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        $('#kpi_snapshots_table').on('click', '.btn-view-snapshot', function () {
            state.selectedSnapshotId = this.dataset.id;
            state.selectedSnapshotLocked = this.dataset.status === 'locked';
            document.getElementById('score_detail_title').textContent = `Snapshot ${this.dataset.code}`;
            scoreItemsTable.ajax.reload();
        });

        $('#kpi_snapshots_table').on('click', '.btn-lock-snapshot', async function () {
            if (!confirm('Lock snapshot ini? Nilai tidak bisa diedit setelah dikunci.')) return;
            try {
                await request(route(kpiUrls.snapshotLock, this.dataset.id), 'POST');
                snapshotsTable.ajax.reload(null, false);
                scoreItemsTable.ajax.reload(null, false);
                notify('Snapshot berhasil dikunci.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });

        $('#kpi_score_items_table').on('click', '.btn-edit-score-item', function () {
            const row = JSON.parse(this.dataset.row);
            document.getElementById('score_item_id').value = row.id;
            document.getElementById('score_item_actual_value').value = row.actual_value ?? '';
            document.getElementById('score_item_note').value = row.note ?? '';
            scoreItemModal.show();
        });

        document.getElementById('score_item_form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = document.getElementById('score_item_id').value;
            try {
                await request(route(kpiUrls.scoreItemUpdate, id), 'PUT', serializeForm(event.target));
                scoreItemModal.hide();
                scoreItemsTable.ajax.reload(null, false);
                snapshotsTable.ajax.reload(null, false);
                notify('Nilai KPI berhasil disimpan.');
            } catch (error) {
                notify(error.message, 'error');
            }
        });
    });
</script>
@endpush
