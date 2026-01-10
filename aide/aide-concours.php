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
 * Page d'aide pour l'organisation des concours
 * Racourcis et procédures pour avant, pendant et après la compétition
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
$PAGE_TITLE = 'Aide Concours - Procédures et Racourcis';
$IncludeJquery = true;

// Récupérer les sessions existantes
$existingSessions = array();

// Utiliser votre requête SQL exacte
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

// Nettoyer et trier les sessions
$existingSessions = array_unique($existingSessions);
sort($existingSessions);

// Si aucune session n'est trouvée, utiliser les sessions par défaut
if (empty($existingSessions)) {
    $existingSessions = array(1, 2); // Sessions par défaut
}

// Déterminer la racine relative en fonction de l'emplacement du fichier
// Si le fichier est dans Modules/Custom/aide/, on remonte de 3 niveaux pour atteindre la racine
$basePath = '../../../';

include('Common/Templates/head.php');
?>

<style>
/* Styles pour l'interface d'aide */
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

.section-before .section-header {
    background-color: #2c5f2d; /* Vert - avant */
}

.section-during .section-header {
    background-color: #0056b3; /* Bleu - pendant */
}

.section-after .section-header {
    background-color: #6c757d; /* Gris - après */
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.task-item:hover {
    background-color: #e9ecef;
    transform: translateX(5px);
}

.task-item-afaire{
    background-color: red;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin-bottom: 10px;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}
.task-link {
    color: #2c5f2d;
    text-decoration: none;
    font-weight: 500;
    flex-grow: 1;
}

.task-link:hover {
    color: #1e3d24;
    text-decoration: underline;
}

.task-icon {
    font-size: 1.2em;
    margin-right: 10px;
}

.task-badge {
    background-color: #6c757d;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    margin-left: 10px;
}

.task-badge-new {
    background-color: #dc3545;
}

.task-badge-important {
    background-color: #ffc107;
    color: #856404;
}

.task-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-small {
    padding: 4px 8px;
    font-size: 0.85em;
    border-radius: 3px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
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

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #117a8b;
}

.save-section {
    background-color: #d4edda;
    border: 2px dashed #c3e6cb;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    text-align: center;
}

.github-section {
    background-color: #f6f8fa;
    border: 2px dashed #d1d5da;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    text-align: center;
}

.save-button, .github-button {
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 5px;
}

.save-button {
    background-color: #28a745;
}

.save-button:hover {
    background-color: #218838;
}

.github-button {
    background-color: #333333;
}

.github-button:hover {
    background-color: #24292e;
}

.github-button-success {
    background-color: #28a745;
}

.github-button-success:hover {
    background-color: #218838;
}

.github-button-info {
    background-color: #0366d6;
}

.github-button-info:hover {
    background-color: #0056b3;
}

/* Styles pour les boutons avec aide */
.quick-link-container {
    display: flex;
    align-items: center;
    gap: 5px;
}

@media (max-width: 768px) {
    .help-section {
        min-width: 100%;
    }
    
    .quick-link-container {
        flex-wrap: wrap;
    }
    
    .task-actions {
        margin-top: 5px;
        width: 100%;
    }
}

/* Styles pour la fenêtre de mise à jour */
.update-status {
    background-color: #e7f3ff;
    border: 1px solid #b3d7ff;
    border-radius: 5px;
    padding: 10px;
    margin: 10px 0;
    font-family: monospace;
    max-height: 200px;
    overflow-y: auto;
}
</style>

<div class="help-container">
    <!-- SECTION AVANT -->
    <div class="help-section section-before">
        <div class="section-header">
            📋 AVANT LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <span class="task-icon">🏁</span>
                <a href="<?php echo $basePath; ?>Tournament/index.php?New=" class="task-link" target="_blank">Créer une nouvelle compétition</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">👥</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/Perso/AddArcher.php?id=0" class="task-link" target="_blank">Ajouter des archers / participants</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">📝</span>
                <a href="<?php echo $basePath; ?>Partecipants/index.php" class="task-link" >List des participants</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">✅</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/Verif/Verification.php" class="task-link" >Vérification complète des inscriptions</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🎯</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/GraphicalView/DragDropPlan.php" class="task-link" >Assignation graphique des cibles</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Partecipants/PrnAlphabetical.php?tf=1" class="task-link" target="_blank">Pour affichage / Liste des Participants par Ordre Alphabétique + Type de Cible</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="<?php echo $basePath; ?>Partecipants/PrnAlphabetical.php?Session=<?php echo $session; ?>&tf=1" 
                       class="btn-small btn-primary" 
                       target="_blank">Départ <?php echo $session; ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
        </ul>
        
        <div class="save-section">
            <p><strong>⚠️ SAUVEGARDE à faire à tout moment</strong></p>
            <button class="save-button" onclick="sauvegarder()">
                💾 Sauvegarder la compétition
            </button>
            <p style="font-size: 12px; color: #666; margin-top: 8px;">
                Sauvegarde la base de données actuelle
            </p>
        </div>
        
        <!-- SECTION GITHUB SIMPLE -->
        <div class="github-section">
            <p><strong>🔄 MISE À JOUR DU ADDON IANSEO</strong></p>
            
            <div style="margin: 15px 0;">
                <button class="github-button github-button-success" onclick="updateAddonSimple()">
                    🔄 Mettre à jour le Addon
                </button>
                
                <a href="https://github.com/loloz3/ianseo-addon" 
                   target="_blank" 
                   class="github-button github-button-info"
                   style="text-decoration: none;">
                    📁 Voir sur GitHub
                </a>
            </div>
            
            <div id="updateStatus" class="update-status" style="display: none;">
                <!-- Le statut de mise à jour apparaîtra ici -->
            </div>
            
            <p style="font-size: 12px; color: #666; margin-top: 8px;">
                Télécharge et installe la dernière version depuis GitHub
            </p>
        </div>
    </div>
    
    <!-- SECTION PENDANT -->
    <div class="help-section section-during">
        <div class="section-header">
            🏹 PENDANT LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <span class="task-icon">💶</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/Greffe/Greffe.php" class="task-link" >Greffe - Gestion des tirs</a>
            </li>

            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="" class="task-link" >Impression des feuilles pour controle du matériel</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="<?php echo $basePath; ?>Partecipants/PrnSession.php?Session=<?php echo $session; ?>&tf=1" 
                       class="btn-small btn-primary" 
                       target="_blank">Départ <?php echo $session; ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
			
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Qualification/PrintScore.php" class="task-link" >Impression des feuilles de marque</a>
                <div class="task-actions">                    
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="<?php echo $basePath; ?>Modules/Custom/aide/PrintScoreAuto.php?session=<?php echo $session; ?>&dist=1" 
                       class="btn-small btn-primary" 
                       target="_blank">D<?php echo $session; ?>-1</a>
                    <a href="<?php echo $basePath; ?>Modules/Custom/aide/PrintScoreAuto.php?session=<?php echo $session; ?>&dist=2" 
                       class="btn-small btn-primary" 
                       target="_blank">D<?php echo $session; ?>-2</a>
                    <?php endforeach; ?>
                </div>
            </li>
            
            <li class="task-item">
                <span class="task-icon">⌨️</span>
                <a href="<?php echo $basePath; ?>Modules/Barcodes/GetScoreBarCode.php" class="task-link" target="_blank">Saisie des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🧮</span>
                <a href="<?php echo $basePath; ?>Qualification/index.php" class="task-link" target="_blank">Mise à jour du classement (à faire pour tout les Départs/Distances)</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Qualification/PrnIndividualAbs.php" class="task-link" target="_blank">Impression des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" target="_blank">Impression autres tirs</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">📱</span>
                <a href="<?php echo $basePath; ?>Qualification/CheckTargetUpdate.php" class="task-link" target="_blank">Contrôles des données</a>
            </li>
			
			<li class="task-item">
                <span class="task-icon">📱</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/ScoreCibles/ScoreCibles.php" class="task-link" target="_blank">Contrôles des données (perso à tester)</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🔄</span>
                <a href="<?php echo $basePath; ?>Tournament/SetCredentials.php?return=Tournament/UploadResults.php" class="task-link" target="_blank">Envoi à IANSEO des résultats (à garder ouvert)</a>
            </li>
        </ul>
    </div>
    
    <!-- SECTION APRES -->
    <div class="help-section section-after">
        <div class="section-header">
            🏆 APRÈS LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <span class="task-icon">🏆️</span>
                <a href="<?php echo $basePath; ?>Qualification/PrnIndividualAbs.php" class="task-link" target="_blank">Impression des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" target="_blank">Impression autres tirs</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">📤</span>
                <a href="<?php echo $basePath; ?>Modules/Sets/FR/exports/" class="task-link" target="_blank">Envoi fichiers à FFTA</a>
            </li>
            
			<li class="task-item">
                <span class="task-icon">🩺</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/test/isk-diagnostic.php" class="task-link" target="_blank">ISK System Diagnostic</a>
            </li>
        </ul>
    </div>
</div>

<script>
function sauvegarder() {
    // Appeler le script d'export via AJAX
    sauvegarderTournamentExport();
}

function sauvegarderTournamentExport() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo $basePath; ?>Tournament/TournamentExport.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                showNotification('✅ Export Tournament terminé avec succès !', 'success');
                window.location.href = '<?php echo $basePath; ?>Tournament/TournamentExport.php?download=true';
            } else {
                showNotification('❌ Erreur lors de l\'export Tournament', 'error');
            }
        }
    };
    
    xhr.send();
}

// FONCTION SIMPLIFIÉE POUR GITHUB
function updateAddonSimple() {
    if (!confirm('Voulez-vous mettre à jour le addon depuis GitHub ?\n\nTous les fichiers seront téléchargés depuis https://github.com/loloz3/ianseo-addon')) {
        return;
    }
    
    // Montrer le statut
    const statusDiv = document.getElementById('updateStatus');
    statusDiv.style.display = 'block';
    statusDiv.innerHTML = '<p>⏳ Début de la mise à jour...</p>';
    
    // Appeler le script PHP
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'github_update.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            statusDiv.innerHTML += '<p>✅ Requête terminée...</p>';
            
            if (xhr.status === 200) {
                // Afficher la réponse
                statusDiv.innerHTML += '<hr><strong>Résultat :</strong><br>' + xhr.responseText;
                
                // Message final
                setTimeout(() => {
                    showNotification('✅ Mise à jour GitHub terminée !', 'success');
                }, 1000);
            } else {
                statusDiv.innerHTML += '<p style="color:red;">❌ Erreur HTTP ' + xhr.status + '</p>';
                showNotification('❌ Erreur lors de la mise à jour', 'error');
            }
        } else if (xhr.readyState === 3) {
            // Mise à jour en temps réel si supporté
            if (xhr.responseText) {
                statusDiv.innerHTML = '<p>🔄 Progression :</p><pre>' + xhr.responseText + '</pre>';
            }
        }
    };
    
    xhr.send('action=update');
}

// Fonction de notification
function showNotification(message, type = 'info') {
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
    
    notification.style.backgroundColor = type === 'success' ? '#28a745' : '#dc3545';
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; margin-left: 10px;">
                ×
            </button>
        </div>
    `;
    
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

// Ajouter les styles d'animation
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
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

// Ouvrir les liens dans un nouvel onglet par défaut
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('.task-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Laisser le navigateur gérer l'ouverture dans un nouvel onglet
        });
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>