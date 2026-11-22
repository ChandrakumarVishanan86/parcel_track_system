<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Staff accounts'; $pageSub = 'Admins, dispatchers, hub operators and riders'; $activeNav = 'admin'; $adminTab = 'users'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/admin_users.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-person-plus-fill me-1"></i> Add staff</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
$branches = db()->query('SELECT id, city FROM branches WHERE is_active = 1 ORDER BY city')->fetchAll();
$pageConfig = '<script>window.BR = ' . json_encode(array_map(fn($b) => [(string) $b['id'], $b['city']], $branches)) . ';</script>';
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>User</th><th>Role</th><th>Branch</th><th>Vehicle</th><th>Phone</th><th>Last login</th><th>Status</th><th></th></tr></thead></table></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
