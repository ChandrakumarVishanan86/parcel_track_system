<footer class="app-footer">&copy; <?= date('Y') ?> <?= e(COMPANY_NAME) ?> &middot; <?= e(COMPANY_PHONE) ?> &middot; built by Chandra Kumar Vishanan</footer>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastHost" style="z-index:2000"></div>
<script>window.APP = { base: <?= json_encode(APP_BASE) ?>, csrf: <?= json_encode(csrf_token()) ?>, role: null, branch: null };</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/main.js') ?>?v=1"></script>
<?= $pageConfig ?? '' ?>
<?php foreach (($pageScripts ?? []) as $src): ?><script src="<?= url($src) ?>?v=1"></script>
<?php endforeach; ?>
</body>
</html>
