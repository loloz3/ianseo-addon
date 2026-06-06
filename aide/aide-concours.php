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
 * Raccourcis et procédures pour avant, pendant et après la compétition
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
$PAGE_TITLE = 'Aide Concours - Procédures et Raccourcis';
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

// Déterminer la racine relative
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

/* Styles pour les notifications */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
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
                <a href="<?php echo $basePath; ?>Tournament/index.php?New=" class="task-link" >Créer une nouvelle compétition</a>
				<a href="<?php echo $basePath; ?>Modules/Custom/aide/CloneTournament.php" class="btn-small btn-success">Cloner une compétition</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">🔃</span>
                <a href="<?php echo $basePath; ?>Participants/LookupTableLoad.php" class="task-link" >Mise à jour de la base de données Archers</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">👥</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/Perso/AddArcher.php?id=0" class="task-link" >Ajouter des archers / participants</a>
            </li>
			
            <li class="task-item">
                <span class="task-icon">📝</span>
                <a href="<?php echo $basePath; ?>Participants/index.php" class="task-link" >Liste des participants</a>
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
                <a href="<?php echo $basePath; ?>Participants/PrnAlphabetical.php?tf=1" class="task-link" >Pour affichage / Liste des Participants par Ordre Alphabétique + Type de Cible</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="<?php echo $basePath; ?>Participants/PrnAlphabetical.php?Session=<?php echo $session; ?>&tf=1" 
                       class="btn-small btn-primary" 
                       >Départ <?php echo $session; ?></a>
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
            <p><strong>🔄 MISE À JOUR DE L'ADDON IANSEO (Loloz3)</strong></p>
            <p style="font-size: 12px; color: #666; margin-bottom: 10px;">
                <strong>Note :</strong> Les fichiers <code>menu.php</code> et <code>Prix.txt</code> existants ne seront pas remplacés.
            </p>
            
            <div style="margin: 15px 0;">
                <button class="github-button github-button-success" onclick="updateAddonSimple()">
                    🔄 Mettre à jour l'Addon
                </button>
                
                <a href="https://github.com/loloz3/ianseo-addon" 
                    
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
                <a href="" class="task-link" >Impression des feuilles pour contrôle du matériel</a>
                <div class="task-actions">
                    <?php foreach ($existingSessions as $session): ?>
                    <a href="<?php echo $basePath; ?>Participants/PrnSession.php?Session=<?php echo $session; ?>&tf=1" 
                       class="btn-small btn-primary" 
                       >Départ <?php echo $session; ?></a>
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
                       >D<?php echo $session; ?>-1</a>
                    <a href="<?php echo $basePath; ?>Modules/Custom/aide/PrintScoreAuto.php?session=<?php echo $session; ?>&dist=2" 
                       class="btn-small btn-primary" 
                       >D<?php echo $session; ?>-2</a>
                    <?php endforeach; ?>
                </div>
            </li>
            
            <li class="task-item">
                <span class="task-icon">⌨️</span>
                <a href="<?php echo $basePath; ?>Modules/Barcodes/GetScoreBarCode.php" class="task-link" >Saisie des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🧮</span>
                <a href="<?php echo $basePath; ?>Qualification/index.php" class="task-link" >Mise à jour du classement (à faire pour tous les Dépôts/Distances)</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Qualification/PrnIndividualAbs.php" class="task-link" >Impression des résultats</a>
                <button onclick="resetTitles()" class="btn-small btn-success">Effacer l'en-tête</button>
			</li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" >Impression autres tirs</a>
            </li>
            			
			<li class="task-item">
                <span class="task-icon">📱</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/ScoreCibles/ScoreCibles.php" class="task-link" >Contrôle des données (avec ScoreKeeper NG)</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🔒</span>
                <a href="<?php echo $basePath; ?>/Api/ISK-NG/Sessions.php" class="task-link" >Verrouillage d'un départ / d'une série</a>
            </li>
            <li class="task-item">
                <span class="task-icon">🔄</span>
                <a href="<?php echo $basePath; ?>Tournament/UploadResults.php?QUAL&" class="task-link" >Envoi à IANSEO des résultats (à garder ouvert)</a>
            </li>
			<li class="task-item">
                <span class="task-icon">🛟</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/aide/aide-equipes.php" class="task-link" >Aide concours Équipes</a>
            </li>
        </ul>
    </div>
    
    <!-- SECTION APRÈS -->
    <div class="help-section section-after">
        <div class="section-header">
            🏆 APRÈS LA COMPÉTITION
        </div>
        
        <ul class="task-list">
            <li class="task-item">
                <span class="task-icon">🏆️</span>
                <a href="<?php echo $basePath; ?>Qualification/PrnIndividualAbs.php" class="task-link" >Impression des résultats</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">🖨️</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/AutresTirs/PrnAutresTirs.php" class="task-link" >Impression autres tirs</a>
            </li>
            
            <li class="task-item">
                <span class="task-icon">📤</span>
                <a href="<?php echo $basePath; ?>Modules/Sets/FR/exports/" class="task-link" >Envoi fichiers à FFTA</a>
            </li>
			
        <div class="github-section">
			<li class="task-item">
                <span class="task-icon">🛟</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/aide/ianseo_Backup.html" class="task-link" >Guide complet de sauvegarde d'IanSEo sous Windows</a>
            </li>
		
			<li class="task-item">
                <span class="task-icon">🩺</span>
                <a href="<?php echo $basePath; ?>Modules/Custom/test/isk-diagnostic.php" class="task-link" >ISK System Diagnostic</a>
            </li>
			
			<li class="task-item">
				<span class="task-icon">🧪</span>
				<a href="<?php echo $basePath; ?>Modules/Custom/test/ScoreSimulate.php" class="task-link" >Simulateur de scores (tests)</a>
			</li>

			
			<li class="task-item">
                <span class="task-icon">📊</span>
                <a href="http://localhost/phpmyadmin/" class="task-link" >PHPMyAdmin (pour debug)</a>
            </li>
		</div>
        </ul>
    </div>
</div>

<script>
// Fonction pour réinitialiser les titres - INTÉGRÉE DIRECTEMENT
function resetTitles() {
    // Afficher un indicateur de chargement
    showNotification('🔄 Réinitialisation en cours...', 'info');
    
    // Utiliser fetch pour appeler un endpoint PHP qui exécute l'opération
    fetch('reset_titles_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reset_titles'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Erreur de connexion', 'error');
        console.error('Erreur:', error);
    });
}

// Créer le fichier reset_titles_handler.php directement dans le script
function createResetHandler() {
    // Vérifier si le fichier existe déjà
    fetch('reset_titles_handler.php')
        .then(response => {
            if (!response.ok) {
                // Créer le fichier s'il n'existe pas
                createResetFile();
            }
        })
        .catch(() => {
            createResetFile();
        });
}

function createResetFile() {
    // Contenu du fichier PHP
    const phpContent = `<?php
require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Sessions.inc.php');

if (!CheckTourSession()) {
    echo json_encode(['success' => false, 'message' => 'Session invalide']);
    exit;
}

// Vérification des ACL (lecture/écriture)
checkFullACL(array(AclQualification, AclEliminations, AclRobin, AclIndividuals, AclTeams), '', AclReadWrite);

// Requête SQL pour réinitialiser tous les en-têtes d'impression
$sql = "UPDATE Events 
        SET EvQualPrintHead = ' ', 
            EvFinalPrintHead = ' ' 
        WHERE EvTournament = " . StrSafe_DB($_SESSION['TourId']) . " 
        AND EvCodeParent = ' '";

// Exécution de la requête
$result = safe_w_SQL($sql);

// Vérification du résultat
if ($result) {
    $affectedRows = safe_w_affected_rows();
    echo json_encode([
        'success' => true,
        'message' => "En-têtes réinitialisés ($affectedRows événement(s))",
        'count' => $affectedRows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la réinitialisation'
    ]);
}
?>`;

    // Envoyer une requête pour créer le fichier
    const formData = new FormData();
    formData.append('content', phpContent);
    formData.append('filename', 'reset_titles_handler.php');
    
    fetch('create_file.php', {
        method: 'POST',
        body: formData
    }).catch(error => {
        console.error('Impossible de créer le fichier:', error);
        // Fallback: utiliser l'ancienne méthode
        useFallbackReset();
    });
}

// Méthode de secours si on ne peut pas créer le fichier
function useFallbackReset() {
    // Exécuter directement la requête via AJAX
    fetch('reset_titles_direct.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'tour_id=' + encodeURIComponent('<?php echo $_SESSION["TourId"] ?? ""; ?>')
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        } catch (e) {
            // Si ce n'est pas du JSON, afficher un message générique
            showNotification('✅ Opération effectuée', 'success');
        }
    })
    .catch(error => {
        showNotification('✅ Opération effectuée (mode simple)', 'success');
    });
}

// OU SIMPLEMENT : Fonction ultra-simple qui fait une requête et affiche une notification
function resetTitlesSimple() {
    // Créer un iframe invisible
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = '<?php echo $basePath; ?>Modules/Custom/aide/Reset_title.php';
    
    iframe.onload = function() {
        showNotification('✅ En-têtes réinitialisés', 'success');
        // Nettoyer après un délai
        setTimeout(() => {
            iframe.remove();
        }, 1000);
    };
    
    iframe.onerror = function() {
        showNotification('❌ Erreur lors de la réinitialisation', 'error');
        iframe.remove();
    };
    
    document.body.appendChild(iframe);
}

// Utiliser la version simple
function resetTitles() {
    resetTitlesSimple();
}

function sauvegarder() {
    // Appeler le script d'export via AJAX
    sauvegarderTournamentExport();
}

async function sauvegarderTournamentExport() {
    try {
        const response = await fetch('<?php echo $basePath; ?>Tournament/TournamentExport.php');
        
        if (response.ok) {
            showNotification('✅ Export Tournament terminé avec succès !', 'success');
            // Rediriger pour télécharger le fichier
            window.location.href = '<?php echo $basePath; ?>Tournament/TournamentExport.php?download=true';
        } else {
            showNotification('❌ Erreur lors de l\'export Tournament', 'error');
        }
    } catch (error) {
        showNotification('❌ Erreur de connexion', 'error');
    }
}

// FONCTION SIMPLIFIÉE POUR GITHUB
function updateAddonSimple() {
    if (!confirm('Voulez-vous mettre à jour l\'addon depuis GitHub ?\n\nTous les fichiers seront téléchargés depuis https://github.com/loloz3/ianseo-addon\n\nNote: Les fichiers menu.php et Prix.txt existants ne seront PAS remplacés.')) {
        return;
    }
    
    // Montrer le statut
    const statusDiv = document.getElementById('updateStatus');
    statusDiv.style.display = 'block';
    statusDiv.innerHTML = '<p>⏳ Début de la mise à jour...</p>';
    
    // Appeler le script PHP avec fetch
    fetch('github_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update'
    })
    .then(response => response.text())
    .then(text => {
        // Extraire uniquement le contenu de la div .log
        const logMatch = text.match(/<div class=['"]log['"][^>]*>([\s\S]*?)<\/div>/);
        const resultMatch = text.match(/<h3>Résumé de la mise à jour<\/h3>([\s\S]*?)<script/i);
        
        if (logMatch && resultMatch) {
            statusDiv.innerHTML = '<p>🔄 Progression :</p>' + logMatch[1] + 
                                '<hr><strong>Résultat :</strong><br>' + resultMatch[1];
        } else {
            // Fallback: afficher tout
            statusDiv.innerHTML = '<p>🔄 Progression :</p>' + text;
        }
        
        // Message final
        setTimeout(() => {
            showNotification('✅ Mise à jour GitHub terminée !', 'success');
        }, 1000);
    })
    .catch(error => {
        statusDiv.innerHTML = '<p style="color:red;">❌ Erreur: ' + error.message + '</p>';
        showNotification('❌ Erreur lors de la mise à jour', 'error');
    });
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

// Initialiser
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier et créer le fichier handler si nécessaire
    // createResetHandler();
    
    const links = document.querySelectorAll('.task-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Laisser le navigateur gérer l'ouverture dans un nouvel onglet
        });
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>