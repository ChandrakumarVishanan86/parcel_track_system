/* Manifest detail: dispatch / cancel / receive */
(function () {
    'use strict';
    var esc = SMS.esc, root = document.getElementById('mf'), id = root.dataset.id, API = APP.base + '/hub/api';
    function post(b) { return SMS.api(API, { method: 'POST', body: Object.assign({ id: id }, b) }); }
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]'); if (!b) return;
        var a = b.dataset.act;
        SMS.confirmBox(a === 'dispatch' ? 'Dispatch this truck? Parcels will be marked In Transit and receivers notified.' : 'Cancel this manifest and release its parcels?', a === 'dispatch' ? 'Dispatch' : 'Cancel manifest', a === 'dispatch' ? 'primary' : 'danger').then(function (ok) {
            if (ok) post({ action: a }).then(function (r) { if (r.success) SMS.reloadWith(r.message, 'success'); else SMS.toast(r.message, 'danger'); });
        });
    });
    var rcv = document.getElementById('btnRcv'); if (!rcv) return;
    document.getElementById('btnAll').addEventListener('click', function () { document.getElementById('rcvTns').value = (window.MF.open || []).join('\n'); });
    rcv.addEventListener('click', function () {
        var v = document.getElementById('rcvTns').value.trim(); if (!v) { SMS.toast('Scan at least one tracking number.', 'warning'); return; }
        post({ action: 'receive', tns: v }).then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            document.getElementById('rcvOut').innerHTML = r.results.map(function (x) { return '<div class="' + (x.ok ? 'text-success' : 'text-danger') + '"><span class="mono">' + esc(x.tn) + '</span> - ' + esc(x.message) + '</div>'; }).join('');
            SMS.reloadWith(r.message, 'success');
        });
    });
})();
