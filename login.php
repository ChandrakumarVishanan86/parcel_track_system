<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (Auth::check()) { redirect(Auth::homeFor(Auth::role())); }

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!csrf_verify()) {
        $error = 'Your session expired. Please try again.';
    } elseif (($wait = Auth::lockedFor()) > 0) {
        $error = "Too many failed attempts. Try again in {$wait} seconds.";
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $user = Auth::attempt($username, $password);
        if ($user) { redirect(Auth::homeFor($user['role'])); }
        $error = ($wait = Auth::lockedFor()) > 0 ? "Too many failed attempts. Try again in {$wait} seconds." : 'Invalid username or password.';
    }
}
$nodes = ['JAF' => [330, 90], 'KUR' => [250, 330], 'BAT' => [470, 400], 'KDY' => [330, 420], 'NEG' => [175, 410], 'CMB' => [160, 500], 'GAL' => [215, 650], 'MTR' => [300, 690]];
$arcs = [['CMB', 'KDY'], ['CMB', 'JAF'], ['KDY', 'BAT'], ['CMB', 'GAL'], ['GAL', 'MTR'], ['KDY', 'KUR'], ['NEG', 'KUR'], ['CMB', 'BAT']];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="login-body">
<div class="login-split">
    <section class="login-art">
        <svg class="netmap" viewBox="0 0 640 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
<?php foreach ($arcs as $i => [$a, $b]): [$x1, $y1] = $nodes[$a]; [$x2, $y2] = $nodes[$b]; $mx = ($x1 + $x2) / 2 + 45; $my = ($y1 + $y2) / 2; ?>
            <path class="arc" id="arc<?= $i ?>" d="M<?= $x1 ?> <?= $y1 ?> Q<?= $mx ?> <?= $my ?> <?= $x2 ?> <?= $y2 ?>"/>
            <rect width="9" height="9" rx="2.5" fill="#c6f432"><animateMotion dur="<?= 5 + $i ?>s" repeatCount="indefinite" begin="-<?= $i * 1.3 ?>s"><mpath href="#arc<?= $i ?>"/></animateMotion></rect>
<?php endforeach; ?>
<?php foreach ($nodes as $c => [$x, $y]): ?>
            <circle class="halo" cx="<?= $x ?>" cy="<?= $y ?>" r="12"/><circle class="node" cx="<?= $x ?>" cy="<?= $y ?>" r="5"/>
            <text x="<?= $x + 12 ?>" y="<?= $y + 4 ?>" fill="#9fb0cc" font-size="12" font-family="JetBrains Mono, monospace"><?= $c ?></text>
<?php endforeach; ?>
        </svg>
        <a class="brand position-relative" href="<?= url('track') ?>"><span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span><span>Parcel<b>Track</b></span></a>
        <div class="position-relative">
            <h1>Every parcel,<br><em>one clear trail.</em></h1>
            <p class="mt-3">Book, dispatch, scan at the hub, deliver with an OTP and settle the cash &mdash; all in one connected system.</p>
            <form class="d-flex gap-2 mt-4" action="<?= url('track') ?>" method="get" style="max-width:430px">
                <input class="form-control" style="font-family:var(--mono);background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);color:#fff" name="n" placeholder="Track a parcel: PT2611210001">
                <button class="btn btn-gradient">Track</button>
            </form>
        </div>
    </section>
    <section class="login-form-side">
        <div class="login-card">
            <h2 class="fw-bold mb-1">Sign in</h2>
            <p class="text-muted mb-4">Welcome back to <?= e(APP_NAME) ?>.</p>
<?php foreach (pull_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
            <form method="post" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control form-control-lg" id="username" name="username" value="<?= e($username) ?>" autofocus required></div>
                <div class="mb-4"><label class="form-label" for="password">Password</label><input type="password" class="form-control form-control-lg" id="password" name="password" required></div>
                <button class="btn btn-gradient btn-lg w-100" type="submit">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
            </form>
            <div class="mt-4 p-3 rounded-3" style="background:var(--surface2);border:1px dashed var(--line)" id="demoBox">
                <div class="small fw-bold mb-2 text-muted text-uppercase" style="letter-spacing:.08em">Demo accounts</div>
                <div class="d-flex flex-wrap gap-2">
<?php foreach ([['admin', 'admin123', 'Admin'], ['dispatcher1', 'dispatch123', 'Dispatcher'], ['hub1', 'hub123', 'Hub'], ['rider1', 'rider123', 'Rider'], ['customer1', 'customer123', 'Customer']] as [$u, $p, $l]): ?>
                    <button type="button" class="btn btn-soft btn-sm" data-u="<?= $u ?>" data-p="<?= $p ?>"><?= $l ?></button>
<?php endforeach; ?>
                </div>
            </div>
            <p class="text-center mt-4 small text-muted">Receiving a parcel? <a href="<?= url('track') ?>">Track it here</a> &middot; <a href="<?= url('rates') ?>">Get a quote</a></p>
        </div>
    </section>
</div>
<script>document.querySelectorAll('#demoBox [data-u]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('username').value = b.dataset.u; document.getElementById('password').value = b.dataset.p; }); });</script>
</body>
</html>
