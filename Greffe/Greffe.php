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
 * 
 * Dernière modification: 2026-01-07 par Laurent Petroff
 * Ajout de la Facture
 * Impression et sauvegarde
 *
 * Greffe.php
 * Page simple pour lister les archers inscrits pour faire le greffe.
 * Vous pouvez modifier le prix et demander à Deepseek une modification en fonction de vos besoins.
 *
 */


// Configuration de base
define('debug', false);

// Inclure les fichiers nécessaires
require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

// Vérifier la session et les permissions
CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly); // AJOUT: Vérification des permissions

// Vérifier si on est en mode génération de facture individuelle
$is_invoice_mode = isset($_GET['invoice']) && $_GET['invoice'] == '1' && isset($_GET['archer_id']);

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

// Récupérer TOUTES les infos du tournoi en une seule requête (OPTIMISÉ)
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
    
    // Si ToComDescr est vide, on essaie de récupérer le nom depuis Countries
    if (empty($organizerClubName) && $organizerClubCode) {
        $organizerNameQuery = "SELECT CoName FROM Countries WHERE CoCode = '" . $organizerClubCode . "' AND CoTournament = $TourId";
        $organizerNameRs = safe_r_sql($organizerNameQuery);
        if ($organizerName = safe_fetch($organizerNameRs)) {
            $organizerClubName = $organizerName->CoName;
        }
    }
}

// Traitement de la validation du paiement
if (isset($_POST['validate_payment']) && isset($_POST['archer_id'])) {
    $archerId = intval($_POST['archer_id']);
    $action = $_POST['validate_payment'];
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'ESPECE';
    
    // Récupérer les filtres actuels et le tri pour les passer dans la redirection
    $filterParams = '';
    foreach ($filters as $key => $value) {
        if ($value !== 'all') {
            $filterParams .= "&{$key}_filter=" . urlencode($value);
        }
    }
    
    // Récupérer le tri actuel depuis POST ou GET
    $sortColumn = isset($_POST['sort_column']) ? $_POST['sort_column'] : (isset($_GET['sort']) ? $_GET['sort'] : 'nom');
    $sortDirection = isset($_POST['sort_direction']) ? $_POST['sort_direction'] : (isset($_GET['dir']) ? $_GET['dir'] : 'asc');
    
    // Ajouter les paramètres de tri
    $filterParams .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
    
    // Déterminer le statut de paiement pour QuNotes
    if ($action == 'validate') {
        $paymentStatus = 'PAYE|' . $paymentMethod;
    } else {
        $paymentStatus = 'NON_PAYE';
    }
    
    // Mettre à jour QuNotes dans Qualifications pour toutes les qualifications de l'archer
    $updateQuery = "UPDATE Qualifications 
                    SET QuNotes = '$paymentStatus' 
                    WHERE QuId = $archerId";
    
    $result = safe_w_sql($updateQuery);
    
    if ($result) {
        $_SESSION['payment_message'] = ($paymentStatus == 'PAYE') 
            ? 'Paiement validé avec succès !' 
            : 'Paiement marqué comme non acquitté.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['payment_message'] = 'Erreur lors de la mise à jour.';
        $_SESSION['message_type'] = 'error';
    }
    
    // Rediriger pour éviter la resoumission du formulaire, en conservant les filtres et le tri
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
    
    // Parser les tarifs au format OJA1=8; OJA2=6; etc.
    // Note: O = autres clubs, P = club organisateur
    preg_match_all('/(OJA1|OJA2|OAA1|OAA2|PJA1|PJA2|PAA1|PAA2)\s*=\s*(\d+)\s*;/', $contenu, $matches, PREG_SET_ORDER);
    
    // Initialiser tous les codes à 0
    $codes = ['OJA1', 'OJA2', 'OAA1', 'OAA2', 'PJA1', 'PJA2', 'PAA1', 'PAA2'];
    foreach ($codes as $code) {
        $tarifsParsed[$code] = 0;
    }
    
    // Remplacer par les valeurs du fichier
    foreach ($matches as $match) {
        if (isset($tarifsParsed[$match[1]])) {
            $tarifsParsed[$match[1]] = (int)$match[2];
        }
    }
    
    // Structurer les tarifs
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
    
    // Ajouter le code et nom du club organisateur pour référence
    $tarifs['organizer_club_code'] = $organizerClubCode;
    $tarifs['organizer_club_name'] = $organizerClubName;
    
    return $tarifs;
}

// Fonction pour déterminer si c'est un jeune ou un adulte
function getAgeCategory($categorie_code) {
    // Catégories jeunes selon le fichier Prix.txt
    $jeunes_categories = ['U11', 'U13', 'U15', 'U18'];
    $adultes_categories = ['U21', 'S1', 'S2', 'S3'];
    
    // Vérifier si c'est une catégorie jeune
    foreach ($jeunes_categories as $cat) {
        if (stripos($categorie_code, $cat) !== false) {
            return 'jeunes';
        }
    }
    
    // Vérifier si c'est une catégorie adulte
    foreach ($adultes_categories as $cat) {
        if (stripos($categorie_code, $cat) !== false) {
            return 'adultes';
        }
    }
    
    // Par défaut, considérer comme adulte
    return 'adultes';
}

// Fonction pour calculer le prix total
function calculerPrix($club_country_code, $categorie_code, $nb_departs, $tarifs, $organizerClubCode) {
    // Déterminer si c'est le club organisateur (comparaison par CoCode)
    $is_organizer = ($club_country_code == $organizerClubCode);
    
    // Déterminer la catégorie d'âge
    $age_category = getAgeCategory($categorie_code);
    
    // Déterminer la clé tarifaire (1 ou 2+ départs)
    $tarif_key = ($nb_departs == 1) ? 1 : 2;
    
    // Récupérer le prix
    if ($is_organizer) {
        return $tarifs['organizer'][$age_category][$tarif_key];
    } else {
        return $tarifs['clubs_autres'][$age_category][$tarif_key];
    }
}

// Fonction pour calculer le prix par départ
function calculerPrixParDepart($club_country_code, $categorie_code, $nb_departs, $tarifs, $organizerClubCode) {
    // Déterminer si c'est le club organisateur (comparaison par CoCode)
    $is_organizer = ($club_country_code == $organizerClubCode);
    
    // Déterminer la catégorie d'âge
    $age_category = getAgeCategory($categorie_code);
    
    // Récupérer les tarifs de base
    if ($is_organizer) {
        $prix_1_depart = $tarifs['organizer'][$age_category][1];
        $prix_2_departs = $tarifs['organizer'][$age_category][2];
    } else {
        $prix_1_depart = $tarifs['clubs_autres'][$age_category][1];
        $prix_2_departs = $tarifs['clubs_autres'][$age_category][2];
    }
    
    // Calcul du supplément pour le 2ème départ
    $supplement_2eme_depart = $prix_2_departs - $prix_1_depart;
    
    // Calculer les prix par départ
    $prix_par_depart = [];
    $total = 0;
    
    if ($nb_departs == 1) {
        // 1 départ seulement
        $prix_par_depart[1] = $prix_1_depart;
        $total = $prix_1_depart;
    } elseif ($nb_departs == 2) {
        // 2 départs - premier à prix normal, deuxième au supplément
        $prix_par_depart[1] = $prix_1_depart;
        $prix_par_depart[2] = $supplement_2eme_depart;
        $total = $prix_1_depart + $supplement_2eme_depart;
    } else {
        // 3 départs ou plus
        $prix_par_depart[1] = $prix_1_depart;
        $total = $prix_1_depart;
        
        // Départs supplémentaires au tarif supplément
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
    
    // Format: "1001A" -> "D1 - 001A"
    if (strlen($target_no) >= 5) {
        $depart = substr($target_no, 0, 1);
        $cible = substr($target_no, 1, 3);
        $position = substr($target_no, 4, 1);
        return "D{$depart} - {$cible}{$position}";
    }
    
    return htmlspecialchars($target_no);
}

// Fonction pour extraire les numéros de départ uniques à partir des numéros de cible
function extraireDeparts($target_numbers) {
    if (empty($target_numbers)) {
        return [];
    }
    
    $departs = [];
    $targets = explode(', ', $target_numbers);
    
    foreach ($targets as $target) {
        $target = trim($target);
        if (!empty($target) && $target != '0') {
            // Extraire le premier caractère (le numéro de départ)
            $depart = substr($target, 0, 1);
            if (is_numeric($depart) && $depart > 0) {
                $departs[] = (int)$depart;
            }
        }
    }
    
    return array_unique($departs);
}

// Lire les tarifs avec le club organisateur
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
    
    // Récupérer d'abord l'archer depuis la liste principale
    $archerInfo = null;
    
    // Exécuter la même requête que la page principale
    $mainQuery = "SELECT 
        MIN(e.EnId) as id,
        e.EnFirstName as prenom,
        e.EnName as nom,
        CONCAT(e.EnFirstName, ' ', e.EnName) as nom_complet,
        MIN(CONCAT(e.EnDivision, e.EnClass)) as categorie,
        c.CoName as club,
        c.CoCode as country_code,
        -- Récupérer le statut de paiement depuis QuNotes
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
        // Extraire les départs
        $departs_archer = extraireDeparts($archer->target_numbers);
        
        // Calculer le montant avec détail par départ (comparaison par CoCode)
        $calculPrix = calculerPrixParDepart($archer->country_code, $archer->categorie, $archer->nb_inscriptions, $tarifs, $organizerClubCode);
        $montant = $calculPrix['total'];
        
        // Parser les affectations de cible/départ
        $affectations = [];
        if (!empty($archer->cibles_departs) && $archer->cibles_departs != 'D? - ?') {
            $affectations_list = explode('; ', $archer->cibles_departs);
            foreach ($affectations_list as $affectation) {
                if (!empty(trim($affectation))) {
                    $affectations[] = $affectation;
                }
            }
        }
        
        // Afficher la facture individuelle en HTML
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture - <?php echo htmlspecialchars($archer->prenom . ' ' . $archer->nom); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 40px;
                    color: #333;
                    background-color: white;
                }
                
                .invoice-container {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 30px;
                    background-color: white;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                
                .invoice-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #2c5f2d;
                    padding-bottom: 20px;
                }
                
                .invoice-title {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2c5f2d;
                    margin-bottom: 10px;
                }
                
                .invoice-subtitle {
                    font-size: 18px;
                    color: #666;
                    margin-bottom: 20px;
                }
                
                .invoice-info {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 5px;
                }
                
                .invoice-section {
                    margin: 25px 0;
                }
                
                .invoice-section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2c5f2d;
                    margin-bottom: 10px;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 5px;
                }
                
                .invoice-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                    font-size: 14px;
                }
                
                .invoice-table th {
                    background-color: #f2f2f2;
                    color: #333;
                    padding: 10px;
                    text-align: left;
                    border: 1px solid #ddd;
                    font-weight: bold;
                }
                
                .invoice-table td {
                    padding: 10px;
                    border: 1px solid #ddd;
                }
                
                .invoice-total {
                    margin-top: 30px;
                    text-align: right;
                    font-size: 18px;
                    font-weight: bold;
                    border-top: 2px solid #2c5f2d;
                    padding-top: 15px;
                }
                
                .invoice-footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    padding-top: 20px;
                }
                
                .print-button {
                    text-align: center;
                    margin: 20px 0;
                }
                
                .print-button button {
                    background-color: #2c5f2d;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    margin: 0 10px;
                }
                
                .print-button button:hover {
                    background-color: #245325;
                }
                
                .info-row {
                    display: flex;
                    margin-bottom: 8px;
                }
                
                .info-label {
                    width: 160px;
                    font-weight: bold;
                    color: #555;
                }
                
                .info-value {
                    flex: 1;
                }
                
                .payment-status {
                    display: inline-block;
                    padding: 3px 10px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                }
                
                .status-paid {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                
                .status-unpaid {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                
                .invoice-logo img {
                    max-width: 150px;
                    max-height: 100px;
                    object-fit: contain;
                }
                
                .invoice-logo-placeholder {
                    width: 150px;
                    height: 80px;
                    background-color: #f0f0f0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    color: #666;
                    font-size: 12px;
                }
                
                @media print {
                    .print-button {
                        display: none;
                    }
                    
                    body {
                        margin: 0;
                        padding: 10px;
                    }
                    
                    .invoice-container {
                        border: none;
                        box-shadow: none;
                        padding: 0;
                    }
                    
                    .invoice-header-logo-container {
                        display: flex;
                        page-break-inside: avoid;
                    }
                    
                    .invoice-logo img {
                        max-width: 120px;
                        max-height: 80px;
                    }
                    
                    .invoice-logo-placeholder {
                        width: 120px;
                        height: 60px;
                    }
                }
                
                .invoice-header-logo-container {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    flex-wrap: wrap;
                }

                .invoice-logo {
                    flex: 0 0 auto;
                    max-width: 200px;
                }

                .invoice-header-info {
                    flex: 1 1 auto;
                    text-align: right;
                    min-width: 300px;
                }

            </style>
        </head>
        <body>
            <div class="print-button">
                <button onclick="window.print()">🖨️ Imprimer la facture</button>
                <button onclick="saveIndividualInvoice()">💾 Sauvegarder la facture</button>
                <button onclick="window.history.back()">← Retour à la liste</button>
            </div>
            
            <div class="invoice-container">
                <table class="invoice-header" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <!-- Colonne logo -->
                        <td width="30%" valign="middle" align="left" class="invoice-logo-container">
                            <div class="invoice-logo">
                            <?php if (!empty($organizerLogo)): ?>
                                <?php 
                                // Nettoyer et convertir en base64
                                $cleanLogo = ltrim($organizerLogo, "\x00..\x1F");
                                $base64Image = 'data:image/jpeg;base64,' . base64_encode($cleanLogo);
                                ?>
                                <img width="150" src="<?php echo htmlspecialchars($base64Image); ?>" 
                                     alt="<?php echo htmlspecialchars($tournamentName ?: 'Tournoi'); ?>" 
                                     style="max-height: 100px; object-fit: contain;" />
                            <?php else: ?>
                                <div class="invoice-logo-placeholder">
                                    <span><?php echo htmlspecialchars(substr($tournamentName, 0, 15) ?: 'Tournoi'); ?></span>
                                </div>
                            <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- Colonne informations -->
                        <td width="70%" valign="middle" align="center">
                            <div class="invoice-title" style="margin-bottom: 10px;">FACTURE INDIVIDUELLE</div>
                            <div class="invoice-subtitle" style="margin-bottom: 8px;">
                                <?php echo htmlspecialchars($tournamentName ?: 'Tournoi de Tir à l\'Arc'); ?>
                            </div>
                            <div class="invoice-info" style="margin-bottom: 5px;">Date de génération : <?php echo date('d/m/Y H:i'); ?></div>
                            <div class="invoice-info">Numéro de facture : FAC-<?php echo date('Ymd'); ?>-<?php echo str_pad($archerId, 4, '0', STR_PAD_LEFT); ?></div>
                        </td>
                    </tr>
                </table>
                
                <div class="invoice-section">
                    <div class="invoice-section-title">Informations de l'Archer</div>
                    <div class="info-row">
                        <div class="info-label">Nom :</div>
                        <div class="info-value"><?php echo htmlspecialchars($archer->nom); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Prénom :</div>
                        <div class="info-value"><?php echo htmlspecialchars($archer->prenom); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Club :</div>
                        <div class="info-value"><?php echo htmlspecialchars($archer->club); ?> (Code: <?php echo htmlspecialchars($archer->country_code); ?>)</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Catégorie :</div>
                        <div class="info-value"><?php echo htmlspecialchars($archer->categorie); ?> (<?php echo $calculPrix['age_category']; ?>)</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Nombre de départs :</div>
                        <div class="info-value"><?php echo $archer->nb_inscriptions; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Statut paiement :</div>
                        <div class="info-value">
                            <span class="payment-status <?php echo ($archer->payment_status == 1) ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo ($archer->payment_status == 1) ? 'Payé' : 'Non payé'; ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($archer->payment_status == 1 && !empty($archer->payment_method)): ?>
<div class="info-row">
    <div class="info-label">Moyen de paiement</div>
    <div class="info-value">
        <strong><?php echo htmlspecialchars($archer->payment_method); ?></strong>
    </div>
</div>
<?php endif; ?>
                </div>
                
                <div class="invoice-section">
                    <div class="invoice-section-title">Détail des Départs et Prix</div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Départ</th>
                                <th>Cible</th>
                                <th>Position</th>
                                <th>Prix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // LIGNES DE DEBUG À AJOUTER ICI :
                            error_log("DEBUG FACTURE - Archer ID: " . $archerId);
                            error_log("DEBUG FACTURE - nb_inscriptions: " . $archer->nb_inscriptions);
                            error_log("DEBUG FACTURE - cibles_departs: " . $archer->cibles_departs);
                            
                            // Afficher aussi dans le HTML (commentaire)
                            echo "<!-- DEBUG: Archer ID: " . $archerId . " -->\n";
                            echo "<!-- DEBUG: nb_inscriptions: " . $archer->nb_inscriptions . " -->\n";
                            echo "<!-- DEBUG: cibles_departs: " . htmlspecialchars($archer->cibles_departs) . " -->\n";
                            echo "<!-- DEBUG: affectations count: " . (isset($affectations) ? count($affectations) : '0') . " -->\n";
                            echo "<!-- DEBUG: Club organisateur code: " . htmlspecialchars($organizerClubCode) . " -->\n";
                            echo "<!-- DEBUG: Club organisateur name: " . htmlspecialchars($organizerClubName) . " -->\n";
                            echo "<!-- DEBUG: Tournament name: " . htmlspecialchars($tournamentName) . " -->\n";
                            echo "<!-- DEBUG: Archer country code: " . htmlspecialchars($archer->country_code) . " -->\n";
                            
                            // CORRECTION: Afficher tous les départs, même sans affectation
                            $total_facture = 0;
                            $nb_departs_total = $archer->nb_inscriptions;
                            
                            // Si l'archer a des affectations de cible/départ
                            if (!empty($affectations)) {
                                $affectation_index = 0;
                                
                                for ($depart_numero = 1; $depart_numero <= $nb_departs_total; $depart_numero++) {
                                    $prix_depart = isset($calculPrix['prix_par_depart'][$depart_numero]) 
                                        ? $calculPrix['prix_par_depart'][$depart_numero] 
                                        : $calculPrix['prix_par_depart'][min($depart_numero, 2)];
                                    
                                    echo '<tr>';
                                    
                                    // Afficher le numéro de départ
                                    echo '<td>Départ ' . $depart_numero . '</td>';
                                    
                                    // Afficher la cible si disponible
                                    if (isset($affectations[$affectation_index])) {
                                        $affectation = trim($affectations[$affectation_index]);
                                        if (!empty($affectation)) {
                                            $parts = explode(' - ', $affectation);
                                            if (count($parts) == 2) {
                                                $cible_position = $parts[1];
                                                $cible = substr($cible_position, 0, 3);
                                                $position = substr($cible_position, 3);
                                                
                                                echo '<td>Cible ' . htmlspecialchars($cible) . '</td>';
                                                echo '<td>Position ' . htmlspecialchars($position) . '</td>';
                                            } else {
                                                echo '<td colspan="2">' . htmlspecialchars($affectation) . '</td>';
                                            }
                                            $affectation_index++;
                                        } else {
                                            echo '<td colspan="2"><em>Non affecté</em></td>';
                                        }
                                    } else {
                                        echo '<td colspan="2"><em>Non affecté</em></td>';
                                    }
                                    
                                    echo '<td>' . number_format($prix_depart, 2) . ' €</td>';
                                    echo '</tr>';
                                    
                                    $total_facture += $prix_depart;
                                }
                            } else {
                                // Si pas d'affectations du tout
                                for ($depart_numero = 1; $depart_numero <= $nb_departs_total; $depart_numero++) {
                                    $prix_depart = isset($calculPrix['prix_par_depart'][$depart_numero]) 
                                        ? $calculPrix['prix_par_depart'][$depart_numero] 
                                        : $calculPrix['prix_par_depart'][min($depart_numero, 2)];
                                    
                                    echo '<tr>';
                                    echo '<td>Départ ' . $depart_numero . '</td>';
                                    echo '<td colspan="2"><em>Non affecté</em></td>';
                                    echo '<td>' . number_format($prix_depart, 2) . ' €</td>';
                                    echo '</tr>';
                                    
                                    $total_facture += $prix_depart;
                                }
                            }
                            
                            // Afficher la ligne de total
                            echo '<tr style="font-weight: bold; background-color: #f8f9fa;">';
                            echo '<td colspan="3" style="text-align: right;">TOTAL :</td>';
                            echo '<td>' . number_format($total_facture, 2) . ' €</td>';
                            echo '</tr>';
                            ?>
                        </tbody>
                    </table>
                </div>               
                
                <div class="invoice-total">
                    <strong>MONTANT TOTAL : <?php echo number_format($montant, 2); ?> €</strong>
                </div>
                
                <div class="invoice-footer">
                    <div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="vertical-align: top;">
                                    <p style="font-size: 11px; line-height: 1.4; color: #666; margin: 0 0 10px 0;text-align: left;">
                                        <strong>Association loi 1901 - TVA non applicable, Article 293 B du Code Général des Impôts</strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <!-- Colonne gauche (alignée à gauche) -->
                                            <td style="vertical-align: top; width: 50%; text-align: left;">
                                                <div style="font-size: 11px; line-height: 1.4; color: #666;">
                                                    <p style="margin: 0 0 5px 0;">
                                                        <strong>Club organisateur :</strong> <?php echo htmlspecialchars($organizerClubName ?: $organizerClubCode); ?><br>
                                                    </p>
                                                    
                                                    <?php if (!empty($toWhere)): ?>
                                                    <p style="margin: 0 0 5px 0;">
                                                        <strong>Adresse :</strong> <?php echo htmlspecialchars($toWhere); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($toVenue)): ?>
                                                    <p style="margin: 0 0 5px 0;">
                                                        <strong>Lieu :</strong> <?php echo htmlspecialchars($toVenue); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                            </td>
                                            
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <script>
                // Fonction pour sauvegarder la facture individuelle (version simple - serveur seulement)
                window.saveIndividualInvoice = function() {
                    console.log('Fonction saveIndividualInvoice appelée');
                    
                    // Afficher une indication de chargement
                    const saveBtn = document.querySelector('button[onclick="saveIndividualInvoice()"]');
                    const originalText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '⏳ Sauvegarde...';
                    saveBtn.disabled = true;
                    
                    try {
                        // Créer un clone de la facture
                        const invoiceContent = document.querySelector('.invoice-container').cloneNode(true);
                        
                        // Supprimer les boutons
                        const printButton = invoiceContent.querySelector('.print-button');
                        if (printButton) printButton.remove();
                        
                        // Récupérer les informations de l'archer depuis la page
                        const infoRows = document.querySelectorAll('.info-row');
                        let nom = '', prenom = '';
                        
                        infoRows.forEach(row => {
                            const label = row.querySelector('.info-label');
                                const value = row.querySelector('.info-value');
                                
                                if (!label || !value) return;
                                
                                const labelText = label.textContent.trim();
                                const valueText = value.textContent.trim();
                                
                                if (labelText.includes('Nom')) {
                                    const span = value.querySelector('span');
                                    nom = span ? span.textContent.trim() : valueText;
                                } else if (labelText.includes('Prénom')) {
                                    const span = value.querySelector('span');
                                    prenom = span ? span.textContent.trim() : valueText;
                                }
                            });
                            
                            // Si on n'a pas réussi à récupérer via les sélecteurs
                            if (!nom || !prenom) {
                                const title = document.title;
                                if (title.includes('-')) {
                                    const namePart = title.split('-')[1].trim();
                                    const names = namePart.split(' ');
                                    if (names.length >= 2) {
                                        prenom = names[0];
                                        nom = names.slice(1).join(' ');
                                    }
                                }
                            }
                            
                            // Créer un nom de fichier sécurisé
                            const now = new Date();
                            const dateStr = now.getFullYear() + 
                                           String(now.getMonth() + 1).padStart(2, '0') + 
                                           String(now.getDate()).padStart(2, '0') + '_' +
                                           String(now.getHours()).padStart(2, '0') + 
                                           String(now.getMinutes()).padStart(2, '0') + 
                                           String(now.getSeconds()).padStart(2, '0');
                            
                            // Nettoyer les noms pour le fichier
                            const safePrenom = (prenom || 'prenom').replace(/[^\w\s]/gi, '').replace(/\s+/g, '_');
                            const safeNom = (nom || 'nom').replace(/[^\w\s]/gi, '').replace(/\s+/g, '_');
                            const filename = `facture_${safePrenom}_${safeNom}_${dateStr}.html`;
                            
                            // Récupérer tous les styles de la page
                            const styles = Array.from(document.querySelectorAll('style'))
                                .map(style => style.innerHTML)
                                .join('\n');
                            
                            // Créer le HTML complet
                            const html = `<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Facture - ${prenom} ${nom}</title>
                <style>
                    ${styles}
                </style>
            </head>
            <body>
                ${invoiceContent.innerHTML}
            </body>
            </html>`;
                            
                            // Envoyer au serveur via fetch (AJAX)
                            fetch('save_invoice.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'html_content=' + encodeURIComponent(html) + '&filename=' + encodeURIComponent(filename)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showNotification(`✅ Facture sauvegardée :<br>${data.filename}`, 'success');
                                } else {
                                    throw new Error(data.message || 'Erreur inconnue');
                                }
                            })
                            .catch(error => {
                                console.error('Erreur:', error);
                                showNotification('❌ Erreur lors de la sauvegarde : ' . error.message, 'error');
                            })
                            .finally(() => {
                                // Restaurer le bouton
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            });
                            
                        } catch (error) {
                            console.error('Erreur:', error);
                            showNotification('❌ Erreur : ' . error.message, 'error');
                            
                            // Restaurer le bouton en cas d'erreur
                            saveBtn.innerHTML = originalText;
                            saveBtn.disabled = false;
                        }
                    };
                    
                    // Fonction utilitaire pour afficher des notifications
                    window.showNotification = function(message, type = 'success') {
                        // Supprimer toute notification existante
                        const existingNotifications = document.querySelectorAll('.custom-notification');
                        existingNotifications.forEach(notif => {
                            if (notif.parentNode) {
                                notif.parentNode.removeChild(notif);
                            }
                        });
                        
                        // Créer l'élément de notification
                        const notification = document.createElement('div');
                        notification.className = `custom-notification notification-${type}`;
                        notification.innerHTML = `
                            <div class="notification-content">${message}</div>
                        `;
                        
                        // Style pour la notification
                        notification.style.position = 'fixed';
                        notification.style.bottom = '20px';
                        notification.style.right = '20px';
                        notification.style.padding = '12px 20px';
                        notification.style.borderRadius = '5px';
                        notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                        notification.style.zIndex = '9999';
                        notification.style.maxWidth = '400px';
                        notification.style.fontSize = '14px';
                        notification.style.animation = 'slideIn 0.3s ease-out, fadeOut 0.3s ease-out 3.7s forwards';
                        notification.style.backgroundColor = type === 'success' ? '#d4edda' : 
                                                           type === 'error' ? '#f8d7da' : 
                                                           type === 'info' ? '#d1ecf1' : '#fefefe';
                        notification.style.color = type === 'success' ? '#155724' : 
                                                  type === 'error' ? '#721c24' : 
                                                  type === 'info' ? '#0c5460' : '#333';
                        notification.style.border = type === 'success' ? '1px solid #c3e6cb' : 
                                                   type === 'error' ? '1px solid #f5c6cb' : 
                                                   type === 'info' ? '1px solid #bee5eb' : '1px solid #ddd';
                        notification.style.borderLeft = type === 'success' ? '4px solid #28a745' : 
                                                       type === 'error' ? '4px solid #dc3545' : 
                                                       type === 'info' ? '4px solid #17a2b8' : '4px solid #6c757d';
                        
                        // Ajouter au body
                        document.body.appendChild(notification);
                        
                        // Supprimer la notification après l'animation
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.parentNode.removeChild(notification);
                            }
                        }, 4000);
                    };
                    
                    // Auto-imprimer si demandé
                    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
                    window.onload = function() {
                        window.print();
                    };
                    <?php endif; ?>
                </script>

            </body>
        </html>
        <?php
        exit();
    } else {
        // Archer non trouvé
        include('Common/Templates/head.php');
        echo '<div class="error" style="margin: 50px auto; max-width: 600px;">';
        echo '<strong>Erreur :</strong> Archer non trouvé.';
        echo '<br><br>';
        echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="filter-reset">← Retour à la liste</a>';
        echo '</div>';
        include('Common/Templates/tail.php');
        exit();
    }
}

// MODE NORMAL - Affichage de la page principale
include('Common/Templates/head.php');
?>

<style>
    /* Styles communs avec Verification.php */
    .Title {
        color: #2c5f2d;
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 15px;
        border-bottom: 2px solid #2c5f2d;
        padding-bottom: 10px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0px;
        font-size: 13px;
        border: 1px solid #ddd;
    }
    
    th {
        background-color: #2c5f2d;
        color: white;
        padding: 8px 10px;
        text-align: left;
        font-weight: bold;
        cursor: pointer;
        position: relative;
        user-select: none;
    }
    
    th:hover {
        background-color: #245325;
    }
    
    th.sort-asc::after {
        content: " ▲";
        font-size: 9px;
        position: absolute;
        right: 5px;
    }
    
    th.sort-desc::after {
        content: " ▼";
        font-size: 9px;
        position: absolute;
        right: 5px;
    }
    
    td {
        padding: 8px 10px;
        border-bottom: 1px solid #ddd;
        vertical-align: middle;
    }
    
    tr:hover {
        background-color: #f5f5f5;
    }
    
    .error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 4px;
        margin: 15px 0;
        border: 1px solid #f5c6cb;
    }
    
    /* Styles des filtres similaires à Verification.php */
    .controls {
        background-color: #f1f8e9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid #dcedc8;
    }
    
    .filter-group {
        display: inline-block;
        margin-right: 20px;
        margin-bottom: 8px;
    }
    
    .filter-group label {
        margin-right: 5px;
        font-weight: bold;
        color: #2c5f2d;
        font-size: 13px;
    }
    
    .filter-group select {
        padding: 5px 8px;
        border-radius: 3px;
        border: 1px solid #b2d8b2;
        background-color: white;
        font-size: 13px;
        min-width: 150px;
    }
    
    .filter-group select:focus {
        outline: none;
        border-color: #2c5f2d;
        box-shadow: 0 0 0 2px rgba(44, 95, 45, 0.2);
    }
    
    /* Boutons similaires à Verification.php */
    .filter-reset {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
    }
    
    .filter-reset:hover {
        background-color: #5a6268;
    }
    
    /* Badges comme dans Verification.php */
    .club-badge {
        display: inline-block;
        background-color: #e3f2fd;
        border: 1px solid #bbdefb;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        color: #1565c0;
        font-weight: bold;
    }
    
    .categorie-badge {
        display: inline-block;
        background-color: #f3e5f5;
        border: 1px solid #e1bee7;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        color: #7b1fa2;
        font-weight: bold;
    }
    
    .montant-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-align: center;
        background-color: #e8f5e9;
        color: #1b5e20;
        border: 1px solid #a5d6a7;
    }
    
    .montant-organizer {
        background-color: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
    }
    
    /* Boutons d'action similaires à Verification.php */
    .payment-button {
        padding: 5px 10px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 11px;
        font-weight: bold;
        transition: all 0.2s;
        min-width: 70px;
        margin: 2px;
    }
    
    .payment-button.validate {
        background-color: #28a745;
        color: white;
    }
    
    .payment-button.validate:hover {
        background-color: #218838;
    }
    
    .payment-button.unvalidate {
        background-color: #dc3545;
        color: white;
    }
    
    .payment-button.unvalidate:hover {
        background-color: #c82333;
    }
    
    /* AJOUT: Bouton facture individuelle */
    .invoice-button {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        font-size: 11px;
        font-weight: bold;
        transition: all 0.2s;
        min-width: 70px;
        margin: 2px;
    }
    
    .invoice-button:hover {
        background-color: #0056b3;
    }
    
    /* Statut de paiement */
    .payment-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: bold;
        text-align: center;
        min-width: 70px;
    }
    
    .status-paid {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .status-unpaid {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* Badges de cible/départ */
    .target-badge {
        display: inline-block;
        background-color: #e3f2fd;
        border: 1px solid #bbdefb;
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 10px;
        color: #1565c0;
        font-weight: bold;
        margin: 1px;
        white-space: nowrap;
    }
    
    .target-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        display: inline-block;
    }
    
    .target-cell {
        min-width: 120px;
        font-size: 11px;
    }
    
    /* Résumé des filtres */
    .filter-summary {
        margin-top: 10px;
        padding: 10px;
        background-color: #e8f5e8;
        border-radius: 4px;
        font-size: 12px;
        border: 1px solid #c8e6c9;
    }
    
    .filter-summary strong {
        color: #2c5f2d;
    }
    
    td:first-child {
        font-weight: bold;
        text-align: left;
        width: 40px;
    }
    
    td:nth-child(2) { /* Nom */
        font-weight: bold;
        color: #333;
    }
    
    td:nth-child(3) { /* Prénom */
        color: #555;
    }
    
    /* Style pour les notifications */
    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-out 2.7s forwards;
    }
    
    .notification-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .notification-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    /* Styles pour le formulaire inline */
    form {
        margin: 0;
        padding: 0;
        display: inline;
    }
    
    /* Amélioration de l'alignement */
    .controls form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 15px;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    /* Ajustement des cellules du tableau */
    td {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    td.target-cell {
        max-width: 250px;
        white-space: normal;
    }
    
    /* Ajustements responsives */
    @media (max-width: 1200px) {
        .filter-group {
            display: block;
            margin-bottom: 10px;
        }
        
        .filter-group select {
            width: 100%;
            max-width: 200px;
        }
    }
    
    @media (max-width: 768px) {
        .controls {
            padding: 10px;
        }
        
        table {
            font-size: 12px;
        }
        
        th, td {
            padding: 6px 8px;
        }
        
        .payment-button, .invoice-button {
            padding: 4px 8px;
            font-size: 10px;
            min-width: 60px;
        }
    }
    
    /* Styles pour le bouton "Tout corriger" similaire à Verification.php */
    .fix-button-large {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        margin: 20px 0;
        text-align: center;
        display: block;
        width: fit-content;
        margin-left: auto;
        margin-right: auto;
    }
    
    .fix-button-large:hover {
        background-color: #c82333;
    }
    
    /* Style pour les séparateurs entre archers (comme dans Verification.php) */
    .archer-group {
        background-color: #e9ecef;
        font-weight: bold;
        border-top: 2px solid #495057;
    }
    
    /* Badges pour les types (comme dans Verification.php) */
    .badge-unique {
        background-color: #17a2b8;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.8em;
    }
    
    .badge-multiple {
        background-color: #6c757d;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.8em;
    }
    
    /* Summary box comme dans Verification.php */
    .summary {
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        border-radius: 5px;
        padding: 15px;
        margin: 20px 0;
    }
    
    .summary h2 {
        margin-top: 0;
        color: #0c5460;
    }
    
    /* Message "Aucune anomalie" comme dans Verification.php */
    .no-anomaly {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 20px;
        border-radius: 5px;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
    }
    
    /* Styles existants pour tr */
    tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    /* Survol modifié pour être visible sur toutes les lignes */
    tr:hover {
        background-color: #e8f5e9 !important;
    }

    .container {
        margin-top: -20px;
    }

    body > table:first-child {
        margin-bottom: 20px;
    }

    /* Style pour les boutons d'action groupés */
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        min-width: 150px;
    }
    
    /* Style pour la cellule d'action */
    .action-cell {
        min-width: 160px;
    }

</style>


<div class="container">
    
    <?php
    // Stocker le message de notification pour JavaScript
    $notificationHtml = '';
    if (isset($_SESSION['payment_message'])) {
        $messageType = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success';
        $message = $_SESSION['payment_message'];
        
        // Déterminer l'icône en fonction du type
        $icon = '✓';
        if ($messageType === 'error') {
            $icon = '⚠';
        } elseif ($messageType === 'info') {
            $icon = 'ℹ';
        }
        
        // Créer l'HTML de la notification
        $notificationHtml = '
        <div class="notification notification-' . $messageType . '">
            <div class="notification-icon">' . $icon . '</div>
            <div class="notification-content">' . htmlspecialchars($message) . '</div>
        </div>';
        
        // Supprimer le message de session
        unset($_SESSION['payment_message']);
        unset($_SESSION['message_type']);
    }
    
    try {
        // Afficher le tournoi et club organisateur
        echo '<div class="summary">';
        echo '<h2>Tournoi : ' . htmlspecialchars($tournamentName ?: 'Non défini') . '</h2>';
        echo '<p>Club organisateur : ' . htmlspecialchars($organizerClubName ?: 'Non défini') . ' (Code: ' . htmlspecialchars($organizerClubCode ?: 'Non défini') . ')</p>';
        echo '</div>';
        
        // MODIFICATION : Ajouter les informations de cible/départ depuis Qualifications
        $query = "SELECT 
    e.EnId as id,
    e.EnFirstName as prenom,
    e.EnName as nom,
    CONCAT(e.EnFirstName, ' ', e.EnName) as nom_complet,
    CONCAT(e.EnDivision, e.EnClass) as categorie,
    c.CoName as club,
    c.CoCode as country_code,
    e.EnIndFEvent as finale_ind,
    e.EnWChair as fauteuil,
    -- Récupérer le statut de paiement depuis QuNotes
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
    GROUP_CONCAT(DISTINCT e.EnId ORDER BY e.EnId SEPARATOR ',') as entry_ids,
    GROUP_CONCAT(DISTINCT CONCAT(e.EnDivision, e.EnClass) ORDER BY e.EnDivision, e.EnClass SEPARATOR ', ') as categories,
    GROUP_CONCAT(DISTINCT c.CoName ORDER BY c.CoName SEPARATOR ', ') as clubs,
    -- AJOUT DES INFORMATIONS DE CIBLE/DÉPART
    GROUP_CONCAT(DISTINCT 
        CONCAT(
            'D', 
            SUBSTRING(q.QuTargetNo, 1, 1),  -- Numéro de départ (premier chiffre)
            ' - ', 
            SUBSTRING(q.QuTargetNo, 2, 3),  -- Numéro de cible (3 chiffres suivants)
            SUBSTRING(q.QuTargetNo, 5, 1)   -- Lettre de position
        ) 
        ORDER BY q.QuSession, q.QuTargetNo 
        SEPARATOR '; '
    ) as cibles_departs,
    GROUP_CONCAT(DISTINCT q.QuTargetNo ORDER BY q.QuSession, q.QuTargetNo SEPARATOR ', ') as target_numbers,
    GROUP_CONCAT(DISTINCT q.QuSession ORDER BY q.QuSession SEPARATOR ', ') as sessions
FROM Entries e
LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
-- CORRECTION : Jointure avec Qualifications sans QuTournament
LEFT JOIN Qualifications q ON e.EnId = q.QuId
WHERE e.EnTournament = $TourId 
AND e.EnAthlete = 1
GROUP BY e.EnFirstName, e.EnName
ORDER BY e.EnName, e.EnFirstName";
        
        // Exécuter la requête
        $Rs = safe_r_sql($query);
        
        if (!$Rs) {
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
        
        $totalArchers = safe_num_rows($Rs);
        
        $statsQuery = "SELECT 
            COUNT(DISTINCT CONCAT(EnFirstName, ' ', EnName)) as archers_uniques,
            COUNT(*) as total_inscriptions,
            SUM(CASE WHEN nb_inscriptions = 1 THEN 1 ELSE 0 END) as archers_1_depart,
            SUM(CASE WHEN nb_inscriptions = 2 THEN 1 ELSE 0 END) as archers_2_departs,
            SUM(CASE WHEN nb_inscriptions >= 3 THEN 1 ELSE 0 END) as archers_3plus_departs,
            COUNT(DISTINCT EnCountry) as clubs_count
        FROM (
            SELECT 
                EnFirstName,
                EnName,
                EnCountry,
                COUNT(*) as nb_inscriptions
            FROM Entries
            WHERE EnTournament = $TourId 
            AND EnAthlete = 1
            GROUP BY EnFirstName, EnName, EnCountry
        ) as archer_counts";
        
        $statsRs = safe_r_sql($statsQuery);
        $stats = safe_fetch($statsRs);
        
        // Récupérer la liste des "clubs" (pays) pour le filtre
        $clubsQuery = "SELECT DISTINCT c.CoCode, c.CoName 
                      FROM Entries e
                      LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
                      WHERE e.EnTournament = $TourId 
                      AND e.EnAthlete = 1 
                      AND c.CoName IS NOT NULL
                      AND c.CoName != ''
                      ORDER BY c.CoName";
        $clubsRs = safe_r_sql($clubsQuery);
        $clubs = [];
        while ($club = safe_fetch($clubsRs)) {
            $clubs[$club->CoCode] = $club->CoName;
        }
        
        // Récupérer la liste des catégories pour le filtre
        $categoriesQuery = "SELECT DISTINCT CONCAT(EnDivision, EnClass) as categorie 
                          FROM Entries 
                          WHERE EnTournament = $TourId 
                          AND EnAthlete = 1 
                          AND EnDivision != '' 
                          AND EnClass != ''
                          ORDER BY CONCAT(EnDivision, EnClass)";
        $categoriesRs = safe_r_sql($categoriesQuery);
        $categories = [];
        while ($cat = safe_fetch($categoriesRs)) {
            $categories[] = $cat->categorie;
        }
        
        // Afficher les filtres
        echo '<div class="controls">';
        echo '<form id="filterForm" method="GET" style="margin-bottom: 10px;">';
        
        // Ajouter les paramètres de tri cachés dans le formulaire de filtres
        $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'nom';
        $currentDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';
        echo '<input type="hidden" name="sort" id="filterSort" value="' . htmlspecialchars($currentSort) . '">';
        echo '<input type="hidden" name="dir" id="filterDir" value="' . htmlspecialchars($currentDir) . '">';
        
        // Filtre par club
        echo '<div class="filter-group">';
        echo '<label for="club_filter">Club :</label>';
        echo '<select id="club_filter" name="club_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        echo '<option value="all"' . ($filters['club'] == 'all' ? ' selected' : '') . '>Tous les clubs</option>';
        foreach ($clubs as $code => $name) {
            $selected = ($filters['club'] == $name) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($name) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Filtre par catégorie
        echo '<div class="filter-group">';
        echo '<label for="category_filter">Catégorie:</label>';
        echo '<select id="category_filter" name="category_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        echo '<option value="all"' . ($filters['category'] == 'all' ? ' selected' : '') . '>Toutes les catégories</option>';
        foreach ($categories as $cat) {
            $selected = ($filters['category'] == $cat) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($cat) . '"' . $selected . '>' . htmlspecialchars($cat) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Filtre par numéro de départ
        echo '<div class="filter-group">';
        echo '<label for="departs_filter">Départs:</label>';
        echo '<select id="departs_filter" name="departs_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        $departs_options = [
            'all' => 'Tous',
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4+'
        ];
        foreach ($departs_options as $value => $label) {
            $selected = ($filters['departs'] == $value) ? ' selected' : '';
            echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Filtre par statut de paiement
        echo '<div class="filter-group">';
        echo '<label for="payment_filter">Paiement:</label>';
        echo '<select id="payment_filter" name="payment_filter" onchange="document.getElementById(\'filterForm\').submit()">';
        $payment_options = [
            'all' => 'Tous',
            'paid' => 'Payés',
            'unpaid' => 'Non payés'
        ];
        foreach ($payment_options as $value => $label) {
            $selected = ($filters['payment'] == $value) ? ' selected' : '';
            echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Bouton de réinitialisation
        echo '<div class="filter-group">';
        echo '<button type="button" onclick="resetFilters()" class="filter-reset">Réinitialiser</button>';
        echo '</div>';
        
        echo '</form>';
        
        // Afficher un résumé des filtres actifs
        $active_filters = [];
        if ($filters['club'] != 'all') $active_filters[] = 'Club: ' . htmlspecialchars($filters['club']);
        if ($filters['category'] != 'all') $active_filters[] = 'Catégorie: ' . htmlspecialchars($filters['category']);
        if ($filters['departs'] != 'all') $active_filters[] = 'Départs: ' . $departs_options[$filters['departs']];
        if ($filters['payment'] != 'all') $active_filters[] = 'Paiement: ' . $payment_options[$filters['payment']];
        
        if (!empty($active_filters)) {
            echo '<div class="filter-summary">';
            echo '<strong>Filtres actifs:</strong> ' . implode(' | ', $active_filters);
            echo '</div>';
        }
        echo '</div>';
        
        if ($totalArchers > 0) {
            // Variables pour les totaux financiers
            $totalPrix = 0;
            $totalOrganizer = 0;
            $totalAutres = 0;
            $countOrganizer = 0;
            $countAutres = 0;
            
            // Variables pour le suivi des paiements
            $totalPaye = 0;
            $totalNonPaye = 0;
            $countPaye = 0;
            $countNonPaye = 0;
            
            // Variables pour le comptage filtré
            $filteredCount = 0;
            $filteredTotal = 0;
            
            // Stocker les archers dans un tableau pour le tri alphabétique
            $archersData = [];
            $counter = 0;
            while ($row = safe_fetch($Rs)) {
                $counter++;
                
                // Extraire les numéros de départ à partir des numéros de cible
                $departs_archer = extraireDeparts($row->target_numbers);
                
                $archersData[] = [
                    'id' => $row->id,
                    'counter' => $counter,
                    'prenom' => $row->prenom,
                    'nom' => $row->nom,
                    'nom_complet' => $row->nom_complet,
                    'categorie' => $row->categorie,
                    'club' => $row->club,
                    'country_code' => $row->country_code,
                    'categories' => $row->categories,
                    'clubs' => $row->clubs,
                    'nb_inscriptions' => (int)$row->nb_inscriptions,
                    'payment_status' => $row->payment_status,
                    'payment_method' => $row->payment_method,
                    'cibles_departs' => $row->cibles_departs,
                    'target_numbers' => $row->target_numbers,
                    'sessions' => $row->sessions,
                    'departs' => $departs_archer  // Ajout des numéros de départ
                ];
            }
            
            // Récupérer le tri depuis l'URL
            $sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'nom';
            $sortDirection = isset($_GET['dir']) ? $_GET['dir'] : 'asc';
            
            // Trier les archers selon les paramètres de l'URL
            usort($archersData, function($a, $b) use ($sortColumn, $sortDirection) {
                $aValue = '';
                $bValue = '';
                
                switch($sortColumn) {
                    case 'counter':
                        $aValue = $a['counter'];
                        $bValue = $b['counter'];
                        break;
                    case 'prenom':
                        $aValue = strtolower($a['prenom']);
                        $bValue = strtolower($b['prenom']);
                        break;
                    case 'nom':
                        $aValue = strtolower($a['nom']);
                        $bValue = strtolower($b['nom']);
                        break;
                    case 'club':
                        $aValue = strtolower($a['club']);
                        $bValue = strtolower($b['club']);
                        break;
                    case 'categorie':
                        $aValue = strtolower($a['categorie']);
                        $bValue = strtolower($b['categorie']);
                        break;
                    case 'montant':
                        $aValue = calculerPrix($a['country_code'], $a['categorie'], $a['nb_inscriptions'], $tarifs, $organizerClubCode);
                        $bValue = calculerPrix($b['country_code'], $b['categorie'], $b['nb_inscriptions'], $tarifs, $organizerClubCode);
                        break;
                    case 'payment_status':
                        $aValue = $a['payment_status'];
                        $bValue = $b['payment_status'];
                        break;
                    case 'cible_depart':
                        $aValue = !empty($a['cibles_departs']) && $a['cibles_departs'] != 'D? - ?' ? $a['cibles_departs'] : '';
                        $bValue = !empty($b['cibles_departs']) && $b['cibles_departs'] != 'D? - ?' ? $b['cibles_departs'] : '';
                        break;
                    default:
                        $aValue = strtolower($a['nom']);
                        $bValue = strtolower($b['nom']);
                }
                
                if ($sortDirection === 'asc') {
                    return $aValue > $bValue ? 1 : ($aValue < $bValue ? -1 : 0);
                } else {
                    return $aValue < $bValue ? 1 : ($aValue > $bValue ? -1 : 0);
                }
            });
            
            // Afficher le tableau avec tri client
            echo '<table id="archersTable">';
            echo '<thead>';
            echo '<tr>';
            echo '<th data-sort="counter">#</th>';
            echo '<th class="action-cell">Actions</th>';
            echo '<th data-sort="prenom">Nom</th>'; 
            echo '<th data-sort="nom">Prénom</th>'; 
            echo '<th data-sort="club">Club</th>';
            echo '<th data-sort="categorie">Catégorie</th>';
            echo '<th data-sort="montant">Montant (€)</th>'; 
            echo '<th data-sort="cible_depart">Départ / Cible</th>';
            echo '<th data-sort="payment_status">Statut Paiement</th>';
            echo '<th data-sort=\"payment_method\">Moyen de paiement</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody id="archersTableBody">';
            
            $displayCounter = 0;
            foreach ($archersData as $archer) {
                // Vérifier si l'archer doit être affiché selon les filtres
                $display = true;
                
                // Filtrer par club (nom exact)
                if ($filters['club'] !== 'all' && $filters['club'] !== '') {
                    if (stripos($archer['club'], $filters['club']) === false) {
                        $display = false;
                    }
                }
                
                // Filtrer par catégorie
                if ($display && $filters['category'] !== 'all' && $filters['category'] !== '') {
                    if (stripos($archer['categorie'], $filters['category']) === false) {
                        $display = false;
                    }
                }
                
                // Filtrer par numéro de départ
                if ($display && $filters['departs'] !== 'all') {
                    $filter_depart = (int)$filters['departs'];
                    $departs_archer = $archer['departs'];
                    
                    if ($filter_depart == 4) {
                        // D4+ : vérifier si l'archer a au moins un départ >= 4
                        $has_depart_4plus = false;
                        foreach ($departs_archer as $depart) {
                            if ($depart >= 4) {
                                $has_depart_4plus = true;
                                break;
                            }
                        }
                        if (!$has_depart_4plus) {
                            $display = false;
                        }
                    } else {
                        // Pour Départ 1, 2, 3, vérifier que le numéro de départ est présent
                        if (!in_array($filter_depart, $departs_archer)) {
                            $display = false;
                        }
                    }
                }
                
                // Filtrer par statut de paiement
                if ($display && $filters['payment'] !== 'all') {
                    $is_paid = ($archer['payment_status'] == 1);
                    
                    if ($filters['payment'] === 'paid' && !$is_paid) $display = false;
                    elseif ($filters['payment'] === 'unpaid' && $is_paid) $display = false;
                }
                
                // Si l'archer doit être affiché
                if ($display) {
                    $displayCounter++;
                    
                    // Déterminer la classe CSS pour le badge des départs
                    $depart_class = 'depart-plus';
                    $nb_inscriptions = (int)$archer['nb_inscriptions'];
                    
                    if ($nb_inscriptions == 1) {
                        $depart_class = 'depart-1';
                    } elseif ($nb_inscriptions == 2) {
                        $depart_class = 'depart-2';
                    } elseif ($nb_inscriptions == 3) {
                        $depart_class = 'depart-3';
                    }
                    
                    // Afficher la/les catégorie(s)
                    $categorie_display = htmlspecialchars($archer['categorie']);
                    if ($archer['categories'] && strpos($archer['categories'], ',') !== false) {
                        $categorie_display = '<span class="categorie-badge" title="' . htmlspecialchars($archer['categories']) . '">Multiple</span>';
                    } else {
                        $categorie_display = '<span class="categorie-badge">' . htmlspecialchars($archer['categorie']) . '</span>';
                    }
                    
                    // Afficher le "club" (pays)
                    $club_display = '-';
                    if ($archer['club']) {
                        $club_display = htmlspecialchars($archer['club']);
                        if ($archer['clubs'] && strpos($archer['clubs'], ',') !== false) {
                            $club_display = '<span class="club-badge" title="' . htmlspecialchars($archer['clubs']) . '">Multiple</span>';
                        } else {
                            $club_display = '<span class="club-badge" title="' . htmlspecialchars($archer['country_code']) . '">' . htmlspecialchars($archer['club']) . '</span>';
                        }
                    }
                    
                    // Calculer le montant (comparaison par CoCode)
                    $montant = calculerPrix($archer['country_code'], $archer['categorie'], $nb_inscriptions, $tarifs, $organizerClubCode);
                    $totalPrix += $montant;
                    $filteredTotal += $montant;
                    
                    // Déterminer la classe CSS pour le montant
                    $montant_class = 'montant-badge';
                    $montant_title = "Tarif standard";
                    
                    // Vérifier si c'est le club organisateur (comparaison par CoCode)
                    $is_organizer_club = ($archer['country_code'] == $organizerClubCode);
                    if ($is_organizer_club) {
                        $montant_class = 'montant-badge montant-organizer';
                        $montant_title = "Tarif club organisateur";
                        $totalOrganizer += $montant;
                        $countOrganizer++;
                    } else {
                        $totalAutres += $montant;
                        $countAutres++;
                    }
                    
                    // Afficher les cibles/départs
                    $cible_display_value = 'Non affecté';
                    $cible_display_html = '<span class="target-warning">Non affecté</span>';
                    
                    if (!empty($archer['cibles_departs']) && $archer['cibles_departs'] != 'D? - ?') {
                        $cible_display_value = $archer['cibles_departs'];
                        // Créer des badges pour chaque affectation
                        $affectations = explode('; ', $archer['cibles_departs']);
                        $cible_display_html = '';
                        foreach ($affectations as $affectation) {
                            if (!empty(trim($affectation))) {
                                $cible_display_html .= '<span class="target-badge">' . htmlspecialchars($affectation) . '</span> ';
                            }
                        }
                    }
                    
                    // Statut du paiement
                    $payment_status = $archer['payment_status'];
                    $is_paid = ($payment_status == 1);
                    
                    // Construire l'URL pour la facture individuelle avec les filtres préservés
                    $invoiceUrl = $_SERVER['PHP_SELF'] . '?invoice=1&archer_id=' . $archer['id'];
                    foreach ($filters as $key => $value) {
                        if ($value !== 'all') {
                            $invoiceUrl .= "&{$key}_filter=" . urlencode($value);
                        }
                    }
                    // Ajouter les paramètres de tri à l'URL de facture
                    $invoiceUrl .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
                    
                    // Bouton de facture
                    $invoice_button = '<a href="' . $invoiceUrl . '" class="invoice-button" title="Générer la facture pour cet archer">📄 Facture</a>';
                    
                    // Récupérer le tri actuel pour l'ajouter aux formulaires
                    $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'nom';
                    $currentDir = isset($_GET['dir']) ? $_GET['dir'] : 'asc';
                    
                    // Ajouter les paramètres de filtre au formulaire
                    $filterParams = '';
                    foreach ($filters as $key => $value) {
                        if ($value !== 'all') {
                            $filterParams .= '<input type="hidden" name="' . $key . '_filter" value="' . htmlspecialchars($value) . '">';
                        }
                    }
                    
                    // Ajouter les paramètres de tri au formulaire
                    $sortFields = '<input type="hidden" name="sort_column" value="' . htmlspecialchars($currentSort) . '">
                                   <input type="hidden" name="sort_direction" value="' . htmlspecialchars($currentDir) . '">';
                    
                    if ($is_paid) {
                        $countPaye++;
                        $totalPaye += $montant;
                        $status_class = 'status-paid';
                        $status_text = 'Payé';
                        
                        $payment_button = '<form method="POST" style="display:inline;">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="unvalidate">'
                                            . $sortFields
                                            . $filterParams . '
                                            <button type="submit" class="payment-button unvalidate" 
                                                    onclick="return confirm(\'Êtes-vous sûr de vouloir marquer le paiement de ' . htmlspecialchars($archer['prenom'] . ' ' . $archer['nom'], ENT_QUOTES) . ' comme non acquitté ?\')"
                                                    title="Marquer comme non payé">
                                                Annuler
                                            </button>
                                          </form>';
                    } else {
                        $countNonPaye++;
                        $totalNonPaye += $montant;
                        $status_class = 'status-unpaid';
                        $status_text = 'Non payé';
                        
                        $payment_button = '<form method="POST" style="display:inline;">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="validate">'
                                            . $sortFields
                                            . $filterParams . '
                                            <select name="payment_method" required style="font-size:12px; padding:4px 6px; margin-right:5px; border:1px solid #ccc; border-radius:3px;">
                                                <option value="ESPECE">Espèce</option>
                                                <option value="CHEQUE">Chèque</option>
                                                <option value="VIREMENT">Virement</option>
                                                <option value="GRATUIT">Gratuit</option>
                                            </select>
                                            <button type="submit" class="payment-button validate" 
                                                    onclick="return confirmPayment(this, \'' . htmlspecialchars($archer['prenom'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['nom'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['club'], ENT_QUOTES) . '\', \'' . htmlspecialchars($archer['categorie'], ENT_QUOTES) . '\', \'' . $montant . '\', \'' . htmlspecialchars($cible_display_value, ENT_QUOTES) . '\')"
                                                    title="Valider le paiement de cet archer">
                                                Valider
                                            </button>
                                          </form>';
                    }

                    echo '<tr  data-counter="' . $archer['counter'] . '" 
                                 data-prenom="' . htmlspecialchars($archer['prenom']) . '" 
                                 data-nom="' . htmlspecialchars($archer['nom']) . '" 
                                 data-club="' . htmlspecialchars($archer['club']) . '" 
                                 data-categorie="' . htmlspecialchars($archer['categorie']) . '" 
                                 data-nb-inscriptions="' . $nb_inscriptions . '" 
                                 data-montant="' . $montant . '" 
                                 data-cible-depart="' . htmlspecialchars($cible_display_value) . '"
                                 data-payment-status="' . ($is_paid ? 'paid' : 'unpaid') . '">';
                    echo '<td>' . $archer['counter'] . '</td>';
                    echo '<td class="action-cell"><div class="action-buttons">' . $payment_button . $invoice_button . '</div></td>';
                    echo '<td>' . htmlspecialchars($archer['prenom']) . '</td>'; 
                    echo '<td>' . htmlspecialchars($archer['nom']) . '</td>'; 
                    echo '<td>' . $club_display . '</td>';
                    echo '<td>' . $categorie_display . '</td>';
                    echo '<td><span class="' . $montant_class . '" title="' . $montant_title . '">' . $montant . ' €</span></td>';
                    echo '<td class="target-cell">' . $cible_display_html . '</td>';
                    echo '<td><span class="payment-status ' . $status_class . '">' . $status_text . '</span></td>';

                    // Afficher le moyen de paiement
                    $payment_method_display = '-';
                    if (!empty($archer['payment_method'])) {
                        $payment_method_display = htmlspecialchars($archer['payment_method']);
                    }
                    echo '<td>' . $payment_method_display . '</td>';

                    echo '</tr>';
                }
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Afficher les totaux
            echo '<div class="filter-summary" style="margin-top: 20px;">';
            echo '<strong>Résumé :</strong> ';
            echo $displayCounter . ' archer(s) affiché(s) sur ' . $totalArchers . ' | ';
            echo 'Total montant : ' . $filteredTotal . ' € | ';
            echo 'Club organisateur (' . $countOrganizer . ') : ' . $totalOrganizer . ' € | ';
            echo 'Autres clubs (' . $countAutres . ') : ' . $totalAutres . ' € | ';
            echo 'Payés : ' . $countPaye . ' (' . $totalPaye . ' €) | ';
            echo 'Non payés : ' . $countNonPaye . ' (' . $totalNonPaye . ' €)';
            echo '</div>';
            
        } else {
            echo '<p style="text-align: center; color: #666; font-style: italic;">';
            echo 'Aucun archer trouvé dans la base de données.';
            echo '</p>';
        }
        
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<strong>Erreur :</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    ?>
    
</div>

<?php if ($notificationHtml): ?>
<div id="notification-wrapper">
    <?php echo $notificationHtml; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notification = document.querySelector('#notification-wrapper .notification');
        if (notification) {
            // Ajouter la notification au body pour la position fixe
            document.body.appendChild(notification.cloneNode(true));
            
            // Supprimer l'élément wrapper
            document.getElementById('notification-wrapper').remove();
            
            // Supprimer la notification après l'animation
            setTimeout(() => {
                const notifications = document.querySelectorAll('.notification');
                notifications.forEach(notif => {
                    if (notif.parentNode) {
                        notif.parentNode.removeChild(notif);
                    }
                });
            }, 3000);
        }
    });
</script>
<?php endif; ?>

<script>
    function resetFilters() {
        // Réinitialiser tous les filtres et le tri
        document.getElementById('club_filter').value = 'all';
        document.getElementById('category_filter').value = 'all';
        document.getElementById('departs_filter').value = 'all';
        document.getElementById('payment_filter').value = 'all';
        document.getElementById('filterSort').value = 'nom';
        document.getElementById('filterDir').value = 'asc';
        
        // Soumettre le formulaire
        document.getElementById('filterForm').submit();
    }
    
    // Fonction utilitaire pour afficher des notifications via JavaScript
    function showNotification(message, type = 'success') {
        // Déterminer l'icône en fonction du type
        let icon = '✓';
        if (type === 'error') {
            icon = '⚠';
        } else if (type === 'info') {
            icon = 'ℹ';
        }
        
        // Créer l'élément de notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-icon">${icon}</div>
            <div class="notification-content">${message}</div>
        `;
        
        // Ajouter au body
        document.body.appendChild(notification);
        
        // Supprimer la notification après l'animation
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    
function confirmPayment(button, prenom, nom, club, categorie, montant, cibleDepart) {
    // Récupérer le moyen de paiement
    let paymentMethod = 'Non spécifié';
    
    try {
        const form = button.form || button.closest('form');
        if (form) {
            const select = form.querySelector('select[name="payment_method"]');
            if (select && select.value) {
                paymentMethod = select.value;
            }
        }
    } catch(e) {
        console.error('Erreur récupération moyen de paiement:', e);
    }
    
    // Formater le message de confirmation
    const message = 'Confirmer le paiement pour :\n\n' +
                   '• Nom : ' + nom + '\n' +
                   '• Prénom : ' + prenom + '\n' +
                   '• Club : ' + (club || '-') + '\n' +
                   '• Catégorie : ' + categorie + '\n' +
                   '• Montant : ' + montant + ' €\n' +
                   '• Départ / Cible : ' + cibleDepart + '\n' +
                   '• Moyen de paiement : ' + paymentMethod + '\n\n' +
                   'Êtes-vous sûr de vouloir valider ce paiement ?';
    
    if (confirm(message)) {
        const form = button.form || button.closest('form');
        if (form) {
            form.submit();
        }
        return true;
    }
    return false;
}

    
    // Tri du tableau par colonne
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('archersTable');
        const tbody = document.getElementById('archersTableBody');
        const headers = table.querySelectorAll('th[data-sort]');
        
        // Récupérer le tri actuel depuis l'URL
        const urlParams = new URLSearchParams(window.location.search);
        let currentSort = {
            column: urlParams.get('sort') || 'nom',
            direction: urlParams.get('dir') || 'asc'
        };
        
        // Appliquer le style visuel du tri actuel
        headers.forEach(header => {
            const column = header.getAttribute('data-sort');
            if (column === currentSort.column) {
                header.classList.remove('sort-asc', 'sort-desc');
                header.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }
        });
        
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                const isAsc = this.classList.contains('sort-asc');
                
                // Supprimer les classes de tri de toutes les en-têtes
                headers.forEach(h => {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Déterminer la nouvelle direction
                let direction = 'asc';
                if (currentSort.column === column && currentSort.direction === 'asc') {
                    direction = 'desc';
                    this.classList.add('sort-desc');
                } else {
                    direction = 'asc';
                    this.classList.add('sort-asc');
                }
                
                // Mettre à jour le tri actuel
                currentSort = { column, direction };
                
                // Mettre à jour les champs cachés dans le formulaire de filtres
                document.getElementById('filterSort').value = column;
                document.getElementById('filterDir').value = direction;
                
                // Mettre à jour les champs de tri dans tous les formulaires de paiement
                updateSortFieldsInForms(column, direction);
                
                // Mettre à jour l'URL sans recharger la page
                updateURLWithSort(column, direction);
                
                // Trier les lignes
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    let aValue, bValue;
                    
                    switch(column) {
                        case 'counter':
                            aValue = parseInt(a.getAttribute('data-counter'));
                            bValue = parseInt(b.getAttribute('data-counter'));
                            break;
                        case 'prenom':
                            aValue = a.getAttribute('data-prenom').toLowerCase();
                            bValue = b.getAttribute('data-prenom').toLowerCase();
                            break;
                        case 'nom':
                            aValue = a.getAttribute('data-nom').toLowerCase();
                            bValue = b.getAttribute('data-nom').toLowerCase();
                            break;
                        case 'club':
                            aValue = a.getAttribute('data-club').toLowerCase();
                            bValue = b.getAttribute('data-club').toLowerCase();
                            break;
                        case 'categorie':
                            aValue = a.getAttribute('data-categorie').toLowerCase();
                            bValue = b.getAttribute('data-categorie').toLowerCase();
                            break;
                        case 'montant':
                            aValue = parseFloat(a.getAttribute('data-montant'));
                            bValue = parseFloat(b.getAttribute('data-montant'));
                            break;
                        case 'payment_status':
                            aValue = a.getAttribute('data-payment-status');
                            bValue = b.getAttribute('data-payment-status');
                            break;
                        case 'cible_depart':
                            // Trier par le premier numéro de départ disponible
                            let aMatches = a.getAttribute('data-cible-depart').match(/\d+/g) || [0];
                            let bMatches = b.getAttribute('data-cible-depart').match(/\d+/g) || [0];
                            aValue = parseInt(aMatches[0]) || 0;
                            bValue = parseInt(bMatches[0]) || 0;
                            break;
                        default:
                            return 0;
                    }
                    
                    if (direction === 'asc') {
                        return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
                    } else {
                        return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
                    }
                });
                
                // Réorganiser les lignes dans le tbody
                rows.forEach(row => {
                    tbody.appendChild(row);
                });
                
                // Mettre à jour les numéros de ligne
                updateRowNumbers();
            });
        });
        
        function updateRowNumbers() {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach((row, index) => {
                row.querySelector('td:first-child').textContent = index + 1;
            });
        }
        
        // Fonction pour mettre à jour les champs de tri dans tous les formulaires
        function updateSortFieldsInForms(sortColumn, sortDirection) {
            const forms = document.querySelectorAll('form[method="POST"]');
            forms.forEach(form => {
                let sortColumnField = form.querySelector('input[name="sort_column"]');
                let sortDirectionField = form.querySelector('input[name="sort_direction"]');
                
                if (sortColumnField) sortColumnField.value = sortColumn;
                if (sortDirectionField) sortDirectionField.value = sortDirection;
            });
        }
        
        // Fonction pour mettre à jour l'URL avec les paramètres de tri
        function updateURLWithSort(column, direction) {
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('dir', direction);
            
            // Mettre à jour l'URL sans recharger la page
            window.history.pushState({}, '', url);
        }
        
        // Initialiser avec le tri depuis l'URL
        if (currentSort.column) {
            // Mettre à jour les champs de tri dans les formulaires
            updateSortFieldsInForms(currentSort.column, currentSort.direction);
        }
    });
</script>

<!-- Bouton Export PDF -->
<div class="filter-group" style="margin-left: auto;">
    <button onclick="exportToPDF()" class="export-pdf-button" title="Exporter la liste en PDF">
        📄 Exporter en PDF
    </button>
</div>

<style>
.export-pdf-button {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: all 0.2s;
}

.export-pdf-button:hover {
    background-color: #c82333;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
</style>

<script>
function exportToPDF() {
    // Récupérer les filtres actuels
    const urlParams = new URLSearchParams(window.location.search);
    const club = urlParams.get('club_filter') || 'all';
    const category = urlParams.get('category_filter') || 'all';
    const payment = urlParams.get('payment_filter') || 'all';

    // Construire l'URL avec les filtres
    const exportUrl = 'export_pdf.php?club=' + club + '&category=' + category + '&payment=' + payment;

    // Ouvrir dans un nouvel onglet
    window.open(exportUrl, '_blank');
}
</script>


<!-- AJOUT: Inclure le pied de page du site -->
<?php include('Common/Templates/tail.php'); ?>