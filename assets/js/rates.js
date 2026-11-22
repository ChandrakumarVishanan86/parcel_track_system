/* Public rate calculator */
(function () {
    'use strict';
    var esc = SMS.esc, f = document.getElementById('rateForm'), out = document.getElementById('rateOut');
    f.addEventListener('submit', function (e) {
        e.preventDefault();
        var p = new URLSearchParams(new FormData(f));
        SMS.api(APP.base + '/rates/api?' + p.toString()).then(function (r) {
            if (!r.success) { out.innerHTML = '<div class="card-panel text-center py-4 text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>' + esc(r.message) + '</div>'; return; }
            out.innerHTML = r.data.map(function (q, i) {
                var rows = [['Freight (' + q.chargeable_kg.toFixed(1) + ' kg)', q.freight], ['Fuel surcharge', q.fuel]];
                if (q.fragile_fee > 0) rows.push(['Fragile handling', q.fragile_fee]);
                if (q.cod_fee > 0) rows.push(['COD fee', q.cod_fee]);
                if (q.insurance_fee > 0) rows.push(['Insurance', q.insurance_fee]);
                rows.push(['VAT', q.tax]);
                return '<div class="card-panel mb-3"><div class="d-flex align-items-center mb-2"><div><span class="fw-bold fs-5" style="font-family:var(--head)">' + esc(q.service) + '</span><div class="small text-muted">' + esc(q.zone) + ' · ' + (q.transit_days === 0 ? 'same day' : q.transit_days + ' day' + (q.transit_days > 1 ? 's' : '')) + ' · by ' + SMS.fdate(q.promised_date) + '</div></div>' +
                    '<div class="ms-auto text-end"><div class="fs-3 fw-bold mono">' + SMS.money(q.total_charge) + '</div></div></div>' +
                    '<details><summary class="small text-muted" style="cursor:pointer">Price breakdown</summary><div class="mt-2">' + rows.map(function (r) { return '<div class="receipt-row"><span>' + r[0] + '</span><span class="mono">' + SMS.money(r[1]) + '</span></div>'; }).join('') + '</div></details>' +
                    '<a class="btn btn-sm btn-soft mt-2" href="' + APP.base + '/shipments/new"><i class="bi bi-box-seam me-1"></i>Book ' + esc(q.service) + '</a></div>';
            }).join('');
        });
    });
})();
