<?php
/**
 * Script AJAX pour vérifier les flèches des archers avec filtrage par session
 * Adapté pour afficher 3 ou 6 dernières flèches selon le type de tournoi (Indoor/Outdoor)
 * Les archers avec QuIrmType <> 0 sont ignorés
 *
 * Règles de statut des cibles (calculées à chaque appel) :
 *  - BLEU   : aucun archer valide sur la cible
 *  - JAUNE  : volée incomplète (nb flèches % 3 != 0) OU décalage interne (archers avec nb de volées différents)
 *  - VERT   : cible cohérente et au niveau du max de volées de la session
 *  - ROUGE  : cible cohérente mais en retard sur le max de volées de la session
 *
 * Les colonnes DB QuD1* / QuD2* sont exposées comme S1 / S2 (Séries 1 et 2).
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 : un warning PHP afficherait du texte dans le JSON

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

header('Content-Type: application/json');

error_log("=== AJAX check_fleches_session.php appelé ===");

$TourId = isset($_POST['TourId']) ? intval($_POST['TourId']) : (isset($_SESSION['TourId']) ? $_SESSION['TourId'] : 0);
$selectedSession = isset($_POST['session']) ? $_POST['session'] : null;
$getSessionsOnly = isset($_POST['get_sessions_list']) && ($_POST['get_sessions_list'] === true || $_POST['get_sessions_list'] === 'true' || $_POST['get_sessions_list'] === '1');

error_log("TourId utilisé: $TourId");
error_log("Session sélectionnée: " . ($selectedSession ?? 'null'));
error_log("getSessionsOnly: " . ($getSessionsOnly ? 'true' : 'false'));

// Déterminer le nombre de flèches à afficher selon le type de tournoi
$nbArrowsToShow = 6; // Valeur par défaut (extérieur)
$tourTypeName = '';
$numSessions = 1;

if ($TourId > 0) {
    $tourTypeQuery = "SELECT ToTypeName, ToNumSession FROM Tournament WHERE ToId = $TourId";
    $tourTypeResult = safe_r_sql($tourTypeQuery);
    if ($tourTypeResult && $tourTypeRow = safe_fetch($tourTypeResult)) {
        $tourTypeName = $tourTypeRow->ToTypeName;
        if (stripos($tourTypeName, 'Indoor') !== false) {
            $nbArrowsToShow = 3;
        }
        if (isset($tourTypeRow->ToNumSession) && $tourTypeRow->ToNumSession > 0) {
            $numSessions = intval($tourTypeRow->ToNumSession);
        }
    }
}
error_log("Type de tournoi: $tourTypeName - Nb flèches à afficher: $nbArrowsToShow - Nb sessions max: $numSessions");

// Convertit une lettre de flèche en score
function convertArrowToScore($letter) {
    $conversion = [
        'A' => 'M', 'B' => '1', 'C' => '2', 'D' => '3', 'E' => '4',
        'F' => '5', 'G' => '6', 'H' => '7', 'I' => '8', 'J' => '9',
        'K' => '10', 'L' => '10', 'X' => '10', 'M' => 'M',
    ];
    $letter = strtoupper($letter);
    return isset($conversion[$letter]) ? $conversion[$letter] : '0';
}

// Retourne les N derniers scores formatés
function getLastNScores($arrowString, $nbArrows) {
    if (empty($arrowString) || strlen(trim($arrowString)) < $nbArrows) {
        return '-';
    }
    $trimmedString = trim($arrowString);
    $lastN = substr($trimmedString, -$nbArrows);
    $scores = [];
    for ($i = 0; $i < $nbArrows; $i++) {
        $scores[] = isset($lastN[$i]) ? convertArrowToScore($lastN[$i]) : '-';
    }
    return implode(' - ', $scores);
}

// Numéro de volée (nb de volées complètes)
function getVolleyNumber($arrowString) {
    if (empty($arrowString) || strlen(trim($arrowString)) == 0) {
        return 0;
    }
    return floor(strlen(trim($arrowString)) / 3);
}

// Score total calculé depuis une chaîne (fallback)
function calculateTotalScore($arrowString) {
    if (empty($arrowString)) {
        return 0;
    }
    $total = 0;
    $length = strlen($arrowString);
    for ($i = 0; $i < $length; $i++) {
        $score = convertArrowToScore($arrowString[$i]);
        if ($score === 'M') {
            $total += 0;
        } elseif (is_numeric($score)) {
            $total += intval($score);
        }
    }
    return $total;
}

// Statut batterie (valeur négative = en charge)
function getBatteryStatus($batteryLevel) {
    if ($batteryLevel < 0) return 'charging';
    if ($batteryLevel >= 80) return 'high';
    if ($batteryLevel >= 40) return 'medium';
    if ($batteryLevel >= 20) return 'low';
    return 'critical';
}

// Dernier départ avec des flèches tirées (archers IRM exclus)
function getLastActiveSession($TourId, $numSessions) {
    for ($session = $numSessions; $session >= 1; $session--) {
        $query = "
            SELECT COUNT(*) as hasArrows
            FROM Qualifications q
            INNER JOIN Entries e ON q.QuId = e.EnId
            WHERE e.EnTournament = $TourId
            AND q.QuSession = $session
            AND q.QuIrmType = 0
            AND (
                (q.QuD1Arrowstring IS NOT NULL AND TRIM(q.QuD1Arrowstring) != '')
                OR (q.QuD2Arrowstring IS NOT NULL AND TRIM(q.QuD2Arrowstring) != '')
            )
        ";
        $result = safe_r_sql($query);
        if ($result && $row = safe_fetch($result)) {
            if ($row->hasArrows > 0) {
                error_log("Dernier départ actif trouvé: session $session avec " . $row->hasArrows . " flèches");
                return $session;
            }
        }
    }
    error_log("Aucun départ actif trouvé, retour du départ 1 par défaut");
    return 1;
}

try {
    if ($TourId <= 0) {
        throw new Exception("TourId invalide: $TourId");
    }
    
    // ============================================================
    // Récupération des sessions disponibles (archers IRM exclus)
    // ============================================================
    $sessionsQuery = "
        SELECT 
            QuSession,
            MAX(QuTarget) as MaxTargetPerSession,
            COUNT(DISTINCT QuTarget) as NbCiblesSession,
            COUNT(DISTINCT QuId) as NbArchersSession
        FROM Qualifications q
        WHERE EXISTS (
            SELECT 1 FROM Entries e 
            WHERE e.EnId = q.QuId 
            AND e.EnTournament = $TourId
        )
        AND q.QuIrmType = 0
        GROUP BY QuSession
        ORDER BY QuSession
    ";
    
    error_log("Requête sessions: $sessionsQuery");
    $sessionsResult = safe_r_sql($sessionsQuery);
    
    if (!$sessionsResult) {
        error_log("Erreur dans la requête sessions: " . mysqli_error($GLOBALS['db_link']));
        throw new Exception("Erreur lors de la récupération des sessions");
    }
    
    $sessionsData = [];
    while ($row = safe_fetch($sessionsResult)) {
        $sessionsData[$row->QuSession] = [
            'maxTarget' => $row->MaxTargetPerSession,
            'nbCibles' => $row->NbCiblesSession,
            'nbArchers' => $row->NbArchersSession,
            'totalTargets' => 0
        ];
    }
    
    // Compléter avec les sessions déclarées mais vides
    $allSessionsData = [];
    for ($session = 1; $session <= $numSessions; $session++) {
        if (isset($sessionsData[$session])) {
            $allSessionsData[$session] = $sessionsData[$session];
        } else {
            $allSessionsData[$session] = [
                'maxTarget' => 0,
                'nbCibles' => 0,
                'nbArchers' => 0,
                'totalTargets' => 0,
                'empty' => true
            ];
        }
    }
    
    // ============================================================
    // Mode "liste des sessions uniquement" (initialisation)
    // ============================================================
    if ($getSessionsOnly) {
        $lastActiveSession = getLastActiveSession($TourId, $numSessions);
        $response = [
            'success' => true,
            'sessionsData' => $allSessionsData,
            'numSessionsMax' => $numSessions,
            'nbArrowsToShow' => $nbArrowsToShow,
            'tourTypeName' => $tourTypeName,
            'lastActiveSession' => $lastActiveSession,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response);
        exit;
    }
    
    if (empty($allSessionsData)) {
        echo json_encode([
            'success' => true,
            'targets' => [],
            'sessionsData' => [],
            'stats' => ['sessionStats' => []],
            'selectedSession' => $selectedSession,
            'nbArrowsToShow' => $nbArrowsToShow,
            'tourTypeName' => $tourTypeName,
            'numSessionsMax' => $numSessions,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Aucune session trouvée pour ce tournoi'
        ]);
        exit;
    }
    
    // Déterminer la session à traiter
    if ($selectedSession === null) {
        $selectedSession = getLastActiveSession($TourId, $numSessions);
    }
    if ($selectedSession < 1 || $selectedSession > $numSessions) {
        $selectedSession = min(array_keys($allSessionsData));
    }
    
    // ============================================================
    // Batteries
    // ============================================================
    $batteryLevels = [];
    $batteryQuery = "
        SELECT IskDvTarget, IskDvBattery, IskDvLastSeen
        FROM IskDevices
        WHERE IskDvTournament = $TourId
        AND IskDvBattery IS NOT NULL
    ";
    $batteryResult = safe_r_sql($batteryQuery);
    if ($batteryResult) {
        while ($row = safe_fetch($batteryResult)) {
            $targetNumber = intval($row->IskDvTarget);
            $batteryValue = intval($row->IskDvBattery);
            $batteryLevels[$targetNumber] = [
                'level' => $batteryValue,
                'absoluteLevel' => abs($batteryValue),
                'lastUpdate' => $row->IskDvLastSeen,
                'status' => getBatteryStatus($batteryValue),
                'isCharging' => $batteryValue < 0
            ];
        }
    }
    
    // ============================================================
    // Requête principale (archers IRM exclus)
    // ============================================================
    $query = "
        SELECT 
            q.QuId,
            q.QuSession,
            q.QuTarget,
            q.QuLetter,
            q.QuD1Arrowstring,
            q.QuD2Arrowstring,
            q.QuD1Score,
            q.QuD2Score,
            e.EnFirstName,
            e.EnName,
            e.EnCode
        FROM Qualifications q
        INNER JOIN Entries e ON q.QuId = e.EnId
        WHERE e.EnTournament = $TourId
        AND q.QuIrmType = 0
    ";
    if ($selectedSession !== null && is_numeric($selectedSession)) {
        $session = intval($selectedSession);
        $query .= " AND q.QuSession = $session";
    }
    $query .= " ORDER BY q.QuSession, q.QuTarget, q.QuLetter";
    
    error_log("Requête principale: $query");
    $result = safe_r_sql($query);
    
    if (!$result) {
        error_log("Erreur dans la requête principale: " . mysqli_error($GLOBALS['db_link']));
        throw new Exception("Erreur lors de la récupération des données des cibles");
    }
    
    // Regroupement par cible (clé = session_target)
    $targets = [];
    while ($row = safe_fetch($result)) {
        $targetNumber = intval($row->QuTarget);
        $session = intval($row->QuSession);
        $key = $session . '_' . $targetNumber;
        
        if (!isset($targets[$key])) {
            $targets[$key] = [
                'session' => $session,
                'targetNumber' => $targetNumber,
                'archers' => [],
                'arrowCounts' => []
            ];
        }
        
        $arrowsD1 = !empty($row->QuD1Arrowstring) ? strlen(trim($row->QuD1Arrowstring)) : 0;
        $arrowsD2 = !empty($row->QuD2Arrowstring) ? strlen(trim($row->QuD2Arrowstring)) : 0;
        $totalArrows = $arrowsD1 + $arrowsD2;
        
        $totalScoreD1 = intval($row->QuD1Score);
        $totalScoreD2 = intval($row->QuD2Score);
        $totalScore = $totalScoreD1 + $totalScoreD2;
        
        $lastScoreD1 = getLastNScores($row->QuD1Arrowstring, $nbArrowsToShow);
        $lastScoreD2 = getLastNScores($row->QuD2Arrowstring, $nbArrowsToShow);
        
        $volleyNumberD1 = getVolleyNumber($row->QuD1Arrowstring);
        $volleyNumberD2 = getVolleyNumber($row->QuD2Arrowstring);
        
        $archerData = [
            'quId' => intval($row->QuId),
            'session' => $session,
            'targetNumber' => $targetNumber,
            'targetLetter' => $row->QuLetter,
            'archerName' => trim($row->EnFirstName . ' ' . $row->EnName),
            'license' => $row->EnCode,
            'arrowsD1' => $arrowsD1,
            'arrowsD2' => $arrowsD2,
            'arrowsTotal' => $totalArrows,
            'lastScoreD1' => $lastScoreD1,
            'lastScoreD2' => $lastScoreD2,
            'volleyNumberD1' => $volleyNumberD1,
            'volleyNumberD2' => $volleyNumberD2,
            'totalScoreD1' => $totalScoreD1,
            'totalScoreD2' => $totalScoreD2,
            'totalScore' => $totalScore
        ];
        
        $targets[$key]['archers'][] = $archerData;
        $targets[$key]['arrowCounts'][] = $totalArrows;
    }
    
    error_log("Cibles trouvées: " . count($targets) . " entrées");
    
    // ============================================================
    // Initialisation des stats
    // ============================================================
    $sessionStats = [];
    for ($session = 1; $session <= $numSessions; $session++) {
        $sessionStats[$session] = [
            'totalTargets' => 0,
            'greenTargets' => 0,
            'yellowTargets' => 0,
            'redTargets' => 0,
            'blueTargets' => 0,
            'maxTarget' => isset($allSessionsData[$session]) ? $allSessionsData[$session]['maxTarget'] : 0
        ];
    }
    
    // Détermination du nombre de cibles à afficher pour la session
    $selectedSessionInfo = isset($allSessionsData[$selectedSession]) ? $allSessionsData[$selectedSession] : ['maxTarget' => 0];
    $maxTargetSession = $selectedSessionInfo['maxTarget'];
    
    if (isset($allSessionsData[$selectedSession])) {
        $allSessionsData[$selectedSession]['totalTargets'] = $maxTargetSession;
    }
    
    if ($maxTargetSession == 0) {
        $maxFound = 0;
        foreach ($targets as $key => $target) {
            if ($target['session'] == $selectedSession && $target['targetNumber'] > $maxFound) {
                $maxFound = $target['targetNumber'];
            }
        }
        $maxTargetSession = $maxFound > 0 ? $maxFound : 20;
    }
    
    // ============================================================
    // PASSE 1 : calcul des infos de volées pour chaque cible
    // ============================================================
    $targetsComputed = [];
    $maxSessionVolleys = 0;
    
    for ($i = 1; $i <= $maxTargetSession; $i++) {
        $key = $selectedSession . '_' . $i;
        $batteryInfo = isset($batteryLevels[$i]) ? $batteryLevels[$i] : null;
        
        if (!isset($targets[$key])) {
            // Cible sans données → bleu
            $targetsComputed[$i] = [
                'session'             => $selectedSession,
                'targetNumber'        => $i,
                'archerCount'         => 0,
                'averageArrows'       => 0,
                'allMultipleOf3'      => false,
                'allSameCount'        => true,
                'hasDifferentArrowCounts' => false,
                'isCoherent'          => false,
                'volleyCount'         => 0,
                'status'              => 'blue',
                'archers'             => [],
                'battery'             => $batteryInfo
            ];
            continue;
        }
        
        $target = $targets[$key];
        $archerCount = count($target['archers']);
        
        $volleyCounts = [];
        $hasRemainder = false;
        foreach ($target['archers'] as $archer) {
            $totalArrows = $archer['arrowsTotal'];
            $volleyCounts[] = intval(floor($totalArrows / 3));
            if ($totalArrows % 3 !== 0) {
                $hasRemainder = true;
            }
        }
        
        $averageArrows      = $archerCount > 0 ? array_sum($target['arrowCounts']) / $archerCount : 0;
        $allSameVolleyCount = $archerCount > 0 ? count(array_unique($volleyCounts)) === 1 : false;
        $allMultipleOf3     = !$hasRemainder;
        $isCoherent         = $archerCount > 0 && $allMultipleOf3 && $allSameVolleyCount;
        $volleyCount        = $isCoherent ? $volleyCounts[0] : 0;
        
        // Seules les cibles cohérentes participent au calcul du max de la session
        if ($isCoherent && $volleyCount > $maxSessionVolleys) {
            $maxSessionVolleys = $volleyCount;
        }
        
        $targetsComputed[$i] = [
            'session'             => $selectedSession,
            'targetNumber'        => $i,
            'archerCount'         => $archerCount,
            'averageArrows'       => round($averageArrows, 1),
            'allMultipleOf3'      => $allMultipleOf3,
            'allSameCount'        => $allSameVolleyCount,
            'hasDifferentArrowCounts' => !$allSameVolleyCount,
            'isCoherent'          => $isCoherent,
            'volleyCount'         => $volleyCount,
            'status'              => 'blue',
            'archers'             => $target['archers'],
            'battery'             => $batteryInfo
        ];
    }
    
    // ============================================================
    // PASSE 2 : attribution finale des statuts
    // ============================================================
    $allTargets = [];
    
    foreach ($targetsComputed as $i => $t) {
        if ($t['archerCount'] == 0) {
            $status = 'blue';
        } elseif (!$t['isCoherent']) {
            // volée incomplète OU décalage entre archers → jaune
            $status = 'yellow';
        } elseif ($t['volleyCount'] >= $maxSessionVolleys) {
            // cohérente et au niveau du max de la session → vert
            $status = 'green';
        } else {
            // cohérente mais en retard sur le max de la session → rouge
            $status = 'red';
        }
        
        $t['status'] = $status;
        $allTargets[] = $t;
        
        $sessionStats[$selectedSession]['totalTargets']++;
        switch ($status) {
            case 'green':  $sessionStats[$selectedSession]['greenTargets']++;  break;
            case 'yellow': $sessionStats[$selectedSession]['yellowTargets']++; break;
            case 'red':    $sessionStats[$selectedSession]['redTargets']++;    break;
            case 'blue':   $sessionStats[$selectedSession]['blueTargets']++;   break;
        }
    }
    
    // Tri par numéro de cible
    usort($allTargets, function($a, $b) {
        return $a['targetNumber'] - $b['targetNumber'];
    });
    
    $response = [
        'success' => true,
        'targets' => $allTargets,
        'sessionsData' => $allSessionsData,
        'stats' => ['sessionStats' => $sessionStats],
        'selectedSession' => $selectedSession,
        'nbArrowsToShow' => $nbArrowsToShow,
        'tourTypeName' => $tourTypeName,
        'numSessionsMax' => $numSessions,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'battery_count' => count($batteryLevels),
            'maxSessionVolleys' => $maxSessionVolleys,
            'note' => 'Archers IRM exclus. Statut recalculé à chaque appel (retour vert/rouge automatique). Colonnes D1/D2 exposées comme S1/S2. QuId exposé pour WriteScoreCard.'
        ]
    ];
    
    error_log("Réponse envoyée avec " . count($allTargets) . " cibles - maxSessionVolleys=$maxSessionVolleys - session=$selectedSession");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Exception capturée: " . $e->getMessage() . " dans " . $e->getFile() . " ligne " . $e->getLine());
    
    $errorResponse = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'tourId' => $TourId,
            'session' => $selectedSession,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
    
    echo json_encode($errorResponse);
}

error_log("=== Fin de l'exécution ===");
?>