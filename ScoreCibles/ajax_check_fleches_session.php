<?php
/**
 * Script AJAX pour vérifier les flèches des archers avec filtrage par session
 * Adapté pour afficher 3 ou 6 dernières flèches selon le type de tournoi (Indoor/Outdoor)
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

header('Content-Type: application/json');

// Log pour débogage
error_log("=== AJAX check_fleches_session.php appelé ===");

$TourId = isset($_POST['TourId']) ? intval($_POST['TourId']) : (isset($_SESSION['TourId']) ? $_SESSION['TourId'] : 0);
$selectedSession = isset($_POST['session']) ? $_POST['session'] : null;
$getSessionsOnly = isset($_POST['get_sessions_list']) && $_POST['get_sessions_list'] === true;

error_log("TourId utilisé: $TourId");
error_log("Session sélectionnée: " . ($selectedSession ?? 'null'));
error_log("getSessionsOnly: " . ($getSessionsOnly ? 'true' : 'false'));

// Déterminer le nombre de flèches à afficher selon le type de tournoi
$nbArrowsToShow = 6; // Valeur par défaut (extérieur)
$tourTypeName = '';
$numSessions = 1; // Nombre de départs par défaut

if ($TourId > 0) {
    $tourTypeQuery = "SELECT ToTypeName, ToNumSession FROM Tournament WHERE ToId = $TourId";
    $tourTypeResult = safe_r_sql($tourTypeQuery);
    if ($tourTypeResult && $tourTypeRow = safe_fetch($tourTypeResult)) {
        $tourTypeName = $tourTypeRow->ToTypeName;
        // Vérifier si c'est un tir en salle (contient "Indoor")
        if (stripos($tourTypeName, 'Indoor') !== false) {
            $nbArrowsToShow = 3; // Tir en salle : 3 dernières flèches
        }
        // Récupérer le nombre de sessions
        if (isset($tourTypeRow->ToNumSession) && $tourTypeRow->ToNumSession > 0) {
            $numSessions = intval($tourTypeRow->ToNumSession);
        }
    }
}
error_log("Type de tournoi: $tourTypeName - Nb flèches à afficher: $nbArrowsToShow - Nb sessions max: $numSessions");

// Fonction pour convertir les lettres en points
function convertArrowToScore($letter) {
    $conversion = [
        'A' => 'M',
        'B' => '1',
        'C' => '2',
        'D' => '3',
        'E' => '4',
        'F' => '5',
        'G' => '6',
        'H' => '7',
        'I' => '8',
        'J' => '9',
        'K' => '10',
        'L' => '10', // L est aussi 10
        'X' => '10', // Pour les X (dix)
        'M' => 'M',  // Pour les M
    ];
    
    $letter = strtoupper($letter);
    return isset($conversion[$letter]) ? $conversion[$letter] : '0';
}

// Fonction pour obtenir les N derniers scores (N = 3 pour salle, 6 pour extérieur)
function getLastNScores($arrowString, $nbArrows) {
    if (empty($arrowString) || strlen(trim($arrowString)) < $nbArrows) {
        return '-';
    }
    
    $trimmedString = trim($arrowString);
    // Prendre les N dernières flèches
    $lastN = substr($trimmedString, -$nbArrows);
    $scores = [];
    
    for ($i = 0; $i < $nbArrows; $i++) {
        if (isset($lastN[$i])) {
            $score = convertArrowToScore($lastN[$i]);
            $scores[] = $score;
        } else {
            $scores[] = '-';
        }
    }
    
    return implode(' - ', $scores);
}

// Fonction pour obtenir le numéro de volée (nombre de volées complètes)
function getVolleyNumber($arrowString) {
    if (empty($arrowString) || strlen(trim($arrowString)) == 0) {
        return 0;
    }
    
    $trimmedString = trim($arrowString);
    $totalArrows = strlen($trimmedString);
    
    // Calculer le nombre de volées complètes (diviser par 3 et prendre la partie entière)
    $volleyNumber = floor($totalArrows / 3);
    
    return $volleyNumber;
}

// Fonction pour calculer le score total d'une chaîne de flèches
function calculateTotalScore($arrowString) {
    if (empty($arrowString)) {
        return 0;
    }
    
    $total = 0;
    $length = strlen($arrowString);
    
    for ($i = 0; $i < $length; $i++) {
        $letter = $arrowString[$i];
        $score = convertArrowToScore($letter);
        
        // Convertir en numérique pour le calcul
        if ($score === 'M') {
            $total += 0; // M vaut 0
        } elseif (is_numeric($score)) {
            $total += intval($score);
        } else {
            $total += 0;
        }
    }
    
    return $total;
}

// Fonction pour déterminer le statut de la batterie avec prise en compte de la charge
function getBatteryStatus($batteryLevel) {
    if ($batteryLevel < 0) {
        return 'charging'; // Valeur négative = en charge
    }
    
    if ($batteryLevel >= 80) return 'high';
    if ($batteryLevel >= 40) return 'medium';
    if ($batteryLevel >= 20) return 'low';
    return 'critical';
}

// Fonction pour trouver le dernier départ avec des flèches tirées
function getLastActiveSession($TourId, $numSessions) {
    // Parcourir les départs de la fin vers le début
    for ($session = $numSessions; $session >= 1; $session--) {
        $query = "
            SELECT COUNT(*) as hasArrows
            FROM Qualifications q
            INNER JOIN Entries e ON q.QuId = e.EnId
            WHERE e.EnTournament = $TourId
            AND q.QuSession = $session
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
    // Vérifier si TourId est valide
    if ($TourId <= 0) {
        throw new Exception("TourId invalide: $TourId");
    }
    
    // Récupérer les informations sur les sessions disponibles
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
    $maxTargetOverall = 0;
    
    while ($row = safe_fetch($sessionsResult)) {
        $sessionsData[$row->QuSession] = [
            'maxTarget' => $row->MaxTargetPerSession,
            'nbCibles' => $row->NbCiblesSession,
            'nbArchers' => $row->NbArchersSession,
            'totalTargets' => 0  // Sera mis à jour plus tard
        ];
        $maxTargetOverall = max($maxTargetOverall, $row->MaxTargetPerSession);
    }
    
    // Ajouter les sessions manquantes basées sur ToNumSession
    // Créer un tableau avec toutes les sessions de 1 à $numSessions
    $allSessionsData = [];
    for ($session = 1; $session <= $numSessions; $session++) {
        if (isset($sessionsData[$session])) {
            $allSessionsData[$session] = $sessionsData[$session];
        } else {
            // Session sans données
            $allSessionsData[$session] = [
                'maxTarget' => 0,
                'nbCibles' => 0,
                'nbArchers' => 0,
                'totalTargets' => 0,
                'empty' => true
            ];
        }
    }
    
    error_log("Sessions trouvées: " . print_r($allSessionsData, true));
    
    // Si on demande uniquement la liste des sessions
    if ($getSessionsOnly) {
        // Trouver le dernier départ actif avec des flèches
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
    
    // Si aucune session n'est disponible
    if (empty($allSessionsData)) {
        $response = [
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
        ];
        echo json_encode($response);
        exit;
    }
    
    // Si aucune session n'est sélectionnée, trouver le dernier départ avec des flèches
    if ($selectedSession === null) {
        $selectedSession = getLastActiveSession($TourId, $numSessions);
        error_log("Aucune session sélectionnée, utilisation du dernier départ actif: $selectedSession");
    }
    
    // Vérifier si la session sélectionnée existe (dans la plage 1..$numSessions)
    if ($selectedSession < 1 || $selectedSession > $numSessions) {
        // Prendre la première session disponible
        $selectedSession = min(array_keys($allSessionsData));
        error_log("Session $selectedSession hors plage, utilisation de la première: $selectedSession");
    }
    
    // Récupérer les informations de batterie
    $batteryLevels = [];
    
    // Récupérer toutes les batteries pour ce tournoi
    $batteryQuery = "
        SELECT IskDvTarget, IskDvBattery, IskDvLastSeen
        FROM IskDevices
        WHERE IskDvTournament = $TourId
        AND IskDvBattery IS NOT NULL
    ";
    
    error_log("Requête batterie: $batteryQuery");
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
        error_log("Batteries trouvées pour " . count($batteryLevels) . " cibles");
    } else {
        error_log("Pas de résultat pour la requête batterie ou erreur");
    }
    
    // Construire la requête en fonction de la session sélectionnée
    $query = "
        SELECT 
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
    ";
    
    // Filtrer par session
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
    
    // Organiser les données par cible ET session
    $targets = [];
    $sessionStats = [];
    
    // Initialiser les statistiques pour toutes les sessions de 1 à $numSessions
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
        
        // Compter les flèches dans chaque chaîne
        $arrowsD1 = !empty($row->QuD1Arrowstring) ? strlen(trim($row->QuD1Arrowstring)) : 0;
        $arrowsD2 = !empty($row->QuD2Arrowstring) ? strlen(trim($row->QuD2Arrowstring)) : 0;
        $totalArrows = $arrowsD1 + $arrowsD2;
        
        // Récupérer les scores totaux depuis la base de données
        $totalScoreD1 = intval($row->QuD1Score);
        $totalScoreD2 = intval($row->QuD2Score);
        $totalScore = $totalScoreD1 + $totalScoreD2;
        
        // Calculer les dernières flèches (3 ou 6 selon le type de tournoi)
        $lastScoreD1 = getLastNScores($row->QuD1Arrowstring, $nbArrowsToShow);
        $lastScoreD2 = getLastNScores($row->QuD2Arrowstring, $nbArrowsToShow);
        
        // Numéro de volée (volées complètes seulement)
        $volleyNumberD1 = getVolleyNumber($row->QuD1Arrowstring);
        $volleyNumberD2 = getVolleyNumber($row->QuD2Arrowstring);
        
        $archerData = [
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
    
    // Traiter les cibles pour la session sélectionnée uniquement
    $allTargets = [];
    $greenTargetsBySession = [];
    
    // Récupérer les informations de la session sélectionnée
    $selectedSessionInfo = isset($allSessionsData[$selectedSession]) ? $allSessionsData[$selectedSession] : ['maxTarget' => 0];
    $maxTargetSession = $selectedSessionInfo['maxTarget'];
    
    // Mettre à jour le total des cibles dans sessionsData
    if (isset($allSessionsData[$selectedSession])) {
        $allSessionsData[$selectedSession]['totalTargets'] = $maxTargetSession;
    }
    
    // Si maxTargetSession est 0, on ne peut pas déterminer le nombre de cibles
    // On utilise une valeur par défaut de 20 ou on cherche le max parmi les cibles existantes
    if ($maxTargetSession == 0) {
        // Chercher la cible max pour cette session
        $maxFound = 0;
        foreach ($targets as $key => $target) {
            if ($target['session'] == $selectedSession && $target['targetNumber'] > $maxFound) {
                $maxFound = $target['targetNumber'];
            }
        }
        $maxTargetSession = $maxFound > 0 ? $maxFound : 20; // Valeur par défaut
        error_log("Aucun maxTarget défini pour la session $selectedSession, utilisation de $maxTargetSession");
    }
    
    // Traiter toutes les cibles de la session sélectionnée
    for ($i = 1; $i <= $maxTargetSession; $i++) {
        $key = $selectedSession . '_' . $i;
        
        // Récupérer les informations de batterie pour cette cible
        $batteryInfo = isset($batteryLevels[$i]) ? $batteryLevels[$i] : null;
        
        if (isset($targets[$key])) {
            $target = $targets[$key];
            $archerCount = count($target['archers']);
            
            // Calculer les statistiques
            $averageArrows = $archerCount > 0 ? array_sum($target['arrowCounts']) / $archerCount : 0;
            $allSameCount = $archerCount > 0 ? count(array_unique($target['arrowCounts'])) === 1 : false;
            $allMultipleOf3 = true;
            
            foreach ($target['arrowCounts'] as $count) {
                if ($count % 3 !== 0) {
                    $allMultipleOf3 = false;
                    break;
                }
            }
            
            // Déterminer le statut
            $status = 'blue';
            
            if ($archerCount > 0) {
                if ($allMultipleOf3 && $allSameCount) {
                    $status = 'green';
                } else if (!$allSameCount) {
                    $status = 'yellow';
                } else if ($allMultipleOf3 && !$allSameCount) {
                    $status = 'yellow';
                } else if (!$allMultipleOf3) {
                    $status = 'yellow';
                }
            }
            
            $targetData = [
                'session' => $selectedSession,
                'targetNumber' => $i,
                'archerCount' => $archerCount,
                'averageArrows' => round($averageArrows, 1),
                'allMultipleOf3' => $allMultipleOf3,
                'allSameCount' => $allSameCount,
                'hasDifferentArrowCounts' => !$allSameCount,
                'status' => $status,
                'archers' => $target['archers'],
                'battery' => $batteryInfo
            ];
            
            // Mettre à jour les statistiques
            $sessionStats[$selectedSession]['totalTargets']++;
            
            switch($status) {
                case 'green':
                    $sessionStats[$selectedSession]['greenTargets']++;
                    if (!isset($greenTargetsBySession[$selectedSession])) {
                        $greenTargetsBySession[$selectedSession] = [];
                    }
                    $greenTargetsBySession[$selectedSession][$i] = $averageArrows;
                    break;
                case 'yellow':
                    $sessionStats[$selectedSession]['yellowTargets']++;
                    break;
                case 'red':
                    $sessionStats[$selectedSession]['redTargets']++;
                    break;
                case 'blue':
                    $sessionStats[$selectedSession]['blueTargets']++;
                    break;
            }
            
            $allTargets[] = $targetData;
        } else {
            // Cible sans données
            $targetData = [
                'session' => $selectedSession,
                'targetNumber' => $i,
                'archerCount' => 0,
                'averageArrows' => 0,
                'allMultipleOf3' => false,
                'allSameCount' => true,
                'hasDifferentArrowCounts' => false,
                'status' => 'blue',
                'archers' => [],
                'battery' => $batteryInfo
            ];
            
            $sessionStats[$selectedSession]['totalTargets']++;
            $sessionStats[$selectedSession]['blueTargets']++;
            
            $allTargets[] = $targetData;
        }
    }
    
    // Vérifier si certaines cibles vertes ont plus de flèches que d'autres DANS LA MÊME SESSION
    if (isset($greenTargetsBySession[$selectedSession]) && count($greenTargetsBySession[$selectedSession]) > 0) {
        $maxGreenArrowsInSession = max($greenTargetsBySession[$selectedSession]);
        
        foreach ($allTargets as &$target) {
            if ($target['session'] == $selectedSession && 
                $target['status'] === 'green' && 
                $target['averageArrows'] < $maxGreenArrowsInSession) {
                $target['status'] = 'red';
                
                // Mettre à jour les statistiques
                $sessionStats[$selectedSession]['greenTargets']--;
                $sessionStats[$selectedSession]['redTargets']++;
            }
        }
    }
    
    // Trier par numéro de cible
    usort($allTargets, function($a, $b) {
        return $a['targetNumber'] - $b['targetNumber'];
    });
    
    $response = [
        'success' => true,
        'targets' => $allTargets,
        'sessionsData' => $allSessionsData,
        'stats' => [
            'sessionStats' => $sessionStats
        ],
        'selectedSession' => $selectedSession,
        'nbArrowsToShow' => $nbArrowsToShow,
        'tourTypeName' => $tourTypeName,
        'numSessionsMax' => $numSessions,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'battery_count' => count($batteryLevels),
            'note' => 'IskDevices n\'a pas de colonne session, batteries mappées par numéro de cible seulement'
        ]
    ];
    
    error_log("Réponse envoyée avec " . count($allTargets) . " cibles pour la session $selectedSession");
    
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
    
    error_log("Erreur JSON: " . json_encode($errorResponse));
    
    echo json_encode($errorResponse);
}

error_log("=== Fin de l'exécution ===");
?>