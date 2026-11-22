<?php
declare(strict_types=1);
$pubActive = 'rates'; $pageTitle = 'Rate calculator';
require_once dirname(__DIR__, 2) . '/includes/public_header.php';
$branches = db()->query('SELECT id, city FROM branches WHERE is_active = 1 ORDER BY city')->fetchAll();
$opts = static function (int $sel = 0) use ($branches): string { $h = '<option value="">Choose city…</option>'; foreach ($branches as $b) { $h .= '<option value="' . $b['id'] . '"' . ($b['id'] === $sel ? ' selected' : '') . '>' . e($b['city']) . '</option>'; } return $h; };
?>
<section class="track-hero" style="padding-bottom:64px">
    <h1>Instant rate calculator</h1>
    <p class="opacity-75 mb-0">Compare Standard, Express and Same-day prices before you book. VAT included.</p>
</section>
<div class="container pull-up pb-5" style="max-width:980px">
    <div class="row g-3">
        <div class="col-lg-5"><div class="card-panel">
            <form id="rateForm" novalidate>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">From</label><select class="form-select" name="origin_branch_id" required><?= $opts() ?></select></div>
                    <div class="col-6"><label class="form-label">To</label><select class="form-select" name="dest_branch_id" required><?= $opts() ?></select></div>
                    <div class="col-12"><label class="form-label">Weight (kg)</label><input class="form-control" name="weight_kg" type="number" step="0.1" min="0.1" max="<?= MAX_WEIGHT_KG ?>" placeholder="e.g. 2.5" required></div>
                    <div class="col-12"><label class="form-label">Size L × W × H (cm) <span class="text-muted fw-normal">optional</span></label>
                        <div class="input-group"><input class="form-control" name="length_cm" type="number" min="1" max="200" placeholder="L"><input class="form-control" name="width_cm" type="number" min="1" max="200" placeholder="W"><input class="form-control" name="height_cm" type="number" min="1" max="200" placeholder="H"></div></div>
                    <div class="col-6"><label class="form-label">COD amount</label><input class="form-control" name="cod_amount" type="number" min="0" max="<?= MAX_COD ?>" placeholder="0"></div>
                    <div class="col-6"><label class="form-label">Declared value</label><input class="form-control" name="declared_value" type="number" min="0" placeholder="0"></div>
                    <div class="col-6"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="fragile" id="fr" value="1"><label class="form-check-label" for="fr">Fragile</label></div></div>
                    <div class="col-6"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="insured" id="in" value="1"><label class="form-check-label" for="in">Insure parcel</label></div></div>
                </div>
                <button class="btn btn-gradient w-100 mt-3" type="submit"><i class="bi bi-calculator me-1"></i> Get prices</button>
                <div class="small text-muted mt-2">Volumetric weight = L × W × H ÷ <?= VOL_DIVISOR ?>. The higher of actual and volumetric weight is charged, rounded up to 0.5 kg.</div>
            </form>
        </div></div>
        <div class="col-lg-7"><div id="rateOut"><div class="card-panel text-center text-muted py-5"><i class="bi bi-tags fs-1"></i><p class="mb-0 mt-2">Pick a route and weight to see prices.</p></div></div></div>
    </div>
</div>
<?php
$pageScripts = ['assets/js/rates.js'];
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
