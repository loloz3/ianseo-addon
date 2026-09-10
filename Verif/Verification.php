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
 * - Laurent Petroff - Les Archers de Perols - (modif: 2026-01-30)
 * - Guillaume Roques (modif: 2026-09-05)
 * 
 * Dernière modification: 2026-09-05 par Guillaume Roques
 * Intégration du module au Set FR de Ianseo
 * Affichage d'un warning dans le menu si il y a une anomalie
 * Réorganisation technique et amélioration du module
 * Cohérence graphique entre chaque type d'anomalie
 * Vérification de l'assignation des cibles uniquement pour les athlètes
 *
 * 
 * Règle :
 * - Pour chaque combinaison (Division, Classe) :
 *   - 1ère inscription (session la plus basse) : EnIndFEvent doit être à 1 (Oui)
 *   - Inscriptions suivantes dans la même (Division, Classe) : EnIndFEvent doit être à 0 (Non)
 * - Vérification si archer en Doublon
 * - Vérification que tous les archers ont une arme (Div.), une catégorie (Age Cl.) et une classe (Cl.)
 * - Vérification si des archers ne sont pas assignés à une cible.
 */

require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_Various.inc.php');
require_once('Verification.class.php');

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

$PAGE_TITLE = 'Vérification des participants';
$IncludeJquery = true;

$JS_SCRIPT=array(
    phpVars2js(array(
        'StrAreYouSure'=>get_text('MsgAreYouSure')
    )),
    '<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Common/js/Fun_JS.inc.js"></script>',
    '<script type="text/javascript" src="'.$CFG->ROOT_DIR.'Partecipants/Fun_index_edit.js"></script>',
    '<script type="text/javascript">
        function PopEdit(id,opts)
        {
            var other=(opts!==null ? \'&\'+opts : \'\');
            OpenPopup(\''.$CFG->ROOT_DIR.'Partecipants/PopEdit.php?id=\'+id+other, \'PopEdit\', 910,700);
        }
    </script>'
);

include('Common/Templates/head.php');
?>

<style>
.anomaly-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.anomaly-table th {
    background-color: #ffc107;
    color: white;
    padding: 8px;
    text-align: left;
    font-weight: bold;
}
.anomaly-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.anomaly-table tr:hover {
    background-color: #fff3cd;
}
.anomaly-table tr.premier-depart {
    background-color: #fff3cd;
}
.anomaly-table tr.depart-supplementaire {
    background-color: #fff;
}
.anomaly-table tr.inscription-unique {
    background-color: #ffe4b5;
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
.alert-warning a {
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
    text-decoration: none;
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
.badge-warning {
    background-color: #ffc107;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
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
.badge-info {
    background-color: #17a2b8;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
}
.badge-cible-manquante {
    background-color: #dc3545;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}
.badge-lettre-manquante {
    background-color: #ff9800;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}
.badge-cible-dupliquee {
    background-color: #ffc107;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}
div.verification hr {
    margin-top: 30px;
}
</style>

<table class="Tabella">
  <tr>
    <th class="Title">
      Vérification des participants
    </th>
  </tr>
</table>
<div class="verification">

<?php 
$Verification = new Verification();

// SECTION 1: Vérification Finale Individuelle (PAR DIVISION + CLASSE)
if ($Verification->getNbAnomalies() == 0):
?>
    <div class="section-title-success">
        ✓ Aucune anomalie détectée dans la configuration "Épreuve Ind."
    </div>
<?php else: ?>
    <div class="section-title-warning">
        ⚠️ <?php echo $Verification->getNbAnomalies(); ?> anomalie(s) détectée(s) dans la configuration "Épreuve Ind."
    </div>

    <div class="alert-warning">
        <h2>Rappel de la règle pour "Épreuve Ind." (PAR DIVISION + CLASSE) :</h2>
        <ul>
            <li><strong>Pour chaque combinaison (Division + Classe) :</strong></li>
            <ul>
                <li>La <strong>première inscription</strong> (session la plus basse) dans cette combinaison → "Épreuve Ind." doit être à <strong>OUI</strong></li>
                <li>Les <strong>inscriptions suivantes</strong> dans la <strong>MÊME combinaison (Division + Classe)</strong> → "Épreuve Ind." doit être à <strong>NON</strong></li>
            </ul>
            <li><strong>Note importante :</strong></li>
            <ul>
                <li>
                    Un archer peut avoir "Épreuve Ind." à OUI dans plusieurs combinaisons (Division, Classe) différentes.<br>
                    <strong>Exemple :</strong> Un archer qui tire en (CL, S2M) au départ 2 et en (CL, S2H) au départ 3 doit avoir "Épreuve Ind." à OUI pour les DEUX, car ce sont des combinaisons différentes.
                </li>
                <li>Un archer peut avoir "Épreuve Ind." à NON sur son premier départ si il ne participe pas à l'épreuve (ex: un archer hors département sur un championnat départemental)</li>
            </ul>
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
                <th>Âge Cl.</th>
                <th>Départ</th>
                <th>Type d'inscription</th>
                <th>Finale Ind.</th>
                <th>Problème</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $previousCode = '';
        $previousDivClasse = '';
        $res = $Verification->getAnomalies();
        while ($Row = safe_fetch($res)):
            $isPremierDepart = ($Row->Depart == $Row->PremierDepartDivClasse);
            $isInscriptionUnique = ($Row->NbInscriptionsDivClasse == 1);
            $currentDivClasse = $Row->Division . '|' . $Row->Classe;

            if ($isInscriptionUnique) {
                $rowClass = 'inscription-unique';
            } else {
                $rowClass = $isPremierDepart ? 'premier-depart' : 'depart-supplementaire';
            }

            $finaleActuelle = ($Row->FinaleInd == 1) ? '<span style="color: green; font-weight: bold;">OUI</span>' : '<span style="color: red; font-weight: bold;">NON</span>';

            // Ligne de séparation entre archers différents
            if ($previousCode != '' && $previousCode != $Row->Licence):
        ?>
            <tr class="archer-group">
                <td colspan="12" style="height: 5px;"></td>
            </tr>
        <?php
            endif;
            $previousCode = $Row->Licence;
            $previousDivClasse = $currentDivClasse;
        ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td><strong><?php echo $Row->Division; ?></strong></td>
                <td><strong><?php echo $Row->Classe; ?></strong></td>
                <td><?php echo $Row->AgeClasse; ?></td>
                <td><strong><?php echo $Row->Depart; ?></strong></td>
                <td>
                    <?php if ($isInscriptionUnique): ?>
                        <span class="badge-unique">Unique (<?php echo $Row->Division; ?>/<?php echo $Row->Classe; ?>)</span>
                    <?php else: ?>
                        <span class="badge-multiple">
                            <?php echo $isPremierDepart ? '1er départ' : ($Row->Depart . 'ème départ'); ?> en <?php echo $Row->Division; ?>/<?php echo $Row->Classe; ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td><?php echo $finaleActuelle; ?></td>
                <td style="font-weight: bold; color: #c82333;"><?php echo $Row->Probleme; ?></td>
                <td>
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
            ⚡ Corriger toutes les anomalies Épreuve Ind. (<?php echo $Verification->getNbAnomalies(); ?>)
        </button>
        <p style="font-size: 12px; color: #666; margin-top: 5px;">
            (Cette méthode corrige automatiquement toutes les anomalies "Épreuve Ind." en une seule opération)
        </p>
    </div>
<?php endif; ?>
<hr>
<?php 
// SECTION 2: Vérification des champs obligatoires
if ($Verification->getNbObligatoires() == 0):
?>
    <div class="section-title-success">
        ✓ Tous les archers ont bien une arme (Division), une catégorie d'âge (Age Cl.) et une classe (Cl.)
    </div>
<?php else: ?>
    <div class="section-title-warning">
        ⚠️ <?php echo $Verification->getNbObligatoires(); ?> archer(s) avec des champs obligatoires manquants
    </div>
    
    <div class="alert-warning">
        <h3>Champs obligatoires manquants</h3>
        <p>Les champs suivants sont obligatoires pour chaque inscription :</p>
        <ul>
            <li><strong>Division (Arme)</strong> : Arc classique, arc à poulies, arc nu, arc droit, etc.</li>
            <li><strong>Age Cl. (Catégorie d'âge)</strong> : U13, U15, U21, S1, S3, etc.</li>
            <li><strong>Classe</strong> : Classe de l'archer dans sa division et catégorie d'âge</li>
        </ul>

        <p style="margin-top: 10px; font-style: italic;">
            <strong>Action requise :</strong> Corrigez les erreurs dans <a href="<?= $CFG->ROOT_DIR . 'Partecipants/index.php' ?>">l'interface de gestion des participants</a>, ou en faisant un double clic sur la ligne à corriger.
        </p>
    </div>

    <table class="anomaly-table">
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
        $res = $Verification->getObligatoires();
        while ($Row = safe_fetch($res)):
            $division = !empty($Row->Division) ? $Row->Division : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
            $ageClasse = !empty($Row->AgeClasse) ? $Row->AgeClasse : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
            $classe = !empty($Row->Classe) ? $Row->Classe : '<span style="color: #dc3545; font-weight: bold;">MANQUANT</span>';
        ?>
            <tr ondblclick="PopEdit(<?= ($Row->EnId !== null ? $Row->EnId : 0) ?>);">
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td><?php echo $Row->Depart; ?></td>
                <td><?php echo $division; ?></td>
                <td><?php echo $ageClasse; ?></td>
                <td><?php echo $classe; ?></td>
                <td style="font-weight: bold; color: #856404;">
                    <span class="badge-warning"><?php echo $Row->Probleme; ?></span>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>
<hr>
<?php
// SECTION 3: Doublons dans un même départ
if ($Verification->getNbDoublons() > 0):
?>
    <div class="section-title-warning">
        ⚠️ ALERTE : <?php echo $Verification->getNbDoublons(); ?> cas d'archer(s) en double dans un même départ
    </div>

    <div class="alert-warning">
        <p>Les archers suivants sont inscrits plusieurs fois dans le <strong>même</strong> départ. Ceci est anormal et nécessite une correction manuelle :</p>

        <p style="margin-top: 10px; font-style: italic;">
            <strong>Action requise :</strong> Vous devez supprimer manuellement les doublons dans <a href="<?= $CFG->ROOT_DIR . 'Partecipants/index.php' ?>">l'interface de gestion des participants</a>.
            Un archer ne peut être inscrit qu'une seule fois par départ.
        </p>
    </div>

    <table class="anomaly-table">
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
        <?php while ($Doublon = safe_fetch($Verification->getDoublons())): ?>
            <tr>
                <td><strong><?php echo $Doublon->Licence; ?></strong></td>
                <td><?php echo $Doublon->Prenom; ?></td>
                <td><?php echo $Doublon->Nom; ?></td>
                <td><strong><?php echo $Doublon->Depart; ?></strong></td>
                <td>
                    <span class="badge-warning"><?php echo $Doublon->NbDoublons; ?> inscriptions</span>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

<?php else: ?>
    <div class="section-title-success">
        ✓ Aucun doublon détecté dans les départs
    </div>
<?php endif; ?>
<hr>
<?php
// SECTION 4: Vérification CORRIGÉE des assignations de cibles
if ($Verification->getNbCibles() == 0):
?>
    <div class="section-title-success">
        ✓ Tous les archers sont correctement assignés à une cible
    </div>
<?php else: ?>
    <div class="section-title-warning">
        ⚠️ <?php echo $Verification->getNbCibles(); ?> archer(s) avec problème d'assignation de cible
    </div>
    
    <div class="alert-warning">
        <h3>Problèmes d'assignation de cible</h3>
        <p>Les archers suivants ont des problèmes d'assignation de cible :</p>
        <ul>
            <li><strong>Cible manquante</strong> : Le numéro de cible (QuTarget) est vide ou à 0</li>
            <li><strong>Lettre manquante</strong> : La lettre de cible (QuLetter) est vide</li>
        </ul>
        <p>Une cible complète doit avoir un numéro ET une lettre (ex: "18 C", "3 A").</p>

        <p style="margin-top: 10px; font-style: italic;">
            <strong>Action requise :</strong> Corrrigez les erreurs dans <a href="<?= $CFG->ROOT_DIR . 'Partecipants/index.php' ?> ">l'interface de gestion des participants</a>, ou en faisant un double clic sur la ligne à corriger.
        </p>

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
                <th>Départ</th>
                <th>Cible assignée</th>
                <th>Problème</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $res = $Verification->getCibles();
        while ($Row = safe_fetch($res)):
            $cibleManquante = empty($Row->CibleComplete) || $Row->Cible == 'NON ASSIGNÉ';
            $lettreManquante = $Row->Cible == 'SANS LETTRE';
            
            if ($cibleManquante) {
                $affichageCible = '<span style="color: #dc3545; font-weight: bold;">NON ASSIGNÉ</span>';
                $probleme = 'Cible manquante';
                $badgeClass = 'badge-cible-manquante';
            } elseif ($lettreManquante) {
                $affichageCible = '<span style="color: #ff9800; font-weight: bold;">' . $Row->CibleDetail . '</span>';
                $probleme = 'Lettre manquante';
                $badgeClass = 'badge-lettre-manquante';
            } else {
                $affichageCible = '<span style="font-weight: bold;">' . $Row->CibleDetail . '</span>';
                $probleme = 'OK';
                $badgeClass = 'badge-info';
            }
            
            if ($cibleManquante) {
                $statut = '<span class="badge-cible-manquante">SANS CIBLE</span>';
            } elseif ($lettreManquante) {
                $statut = '<span class="badge-lettre-manquante">SANS LETTRE</span>';
            } else {
                $statut = '<span class="badge-info">OK</span>';
            }
        ?>
            <tr ondblclick="PopEdit(<?= ($Row->EnId !== null ? $Row->EnId : 0) ?>);">
                <td><strong><?php echo $Row->Licence; ?></strong></td>
                <td><?php echo $Row->Prenom; ?></td>
                <td><?php echo $Row->Nom; ?></td>
                <td><?php echo $Row->Pays; ?></td>
                <td><?php echo $Row->Division; ?></td>
                <td><?php echo $Row->Classe; ?></td>
                <td><?php echo $Row->Depart; ?></td>
                <td style="font-weight: bold;"><?php echo $affichageCible; ?></td>
                <td>
                    <span class="<?php echo $badgeClass; ?>"><?php echo $probleme; ?></span>
                </td>
                <td><?php echo $statut; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    
<?php endif; ?>
<hr>
<?php
// SECTION 5: Cibles avec plusieurs archers dans un même départ
if ($Verification->getNbCiblesDupliquees() == 0):
?>
    <div class="section-title-success">
        ✓ Aucune cible n'a plusieurs archers assignés dans un même départ
    </div>
<?php else: ?>
    <div class="section-title-warning">
        ⚠️ ALERTE : <?php echo $Verification->getNbCiblesDupliquees(); ?> cible(s) avec plusieurs archers dans un même départ
    </div>
    
    <div class="alert-warning">
        <p>Les cibles suivantes ont plusieurs archers assignés dans le <strong>même</strong> départ. Une cible ne peut avoir qu'un seul archer par départ :</p>

        <p style="margin-top: 10px; font-style: italic;">
            <strong>Action requise :</strong> Cette anomalie est critique et doit être corrigée avant le tournoi. 
            Vous devez réassigner manuellement les archers à des cibles différentes dans l'interface de gestion des cibles (en cliquant sur le bouton <strong>corriger</strong>).<br>
            Une cible ne peut avoir qu'un seul archer par départ.
        </p>
    </div>

    <table class="anomaly-table">
        <thead>
            <tr>
                <th>Cible</th>
                <th>Départ</th>
                <th>Nombre d'archers</th>
                <th>Archers assignés</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $res = $Verification->getCiblesDupliquees(); while ($CibleDupliquee = safe_fetch($res)): ?>
            <tr>
                <td>
                    <strong><?php echo $CibleDupliquee->Cible; ?></strong>
                </td>
                <td>
                    <strong><?php echo $CibleDupliquee->Depart; ?></strong>
                </td>
                <td>
                    <span class="badge-cible-dupliquee"><?php echo $CibleDupliquee->NbArchers; ?> archers</span>
                </td>
                <td style="padding: 10px;">
                    <?php echo $CibleDupliquee->Archers; ?>
                </td>
                <td>
                    <a class="fix-button" href="<?= $CFG->ROOT_DIR . 'Partecipants/SetTarget_default.php?Ses='. ($CibleDupliquee->Depart ?? '*') ?>">
                        Corriger
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<script>
function corrigerArcher(enId, nouvelleValeur) {
    if (!confirm('Voulez-vous vraiment corriger cet archer ?\n\n' +
                 'Nouvelle valeur "Épreuve Ind." : ' + (nouvelleValeur == 1 ? 'OUI' : 'NON') + '\n' +
                 '(La vérification est faite par Division + Classe)')) {
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

function showCustomNotification(message, type = 'success') {
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
    
    const existing = document.getElementById('custom-notification');
    if (existing) existing.remove();
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

$(document).ready(function() {
    $('#corriger-tout-sql').click(function() {
        if (!confirm('Êtes-vous sûr de vouloir corriger TOUTES les anomalies "Épreuve Ind." en une seule opération ?\n\n' +
                     'Cette action va modifier ' + <?php echo $Verification->getNbAnomalies(); ?> + ' inscription(s).\n' +
                     'Attention : La correction est basée sur la règle (Division + Classe).\n' +
                     'Un archer peut avoir "Finale Ind." = OUI pour plusieurs combinaisons différentes.')) {
            return;
        }
        
        const bouton = $(this);
        const texteOriginal = bouton.text();
        bouton.text('Correction en cours...').prop('disabled', true);
        
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
