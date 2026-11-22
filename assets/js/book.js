/* Booking form: live quote, customer lookup, submit */
(function () {
    'use strict';
    var esc = SMS.esc, f = document.getElementById('bookForm'), qbox = document.getElementById('quote'), btn = document.getElementById('btnBook');
    if (window.flatpickr) flatpickr('#pickup_date', { minDate: 'today', maxDate: new Date(Date.now() + 7 * 864e5), dateFormat: 'Y-m-d' });
    function data() { var o = {}; new FormData(f).forEach(function (v, k) { o[k] = v; }); o.fragile = f.fragile.checked ? 1 : 0; o.insured = f.insured.checked ? 1 : 0; return o; }

    function quote() {
        var d = data();
        if (!d.origin_branch_id || !d.dest_branch_id || !d.weight_kg) { qbox.className = 'text-muted small'; qbox.textContent = 'Fill in the route and weight to see the price.'; return; }
        var p = new URLSearchParams(d);
        SMS.api(APP.base + '/shipments/api?action=quote&' + p.toString()).then(function (r) {
            if (!r.success) { qbox.className = 'text-danger small fw-semibold'; qbox.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + esc(r.message); return; }
            var q = r.data; qbox.className = '';
            var rows = [['Freight · ' + q.chargeable_kg.toFixed(1) + ' kg', q.freight], ['Fuel surcharge', q.fuel]];
            if (q.fragile_fee > 0) rows.push(['Fragile handling', q.fragile_fee]);
            if (q.cod_fee > 0) rows.push(['COD fee', q.cod_fee]);
            if (q.insurance_fee > 0) rows.push(['Insurance', q.insurance_fee]);
            rows.push(['VAT', q.tax]);
            qbox.innerHTML = '<div class="mb-2"><span class="badge badge-soft-teal">' + esc(q.zone) + '</span> <span class="badge badge-soft-secondary">' + esc(q.service) + '</span> <span class="small text-muted ms-1">by ' + SMS.fdate(q.promised_date) + '</span></div>' +
                (q.volumetric_kg > q.weight_kg ? '<div class="small text-muted mb-2">Volumetric weight ' + q.volumetric_kg + ' kg is higher than actual ' + q.weight_kg + ' kg.</div>' : '') +
                rows.map(function (x) { return '<div class="receipt-row"><span>' + x[0] + '</span><span class="mono">' + SMS.money(x[1]) + '</span></div>'; }).join('') +
                '<div class="receipt-total"><span>Total</span><span>' + SMS.money(q.total_charge) + '</span></div>' +
                (q.cod_amount > 0 ? '<div class="small mt-1"><i class="bi bi-cash-coin me-1"></i>Rider collects <b>' + SMS.money(q.cod_amount) + '</b> from the receiver.</div>' : '');
        });
    }
    var dq = SMS.debounce(quote, 300);
    f.addEventListener('input', dq); f.addEventListener('change', function () { dq(); payToggle(); });
    function payToggle() { document.getElementById('payWrap').style.opacity = f.payer.value === 'Receiver' ? .45 : 1; f.pay_method.disabled = f.payer.value === 'Receiver'; }
    payToggle();

    /* customer lookup (desk only) */
    var cs = document.getElementById('custSearch');
    if (cs) {
        var res = document.getElementById('custResults'), picked = document.getElementById('custPicked'), clear = document.getElementById('custClear');
        var look = SMS.debounce(function () {
            var t = cs.value.trim(); if (t.length < 2) { res.classList.add('d-none'); return; }
            SMS.api(APP.base + '/customers/api?action=search&term=' + encodeURIComponent(t)).then(function (r) {
                res.innerHTML = (r.data || []).map(function (c) { return '<button type="button" class="list-group-item list-group-item-action" data-id="' + c.id + '"><b>' + esc(c.company || c.full_name) + '</b> <span class="tn">' + esc(c.code) + '</span> ' + (c.is_credit == 1 ? '<span class="badge badge-soft-teal">Credit</span>' : '') + '<div class="small text-muted">' + esc(c.full_name) + ' · ' + esc(c.phone) + '</div></button>'; }).join('') || '<div class="list-group-item text-muted">No customers found</div>';
                res.classList.remove('d-none'); res._rows = r.data || [];
            });
        }, 250);
        cs.addEventListener('input', look);
        res.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-id]'); if (!b) return;
            var c = res._rows.find(function (x) { return x.id == b.dataset.id; });
            f.customer_id.value = c.id; f.sender_name.value = c.company || c.full_name; f.sender_phone.value = c.phone; f.sender_address.value = c.address || '';
            if (c.branch_id) f.origin_branch_id.value = c.branch_id;
            cs.value = ''; res.classList.add('d-none'); clear.classList.remove('d-none');
            picked.classList.remove('d-none'); picked.innerHTML = '<i class="bi bi-person-check-fill text-success me-1"></i>Booking for <b>' + esc(c.company || c.full_name) + '</b> (' + esc(c.code) + ')' + (c.is_credit == 1 ? ' - credit account: leave payment empty to bill on account.' : ''); dq();
        });
        clear.addEventListener('click', function () { f.customer_id.value = ''; picked.classList.add('d-none'); clear.classList.add('d-none'); });
    }

    f.addEventListener('submit', function (e) {
        e.preventDefault();
        var d = data(); if (f.payer.value === 'Receiver') d.pay_method = '';
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Booking…';
        SMS.api(APP.base + '/shipments/api', { method: 'POST', body: Object.assign({ action: 'create' }, d) }).then(function (r) {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirm booking';
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            try { sessionStorage.setItem('sms_toast', JSON.stringify({ m: r.message, t: 'success' })); } catch (x) {} location.href = APP.base + '/shipments/' + r.id;
        });
    });
})();
