/* Dispatch board: assign pickup / delivery riders, grouped by branch */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/shipments/api', riders = {};

    function getRiders(branch) {
        if (riders[branch]) return Promise.resolve(riders[branch]);
        return SMS.api(API + '?action=riders&branch_id=' + branch).then(function (r) { riders[branch] = r.data || []; return riders[branch]; });
    }
    function load(statuses) {
        return Promise.all(statuses.map(function (s) { return SMS.api(API + '?action=list&status=' + encodeURIComponent(s)); })).then(function (res) {
            return [].concat.apply([], res.map(function (r) { return r.data || []; }));
        });
    }
    function group(rows, key) { var g = {}; rows.forEach(function (r) { (g[r[key]] = g[r[key]] || []).push(r); }); return g; }

    function board(el, rows, o) {
        if (!rows.length) { el.innerHTML = '<div class="card-panel text-center text-muted py-5"><i class="bi bi-check2-all fs-1"></i><p class="mb-0 mt-2">' + o.empty + '</p></div>'; return; }
        var g = group(rows, o.branchKey), html = '';
        Object.keys(g).forEach(function (b) {
            var list = g[b], city = list[0][o.cityKey];
            html += '<div class="card-panel mb-3 dgroup" data-branch="' + b + '"><div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h6 class="mb-0"><i class="bi bi-geo-alt-fill me-1"></i>' + esc(city) + ' <span class="text-muted">· ' + list.length + ' parcel' + (list.length > 1 ? 's' : '') + '</span></h6>' +
                '<div class="ms-auto d-flex gap-2"><select class="form-select form-select-sm rsel" style="min-width:210px"><option>Loading riders…</option></select><button class="btn btn-sm btn-gradient assign" disabled><i class="bi bi-send me-1"></i>' + o.btn + '</button></div></div>' +
                '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input all"></th><th>Tracking</th><th>' + o.col + '</th><th>Service</th><th>Status</th><th>Due</th><th class="text-end">COD</th></tr></thead><tbody>' +
                list.map(function (r) {
                    return '<tr><td><input type="checkbox" class="form-check-input pick" value="' + r.id + '"></td><td><a href="' + APP.base + '/shipments/' + r.id + '"><span class="tn">' + esc(r.tracking_no) + '</span></a></td><td>' + esc(o.who(r)) + '<div class="small text-muted">' + esc(o.addr(r)) + '</div></td><td>' + esc(r.service) + '</td><td>' + SMS.badge(r.status) + (r.is_late == 1 ? ' <span class="badge badge-soft-danger">Late</span>' : '') + (r.attempts > 0 ? ' <span class="small text-muted">' + r.attempts + ' failed</span>' : '') + (o.rider ? '<div class="small text-muted">' + esc(r[o.rider] || '') + '</div>' : '') + '</td><td>' + SMS.fdate(r.promised_date) + '</td><td class="text-end mono">' + (r.cod_amount > 0 ? SMS.money(r.cod_amount) : '–') + '</td></tr>';
                }).join('') + '</tbody></table></div></div>';
        });
        el.innerHTML = html;
        el.querySelectorAll('.dgroup').forEach(function (card) {
            var sel = card.querySelector('.rsel'), btn = card.querySelector('.assign');
            getRiders(card.dataset.branch).then(function (rs) {
                sel.innerHTML = rs.length ? rs.map(function (r) { return '<option value="' + r.id + '"' + (r.load >= r.max ? ' disabled' : '') + '>' + esc(r.full_name) + ' · ' + r.load + '/' + r.max + ' tasks</option>'; }).join('') : '<option value="">No riders in this branch</option>';
            });
            function sync() { btn.disabled = !card.querySelectorAll('.pick:checked').length || !sel.value; }
            card.addEventListener('change', function (e) { if (e.target.classList.contains('all')) card.querySelectorAll('.pick').forEach(function (c) { c.checked = e.target.checked; }); sync(); });
            btn.addEventListener('click', function () {
                var ids = [].map.call(card.querySelectorAll('.pick:checked'), function (c) { return +c.value; });
                btn.disabled = true;
                SMS.api(API, { method: 'POST', body: { action: o.action, ids: ids, rider_id: +sel.value } }).then(function (r) { if (r.success) SMS.reloadWith(r.message, 'success'); else { SMS.toast(r.message, 'danger'); btn.disabled = false; } });
            });
        });
    }

    Promise.all([load(['Booked', 'Pickup Assigned']), load(['At Destination Hub', 'Delivery Failed', 'Returning'])]).then(function (all) {
        var pick = all[0], del = all[1];
        var needDel = del.filter(function (r) { return r.status !== 'Returning' || !r.delivery_rider_id; });
        var exc = del.filter(function (r) { return r.status === 'Delivery Failed' || r.status === 'Returning'; });
        document.getElementById('cPick').textContent = pick.length;
        document.getElementById('cDeliv').textContent = del.filter(function (r) { return r.status === 'At Destination Hub'; }).length;
        document.getElementById('cExc').textContent = exc.length;
        board(document.getElementById('listPick'), pick, { branchKey: 'origin_branch_id', cityKey: 'origin_city', col: 'Sender / pickup address', who: function (r) { return r.sender_name; }, addr: function (r) { return r.sender_address; }, action: 'assign_pickup', btn: 'Assign pickup', empty: 'No parcels waiting for pickup.', rider: 'pickup_rider' });
        board(document.getElementById('listDeliv'), del.filter(function (r) { return r.status === 'At Destination Hub'; }), { branchKey: 'dest_branch_id', cityKey: 'dest_city', col: 'Receiver / delivery address', who: function (r) { return r.receiver_name; }, addr: function (r) { return r.receiver_address; }, action: 'assign_delivery', btn: 'Send out for delivery', empty: 'Nothing is waiting at a destination hub.' });
        board(document.getElementById('listExc'), exc, { branchKey: 'dest_branch_id', cityKey: 'dest_city', col: 'Receiver / reason', who: function (r) { return r.receiver_name; }, addr: function (r) { return r.status === 'Returning' ? 'Return to sender' : (r.fail_reason || ''); }, action: 'assign_delivery', btn: 'Re-assign rider', empty: 'No exceptions - all deliveries are on track.', rider: 'delivery_rider' });
    });
})();
