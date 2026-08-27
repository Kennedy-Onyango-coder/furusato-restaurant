<?php
require_once __DIR__ . '/includes/functions.php';

$menu = getJsonData('menu');
$settings = getJsonData('settings');

class FPDF {
    public $page;
    public $n;
    public $offsets;
    public $buffer;
    public $pages;
    public $state;
    public $compress;
    public $k;
    public $DefOrientation;
    public $CurOrientation;
    public $StdPageSizes;
    public $DefPageSize;
    public $CurPageSize;
    public $PageSizes;
    public $wPt;
    public $hPt;
    public $w;
    public $h;
    public $lMargin;
    public $tMargin;
    public $rMargin;
    public $bMargin;
    public $cMargin;
    public $x;
    public $y;
    public $lasth;
    public $LineWidth;
    public $fontpath;
    public $CoreFonts;
    public $fonts;
    public $FontFiles;
    public $diffs;
    public $FontFamily;
    public $FontStyle;
    public $underline;
    public $CurrentFont;
    public $FontSizePt;
    public $FontSize;
    public $DrawColor;
    public $FillColor;
    public $TextColor;
    public $ColorFlag;
    public $ws;
    public $AutoPageBreak = true;
    public $PageBreakTrigger = 0;

    public function __construct($orientation='P', $unit='mm', $size='A4') {
        $this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28), 'letter'=>array(612,792), 'legal'=>array(612,1008));
        $size = strtolower($size);
        if(!isset($this->StdPageSizes[$size])) $size = 'a4';
        $this->DefPageSize = $this->StdPageSizes[$size];
        $this->DefOrientation = strtoupper($orientation[0]);
        $this->k = 72/25.4;
        $scale = $unit==='mm' ? 72/25.4 : ($unit==='cm' ? 72/2.54 : ($unit==='in' ? 72 : 1));
        $this->k = $scale;
        $this->CurOrientation = $this->DefOrientation;
        $this->CurPageSize = $this->DefPageSize;
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = array();
        $this->state = 0;
        $this->fonts = array();
        $this->FontFiles = array();
        $this->diffs = array();
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->FontSize = 12/$this->k;
        $this->underline = false;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->bMargin = 25;
        $this->PageBreakTrigger = $this->CurPageSize[1] * $this->k - $this->bMargin;
        $this->AddPage();
    }

    public function AddPage($orientation='', $size='') {
        if($this->state==0) $this->Open();
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->SetFont('Helvetica', '', 10);
    }

    public function Open() {
        $this->state = 1;
    }

    public function SetFont($family, $style='', $size=0) {
        $family = strtolower($family);
        if($family=='') $family = $this->FontFamily;
        $style = strtoupper($style);
        $style = str_replace('U', '', $style);
        if($size==0) $size = $this->FontSizePt;
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        $key = $family.$style;
        if(!isset($this->fonts[$key])) {
            $this->fonts[$key] = array('i'=>$this->n, 'type'=>'core', 'name'=>ucfirst($family), 'up'=>-100, 'ut'=>50, 'cw'=>$this->getCoreFontWidths($family));
            $this->n++;
        }
        $this->CurrentFont = &$this->fonts[$key];
    }

    private function getCoreFontWidths($family) {
        $cw = array();
        for($i=0;$i<256;$i++) $cw[$i]=600;
        if($family=='helvetica') { for($i=32;$i<256;$i++) $cw[$i]=573; }
        if($family=='times') { for($i=32;$i<256;$i++) $cw[$i]=500; }
        if($family=='courier') { for($i=0;$i<256;$i++) $cw[$i]=600; }
        return $cw;
    }

    public function SetFontSize($size) {
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
    }

    public function SetTextColor($r, $g=null, $b=null) {
        if($g===null) { $this->TextColor = sprintf('%.3F g', $r/255); }
        else { $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255); }
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    public function SetDrawColor($r, $g=null, $b=null) {
        if($g===null) { $this->DrawColor = sprintf('%.3F G', $r/255); }
        else { $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255); }
    }

    public function SetFillColor($r, $g=null, $b=null) {
        if($g===null) { $this->FillColor = sprintf('%.3F g', $r/255); }
        else { $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255); }
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    public function SetLineWidth($width) {
        $this->LineWidth = $width;
        $this->pages[$this->page] .= sprintf('%.2F w', $width*$this->k)."\n";
    }

    public function SetAutoPageBreak($auto, $margin = 0) {
        $this->AutoPageBreak = $auto;
        $this->bMargin       = $margin;
        $this->PageBreakTrigger = $this->CurPageSize[1] * $this->k - $margin;
    }

    public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        // Auto page break
        if ($this->AutoPageBreak && ($this->y + $h) > $this->PageBreakTrigger && $this->page > 0) {
            $this->AddPage();
        }
        $txt = $this->utf8ToLatin($txt);
        $s = '';
        if($fill || $border==1) {
            if($fill) $s .= $this->FillColor.' ';
            else $s .= $this->TextColor.' ';
            $s .= sprintf('%.2F %.2F %.2F %.2F re f', $this->x*$this->k, ($this->h-$this->y)*$this->k, $w*$this->k, -$h*$this->k)."\n";
        }
        if($txt!='') {
            $s .= $this->TextColor.' ';
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', $this->x*$this->k+$this->cMargin*$this->k, ($this->h-($this->y+.5*$h+.3*$this->FontSize))*$this->k, $this->escape($txt))."\n";
        }
        $this->pages[$this->page] .= $s;
        if($ln==1) { $this->x = $this->lMargin; $this->y += $h; }
        else { $this->x += $w; }
    }

    public function MultiCell($w, $h, $txt, $border=0, $align='L', $fill=false) {
        $txt = $this->utf8ToLatin($txt);
        $lines = explode("\n", wordwrap($txt, floor($w/($this->FontSize*0.6)), "\n", true));
        foreach($lines as $line) {
            $this->Cell($w, $h, $line, $border, 1, $align, $fill);
        }
    }

    public function Ln($h=null) {
        $this->x = $this->lMargin;
        if($h===null) $this->y += $this->lasth;
        else $this->y += $h;
    }

    public function SetX($x) { $this->x = $x; }
    public function SetY($y) { $this->y = $y; $this->x = $this->lMargin; }
    public function SetXY($x, $y) { $this->x = $x; $this->y = $y; }
    public function GetX() { return $this->x; }
    public function GetY() { return $this->y; }

    public function Line($x1, $y1, $x2, $y2) {
        $s = $this->DrawColor.' ';
        $s .= sprintf('%.2F %.2F m %.2F %.2F l S', $x1*$this->k, ($this->h-$y1)*$this->k, $x2*$this->k, ($this->h-$y2)*$this->k)."\n";
        $this->pages[$this->page] .= $s;
    }

    public function Rect($x, $y, $w, $h, $style='') {
        $s = '';
        if($style=='F' || $style=='DF' || $style=='FD') $s .= $this->FillColor.' ';
        if($style=='D' || $style=='DF' || $style=='FD') $s .= $this->DrawColor.' ';
        $s .= sprintf('%.2F %.2F %.2F %.2F re', $x*$this->k, ($this->h-$y)*$this->k, $w*$this->k, -$h*$this->k)."\n";
        if($style=='F' || $style=='DF' || $style=='FD') $s .= "f\n";
        if($style=='D' || $style=='DF' || $style=='FD') $s .= "S\n";
        $this->pages[$this->page] .= $s;
    }

    private function escape($s) {
        return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $s);
    }

    private function utf8ToLatin($s) {
        return @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    }

    public function Output($name='', $dest='') {
        if($name=='' && $dest=='') { $dest='I'; }
        if($name=='') $name='doc.pdf';
        $pdf = $this->buildPDF();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        exit;
    }

    private function buildPDF() {
        $this->state = 3;
        $pdf = '%PDF-1.3'."\n";
        $offsets = array();
        $pages = array();
        foreach($this->pages as $p) {
            $pages[] = '<< /Length '.strlen($p).' >>'."\n".'stream'."\n".$p.'endstream';
        }
        // Catalog
        $pdf .= '1 0 obj'."\n".'<< /Type /Catalog /Pages 2 0 R >>'."\n".'endobj'."\n";
        // Pages
        $kids = '';
        for($i=0;$i<count($pages);$i++) {
            $kids .= ($i+3).' 0 R ';
        }
        $pdf .= '2 0 obj'."\n".'<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pages).' >>'."\n".'endobj'."\n";
        // Fonts
        foreach($this->fonts as $f) {
            $pdf .= $f['i'].' 0 obj'."\n".'<< /Type /Font /Subtype /Type1 /BaseFont /'.$f['name'].' >>'."\n".'endobj'."\n";
        }
        // Pages content
        foreach($pages as $i=>$p) {
            $pdf .= ($i+3).' 0 obj'."\n".$p."\n".'endobj'."\n";
        }
        // XRef
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n";
        $pdf .= '0 '.($this->n+count($pages)+1)."\n";
        $pdf .= '0000000000 65535 f '."\n";
        $pdf .= '0000000009 00000 n '."\n";
        $pdf .= '0000000058 00000 n '."\n";
        for($i=0;$i<count($pages);$i++) {
            $pdf .= sprintf('%010d 00000 n '."\n", 0);
        }
        $pdf .= 'trailer'."\n".'<< /Size '.($this->n+count($pages)+1).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n".$xref."\n".'%%EOF';
        return $pdf;
    }
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 25);

foreach ($menu['categories'] as $category) {
    if (!$category['visible']) continue;

    $pdf->AddPage();

    // Header
    $pdf->SetFillColor(13, 27, 42);
    $pdf->Rect(0, 0, 210, 40, 'F');
    $pdf->SetTextColor(212, 175, 122);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetXY(25, 10);
    $pdf->Cell(160, 10, 'Furusato Japanese Restaurant', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY(25, 22);
    $pdf->Cell(160, 6, 'Taste That Carries You Home', 0, 1, 'C');
    $pdf->SetXY(25, 30);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Cell(160, 5, 'Ring Road Parklands, Westlands, Nairobi | 0722 488 706', 0, 1, 'C');

    // Category heading
    $pdf->SetY(50);
    $pdf->SetTextColor(13, 27, 42);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, $category['icon'] . ' ' . $category['label'], 0, 1);
    if (!empty($category['labelJp'])) {
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 5, $category['labelJp'], 0, 1);
    }
    $pdf->SetDrawColor(212, 175, 122);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(25, $pdf->GetY()+2, 185, $pdf->GetY()+2);
    $pdf->SetY($pdf->GetY() + 8);

    // Subcategories
    foreach ($category['subcategories'] ?? [] as $sub) {
        if (!$sub['visible']) continue;
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(42, 125, 158);
        $pdf->Cell(0, 8, $sub['label'], 0, 1);
        $pdf->SetY($pdf->GetY() + 2);

        foreach ($sub['items'] ?? [] as $item) {
            if (!$item['visible']) continue;
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(44, 44, 44);
            $name = $item['name'];
            if (!empty($item['badge'])) $name .= '  [' . $item['badge'] . ']';
            $pdf->Cell(120, 6, $name, 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(192, 57, 43);
            $pdf->Cell(40, 6, 'Ksh ' . number_format($item['price']), 0, 1, 'R');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(160, 4, $item['description']);
            $pdf->SetY($pdf->GetY() + 3);
        }
    }

    // Main items
    foreach ($category['items'] ?? [] as $item) {
        if (!$item['visible']) continue;
        if ($pdf->GetY() > 260) $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(44, 44, 44);
        $name = $item['name'];
        if (!empty($item['badge'])) $name .= '  [' . $item['badge'] . ']';
        $pdf->Cell(120, 6, $name, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(192, 57, 43);
        $pdf->Cell(40, 6, 'Ksh ' . number_format($item['price']), 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(160, 4, $item['description']);
        $pdf->SetY($pdf->GetY() + 3);
    }

    // Footer
    $pdf->SetY(-20);
    $pdf->SetDrawColor(212, 175, 122);
    $pdf->Line(25, $pdf->GetY(), 185, $pdf->GetY());
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'All prices inclusive of VAT and Levy. Gift Vouchers available.', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Furusato Japanese Restaurant | Est. 1 May 2001 | Ring Road Parklands, Westlands', 0, 1, 'C');
}

$pdf->Output('FurusatoMenu.pdf', 'D');
