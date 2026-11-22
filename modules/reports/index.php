<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::DESK);
if (($_GET['action'] ?? '') === 'data') {
    $from = (string) ($_GET['from'] ?? ''); $to = (string) ($_GET['to'] ?? '');
    if (!valid_date($from) || !valid_date($to) || $from > $to) { json_response(['success' => false, 'message' => 'Choose a valid date range.'], 422); }
    if ((strtotime($to) - strtotime($from)) > 366 * 86400) { json_response(['success' => false, 'message' => 'Range is limited to one year.'], 422); }
    json_response(['success' => true] + ReportModel::summary($from, $to));
}
$pageTitle = 'Reports'; $pageSub = 'Volume, revenue, service quality and team performance'; $activeNav = 'reports'; $libs = ['chart']; $pageScripts = ['assets/js/reports.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel mb-3"><form class="row g-2 align-items-end" id="rf">
    <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control" id="rFrom" value="<?= date('Y-m-d', strtotime('-29 days')) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control" id="rTo" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-auto d-flex gap-1"><button class="btn btn-sm btn-soft" type="button" data-days="7">7 d</button><button class="btn btn-sm btn-soft" type="button" data-days="30">30 d</button><button class="btn btn-sm btn-soft" type="button" data-days="90">90 d</button></div>
    <div class="col-auto"><button class="btn btn-gradient" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Run</button></div>
    <div class="col-auto ms-md-auto d-flex gap-2"><a class="btn btn-soft" id="exShip" href="#"><i class="bi bi-filetype-csv me-1"></i>Shipments CSV</a><a class="btn btn-soft" id="exCod" href="#"><i class="bi bi-filetype-csv me-1"></i>Settlements CSV</a></div>
</form></div>
<div class="row g-3 mb-3" id="kp"></div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card-panel h-100"><h6>Bookings, deliveries and revenue</h6><div style="height:280px"><canvas id="chDaily"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card-panel h-100"><h6>Outcome by status</h6><div style="height:280px"><canvas id="chStatus"></canvas></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card-panel h-100"><h6>Service mix</h6><div style="height:230px"><canvas id="chSvc"></canvas></div></div></div>
    <div class="col-md-4"><div class="card-panel h-100"><h6>Zone mix</h6><div style="height:230px"><canvas id="chZone"></canvas></div></div></div>
    <div class="col-md-4"><div class="card-panel h-100"><h6>Failed delivery reasons</h6><div style="height:230px"><canvas id="chReason"></canvas></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card-panel h-100"><h6>Branch throughput</h6><div id="tBr"></div></div></div>
    <div class="col-lg-6"><div class="card-panel h-100"><h6>Rider performance</h6><div id="tRd"></div></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
