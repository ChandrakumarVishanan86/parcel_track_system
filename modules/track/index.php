<?php
declare(strict_types=1);
$pubActive = 'track'; $pageTitle = 'Track a parcel';
require_once dirname(__DIR__, 2) . '/includes/public_header.php';
$n = htmlspecialchars((string) ($_GET['n'] ?? ''), ENT_QUOTES);
?>
<section class="track-hero">
    <h1>Where is my parcel?</h1>
    <p class="opacity-75 mb-0">Enter your tracking number to see live status, route and delivery estimate.</p>
    <form class="track-box" id="trkForm" autocomplete="off">
        <input id="trkNo" name="n" value="<?= $n ?>" placeholder="PT2611010001" spellcheck="false" maxlength="30" aria-label="Tracking number">
        <button class="btn btn-gradient px-4" type="submit"><i class="bi bi-search me-1"></i> Track</button>
    </form>
</section>
<div class="container pull-up pb-5" style="max-width:860px">
    <div id="trkOut"></div>
</div>
<?php
$pageScripts = ['assets/js/track.js'];
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
