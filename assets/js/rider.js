/* Rider app */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/rider/api', box = document.getElementById('tasks'), cur = null, reasons = [];
    var mD = new bootstrap.Modal(document.getElementById('mDeliver')), mF = new bootstrap.Modal(document.getElementById('mFail'));

    function phone(p) { return '<a href="tel:' + esc(p) + '" class="text-decoration-none"><i class="bi bi-telephone-fill me-1"></i>' + esc(p) + '</a>'; }
    function card(s, kind) {
        var cls = kind === 'pick' ? 'pick' : (kind === 'ret' ? 'ret' : ''), who = kind === 'pick' ? [s.sender_name, s.sender_phone, s.sender_address] : [s.receiver_name, s.receiver_phone, s.receiver_address];
        var money = kind === 'pick' ? s.collect_pickup : s.collect_delivery, btns = '';
        if (kind === 'pick') btns = '<button class="btn btn-gradient flex-fill" data-a="pickup" data-id="' + s.id + '" data-collect="' + (money > 0 ? 1 : 0) + '" data-amt="' + money + '"><i class="bi bi-box-arrow-up me-1"></i>Confirm pickup</button>';
        if (kind === 'del') btns = '<button class="btn btn-gradient flex-fill" data-a="deliver" data-id="' + s.id + '"><i class="bi bi-check2-circle me-1"></i>Deliver</button><button class="btn btn-soft-danger" data-a="fail" data-id="' + s.id + '"><i class="bi bi-x-circle me-1"></i>Failed</button>';
        if (kind === 'ret') btns = '<button class="btn btn-dark flex-fill" data-a="returned" data-id="' + s.id + '"><i class="bi bi-arrow-return-left me-1"></i>Handed back to sender</button>';
        return '<div class="task ' + cls + '" data-s=\'' + esc(JSON.stringify({ id: s.id, tn: s.tracking_no, name: who[0], money: money })) + '\'><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><span class="tn">' + esc(s.tracking_no) + '</span>' + SMS.badge(s.status) + (s.is_late == 1 ? '<span class="badge badge-soft-danger">Late</span>' : '') + (s.fragile == 1 ? '<span class="badge badge-soft-danger">Fragile</span>' : '') + '<span class="ms-auto small text-muted">' + esc(s.service) + ' · ' + s.pieces + ' pc · ' + Number(s.chargeable_kg).toFixed(1) + ' kg</span></div>' +
            '<div class="fw-semibold">' + (kind === 'ret' ? 'Return to: ' : '') + esc(kind === 'ret' ? s.sender_name : who[0]) + '</div><div class="small">' + phone(kind === 'ret' ? s.sender_phone : who[1]) + '</div><div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>' + esc(kind === 'ret' ? s.sender_address : who[2]) + '</div>' +
            (money > 0 ? '<div class="alert alert-warning py-1 px-2 small mb-2"><i class="bi bi-cash-coin me-1"></i>Collect <b>' + SMS.money(money) + '</b>' + (kind === 'pick' ? ' freight in cash' : (s.cod_amount > 0 ? ' (COD' + (s.payer === 'Receiver' && s.payment_status === 'Unpaid' ? ' + freight' : '') + ')' : ' freight')) + '</div>' : '') +
            (s.notes ? '<div class="small text-muted mb-2"><i class="bi bi-sticky me-1"></i>' + esc(s.notes) + '</div>' : '') + '<div class="d-flex gap-2">' + btns + '</div></div>';
    }
    function section(title, icon, list, kind) { return list.length ? '<h6 class="mt-3"><i class="bi ' + icon + ' me-1"></i>' + title + ' (' + list.length + ')</h6><div class="row"><div class="col-lg-6">' + list.filter(function (_, i) { return i % 2 === 0; }).map(function (s) { return card(s, kind); }).join('') + '</div><div class="col-lg-6">' + list.filter(function (_, i) { return i % 2 === 1; }).map(function (s) { return card(s, kind); }).join('') + '</div></div>' : ''; }

    function load() {
        SMS.api(API + '?action=tasks').then(function (r) {
            if (!r.success) { box.innerHTML = '<div class="alert alert-danger">' + esc(r.message) + '</div>'; return; }
            reasons = r.reasons;
            document.getElementById('kPick').textContent = r.pickups.length; document.getElementById('kDeliv').textContent = r.deliveries.length + r.returns.length;
            document.getElementById('kDone').textContent = r.done_today; document.getElementById('kCash').textContent = SMS.money(r.cash);
            var h = section('Deliveries', 'bi-bicycle', r.deliveries, 'del') + section('Pickups', 'bi-box-seam', r.pickups, 'pick') + section('Return to sender', 'bi-arrow-return-left', r.returns, 'ret');
            box.innerHTML = h || '<div class="card-panel text-center text-muted py-5"><i class="bi bi-emoji-smile fs-1"></i><p class="mb-0 mt-2">No tasks right now. Enjoy the break!</p></div>';
        });
    }
    function post(body) { return SMS.api(API, { method: 'POST', body: body }); }

    box.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-a]'); if (!b) return;
        var t = JSON.parse(b.closest('.task').dataset.s), a = b.dataset.a; cur = t;
        if (a === 'pickup') {
            var msg = b.dataset.collect === '1' ? 'Collect ' + SMS.money(b.dataset.amt) + ' in cash from the sender, then confirm?' : 'Confirm that you have collected ' + t.tn + '?';
            return SMS.confirmBox(msg, 'Confirm pickup', 'primary').then(function (ok) { if (!ok) return; post({ action: 'pickup', id: t.id, collect: b.dataset.collect === '1' ? 1 : 0 }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) load(); }); });
        }
        if (a === 'returned') return SMS.confirmBox('Confirm that you handed ' + t.tn + ' back to the sender?', 'Confirm return', 'primary').then(function (ok) { if (ok) post({ action: 'returned', id: t.id }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) load(); }); });
        if (a === 'deliver') {
            document.getElementById('dInfo').textContent = t.tn + ' · ' + t.name; document.getElementById('dErr').textContent = ''; document.getElementById('dOtp').value = ''; document.getElementById('dName').value = t.name;
            var c = document.getElementById('dCash'); c.classList.toggle('d-none', !(t.money > 0)); c.innerHTML = '<i class="bi bi-cash-coin me-1"></i>Collect <b>' + SMS.money(t.money) + '</b> before confirming.'; mD.show();
        }
        if (a === 'fail') { document.getElementById('fReason').innerHTML = reasons.map(function (x) { return '<option>' + esc(x) + '</option>'; }).join(''); mF.show(); }
    });
    document.getElementById('dGo').addEventListener('click', function () {
        post({ action: 'deliver', id: cur.id, otp: document.getElementById('dOtp').value.trim(), received_by: document.getElementById('dName').value.trim() }).then(function (r) {
            if (r.success) { mD.hide(); SMS.toast(r.message, 'success'); load(); } else { document.getElementById('dErr').textContent = r.message; }
        });
    });
    document.getElementById('fGo').addEventListener('click', function () {
        post({ action: 'fail', id: cur.id, reason: document.getElementById('fReason').value }).then(function (r) { mF.hide(); SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) load(); });
    });
    document.getElementById('refresh').addEventListener('click', load);
    load();
})();
