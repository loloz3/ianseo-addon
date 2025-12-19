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
 * - Laurent Petroff - Les Archers de Perols - (modif: 2025-12-11)
 * 
 * Dernière modification: 2025-12-15 par Laurent Petroff
 *
 *
 * Vérification de la cohérence du champ "Finale Ind." (EnIndFEvent)
 * pour TOUS les archers
 * 
 * Règle : 
 * - 1ère inscription (session la plus basse) : EnIndFEvent doit être à 1 (Oui)
 * - Inscriptions suivantes : EnIndFEvent doit être à 0 (Non)
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

$TourId = $_SESSION['TourId'];

// Requête pour trouver les anomalies
// On vérifie TOUS les archers (inscrits 1 ou plusieurs fois)
$Query = "
    SELECT 
        e.EnId,
        e.EnCode AS Licence,
        e.EnFirstName AS Prenom,
        e.EnName AS Nom,
        c.CoCode AS Pays,
        e.EnDivision AS Division,
        e.EnClass AS Classe,
        q.QuSession AS Depart,
        e.EnIndFEvent AS FinaleInd,
        (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AS PremierDepart,
        (
            SELECT COUNT(*) 
            FROM Entries e3
            WHERE e3.EnCode = e.EnCode 
            AND e3.EnTournament = e.EnTournament
            AND e3.EnCode != ''
        ) AS NbInscriptions,
        CASE 
            WHEN q.QuSession = (
                SELECT MIN(q2.QuSession) 
                FROM Entries e2
                INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                WHERE e2.EnCode = e.EnCode 
                AND e2.EnTournament = e.EnTournament
                AND e2.EnCode != ''
            ) AND e.EnIndFEvent = 0 THEN 'Premier départ : devrait être OUI'
            WHEN q.QuSession > (
                SELECT MIN(q2.QuSession) 
                FROM Entries e2
                INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                WHERE e2.EnCode = e.EnCode 
                AND e2.EnTournament = e.EnTournament
                AND e2.EnCode != ''
            ) AND e.EnIndFEvent = 1 THEN 'Départ supplémentaire : devrait être NON'
            ELSE 'Erreur inconnue'
        END AS Probleme
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND (
        (q.QuSession = (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 0)
        OR
        (q.QuSession > (
            SELECT MIN(q2.QuSession) 
            FROM Entries e2
            INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
            WHERE e2.EnCode = e.EnCode 
            AND e2.EnTournament = e.EnTournament
            AND e2.EnCode != ''
        ) AND e.EnIndFEvent = 1)
    )
    ORDER BY e.EnCode, q.QuSession
";

$Rs = safe_r_sql($Query);
$NbAnomalies = safe_num_rows($Rs);

$PAGE_TITLE = 'Vérification Finale Individuelle';
$IncludeJquery = true;

include('Common/Templates/head.php');
?>

<style>
.anomaly-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.anomaly-table th {
    background-color: #2c5f2d;
    color: white;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
.anomaly-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.anomaly-table tr:hover {
    background-color: #f5f5f5;
}
.anomaly-table tr.premier-depart {
    background-color: #fff3cd;
}
.anomaly-table tr.depart-supplementaire {
    background-color: #f8d7da;
}
.anomaly-table tr.inscription-unique {
    background-color: #ffe4b5;
}
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
.fix-button {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
}
.fix-button:hover {
    background-color: #218838;
}
.archer-group {
    background-color: #e9ecef;
    font-weight: bold;
    border-top: 2px solid #495057;
}
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
</style>

<div class="Title">Vérification : Finale Individuelle - Tous les archers</div>

<?php if ($NbAnomalies == 0): ?>
    <div class="no-anomaly">
        ✓ Aucune anomalie détectée ! Tous les archers ont la bonne configuration "Finale Ind."
    </div>
<?php else: ?>
    <div class="summary">
        <h2>⚠️ <?php echo $NbAnomalies; ?> anomalie(s) détectée(s)</h2>
        <p><strong>Rappel de la règle :</strong></p>
        <ul>
            <li><strong>Archer inscrit 1 fois</strong> → "Finale Ind." doit être à <strong>OUI</strong></li>
            <li><strong>Archer inscrit plusieurs fois :</strong>
                <ul>
                    <li>Premier départ (session la plus basse) → "Finale Ind." à <strong>OUI</strong></li>
                    <li>Départs suivants → "Finale Ind." à <strong>NON</strong></li>
                </ul>
            </li>
        </ul>
    </div>

    <table class="anomaly-table">
        <thead>
            <tr>
                <th>Licence</th>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Pays</th>
                <th>Division</th>
                <th>Classe</th>
                <th>Départ (Session)</th>
                <th>Type</th>
                <th>Finale Ind. actuelle</th>
                <th>Problème</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $previousCode = '';
        while ($Row = safe_fetch($Rs)): 
            $isPremierDepart = ($Row->Depart == $Row->PremierDepart);
            $isInscriptionUnique = ($Row->NbInscriptions == 1);
            
            if ($isInscriptionUnique) {
                $rowClass = 'inscription-unique';
            } else {
                $rowClass = $isPremierDepart ? 'premier-depart' : 'depart-supplementaire';
            }
            
            $finaleActuelle = ($Row->FinaleInd == 1) ? 'OUI' : 'NON';
            
            // Ligne de séparation entre archers différents
            if ($previousCode != '' && $previousCode != $Row->Licence):
        ?>
            <tr class="archer-group">
                <td colspan="11" style="height: 5px;"></td>
            </tr>
        <?php 
            endif;
            $previousCode = $Row->Licence;
        ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td><?php echo $Row->Division; ?></td>
                <td><?php echo $Row->Classe; ?></td>
                <td style="text-align: center;"><strong><?php echo $Row->Depart; ?></strong></td>
                <td style="text-align: center;">
                    <?php if ($isInscriptionUnique): ?>
                        <span class="badge-unique">Unique</span>
                    <?php else: ?>
                        <span class="badge-multiple"><?php echo $isPremierDepart ? '1er' : ($Row->Depart . 'ème'); ?> départ</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><strong><?php echo $finaleActuelle; ?></strong></td>
                <td style="font-weight: bold; color: #c82333;"><?php echo $Row->Probleme; ?></td>
                <td style="text-align: center;">
                    <button class="fix-button" onclick="corrigerArcher(<?php echo $Row->EnId; ?>, <?php echo $isPremierDepart ? 1 : 0; ?>)">
                        Corriger
                    </button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <script>
    function corrigerArcher(enId, nouvelleValeur) {
        if (!confirm('Voulez-vous vraiment corriger cet archer ?\n\nNouvelle valeur "Finale Ind." : ' + (nouvelleValeur == 1 ? 'OUI' : 'NON'))) {
            return;
        }
        
        $.post('corriger-finale-ind.php', {
            enId: enId,
            valeur: nouvelleValeur
        }, function(response) {
            if (response.success) {
                alert('✓ Correction effectuée !');
                location.reload();
            } else {
                alert('Erreur : ' + response.message);
            }
        }, 'json');
    }
    </script>
<?php endif; ?>

<?php include('Common/Templates/tail.php'); ?>