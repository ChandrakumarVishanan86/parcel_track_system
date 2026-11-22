<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'SMS outbox'; $pageSub = 'Messages are simulated - plug a real gateway into ShipmentModel::textTo()'; $activeNav = 'admin'; $adminTab = 'sms'; $libs = ['datatables'];
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
$rows = db()->query("SELECT l.*, s.tracking_no FROM sms_log l LEFT JOIN shipments s ON s.id = l.shipment_id ORDER BY l.id DESC LIMIT 300")->fetchAll();
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-sm table-hover align-middle w-100"><thead><tr><th>Sent</th><th>To</th><th>Parcel</th><th>Message</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="text-nowrap"><?= e(fdatetime($r['created_at'])) ?></td><td class="mono"><?= e($r['to_phone']) ?></td><td><?= $r['shipment_id'] ? '<a href="' . url('shipments/' . (int) $r['shipment_id']) . '">' . tn_chip((string) $r['tracking_no']) . '</a>' : '–' ?></td><td><?= e($r['message']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php $pageInline = '<script>$("#tbl").DataTable({order:[],pageLength:15,language:{search:"",searchPlaceholder:"Filter…"}});</script>'; require_once dirname(__DIR__, 2) . '/includes/footer.php';
