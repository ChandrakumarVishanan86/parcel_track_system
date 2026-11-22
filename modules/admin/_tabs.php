<?php
$tabs = ['branches' => ['Branches', 'bi-building'], 'rates' => ['Rate cards', 'bi-tags-fill'], 'users' => ['Staff', 'bi-person-badge-fill'], 'audit' => ['Audit log', 'bi-shield-check'], 'sms' => ['SMS outbox', 'bi-chat-dots-fill']];
echo '<ul class="nav nav-pills mb-3 gap-1">';
foreach ($tabs as $k => [$l, $i]) { echo '<li class="nav-item"><a class="nav-link ' . ($adminTab === $k ? 'active' : '') . '" href="' . url('admin/' . $k) . '"><i class="bi ' . $i . ' me-1"></i>' . $l . '</a></li>'; }
echo '</ul>';
