<?php
declare(strict_types=1);
require_once APP_ROOT . '/lib/fpdf/fpdf.php';

/** Latin-1 safe text for FPDF core fonts. */
function pdf_text(?string $s): string
{
    $s = (string) $s;
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
    return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
}

/** ParcelTrack PDF base: navy letterhead with a lime accent, plus a Code 39 barcode. */
class PtPdf extends FPDF
{
    public string $docTitle = '';
    public bool $letterhead = true;

    public function Header(): void
    {
        if (!$this->letterhead) { return; }
        $this->SetFillColor(11, 18, 32); $this->Rect(0, 0, 210, 28, 'F');
        $this->SetFillColor(198, 244, 50); $this->Rect(0, 28, 210, 1.6, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 18); $this->SetXY(14, 7); $this->Cell(60, 8, 'Parcel');
        $this->SetTextColor(198, 244, 50); $this->SetXY(14 + $this->GetStringWidth('Parcel') + 0.5, 7); $this->Cell(40, 8, 'Track');
        $this->SetTextColor(190, 200, 220); $this->SetFont('Helvetica', '', 8.5); $this->SetXY(14, 17); $this->Cell(0, 5, pdf_text(COMPANY_ADDRESS . '  |  ' . COMPANY_PHONE));
        $this->SetTextColor(255, 255, 255); $this->SetFont('Helvetica', 'B', 11); $this->SetXY(-84, 9); $this->Cell(70, 6, pdf_text(strtoupper($this->docTitle)), 0, 0, 'R');
        $this->SetXY(-84, 16); $this->SetFont('Helvetica', '', 8.5); $this->Cell(70, 5, 'Printed: ' . date('d M Y H:i'), 0, 0, 'R');
        $this->SetY(36); $this->SetTextColor(15, 23, 42);
    }
    public function Footer(): void
    {
        if (!$this->letterhead) { return; }
        $this->SetY(-14); $this->SetDrawColor(220, 226, 232); $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->SetFont('Helvetica', 'I', 8); $this->SetTextColor(120, 130, 145);
        $this->Cell(0, 8, 'Computer generated document  -  ' . pdf_text(COMPANY_NAME) . '    Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    public function heading(string $t): void
    {
        $this->SetFont('Helvetica', 'B', 10.5); $this->SetTextColor(60, 90, 0); $this->Cell(0, 8, pdf_text(strtoupper($t)), 0, 1);
        $this->SetTextColor(15, 23, 42);
    }

    private const C39 = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
    ];
    /** Code 39 barcode; bars are drawn as filled rects so scanners can read them from a printout. */
    public function barcode(float $x, float $y, string $code, float $w, float $h): void
    {
        $code = '*' . strtoupper($code) . '*';
        $units = 0.0;
        foreach (str_split($code) as $ch) { foreach (str_split(self::C39[$ch] ?? self::C39['-']) as $p) { $units += $p === 'w' ? 2.5 : 1; } $units += 1; }
        $unit = $w / ($units - 1);
        $this->SetFillColor(0, 0, 0);
        $cx = $x;
        foreach (str_split($code) as $ch) {
            foreach (str_split(self::C39[$ch] ?? self::C39['-']) as $i => $p) {
                $bw = ($p === 'w' ? 2.5 : 1) * $unit;
                if ($i % 2 === 0) { $this->Rect($cx, $y, $bw, $h, 'F'); }
                $cx += $bw;
            }
            $cx += $unit;
        }
    }
}
