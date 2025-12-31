<?php
/*
    - UpdateCtrlCode.php -
    Contrôle le code de contrôle des participants
*/

define('debug', true); // Activez pour voir les erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Chemin de base corrigé
$basePath = dirname(dirname(dirname(dirname(__FILE__)))); // Remonte à la racine

// Inclure config.php
if (!file_exists($basePath . '/config.php')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 1, 'message' => 'Fichier config.php non trouvé']);
    exit;
}

require_once($basePath . '/config.php');

// Initialiser la réponse JSON
$JSON = ['error' => 1, 'div' => [], 'age' => [], 'clas' => [], 'dob' => ''];

try {
    // Vérifier la session
    if (!CheckTourSession()) {
        $JSON['message'] = 'Session invalide';
        JsonOut($JSON);
    }
    
    // Vérifier les permissions
    if (!hasFullACL(AclParticipants, 'pEntries', AclReadOnly)) {
        $JSON['message'] = 'Permissions insuffisantes';
        JsonOut($JSON);
    }
    
    // Vérifier les paramètres requis
    if (!isset($_REQUEST['d_e_EnCtrlCode']) || !isset($_REQUEST['d_e_EnSex'])) {
        $JSON['message'] = 'Paramètres manquants';
        JsonOut($JSON);
    }
    
    // Inclure les fichiers nécessaires
    $requiredFiles = [
        'Common/Lib/Fun_DateTime.inc.php',
        'Fun_Partecipants.local.inc.php'
    ];
    
    foreach ($requiredFiles as $file) {
        $fullPath = $basePath . '/' . $file;
        if (file_exists($fullPath)) {
            require_once($fullPath);
        } elseif (debug) {
            error_log("Fichier non trouvé: " . $fullPath);
        }
    }
    
    // Traiter les paramètres
    $ctrlCode = '';
    $Age = '';
    $Sex = intval($_REQUEST['d_e_EnSex']);
    $Div = (!empty($_REQUEST['d_e_EnDiv']) ? $_REQUEST['d_e_EnDiv'] : '');
    $Clas = (!empty($_REQUEST['d_e_EnAgeClass']) ? $_REQUEST['d_e_EnAgeClass'] : '');
    
    // Convertir la date si fournie
    if (!empty($_REQUEST['d_e_EnCtrlCode'])) {
        $ctrlCode = ConvertDateLoc($_REQUEST['d_e_EnCtrlCode']);
        if ($ctrlCode !== false) {
            // Calculer l'âge
            $currentYear = intval(substr($_SESSION['TourRealWhenTo'], 0, 4));
            $birthYear = intval(substr($ctrlCode, 0, 4));
            $Age = $currentYear - $birthYear;
        }
    }
    
    // Obtenir les divisions autorisées basées sur l'âge
    $Select1 = "SELECT DISTINCT DivId 
                FROM Classes
                INNER JOIN Divisions ON DivTournament = ClTournament AND DivAthlete = ClAthlete
                WHERE ClTournament = " . intval($_SESSION['TourId']) . "
                AND (ClDivisionsAllowed = '' OR FIND_IN_SET(DivId, ClDivisionsAllowed))
                AND ClSex IN (-1, " . $Sex . ")";
    
    // Ajouter la condition d'âge si disponible et si c'est un athlète
    if ($Age) {
        $Select1 .= " AND (ClAthlete != '1' OR (ClAgeFrom <= " . intval($Age) . " AND ClAgeTo >= " . intval($Age) . "))";
    }
    
    $Select1 .= " ORDER BY DivViewOrder";
    
    $RsCl = safe_r_sql($Select1);
    if ($RsCl) {
        while ($MyRow = safe_fetch($RsCl)) {
            $JSON['div'][] = $MyRow->DivId;
        }
    }
    
    // Obtenir les classes d'âge basées sur la division sélectionnée
    $Select2 = "SELECT DISTINCT ClId 
                FROM Classes
                INNER JOIN Divisions ON DivTournament = ClTournament AND DivAthlete = ClAthlete";
    
    if ($Div) {
        $Select2 .= " AND DivId = " . StrSafe_DB($Div);
    }
    
    $Select2 .= " WHERE ClTournament = " . intval($_SESSION['TourId']) . "
                  AND (ClDivisionsAllowed = '' OR FIND_IN_SET(DivId, ClDivisionsAllowed))
                  AND ClSex IN (-1, " . $Sex . ")";
    
    if ($Age) {
        $Select2 .= " AND (ClAthlete != '1' OR (ClAgeFrom <= " . intval($Age) . " AND ClAgeTo >= " . intval($Age) . "))";
    }
    
    $Select2 .= " ORDER BY ClViewOrder, DivViewOrder";
    
    $RsCl = safe_r_sql($Select2);
    if ($RsCl) {
        while ($MyRow = safe_fetch($RsCl)) {
            $JSON['age'][] = $MyRow->ClId;
        }
    }
    
    // Ajuster la classe si nécessaire
    if (!empty($JSON['age']) && !in_array($Clas, $JSON['age'])) {
        $Clas = $JSON['age'][0];
    } else {
        $Clas = '';
    }
    
    // Obtenir les classes valides basées sur la division et la classe sélectionnées
    $Select3 = "SELECT DISTINCT ClValidClass 
                FROM Classes
                INNER JOIN Divisions ON DivTournament = ClTournament AND DivAthlete = ClAthlete";
    
    if ($Div) {
        $Select3 .= " AND DivId = " . StrSafe_DB($Div);
    }
    
    $Select3 .= " WHERE ClTournament = " . intval($_SESSION['TourId']) . "
                  AND (ClDivisionsAllowed = '' OR FIND_IN_SET(DivId, ClDivisionsAllowed))
                  AND ClSex IN (-1, " . $Sex . ")";
    
    if ($Clas) {
        $Select3 .= " AND ClId = " . StrSafe_DB($Clas);
    }
    
    if ($Age) {
        $Select3 .= " AND (ClAthlete != '1' OR (ClAgeFrom <= " . intval($Age) . " AND ClAgeTo >= " . intval($Age) . "))";
    }
    
    $Select3 .= " ORDER BY ClViewOrder, DivViewOrder";
    
    $RsCl = safe_r_sql($Select3);
    if ($RsCl) {
        $validClasses = [];
        while ($MyRow = safe_fetch($RsCl)) {
            if (!empty($MyRow->ClValidClass)) {
                $classes = explode(',', $MyRow->ClValidClass);
                $validClasses = array_merge($validClasses, $classes);
            }
        }
        $JSON['clas'] = array_unique($validClasses);
    }
    
    // Convertir la date au format local
    if ($ctrlCode !== false && !empty($ctrlCode)) {
        $JSON['dob'] = RevertDate($ctrlCode);
    }
    
    $JSON['error'] = 0;
    
} catch (Exception $e) {
    $JSON['error'] = 1;
    $JSON['message'] = 'Erreur: ' . $e->getMessage();
    if (debug) {
        error_log("CheckCtrlCode.php Exception: " . $e->getMessage());
    }
}

// Utilisez la fonction JsonOut() qui existe déjà dans Globals.inc.php
// NE PAS redéclarer la fonction ici
JsonOut($JSON);
?>