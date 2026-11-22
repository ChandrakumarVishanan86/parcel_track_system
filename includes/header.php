<?php
/**
 * Shared top-navigation layout. Pages may set before including:
 *   $pageTitle, $pageSub, $pageActions (html), $activeNav, $libs ['datatables','flatpickr','chart']
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$user      = Auth::guard();
$role      = $user['role'];
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$libs      = $libs ?? [];

$badges = [];
if ($role !== 'customer') {
    $c = db()->query("SELECT
        (SELECT COUNT(*) FROM shipments WHERE status = 'Booked') AS booked,
        (SELECT COUNT(*) FROM shipments WHERE status IN ('At Destination Hub','Delivery Failed')) AS ready,
        (SELECT COUNT(*) FROM shipments WHERE status = 'At Origin Hub') AS hubq")->fetch();
    $badges = ['dispatch' => (int) $c['booked'] + (int) $c['ready'], 'hub' => (int) $c['hubq']];
    if ($role === 'rider') {
        $s = db()->prepare("SELECT COUNT(*) FROM shipments WHERE (status = 'Pickup Assigned' AND pickup_rider_id = :u) OR (status IN ('Out for Delivery','Returning') AND delivery_rider_id = :u2)");
        $s->execute([':u' => $user['id'], ':u2' => $user['id']]);
        $badges = ['rider' => (int) $s->fetchColumn()];
    }
}
$mine = $role === 'customer';
// [key, label, icon, href, roles]
$nav = [
    ['dashboard', 'Dashboard', 'bi-grid-1x2-fill', 'dashboard', ['admin', 'dispatcher', 'hub', 'customer']],
    ['rider', 'My Tasks', 'bi-bicycle', 'rider', ['rider']],
    ['book', 'Book Parcel', 'bi-box-seam-fill', 'shipments/new', ['admin', 'dispatcher', 'customer']],
    ['shipments', $mine ? 'My Parcels' : 'Shipments', 'bi-boxes', 'shipments', ['admin', 'dispatcher', 'hub', 'customer']],
    ['dispatch', 'Dispatch', 'bi-signpost-split-fill', 'dispatch', ['admin', 'dispatcher']],
    ['hub', 'Hub', 'bi-diagram-3-fill', 'hub', ['admin', 'dispatcher', 'hub']],
    ['cod', 'Cash & COD', 'bi-cash-coin', 'cod', ['admin', 'dispatcher']],
    ['customers', 'Customers', 'bi-people-fill', 'customers', ['admin', 'dispatcher']],
    ['reports', 'Reports', 'bi-bar-chart-line-fill', 'reports', ['admin', 'dispatcher']],
    ['admin', 'Admin', 'bi-gear-fill', 'admin', ['admin']],
];
$initials = strtoupper(mb_substr($user['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <script>try{var t=localStorage.getItem('pt_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php if (in_array('datatables', $libs, true)): ?>
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?php endif; ?>
<?php if (in_array('flatpickr', $libs, true)): ?>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
<?php endif; ?>
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="app-body">
<nav class="topnav">
    <div class="topnav-in">
        <a class="brand" href="<?= url(Auth::homeFor($role)) ?>"><span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span><span>Parcel<b>Track</b></span></a>
        <div class="navlinks" id="navlinks">
<?php foreach ($nav as [$key, $label, $icon, $href, $roles]): if (!in_array($role, $roles, true)) continue; ?>
            <a class="nl <?= $activeNav === $key ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?><?php if (!empty($badges[$key])): ?><span class="nb"><?= (int) $badges[$key] ?></span><?php endif; ?></a>
<?php endforeach; ?>
        </div>
        <div class="nav-tools">
            <form class="trk-search" action="<?= url('shipments/find') ?>" method="get"><i class="bi bi-search"></i><input name="q" placeholder="Track parcel…" autocomplete="off" spellcheck="false" aria-label="Tracking number"></form>
            <button class="icon-btn" type="button" id="themeBtn" title="Toggle dark mode"><i class="bi bi-moon-stars-fill"></i></button>
            <div class="dropdown">
                <button class="user-btn" data-bs-toggle="dropdown" aria-label="Account"><span class="avatar"><?= e($initials) ?></span></button>
                <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:210px">
                    <li class="px-2 py-1"><div class="fw-bold"><?= e($user['full_name']) ?></div><span class="badge role-badge"><?= e($role) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= url('track') ?>" target="_blank"><i class="bi bi-globe2 me-2"></i>Public tracking page</a></li>
                    <li><a class="dropdown-item" href="<?= url('logout') ?>" data-confirm="Sign out of your account?"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                </ul>
            </div>
            <button class="icon-btn nav-toggle" type="button" id="navToggle" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
        </div>
    </div>
</nav>
<main class="content fade-in">
<?php foreach (pull_flash() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
    <div class="pagehead">
        <div><h1 class="page-title"><?= e($pageTitle) ?></h1><div class="sub"><?= isset($pageSub) ? e($pageSub) : date('l, d F Y') ?></div></div>
        <?php if (!empty($pageActions)): ?><div class="actions"><?= $pageActions ?></div><?php endif; ?>
    </div>
