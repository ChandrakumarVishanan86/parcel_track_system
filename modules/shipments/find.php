<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard();
$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') { redirect('shipments'); }
$s = ShipmentModel::findByTracking($q);
if ($s && ShipmentModel::canAccess($user, $s)) { redirect('shipments/' . $s['id']); }
if ($user['role'] === 'rider') { flash('warning', 'No parcel with that tracking number is assigned to you.'); redirect('rider'); }
$rows = ShipmentModel::list($user, ['q' => $q], 2);
if (count($rows) === 1) { redirect('shipments/' . $rows[0]['id']); }
flash($rows ? 'info' : 'warning', $rows ? 'Several parcels match - refine the search.' : 'No parcel matched "' . $q . '".');
redirect('shipments?q=' . urlencode($q));
