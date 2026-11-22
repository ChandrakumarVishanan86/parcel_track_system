(function () {
    'use strict'; var esc = SMS.esc;
    new Crud({ table: '#tbl', api: APP.base + '/admin/branches-api', label: 'Branch', canWrite: true, rowName: function (r) { return r.city; }, order: [[0, 'asc']],
        columns: [
            { data: 'code', render: function (d) { return '<span class="tn">' + esc(d) + '</span>'; } },
            { data: 'name', render: function (d, t, r) { return '<div class="fw-semibold">' + esc(d) + '</div><div class="small text-muted">' + esc(r.city) + '</div>'; } },
            { data: 'region' }, { data: 'phone', defaultContent: '–' },
            { data: 'is_hub', render: function (d) { return d == 1 ? '<span class="badge badge-soft-violet">Sorting hub</span>' : '<span class="badge badge-soft-secondary">Depot</span>'; } },
            { data: 'riders', className: 'text-center mono' }, { data: 'active_parcels', className: 'text-center mono' },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [
            { name: 'code', label: 'Code', required: true, max: 3, upper: true, unique: true, pattern: '^[A-Z]{3}$', patternMsg: '3 capital letters.', col: 4 },
            { name: 'name', label: 'Branch name', required: true, max: 80, col: 8 },
            { name: 'city', label: 'City', required: true, max: 60, unique: true }, { name: 'region', label: 'Region', required: true, max: 40, help: 'Same region = Regional zone, different = National.' },
            { name: 'address', label: 'Address', max: 200, col: 8 }, { name: 'phone', label: 'Phone', max: 20, col: 4, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
            { name: 'is_hub', label: 'Sorting hub', type: 'checkbox' }, { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
