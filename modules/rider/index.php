<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['rider']);
$pageTitle = 'My tasks'; $pageSub = 'Hello ' . explode(' ', $user['full_name'])[0] . ' - pickups and deliveries assigned to you'; $activeNav = 'rider'; $pageScripts = ['assets/js/rider.js'];
$pageActions = '<button class="btn btn-soft" id="refresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="kpi kpi-blue"><i class="bi bi-box-seam kpi-ico"></i><span class="kpi-label">Pickups</span><span class="kpi-num" id="kPick">–</span></div></div>
    <div class="col-6 col-md-3"><div class="kpi kpi-amber"><i class="bi bi-bicycle kpi-ico"></i><span class="kpi-label">Deliveries</span><span class="kpi-num" id="kDeliv">–</span></div></div>
    <div class="col-6 col-md-3"><div class="kpi kpi-green"><i class="bi bi-check2-all kpi-ico"></i><span class="kpi-label">Done today</span><span class="kpi-num" id="kDone">–</span></div></div>
    <div class="col-6 col-md-3"><div class="kpi kpi-lime"><i class="bi bi-cash-coin kpi-ico"></i><span class="kpi-label">Cash on hand</span><span class="kpi-num" id="kCash" style="font-size:1.3rem">–</span><span class="kpi-sub">hand over to dispatcher</span></div></div>
</div>
<div id="tasks"></div>

<div class="modal fade" id="mDeliver" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Complete delivery</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="small text-muted mb-2" id="dInfo"></div><div class="alert alert-warning py-2 d-none" id="dCash"></div>
        <label class="form-label">4-digit delivery code from receiver</label><input class="form-control form-control-lg text-center mono" id="dOtp" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••">
        <label class="form-label mt-3">Received by (name)</label><input class="form-control" id="dName" maxlength="120"><div class="text-danger small mt-2" id="dErr"></div></div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" id="dGo">Confirm delivery</button></div></div></div></div>
<div class="modal fade" id="mFail" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Delivery failed</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">Reason</label><select class="form-select" id="fReason"></select><div class="small text-muted mt-2">After <?= MAX_ATTEMPTS ?> failed attempts the parcel returns to the sender.</div></div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" id="fGo">Record failure</button></div></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
