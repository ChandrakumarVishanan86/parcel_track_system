<?php
declare(strict_types=1);
/** Public tracking lookup: returns only what a stranger holding the tracking number may see. */
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::start();
header('Cache-Control: no-store');

// light per-session throttle so the endpoint cannot be used to enumerate tracking numbers
$now = time(); $_SESSION['trk_hits'] = array_values(array_filter($_SESSION['trk_hits'] ?? [], fn($t) => $t > $now - 60));
if (count($_SESSION['trk_hits']) >= 30) { json_response(['success' => false, 'message' => 'Too many lookups. Please wait a minute.'], 429); }
$_SESSION['trk_hits'][] = $now;

$mask = static function (string $n): string {
    $parts = preg_split('/\s+/', trim($n)) ?: [];
    return implode(' ', array_map(fn($p) => mb_substr($p, 0, 1) . str_repeat('*', max(2, min(5, mb_strlen($p) - 1))), $parts));
};
$tn = ShipmentModel::normaliseTn((string) ($_GET['n'] ?? ''));
if (strlen($tn) < 8) { json_response(['success' => false, 'message' => 'Enter a valid tracking number, e.g. PT2611010001.'], 422); }
$s = ShipmentModel::findByTracking($tn);
if (!$s) { json_response(['success' => false, 'message' => 'We could not find a parcel with that tracking number.'], 404); }
$events = [];
foreach (ShipmentModel::events((int) $s['id']) as $e) {
    $events[] = ['status' => $e['status'], 'location' => $e['location'], 'at' => $e['created_at'], 'note' => in_array($e['status'], ['Delivery Failed', 'Delivered'], true) ? $e['note'] : null];
}
json_response(['success' => true, 'data' => [
    'tracking_no' => $s['tracking_no'], 'status' => $s['status'], 'service' => $s['service'], 'zone' => $s['zone'],
    'origin' => $s['origin_city'], 'destination' => $s['dest_city'], 'promised_date' => $s['promised_date'], 'is_late' => (bool) $s['is_late'],
    'receiver' => $mask($s['receiver_name']), 'pieces' => (int) $s['pieces'], 'weight_kg' => (float) $s['chargeable_kg'],
    'cod' => (float) $s['cod_amount'] > 0, 'delivered_at' => $s['status'] === 'Delivered' ? ($events[0]['at'] ?? null) : null,
    'stage' => ShipmentModel::stage($s['status']), 'exception' => in_array($s['status'], ['Delivery Failed', 'Returning', 'Returned', 'Cancelled'], true),
    'events' => $events,
]]);
