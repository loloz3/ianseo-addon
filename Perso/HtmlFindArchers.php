<?php
define('debug', true); // Activez pour le débogage

// Chemin de base corrigé
$basePath = dirname(dirname(dirname(dirname(__FILE__)))); // Remonte jusqu'à la racine htdocs

// Inclure config.php
if (!file_exists($basePath . '/config.php')) {
    die("Erreur: Fichier config.php non trouvé à " . $basePath . '/config.php');
}
require_once($basePath . '/config.php');

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier la session
if (!CheckTourSession()) {
    echo json_encode(['error' => 'Session invalide']);
    exit;
}

// Vérifier les permissions
checkFullACL(AclParticipants, 'pEntries', AclReadOnly);

// Inclure les autres fichiers nécessaires avec gestion d'erreurs
$requiredFiles = [
    'Common/Fun_Various.inc.php',
    'Common/Fun_Sessions.inc.php',
    'Common/Lib/Fun_DateTime.inc.php',
    'Common/Fun_FormatText.inc.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        require_once($fullPath);
    } else {
        error_log("Fichier non trouvé: " . $fullPath);
        echo json_encode(['error' => "Fichier $file non trouvé"]);
        exit;
    }
}

// Inclure les fichiers de participants
$participantsFiles = [
    'Qualification/Fun_Qualification.local.inc.php',
    'Partecipants/Fun_Partecipants.local.inc.php'
];

foreach ($participantsFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        require_once($fullPath);
    }
}

// Récupérer les paramètres avec des valeurs par défaut
$Code = !empty($_REQUEST['findCode']) ? trim($_REQUEST['findCode']) : '';
$Ath = !empty($_REQUEST['findAth']) ? trim($_REQUEST['findAth']) : '';
$Country = !empty($_REQUEST['findCountry']) ? trim($_REQUEST['findCountry']) : '';
$Div = !empty($_REQUEST['findDiv']) ? trim($_REQUEST['findDiv']) : '';
$Cl = !empty($_REQUEST['findCl']) ? trim($_REQUEST['findCl']) : '';
$SubCl = !empty($_REQUEST['findSubCl']) ? trim($_REQUEST['findSubCl']) : '';
$IocCode = !empty($_REQUEST['findIocCode']) ? trim($_REQUEST['findIocCode']) : '';

// Construire le filtre
$Filter = '1=1'; // Commencer avec une condition toujours vraie

if ($IocCode) {
    $Filter .= " AND LueIocCode = " . StrSafe_DB($IocCode);
}

if ($Code) {
    $Filter .= " AND LueCode LIKE " . StrSafe_DB("%" . $Code . "%");
}

if ($Ath && strlen($Ath) > 1) {
    $Ath = stripslashes($Ath);
    $Filter .= " AND (LueFamilyName LIKE " . StrSafe_DB("%" . $Ath . "%") . 
               " OR LueName LIKE " . StrSafe_DB("%" . $Ath . "%") . 
               " OR CONCAT(LueFamilyName, ' ', LueName) LIKE " . StrSafe_DB("%" . $Ath . "%") . ")";
}

if ($Country) {
    $Country = stripslashes($Country);
    $Filter .= " AND (LueCountry LIKE " . StrSafe_DB("%" . $Country . "%") . 
               " OR LueCoShort LIKE " . StrSafe_DB("%" . $Country . "%") . ")";
}

if ($Div && $Div != '--') {
    $Filter .= " AND LueDivision = " . StrSafe_DB($Div);
}

if ($Cl && $Cl != '--') {
    $Filter .= " AND LueClass = " . StrSafe_DB($Cl);
}

if ($SubCl && $SubCl != '--') {
    $Filter .= " AND LueSubClass = " . StrSafe_DB($SubCl);
}

// Requête SQL
$Select = "SELECT * 
           FROM LookUpEntries 
           WHERE " . $Filter . " 
           ORDER BY LueFamilyName, LueName 
           LIMIT 100"; // Limiter les résultats pour éviter des charges trop importantes

try {
    $Rs = safe_r_sql($Select);
    
    $html = '';
    $rowCount = safe_num_rows($Rs);
    
    if ($rowCount > 0) {
        $html = '<table class="Tabella">';
        $html .= '<thead><tr>
                    <th style="width:10%;">Code</th>
                    <th style="width:25%;">Athlète</th>
                    <th style="width:25%;">Pays</th>
                    <th style="width:10%;">Division</th>
                    <th style="width:10%;">Classe</th>
                    <th style="width:10%;">Sous-classe</th>
                    <th style="width:10%;">Action</th>
                  </tr></thead><tbody>';
        
        while ($row = safe_fetch($Rs)) {
            // Gestion spéciale pour ITA_i
            if ($row->LueIocCode == 'ITA_i' && $row->LueSubClass == '00' && 
                in_array($row->LueDivision, ['OL', 'CO', 'AN'])) {
                $row->LueSubClass = '04';
            }
            
            // Nettoyer les données
            $familyName = htmlspecialchars(stripslashes($row->LueFamilyName), ENT_QUOTES);
            $name = htmlspecialchars(stripslashes($row->LueName), ENT_QUOTES);
            $coShort = htmlspecialchars(stripslashes($row->LueCoShort), ENT_QUOTES);
            
            $html .= "
                <tr>
                    <td>
                        <a class=\"Link btn\" href=\"javascript:void(0)\" 
                           id=\"{$row->LueCode}\" 
                           name=\"{$row->LueIocCode}\" 
                           ianseoDiv=\"{$row->LueDivision}\" 
                           ianseoSCl=\"{$row->LueSubClass}\">
                           {$row->LueCode} ({$row->LueIocCode})
                        </a>
                    </td>
                    <td>{$familyName} {$name}</td>
                    <td>{$row->LueCountry} - {$coShort}</td>
                    <td>{$row->LueDivision}</td>
                    <td>{$row->LueClass}</td>
                    <td>{$row->LueSubClass}</td>
                    <td>
                        <input type=\"hidden\" id=\"fdiv_{$row->LueCode}_{$row->LueIocCode}\" value=\"{$row->LueDivision}\"/>
                        <input type=\"hidden\" id=\"fcl_{$row->LueCode}_{$row->LueIocCode}\" value=\"{$row->LueClass}\"/>
                        <input type=\"hidden\" id=\"fscl_{$row->LueCode}_{$row->LueIocCode}\" value=\"{$row->LueSubClass}\"/>
                    </td>
                </tr>
            ";
        }
        
        $html .= '</tbody></table>';
        $html .= "<div style='padding: 5px; font-style: italic;'>" . $rowCount . " résultat(s) trouvé(s)</div>";
    } else {
        $html = "<div style='padding: 20px; text-align: center; color: #666;'>
                    Aucun athlète trouvé avec les critères de recherche.
                 </div>";
    }
    
    echo $html;
    
} catch (Exception $e) {
    error_log("Erreur dans HtmlFindArchers.php: " . $e->getMessage());
    echo "<div style='color: red; padding: 20px; text-align: center;'>
            Erreur lors de la recherche: " . htmlspecialchars($e->getMessage()) . "
          </div>";
}
?>