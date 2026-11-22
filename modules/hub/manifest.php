<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::OPS);
$m = ManifestModel::find((int) ($_GET['id'] ?? 0));
if (!$m || !ManifestModel::canAccess($user, $m)) { flash('danger', 'Manifest not found.'); redirect('hub'); }
$items = ManifestModel::items((int) $m['id']);
$pageTitle = $m['manifest_no']; $pageSub = $m['from_city'] . ' → ' . $m['to_city'] . ' · ' . $m['parcels'] . ' parcels'; $activeNav = 'hub'; $pageScripts = ['assets/js/manifest.js'];
$pageActions = '<a class="btn btn-soft" href="' . url('hub') . '"><i class="bi bi-arrow-left me-1"></i>Hub</a>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
$canAct = $user['role'] !== 'hub' || (int) $user['branch_id'] === (int) $m['from_branch_id'];
$canRecv = $user['role'] !== 'hub' || (int) $user['branch_id'] === (int) $m['to_branch_id'];
?>
<div class="row g-3" id="mf" data-id="<?= (int) $m['id'] ?>">
    <div class="col-lg-4">
        <div class="card-panel mb-3"><h6>Manifest</h6>
            <div class="route"><div class="city"><?= e($m['from_city']) ?></div><div class="line" style="--p:<?= $m['status'] === 'Received' ? 100 : ($m['status'] === 'Dispatched' ? 55 : 5) ?>%"><span class="pk"><i class="bi bi-truck"></i></span></div><div class="city text-end"><?= e($m['to_city']) ?></div></div>
            <dl class="kv mt-3 mb-0"><dt>Status</dt><dd><?= status_badge($m['status']) ?></dd><dt>Vehicle</dt><dd><?= e($m['vehicle_no'] ?: '–') ?></dd><dt>Driver</dt><dd><?= e($m['driver_name'] ?: '–') ?></dd>
                <dt>Dispatched</dt><dd><?= e(fdatetime($m['dispatched_at'] ?? null)) ?></dd><dt>Received</dt><dd><?= e(fdatetime($m['received_at'] ?? null)) ?></dd><dt>Progress</dt><dd><?= (int) $m['received_count'] ?> / <?= (int) $m['parcels'] ?> received</dd></dl>
            <div class="d-grid gap-2 mt-3">
<?php if ($m['status'] === 'Open' && $canAct): ?><button class="btn btn-gradient" data-act="dispatch"><i class="bi bi-send me-1"></i>Dispatch truck</button><button class="btn btn-soft-danger" data-act="cancel"><i class="bi bi-x-circle me-1"></i>Cancel manifest</button><?php endif; ?>
            </div>
        </div>
<?php if ($m['status'] === 'Dispatched' && $canRecv): ?>
        <div class="card-panel"><h6><i class="bi bi-upc-scan me-1"></i>Receive at <?= e($m['to_city']) ?></h6>
            <textarea class="form-control scanbox" id="rcvTns" placeholder="Scan tracking numbers as they come off the truck"></textarea>
            <div class="d-flex gap-2 mt-2"><button class="btn btn-gradient flex-fill" id="btnRcv">Receive scanned</button><button class="btn btn-soft" id="btnAll" title="Fill with all outstanding parcels">Select all</button></div><div id="rcvOut" class="small mt-2"></div></div>
<?php endif; ?>
    </div>
    <div class="col-lg-8"><div class="card-panel"><h6>Parcels on this manifest</h6>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tracking</th><th>Receiver</th><th>Service</th><th class="text-end">Kg</th><th>Status</th><th class="text-center">Received</th></tr></thead><tbody>
<?php foreach ($items as $i): ?>
            <tr><td><a href="<?= url('shipments/' . $i['id']) ?>"><?= tn_chip($i['tracking_no']) ?></a></td><td><?= e($i['receiver_name']) ?></td><td><?= e($i['service']) ?></td><td class="text-end mono"><?= e(number_format((float) $i['weight_kg'], 1)) ?></td><td><?= status_badge($i['status']) ?></td><td class="text-center"><?= $i['received'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-hourglass-split text-muted"></i>' ?></td></tr>
<?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div>
<?php
$pageConfig = '<script>window.MF = ' . json_encode(['open' => array_map(fn($i) => $i['tracking_no'], array_filter($items, fn($i) => !$i['received']))]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
