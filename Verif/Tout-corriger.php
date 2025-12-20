<?php
/**
 * Correction de toutes les anomalies en une seule opération
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

header('Content-Type: application/json');

CheckTourSession(true);
checkACL(AclParticipants, AclReadWrite);

$JSON = array('success' => false, 'message' => 'Erreur', 'corriges' => 0);

if (IsBlocked(BIT_BLOCK_PARTICIPANT)) {
    $JSON['message'] = get_text('Blocked');
    echo json_encode($JSON);
    exit;
}

$TourId = $_SESSION['TourId'];

// D'abord, compter le nombre d'anomalies avant correction
$QueryCountBefore = "
    SELECT COUNT(*) as nb_anomalies
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND (
        (q.QuSession = (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 0)
        OR
        (q.QuSession > (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 1)
    )
";

$RsCount = safe_r_sql($QueryCountBefore);
$RowCount = safe_fetch($RsCount);
$nbAnomalies = $RowCount->nb_anomalies;

if ($nbAnomalies == 0) {
    $JSON['success'] = true;
    $JSON['message'] = 'Aucune anomalie à corriger';
    $JSON['corriges'] = 0;
    echo json_encode($JSON);
    exit;
}

// Ensuite, effectuer la correction
$QueryCorrection = "
    UPDATE Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    SET e.EnIndFEvent = 
        CASE 
            WHEN q.QuSession = (
                SELECT MIN(q2.QuSession) 
                FROM Entries e2
                INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                WHERE e2.EnCode = e.EnCode 
                AND e2.EnTournament = e.EnTournament
                AND e2.EnCode != ''
            ) THEN 1
            ELSE 0
        END
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND (
        (q.QuSession = (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 0)
        OR
        (q.QuSession > (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 1)
    )
";

$Result = safe_w_sql($QueryCorrection);

if ($Result) {
    // Utiliser le nombre d'anomalies compté avant la correction
    $JSON['success'] = true;
    $JSON['message'] = 'Corrections effectuées avec succès';
    $JSON['corriges'] = $nbAnomalies;
} else {
    $JSON['message'] = 'Erreur lors de la correction';
}

echo json_encode($JSON);
?>