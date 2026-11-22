<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once APP_ROOT . '/includes/PdfBase.php';
$user = Auth::guard();
if ($user['role'] === 'rider') { http_response_code(403); exit('Not allowed.'); }
$s = ShipmentModel::find((int) ($_GET['id'] ?? 0));
if (!$s || !ShipmentModel::canAccess($user, $s)) { http_response_code(404); exit('Shipment not found.'); }

$pdf = new PtPdf('P', 'mm', [105, 148]);
$pdf->letterhead = false; $pdf->SetMargins(6, 6, 6); $pdf->SetAutoPageBreak(false); $pdf->AddPage();
$pdf->SetDrawColor(11, 18, 32); $pdf->SetLineWidth(0.6); $pdf->Rect(4, 4, 97, 140);
// header band
$pdf->SetFillColor(11, 18, 32); $pdf->Rect(4, 4, 97, 14, 'F');
$pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 13); $pdf->SetXY(8, 7); $pdf->Cell(40, 8, 'Parcel');
$pdf->SetTextColor(198, 244, 50); $pdf->SetXY(8 + $pdf->GetStringWidth('Parcel'), 7); $pdf->Cell(30, 8, 'Track');
$pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 12); $pdf->SetXY(50, 7); $pdf->Cell(47, 8, strtoupper($s['service']), 0, 0, 'R');
// route
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('Helvetica', 'B', 20); $pdf->SetXY(8, 21); $pdf->Cell(40, 10, pdf_text(strtoupper(substr($s['origin_city'], 0, 3))));
$pdf->SetXY(40, 21); $pdf->Cell(20, 10, '>>', 0, 0, 'C');
$pdf->SetXY(57, 21); $pdf->Cell(40, 10, pdf_text(strtoupper(substr($s['dest_city'], 0, 3))), 0, 0, 'R');
$pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(90, 100, 120);
$pdf->SetXY(8, 30); $pdf->Cell(45, 4, pdf_text($s['origin_city'])); $pdf->SetXY(52, 30); $pdf->Cell(45, 4, pdf_text($s['dest_city'] . '  (' . $s['zone'] . ')'), 0, 0, 'R');
// to
$pdf->SetDrawColor(200, 206, 216); $pdf->SetLineWidth(0.2); $pdf->Line(6, 36, 99, 36);
$pdf->SetTextColor(15, 23, 42); $pdf->SetFont('Helvetica', 'B', 7); $pdf->SetXY(8, 37); $pdf->Cell(0, 4, 'DELIVER TO');
$pdf->SetFont('Helvetica', 'B', 12); $pdf->SetXY(8, 41); $pdf->Cell(0, 6, pdf_text($s['receiver_name']));
$pdf->SetFont('Helvetica', '', 9); $pdf->SetXY(8, 47); $pdf->MultiCell(89, 4.4, pdf_text($s['receiver_address']), 0, 'L');
$pdf->SetFont('Helvetica', 'B', 10); $pdf->SetXY(8, 58); $pdf->Cell(0, 5, pdf_text('Tel: ' . $s['receiver_phone']));
$pdf->Line(6, 66, 99, 66);
$pdf->SetFont('Helvetica', 'B', 7); $pdf->SetXY(8, 67); $pdf->Cell(0, 4, 'FROM');
$pdf->SetFont('Helvetica', '', 8.5); $pdf->SetXY(8, 71); $pdf->Cell(0, 4, pdf_text($s['sender_name'] . '  -  ' . $s['sender_phone']));
$pdf->SetXY(8, 75.5); $pdf->SetTextColor(90, 100, 120); $pdf->Cell(0, 4, pdf_text(mb_strimwidth($s['sender_address'], 0, 70, '...')));
$pdf->SetTextColor(15, 23, 42); $pdf->Line(6, 82, 99, 82);
// stats
$cols = [['WEIGHT', number_format((float) $s['chargeable_kg'], 1) . ' kg'], ['PIECES', (string) $s['pieces']], ['PICKUP', date('d M', strtotime($s['pickup_date']))], ['DUE', date('d M', strtotime($s['promised_date']))]];
$x = 8;
foreach ($cols as [$l, $v]) { $pdf->SetFont('Helvetica', '', 6.5); $pdf->SetTextColor(90, 100, 120); $pdf->SetXY($x, 84); $pdf->Cell(22, 3.5, $l); $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(15, 23, 42); $pdf->SetXY($x, 87.5); $pdf->Cell(22, 5, $v); $x += 23; }
$pdf->Line(6, 95, 99, 95);
// COD / payment block
$cod = (float) $s['cod_amount'];
if ($cod > 0) {
    $pdf->SetFillColor(198, 244, 50); $pdf->Rect(6, 97, 93, 14, 'F');
    $pdf->SetTextColor(11, 18, 32); $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetXY(9, 98.5); $pdf->Cell(40, 4, 'COLLECT ON DELIVERY');
    $pdf->SetFont('Helvetica', 'B', 16); $pdf->SetXY(9, 102.5); $pdf->Cell(87, 7, pdf_text(money($cod)), 0, 0, 'R');
} else {
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(15, 23, 42); $pdf->SetXY(8, 100);
    $pdf->Cell(0, 5, $s['payer'] === 'Receiver' ? pdf_text('FREIGHT TO PAY: ' . money($s['total_charge'])) : ($s['payment_status'] === 'Paid' ? 'PREPAID' : 'FREIGHT: ' . pdf_text(money($s['total_charge'])) . ' (' . strtoupper($s['payment_status']) . ')'));
}
if ($s['fragile']) {
    $pdf->SetFillColor(229, 72, 77); $pdf->Rect(8, 113, 30, 6, 'F'); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetXY(8, 113.8); $pdf->Cell(30, 4.5, 'FRAGILE', 0, 0, 'C');
}
$pdf->SetTextColor(90, 100, 120); $pdf->SetFont('Helvetica', '', 7); $pdf->SetXY(42, 114); $pdf->Cell(55, 4, pdf_text(mb_strimwidth($s['description'], 0, 38, '...')), 0, 0, 'R');
// barcode
$pdf->barcode(14, 121, $s['tracking_no'], 77, 14);
$pdf->SetTextColor(15, 23, 42); $pdf->SetFont('Courier', 'B', 11); $pdf->SetXY(4, 136); $pdf->Cell(97, 5, $s['tracking_no'], 0, 0, 'C');
$pdf->Output('I', 'label-' . $s['tracking_no'] . '.pdf');
