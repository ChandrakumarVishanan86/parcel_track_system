<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$u = Auth::guard(['admin', 'dispatcher', 'hub', 'customer'], json: true);
try {
    json_response(['success' => true] + ($u['role'] === 'customer' ? ReportModel::customer((int) $u['customer_id']) : ReportModel::dashboard()));
} catch (Throwable $e) { error_log('[dash] ' . $e->getMessage()); json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error.'], 500); }
