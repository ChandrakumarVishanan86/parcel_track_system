<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::DESK);
$pageTitle = 'Cash & COD'; $pageSub = 'Cash riders collected on the road - count it, match it, settle it'; $activeNav = 'cod'; $pageScripts = ['assets/js/cod.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="kpi kpi-amber"><i class="bi bi-wallet2 kpi-ico"></i><span class="kpi-label">Cash on the road</span><span class="kpi-num" id="kTotal" style="font-size:1.35rem">–</span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-blue"><i class="bi bi-bicycle kpi-ico"></i><span class="kpi-label">Riders holding cash</span><span class="kpi-num" id="kRiders">–</span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-red"><i class="bi bi-hourglass-split kpi-ico"></i><span class="kpi-label">Oldest unsettled</span><span class="kpi-num" id="kOld">–</span></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-green"><i class="bi bi-receipt kpi-ico"></i><span class="kpi-label">Settlements (60 latest)</span><span class="kpi-num" id="kHist">–</span></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="card-panel"><h6><i class="bi bi-bicycle me-1"></i>Riders</h6><div id="riders"></div></div></div>
    <div class="col-lg-5"><div class="card-panel"><h6><i class="bi bi-clock-history me-1"></i>Settlement history</h6><div id="history" style="max-height:520px;overflow:auto"></div></div></div>
</div>
<div class="modal fade" id="stModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="stTitle">Settle cash</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div id="stLines" class="mb-3"></div>
        <div class="row g-3 align-items-end"><div class="col-md-4"><div class="small text-muted text-uppercase fw-bold">Amount due</div><div class="fs-4 mono fw-bold" id="stDue">–</div></div>
        <div class="col-md-4"><label class="form-label">Cash counted (Rs.)</label><input type="number" step="0.01" min="0" class="form-control" id="stCount"></div>
        <div class="col-md-4"><label class="form-label">Note</label><input class="form-control" id="stNote" maxlength="200" placeholder="Optional"></div></div>
        <div class="small mt-2" id="stDiff"></div></div>
    <div class="modal-footer border-0"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" id="stGo"><i class="bi bi-check2-circle me-1"></i>Settle</button></div>
</div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
