<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(Auth::DESK);
$from = (string) ($_GET['from'] ?? ''); $to = (string) ($_GET['to'] ?? ''); $type = ($_GET['type'] ?? '') === 'cod' ? 'cod' : 'shipments';
if (!valid_date($from) || !valid_date($to) || $from > $to) { http_response_code(422); exit('Invalid date range.'); }
[$head, $rows] = ReportModel::export($type, $from, $to);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="parceltrack_' . $type . '_' . $from . '_' . $to . '.csv"');
$o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
$safe = static fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;   // CSV formula injection guard
fputcsv($o, $head);
foreach ($rows as $r) { fputcsv($o, array_map($safe, array_values($r))); }
fclose($o);
Audit::log('export', 'report', null, "$type $from..$to (" . count($rows) . ' rows)');
