/* Public tracking page */
(function () {
    'use strict';
    var esc = SMS.esc, out = document.getElementById('trkOut'), input = document.getElementById('trkNo');
    var STEPS = [['Booked', 'bi-pencil-square'], ['Picked up', 'bi-box-seam'], ['Origin hub', 'bi-building'], ['In transit', 'bi-truck'], ['Out for delivery', 'bi-bicycle'], ['Delivered', 'bi-check2-circle']];
    var NEXT = { 'Booked': 'Waiting for a rider to collect the parcel.', 'Pickup Assigned': 'A rider has been assigned to collect your parcel.', 'Picked Up': 'Collected - on its way to the sorting hub.', 'At Origin Hub': 'Sorted at the origin hub, waiting for the next truck.', 'In Transit': 'On the truck to the destination city.', 'At Destination Hub': 'Arrived in the destination city - delivery will be scheduled.', 'Out for Delivery': 'With the delivery rider - keep your phone nearby for the OTP.', 'Delivered': 'Delivered successfully.', 'Delivery Failed': 'We could not deliver today. A new attempt will be scheduled.', 'Returning': 'Returning to the sender after unsuccessful attempts.', 'Returned': 'Returned to the sender.', 'Cancelled': 'This booking was cancelled.' };

    function render(d) {
        var bad = d.exception;
        var steps = STEPS.map(function (s, i) {
            var cls = i < d.stage ? 'done' : (i === d.stage ? 'now' : '');
            if (d.status === 'Delivered') cls = 'done';
            if (d.status === 'Cancelled') cls = i === 0 ? 'now' : '';
            return '<div class="st ' + cls + '"><div class="dot"><i class="bi ' + (cls === 'done' ? 'bi-check-lg' : s[1]) + '"></i></div>' + s[0] + '</div>';
        }).join('');
        var pct = d.status === 'Delivered' ? 100 : Math.min(92, 4 + d.stage * 18);
        var tl = d.events.map(function (e) {
            return '<li class="' + (e.status === 'Delivery Failed' ? 'fail' : '') + '"><div class="tl-h">' + esc(e.status) + '</div><div class="tl-s">' + SMS.fdatetime(e.at) + (e.location ? ' · ' + esc(e.location) : '') + (e.note ? '<br>' + esc(e.note) : '') + '</div></li>';
        }).join('');
        out.innerHTML =
            '<div class="card-panel mb-3"><div class="d-flex flex-wrap align-items-center gap-2 mb-2"><span class="tn fs-6">' + esc(d.tracking_no) + '</span>' + SMS.badge(d.status) +
            (d.is_late ? '<span class="badge badge-soft-danger">Running late</span>' : '') + '<span class="ms-auto small text-muted">' + esc(d.service) + ' · ' + esc(d.zone) + '</span></div>' +
            '<p class="mb-3 fw-medium">' + esc(NEXT[d.status] || '') + '</p>' +
            '<div class="route"><div class="city">' + esc(d.origin) + '<small>Origin</small></div><div class="line" style="--p:' + pct + '%"><span class="pk"><i class="bi bi-box-seam-fill"></i></span></div><div class="city text-end">' + esc(d.destination) + '<small>Destination</small></div></div>' +
            '<div class="stepper mt-4' + (bad ? ' bad' : '') + '">' + steps + '</div></div>' +
            '<div class="row g-3"><div class="col-md-5"><div class="card-panel h-100"><h6>Shipment</h6><dl class="kv mb-0">' +
            '<dt>Receiver</dt><dd class="mono">' + esc(d.receiver) + '</dd><dt>Estimated</dt><dd>' + (d.status === 'Delivered' ? 'Delivered ' + SMS.fdate(d.delivered_at) : SMS.fdate(d.promised_date)) + '</dd>' +
            '<dt>Pieces</dt><dd>' + d.pieces + '</dd><dt>Weight</dt><dd>' + d.weight_kg.toFixed(1) + ' kg</dd><dt>Cash on delivery</dt><dd>' + (d.cod ? 'Yes - keep payment ready' : 'No') + '</dd></dl></div></div>' +
            '<div class="col-md-7"><div class="card-panel h-100"><h6>Journey</h6><ul class="timeline">' + tl + '</ul></div></div></div>';
        out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function lookup(n) {
        if (!n.trim()) { return; }
        out.innerHTML = '<div class="card-panel text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Looking up…</div>';
        SMS.api(APP.base + '/track/api?n=' + encodeURIComponent(n)).then(function (r) {
            if (!r.success) { out.innerHTML = '<div class="card-panel text-center py-4"><i class="bi bi-search fs-2 text-muted"></i><p class="fw-semibold mb-0 mt-2">' + esc(r.message) + '</p></div>'; return; }
            history.replaceState(null, '', APP.base + '/track?n=' + encodeURIComponent(r.data.tracking_no));
            render(r.data);
        });
    }
    document.getElementById('trkForm').addEventListener('submit', function (e) { e.preventDefault(); lookup(input.value); });
    if (input.value) lookup(input.value);
})();
