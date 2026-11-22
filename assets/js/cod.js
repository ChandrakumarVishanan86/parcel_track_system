(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/cod/api', cur = null, due = 0, modal = new bootstrap.Modal(document.getElementById('stModal'));
    function age(d) { if (!d) return '–'; var h = Math.floor((Date.now() - new Date(d.replace(' ', 'T')).getTime()) / 36e5); return h < 24 ? h + 'h' : Math.floor(h / 24) + 'd'; }
    function load() {
        SMS.api(API + '?action=overview').then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            var holding = r.riders.filter(function (x) { return +x.on_hand > 0; });
            document.getElementById('kTotal').textContent = SMS.money(r.total); document.getElementById('kRiders').textContent = holding.length;
            document.getElementById('kOld').textContent = holding.length ? age(holding.map(function (x) { return x.oldest; }).sort()[0]) : '–'; document.getElementById('kHist').textContent = r.history.length;
            document.getElementById('riders').innerHTML = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Rider</th><th>Branch</th><th class="text-center">Items</th><th class="text-end">COD</th><th class="text-end">On hand</th><th>Held</th><th></th></tr></thead><tbody>' + r.riders.map(function (x) {
                var has = +x.on_hand > 0;
                return '<tr><td><div class="fw-semibold">' + esc(x.full_name) + '</div><div class="small text-muted">' + esc(x.vehicle || '') + '</div></td><td>' + esc(x.city || '–') + '</td><td class="text-center mono">' + (+x.items || 0) + '</td><td class="text-end mono">' + SMS.money(x.cod) + '</td><td class="text-end mono fw-bold">' + SMS.money(x.on_hand) + '</td><td>' + (has ? age(x.oldest) : '–') + '</td><td class="text-end">' + (has ? '<button class="btn btn-sm btn-gradient" data-id="' + x.id + '" data-name="' + esc(x.full_name) + '">Settle</button>' : '<span class="text-muted small">Clear</span>') + '</td></tr>';
            }).join('') + '</tbody></table></div>';
            document.getElementById('history').innerHTML = r.history.length ? r.history.map(function (h) {
                return '<div class="d-flex justify-content-between border-bottom py-2"><div><span class="tn">' + esc(h.settle_no) + '</span><div class="small text-muted">' + esc(h.rider) + ' · ' + h.items + ' items · ' + SMS.fdatetime(h.created_at) + '</div></div><div class="mono fw-bold">' + SMS.money(h.amount) + '</div></div>';
            }).join('') : '<div class="text-muted small">No settlements yet.</div>';
        });
    }
    document.getElementById('riders').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-id]'); if (!b) return;
        cur = b.dataset.id; document.getElementById('stTitle').textContent = 'Settle cash - ' + b.dataset.name; document.getElementById('stCount').value = ''; document.getElementById('stNote').value = ''; document.getElementById('stDiff').textContent = '';
        SMS.api(API + '?action=lines&rider_id=' + cur).then(function (r) {
            due = r.data.reduce(function (a, l) { return a + (+l.amount); }, 0); document.getElementById('stDue').textContent = SMS.money(due);
            document.getElementById('stLines').innerHTML = '<table class="table table-sm mb-0"><thead><tr><th>Parcel</th><th>Receiver</th><th>Type</th><th class="text-end">Amount</th></tr></thead><tbody>' + r.data.map(function (l) { return '<tr><td><span class="tn">' + esc(l.tracking_no) + '</span></td><td>' + esc(l.receiver_name) + '</td><td>' + esc(l.kind) + '</td><td class="text-end mono">' + SMS.money(l.amount) + '</td></tr>'; }).join('') + '</tbody></table>';
            modal.show();
        });
    });
    document.getElementById('stCount').addEventListener('input', function () {
        var v = parseFloat(this.value), el = document.getElementById('stDiff'); if (isNaN(v)) { el.textContent = ''; return; }
        var d = Math.round((v - due) * 100) / 100; el.className = 'small mt-2 ' + (d === 0 ? 'text-success' : 'text-danger'); el.textContent = d === 0 ? 'Matches exactly.' : (d > 0 ? 'Over by ' : 'Short by ') + SMS.money(Math.abs(d)) + ' - must match to settle.';
    });
    document.getElementById('stGo').addEventListener('click', function () {
        SMS.api(API, { method: 'POST', body: { action: 'settle', rider_id: cur, counted: document.getElementById('stCount').value, note: document.getElementById('stNote').value } }).then(function (r) {
            SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { modal.hide(); load(); }
        });
    });
    load();
})();
