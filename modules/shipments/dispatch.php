<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::DESK);
$pageTitle = 'Dispatch board'; $pageSub = 'Assign riders for pickups and last-mile delivery'; $activeNav = 'dispatch'; $pageScripts = ['assets/js/dispatch.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<ul class="nav nav-pills mb-3 gap-1" id="tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pPick">Pickups <span class="badge bg-dark ms-1" id="cPick">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pDeliv">Deliveries <span class="badge bg-dark ms-1" id="cDeliv">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pExc">Exceptions <span class="badge bg-danger ms-1" id="cExc">0</span></button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="pPick"><div id="listPick"></div></div>
    <div class="tab-pane fade" id="pDeliv"><div id="listDeliv"></div></div>
    <div class="tab-pane fade" id="pExc"><div id="listExc"></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
