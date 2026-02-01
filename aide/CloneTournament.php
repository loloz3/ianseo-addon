<?php
require_once(dirname(__FILE__).'/../../../config.php');
require_once('Common/Fun_FormatText.inc.php');
panicACL();
checkIanseoLicense();

$JS_SCRIPT[] = '<script>
    var dateVar = new Date();
    var offset = dateVar.getTimezoneOffset();
    document.cookie = "offset="+(offset*-1);
</script>';

$JS_SCRIPT[] = '<script>
function validateDates() {
    var from = document.getElementById("new_date_from").value;
    var to = document.getElementById("new_date_to").value;
    
    if (!from || !to) {
        alert("Veuillez sélectionner les deux dates.");
        return false;
    }
    
    if (new Date(from) > new Date(to)) {
        alert("La date de début ne peut pas être après la date de fin.");
        return false;
    }
    
    return true;
}
</script>';

include('Common/Templates/head.php');

echo '<h1>Cloner une compétition</h1>';

// Étape 1 : Sélection de la compétition à cloner
if (!isset($_GET['ToId'])) {
    echo '<h2>Sélectionnez une compétition à cloner</h2>';
    
    // Récupérer la liste des compétitions (même logique que index.php)
    $AuthFiler = array();
    if(AuthModule && !empty($_SESSION['AUTH_ENABLE']) && empty($_SESSION['AUTH_ROOT'])) {
        $compList = array();
        foreach (($_SESSION["AUTH_COMP"] ?? array()) as $comp) {
            if (str_contains($comp, '%')) {
                $AuthFiler[] = 'ToCode LIKE ' . StrSafe_DB($comp);
            } else {
                $compList[] = $comp;
            }
        }
        if (count($compList)) {
            $AuthFiler[] = 'FIND_IN_SET(ToCode, \'' . implode(',', $compList) . '\') != 0 ';
        } else {
            $AuthFiler[] = "ToCode IS NULL ";
        }
    }
    
    $Select = "SELECT ToId,ToType,ToCode,ToName,ToNameShort,ToCommitee,ToComDescr,ToWhere,ToVenue,ToCountry,
                DATE_FORMAT(ToWhenFrom,'" . get_text('DateFmtDB') . "') AS DtFrom,
                DATE_FORMAT(ToWhenTo,'" . get_text('DateFmtDB') . "') AS DtTo,
                ToNumSession, ToTypeName AS TtName, ToNumDist AS TtNumDist, ToIsORIS
                FROM Tournament
                " . (count($AuthFiler) ? 'WHERE ' . implode(' OR ', $AuthFiler) : '') . "
                ORDER BY ToWhenTo DESC, ToWhenFrom DESC, ToCode ASC";
    
    $Rs = safe_r_sql($Select);
    
    if (safe_num_rows($Rs) == 0) {
        echo '<p>Aucune compétition trouvée.</p>';
    } else {
        echo '<table class="Tabella" style="width: 100%;">';
        echo '<tr>
                <th class="Title w-5">&nbsp;</th>
                <th class="Title w-10">'.get_text('TourCode','Tournament').'</th>
                <th class="Title w-20">'.get_text('TourName','Tournament').'</th>
                <th class="Title w-20">'.get_text('TourCommitee','Tournament').'</th>
                <th class="Title w-15">'.get_text('TourWhere','Tournament').'</th>
                <th class="Title w-10">'.get_text('TourType','Tournament').'</th>
                <th class="Title w-10">'.get_text('TourWhen','Tournament').'</th>
                <th class="Title w-10">'.get_text('NumSession', 'Tournament').'</th>
              </tr>';
        
        while ($MyRow = safe_fetch($Rs)) {
            echo '<tr>';
            echo '<td><a class="Button" href="?ToId=' . $MyRow->ToId . '">Sélectionner</a></td>';
            echo '<td>' . $MyRow->ToCode . '</td>';
            echo '<td>' . ManageHTML($MyRow->ToName) . '</td>';
            echo '<td>' . $MyRow->ToCommitee . ' - ' . ManageHTML($MyRow->ToComDescr) . '</td>';
            echo '<td>' . ManageHTML($MyRow->ToWhere) . '</td>';
            echo '<td>' . get_text($MyRow->TtName, 'Tournament') . ', ' . $MyRow->TtNumDist . ' ' . get_text($MyRow->TtNumDist==1?'Distance':'Distances','Tournament').'</td>';
            echo '<td>' . get_text('From','Tournament') . ' ' . $MyRow->DtFrom . ' ' . get_text('To','Tournament') . ' ' . $MyRow->DtTo . '</td>';
            echo '<td>' . $MyRow->ToNumSession . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
}
// Étape 2 : Formulaire pour spécifier les nouvelles dates
elseif (isset($_GET['ToId']) && !isset($_POST['new_date_from'])) {
    $ToId = intval($_GET['ToId']);
    
    // Vérifier les permissions
    $allowed = false;
    if (AuthModule && !empty($_SESSION['AUTH_ENABLE']) && empty($_SESSION['AUTH_ROOT'])) {
        // Récupérer le code de la compétition
        $Select = "SELECT ToCode FROM Tournament WHERE ToId = $ToId";
        $Rs = safe_r_sql($Select);
        if ($MyRow = safe_fetch($Rs)) {
            $tourCode = $MyRow->ToCode;
            // Vérifier si l'utilisateur a accès à cette compétition
            foreach (($_SESSION["AUTH_COMP"] ?? array()) as $comp) {
                if (str_contains($comp, '%')) {
                    $pattern = str_replace('%', '.*', $comp);
                    if (preg_match('/^' . $pattern . '$/', $tourCode)) {
                        $allowed = true;
                        break;
                    }
                } elseif ($comp == $tourCode) {
                    $allowed = true;
                    break;
                }
            }
        }
    } else {
        $allowed = true;
    }
    
    if (!$allowed) {
        echo '<div class="error">Vous n\'avez pas la permission d\'accéder à cette compétition.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    // Récupérer les informations de la compétition (avec les mêmes champs que Main.php)
    $Select = "SELECT *, 
        DATE_FORMAT(ToWhenFrom,'" . get_text('DateFmtDB') . "') AS DtFrom,
        DATE_FORMAT(ToWhenTo,'" . get_text('DateFmtDB') . "') AS DtTo, 
        DATE_FORMAT(ToWhenFrom,'%d') AS DtFromDay,
        DATE_FORMAT(ToWhenFrom,'%m') AS DtFromMonth,
        DATE_FORMAT(ToWhenFrom,'%Y') AS DtFromYear,
        DATE_FORMAT(ToWhenTo,'%d') AS DtToDay,
        DATE_FORMAT(ToWhenTo,'%m') AS DtToMonth,
        DATE_FORMAT(ToWhenTo,'%Y') AS DtToYear, 
        ToTypeName AS TtName,
        ToNumDist AS TtNumDist
        FROM Tournament
        WHERE ToId=" . $ToId;
    
    $Rs = safe_r_sql($Select);
    
    if (safe_num_rows($Rs) != 1) {
        echo '<div class="error">Compétition non trouvée.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    $MyRow = safe_fetch($Rs);
    
    // Récupérer les informations des sessions
    $sessions = array();
    if ($MyRow->ToNumSession > 0) {
        // Se connecter à la base de données pour récupérer les sessions
        $sessionsQuery = "SELECT SesOrder, SesName, SesTar4Session, SesAth4Target 
                         FROM Session 
                         WHERE SesTournament = $ToId 
                         ORDER BY SesOrder";
        $RsSessions = safe_r_sql($sessionsQuery);
        while ($session = safe_fetch($RsSessions)) {
            $sessions[] = $session;
        }
    }
    
    // Récupérer les informations des officiels
    $officials = array();
    $SelectOfficials = "SELECT TiCode, TiName, TiGivenName, CoCode, ItDescription
            FROM TournamentInvolved AS ti 
            LEFT JOIN InvolvedType AS it ON ti.TiType=it.ItId 
            LEFT JOIN Countries on CoId=TiCountry and CoTournament=TiTournament
            WHERE ti.TiTournament = $ToId 
            ORDER BY ti.TiType ASC, ti.TiName ASC";
    $RsOfficials = safe_r_sql($SelectOfficials);
    while ($official = safe_fetch($RsOfficials)) {
        $officials[] = $official;
    }
    
    // Récupérer la liste des pays
    $Countries = get_Countries();
    
    // Générer un code selon le format spécifié
    // Format : F + année (2 chiffres) + initiale ville (majuscule) + type (S/E) + 3 chiffres aléatoires
    $defaultCode = 'F';
    
    // Ajouter les deux derniers chiffres de l'année de la nouvelle date (on utilisera l'année courante par défaut)
    $currentYear = date('Y');
    $defaultCode .= substr($currentYear, -2);
    
    // Ajouter la première lettre de la ville (en majuscule)
    $city = $MyRow->ToVenue; // Variable contenant le nom de la ville
    if ($city && strlen($city) > 0) {
        // Prendre le premier caractère et le mettre en majuscule
        $firstLetter = strtoupper(substr($city, 0, 1));
        
        // Vérifier que c'est bien une lettre (pas un chiffre)
        if (!preg_match('/[A-Z]/', $firstLetter)) {
            // Si ce n'est pas une lettre, trouver la première lettre dans le nom
            for ($i = 0; $i < strlen($city); $i++) {
                $letter = strtoupper(substr($city, $i, 1));
                if (preg_match('/[A-Z]/', $letter)) {
                    $firstLetter = $letter;
                    break;
                }
            }
        }
        $defaultCode .= $firstLetter;
    } else {
        // Si pas de ville, utiliser X comme placeholder
        $defaultCode .= 'X';
    }
    
    // Ajouter le type : S pour indoor, E pour extérieur
    // Vérifier si le type contient "indoor" (insensible à la casse)
    $typeName = isset($MyRow->ToTypeName) ? strtolower($MyRow->ToTypeName) : '';
    $isIndoor = (strpos($typeName, 'indoor') !== false);
    $defaultCode .= $isIndoor ? 'S' : 'E';
    
    // Ajouter 3 chiffres aléatoires
    $defaultCode .= sprintf('%03d', mt_rand(0, 999));
    
    // Vérifier si ce code existe déjà
    $suffix = 1;
    $originalCode = $defaultCode;
    
    while (true) {
        $Check = "SELECT COUNT(*) as count FROM Tournament WHERE ToCode = " . StrSafe_DB($defaultCode);
        $RsCheck = safe_r_sql($Check);
        $RowCheck = safe_fetch($RsCheck);
        
        if ($RowCheck->count == 0) {
            break; // Code disponible
        }
        
        // Si le code existe déjà, générer un nouveau avec des chiffres différents
        $defaultCode = substr($originalCode, 0, 5); // Garder F + année + ville + type
        $defaultCode .= sprintf('%03d', mt_rand(0, 999));
        
        // Sécurité pour éviter une boucle infinie
        if ($suffix > 20) {
            // Si on a essayé 20 fois sans succès, utiliser un suffixe
            $defaultCode = substr($originalCode, 0, 5) . sprintf('%03d', $suffix);
        }
        
        $suffix++;
        
        // Sécurité supplémentaire
        if ($suffix > 100) {
            // Fallback: utiliser un code simple
            $defaultCode = 'F' . date('ym') . 'X' . ($isIndoor ? 'S' : 'E') . sprintf('%03d', mt_rand(0, 999));
            break;
        }
    }
    
    // Afficher les informations de la compétition comme dans Main.php
    echo '<h2>Informations de la compétition à cloner</h2>';
    
    echo '<table class="Tabella" style="width: 100%; margin-bottom: 20px;">
        <tr><th class="Title" colspan="2">' . $MyRow->ToName . '</th></tr>
        <tr class="Divider"><td colspan="2"></td></tr>
        <tr><td class="Title" colspan="2">' . get_text('TourMainInfo', 'Tournament') . '</td></tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourCode','Tournament') . '</th>
            <td class="Bold">' . $MyRow->ToCode . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourName','Tournament') . '</th>
            <td class="Bold">' . $MyRow->ToName . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourShortName','Tournament') . '</th>
            <td class="Bold">' . $MyRow->ToNameShort . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourCommitee','Tournament') . '</th>
            <td>' . $MyRow->ToCommitee . ' - ' . $MyRow->ToComDescr . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourType','Tournament') . '</th>
            <td>' . get_text($MyRow->TtName, 'Tournament') . ', ' . $MyRow->TtNumDist . ' ' . get_text($MyRow->TtNumDist==1?'Distance':'Distances','Tournament') . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourIsOris','Tournament') . '</th>
            <td>' . get_text($MyRow->ToIsORIS ? 'Yes' : 'No') . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourWhere','Tournament') . '</th>
            <td>' . $MyRow->ToWhere . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('CompVenue','Tournament') . '</th>
            <td>' . $MyRow->ToVenue . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('Natl-Nation','Tournament') . '</th>
            <td>' . ($MyRow->ToCountry ? $MyRow->ToCountry . ' - ' . $Countries[$MyRow->ToCountry] : $MyRow->ToCountry) . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('TourWhen','Tournament') . '</th>
            <td>' . get_text('From','Tournament') . ' ' . $MyRow->DtFrom . '<br>' . get_text('To','Tournament') . '&nbsp;&nbsp;&nbsp;' . $MyRow->DtTo . '</td>
        </tr>
        <tr>
            <th class="TitleLeft w-15">' . get_text('NumSession', 'Tournament') . '</th>
            <td>' . $MyRow->ToNumSession . '</td>
        </tr>';
    
    // Affichage des sessions
    echo '<tr>
            <th class="TitleLeft w-15">' . get_text('SessionDescr', 'Tournament') . '</th>
            <td>';
    if ($MyRow->ToNumSession > 0) {
        foreach ($sessions as $s) {
            echo get_text('Session') . ' ' . $s->SesOrder . ': ' . $s->SesName . ' --> ' . $s->SesTar4Session . ' ' . get_text('Targets', 'Tournament') . ', ' . $s->SesAth4Target . ' ' . get_text('Ath4Target', 'Tournament')  . '<br>';
        }
    } else {
        echo get_text('NoSession','Tournament');
    }
    echo '</td></tr>';
    
    // Affichage des officiels
    echo '<tr>
            <th class="TitleLeft w-15">' . get_text('StaffOnField','Tournament') . '</th>
            <td>';
    if (count($officials) > 0) {
        foreach ($officials as $Row) {
            echo (empty($Row->TiCode) ? '' : $Row->TiCode . '&nbsp;-&nbsp;') .
                $Row->TiName . ' ' . $Row->TiGivenName . (is_null($Row->CoCode) ? '' : ' (' . $Row->CoCode . ')') .
                (empty($Row->ItDescription) ? '' : ', ' . get_text($Row->ItDescription,'Tournament')) . '<br>';
        }
    } else {
        echo get_text('NoStaffOnField','Tournament');
    }
    echo '</td></tr>';
    
    echo '</table>';
    
    echo '<hr>';
    echo '<h2>Paramètres du clonage</h2>';
    echo '<p>Veuillez spécifier les nouvelles informations pour la compétition clonée :</p>';
      
    echo '<form method="POST" action="" onsubmit="return validateDates()">';
    echo '<input type="hidden" name="ToId" value="' . $ToId . '">';
    
    echo '<table class="Tabella" style="width: auto;">';
    echo '<tr>';
    echo '<th colspan="2" class="Title">Définir les nouvelles informations</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_date_from">Nouvelle date de début :</label></td>';
    echo '<td><input type="date" id="new_date_from" name="new_date_from" required></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_date_to">Nouvelle date de fin :</label></td>';
    echo '<td><input type="date" id="new_date_to" name="new_date_to" required></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_code">Nouveau code de compétition :</label></td>';
    echo '<td><input type="text" id="new_code" name="new_code" value="' . $defaultCode . '" required maxlength="8"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_name">Nouveau nom de compétition :</label></td>';
    echo '<td><input type="text" id="new_name" name="new_name" value="' . htmlspecialchars($MyRow->ToName) . '" required style="width: 300px;"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_name_short">Nouveau nom court :</label></td>';
    echo '<td><input type="text" id="new_name_short" name="new_name_short" value="' . htmlspecialchars($MyRow->ToNameShort) . '" style="width: 200px;"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_where">Nouveau lieu :</label></td>';
    echo '<td><input type="text" id="new_where" name="new_where" value="' . htmlspecialchars($MyRow->ToWhere) . '" style="width: 300px;"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_venue">Nouveau site :</label></td>';
    echo '<td><input type="text" id="new_venue" name="new_venue" value="' . htmlspecialchars($MyRow->ToVenue) . '" style="width: 300px;"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="2" class="Center">';
    echo '<input type="submit" value="Cloner la compétition" class="Button">';
    echo ' <a href="?" class="Button">Annuler</a>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    
    // Script pour limiter les dates minimum
    echo '<script>
        document.getElementById("new_date_from").min = new Date().toISOString().split("T")[0];
        document.getElementById("new_date_to").min = new Date().toISOString().split("T")[0];
        
        // Synchroniser la date de fin avec la date de début
        document.getElementById("new_date_from").addEventListener("change", function() {
            var endDate = document.getElementById("new_date_to");
            if (!endDate.value || new Date(this.value) > new Date(endDate.value)) {
                endDate.value = this.value;
            }
            endDate.min = this.value;
        });
        
        // Mettre à jour le code selon le format quand la date ou la ville change
        function updateDefaultCode() {
            var dateFrom = document.getElementById("new_date_from").value;
            var venue = document.getElementById("new_venue").value;
            var typeName = "' . (isset($MyRow->ToTypeName) ? $MyRow->ToTypeName : '') . '";
            var isIndoor = (typeName.toLowerCase().indexOf(\'indoor\') !== -1);
            
            // Format : F + année (2 chiffres) + initiale ville (majuscule) + type (S/E) + 3 chiffres aléatoires
            var code = "F";
            
            // Année : prendre l\'année de la date de début si disponible, sinon année courante
            if (dateFrom) {
                var year = new Date(dateFrom).getFullYear();
                code += year.toString().slice(-2);
            } else {
                code += new Date().getFullYear().toString().slice(-2);
            }
            
            // Ville : première lettre en majuscule
            if (venue && venue.length > 0) {
                var firstLetter = venue.charAt(0).toUpperCase();
                if (!/[A-Z]/.test(firstLetter)) {
                    for (var i = 0; i < venue.length; i++) {
                        var letter = venue.charAt(i).toUpperCase();
                        if (/[A-Z]/.test(letter)) {
                            firstLetter = letter;
                            break;
                        }
                    }
                }
                code += firstLetter;
            } else {
                code += "X";
            }
            
            // Type : S pour indoor, E pour extérieur
            code += isIndoor ? "S" : "E";
            
            // 3 chiffres aléatoires
            code += Math.floor(Math.random() * 1000).toString().padStart(3, "0");
            
            // Mettre à jour le champ
            document.getElementById("new_code").value = code;
        }
        
        // Attacher les événements
        document.getElementById("new_date_from").addEventListener("change", updateDefaultCode);
        document.getElementById("new_venue").addEventListener("input", updateDefaultCode);
        
        // Initialiser le code
        updateDefaultCode();
    </script>';
}
// Étape 3 : Traitement du clonage
elseif (isset($_POST['ToId'], $_POST['new_date_from'], $_POST['new_date_to'])) {
    $ToId = intval($_POST['ToId']);
    $newDateFrom = $_POST['new_date_from'];
    $newDateTo = $_POST['new_date_to'];
    $newCode = isset($_POST['new_code']) ? trim($_POST['new_code']) : '';
    $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
    $newNameShort = isset($_POST['new_name_short']) ? trim($_POST['new_name_short']) : '';
    $newWhere = isset($_POST['new_where']) ? trim($_POST['new_where']) : '';
    $newVenue = isset($_POST['new_venue']) ? trim($_POST['new_venue']) : '';
    
    // Calculer le décalage de jours entre l'ancienne et la nouvelle date
    $oldDateFrom = '';
    $SelectOldDate = "SELECT ToWhenFrom FROM Tournament WHERE ToId = $ToId";
    $RsOldDate = safe_r_sql($SelectOldDate);
    if ($RowOldDate = safe_fetch($RsOldDate)) {
        $oldDateFrom = $RowOldDate->ToWhenFrom;
    }
    
    // Vérifier les permissions
    $allowed = false;
    if (AuthModule && !empty($_SESSION['AUTH_ENABLE']) && empty($_SESSION['AUTH_ROOT'])) {
        $Select = "SELECT ToCode FROM Tournament WHERE ToId = $ToId";
        $Rs = safe_r_sql($Select);
        if ($MyRow = safe_fetch($Rs)) {
            $tourCode = $MyRow->ToCode;
            foreach (($_SESSION["AUTH_COMP"] ?? array()) as $comp) {
                if (str_contains($comp, '%')) {
                    $pattern = str_replace('%', '.*', $comp);
                    if (preg_match('/^' . $pattern . '$/', $tourCode)) {
                        $allowed = true;
                        break;
                    }
                } elseif ($comp == $tourCode) {
                    $allowed = true;
                    break;
                }
            }
        }
    } else {
        $allowed = true;
    }
    
    if (!$allowed) {
        echo '<div class="error">Vous n\'avez pas la permission de cloner cette compétition.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    // Valider la longueur du code (max 8 caractères)
    if (strlen($newCode) > 8) {
        echo '<div class="error">Le code de compétition ne peut pas dépasser 8 caractères.</div>';
        echo '<p><a href="?ToId=' . $ToId . '" class="Button">Retour</a></p>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    // Vérifier si le code existe déjà
    if ($newCode) {
        $Check = "SELECT COUNT(*) as count FROM Tournament WHERE ToCode = " . StrSafe_DB($newCode);
        $RsCheck = safe_r_sql($Check);
        $RowCheck = safe_fetch($RsCheck);
        if ($RowCheck->count > 0) {
            echo '<div class="error">Ce code de compétition existe déjà. Veuillez en choisir un autre.</div>';
            echo '<p><a href="?ToId=' . $ToId . '" class="Button">Retour</a></p>';
            include('Common/Templates/tail.php');
            exit;
        }
    }
    
    // Récupérer la compétition originale
    $Select = "SELECT * FROM Tournament WHERE ToId = $ToId";
    $Rs = safe_r_sql($Select);
    
    if (safe_num_rows($Rs) != 1) {
        echo '<div class="error">Compétition non trouvée.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    $MyRow = safe_fetch($Rs);
    
    // Si aucun code n'a été fourni, en générer un selon le format spécifié
    if (empty($newCode)) {
        // Format : F + année (2 chiffres) + initiale ville (majuscule) + type (S/E) + 3 chiffres aléatoires
        $defaultCode = 'F';
        
        // Prendre l'année de la nouvelle date
        if ($newDateFrom) {
            $year = date('Y', strtotime($newDateFrom));
        } else {
            $year = date('Y');
        }
        $defaultCode .= substr($year, -2);
        
        // Prendre la première lettre de la ville
        $city = $newVenue ?: $MyRow->ToVenue;
        if ($city && strlen($city) > 0) {
            $firstLetter = strtoupper(substr($city, 0, 1));
            if (!preg_match('/[A-Z]/', $firstLetter)) {
                for ($i = 0; $i < strlen($city); $i++) {
                    $letter = strtoupper(substr($city, $i, 1));
                    if (preg_match('/[A-Z]/', $letter)) {
                        $firstLetter = $letter;
                        break;
                    }
                }
            }
            $defaultCode .= $firstLetter;
        } else {
            $defaultCode .= 'X';
        }
        
        // Type : S pour indoor, E pour extérieur
        // Vérifier si le type contient "indoor" (insensible à la casse)
        $typeName = isset($MyRow->ToTypeName) ? strtolower($MyRow->ToTypeName) : '';
        $isIndoor = (strpos($typeName, 'indoor') !== false);
        $defaultCode .= $isIndoor ? 'S' : 'E';
        
        // 3 chiffres aléatoires
        $defaultCode .= sprintf('%03d', mt_rand(0, 999));
        
        $newCode = $defaultCode;
        
        // Vérifier si ce code existe déjà
        $suffix = 1;
        $originalCode = $newCode;
        
        while (true) {
            $Check = "SELECT COUNT(*) as count FROM Tournament WHERE ToCode = " . StrSafe_DB($newCode);
            $RsCheck = safe_r_sql($Check);
            $RowCheck = safe_fetch($RsCheck);
            
            if ($RowCheck->count == 0) {
                break;
            }
            
            // Générer un nouveau code avec des chiffres différents
            $newCode = substr($originalCode, 0, 5);
            $newCode .= sprintf('%03d', mt_rand(0, 999));
            
            if ($suffix > 20) {
                $newCode = substr($originalCode, 0, 5) . sprintf('%03d', $suffix);
            }
            
            $suffix++;
            
            if ($suffix > 100) {
                $newCode = 'F' . date('ym') . 'X' . ($isIndoor ? 'S' : 'E') . sprintf('%03d', mt_rand(0, 999));
                break;
            }
        }
    }
    
    // Utiliser les valeurs fournies ou les valeurs par défaut
    $finalName = $newName ?: $MyRow->ToName . ' (Copie)';
    $finalNameShort = $newNameShort ?: $MyRow->ToNameShort;
    $finalWhere = $newWhere ?: $MyRow->ToWhere;
    $finalVenue = $newVenue ?: $MyRow->ToVenue;
    
    // Cloner la compétition
    $Insert = "INSERT INTO Tournament (
                ToOnlineId, ToType, ToCode, ToIocCode, ToTimeZone, ToName, ToNameShort,
                ToCommitee, ToComDescr, ToWhere, ToVenue, ToCountry, ToWhenFrom, ToWhenTo,
                ToIntEvent, ToCurrency, ToPrintLang, ToPrintChars, ToPrintPaper, ToImpFin,
                ToImgL, ToImgR, ToImgB, ToImgB2, ToNumSession, ToIndFinVxA, ToTeamFinVxA,
                ToDbVersion, ToBlock, ToUseHHT, ToLocRule, ToTypeName, ToTypeSubRule,
                ToNumDist, ToNumEnds, ToMaxDistScore, ToMaxFinIndScore, ToMaxFinTeamScore,
                ToCategory, ToElabTeam, ToElimination, ToGolds, ToXNine, ToTieBreaker3,
                ToGoldsChars, ToXNineChars, ToTieBreaker3Chars, ToDouble, ToCollation,
                ToIsORIS, ToOptions, ToRecCode
            ) VALUES (
                " . intval($MyRow->ToOnlineId) . ",
                " . intval($MyRow->ToType) . ",
                " . StrSafe_DB($newCode) . ",
                " . StrSafe_DB($MyRow->ToIocCode) . ",
                " . StrSafe_DB($MyRow->ToTimeZone) . ",
                " . StrSafe_DB($finalName) . ",
                " . StrSafe_DB($finalNameShort) . ",
                " . StrSafe_DB($MyRow->ToCommitee) . ",
                " . StrSafe_DB($MyRow->ToComDescr) . ",
                " . StrSafe_DB($finalWhere) . ",
                " . StrSafe_DB($finalVenue) . ",
                " . StrSafe_DB($MyRow->ToCountry) . ",
                '" . $newDateFrom . "',
                '" . $newDateTo . "',
                " . intval($MyRow->ToIntEvent) . ",
                " . StrSafe_DB($MyRow->ToCurrency) . ",
                " . StrSafe_DB($MyRow->ToPrintLang) . ",
                " . intval($MyRow->ToPrintChars) . ",
                " . intval($MyRow->ToPrintPaper) . ",
                " . intval($MyRow->ToImpFin) . ",
                " . StrSafe_DB($MyRow->ToImgL) . ",
                " . StrSafe_DB($MyRow->ToImgR) . ",
                " . StrSafe_DB($MyRow->ToImgB) . ",
                " . StrSafe_DB($MyRow->ToImgB2) . ",
                " . intval($MyRow->ToNumSession) . ",
                " . intval($MyRow->ToIndFinVxA) . ",
                " . intval($MyRow->ToTeamFinVxA) . ",
                '" . date('Y-m-d H:i:s') . "',
                " . intval($MyRow->ToBlock) . ",
                " . intval($MyRow->ToUseHHT) . ",
                " . StrSafe_DB($MyRow->ToLocRule) . ",
                " . StrSafe_DB($MyRow->ToTypeName) . ",
                " . StrSafe_DB($MyRow->ToTypeSubRule) . ",
                " . intval($MyRow->ToNumDist) . ",
                " . intval($MyRow->ToNumEnds) . ",
                " . intval($MyRow->ToMaxDistScore) . ",
                " . intval($MyRow->ToMaxFinIndScore) . ",
                " . intval($MyRow->ToMaxFinTeamScore) . ",
                " . intval($MyRow->ToCategory) . ",
                " . intval($MyRow->ToElabTeam) . ",
                " . intval($MyRow->ToElimination) . ",
                " . StrSafe_DB($MyRow->ToGolds) . ",
                " . StrSafe_DB($MyRow->ToXNine) . ",
                " . StrSafe_DB($MyRow->ToTieBreaker3) . ",
                " . StrSafe_DB($MyRow->ToGoldsChars) . ",
                " . StrSafe_DB($MyRow->ToXNineChars) . ",
                " . StrSafe_DB($MyRow->ToTieBreaker3Chars) . ",
                " . intval($MyRow->ToDouble) . ",
                " . StrSafe_DB($MyRow->ToCollation) . ",
                " . StrSafe_DB($MyRow->ToIsORIS) . ",
                " . StrSafe_DB($MyRow->ToOptions) . ",
                " . StrSafe_DB($MyRow->ToRecCode) . "
            )";
    
    // Exécuter la requête
    safe_w_sql($Insert);
    $newToId = safe_w_Last_Id();
    
    if ($newToId) {
        echo '<div class="success">';
        echo '<h3>Compétition clonée avec succès !</h3>';
        
        // Afficher les informations clonées
        echo '<h4>Informations de la nouvelle compétition :</h4>';
        echo '<table class="Tabella" style="width: 100%; margin-bottom: 20px;">
            <tr><th class="Title" colspan="2">' . htmlspecialchars($finalName) . '</th></tr>
            <tr class="Divider"><td colspan="2"></td></tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourCode','Tournament') . '</th>
                <td class="Bold">' . htmlspecialchars($newCode) . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourName','Tournament') . '</th>
                <td class="Bold">' . htmlspecialchars($finalName) . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourShortName','Tournament') . '</th>
                <td class="Bold">' . htmlspecialchars($finalNameShort) . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourCommitee','Tournament') . '</th>
                <td>' . $MyRow->ToCommitee . ' - ' . $MyRow->ToComDescr . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourType','Tournament') . '</th>
                <td>' . get_text($MyRow->ToTypeName, 'Tournament') . ', ' . $MyRow->ToNumDist . ' ' . get_text($MyRow->ToNumDist==1?'Distance':'Distances','Tournament') . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourIsOris','Tournament') . '</th>
                <td>' . get_text($MyRow->ToIsORIS ? 'Yes' : 'No') . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourWhere','Tournament') . '</th>
                <td>' . htmlspecialchars($finalWhere) . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('CompVenue','Tournament') . '</th>
                <td>' . htmlspecialchars($finalVenue) . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('TourWhen','Tournament') . '</th>
                <td>' . get_text('From','Tournament') . ' ' . $newDateFrom . '<br>' . get_text('To','Tournament') . '&nbsp;&nbsp;&nbsp;' . $newDateTo . '</td>
            </tr>
            <tr>
                <th class="TitleLeft w-15">' . get_text('NumSession', 'Tournament') . '</th>
                <td>' . $MyRow->ToNumSession . '</td>
            </tr>
        </table>';
        
        // Calculer le décalage de jours pour ajuster les dates du planificateur
        $dateOffset = 0;
        if ($oldDateFrom && $newDateFrom) {
            $oldTimestamp = strtotime($oldDateFrom);
            $newTimestamp = strtotime($newDateFrom);
            $dateOffset = ($newTimestamp - $oldTimestamp) / (60 * 60 * 24); // Différence en jours
        }
        
        // 1. Cloner les événements liés à la compétition
        $CloneEvents = "
            INSERT INTO Events 
            (EvCode, EvTeamEvent, EvTournament, EvEventName, EvProgr, EvShootOff, 
             EvE1ShootOff, EvE2ShootOff, EvSession, EvPrint, EvQualPrintHead, 
             EvQualLastUpdate, EvFinalFirstPhase, EvWinnerFinalRank, EvNumQualified, 
             EvFirstQualified, EvFinalPrintHead, EvFinalLastUpdate, EvFinalTargetType, 
             EvGolds, EvXNine, EvTieBreaker3, EvGoldsChars, EvXNineChars, EvTieBreaker3Chars, 
             EvCheckGolds, EvCheckXNines, EvCheckTieBreaker3, EvTargetSize, EvDistance, 
             EvFinalAthTarget, EvMatchMultipleMatches, EvElimType, EvElim1, EvE1Ends, 
             EvE1Arrows, EvE1SO, EvElim2, EvE2Ends, EvE2Arrows, EvE2SO, EvPartialTeam, 
             EvMultiTeam, EvMultiTeamNo, EvMixedTeam, EvTeamCreationMode, EvMaxTeamPerson, 
             EvRunning, EvMatchMode, EvMatchArrowsNo, EvElimEnds, EvElimArrows, EvElimSO, 
             EvFinEnds, EvFinArrows, EvFinSO, EvRecCategory, EvWaCategory, EvMedals, 
             EvTourRules, EvCodeParent, EvCodeParentWinnerBranch, EvOdfCode, EvOdfGender, 
             EvIsPara, EvArrowPenalty, EvLoopPenalty, EvLockResults, EvQualDistances, 
             EvLuckyDogDistance, EvSoDistance, EvTiePositionSO, EvQualBestOfDistances)
            SELECT 
                EvCode, EvTeamEvent, $newToId, EvEventName, EvProgr, EvShootOff, 
                EvE1ShootOff, EvE2ShootOff, EvSession, EvPrint, EvQualPrintHead, 
                EvQualLastUpdate, EvFinalFirstPhase, EvWinnerFinalRank, EvNumQualified, 
                EvFirstQualified, EvFinalPrintHead, EvFinalLastUpdate, EvFinalTargetType, 
                EvGolds, EvXNine, EvTieBreaker3, EvGoldsChars, EvXNineChars, EvTieBreaker3Chars, 
                EvCheckGolds, EvCheckXNines, EvCheckTieBreaker3, EvTargetSize, EvDistance, 
                EvFinalAthTarget, EvMatchMultipleMatches, EvElimType, EvElim1, EvE1Ends, 
                EvE1Arrows, EvE1SO, EvElim2, EvE2Ends, EvE2Arrows, EvE2SO, EvPartialTeam, 
                EvMultiTeam, EvMultiTeamNo, EvMixedTeam, EvTeamCreationMode, EvMaxTeamPerson, 
                EvRunning, EvMatchMode, EvMatchArrowsNo, EvElimEnds, EvElimArrows, EvElimSO, 
                EvFinEnds, EvFinArrows, EvFinSO, EvRecCategory, EvWaCategory, EvMedals, 
                EvTourRules, EvCodeParent, EvCodeParentWinnerBranch, EvOdfCode, EvOdfGender, 
                EvIsPara, EvArrowPenalty, EvLoopPenalty, EvLockResults, EvQualDistances, 
                EvLuckyDogDistance, EvSoDistance, EvTiePositionSO, EvQualBestOfDistances
            FROM Events 
            WHERE EvTournament = $ToId
        ";
        
        safe_w_sql($CloneEvents);
        $eventsCloned = safe_w_affected_rows();
        
        // 2. Cloner les classes liées à la compétition
        $CloneClasses = "
            INSERT INTO Classes 
            (ClId, ClTournament, ClDescription, ClViewOrder, ClAgeFrom, ClAgeTo, 
             ClValidClass, ClSex, ClAthlete, ClDivisionsAllowed, ClRecClass, 
             ClWaClass, ClTourRules, ClIsPara)
            SELECT 
                ClId, $newToId, ClDescription, ClViewOrder, ClAgeFrom, ClAgeTo, 
                ClValidClass, ClSex, ClAthlete, ClDivisionsAllowed, ClRecClass, 
                ClWaClass, ClTourRules, ClIsPara
            FROM Classes 
            WHERE ClTournament = $ToId
        ";
        
        safe_w_sql($CloneClasses);
        $classesCloned = safe_w_affected_rows();
        
        // 3. Cloner les divisions liées à la compétition
        $CloneDivisions = "
            INSERT INTO Divisions 
            (DivId, DivTournament, DivDescription, DivViewOrder, DivAthlete, 
             DivRecDivision, DivWaDivision, DivTourRules, DivIsPara)
            SELECT 
                DivId, $newToId, DivDescription, DivViewOrder, DivAthlete, 
                DivRecDivision, DivWaDivision, DivTourRules, DivIsPara
            FROM Divisions 
            WHERE DivTournament = $ToId
        ";
        
        safe_w_sql($CloneDivisions);
        $divisionsCloned = safe_w_affected_rows();
        
        // 4. Cloner les informations de distance liées à la compétition
        $CloneDistanceInfo = "
            INSERT INTO DistanceInformation 
            (DiTournament, DiSession, DiDistance, DiEnds, DiArrows, DiMaxpoints, 
             DiOptions, DiType, DiDay, DiWarmStart, DiWarmDuration, DiStart, 
             DiDuration, DiShift, DiTargets, DiTourRules, DiScoringEnds, DiScoringOffset)
            SELECT 
                $newToId, DiSession, DiDistance, DiEnds, DiArrows, DiMaxpoints, 
                DiOptions, DiType, 
                DATE_ADD(DiDay, INTERVAL $dateOffset DAY), 
                DiWarmStart, DiWarmDuration, DiStart, 
                DiDuration, DiShift, DiTargets, DiTourRules, DiScoringEnds, DiScoringOffset
            FROM DistanceInformation 
            WHERE DiTournament = $ToId
        ";
        
        safe_w_sql($CloneDistanceInfo);
        $distanceInfoCloned = safe_w_affected_rows();
        
        // 5. Cloner les associations événement-classe (EventClass)
        $CloneEventClass = "
            INSERT INTO EventClass 
            (EcCode, EcTeamEvent, EcTournament, EcClass, EcDivision, EcSubClass, 
             EcExtraAddons, EcNumber, EcTourRules)
            SELECT 
                EcCode, EcTeamEvent, $newToId, EcClass, EcDivision, EcSubClass, 
                EcExtraAddons, EcNumber, EcTourRules
            FROM EventClass 
            WHERE EcTournament = $ToId
        ";
        
        safe_w_sql($CloneEventClass);
        $eventClassCloned = safe_w_affected_rows();
        
        // 6. Cloner les sessions liées à la compétition
        $CloneSessions = "
            INSERT INTO Session 
            (SesTournament, SesOrder, SesType, SesName, SesTar4Session, 
             SesAth4Target, SesFirstTarget, SesFollow, SesStatus, SesDtStart, 
             SesDtEnd, SesOdfCode, SesOdfPeriod, SesOdfVenue, SesOdfLocation, 
             SesLocation, SesEvents)
            SELECT 
                $newToId, SesOrder, SesType, SesName, SesTar4Session, 
                SesAth4Target, SesFirstTarget, SesFollow, SesStatus, 
                DATE_ADD(SesDtStart, INTERVAL $dateOffset DAY), 
                DATE_ADD(SesDtEnd, INTERVAL $dateOffset DAY), 
                SesOdfCode, SesOdfPeriod, SesOdfVenue, SesOdfLocation, 
                SesLocation, SesEvents
            FROM Session 
            WHERE SesTournament = $ToId
        ";
        
        safe_w_sql($CloneSessions);
        $sessionsCloned = safe_w_affected_rows();
        
        // 7. Cloner les faces de cible (TargetFaces)
        $CloneTargetFaces = "
            INSERT INTO TargetFaces 
            (TfId, TfName, TfTournament, TfClasses, TfRegExp, TfGolds, TfXNine, 
             TfTieBreaker3, TfGoldsChars, TfXNineChars, TfTieBreaker3Chars, 
             TfT1, TfW1, TfT2, TfW2, TfT3, TfW3, TfT4, TfW4, TfT5, TfW5, 
             TfT6, TfW6, TfT7, TfW7, TfT8, TfW8, TfDefault, TfTourRules, 
             TfWaTarget, TfGoldsChars1, TfXNineChars1, TfTieBreaker3Chars1, 
             TfGoldsChars2, TfXNineChars2, TfTieBreaker3Chars2, TfGoldsChars3, 
             TfXNineChars3, TfTieBreaker3Chars3, TfGoldsChars4, TfXNineChars4, 
             TfTieBreaker3Chars4, TfGoldsChars5, TfXNineChars5, TfTieBreaker3Chars5, 
             TfGoldsChars6, TfXNineChars6, TfTieBreaker3Chars6, TfGoldsChars7, 
             TfXNineChars7, TfTieBreaker3Chars7, TfGoldsChars8, TfXNineChars8, 
             TfTieBreaker3Chars8)
            SELECT 
                TfId, TfName, $newToId, TfClasses, TfRegExp, TfGolds, TfXNine, 
                TfTieBreaker3, TfGoldsChars, TfXNineChars, TfTieBreaker3Chars, 
                TfT1, TfW1, TfT2, TfW2, TfT3, TfW3, TfT4, TfW4, TfT5, TfW5, 
                TfT6, TfW6, TfT7, TfW7, TfT8, TfW8, TfDefault, TfTourRules, 
                TfWaTarget, TfGoldsChars1, TfXNineChars1, TfTieBreaker3Chars1, 
                TfGoldsChars2, TfXNineChars2, TfTieBreaker3Chars2, TfGoldsChars3, 
                TfXNineChars3, TfTieBreaker3Chars3, TfGoldsChars4, TfXNineChars4, 
                TfTieBreaker3Chars4, TfGoldsChars5, TfXNineChars5, TfTieBreaker3Chars5, 
                TfGoldsChars6, TfXNineChars6, TfTieBreaker3Chars6, TfGoldsChars7, 
                TfXNineChars7, TfTieBreaker3Chars7, TfGoldsChars8, TfXNineChars8, 
                TfTieBreaker3Chars8
            FROM TargetFaces 
            WHERE TfTournament = $ToId
        ";
        
        safe_w_sql($CloneTargetFaces);
        $targetFacesCloned = safe_w_affected_rows();
        
        // 8. Cloner les distances de la compétition (TournamentDistances)
        $CloneTournamentDistances = "
            INSERT INTO TournamentDistances 
            (TdClasses, TdType, TdTournament, Td1, Td2, Td3, Td4, Td5, Td6, 
             Td7, Td8, TdTourRules, TdDist1, TdDist2, TdDist3, TdDist4, 
             TdDist5, TdDist6, TdDist7, TdDist8)
            SELECT 
                TdClasses, TdType, $newToId, Td1, Td2, Td3, Td4, Td5, Td6, 
                Td7, Td8, TdTourRules, TdDist1, TdDist2, TdDist3, TdDist4, 
                TdDist5, TdDist6, TdDist7, TdDist8
            FROM TournamentDistances 
            WHERE TdTournament = $ToId
        ";
        
        safe_w_sql($CloneTournamentDistances);
        $tournamentDistancesCloned = safe_w_affected_rows();
        
        // 9. Cloner les personnes impliquées (TournamentInvolved)
        $CloneTournamentInvolved = "
            INSERT INTO TournamentInvolved 
            (TiTournament, TiType, TiCode, TiCodeLocal, TiName, TiGivenName, 
             TiCountry, TiGender, TiTimeStamp)
            SELECT 
                $newToId, TiType, TiCode, TiCodeLocal, TiName, TiGivenName, 
                TiCountry, TiGender, '" . date('Y-m-d H:i:s') . "'
            FROM TournamentInvolved 
            WHERE TiTournament = $ToId
        ";
        
        safe_w_sql($CloneTournamentInvolved);
        $tournamentInvolvedCloned = safe_w_affected_rows();
        
        // 10. Cloner le planificateur (Scheduler) avec ajustement des dates
        $CloneScheduler = "
            INSERT INTO Scheduler 
            (SchTournament, SchOrder, SchDateStart, SchDateEnd, SchSesOrder, 
             SchSesType, SchDescr, SchDay, SchStart, SchDuration, SchTitle, 
             SchSubTitle, SchText, SchShift, SchTargets, SchLink, SchLocation)
            SELECT 
                $newToId, SchOrder, 
                DATE_ADD(SchDateStart, INTERVAL $dateOffset DAY), 
                DATE_ADD(SchDateEnd, INTERVAL $dateOffset DAY), 
                SchSesOrder, SchSesType, SchDescr, 
                DATE_ADD(SchDay, INTERVAL $dateOffset DAY), 
                SchStart, SchDuration, SchTitle, 
                SchSubTitle, SchText, SchShift, SchTargets, SchLink, SchLocation
            FROM Scheduler 
            WHERE SchTournament = $ToId
        ";
        
        safe_w_sql($CloneScheduler);
        $schedulerCloned = safe_w_affected_rows();
        
        // 11. Cloner les paramètres TV (TVParams)
        $CloneTVParams = "
            INSERT INTO TVParams 
            (TVPId, TVPTournament, TVPTimeStop, TVPTimeScroll, TVPNumRows, 
             TVMaxPage, TVPSession, TVPViewNationName, TVPNameComplete, 
             TVPViewTeamComponents, TVPEventInd, TVPEventTeam, TVPPhasesInd, 
             TVPPhasesTeam, TVPColumns, TVPPage, TVPDefault, TVP_TR_BGColor, 
             TVP_TRNext_BGColor, TVP_TR_Color, TVP_TRNext_Color, TVP_Content_BGColor, 
             TVP_Page_BGColor, TVP_TH_BGColor, TVP_TH_Color, TVP_THTitle_BGColor, 
             TVP_THTitle_Color, TVP_Carattere, TVPViewPartials, TVPViewDetails, 
             TVPViewIdCard, TVPSettings)
            SELECT 
                TVPId, $newToId, TVPTimeStop, TVPTimeScroll, TVPNumRows, 
                TVMaxPage, TVPSession, TVPViewNationName, TVPNameComplete, 
                TVPViewTeamComponents, TVPEventInd, TVPEventTeam, TVPPhasesInd, 
                TVPPhasesTeam, TVPColumns, TVPPage, TVPDefault, TVP_TR_BGColor, 
                TVP_TRNext_BGColor, TVP_TR_Color, TVP_TRNext_Color, TVP_Content_BGColor, 
                TVP_Page_BGColor, TVP_TH_BGColor, TVP_TH_Color, TVP_THTitle_BGColor, 
                TVP_THTitle_Color, TVP_Carattere, TVPViewPartials, TVPViewDetails, 
                TVPViewIdCard, TVPSettings
            FROM TVParams 
            WHERE TVPTournament = $ToId
        ";
        
        safe_w_sql($CloneTVParams);
        $tvParamsCloned = safe_w_affected_rows();
        
        // 12. Cloner les règles TV (TVRules)
        $CloneTVRules = "
            INSERT INTO TVRules 
            (TVRId, TVRTournament, TVRName, TV_TR_BGColor, TV_TRNext_BGColor, 
             TV_TR_Color, TV_TRNext_Color, TV_Content_BGColor, TV_Page_BGColor, 
             TV_TH_BGColor, TV_TH_Color, TV_THTitle_BGColor, TV_THTitle_Color, 
             TV_Carattere, TVRSettings)
            SELECT 
                TVRId, $newToId, TVRName, TV_TR_BGColor, TV_TRNext_BGColor, 
                TV_TR_Color, TV_TRNext_Color, TV_Content_BGColor, TV_Page_BGColor, 
                TV_TH_BGColor, TV_TH_Color, TV_THTitle_BGColor, TV_THTitle_Color, 
                TV_Carattere, TVRSettings
            FROM TVRules 
            WHERE TVRTournament = $ToId
        ";
        
        safe_w_sql($CloneTVRules);
        $tvRulesCloned = safe_w_affected_rows();
        
        // 13. Cloner les séquences TV (TVSequence)
        $CloneTVSequence = "
            INSERT INTO TVSequence 
            (TVSId, TVSTournament, TVSRule, TVSContent, TVSCntSameTour, 
             TVSTime, TVSScroll, TVSTable, TVSOrder, TVSFullScreen)
            SELECT 
                TVSId, $newToId, TVSRule, TVSContent, TVSCntSameTour, 
                TVSTime, TVSScroll, TVSTable, TVSOrder, TVSFullScreen
            FROM TVSequence 
            WHERE TVSTournament = $ToId
        ";
        
        safe_w_sql($CloneTVSequence);
        $tvSequenceCloned = safe_w_affected_rows();
        
        echo '<hr>';
        echo '<p>La compétition a été clonée avec succès.</p>';
       
       // Conseils pour les prochaines étapes
        echo '<hr>';
        echo '<h4>Conseils pour la nouvelle compétition :</h4>';
        echo '<ol>';
        echo '<li><strong>Vérifiez les dates</strong> : Toutes les dates ont été ajustées selon votre sélection.</li>';
        echo '<li><strong>Modifiez les Arbitres</strong> : Ajuster les juges, arbitres, etc.</li>';
        echo '<li><strong>Vérifiez le planificateur</strong> : Les événements du planificateur ont été décalés de ' . $dateOffset . ' jour(s).</li>';
        echo '<li><strong>Configurez les sessions</strong> : Les sessions ont été copiées avec leurs nouveaux horaires.</li>';
        echo '<li><strong>Vérifiez les informations de distance</strong> : Les dates ont été mises à jour.</li>';
        echo '<li><strong>Vérifiez les paramètres TV</strong> : Les configurations d\'affichage TV ont été copiées.</li>';
        echo '</ol>';
		echo '<p>Vous pouvez maintenant :</p>';
		
        echo '<ul>';
        echo '<li><a href="' . $CFG->ROOT_DIR . 'Common/TourOn.php?ToId=' . $newToId . '" class="Button">Ouvrir la nouvelle compétition</a></li>';
        echo '</ul>';
        
        echo '</div>';
    } else {
        echo '<div class="error">Erreur lors du clonage de la compétition. Aucun ID retourné.</div>';
        echo '<p>Erreur SQL possible. Veuillez vérifier les logs.</p>';
        echo '<p><a href="?ToId=' . $ToId . '" class="Button">Réessayer</a></p>';
    }
}

include('Common/Templates/tail.php');
?>