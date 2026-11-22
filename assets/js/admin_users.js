(function () {
    'use strict'; var esc = SMS.esc, cls = { admin: 'danger', dispatcher: 'primary', hub: 'violet', rider: 'teal' };
    new Crud({ table: '#tbl', api: APP.base + '/admin/users-api', label: 'User', canWrite: true, rowName: function (r) { return r.username; }, order: [[1, 'asc']],
        columns: [{ data: 'full_name', render: function (d, t, r) { return '<div class="fw-semibold">' + esc(d) + '</div><div class="small text-muted">@' + esc(r.username) + '</div>'; } },
            { data: 'role', render: function (d) { return '<span class="badge badge-soft-' + (cls[d] || 'secondary') + '">' + esc(d) + '</span>'; } },
            { data: 'branch_city', defaultContent: '–' }, { data: 'vehicle', defaultContent: '–' }, { data: 'phone', defaultContent: '–' },
            { data: 'last_login', render: function (d, t) { return t === 'display' ? (d ? SMS.fdatetime(d) : '<span class="text-muted">never</span>') : d; } },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [{ name: 'full_name', label: 'Full name', required: true, max: 120 }, { name: 'username', label: 'Username', required: true, max: 50, unique: true, pattern: '^[a-z0-9._\\-]{3,50}$', patternMsg: '3-50 chars: a-z, 0-9 . _ -' },
            { name: 'role', label: 'Role', type: 'select', required: true, options: [['admin', 'Admin'], ['dispatcher', 'Dispatcher'], ['hub', 'Hub operator'], ['rider', 'Rider']] },
            { name: 'branch_id', label: 'Branch', type: 'select', options: window.BR, help: 'Required for dispatcher, hub operator and rider.' },
            { name: 'email', label: 'Email', type: 'email', max: 120 }, { name: 'phone', label: 'Phone', max: 20, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
            { name: 'vehicle', label: 'Vehicle (riders)', max: 40, placeholder: 'e.g. Bike WP BCD-1234' }, { name: 'password', label: 'Password', type: 'password', min: 0, minLen: 8, max: 72, placeholder: 'At least 8 characters', placeholderEdit: 'Leave blank to keep current' },
            { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
