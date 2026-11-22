<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Rate cards'; $pageSub = 'Freight = base + extra kg × rate. Fuel, fees and VAT are added on top (see Configuration).'; $activeNav = 'admin'; $adminTab = 'rates'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/admin_rates.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-plus-lg me-1"></i> Add rate</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Service</th><th>Zone</th><th class="text-end">Base price</th><th class="text-end">Included kg</th><th class="text-end">Per extra kg</th><th class="text-center">Days</th><th>Status</th><th></th></tr></thead></table></div>
<div class="small text-muted mt-3">Fixed charges (from <code>config/config.php</code>): fuel <?= FUEL_PCT ?>% · fragile <?= money(FRAGILE_FEE) ?> · COD fee <?= COD_FEE_PCT ?>% (min <?= money(COD_FEE_MIN) ?>) · insurance <?= INSURANCE_PCT ?>% of declared value · VAT <?= TAX_PCT ?>%.</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
