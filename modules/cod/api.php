<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::DESK, json: true);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'overview') {
            $r = CodModel::riders();
            json_response(['success' => true, 'riders' => $r, 'history' => CodModel::history(), 'total' => round(array_sum(array_column($r, 'on_hand')), 2)]);
        }
        if ($a === 'lines') { json_response(['success' => true, 'data' => CodModel::lines((int) ($_GET['rider_id'] ?? 0))]); }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    if (($_POST['action'] ?? '') === 'settle') {
        if (!is_numeric($_POST['counted'] ?? null)) { $bad('Enter the counted amount.'); }
        [$id, $e] = CodModel::settle((int) ($_POST['rider_id'] ?? 0), (float) $_POST['counted'], (string) ($_POST['note'] ?? ''), $user);
        $e ? $bad($e, 409) : json_response(['success' => true, 'id' => $id, 'message' => 'Cash settled and receipted.']);
    }
    $bad('Unknown action.', 400);
} catch (Throwable $e) {
    error_log('[cod api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
