(function () {
    'use strict';
    var esc = SMS.esc;
    new Crud({
        table: '#tbl', api: APP.base + '/customers/api', label: 'Customer', canWrite: true, rowName: function (r) { return r.full_name; }, order: [[1, 'asc']],
        columns: [
            { data: 'code', render: function (d) { return '<span class="tn">' + esc(d) + '</span>'; } },
            { data: 'full_name', render: function (d, t, r) { return '<div class="fw-semibold">' + esc(d) + '</div>' + (r.company ? '<div class="small text-muted">' + esc(r.company) + '</div>' : ''); } },
            { data: 'phone' },
            { data: 'branch_city', defaultContent: '–' },
            { data: 'is_credit', render: function (d) { return d == 1 ? '<span class="badge badge-soft-violet">Credit</span>' : '<span class="badge badge-soft-secondary">Pay per parcel</span>'; } },
            { data: 'shipments', className: 'text-center mono' },
            { data: 'outstanding', className: 'text-end mono', render: function (d, t) { return t === 'display' ? (d > 0 ? '<span class="text-danger">' + SMS.money(d) + '</span>' : '–') : d; } },
            { data: 'has_login', render: function (d) { return d > 0 ? '<i class="bi bi-check-circle-fill text-success" title="Has portal login"></i>' : '<span class="text-muted">–</span>'; } },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }
        ],
        fields: [
            { name: 'full_name', label: 'Contact name', required: true, max: 120 },
            { name: 'company', label: 'Company', max: 120 },
            { name: 'phone', label: 'Phone', required: true, max: 20, unique: true, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
            { name: 'email', label: 'Email', type: 'email', max: 120 },
            { name: 'address', label: 'Pickup address', max: 255, col: 12 },
            { name: 'branch_id', label: 'Home branch', type: 'select', options: window.BR },
            { name: 'notes', label: 'Notes', max: 255 },
            { name: 'is_credit', label: 'Credit account (billed monthly)', type: 'checkbox' },
            { name: 'is_active', label: 'Active', type: 'checkbox', default: true },
            { name: 'create_login', label: 'Create portal login (username c + phone digits, password customer123)', type: 'checkbox', col: 12, createOnly: true }
        ]
    });
})();
