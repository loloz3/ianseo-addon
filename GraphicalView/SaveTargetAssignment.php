<?php
/**
 * @license Libre - Copyright (c) 2025 Auteur Original
 * Libre d'utilisation, modification et distribution sous conditions:
 * 1. Garder cette notice et la liste des contributeurs
 * 2. Partager toute modification
 * 3. Citer les contributeurs
 * 
 * Contributeurs:
 * - Auteur Original
 * - Laurent Petroff - Les Archers de Perols - (modif: 2025-12-15)
 * 
 * Dernière modification: 2025-12-15 par Laurent Petroff
 *
 * SaveTargetAssignment.php
 * API pour sauvegarder l'assignment des archers aux cibles
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_FormatText.inc.php');

// Vérifier la session et les droits
CheckTourSession(true);
checkFullACL(AclParticipants, 'pTarget', AclReadWrite);

// Définir le header JSON
header('Content-Type: application/json');

// Récupérer les données POST
$EnId = isset($_POST['EnId']) ? intval($_POST['EnId']) : 0;
$QuTargetNo = isset($_POST['QuTargetNo']) ? $_POST['QuTargetNo'] : '';
$QuSession = isset($_POST['QuSession']) ? intval($_POST['QuSession']) : 0;

// Validation des données
if ($EnId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'ID archer invalide'
    ]);
    exit;
}

// DÉCOMPOSER QuTargetNo EN QuTarget ET QuLetter
$QuTarget = 0;
$QuLetter = '';

if ($QuTargetNo !== '') {
    // Validation du format de QuTargetNo si non vide
    if (!preg_match('/^[1-9]\d{3}[A-D]$/', $QuTargetNo)) {
        echo json_encode([
            'success' => false,
            'error' => 'Format de cible invalide (attendu: 1001A, 1002B, etc.)'
        ]);
        exit;
    }
    
    // Extraire la partie numérique (les 3 derniers chiffres après le premier)
    // Exemple: "1013B" -> cible "013" = 13
    $QuTarget = intval(substr($QuTargetNo, 1, 3));
    
    // Extraire la lettre (dernier caractère)
    $QuLetter = substr($QuTargetNo, -1);
}

try {
    // Vérifier que l'archer appartient au tournoi courant
    $CheckQuery = "SELECT EnId FROM Entries 
                   WHERE EnId=" . StrSafe_DB($EnId) . " 
                   AND EnTournament=" . StrSafe_DB($_SESSION['TourId']);
    
    $CheckResult = safe_r_sql($CheckQuery);
    
    if (safe_num_rows($CheckResult) == 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Archer non trouvé dans ce tournoi'
        ]);
        exit;
    }

    // Mettre à jour la qualification AVEC QuTarget, QuLetter ET QuTargetNo
    $UpdateQuery = "UPDATE Qualifications SET 
                    QuTarget=" . StrSafe_DB($QuTarget) . ",
                    QuLetter=" . StrSafe_DB($QuLetter) . ",
                    QuTargetNo=" . StrSafe_DB($QuTargetNo) . ",
                    QuSession=" . StrSafe_DB($QuSession) . "
                    WHERE QuId=" . StrSafe_DB($EnId);
    
    $result = safe_w_sql($UpdateQuery);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Assignment sauvegardé avec succès',
            'data' => [
                'EnId' => $EnId,
                'QuTarget' => $QuTarget,
                'QuLetter' => $QuLetter,
                'QuTargetNo' => $QuTargetNo,
                'QuSession' => $QuSession
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de la mise à jour de la base de données'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>