<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'dispatcher', 'hub', 'customer']);
$mine = $user['role'] === 'customer';
$pageTitle = $mine ? 'My Parcels' : 'Shipments'; $pageSub = $mine ? 'Every parcel you have booked' : 'Search, filter and open any consignment';
$activeNav = 'shipments'; $libs = ['datatables']; $pageScripts = ['assets/js/shipments.js'];
$canBook = in_array($user['role'], ['admin', 'dispatcher', 'customer'], true);
$pageActions = $canBook ? '<a class="btn btn-gradient" href="' . url('shipments/new') . '"><i class="bi bi-plus-lg me-1"></i> Book parcel</a>' : '';
require_once dirname(__DIR__, 2) . '/includes/header.php';
$branches = db()->query('SELECT id, city FROM branches ORDER BY city')->fetchAll();
$statuses = ['Booked', 'Pickup Assigned', 'Picked Up', 'At Origin Hub', 'In Transit', 'At Destination Hub', 'Out for Delivery', 'Delivered', 'Delivery Failed', 'Returning', 'Returned', 'Cancelled'];
?>
<div class="card-panel mb-3">
    <form class="row g-2 align-items-end" id="flt">
        <div class="col-md-3"><label class="form-label">Search</label><input class="form-control" name="q" placeholder="Tracking no, name or phone" value="<?= e($_GET['q'] ?? '') ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All</option><option value="active"<?= ($_GET['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active (in progress)</option><option value="late"<?= ($_GET['status'] ?? '') === 'late' ? ' selected' : '' ?>>Running late</option><option value="exception"<?= ($_GET['status'] ?? '') === 'exception' ? ' selected' : '' ?>>Exceptions</option><?php foreach ($statuses as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
<?php if (!$mine): ?>
        <div class="col-6 col-md-2"><label class="form-label">Branch</label><select class="form-select" name="branch"><option value="">All</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['city']) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from"></div>
        <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to"></div>
        <div class="col-auto"><button class="btn btn-dark" type="button" id="reset" title="Reset filters"><i class="bi bi-x-lg"></i></button></div>
    </form>
</div>
<div class="card-panel">
    <div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100">
        <thead><tr><th>Tracking</th><th>Route</th><th>Receiver</th><th>Service</th><th>Status</th><th>Payment</th><th>Due</th><th class="text-end">Total</th><th class="text-end"></th></tr></thead></table></div>
</div>
<?php
$pageConfig = '<script>window.SHIP = ' . json_encode(['mine' => $mine]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
