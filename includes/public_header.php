<?php
/** Light header for the public pages (tracking, rate calculator). No login required. */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
Auth::start();
$pubActive = $pubActive ?? '';
$pageTitle = $pageTitle ?? 'Track a parcel';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <script>try{var t=localStorage.getItem('pt_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="app-body">
<nav class="pub-nav">
    <a class="brand" href="<?= url('track') ?>"><span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span><span>Parcel<b>Track</b></span></a>
    <div class="links">
        <a class="nl <?= $pubActive === 'track' ? 'active' : '' ?>" href="<?= url('track') ?>"><i class="bi bi-geo-alt-fill"></i>Track</a>
        <a class="nl <?= $pubActive === 'rates' ? 'active' : '' ?>" href="<?= url('rates') ?>"><i class="bi bi-calculator-fill"></i>Rates</a>
        <a class="nl" href="<?= url(Auth::check() ? Auth::homeFor(Auth::role()) : 'login') ?>"><i class="bi bi-person-circle"></i><?= Auth::check() ? 'My account' : 'Sign in' ?></a>
    </div>
</nav>
