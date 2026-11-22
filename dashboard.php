<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = Auth::guard(['admin', 'dispatcher', 'hub', 'customer']);
$cust = $user['role'] === 'customer';
$pageTitle = 'Dashboard'; $pageSub = $cust ? 'Welcome back, ' . $user['full_name'] : 'Network pulse - ' . date('l, d F Y'); $activeNav = 'dashboard';
$libs = ['chart']; $pageScripts = ['assets/js/dashboard.js'];
$pageActions = in_array($user['role'], ['admin', 'dispatcher', 'customer'], true) ? '<a class="btn btn-gradient" href="' . url('shipments/new') . '"><i class="bi bi-box-seam-fill me-1"></i> Book parcel</a>' : '';
require_once __DIR__ . '/includes/header.php';
require __DIR__ . '/modules/dashboard/' . ($cust ? 'customer' : 'staff') . '.php';
$pageConfig = '<script>window.DASH = ' . json_encode(['customer' => $cust]) . ';</script>';
require_once __DIR__ . '/includes/footer.php';
