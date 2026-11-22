/* Hub operations */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/hub/api', br = document.getElementById('branch');

    function results(el, res) {
        el.innerHTML = res.map(function (r) { return '<div class="' + (r.ok ? 'text-success' : 'text-danger') + '"><i class="bi ' + (r.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' me-1"></i><span class="mono">' + esc(r.tn) + '</span> - ' + esc(r.message) + '</div>'; }).join('');
    }
    function load() {
        SMS.api(API + '?action=overview&branch_id=' + br.value).then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            document.getElementById('cScan').textContent = r.counts.to_scan; document.getElementById('cShip').textContent = r.counts.to_ship; document.getElementById('cIn').textContent = r.counts.inbound; document.getElementById('cDel').textContent = r.counts.to_deliver;
            document.getElementById('toScan').innerHTML = r.to_scan.length ? r.to_scan.map(function (s) { return '<span class="tn me-1 mb-1">' + esc(s.tracking_no) + '</span>'; }).join('') : '<span class="text-muted">Nothing waiting.</span>';
            // waiting parcels grouped by destination
            var g = {}; r.waiting.forEach(function (w) { (g[w.dest_branch_id] = g[w.dest_branch_id] || { city: w.dest_city, rows: [] }).rows.push(w); });
            var keys = Object.keys(g), el = document.getElementById('waiting');
            el.innerHTML = keys.length ? keys.map(function (k) {
                return '<div class="mb-2 wgrp" data-to="' + k + '"><label class="d-flex align-items-center gap-2 fw-semibold"><input type="radio" name="dest" class="form-check-input mt-0" value="' + k + '"> <i class="bi bi-arrow-right-circle"></i> ' + esc(g[k].city) + ' <span class="badge badge-soft-secondary">' + g[k].rows.length + '</span></label>' +
                    '<div class="ps-4 mt-1">' + g[k].rows.map(function (w) { return '<label class="tn me-1 mb-1" style="cursor:pointer"><input type="checkbox" class="form-check-input me-1 wchk" data-to="' + k + '" value="' + w.id + '"> ' + esc(w.tracking_no) + '</label>'; }).join('') + '</div></div>';
            }).join('') : '<div class="text-muted small">No parcels are waiting for linehaul.</div>';
            document.getElementById('mkBar').classList.toggle('d-none', !keys.length);
            // manifests
            document.getElementById('manifests').innerHTML = r.manifests.length ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>No.</th><th>Route</th><th>Status</th><th class="text-center">Parcels</th><th></th></tr></thead><tbody>' + r.manifests.slice(0, 15).map(function (m) {
                return '<tr><td><a href="' + APP.base + '/hub/manifests/' + m.id + '"><span class="tn">' + esc(m.manifest_no) + '</span></a></td><td>' + esc(m.from_city) + ' → ' + esc(m.to_city) + '</td><td>' + SMS.badge(m.status) + '</td><td class="text-center">' + m.received_count + '/' + m.parcels + '</td><td class="text-end"><a class="btn btn-sm btn-soft" href="' + APP.base + '/hub/manifests/' + m.id + '">' + (m.status === 'Dispatched' ? 'Receive' : 'Open') + '</a></td></tr>';
            }).join('') + '</tbody></table></div>' : '<div class="text-muted small">No manifests yet.</div>';
        });
    }
    document.getElementById('waiting').addEventListener('change', function (e) {
        if (e.target.classList.contains('wchk')) { var radio = document.querySelector('input[name=dest][value="' + e.target.dataset.to + '"]'); radio.checked = true; document.querySelectorAll('.wchk').forEach(function (c) { if (c.dataset.to !== e.target.dataset.to) c.checked = false; }); }
        if (e.target.name === 'dest') { document.querySelectorAll('.wchk').forEach(function (c) { c.checked = c.dataset.to === e.target.value; }); }
    });
    document.getElementById('btnScan').addEventListener('click', function () {
        var v = document.getElementById('scanTns').value.trim(); if (!v) { SMS.toast('Scan or type at least one tracking number.', 'warning'); return; }
        SMS.api(API, { method: 'POST', body: { action: 'scan_in', branch_id: br.value, tns: v } }).then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            results(document.getElementById('scanOut'), r.results); SMS.toast(r.message, 'success'); document.getElementById('scanTns').value = ''; load();
        });
    });
    document.getElementById('btnMk').addEventListener('click', function () {
        var ids = [].map.call(document.querySelectorAll('.wchk:checked'), function (c) { return +c.value; }), to = (document.querySelector('input[name=dest]:checked') || {}).value;
        if (!ids.length) { SMS.toast('Select the parcels to load.', 'warning'); return; }
        SMS.api(API, { method: 'POST', body: { action: 'create_manifest', from_branch_id: br.value, to_branch_id: to, ids: ids, vehicle: document.getElementById('mkVeh').value, driver: document.getElementById('mkDrv').value } }).then(function (r) {
            if (r.success) { try { sessionStorage.setItem('sms_toast', JSON.stringify({ m: r.message, t: 'success' })); } catch (x) {} location.href = APP.base + '/hub/manifests/' + r.id; } else SMS.toast(r.message, 'danger');
        });
    });
    br.addEventListener('change', load);
    load();
})();
