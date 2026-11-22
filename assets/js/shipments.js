/* Shipments list */
(function () {
    'use strict';
    var esc = SMS.esc, f = document.getElementById('flt');
    var tbl = $('#tbl').DataTable({
        ajax: { url: APP.base + '/shipments/api', data: function (d) { d.action = 'list'; new FormData(f).forEach(function (v, k) { if (v) d[k] = v; }); }, dataSrc: 'data' },
        order: [[0, 'desc']], pageLength: 15, language: { search: '', searchPlaceholder: 'Quick filter…', emptyTable: 'No shipments match these filters.' },
        columns: [
            { data: 'tracking_no', render: function (d, t, r) { return t === 'display' ? '<a href="' + APP.base + '/shipments/' + r.id + '"><span class="tn">' + esc(d) + '</span></a>' + (r.cod_amount > 0 ? ' <span class="badge badge-soft-warning" title="Cash on delivery">COD</span>' : '') + (r.fragile == 1 ? ' <i class="bi bi-cup-straw text-danger" title="Fragile"></i>' : '') : d; } },
            { data: 'origin_city', render: function (d, t, r) { return esc(d) + ' <i class="bi bi-arrow-right text-muted"></i> ' + esc(r.dest_city); } },
            { data: 'receiver_name', render: function (d, t, r) { return '<div class="fw-semibold">' + esc(d) + '</div><div class="small text-muted">' + esc(r.receiver_phone) + '</div>'; } },
            { data: 'service', render: function (d, t, r) { return esc(d) + '<div class="small text-muted">' + esc(r.zone) + '</div>'; } },
            { data: 'status', render: function (d, t, r) { return t === 'display' ? SMS.badge(d) + (r.is_late == 1 ? ' <span class="badge badge-soft-danger">Late</span>' : '') : d; } },
            { data: 'payment_status', render: function (d, t) { return t === 'display' ? SMS.badge(d) : d; } },
            { data: 'promised_date', render: function (d, t) { return t === 'display' ? SMS.fdate(d) : d; } },
            { data: 'total_charge', className: 'text-end mono', render: function (d, t) { return t === 'display' ? SMS.money(d) : d; } },
            { data: null, orderable: false, className: 'text-end text-nowrap', render: function (r) { return '<a class="btn btn-icon btn-soft-primary" title="Open" href="' + APP.base + '/shipments/' + r.id + '"><i class="bi bi-eye"></i></a> <a class="btn btn-icon btn-soft" title="Label" target="_blank" href="' + APP.base + '/shipments/' + r.id + '/label"><i class="bi bi-upc-scan"></i></a>'; } }
        ]
    });
    var reload = SMS.debounce(function () { tbl.ajax.reload(); }, 250);
    f.addEventListener('input', reload); f.addEventListener('change', reload); f.addEventListener('submit', function (e) { e.preventDefault(); });
    document.getElementById('reset').addEventListener('click', function () { f.reset(); tbl.ajax.reload(); });
})();
