<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'dispatcher', 'customer']);
$isCust = $user['role'] === 'customer';
$pageTitle = 'Book a parcel'; $pageSub = $isCust ? 'Schedule a pickup - a rider will collect it from you' : 'Counter booking'; $activeNav = 'book';
$libs = ['flatpickr']; $pageScripts = ['assets/js/book.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$branches = db()->query('SELECT id, city FROM branches WHERE is_active = 1 ORDER BY city')->fetchAll();
$cust = null;
if ($isCust) { $c = db()->prepare('SELECT * FROM customers WHERE id = :id'); $c->execute([':id' => $user['customer_id']]); $cust = $c->fetch(); }
$opts = static function (int $sel = 0) use ($branches): string { $h = '<option value="">Choose city…</option>'; foreach ($branches as $b) { $h .= '<option value="' . $b['id'] . '"' . ($b['id'] === $sel ? ' selected' : '') . '>' . e($b['city']) . '</option>'; } return $h; };
?>
<form id="bookForm" novalidate>
<div class="row g-3">
    <div class="col-lg-8">
<?php if (!$isCust): ?>
        <div class="card-panel mb-3 position-relative">
            <h6>Customer account <span class="text-muted text-lowercase fw-normal">(optional - walk-in if empty)</span></h6>
            <input type="hidden" name="customer_id" id="customer_id">
            <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="custSearch" placeholder="Search by name, company, phone or code…" autocomplete="off"><button class="btn btn-dark d-none" type="button" id="custClear">Clear</button></div>
            <div class="list-group position-absolute shadow d-none" id="custResults" style="z-index:30;left:18px;right:18px"></div>
            <div class="small mt-2 d-none" id="custPicked"></div>
        </div>
<?php endif; ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card-panel h-100"><h6><i class="bi bi-box-arrow-up me-1"></i>Sender (pickup)</h6>
<?php if ($isCust): ?>
                <div class="fw-semibold"><?= e($cust['company'] ?: $cust['full_name']) ?></div><div class="small text-muted mb-2"><?= e($cust['phone']) ?></div>
                <label class="form-label">Pickup address</label><input class="form-control" name="sender_address" maxlength="255" value="<?= e($cust['address']) ?>" required>
<?php else: ?>
                <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="sender_name" maxlength="120" required></div>
                <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="sender_phone" maxlength="20" inputmode="tel" required></div>
                <div><label class="form-label">Pickup address</label><input class="form-control" name="sender_address" maxlength="255" required></div>
<?php endif; ?>
                <div class="mt-2"><label class="form-label">Pickup city</label><select class="form-select" name="origin_branch_id" required><?= $opts((int) ($cust['branch_id'] ?? 0)) ?></select></div>
            </div></div>
            <div class="col-md-6"><div class="card-panel h-100"><h6><i class="bi bi-box-arrow-in-down me-1"></i>Receiver (delivery)</h6>
                <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="receiver_name" maxlength="120" required></div>
                <div class="mb-2"><label class="form-label">Phone <span class="text-muted fw-normal">(gets the delivery OTP)</span></label><input class="form-control" name="receiver_phone" maxlength="20" inputmode="tel" required></div>
                <div class="mb-2"><label class="form-label">Delivery address</label><input class="form-control" name="receiver_address" maxlength="255" required></div>
                <div><label class="form-label">Delivery city</label><select class="form-select" name="dest_branch_id" required><?= $opts() ?></select></div>
            </div></div>
        </div>
        <div class="card-panel mb-3"><h6><i class="bi bi-box-seam me-1"></i>Parcel</h6>
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label">Contents</label><input class="form-control" name="description" maxlength="160" placeholder="e.g. Mobile phone, documents, clothes" required></div>
                <div class="col-md-4"><label class="form-label">Pieces</label><input class="form-control" type="number" name="pieces" min="1" max="50" value="1" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Weight (kg)</label><input class="form-control" type="number" step="0.1" min="0.1" max="<?= MAX_WEIGHT_KG ?>" name="weight_kg" required></div>
                <div class="col-6 col-md-3"><label class="form-label">L (cm)</label><input class="form-control" type="number" min="1" max="200" name="length_cm"></div>
                <div class="col-6 col-md-3"><label class="form-label">W (cm)</label><input class="form-control" type="number" min="1" max="200" name="width_cm"></div>
                <div class="col-6 col-md-3"><label class="form-label">H (cm)</label><input class="form-control" type="number" min="1" max="200" name="height_cm"></div>
                <div class="col-6 col-md-3"><label class="form-label">COD amount</label><input class="form-control" type="number" min="0" max="<?= MAX_COD ?>" name="cod_amount" placeholder="0"></div>
                <div class="col-6 col-md-3"><label class="form-label">Declared value</label><input class="form-control" type="number" min="0" name="declared_value" placeholder="0"></div>
                <div class="col-6 col-md-3 pt-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="fragile" id="fragile" value="1"><label class="form-check-label" for="fragile">Fragile</label></div></div>
                <div class="col-6 col-md-3 pt-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="insured" id="insured" value="1"><label class="form-check-label" for="insured">Insure</label></div></div>
            </div>
        </div>
        <div class="card-panel"><h6><i class="bi bi-truck me-1"></i>Service &amp; payment</h6>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label">Service</label><select class="form-select" name="service" required><option value="Standard">Standard</option><option value="Express">Express</option><option value="Same-day">Same-day (local only)</option></select></div>
                <div class="col-md-4"><label class="form-label">Pickup date</label><input class="form-control" name="pickup_date" id="pickup_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-4"><label class="form-label">Freight paid by</label><select class="form-select" name="payer"><option value="Sender">Sender</option><option value="Receiver">Receiver (on delivery)</option></select></div>
                <div class="col-md-6" id="payWrap"><label class="form-label">Payment</label><select class="form-select" name="pay_method" id="pay_method">
<?php if ($isCust): ?>
                    <option value="">Pay cash to the rider at pickup<?= $cust['is_credit'] ? ' / bill my account' : '' ?></option><option value="Online">Pay online now</option>
<?php else: ?>
                    <option value="">Not paid yet (pay later / credit account)</option><option>Cash</option><option>Card</option><option>Online</option><option>Bank</option>
<?php endif; ?>
                </select></div>
                <div class="col-md-6"><label class="form-label">Notes for the rider</label><input class="form-control" name="notes" maxlength="255" placeholder="Gate code, landmark…"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel" style="position:sticky;top:82px">
            <h6>Price quote</h6>
            <div id="quote" class="text-muted small">Fill in the route and weight to see the price.</div>
            <button class="btn btn-gradient w-100 mt-3" type="submit" id="btnBook"><i class="bi bi-check2-circle me-1"></i> Confirm booking</button>
            <div class="small text-muted mt-2">Tracking number and SMS to the receiver are issued instantly.</div>
        </div>
    </div>
</div>
</form>
<?php
$pageConfig = '<script>window.BOOK = ' . json_encode(['cust' => $isCust]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
