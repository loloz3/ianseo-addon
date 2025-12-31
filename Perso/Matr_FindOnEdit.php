<?php
/*
    - Matr_FindOnEdit.php -
    Recherche de matricule pour l'édition
*/

define('debug', false);

// Chemin de base corrigé
$basePath = dirname(dirname(dirname(dirname(__FILE__)))); // C:\ianseo\htdocs

// Inclure config.php
if (!file_exists($basePath . '/config.php')) {
    die("Erreur: Fichier config.php non trouvé");
}
require_once($basePath . '/config.php');

// Activer l'affichage des erreurs si en debug
if (debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Vérifier la session
if (!CheckTourSession()) {
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<response><error>1</error><message>Session invalide</message></response>';
    exit;
}

// Vérifier les permissions
checkFullACL(AclParticipants, 'pEntries', AclReadWrite, false);

// Inclure les fichiers nécessaires
$requiredFiles = [
    'Partecipants/Fun_Partecipants.local.inc.php',
    'Common/Fun_Various.inc.php',
    'Common/Fun_Sessions.inc.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        require_once($fullPath);
    } elseif (debug) {
        error_log("Fichier non trouvé: " . $fullPath);
    }
}

// Vérifier les paramètres
if (!isset($_REQUEST['Matr'])) {
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<response><error>1</error><message>Paramètre Matr manquant</message></response>';
    exit;
}

// Initialiser les variables
$nocCode = isset($_REQUEST['Noc']) ? trim($_REQUEST['Noc']) : '';
$matr = trim($_REQUEST['Matr']);
$Errore = 0;

// Initialiser les variables de réponse
$response = [
    'error' => 1,
    'code' => '',
    'name' => '',
    'firstname' => '',
    'ctrl_code' => '',
    'dob' => '',
    'sex' => '0',
    'division' => '',
    'class' => '',
    'ageclass' => '',
    'subclass' => '',
    'status' => '0',
    'country' => '',
    'idcountry' => '0',
    'nation' => '',
    'country2' => '',
    'idcountry2' => '0',
    'nation2' => ''
];

try {
    if ($nocCode && $matr) {
        // Construction de la requête
        $Select = "SELECT * 
                   FROM LookUpEntries 
                   LEFT JOIN Divisions ON DivTournament = " . StrSafe_DB($_SESSION['TourId']) . " 
                       AND LueDivision = DivId 
                   LEFT JOIN Classes ON ClTournament = " . StrSafe_DB($_SESSION['TourId']) . " 
                       AND LueClass = ClId 
                   WHERE LueCode = " . StrSafe_DB($matr) . " 
                   AND LueIocCode = " . StrSafe_DB($nocCode);
        
        // Si aucun code NOC spécifié, chercher celui par défaut
        if (empty($nocCode)) {
            $Select .= " AND LueDefault = '1'";
        }
        
        $Rs = safe_r_sql($Select);
        
        if (safe_num_rows($Rs) == 1) {
            $MyRow = safe_fetch($Rs);
            
            // Remplir les données de base
            $response['code'] = $matr;
            $response['name'] = stripslashes($MyRow->LueName);
            $response['firstname'] = stripslashes($MyRow->LueFamilyName);
            $response['ctrl_code'] = $MyRow->LueCtrlCode;
            $response['sex'] = $MyRow->LueSex;
            $response['division'] = $MyRow->DivId ?: '';
            $response['class'] = $MyRow->ClId ?: '';
            $response['ageclass'] = $MyRow->ClId ?: '';
            $response['subclass'] = str_pad($MyRow->LueSubClass, 2, '0', STR_PAD_LEFT);
            
            // Calculer le statut
            $statusDate = $MyRow->LueStatusValidUntil;
            $today = $_SESSION['TourRealWhenTo'];
            
            if ($statusDate != '0000-00-00' && $statusDate < $today) {
                $response['status'] = '5'; // Expiré
            } else {
                $response['status'] = $MyRow->LueStatus;
            }
            
            // Gestion de la date de naissance
            $dob = $MyRow->LueCtrlCode;
            if ($dob && $dob != '0000-00-00') {
                $response['dob'] = date(get_text('DateFmt'), strtotime($dob));
            }
            
            // Gestion des pays
            $countries = [
                '' => [
                    'code' => stripslashes($MyRow->LueCountry),
                    'short' => stripslashes($MyRow->LueCoShort),
                    'descr' => stripslashes($MyRow->LueCoDescr)
                ],
                '2' => [
                    'code' => stripslashes($MyRow->LueCountry2),
                    'short' => stripslashes($MyRow->LueCoShort2 ?: $MyRow->LueCoShort),
                    'descr' => stripslashes($MyRow->LueCoDescr2 ?: $MyRow->LueCoDescr)
                ]
            ];
            
            foreach ($countries as $key => $countryData) {
                if (!empty($countryData['code'])) {
                    $suffix = ($key == '2') ? '2' : '';
                    
                    $response['country' . $suffix] = $countryData['code'];
                    $response['nation' . $suffix] = $countryData['short'];
                    
                    // Chercher le pays dans la table Countries
                    $SelCountry = "SELECT CoId, CoName, CoNameComplete 
                                   FROM Countries 
                                   WHERE CoCode = " . StrSafe_DB($countryData['code']) . " 
                                   AND CoTournament = " . StrSafe_DB($_SESSION['TourId']);
                    
                    $RsC = safe_r_sql($SelCountry);
                    
                    if (safe_num_rows($RsC) == 1) {
                        $RowC = safe_fetch($RsC);
                        $response['idcountry' . $suffix] = $RowC->CoId;
                        $response['nation' . $suffix] = stripslashes($RowC->CoName);
                    } else {
                        $response['idcountry' . $suffix] = '-1';
                    }
                }
            }
            
            $response['error'] = 0;
            
        } else {
            // Aucun résultat ou plusieurs résultats
            $response['error'] = 1;
            if (debug) {
                error_log("Matr_FindOnEdit: Aucun résultat pour Matr=$matr, Noc=$nocCode");
            }
        }
    } else {
        $response['error'] = 1;
        if (debug) {
            error_log("Matr_FindOnEdit: Paramètres manquants Matr=$matr, Noc=$nocCode");
        }
    }
    
} catch (Exception $e) {
    $response['error'] = 1;
    if (debug) {
        error_log("Matr_FindOnEdit Exception: " . $e->getMessage());
    }
}

// Envoyer la réponse XML
if (!debug) {
    header('Content-Type: text/xml');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
}

// Générer le XML
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<response>';
foreach ($response as $key => $value) {
    echo "<$key><![CDATA[" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "]]></$key>";
}
echo '</response>';
?>