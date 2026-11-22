<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-blue"><i class="bi bi-box-seam kpi-ico"></i><span class="kpi-label">Booked today</span><span class="kpi-num" id="kBooked">–</span></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-green"><i class="bi bi-check2-circle kpi-ico"></i><span class="kpi-label">Delivered today</span><span class="kpi-num" id="kDel">–</span></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-teal"><i class="bi bi-truck kpi-ico"></i><span class="kpi-label">In the network</span><span class="kpi-num" id="kActive">–</span><span class="kpi-sub" id="kOfd"></span></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-red"><i class="bi bi-exclamation-triangle kpi-ico"></i><span class="kpi-label">Running late</span><span class="kpi-num" id="kLate">–</span><span class="kpi-sub" id="kExc"></span></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-lime"><i class="bi bi-stopwatch kpi-ico"></i><span class="kpi-label">On-time (30 d)</span><span class="kpi-num" id="kOnTime">–</span></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="kpi kpi-amber"><i class="bi bi-wallet2 kpi-ico"></i><span class="kpi-label">Cash on road</span><span class="kpi-num" id="kCod" style="font-size:1.2rem;padding-top:6px">–</span></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card-panel h-100"><div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">Last 14 days</h6><span class="small text-muted" id="rev"></span></div><div style="height:260px"><canvas id="chTrend"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card-panel h-100"><h6>Pipeline now</h6><div style="height:260px"><canvas id="chPipe"></canvas></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card-panel h-100"><h6>Parcels heading to each branch</h6><div style="height:260px"><canvas id="chBr"></canvas></div></div></div>
    <div class="col-lg-7"><div class="card-panel h-100"><div class="d-flex justify-content-between"><h6>Needs attention</h6><a class="small" href="<?= url('shipments?status=late') ?>">View late parcels</a></div><div id="attn"></div></div></div>
</div>
