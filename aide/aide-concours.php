
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

.save-button {
    background-color: #28a745;
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
}

.save-button:hover {
    background-color: #218838;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c5f2d;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

.info-box {
    background-color: #e7f3ff;
    border: 1px solid #b3d7ff;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}

.info-box h4 {
    color: #0056b3;
    margin-top: 0;
}

/* Styles pour les boutons avec aide */
.quick-link-container {
    display: flex;
    align-items: center;
    gap: 5px;
}

.help-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background-color: #6c757d;
    color: white;
    border-radius: 50%;
    font-size: 12px;
    font-weight: bold;
    text-decoration: none;
    margin-left: 5px;
    transition: all 0.2s ease;
}

.help-link:hover {
    background-color: #545b62;
    transform: scale(1.1);
}

.help-link-primary {
    background-color: #0056b3;
}

.help-link-primary:hover {
    background-color: #003d7a;
}

.help-link-success {
    background-color: #218838;
}

.help-link-success:hover {
    background-color: #1c7430;
}

.help-link-warning {
    background-color: #e0a800;
    color: #856404;
}

.help-link-warning:hover {
    background-color: #c69500;
}

.help-link-info {
    background-color: #117a8b;
}

.help-link-info:hover {
    background-color: #0e5a6b;
}

@media (max-width: 768px) {
    .help-section {
        min-width: 100%;
    }
    
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .quick-link-container {
        flex-wrap: wrap;
    }
    
    .task-actions {
        margin-top: 5px;
        width: 100%;
    }
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
                <a href="/Tournament/index.php?New=" class="task-link" target="_blank">Créer une nouvelle compétition</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">👥</span>
                <a href="/Modules/Custom/Perso/AddArcher.php?id=0" class="task-link" target="_blank">Ajouter des archers / participants</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">📝</span>
                <a href="/Partecipants/index.php" class="task-link" >List des participants</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">✅</span>
                <a href="/Modules/Custom/Verif/Verification.php" class="task-link" >Vérification complète des inscriptions</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🎯</span>
                <a href="/Modules/Custom/GraphicalView/DragDropPlan.php" class="task-link" >Assignation graphique des cibles</a>
            </li>
            
           
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="/Partecipants/PrnAlphabetical.php?tf=1" class="task-link" target="_blank">Pour affichage / Liste des Participants par Ordre Alphabétique
+ Type de Cible</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="/Partecipants/PrnAlphabetical.php?Session=<?php echo $session; ?>&tf=1" 
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
    </div>
    
    <!-- SECTION PENDANT -->
    <div class="help-section section-during">
        <div class="section-header">
            🏹 PENDANT LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <span class="task-icon">💶</span>
                <a href="/Modules/Custom/Greffe/Greffe.php" class="task-link" >Greffe - Gestion des tirs</a>
            </li>

            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="" class="task-link" >Impression des feuilles pour controle du matériel</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="/Partecipants/PrnSession.php?Session=<?php echo $session; ?>&tf=1" 
                       class="btn-small btn-primary" 
                       target="_blank">Départ <?php echo $session; ?></a>
                    <?php endforeach; ?>
                </div>
            </li>
			
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="/Qualification/PrintScore.php" class="task-link" >Impression des feuilles de marque</a>
                <div class="task-actions">                    
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="/Modules/Custom/aide/PrintScoreAuto.php?session=<?php echo $session; ?>&dist=1" 
                       class="btn-small btn-primary" 
                       target="_blank">D<?php echo $session; ?>-1</a>
                    <a href="/Modules/Custom/aide/PrintScoreAuto.php?session=<?php echo $session; ?>&dist=2" 
                       class="btn-small btn-primary" 
                       target="_blank">D<?php echo $session; ?>-2</a>
                    <?php endforeach; ?>
                </div>
            </li>
            
            <li class="task-item">
                <span class="task-icon">⌨️</span>
                <a href="/Modules/Barcodes/GetScoreBarCode.php" class="task-link" target="_blank">Saisie des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🧮</span>
                <a href="/Qualification/index.php" class="task-link" target="_blank">Mise à jour du classement (à faire pour tout les Départs/Distances)</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="/Qualification/PrnIndividualAbs.php" class="task-link" target="_blank">Impression des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="/Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" target="_blank">Impression autres tirs</a>
            </li>
            
            <li class="task-item-afaire">
                <span class="task-icon">📱</span>
                <a href="Mobile/ViewScores.php" class="task-link" target="_blank">Vision remontée mobile (téléphones)</a>
                <div class="task-actions">
                    <a href="Mobile/ViewScores.php" class="btn-small btn-info" target="_blank">à faire</a>
                </div>
            </li>
            
            <li class="task-item-afaire">
                <span class="task-icon">🔄</span>
                <a href="Export/IanseoExport.php" class="task-link" target="_blank">Envoi à IANSEO des résultats</a>
				<div class="task-actions">
                    <a href="Mobile/ViewScores.php" class="btn-small btn-info" target="_blank">à faire</a>
                </div>
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
                <a href="/Qualification/PrnIndividualAbs.php" class="task-link" target="_blank">Impression des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="/Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" target="_blank">Impression autres tirs</a>
            </li>
            
            <li class="task-item-afaire">
                <span class="task-icon">📤</span>
                <a href="Export/FFTAExport.php" class="task-link" target="_blank">Envoi fichiers à FFTA</a>
                <div class="task-actions">
                    <a href="Mobile/ViewScores.php" class="btn-small btn-info" target="_blank">à faire</a>
                </div>
            </li>
            
  

        </ul>
        
    </div>
</div>


<script>
function sauvegarder() {
    showNotification('Sauvegarde des résultats en cours...', 'info');
    
    // Appeler le script d'export via AJAX
    sauvegarderTournamentExport();
}

function sauvegarderTournamentExport() {
    // Créer une requête AJAX vers TournamentExport.php
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '/Tournament/TournamentExport.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                // Succès
                showNotification('✅ Export Tournament terminé avec succès !', 'success');
                console.log('Réponse du serveur:', xhr.responseText);
                
                // Optionnel: télécharger le fichier généré si le script le permet
                window.location.href = '/Tournament/TournamentExport.php?download=true';
            } else {
                // Erreur
                showNotification('❌ Erreur lors de l\'export Tournament', 'error');
                console.error('Erreur AJAX:', xhr.status, xhr.statusText);
            }
        }
    };
    
    xhr.onerror = function() {
        showNotification('❌ Erreur réseau lors de l\'export', 'error');
    };
    
    xhr.send();
}

function showNotification(message, type = 'info') {
    // Utiliser la même fonction de notification que dans Verification.php
    if (typeof showCustomNotification === 'function') {
        showCustomNotification(message, type === 'success' ? 'success' : 'error');
    } else {
        alert(message);
    }
}

// Fonction de notification personnalisée (identique à Verification.php)
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

// Ouvrir les liens dans un nouvel onglet par défaut
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('.task-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Laisser le navigateur gérer l'ouverture dans un nouvel onglet
            // car target="_blank" est déjà dans le HTML
        });
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>
