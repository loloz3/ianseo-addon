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
 * - Laurent Petroff - Les Archers de Perols - (modif: 2025-12-23)
 * - Th3ju (modif: 2026-01-30)
 * 
 * Dernière modification: 2026-01-31 par Laurent Petroff
 * Ajout de la Facture
 * Impression et sauvegarde
 * Ajout ESPÈCES / CHÈQUE / VIREMENT et impression pour le trésorier (Merci à Th3ju)
 * Ajout facture club (2026-01-31)
 * Ajout persistance position après validation paiement (2026-01-31)
 * Inversion affichage Prénom/Nom (2026-01-31)
 * Correction annulation paiement (2026-06-04)
 *
 * Greffe.php
 * Page simple pour lister les archers inscrits pour faire le greffe.
 */


// Configuration de base
define('debug', false);

// Inclure les fichiers nécessaires
require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

// Vérifier la session et les permissions
CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

// Vérifier si on est en mode génération de facture individuelle
$is_invoice_mode = isset($_GET['invoice']) && $_GET['invoice'] == '1' && isset($_GET['archer_id']);

// Vérifier si on est en mode génération de facture club
$is_club_invoice_mode = isset($_GET['club_invoice']) && $_GET['club_invoice'] == '1' && isset($_GET['club_name']);

// Initialiser les filtres depuis la session ou GET
$filters = [
    'club' => isset($_GET['club_filter']) ? $_GET['club_filter'] : (isset($_SESSION['filters']['club']) ? $_SESSION['filters']['club'] : 'all'),
    'category' => isset($_GET['category_filter']) ? $_GET['category_filter'] : (isset($_SESSION['filters']['category']) ? $_SESSION['filters']['category'] : 'all'),
    'departs' => isset($_GET['departs_filter']) ? $_GET['departs_filter'] : (isset($_SESSION['filters']['departs']) ? $_SESSION['filters']['departs'] : 'all'),
    'payment' => isset($_GET['payment_filter']) ? $_GET['payment_filter'] : (isset($_SESSION['filters']['payment']) ? $_SESSION['filters']['payment'] : 'all')
];

// Sauvegarder les filtres dans la session pour la prochaine requête
$_SESSION['filters'] = $filters;

// Récupérer l'ID du tournoi
$TourId = $_SESSION['TourId'];

// Récupérer TOUTES les infos du tournoi en une seule requête
$tournamentQuery = "SELECT ToCommitee, ToComDescr, ToImgL, ToName, ToWhere, ToVenue FROM Tournament WHERE ToId = $TourId";
$tournamentRs = safe_r_sql($tournamentQuery);
$organizerClubCode = null;
$organizerClubName = null;
$organizerLogo = null;
$tournamentName = null;
$toWhere = null;
$toVenue = null;

if ($tournament = safe_fetch($tournamentRs)) {
    $organizerClubCode = $tournament->ToCommitee;
    $organizerClubName = $tournament->ToComDescr;
    $organizerLogo = $tournament->ToImgL;
    $tournamentName = $tournament->ToName;
    $toWhere = $tournament->ToWhere;
    $toVenue = $tournament->ToVenue;
    
    if (empty($organizerClubName) && $organizerClubCode) {
        $organizerNameQuery = "SELECT CoName FROM Countries WHERE CoCode = '" . $organizerClubCode . "' AND CoTournament = $TourId";
        $organizerNameRs = safe_r_sql($organizerNameQuery);
        if ($organizerName = safe_fetch($organizerNameRs)) {
            $organizerClubName = $organizerName->CoName;
        }
    }
}

// Traitement de la validation du paiement (CORRIGÉ)
if (isset($_POST['validate_payment']) && isset($_POST['archer_id'])) {
    $archerId = intval($_POST['archer_id']);
    $action = $_POST['validate_payment'];
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'ESPECE';
    
    $filterParams = '';
    foreach ($filters as $key => $value) {
        if ($value !== 'all') {
            $filterParams .= "&{$key}_filter=" . urlencode($value);
        }
    }
    
    $sortColumn = isset($_POST['sort_column']) ? $_POST['sort_column'] : (isset($_GET['sort']) ? $_GET['sort'] : 'nom');
    $sortDirection = isset($_POST['sort_direction']) ? $_POST['sort_direction'] : (isset($_GET['dir']) ? $_GET['dir'] : 'asc');
    
    $filterParams .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
    
    $scrollPosition = isset($_POST['scroll_position']) ? intval($_POST['scroll_position']) : 0;
    if ($scrollPosition > 0) {
        $filterParams .= "&scroll_pos=" . urlencode($scrollPosition);
    }
    
    $lastArcherId = $archerId;
    $filterParams .= "&last_archer=" . urlencode($lastArcherId);
    
    if ($action == 'validate') {
        $paymentStatus = 'PAYE|' . $paymentMethod;
        $successMessage = 'Paiement validé avec succès !';
        $messageType = 'success';
    } else {
        $paymentStatus = 'NON_PAYE';
        $successMessage = 'Paiement marqué comme non acquitté.';
        $messageType = 'info';
    }
    
    // Récupérer d'abord toutes les Entries liées à cet archer (même nom et prénom)
    $getEntriesQuery = "SELECT EnId FROM Entries 
                        WHERE EnTournament = $TourId 
                        AND EnAthlete = 1 
                        AND (EnId = $archerId 
                             OR (EnFirstName = (SELECT EnFirstName FROM Entries WHERE EnId = $archerId LIMIT 1) 
                                 AND EnName = (SELECT EnName FROM Entries WHERE EnId = $archerId LIMIT 1)))";
    $entriesRs = safe_r_sql($getEntriesQuery);
    
    $updated = 0;
    $errors = [];
    
    while ($entry = safe_fetch($entriesRs)) {
        $updateQuery = "UPDATE Qualifications 
                        SET QuNotes = '" . addslashes($paymentStatus) . "' 
                        WHERE QuId = " . $entry->EnId;
        
        if (safe_w_sql($updateQuery)) {
            $updated++;
        } else {
            $errors[] = "Erreur pour QuId=" . $entry->EnId;
        }
    }
    
    if ($updated > 0) {
        $_SESSION['payment_message'] = $successMessage . " ($updated inscription(s) mise(s) à jour)";
        $_SESSION['message_type'] = $messageType;
        $_SESSION['last_archer_id'] = $lastArcherId;
        $_SESSION['scroll_position'] = $scrollPosition;
    } else {
        $_SESSION['payment_message'] = 'Erreur: Aucune mise à jour effectuée. ' . implode(', ', $errors);
        $_SESSION['message_type'] = 'error';
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . ltrim($filterParams, '&'));
    exit();
}

// Fonction pour lire et parser le fichier Prix.txt
function parsePrixTxt($organizerClubCode = null, $organizerClubName = null) {
    $prixFile = dirname(__FILE__) . '/Prix.txt';
    
    if (!file_exists($prixFile)) {
        throw new Exception("Fichier Prix.txt introuvable");
    }
    
    $contenu = file_get_contents($prixFile);
    $tarifsParsed = [];
    
    preg_match_all('/(OJA1|OJA2|OAA1|OAA2|PJA1|PJA2|PAA1|PAA2)\s*=\s*(\d+)\s*;/', $contenu, $matches, PREG_SET_ORDER);
    
    $codes = ['OJA1', 'OJA2', 'OAA1', 'OAA2', 'PJA1', 'PJA2', 'PAA1', 'PAA2'];
    foreach ($codes as $code) {
        $tarifsParsed[$code] = 0;
    }
    
    foreach ($matches as $match) {
        if (isset($tarifsParsed[$match[1]])) {
            $tarifsParsed[$match[1]] = (int)$match[2];
        }
    }
    
    $tarifs = [
        'clubs_autres' => [
            'jeunes' => [
                1 => $tarifsParsed['OJA1'],
                2 => $tarifsParsed['OJA1'] + $tarifsParsed['OJA2']
            ],
            'adultes' => [
                1 => $tarifsParsed['OAA1'],
                2 => $tarifsParsed['OAA1'] + $tarifsParsed['OAA2']
            ]
        ],
        'organizer' => [
            'jeunes' => [
                1 => $tarifsParsed['PJA1'],
                2 => $tarifsParsed['PJA1'] + $tarifsParsed['PJA2']
            ],
            'adultes' => [
                1 => $tarifsParsed['PAA1'],
                2 => $tarifsParsed['PAA1'] + $tarifsParsed['PAA2']
            ]
        ]
    ];
    
    $tarifs['organizer_club_code'] = $organizerClubCode;
    $tarifs['organizer_club_name'] = $organizerClubName;
    
    return $tarifs;
}

// Fonction pour déterminer si c'est un jeune ou un adulte
function getAgeCategory($categorie_code) {
    $jeunes_categories = ['U11', 'U13', 'U15', 'U18'];
    $adultes_categories = ['U21', 'S1', 'S2', 'S3'];
    
    foreach ($jeunes_categories as $cat) {
        if (stripos($categorie_code, $cat) !== false) {
            return 'jeunes';
        }
    }
    
    foreach ($adultes_categories as $cat) {
        if (stripos($categorie_code, $cat) !== false) {
            return 'adultes';
        }
    }
    
    return 'adultes';
}

// Fonction pour calculer le prix total
function calculerPrix($club_country_code, $categorie_code, $nb_departs, $tarifs, $organizerClubCode) {
    $is_organizer = ($club_country_code == $organizerClubCode);
    $age_category = getAgeCategory($categorie_code);
    
    if ($is_organizer) {
        $prix_1_depart = $tarifs['organizer'][$age_category][1];
        $prix_2_departs = $tarifs['organizer'][$age_category][2];
    } else {
        $prix_1_depart = $tarifs['clubs_autres'][$age_category][1];
        $prix_2_departs = $tarifs['clubs_autres'][$age_category][2];
    }
    
    $supplement_2eme_depart = $prix_2_departs - $prix_1_depart;
    
    if ($nb_departs == 1) {
        return $prix_1_depart;
    } elseif ($nb_departs == 2) {
        return $prix_2_departs;
    } else {
        return $prix_1_depart + ($supplement_2eme_depart * ($nb_departs - 1));
    }
}

// Fonction pour calculer le prix par départ
function calculerPrixParDepart($club_country_code, $categorie_code, $nb_departs, $tarifs, $organizerClubCode) {
    $is_organizer = ($club_country_code == $organizerClubCode);
    $age_category = getAgeCategory($categorie_code);
    
    if ($is_organizer) {
        $prix_1_depart = $tarifs['organizer'][$age_category][1];
        $prix_2_departs = $tarifs['organizer'][$age_category][2];
    } else {
        $prix_1_depart = $tarifs['clubs_autres'][$age_category][1];
        $prix_2_departs = $tarifs['clubs_autres'][$age_category][2];
    }
    
    $supplement_2eme_depart = $prix_2_departs - $prix_1_depart;
    
    $prix_par_depart = [];
    $total = 0;
    
    if ($nb_departs == 1) {
        $prix_par_depart[1] = $prix_1_depart;
        $total = $prix_1_depart;
    } elseif ($nb_departs == 2) {
        $prix_par_depart[1] = $prix_1_depart;
        $prix_par_depart[2] = $supplement_2eme_depart;
        $total = $prix_1_depart + $supplement_2eme_depart;
    } else {
        $prix_par_depart[1] = $prix_1_depart;
        $total = $prix_1_depart;
        for ($i = 2; $i <= $nb_departs; $i++) {
            $prix_par_depart[$i] = $supplement_2eme_depart;
            $total += $supplement_2eme_depart;
        }
    }
    
    return [
        'total' => $total,
        'prix_par_depart' => $prix_par_depart,
        'is_organizer' => $is_organizer,
        'age_category' => $age_category,
        'prix_1_depart' => $prix_1_depart,
        'prix_2_departs' => $prix_2_departs,
        'supplement_par_depart' => $supplement_2eme_depart
    ];
}

// Fonction pour formater l'affichage des cibles/départs
function formaterCibleDepart($target_no) {
    if (empty($target_no) || $target_no == '0') {
        return 'Non affecté';
    }
    
    if (strlen($target_no) >= 5) {
        $depart = substr($target_no, 0, 1);
        $cible = substr($target_no, 1, 3);
        $position = substr($target_no, 4, 1);
        return "D{$depart} - {$cible}{$position}";
    }
    
    return htmlspecialchars($target_no);
}

// Fonction pour extraire les numéros de départ uniques
function extraireDeparts($target_numbers) {
    if (empty($target_numbers)) {
        return [];
    }
    
    $departs = [];
    $targets = explode(', ', $target_numbers);
    
    foreach ($targets as $target) {
        $target = trim($target);
        if (!empty($target) && $target != '0') {
            $depart = substr($target, 0, 1);
            if (is_numeric($depart) && $depart > 0) {
                $departs[] = (int)$depart;
            }
        }
    }
    
    return array_unique($departs);
}

// Lire les tarifs
try {
    $tarifs = parsePrixTxt($organizerClubCode, $organizerClubName);
} catch (Exception $e) {
    $tarifs = [
        'clubs_autres' => [
            'jeunes' => [1 => 8, 2 => 14],
            'adultes' => [1 => 10, 2 => 18]
        ],
        'organizer' => [
            'jeunes' => [1 => 4, 2 => 8],
            'adultes' => [1 => 5, 2 => 10]
        ],
        'organizer_club_code' => $organizerClubCode,
        'organizer_club_name' => $organizerClubName
    ];
}

// Si on est en mode facture individuelle
if ($is_invoice_mode && isset($_GET['archer_id'])) {
    $archerId = intval($_GET['archer_id']);
    
    $mainQuery = "SELECT 
        MIN(e.EnId) as id,
        e.EnFirstName as prenom,
        e.EnName as nom,
        CONCAT(e.EnFirstName, ' ', e.EnName) as nom_complet,
        MIN(CONCAT(e.EnDivision, e.EnClass)) as categorie,
        c.CoName as club,
        c.CoCode as country_code,
        COALESCE(
            MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN 1 ELSE 0 END),
            0
        ) as payment_status,
        COALESCE(
            MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN 
                SUBSTRING_INDEX(q.QuNotes, '|', -1) 
            ELSE NULL END),
            NULL
        ) as payment_method,
        COUNT(*) as nb_inscriptions,
        GROUP_CONCAT(DISTINCT CONCAT(e.EnDivision, e.EnClass) ORDER BY e.EnDivision, e.EnClass SEPARATOR ', ') as categories,
        GROUP_CONCAT(DISTINCT c.CoName ORDER BY c.CoName SEPARATOR ', ') as clubs,
        GROUP_CONCAT(DISTINCT 
            CONCAT(
                'D', 
                SUBSTRING(q.QuTargetNo, 1, 1),
                ' - ', 
                SUBSTRING(q.QuTargetNo, 2, 3),
                SUBSTRING(q.QuTargetNo, 5, 1)
            ) 
            ORDER BY q.QuSession, q.QuTargetNo 
            SEPARATOR '; '
        ) as cibles_departs,
        GROUP_CONCAT(DISTINCT q.QuTargetNo ORDER BY q.QuSession, q.QuTargetNo SEPARATOR ', ') as target_numbers,
        GROUP_CONCAT(DISTINCT q.QuSession ORDER BY q.QuSession SEPARATOR ', ') as sessions,
        GROUP_CONCAT(DISTINCT e.EnId ORDER BY e.EnId SEPARATOR ',') as all_ids
    FROM Entries e
    LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
    LEFT JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId 
    AND e.EnAthlete = 1
    AND (e.EnId = $archerId OR CONCAT(e.EnFirstName, ' ', e.EnName) = 
        (SELECT CONCAT(EnFirstName, ' ', EnName) FROM Entries WHERE EnId = $archerId LIMIT 1))
    GROUP BY e.EnFirstName, e.EnName";
    
    $Rs = safe_r_sql($mainQuery);
    
    if ($archer = safe_fetch($Rs)) {
        $departs_archer = extraireDeparts($archer->target_numbers);
        $calculPrix = calculerPrixParDepart($archer->country_code, $archer->categorie, $archer->nb_inscriptions, $tarifs, $organizerClubCode);
        $montant = $calculPrix['total'];
        
        $affectations = [];
        if (!empty($archer->cibles_departs) && $archer->cibles_departs != 'D? - ?') {
            $affectations_list = explode('; ', $archer->cibles_departs);
            foreach ($affectations_list as $affectation) {
                if (!empty(trim($affectation))) {
                    $affectations[] = $affectation;
                }
            }
        }
        
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture - <?php echo htmlspecialchars($archer->prenom . ' ' . $archer->nom); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; background-color: white; }
                .invoice-container { max-width: 1000px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2c5f2d; padding-bottom: 20px; }
                .invoice-title { font-size: 24px; font-weight: bold; color: #2c5f2d; margin-bottom: 10px; }
                .invoice-subtitle { font-size: 18px; color: #666; margin-bottom: 20px; }
                .invoice-info { font-size: 14px; color: #666; margin-bottom: 5px; }
                .invoice-section-title { font-size: 16px; font-weight: bold; color: #2c5f2d; margin: 25px 0 15px 0; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .invoice-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
                .invoice-table th { background-color: #f2f2f2; color: #333; padding: 10px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
                .invoice-table td { padding: 8px 10px; border: 1px solid #ddd; }
                .invoice-total { margin-top: 30px; text-align: right; font-size: 18px; font-weight: bold; border-top: 2px solid #2c5f2d; padding-top: 15px; }
                .invoice-footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; padding-top: 20px; }
                .print-button { text-align: center; margin: 20px 0; }
                .print-button button { background-color: #2c5f2d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 0 10px; }
                .club-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #e9ecef; }
                .invoice-logo img { max-width: 150px; max-height: 100px; object-fit: contain; }
                .payment-status-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
                .status-paid { background-color: #d4edda; color: #155724; }
                .status-unpaid { background-color: #f8d7da; color: #721c24; }
                .target-badge { display: inline-block; background-color: #e3f2fd; border: 1px solid #bbdefb; padding: 1px 4px; border-radius: 3px; font-size: 10px; color: #1565c0; margin: 1px; white-space: nowrap; }
                .info-row { display: flex; margin-bottom: 8px; }
                .info-label { width: 180px; font-weight: bold; color: #555; }
                .info-value { flex: 1; }
                @media print { .print-button { display: none; } body { margin: 0; padding: 10px; } .invoice-container { border: none; box-shadow: none; padding: 0; } }
            </style>
        </head>
        <body>
            <div class="print-button">
                <button onclick="window.print()">🖨️ Imprimer la facture</button>
                <button onclick="saveIndividualInvoice()">💾 Sauvegarder la facture</button>
                <button onclick="window.history.back()">← Retour à la liste</button>
            </div>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <?php if (!empty($organizerLogo)): ?>
                        <?php $cleanLogo = ltrim($organizerLogo, "\x00..\x1F"); $base64Image = 'data:image/jpeg;base64,' . base64_encode($cleanLogo); ?>
                        <img width="150" src="<?php echo htmlspecialchars($base64Image); ?>" alt="<?php echo htmlspecialchars($tournamentName ?: 'Tournoi'); ?>" style="margin-bottom: 15px;" />
                    <?php endif; ?>
                    <div class="invoice-title">FACTURE INDIVIDUELLE</div>
                    <div class="invoice-subtitle"><?php echo htmlspecialchars($tournamentName ?: 'Tournoi de Tir à l\'Arc'); ?></div>
                    <div class="invoice-info">Date de génération : <?php echo date('d/m/Y H:i'); ?></div>
                    <div class="invoice-info">Numéro de facture : FAC-<?php echo date('Ymd'); ?>-<?php echo str_pad($archerId, 4, '0', STR_PAD_LEFT); ?></div>
                </div>
                
                <div class="club-info">
                    <div class="info-row"><div class="info-label">Nom :</div><div class="info-value"><strong><?php echo htmlspecialchars($archer->prenom . ' ' . $archer->nom); ?></strong></div></div>
                    <div class="info-row"><div class="info-label">Club :</div><div class="info-value"><?php echo htmlspecialchars($archer->club); ?> (Code: <?php echo htmlspecialchars($archer->country_code); ?>)</div></div>
                    <div class="info-row"><div class="info-label">Catégorie :</div><div class="info-value"><?php echo htmlspecialchars($archer->categorie); ?> (<?php echo $calculPrix['age_category']; ?>)</div></div>
                    <div class="info-row"><div class="info-label">Nombre de départs :</div><div class="info-value"><?php echo $archer->nb_inscriptions; ?></div></div>
                    <div class="info-row"><div class="info-label">Statut paiement :</div><div class="info-value"><span class="payment-status-badge <?php echo ($archer->payment_status == 1) ? 'status-paid' : 'status-unpaid'; ?>"><?php echo ($archer->payment_status == 1) ? 'Payé' : 'Non payé'; ?></span></div></div>
                    <?php if ($archer->payment_status == 1 && !empty($archer->payment_method)): ?>
                    <div class="info-row"><div class="info-label">Moyen de paiement :</div><div class="info-value"><strong><?php echo htmlspecialchars($archer->payment_method); ?></strong></div></div>
                    <?php endif; ?>
                </div>
                
                <div class="invoice-section-title">Détail des Départs</div>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Départ</th>
                            <th>Cible</th>
                            <th>Position</th>
                            <th>Prix (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_facture = 0;
                        $nb_departs_total = $archer->nb_inscriptions;
                        $affectation_index = 0;
                        
                        for ($depart_numero = 1; $depart_numero <= $nb_departs_total; $depart_numero++) {
                            $prix_depart = isset($calculPrix['prix_par_depart'][$depart_numero]) ? $calculPrix['prix_par_depart'][$depart_numero] : $calculPrix['prix_par_depart'][min($depart_numero, 2)];
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $depart_numero; ?></td>
                                <td style="text-align: center;">Départ <?php echo $depart_numero; ?></td>
                                <?php
                                if (isset($affectations[$affectation_index])) {
                                    $affectation = trim($affectations[$affectation_index]);
                                    if (!empty($affectation)) {
                                        $parts = explode(' - ', $affectation);
                                        if (count($parts) == 2) {
                                            $cible_position = $parts[1];
                                            $cible = substr($cible_position, 0, 3);
                                            $position = substr($cible_position, 3);
                                            echo '<td style="text-align: center;">Cible ' . htmlspecialchars($cible) . '</td>';
                                            echo '<td style="text-align: center;">Position ' . htmlspecialchars($position) . '</td>';
                                        } else {
                                            echo '<td colspan="2" style="text-align: center;"><em>' . htmlspecialchars($affectation) . '</em></td>';
                                        }
                                        $affectation_index++;
                                    } else {
                                        echo '<td colspan="2" style="text-align: center;"><em>Non affecté</em></td>';
                                    }
                                } else {
                                    echo '<td colspan="2" style="text-align: center;"><em>Non affecté</em></td>';
                                }
                                ?>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($prix_depart, 2); ?> €</td>
                            </tr>
                            <?php
                            $total_facture += $prix_depart;
                        }
                        ?>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="4" style="text-align: right;">TOTAL :</td>
                            <td style="text-align: right;"><?php echo number_format($total_facture, 2); ?> €</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="invoice-total">
                    <strong>MONTANT TOTAL : <?php echo number_format($montant, 2); ?> €</strong>
                </div>
                
                <div class="invoice-footer">
                    <p><strong>Association loi 1901 - TVA non applicable, Article 293 B du Code Général des Impôts</strong></p>
                    <p><strong>Club organisateur :</strong> <?php echo htmlspecialchars($organizerClubName ?: $organizerClubCode); ?></p>
                    <?php if (!empty($toWhere)): ?><p><strong>Adresse :</strong> <?php echo htmlspecialchars($toWhere); ?></p><?php endif; ?>
                    <?php if (!empty($toVenue)): ?><p><strong>Lieu :</strong> <?php echo htmlspecialchars($toVenue); ?></p><?php endif; ?>
                </div>
            </div>
            
            <script>
                function saveIndividualInvoice() {
                    const saveBtn = document.querySelector('button[onclick="saveIndividualInvoice()"]');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '⏳ Sauvegarde...';
                    saveBtn.disabled = true;
                    try {
                        const invoiceContent = document.querySelector('.invoice-container').cloneNode(true);
                        const printButton = invoiceContent.querySelector('.print-button');
                        if (printButton) printButton.remove();
                        const archerName = '<?php echo addslashes($archer->prenom . '_' . $archer->nom); ?>';
                        const now = new Date();
                        const dateStr = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0') + '_' + String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0');
                        const filename = 'facture_' + archerName + '_' + dateStr + '.html';
                        const styles = Array.from(document.querySelectorAll('style')).map(style => style.innerHTML).join('\n');
                        const html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Facture</title><style>' + styles + '</style></head><body>' + invoiceContent.innerHTML + '</body></html>';
                        fetch('save_invoice.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'html_content=' + encodeURIComponent(html) + '&filename=' + encodeURIComponent(filename)
                        })
                        .then(response => response.json())
                        .then(data => { if (data.success) alert('✅ Facture sauvegardée : ' + data.filename); else throw new Error(data.message || 'Erreur'); })
                        .catch(error => { console.error('Erreur:', error); alert('❌ Erreur : ' + error.message); })
                        .finally(() => { saveBtn.innerHTML = originalText; saveBtn.disabled = false; });
                    } catch (error) { console.error('Erreur:', error); alert('❌ Erreur : ' + error.message); saveBtn.innerHTML = originalText; saveBtn.disabled = false; }
                }
            </script>
        </body>
        </html>
        <?php
        exit();
    } else {
        include('Common/Templates/head.php');
        echo '<div class="error" style="margin: 50px auto; max-width: 600px;">';
        echo '<strong>Erreur :</strong> Archer non trouvé.';
        echo '<br><br><a href="' . $_SERVER['PHP_SELF'] . '" class="filter-reset">← Retour à la liste</a>';
        echo '</div>';
        include('Common/Templates/tail.php');
        exit();
    }
}

// Mode facture club
if ($is_club_invoice_mode && isset($_GET['club_name'])) {
    $club_name = urldecode($_GET['club_name']);
    
    $clubQuery = "SELECT 
        e.EnFirstName as prenom,
        e.EnName as nom,
        CONCAT(e.EnDivision, e.EnClass) as categorie,
        c.CoName as club,
        c.CoCode as country_code,
        COUNT(*) as nb_inscriptions,
        COALESCE(MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN 1 ELSE 0 END), 0) as payment_status,
        GROUP_CONCAT(DISTINCT 
            CONCAT('D', SUBSTRING(q.QuTargetNo, 1, 1), ' - ', SUBSTRING(q.QuTargetNo, 2, 3), SUBSTRING(q.QuTargetNo, 5, 1)) 
            ORDER BY q.QuSession, q.QuTargetNo SEPARATOR '; '
        ) as cibles_departs
    FROM Entries e
    LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
    LEFT JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId AND e.EnAthlete = 1 AND c.CoName = '" . addslashes($club_name) . "'
    GROUP BY e.EnFirstName, e.EnName
    ORDER BY e.EnName, e.EnFirstName";
    
    $clubRs = safe_r_sql($clubQuery);
    $clubArchers = [];
    $total_club = 0;
    
    while ($archer = safe_fetch($clubRs)) {
        $montant = calculerPrix($archer->country_code, $archer->categorie, (int)$archer->nb_inscriptions, $tarifs, $organizerClubCode);
        $total_club += $montant;
        $clubArchers[] = [
            'prenom' => $archer->prenom, 'nom' => $archer->nom, 'categorie' => $archer->categorie,
            'club' => $archer->club, 'country_code' => $archer->country_code,
            'nb_inscriptions' => (int)$archer->nb_inscriptions, 'payment_status' => $archer->payment_status,
            'montant' => $montant, 'cibles_departs' => $archer->cibles_departs
        ];
    }
    
    if (count($clubArchers) > 0) {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture Club - <?php echo htmlspecialchars($club_name); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; background-color: white; }
                .invoice-container { max-width: 1000px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2c5f2d; padding-bottom: 20px; }
                .invoice-title { font-size: 24px; font-weight: bold; color: #2c5f2d; margin-bottom: 10px; }
                .invoice-subtitle { font-size: 18px; color: #666; margin-bottom: 20px; }
                .invoice-info { font-size: 14px; color: #666; margin-bottom: 5px; }
                .invoice-section-title { font-size: 16px; font-weight: bold; color: #2c5f2d; margin: 25px 0 15px 0; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .invoice-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
                .invoice-table th { background-color: #f2f2f2; color: #333; padding: 10px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
                .invoice-table td { padding: 8px 10px; border: 1px solid #ddd; }
                .invoice-total { margin-top: 30px; text-align: right; font-size: 18px; font-weight: bold; border-top: 2px solid #2c5f2d; padding-top: 15px; }
                .invoice-footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; padding-top: 20px; }
                .print-button { text-align: center; margin: 20px 0; }
                .print-button button { background-color: #2c5f2d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 0 10px; }
                .club-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #e9ecef; }
                .invoice-logo img { max-width: 150px; max-height: 100px; object-fit: contain; }
                .payment-status-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
                .status-paid { background-color: #d4edda; color: #155724; }
                .status-unpaid { background-color: #f8d7da; color: #721c24; }
                .target-badge { display: inline-block; background-color: #e3f2fd; border: 1px solid #bbdefb; padding: 1px 4px; border-radius: 3px; font-size: 10px; color: #1565c0; margin: 1px; white-space: nowrap; }
                @media print { .print-button { display: none; } body { margin: 0; padding: 10px; } .invoice-container { border: none; box-shadow: none; padding: 0; } }
            </style>
        </head>
        <body>
            <div class="print-button">
                <button onclick="window.print()">🖨️ Imprimer la facture</button>
                <button onclick="saveClubInvoice()">💾 Sauvegarder la facture</button>
                <button onclick="window.history.back()">← Retour à la liste</button>
            </div>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <?php if (!empty($organizerLogo)): ?>
                        <?php $cleanLogo = ltrim($organizerLogo, "\x00..\x1F"); $base64Image = 'data:image/jpeg;base64,' . base64_encode($cleanLogo); ?>
                        <img width="150" src="<?php echo htmlspecialchars($base64Image); ?>" alt="<?php echo htmlspecialchars($tournamentName ?: 'Tournoi'); ?>" style="margin-bottom: 15px;" />
                    <?php endif; ?>
                    <div class="invoice-title">FACTURE CLUB</div>
                    <div class="invoice-subtitle"><?php echo htmlspecialchars($tournamentName ?: 'Tournoi de Tir à l\'Arc'); ?></div>
                    <div class="invoice-info">Date de génération : <?php echo date('d/m/Y H:i'); ?></div>
                    <div class="invoice-info">Facture N° : CLUB-<?php echo date('Ymd'); ?>-<?php echo substr(md5($club_name), 0, 8); ?></div>
                </div>
                
                <div class="club-info">
                    <strong>Club :</strong> <?php echo htmlspecialchars($club_name); ?><br>
                    <strong>Nombre d'archers :</strong> <?php echo count($clubArchers); ?><br>
                    <strong>Code club :</strong> <?php echo htmlspecialchars($clubArchers[0]['country_code']); ?>
                </div>
                
                <div class="invoice-section-title">Détail des inscriptions</div>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Catégorie</th>
                            <th>Nb départs</th>
                            <th>Départs / Cibles</th>
                            <th>Statut</th>
                            <th>Montant (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $index = 1; foreach ($clubArchers as $archer): 
                            $cibles_html = '';
                            if (!empty($archer['cibles_departs']) && $archer['cibles_departs'] != 'D? - ?') {
                                $affectations = explode('; ', $archer['cibles_departs']);
                                foreach ($affectations as $affectation) {
                                    if (!empty(trim($affectation))) $cibles_html .= '<span class="target-badge">' . htmlspecialchars($affectation) . '</span> ';
                                }
                            } else { $cibles_html = '<em>Non affecté</em>'; }
                            $status_class = ($archer['payment_status'] == 1) ? 'status-paid' : 'status-unpaid';
                            $status_text = ($archer['payment_status'] == 1) ? 'Payé' : 'Non payé';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($archer['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($archer['nom']); ?></td>
                            <td><?php echo htmlspecialchars($archer['categorie']); ?></td>
                            <td style="text-align: center;"><?php echo $archer['nb_inscriptions']; ?></td>
                            <td style="max-width: 200px;"><?php echo $cibles_html; ?></td>
                            <td style="text-align: center;"><span class="payment-status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td style="text-align: right;"><?php echo number_format($archer['montant'], 2); ?> €</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="7" style="text-align: right;">TOTAL CLUB :</td>
                            <td style="text-align: right;"><?php echo number_format($total_club, 2); ?> €</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="invoice-total">
                    <strong>MONTANT TOTAL À PAYER : <?php echo number_format($total_club, 2); ?> €</strong>
                </div>
                
                <div class="invoice-footer">
                    <p><strong>Association loi 1901 - TVA non applicable, Article 293 B du Code Général des Impôts</strong></p>
                    <p><strong>Club organisateur :</strong> <?php echo htmlspecialchars($organizerClubName ?: $organizerClubCode); ?></p>
                    <?php if (!empty($toWhere)): ?><p><strong>Adresse :</strong> <?php echo htmlspecialchars($toWhere); ?></p><?php endif; ?>
                    <?php if (!empty($toVenue)): ?><p><strong>Lieu :</strong> <?php echo htmlspecialchars($toVenue); ?></p><?php endif; ?>
                </div>
            </div>
            
            <script>
                function saveClubInvoice() {
                    const saveBtn = document.querySelector('button[onclick="saveClubInvoice()"]');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '⏳ Sauvegarde...';
                    saveBtn.disabled = true;
                    try {
                        const invoiceContent = document.querySelector('.invoice-container').cloneNode(true);
                        const printButton = invoiceContent.querySelector('.print-button');
                        if (printButton) printButton.remove();
                        const clubName = '<?php echo addslashes($club_name); ?>';
                        const now = new Date();
                        const dateStr = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0') + '_' + String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0');
                        const safeClubName = clubName.replace(/[^\w\s]/gi, '').replace(/\s+/g, '_');
                        const filename = 'facture_club_' + safeClubName + '_' + dateStr + '.html';
                        const styles = Array.from(document.querySelectorAll('style')).map(style => style.innerHTML).join('\n');
                        const html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Facture Club - ' + clubName + '</title><style>' + styles + '</style></head><body>' + invoiceContent.innerHTML + '</body></html>';
                        fetch('save_invoice.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'html_content=' + encodeURIComponent(html) + '&filename=' + encodeURIComponent(filename)
                        })
                        .then(response => response.json())
                        .then(data => { if (data.success) alert('✅ Facture sauvegardée : ' + data.filename); else throw new Error(data.message || 'Erreur'); })
                        .catch(error => { console.error('Erreur:', error); alert('❌ Erreur : ' + error.message); })
                        .finally(() => { saveBtn.innerHTML = originalText; saveBtn.disabled = false; });
                    } catch (error) { console.error('Erreur:', error); alert('❌ Erreur : ' + error.message); saveBtn.innerHTML = originalText; saveBtn.disabled = false; }
                }
            </script>
        </body>
        </html>
        <?php
        exit();
    } else {
        include('Common/Templates/head.php');
        echo '<div class="error" style="margin: 50px auto; max-width: 600px;">';
        echo '<strong>Erreur :</strong> Aucun archer trouvé pour ce club.';
        echo '<br><br><a href="' . $_SERVER['PHP_SELF'] . '" class="filter-reset">← Retour à la liste</a>';
        echo '</div>';
        include('Common/Templates/tail.php');
        exit();
    }
}

// MODE NORMAL - Affichage de la page principale
include('Common/Templates/head.php');
?>

<style>
    .Title { color: #2c5f2d; font-size: 24px; font-weight: bold; margin-bottom: 15px; border-bottom: 2px solid #2c5f2d; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 0px; font-size: 13px; border: 1px solid #ddd; }
    th { background-color: #2c5f2d; color: white; padding: 8px 10px; text-align: left; font-weight: bold; cursor: pointer; position: relative; user-select: none; }
    th:hover { background-color: #245325; }
    th.sort-asc::after { content: " ▲"; font-size: 9px; position: absolute; right: 5px; }
    th.sort-desc::after { content: " ▼"; font-size: 9px; position: absolute; right: 5px; }
    td { padding: 8px 10px; border-bottom: 1px solid #ddd; vertical-align: middle; }
    tr:hover { background-color: #f5f5f5; }
    .error { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #f5c6cb; }
    .controls { background-color: #f1f8e9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dcedc8; }
    .filter-group { display: inline-block; margin-right: 20px; margin-bottom: 8px; }
    .filter-group label { margin-right: 5px; font-weight: bold; color: #2c5f2d; font-size: 13px; }
    .filter-group select { padding: 5px 8px; border-radius: 3px; border: 1px solid #b2d8b2; background-color: white; font-size: 13px; min-width: 150px; }
    .filter-reset { background-color: #6c757d; color: white; border: none; padding: 5px 12px; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: bold; }
    .filter-reset:hover { background-color: #5a6268; }
    .club-badge { display: inline-block; background-color: #e3f2fd; border: 1px solid #bbdefb; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #1565c0; font-weight: bold; }
    .categorie-badge { display: inline-block; background-color: #f3e5f5; border: 1px solid #e1bee7; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #7b1fa2; font-weight: bold; }
    .montant-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-align: center; background-color: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
    .montant-organizer { background-color: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
    .payment-button { padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s; min-width: 70px; margin: 2px; }
    .payment-button.validate { background-color: #28a745; color: white; }
    .payment-button.validate:hover { background-color: #218838; }
    .payment-button.unvalidate { background-color: #dc3545; color: white; }
    .payment-button.unvalidate:hover { background-color: #c82333; }
    .invoice-button { background-color: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s; min-width: 70px; margin: 2px; text-decoration: none; display: inline-block; text-align: center; }
    .invoice-button:hover { background-color: #0056b3; }
    .club-invoice-btn { background-color: #17a2b8; font-size: 10px; padding: 2px 6px; margin-left: 5px; text-decoration: none; display: inline-block; }
    .club-invoice-btn:hover { background-color: #138496; }
    .payment-status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; text-align: center; min-width: 70px; }
    .status-paid { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-unpaid { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .target-badge { display: inline-block; background-color: #e3f2fd; border: 1px solid #bbdefb; padding: 1px 4px; border-radius: 3px; font-size: 10px; color: #1565c0; font-weight: bold; margin: 1px; white-space: nowrap; }
    .target-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; }
    .target-cell { min-width: 120px; font-size: 11px; }
    .filter-summary { margin-top: 10px; padding: 10px; background-color: #e8f5e8; border-radius: 4px; font-size: 12px; border: 1px solid #c8e6c9; }
    .filter-summary strong { color: #2c5f2d; }
    td:first-child { font-weight: bold; text-align: left; width: 40px; }
    .notification { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px; z-index: 1000; animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-out 2.7s forwards; }
    .notification-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .notification-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .notification-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; transform: translateX(100%); } }
    form { margin: 0; padding: 0; display: inline; }
    .controls form { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; }
    .filter-group { display: flex; align-items: center; gap: 5px; }
    .action-buttons { display: flex; flex-wrap: wrap; gap: 5px; min-width: 150px; }
    .action-cell { min-width: 160px; }
    .export-pdf-button { background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s; }
    .export-pdf-button:hover { background-color: #c82333; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .export-container { text-align: right; margin-bottom: 15px; }
    .highlight-row { background-color: #d4edda !important; transition: background-color 0.5s ease; }
    .summary { background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; padding: 15px; margin: 20px 0; }
    .summary h2 { margin-top: 0; color: #0c5460; }
    tr:nth-child(even) { background-color: #f8f9fa; }
    tr:hover { background-color: #e8f5e9 !important; }
    .container { margin-top: -20px; }
</style>

<div class="container">
    
    <?php
    $notificationHtml = '';
    if (isset($_SESSION['payment_message'])) {
        $messageType = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success';
        $message = $_SESSION['payment_message'];
        $icon = $messageType === 'error' ? '⚠' : ($messageType === 'info' ? 'ℹ' : '✓');
        $notificationHtml = '<div class="notification notification-' . $messageType . '"><div class="notification-icon">' . $icon . '</div><div class="notification-content">' . htmlspecialchars($message) . '</div></div>';
        unset($_SESSION['payment_message']);
        unset($_SESSION['message_type']);
    }
    
    $lastArcherId = isset($_SESSION['last_archer_id']) ? $_SESSION['last_archer_id'] : (isset($_GET['last_archer']) ? $_GET['last_archer'] : null);
    $scrollPosition = isset($_SESSION['scroll_position']) ? $_SESSION['scroll_position'] : (isset($_GET['scroll_pos']) ? $_GET['scroll_pos'] : null);
    if (isset($_SESSION['last_archer_id'])) unset($_SESSION['last_archer_id']);
    if (isset($_SESSION['scroll_position'])) unset($_SESSION['scroll_position']);
    
    try {
        echo '<div class="summary">';
        echo '<h2>Tournoi : ' . htmlspecialchars($tournamentName ?: 'Non défini') . '</h2>';
        echo '<p>Club organisateur : ' . htmlspecialchars($organizerClubName ?: 'Non défini') . ' (Code: ' . htmlspecialchars($organizerClubCode ?: 'Non défini') . ')</p>';
        echo '</div>';
        
        $query = "SELECT 
            e.EnId as id,
            e.EnFirstName as prenom,
            e.EnName as nom,
            CONCAT(e.EnFirstName, ' ', e.EnName) as nom_complet,
            CONCAT(e.EnDivision, e.EnClass) as categorie,
            c.CoName as club,
            c.CoCode as country_code,
            COALESCE(MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN 1 ELSE 0 END), 0) as payment_status,
            COALESCE(MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN SUBSTRING_INDEX(q.QuNotes, '|', -1) ELSE NULL END), NULL) as payment_method,
            COUNT(*) as nb_inscriptions,
            GROUP_CONCAT(DISTINCT CONCAT(e.EnDivision, e.EnClass) ORDER BY e.EnDivision, e.EnClass SEPARATOR ', ') as categories,
            GROUP_CONCAT(DISTINCT c.CoName ORDER BY c.CoName SEPARATOR ', ') as clubs,
            GROUP_CONCAT(DISTINCT CONCAT('D', SUBSTRING(q.QuTargetNo, 1, 1), ' - ', SUBSTRING(q.QuTargetNo, 2, 3), SUBSTRING(q.QuTargetNo, 5, 1)) ORDER BY q.QuSession, q.QuTargetNo SEPARATOR '; ') as cibles_departs,
            GROUP_CONCAT(DISTINCT q.QuTargetNo ORDER BY q.QuSession, q.QuTargetNo SEPARATOR ', ') as target_numbers,
            GROUP_CONCAT(DISTINCT q.QuSession ORDER BY q.QuSession SEPARATOR ', ') as sessions
        FROM Entries e
        LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
        LEFT JOIN Qualifications q ON e.EnId = q.QuId
        WHERE e.EnTournament = $TourId AND e.EnAthlete = 1
        GROUP BY e.EnFirstName, e.EnName
        ORDER BY e.EnName, e.EnFirstName";
        
        $Rs = safe_r_sql($query);
        
        if (!$Rs) throw new Exception("Erreur lors de l'exécution de la requête");
        
        $totalArchers = safe_num_rows($Rs);
        
        $clubsQuery = "SELECT DISTINCT c.CoCode, c.CoName 
                      FROM Entries e
                      LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
                      WHERE e.EnTournament = $TourId AND e.EnAthlete = 1 AND c.CoName IS NOT NULL AND c.CoName != ''
                      ORDER BY c.CoName";
        $clubsRs = safe_r_sql($clubsQuery);
        $clubs = [];
        while ($club = safe_fetch($clubsRs)) $clubs[$club->CoCode] = $club->CoName;
        
        $categoriesQuery = "SELECT DISTINCT CONCAT(EnDivision, EnClass) as categorie 
                          FROM Entries WHERE EnTournament = $TourId AND EnAthlete = 1 AND EnDivision != '' AND EnClass != ''
                          ORDER BY CONCAT(EnDivision, EnClass)";
        $categoriesRs = safe_r_sql($categoriesQuery);
        $categories = [];
        while ($cat = safe_fetch($categoriesRs)) $categories[] = $cat->categorie;
        
        echo '<div class="controls">';
        echo '<form id="filterForm" method="GET" style="margin-bottom: 10px;">';
        
        $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'prenom';
        $currentDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';
        echo '<input type="hidden" name="sort" id="filterSort" value="' . htmlspecialchars($currentSort) . '">';
        echo '<input type="hidden" name="dir" id="filterDir" value="' . htmlspecialchars($currentDir) . '">';
        
        echo '<div class="filter-group"><label for="club_filter">Club :</label>';
        echo '<select id="club_filter" name="club_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        echo '<option value="all"' . ($filters['club'] == 'all' ? ' selected' : '') . '>Tous les clubs</option>';
        foreach ($clubs as $code => $name) echo '<option value="' . htmlspecialchars($name) . '"' . ($filters['club'] == $name ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
        echo '</select></div>';
        
        echo '<div class="filter-group"><label for="category_filter">Catégorie:</label>';
        echo '<select id="category_filter" name="category_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        echo '<option value="all"' . ($filters['category'] == 'all' ? ' selected' : '') . '>Toutes les catégories</option>';
        foreach ($categories as $cat) echo '<option value="' . htmlspecialchars($cat) . '"' . ($filters['category'] == $cat ? ' selected' : '') . '>' . htmlspecialchars($cat) . '</option>';
        echo '</select></div>';
        
        echo '<div class="filter-group"><label for="departs_filter">Départs:</label>';
        echo '<select id="departs_filter" name="departs_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        $departs_options = ['all' => 'Tous', '1' => '1', '2' => '2', '3' => '3', '4' => '4+'];
        foreach ($departs_options as $value => $label) echo '<option value="' . $value . '"' . ($filters['departs'] == $value ? ' selected' : '') . '>' . $label . '</option>';
        echo '</select></div>';
        
        echo '<div class="filter-group"><label for="payment_filter">Paiement:</label>';
        echo '<select id="payment_filter" name="payment_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        $payment_options = ['all' => 'Tous', 'paid' => 'Payés', 'unpaid' => 'Non payés'];
        foreach ($payment_options as $value => $label) echo '<option value="' . $value . '"' . ($filters['payment'] == $value ? ' selected' : '') . '>' . $label . '</option>';
        echo '</select></div>';
        
        echo '<div class="filter-group"><button type="button" onclick="resetFilters()" class="filter-reset">Réinitialiser</button></div>';
        echo '</form>';
        
        echo '<div class="export-container"><button onclick="exportToPDF()" class="export-pdf-button" title="Exporter la liste en PDF">📄 Exporter la liste en PDF</button></div>';
        
        $active_filters = [];
        if ($filters['club'] != 'all') $active_filters[] = 'Club: ' . htmlspecialchars($filters['club']);
        if ($filters['category'] != 'all') $active_filters[] = 'Catégorie: ' . htmlspecialchars($filters['category']);
        if ($filters['departs'] != 'all') $active_filters[] = 'Départs: ' . $departs_options[$filters['departs']];
        if ($filters['payment'] != 'all') $active_filters[] = 'Paiement: ' . $payment_options[$filters['payment']];
        if (!empty($active_filters)) echo '<div class="filter-summary"><strong>Filtres actifs:</strong> ' . implode(' | ', $active_filters) . '</div>';
        echo '</div>';
        
        if ($totalArchers > 0) {
            $totalPrix = 0; $totalOrganizer = 0; $totalAutres = 0; $countOrganizer = 0; $countAutres = 0;
            $totalPaye = 0; $totalNonPaye = 0; $countPaye = 0; $countNonPaye = 0; $filteredTotal = 0;
            
            $archersData = [];
            $counter = 0;
            while ($row = safe_fetch($Rs)) {
                $counter++;
                $archersData[] = [
                    'id' => $row->id, 'counter' => $counter, 'prenom' => $row->prenom, 'nom' => $row->nom,
                    'categorie' => $row->categorie, 'club' => $row->club, 'country_code' => $row->country_code,
                    'categories' => $row->categories, 'clubs' => $row->clubs, 'nb_inscriptions' => (int)$row->nb_inscriptions,
                    'payment_status' => $row->payment_status, 'payment_method' => $row->payment_method,
                    'cibles_departs' => $row->cibles_departs, 'target_numbers' => $row->target_numbers,
                    'departs' => extraireDeparts($row->target_numbers)
                ];
            }
            
            $sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'prenom';
            $sortDirection = isset($_GET['dir']) ? $_GET['dir'] : 'asc';
            
            usort($archersData, function($a, $b) use ($sortColumn, $sortDirection, $tarifs, $organizerClubCode) {
                $aValue = ''; $bValue = '';
                switch($sortColumn) {
                    case 'counter': $aValue = $a['counter']; $bValue = $b['counter']; break;
                    case 'prenom': $aValue = strtolower($a['prenom']); $bValue = strtolower($b['prenom']); break;
                    case 'nom': $aValue = strtolower($a['nom']); $bValue = strtolower($b['nom']); break;
                    case 'club': $aValue = strtolower($a['club']); $bValue = strtolower($b['club']); break;
                    case 'categorie': $aValue = strtolower($a['categorie']); $bValue = strtolower($b['categorie']); break;
                    case 'montant':
                        $aValue = calculerPrix($a['country_code'], $a['categorie'], $a['nb_inscriptions'], $tarifs, $organizerClubCode);
                        $bValue = calculerPrix($b['country_code'], $b['categorie'], $b['nb_inscriptions'], $tarifs, $organizerClubCode);
                        break;
                    case 'payment_status': $aValue = $a['payment_status']; $bValue = $b['payment_status']; break;
                    default: $aValue = strtolower($a['prenom']); $bValue = strtolower($b['prenom']);
                }
                if ($sortDirection === 'asc') return $aValue > $bValue ? 1 : ($aValue < $bValue ? -1 : 0);
                else return $aValue < $bValue ? 1 : ($aValue > $bValue ? -1 : 0);
            });
            
            echo '<table id="archersTable">';
            echo '<thead><tr>';
            echo '<th data-sort="counter">#</th>';
            echo '<th class="action-cell">Actions</th>';
            echo '<th data-sort="prenom">Nom</th>';
            echo '<th data-sort="nom">Prénom</th>';
            echo '<th data-sort="club">Club</th>';
            echo '<th data-sort="categorie">Catégorie</th>';
            echo '<th data-sort="montant">Montant (€)</th>';
            echo '<th data-sort="cible_depart">Départ / Cible</th>';
            echo '<th data-sort="payment_status">Statut Paiement</th>';
            echo '<th data-sort="payment_method">Moyen de paiement</th>';
            echo '</tr></thead>';
            echo '<tbody id="archersTableBody">';
            
            $displayCounter = 0;
            foreach ($archersData as $archer) {
                $display = true;
                if ($filters['club'] !== 'all' && $filters['club'] !== '' && stripos($archer['club'], $filters['club']) === false) $display = false;
                if ($display && $filters['category'] !== 'all' && $filters['category'] !== '' && stripos($archer['categorie'], $filters['category']) === false) $display = false;
                
                if ($display && $filters['departs'] !== 'all') {
                    $filter_depart = (int)$filters['departs'];
                    if ($filter_depart == 4) {
                        $has_depart_4plus = false;
                        foreach ($archer['departs'] as $depart) if ($depart >= 4) { $has_depart_4plus = true; break; }
                        if (!$has_depart_4plus) $display = false;
                    } else {
                        if (!in_array($filter_depart, $archer['departs'])) $display = false;
                    }
                }
                
                if ($display && $filters['payment'] !== 'all') {
                    $is_paid = ($archer['payment_status'] == 1);
                    if (($filters['payment'] === 'paid' && !$is_paid) || ($filters['payment'] === 'unpaid' && $is_paid)) $display = false;
                }
                
                if ($display) {
                    $displayCounter++;
                    $nb_inscriptions = (int)$archer['nb_inscriptions'];
                    
                    $categorie_display = htmlspecialchars($archer['categorie']);
                    if ($archer['categories'] && strpos($archer['categories'], ',') !== false) $categorie_display = '<span class="categorie-badge" title="' . htmlspecialchars($archer['categories']) . '">Multiple</span>';
                    else $categorie_display = '<span class="categorie-badge">' . htmlspecialchars($archer['categorie']) . '</span>';
                    
                    $club_display = '-';
                    if ($archer['club']) {
                        $club_name_clean = htmlspecialchars($archer['club']);
                        $clubInvoiceUrl = $_SERVER['PHP_SELF'] . '?club_invoice=1&club_name=' . urlencode($archer['club']);
                        foreach ($filters as $key => $value) if ($value !== 'all') $clubInvoiceUrl .= "&{$key}_filter=" . urlencode($value);
                        $clubInvoiceUrl .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
                        
                        if ($archer['clubs'] && strpos($archer['clubs'], ',') !== false) $club_display = '<span class="club-badge" title="' . htmlspecialchars($archer['clubs']) . '">Multiple</span>';
                        else $club_display = '<span class="club-badge" title="' . htmlspecialchars($archer['country_code']) . '">' . $club_name_clean . '</span> <a href="' . $clubInvoiceUrl . '" class="invoice-button club-invoice-btn" title="Facture pour tout le club">📄 Club</a>';
                    }
                    
                    $montant = calculerPrix($archer['country_code'], $archer['categorie'], $nb_inscriptions, $tarifs, $organizerClubCode);
                    $totalPrix += $montant; $filteredTotal += $montant;
                    
                    $montant_class = 'montant-badge';
                    $montant_title = "Tarif standard";
                    if ($archer['country_code'] == $organizerClubCode) {
                        $montant_class = 'montant-badge montant-organizer';
                        $montant_title = "Tarif club organisateur";
                        $totalOrganizer += $montant; $countOrganizer++;
                    } else { $totalAutres += $montant; $countAutres++; }
                    
                    $cible_display_value = 'Non affecté';
                    $cible_display_html = '<span class="target-warning">Non affecté</span>';
                    if (!empty($archer['cibles_departs']) && $archer['cibles_departs'] != 'D? - ?') {
                        $cible_display_value = $archer['cibles_departs'];
                        $cible_display_html = '';
                        foreach (explode('; ', $archer['cibles_departs']) as $affectation) if (!empty(trim($affectation))) $cible_display_html .= '<span class="target-badge">' . htmlspecialchars($affectation) . '</span> ';
                    }
                    
                    $is_paid = ($archer['payment_status'] == 1);
                    $invoiceUrl = $_SERVER['PHP_SELF'] . '?invoice=1&archer_id=' . $archer['id'];
                    foreach ($filters as $key => $value) if ($value !== 'all') $invoiceUrl .= "&{$key}_filter=" . urlencode($value);
                    $invoiceUrl .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
                    $invoice_button = '<a href="' . $invoiceUrl . '" class="invoice-button" title="Générer la facture pour cet archer">📄 Facture</a>';
                    
                    $filterParams = '';
                    foreach ($filters as $key => $value) if ($value !== 'all') $filterParams .= '<input type="hidden" name="' . $key . '_filter" value="' . htmlspecialchars($value) . '">';
                    $sortFields = '<input type="hidden" name="sort_column" value="' . htmlspecialchars($currentSort) . '"><input type="hidden" name="sort_direction" value="' . htmlspecialchars($currentDir) . '">';
                    
                    if ($is_paid) {
                        $countPaye++; $totalPaye += $montant;
                        $status_class = 'status-paid'; $status_text = 'Payé';
                        $payment_button = '<form method="POST" style="display:inline;" onsubmit="saveScrollPositionAndArcher(' . $archer['id'] . ');">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="unvalidate">' . $sortFields . $filterParams . '
                                            <button type="submit" class="payment-button unvalidate" onclick="return confirm(\'Êtes-vous sûr de vouloir marquer le paiement de ' . htmlspecialchars($archer['prenom'] . ' ' . $archer['nom'], ENT_QUOTES) . ' comme non acquitté ?\')">Annuler</button>
                                          </form>';
                    } else {
                        $countNonPaye++; $totalNonPaye += $montant;
                        $status_class = 'status-unpaid'; $status_text = 'Non payé';
                        $payment_button = '<form method="POST" style="display:inline;" onsubmit="saveScrollPositionAndArcher(' . $archer['id'] . ');">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="validate">' . $sortFields . $filterParams . '
                                            <select name="payment_method" required style="font-size:12px; padding:4px 6px; margin-right:5px; border:1px solid #ccc; border-radius:3px;">
                                                <option value="ESPECE">Espèce</option><option value="CHEQUE">Chèque</option><option value="VIREMENT">Virement</option><option value="GRATUIT">Gratuit</option>
                                            </select>
                                            <button type="submit" class="payment-button validate" onclick="return confirmPayment(this, \'' . htmlspecialchars($archer['prenom'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['nom'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['club'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['categorie'], ENT_QUOTES) . '\', \'' . $montant . '\', \'' . htmlspecialchars($cible_display_value, ENT_QUOTES) . '\')">Valider</button>
                                          </form>';
                    }
                    
                    $highlightClass = ($lastArcherId && $lastArcherId == $archer['id']) ? 'highlight-row' : '';
                    echo '<tr data-archer-id="' . $archer['id'] . '" data-counter="' . $archer['counter'] . '" 
                              data-prenom="' . htmlspecialchars($archer['prenom']) . '" 
                              data-nom="' . htmlspecialchars($archer['nom']) . '" 
                              data-club="' . htmlspecialchars($archer['club']) . '" 
                              data-categorie="' . htmlspecialchars($archer['categorie']) . '" 
                              data-nb-inscriptions="' . $nb_inscriptions . '" 
                              data-montant="' . $montant . '" 
                              data-cible-depart="' . htmlspecialchars($cible_display_value) . '"
                              data-payment-status="' . ($is_paid ? 'paid' : 'unpaid') . '"
                              class="' . $highlightClass . '">';
                    echo '<td style="text-align: center;">' . $displayCounter . '</td>';
                    echo '<td class="action-cell"><div class="action-buttons">' . $payment_button . $invoice_button . '</div></td>';
                    echo '<td>' . htmlspecialchars($archer['prenom']) . '</td>';
                    echo '<td>' . htmlspecialchars($archer['nom']) . '</td>';
                    echo '<td>' . $club_display . '</td>';
                    echo '<td>' . $categorie_display . '</td>';
                    echo '<td><span class="' . $montant_class . '" title="' . $montant_title . '">' . $montant . ' €</span></td>';
                    echo '<td class="target-cell">' . $cible_display_html . '</td>';
                    echo '<td><span class="payment-status ' . $status_class . '">' . $status_text . '</span></td>';
                    echo '<td>' . (!empty($archer['payment_method']) ? htmlspecialchars($archer['payment_method']) : '-') . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table>';
            echo '<div class="filter-summary" style="margin-top: 20px;">';
            echo '<strong>Résumé :</strong> ' . $displayCounter . ' archer(s) affiché(s) sur ' . $totalArchers . ' | ';
            echo 'Total montant : ' . $filteredTotal . ' € | ';
            echo 'Club organisateur (' . $countOrganizer . ') : ' . $totalOrganizer . ' € | ';
            echo 'Autres clubs (' . $countAutres . ') : ' . $totalAutres . ' € | ';
            echo 'Payés : ' . $countPaye . ' (' . $totalPaye . ' €) | ';
            echo 'Non payés : ' . $countNonPaye . ' (' . $totalNonPaye . ' €)';
            echo '</div>';
        } else {
            echo '<p style="text-align: center; color: #666; font-style: italic;">Aucun archer trouvé dans la base de données.</p>';
        }
    } catch (Exception $e) {
        echo '<div class="error"><strong>Erreur :</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
</div>

<?php if ($notificationHtml): ?>
<div id="notification-wrapper"><?php echo $notificationHtml; ?></div>
<?php endif; ?>

<script>
    function saveScrollPositionAndArcher(archerId) {
        localStorage.setItem('scrollPosition', window.scrollY);
        localStorage.setItem('lastProcessedArcher', archerId);
    }
    
    function restoreScrollPositionAndHighlight() {
        const scrollPosition = localStorage.getItem('scrollPosition');
        const lastArcher = localStorage.getItem('lastProcessedArcher');
        if (scrollPosition !== null) { setTimeout(function() { window.scrollTo(0, parseInt(scrollPosition)); localStorage.removeItem('scrollPosition'); }, 100); }
        if (lastArcher !== null) {
            setTimeout(function() {
                const rows = document.querySelectorAll('#archersTableBody tr');
                for (let row of rows) {
                    if (row.getAttribute('data-archer-id') == lastArcher) {
                        row.classList.add('highlight-row');
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => row.classList.remove('highlight-row'), 2000);
                        break;
                    }
                }
                localStorage.removeItem('lastProcessedArcher');
            }, 200);
        }
    }
    
    function resetFilters() {
        document.getElementById('club_filter').value = 'all';
        document.getElementById('category_filter').value = 'all';
        document.getElementById('departs_filter').value = 'all';
        document.getElementById('payment_filter').value = 'all';
        document.getElementById('filterSort').value = 'prenom';
        document.getElementById('filterDir').value = 'asc';
        document.getElementById('filterForm').submit();
    }
    
    function confirmPayment(button, prenom, nom, club, categorie, montant, cibleDepart) {
        let paymentMethod = 'Non spécifié', archerId = null;
        try {
            const form = button.form || button.closest('form');
            if (form) {
                const select = form.querySelector('select[name="payment_method"]');
                if (select && select.value) paymentMethod = select.value;
                const idInput = form.querySelector('input[name="archer_id"]');
                if (idInput) archerId = idInput.value;
            }
        } catch(e) { console.error(e); }
        
        const message = 'Confirmer le paiement pour :\n\n• Prénom : ' + prenom + '\n• Nom : ' + nom + '\n• Club : ' + (club || '-') + '\n• Catégorie : ' + categorie + '\n• Montant : ' + montant + ' €\n• Départ / Cible : ' + cibleDepart + '\n• Moyen de paiement : ' + paymentMethod + '\n\nÊtes-vous sûr de vouloir valider ce paiement ?';
        if (confirm(message)) { if (archerId) saveScrollPositionAndArcher(archerId); const form = button.form || button.closest('form'); if (form) form.submit(); return true; }
        return false;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        restoreScrollPositionAndHighlight();
        
        const notification = document.querySelector('#notification-wrapper .notification');
        if (notification) {
            document.body.appendChild(notification.cloneNode(true));
            document.getElementById('notification-wrapper').remove();
            setTimeout(() => { document.querySelectorAll('.notification').forEach(n => { if (n.parentNode) n.parentNode.removeChild(n); }); }, 3000);
        }
        
        const table = document.getElementById('archersTable');
        const tbody = document.getElementById('archersTableBody');
        const headers = table.querySelectorAll('th[data-sort]');
        const urlParams = new URLSearchParams(window.location.search);
        let currentSort = { column: urlParams.get('sort') || 'prenom', direction: urlParams.get('dir') || 'asc' };
        
        headers.forEach(header => {
            const column = header.getAttribute('data-sort');
            if (column === currentSort.column) { header.classList.remove('sort-asc', 'sort-desc'); header.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc'); }
        });
        
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                let direction = 'asc';
                if (currentSort.column === column && currentSort.direction === 'asc') direction = 'desc';
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                this.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
                currentSort = { column, direction };
                document.getElementById('filterSort').value = column;
                document.getElementById('filterDir').value = direction;
                
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    let aValue, bValue;
                    switch(column) {
                        case 'counter': aValue = parseInt(a.getAttribute('data-counter')); bValue = parseInt(b.getAttribute('data-counter')); break;
                        case 'prenom': aValue = a.getAttribute('data-prenom').toLowerCase(); bValue = b.getAttribute('data-prenom').toLowerCase(); break;
                        case 'nom': aValue = a.getAttribute('data-nom').toLowerCase(); bValue = b.getAttribute('data-nom').toLowerCase(); break;
                        case 'club': aValue = a.getAttribute('data-club').toLowerCase(); bValue = b.getAttribute('data-club').toLowerCase(); break;
                        case 'categorie': aValue = a.getAttribute('data-categorie').toLowerCase(); bValue = b.getAttribute('data-categorie').toLowerCase(); break;
                        case 'montant': aValue = parseFloat(a.getAttribute('data-montant')); bValue = parseFloat(b.getAttribute('data-montant')); break;
                        case 'payment_status': aValue = a.getAttribute('data-payment-status'); bValue = b.getAttribute('data-payment-status'); break;
                        case 'cible_depart':
                            let aMatches = a.getAttribute('data-cible-depart').match(/\d+/g) || [0];
                            let bMatches = b.getAttribute('data-cible-depart').match(/\d+/g) || [0];
                            aValue = parseInt(aMatches[0]) || 0; bValue = parseInt(bMatches[0]) || 0;
                            break;
                        default: return 0;
                    }
                    if (direction === 'asc') return aValue > bValue ? 1 : (aValue < bValue ? -1 : 0);
                    else return aValue < bValue ? 1 : (aValue > bValue ? -1 : 0);
                });
                rows.forEach(row => tbody.appendChild(row));
                const rows2 = tbody.querySelectorAll('tr');
                rows2.forEach((row, index) => { row.querySelector('td:first-child').textContent = index + 1; });
                
                const forms = document.querySelectorAll('form[method="POST"]');
                forms.forEach(form => {
                    let s = form.querySelector('input[name="sort_column"]');
                    let d = form.querySelector('input[name="sort_direction"]');
                    if (s) s.value = column;
                    if (d) d.value = direction;
                });
                const url = new URL(window.location);
                url.searchParams.set('sort', column);
                url.searchParams.set('dir', direction);
                window.history.pushState({}, '', url);
            });
        });
        
        const forms = document.querySelectorAll('form[method="POST"]');
        forms.forEach(form => {
            let s = form.querySelector('input[name="sort_column"]');
            let d = form.querySelector('input[name="sort_direction"]');
            if (s) s.value = currentSort.column;
            if (d) d.value = currentSort.direction;
        });
    });
    
    function exportToPDF() {
        const urlParams = new URLSearchParams(window.location.search);
        const club = urlParams.get('club_filter') || 'all';
        const category = urlParams.get('category_filter') || 'all';
        const payment = urlParams.get('payment_filter') || 'all';
        window.open('export_pdf.php?club=' + club + '&category=' + category + '&payment=' + payment, '_blank');
    }
</script>

<?php include('Common/Templates/tail.php'); ?>