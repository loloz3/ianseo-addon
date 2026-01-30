<?php
/**
 * export_pdf.php - VERSION AVEC BORDURES
 * Export PDF du greffe avec bordures dans le tableau
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Includes
require_once(dirname(dirname(__FILE__)) . '/config.php');

// Vérifier la session
CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly, false);

$TourId = $_SESSION['TourId'];

// Récupérer les infos du tournoi
$tournamentQuery = "SELECT ToCommitee, ToComDescr, ToName FROM Tournament WHERE ToId = $TourId";
$tournamentRs = safe_r_sql($tournamentQuery);
$organizerClubCode = '';
$organizerClubName = '';
$tournamentName = 'Tournoi';

if ($tournament = safe_fetch($tournamentRs)) {
    $organizerClubCode = $tournament->ToCommitee;
    $organizerClubName = $tournament->ToComDescr;
    $tournamentName = $tournament->ToName;
}

// =====================================================================
// FONCTIONS POUR GÉRER LES TARIFS (COPIÉES DE GREFFE.PHP)
// =====================================================================

// Fonction pour lire et parser le fichier Prix.txt
function parsePrixTxt($organizerClubCode = null, $organizerClubName = null) {
    $prixFile = dirname(__FILE__) . '/Prix.txt';
    
    if (!file_exists($prixFile)) {
        // Valeurs par défaut si le fichier n'existe pas
        return [
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
    
    $contenu = file_get_contents($prixFile);
    $tarifsParsed = [];
    
    // Parser les tarifs au format OJA1=8; OJA2=6; etc.
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

// Fonction pour calculer le prix total (version corrigée pour 3+ départs)
function calculerPrix($club_country_code, $categorie_code, $nb_departs, $tarifs, $organizerClubCode) {
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
    
    // Calculer le prix total en fonction du nombre de départs
    if ($nb_departs == 1) {
        return $prix_1_depart;
    } elseif ($nb_departs == 2) {
        return $prix_2_departs;
    } else {
        // 3 départs ou plus : premier départ à prix normal, supplémentaires au tarif supplément
        return $prix_1_depart + ($supplement_2eme_depart * ($nb_departs - 1));
    }
}

// =====================================================================
// CHARGER LES TARIFS
// =====================================================================
$tarifs = parsePrixTxt($organizerClubCode, $organizerClubName);

// Récupérer les filtres
$clubFilter = isset($_GET['club']) ? $_GET['club'] : 'all';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';
$paymentFilter = isset($_GET['payment']) ? $_GET['payment'] : 'all';

// Requête pour récupérer les archers
$query = "SELECT 
    e.EnFirstName as prenom,
    e.EnName as nom,
    CONCAT(e.EnDivision, e.EnClass) as categorie,
    c.CoName as club,
    c.CoCode as country_code,
    COALESCE(MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN 1 ELSE 0 END), 0) as payment_status,
    COALESCE(MAX(CASE WHEN q.QuNotes LIKE 'PAYE%' THEN SUBSTRING_INDEX(q.QuNotes, '|', -1) ELSE NULL END), NULL) as payment_method,
    COUNT(*) as nb_inscriptions,
    GROUP_CONCAT(DISTINCT CONCAT('D', SUBSTRING(q.QuTargetNo, 1, 1), ' - ', SUBSTRING(q.QuTargetNo, 2, 3), SUBSTRING(q.QuTargetNo, 5, 1)) ORDER BY q.QuSession, q.QuTargetNo SEPARATOR '; ') as cibles
FROM Entries e
LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
LEFT JOIN Qualifications q ON e.EnId = q.QuId
WHERE e.EnTournament = $TourId AND e.EnAthlete = 1
GROUP BY e.EnFirstName, e.EnName, c.CoName, c.CoCode
ORDER BY e.EnName, e.EnFirstName";

$Rs = safe_r_sql($query);

// Variables pour les statistiques
$archers = [];
$totalPaye = 0;
$totalNonPaye = 0;
$montantPaye = 0;
$montantNonPaye = 0;
$totalOrganizer = 0;
$totalAutres = 0;
$countOrganizer = 0;
$countAutres = 0;

// Variables pour les moyens de paiement
$paymentMethods = [
    'ESPECE' => ['count' => 0, 'amount' => 0],
    'CHEQUE' => ['count' => 0, 'amount' => 0],
    'VIREMENT' => ['count' => 0, 'amount' => 0],
    'GRATUIT' => ['count' => 0, 'amount' => 0],
    'AUTRE' => ['count' => 0, 'amount' => 0]
];

while ($row = safe_fetch($Rs)) {
    // Calculer le montant avec les mêmes règles que Greffe.php
    $montant = calculerPrix($row->country_code, $row->categorie, $row->nb_inscriptions, $tarifs, $organizerClubCode);
    $isPaid = ($row->payment_status == 1);
    
    // Appliquer les filtres
    if ($clubFilter != 'all' && $clubFilter != '') {
        if (stripos($row->club, $clubFilter) === false) {
            continue;
        }
    }
    
    if ($categoryFilter != 'all' && $row->categorie != $categoryFilter) continue;
    if ($paymentFilter == 'paid' && !$isPaid) continue;
    if ($paymentFilter == 'unpaid' && $isPaid) continue;

    if ($isPaid) {
        $totalPaye++;
        $montantPaye += $montant;
        
        // Statistiques par moyen de paiement
        $method = $row->payment_method ? strtoupper(trim($row->payment_method)) : 'AUTRE';
        
        // Normaliser le moyen de paiement
        if (!in_array($method, ['ESPECE', 'CHEQUE', 'VIREMENT', 'GRATUIT'])) {
            if ($method && $method != '') {
                // Essayer de détecter les variations d'écriture
                if (stripos($method, 'ESP') !== false) $method = 'ESPECE';
                elseif (stripos($method, 'CHE') !== false) $method = 'CHEQUE';
                elseif (stripos($method, 'VIR') !== false) $method = 'VIREMENT';
                elseif (stripos($method, 'GRA') !== false) $method = 'GRATUIT';
                else $method = 'AUTRE';
            } else {
                $method = 'AUTRE';
            }
        }
        
        // Initialiser si nécessaire
        if (!isset($paymentMethods[$method])) {
            $paymentMethods[$method] = ['count' => 0, 'amount' => 0];
        }
        
        $paymentMethods[$method]['count']++;
        $paymentMethods[$method]['amount'] += $montant;
        
    } else {
        $totalNonPaye++;
        $montantNonPaye += $montant;
    }
    
    // Statistiques club organisateur
    if ($row->country_code == $organizerClubCode) {
        $countOrganizer++;
        $totalOrganizer += $montant;
    } else {
        $countAutres++;
        $totalAutres += $montant;
    }

    $archers[] = [
        'prenom' => $row->prenom,
        'nom' => $row->nom,
        'club' => $row->club ?: '-',
        'country_code' => $row->country_code,
        'categorie' => $row->categorie,
        'nb_inscriptions' => $row->nb_inscriptions,
        'montant' => $montant,
        'cibles' => $row->cibles ?: 'Non affecté',
        'statut' => $isPaid ? 'Payé' : 'Non payé',
        'payment_method' => $row->payment_method ?: '-',
        'is_organizer' => ($row->country_code == $organizerClubCode)
    ];
}

// Calculer le total des moyens de paiement
$totalPaymentMethodsCount = 0;
$totalPaymentMethodsAmount = 0;
foreach ($paymentMethods as $method => $data) {
    $totalPaymentMethodsCount += $data['count'];
    $totalPaymentMethodsAmount += $data['amount'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Greffe - <?php echo htmlspecialchars($tournamentName); ?></title>
    <style>
        @page { margin: 15mm; size: A4 landscape; }
        body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2c5f2d; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px 0; color: #2c5f2d; font-size: 18pt; }
        .header .subtitle { font-size: 12pt; color: #666; }
        .info-bar { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px; background-color: #f1f8e9; border-radius: 4px; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; border: 1px solid #333; }
        th { background-color: #2c5f2d; color: white; padding: 6px 4px; text-align: left; font-weight: bold; border: 1px solid #333; }
        td { padding: 5px 4px; border: 1px solid #333; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .status-paid { color: #155724; font-weight: bold; }
        .status-unpaid { color: #721c24; font-weight: bold; }
        .summary { margin-top: 15px; padding: 10px; background-color: #e8f5e9; border-radius: 4px; display: flex; justify-content: space-around; flex-wrap: wrap; }
        .summary-item { text-align: center; margin: 5px 10px; }
        .summary-item .label { font-size: 8pt; color: #666; }
        .summary-item .value { font-size: 11pt; font-weight: bold; color: #2c5f2d; }
        .payment-summary { margin-top: 15px; padding: 10px; background-color: #e3f2fd; border-radius: 4px; }
        .payment-summary h3 { margin-top: 0; margin-bottom: 10px; color: #1565c0; font-size: 11pt; text-align: center; }
        .payment-methods { display: flex; justify-content: space-around; flex-wrap: wrap; }
        .payment-method { text-align: center; margin: 5px 15px; padding: 8px; background-color: white; border-radius: 4px; border: 1px solid #bbdefb; min-width: 120px; }
        .payment-method .method-name { font-size: 10pt; font-weight: bold; color: #1565c0; }
        .payment-method .method-stats { font-size: 9pt; margin-top: 3px; }
        .footer { margin-top: 10px; text-align: center; font-size: 8pt; color: #666; }
        @media print { .no-print { display: none; } }
        .print-button { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background-color: #2c5f2d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12pt; z-index: 1000; }
        .print-button:hover { background-color: #245325; }
        .back-button { position: fixed; top: 10px; left: 10px; padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12pt; z-index: 1000; text-decoration: none; }
        .back-button:hover { background-color: #5a6268; }
        .organizer-row { background-color: #fff3e0 !important; }
        .total-payment-row { background-color: #e8f5e9 !important; font-weight: bold; }
        .section-title { background-color: #f1f8e9; padding: 8px; margin-top: 15px; border-left: 4px solid #2c5f2d; font-weight: bold; }
    </style>
</head>
<body>
    <a href="Greffe.php" class="back-button no-print">← Retour</a>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimer / PDF</button>

    <div class="header">
        <h1><?php echo htmlspecialchars($tournamentName); ?></h1>
        <div class="subtitle">Liste des Archers - Greffe</div>
        <div style="font-size: 9pt; color: #666; margin-top: 5px;">
            Club organisateur: <?php echo htmlspecialchars($organizerClubName ?: $organizerClubCode); ?>
            <?php if ($clubFilter != 'all'): ?> | Filtre club: <?php echo htmlspecialchars($clubFilter); ?><?php endif; ?>
            <?php if ($categoryFilter != 'all'): ?> | Filtre catégorie: <?php echo htmlspecialchars($categoryFilter); ?><?php endif; ?>
            <?php if ($paymentFilter != 'all'): ?> | Filtre paiement: <?php echo $paymentFilter == 'paid' ? 'Payés' : 'Non payés'; ?><?php endif; ?>
        </div>
    </div>

    <div class="info-bar">
        <div><strong>Date :</strong> <?php echo date('d/m/Y H:i'); ?></div>
        <div><strong>Total archers :</strong> <?php echo count($archers); ?></div>
        <div><strong>Total montant :</strong> <?php echo number_format($montantPaye + $montantNonPaye, 2); ?> €</div>
    </div>

    <?php if (count($archers) == 0): ?>
        <p style="text-align: center; padding: 20px; background: #fff3cd;">Aucun archer trouvé.</p>
    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 12%;">Nom</th>
                <th style="width: 12%;">Prénom</th>
                <th style="width: 15%;">Club</th>
                <th style="width: 8%;">Catégorie</th>
                <th style="width: 7%;">Départs</th>
                <th style="width: 8%;">Montant</th>
                <th style="width: 15%;">Départ / Cible</th>
                <th style="width: 10%;">Statut</th>
                <th style="width: 10%;">Paiement</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            foreach ($archers as $archer): 
                $statusClass = $archer['statut'] == 'Payé' ? 'status-paid' : 'status-unpaid';
                $rowClass = $archer['is_organizer'] ? 'organizer-row' : '';
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($archer['nom']); ?></td>
                <td><?php echo htmlspecialchars($archer['prenom']); ?></td>
                <td><?php echo htmlspecialchars($archer['club']); ?></td>
                <td><?php echo htmlspecialchars($archer['categorie']); ?></td>
                <td><?php echo $archer['nb_inscriptions']; ?></td>
                <td><?php echo number_format($archer['montant'], 2); ?> €</td>
                <td style="font-size: 8pt;"><?php echo htmlspecialchars($archer['cibles']); ?></td>
                <td class="<?php echo $statusClass; ?>"><?php echo $archer['statut']; ?></td>
                <td><?php echo htmlspecialchars($archer['payment_method']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-item">
            <div class="label">Archers payés</div>
            <div class="value"><?php echo $totalPaye; ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Montant payé</div>
            <div class="value"><?php echo number_format($montantPaye, 2); ?> €</div>
        </div>
        <div class="summary-item">
            <div class="label">Archers non payés</div>
            <div class="value"><?php echo $totalNonPaye; ?></div>
        </div>
        <div class="summary-item">
            <div class="label">Montant à percevoir</div>
            <div class="value"><?php echo number_format($montantNonPaye, 2); ?> €</div>
        </div>
        <div class="summary-item">
            <div class="label">Club organisateur</div>
            <div class="value"><?php echo $countOrganizer; ?> (<?php echo number_format($totalOrganizer, 2); ?> €)</div>
        </div>
        <div class="summary-item">
            <div class="label">Autres clubs</div>
            <div class="value"><?php echo $countAutres; ?> (<?php echo number_format($totalAutres, 2); ?> €)</div>
        </div>
        <div class="summary-item">
            <div class="label">Montant total</div>
            <div class="value"><?php echo number_format($montantPaye + $montantNonPaye, 2); ?> €</div>
        </div>
    </div>

    <!-- NOUVEAU : Section détaillée des moyens de paiement -->
    <div class="payment-summary">
        <h3>Récapitulatif des moyens de paiement</h3>
        <div class="payment-methods">
            <?php 
            // Trier les moyens de paiement par montant décroissant
            uasort($paymentMethods, function($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
            
            foreach ($paymentMethods as $method => $data):
                if ($data['count'] > 0):
            ?>
            <div class="payment-method">
                <div class="method-name"><?php echo htmlspecialchars($method); ?></div>
                <div class="method-stats">
                    <?php echo $data['count']; ?> archer(s)<br>
                    <?php echo number_format($data['amount'], 2); ?> €
                </div>
            </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
        
        <!-- Ligne de total des moyens de paiement -->
        <div style="text-align: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #bbdefb;">
            <strong>Total des moyens de paiement :</strong> 
            <?php echo $totalPaymentMethodsCount; ?> archer(s) - 
            <?php echo number_format($totalPaymentMethodsAmount, 2); ?> €
        </div>
        
        <?php if ($totalPaye > $totalPaymentMethodsCount): ?>
        <div style="text-align: center; margin-top: 5px; font-size: 9pt; color: #666;">
            <em>Note : <?php echo ($totalPaye - $totalPaymentMethodsCount); ?> archer(s) payé(s) sans moyen spécifié</em>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <div class="footer">
        Document généré le <?php echo date('d/m/Y à H:i'); ?> - <?php echo htmlspecialchars($tournamentName); ?>
        | Tarifs: <?php 
            echo "Clubs autres: " . $tarifs['clubs_autres']['adultes'][1] . "€ (1d) / " . 
                ($tarifs['clubs_autres']['adultes'][1] + ($tarifs['clubs_autres']['adultes'][2] - $tarifs['clubs_autres']['adultes'][1])) . "€ (2d) + " . 
                ($tarifs['clubs_autres']['adultes'][2] - $tarifs['clubs_autres']['adultes'][1]) . "€/d supplémentaire";
        ?>
    </div>
</body>
</html>