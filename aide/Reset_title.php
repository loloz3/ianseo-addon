<?php
// Reset_title.php - Version ultra-simple
require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');

if (!CheckTourSession()) {
    exit;
}

// Vérification des ACL (lecture/écriture)
checkFullACL(array(AclQualification, AclEliminations, AclRobin, AclIndividuals, AclTeams), '', AclReadWrite);

// Requête SQL pour réinitialiser tous les en-têtes d'impression
$sql = "UPDATE Events 
        SET EvQualPrintHead = ' ', 
            EvFinalPrintHead = ' ' 
        WHERE EvTournament = " . StrSafe_DB($_SESSION['TourId']) . " 
        AND EvCodeParent = ' '";

// Exécution de la requête
$result = safe_w_SQL($sql);

// Pas de sortie - l'iframe reste vide
// Le JavaScript dans aide-concours.php gère la notification
exit;
?>