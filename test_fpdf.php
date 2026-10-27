<?php
// Test avec Composer
require_once 'vendor/autoload.php';

// OU test sans Composer (décommentez la ligne suivante et commentez celle du dessus)
// require_once 'fpdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(40, 10, 'Test FPDF OK !');
$pdf->Output();