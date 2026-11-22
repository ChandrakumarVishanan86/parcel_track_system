(function () {
    'use strict'; var esc = SMS.esc, S = [['Standard', 'Standard'], ['Express', 'Express'], ['Same-day', 'Same-day']], Z = [['Local', 'Local'], ['Regional', 'Regional'], ['National', 'National']];
    new Crud({ table: '#tbl', api: APP.base + '/admin/rates-api', label: 'Rate', canWrite: true, canDelete: false, order: [], rowName: function (r) { return r.service + ' / ' + r.zone; },
        columns: [{ data: 'service', render: function (d) { return '<b>' + esc(d) + '</b>'; } }, { data: 'zone' },
            { data: 'base_price', className: 'text-end mono', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } }, { data: 'base_weight', className: 'text-end mono' },
            { data: 'per_extra_kg', className: 'text-end mono', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } }, { data: 'transit_days', className: 'text-center mono' },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [{ name: 'service', label: 'Service', type: 'select', options: S, required: true }, { name: 'zone', label: 'Zone', type: 'select', options: Z, required: true, help: 'Same-day is Local only.' },
            { name: 'base_price', label: 'Base price (Rs.)', type: 'number', step: '0.01', min: 1, maxv: 99999, required: true }, { name: 'base_weight', label: 'Included weight (kg)', type: 'number', step: '0.5', min: 0.5, maxv: 10, required: true },
            { name: 'per_extra_kg', label: 'Per extra kg (Rs.)', type: 'number', step: '0.01', min: 0, maxv: 99999, required: true }, { name: 'transit_days', label: 'Transit days', type: 'number', min: 0, maxv: 30, integer: true, required: true },
            { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
