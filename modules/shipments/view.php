<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'dispatcher', 'hub', 'customer']);
$s = ShipmentModel::find((int) ($_GET['id'] ?? 0));
if (!$s || !ShipmentModel::canAccess($user, $s)) { http_response_code(404); flash('danger', 'Shipment not found.'); redirect('shipments'); }
$role = $user['role']; $desk = in_array($role, Auth::DESK, true); $staff = $role !== 'customer';
$events = ShipmentModel::events((int) $s['id']);
$sms = $staff ? ShipmentModel::sms((int) $s['id']) : [];
$pageTitle = $s['tracking_no']; $pageSub = $s['origin_city'] . ' → ' . $s['dest_city'] . ' · ' . $s['service']; $activeNav = 'shipments'; $pageScripts = ['assets/js/view.js'];
$pageActions = '<a class="btn btn-soft" target="_blank" href="' . url('shipments/' . $s['id'] . '/label') . '"><i class="bi bi-upc-scan me-1"></i>Label</a> <a class="btn btn-soft" target="_blank" href="' . url('shipments/' . $s['id'] . '/invoice') . '"><i class="bi bi-file-earmark-pdf me-1"></i>Waybill</a>';
require_once dirname(__DIR__, 2) . '/includes/header.php';

$stage = ShipmentModel::stage($s['status']); $exc = in_array($s['status'], ['Delivery Failed', 'Returning', 'Returned', 'Cancelled'], true);
$steps = [['Booked', 'bi-pencil-square'], ['Picked up', 'bi-box-seam'], ['Origin hub', 'bi-building'], ['In transit', 'bi-truck'], ['Out for delivery', 'bi-bicycle'], ['Delivered', 'bi-check2-circle']];
$pct = $s['status'] === 'Delivered' ? 100 : min(92, 4 + $stage * 18);
$canCancel = in_array($s['status'], ['Booked', 'Pickup Assigned'], true) && $role !== 'hub';
$canPay = $desk && $s['payment_status'] === 'Unpaid' && $s['payer'] === 'Sender' && $s['status'] !== 'Cancelled';
?>
<div class="card-panel mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <?= tn_chip($s['tracking_no']) ?> <?= status_badge($s['status']) ?>
        <?php if ($s['is_late']): ?><span class="badge badge-soft-danger">Running late</span><?php endif; ?>
        <?php if ($s['cod_amount'] > 0): ?><span class="badge badge-soft-warning">COD <?= e(money($s['cod_amount'])) ?></span><?php endif; ?>
        <?php if ($s['fragile']): ?><span class="badge badge-soft-danger">Fragile</span><?php endif; ?>
        <span class="ms-auto small text-muted">Due <?= e(fdate($s['promised_date'])) ?></span>
    </div>
    <div class="route"><div class="city"><?= e($s['origin_city']) ?><small><?= e($s['origin_name']) ?></small></div><div class="line" style="--p:<?= $pct ?>%"><span class="pk"><i class="bi bi-box-seam-fill"></i></span></div><div class="city text-end"><?= e($s['dest_city']) ?><small><?= e($s['dest_name']) ?></small></div></div>
    <div class="stepper mt-4<?= $exc && $s['status'] !== 'Delivered' ? ' bad' : '' ?>">
<?php foreach ($steps as $i => [$l, $ic]): $cls = $s['status'] === 'Delivered' || $i < $stage ? 'done' : ($i === $stage ? 'now' : ''); if ($s['status'] === 'Cancelled') { $cls = $i === 0 ? 'now' : ''; } ?>
        <div class="st <?= $cls ?>"><div class="dot"><i class="bi <?= $cls === 'done' ? 'bi-check-lg' : $ic ?>"></i></div><?= e($l) ?></div>
<?php endforeach; ?>
    </div>
<?php if ($s['status'] === 'Out for Delivery' && $s['delivery_otp'] && ($desk || $role === 'customer')): ?>
    <div class="mt-3 d-flex align-items-center gap-3 flex-wrap"><span class="small text-muted">Delivery code for the receiver:</span><span class="otp"><?= e($s['delivery_otp']) ?></span><?php if ((int) $s['otp_fails'] >= OTP_MAX_FAILS): ?><span class="badge badge-soft-danger">Locked after <?= OTP_MAX_FAILS ?> wrong entries</span><?php endif; ?></div>
<?php endif; ?>
<?php if ($s['status'] === 'Delivery Failed' && $s['fail_reason']): ?>
    <div class="alert alert-danger mt-3 mb-0 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Attempt <?= (int) $s['attempts'] ?> of <?= MAX_ATTEMPTS ?> failed: <?= e($s['fail_reason']) ?></div>
<?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card-panel h-100"><h6>Sender</h6><div class="fw-semibold"><?= e($s['sender_name']) ?></div><div class="small"><?= e($s['sender_phone']) ?></div><div class="small text-muted"><?= e($s['sender_address']) ?></div>
                <?php if ($s['customer_code']): ?><div class="mt-2"><span class="tn"><?= e($s['customer_code']) ?></span><?= $s['is_credit'] ? ' <span class="badge badge-soft-teal">Credit account</span>' : '' ?></div><?php endif; ?></div></div>
            <div class="col-md-6"><div class="card-panel h-100"><h6>Receiver</h6><div class="fw-semibold"><?= e($s['receiver_name']) ?></div><div class="small"><?= e($s['receiver_phone']) ?></div><div class="small text-muted"><?= e($s['receiver_address']) ?></div>
                <?php if ($s['received_by']): ?><div class="mt-2 small"><i class="bi bi-person-check-fill text-success me-1"></i>Received by <b><?= e($s['received_by']) ?></b></div><?php endif; ?></div></div>
        </div>
        <div class="card-panel mb-3"><h6>Parcel</h6>
            <dl class="kv kv-4 mb-0">
                <dt>Contents</dt><dd><?= e($s['description']) ?> (<?= (int) $s['pieces'] ?> pc)</dd><dt>Service</dt><dd><?= e($s['service']) ?> · <?= e($s['zone']) ?></dd>
                <dt>Actual weight</dt><dd><?= e(number_format((float) $s['weight_kg'], 2)) ?> kg</dd><dt>Chargeable</dt><dd><?= e(number_format((float) $s['chargeable_kg'], 1)) ?> kg<?= $s['length_cm'] ? ' (' . (float) $s['length_cm'] . '×' . (float) $s['width_cm'] . '×' . (float) $s['height_cm'] . ' cm)' : '' ?></dd>
                <dt>Pickup date</dt><dd><?= e(fdate($s['pickup_date'])) ?></dd><dt>Booked via</dt><dd><?= e($s['source']) ?> · <?= e(fdatetime($s['created_at'])) ?></dd>
<?php if ($staff): ?>
                <dt>Pickup rider</dt><dd><?= e($s['pickup_rider'] ?: '–') ?></dd><dt>Delivery rider</dt><dd><?= e($s['delivery_rider'] ?: '–') ?> <?= (int) $s['attempts'] ? '<span class="small text-muted">(' . (int) $s['attempts'] . ' failed attempt' . ((int) $s['attempts'] > 1 ? 's' : '') . ')</span>' : '' ?></dd>
<?php endif; ?>
<?php if ($s['notes']): ?><dt>Notes</dt><dd><?= e($s['notes']) ?></dd><?php endif; ?>
            </dl>
        </div>
        <div class="card-panel"><h6>Tracking history</h6>
            <ul class="timeline mb-0">
<?php foreach ($events as $ev): ?>
                <li class="<?= $ev['status'] === 'Delivery Failed' ? 'fail' : '' ?>"><div class="tl-h"><?= e($ev['status']) ?></div><div class="tl-s"><?= e(fdatetime($ev['created_at'])) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?><?= $ev['note'] ? '<br>' . e($ev['note']) : '' ?><?= $staff && $ev['by_name'] ? ' <span class="opacity-75">— ' . e($ev['by_name']) . '</span>' : '' ?></div></li>
<?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel mb-3"><h6>Charges</h6>
            <div class="receipt-row"><span>Freight</span><span class="mono"><?= e(money($s['freight'])) ?></span></div>
            <div class="receipt-row"><span>Fuel surcharge</span><span class="mono"><?= e(money($s['fuel'])) ?></span></div>
<?php foreach ([['fragile_fee', 'Fragile handling'], ['cod_fee', 'COD fee'], ['insurance_fee', 'Insurance']] as [$k, $l]): if ((float) $s[$k] > 0): ?>
            <div class="receipt-row"><span><?= $l ?></span><span class="mono"><?= e(money($s[$k])) ?></span></div>
<?php endif; endforeach; ?>
            <div class="receipt-row"><span>VAT (<?= TAX_PCT ?>%)</span><span class="mono"><?= e(money($s['tax'])) ?></span></div>
            <div class="receipt-total"><span>Total</span><span><?= e(money($s['total_charge'])) ?></span></div>
            <div class="mt-2 small">Paid by <b><?= e($s['payer']) ?></b> · <?= status_badge($s['payment_status']) ?><?= $s['payment_method'] ? ' <span class="text-muted">' . e($s['payment_method']) . '</span>' : '' ?></div>
        </div>
        <div class="card-panel mb-3"><h6>Actions</h6><div class="d-grid gap-2" id="acts" data-id="<?= (int) $s['id'] ?>">
<?php if ($canPay): ?>
            <div class="input-group"><select class="form-select" id="payMethod"><option>Cash</option><option>Card</option><option>Online</option><option>Bank</option></select><button class="btn btn-gradient" data-act="pay"><i class="bi bi-cash-coin me-1"></i>Record payment</button></div>
<?php endif; ?>
<?php if ($desk && in_array($s['status'], ['Booked', 'Pickup Assigned'], true)): ?><a class="btn btn-soft" href="<?= url('dispatch') ?>"><i class="bi bi-signpost-split me-1"></i>Assign pickup rider</a><?php endif; ?>
<?php if ($desk && in_array($s['status'], ['At Destination Hub', 'Delivery Failed'], true)): ?><a class="btn btn-soft" href="<?= url('dispatch') ?>"><i class="bi bi-signpost-split me-1"></i>Assign delivery rider</a><button class="btn btn-soft-danger" data-act="return"><i class="bi bi-arrow-return-left me-1"></i>Return to sender</button><?php endif; ?>
<?php if ($desk && $s['status'] === 'Out for Delivery'): ?><button class="btn btn-soft" data-act="regen_otp"><i class="bi bi-key me-1"></i>Re-issue delivery code</button><?php endif; ?>
<?php if ($canCancel): ?><button class="btn btn-soft-danger" data-act="cancel"><i class="bi bi-x-circle me-1"></i>Cancel shipment</button><?php endif; ?>
<?php if (!($canPay || $canCancel) && !($desk && in_array($s['status'], ['Booked', 'Pickup Assigned', 'At Destination Hub', 'Delivery Failed', 'Out for Delivery'], true))): ?><div class="small text-muted">No actions available for this status.</div><?php endif; ?>
        </div></div>
<?php if ($staff): ?>
        <div class="card-panel"><h6>SMS sent</h6>
<?php if (!$sms): ?><div class="small text-muted">No messages.</div><?php endif; ?>
<?php foreach ($sms as $m): ?><div class="small border-bottom py-2"><span class="text-muted"><?= e(fdatetime($m['created_at'])) ?> → <?= e($m['to_phone']) ?></span><br><?= e($m['message']) ?></div><?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
