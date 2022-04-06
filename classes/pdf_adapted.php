<?php
// See https://tcpdf.org/examples/example_003/
class PDF_adapted extends TCPDF
{
    public $footer_text;
    
    // Page footer
    public function Footer ()
    {
        // Position at 15 mm from bottom
        $this->SetY(- 15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 
                $this->footer_text . "         " . $this->getAliasNumPage() .
                         '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
?>
