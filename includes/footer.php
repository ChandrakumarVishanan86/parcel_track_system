<?php
/** Closes the layout opened in header.php and loads scripts. */
declare(strict_types=1);
$libs        = $libs ?? [];
$pageScripts = $pageScripts ?? [];
$user        = Auth::user();
?>
</main>
<footer class="app-footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Courier &amp; Parcel Tracking System &middot; by Chandra Kumar Vishanan</footer>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastHost" style="z-index:2000"></div>
<script>window.APP = { base: <?= json_encode(APP_BASE) ?>, csrf: <?= json_encode(csrf_token()) ?>, role: <?= json_encode($user['role'] ?? null) ?>, branch: <?= json_encode($user['branch_id'] ?? null) ?> };</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (in_array('datatables', $libs, true)): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if (in_array('flatpickr', $libs, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<?php endif; ?>
<?php if (in_array('chart', $libs, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= url('assets/js/main.js') ?>?v=1"></script>
<script>
(function () {
    var b = document.getElementById('themeBtn'), t = document.getElementById('navToggle'), n = document.getElementById('navlinks');
    function sync() { var d = document.documentElement.getAttribute('data-theme') === 'dark'; b.innerHTML = '<i class="bi ' + (d ? 'bi-sun-fill' : 'bi-moon-stars-fill') + '"></i>'; }
    sync(); b.addEventListener('click', function () { var d = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', d); try { localStorage.setItem('pt_theme', d); } catch (e) {} sync(); });
    t.addEventListener('click', function () { n.classList.toggle('open'); });
})();
</script>
<?= $pageConfig ?? '' ?>
<?php foreach ($pageScripts as $src): ?>
<script src="<?= url($src) ?>?v=<?= (int) @filemtime(APP_ROOT . '/' . $src) ?>"></script>
<?php endforeach; ?>
<?= $pageInline ?? '' ?>
</body>
</html>
