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

// Fonction simple pour calculer le prix (tarifs fixes)
function getPrix($nbDeparts) {
    if ($nbDeparts == 1) return 10;
    if ($nbDeparts == 2) return 18;
    return 18 + (($nbDeparts - 2) * 8);
}

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

// Collecter les données
$archers = [];
$totalPaye = 0;
$totalNonPaye = 0;
$montantPaye = 0;
$montantNonPaye = 0;

while ($row = safe_fetch($Rs)) {
    $montant = getPrix($row->nb_inscriptions);
    $isPaid = ($row->payment_status == 1);

    // Appliquer les filtres
    if ($clubFilter != 'all' && $row->country_code != $clubFilter) continue;
    if ($categoryFilter != 'all' && $row->categorie != $categoryFilter) continue;
    if ($paymentFilter == 'paid' && !$isPaid) continue;
    if ($paymentFilter == 'unpaid' && $isPaid) continue;

    if ($isPaid) {
        $totalPaye++;
        $montantPaye += $montant;
    } else {
        $totalNonPaye++;
        $montantNonPaye += $montant;
    }

    $archers[] = [
        'prenom' => $row->prenom,
        'nom' => $row->nom,
        'club' => $row->club ?: '-',
        'categorie' => $row->categorie,
        'montant' => $montant,
        'cibles' => $row->cibles ?: 'Non affecté',
        'statut' => $isPaid ? 'Payé' : 'Non payé',
        'payment_method' => $row->payment_method ?: '-'
    ];
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
        .summary { margin-top: 15px; padding: 10px; background-color: #e8f5e9; border-radius: 4px; display: flex; justify-content: space-around; }
        .summary-item { text-align: center; }
        .summary-item .label { font-size: 9pt; color: #666; }
        .summary-item .value { font-size: 14pt; font-weight: bold; color: #2c5f2d; }
        .footer { margin-top: 10px; text-align: center; font-size: 8pt; color: #666; }
        @media print { .no-print { display: none; } }
        .print-button { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background-color: #2c5f2d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12pt; z-index: 1000; }
        .print-button:hover { background-color: #245325; }
        .back-button { position: fixed; top: 10px; left: 10px; padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12pt; z-index: 1000; text-decoration: none; }
        .back-button:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <a href="Greffe.php" class="back-button no-print">← Retour</a>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimer / PDF</button>

    <div class="header">
        <h1><?php echo htmlspecialchars($tournamentName); ?></h1>
        <div class="subtitle">Liste des Archers - Greffe</div>
    </div>

    <div class="info-bar">
        <div><strong>Club organisateur :</strong> <?php echo htmlspecialchars($organizerClubName); ?></div>
        <div><strong>Date :</strong> <?php echo date('d/m/Y H:i'); ?></div>
        <div><strong>Total :</strong> <?php echo count($archers); ?> archers</div>
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
                <th style="width: 7%;">Montant</th>
                <th style="width: 18%;">Départ / Cible</th>
                <th style="width: 10%;">Statut</th>
                <th style="width: 10%;">Paiement</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            foreach ($archers as $archer): 
                $statusClass = $archer['statut'] == 'Payé' ? 'status-paid' : 'status-unpaid';
            ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($archer['nom']); ?></td>
                <td><?php echo htmlspecialchars($archer['prenom']); ?></td>
                <td><?php echo htmlspecialchars($archer['club']); ?></td>
                <td><?php echo htmlspecialchars($archer['categorie']); ?></td>
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
            <div class="label">Montant total</div>
            <div class="value"><?php echo number_format($montantPaye + $montantNonPaye, 2); ?> €</div>
        </div>
    </div>

    <?php endif; ?>

    <div class="footer">
        Document généré le <?php echo date('d/m/Y à H:i'); ?> - <?php echo htmlspecialchars($tournamentName); ?>
    </div>
</body>
</html>
