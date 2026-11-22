<?php
/**
 * ParcelTrack - setup self-check.  Open:  http://localhost/<your-folder>/check.php
 * Shows exactly why the dashboard may not load (PHP version, extensions, database, URL rewriting, CDN).
 * DELETE THIS FILE once everything is green.
 * (Written in old-style PHP on purpose so it still runs on an outdated PHP and can report that.)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$rows = array();
function row(&$rows, $ok, $label, $detail)
{
    $rows[] = array($ok, $label, $detail);
}

// 1. PHP version
$v = PHP_VERSION;
if (version_compare($v, '8.0.0', '<')) {
    row($rows, false, 'PHP version', "$v is too old. ParcelTrack needs PHP 8.0+ (8.1+ recommended). In WAMP: tray icon > PHP > Version.");
} elseif (version_compare($v, '8.1.0', '<')) {
    row($rows, true, 'PHP version', "$v (works; 8.1+ recommended)");
} else {
    row($rows, true, 'PHP version', $v);
}

// 2. Extensions
foreach (array('pdo_mysql', 'mbstring', 'json', 'session') as $ext) {
    row($rows, extension_loaded($ext), "Extension $ext", extension_loaded($ext) ? 'loaded' : 'MISSING - enable it in php.ini / WAMP > PHP > PHP extensions');
}

// 3. Config + APP_BASE
$cfg = dirname(__FILE__) . '/config/config.php';
$haveCfg = false;
if (version_compare($v, '8.0.0', '>=') && is_file($cfg)) {
    require_once $cfg;
    $haveCfg = true;
    $actual = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $same = (strcasecmp($actual, APP_BASE) === 0);
    row($rows, $same, 'URL base path', 'APP_BASE = "' . APP_BASE . '", this page is served from "' . $actual . '"'
        . ($same ? '' : ' - they differ: set APP_BASE in config/config.php to "' . $actual . '"'));
}

// 4. Database
if ($haveCfg) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        row($rows, true, 'Database connection', 'Connected to ' . DB_NAME . ' on ' . DB_HOST . ':' . DB_PORT);
        $need = array('branches', 'customers', 'users', 'rate_cards', 'shipments', 'shipment_events', 'manifests', 'manifest_items', 'cod_settlements', 'rider_cash', 'sms_log', 'audit_log');
        $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($need, $have);
        row($rows, count($missing) === 0, 'Tables', count($missing) ? 'Missing: ' . implode(', ', $missing) . ' - import sql/database.sql in phpMyAdmin' : 'all ' . count($need) . ' tables present');
        if (!count($missing)) {
            $u = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $s = (int) $pdo->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
            row($rows, $u > 0, 'Seed data', "$u users, $s shipments" . ($u > 0 ? '' : ' - the database is empty, re-import sql/database.sql'));
        }
    } catch (Exception $e) {
        row($rows, false, 'Database connection', htmlspecialchars($e->getMessage()) . ' - check DB_PORT (WAMP MariaDB is often 3307), DB_USER and DB_PASS in config/config.php, and that you imported sql/database.sql');
    }
}

// 5. Apache modules (only visible when PHP runs as an Apache module)
if (function_exists('apache_get_modules')) {
    $has = in_array('mod_rewrite', apache_get_modules(), true);
    row($rows, $has, 'Apache mod_rewrite', $has ? 'enabled' : 'DISABLED - WAMP tray > Apache > Apache modules > rewrite_module');
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ParcelTrack setup check</title>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:860px;margin:32px auto;padding:0 16px;color:#1e293b}
h1{margin-bottom:4px} .sub{color:#64748b;margin-bottom:20px}
table{width:100%;border-collapse:collapse} td{padding:9px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top}
.ok{color:#15803d;font-weight:700} .bad{color:#b91c1c;font-weight:700} .wait{color:#b45309;font-weight:700}
code{background:#f1f5f9;padding:1px 5px;border-radius:4px}
</style></head><body>
<h1>ParcelTrack setup check</h1>
<div class="sub">Delete <code>check.php</code> when everything below is green.</div>
<table>
<?php foreach ($rows as $r): ?>
<tr><td style="width:34px" class="<?php echo $r[0] ? 'ok' : 'bad'; ?>"><?php echo $r[0] ? '&#10003;' : '&#10007;'; ?></td><td style="width:190px"><b><?php echo $r[1]; ?></b></td><td><?php echo $r[2]; ?></td></tr>
<?php endforeach; ?>
<tr><td id="rwIcon" class="wait">…</td><td><b>Clean URLs (rewrite)</b></td><td id="rwMsg">testing…</td></tr>
<tr><td id="cdnIcon" class="wait">…</td><td><b>Chart.js / Bootstrap CDN</b></td><td id="cdnMsg">testing…</td></tr>
</table>
<p style="margin-top:22px"><a href="<?php echo $haveCfg ? htmlspecialchars(APP_BASE) . '/login' : 'login.php'; ?>">&rarr; Go to login</a></p>
<script>
(function () {
    var base = <?php echo json_encode($haveCfg ? APP_BASE : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')); ?>;
    function set(id, ok, msg) {
        document.getElementById(id + 'Icon').className = ok ? 'ok' : 'bad';
        document.getElementById(id + 'Icon').innerHTML = ok ? '&#10003;' : '&#10007;';
        document.getElementById(id + 'Msg').innerHTML = msg;
    }
    // /dashboard/api must answer with JSON 401 (not logged in). A 404 means rewriting / folder name is wrong.
    fetch(base + '/dashboard/api', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.text().then(function (t) {
            var json = false; try { JSON.parse(t); json = true; } catch (e) {}
            if (json && (r.status === 401 || r.status === 200)) { set('rw', true, base + '/dashboard/api reached the app (HTTP ' + r.status + ')'); }
            else { set('rw', false, base + '/dashboard/api returned HTTP ' + r.status + ' (not the app). Enable <code>rewrite_module</code>, set <code>AllowOverride All</code> for your www folder in httpd.conf, restart Apache.'); }
        }); })
        .catch(function () { set('rw', false, 'Request failed.'); });
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    s.onload = function () { set('cdn', !!window.Chart, window.Chart ? 'loaded from cdn.jsdelivr.net' : 'script loaded but Chart is missing'); };
    s.onerror = function () { set('cdn', false, 'could not reach cdn.jsdelivr.net - no internet / blocked. Dashboard KPIs still work but charts and styling will not.'); };
    document.head.appendChild(s);
})();
</script>
</body></html>
