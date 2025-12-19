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

// Validation du format de QuTargetNo si non vide
if ($QuTargetNo !== '' && !preg_match('/^[1-9]\d{3}[A-D]$/', $QuTargetNo)) {
    echo json_encode([
        'success' => false,
        'error' => 'Format de cible invalide (attendu: 1001A, 1002B, etc.)'
    ]);
    exit;
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

    // Mettre à jour la qualification
    $UpdateQuery = "UPDATE Qualifications SET 
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