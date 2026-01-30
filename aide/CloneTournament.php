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

echo '<h1>Cloner un tournoi</h1>';

// Étape 1 : Sélection du tournoi à cloner
if (!isset($_GET['ToId'])) {
    echo '<h2>Sélectionnez un tournoi à cloner</h2>';
    
    // Récupérer la liste des tournois (même logique que index.php)
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
        echo '<p>Aucun tournoi trouvé.</p>';
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
        // Récupérer le code du tournoi
        $Select = "SELECT ToCode FROM Tournament WHERE ToId = $ToId";
        $Rs = safe_r_sql($Select);
        if ($MyRow = safe_fetch($Rs)) {
            $tourCode = $MyRow->ToCode;
            // Vérifier si l'utilisateur a accès à ce tournoi
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
        echo '<div class="error">Vous n\'avez pas la permission d\'accéder à ce tournoi.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    // Récupérer les informations du tournoi (avec les mêmes champs que Main.php)
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
        echo '<div class="error">Tournoi non trouvé.</div>';
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
    
	// Générer un code unique par défaut
	$baseCode = $MyRow->ToCode;

	// Générer une lettre majuscule aléatoire (A-Z)
	$randomLetter = chr(mt_rand(65, 90)); // 65 = 'A', 90 = 'Z'

	// Générer 7 chiffres aléatoires
	$randomNumbers = '';
	for ($i = 0; $i < 7; $i++) {
		$randomNumbers .= mt_rand(0, 9);
	}

	// Combiner pour former le code par défaut
	$defaultCode = $randomLetter . $randomNumbers;

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
		
		// Si le code existe déjà, générer un nouveau code
		$randomLetter = chr(mt_rand(65, 90));
		$randomNumbers = '';
		for ($i = 0; $i < 7; $i++) {
			$randomNumbers .= mt_rand(0, 9);
		}
		$defaultCode = $randomLetter . $randomNumbers;
		
		// Sécurité pour éviter une boucle infinie
		if ($suffix > 20) {
			// Si on a essayé 20 fois sans succès, ajouter un suffixe
			$defaultCode = $originalCode . '_' . $suffix;
		}
		
		$suffix++;
		
		// Sécurité supplémentaire
		if ($suffix > 100) {
			$defaultCode = 'T' . date('Ymd');
			break;
		}
	}
    
    // Afficher les informations du tournoi comme dans Main.php
    echo '<h2>Informations du tournoi à cloner</h2>';
    
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
    echo '<p>Veuillez spécifier les nouvelles informations pour le tournoi cloné :</p>';
      
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
    echo '<td><label for="new_code">Nouveau code de tournoi :</label></td>';
    echo '<td><input type="text" id="new_code" name="new_code" value="' . $defaultCode . '" required maxlength="8"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="new_name">Nouveau nom de tournoi :</label></td>';
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
    echo '<input type="submit" value="Cloner le tournoi" class="Button">';
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
        echo '<div class="error">Vous n\'avez pas la permission de cloner ce tournoi.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    // Valider la longueur du code (max 8 caractères)
    if (strlen($newCode) > 8) {
        echo '<div class="error">Le code de tournoi ne peut pas dépasser 8 caractères.</div>';
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
            echo '<div class="error">Ce code de tournoi existe déjà. Veuillez en choisir un autre.</div>';
            echo '<p><a href="?ToId=' . $ToId . '" class="Button">Retour</a></p>';
            include('Common/Templates/tail.php');
            exit;
        }
    }
    
    // Récupérer le tournoi original
    $Select = "SELECT * FROM Tournament WHERE ToId = $ToId";
    $Rs = safe_r_sql($Select);
    
    if (safe_num_rows($Rs) != 1) {
        echo '<div class="error">Tournoi non trouvé.</div>';
        include('Common/Templates/tail.php');
        exit;
    }
    
    $MyRow = safe_fetch($Rs);
    
    // Si aucun code n'a été fourni, en générer un unique
    if (empty($newCode)) {
        $baseCode = $MyRow->ToCode;
        $suffix = 1;
        $newCode = $baseCode . '_COPY';
        
        while (true) {
            $Check = "SELECT COUNT(*) as count FROM Tournament WHERE ToCode = " . StrSafe_DB($newCode);
            $RsCheck = safe_r_sql($Check);
            $RowCheck = safe_fetch($RsCheck);
            
            if ($RowCheck->count == 0) {
                break;
            }
            
            $suffix++;
            $newCode = $baseCode . '_COPY' . $suffix;
            
            if ($suffix > 100) {
                $newCode = substr($baseCode, 0, 4) . date('md');
                break;
            }
        }
    }
    
    // Utiliser les valeurs fournies ou les valeurs par défaut
    $finalName = $newName ?: $MyRow->ToName . ' (Copie)';
    $finalNameShort = $newNameShort ?: $MyRow->ToNameShort;
    $finalWhere = $newWhere ?: $MyRow->ToWhere;
    $finalVenue = $newVenue ?: $MyRow->ToVenue;
    
    // Cloner le tournoi
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
        echo '<h3>Tournoi cloné avec succès !</h3>';
        
        // Afficher les informations clonées
        echo '<h4>Informations du nouveau tournoi :</h4>';
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
        
        // 1. Cloner les événements liés au tournoi
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
        //echo '<p><strong>Événements clonés :</strong> ' . $eventsCloned . '</p>';
        
        // 2. Cloner les classes liées au tournoi
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
        //echo '<p><strong>Classes clonées :</strong> ' . $classesCloned . '</p>';
        
        // 3. Cloner les divisions liées au tournoi
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
        //echo '<p><strong>Divisions clonées :</strong> ' . $divisionsCloned . '</p>';
        
        // 4. Cloner les informations de distance liées au tournoi
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
        //echo '<p><strong>Informations de distance clonées :</strong> ' . $distanceInfoCloned . '</p>';
        
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
        //echo '<p><strong>Associations événement-classe clonées :</strong> ' . $eventClassCloned . '</p>';
        
        // 6. Cloner les sessions liées au tournoi
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
        //echo '<p><strong>Sessions clonées :</strong> ' . $sessionsCloned . '</p>';
        
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
        //echo '<p><strong>Faces de cible clonées :</strong> ' . $targetFacesCloned . '</p>';
        
        // 8. Cloner les distances du tournoi (TournamentDistances)
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
        //echo '<p><strong>Distances du tournoi clonées :</strong> ' . $tournamentDistancesCloned . '</p>';
        
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
        //echo '<p><strong>Personnes impliquées clonées :</strong> ' . $tournamentInvolvedCloned . '</p>';
        
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
        //echo '<p><strong>Événements du planificateur clonés :</strong> ' . $schedulerCloned . '</p>';
        //echo '<p><em>Les dates du planificateur ont été ajustées de ' . $dateOffset . ' jour(s)</em></p>';
        
        
        echo '<hr>';
        echo '<p>Le tournoi a été cloné avec succès.</p>';
       
	   // Conseils pour les prochaines étapes
        echo '<hr>';
        echo '<h4>Conseils pour le nouveau tournoi :</h4>';
        echo '<ol>';
        echo '<li><strong>Vérifiez les dates</strong> : Toutes les dates ont été ajustées selon votre sélection.</li>';
        echo '<li><strong>Modifiez les Arbitres</strong> : Ajuster les juges, arbitres, etc.</li>';
        echo '<li><strong>Vérifiez le planificateur</strong> : Les événements du planificateur ont été décalés de ' . $dateOffset . ' jour(s).</li>';
        echo '<li><strong>Configurez les sessions</strong> : Les sessions ont été copiées avec leurs nouveaux horaires.</li>';
        echo '<li><strong>Vérifiez les informations de distance</strong> : Les dates ont été mises à jour.</li>';
        echo '</ol>';
		echo '<p>Vous pouvez maintenant :</p>';
		
        echo '<ul>';
        echo '<li><a href="' . $CFG->ROOT_DIR . 'Common/TourOn.php?ToId=' . $newToId . '" class="Button">Ouvrir le nouveau tournoi</a></li>';

        echo '</ul>';
        

        
        echo '</div>';
    } else {
        echo '<div class="error">Erreur lors du clonage du tournoi. Aucun ID retourné.</div>';
        echo '<p>Erreur SQL possible. Veuillez vérifier les logs.</p>';
        echo '<p><a href="?ToId=' . $ToId . '" class="Button">Réessayer</a></p>';
    }
}

include('Common/Templates/tail.php');
?>