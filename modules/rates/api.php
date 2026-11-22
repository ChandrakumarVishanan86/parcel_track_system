<?php
declare(strict_types=1);
/** Public rate calculator. Returns a quote for every service available on the lane. */
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::start();
header('Cache-Control: no-store');
$out = []; $err = null;
foreach (Pricing::SERVICES as $svc) {
    [$q, $e] = Pricing::quote(array_merge($_GET, ['service' => $svc]));
    if ($q) { $out[] = $q; } else { $err = $err ?? $e; }
}
if (!$out) { json_response(['success' => false, 'message' => $err ?: 'No service is available for this lane.'], 422); }
json_response(['success' => true, 'data' => $out]);
