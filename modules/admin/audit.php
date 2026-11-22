<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Audit log'; $pageSub = 'The latest 300 actions - who did what, from where'; $activeNav = 'admin'; $adminTab = 'audit'; $libs = ['datatables'];
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
$rows = db()->query("SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 300")->fetchAll();
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-sm table-hover align-middle w-100"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr data-order="<?= (int) $r['id'] ?>"><td class="text-nowrap"><?= e(fdatetime($r['created_at'])) ?></td><td><?= e($r['username'] ?? 'system') ?></td><td><span class="badge badge-soft-secondary"><?= e($r['action']) ?></span></td><td><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td><td><?= e($r['details']) ?></td><td class="text-muted small"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php $pageInline = '<script>$("#tbl").DataTable({order:[],pageLength:15,language:{search:"",searchPlaceholder:"Filter…"}});</script>'; require_once dirname(__DIR__, 2) . '/includes/footer.php';
