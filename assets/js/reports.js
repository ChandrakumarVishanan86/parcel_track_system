(function () {
    'use strict';
    var esc = SMS.esc, $ = function (id) { return document.getElementById(id); }, P = SMS.palette, charts = {};
    function chart(id, cfg) { if (charts[id]) charts[id].destroy(); charts[id] = new Chart($(id), cfg); }
    function kpi(c, ico, label, val, sub) { return '<div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-' + c + '"><i class="bi ' + ico + ' kpi-ico"></i><span class="kpi-label">' + label + '</span><span class="kpi-num" style="font-size:' + (val.length > 9 ? '1.15rem' : '1.6rem') + '">' + val + '</span><span class="kpi-sub">' + (sub || '&nbsp;') + '</span></div></div>'; }
    function pie(id, rows) { chart(id, { type: 'doughnut', data: { labels: rows.map(function (x) { return x.k; }), datasets: [{ data: rows.map(function (x) { return +x.n; }), backgroundColor: P, borderWidth: 0 }] }, options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom' } } } }); }
    function links() { var q = 'from=' + $('rFrom').value + '&to=' + $('rTo').value; $('exShip').href = APP.base + '/reports/export?type=shipments&' + q; $('exCod').href = APP.base + '/reports/export?type=cod&' + q; }
    function pct(a, b) { return b > 0 ? Math.round(1000 * a / b) / 10 + '%' : '–'; }
    function run() {
        links();
        SMS.api(location.pathname + '?action=data&from=' + $('rFrom').value + '&to=' + $('rTo').value).then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            var t = r.totals;
            $('kp').innerHTML = kpi('blue', 'bi-box-seam', 'Parcels booked', String(t.booked), (t.cancelled || 0) + ' cancelled') + kpi('green', 'bi-check2-circle', 'Delivered', String(t.delivered || 0), 'Success ' + (t.success_pct === null ? '–' : t.success_pct + '%')) +
                kpi('lime', 'bi-stopwatch', 'On-time delivery', t.on_time_pct === null ? '–' : t.on_time_pct + '%', (t.on_time || 0) + ' on time') + kpi('violet', 'bi-cash-stack', 'Revenue (incl. VAT)', SMS.money(t.revenue), 'Avg ' + SMS.money(t.avg_value)) +
                kpi('amber', 'bi-cash-coin', 'COD delivered', SMS.money(t.cod_collected), '') + kpi('teal', 'bi-speedometer2', 'Weight moved', Number(t.kg).toLocaleString() + ' kg', 'chargeable');
            chart('chDaily', { data: { labels: r.daily.map(function (d) { return SMS.fdate(d.d).slice(0, 6); }), datasets: [
                { type: 'bar', label: 'Booked', data: r.daily.map(function (d) { return +d.booked; }), backgroundColor: P[0], borderRadius: 4, yAxisID: 'y' },
                { type: 'bar', label: 'Delivered', data: r.daily.map(function (d) { return +d.delivered; }), backgroundColor: P[1], borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Revenue', data: r.daily.map(function (d) { return +d.revenue; }), borderColor: P[2], tension: .35, yAxisID: 'y1', pointRadius: 2 }] },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } } });
            pie('chStatus', r.status); pie('chSvc', r.service); pie('chZone', r.zone);
            chart('chReason', { type: 'bar', data: { labels: r.reasons.map(function (x) { return x.k; }), datasets: [{ data: r.reasons.map(function (x) { return +x.n; }), backgroundColor: P[6], borderRadius: 4 }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } } });
            $('tBr').innerHTML = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Branch</th><th class="text-end">Sent</th><th class="text-end">Delivered in</th><th class="text-end">Revenue</th></tr></thead><tbody>' + r.branches.map(function (b) { return '<tr><td>' + esc(b.city) + '</td><td class="text-end mono">' + b.sent + '</td><td class="text-end mono">' + b.delivered + '</td><td class="text-end mono">' + SMS.money(b.revenue) + '</td></tr>'; }).join('') + '</tbody></table></div>';
            $('tRd').innerHTML = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Rider</th><th class="text-end">Pickups</th><th class="text-end">Delivered</th><th class="text-end">On-time</th><th class="text-end">Failed attempts</th></tr></thead><tbody>' + r.riders.map(function (b) { return '<tr><td>' + esc(b.full_name) + '<div class="small text-muted">' + esc(b.city || '') + '</div></td><td class="text-end mono">' + b.pickups + '</td><td class="text-end mono">' + b.delivered + '</td><td class="text-end mono">' + pct(+b.on_time, +b.delivered) + '</td><td class="text-end mono">' + b.failed + '</td></tr>'; }).join('') + '</tbody></table></div>';
        });
    }
    $('rf').addEventListener('submit', function (e) { e.preventDefault(); run(); });
    document.querySelectorAll('[data-days]').forEach(function (b) { b.addEventListener('click', function () { var d = new Date(); $('rTo').value = d.toISOString().slice(0, 10); d.setDate(d.getDate() - (+b.dataset.days - 1)); $('rFrom').value = d.toISOString().slice(0, 10); run(); }); });
    ['rFrom', 'rTo'].forEach(function (i) { $(i).addEventListener('change', links); });
    run();
})();
