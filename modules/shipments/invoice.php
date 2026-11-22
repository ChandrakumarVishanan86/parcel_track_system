<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once APP_ROOT . '/includes/PdfBase.php';
$user = Auth::guard();
if ($user['role'] === 'rider') { http_response_code(403); exit('Not allowed.'); }
$s = ShipmentModel::find((int) ($_GET['id'] ?? 0));
if (!$s || !ShipmentModel::canAccess($user, $s)) { http_response_code(404); exit('Shipment not found.'); }

$pdf = new PtPdf('P', 'mm', 'A4'); $pdf->docTitle = 'Waybill & invoice'; $pdf->SetMargins(14, 14, 14); $pdf->AliasNbPages(); $pdf->AddPage();
$pdf->SetFont('Courier', 'B', 15); $pdf->Cell(110, 8, $s['tracking_no']);
$pdf->barcode(130, $pdf->GetY(), $s['tracking_no'], 66, 9); $pdf->Ln(11);
$pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(90, 100, 120);
$pdf->Cell(0, 5, pdf_text('Booked ' . date('d M Y H:i', strtotime($s['created_at'])) . '  |  ' . $s['service'] . ' - ' . $s['zone'] . '  |  Status: ' . $s['status']), 0, 1);
$pdf->SetTextColor(15, 23, 42); $pdf->Ln(3);

$y = $pdf->GetY();
foreach ([['SENDER', $s['sender_name'], $s['sender_phone'], $s['sender_address'], $s['origin_city']], ['RECEIVER', $s['receiver_name'], $s['receiver_phone'], $s['receiver_address'], $s['dest_city']]] as $i => [$h, $n, $p, $a, $c]) {
    $x = 14 + $i * 93; $pdf->SetXY($x, $y);
    $pdf->SetFillColor(244, 247, 250); $pdf->Rect($x, $y, 89, 30, 'F');
    $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetTextColor(90, 100, 120); $pdf->SetXY($x + 3, $y + 2); $pdf->Cell(0, 4, $h . ' - ' . strtoupper(pdf_text($c)));
    $pdf->SetFont('Helvetica', 'B', 11); $pdf->SetTextColor(15, 23, 42); $pdf->SetXY($x + 3, $y + 7); $pdf->Cell(83, 5, pdf_text($n));
    $pdf->SetFont('Helvetica', '', 9); $pdf->SetXY($x + 3, $y + 13); $pdf->Cell(83, 4, pdf_text($p));
    $pdf->SetXY($x + 3, $y + 18); $pdf->MultiCell(83, 4, pdf_text(mb_strimwidth($a, 0, 110, '...')), 0, 'L');
}
$pdf->SetY($y + 36);
$pdf->heading('Shipment details');
$pdf->SetFont('Helvetica', '', 9.5);
$det = [['Contents', $s['description'] . ' (' . $s['pieces'] . ' pc)'], ['Actual weight', number_format((float) $s['weight_kg'], 2) . ' kg'],
    ['Chargeable weight', number_format((float) $s['chargeable_kg'], 1) . ' kg' . ($s['length_cm'] ? '  (' . (float) $s['length_cm'] . ' x ' . (float) $s['width_cm'] . ' x ' . (float) $s['height_cm'] . ' cm)' : '')],
    ['Pickup / due', date('d M Y', strtotime($s['pickup_date'])) . ' / ' . date('d M Y', strtotime($s['promised_date']))],
    ['Handling', trim(($s['fragile'] ? 'Fragile  ' : '') . ($s['insured'] ? 'Insured (declared ' . money($s['declared_value']) . ')' : '')) ?: 'Standard']];
foreach ($det as [$k, $v]) { $pdf->SetTextColor(90, 100, 120); $pdf->Cell(40, 6, $k); $pdf->SetTextColor(15, 23, 42); $pdf->Cell(0, 6, pdf_text($v), 0, 1); }
$pdf->Ln(3); $pdf->heading('Charges');
$rows = [['Freight (' . $s['service'] . ', ' . $s['zone'] . ', ' . number_format((float) $s['chargeable_kg'], 1) . ' kg)', $s['freight']], ['Fuel surcharge (' . FUEL_PCT . '%)', $s['fuel']]];
if ((float) $s['fragile_fee'] > 0) { $rows[] = ['Fragile handling', $s['fragile_fee']]; }
if ((float) $s['cod_fee'] > 0) { $rows[] = ['COD handling fee', $s['cod_fee']]; }
if ((float) $s['insurance_fee'] > 0) { $rows[] = ['Insurance (' . INSURANCE_PCT . '%)', $s['insurance_fee']]; }
$rows[] = ['VAT (' . TAX_PCT . '%)', $s['tax']];
$pdf->SetFont('Helvetica', '', 10);
foreach ($rows as [$k, $v]) { $pdf->Cell(130, 7, pdf_text($k), 'B'); $pdf->Cell(52, 7, pdf_text(money($v)), 'B', 1, 'R'); }
$pdf->SetFont('Helvetica', 'B', 12); $pdf->SetFillColor(11, 18, 32); $pdf->SetTextColor(198, 244, 50);
$pdf->Cell(130, 10, '  TOTAL FREIGHT', 0, 0, 'L', true); $pdf->Cell(52, 10, pdf_text(money($s['total_charge'])) . '  ', 0, 1, 'R', true);
$pdf->SetTextColor(15, 23, 42); $pdf->Ln(4); $pdf->SetFont('Helvetica', '', 9.5);
$pdf->Cell(0, 6, pdf_text('Freight payer: ' . $s['payer'] . '   |   Payment: ' . $s['payment_status'] . ($s['payment_method'] ? ' (' . $s['payment_method'] . ')' : '') . ($s['customer_code'] && $s['is_credit'] ? '   |   Credit account ' . $s['customer_code'] : '')), 0, 1);
if ((float) $s['cod_amount'] > 0) { $pdf->SetFont('Helvetica', 'B', 10.5); $pdf->Cell(0, 7, pdf_text('Cash on delivery to collect from receiver: ' . money($s['cod_amount'])), 0, 1); }
$pdf->Ln(8); $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(120, 130, 145);
$pdf->MultiCell(0, 4, pdf_text('Track this parcel any time at ' . COMPANY_NAME . ' - enter ' . $s['tracking_no'] . ' on the public tracking page. Liability is limited to the declared value for insured parcels. Maximum weight ' . MAX_WEIGHT_KG . ' kg per shipment.'));
$pdf->Output('I', 'waybill-' . $s['tracking_no'] . '.pdf');
