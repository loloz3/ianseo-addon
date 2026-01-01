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
 * Dernière modification: 2026-01-01 par Laurent Petroff
 * rajout du bouton "Tout corriger"
 * Verif doublon
 * Vérification des champs obligatoires (Division, Age Cl., Cl.)
 * Vérification de la cohérence du champ "Finale Ind." (EnIndFEvent)
 * pour TOUS les archers
 * Virification assignation des cibles
 * 
 * Règle : 
 * - 1ère inscription (session la plus basse) : EnIndFEvent doit être à 1 (Oui)
 * - Inscriptions suivantes : EnIndFEvent doit être à 0 (Non)
 * - Vérification si archer en Doublon
 * - Vérification que tous les archers ont une arme (Div.), une catégorie (Age Cl.) et une classe (Cl.)
 * - Vérification si des archers ne sont pas assignés à une cible.
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

// Requête pour détecter les archers en double dans un même départ
$QueryDoublons = "
    SELECT 
        e.EnCode AS Licence,
        e.EnFirstName AS Prenom,
        e.EnName AS Nom,
        q.QuSession AS Depart,
        COUNT(*) AS NbDoublons
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    GROUP BY e.EnCode, e.EnFirstName, e.EnName, q.QuSession
    HAVING COUNT(*) > 1
    ORDER BY NbDoublons DESC, q.QuSession, e.EnCode
";

$RsDoublons = safe_r_sql($QueryDoublons);
$NbDoublons = safe_num_rows($RsDoublons);

// Requête pour vérifier les champs obligatoires
$QueryObligatoires = "
    SELECT 
        e.EnId,
        e.EnCode AS Licence,
        e.EnFirstName AS Prenom,
        e.EnName AS Nom,
        c.CoCode AS Pays,
        e.EnDivision AS Division,
        e.EnClass AS Classe,
        e.EnAgeClass AS AgeClasse,
        q.QuSession AS Depart,
        CASE 
            WHEN e.EnDivision = '' OR e.EnDivision IS NULL THEN 'Division manquante'
            WHEN e.EnAgeClass = '' OR e.EnAgeClass IS NULL THEN 'Age Cl. manquant'
            WHEN e.EnClass = '' OR e.EnClass IS NULL THEN 'Classe manquante'
            ELSE 'OK'
        END AS Probleme,
        CASE 
            WHEN e.EnDivision = '' OR e.EnDivision IS NULL THEN 'division'
            WHEN e.EnAgeClass = '' OR e.EnAgeClass IS NULL THEN 'age_classe'
            WHEN e.EnClass = '' OR e.EnClass IS NULL THEN 'classe'
            ELSE 'ok'
        END AS ChampManquant
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND (
        e.EnDivision = '' OR e.EnDivision IS NULL
        OR e.EnAgeClass = '' OR e.EnAgeClass IS NULL
        OR e.EnClass = '' OR e.EnClass IS NULL
    )
    ORDER BY e.EnCode, q.QuSession
";

$RsObligatoires = safe_r_sql($QueryObligatoires);
$NbObligatoires = safe_num_rows($RsObligatoires);


// Requête pour vérifier les archers sans cible assignée
$QueryCibles = "
    SELECT 
        e.EnId,
        e.EnCode AS Licence,
        e.EnFirstName AS Prenom,
        e.EnName AS Nom,
        c.CoCode AS Pays,
        e.EnDivision AS Division,
        e.EnClass AS Classe,
        q.QuSession AS Depart,
        IFNULL(q.QuTargetNo, 'NON ASSIGNÉ') AS Cible
    FROM Entries e
    INNER JOIN Qualifications q ON e.EnId = q.QuId
    LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
    WHERE e.EnTournament = $TourId
    AND e.EnCode != ''
    AND (q.QuTargetNo IS NULL OR q.QuTargetNo = 0 OR q.QuTargetNo = '')
    ORDER BY e.EnCode, q.QuSession
";

$RsCibles = safe_r_sql($QueryCibles);
$NbSansCible = safe_num_rows($RsCibles);



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
.obligatoire-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.obligatoire-table th {
    background-color: #ffc107;
    color: #856404;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
.obligatoire-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.obligatoire-table tr:hover {
    background-color: #fff3cd;
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
.alert-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-left: 5px solid #ffc107;
    color: #856404;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
}
.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-left: 5px solid #dc3545;
    color: #721c24;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
}
.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    border-left: 5px solid #28a745;
    color: #155724;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    font-size: 18px;
    font-weight: bold;
}
.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    border-left: 5px solid #17a2b8;
    color: #0c5460;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
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
.fix-button-large {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}
.fix-button-large:hover {
    background-color: #c82333;
}
.fix-button-large:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
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
.badge-danger {
    background-color: #dc3545;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
}
.badge-warning {
    background-color: #ffc107;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}
.doublon-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.doublon-table th {
    background-color: #dc3545;
    color: white;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
.doublon-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.doublon-table tr:hover {
    background-color: #f8d7da;
}
.section-title {
    background-color: #6c757d;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    margin: 30px 0 15px 0;
    font-size: 20px;
    font-weight: bold;
}
.section-title-success {
    background-color: #28a745;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    margin: 30px 0 15px 0;
    font-size: 20px;
    font-weight: bold;
}
.section-title-warning {
    background-color: #ffc107;
    color: #856404;
    padding: 10px 15px;
    border-radius: 5px;
    margin: 30px 0 15px 0;
    font-size: 20px;
    font-weight: bold;
}
.section-title-danger {
    background-color: #dc3545;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    margin: 30px 0 15px 0;
    font-size: 20px;
    font-weight: bold;
}
.cible-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.cible-table th {
    background-color: #17a2b8;
    color: white;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
.cible-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.cible-table tr:hover {
    background-color: #e3f2fd;
}
.badge-info {
    background-color: #17a2b8;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
}
.section-title-info {
    background-color: #17a2b8;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    margin: 30px 0 15px 0;
    font-size: 20px;
    font-weight: bold;
}
.alert-info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    border-left: 5px solid #17a2b8;
    color: #0c5460;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
}
</style>

<div class="Title">Vérification complète des inscriptions</div>

<?php 
// SECTION 1: Doublons dans un même départ
if ($NbDoublons > 0): 
?>
    <div class="section-title-danger">
        ⚠️ ALERTE : <?php echo $NbDoublons; ?> cas d'archer(s) en double dans un même départ
    </div>
    
    <div class="alert-danger">
        <p>Les archers suivants sont inscrits plusieurs fois dans le MÊME départ. Ceci est anormal et nécessite une correction manuelle :</p>
        
        <table class="doublon-table">
            <thead>
                <tr>
                    <th>Licence</th>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Départ</th>
                    <th>Nombre d'inscriptions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($Doublon = safe_fetch($RsDoublons)): ?>
                <tr>
                    <td><strong><?php echo $Doublon->Licence; ?></strong></td>
                    <td><?php echo $Doublon->Prenom; ?></td>
                    <td><?php echo $Doublon->Nom; ?></td>
                    <td style="text-align: center;"><strong><?php echo $Doublon->Depart; ?></strong></td>
                    <td style="text-align: center;">
                        <span class="badge-danger"><?php echo $Doublon->NbDoublons; ?> inscriptions</span>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 10px; font-style: italic;">
            <strong>Action requise :</strong> Vous devez supprimer manuellement les doublons dans l'interface de gestion des participants.
            Un archer ne peut être inscrit qu'une seule fois par départ.
        </p>
    </div>
<?php else: ?>
    <div class="section-title-success">
        ✓ Aucun doublon détecté dans les départs
    </div>
<?php endif; ?>

<?php 
// SECTION 2: Vérification des champs obligatoires
if ($NbObligatoires == 0): 
?>
    <div class="section-title-success">
        ✓ Tous les archers ont bien une arme (Division), une catégorie d'âge (Age Cl.) et une classe (Cl.)
    </div>
<?php else: ?>
    <div class="section-title-warning">
        ⚠️ <?php echo $NbObligatoires; ?> archer(s) avec des champs obligatoires manquants
    </div>
    
    <div class="alert-warning">
        <h3>Champs obligatoires manquants</h3>
        <p>Les champs suivants sont obligatoires pour chaque inscription :</p>
        <ul>
            <li><strong>Division (Arme)</strong> : Arc classique, arc à poulies, arc nu, arc traditionnel, etc.</li>
            <li><strong>Age Cl. (Catégorie d'âge)</strong> : Benjamins, Cadets, Juniors, Séniors, Masters, etc.</li>
            <li><strong>Classe</strong> : Classe de l'archer dans sa division et catégorie d'âge</li>
        </ul>
    </div>

    <table class="obligatoire-table">
        <thead>
            <tr>
                <th>Licence</th>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Pays</th>
                <th>Départ</th>
                <th>Division actuelle</th>
                <th>Age Cl. actuelle</th>
                <th>Classe actuelle</th>
                <th>Problème</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        safe_data_seek($RsObligatoires, 0);
        while ($Row = safe_fetch($RsObligatoires)): 
            $division = !empty($Row->Division) ? $Row->Division : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
            $ageClasse = !empty($Row->AgeClasse) ? $Row->AgeClasse : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
            $classe = !empty($Row->Classe) ? $Row->Classe : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
        ?>
            <tr>
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td style="text-align: center;"><?php echo $Row->Depart; ?></td>
                <td style="text-align: center;"><?php echo $division; ?></td>
                <td style="text-align: center;"><?php echo $ageClasse; ?></td>
                <td style="text-align: center;"><?php echo $classe; ?></td>
                <td style="font-weight: bold; color: #856404;">
                    <span class="badge-warning"><?php echo $Row->Probleme; ?></span>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
// SECTION 3: Vérification Finale Individuelle
if ($NbAnomalies == 0): 
?>
    <div class="section-title-success">
        ✓ Aucune anomalie détectée dans la configuration "Finale Ind."
    </div>
<?php else: ?>
    <div class="section-title">
        ⚠️ <?php echo $NbAnomalies; ?> anomalie(s) détectée(s) dans la configuration "Finale Ind."
    </div>
    
    <div class="summary">
        <h2>Rappel de la règle pour "Finale Ind." :</h2>
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
        safe_data_seek($Rs, 0);
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

    <div style="margin: 20px 0; text-align: center;">
        <button id="corriger-tout-sql" class="fix-button-large">
            ⚡ Corriger toutes les anomalies Finale Ind. (<?php echo $NbAnomalies; ?>)
        </button>
        <p style="font-size: 12px; color: #666; margin-top: 5px;">
            (Cette méthode corrige toutes les anomalies "Finale Ind." en une seule opération)
        </p>
    </div>
<?php endif; ?>

<?php
// SECTION 4: Vérification des assignations de cibles
if ($NbSansCible == 0): 
?>
    <div class="section-title-success">
        ✓ Tous les archers sont assignés à une cible
    </div>
<?php else: ?>
    <div class="section-title-info">
        ⚠️ <?php echo $NbSansCible; ?> archer(s) non assigné(s) à une cible
    </div>
    
    <div class="alert-info">
        <h3>Archers sans cible assignée</h3>
        <p>Les archers suivants ne sont pas encore assignés à une cible. Ils doivent l'être pour pouvoir participer au tournoi :</p>
    </div>

    <table class="cible-table">
        <thead>
            <tr>
                <th>Licence</th>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Pays</th>
                <th>Division</th>
                <th>Classe</th>
                <th>Départ</th>
                <th>Cible actuelle</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        safe_data_seek($RsCibles, 0);
        while ($Row = safe_fetch($RsCibles)): 
            $cible = ($Row->Cible == 'NON ASSIGNÉ' || $Row->Cible == 0 || $Row->Cible == '') 
                ? '<span style="color: #dc3545; font-weight: bold;">NON ASSIGNÉ</span>' 
                : $Row->Cible;
            $statut = ($Row->Cible == 'NON ASSIGNÉ' || $Row->Cible == 0 || $Row->Cible == '') 
                ? '<span class="badge-danger">SANS CIBLE</span>' 
                : '<span class="badge-info">OK</span>';
        ?>
            <tr>
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td style="text-align: center;"><?php echo $Row->Division; ?></td>
                <td style="text-align: center;"><?php echo $Row->Classe; ?></td>
                <td style="text-align: center;"><?php echo $Row->Depart; ?></td>
                <td style="text-align: center; font-weight: bold;"><?php echo $cible; ?></td>
                <td style="text-align: center;"><?php echo $statut; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

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
            showCustomNotification('✓ Correction effectuée !');
            setTimeout(() => location.reload(), 1500);
        } else {
            showCustomNotification('Erreur : ' + response.message, 'error');
        }
    }, 'json');
}

// Fonction de notification personnalisée
function showCustomNotification(message, type = 'success') {
    // Créer une div de notification
    const notification = document.createElement('div');
    notification.id = 'custom-notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: bold;
        z-index: 9999;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease-out;
        min-width: 300px;
        text-align: center;
    `;
    
    if (type === 'success') {
        notification.style.backgroundColor = '#28a745';
    } else {
        notification.style.backgroundColor = '#dc3545';
    }
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; margin-left: 10px;">
                ×
            </button>
        </div>
    `;
    
    // Ajouter l'animation CSS
    const style = document.createElement('style');
    if (!document.querySelector('#notification-styles')) {
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Supprimer toute notification existante
    const existing = document.getElementById('custom-notification');
    if (existing) existing.remove();
    
    // Ajouter la nouvelle notification
    document.body.appendChild(notification);
    
    // Supprimer automatiquement après 3 secondes
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// Fonction pour corriger toutes les anomalies en une seule opération
$(document).ready(function() {
    $('#corriger-tout-sql').click(function() {
        if (!confirm('Êtes-vous sûr de vouloir corriger TOUTES les anomalies "Finale Ind." en une seule opération ?\n\n' + 
                     'Cette action va modifier ' + <?php echo $NbAnomalies; ?> + ' inscription(s).\n' +
                     'Cette méthode est plus rapide mais ne montre pas la progression détaillée.')) {
            return;
        }
        
        const bouton = $(this);
        const texteOriginal = bouton.text();
        bouton.text('Correction en cours...').prop('disabled', true);
        
        // Appeler le script de correction en masse
        $.post('Tout-corriger.php', function(response) {
            if (response.success) {
                showCustomNotification('✓ ' + response.corriges + ' anomalie(s) corrigée(s) avec succès !');
                setTimeout(() => location.reload(), 1500);
            } else {
                showCustomNotification('Erreur : ' + response.message, 'error');
                bouton.text(texteOriginal).prop('disabled', false);
            }
        }, 'json').fail(function() {
            showCustomNotification('Erreur réseau. Veuillez réessayer.', 'error');
            bouton.text(texteOriginal).prop('disabled', false);
        });
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>