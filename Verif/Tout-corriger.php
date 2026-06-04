<?php
/**
 * Correction de toutes les anomalies en une seule opération
 * MODIFICATION : La vérification se fait maintenant par (Division + Classe)
 * VERSION ROBUSTE avec débogage
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher d'erreurs dans la réponse JSON

CheckTourSession(true);
checkACL(AclParticipants, AclReadWrite);

$JSON = array('success' => false, 'message' => 'Erreur', 'corriges' => 0);

if (IsBlocked(BIT_BLOCK_PARTICIPANT)) {
    $JSON['message'] = get_text('Blocked');
    echo json_encode($JSON);
    exit;
}

$TourId = $_SESSION['TourId'];

// ============================================================================
// MÉTHODE SIMPLIFIÉE : Correction directe sans sous-requêtes imbriquées complexes
// ============================================================================

// ÉTAPE 1: Trouver les IDs des inscriptions à corriger (premiers départs avec EnIndFEvent=0)
$QueryGetFirstToFix = "
    SELECT e.EnId
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND e.EnIndFEvent = 0
    AND q.QuSession = (
        SELECT MIN(q2.QuSession)
        FROM Entries e2
        INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
        WHERE e2.EnCode = e.EnCode
        AND e2.EnTournament = e.EnTournament
        AND e2.EnDivision = e.EnDivision
        AND e2.EnClass = e.EnClass
    )
";

// ÉTAPE 2: Trouver les IDs des inscriptions à corriger (départs suivants avec EnIndFEvent=1)
$QueryGetLaterToFix = "
    SELECT e.EnId
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND e.EnIndFEvent = 1
    AND q.QuSession > (
        SELECT MIN(q2.QuSession)
        FROM Entries e2
        INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
        WHERE e2.EnCode = e.EnCode
        AND e2.EnTournament = e.EnTournament
        AND e2.EnDivision = e.EnDivision
        AND e2.EnClass = e.EnClass
    )
";

// Récupérer les IDs à mettre à 1
$idsToSet1 = array();
$Rs = safe_r_sql($QueryGetFirstToFix);
while ($row = safe_fetch($Rs)) {
    $idsToSet1[] = $row->EnId;
}

// Récupérer les IDs à mettre à 0
$idsToSet0 = array();
$Rs = safe_r_sql($QueryGetLaterToFix);
while ($row = safe_fetch($Rs)) {
    $idsToSet0[] = $row->EnId;
}

$totalCorriges = 0;

// Mettre à jour les premiers départs (passer de 0 à 1)
if (!empty($idsToSet1)) {
    $idsString = implode(',', $idsToSet1);
    $Update = "UPDATE Entries SET EnIndFEvent = 1, EnTimestamp = EnTimestamp WHERE EnId IN ($idsString)";
    if (safe_w_sql($Update)) {
        $totalCorriges += count($idsToSet1);
    }
}

// Mettre à jour les départs suivants (passer de 1 à 0)
if (!empty($idsToSet0)) {
    $idsString = implode(',', $idsToSet0);
    $Update = "UPDATE Entries SET EnIndFEvent = 0, EnTimestamp = EnTimestamp WHERE EnId IN ($idsString)";
    if (safe_w_sql($Update)) {
        $totalCorriges += count($idsToSet0);
    }
}

if ($totalCorriges > 0) {
    $JSON['success'] = true;
    $JSON['message'] = 'Corrections effectuées avec succès';
    $JSON['corriges'] = $totalCorriges;
} else {
    $JSON['success'] = true;
    $JSON['message'] = 'Aucune anomalie à corriger';
    $JSON['corriges'] = 0;
}

echo json_encode($JSON);
?>