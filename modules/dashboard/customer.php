<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="kpi kpi-blue"><i class="bi bi-boxes kpi-ico"></i><span class="kpi-label">Total parcels</span><span class="kpi-num" id="cTotal">–</span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-teal"><i class="bi bi-truck kpi-ico"></i><span class="kpi-label">On the way</span><span class="kpi-num" id="cActive">–</span><span class="kpi-sub" id="cOfd"></span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-green"><i class="bi bi-check2-circle kpi-ico"></i><span class="kpi-label">Delivered</span><span class="kpi-num" id="cDel">–</span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-amber"><i class="bi bi-receipt kpi-ico"></i><span class="kpi-label">Unpaid</span><span class="kpi-num" id="cOut" style="font-size:1.25rem;padding-top:6px">–</span></div></div>
</div>
<div class="card-panel"><div class="d-flex justify-content-between"><h6>Recent parcels</h6><a class="small" href="<?= url('shipments') ?>">See all</a></div><div id="recent"></div></div>
