<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['rider'], json: true);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') !== 'tasks') { $bad('Unknown action.', 400); }
        $pick = []; $deliv = []; $ret = [];
        foreach (ShipmentModel::list($user, ['status' => 'active'], 100) as $s) {
            unset($s['delivery_otp'], $s['otp_fails']);      // the code only ever travels to the receiver
            $s['collect_pickup'] = 0.0; $s['collect_delivery'] = 0.0;
            if ($s['status'] === 'Pickup Assigned' && (int) $s['pickup_rider_id'] === $user['id']) {
                if ($s['payer'] === 'Sender' && $s['payment_status'] === 'Unpaid' && !$s['is_credit']) { $s['collect_pickup'] = (float) $s['total_charge']; }
                $pick[] = $s;
            } elseif ($s['status'] === 'Out for Delivery' && (int) $s['delivery_rider_id'] === $user['id']) {
                $s['collect_delivery'] = (float) $s['cod_amount'] + ($s['payer'] === 'Receiver' && $s['payment_status'] === 'Unpaid' ? (float) $s['total_charge'] : 0.0);
                $deliv[] = $s;
            } elseif ($s['status'] === 'Returning' && (int) $s['delivery_rider_id'] === $user['id']) { $ret[] = $s; }
        }
        $cash = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM rider_cash WHERE rider_id = :r AND settlement_id IS NULL'); $cash->execute([':r' => $user['id']]);
        $done = db()->prepare("SELECT COUNT(*) FROM shipment_events WHERE user_id = :u AND status IN ('Delivered','Picked Up') AND DATE(created_at) = CURDATE()"); $done->execute([':u' => $user['id']]);
        json_response(['success' => true, 'pickups' => $pick, 'deliveries' => $deliv, 'returns' => $ret, 'cash' => (float) $cash->fetchColumn(), 'done_today' => (int) $done->fetchColumn(), 'reasons' => ShipmentModel::FAIL_REASONS]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    $res = match ($a) {
        'pickup'   => [ShipmentModel::pickup($id, $user, !empty($_POST['collect']) && $_POST['collect'] !== '0'), 'Pickup confirmed.'],
        'deliver'  => [ShipmentModel::deliver($id, $user, (string) ($_POST['otp'] ?? ''), (string) ($_POST['received_by'] ?? '')), 'Delivered. Well done!'],
        'fail'     => [ShipmentModel::fail($id, $user, (string) ($_POST['reason'] ?? '')), 'Failed attempt recorded.'],
        'returned' => [ShipmentModel::returned($id, $user), 'Return confirmed.'],
        default    => null,
    };
    if ($res === null) { $bad('Unknown action.', 400); }
    json_response($res[0] ? ['success' => false, 'message' => $res[0]] : ['success' => true, 'message' => $res[1]], $res[0] ? 409 : 200);
} catch (Throwable $e) {
    error_log('[rider api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
