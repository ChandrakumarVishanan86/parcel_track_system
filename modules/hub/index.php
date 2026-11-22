<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::OPS);
$pageTitle = 'Hub operations'; $pageSub = 'Scan in pickups, build linehaul manifests, receive trucks'; $activeNav = 'hub'; $pageScripts = ['assets/js/hub.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$branches = db()->query('SELECT id, city, is_hub FROM branches WHERE is_active = 1 ORDER BY city')->fetchAll();
$fixed = $user['role'] === 'hub';
$mine = (int) ($user['branch_id'] ?? 0) ?: (int) $branches[0]['id'];
?>
<div class="card-panel mb-3 d-flex flex-wrap align-items-center gap-3">
    <div><div class="small text-muted text-uppercase fw-bold" style="letter-spacing:.08em">Working branch</div>
<?php if ($fixed): ?>
        <?php foreach ($branches as $b) { if ((int) $b['id'] === $mine) { echo '<div class="fs-5 fw-bold" style="font-family:var(--head)"><i class="bi bi-building me-1"></i>' . e($b['city']) . '</div>'; } } ?>
        <input type="hidden" id="branch" value="<?= $mine ?>">
<?php else: ?>
        <select class="form-select" id="branch"><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"<?= (int) $b['id'] === $mine ? ' selected' : '' ?>><?= e($b['city']) ?><?= $b['is_hub'] ? ' (hub)' : '' ?></option><?php endforeach; ?></select>
<?php endif; ?>
    </div>
    <div class="row g-2 ms-auto flex-grow-1" style="max-width:640px">
        <div class="col-3"><div class="kpi kpi-blue py-2"><span class="kpi-label">To scan</span><span class="kpi-num" id="cScan" style="font-size:1.4rem">–</span></div></div>
        <div class="col-3"><div class="kpi kpi-violet py-2"><span class="kpi-label">To ship</span><span class="kpi-num" id="cShip" style="font-size:1.4rem">–</span></div></div>
        <div class="col-3"><div class="kpi kpi-teal py-2"><span class="kpi-label">Inbound trucks</span><span class="kpi-num" id="cIn" style="font-size:1.4rem">–</span></div></div>
        <div class="col-3"><div class="kpi kpi-amber py-2"><span class="kpi-label">To deliver</span><span class="kpi-num" id="cDel" style="font-size:1.4rem">–</span></div></div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4"><div class="card-panel h-100"><h6><i class="bi bi-upc-scan me-1"></i>Scan in picked-up parcels</h6>
        <textarea class="form-control scanbox" id="scanTns" placeholder="Scan or type tracking numbers - one per line"></textarea>
        <button class="btn btn-gradient w-100 mt-2" id="btnScan"><i class="bi bi-box-arrow-in-down me-1"></i>Scan in</button>
        <div id="scanOut" class="mt-2 small"></div>
        <div class="small text-muted mt-3">Waiting to be scanned:</div><div id="toScan" class="small"></div>
    </div></div>
    <div class="col-lg-8">
        <div class="card-panel mb-3"><h6><i class="bi bi-truck me-1"></i>Ready for linehaul <span class="text-muted fw-normal text-lowercase">- pick parcels for one destination</span></h6>
            <div id="waiting"></div>
            <div class="row g-2 mt-1 align-items-end d-none" id="mkBar">
                <div class="col-md-4"><label class="form-label">Vehicle</label><input class="form-control form-control-sm" id="mkVeh" placeholder="e.g. WP LB-4521" maxlength="20"></div>
                <div class="col-md-4"><label class="form-label">Driver</label><input class="form-control form-control-sm" id="mkDrv" placeholder="Driver name" maxlength="80"></div>
                <div class="col-md-4"><button class="btn btn-gradient w-100" id="btnMk"><i class="bi bi-clipboard2-plus me-1"></i>Create manifest</button></div>
            </div>
        </div>
        <div class="card-panel"><h6><i class="bi bi-list-check me-1"></i>Manifests</h6><div id="manifests"></div></div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
