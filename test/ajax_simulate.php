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

function db_escape($str) {
    global $db_link;
    return mysqli_real_escape_string($db_link, $str);
}

function letterToScore($letter) {
    $conversion = [
        'A' => 0, 'M' => 0,
        'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 10, 'X' => 10
    ];
    $letter = strtoupper($letter);
    return isset($conversion[$letter]) ? $conversion[$letter] : 0;
}

// Fonction pour calculer les statistiques à partir d'une chaîne de lettres
function calculateStats($arrowString) {
    $hits = 0;      // Nombre total de flèches
    $gold = 0;      // Nombre de 10 (X, K, L)
    $xnine = 0;     // Nombre de 9 (J)
    
    if (empty($arrowString)) {
        return ['hits' => 0, 'gold' => 0, 'xnine' => 0];
    }
    
    for ($i = 0; $i < strlen($arrowString); $i++) {
        $letter = strtoupper($arrowString[$i]);
        $hits++;
        
        if ($letter == 'K' || $letter == 'L' || $letter == 'X') {
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
        $letters = ['F', 'G', 'H', 'I', 'J', 'X', 'X', 'M'];
        return $letters[array_rand($letters)];
    } else {
        $letters = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'X', 'X', 'X', 'M'];
        return $letters[array_rand($letters)];
    }
}

function generateFixedLetter($arrowType, $letterValue) {
    $letterMap = [
        '10' => 'X',
        '9' => 'J',
        '8' => 'I',
        '7' => 'H',
        '6' => 'G',
        '5' => 'F',
        '4' => 'E',
        '3' => 'D',
        '2' => 'C',
        '1' => 'B',
        '0' => 'M'
    ];
    return isset($letterMap[$letterValue]) ? $letterMap[$letterValue] : 'M';
}

function generateVolley($archerType, $arrowType = 'random') {
    if ($arrowType === 'all_10') {
        return 'XXX';
    } elseif ($arrowType === 'all_9') {
        return 'JJJ';
    } elseif ($arrowType === 'all_8') {
        return 'III';
    } else {
        return generateRandomLetter($archerType) . 
               generateRandomLetter($archerType) . 
               generateRandomLetter($archerType);
    }
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
            $last1 = ($d1 >= 3) ? substr($a['d1_str'], -3) : $a['d1_str'];
            $last2 = ($d2 >= 3) ? substr($a['d2_str'], -3) : $a['d2_str'];
            
            if ($d1 < 3) $last1 = $a['d1_str'];
            if ($d2 < 3) $last2 = $a['d2_str'];
            
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
        $targetDistance = isset($_POST['target_distance']) ? $_POST['target_distance'] : 'both'; // 'd1', 'd2', 'both'
        
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
                    $volley = generateVolley($a['type'], $arrowType);
                    $a['d1_str'] .= $volley;
                    $totalArrowsAdded += 3;
                }
            }
            
            if ($targetDistance === 'd2' || $targetDistance === 'both') {
                for ($i = 0; $i < $numVolleys; $i++) {
                    $volley = generateVolley($a['type'], $arrowType);
                    $a['d2_str'] .= $volley;
                    $totalArrowsAdded += 3;
                }
            }
            
            $a['d1_str'] = substr($a['d1_str'], 0, 30);
            $a['d2_str'] = substr($a['d2_str'], 0, 30);
            
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
        echo json_encode([
            'success' => true,
            'message' => $numVolleys . " volée(s) ajoutée(s) sur " . $distanceText . " pour " . $affected . " archers (" . $totalArrowsAdded . " flèches)"
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
            
            // Construire la requête dynamique selon la distance sélectionnée
            $setClauses = [];
            
            if ($targetDistance === 'd1' || $targetDistance === 'both') {
                $setClauses[] = "QuD1Arrowstring = ''";
                $setClauses[] = "QuD1Score = 0";
                $setClauses[] = "QuD1Hits = 0";
                $setClauses[] = "QuD1Gold = 0";
                $setClauses[] = "QuD1Xnine = 0";
                $setClauses[] = "QuD1Rank = 0";
            }
            
            if ($targetDistance === 'd2' || $targetDistance === 'both') {
                $setClauses[] = "QuD2Arrowstring = ''";
                $setClauses[] = "QuD2Score = 0";
                $setClauses[] = "QuD2Hits = 0";
                $setClauses[] = "QuD2Gold = 0";
                $setClauses[] = "QuD2Xnine = 0";
                $setClauses[] = "QuD2Rank = 0";
            }
            
            // Si on réinitialise une seule distance, il faut recalculer les totaux
            if ($targetDistance === 'd1') {
                $statsD2 = calculateStats($a['d2_str']);
                $setClauses[] = "QuScore = QuD2Score";
                $setClauses[] = "QuHits = " . $statsD2['hits'];
                $setClauses[] = "QuGold = " . $statsD2['gold'];
                $setClauses[] = "QuXnine = " . $statsD2['xnine'];
            } elseif ($targetDistance === 'd2') {
                $statsD1 = calculateStats($a['d1_str']);
                $setClauses[] = "QuScore = QuD1Score";
                $setClauses[] = "QuHits = " . $statsD1['hits'];
                $setClauses[] = "QuGold = " . $statsD1['gold'];
                $setClauses[] = "QuXnine = " . $statsD1['xnine'];
            } else {
                // both: tout remettre à zéro
                $setClauses[] = "QuScore = 0";
                $setClauses[] = "QuHits = 0";
                $setClauses[] = "QuGold = 0";
                $setClauses[] = "QuXnine = 0";
                $setClauses[] = "QuClRank = 0";
                $setClauses[] = "QuConfirm = 0";
            }
            
            $updateQuery = "UPDATE Qualifications SET " . implode(", ", $setClauses) . " WHERE QuId = " . $a['id'];
            
            safe_w_sql($updateQuery);
        }
        
        $distanceText = ($targetDistance === 'd1') ? 'D1' : (($targetDistance === 'd2') ? 'D2' : 'D1 et D2');
        echo json_encode([
            'success' => true,
            'message' => "Flèches réinitialisées sur " . $distanceText . " pour " . $affected . " archers"
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
                $neededD1 = max(0, 30 - $currentLenD1);
                $volleysNeededD1 = ceil($neededD1 / 3);
                
                for ($i = 0; $i < $volleysNeededD1; $i++) {
                    $volley = generateVolley($a['type'], $arrowType);
                    $a['d1_str'] .= $volley;
                    $totalArrowsAdded += min(3, 30 - strlen($a['d1_str']) + 3);
                }
                $a['d1_str'] = substr($a['d1_str'], 0, 30);
            }
            
            if ($targetDistance === 'd2' || $targetDistance === 'both') {
                $currentLenD2 = strlen($a['d2_str']);
                $neededD2 = max(0, 30 - $currentLenD2);
                $volleysNeededD2 = ceil($neededD2 / 3);
                
                for ($i = 0; $i < $volleysNeededD2; $i++) {
                    $volley = generateVolley($a['type'], $arrowType);
                    $a['d2_str'] .= $volley;
                    $totalArrowsAdded += min(3, 30 - strlen($a['d2_str']) + 3);
                }
                $a['d2_str'] = substr($a['d2_str'], 0, 30);
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
        echo json_encode([
            'success' => true,
            'message' => "Session complétée sur " . $distanceText . " pour " . $affected . " archers (" . $totalArrowsAdded . " flèches ajoutées)"
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => "Action non reconnue: $action"]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
?>