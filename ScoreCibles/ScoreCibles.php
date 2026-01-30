<?php
/**
 * Script AJAX pour vérifier les flèches des archers - Version avec sélection de session
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

CheckTourSession(true);
checkACL(AclParticipants, AclReadOnly);

$TourId = $_SESSION['TourId'];

$IncludeJquery = true;

include('Common/Templates/head.php');
?>

<style>
    .verification-container {
        padding: 20px;
    }
    
    .title-section {
        background-color: #2c5f2d;
        color: white;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
        font-size: 24px;
        font-weight: bold;
    }
    
    .controls {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 5px solid #17a2b8;
    }
    
    .session-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
        align-items: center;
    }
    
    .session-btn {
        padding: 10px 20px;
        border: 2px solid #6c757d;
        background-color: white;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
        min-width: 80px;
        text-align: center;
    }
    
    .session-btn:hover {
        background-color: #e9ecef;
        transform: translateY(-2px);
    }
    
    .session-btn.active {
        background-color: #2c5f2d;
        color: white;
        border-color: #2c5f2d;
    }
    
    .status-indicator {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        margin-right: 10px;
        vertical-align: middle;
    }
    
    .status-active {
        background-color: #28a745;
        animation: pulse 1.5s infinite;
    }
    
    .status-paused {
        background-color: #6c757d;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .targets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px;
        margin-top: 20px;
    }
    
    .target-card {
        border-radius: 6px;
        padding: 10px;
        text-align: center;
        color: white;
        font-weight: bold;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    
    .target-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    }
    
    .target-card.green {
        background: linear-gradient(135deg, #28a745, #218838);
        border: 2px solid #1e7e34;
    }
    
    .target-card.yellow {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        border: 2px solid #d39e00;
        color: #212529;
    }
    
    .target-card.red {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: 2px solid #bd2130;
    }
    
    .target-card.blue {
        background: linear-gradient(135deg, #17a2b8, #138496);
        border: 2px solid #117a8b;
    }
    
    .target-number {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 3px;
    }
    
    .target-session {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 5px;
        background-color: rgba(255,255,255,0.2);
        padding: 2px 6px;
        border-radius: 8px;
        display: inline-block;
    }
    
    .target-archers {
        font-size: 12px;
        margin-top: 5px;
        opacity: 0.9;
        background-color: rgba(255,255,255,0.2);
        padding: 3px 8px;
        border-radius: 12px;
        display: inline-block;
    }
    
    .target-warning {
        position: absolute;
        top: 3px;
        right: 5px;
        background-color: rgba(255,255,255,0.3);
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    
    .details-panel {
        background-color: white;
        border-radius: 8px;
        padding: 20px;
        margin-top: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: none;
    }
    
    .details-panel.active {
        display: block;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .details-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .details-table th {
        background-color: #2c5f2d;
        color: white;
        padding: 10px;
        text-align: left;
    }
    
    .details-table td {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .details-table tr:hover {
        background-color: #f8f9fa;
    }
    
    .arrow-count {
        font-weight: bold;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    
    .arrow-count.ok {
        background-color: #d4edda;
        color: #155724;
    }
    
    .arrow-count.warning {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .arrow-count.error {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .refresh-info {
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        border-left: 5px solid #17a2b8;
        color: #0c5460;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
        display: none;
    }
    
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin: 20px 0;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        margin-right: 20px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        margin-right: 8px;
    }
    
    .session-stats {
        background-color: #e9ecef;
        padding: 15px;
        border-radius: 5px;
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
    }
    
    .stat-item {
        text-align: center;
        padding: 0 15px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #2c5f2d;
    }
    
    .stat-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        margin-top: 5px;
    }
    
    .no-targets-message {
        grid-column: 1 / -1;
        text-align: center;
        padding: 40px;
        color: #6c757d;
        font-style: italic;
        background-color: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }
    
    .current-session-badge {
        background-color: #2c5f2d;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: bold;
        margin-left: 10px;
        font-size: 16px;
        display: inline-block;
    }
    
    .last-check-info {
        color: #2c5f2d;
        font-weight: bold;
        margin-left: 10px;
        padding: 5px 10px;
        background-color: #e8f5e9;
        border-radius: 4px;
        font-size: 14px;
        border: 1px solid #c3e6cb;
    }
    
    .badge-info {
        background-color: #17a2b8;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
    }
    
    .badge-secondary {
        background-color: #6c757d;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
        min-width: 60px;
        display: inline-block;
        text-align: center;
    }
    
    .badge-ok {
        background-color: #28a745;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
    }
    
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
    }
    
    .badge-error {
        background-color: #dc3545;
        color: white;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 12px;
    }
    
    .score-total {
        font-weight: bold;
        padding: 3px 8px;
        border-radius: 4px;
        background-color: #e9ecef;
        color: #495057;
        min-width: 40px;
        display: inline-block;
        text-align: center;
    }
    
    .score-total.d1 {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .score-total.d2 {
        background-color: #d4edda;
        color: #155724;
    }
    
    .score-total.combined {
        background-color: #fff3cd;
        color: #856404;
        font-size: 14px;
        padding: 4px 10px;
    }
    
    .volley-number {
        font-weight: bold;
        padding: 3px 8px;
        border-radius: 4px;
        background-color: #e9ecef;
        color: #495057;
        min-width: 40px;
        display: inline-block;
        text-align: center;
        font-size: 12px;
    }
    
    /* Styles pour l'indicateur de batterie */
    .battery-indicator {
        position: absolute;
        top: 5px;
        left: 5px;
        width: 28px;
        height: 36px;
        border: 2px solid rgba(255, 255, 255, 0.7);
        border-radius: 4px;
        background: rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: bold;
        color: white;
        z-index: 10;
        overflow: hidden;
    }
    
    .battery-level {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 2px;
        transition: height 0.3s;
    }
    
    .battery-level.high {
        background: linear-gradient(to top, #4CAF50, #8BC34A);
        height: 80%;
    }
    
    .battery-level.medium {
        background: linear-gradient(to top, #FFC107, #FF9800);
        height: 50%;
    }
    
    .battery-level.low {
        background: linear-gradient(to top, #FF9800, #FF5722);
        height: 25%;
    }
    
    .battery-level.critical {
        background: linear-gradient(to top, #F44336, #D32F2F);
        height: 10%;
    }
    
    .battery-level.charging {
        background: linear-gradient(to top, #2196F3, #1976D2);
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    
    /* Effet d'animation pour la charge */
    .battery-level.charging::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(255, 255, 255, 0.4) 50%,
            transparent 100%
        );
        animation: charging-flow 2s infinite;
    }
    
    @keyframes charging-flow {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    .battery-level.none {
        background: rgba(128, 128, 128, 0.5);
        height: 20%;
    }
    
    .battery-percent {
        position: relative;
        z-index: 2;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9);
        font-size: 9px;
        font-weight: bold;
        color: white;
        padding: 1px;
        background-color: rgba(0, 0, 0, 0.3);
        border-radius: 2px;
    }
    
    .battery-warning {
        position: absolute;
        top: -2px;
        right: -2px;
        background-color: #ff4444;
        color: white;
        border-radius: 50%;
        width: 14px;
        height: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8px;
        animation: pulse-warning 1.5s infinite;
        z-index: 11;
    }
    
    .charging-icon {
        position: absolute;
        top: 2px;
        right: 2px;
        color: #FFEB3B;
        font-size: 10px;
        z-index: 11;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
        animation: charging-pulse 1.5s infinite;
    }
    
    @keyframes charging-pulse {
        0%, 100% { opacity: 0.7; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.2); }
    }
    
    @keyframes pulse-warning {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    /* Effet de scintillement pour batterie faible */
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .battery-level.low {
        animation: blink 2s infinite;
    }
    
    .battery-level.critical {
        animation: blink 1s infinite;
    }
    
    /* Pointe de la batterie */
    .battery-indicator::before {
        content: '';
        position: absolute;
        top: -6px;
        left: 50%;
        transform: translateX(-50%);
        width: 10px;
        height: 4px;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 2px 2px 0 0;
    }
</style>

<div class="verification-container">
    
    <div class="controls">
        <h3 style="margin-top: 0; margin-bottom: 10px;">Sélectionnez un départ :</h3>
        <div class="session-selector" id="session-selector">
            <!-- Les boutons de session seront générés dynamiquement -->
        </div>
        
        <div style="margin-top: 10px;">
            <button id="test-btn" class="btn btn-sm btn-warning">
                <i class="fas fa-bug"></i> Tester la connexion
            </button>
            <button id="debug-btn" class="btn btn-sm btn-info ml-2">
                <i class="fas fa-terminal"></i> Mode débogage
            </button>
        </div>
        
        <div class="session-stats" id="session-stats">
            <!-- Les statistiques de session seront affichées ici -->
        </div>
    </div>
    
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background-color: #28a745;"></div>
            <span>Tous les scores sont à jours</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #ffc107;"></div>
            <span>Mise à jours en cours</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #dc3545;"></div>
            <span>Cible en retard</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #17a2b8;"></div>
            <span>Cible sans données ou vide</span>
        </div>
        <div class="legend-item">
            <div style="display: flex; align-items: center;">
                <div class="battery-indicator" style="position: relative; top: 0; left: 0; margin-right: 8px;">
                    <div class="battery-level high" style="height: 80%;"></div>
                    <div class="battery-percent">80%</div>
                </div>
                <span>Batterie haute</span>
            </div>
        </div>
        <div class="legend-item">
            <div style="display: flex; align-items: center;">
                <div class="battery-indicator" style="position: relative; top: 0; left: 0; margin-right: 8px;">
                    <div class="battery-level charging" style="height: 100%;"></div>
                    <div class="battery-percent">75%</div>
                    <div class="charging-icon">⚡</div>
                </div>
                <span>En charge: 75%</span>
            </div>
        </div>
        <div class="legend-item">
            <div style="display: flex; align-items: center;">
                <div class="battery-indicator" style="position: relative; top: 0; left: 0; margin-right: 8px;">
                    <div class="battery-level critical" style="height: 10%;"></div>
                    <div class="battery-percent">15%</div>
                    <div class="battery-warning">!</div>
                </div>
                <span>Batterie critique</span>
            </div>
        </div>
    </div>
    
    <div id="targets-container" class="targets-grid">
        <!-- Les cibles seront affichées ici dynamiquement -->
    </div>
    
    <div id="details-panel" class="details-panel">
        <h3>Détails de la cible <span id="detail-target-number"></span> (Départ <span id="detail-session"></span>)</h3>
        <div id="detail-status"></div>
        <table class="details-table" id="detail-table">
            <thead>
                <tr>
                    <!-- L'en-tête sera mis à jour dynamiquement -->
                </tr>
            </thead>
            <tbody id="detail-table-body">
                <!-- Les détails seront insérés ici -->
            </tbody>
        </table>
    </div>
</div>

<script>
// Variables globales
let checkInterval;
let currentSession = '1'; // Départ 1 par défaut
let availableSessions = [];

// Fonction pour formater la date/heure
function formatDateTime(date) {
    return date.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// Fonction pour mettre à jour l'indicateur de dernière vérification
function updateLastCheck() {
    const now = new Date();
    // Mettre à jour l'info de dernière vérification dans le sélecteur de session
    $('.last-check-info').remove();
    $('#session-selector').append(
        $('<span>').addClass('last-check-info')
            .html(`<i class="fas fa-sync-alt"></i> Dernière vérification: ${formatDateTime(now)}`)
    );
}

// Fonction pour démarrer la vérification automatique
function startAutoChecking() {
    // Démarrer la vérification immédiatement
    checkTargets();
    
    // Configurer l'intervalle de 5 secondes
    checkInterval = setInterval(checkTargets, 5000);
}

// Fonction pour arrêter la vérification automatique
function stopAutoChecking() {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
}

// Fonction pour mettre à jour le sélecteur de session
function updateSessionSelector(sessionsData) {
    const selector = $('#session-selector');
    selector.empty();
    
    // Supprimer l'info par défaut si elle existe
    $('.last-check-info').remove();
    
    // Boutons pour chaque session seulement
    Object.keys(sessionsData).sort((a, b) => a - b).forEach(session => {
        const sessionBtn = $('<button>')
            .addClass('session-btn' + (currentSession === session ? ' active' : ''))
            .text('Départ ' + session)
            .click(function() {
                selectSession(session);
            });
        selector.append(sessionBtn);
    });
    
    availableSessions = Object.keys(sessionsData);
    
    // Mettre à jour l'heure de dernière vérification
    updateLastCheck();
}

// Fonction pour sélectionner une session
function selectSession(session) {
    currentSession = session;
    updateSessionSelectorUI();
    
    // Arrêter et redémarrer la vérification pour la nouvelle session
    clearInterval(checkInterval);
    startAutoChecking();
}

// Fonction pour mettre à jour l'UI du sélecteur de session
function updateSessionSelectorUI() {
    $('#session-selector .session-btn').removeClass('active');
    $(`#session-selector .session-btn:contains('Départ ${currentSession}')`).addClass('active');
    
    // Mettre à jour l'heure de dernière vérification
    updateLastCheck();
}

// Fonction pour afficher les statistiques de session
function updateSessionStats(stats) {
    const statsContainer = $('#session-stats');
    statsContainer.empty();
    
    // Statistiques pour la session sélectionnée uniquement
    const sessionStats = stats.sessionStats?.[currentSession] || {};
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value">Départ ${currentSession}</div>
        <div class="stat-label">Départ actif</div>
    `).appendTo(statsContainer);
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value">${sessionStats.totalTargets || 0}</div>
        <div class="stat-label">Cibles</div>
    `).appendTo(statsContainer);
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value" style="color: #28a745;">${sessionStats.greenTargets || 0}</div>
        <div class="stat-label">Vertes</div>
    `).appendTo(statsContainer);
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value" style="color: #ffc107;">${sessionStats.yellowTargets || 0}</div>
        <div class="stat-label">Jaunes</div>
    `).appendTo(statsContainer);
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value" style="color: #dc3545;">${sessionStats.redTargets || 0}</div>
        <div class="stat-label">Rouges</div>
    `).appendTo(statsContainer);
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value" style="color: #17a2b8;">${sessionStats.blueTargets || 0}</div>
        <div class="stat-label">Bleues</div>
    `).appendTo(statsContainer);
}

// Fonction pour créer un indicateur de batterie
function createBatteryIndicator(batteryInfo) {
    if (!batteryInfo) {
        // Pas de données de batterie
        const batteryIndicator = $('<div>').addClass('battery-indicator');
        const batteryLevel = $('<div>').addClass('battery-level none');
        const batteryPercent = $('<div>').addClass('battery-percent').text('NA');
        
        batteryIndicator.append(batteryLevel, batteryPercent);
        batteryIndicator.attr('title', 'Aucune donnée de batterie disponible');
        return batteryIndicator;
    }
    
    const batteryIndicator = $('<div>').addClass('battery-indicator');
    let batteryLevelClass = batteryInfo.status;
    let displayText = batteryInfo.absoluteLevel + '%'; // Toujours afficher le pourcentage
    
    // Ajuster la hauteur de la jauge en fonction du niveau
    let gaugeHeight = '20%'; // Par défaut (pour "none")
    
    if (batteryInfo.isCharging) {
        batteryLevelClass = 'charging';
        // Pour la charge, on montre la jauge pleine
        gaugeHeight = '100%';
        
        // Ajouter l'icône de charge en plus du pourcentage
        const chargingIcon = $('<div>').addClass('charging-icon').html('⚡');
        batteryIndicator.append(chargingIcon);
    } else {
        // Ajuster la hauteur de la jauge selon le niveau
        switch(batteryInfo.status) {
            case 'high': gaugeHeight = '80%'; break;
            case 'medium': gaugeHeight = '50%'; break;
            case 'low': gaugeHeight = '25%'; break;
            case 'critical': gaugeHeight = '10%'; break;
        }
    }
    
    const batteryLevel = $('<div>').addClass('battery-level ' + batteryLevelClass)
        .css('height', gaugeHeight);
    
    const batteryPercent = $('<div>').addClass('battery-percent').text(displayText);
    
    batteryIndicator.append(batteryLevel, batteryPercent);
    
    // Ajouter un avertissement si batterie faible et pas en charge
    if (!batteryInfo.isCharging && (batteryInfo.status === 'low' || batteryInfo.status === 'critical')) {
        const batteryWarning = $('<div>').addClass('battery-warning').html('!');
        batteryIndicator.append(batteryWarning);
    }
    
    // Info-bulle avec date de dernière mise à jour et état
    let tooltip = '';
    if (batteryInfo.isCharging) {
        tooltip = `⚡ En charge: ${batteryInfo.absoluteLevel}%`;
    } else {
        let statusText = '';
        switch(batteryInfo.status) {
            case 'high': statusText = 'Haute'; break;
            case 'medium': statusText = 'Moyenne'; break;
            case 'low': statusText = 'Faible'; break;
            case 'critical': statusText = 'Critique'; break;
        }
        tooltip = `Batterie: ${batteryInfo.level}% (${statusText})`;
    }
    
    if (batteryInfo.lastUpdate) {
        const lastUpdate = new Date(batteryInfo.lastUpdate);
        tooltip += `\nDernière mise à jour: ${lastUpdate.toLocaleString('fr-FR')}`;
    }
    
    batteryIndicator.attr('title', tooltip);
    
    return batteryIndicator;
}

// Fonction principale pour vérifier les cibles
function checkTargets() {
    console.log("Vérification des cibles pour la session: " + currentSession);
    
    $.ajax({
        url: 'ajax_check_fleches_session.php',
        type: 'POST',
        dataType: 'json',
        data: {
            TourId: <?php echo isset($_SESSION['TourId']) ? $_SESSION['TourId'] : '0'; ?>,
            session: currentSession
        },
        beforeSend: function() {
            console.log("Requête AJAX envoyée à ajax_check_fleches_session.php");
        },
        success: function(response, status, xhr) {
            console.log("Réponse AJAX reçue:", response);
            console.log("Status:", status);
            console.log("Content-Type:", xhr.getResponseHeader('Content-Type'));
            
            if (response.success) {
                // Mettre à jour le sélecteur de session si nécessaire
                if (response.sessionsData && Object.keys(response.sessionsData).length > 0) {
                    updateSessionSelector(response.sessionsData);
                }
                
                // Afficher les cibles
                displayTargets(response.targets);
                
                // Mettre à jour les statistiques
                updateSessionStats(response.stats || {});
                
                updateLastCheck();
            } else {
                console.error("Erreur dans la réponse:", response.message);
                showCustomNotification('Erreur: ' + response.message, 'error');
                
                // Afficher les détails de débogage si disponibles
                if (response.debug) {
                    console.error("Détails du débogage:", response.debug);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error("Erreur AJAX complète:");
            console.error("Status:", status);
            console.error("Error:", error);
            
            let errorMessage = 'Erreur réseau lors de la vérification';
            
            // Vérifier si c'est une erreur de parsing JSON
            if (status === 'parsererror') {
                errorMessage = 'Erreur de parsing JSON. Vérifiez la réponse du serveur.';
                console.error("Réponse brute:", xhr.responseText);
                if (xhr.responseText.length < 1000) {
                    alert("Réponse brute du serveur:\n" + xhr.responseText);
                }
            }
            // Vérifier si c'est une erreur 404
            else if (xhr.status === 404) {
                errorMessage = 'Fichier ajax_check_fleches_session.php non trouvé';
            }
            // Vérifier si c'est une erreur 500
            else if (xhr.status === 500) {
                errorMessage = 'Erreur serveur (500)';
                console.error("Réponse d'erreur:", xhr.responseText);
            }
            
            showCustomNotification(errorMessage, 'error');
            
            // Afficher un message d'erreur dans le conteneur
            const container = $('#targets-container');
            container.html(`
                <div class="no-targets-message">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; color: #dc3545;"></i>
                    <h3 style="color: #dc3545;">Erreur lors de la vérification</h3>
                    <p>${errorMessage}</p>
                    <p><small>Status: ${xhr.status} - ${status}</small></p>
                    <button id="retry-btn" class="btn btn-primary mt-3">Réessayer</button>
                </div>
            `);
            
            // Ajouter un gestionnaire pour le bouton réessayer
            $('#retry-btn').click(function() {
                checkTargets();
            });
        },
        complete: function() {
            console.log("Requête AJAX terminée");
        }
    });
}

// Fonction pour afficher les cibles
function displayTargets(targets) {
    const container = $('#targets-container');
    container.empty();
    
    if (targets.length === 0) {
        container.html(`
            <div class="no-targets-message">
                <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h3>Aucune cible trouvée</h3>
                <p>Aucune donnée de flèches n'est disponible pour le départ ${currentSession}.</p>
            </div>
        `);
        return;
    }
    
    targets.forEach(target => {
        const targetCard = $('<div>').addClass('target-card ' + target.status);
        
        // Indicateur de batterie
        const batteryIndicator = createBatteryIndicator(target.battery);
        targetCard.append(batteryIndicator);
        
        // Numéro de cible (plus gros)
        $('<div>').addClass('target-number').text(target.targetNumber).appendTo(targetCard);
        
        // Session
        $('<div>').addClass('target-session').text('Départ ' + target.session).appendTo(targetCard);
        
        // Nombre d'archers
        if (target.archerCount > 0) {
            $('<div>').addClass('target-archers')
                .html(`<i class="fas fa-user"></i> ${target.archerCount} archer${target.archerCount > 1 ? 's' : ''}`)
                .appendTo(targetCard);
        } else {
            $('<div>').addClass('target-archers')
                .html(`<i class="fas fa-user-slash"></i> 0 archer`)
                .appendTo(targetCard);
        }
        
        // Icône d'avertissement si nécessaire
        if (target.status === 'yellow' || target.status === 'red') {
            $('<div>').addClass('target-warning').html('⚠️').appendTo(targetCard);
        }
        
        // Événement de clic pour afficher les détails
        targetCard.click(function() {
            showTargetDetails(target);
        });
        
        container.append(targetCard);
    });
}

// Fonctions utilitaires pour les estimations
function estimateChargingTime(batteryLevel) {
    // Estimation simple du temps de charge
    const remaining = 100 - batteryLevel;
    if (remaining <= 0) return null;
    
    // Estimation: environ 2 minutes par pourcentage
    const totalMinutes = Math.round(remaining * 2);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    
    if (hours > 0) {
        return `~${hours}h${minutes}min restantes`;
    } else {
        return `~${minutes}min restantes`;
    }
}

function estimateBatteryLife(batteryLevel) {
    // Estimation simple de l'autonomie
    if (batteryLevel >= 80) return 'Autonomie excellente';
    if (batteryLevel >= 60) return 'Autonomie bonne';
    if (batteryLevel >= 40) return 'Autonomie moyenne';
    if (batteryLevel >= 20) return 'Autonomie faible';
    return 'Autonomie très faible';
}

// Fonction pour afficher les détails d'une cible
function showTargetDetails(target) {
    $('#detail-target-number').text(target.targetNumber);
    $('#detail-session').text(target.session);
    $('#details-panel').addClass('active');
    
    // Mettre à jour le statut
    let statusText = '';
    let statusClass = '';
    
    switch(target.status) {
        case 'green':
            statusText = '✓ Tous les scores des archers sont rentrés';
            statusClass = 'text-success';
            break;
        case 'yellow':
            statusText = '⚠️ Les scores sont en cours';
            statusClass = 'text-warning';
            break;
        case 'red':
            statusText = '✗ Scores non mis à jours';
            statusClass = 'text-danger';
            break;
        case 'blue':
            statusText = 'ℹ️ Cible sans données ou vide';
            statusClass = 'text-info';
            break;
    }
    
    $('#detail-status').html(`<div class="alert ${statusClass.replace('text-', 'alert-')}">${statusText}</div>`);
    
    // Ajouter les informations de batterie si disponibles
    if (target.battery) {
        let batteryStatusText = '';
        let batteryStatusClass = '';
        let batteryIcon = '';
        
        if (target.battery.isCharging) {
            batteryIcon = '⚡ ';
            batteryStatusText = `En charge: ${target.battery.absoluteLevel}%`;
            batteryStatusClass = 'text-info';
            
            // Ajouter une estimation du temps de charge si possible
            if (target.battery.absoluteLevel < 100) {
                const estimatedTime = estimateChargingTime(target.battery.absoluteLevel);
                if (estimatedTime) {
                    batteryStatusText += ` (${estimatedTime})`;
                }
            } else {
                batteryStatusText += ' (Chargement terminé)';
            }
        } else {
            switch(target.battery.status) {
                case 'high':
                    batteryStatusText = `Batterie: ${target.battery.level}% - Bon niveau`;
                    batteryStatusClass = 'text-success';
                    break;
                case 'medium':
                    batteryStatusText = `Batterie: ${target.battery.level}% - Niveau moyen`;
                    batteryStatusClass = 'text-warning';
                    break;
                case 'low':
                    batteryStatusText = `Batterie: ${target.battery.level}% - Faible niveau`;
                    batteryStatusClass = 'text-danger';
                    break;
                case 'critical':
                    batteryStatusText = `Batterie: ${target.battery.level}% - Niveau critique !`;
                    batteryStatusClass = 'text-danger';
                    break;
                default:
                    batteryStatusText = `Batterie: ${target.battery.level}%`;
                    batteryStatusClass = 'text-info';
            }
            
            // Ajouter une estimation d'autonomie si la batterie est faible
            if (target.battery.status === 'low' || target.battery.status === 'critical') {
                const estimatedTime = estimateBatteryLife(target.battery.level);
                batteryStatusText += ` (${estimatedTime})`;
            }
        }
        
        if (target.battery.lastUpdate) {
            const lastUpdate = new Date(target.battery.lastUpdate);
            const now = new Date();
            const diffMinutes = Math.floor((now - lastUpdate) / (1000 * 60));
            
            batteryStatusText += ` - Dernière mise à jour: ${lastUpdate.toLocaleTimeString('fr-FR')}`;
            
            if (diffMinutes > 60) {
                batteryStatusText += ` <span class="badge badge-warning">(${diffMinutes} min)</span>`;
            }
        }
        
    //    $('#detail-status').append(
    //        `<div class="alert ${batteryStatusClass.replace('text-', 'alert-')} mt-2">
    //            ${batteryIcon}${batteryStatusText}
    //        </div>`
    //    );
    }
    
    // Mettre à jour l'en-tête du tableau avec les nouvelles colonnes
    $('#detail-table thead tr').html(`
        <th>Départ</th>
        <th>Cible</th>
        <th>Lettre</th>
        <th>Archer</th>
        <th>Licence</th>
        <th>N° Volée D1</th>
        <th>Dernière volée D1</th>
        <th>Total D1</th>
        <th>N° Volée D2</th>
        <th>Dernière volée D2</th>
        <th>Total D2</th>
        <th>Total</th>
        <th>Statut</th>
    `);
    
    // Remplir le tableau des détails
    const tbody = $('#detail-table-body');
    tbody.empty();
    
    if (target.archers && target.archers.length > 0) {
        target.archers.forEach(archer => {
            const totalArrows = archer.arrowsD1 + archer.arrowsD2;
            let arrowClass = 'ok';
            let statusText = 'OK';
            
            if (totalArrows % 3 !== 0) {
                arrowClass = 'error';
                statusText = 'A vérifier';
            } else if (target.hasDifferentArrowCounts && archer.arrowsTotal !== target.averageArrows) {
                arrowClass = 'warning';
                statusText = 'Scores en cours';
            }
            
            // Numéros de volée (nombres entiers uniquement)
            const volleyD1 = archer.volleyNumberD1 || 0;
            const volleyD2 = archer.volleyNumberD2 || 0;
            
            // Formater les derniers scores
            const lastScoreD1 = archer.lastScoreD1 || '-';
            const lastScoreD2 = archer.lastScoreD2 || '-';
            
            // Formater les totaux
            const totalScoreD1 = archer.totalScoreD1 || 0;
            const totalScoreD2 = archer.totalScoreD2 || 0;
            const totalScore = archer.totalScore || 0;
            
            const row = $('<tr>');
            row.append($('<td>').text(archer.session || '-'));
            row.append($('<td>').text(archer.targetNumber || '-'));
            row.append($('<td>').text(archer.targetLetter || '-'));
            row.append($('<td>').text(archer.archerName || 'Non assigné'));
            row.append($('<td>').text(archer.license || '-'));
            row.append($('<td>').html(`<span class="volley-number">${volleyD1}</span>`));
            row.append($('<td>').html(`<span class="badge badge-secondary">${lastScoreD1}</span>`));
            row.append($('<td>').html(`<span class="score-total d1">${totalScoreD1}</span>`));
            row.append($('<td>').html(`<span class="volley-number">${volleyD2}</span>`));
            row.append($('<td>').html(`<span class="badge badge-secondary">${lastScoreD2}</span>`));
            row.append($('<td>').html(`<span class="score-total d2">${totalScoreD2}</span>`));
            row.append($('<td>').html(`<span class="score-total combined">${totalScore}</span>`));
            row.append($('<td>').html(`<span class="badge badge-${arrowClass}">${statusText}</span>`));
            
            tbody.append(row);
        });
    } else {
        tbody.html('<tr><td colspan="13" style="text-align: center; font-style: italic; color: #6c757d;">Aucun archer sur cette cible</td></tr>');
    }
    
    // Faire défiler jusqu'au panneau de détails
    $('html, body').animate({
        scrollTop: $('#details-panel').offset().top - 20
    }, 500);
}

// Fonction de notification personnalisée
function showCustomNotification(message, type = 'success') {
    const notification = $('<div>').css({
        'position': 'fixed',
        'top': '20px',
        'right': '20px',
        'padding': '15px 20px',
        'border-radius': '5px',
        'color': 'white',
        'font-weight': 'bold',
        'z-index': '9999',
        'box-shadow': '0 4px 6px rgba(0,0,0,0.1)',
        'animation': 'slideIn 0.3s ease-out',
        'min-width': '300px',
        'text-align': 'center',
        'backgroundColor': type === 'success' ? '#28a745' : '#dc3545'
    });
    
    notification.html(`
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
            <button style="background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; margin-left: 10px;">
                ×
            </button>
        </div>
    `);
    
    // Supprimer toute notification existante
    $('#custom-notification').remove();
    notification.attr('id', 'custom-notification');
    
    // Ajouter l'animation CSS
    if (!$('#notification-styles').length) {
        $('<style>').attr('id', 'notification-styles').text(`
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `).appendTo('head');
    }
    
    $('body').append(notification);
    
    // Gérer la fermeture
    notification.find('button').click(function() {
        notification.css('animation', 'fadeOut 0.3s ease-out');
        setTimeout(() => notification.remove(), 300);
    });
    
    // Supprimer automatiquement après 5 secondes
    setTimeout(() => {
        if (notification.parent().length) {
            notification.css('animation', 'fadeOut 0.3s ease-out');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Fonction pour tester l'URL directement
function testAjaxUrl() {
    const testUrl = 'ajax_check_fleches_session.php';
    console.log("Test de l'URL: " + testUrl);
    
    $.ajax({
        url: testUrl,
        type: 'GET',
        success: function(response) {
            console.log("Test GET réussi:", response);
            alert("Test GET réussi! Le fichier est accessible.");
        },
        error: function(xhr, status, error) {
            console.error("Test GET échoué:", status, error);
            alert("Impossible d'accéder à " + testUrl + "\n" + status + ": " + error);
        }
    });
}

// Initialisation lorsque le document est prêt
$(document).ready(function() {
    // Test de connexion
    $('#test-btn').click(function() {
        testAjaxUrl();
    });
    
    // Mode débogage
    $('#debug-btn').click(function() {
        console.clear();
        console.log("=== Mode débogage activé ===");
        console.log("Session actuelle:", currentSession);
        console.log("TourId PHP:", <?php echo isset($_SESSION['TourId']) ? $_SESSION['TourId'] : '0'; ?>);
        console.log("URL AJAX: ajax_check_fleches_session.php");
        
        // Ouvrir la console
        if (typeof console !== 'undefined') {
            alert("Mode débogage activé. Ouvrez la console du navigateur (F12) pour voir les logs.");
        }
    });
    
    // Démarrer automatiquement la vérification toutes les 5 secondes
    startAutoChecking();
    
    // Mettre à jour l'heure de la dernière vérification
    updateLastCheck();
    
    // Fermer le panneau de détails en cliquant à l'extérieur
    $(document).click(function(event) {
        if (!$(event.target).closest('#details-panel').length && 
            !$(event.target).closest('.target-card').length) {
            $('#details-panel').removeClass('active');
        }
    });
    
    // Arrêter la vérification automatique lorsque l'utilisateur quitte la page
    $(window).on('beforeunload', function() {
        stopAutoChecking();
    });
});
</script>

<?php include('Common/Templates/tail.php'); ?>