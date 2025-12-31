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
 * Dernière modification: 2025-12-31 par Laurent Petroff
 * Ajout de la Facture
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

// Traitement de la validation du paiement
if (isset($_POST['validate_payment']) && isset($_POST['archer_id'])) {
    $archerId = intval($_POST['archer_id']);
    $action = $_POST['validate_payment'];
    
    // Récupérer les filtres actuels pour les passer dans la redirection
    $filterParams = '';
    foreach ($filters as $key => $value) {
        if ($value !== 'all') {
            $filterParams .= "&{$key}_filter=" . urlencode($value);
        }
    }
    
    // Mettre à jour EnCountry3 avec 1 pour payé, 0 pour non payé
    $paymentStatus = ($action == 'validate') ? 1 : 0;
    
    $updateQuery = "UPDATE Entries 
                    SET EnCountry3 = $paymentStatus 
                    WHERE EnId = $archerId";
    
    $result = safe_w_sql($updateQuery);
    
    if ($result) {
        $_SESSION['payment_message'] = ($paymentStatus == 1) 
            ? 'Paiement validé avec succès !' 
            : 'Paiement marqué comme non acquitté.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['payment_message'] = 'Erreur lors de la mise à jour.';
        $_SESSION['message_type'] = 'error';
    }
    
    // Rediriger pour éviter la resoumission du formulaire, en conservant les filtres
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . ltrim($filterParams, '&'));
    exit();
}

// Fonction pour lire et parser le fichier Prix.txt
function parsePrixTxt() {
    $prixFile = dirname(__FILE__) . '/Prix.txt';
    
    if (!file_exists($prixFile)) {
        throw new Exception("Fichier Prix.txt introuvable");
    }
    
    $content = file_get_contents($prixFile);
    $tarifs = [
        'clubs_autres' => [
            'jeunes' => [
                1 => 8,    // 1 départ
                2 => 14    // 2 départs ou plus (8 + 6)
            ],
            'adultes' => [
                1 => 10,   // 1 départ
                2 => 18    // 2 départs ou plus (10 + 8)
            ]
        ],
        'perols' => [
            'jeunes' => [
                1 => 4,    // 1 départ
                2 => 8     // 2 départs ou plus (4 + 4)
            ],
            'adultes' => [
                1 => 5,    // 1 départ
                2 => 10    // 2 départs ou plus (5 + 5)
            ]
        ]
    ];
    
    // Essayer de parser dynamiquement depuis le fichier
    $lines = explode("\n", $content);
    $current_section = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Détecter les sections
        if (strpos($line, 'POUR LE CLUB DE PEROLS') !== false) {
            $current_section = 'perols';
            continue;
        } elseif (strpos($line, 'POUR TOUS LES CLUBS AUTRE QUE PEROLS') !== false) {
            $current_section = 'autres';
            continue;
        }
        
        // Parser les tarifs
        if (preg_match('/Jeunes.*U11.*U18.*:.*(\d+)/', $line, $matches)) {
            if ($current_section == 'autres') {
                $tarifs['clubs_autres']['jeunes'][1] = (int)$matches[1];
            } elseif ($current_section == 'perols') {
                $tarifs['perols']['jeunes'][1] = (int)$matches[1];
            }
        } elseif (preg_match('/Adultes.*U21.*S3.*:.*(\d+)/', $line, $matches)) {
            if ($current_section == 'autres') {
                $tarifs['clubs_autres']['adultes'][1] = (int)$matches[1];
            } elseif ($current_section == 'perols') {
                $tarifs['perols']['adultes'][1] = (int)$matches[1];
            }
        } elseif (preg_match('/Jeunes.*U11.*U18.*:\s*\+\s*(\d+)/', $line, $matches)) {
            if ($current_section == 'autres') {
                $tarifs['clubs_autres']['jeunes'][2] = $tarifs['clubs_autres']['jeunes'][1] + (int)$matches[1];
            } elseif ($current_section == 'perols') {
                $tarifs['perols']['jeunes'][2] = $tarifs['perols']['jeunes'][1] + (int)$matches[1];
            }
        } elseif (preg_match('/Adultes.*U21.*S3.*:\s*\+\s*(\d+)/', $line, $matches)) {
            if ($current_section == 'autres') {
                $tarifs['clubs_autres']['adultes'][2] = $tarifs['clubs_autres']['adultes'][1] + (int)$matches[1];
            } elseif ($current_section == 'perols') {
                $tarifs['perols']['adultes'][2] = $tarifs['perols']['adultes'][1] + (int)$matches[1];
            }
        }
    }
    
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
function calculerPrix($club_name, $categorie_code, $nb_departs, $tarifs) {
    // Déterminer si c'est Pérols
    $is_perols = (stripos($club_name, 'perols') !== false);
    
    // Déterminer la catégorie d'âge
    $age_category = getAgeCategory($categorie_code);
    
    // Déterminer la clé tarifaire (1 ou 2+ départs)
    $tarif_key = ($nb_departs == 1) ? 1 : 2;
    
    // Récupérer le prix
    if ($is_perols) {
        return $tarifs['perols'][$age_category][$tarif_key];
    } else {
        return $tarifs['clubs_autres'][$age_category][$tarif_key];
    }
}

// Fonction pour calculer le prix par départ (CORRIGÉE)
// Fonction pour calculer le prix par départ (CORRECTION pour tarif 10+8)
function calculerPrixParDepart($club_name, $categorie_code, $nb_departs, $tarifs) {
    // Déterminer si c'est Pérols
    $is_perols = (stripos($club_name, 'perols') !== false);
    
    // Déterminer la catégorie d'âge
    $age_category = getAgeCategory($categorie_code);
    
    // Récupérer les tarifs de base
    if ($is_perols) {
        $prix_1_depart = $tarifs['perols'][$age_category][1];
        $prix_2_departs = $tarifs['perols'][$age_category][2];
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
        // 2 départs - CORRECTION: premier à prix normal, deuxième au supplément
        $prix_par_depart[1] = $prix_1_depart;      // Premier départ: tarif normal
        $prix_par_depart[2] = $supplement_2eme_depart; // Deuxième départ: supplément seulement
        $total = $prix_1_depart + $supplement_2eme_depart;
    } else {
        // 3 départs ou plus
        // Premier départ au tarif normal
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
        'is_perols' => $is_perols,
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

// Lire les tarifs
try {
    $tarifs = parsePrixTxt();
} catch (Exception $e) {
    $tarifs = [
        'clubs_autres' => [
            'jeunes' => [1 => 8, 2 => 14],
            'adultes' => [1 => 10, 2 => 18]
        ],
        'perols' => [
            'jeunes' => [1 => 4, 2 => 8],
            'adultes' => [1 => 5, 2 => 10]
        ]
    ];
}

// Si on est en mode facture individuelle
if ($is_invoice_mode && isset($_GET['archer_id'])) {
    $archerId = intval($_GET['archer_id']);
    
    // Récupérer l'ID du tournoi
    $TourId = $_SESSION['TourId'];
    
    // Récupérer d'abord l'archer depuis la liste principale
    $archerInfo = null;
    
    // Exécuter la même requête que la page principale
$mainQuery = "SELECT 
    MIN(e.EnId) as id,  -- Prend le premier ID (pour les actions)
    e.EnFirstName as prenom,
    e.EnName as nom,
    CONCAT(e.EnFirstName, ' ', e.EnName) as nom_complet,
    -- Prendre la première catégorie (elles devraient être identiques)
    MIN(CONCAT(e.EnDivision, e.EnClass)) as categorie,
    c.CoName as club,
    c.CoCode as country_code,
    -- Prendre le statut de paiement du premier enregistrement
    MIN(e.EnCountry3) as payment_status,
    COUNT(*) as nb_inscriptions,
    GROUP_CONCAT(DISTINCT CONCAT(e.EnDivision, e.EnClass) ORDER BY e.EnDivision, e.EnClass SEPARATOR ', ') as categories,
    GROUP_CONCAT(DISTINCT c.CoName ORDER BY c.CoName SEPARATOR ', ') as clubs,
    -- CORRECTION IMPORTANTE : Récupérer TOUTES les cibles/départs des différentes entrées
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
    -- AJOUT : Récupérer tous les IDs pour référence
    GROUP_CONCAT(DISTINCT e.EnId ORDER BY e.EnId SEPARATOR ',') as all_ids
FROM Entries e
LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
LEFT JOIN Qualifications q ON e.EnId = q.QuId
WHERE e.EnTournament = $TourId 
AND e.EnAthlete = 1
-- CHANGEMENT : Ajouter l'ID spécifique OU regrouper par nom
AND (e.EnId = $archerId OR CONCAT(e.EnFirstName, ' ', e.EnName) = 
    (SELECT CONCAT(EnFirstName, ' ', EnName) FROM Entries WHERE EnId = $archerId LIMIT 1))
GROUP BY e.EnFirstName, e.EnName";
    
    $Rs = safe_r_sql($mainQuery);
    
    if ($archer = safe_fetch($Rs)) {
        // Extraire les départs
        $departs_archer = extraireDeparts($archer->target_numbers);
        
        // Calculer le montant avec détail par départ
        $calculPrix = calculerPrixParDepart($archer->club, $archer->categorie, $archer->nb_inscriptions, $tarifs);
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
                <button onclick="window.history.back()">← Retour à la liste</button>
            </div>
            
            <div class="invoice-container">
<div class="invoice-header">
    <div class="invoice-header-logo-container">
        <!-- Logo à gauche -->
        <div class="invoice-logo">
            <img src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALwAAAC5CAIAAAAd5sJPAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAB3RJTUUH6QwfER0hnvT/JwAAAAd0RVh0QXV0aG9yAKmuzEgAAAAMdEVYdERlc2NyaXB0aW9uABMJISMAAAAKdEVYdENvcHlyaWdodACsD8w6AAAADnRFWHRDcmVhdGlvbiB0aW1lADX3DwkAAAAJdEVYdFNvZnR3YXJlAF1w/zoAAAALdEVYdERpc2NsYWltZXIAt8C0jwAAAAh0RVh0V2FybmluZwDAG+aHAAAAB3RFWHRTb3VyY2UA9f+D6wAAAAh0RVh0Q29tbWVudAD2zJa/AAAABnRFWHRUaXRsZQCo7tInAAABAElEQVR4nOx9d5hV1dX+u/Y+59w2d/oMMwxD7x0BAekq1YKKJZaoUWM31pjoZ9fEWGKJvTdUFBRRwYIiCtKLtKEPMDNM77efc/Zevz/OgORLvkRJ/H3J82U9PNyZe88+d5f3rL32Wu9aQ8yM/8h/5MeI+N/uwH/k30/+A5r/yI+W/4DmP/Kj5T+g+Y/8aDH+tzvw/03UwSeEGOB/7HFh7UILCFWxvzyecKwggfwCLIRWymTETBnKzskKBkMAXGZobUhxqDGINAQDkjVgQwHSD2r73NUsoIWQ/0AHf1r5vwMaOvQ//e0Lf5AwkQDxpx9+/MQzL5TV7PH7ssGuJsdOpcWTFWmB+mtLogAAAQBJREFUnMJ2ub0H9Dv97PPPPvUkSMnwsMoEDUgGmAEwiO78zU0ffrksN7+oU5cul1155dBBfbSjiIjoX3Uf4P8bopn1978pZvVnb/zIm7lsuy5rlWDlVuwtnzDqKADeGhd3GnLmOWfm5RQemuHLr77W64DyOqJtZq0O/tpat79ru1zvyhHHTKiuq1dKOU7qH+jeTy7/V0DjYUQppZS3WM4/ApqUTqY0a06ydpn55afvB2AaBoCTT7uKmcv2l00cNZIACAPA/Y/+iZndw/qimbXWzLxgziwBAFRU3K2sopKZldJau//KoPlXVYD/VGHWUC4BQgghhO0oR5PWR35DSSQJDAEGgJWeeXsAAAEASURBVLy8LBNQ2gWQchhAccfiP9x3u08ArAF65JGnappbBaAZgACIwIAGMH/+fA2QEXr+xReLiwoBCAEi+ufsoj+N/J8ADZEQ0ohHGhZ/sei6628cetSw9Zu3CXHkqyKhBYPQtrShkE/Cww8Y0nsZNmro8EE9wBoiWFdeuuDTLwkQBy8DmEjW15R/9tkiAHf+7qHpkyewZrD2wPSvLP9WoGENVmAFKEApKIYCXMAFFKDBh/8DDk5/Itp682UX9urR+7hJkx9/7JEtW76b9/GnAMCA1uC2p54BDUd7jRjfrx8D0Lrt6zTAzEQMgvDUgcOqzbgFwAIAK4hAVr9evQAIqQBn3drKJ6hPAAABAElEQVQNADQzg9VB6Hz71ZL9dc2Tp8/87U2XMzwF0waqQ9g62DX8mHCPPuxy1p7Jzfzj7vE/y78NaBhsk3bADD6EDA3ttuHoMDQBCqQJLmsFDeiAX/brN2DyySeRKS1BAH24YHECAAEiCSSJQWDiGNiFy3AA7SqoONhmQIOZFVwHNrPbhkRmuAx2ABAUAGI6+IF3KrWCwQAAg5MA6mqqAZAAtYFGQNvvvjErN7/L86+84BMgb0GJPKfAQaR4GNP8I/SP1rAZh25BIFJKs3a1Vlq7B+fzyOXf6MhNGoZnCxC3TQgx6LC5bFs0AgBi14Abt53aeLIpqYddcG1Px166ddeu5cv8munIJQAAAQBJREFUENvWr5v9xcrRw/uZpgoavjQLIRDIEjAPTglJUACSCJAgSNPbdwggEBjsAoDQAEiyC2hDwnVJ2AAUYAChrCwAnvEUjcbgNcXBXY31b++88952nTvlZ7WtYduH/OcbJ/3Zyw+YqLZrCVUHyveX7z161DgphacglNI/8m5/Rf5tQEMMv6sBgAgSTHBAFpQUCuRZC+KwqVAAAf53NpXOWl0eoQzD2Z0RsjN7H4Xlyyxi22648/F3ep5zIbvJkDCyhcxPM7LSjKyAGUiT4cz0nEB6oTQyNJNw2M8mgx12WUScZF000jE/vSjoB9AKc0dV3cdrNmgCyABcyS7BwxdaojEAkFOCN1AAAAEASURBVATNmRnZABhMTIanUKS/98CBdY0tTZFIVjjseW7IgxVYgAkEIrBnOP+YuYIkEAj79u05ecYp3Xv2PeHEU2ecckKP7t18lo//YRv73wA0zAwQSJORBBFgAVICAYAhm0gkFadSdsp24o6tFBxHEJjBriW3xQL7dDvHnxe0nITRktV7qC8336mvNaCatu+J1ftazLCr2XKTui6qQf5EpgpXWbSXtlcFkhmB3j2pKFfFkgYLaOkwxVlHncTMIe3GdXSbIonl+6p2lTenGTmmX3JSuwA0ASDNEJSMxQAoIoAD/jQAgNZKCykA8cijjz304AONtTUdOnd96PGnTztxiq2UJBiaEuSLAAABAElEQVRS4vt1JQ1ircFKCAESSikAQgj6H4HkPVUAMGrMhJ///IxHH39x5coV995z+8233n7vnf91aF69i49gRf4NQOM9eYAABRmodty9za37axvro05pfbQsplIOUq5KuSqeSjIJR4cEa0snJLmQRrbP56halmlJTTq3Q3qXPnX1tSFBsf2rAvuW+3uPiAB+I+FK1WoEONNKq9hS8urdrbuqgSBye/Y549z8oRMakill+hRAIGn45m+o+nJ1GcGtN8zMtA5NDSm2tY+FCyRFAAAEwU3UVFUAYBaAHDBoAADWDKQAec3lVzz53Gtde/a/+dqrbrn1jgt+fkHX5UsH9+nBzB/MRvNY3wAAAQBJREFUe2/b9u3HTpzYtWvXvPwCImgmQx7cNclTSH97sQ/u4uBkIgZAGmm2He3SowcADc9VcOShlH9x0Hg6hgA0xpJLK5o2lNWWVDUdiHNDQjoy6CcEtA0hWAglfDACTAKsGZSCX7KW0EKpNLbBLc3SSFnhokGj6tcu01Ds1FaUr8kdNMS2TcsB2BIkM+zt22a91borOeTMGxqc6IF5z5e+VG4FCjL792tQzCT9rva7HPFlxk1t6gRbQSXT7JaYoRikQEZpnfPc8t3njuzuVJatWrMRBLiuFSwaN34cACImI/DWS488+dxrEMZDf3ritCkTNn238dV33nvs8adfffZRInId58ZCyL8AAAEASURBVLZbbzMMmZOb06tP//vuf3D0iKH79+1Z+PGC4k5dpp9wgufG/1u4OWhTx6OV33z9DQHKTRb3HHTSSSf+43sT/jVAowFmSM9Ck20hGgYrGACMbXWtH2yp+ba0tTIZtZUrDB8JXzAtoJVhUMqA4x2LCYrBBC2QcmG4wlRQSmkyg65GGuIB6WMK5/SZaGTNQ9Mui3Tlli0Fx6UMabmCwZQhJW9YV79tW0bO4Hbjzwum6+ie7xo3rd7z7uPHFD3oyyxMcBLC1kL4XG2bLiCCDkyjxTHDLuCDFiyiMjh7s1NVvaps/jPVrY40oRzZZ+yJHToWADYRNdV8d/udfwAw+JhxJ0///ZLgAAABAElEQVQZD6DfkP54571FC5eUN0eLM9NOP/OUs2dPf3Pewqrq2qrqxRnhzBXLvjjjrHMPVNZavtCmTTt69SxytDbJAazD55E1A8xCaDC0Ywjrs/mflOw4YAqytTv5hJPywkHNWhADQkMc8cn5XwE0wjtOto3BAw0pSGNvwnl3ZcnikoraFLHPL0y/zxQEL+rnCHIJpDnNayXR9hQRaRIQYNLaBJTjkl8kncbUqq+NVIvVO69oWPv9i3b4iWL7SxNl+43uQxxtGtAm7L07lgL13M5olray0toNObpx0+poxabylUvzp57laNYERWQwp6CZ4FMg2C6kK0VESmE7iG5SFd8+/+qs+cATrwAAAQBJREFUqpVfQ1jKsdP6jep57gWNaUY+AOCdV94tPdAI0LHTZhggQPmQBFBzoGLnvoriwb3Bta3NDQABcsioMU0tFeeeefaBqnpI2A5XVNb16llkCtHSfOCZp99cvXpTQkVHjhp3w7XXhkMBraE0e3Y0oN577wP2wuxG2hlnng4AWkNokGib5iNasP990DCgIaTnffHOpEJoGJ/uKn9u6d7NET8F2mf5owG3JcWGbgv8ek4wdgSUUAATM0GDmBiGFgQQaxKmct2AoY2myg0v/6F161ogEcyUmYXtrUCIEjHE66u2flfca2TUBaRrx2pa9pcIAI5iDtp2oKBDvz1+v5u0y9Z83RFruDsAAAEASURBVH7UcWZa2GXroI/skMONCAw2SAdy0dKy8culG78CXGGGfOld2w85qtNJp+4m390LNs7snDuyKOutdz8PACkZLue8bxrscTmWHWsFoNiuqW8AEKup27ZtB8BEgWAw68prLiuvqvcLKwlHpuW2K8wBsG390vMuunT9xu0AgmnWpx9/surbFXPmvhsM+KRmYibDV1FasujLrwBoxoBhw0YPG+BqFgDocEP7SORfBDSQYLBihpaWDbyxZvfby/dEEAwHgy4JR1sQmdCyzVsFABoCpmbT1iCWUjCUByUFU0ATa1sYQpgh6R5Y8lFy61IS6e0Hjg4Yvr3rV5sGEUGy07B9feccMJ7KAAABAElEQVRokzTTtBCcjFnV0SAgosqnAxHbLwu6Bdu1j+4vjVVsT5Tv8Pcf2aoI5MI70H3vISPABWIRINB5WOeJp2m3OZydk9axr8jMTSnSUbGpya7eW/JWU8mabVsMIJCdv1dk/3rh3nO7umv3NwIAxxtaIgD27y2vqW0hkoaPvv1y0bSTps6cNuPu+x4E0KN3/17di6vLdpx9xs82llZCBJ544bmzTp58zsxTP1n44aNPvXTbTVeCNNgF5CcfL6htjUvDUK576qkz0gyhGEQC3IbzH3eOP0z+JTzCBM+IIUUyAry0cvvzS/Y2G4V+05fptGQ6rYY2U8hkGID0fHsMTcSu7QatPU6EMwAAAQBJREFUoGWEoglEU2Zjyohy0CWLmCUrkBTCQqypctcqV0BmdOg089eFNz/e5dobksLRDCkouW+LXbo+qOMSLqCTMBOALVpdNFDIsYvS0np1VgDchua920yDNIHg0J+7ZxkAa0O7cUAWjcycdm14yhXW4CmN2R0qEXBcI1spn5FmZxZs2bU5mXRTAAzpU61OS81Ha7cvXr8HAKCjKQawas13Ec0+0+/YrXnFBa++8sZd9z4wZuwgIaxrL/+FKd1fXHDxxtJKACfOPOfqi36el9tuxOABAJ5/6Y3GhEskSAKw58yZC0Br+NIKTznlFACSQCQYpJn/vTWN55gDM4QkEh9uqXhtzdB/RmoAAAEASURBVAE7nGMKTrIhIAAQbIsdOogYkHBcJ5Zwe2bwtdM6pSAXrymJK9dKC++vaypvDhIJCQ0YkAZHG6O1+7WA9PujRPGW5v4jhw9Invvhs68AEqkatXdt31HHlDRFU1rFTUsLJBu2OVsXZRZ0jzaVJOsPMACkyretz5x2FpsW6RRBMB2yCQ45PKQJVybivuYG7bYoKJMkS+nKVIqiSTMYUMRVlQRIMxSvq1v5uyvJJ4xwRqqmFgDAtm0B2FayCwAjAY2Lfnl5fo6fGbPffv/Ageajjz7quWfu+XTJtySJOfuyK6/y5tCOtgI4UFm3vbTimH6dAXPL5tUr1663iGztDhsxelAHA6bHAAABAElEQVTPzvje5iPyIv//mkdub+shzzHA0MQ2yAIJFyzgCkh4ZpkNEkzi2/11ryxc0ypySSdsclzyuSSJyIAt2SFIYlNoZXEiz48JAzpeNrJLvxAA46SThroQJlDa0HLJ7I37lJFmSsdhxU7ESbEyoeFEyoYGW08fN2hIQZ+0KQMGfPbZ/tJKA0hW7Zs+qtP+jzdkk1ulE60MV6W+e+VRJ9FEUvizcgK9+gXS8n1FAxyHfWSSGxJKBslmkE0sYZnaAcGWkrVLImFSc8SIAWy4PunCMThpMAsFOxrZXy4AR6n0Dt27jBnTUF3KyaSd0yXS2ODGEh9trphcVbfuuy0AXJeD2b3OPV+fnQAAAQBJREFUOu98AKy4qKhrURFa6/Y+fP+jADFj4LDhE0cP1oBwoyXbdgHQsdaa2mr06wyIj+fPjSTdoCFsF2f/7CyPshOJ1n704aKG+sikiZN7D+iqtJZCMCsv5K5B+GE4+sk1jet9Rxu3kQku2AAEEQiqzYQFERmtitfvKB0/pFu7/JyggCRK2TqSdBO2Y7tKESuZIvalG75OWcawLnndQgEA+7Zve/3dd+644y4v0NctO+OKMT0+3lfV2qx9KW0E3fSC0A6NOJCK1o/Ptab39A4xwRMmHfP0c3OEoJJ1K5q3brr3xAEdff2vfivv0/UNrKl7x6LfXH9v34G9cjsNPaBVSVVkw4bBodsAAAEASURBVP7mbZUtiUjctYIW+QM6mSIz5ieL/ZJBmn06lQTAfgV/SjhBN6GEFqyENoh8QWYi23ZtBQid9HcZ4J9xc54TTecmX0wnYo6LRDSU8dBbC0pKS4lIaQwYPqx3lzxwCmQ6IBOY++aru8ubDSPguokZM44PmQSgtalmx55yAFA2aRuAHWt5f/ZcASRcnZ3bZdq04wGsXfbteZedv6OkFEDQlz//owXHTxrWEokE/KYUkJIY8gf6+35a0BDDauPmCgASLB0X0k0YlgGYkNDaFXBg+oAAcMlxw31SmG2t1cFwkqetoKEIhne/pspdzz3xdms0WdnU8NQLrx039dTRRw/SBMHHaSlPAAABAElEQVTqzEGFJw4qjNmakpoChhXP33I/r4gAwOdLllzw85OSDD/hgtNOe+75ObbBsJvXzFt4xaQRADI7Fuv126HIzCi8+LIrAUCjh8CEDjnVg7C5oXld+YGt5ZU76hNNdoIRVK4k09BkspAuA6wIgkEaUpGBgxw3Iggi13UdrXDQF2vH4ymViriJFIcoZGkZ8PvD5Zv31tVFfdKfVMkxxwwJAGCHyJSAdiKz3ngXgNJs+LOnTJ3i3XzLlvUHGuoAQJimMAGs+Wbxpq37ySJty6nTTupalLlzy6YTTz6lpql+xJix55x2+n333HnD9ZePPXZsUccut9x4jTel8jDD/m/LT65p6LBoXAAAAQBJREFUSAEERRAE0gRtQbAJfFddB4jBBTmS4QJgWARLMNiG0iChhABpL4zjmW1Cwzss1tRVT50y47st20K+9FiqFcBvb7zjqyXzpQTDJWVbkoKWActIQfvCyOvYTu2rBWjFmpU1sVRWyNdq24UDRxb1KCzbXQXQ5ws/aGm+MSMzvU/n9gLQ0ihZv/bLFd8dN2qwZpfZFSQLLKOgMHNSYWZ8SO/1jfF1ZVU7y5oqY1GdTFWmGiFJKwlWIAYzCaFZ0sGDrdaaJBKJhJ2yvXecWFwqF5ohrFbt9yPhahiQLWV7NaCVFsI3fMwIBUi0MSzXrVy5atMOU0pH2QOGjDtqUH/PSPl68Wq3MOcAAAEASURBVOKEYpA00zIL8vIBfPTeeyl4SiP79DPPAVI3XH1lTVN9Qadus956q3txh4wgX3j5dZu3rvtyxQYiUoqlOEi5/wEW8k94etIe90UDDJfQ6ri2o2yLGMIAKiPJ+z7+ak80QQwfWDA8j16KLC39IEvCkCyFhlDaAlkgKQylXBB/8vHc77Zsm37GBTv37Vq54uuTT5qxbNmHr7/+DgGOy8w+YkuzgIILgsjvWNyNAAPW3s3rVq7f4gKfl+y457O1sqAzNEH6D5Rtee6zZQDGj56cBoSUA7dx/kfvAXCkcsnQIEDFmpoRTwW1GJMfvnZ4zz/OHPGnM8f9/uQhxw8oAqu2U6Bflqy4AAABAElEQVSbgpMU7IK4jdXZxjknIdrYghJwWpssJ2URuRqOtAIU80ltKCd2YI9nCGozp1oFWwAWltdq8RefxV3WJAE9bfrUgAQB7DYuWrgYAEDBQKBjh3ap1upPP/mSIJDigi7djj92+DcL53z69bcA+g4Y2b24A8ATjh0tgGFjphwzfDB7tsKPkZ8QNApQHrOI0GLbW/eXkSSHoEkBXBDOcKK6OZICMXnkObTx8BRBC9bkMjlMNhluTf2+r7/5PBZNGqYB6Iay7QAG9+3WviB/xMhxH8yfe/PN191++3XlB8oNaUELyUIoARsBRYAx/JixDCgDQOqTeQuDwEmDB4wbf1zXUSe0PC05wwAAAQBJREFUkWEYr81euLIqHho8KpyX4am22bPeKauP+eDzKUOy4SRSf3j4D9WJaNIkdlyopF+h2C/6FWRO6NGJFAvWAHIzA907ZJKbhHaY9cEHlxns8wcMn08APiBZW6Vbmy2tpCBhCH+kIlW1lyMN0Zp9RFBwM7oO+nBb6ye7agk+b3PfvmkLAKUJIn3ihLHeffdu37mtZK8JE6z69O+bl5n29WfzSyqrpQQYM0+bFA6Kl1+crQAQSvccqI8mAVJsaNDFF5zvl1BKEbkgzeRF6P++/KSaBoc28OrW5uZkzDBEgFmIBJDsk5/++3NP7JWXzkihjTfJBlw/UiYSpLVQBilDkJVqdTTs1RwAAAEASURBVH9x+nkTxk85fupZu8saAZmZWwzg5Zfe2ritAgCR8cADj1566XlPPfWkEIJNpIRWRgqWmxJKA8dMGNk+YJpaEfDpws/mrtrywbot0YamjE79zLywTztEwb3r19z87soH1h3gnt3jEAHDrCsvu/+uZwHPN4Q9pbtXr1qTGQ6bBMeiuKSkALMDoLGsGgwJDaCwQ3q3brm265hgAVYETaxJuEpJg4xQgABJSDU3NJbusIImA1mmKPn8w7pdW0WsIV5XbREAHercLxZs99rnG9bXNIHIiVTv2V4CAKy69e171FEDFABg8aLFDUkH0gLEmLGjAMx9Z64DaCLDDF9w7qmtTXUaEUUzAAABAElEQVRffrPaEiDCvp27S/dVAUgmnY4dekybMgWAIOVtBz+cnPwTgoZAxG0umFB6WnZOBgGO7ba0KsAKCTUwL5ghBWlfmwdBQIAkBCA1FHMCkgCxe8fWVV9/K0Grvv3w0utucIGevbtnEKortp80ffpjf3y8rq4OwJ13PHTddTc6ShFgerxKqXwEArr0GTr26H62dqVh7N+x+u2P1sZd6TZHR/UvPnbMiBRA0kxUb23at3e3MkL9xgFE0D6oZ5+659U5H3nDee65F0U4329ZUtvEkPCZ0B7F04XFgEtaAPt271yyZZcTzCRlBLTU0EQOs89iyibKKOzAQELCQGvVF+84kd0ZwgCSRQAAAQBJREFUYUQ/e7Vxy45Bw0Yl9m1WDiwmQYavQxdTqAoO3/FleW3CRUtDRVUdQGB33LhR2UEfAdDRTz+a5wCuZKBw5uRxzQ07P/tihQ/Qrq/PsGOGDxlcW7a2JtbimBCWCVW9YPEKALWVlSdNOb5TcQ4AIUwiE5DiIHfs78pPCBoJSAIMEKO9FeyYnh11VY1SlZEEIEm3kWIPkdW8Xzz4SGGR4Xvvg/d3lFdJUwoCSErp+2reu+8tXDJm4rT+XdpLoHzf7utvum74sJHXX3fjkiXf5Ofnm1ISQXhEJEjh+crJN3nmWW39gUrs/ubCEX0uPW7gTWOH3nrZVSECcRx2LK1q1y0je1FYaO4AAAEASURBVP7h4vMHDe6bdJUhXBixS39xwXXX3/Rfv77hsSeenDx1KgCQkFqbGgKA8DXUVb/97nsAvEzbnRtX1n67uD0JZVkJsMkwFZE2NISrzMJ+o7TPJCUzkXD3LNv58G93PHHr5nde6Hbcaem5uU17toIRI0OFis2OnRzhmL7Alub4s2s2lSW51TU8s2ns2LHeypVv37hs+QYA7CR69ek7YsTgubPfKWuJgQiwZ5x+OoBUiqXrsCbWILgL5i20GV169PjVdVf9+ZwfYon+APlJs6ra0gpd5oRWjtpUXfXQosXfVlYzMzvMB1Mdne+zD5WXflZfV3PlpRcBmHb6Bft37yxK+54pATh6AAABAElEQVQDcNmVtzPzM/ffA8AwDcMItEFf+oYNH/H6W7MPffXh+WYtB/aO6JBtAiRDwYC59LvN3tdHK8sHdcgAYCCY375PTW0jMy/6aLYBmATDEKA2D4AVyNy4c69mVkqz0tpVzPzYIw8XFbY7bPa918LQkAt6P7G072ubhzy34ahnvxvwwrbeL24f/Px3E57+Kr33CIAsKYQfPgBA1tCzJ7x5YNJzS4Jd+gSBNCC9qP/gpxcPfWHtiGfW9Xh5w+AXP/rtrDk52e0BBLM7bNhV4Q3qmQd/C4BIArj/D08z85SxQ70umJkFK3dXMXP13m1dMoIgMi3TEkKGOi3buN1rro50WX/K9jXcJQAAAQBJREFU2NOhWDAAQJDomJ/vDwYcxwW+z7I43DfAzI6r5rz55uBBg59+/mVLik/mvvna66937d33tPMuvfqqqyZOOG78qGHQuOhX108ee5TruESuNKTpC4B57ZpV55/zsxmnn1lVW0+AbmP4awDp7Tuff8l5DiCliiecxx56AgAEUpAxAyQgDV9t5bYLL7jwtVlvHnfCabff9muHoV1tHozSXHbV1QN6dNYMJmrzxTMfe/ykt2bPXrN2xYYN69Zt+G7Nlg1bt27cuuGLl+69sp1lW9IOWCJk+kKmsCztSjMVyBtw1plZnXvaSuskXIQKjp4x7Bc/13u/3fzKvfH92wxCCvCn59/VXocAAAEASURBVOanpZkuW672uWz7ilaWxqJxDaB9x84dOxcBDJWaN38hAGYzt12XS3/5s73bN6xY+R1JCWDkqBGDuxUAKqddh75dO4GZFASxilUuX7YagMtHnl31U/ppCMSQAEuwJAYCLM4YOZKTKU5pMkSb9+Uw4iEB0dbmd96dbfksARgCNvke/9MTfst34x8vnTFuqGf9aAUrmPb0C69PmTx1T1mFIUGsDUEsfEqpD9+bU1PXsODD+TkZaR4iW+xUdcIZeuZFuc/Nra+qlIb/vdlvPDF12jXnnbJ5T+m+ypaghG04EOYnn3xYWlN98owZd9z7YG52hyeferq+tiaQWXDC6efdc/fK3oi7AAABAElEQVRt1LbBfe/QGDBgoDdW27bj0VgkmUxFWl0nWdzO7Ly9Yt3u9QkjYDh+ZSgVJl8gN+DLT+8w8pgbe1ft2YaYLdI7GwNGRU2O7fooFk2Fu/aMVFVyLFW7r6Ri2bLMoyY6Rsrvskrl6WB7IyeUOoDCjh0DBgDasmbJspVbQBaYfvub/8rOznrukTtaHSUsyYrOOGWmD7BTthVI6zO4/4IN26QWSjKgSkur0Eb6PNKF5Z+s5p4+lGIisS3WGCDZ2cpoUyxaw2yjSpM+qNeJtXKVdk3TF48nfnne2W/N+4j8GdKOuFpf9OsHXnrwZsBV7DhsKiVCpthdsuWmm6+bv+BLAAYgyeNp5AAAAQBJREFUJDGEMn06Gb/oiutfevoRrycbqmov+WhrBnFo8wcLn3hCkOkHbF/2bXdev/DDD5MtZsg98O3O3YC46qbf3H37rTmBEGtNPtkSa26srSpq19EKhgA4qWR99YFd5TX7y/dVlJVVHjhQVVl1oKIyEonGY5F4pCURF5Fki+etBHyew8VAkGGrAPtCuSGjkxFIS3YIZxYV5vmzdWaWyM4NBNshLTOYabmxaLKmRtVV1mxd5aIg67Rz7KATjmro4nCgfuesKw98teKo42euWzQXwOVnHPfc3MUAJk46//NPXiNdOf6oUcu3lDEhPafbnt1bPp7zbnHP7seNO2bJp+9MmX6uZBv7UHQAAAEASURBVKkNO+Xi7IvveuvFO21owzt7HIEc6b7290WzYpVgrXa0Js5+56tbFm9sYWaX2dHsOlq7DrPruoc3SbmO66a0TjBztLmmf7euAIKGBSC3c9/SxhZm7aqU7aZSrqtcz2Rx582dNXn8qODBEZE/0zQsCN+nK9Z7F6zeX3H8Mwtv/2zLzqrqm391GQCTIIyAd/2Tj79x929uzc7OffPtuX85itq6qq++WHTf7/9w5tnnDh0yKD8r0yMx/Q8i0kJZOe3ycgry2hcUd+1e3LF796xwDzM9EzlB+AsQ6IVAVwQLgTRAACaFgjKcYxX3zRs2uf0Jlw+4+k+jfvfepBe+mvTK1uGJgyO1AAABAElEQVSzdg97e+OYF1cd/cymYa9vGXjr76QFXyjj2bffvPt3/+V1ok//kXvLa5l56RdzQxIGEYAzLrqCmU+deurWku3MbKcaR/XqTIA0DAA33PYwMzvK0Udq1fyEmoahCTZgfbC14rYP1gcted/Mocd1bd/Gt4KwHddnWXa06cCBehnw5xUWBkwDgFYuEUgY336xaNq0abbSriGV497w+0f+eMv1WkOAQRpEilkwkzCA1LrlK+Z+MH/2u+/t21+RBo4CZ155y+ynfk/sVMXsekbvcMgEoN17bv3N/Q8/llQaQPuiTmvXrY7F4rbt9O3dw+t5dXX12nVrV61ctXTp0h07dlZXdE/N7wAAAQBJREFUVx0+LtM08/LyCgoKunfvnl/QLjsnJyc7O5wetvymRQYUgURDUwtZZkF22vIdzuINjfH2LW7YzW1KZ8uKB9K0nVKpVmI3Oze3sSXWUFZi1O1J1jW21tRyLOJobYUCvuy8nI49g50Hyi6Drdz2VigrYLjVK97f9cZsp3kTAJ8Ux8+48PHHH+zWIQfAzOmT3v/kC0mkpLVo1bIOOjn15IvXblqRk5tNwM0XnfLQK/PJDLGTePntub/42amsFZE4MiLWTwgaG2DH9ZGKkPnRzqr1m7b3SfddOGW0FARoZkVkbN288dyfnb2xZHsoEOjdr+8pp51xycWXFOTnaAXWLE164C+f2p4AAAEASURBVK6bf3v3Q6aULptpue2WL/+6f7dOWmkhvRxFl1nHk04glOY9efV1NY89+sSzDz/Q5LhFfUd/t25Jtl8qkAS0YmKbSEEGV3/99eJl38Kwpk6dOnhQf6/D+/bvX/zll5999tnSZUurKr8HSiAQyM7O7tChQ//+/YuLi9PS0oQho62RysrK5ubmaEtrIh5vbGxsam5mpX2WH1JAGg64JdUURa5wO0BUc1qcEgzECO1CaVnBcLoRTDcysvI6dc/uNTSSVmColIg3OZH6htqySNX+RMWO+P7dsaZm7bIvIy+3fae0Xn2z+vZPT0BX758+quuYo/oOGzwIwIIPP3j91Vfen/el3S6bAAABAElEQVShlCKl0K5T94p9W26+7Jcvzf5y94HSrDTTAL/w0C2X3vwAYOS077RqzYqu7fM80Py9VJi/Lj8xaDT7lA0pUsJMAnBUmiQSAoCAnYq1jh0zcU9FzSknTP/0kwWVtfUACtt3vePuey+/5BwAgIKOT580+ZPFK/1WOGlHpp/6s4/ef5v4IOuTHQhzzgfzP/1y6UtP/NE9aNg//7tbL7/tfpnWcemyL0YO6qEVhMswtSbHdm1L+oTwHepnbW39559/Nnfue8uWLW1oqPfelFISUXFxcbdu3bKyslpbWxsbG/1+f21tbUtLSyKVzMnOaV9QmJ+X27lz507FHdvlt8vKysnMAfuRVwAAAQBJREFUzEhLCxt+U5pmIpkqUYn7V+xWNWGrpT4WkmmuNFOVxLFIpLWxoTEeiUrX0dIytCbDSm/XXoRzMvOKfFn5VkY2rJByUk59hVu1r3bP9oZ9pZGyfSLWEMzJyOhcdMEFP/v1ueekh0IA3nvnzcefeHL75s11rTEAI8ZM/Gzeq/36DmqIidXbt/UrzhdQ9//XVbf+/jkAN9x61x9/d6fy/NdHWtDkJwSN67HZlAIpLaSCNAFAuyANsqDKtq/s2mfMeVf+6tWnHi/bt/fKyy5e8PlXAADj/AuvePqpB0NBH8B7dnw3ZuTEppaYK0ylko8+9+J1l17sMAyviASJ1mhs8pRpQ1kEmAkAAAEASURBVIYf/cyfHiVAg52GPUcNnVCyv+mjL7848dhRWkOwgmCX4EBbsDy1tOSrJe/OeXfhwk/279/n9dnDipTSK3+Uk5Nj23Zra2t2dnaXLl369u07dOjQQYMGde3SNSc3JxQK/e0ZsIFfzFtTVpUY0D5jve1riZl+u8X1BUiaJrFJ2lQpspPJ5nK/29QaiUUbozLFrY2R3GC4wc9pOXlGTsdATvuMUNhQKdXYmCwvryvb0rBjQ+O2zUX5oTGj+58y44STTz4tmJbRWF/z3rz5c+a+v3nT5p7F7b5ZswHA/U+/8tsrLow27Bk5+titO8o6dOm1fPnXxQWeY+nIMyx/QtB4tQ7k6LyFAAABAElEQVSIBYM1uQQpWAB2ikwNCsDZs2HRoKNPyOg2ZO2a5YVhv3Zbb7jy0sdfeAdCQOvjJp3w1qzX8nIyScp3X3n6nIuuMgwzpTiclfPl0iXD+/b2SmgoaFPI71Z9OWTk8dNOmnHfw48d1bMz0Dpq6JiV6/eu3Lx2RP9eLnNbcjMAoLmp5d335rzx2mvLli3z3rGkwQAJUlp7ea8AwuHwkCFDRo4cNWHC+IEDBxYVFf3lCFNaO+wRCyQBvoMMIC+63ZLka97+dNzAnhcP7f7wt9ve2VDer2NOfcJX1ZBgw4IiC8pkxxA+xZY2NFlaI0V21IlFIy0Rf7w1Vn9ApKKu9IezCs2cAAPgnQAAAQBJREFUIlHURYbTXNd091en12/MTa7dvWKFHbOPGTN2xowZJ5wwnYSxd9eO39/z++HHTXj4vlv3V8bvvf2Orz+ds/CbVaGM3Hfnzp1+/Hhu696RZ1j+00DDbUQA3cadZQFyAWaYLuA5a4QWIMcmQ4P8cOv2rRsyfNKB+sitf3jqd7+5EkhA2zdde81jT71Bls9NpY6bdOonC983JUDOJWef9tLsj0O+UCwVHz7uuEWffZTh97uuZgFTp2DIX99wxcOPvhzOKh43cbTdUrHoy2V9x01ZvujjDEswtxXEqymreu3V116a9crOXTsBkBSmkAQmQLmuwwCQ2y5/3MQJ06ZNGzd6dMo9nREAAAEASURBVM9uPf5imAyAvDg4wdY6puxYMt7aGrMVJ2Ox4vx27XNyAAhwaW101pbtFxw7vCiV+Gr77rwOnYtzwnd/XvLN9hq/z08w4giAhMmuhtDETCzBBmuhmUwBIcHkplJOpDnWWGu1NGs75fgdZGbl5XQqNMQdFx03OA3r169btWrVV0uWgDFs6JCJ40YPGHS0P+hft+rTk0+9sLKqBkDnHj0fe+LZGVMmupoNQQdDk/9fQfO9Zvu+dI4CCC6npIAkCZaed4//PLDkUT49kGudOmHShE8Xr/SFcud9smja2MFQgIyfPm38e5+uzSvo8ugTfzzrtFMIWgpZXVk6aez40tKKuBXHvIpQAAABAElEQVSGHbnyplueeuj32mGYEA5Boq6lYshRw+uqmgoyCpUW448d/5u7/2tg727eV+8pLX3m2adnzZpVU1UDrz6ekARhKpVQtgZ8Aev48RNOOeX0yZOnduxSfLDLrgYLmPibwsyOVlHltra0hAKBrLR0r3DEvubmmO30zc9T0SQs+V0k/uKq3Sv3Jm1Ky7SbQwbVWIVacNBtUuQZY4fbpQyQJvKIOFKQJEas1Yk32/WVTmuD4FTn9vm3nHfC6G6dvAalZRVLvlq8a8vGptZIl67dTjzppMyMjJKSEoCOGjY0JytLa01ER2b8Hi7/LE3zfbaerdkS5NEiDsZND8/lOwQ4BciXIkSrVQAAAQBJREFUn3744qt+DaBj9yHLv/2kKL/day89ccONv85t133W7NnDh/RPKiWJpWZhmEs+mD391LMT0rDYgEFvf7zwtEkTALgHC40889TTS3btfumRB1mlwmabwVFdUfn4M0++8PJLDdW1EBDSZynyadiGSLgO4PQq7nXy2aeffe5ZQwYOaOuj2wZwJZgJxg94IhlwwPLgML1hu6wFWLCAoHV7q2//YE2JaOf3mUPzOCtgrNwXS1jhIDeaWtlI/wFfITQZhoDfgHDjbqy1trpqYqExOluFfdS9e89eA4f5pACwe9euJV9/vXbt2kAgMHr06IkTJ+bk5Hg30VqLI8/HbZN/EDReGvutFhMAAAEASURBVDWD5OIvPnjy6dcefPiJ7l07aIDgAEywAAYrkPHnrcizYRsqS4cPGlrWGFfaHXf8pKEDBj766B/7HjXywznvdOva0VYKRNDaMtqa3/HbX937wBMhacWU6ti994IvPu7dqXNzyg5o24g1phoj+8pqe/XroISjrYy65sRbb8955vEnD9RWAvAbfhNQmhNCMxQE9S3q8PNzfn7uJb8s7tzRu38S8AEt1TVOcyynQwcKWZp+EGGAmZnBrIUQ30ftmb00WBZocMQ7O5qeWLSvKJB85oJh5VG+6r0dMIwsXc1sugj/XYauhmBhsfYMAC2FcJQ6qbN5w7E9q3aW7NhWUtcUTUvwJiq9AAABAElEQVTP7Nu756CBAz1krF69etGiRSUlJR07dpw8efLIkSMDgcCPWN7/QY4ENIf0BrNi1xZmoGzvzilTJ23fWdapc59nX3h+6vFjXGjWSsIguCTMZKThuy07fIFgn769/FZAg12lLMEg85k//u7Km24zLcuxHYCnnHDyrHfezg0FoVlBO1pbUu4oKVm1cs2FF//C0ZHTJk35ePEKYYS1Gyno1bVfduE0M3dqbl6seScLHaIMX0xWWuKDaNU7e7ZXNbUACMGnBbtSK9La0SFG13D6SUXF03v2Si9sX0HO6GsuS+/bW7W2tOzYu+GrpS75eowe06VfP53u1/RXNietNQDPnW2aBkqnqwAAAQBJREFUJhExs/hvtaIPWQ6kgCSTrGL/o+9+e0zPolOHdH5txfY/rI6LYDikI/r7S//eankURyKANKCZcuzq+342amR+JoBUMrF9556NmzfFIi3ZWdmDBw/u1asXgKampkWLFi1fvjwajR599NEzZ870FI9S6pDW+VF71hGCBt6cKBvgeCRy7PjxazaVWKZhO640wo8+9fg1l/4CADMTVHnpjgvPv2jx8tVCmr379Z555hlXXXlNu6xMV7MAwU2cNu24+YtXGEa61q3jJ0x66/35BRkBraE9+4fdptrq02ee9eQ77/cJ+z+6/54LH3q8RZo+1/GDJ+UWnF7YrbNIaqsFhuVXmVvftI8AAAEASURBVBsbnXllO5fZLY0EWJbpkqXZNuGwTYxBaeGZhR2PC+XnCuUmW92kjpvS6Nxe5Wb7WhMoKS/1qWOee6DdyGPhrSTzX1JomZmInn766Z07dz722GM4WOLqsLVtC62yx7rRNjjFZLQqmWFay8obH/xgbSXlaDMkmAEXZP/9pQKLth55RRCIQSoZPfPo4mtHdQ2yAto8vNFYbHtJyabNm2OxWM+ePUeNGpWeng5gy5YtCxYsqK+vP+qooyZPnuxB5wgMnX8INKwdEvLma654/uU3UvFEEpA+Q7kKis+96Kpnn3okzW8BuPvXv7rr4Sd8BFcaynUB9Ow3+JHHHj3h+AneDSvGHB6bAAABAElEQVRK142dOHVfWX3Ah0QKfQdPnDXr6SH9egNwtSvYETJwyc/P7jxq6vXDBi666ur7tm9cF40NDWWc27Xj2KxsSth+Jyn8cqcw3i+vfa9qX0xpv+mXLF2lNCnHUHB0Tyt0XqeeEzIy84TrqlhCatLkd8EQMa2Uq0yQNOFK007LTB84KOOYUTnHHB0u7vhXZ4CAR/74yLtz5qxcuYIBaE3UVuxTH+R9GEhoSAXL1CCtIBSEtba6/sZPKvYnzXxqFjA0+wVc9weYGUweaYC8ivkEAJRiZOvme07sN7E4F1oxSQfabMuiRE1Nzdq1a0tLS8Ph8PDhw/v16wegtrb2q6++qq6uS11KEAAAAQBJREFU7t69+/Dhw/Pz838sAORdd931Y9sc8iN6NZJz2xXceNNNU6ZM2r55Y0VljWUSSWPj2lVfLV03fPSYgpzMhfPeW752gwJYawCG31dXdWD27Hdth8aMG2sIpGe1H3vMsA/nzW+OpKTPqj2w7+05HwXD6UcNGWRKScIEsOLrL99/5fUTO/Uo37ppdVnp+I4df9Wj93BD+ePNEEYslLmoMfLwli1fRusdk0FkaPIrWNAx4RDxtIyMXw7oNTYjlBlvJSdpE2sIAaGYFREZQpgGm8IxyWIKtMZT23ZVf7O07ovPY3VVwZ69rGAokVSmEFAq3tDAig2ftfCteYuWfH3Zb25SgDBJ6mkAAAEASURBVJXQsMSh+TlYAVAQS6FYaBtwlOFfUdl01/xNlSnT5xOClBbEXpLfD3CyUVv9UPZ8BF5FUgnRyNheWTOqS2GWHzYpgw06uN2kpaX17NlzxIgRUso1a9asWLEiEon06tVr4MCBgwYNqq2tXbVqVWVlZW5urmfruK7rNfzbiucfPT0dbo23Ntdecdnlb707jwimKW1bZed3nTPrucqyPRdcdtVNN/02Ly3w+JOPVtQ0+KWhtHQ4ddzUU1986ZnO7dsBWPr5gpmnnN6cSAaMUKvrAPbQUSOvv+zyTJ9vy7ffvjzrjcpo7KGBowdoVaNb8zKzgqmUz00Ky7/bNN4u3ftpxYHxH7d/AAABAElEQVQWQFqCFROkawiQi6Qe4Q+f2aHHyPywXzvCSTI4JUXCMPyu9it9eN0HAJqgvaeYWTDDdVviST1o6NH335vevbudqF//5POVHyw+6pqrnQlDr73octGaHDHy6N/cf7fPtP7a9PyZfFOfvGvO6nL4w4bhdzVADomkCYB97hGvAiUMqaKNP+uVefPUIT64QkmSf33Jq6qq1q1bV1NTU1hYOHLkyOzsbNd1N23aVF5enpmZ2atXr4KCAu9K/puVtv4h0BxsS2CtlG2YfoAfe+ShW2+5LWE7puF3XJ0elNNPnD5/4Rffrlw7pF/33TtKbrj2mo8+W+wDsemznWS3XgNffOmFrZO3kgAAAQBJREFUCaOPBrDs0wXn/fLi/RU1ps8gzbajDuspsgMZf+oz9BgVtY2kzQaziKeFV7dE395TsjIag5+kkmkOFIwUaYftAoHp7Ttf2K57MVMDtxJrA8yCUpISUlhaHQ4aT3RbolLbrDEQUqopGjWmTuo0Y/p3779lL11XmLCGzZ519Qdv9OvY44xJ0ycMGxkLUe/+fSUb8Xg8mUx6ZnIwFMzNzu3SpVunTsV9+vZt16vPU0vL1kbSUj4lU45PCcHCJUoZDNKmOkIenTLYcAVpQNXeccLQE9vSeK2/VF2HHu9UKrV+/frS0tLBgwd37tzZC4aUlpZWVFRkZWV16tTJM4D+hvwT/HBxNB4AAAEASURBVDTeHTRAB0t4f7pg/tVXXb1nf0XA8KV0igypbHndzbc9+sDtAKCdu2695Z4H/gjANH22k/KFch544A/XXnUJgNVffnzJuRfsqmkkQUqYLplQ2kcC2i02fQ8MGtKLmlopASNTITC38sCs/fsatVKWCSFJw1TaJa1dd2R2xkWduowMhm2djAjb0hAum5pMBrz61ST4L5gxh0DjHYgAgBwWcJyASDkWp4gQ7NLdveXGax94eN7rs9Izwpt37PrVNVcvWfT535giCYRzMs2CngXDpuX1Gyly8xsYCQhLyVBKCeKkcYSgcaQbcISGv14kxobVU6cfnWa5gPmX3t62x+AwFbJaCmtAAAABAElEQVR///6GhgbTNNu3b+8ZxdXV1Vrr/Px8Lwb3Pw7nCGya/yZEBCL2XgClUj179Tvj9NN2bd6ydfcuMiAtU2hz8+aSQceM6tGpGCQnHD+5b/eOXy1eHIknDMNytPvpxx/GG1rbJ1X8/c+mJmEJ7ExE4o6rvdOFdl12xhcXHJebHUgknFDWXvI/sW3rW9UHogaIfD5lGEposKtSPq3PKyi+qlOPgYZQTiSlUjruiJgmRYpJE7GhIT2P4H8HzV8p20IElgZbAZBPkG1YiVDWI+/NO/HE046ZOMYG2ufm/OyE004+5/Q+PXsWdyguKCjIzc1NT08Ph8OhrBxtBYU/DT4Rb26JiChIBgAAAQBJREFU1x6o3fh15fqtfr8Mde6QMiW7OqwEsXaO9C+CEbFkuEIoy9/cEGkXEv0KPT/efx/JXxormZmZeXl5ACorK2tra6WU+fn54XDYA9ZPtT39N/FiTt6fnZHSZ0ej991x272PPQ4gYIQdx/Vn+e+9/7HrLjvfu37Dqq9/ecll67bsMH0+VwhOJPoFw1d27zs9kBaR9ppUZEVl7ZZINAoWcPsU552R36FfVCm/b3EsNmvb9tWpGNJI2hBKCm0waxv2ccdOvPGCiwf4M+ILF1R/8wWHA052bkZRJyEs0uBILFpTabTUB9y49Pu15QPhsEAHaTo0HW0aR0AB5JBF5BI5ygjuigWxq6YAAAEASURBVLgvblp73YwLx1x1Sf6kUQBWPvb0yJmnobi919J1XcdxXdfZFUneN/vrGtfvsGvX7HGr9tXv2dFYstaJ2fnjzxx0xi8Thj8CF9IwjnR7kgxXKkUk3YDjpvqmR58465g839+Je3jiOI4X0ieiuro6T80UFhb+3fPUPwc0B0ugaWgNkl4+ilf69q23X7rmiusaW6Jh0x9xkoB5/mXXPvmn34ctE0C0qeaW6258/vU3bQlYFiXsroLO6NdzZlphsWPWW6pOJxjKr90sMq0URf3W3IaqF3fvrmepDIOFY2ntgpSr/Wnp9952+43XX0eWAWDf+hW7P/ho0OjxVq/u4eJCIU2l7AthAAABAElEQVQwq9ZopLo6um1H5br18TXf6fJyS1DAlAZpQLFghwRADIPYNBS50gG5DHIFTO34FJrTs1/Zui0vYJ6V0zVCHJo8vGDkoIpZHzlxHvL7e8OD+h5epfeLqvgt8zYlg1mONEJwwtoN2NFU3aady1ceWLCg9/ipRRdft98VIdcxtbe9g71T0Q8WyeRIxcQ+x2dL4cZrfzWh9yWDi9oSnYkJ9l/drf5s+Q7uWU1NTRUVFQAObVieF8e77NAP/4TtyRNXQ2hFzIAkQYIA1qwxcODQqceP27xh3e6KA6ZBPsNat3rpNyvWjxo2Ku//EffecVoV1//4+8zMvU/dvgu7Cyy9dwXEo6OG6QAAAQBJREFUBlLsBY0iFhSNiTHGlnysibEkJmLXGFvsDY1GETvYCwqIFOm9LMv2vk+7d2bO74+7IIoKbvJ9/c4LXrDwPHPvnTl35pT3eZ+iPDcSH9DQlli6dHNLcxpwKJKxmF9bswL+Gs6sr9w2IDeSm8mYRDIDaorFH9u66bFtW+ultMpRbEOWyVrP8gEjRj334gtnnnG6lcLz2UhyiooGTTo61qdPODcfwrGQmqQJR0KFRbkDBnadOLF40vhQ3+6tmlPVdWhLCLIQcOGArEcGNuwYF0IDcIzjKU2EXBNb6ulPd1ac06NnntKSM97KNY3zPnFbW1Ffu/HjT0zULRw8gMiyNYbUs3KvzvcAAAEASURBVIsrVlYlQpSRNm205xtOsJPO6dVl2Niy4uiyec9RblGn7kMp46G9NKM9+rL/+WcmEJNgYWAErKZweWt60sD8LCHJBwtrKSNYgsVPqOJubYhEIp07d1ZK1dTU1NbWCiEikUh7Yn+P0+1/ozQECPZIOhAyCEsGLRgNyGpd2q3HaaedsmPblmUr1mjW0glt27j21dfmDRw1VC1dvnPmg4cU5PfIztvSUF/Lnhd2rJbbM42La+sO7DtgQGEPq7IKjjk+MXzQX+a++VZVVcIRkCFloNh6VnvAjBkzXnx+Vv9+fTjgtJE+Wlo2vf1RPDvXjcdZg3RwhxDtOFPWsDIrK3/g0K5QiGFDAAABAElEQVTHjS84ZHQ6L6++JZls9G1CWMdK5UvSlqxiC7C01F5iHIo+tWlV7/ziI/KLPJ22IOk4bjgMIVSYwm11TR99UV9bWXrwQW029o8vNry7tkZa6ZJSAMMaaYwwbsaQZzBgUE6n0m3PPNNtUJ/m4lLh0y7DPKiOsXsbJftYAkHMLJVsaWmOh+zo0gIYH9J4FBY/h5dRax2LxfLz85VS1dXV9fX1oVAoiOLs3pD+VzaNBXRVxc77//mv1atXM3N+55Ljpkw58dhjQxLMlkgA5vZbbrzpz39LAa6T4/k+Qvb0rt0uKyot1C0RG/nG+LesX7nYT0lElU5OHDLiudmvhOKhcDTWjGt9AgAAAQBJREFUJNSM86a//socuBJQkqVjbNqmVdi547aZF597oZsbNxlv8/wvmxd+ww3b/G07mjZVlkw9YeS1VwVeOwEcMOgaCwKTMSSMEM6ujJHfVFO9an3tB19t/mxOSV15Z3abVciQUiaARSpHRpakm/+1adU1Qw7uyr7mDL5jDOmIzeQk5CaTKbriwl4XX/m7lxZ+XpGKx/McIwTDCtZCW2HDmgWcajevq2yr+OcVDcYOvvS2lO9agEHEVsAXHOQffk5onwiAsVYbUxrWj0wb3T2aYZIpirpsFBuIfUeSsOs8YubAP29oaGhoaHBdt6ioaHey8391PNH2DUsnH3Py7Dlz1q5btxaTCsgAAAEASURBVG79umVLvn75hec/+mTBuAkTCnJzmJkIh46bdPDAvp98+UVDU72jXOunNzTWVyYaSzrFclzZ1crRnUqrGhvXZxLdcoseeOCRnmMPVPHsHS1tpx4/5b1580IUYpLEIgSRNqmi4s6vvPbK5EEjl8yeWzagrxen5I7tqTmfpr78KG/nzmJPV2xbn/AaTTop0inOch0nrAVYtjf8k5AK5AmkAAOEw7HssrKS8QcXHNCv5asVVNG6AzalVJRAZD0RdkX88fI1Q7JyJ+YUJpEW33vXSCVFxFNOnqFtW9eVnTR59IFD1u2o3drSAimtcMGOMDIEh8nXZMImDkZ2D7Xh7dldcrqBOapTAAABAElEQVRFy3r5voaUFgGKxrajvH7uMhBBqqakUX7b2N5diYKntYL4JytvvjPCnq5TAKq31ra2tmqtHcfB/9Cmuf2m62a/89GAA8aF4SXb2iwAFSrfsm7Ttoqpp/0CQjAzQfcaMuKkKSeuWbBwR/lWExLGkRvbvOUtLbnZecXReDQre1lNdUM89uSs5w457kgA6zesP+XEk75avDAUiZJhS2Cyvk6PHDbsldfmHDpmLGKh7sMHurkRNiknDC9LNM1fJFuSlhBK+42fLWl44+OaNz7Y/Ma7rdt3FvTqpbKz0gQr2AhYQQIQBg4joPDOCJPTqXussGjtpsqel/46qyDfW7eOhFZupo/x5QAAAQBJREFU7rLWtk9ry3/TbWCc0xnS37MRCGTIySgRIqhEW12ipbRT8dGHjzSp1urqqmY/w66jIB3LhtNap+OmlQVEYbfMppXliz7rccgEEvAZLASTpaBvT8cAvICS7qbqurzCeL/8LAeaCJbUTxk1PynMHIlE4vG47/uJRIKZO6Q0PwRJvuOWm1IytnTN0nOnnlYUx7byHQ0NjQA6dSo5+5zpwcsNiARRcX7hiSMO2PDmW1ubWjwZkojXZvRn1TsrYNc01nceOGzmi7MOOuRgAOvWrTrhxJPWrF4dibjExmNjpYWnJx9z5JxXXuvVq6exjJB0w9Et73229O+PNC1dXZtpyhk6TNbUzDgAAAEASURBVB44Qo/oi24lkZISp7TY6VoY6lkYG9Ivb0BfjkQdkONDsBVgxayIBRFLQEAZUddQtaO+YdC007uNO5yrKxo/+hguXBu/v3zt8PxOk2P5aU5AfLui1P7LhKzvWPYkYqDUkrVby7cMP+rIcX27DejRKUNeVVNdKpOy1s+PqVMOHZD0aivbUpTIaljyUfP6z5zCssLefdNaMBELIzjIM3VQaViYJLtVlQ2jehXkhwQz2aDCu6PCzNbacDgcDoe11h2q5f7+zgwAnboU7Fyw8ql/Pn/5JWdf/dd7fnPFHz6fv2xL+Y6JEydFleQgKwdyLUOkqzesvLRTt1FpfUtjdTNSYSXtlPWXAAABAElEQVSTwp29rYKiOfOfv2XIkMEAtm7dduqpp29cv9FRyngGgBTCZvypp5/+5JNPxqJRMFiQZKEb66q3lI88/5eFI/ojS+4RsmPWfkBQjF30j+01zJIIcg/3tr0429PpnQtX53YtzuvTfeNrL2++9Z5OiiKIf5JoqvRbry3o7onmlHCVZgGz5xwwkSWArQAyQoQjIW/BoobPF+QfNXlsp5wxnYatHNb4zvqdH32zfWLfzmcOKdi0Me6nq4rUuqYNi8PAjrnPFI4cw1k9YBJEnrIOWO4nL9X3lkILapXcVafOOXQoCVGVMJ3ckAPsC7P642MGTo0QAKSU8Xi8QzvN994BAgA/KUgvowAAAQBJREFU2fCf1959953XnUj+uMPGhKM5/fr3HzNmdFFRgcW3kBQFDV8tueP+5M7GQy+9+NBfHL9mxerKxkallDW479EnTzthMoDampqTpkz5Zvk3SilmgMgARusZM2Y8++yzIddtvy6DLJNju4weGe5egpCE9UBEuwgFSEiScs/j/Lv7A+1+mKD2JWW9krLu+d1Ktq1ZtfTNt8K1LVHP2nD4P1s2DuhUOCY3l/1MhoWy2NVz8tuptbu6WJIQTOCM12JM6ZHjfEHKmM6x6KhunSf1KxuVl//SVys3NdT+7tDhZ/Xt+vRjD6Zs0mtpygrnZQ8d1sJu2HeZ2O5RPvEzRTjGyfITpwrJAfoAAAEASURBVBzSo392xEu2kYRw1M8l1vupC3TgO+14kd3peYCBKb+YcfjoIYC+/trfn3zKL1euWRN82Dd2z2iVL2xDU1PxoYe/FrLLR/Q++aLfvTL7jZ5dS/1M+qobb7xsxlQA6XTq3HOmL/n6a6UUtXN6sDXmvPPOe/LJJ4UQuz0+YghI6YZ84lawB5B2yHRwdgTDkSoVlel0JhrOnnLzzUP/eF2Nyl6aSDZlkscWdk0Z7bNwLRP9lMtprSUhQrFY82ef1c3/hOFoKHg6bHW3SCg7HDqiV8m90448dXBJaRyun4YlpZzyT14VFd+E3ajP2Ybk95Vyf4WFpbAON2letr3KAYqyIq7PKtptAAABAElEQVTLvtAdm5MflI4ozW5YGqCx61UNZec/8K8H8rJcwMx57cXxEybd/Lebq2vrHClke+dya9mSlVkF7rCLzz/y97+bccGMryrK+xwwpEuXkqOPPvr2m/4UzNPll//+3XnvBRoTXNEYc9ZZZz3++OPfS7n5BG21bUg4da25PgnLrOR/0ciIwtZ1IR03VFTWTYejxUdPKJhy4str15QV5JaCSLMW0mG2P7miRGSthRCFCW/b8687voEU1nFZEAtr42p4t5LObAFuUGm4cAxYUGtj5fpZjxc21+kIC4YyP3GFnxImJBVao+5767dU+Roi5FilOmoF/6B00HsS3K40SxZ8sWL14SJqNwAAAQBJREFU2rRvOhUVdi7uPnhArzdee8MzxvNSH73/4Suz56TSme7dy3Kys9uPRk9IoSHc3sWd7rzhLxVN/rSTjist7Xzhby7Kys4m4B8PPvy3v968Z49GrfVJJ5303HPPBf7enok0IjTVVL95/c0rXn2r5wGjogU5GQr6hHZ0jiwpQULAwjJBEjUq+ercN385/tDwzgqGJEgirSWLvVra7qlHwQ2EpWjZWhfvNSjeryxJEBBWkAQEmImJZCqVePCRR9KJNAlYyW2VFZzMlA4fYLUQJDuUjiIm+A5IyfqGtqqalv5lnXJDjvyf8uR1ZKz2qBPJ5QsWHnHY+KOPOWbMqNGnTDtzzZvTIh0AAAEASURBVLrNJ5xy9uz/PJufI4zvSyd3y8ZN111z1YGjxxx34onPvzirurqaBSBjgHnzxact8OarLy3bVHH08VNKu3QB8OEnn135+yuEEHKXsa+1Hjt27NNPPx0KhX7wTuLWmrqavkP7uoWRDGy7odIhCaYbQct2Yrc1nWluueOFp8+6/PcDuvdK+SkW5FpKOXa/kHZAY8yPm7aqZ2Zpv43gK2sFw7EASJMCEDKuzhgDSJBjY1I6Wz9+pv71Z6IxNx3QQf98IbZhncxOK1eVvV2uf/vK/NfXbe/QSD8qHTqeOGh1gIamulYLF0gm21576cXxEyZ/tWz15BOmzZv7ztgRg43f5BJN7Ey3AAABAElEQVSEUrXVle+8+eb0M88ePHTooUdNOmXGeVOOn3zB769NOq5uaVn8zUoAANXX1//uot/4XkZKGUQktdbdu3d//vnnc3NzA+j/3jcTzi84/q83j7nmylBeHhE5RB21H3c9nWCGJRBc9cm771du3nDOaae0Vdb7jgK0Y4y0Lu1FO0fcnqYQe/yFrIsIZZZ+WvPpexE48AjMmgDAAdhydlb2uIMOAgAmq4yRaQBrZj/TtGRuKCwzQmkpBPmKM4IRYIH2KQSSFsSWYJ1wbHtC/fXNlQ8v2pSBhWZY2HZuBr+9tfjPlw7bNAzogw4fd8Cgvh7gKBdAbeWW1Zs2ADjwoInvv//ea5J3FgAAAQBJREFUpeeeGY2FrdYgOI6Ujqyvq/3y4w9fe+bp19/+OEUkWMGyTqaDYa+55pq1a9copQKrRWsdDocff/zxXr16GWN+FOERCecOHgTHBYQL+m92YQIctgSWAJgRct789MODundb9vCDqcWr8zmUdvzGqA755JjvB4SDrwcu/O6/OL4jhQORXvfiHJ1KwSEDMJkA7GWtVbHwi/955aSTf5HRvtWZHl26PPbWR8UDhq966m61c0NECRbKkKAAhrrf6W9LypcGIhU2mQhFkpHiV78ub25JaphHPlq2pq6RYKwhdJRxsSNfIw5qKUw0Xvjks09lxaKC5FVX3/j8y6+dfMIxPmtYL5gHiMQAAAEASURBVJbd+cKLL/v0i48vvPCX2Vkx3zfGNxCAUlK5rhRgWJ3MKep65BFjAbzyyiuPP/74brixMcZaO3PmzEmTJllrd6dC/t+LCIgYpZBLly1Zu2nDtKnTUh9/EU0nPMe6BtmeaQv7GfXTpnC7GDJSQ+dkpb5aVf3hp0EpgYIhGMCy4LQ24azsa2beceD4Y9lwlogmSwfljRilG6tWP/9QXqbesUiLWFqGrTASqZ/5LIHqshLW+jlIxcut+cfGbVXJjIM0pOhwLXeHDGFrSKrZL/37wl9f0G/AoMMPO/zTz7686aaZxx8zzlWSkRaQxEpb26dvtxNPmnrEyBGDhg3PeJnW5qYGfNh5AAABAElEQVRUIsXWGGYLlJX1fOjhBw4dPaK2rm7atGmNjY1KKQBEZIw5/fTT77zzzuBICkyc/74I+ceEmdPpdCqZMumME2qnlb/++j8dc/wJxxx//LZnnm6VfkvUjWvKSXHaYQap/ejNRoIghCGKtZlkW6Lg+HGCHMDxfQgLSUKCNXN+PDbhuGNeenF2Y23j0lWrNi5+0014ieod0YKi3D7Dm42CgEJGwnJHiTWVcIu7x15fubJzYdEvh/d2fWIpSHQwFNShLDcbWDP+sEM/XbAYwIjhw6oq64x1Z816evKR4wBoXxOkdOC31G1ctEqQ6j/pMMDu3Lpt9foNm7Zu9TKZ0tLSI8ZPj3OgtwAAAQBJREFUKijMBfCHP/zfPffc7bpucDB5ntetW7eFCxeWlJQYY+R/FQHfj6dhZmZtdHNjU1tdfXV1da+B/Wtqa6+88v/mvPl28+x3l7z94vBrL0nOW1h555MyV4owHLufMX5OKxnxhatNvTa9Hvl718OO8SwyKc8kUq4ro7nx4HNvbai4+u+PrHnqfkYTRRBKhz1OR3oOHf2H+xqjxcZ6LqcEM/N+Zar3FhJozewYURS555RJJUrAJ+NqIogOaWGHNJfk5++/+dmCxQCUI5ct/0ZJVxt9wgnHnTfjVzfeeH1Jl0IACfbdaHayLVOzbWO/Iw5hSaU9epb26Dn5u4MtWvjVQw89GABvrDkAAAEASURBVJgyQY6DCHfffXdJScn/pFp9nxK8NkqpgsLCoqKiotISNxK5+qqrpp06NeQ4m1KJw2+5NVzadfOqppIbr5PUVPuvZ1wvnVH7s+8xMcCShXY5Ufvq3K6HHSPIZEWFCcWShj/duGNzS5ozzgd1KXnwqNx5JcmdTRqwUjhGJCo2t25emTsst1X7lhwLKdGx6A21+Jnh+dFbTzi8xJXQKTiwFO4wHXBHlkT7/t//fhuASCSkfQOAiKNhR/veI4/eP/bgw/72tzu276iIkeOo0IFTjj70xOO1sSlGxhprfWZrrPV9bbQF44brr0+n04KCHlVsjDnzjOmnnXaaNsYy865Y4l755kV1AAABAElEQVS/du2Q/L1c2Pd/3mdhfTuwCMSW2dp4bu7qtasrKirOmD7dpr3BJx8b6Vpmmr0ehxxccNDAus1bTDsvwb6FiV0DS9aQQdRpXrisdfVqScbYtBFQIWdj2t71/roHPqtcUGVlbnZuWVEG0qTh6aTPVvjJ2q1rrBDSKtewADMEU2CLCAIEW9rX0wGwzFHCxQeP6hkLt1n2FfsiA0u0HyfsD0pHlMbLZAaNPOi9uXMXLvjqrzffPHDAAF/7yXSG2Q+HnO3l666//uoDDzzoVxf+ev6Xn4GQ3avEcZUyljjY1i1bI5WUSrzx5htz358nhGPZMLQ2prCw8MYb/gJASCmkBNEe3KwPHgAAAQBJREFUimIZPkMzwME73P7bgO0ubbH71JLvSZCNIyKp2vs4/vP+B6addXYkGoWrsnJyNFg65vN77lh85ozUv19LK/LCLvbjWCeWAlohZSS0jLS2Nu6cPYfgWhknUpTIeH7Gj+cgnhuRlJUMWykBWzzwsG6jJuQPHqGYE1Ub29ww2UjYZgQylpSBo8k1JIO+L2I/iMc9YwcXyCE9c9kgao2FZUQdvd9NV/aSjihNOBK98957Jh111NBhQ6+/4YaFCxa9OOuFY46a5CiRzmQAKCXraioef/SxI46YcOSRRz33/KzGxuaQkq50SDieb5ghiDKZzK233gpASA3SzATGH/5wRb8BPUZhRicAAAEASURBVC1nCD7tQiIRNMETYAEWsAJWgIUFwUC0gpIgHwgA7vI7nuTuquf9EynFkiVLduzYce655yIAOGqjfItYtOzYSYm0CpX27ffQTGdEf5tK73O0gL6JicjCsZSlnNpFS2xDiwIcwy2JhJ9qKQmnNdczfMGKbRiUO/TUSwb8/v7Bv75BOoVOc112ohyqrcWJeMgiSkNkrPC1NJ5CWpLejxAggbRNe6zBEJ4KwXUsswoagHREOuRyC7LGBl6xMSYrJ2vamWe8M/e9Tz/9+JLf/qZLpyKtDQBXCqvx/vvvnTP97ANGjv7z9Td9883KJ59+7tTTz9TGApjz2mtffvmlVApMBNd0Ifx2AAABAElEQVRoHtB/6GWXXR60CSOAAmYERlBRBSvBLuACSVACZJkl2zjbGBACGVAG5IGCzcYAPigFSmBfzuqe3sB99903depU13V3AfHZI+sRlQ4YHj/9hP4P31o0cFTbzgYh94fpiC0BIAHhMEcJ6W1baxcvJoAN5xfmnXvIgTefdHBxKOX5PpMLuGBwONaUVejFctgJpVo9zuiUaz1ps43JVjomtLIZZQOff79KFxxJ6xvNlxWNpKChP9/hb+UQuBW8b73/QemQ0hCRFEJK+jbezwCPOfjw+x98eNHXX9058+8HjRzuGWutAeAoZ+u2Dbf87eaxBx32y/PO6dVvYCQcssa/796AWnfMSQAAAQBJREFUht4FR8EhANdee3UsFjcGQJjZCQZmBlvJ7IIyCfNJdeJZyw0wMVgBSjH5gLDWMjIgC7iwAoaslmwdtiFrXeZ92HzWWq01gK+//rqysvLss88GIIQQQlgFliQZkU65k/52Yzgn8uUlfzabKkRoPxwZYqagT5ggJoet8pLlS75kMDsk2UTZjiuM/WrckJBJawtBAJq3ffpi3dwnt7z2hOfVtxq0ZeIqlJflpMMNW6rmz69atCjGCBkOGbiG1H5sFoJgbOylTzet8+2cquqrXnxv/roasNPh4F7HqUZ+YKw9IH3WZN55e+4TTzzxzrvvpNIeAFc5nvazOvVcunxx7+L89xJWV9YAAAEASURBVOe+c/Qxx0FKEjFoMtwyaszgzz/90HGj4Gh7BIF8wAMkOAwgTZ9tTl5vqKbMnZmDKZaMELVAEVhq3tGov2RVHxYFYe7nUgE4CmQBTnDo/wRAdpfLRkKIc845Z+LEieeffz63Ay6hocly0MJ3w+znav75hKlqjEQche/Xgf/AyGQsAZBgQWABncmk04OHT3j6X8bNlr6BMCCVFuLy2UuWVPmVL91e9fGrBoAIwZISafTodcRv/1XfnKjcuSTSqp28zjm9+7i5Rb4lAILZCNh9bTYMuFazTkdynXy0njWq76Q+ZWFSrkTH8lsddLv2nq+g7jloJMdWC+kcf+JJx5940oqt+MuuAAABAElEQVRvljzz7JNPPPZ0Q1MrgCmnntq7OB/A/fffbwElBAfEK8yX/P6kUKjJclKIzkAI3GZRy9xC5AoqAUJ1/udpWaectlr7ipXWWiekKYzuJKXPazPyvUp/iRTRqA1HRb7DPcI0OCaHubIXOL5HWOUHwKqBxixatKiyquqss85KJxLhaCz4iIKCgGlOfn3X/bWvzSrmtnDE8QG7v23YODBrAMtkHVdgzZaqtz7ufMpJDMFsfWnDwLRRvVa8tSJFwgJxgmYrEU/aNNdvXvTQn3r1P7z44BGR0v7SkZ42bRoshYAVzIR9+08ESis3bXSnjL72hENHF8ehjaf4Z1XlfWfAPXca+R7fngAAAQBJREFUa3xBAkJiD8/1x7YwZt4zRrvXUjAs7f7yzq3rzz572sdfli9avnD00N7Ll38z9qCDMp4vlQRY+7FhI4tf/3JyrjsijCEpvaJNbG5DtTZJEpYZjohHZLRBr9HULIRhFrAh5pCwwiFFwvVt0so2CGOZmFrIOsLGBAvXlhQ6EwvlZMl9QBIMUIoBQjhISoICYjyWki745XkTjpg8/dzpGz/5pHb+4kiXgqL+ffJKiiHkN/94uOGNuWVhpaW/m1xon5PrS7bErpbKEpi0ZCsQatOpzl2GvfCgLO7mWGZrfE21Yfnm+h23XXrp1nmv0a7JjMdjvQf1i4lQtRcZ/Nu/bEWBsJC1zxsAAAEASURBVAkK6NOIAvdtb1uWCb5ghz1l4SNkyHHYJkxjt2x79/EHD8mLss14JBWk7GiE/Ts7DVvtW+uEoruBkD8h3wvq7/Xh3RrjG1/UVteU76w67rTTRw/tDeDZZ59JZ9JKOWAiZqDpuGmDRWjhtswqacs0LfMppUWYHNql1raJIaSioDk3WZZJUMIAGgxmUgIsKcA/UYxFFMIS1flcW5Fs9sOVji0K0chcNRY2AraQPkiABOADJKX71VdfV5RXnjl1mmbbY9yYkp7dFjzw8Kb7HsrNLci4wqmtKY65sAZwPCFkwF6zz8m1FBxMWpAlqwVpxelc0lUVK554Ycwfr84IEMkW3y5RxTUUAAABAElEQVRevmH7umVNm1eFnNDowUOGjxt76MFjRgw/qE/v3iu+WXzgQYdHly6KjT7R82SQufwJh58RVM4RwEwEEkanikXqysljhuRF4Rl2iQBp6OdUcn73ub57Pbrv7ruff+HfUspIJBw0sbXfHZiZw+Gw53mRSGRPnvcgnuu6boC4E0K4jtvYXFtdU5Np81evXtFv4JDH/3UbgPq6ppdffhkAyIcN+4aKu0aPPT2vFdt8tz6NSkFpQkTZKLMBQwjBCCru7K5pIYJsPxG/napASMAyLFgwKUstIrK91rRplg6/XqSP6izPVVwCKyBM0AeXyAD45/33nTH9XBlxUsaTMhwqKIjuEM5TBgAAAQBJREFUbMhLplS63ApXOpKl1YINCbHf27pjiJgyDntCW2LFcLQ1gvJVKPHvD5InTYkO6Q+CSTVXrFx691W/P+X4Y/94yRVDBw+E+vbs69+v/5AhAyvWLBk+5ihvf4JDIGEk4BhYS0LajEg3zDhq2FHFhcgALARLhwzYh5Ad05rvKI1S6qKLfxfPzr39ttu2lFd0YLgfEQHYIyZOKc7NAvDRhx9t375dKgBB6M47akqvXj1RrfOMUhbEXCqQIPIJRAJBs8Wfk63URGkgwhwDkSEDlbAgn1qr/P8k/NXd3GkRPpJNCASwSxILF31YXbvtzDNP99lEDEHSus/m1y9b1lcZ4xrLwsKW/zgAAAEASURBVJfWEyASynBYwwjen1IBS0HsCY4lxQj51rUiI0goSYm2b96YE+Wj58/9NKX1x1/O71FY8OQjj4SFBNhCw5KBdASsybQ21WVQZTMJQe4+HRdiSBCxNIIITKmmw/oUHj+wC5CBcFgITRAgKe1+ls/tLd8zhEU8J/+i31167oxzn3zyqbvuuCNQHcdxAmBUwMm2ewnbW0btkj0rxZnZCrANucbV8AyLQ8ZNAADol159GoAghxnG+DKEo6ZmedgIkQOGCE5rQrB7tBML7fpx/4QAA/KYFXMOQKAkIcWcy9Jvs4u2eA1FsqpQTiTuEjj29933wPTpZ4RcJ82GWxP1X329818fF/9oAAABAElEQVTPFQKtMZmScC0MkYVUlgSzZLtPhyUQLdmCJZOySlgwyLgqHVGbvdbyhvpv/nV/f69u4lG/GDZi5Otvzf7zVVeHRfsqCkgLC2aAGhsayssrC7uOFVLx/qWeBFsQg4h0ptg15x8+qCjodOTAIKgmIkOiw3ng7yiNZsGWiTgaz/ndpZdPmTLlwQcffOiRR5uamrTWQbzrJzTddV3P+x61qc4gASC7pOzAQ0YBmS3lH7z/0TwiwMYA33Ji2MjCYWMjbVwH+ARHsBZUzQSL3fjO3ZQxor1uZQ8q4x+wpmwMwgdSIMlwAQso4jxQK5MGZafRst0+kuKlpfJsJccuWrCkoaFlKMRWcwAAAQBJREFU6tTp2jAJmUw1fH77rf2314aznFoOSQ3FLFkSSwJb0mnHtJP470sCkm3HCEkyEw5VSmxqqqurbGwTpiQaOc6Pj+4xuOfBYz+Y/2ldfc2006bCBqGT9nIYIcCMO+681zLi+UWkQqy9/cmSgjSTZcB6qWMPHTA8LwY/ZZ2whZXQZAlQWjgdbmP53Z2GQEQCZIwF265lPf4+8/YZ511wx+23Pfvs02zMDX+6YdToUa3JVFsqYTMZQZaksJaldJ978smP539+6RVX9erRFewZw2mTrKuuWr9kxTsfzx8xYmT34mQCz778+oeNNSnHUSQ8WAuDo6f0jofyGm2CKUOkLUvAZaAYFv0AAAEASURBVA4BACUZBpAEYZkEQNTMHCWOgdJgFyxJJINbt+12nSAErqggFoIJEExMSAMEdhkGqg1I1qW/9Py2nhHv/n88dd70C0NulvGTIR0Ode0xcNoJVXc/VuY7OaR9QYaUYBJBDl7CknRYBNcDU5AEswQblNiz1cIaGAm4pJWUKcfZ4Hvf1FY1JVJFjjswO7+wMLtYhYor0jtmvdHluGNum3nrMccfryIhqy0R2eCOg4I+YN7771Ik2mnoqDYraZfT9BNCgLBWS8Veun9e+PgRZWA/AHYLiHY7eg9G/v9WadTucfaAdfUf0P+xJ5749QXn3XHnXbOef44gr/zjNbHI9+Ohzz+9+xO9AAABAElEQVR2b0F+z5v/PjMv8l31ZRxx2OE9+rTW4oYkvvzgw50ASXI1J7VBNB4//PhwAtuNMGDXagGhmIRgC6QZFjYkKMLIgFsFJEwe4JI0EClGmhAhDnZZQe2kzBaBMcRhwQz4gGVYJsBGwJEgbMHCQEmjlr/08Z9rGrzTT7sLBoIIAgZm8Izp/raG6pffygrphGsjvnCsbQlDWsrJUFKppDSOIMdAMgwhrcAS0teaSFh2WLhSWVfu8HhLIr21tkJo6hGNHVWU3dsN+Uw7Wczfvi3XDflN9k/jjqjPzb7u2j8iAFIwAiMuwKPdffet23fszO11QHafoRWGQrzvRBozk3SYVSjTHy9ZsAAAAQBJREFUMnVCv54KzALKEUCAaA0CTP8Nnm0f+5O1rD0DywcdOu4/s+f87da/vfjyc926lNx0001NbUnsSpRu37D8s0Urxo47OC8iLBhIAez7gIalZfl9Gvof1uxhSWVl1oqvWwHWRsNEYdXA4dl9BoUyYHCY4DqCHE4oWy1tk7IqbDpH/L6hdG/X7xwyWZ3p6L7qwTxxKKgJHLUkjMj4gjVJQ4qhQJYoI1iAHQYb4Wvh+SKlIVl3ZiKWVSy3s2gBXEFhhbxnnlxz1BmODr0GUU8UgWADbcLR4Tddlnfx2XV+vtsUk5YFKNzmi7Z0StmM0BKWyU87fltIp1xjBMiYMOkQbNiNZOX0oA0AAAEASURBVNzYkpR9Zmf17IrKukbv4LweJ/UZOKp/byopXheLpONZ71dX319fc0tL7XXLv+40cui7b71dUFCwm8NXCKGUqqmpPvPss//v//6YTocLhhzuwVHW358VZRJp6ZpM6+ie2RMHlQJmP2LXP0/2EREmQAhhLWAtSUydNu2UU09+6rFH/3rLrU89O+vq6/98wTlnh5RY9MVCbXHE0RN33bgEEcnGGvNqc+bpK2aGZFYXD5kVC9JV5RlXuiyMNRIwE47LisqqNki2noCRJuza0qjT2UWfCPqHZR8puzB8w6ua9bJC/oVC31b5LGwT21ySwpAHNDOFCWEiQQxAMgLOMwaHLEeNQOnyAAABAElEQVSBMIEhmxiGOAwOgSTBxmX6849lU5M+4Yz4Ju+ZPFFToM4gdFc6YgX7rjvk978rGTJ21YOPpdYtsdJiYLeinAJetFYxjJ82Ok25ES2tqymqKYxIRooq9hY3Vq1LJUKQg92cwSXhvJD1dNvOMKq69G8t7K9rMy/PffzDhqrb3nqzV8+yuvIdR42fCMD3fd61uwB4++23r/y/P6xZuy6U27XfcdM7j5/SaoS0mZ9wIXeTd0IKT/udZPr0sUMKABifsF9lDPsv+1IaQSTaA0YAAKuU+6uLLjlt2pn3/OOha6+86qF773ng3plffPqpG4ofevB4C7AmwM2or7bzIwksVgL5Tu5hbgAAAQBJREFUJXlJbAfEgg+b2YKFA2bLaeHw2CNKDJJhvyAiumSJvmH0iaiBLnUDuYAEy3ZLV1hyHGW7WlSTyRTSsUkrEvwVkSURBVmBDEgwHEbYwifWgnyGlhBKRMJwCGwRzRhHQDKnLbVKwguPbj717J5u2G+2tTv54Va9o0RdFecykSIOqzZp8448ZMyY/nVPvbh89tsjbr6attRse21pqku2P3FY/379Wz/43G7foSKhWtdubkuurGluEn4n1z0mr9PAcNSFbbUikxEEXZI2uZu3Llrw9eub15uBvR569o3JkyYLAL367V7voBRw5cqVd9111/PPz/J9TynZqVtJcf/BbRTNMDF39qkAAAEASURBVBTpn4gQBdXKQghrrZNsPPqA0oNKssFewNfxvwVX7zNhGWAM5LdscLDMlkgB2F5e/ve/3Pji8883p7wBgw5YseprBbBNJPDmNv1Cm1oXpWiUOjXbBiMS0sv61fhvli5IOiIGwLeJHn2L5n1xZZfC3g66K3QhW7KHM6RBAAVpQdJUp7k2TL0spw22O1Sq0dZk32tObxNSs9rZbNfCTRlI5ijIMPsCJoeiErFkUs7/dIefyO09ONK9DxtuynJCLiLvzWt7/J+bH/7PUO00aJKMKqsL8u0JvZyzyBsMiZTyLYihwoDeXlm1cs3y2XNKupR0Pe7g4jEHC9A7M36TWrysId1piBqbAAABAElEQVS6PNFoo5EB2QWDnXiJclI6kZG+ZC0MxUwoJnJWsb5v08p1Yb7w8ssuvvyKnFiO1lAIHGPJYBBWr1r55xtufO+999vaWqWUkK60ac/CHXjkyEtvy0jXsUn74yYNB40/hEwnE4eWyhtPGV1CGoCVIQsrYamjeca9ZZ9KYwEfUOBdXj3BAMZYxb5QLkCrli++8+47X3nljaOO+/VNN/16wKD55XiggZOCwiKZu3Z1ssdQLxoKV23odvoB77S2pYWMwoZ9bvjlr6Y//ug/YPKCYQGGMGALAsMBG1CGIcBhtNOHZ8iGAXCAryEGGpkb2+z8Cv+NpLvRCFhECL6AyUfPt+ZUcrfvnwAAAQBJREFUvfjQiprtVlGpb/TW8i3de+Z7uvmoKaUXXDDylj8vmXRC4S/OUY3catCZ2TiizfH8HDmlSF7iolTAA7Ox0hdKEto2b4/lxN2CfADW4Is57z5641VOTdXQ7NzesU5FETfOLcYYw0DQ4YmkClEzaPbGbW821o0644wbbrp+YK9+YEDDCgOZZhAQCRyZc8+/4NmnniCC4yiw9CHDJulZuAMPH3X5vQkZEUix+YHF2hV7ECBo7Zd1yrnh+L4HRgTpDAulhWRoB4bwAyWqHZN9ap8AdvF67P4nhhAC5DCDGIOHj3ry6ed/86s5V11904GjHj39/H5nX9K510DuDH76hZo77k0Zu9wAAAEASURBVC3/9xejc0O1nyzZ0tTmC6WYJZMF44jxk4E8poCRLxAJEu0tCkgAYQoAWO1Xd3cb7gxrrQ/jStkpLLtJoyy7FmEfSVfLXJX16APLn7w186uz/9Tn9C4nTjlSSjX33Y/TKbNq1Tevvvrq0/e8PmpC95PP6dmEVb4pIqkFLEP7TqjWviURKcWv2BYztZGAa6JQnNerDMCmrZs++eDzrRU7czOJg93QkB5d40qEUmjz0x48ZpZQDEEqbN3Iy7VV/966sfSAkf+8+amjjzwKaAejsgKRYISYg7gMHnj08Zde/SAqHSX9FmjSQiobZpmCyRk4MERh31dNIRk1+vtbDbFgSyxLwUidAAABAElEQVQ8OBbCoXRMZF6cv+WNdPOJI7qN6lqkGMD/kGYE2D9oxF73uRu2S4YJZB0IOfbww1/+5IxZr750581rn3t09aHjO/frE3rp2W0HnzgoP8sSnOVftQKCTC6cBmNMNBIbPnQEACID0oD8ttNs+0X3flAL4oCnl8gQfBaKqTlhtmdMpXQqoTuTqC5QWa+/nnr8zpa5cz4YeWDQYVADatoZpwIATr/5r9fff99D9zx80+qV6d5DIj7Vg9JAgeUoA2RDdfYll6KF4mLYLJKAQmtry5dfzF+wcIHWduiwkZdOvdh+9dWK/8wtjMhG8hJhP+aT9Lk16pKMxExsXWvLU6u+KM/LrnZSdgAAAQBJREFU/8PMmRdcdJEbDjEQpM60DIp2iYwSBEg8fNedl1x5FUR2brbrNVkq7BWLu2bHhgSbWO9hfSacmDIwNiOh9w5mGgiCdeBLSE2ucaLrqlsWlzfmpXccMaIEwUXbU7P/M+nIOcfg9i4CAfRL6CRv3pL+e0au+sUZWROOOfqhv2x49J7V8993AHPYFCHR0OrlrV5eAWg4TbAKhsvKuvfpMxAA4AAtgALcXffzg+/FnmrEDCEowiQte1liYqnbstO/u1AMCYnjDNbPemDO4O4njTxwqOU2QDKLwCaTUmqtI5Hw1df+fmdF9Znj73x63oTBB+pWVDIisGGWO6XIZRuvw4uFIu7ahHYAAAEASURBVEY4f9U32z7+9MPt5eVlZaVTppwyfPgIAH5DzaL5XxefdkrNG+9mNWivwEr2SIRDKntTRr+yavFaYY759a+fuOrKktKuFpwy2pVKErCrbhe2/VnXbd5yy9135woaUJK3rLHCD3UbetEf8xLlX9xzox+KDptyjs3tnkwIR6RDNkP4fnjMkjAE1/gSIFJpKOnkcqb+4AMHDu5aAjaBftq9Gf3/C+mYcSSCFsMwkgTStGiLvr8t/KkgtwYiltv417v7jx/f49IL3k+Ch45WIRzqtPXbsfmGAPkNVgAPHzkwGpNpszCpt2W74xRlM/aVBNxlV+1GHhFBUBbZ7FxxYtpbV+y23B8mAAABAElEQVSeJTDq11dMaC7v8vBzN1puEyTAERDvxqgFSTQi6lHWLdFgHpq59f4XxijZYriOZISAsGwNy5LqusZ/ffJS1apVZDuPGjNm+jln5+Rk7b4PX8rRv/+tyYqIg4Ym5sytW7ewyBdRN2dRbcODG1aPmHH2Y3+4avTgIWgngCIldjXTIZbGMhOUWLlm/TXXXr3w6wX1O6uPLOubL8SCpM4fPSR75JBVt7/kg/OHHxQbPLYxrSxZSUbZH616YgACAtYFuKXlyM7Zlxw2rMBasAa5HoH/f1caCxBk8IcRW3aaFxLqE6IctoUESnFTNa87ZkrXqQs7v/Vaw4iup3bB+Wu219bvkPSsmwAAAQBJREFUTIMiYCYyAPcZJBL4y/bkx3nuCZLiDIe/tW1+hMqDdxXX76pTYQjAh9AEjupfCBzyyD8emPefHZ989lmPnl19NAJ5IogX7Bpvt/t5ytSJf7u9oKmhmU2zlC0h4YeQr/3+S5dt+3pReX2l7dY9Mfm0nocP+i1QgN1HAwOEpHS8tmSW1n0OG9tAXPeXRdkiZ3nG3LZ2xW8fuveaiy4HEESKFJFsvyiDGGxhmFznqxUrTvvFqds3bsgG+mWFy6LRxVu3hYBTDxvmN+/4/OvPIWPdxh2bCuWKFhbK18TSOntPi2MtEQy5Go5gq5prx3bLuf6E4UVhAe0DAaKTBbjDldt7S0mjHd8AAAEASURBVMd2mhTYhZVQO6v9+xvwEclCaSWzNgRDHsPu4OZp5/U6/egLeoZmEDqtX/thIp2GK1lkTCYb4E49d7SipST+i1ycC47zLv9p1x8/9G4IZmggYTjF8JmtoJikfLAlRLOjB1kf9z9y70MP/KNHz7K0l5SyANJAZGB/wHHo3mPg5MmnbGr4T27IZJC7Y2fWgvfslg1LLZlRE+PDz6NOsS4ZXdliX8rCdAaMVVKEhBAAZXY2RONu3bwPV//jn10l9VDyc7/pL8u+Of9P111z0eVgwLCQPiBpd6ipPcUh4Io3582bMWNGQ1XVYfn5Z/bqOyTifNDU9kw6HQKNb2tc8dar0K0nyQe3AAABAElEQVQ5ZSPyeg+v9ZHHxrephFKG3fZKnT3Xjw0xPOF6CKl029juudecOKIozMwJIxUQEgQXPsEA/4M+ue0X/c5PQdocBpBBHMlYD2yU/M71JCK+sZDJFjOvCZ9kOVaiJ0SS0WqQMbA+27Rp7dl7dM9+vwHng3jTtm8ALQQEpEFKumZI3ykFGOfQSJhwe8POb9+inzqmGA6BiIwgAlxiCURAYSHpuVmzPN+OnzQBsI6MYPfL9SPv2AknHPnb/3ts7uu0flNzRUVzr/445YL8Hj0dC5tGul7XgVLb7Qul6JSvpmQyqRavIRaLh1VOl349dG35V8892qOuKhzNfqWt7tZtWy670DdvDQAAAQBJREFU9a83X3t9O0G9IgFhAQsrINgyEwuhADz9+PO/uuJC3ZY8LB69tnf//tLVaWyorvWFX8DU9Mni9VtXA8gbPlbEOptMJq2sZBv2hbDSSP09iIgWLBjCkiRW1jt2zJDSsAI8BAZ8u7ruV05+/2XvnSbY/AUxMaG+puKyC39T3pTq2ad/TsQNOyqWlT90+IGnnjKZaU1GLvczcsMavWF1TUNLKK/I71Ysu3bLL+2aNqprLqYwionSAK1YsQkAjASDOVNa2ml43zMdlAH8Q/nWH9tIieAQnO98YZex4HneHXfOvOqq62LxbAC7WAPkT5zmUnphjv3jlgV/vO2w6b+OhePlCQdnlD4AAAEASURBVKSa4bdHwKUHqASadphHQxyJRY5zQk6mtmXF/NdDnKQVq7Or65Od8x5Zu+Gt1swDj71w1rlnINCS9lNWUcAaRDACioTv+X+87to7774bwOSCvIsG9utu4KXaqsLxdYkWAWMYoQF9IwUCXy7MHzg8bV0H7AkTMXAszA8VjxpBbFkxEwwk1mwuP6Z7HuDSd5b2pyahA/KDx1P7W0+wnYt7nnDUpHMvvfbLzz7v1LnL0cccVdpncEnXEikjyWTZrKdTTzyxYdP6llSLBQABRQX5nZ0DJkSmnzPh7KODUn/X91o2bdoMgIiD1ug9ynrl53dub0f3IwbMz5XNmzdnMt7JJ09uf8RoAAABAElEQVTZz88z+PY7H66tSQw8MOeQCckGbG4yURZyj2rVIAgezsjVld7tpU5xWBwQi4aaX3nR//zLrOIuG6V64OulmV59Zs1+4qBDx+wuhdnjIu1LpYg2rN14yRWXz5v7tgAu6NT1N6W9XM/Uul4kRC2sm3XGsWgFep4ycd6HCSyEjccyIGI3ZCGsMZJ94r1h5IJZMhgwUnmEddUNrUDW9z/1P5a9jieASXBQ2ki0ffPqex58Kr+wy5333Dv15GPj8XZQ8PzPXr78sju/XvY1YACSUgmptRfSMDU7G959nt99/t7l1+Xf8terXSlTqUzAXk4MaxhAaWlZe4CSf8Dq/Zbx9efEjtcDQQAAAQBJREFUpCKRSOAZ7efnCXTRxef95lfzO3ULpdDoIa1FTO71KhMyEvEMdm7lB4vxx0R5VQymOdbpqU0b3ki2TDzz7Dtvv7OgIC/NxoWQ7XBm3k3wGew5Tz/93J+uua6iekeOlBcM7DMtv2tRc7rVS0uyilwv4yfZeg7gwyS8mJZgkLUQJFiQFcTSgI2wYm/3KfAHQBYwREbKfTf4/q9l752GAN7l1tMbc15aumbtM7PfP+fkScF/p1tr77n7rr/ffldbUodlyEKSdJjheRJwBxwg+w3t0lSev+CjTXfc+sf84vi1l13a2Ny6s3InUTCdFkCP7r2+vdqeZS/MwarvyTDS0tJSWbqhWs0AAAEASURBVFnZ0NCQSCS2bNmyefPm+vp6z/OCDjMlJSXdunXLzc2NRCLGmLfeeuv888/fz4cfP/5AKBp+QHcBZVgygffC/kp2HZujlWkRX4fwVNeuU1+18u4NSwaNn/DMddePnzQOgEEyRExor+IItpzgEdauWHPlzJveeuElMA6PZ5/VZ/BhIUen23aGjTScrYUiK4RgQSxJ+bxm3ueqrg6MTE1lYU+/lWEEERMIxLwLuLjHjIGC7LZgo6zuUZT//3qbwQ/aNHZ3Ygfw020A5r39yuihPTvlZX00943bZt711TerJWIhh1n41gqrjbWm54Dc314z+JipydxYp2645f03tk6Zdvp+e7vSAAABAElEQVRrr79x7WWXbt9R0ZZISQESTNYAKCvr9u1TA9/WFDAzs5SytbV11qxZc+fOrayszGQyzc3N1trdvSHj8bi1tqWlZdOmTYlEorm5ubq62nGchoaG5ubm/X/4dIsli9bmVoHsH+GTZsnRNKU9kSpAsV+/89KrrvlybfV1zz554RnTg+5RPlkHkiztDgUFiOn6+vqHHnjwnrvvaWhuzBU4oXuPaaVd+xnB6VTShUccZSEta8doRykWMiOi4M9XL1tRVyGB+qVLy4aPS1DYC7CRlhwboAS/I5YEwA7YMTokePyQHm778fr/kNfnB3aaXShcJpgTTzrl3vsee+7Rh1558YWsWeZChgAAAQBJREFUcLSmdicAN+yQTRqG9V1rLShzyi97X3V7n5KCna2cycb5wPCevcHMxcVdAVRXVVsLEIRsf1G6dCkBsMvo5j1vQwixadOmk08+OS8vb8KECdOmTSsrKysuLs7Ly8vKyvpBVizf91tbW6urq2trawcNGgSYXYV6+yjsMZ5kI8h6AgmBlIUEIgymICbHLIgIKVcgjvzlC72Z02d1737Am+++3b1zZ8Ckre9AhKwDCjGBOMiXIZ1IPfvcczPvuWPzug0AJmblTC/rNzInS/htLTZlQiFYimshLbRgQxCEKFNEw4WYu2V1q4KCaNm4yrY2/H/tfXecHcWV7neqOtwwWZoZzVdKpxAAAAEASURBVChrpJFmlLPQCEReENGwmGjDGtuPaGMccGBtbLAxz2sb88ALLGHJFg8whmeMsUmWAAWUQEgoa5Q1OdzU3VV13h99ZxiNRgExQtLufr/5/aSZ27e6uvt01alT3/mOKRqgmCUkAIFQtbDb4woLbwrfT546pmxi3zzoNITTq6ul7tjTaAQQXjcBkgBZMbrmhT89/93v/uCNeYvT7S3hUX4m5JwawBt3Yr9rvzfy9DMjFlrTKBhMp+WaU16c+8ebvne9tpyvX3s9gKb6egAkSBlJhoUwffuWZK94T68+fEfvuOOOsWPHPv300/vqdNe0BwC2bRcVFRUVFVVVVQFQ2hMEpq6BzCtFAAABAElEQVTisD07222pZkDHcySBiWNARhsWJJj9iBQu2RG4RJGdW50H79/83KNbbrz2x7f+6y0AMTRBRrJCTACBjSZoSOfFF1/+2Y9vW/bhUgCjou5Zg4acVlY2KOOJRFPakoEVs4yWbAjQAgAcLfpoymfaQAyIFMEyEIBp2dba2pQqHRZJe04Q822PRLtQOd0uhKCkEQEjnqMvmjQ4bhAIIenw6oftP7hHbPSk6ae8/tZbb7319ssvv7pg4byk38Dks9SjxufV/FPl7HN0vr29ISlWvOG0NOWtWvbWP1759w3rdgC45MprzqiZDKCurh4AZeta6ty8nKKiop7PR6S1/uCDD371LBI1xwAAAQBJREFUq1+ho8rZ3u5wZ5bMggUL1q5dG4/Hq6qqRo8enb0k6QJg3TnQhLGNHoqQ1DU2AMjJLzJIGwZRXFBrnog4iLWm7ZUf6sXz6xe9XbdtgxnSf+pLf3x4+vTpALQJJIVZW+iMNQspDeQdP7njp3fcZoweEnG+0G/giSUDSxzLSbYb5WnBKiu5BezhmLCQApK06qxszETkK6+luSEiIDkAbAZRz6OmBUEi1Xz6+AHjCqPw2Tj24VacOxBzj6QJWFixE08+88STzwTq6lP/0aI+blcJK46o25I09W2Z0txgYmaT17BTSMas2RU1M7xEwjOBXvLBqqnjquvr67KNERgcj+fsp9js9PUAAAEASURBVMZ8JpNJp9P7qfKrlFqzZs3rr7/+5JNPbt68ecyYMVLKVatWXXDBBePGjY3Fckr6lk6eMrGoT2iXYfi85yXVkmUfAYj20Qo2IxMlO58G/GNew0tPb1j1viGvdPiw8efOPvHE246fMHE0AKPBbEDZyFIYBg1vYlsi8fXrb5r7+MM28MUBAy4cOHCYLZ1E2vZZka/JhLFIe68JhkCB0Wk2EMQmq+utwSytiBt1jBIcaKEZJE0PuiLMljK6r6vPHVXuICTSO4dzaspebw8I85tICBgWtgCwa2ftkiXz33zrTxu3ftjYXrd7B0Suuf+l/gWF0b7OdeWRc0q+UH/Hv91ZXFCwnSMkAAABAElEQVR4+TVXjq4aASDJSgQMoK09AQAgYgMgFovGYrF9dSgajQ4bNuzee++9//77w550+jHhkmTLli0zZsxIJBI33HDDq6++Gg5a77777lNPPTV37rPpdDrlNaXSyRnTjvvXW39cUVENFp+84F2mtmSqae4fnpcxe/AIGWBzsSir3Vr8o+8tX/pe+vw5V37lJ3NmzJhU0LdzLWIAX0hiWATJOlThYm2MK63lS5d+9ZprlyxeVARcOWzkxQMG56VaMn67L4VHpCUbkMXCUrCMCWT3lAKfOJWlnjGIjGHDHC3tn99/aCKjLTaB0IDT44pbsPDZK+0bqyjMgfFgfcrSl4eEno0mdYgJ5gAAAQBJREFUpCizYSnF9m0L/u1X//7cc69t29EQll0BIkBu4eB2BLIvTiuhs1m7BaVlN37z5gcfuO/0E2ZOOe6Eb9z6/VOmTQ138tva2gEgqyYG1410ltDshtAs7r333pqamoEDB/7oRz/qGncJ11YDBw586KGH7rrrrhdffLGoqOiHP/yh67ozZ86cOXNmeJinNi5avPD6637wu3vu/j+/+w9jelYz+OOf/lC7ceW0UwYOGxp3UfDaX1t+fM3ik2dcPP/NnwwZWgHokAPCzCT87K+Q2Q12JoYRJCxpPfPUUzded11jW9so1/760JGnFpRyojUplIStAd8SApAdtaD2VjonIDDGAxqWGMYAAAEASURBVIOISJBRTAC4ePQkFAwwAbhj918aMqL7mClZe+xHCnIigmCIhfkcNLr3eQpmllIsem/B7JlfuPuex7ft2AVIIAewAQU0RkSkBF8rw9ctRCBaA9k4dFjFnXf95uF//+1LL79wxkn/9PgLr4dNJZNJZJkkAOA4TiQS6fGkoU8zZMiQl1566Ve/+tUDDzzQ1aERQmitLcu6+OKL33vvvZtvvvnOO++sqalZuXJl10Zcq/T44y799rduWfnhagBEzD1NT3+aOw/ADbcMGWDZzz0pvvul9T///oNPP/P4kKEVfibQgTTaMqwMFNgBR8ExcKRTPSqUDbzvgQcuu+KKtra2k/KHl0MCAAABAElEQVT63lk99vh+RRnVBONZRgbkMrvRQMY8K+pLSwklkbZN51PNBhsIirUSDAIzZ+scCdln9JS0zDGwDVEoldajQp5gLWzUNrW3eQzhKIBwqPWuDhrdE9sYrMGaIYQ0Xuut375xw9Zd55x5yUMP3PN//3rqo38pe/jPk59/e9JFl1Umm5ErphOGaGRAtuEcrRSASZMnlObHVKr5xutuWL+7BUDD7h0AGCIcafbj0BBRGNWdPHnySy+9dMsttzzzzDMAlFJaa3RIlwNwXfdb3/rWokWLMpnM5MmTH3nkkfDvxphAOQCqRk1samxPJpMkqKvRdHrWQytGANhVK+/69eaffnPpa5jj6gAAAQBJREFU4w8+d/X/uhIAGziuLS2IMA9HWFk9GOqoRUogJmnJBYsWfuOb3wJwRVn5HaPHVNo2JRLE4b6XATRBCzCT0ZKN0ASWzIBylaXITdtCQIFN0rYCBpT0KJchogqx/pXO0PHaT1rIGIpIA4uDQMi9sxEs5lwV37XbfnzhppSBZSzSnybt/ZDQfXrqYDcJQWhrqlu48P0LL7nyuWf+E0jV4+9JJDRyS2AvfMP2tadNKwBJDiCiFgBs3rDqputvbGxNSSnadm9euPyj4f9U09bSEjZNxgDoKlDSI8Lx5oQTTnj66acvu+wyx3EuvPDCsKZGtyMnTJiwYMGCW2+99eqrr37++QEmbSkAAAEASURBVOd/+ctfjh07NgzFjhgxMgiCZcuWzZo1q8ez/PBfvxPNVa/98dWYXfTkI8+fd97Zvu85jkNdJMVoH7oKbCOR3PyNb/yL8dJXnX7O5TmBu3Zdws2zSFoEbdgQBAyFM0snlYchQDqb6iiJfTdgCfJsC5awfW2kYIYB8ssr7Ly+gecRGwYJBmB0T5sqGQkFCMcsWP3hjHJTUzFUM1mfsRDNgdDdaBgEGCLAIF5YMmHS6NdeefnXv7l7xoklor+TVzpMosggKmiLkJwTLwDga717+673F733+huvvfj889vrGqOu42kJiMLcmOdlPC/cD8n6f5mMdzA9C4Jgzpw5TzzxxJdDYZK4AAABAElEQVS+9CVjzEUXXbT3McaYeDx+9913z5kz5wc/+MGkSZMuuuiiG2+88bjjjissLDzzzDMffvjhWbNmdbW2TsmLgoK8n972C+AngAtAB3zwPFoCvvudXy5euLpqbOX9L/7GavSX3vsHf95fVRA4kCRAPc6IYTSTSAkwtM0sYLWSPXjGLHfTmmSqUSCtmDPA0JHjANkRNtgfjK+H9Yl/+ZSRk3KtMqmF0gJOr25p94AethGy7wazjOTf/r9/ceZp533n29+KOtHiIaJidE4sNxKznDVLEyrDX736Vinyt2zetHb1+paW5rA515KKLaNSwybUHDexuqW5KVAhdegTwuUBuxUqNymipwAAAQBJREFUawI455xznnzyyS9/+ctKqUsvvbSruAmy2Z+GiE4//fTTTz/9qaeeuvfee0888cTq6urzzz9/6NChL730UlNTU9ewUKcBsWEAJNwwKC0ki+zIvs/n1Lmj9PBD9z5w/6OxuLjlN/1aI48VlF1UdfUV69ctb9u8WUohuSPHZq8WCGRrCYYhbSTvZC+nZtqAKy5reuQhZobxwCTi+bGBI5TmA6rpMSBtJNu2xb38wSXlQFoZaQg9MPx6Fd2UsACAwhi8IIBPOOncN9748/XX3Lxk9Zota2nL2mTHsVKK+B/n/rXzu1KQEDJQSikNpKrHTnr4kd8XRt11tU1+EIRuXHgljiibRIcAAAEASURBVHPgYr+fPFrms88++9lnn73kkksSicTXvva1biU2wqcYWtLll19++eWXL1y48IUXXnjxxRdra2ullI2NjT3GEknobNBPAGDCwVbMevvtt2+68fuAf9Ptwy871Sx5fu6uZ98qbLRFe6tjObJDu2aPTbXOkzIECwAstFKZaGm/MddeMz+/rN/UU7e9MZdIs6G8wSNj/Uck1YEdE6X04D7uVbMmRxINOshIy0EnGflwYk+j6ZAuzG49EQA1/YQ5by4ove/J7//hiSVrV7Sm0+EgrrRp6/pdbVgbZcfyqquqvnD+uTff/O3cmAvAy6T8QGWFyrBH+P8goZQ69dRTX3jhhX9/YVNDAAABAElEQVT+539uamq65ZZb9j4mS0UAAEyfPn369Ol33XXXxo0bm5ubBw0atO/CHKIrWRFs77/mgRBi3bp1X7riy4lM8ryvDT7vm5W1QHFEtK5fVZwYns6TBEOaQWSIwrzJbjuMTOSRkESCdZ5CrnH/ct+jr46uGTrr7K1v/5FJGU2FQ4ZzToFJh8Te/fVHG+7H6owBRTFTxH7AQkoCQUMc3mCNBSBUntIgcHjTRMe8T8wW+4jn8RXX9Tn7Kydu+Lhl28bEx4uSdVsLos7QVEYTGYmI44i+pf1mHlczsqqyemQlATt3bnnjzZXnnTUn7XnK90OulSICmD5NDk4o3xf6xa+99tr5jymSMAAAAQBJREFU55+/ffv2e+65J/y0q6xa596C1jqsdTBs2DB0EBX2ee1ZiCz5GnuTewI2JIQFQt3uhi9+8eKt27accGLebfdUNojtPorLRrIalmpfl7FIKm0cEpK1JtZkd5BdurQGMBnBWrNFILV9Z8KLb5xU0JbczMxkjBSRvBGTfS3AmQM+eMsS2xLJja2ZMXkREsaTSsNys9JPRkCEmVYsDMMcWpWens/LDNIKgpSwJMIah7JzQiaAXARoT+vGSMSfMMGfPEFeekFNEa4DJuyjTd64Yf33v/fNuoQ+76w5GS9QgYYgkNQQQI+LgP2hcwKaMGHCvHnzzj///PPPP3/u3Lmu64Zhm4mZdxcAAAEASURBVPCwrJn3tFG1Py8qGy3uWE7v/TmBSYOs5ua6iy/54vLly4ZXF9316Awn0mohk4tWO9lXtBW5kAFpI11ljIAnYMD23vQXYrKZJTzNOe22tEUyVTZ0a98yPf9NVoEFcFHcGTiGtRAIDsjRtITcnqZH563/0ZwxRY504WuwgGVgNBRgSSPAYKEZ6pA1o3s4LwBYEhBueId6UHr1vHTrrh2c0T6E3ZbQ0QxT68Id21b4firjpdpaM6lUe3397g0bNvq+r3x/8+aNDU0tcy68GEDaD1SYO2kgGT7Qt7j4EDoaTkADBw6cN2/ehRdeeMYZZ/z5z3/ez3ZEb4GNLYRKZxouufTTLHhqAAABAElEQVSKt956u7zCve8P00qHpHZzmw0rPyisW9jg7IgXBX1b0RAIaUj6Mghjv3tTdASDIX1JUZVWQtTF8leMrizy7e11OwEoIK/foEhuTkYHWdXjA/XOcvL/tqG96f8t++bsqgn5MYkAlJIckcRAYCyXGATRi9n/ACwibtq1deWqj9s9JBLtidaWzbW1dXV1QRB4XiaT1Kk27K5fv2XXBmUCA+UHGnqhl3pof43CAtDQ0BYAZFkMEJNgWAwfGDBgwKH1NVw6xWKxl19++aSTTrrpppsefPDBQ2vqU4AAWN/41vWv/fVvRWU5v3568KixrQ0mQ8LYsDMZr8Gr98uCbbu25MB1B3SicwAAAQBJREFUjAik8Mhi4ogC7ZX/R1kfSoA8y7fNOedtqR4vGpOpHbUI3fKCcmHb2lMsDopGFdjQdvT9Ta0/2vneueMHnj95aKkdBwOsmAJFFkjaIGLRi06OBdDSxe+edu5lBzqymBAwgpAzRYgIYQBLm9TehyooAC0tLR6yNG1CWE6F0YUC/GnROctYlvXMM89MnTr1zTffPOmkk8K4X+/WoOtkZRDhnt/97qH7n3Vizs8fOH7GtNYm3So4apGtCV7cG3vVGFMTrH72o+SbqRLlSmODwSJU/OvOHg2pbZZxMsK37PyPR43ZGkQL0nXJnbWCYBixIdVGWoJIQWKfGZWfQIuUq7lQxPZwIzcAAAEASURBVOp8/7fLN/19866vTRlbMzw/oi3BWgitSIKpY1nTO7AA5OZE4oCX1YTZ1xNtZXRylgUjow0IkZKSslguxSJF0UjUsoTrOoMGDiDwO/PnbdtSm8oYV1ohX4uECZhhYPSh1dT7BMaYAQMGXHvttT/72c9OOumkz9jafs4ipVyxYumtt/4AwHd/OfGsc4J6BYWYKxSxDTIp4e2M7yqYFBndf+xHu7e1ftBSagq0pgwJYpnNyu0CJkNQUsdZxnfI6Cvrd4mKEa3bV3st9cQgEtHyYYYgiAxgdWjj7ge2FrY2iqCcaMZxlzWkfvHy8ktmD7xq0rBI4FhgltjbtfqMsAAU9ulZjFt0AAABAElEQVSTG7OTqQCggoI8IaQQorCwsF+/0pyc4tyC3FhuQNJX9rpRQ8cO6ntufm5RPD9wXStqlfYtKczJ19FIH9fJ7fqq79z28Y3f+bkJOCwSwpQtRdCLuOGGGx599NEFCxbMmDHjkEevfaFzXfbgg4+1J9IXfHXoV24oaMJWLWxJcR8EMgwpYGnSjdxSWpo7dM7IjasXw1e2QcDQ1JMUPjHYaJIisHf1K/JipRaoacvHRrULIJpfECsZ4AVM+xDb7ogAhQM2QOQEbkCUiRr2U+WB0E5st0VPLFo3dUjp5II4jO7wpXspUQhAaDR2TkmbEV/+0lVfvvKKiophtu24rttNxR5AeQ+A4AAAAQBJREFUG36ZhynAqftoqiu4bMCo5/7wBICPYZgIbKQJuWe6oKDgs/fbGFNUVDRr1qy5c+fOmDGjK+2mVxDOTMaYd95dYuXSNbf2D2i3z3EIxfA1bIaw2Qg2TMaQXY+6eHUsEo2nUjEpWkFIOzoadCcpCyM1uYFlLNabygfV5vePe4m2NUvCwdgdUJVbUNJsohZ8i4NuwwyBLA0jjBaGhWCWYMHC12TpdHBWRfxfpg3/j/nrn98SrWf7g03NkyfHwxgUQQMKvSpqZCKxnBtv+tZPb7vddXv0sYOQzGvzlAD97YNSrfjkam3HJimNUh1LeATBQalU7h/h0HLxxRfffvvth6MMc1ODiJkAAAEASURBVGg0tZs3f7x65YQTCwcPzm1HOygARwBNpAhWGA4BoMECHHecFmF5rGIgYYTM0qj3QMhziOt0g+NuG1DBdomuX57cucYi+JAlI8dbtsMBgE9027tetBEUCDsQxGCHNGWSSgpfuPkWn3tc1agC98vHDfloy7L6FBfGSjqSyrIX1Is3RwBc1q/8l3feaVtCKx2Gwj4Z7cN8AQRgitI0y4xg5oNx0Dph27a15xPNZA6xCl4nOveeZs2alclk1qxZ8xkb3BfWr9/gea0zZ5c40DA5QBJsAcJi3zJGMANkSAAURdzb4PtJj23DDFu7lnbEXqXbmKCFjgaZ3X2KN5RWWpzTvna09e+IAAABAElEQVRpurFJEcEpKBpapQxJ1kw9eDMM+BKBkJosoxiZxKh+kYp8222pGxrzK/JdDlrGl+R+98xRx5fT+AEFYAUoAYS1EHrxtlid44aQFPIQuy9DCIAHcsB5BEAE2aD7wcG27W4N7iWF/6nRaTQFBQWDBw9etGhRdXX1Z2yzG0Kf5uOP1wCYMD0vQBuMA5F9dwkGpBlgEDFJkjETaVy0ixJEOWAFqSxBhvfyJAhkKEgwrRtYWZdT3tdLbF82HwwDESmriJaPSCqdFRrbyx1igiFjsSfTqSIbTtA6Z1zljKEl85euN55XSCDpMKua4WXjhpTmWwT2QOgQoutdo/kkdm56oI9k9RdCQQAAAQBJREFUA6YOYECyg4D6KXrgOE63uSOdPkAZ0k+FESNGHI6RJjT0pUtWuLkYWZ0ToA4ij+GQyIAlk2BohgCzZEsQq91+akmqkIvbSRnBFpMhJUhjz/KZBtox2JVT8tGAapBrGpc0bVghBbRB8ciJVFTuZ3wixRBh3mTX7zJArJxE8ylV5V+YPtKkki0t7WXSXDmjUgHSABQJ/ediK9yDtTu8GZsPrpD4wd6cPX/de4+GAQkTy6aAZO/CgbepOxFWgOr6l1Sqh9DOIeP444/fvHlzLzbYFRs2bqkan1NaKhV8hgfOATKA1rA0WIMZRIajsOrX7RTbrLjJYbBmYwhaKEN7EGIIYOzlVO8AAAEASURBVGJXcX1RWW3J8HxQ86pXMq07BTmQVln1hATbRkrBBj1VQDCgdCp1yvjB3zlj/NRCOb1/QU1FuVDMSlsaxAALweyyglEMBJAmm4lxqFXb9wHRZc+lp90XJiiCAXO9pxcosy6s5YasCes9va0e4LpuN6PxvIMiYR0kRo0a1VnrthfAADywAhAEybrddcOqY0RKwyUKmUYWgW1DGtLAIlZa+poR/Ueek3YzdsrVELC08ASDQJ5lArIcJQJhEo5xA5Fhd8Xwoe2Rkvzm9l0L/y6ZjYnkDqrGyCFa+bkZIxiG2LeUtpTkIKIDx2gtGEHrmYOjX59dnQcD7UOrHNeJODayZUPLKAKiAAABAElEQVS6PD3KTgeEUKWml3e8xZ7/36t1UpBJSG0o05ReuDv1nKG6DiOhg4kaRSKRbkbT3t7+mbv9CYYNGzZw4MCWlpbeazJ02tDS0rx7965R4/oE8DQ7DCJosA0Iy2TLy9kASCNTaC+PW0J6bsY2IJYstGSShgyxEkIwWChDKhZQXazPh/0HR4WbWru8beNGS0Kz6T/lZC7qq7WSDCUIkFE/JlSkTZl6lW5V7SrZNrmszw2njCmTUEox2aCsVG9WVT7LXxYgCyQpazQd//YqDriPRSABIyQP6JdzZsD1ZHLB2NOtCZdYPUfxo9FoKODeiba2th6PPDQ4jlNVVdXe3t63b99eLL9OlQAAAQBJREFUatIOr6W1NZlItQwZNsog3SU4xmAycAjtEobhOHDs2mF1u+fl24L3zNAXTLYWARnP0rbRrgYz1/Yd2FpYmacy6xb9kT0VECIFhcWTj/MTlhBOc8TP2IgGlu2za5lJFUX9iiLlebLUdWJKJRraM9KJWQKiIwRzJHAQRgMXTBA+cYWjKyF9FilwLEu+yXbb9Dy7AY7jHD6jCdc4Y8aM6dWIsBVeR3NTIpqjSsq17jaaEjRJAWNxwCpW6FSmVpfrFkWO060TDLIMtIRvGVcbl+1G291YPjLI7d++etmu5X8XUrI2xVOnoHy4SUgjyZdaMhO4JeoV6MRZlQPOrwhJAT4gtTayibgAAAEASURBVJFsjEVZbY9e3VD6FDjgJh+BGcKAbbBkmQYlCQYUZBXyD/SwLMvqlrOSSCQ++6q7K4YPH76fNN5PDc7WuqqraynsS8XlSmdfiY7PwVooAUuyECYewWR7tXYDMnvdC0MQDIvZECy2fePW9i3ePmBYRHm177yMTDvBkFtWUjMnyQ5AjEzMT+a3tQv2fDu3CbHf/HHen1Zt9gMNj+BBEiwpIATCxJojhAMZDROMZNbGKDCDUr5pVqYFSBD52al0v+Yupey2b+B5Xi+6NcaYnJycfaVsfkpwx5DJAFqb26IxJ68Qeq9gpqEMmagwjiPz3GBK27J10dCf2BOBBGAsDTKSONaZ0gs+AAABAElEQVQkYjvKB7aWDQi2LGta/DoJ0ppLp84WFTM4owOpPZ04q3rwrOKCZKrJ8ZKDY5HJ4wY1pZItANs2kwQMh8VUD1zC/TDiILg5QoMaFTUILhIc8c3GdJqK4pMkEQwgDCjYP7s2Pz+/669hiv9n7zqQTR+2bbvXitMQdw4rqVTairBtmb0XexJGkAej+zrjvA1Ocuu6HDu6t5AiMQwxMRNbGsbY7q7Sct8Rm/76gm7bwYLiTm7lCWe3qXypWxiZmGodX+YcN2WCmv/Ruo2bvn/2qdOKog7AOq2JjCPtkJne4RccqaHmQEaT3TEqdCiu4TOCqFUZjfcVJJnDMIQGtQJ5YHdfa0vb4AAAAQBJREFUdtNt49OyrG7rqc+CXqXRsIEmkiFlKem1WXENWED3ydTWrKktJmaW4Ppdq5eaZLNySglJ7DmRRQLHs9OKpNARYzXm+na6fGDz6vea3p1vSQo0F572BVU92bQFGjzI8A3nTJ8xqCBfqN+cPOadgXlDYxYhLM9mS4QEPHHE5qQuOIiHxxKQIFdSBztEqNDJg6MAAxQA+zP7nJycrr8GQdC7Ps1hQiadkbLH8A8zKYeHlMirgZLGdR8JlcOR9g5NvE+gCQxJTLZRxMYRItKwY+1fXmTVoiQiA8oq5szeFmnllOCW1tIR/Y8fUhwD4LWzradUD9KGheEs6f1oMJYOHPQbz3lM8cEAAAEASURBVAhr0QIwaA2wiaCIi2wxCMaF2V84oJvRpFKpUBLgKIfSKhbrUeslpDt7df4Lfurtls0vS+lTTzE0LRhsS4LFaSUsxOJr/vKnxPLFiBIyfEW/ypM+3OGZPD2mciOX7E7WP7uCTiwtLi+KpoXvwopCiiO1QNovDmg0DArftjAwQwAxY2viUV+8U2p9t49bCaEg0kB8X2511+mJiBKJRO/G9w4XmF03ulfqEgBosiw0J51n7Z3DM+vriqw8zRGC182n0cJI7RihtQgyOQWr25IrViwCBHz/lOLiS6OR8rdfSS18OTO28vjT50ROnb3NWLvak/nxnBwZEUJ1m4qOHuM5+Hp3IZDbAAABAElEQVRPn8zWktwCd3xAeYX2tGzQi6N7rx065YMKCwvREVMJk5g+lQbnkcJ+hOsMEWkZsaZEG2ZHdv5N6t3aMrzXFg/BWEZ6wqgIbRbysXUf7YaC7Q6k6NcHjoqitTW/Jd/XWPBu/TuL8VLV1Nt+4VZVcQDyGS5lXd6sHfZyUYzPgoOJ01gdPxIcRiLdvs4lZfatgoeDeF8q6tnirUAYq+2qrnhMjDSCSPaU5Mogwxa0W4Yr29YO4fad0sp0c4GzLbCxDIhIufKv69cvTLRnHAFJZ55w0sBYjkj7FsdSmfxM8WAnLyqXLlt41891Kp22Ebgek9EgHRbWCvMmPw2N6bDiICyXp2IRtQAAAQBJREFU9vwBCDaQB8Q/iWT3LMKbNZG9BWl6dyeh98BdA0+RiKPSaQEFUIc/l01ejAQ5/eR1uTjdbPhYilTSEVJLXzJAlhbCSLCQRtiatJ0QMfH/diWf21qnyEHGTBw387TcYXYyIRFQQjszp0/4z4dx5jkqTXbtLt3engK0AFG2HlzHXe9lTsxnwWdc+oY213PIoFPhITc3F3tmrjQ3N3+2834eyInHvZRmUDe3xrApkqOK5TnGz6lbtc61Y74MItC2JmJoASWMEawZwlh5lrM4kXhkfW2SLHBqfG78G/H4yNq1aa/RdxH4smLOaZHhQyd+79urVWLr1h0inhMDHCZ0T1s8KvnvxxMAAAEASURBVCamEL2ZeLc3QkPJz8+XUnZNqK6vrz+s5z1UUFe3xHEjQASwGd4etBgmwaVAnr+jMWhodaUQ0ERBRFu+NEqEZE3WhJhdsMDzfrVq9c5AQ4ohlryxYtjk1OaUl7Kmjlcpq3nZLo08A+j8/NF3/KD/9l0y6kazBM2jx/HtjsNrNJ3TU6eQTGhGu3btOqzn7RXEY3ntreQFTHZ2pBEkDGuAfJWB5TdvWBE0bBK2FMbSkERQAoCJKiIjhRtZ7/n/++MVa5SGtPsI852qCRNsq85Pm7z+I069aPw5Z29fuCKvYpAEGw3PLigc0oeYYMBC4Ci2msNoNJ1M3lgsFo/HW1paOmeLkjT9AAABAElEQVSopqamw3fez4ZPnlRuPN8oSyvRaTTGZMVnbOEAfuPy+dGgla0iaWwtBMBasGXgGNs48Y997+drl21Kp23L0cb75qCqGiu+Oz9SffMv+gyurKvfqWOxwafVAIyAHQhL2p0iEwqy91kwvYfDPj0RUVguJTSa0Ix6l/F5GMAA5eUVGi29jLGi1MGYDDnfKt+pBPITGxojShqQBd8QpLHDL4pIbHkm/Ys1Kz4M0o4loir46pDhFxT12aYT1Td/d+CZJyaBcpRZSrECCzICkowIxfgEgyCZjor9gn3g83CvXNcNg8Kd+0Q9Lrk7x6FeT5c8aIQrlOzZCwpztQ7SSV9AMykNnsjlkAAAAQBJREFUm0mTaerDx+Vidqq+XW3a4cqIxZy2hG8bwzrPE7ab85dk/c9Wvv9hOk1AwObSkRVXl5Q1B81lV1048MwzEMBizQjCss9KkCdhhOk6sHRkPRyl+Dx8GsdxugaFpZSrV6++6qqrampqJkyYUFVVFZpUr+1UHzLCRyWy6YB5eVHLlok21QcqAxjYAknHuOXiEoGy1Pp3goaNQVxEAp1mW2gjpPTcnL827v7d2tW7iaR0i5R3yYBhX4wVJ5pbzMQx/ed8IcEctdglCcjw3juAEy7msy9Ux/r6KMbhNZoQUspwW7vr9PTYY4899thjUsqRI0eOHTt29uzZU6dOraqqisfjR2Q+jP0AAAEASURBVNJ6upw5Py/XEiVNTTQEIFiSfGhZIM6KySoAuxctU+lUW6EV90RxAn7MaXTd/9y69vmtOxNwhRFE+opJNdece549rLxo2DDfzVHKle2eyDvsmjqHG5+H0QA47rjj5s2bx8xCiHD2cV1XKcXMq1atWrVq1dy5cx3HGTp06JQpU2pqampqakaNGuU43Qve8z4y43sHnTR5BkjHcyM58eLG+hYJIpZgP4IBJfIyRikbr3npiiJNQQDbWGS7H8TNY6tWv7m7MWlHwChT6a+MrJzRv7DfWdPypsxuBooBGOjQKTp6fdyDAn0+DkQqlbrwwgtfffVVy7LChXe4CO88INTK6/xzrUD1AAABAElEQVTVcZzKyspJkyadfPLJkydPrqqq6lZW47BZDwMGLEEZIHLWWRdMOmfl5dfkJwGh3H7yvH64mUg27Viz+PLrR+zanXYlxUs+8tO/3vjBoqY2dlwEwQA2360acVa+a7YmNvQtG/7b28pOOt0BbGgDRXCOxp3rT4PPY6QxxsRisSeeeOL0009ftmxZ+Mdw87KrzGInM4uZgyBYuXLlypUrH3/88Wg0Wl1dPX369NmzZ0+fPn3w4ME4jA6QDqsfGAQCkeI+Jdu3NVkoY26IcHkRnUwsATS8u0TW13tR8uPxv6ebn/lo9XIvCcuF71WVlv704i/mfbRix47a4tNmjT3tLKdPL38mowAAAQBJREFUSVQpSMmdSpjHOORtt932OZxGKZWbm3vuuedWVVX16dNHa93e3u77vulAVwJeaE9SylCCOgiCHTt2LF68+Lnnnps3b15jY2NYkiMajfa26XTm4hAoIDhLF69avf3NM8/vp9BmqUF95cWCcgDees/D9voNidLYPxobf71qVa2vjeOy9o6LF9584SWX/ubOSGXFlrr0qFtu7nvSqTmlJcRgEiAhjCQc6wPN5zU9dUMmk9mwYcP7778/f/78RYsWrVu3ritrWEoZTkadxXNDhOYFIB6Px+PxysrKysrKwYMHT5gwoby8vLy8vG/fvl3doL2VYHusU7cnOhwONiDxwL8/+WpSXWYAAAEASURBVNhf/tejL03OmFYnKK9wf+zwcSCz8sqvtq9e+4qXeOTDD3YAEJSjzWmx2CVXXfnFO+5S+TmWoExTsxEULSigru0e8/4M8Lk5wp0INxMikcjo0aNHjx595ZVXBkGwbt26pUuXvvPOOytWrFi1alVra2unf9OVTRwOPMycTCaTyWRdXd38+fM7P+rXr19xcfHEiRMnTpw4duzY0aNH753XchBvSPaRhvPI0KEDdtWa9kTMjZAO8pNip2MDEGrcmGfe/cdz6zfWhVJn2pxaNuhrseLCaBHHnbQgBZVblGt1ub20xz/HNj7vkaZbBK/bqx8EwaZNmxYuXLh48eIFCxasWbOthVcCAAABAElEQVSmK4mis2BCiPCLSqlwN7TruEJEpaWlY8aMqa6unjZt2pgxYyoqKrqxTg/YTyL6eNXaU8+d/djfRk4YUuPymRHqb2EoCA/88mc3/uAn2pbGMGn+7c/vvOTcs1Mb1jTubMxQZPDosWUTR3pxaUG6Rw2foRdxZKanrmufzg7sPXGsX79++fLl8+fPf//991etWtWVUGFZljEmEolEIpGWlpZOiwnLRWHPucm27aFDh44aNWry5MmTJk0aN25cWVlZt7xPdOFydPanpbl1Ws3UH/223xX/9EOJM4zJCBFZsPD188+5qKGhzQjBxtx3773XXXdNZxtByqOMsqIuOwQhPpU8FgaPCwAAAQBJREFU+7GCI2M0B0S3FbUxpra2dunSpYsWLXr33XdXr17d2NgYfjR27Ng+ffowc1tbW3Nzc0NDQyIRlsyEZVnh1ek99UQLCgoqKirGjh07bdq08ePHV1RUlJaWdjs7Oox4wvhpV10946Zv/FSzkORsqW05+eQTN2xcGyc3yd7tt91x609+ZBhstCQyAgHIABLGYib8j9F8juh86cOITrdPN2/evHLlyvfee2/58uVLlixJJBKxWKysrGzSpEnjx49PtCc2btq4aNGiNWvWdEqQSCnDqVBr3c1B7t+/f2Vl5aRJkydOnDBmzJjhw4d33fS4/LLLYeRTf3gcSHueOGvOxa+/8QvM5+kAAAEASURBVCc3GvHSma9c9S8PP/oIGAEbbUxEWgwwMUMzwAxJ8ujKPeklHKVG0w37mcIaGxtXr179/vvvL1q06KOPPkqlkgUFBVWjJg4ZPIxkpqmlYcWyj9asWVNXl2XwOGSRNGDDUmhjGUNsVCf91onEBw0unzhp7Lix1VMmTx1bPeWRRx5++OFH169fZ9nyx//609vvuE3apAOeMn3aW2+8GY/FmMFgwyxFtsqkCLPDTEcFw/9yODaM5uCxadPG1atXLV26pLZ2g9YOm9yyQU7CfNCwc8v2zd7mdfVbt6YZCmAhiQUEE9gSxIBmZqXtrvmUJcX9I5Ho7rqtGzZs3LFje01NjdZy06nUAAABAElEQVTKGC4qKnrrrbfGjh17BK/0COKYN5qu/e8yDmlAMmP7rqXv1/7bxq1rHfSzItv7lqK9Rb77ur/gjeYNHzelE1lajxCQQggwBLOxjIqDHIOU4WxS39133/3KK6+89tprlmUppX7/+99fe+214TTXu/L6xwSOeaPZuwAYMxttSEpNG+twZwIf2hiokUpzM1EiBkcg0uqbbWtylsxLzn9r64dLGndvTsFIwCVo29JCGG0kSBrDQoBZRaOxTCZDRL7vX3DBBc8//zw6Fmj/YzT/BZAV983Qpq36Jy30riULDAIgA0QAh5mJ0hLpCGIRFGnEd+5w138YvPvW5sXv1a1fqhLtwyabNAAAAQBJREFUKcAHhJBGSoAlMymlQuPo06fPokWLhgwZcqQv80jiv57RAGhM8Udb/ceT8h3IIsUMUS8Qz0oWGgtwwIIpA8pYZCwgAteC0270xnVq5aLMO39Lfry4ZfPaNpisgyylxWyMMffdd9911113ZC/viONYNxrmsNIhOjXo2hrVS7vVE8reAioOKGMoySZPABBpQIIBCphdAAwPMGAicsARKaSFtAsL8NpaI6sWyPdfF6uW+EuXrW5pbgUwc+bxb7/9lhXOR//tJqVPcGwbjQEzkwzDLjIZYOE279VmXgq7mYTpINpK5hjgQzSxKQEsiJ3MMUIMFIAlIEAG8DUnDSubh1mUkca0YrYAAAEASURBVGJ3PvoPxo3AeR9tmPvK6/e//Wry+q/efuack1WgLBk7OpRijgyOcaNhBrGAD5hm9fed/PskbYGQJCRgMVsdibSC4IGSMIUMIrETIEY+IMBELKFJkojIgsCLxcXoUvu0nf5jUeEOsG+FKYZYXo+HivAFySdr00qyWaAMiBzpqz9iOLaNBgCzIkrUq1e3Bs8oq43sdkYyrCpPsBhaIAAgWAIMjoDBaJMCGnFDHvMuyZE8M6tYXJqHqQo7FepidJqiFcy2ZapBhjkT0HoHQ1nnQgCshJT/JdhUh4hj22jYAAZJ+ZdN+s5A7jQgTQ4QCelUwhhBEGAGAw6MBdaOyLVViQpTfujCAAABAElEQVQs39rM0suhcQWYWShm2lQNBUgflIIugDDZLF0RCgCEzpAP+DA5R1Lw7ijA582n6V0QwNaaXerBjKkjilogARWASdgCGTKgIM/FQNsSzbTKIgkkXNNvkHOesQb52B6wH8W4uBwNtsAGVhpwYAqylVZIATaMBTIgDaKwbg+E6hQF+++JY9toWOzc6T2V4Np8a3Q+roiJ/kn9Rh295ZndBI6i1FIjS5xTpZVJmE1GsRTk+5aRlVGaEgVAMCbF7BEpsAsTZwDCIwHAgbZAGtRZk810yN8F+1Fo/++AY8touuqipzX7zd48TzUPin41X9ZIjAcj15qQxzN3+g8qkxoc+XokrfxGXQAAAQBJREFUdxxQxGgu0xstURAV/YxTapvhQLYlITqykCibwkLZyiWAIIQq/51HZE9+NOdZfx44hnwazhYny2oXeoYTAdc5ggkDwbkAQB6QBoTBulZ/bZSqIvYEMEBtBjsF8oE+gA0OhQSPIpmgYwvHpNGEET2Gz2wJSO6Ur6IAyDAkqRiLRIbX2DRCch7JBDjOHTLfJBgU7Ev17X9wQBy7RtNREo/C9DYCRPZPImBlE4FlPWATFwCGIUKyOGf1vrPfPmJXcyzjGDIa9FDrJfvoTUfZ9HDQMYAGogABScABO0zpjvko68b9j70cMo4to0GXQnadCUrILmTIAD5gwC4oA0hwpEMHWaKDGLoAAAA3SURBVJpPimVkxRjD7xyhqzi2cSwaDfYcVBiwP9HKo05zMh3SuwxohtVpZQzOyqz+N142fxb8f41q2doWJ42/AAAAAElFTkSuQmCC'/>
        </div>
        
        <!-- Informations de la facture à droite -->
        <div style="text-align: center; margin-right: 110px;">
            <div class="invoice-title">FACTURE INDIVIDUELLE</div>
            <div class="invoice-subtitle">Tournoi de Tir à l'Arc - Les Archers de Pérols -</div>
            <div class="invoice-info">Date de génération : <?php echo date('d/m/Y H:i'); ?></div>
            <div class="invoice-info">Numéro de facture : FAC-<?php echo date('Ymd'); ?>-<?php echo str_pad($archerId, 4, '0', STR_PAD_LEFT); ?></div>
        </div>
    </div>

</div>
                
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
                        <div class="info-value"><?php echo htmlspecialchars($archer->club); ?></div>
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
                                        <strong>SIÈGE SOCIAL :</strong> CLUB HOUSE TERRAIN DE TIR À L'ARC<br>
                                        CHEMIN DU MAS ROUGE 34470 PEROLS<br>
                                        Site : http://sites.google.com/site/archersdeperols/
                                    </p>
                                    <p style="margin: 0;">E-mail : contact.archersdeperols@gmail.com</p>
                                </div>
                            </td>
                            
                            <!-- Colonne droite (alignée à droite) -->
                            <td style="vertical-align: bottom; width: 50%; text-align: right;">
                                <div style="font-size: 11px; line-height: 1.4; color: #666;">
                                    <p style="margin: 0 0 5px 0;">
                                        <strong>N° Jeunesse et Sport :</strong> 03400ET0024
                                    </p>
                                    <p style="margin: 0 0 5px 0;">
                                        <strong>N° Préfecture :</strong> W343003998
                                    </p>
                                    <p style="margin: 0;">
                                        <strong>Agrément FFTA N° :</strong> 2034037
                                    </p>
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
    
    .montant-perols {
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
        // Récupérer l'ID du tournoi
        $TourId = $_SESSION['TourId'];
        
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
    e.EnCountry3 as payment_status,
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
        
        // CORRECTION : Il y avait une faute de frappe "$TrackId" au lieu de "$TourId"
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
            $totalPerols = 0;
            $totalAutres = 0;
            $countPerols = 0;
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
                    'cibles_departs' => $row->cibles_departs,
                    'target_numbers' => $row->target_numbers,
                    'sessions' => $row->sessions,
                    'departs' => $departs_archer  // Ajout des numéros de départ
                ];
            }
            
            // Trier les archers par nom (alphabétique) puis par prénom
            usort($archersData, function($a, $b) {
                // Comparaison par nom
                $nomCompare = strcasecmp($a['nom'], $b['nom']);
                if ($nomCompare !== 0) {
                    return $nomCompare;
                }
                // Si mêmes noms, comparer par prénom
                return strcasecmp($a['prenom'], $b['prenom']);
            });
			         
					 
			// Afficher le tableau avec tri client
            echo '<table id="archersTable">';
            echo '<thead>';
            echo '<tr>';
            echo '<th data-sort="counter">#</th>';
            echo '<th class="action-cell">Actions</th>';
            echo '<th data-sort="prenom" class="sort-asc">Nom ▲</th>'; 
            echo '<th data-sort="nom" class="sort-asc">Prénom ▲</th>'; 
            echo '<th data-sort="club">Club</th>';
            echo '<th data-sort="categorie">Catégorie</th>';
            echo '<th data-sort="montant">Montant (€)</th>'; 
            echo '<th data-sort="cible_depart">Départ / Cible</th>';
            echo '<th data-sort="payment_status">Statut Paiement</th>';
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
                    
                    // Calculer le montant
                    $montant = calculerPrix($archer['club'], $archer['categorie'], $nb_inscriptions, $tarifs);
                    $totalPrix += $montant;
                    $filteredTotal += $montant;
                    
                    // Déterminer la classe CSS pour le montant
                    $montant_class = 'montant-badge';
                    $montant_title = "Tarif standard";
                    
                    // Vérifier si c'est Pérols
                    $is_perols = (stripos($archer['club'], 'perols') !== false);
                    if ($is_perols) {
                        $montant_class = 'montant-badge montant-perols';
                        $montant_title = "Tarif spécial Pérols";
                        $totalPerols += $montant;
                        $countPerols++;
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
                    
                    // Bouton de facture
                    $invoice_button = '<a href="' . $invoiceUrl . '" class="invoice-button" title="Générer la facture pour cet archer">📄 Facture</a>';
                    
                    if ($is_paid) {
                        $countPaye++;
                        $totalPaye += $montant;
                        $status_class = 'status-paid';
                        $status_text = 'Payé';
                        
                        // Ajouter les paramètres de filtre au formulaire
                        $filterParams = '';
                        foreach ($filters as $key => $value) {
                            if ($value !== 'all') {
                                $filterParams .= '<input type="hidden" name="' . $key . '_filter" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        
                        $payment_button = '<form method="POST" style="display:inline;">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="unvalidate">'
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
                        
                        // Ajouter les paramètres de filtre au formulaire
                        $filterParams = '';
                        foreach ($filters as $key => $value) {
                            if ($value !== 'all') {
                                $filterParams .= '<input type="hidden" name="' . $key . '_filter" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        
                        $payment_button = '<form method="POST" style="display:inline;">
                                            <input type="hidden" name="archer_id" value="' . $archer['id'] . '">
                                            <input type="hidden" name="validate_payment" value="validate">'
                                            . $filterParams . '
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
        // Réinitialiser tous les filtres
        document.getElementById('club_filter').value = 'all';
        document.getElementById('category_filter').value = 'all';
        document.getElementById('departs_filter').value = 'all';
        document.getElementById('payment_filter').value = 'all';
        
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
    
    // Fonction pour confirmer le paiement avec les détails
    function confirmPayment(button, prenom, nom, club, categorie, montant, cibleDepart) {
        // Formater le message de confirmation
        const message = `Confirmer le paiement pour :\n\n` +
                       `• Nom : ${nom}\n` +
                       `• Prénom : ${prenom}\n` +
                       `• Club : ${club || '-'}\n` +
                       `• Catégorie : ${categorie}\n` +
                       `• Montant : ${montant} €\n` +
                       `• Départ / Cible : ${cibleDepart}\n\n` +
                       `Êtes-vous sûr de vouloir valider ce paiement ?`;
        
        // Afficher la confirmation
        if (confirm(message)) {
            // Si confirmé, soumettre le formulaire
            button.closest('form').submit();
            return true;
        } else {
            // Si annulé, ne rien faire
            return false;
        }
    }
    
    // Tri du tableau par colonne
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('archersTable');
        const tbody = document.getElementById('archersTableBody');
        const headers = table.querySelectorAll('th[data-sort]');
        
        let currentSort = {
            column: 'nom',
            direction: 'asc'
        };
        
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
        
        // Initialiser avec le tri par nom (alphabétique)
        const nomHeader = document.querySelector('th[data-sort="nom"]');
        if (nomHeader) {
            nomHeader.click(); // Cliquer pour initialiser le tri
        }
    });
</script>

<!-- AJOUT: Inclure le pied de page du site -->
<?php include('Common/Templates/tail.php'); ?>