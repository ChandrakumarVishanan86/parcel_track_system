<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::OPS, json: true);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
$branch = $user['role'] === 'hub' ? (int) $user['branch_id'] : (int) ($_REQUEST['branch_id'] ?? 0);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'overview') {
            if (!$branch) { $bad('Choose a branch.'); }
            $q = static function (string $sql, array $p) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); };
            $inbound = $q("SELECT s.id, s.tracking_no, s.sender_name, s.status, o.city AS origin_city, s.service FROM shipments s JOIN branches o ON o.id = s.origin_branch_id WHERE s.origin_branch_id = :b AND s.status = 'Picked Up' ORDER BY s.id", [':b' => $branch]);
            $counts = $q("SELECT
                (SELECT COUNT(*) FROM shipments WHERE origin_branch_id = :a1 AND status = 'Picked Up') AS to_scan,
                (SELECT COUNT(*) FROM shipments WHERE origin_branch_id = :a2 AND status = 'At Origin Hub') AS to_ship,
                (SELECT COUNT(*) FROM shipments WHERE dest_branch_id = :a3 AND status = 'At Destination Hub') AS to_deliver,
                (SELECT COUNT(*) FROM manifests WHERE to_branch_id = :a4 AND status = 'Dispatched') AS inbound", [':a1' => $branch, ':a2' => $branch, ':a3' => $branch, ':a4' => $branch])[0];
            json_response(['success' => true, 'counts' => $counts, 'to_scan' => $inbound, 'waiting' => ManifestModel::waiting($branch), 'manifests' => ManifestModel::list($user)]);
        }
        if ($a === 'manifest') {
            $m = ManifestModel::find((int) ($_GET['id'] ?? 0));
            if (!$m || !ManifestModel::canAccess($user, $m)) { $bad('Manifest not found.', 404); }
            json_response(['success' => true, 'data' => $m, 'items' => ManifestModel::items((int) $m['id'])]);
        }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    $tns = preg_split('/[\s,;]+/', (string) ($_POST['tns'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($a === 'scan_in') {
        if (!$branch) { $bad('Choose a branch.'); }
        $res = ShipmentModel::scanIn($tns, $branch, $user);
        $ok = count(array_filter($res, fn($r) => $r['ok']));
        json_response(['success' => true, 'results' => $res, 'message' => "$ok of " . count($res) . ' parcel(s) scanned in.']);
    }
    if ($a === 'create_manifest') {
        $from = $user['role'] === 'hub' ? (int) $user['branch_id'] : (int) ($_POST['from_branch_id'] ?? 0);
        [$nid, $e] = ManifestModel::create($user, $from, (int) ($_POST['to_branch_id'] ?? 0), array_map('intval', (array) ($_POST['ids'] ?? [])), trim((string) ($_POST['vehicle'] ?? '')), trim((string) ($_POST['driver'] ?? '')));
        $e ? $bad($e, 409) : json_response(['success' => true, 'message' => 'Manifest created.', 'id' => $nid]);
    }
    if ($a === 'dispatch') { ($e = ManifestModel::dispatch($id, $user)) ? $bad($e, 409) : json_response(['success' => true, 'message' => 'Manifest dispatched - parcels are now in transit.']); }
    if ($a === 'cancel') { ($e = ManifestModel::cancel($id, $user)) ? $bad($e, 409) : json_response(['success' => true, 'message' => 'Manifest cancelled.']); }
    if ($a === 'receive') {
        [$res, $e] = ManifestModel::receive($id, $tns, $user);
        if ($e) { $bad($e, 409); }
        $ok = count(array_filter($res, fn($r) => $r['ok']));
        json_response(['success' => true, 'results' => $res, 'message' => "$ok of " . count($res) . ' parcel(s) received.']);
    }
    $bad('Unknown action.', 400);
} catch (Throwable $e) {
    error_log('[hub api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
