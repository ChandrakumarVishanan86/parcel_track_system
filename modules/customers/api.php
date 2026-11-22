<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search') {
    Auth::guard(Auth::DESK, json: true);
    $t = trim((string) ($_GET['term'] ?? ''));
    json_response(['success' => true, 'data' => mb_strlen($t) >= 2 ? CustomerModel::search($t) : []]);
}
CrudController::handle(CustomerModel::class, ['read' => ['admin', 'dispatcher', 'customer'], 'write' => Auth::DESK, 'label' => 'Customer']);
