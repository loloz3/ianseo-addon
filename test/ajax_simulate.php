<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

// Chercher la connexion existante
global $db_link;
if (!isset($db_link) || !$db_link) {
    $host = $DBHost ?? $db_host ?? $database_host ?? 'localhost';
    $user = $DBUser ?? $db_user ?? $database_user ?? 'root';
    $pass = $DBPassword ?? $db_password ?? $database_password ?? '';
    $name = $DBName ?? $db_name ?? $database_name ?? 'ianseo';
    
    $db_link = mysqli_connect($host, $user, $pass, $name);
    if (!$db_link) {
        echo json_encode(['success' => false, 'message' => 'Erreur connexion DB: ' . mysqli_connect_error()]);
        exit;
    }
}

CheckTourSession(true);
header('Content-Type: application/json');

$TourId = isset($_POST['TourId']) ? intval($_POST['TourId']) : (isset($_SESSION['TourId']) ? $_SESSION['TourId'] : 0);

// Récupérer le type de tournoi - RENOMMÉE pour éviter conflit
function getTournamentTypeForSimulation($TourId) {
    global $db_link;
    
    // Valeur par défaut outdoor
    $defaultType = 'outdoor';
    
    if (!$TourId || $TourId <= 0) {
        return $defaultType;
    }
    
    $query = "SELECT ToTypeName FROM Tournament WHERE ToId = " . intval($TourId);
    $result = mysqli_query($db_link, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $toTypeName = $row['ToTypeName'];
        // Vérifier si c'est un tournoi indoor (Type_Indoor 18)
        if (stripos($toTypeName, 'indoor') !== false || stripos($toTypeName, 'type_indoor') !== false) {
            return 'indoor';
        }
        // Vérifier aussi par l'ID si nécessaire (Type_Indoor_18 = 1 selon votre base)
        if ($toTypeName == '1' || $toTypeName == 'Type_Indoor_18') {
            return 'indoor';
        }
    }
    
    return $defaultType; // Par défaut outdoor
}

// Obtenir le type de tournoi APRÈS que la connexion soit établie
$tournamentType = getTournamentTypeForSimulation($TourId);
$arrowsPerEnd = ($tournamentType === 'indoor') ? 3 : 6;  // Indoor: +3 flèches, Outdoor: +6 flèches
$maxArrowsPerDistance = ($tournamentType === 'indoor') ? 30 : 36; // Indoor: 30 max, Outdoor: 36 max
$totalArrowsMax = $maxArrowsPerDistance * 2; // 60 ou 72 flèches total

function db_escape($str) {
    global $db_link;
    return mysqli_real_escape_string($db_link, $str);
}

function letterToScore($letter) {
    $conversion = [
        'A' => 0,
        'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 10
    ];
	$letter = strtoupper($letter);
    return isset($conversion[$letter]) ? $conversion[$letter] : 0;
}

// Fonction pour calculer les statistiques à partir d'une chaîne de lettres
function calculateStats($arrowString) {
    $hits = 0;      // Nombre total de flèches
    $gold = 0;      // Nombre de 10 (K, L)
    $xnine = 0;     // Nombre de 9 (J)

    if (empty($arrowString)) {
        return ['hits' => 0, 'gold' => 0, 'xnine' => 0];
    }
    
    for ($i = 0; $i < strlen($arrowString); $i++) {
        $letter = strtoupper($arrowString[$i]);
        $hits++;
        
        if ($letter == 'K' || $letter == 'L') {
            $gold++;
        } elseif ($letter == 'J') {
            $xnine++;
        }
    }
    
    return ['hits' => $hits, 'gold' => $gold, 'xnine' => $xnine];
}

function getArcherTypeSimple($division) {
    $keywords = array('Compound', 'CO', 'Cmp', 'COMPOUND');
    foreach ($keywords as $kw) {
        if (stripos($division, $kw) !== false) return 'co';
    }
    return 'spot';
}

function generateRandomLetter($archerType) {
    if ($archerType === 'co') {
        $letters = ['F', 'G', 'H', 'I', 'J', 'L', 'K', 'A'];
        return $letters[array_rand($letters)];
    } else {
        $letters = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'A'];
        return $letters[array_rand($letters)];
    }
}

function generateFixedLetter($arrowType, $letterValue) {
    $letterMap = [
        '10' => 'L',
        '9' => 'J',
        '8' => 'I',
        '7' => 'H',
        '6' => 'G',
        '5' => 'F',
        '4' => 'E',
        '3' => 'D',
        '2' => 'C',
        '1' => 'B',
        '0' => 'A'
    ];

    return isset($letterMap[$letterValue]) ? $letterMap[$letterValue] : 'M';
}

function generateVolley($archerType, $arrowType, $arrowsPerEnd = 3) {
    $volley = '';
    if ($arrowType === 'all_10') {
        $volley = str_repeat('X', $arrowsPerEnd);
    } elseif ($arrowType === 'all_9') {
        $volley = str_repeat('J', $arrowsPerEnd);
    } elseif ($arrowType === 'all_8') {
        $volley = str_repeat('I', $arrowsPerEnd);
    } else {
        for ($i = 0; $i < $arrowsPerEnd; $i++) {
            $volley .= generateRandomLetter($archerType);
        }
    }
    return $volley;
}

function calculateScore($str) {
    if (empty($str)) return 0;
    $total = 0;
    for ($i = 0; $i < strlen($str); $i++) {
        $total += letterToScore($str[$i]);
    }
    return $total;
}

try {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    $query = "
        SELECT 
            e.EnId, e.EnFirstName, e.EnName, e.EnDivision,
            q.QuId, q.QuSession, q.QuTarget, q.QuLetter,
            q.QuD1Arrowstring, q.QuD2Arrowstring,
            q.QuD1Score, q.QuD2Score,
            q.QuD1Hits, q.QuD1Gold, q.QuD1Xnine,
            q.QuD2Hits, q.QuD2Gold, q.QuD2Xnine,
            q.QuD1Rank, q.QuD2Rank,
            q.QuScore, q.QuHits, q.QuGold, q.QuXnine, q.QuClRank, q.QuConfirm
        FROM Entries e
        INNER JOIN Qualifications q ON e.EnId = q.QuId
        WHERE e.EnTournament = $TourId AND e.EnStatus = 'A'
        ORDER BY q.QuSession, q.QuTarget, q.QuLetter
    ";
    
    $result = safe_r_sql($query);
    if (!$result) {
        throw new Exception("Erreur requête: " . mysqli_error($GLOBALS['db_link']));
    }
    
    $archers = [];
    while ($row = safe_fetch($result)) {
        $archers[] = [
            'id' => $row->QuId,
            'session' => $row->QuSession,
            'type' => getArcherTypeSimple($row->EnDivision),
            'd1_str' => $row->QuD1Arrowstring,
            'd2_str' => $row->QuD2Arrowstring,
            'd1_score' => intval($row->QuD1Score),
            'd2_score' => intval($row->QuD2Score),
            'firstname' => $row->EnFirstName,
            'lastname' => $row->EnName,
            'target' => $row->QuTarget,
            'letter' => $row->QuLetter
        ];
    }
    
    if ($action === 'get_data') {
        $list = [];
        $totalArrows = 0;
        $totalScore = 0;
        
        foreach ($archers as $a) {
            $d1 = strlen($a['d1_str']);
            $d2 = strlen($a['d2_str']);
            $totalArrows += $d1 + $d2;
            $totalScore += $a['d1_score'] + $a['d2_score'];
            
            // Convertir la dernière volée en chiffres
            $lastVolleySize = $arrowsPerEnd;
            $last1 = ($d1 >= $lastVolleySize) ? substr($a['d1_str'], -$lastVolleySize) : $a['d1_str'];
            $last2 = ($d2 >= $lastVolleySize) ? substr($a['d2_str'], -$lastVolleySize) : $a['d2_str'];
            
            if ($d1 < $lastVolleySize) $last1 = $a['d1_str'];
            if ($d2 < $lastVolleySize) $last2 = $a['d2_str'];
            
            $list[] = [
                'id' => $a['id'],
                'session' => $a['session'],
                'target' => $a['target'],
                'targetLetter' => $a['letter'],
                'firstName' => $a['firstname'],
                'lastName' => $a['lastname'],
                'archerType' => ($a['type'] === 'co') ? 'CO' : 'Spot',
                'arrowsD1' => $d1,
                'arrowsD2' => $d2,
                'scoreD1' => $a['d1_score'],
                'scoreD2' => $a['d2_score'],
                'lastVolleyD1' => $last1,
                'lastVolleyD2' => $last2,
                'fullD1Str' => $a['d1_str'],
                'fullD2Str' => $a['d2_str']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'archers' => $list,
            'tournamentType' => $tournamentType,
            'arrowsPerEnd' => $arrowsPerEnd,
            'maxArrowsPerDistance' => $maxArrowsPerDistance,
            'totalArrowsMax' => $totalArrowsMax,
            'stats' => [
                'total_archers' => count($list),
                'total_arrows' => $totalArrows,
                'total_score' => $totalScore
            ]
        ]);
        exit;
    }
    
    if ($action === 'add_arrows') {
        $numVolleys = isset($_POST['num_volleys']) ? intval($_POST['num_volleys']) : 1;
        $group = isset($_POST['archer_group']) ? $_POST['archer_group'] : 'all';
        $arrowType = isset($_POST['arrow_type']) ? $_POST['arrow_type'] : 'random';
        $targetDistance = isset($_POST['target_distance']) ? $_POST['target_distance'] : 'both';
        
        $affected = 0;
        $totalArrowsAdded = 0;
        
        foreach ($archers as &$a) {
            $skip = false;
            if ($group === 'co_only' && $a['type'] !== 'co') $skip = true;
            if ($group === 'spot_only' && $a['type'] !== 'spot') $skip = true;
            if ($group === 'session1' && $a['session'] != 1) $skip = true;
            if ($group === 'session2' && $a['session'] != 2) $skip = true;
            
            if ($skip) continue;
            
            $affected++;
            
            // Ajouter aux distances sélectionnées
            if ($targetDistance === 'd1' || $targetDistance === 'both') {
                for ($i = 0; $i < $numVolleys; $i++) {
                    $currentLen = strlen($a['d1_str']);
                    if ($currentLen + $arrowsPerEnd <= $maxArrowsPerDistance) {
                        $volley = generateVolley($a['type'], $arrowType, $arrowsPerEnd);
                        $a['d1_str'] .= $volley;
                        $totalArrowsAdded += $arrowsPerEnd;
                    } else {
                        // Ajouter seulement les flèches nécessaires pour atteindre le max
                        $remaining = $maxArrowsPerDistance - $currentLen;
                        if ($remaining > 0) {
                            $partialVolley = generateVolley($a['type'], $arrowType, $remaining);
                            $a['d1_str'] .= $partialVolley;
                            $totalArrowsAdded += $remaining;
                        }
                    }
                }
            }
            
            if ($targetDistance === 'd2' || $targetDistance === 'both') {
                for ($i = 0; $i < $numVolleys; $i++) {
                    $currentLen = strlen($a['d2_str']);
                    if ($currentLen + $arrowsPerEnd <= $maxArrowsPerDistance) {
                        $volley = generateVolley($a['type'], $arrowType, $arrowsPerEnd);
                        $a['d2_str'] .= $volley;
                        $totalArrowsAdded += $arrowsPerEnd;
                    } else {
                        // Ajouter seulement les flèches nécessaires pour atteindre le max
                        $remaining = $maxArrowsPerDistance - $currentLen;
                        if ($remaining > 0) {
                            $partialVolley = generateVolley($a['type'], $arrowType, $remaining);
                            $a['d2_str'] .= $partialVolley;
                            $totalArrowsAdded += $remaining;
                        }
                    }
                }
            }
            
            $a['d1_str'] = substr($a['d1_str'], 0, $maxArrowsPerDistance);
            $a['d2_str'] = substr($a['d2_str'], 0, $maxArrowsPerDistance);
            
            $newScoreD1 = calculateScore($a['d1_str']);
            $newScoreD2 = calculateScore($a['d2_str']);
            
            // Calculer les nouvelles statistiques D1 et D2
            $statsD1 = calculateStats($a['d1_str']);
            $statsD2 = calculateStats($a['d2_str']);
            
            // Calculer les totaux généraux
            $totalScore = $newScoreD1 + $newScoreD2;
            $totalHits = $statsD1['hits'] + $statsD2['hits'];
            $totalGold = $statsD1['gold'] + $statsD2['gold'];
            $totalXnine = $statsD1['xnine'] + $statsD2['xnine'];
            
            $updateQuery = "UPDATE Qualifications SET 
                QuD1Arrowstring = '" . db_escape($a['d1_str']) . "',
                QuD2Arrowstring = '" . db_escape($a['d2_str']) . "',
                QuD1Score = $newScoreD1,
                QuD2Score = $newScoreD2,
                QuD1Hits = " . $statsD1['hits'] . ",
                QuD1Gold = " . $statsD1['gold'] . ",
                QuD1Xnine = " . $statsD1['xnine'] . ",
                QuD2Hits = " . $statsD2['hits'] . ",
                QuD2Gold = " . $statsD2['gold'] . ",
                QuD2Xnine = " . $statsD2['xnine'] . ",
                QuScore = $totalScore,
                QuHits = $totalHits,
                QuGold = $totalGold,
                QuXnine = $totalXnine
                WHERE QuId = " . $a['id'];
            
            safe_w_sql($updateQuery);
        }
        
        $distanceText = ($targetDistance === 'd1') ? 'D1' : (($targetDistance === 'd2') ? 'D2' : 'D1 et D2');
        $arrowText = ($arrowsPerEnd === 3) ? '3 flèches' : '6 flèches';
        echo json_encode([
            'success' => true,
            'message' => $numVolleys . " volée(s) de " . $arrowText . " ajoutée(s) sur " . $distanceText . " pour " . $affected . " archers (" . $totalArrowsAdded . " flèches) - Tournoi " . ($tournamentType === 'indoor' ? 'INDOOR' : 'OUTDOOR')
        ]);
        exit;
    }
    
if ($action === 'reset_arrows') {
    $group = isset($_POST['archer_group']) ? $_POST['archer_group'] : 'all';
    $targetDistance = isset($_POST['target_distance']) ? $_POST['target_distance'] : 'both';
    $affected = 0;
    
    foreach ($archers as $a) {
        $skip = false;
        if ($group === 'co_only' && $a['type'] !== 'co') $skip = true;
        if ($group === 'spot_only' && $a['type'] !== 'spot') $skip = true;
        if ($group === 'session1' && $a['session'] != 1) $skip = true;
        if ($group === 'session2' && $a['session'] != 2) $skip = true;
        
        if ($skip) continue;
        
        $affected++;
        
        // Déterminer quelles distances réinitialiser
        $resetD1 = ($targetDistance === 'd1' || $targetDistance === 'both');
        $resetD2 = ($targetDistance === 'd2' || $targetDistance === 'both');
        
        // Valeurs actuelles si on ne réinitialise qu'une seule distance
        $currentD1Str = $a['d1_str'];
        $currentD2Str = $a['d2_str'];
        
        if ($resetD1) {
            $a['d1_str'] = '';
        }
        if ($resetD2) {
            $a['d2_str'] = '';
        }
        
        // Construire la requête dynamique
        $setClauses = [];
        
        if ($resetD1) {
            $setClauses[] = "QuD1Arrowstring = ''";
            $setClauses[] = "QuD1Score = 0";
            $setClauses[] = "QuD1Hits = 0";
            $setClauses[] = "QuD1Gold = 0";
            $setClauses[] = "QuD1Xnine = 0";
            $setClauses[] = "QuD1Rank = 0";
        }
        
        if ($resetD2) {
            $setClauses[] = "QuD2Arrowstring = ''";
            $setClauses[] = "QuD2Score = 0";
            $setClauses[] = "QuD2Hits = 0";
            $setClauses[] = "QuD2Gold = 0";
            $setClauses[] = "QuD2Xnine = 0";
            $setClauses[] = "QuD2Rank = 0";
        }
        
        // Recalculer les totaux si nécessaire
        if ($resetD1 && $resetD2) {
            // Tout réinitialiser
            $setClauses[] = "QuScore = 0";
            $setClauses[] = "QuHits = 0";
            $setClauses[] = "QuGold = 0";
            $setClauses[] = "QuXnine = 0";
            $setClauses[] = "QuClRank = 0";
            $setClauses[] = "QuConfirm = 0";
        } elseif ($resetD1 && !$resetD2) {
            // Réinitialiser D1, garder D2
            $statsD2 = calculateStats($currentD2Str);
            $setClauses[] = "QuScore = QuD2Score";
            $setClauses[] = "QuHits = " . $statsD2['hits'];
            $setClauses[] = "QuGold = " . $statsD2['gold'];
            $setClauses[] = "QuXnine = " . $statsD2['xnine'];
        } elseif (!$resetD1 && $resetD2) {
            // Réinitialiser D2, garder D1
            $statsD1 = calculateStats($currentD1Str);
            $setClauses[] = "QuScore = QuD1Score";
            $setClauses[] = "QuHits = " . $statsD1['hits'];
            $setClauses[] = "QuGold = " . $statsD1['gold'];
            $setClauses[] = "QuXnine = " . $statsD1['xnine'];
        }
        
        $updateQuery = "UPDATE Qualifications SET " . implode(", ", $setClauses) . " WHERE QuId = " . $a['id'];
        safe_w_sql($updateQuery);
    }
    
    $distanceText = ($targetDistance === 'd1') ? 'D1' : (($targetDistance === 'd2') ? 'D2' : 'D1 et D2');
    echo json_encode([
        'success' => true,
        'message' => "Flèches réinitialisées sur " . $distanceText . " pour " . $affected . " archers - Tournoi " . ($tournamentType === 'indoor' ? 'INDOOR' : 'OUTDOOR')
    ]);
    exit;
}

    if ($action === 'complete_session') {
        $group = isset($_POST['archer_group']) ? $_POST['archer_group'] : 'all';
        $arrowType = isset($_POST['arrow_type']) ? $_POST['arrow_type'] : 'random';
        $targetDistance = isset($_POST['target_distance']) ? $_POST['target_distance'] : 'both';
        $affected = 0;
        $totalArrowsAdded = 0;
        
        foreach ($archers as &$a) {
            $skip = false;
            if ($group === 'co_only' && $a['type'] !== 'co') $skip = true;
            if ($group === 'spot_only' && $a['type'] !== 'spot') $skip = true;
            if ($group === 'session1' && $a['session'] != 1) $skip = true;
            if ($group === 'session2' && $a['session'] != 2) $skip = true;
            
            if ($skip) continue;
            
            $affected++;
            
            if ($targetDistance === 'd1' || $targetDistance === 'both') {
                $currentLenD1 = strlen($a['d1_str']);
                $neededD1 = max(0, $maxArrowsPerDistance - $currentLenD1);
                $volleysNeededD1 = ceil($neededD1 / $arrowsPerEnd);
                
                for ($i = 0; $i < $volleysNeededD1; $i++) {
                    $remaining = $maxArrowsPerDistance - strlen($a['d1_str']);
                    $arrowsToAdd = min($arrowsPerEnd, $remaining);
                    if ($arrowsToAdd > 0) {
                        $volley = generateVolley($a['type'], $arrowType, $arrowsToAdd);
                        $a['d1_str'] .= $volley;
                        $totalArrowsAdded += $arrowsToAdd;
                    }
                }
                $a['d1_str'] = substr($a['d1_str'], 0, $maxArrowsPerDistance);
            }
            
            if ($targetDistance === 'd2' || $targetDistance === 'both') {
                $currentLenD2 = strlen($a['d2_str']);
                $neededD2 = max(0, $maxArrowsPerDistance - $currentLenD2);
                $volleysNeededD2 = ceil($neededD2 / $arrowsPerEnd);
                
                for ($i = 0; $i < $volleysNeededD2; $i++) {
                    $remaining = $maxArrowsPerDistance - strlen($a['d2_str']);
                    $arrowsToAdd = min($arrowsPerEnd, $remaining);
                    if ($arrowsToAdd > 0) {
                        $volley = generateVolley($a['type'], $arrowType, $arrowsToAdd);
                        $a['d2_str'] .= $volley;
                        $totalArrowsAdded += $arrowsToAdd;
                    }
                }
                $a['d2_str'] = substr($a['d2_str'], 0, $maxArrowsPerDistance);
            }
            
            $newScoreD1 = calculateScore($a['d1_str']);
            $newScoreD2 = calculateScore($a['d2_str']);
            
            // Calculer les nouvelles statistiques D1 et D2
            $statsD1 = calculateStats($a['d1_str']);
            $statsD2 = calculateStats($a['d2_str']);
            
            // Calculer les totaux généraux
            $totalScore = $newScoreD1 + $newScoreD2;
            $totalHits = $statsD1['hits'] + $statsD2['hits'];
            $totalGold = $statsD1['gold'] + $statsD2['gold'];
            $totalXnine = $statsD1['xnine'] + $statsD2['xnine'];
            
            $updateQuery = "UPDATE Qualifications SET 
                QuD1Arrowstring = '" . db_escape($a['d1_str']) . "',
                QuD2Arrowstring = '" . db_escape($a['d2_str']) . "',
                QuD1Score = $newScoreD1,
                QuD2Score = $newScoreD2,
                QuD1Hits = " . $statsD1['hits'] . ",
                QuD1Gold = " . $statsD1['gold'] . ",
                QuD1Xnine = " . $statsD1['xnine'] . ",
                QuD2Hits = " . $statsD2['hits'] . ",
                QuD2Gold = " . $statsD2['gold'] . ",
                QuD2Xnine = " . $statsD2['xnine'] . ",
                QuScore = $totalScore,
                QuHits = $totalHits,
                QuGold = $totalGold,
                QuXnine = $totalXnine
                WHERE QuId = " . $a['id'];
            
            safe_w_sql($updateQuery);
        }
        
        $distanceText = ($targetDistance === 'd1') ? 'D1' : (($targetDistance === 'd2') ? 'D2' : 'D1 et D2');
        $arrowText = ($arrowsPerEnd === 3) ? '3 flèches' : '6 flèches';
        $maxText = ($maxArrowsPerDistance === 30) ? '30 flèches' : '36 flèches';
        echo json_encode([
            'success' => true,
            'message' => "Session complétée sur " . $distanceText . " pour " . $affected . " archers (" . $totalArrowsAdded . " flèches ajoutées) - Tournoi " . ($tournamentType === 'indoor' ? 'INDOOR' : 'OUTDOOR') . " (" . $arrowText . "/volée, max " . $maxText . " par distance)"
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => "Action non reconnue: $action"]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
?>