<?php
/**
 * Script AJAX pour vérifier les flèches des archers avec filtrage par session
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

header('Content-Type: application/json');

$TourId = isset($_POST['TourId']) ? intval($_POST['TourId']) : $_SESSION['TourId'];
$selectedSession = isset($_POST['session']) ? $_POST['session'] : '1'; // '1' par défaut

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
        'L' => '10', // L est aussi 10 selon votre exemple
        'X' => '10', // Pour les X (dix)
        'M' => 'M',  // Pour les M
    ];
    
    return isset($conversion[strtoupper($letter)]) ? $conversion[strtoupper($letter)] : '0';
}

// Fonction pour obtenir les 3 derniers scores
function getLastThreeScores($arrowString) {
    if (empty($arrowString) || strlen($arrowString) < 3) {
        return '';
    }
    
    $lastThree = substr($arrowString, -3);
    $scores = [];
    
    for ($i = 0; $i < 3; $i++) {
        $scores[] = convertArrowToScore($lastThree[$i]);
    }
    
    return implode(' - ', $scores);
}

// Fonction pour obtenir le numéro de volée
function getVolleyNumber($arrowString) {
    if (empty($arrowString)) {
        return 0;
    }
    
    $totalArrows = strlen($arrowString);
    return ceil($totalArrows / 3);
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

try {
    // Récupérer les informations sur les sessions disponibles
    $sessionsQuery = "
        SELECT 
            QuSession,
            MAX(QuTarget) as MaxTargetPerSession,
            COUNT(DISTINCT QuTarget) as NbCiblesSession
        FROM Qualifications q
        WHERE EXISTS (
            SELECT 1 FROM Entries e 
            WHERE e.EnId = q.QuId 
            AND e.EnTournament = $TourId
        )
        GROUP BY QuSession
        ORDER BY QuSession
    ";
    
    $sessionsResult = safe_r_sql($sessionsQuery);
    
    $sessionsData = [];
    $maxTargetOverall = 0;
    
    while ($row = safe_fetch($sessionsResult)) {
        $sessionsData[$row->QuSession] = [
            'maxTarget' => $row->MaxTargetPerSession,
            'nbCibles' => $row->NbCiblesSession
        ];
        $maxTargetOverall = max($maxTargetOverall, $row->MaxTargetPerSession);
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
        AND (q.QuD1Arrowstring != '' OR q.QuD2Arrowstring != '' OR 1=1)
    ";
    
    // Filtrer par session si nécessaire
    if ($selectedSession !== 'all' && is_numeric($selectedSession)) {
        $session = intval($selectedSession);
        $query .= " AND q.QuSession = $session";
    }
    
    $query .= " ORDER BY q.QuSession, q.QuTarget, q.QuLetter";
    
    $result = safe_r_sql($query);
    
    // Organiser les données par cible ET session
    $targets = [];
    $sessionStats = [];
    
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
            
            // Initialiser les statistiques de session
            if (!isset($sessionStats[$session])) {
                $sessionStats[$session] = [
                    'totalTargets' => 0,
                    'greenTargets' => 0,
                    'yellowTargets' => 0,
                    'redTargets' => 0,
                    'blueTargets' => 0
                ];
            }
        }
        
        // Compter les flèches dans chaque chaîne
        $arrowsD1 = !empty($row->QuD1Arrowstring) ? strlen(trim($row->QuD1Arrowstring)) : 0;
        $arrowsD2 = !empty($row->QuD2Arrowstring) ? strlen(trim($row->QuD2Arrowstring)) : 0;
        $totalArrows = $arrowsD1 + $arrowsD2;
        
        // Récupérer les scores totaux depuis la base de données
        $totalScoreD1 = intval($row->QuD1Score);
        $totalScoreD2 = intval($row->QuD2Score);
        $totalScore = $totalScoreD1 + $totalScoreD2;
        
        // Calculer les nouvelles informations
        $lastScoreD1 = getLastThreeScores($row->QuD1Arrowstring);
        $lastScoreD2 = getLastThreeScores($row->QuD2Arrowstring);
        
        // Numéro de volée
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
    
    // Traiter les cibles
    $allTargets = [];
    $greenTargetsBySession = [];
    
    // Pour chaque session disponible
    foreach ($sessionsData as $session => $sessionInfo) {
        // Ne traiter que la session sélectionnée
        if ($session != $selectedSession) {
            continue;
        }
        
        $maxTargetSession = $sessionInfo['maxTarget'];
        
        // Initialiser les statistiques de session
        if (!isset($sessionStats[$session])) {
            $sessionStats[$session] = [
                'totalTargets' => 0,
                'greenTargets' => 0,
                'yellowTargets' => 0,
                'redTargets' => 0,
                'blueTargets' => 0
            ];
        }
        
        // Traiter toutes les cibles de cette session
        for ($i = 1; $i <= $maxTargetSession; $i++) {
            $key = $session . '_' . $i;
            
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
                    'session' => $session,
                    'targetNumber' => $i,
                    'archerCount' => $archerCount,
                    'averageArrows' => round($averageArrows, 1),
                    'allMultipleOf3' => $allMultipleOf3,
                    'allSameCount' => $allSameCount,
                    'hasDifferentArrowCounts' => !$allSameCount,
                    'status' => $status,
                    'archers' => $target['archers']
                ];
                
                // Mettre à jour les statistiques
                $sessionStats[$session]['totalTargets']++;
                
                switch($status) {
                    case 'green':
                        $sessionStats[$session]['greenTargets']++;
                        if (!isset($greenTargetsBySession[$session])) {
                            $greenTargetsBySession[$session] = [];
                        }
                        $greenTargetsBySession[$session][$i] = $averageArrows;
                        break;
                    case 'yellow':
                        $sessionStats[$session]['yellowTargets']++;
                        break;
                    case 'red':
                        $sessionStats[$session]['redTargets']++;
                        break;
                    case 'blue':
                        $sessionStats[$session]['blueTargets']++;
                        break;
                }
                
                $allTargets[] = $targetData;
            } else {
                // Cible sans données
                $targetData = [
                    'session' => $session,
                    'targetNumber' => $i,
                    'archerCount' => 0,
                    'averageArrows' => 0,
                    'allMultipleOf3' => false,
                    'allSameCount' => true,
                    'hasDifferentArrowCounts' => false,
                    'status' => 'blue',
                    'archers' => []
                ];
                
                $sessionStats[$session]['totalTargets']++;
                $sessionStats[$session]['blueTargets']++;
                
                $allTargets[] = $targetData;
            }
        }
    }
    
    // Vérifier si certaines cibles vertes ont plus de flèches que d'autres DANS LA MÊME SESSION
    foreach ($greenTargetsBySession as $session => $greenTargets) {
        if (count($greenTargets) > 0) {
            $maxGreenArrowsInSession = max($greenTargets);
            
            foreach ($allTargets as &$target) {
                if ($target['session'] == $session && 
                    $target['status'] === 'green' && 
                    $target['averageArrows'] < $maxGreenArrowsInSession) {
                    $target['status'] = 'red';
                    
                    // Mettre à jour les statistiques
                    $sessionStats[$session]['greenTargets']--;
                    $sessionStats[$session]['redTargets']++;
                }
            }
        }
    }
    
    // Trier par session puis par numéro de cible
    usort($allTargets, function($a, $b) {
        if ($a['session'] == $b['session']) {
            return $a['targetNumber'] - $b['targetNumber'];
        }
        return $a['session'] - $b['session'];
    });
    
    echo json_encode([
        'success' => true,
        'targets' => $allTargets,
        'sessionsData' => $sessionsData,
        'stats' => [
            'sessionStats' => $sessionStats
        ],
        'selectedSession' => $selectedSession,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}