<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::DESK);
$pageTitle = 'Customers'; $pageSub = 'Senders, credit accounts and portal logins'; $activeNav = 'customers'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/customers.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-person-plus-fill me-1"></i> Add customer</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
$branches = db()->query('SELECT id, city FROM branches WHERE is_active = 1 ORDER BY city')->fetchAll();
$pageConfig = '<script>window.BR = ' . json_encode(array_map(fn($b) => [(string) $b['id'], $b['city']], $branches)) . ';</script>';
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100">
    <thead><tr><th>Code</th><th>Customer</th><th>Phone</th><th>Branch</th><th>Account</th><th class="text-center">Parcels</th><th class="text-end">Outstanding</th><th>Portal</th><th>Status</th><th></th></tr></thead></table></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
