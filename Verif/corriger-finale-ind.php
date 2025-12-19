<?php
/**
 * Script de correction du champ EnIndFEvent
 * Appelé en AJAX depuis la page de vérification
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

header('Content-Type: application/json');

CheckTourSession(true);
checkACL(AclParticipants, AclReadWrite);

$JSON = array('success' => false, 'message' => 'Données invalides');

if (IsBlocked(BIT_BLOCK_PARTICIPANT)) {
    $JSON['message'] = get_text('Blocked');
    echo json_encode($JSON);
    exit;
}

$EnId = (isset($_POST['enId']) ? intval($_POST['enId']) : 0);
$Valeur = (isset($_POST['valeur']) ? intval($_POST['valeur']) : -1);

if ($EnId > 0 && ($Valeur == 0 || $Valeur == 1)) {
    // Vérification que l'archer appartient au tournoi en cours
    $TourId = $_SESSION['TourId'];
    
    $Check = "SELECT EnId FROM Entries WHERE EnId = $EnId AND EnTournament = $TourId";
    $Rs = safe_r_sql($Check);
    
    if (safe_num_rows($Rs) == 1) {
        // Mise à jour
        $Update = "UPDATE Entries SET EnIndFEvent = $Valeur, EnTimestamp = EnTimestamp WHERE EnId = $EnId";
        $Result = safe_w_sql($Update);
        
        if ($Result) {
            $JSON['success'] = true;
            $JSON['message'] = 'Correction effectuée avec succès';
        } else {
            $JSON['message'] = 'Erreur lors de la mise à jour';
        }
    } else {
        $JSON['message'] = 'Archer non trouvé dans ce tournoi';
    }
} else {
    $JSON['message'] = 'Paramètres invalides (EnId: '.$EnId.', Valeur: '.$Valeur.')';
}

echo json_encode($JSON);
?>
