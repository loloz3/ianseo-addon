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
 * - Votre Nom - Votre Club - (modif: 2026-01-02)
 * 
 * Page d'aide pour l'organisation des concours par équipes
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');

// Chercher Fun_Various.inc.php dans plusieurs chemins possibles
$possiblePaths = array(
    'Common/Fun_Various.inc.php',
    '../Common/Fun_Various.inc.php',
    dirname(dirname(__FILE__)) . '/Common/Fun_Various.inc.php',
    dirname(__FILE__) . '/../Common/Fun_Various.inc.php'
);

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once($path);
        break;
    }
}

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

$TourId = $_SESSION['TourId'];
$PAGE_TITLE = 'Aide Concours par Équipes - Procédures';
$IncludeJquery = true;

// Récupérer les sessions existantes
$existingSessions = array();

$sql = "SELECT DISTINCT q.QuSession 
        FROM Qualifications q 
        INNER JOIN Entries e ON q.QuId = e.EnId 
        WHERE e.EnTournament = $TourId 
        AND q.QuSession IS NOT NULL 
        AND q.QuSession != ''";

if (function_exists('db_query')) {
    $result = db_query($sql);
    
    if ($result !== false) {
        while ($row = db_fetch_array($result)) {
            $session = $row['QuSession'];
            if (!empty($session) && is_numeric($session)) {
                $existingSessions[] = (int)$session;
            }
        }
        
        if (function_exists('db_free_result')) {
            db_free_result($result);
        }
    }
}

$existingSessions = array_unique($existingSessions);
sort($existingSessions);

if (empty($existingSessions)) {
    $existingSessions = array(1, 2);
}

$basePath = '../../../';

include('Common/Templates/head.php');
?>

<style>
.help-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
}

.help-section {
    flex: 1;
    min-width: 300px;
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-header {
    background-color: #2c5f2d;
    color: white;
    padding: 15px;
    border-radius: 8px 8px 0 0;
    margin: -20px -20px 20px -20px;
    text-align: center;
    font-size: 20px;
    font-weight: bold;
}

.section-avant .section-header {
    background-color: #2c5f2d;
}

.section-qualif .section-header {
    background-color: #0056b3;
}

.section-finales .section-header {
    background-color: #dc3545;
}

.task-list {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.task-item {
    background-color: white;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin-bottom: 10px;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.task-item:hover {
    background-color: #e9ecef;
    transform: translateX(5px);
}

.task-title {
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.task-icon {
    font-size: 1.2em;
}

.task-description {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 10px;
    padding-left: 28px;
}

.task-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding-left: 28px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.85em;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #1e7e34;
}

.btn-warning {
    background-color: #ffc107;
    color: #856404;
}

.btn-warning:hover {
    background-color: #e0a800;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #bd2130;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #117a8b;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.notification {
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
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

@media (max-width: 768px) {
    .help-section {
        min-width: 100%;
    }
    .task-actions {
        flex-direction: column;
    }
}
</style>

<div class="help-container">
    <!-- SECTION AVANT COMPETITION -->
    <div class="help-section section-avant">
        <div class="section-header">
            📋 AVANT LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">📤</span>
                    Envoi à Ianseo du Programme & Plan des Cibles
                </div>
                <div class="task-description">
                    Envoyer le programme complet et le plan des cibles à Ianseo<br>
					 ✅ Listes de cibles<br>
					 ✅ Liste des Participants par Pays/Clubs<br>
					 ✅ Liste des Participants par Ordre Alphabétique<br>
                    Télécharger des fichiers (PDF)<br>
                     ✅ Programme complet<br>
					 ✅ Plan des Cibles
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Tournament/UploadResults.php?QUAL&" class="btn-small btn-primary" target="_blank">📤 Envoi à Ianseo</a>
                </div>
            </li>
        </ul>
    </div>
    
    <!-- SECTION PENDANT LES QUALIFICATIONS -->
    <div class="help-section section-qualif">
        <div class="section-header">
            🏹 PENDANT LES QUALIFICATIONS
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">📺</span>
                    Affichage TV - Qualifications
                </div>
                <div class="task-description">
                    Affichage des qualifications par équipes
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>TV/" class="btn-small btn-primary" target="_blank">📺 Qualifications - Équipes (QualEq)</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">👥</span>
                    Après la 1ère volée
                </div>
                <div class="task-description">
                    Création des équipes après la première volée
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Qualification/index.php" class="btn-small btn-success" target="_blank">🔧 Création des Équipes</a>
                    <a href="<?php echo $basePath; ?>Qualification/PrnTeamAbs.php" class="btn-small btn-info" target="_blank">📊 Résultat Équipes</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">📤</span>
                    Envoi à IANSEO après 1ère volée
                </div>
                <div class="task-description">
                    Envoyer qualifications indiv (tout) / Qualifications Équipes (Décocher 5-8 / 9-12 / 13-16)
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Tournament/UploadResults.php?QUAL&" class="btn-small btn-primary" target="_blank">📤 Envoi à IANSEO</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">✅</span>
                    Fin des Qualifs - Contrôle et validation des scores
                </div>
                <div class="task-description">
                    Contrôler les scores / Lire le séparateur "-" / Distance 1 / Vérifier les feuilles de marque
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Modules/Barcodes/GetScoreBarCode.php" class="btn-small btn-warning" target="_blank">🔍 Contrôle des scores</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">⚖️</span>
                    Vérification des ex-aequo
                </div>
                <div class="task-description">
                    Vérifier les ex-aequo avant les phases finales
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Modules/Sets/FR/Manage/AbsTae.php" class="btn-small btn-danger" target="_blank">⚠️ Vérif ex-aequo Qualifications</a>
                    <a href="<?php echo $basePath; ?>Final/Team/AbsTeam.php" class="btn-small btn-danger" target="_blank">⚠️ Contrôle ex-aequo Finales Équipes</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">🟢🔴⚪</span>
                    Gestion du mode exécution
                </div>
                <div class="task-description">
                    Si le bouton est gris/blanc (mode exécution) => Gestion Classement par moyenne => Classement standard
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Final/RunningEvent.php" class="btn-small btn-secondary" target="_blank">📊 Gestion Classement par moyenne</a>
                </div>
            </li>
        </ul>
    </div>
    
    <!-- SECTION FINALES PAR ÉQUIPES -->
    <div class="help-section section-finales">
        <div class="section-header">
            🏆 FINALES PAR ÉQUIPES
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">🖨️</span>
                    Impression des feuilles de marque des duels
                </div>
                <div class="task-description">
                    Impression des feuilles de marque pour les matchs par équipe
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Final/Team/PrintScore.php" class="btn-small btn-primary" target="_blank">📄 Feuilles de marque</a>
                    <a href="<?php echo $basePath; ?>Final/Team/PDFScore.php?Barcode=1&QRCode%5B%5D=ISK-NG&Submit=Impression+des+feuilles+de+marque" class="btn-small btn-success" target="_blank">📑 PDF + QR Code</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">🖨️</span>
                    Impression des Brackets
                </div>
                <div class="task-description">
                    Tableau des matchs avec numéros de cibles et horaires
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Final/Team/PrnBracket.php?ShowTargetNo=1&ShowSchedule=1" class="btn-small btn-info" target="_blank">🏆 Brackets</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">📺</span>
                    Affichage TV pendant les duels
                </div>
                <div class="task-description">
                    Nom tour 1 → Matchs par équipe<br>
                    Cocher DRE Femme CO / DRE Homme CO puis ½ et 1/8 (qui vont être en même temps)
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>TV/" class="btn-small btn-danger" target="_blank">📺 Affichage TV Duels</a>
                </div>
            </li>
            
            <li class="task-item">
                <div class="task-title">
                    <span class="task-icon">✅</span>
                    Valider les feuilles pour le prochain tour
                </div>
                <div class="task-description">
                    Lire le séparateur "-" / Aller
                </div>
                <div class="task-actions">
                    <a href="<?php echo $basePath; ?>Modules/Barcodes/GetFinScoreBarCode.php" class="btn-small btn-success" target="_blank">✅ Validation feuilles</a>
                </div>
            </li>
        </ul>
    </div>
</div>

<script>
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = 'notification';
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        info: '#17a2b8',
        warning: '#ffc107'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    if (type === 'warning') notification.style.color = '#856404';
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: transparent; border: none; color: ${type === 'warning' ? '#856404' : 'white'}; font-size: 18px; cursor: pointer; margin-left: 10px;">
                ×
            </button>
        </div>
    `;
    
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 4000);
}

// Fonction pour copier un texte dans le presse-papier
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('✅ Texte copié : ' + text, 'success');
    }).catch(() => {
        showNotification('❌ Impossible de copier', 'error');
    });
}

// Ouvrir tous les liens dans un nouvel onglet par défaut
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('.task-actions a');
    links.forEach(link => {
        if (!link.getAttribute('target')) {
            link.setAttribute('target', '_blank');
        }
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>