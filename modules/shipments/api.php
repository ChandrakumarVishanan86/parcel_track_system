<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin', 'dispatcher', 'hub', 'customer', 'rider'], json: true);
$ok = static fn(string $m, array $x = []) => json_response(['success' => true, 'message' => $m] + $x);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'list') { json_response(['success' => true, 'data' => ShipmentModel::list($user, $_GET)]); }
        if ($a === 'get') {
            $s = ShipmentModel::find((int) ($_GET['id'] ?? 0));
            if (!$s || !ShipmentModel::canAccess($user, $s)) { $bad('Shipment not found.', 404); }
            json_response(['success' => true, 'data' => $s, 'events' => ShipmentModel::events((int) $s['id'])]);
        }
        if ($a === 'quote') {
            [$q, $err] = Pricing::quote($_GET);
            json_response($q ? ['success' => true, 'data' => $q] : ['success' => false, 'message' => $err], $q ? 200 : 422);
        }
        if ($a === 'riders') {
            if (!in_array($user['role'], Auth::DESK, true)) { $bad('Not allowed.', 403); }
            $s = db()->prepare("SELECT u.id, u.full_name, u.vehicle, u.branch_id, b.city FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.role = 'rider' AND u.is_active = 1 AND (:b1 = 0 OR u.branch_id = :b2) ORDER BY u.full_name");
            $s->execute([':b1' => (int) ($_GET['branch_id'] ?? 0), ':b2' => (int) ($_GET['branch_id'] ?? 0)]);
            $rows = $s->fetchAll(); foreach ($rows as &$r) { $r['load'] = ShipmentModel::riderLoad((int) $r['id']); $r['max'] = RIDER_MAX_TASKS; }
            json_response(['success' => true, 'data' => $rows]);
        }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if ($user['role'] === 'rider') { $bad('Riders use the rider app.', 403); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));

    if ($a === 'create') {
        if (!in_array($user['role'], ['admin', 'dispatcher', 'customer'], true)) { $bad('You cannot book parcels.', 403); }
        [$nid, $err] = ShipmentModel::create($user, $_POST);
        if ($err) { $bad($err); }
        $s = ShipmentModel::find($nid);
        $ok('Parcel booked: ' . $s['tracking_no'], ['id' => $nid, 'tracking_no' => $s['tracking_no']]);
    }
    if ($user['role'] === 'hub') { $bad('Hub staff cannot change bookings.', 403); }
    if ($a === 'cancel') { ($e = ShipmentModel::cancel($id, $user, trim((string) ($_POST['reason'] ?? '')))) ? $bad($e, 409) : $ok('Shipment cancelled.'); }
    if (!in_array($user['role'], Auth::DESK, true)) { $bad('Not allowed.', 403); }
    $guard = static function () use ($id, $user, $bad) {
        $s = ShipmentModel::find($id);
        if (!$s || !ShipmentModel::canAccess($user, $s)) { $bad('Shipment not found.', 404); }
    };
    if ($a === 'pay') { $guard(); ($e = ShipmentModel::pay($id, (string) ($_POST['method'] ?? ''), $user)) ? $bad($e, 409) : $ok('Payment recorded.'); }
    if ($a === 'assign_pickup') { [$n, $e] = ShipmentModel::assignPickup($ids, (int) ($_POST['rider_id'] ?? 0), $user); $e ? $bad($e, 409) : $ok("$n pickup(s) assigned.", ['count' => $n]); }
    if ($a === 'assign_delivery') { [$n, $e] = ShipmentModel::assignDelivery($ids, (int) ($_POST['rider_id'] ?? 0), $user); $e ? $bad($e, 409) : $ok("$n parcel(s) sent out for delivery.", ['count' => $n]); }
    if ($a === 'return') { $guard(); ($e = ShipmentModel::returnToSender($id, $user)) ? $bad($e, 409) : $ok('Parcel is being returned to the sender.'); }
    if ($a === 'returned') { $guard(); ($e = ShipmentModel::returned($id, $user)) ? $bad($e, 409) : $ok('Marked as returned to sender.'); }
    if ($a === 'regen_otp') { $guard(); ($e = ShipmentModel::regenOtp($id, $user)) ? $bad($e, 409) : $ok('A new OTP was generated and sent to the receiver.'); }
    $bad('Unknown action.', 400);
} catch (Throwable $e) {
    error_log('[shipments api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
