<?php
// AutoPrintDirect.php
// Génère directement le PDF avec QR code ISK-NG unique
// Exemple: AutoPrintDirect.php?session=2&dist=1

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/pdf/ScorePDF.inc.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Sessions.inc.php');
require_once('Common/Lib/ScorecardsLib.php');

CheckTourSession(true);
checkFullACL(AclQualification, '', AclReadOnly);

// Récupérer les paramètres d'URL
$session = isset($_GET['session']) ? intval($_GET['session']) : 1;
$distance = isset($_GET['dist']) ? intval($_GET['dist']) : 1;
$scoreType = isset($_GET['type']) ? $_GET['type'] : 'Complete';

// Valider les paramètres
if ($session < 1) $session = 1;
if ($distance < 1) $distance = 1;

// Vérifier si le tournoi existe
$Select = "SELECT ToNumDist AS TtNumDist FROM Tournament WHERE ToId=" . StrSafe_DB($_SESSION['TourId']);
$RsTour = safe_r_sql($Select);

if (safe_num_rows($RsTour) == 1) {
    $RowTour = safe_fetch($RsTour);
    safe_free_result($RsTour);
    
    // Vérifier si la distance existe
    if ($distance > $RowTour->TtNumDist) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h2>Erreur</h2>";
        echo "<p>La distance $distance n'est pas disponible dans ce tournoi.</p>";
        echo "<p>Distances disponibles : 1 à " . $RowTour->TtNumDist . "</p>";
        exit();
    }
    
    // Définir les paramètres avec UN SEUL QR code (ISK-NG)
    $_REQUEST = array(
        'x_Session' => $session,
        'x_From' => '',
        'x_To' => '',
        'ScoreDraw' => $scoreType,
        'ScoreDist' => array($distance),
        'ScorePageHeaderFooter' => 1,
        'ScoreFlags' => 1,
        'noEmpty' => 1,
        'ScoreBarcode' => module_exists("Barcodes") ? 1 : 0,
        
        // UNIQUEMENT le QR code ISK-NG - PAS de ScoreQrPersonal
        'QRCode' => array('ISK-NG'), // ← C'est le bon !
        
        // Option pour forcer un seul QR code
        'SingleQR' => 1,
    );
    
    // NE PAS inclure ScoreQrPersonal pour éviter les doublons
    
    // Générer le PDF directement
    $pdf = CreateSessionScorecard(
        $_REQUEST['x_Session'],
        $_REQUEST['x_From'],
        (empty($_REQUEST['x_To']) ? $_REQUEST['x_From'] : $_REQUEST['x_To']),
        $_REQUEST
    );
    
    // Nom du fichier avec les paramètres
    $filename = "Feuilles_Session{$session}_Dist{$distance}.pdf";
    
    // Envoyer le PDF au navigateur
    $pdf->Output($filename, 'I');
    
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>Erreur</h2>";
    echo "<p>Tournoi non trouvé.</p>";
}