(function () {
    'use strict';
    var esc = SMS.esc, $ = function (id) { return document.getElementById(id); }, P = SMS.palette;
    function set(id, v) { var e = $(id); if (e) e.textContent = v; }
    SMS.api(APP.base + '/modules/dashboard/api.php').then(function (r) {
        if (!r.success) { SMS.toast(r.message || 'Could not load the dashboard.', 'danger'); return; }
        if (window.DASH.customer) {
            var k = r.kpi; set('cTotal', k.total); set('cActive', k.active || 0); set('cOfd', +k.ofd ? k.ofd + ' out for delivery' : ''); set('cDel', k.delivered || 0); set('cOut', SMS.money(k.outstanding));
            $('recent').innerHTML = r.recent.length ? '<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tracking</th><th>To</th><th>Receiver</th><th>Status</th><th>Due</th><th class="text-end">Total</th></tr></thead><tbody>' + r.recent.map(function (s) {
                return '<tr><td><a href="' + APP.base + '/shipments/' + s.id + '"><span class="tn">' + esc(s.tracking_no) + '</span></a></td><td>' + esc(s.dest_city) + '</td><td>' + esc(s.receiver_name) + '</td><td>' + SMS.badge(s.status) + '</td><td>' + SMS.fdate(s.promised_date) + '</td><td class="text-end mono">' + SMS.money(s.total_charge) + '</td></tr>';
            }).join('') + '</tbody></table></div>' : '<div class="text-muted">No parcels yet - book your first one.</div>';
            return;
        }
        var k = r.kpi;
        set('kBooked', k.booked_today || 0); set('kDel', k.delivered_today || 0); set('kActive', k.active || 0); set('kOfd', (k.ofd || 0) + ' out for delivery'); set('kLate', k.late || 0); set('kExc', (k.exceptions || 0) + ' failed / returning');
        set('kOnTime', k.on_time_pct === null ? '–' : k.on_time_pct + '%'); set('kCod', SMS.money(k.cod_road)); set('rev', 'Revenue today ' + SMS.money(k.revenue_today) + '  ·  month ' + SMS.money(k.revenue_month));
        if (window.Chart) {
        new Chart($('chTrend'), { type: 'line', data: { labels: r.trend.map(function (d) { return SMS.fdate(d.day).slice(0, 6); }), datasets: [
            { label: 'Booked', data: r.trend.map(function (d) { return +d.booked; }), borderColor: P[0], backgroundColor: P[0] + '22', fill: true, tension: .35 },
            { label: 'Delivered', data: r.trend.map(function (d) { return +d.delivered; }), borderColor: P[1], backgroundColor: P[1] + '22', fill: true, tension: .35 }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
        new Chart($('chPipe'), { type: 'doughnut', data: { labels: r.pipeline.map(function (x) { return x.k; }), datasets: [{ data: r.pipeline.map(function (x) { return +x.n; }), backgroundColor: P, borderWidth: 0 }] }, options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } } });
        new Chart($('chBr'), { type: 'bar', data: { labels: r.branches.map(function (b) { return b.city; }), datasets: [{ data: r.branches.map(function (b) { return +b.n; }), backgroundColor: P[2], borderRadius: 6 }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } } });
        } else { SMS.toast('Charts could not load (Chart.js CDN blocked or offline). Check your internet connection.', 'warning'); }
        $('attn').innerHTML = r.attention.length ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>' + r.attention.map(function (s) {
            return '<tr><td><a href="' + APP.base + '/shipments/' + s.id + '"><span class="tn">' + esc(s.tracking_no) + '</span></a></td><td>' + esc(s.dest_city) + '</td><td>' + SMS.badge(s.status) + '</td><td class="small text-muted">' + (s.fail_reason ? esc(s.fail_reason) + ' · ' : '') + 'due ' + SMS.fdate(s.promised_date) + '</td></tr>';
        }).join('') + '</tbody></table></div>' : '<div class="text-muted small">Nothing needs attention. 🎉</div>';
    }).catch(function (err) { SMS.toast('Dashboard error: ' + err.message, 'danger'); });
})();
