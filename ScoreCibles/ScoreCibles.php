<?php
/**
 * Script AJAX pour vérifier les flèches des archers - Version avec sélection de session
 * Adapté pour afficher 3 ou 6 dernières flèches selon le type de tournoi (Indoor/Outdoor)
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
    .verification-container { padding: 20px; }
    .title-section { background-color: #2c5f2d; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 24px; font-weight: bold; }
    .controls { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #17a2b8; }
    .session-selector { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; align-items: center; }
    .session-btn { padding: 10px 20px; border: 2px solid #6c757d; background-color: white; border-radius: 5px; cursor: pointer; font-weight: bold; transition: all 0.3s; min-width: 80px; text-align: center; }
    .session-btn:hover { background-color: #e9ecef; transform: translateY(-2px); }
    .session-btn.active { background-color: #2c5f2d; color: white; border-color: #2c5f2d; }
    .status-indicator { display: inline-block; width: 20px; height: 20px; border-radius: 50%; margin-right: 10px; vertical-align: middle; }
    .status-active { background-color: #28a745; animation: pulse 1.5s infinite; }
    .status-paused { background-color: #6c757d; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    .targets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 20px; }
    .target-card { border-radius: 6px; padding: 10px; text-align: center; color: white; font-weight: bold; transition: transform 0.3s, box-shadow 0.3s; position: relative; overflow: hidden; cursor: pointer; min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    .target-card:hover { transform: translateY(-3px); box-shadow: 0 3px 10px rgba(0,0,0,0.2); }
    .target-card.green { background: linear-gradient(135deg, #28a745, #218838); border: 2px solid #1e7e34; }
    .target-card.yellow { background: linear-gradient(135deg, #ffc107, #e0a800); border: 2px solid #d39e00; color: #212529; }
    .target-card.red { background: linear-gradient(135deg, #dc3545, #c82333); border: 2px solid #bd2130; }
    .target-card.blue { background: linear-gradient(135deg, #17a2b8, #138496); border: 2px solid #117a8b; }
    .target-number { font-size: 24px; font-weight: bold; margin-bottom: 3px; }
    .target-session { font-size: 12px; opacity: 0.9; margin-bottom: 5px; background-color: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 8px; display: inline-block; }
    .target-archers { font-size: 12px; margin-top: 5px; opacity: 0.9; background-color: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 12px; display: inline-block; }
    .target-warning { position: absolute; top: 3px; right: 5px; background-color: rgba(255,255,255,0.3); border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    .details-panel { background-color: white; border-radius: 8px; padding: 20px; margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: none; }
    .details-panel.active { display: block; animation: slideDown 0.3s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    .details-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .details-table th { background-color: #2c5f2d; color: white; padding: 10px; text-align: left; }
    .details-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
    .details-table tr:hover { background-color: #f8f9fa; }
    .details-table tr.archer-row-clickable:hover { background-color: #d1ecf1 !important; box-shadow: inset 3px 0 0 #17a2b8; }
    .details-table tr.archer-row-clickable td:first-child::before { content: '🔗 '; font-size: 11px; opacity: 0.6; }
    .arrow-count { font-weight: bold; padding: 2px 8px; border-radius: 4px; display: inline-block; }
    .arrow-count.ok { background-color: #d4edda; color: #155724; }
    .arrow-count.warning { background-color: #fff3cd; color: #856404; }
    .arrow-count.error { background-color: #f8d7da; color: #721c24; }
    .refresh-info { background-color: #d1ecf1; border: 1px solid #bee5eb; border-left: 5px solid #17a2b8; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; display: none; }
    .legend { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
    .legend-item { display: flex; align-items: center; margin-right: 20px; }
    .legend-color { width: 20px; height: 20px; border-radius: 4px; margin-right: 8px; }
    .session-stats { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; }
    .stat-item { text-align: center; padding: 0 15px; }
    .stat-value { font-size: 24px; font-weight: bold; color: #2c5f2d; }
    .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; margin-top: 5px; }
    .no-targets-message { grid-column: 1 / -1; text-align: center; padding: 40px; color: #6c757d; font-style: italic; background-color: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6; }
    .current-session-badge { background-color: #2c5f2d; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; margin-left: 10px; font-size: 16px; display: inline-block; }
    .last-check-info { color: #2c5f2d; font-weight: bold; margin-left: 10px; padding: 5px 10px; background-color: #e8f5e9; border-radius: 4px; font-size: 14px; border: 1px solid #c3e6cb; }
    .badge-info { background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
    .badge-secondary { background-color: #6c757d; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; min-width: 60px; display: inline-block; text-align: center; }
    .badge-ok { background-color: #28a745; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
    .badge-warning { background-color: #ffc107; color: #212529; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
    .badge-error { background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 10px; font-size: 12px; }
    .score-total { font-weight: bold; padding: 3px 8px; border-radius: 4px; background-color: #e9ecef; color: #495057; min-width: 40px; display: inline-block; text-align: center; }
    .score-total.d1 { background-color: #d1ecf1; color: #0c5460; }
    .score-total.d2 { background-color: #d4edda; color: #155724; }
    .score-total.combined { background-color: #fff3cd; color: #856404; font-size: 14px; padding: 4px 10px; }
    .volley-number { font-weight: bold; padding: 3px 8px; border-radius: 4px; background-color: #e9ecef; color: #495057; min-width: 40px; display: inline-block; text-align: center; font-size: 12px; }
    .battery-indicator { position: absolute; top: 5px; left: 5px; width: 28px; height: 36px; border: 2px solid rgba(255, 255, 255, 0.7); border-radius: 4px; background: rgba(0, 0, 0, 0.3); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; color: white; z-index: 10; overflow: hidden; }
    .battery-level { position: absolute; bottom: 0; left: 0; width: 100%; border-radius: 2px; transition: height 0.3s; }
    .battery-level.high { background: linear-gradient(to top, #4CAF50, #8BC34A); height: 80%; }
    .battery-level.medium { background: linear-gradient(to top, #FFC107, #FF9800); height: 50%; }
    .battery-level.low { background: linear-gradient(to top, #FF9800, #FF5722); height: 25%; }
    .battery-level.critical { background: linear-gradient(to top, #F44336, #D32F2F); height: 10%; }
    .battery-level.charging { background: linear-gradient(to top, #2196F3, #1976D2); height: 100%; position: relative; overflow: hidden; }
    .battery-level.charging::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.4) 50%, transparent 100%); animation: charging-flow 2s infinite; }
    @keyframes charging-flow { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    .battery-level.none { background: rgba(128, 128, 128, 0.5); height: 20%; }
    .battery-percent { position: relative; z-index: 2; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9); font-size: 9px; font-weight: bold; color: white; padding: 1px; background-color: rgba(0, 0, 0, 0.3); border-radius: 2px; }
    .battery-warning { position: absolute; top: -2px; right: -2px; background-color: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; font-size: 8px; animation: pulse-warning 1.5s infinite; z-index: 11; }
    .charging-icon { position: absolute; top: 2px; right: 2px; color: #FFEB3B; font-size: 10px; z-index: 11; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8); animation: charging-pulse 1.5s infinite; }
    @keyframes charging-pulse { 0%, 100% { opacity: 0.7; transform: scale(1); } 50% { opacity: 1; transform: scale(1.2); } }
    @keyframes pulse-warning { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .battery-level.low { animation: blink 2s infinite; }
    .battery-level.critical { animation: blink 1s infinite; }
    .battery-indicator::before { content: ''; position: absolute; top: -6px; left: 50%; transform: translateX(-50%); width: 10px; height: 4px; background: rgba(255, 255, 255, 0.7); border-radius: 2px 2px 0 0; }
    .tournament-info { margin-bottom: 10px; padding: 8px 15px; background-color: #e9ecef; border-radius: 5px; text-align: center; font-weight: bold; }
    .tournament-info.indoor { background-color: #c8e6e0; color: #0a5c4e; border-left: 5px solid #0a5c4e; }
    .tournament-info.outdoor { background-color: #d4edda; color: #155724; border-left: 5px solid #28a745; }
</style>

<div class="verification-container">
    <div class="controls">
        <h3 style="margin-top: 0; margin-bottom: 10px;">Sélectionnez un départ :</h3>
        <div class="session-selector" id="session-selector">
            <div style="text-align: center; padding: 10px; color: #6c757d;">
                <i class="fas fa-spinner fa-spin"></i> Chargement des départs...
            </div>
        </div>
        <div class="session-stats" id="session-stats"></div>
    </div>
    
    <div class="legend">
        <div class="legend-item"><div class="legend-color" style="background-color: #28a745;"></div><span>Tous les scores sont à jour (au niveau du max de la session)</span></div>
        <div class="legend-item"><div class="legend-color" style="background-color: #ffc107;"></div><span>Scores en cours ou décalage entre archers</span></div>
        <div class="legend-item"><div class="legend-color" style="background-color: #dc3545;"></div><span>Cible en retard (volée(s) de retard sur le max de la session)</span></div>
        <div class="legend-item"><div class="legend-color" style="background-color: #17a2b8;"></div><span>Cible sans données ou vide</span></div>
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
        <div class="no-targets-message">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h3>Chargement des cibles...</h3>
            <p>Veuillez patienter pendant le chargement des données.</p>
        </div>
    </div>
    
    <div id="details-panel" class="details-panel">
        <h3>Détails de la cible <span id="detail-target-number"></span> (Départ <span id="detail-session"></span>)</h3>
        <div id="detail-status"></div>
        <p style="font-size: 13px; color: #6c757d; font-style: italic; margin: 10px 0;">
            <i class="fas fa-info-circle"></i> Cliquez sur une ligne d'archer pour ouvrir sa carte de score.
        </p>
        <table class="details-table" id="detail-table">
            <thead><tr></tr></thead>
            <tbody id="detail-table-body"></tbody>
        </table>
    </div>
</div>

<script>
let checkInterval;
let currentSession = null;
let availableSessions = [];
let isFirstLoad = true;
let nbArrowsToShow = 6;
let tourTypeName = '';

const WRITE_SCORECARD_URL = 'ViewScoreCard.php';

function formatDateTime(date) {
    return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function updateLastCheck() {
    const now = new Date();
    $('.last-check-info').remove();
    $('#session-selector').append(
        $('<span>').addClass('last-check-info')
            .html(`<i class="fas fa-sync-alt"></i> Dernière vérification: ${formatDateTime(now)}`)
    );
}

function findLastActiveSessionFromData(sessionsData, numSessionsMax) {
    for (let session = numSessionsMax; session >= 1; session--) {
        if (sessionsData[session] && !sessionsData[session].empty && sessionsData[session].totalTargets > 0 && sessionsData[session].nbArchers > 0) {
            return session;
        }
    }
    return null;
}

function updateSessionSelector(sessionsData, numSessionsMax) {
    const selector = $('#session-selector');
    selector.empty();
    $('.last-check-info').remove();
    
    for (let session = 1; session <= numSessionsMax; session++) {
        const sessionData = sessionsData[session] || { empty: true, totalTargets: 0 };
        const hasData = sessionData && !sessionData.empty && sessionData.totalTargets > 0;
        
        const sessionBtn = $('<button>')
            .addClass('session-btn' + (currentSession == session ? ' active' : ''))
            .text('Départ ' + session)
            .click(function() {
                selectSession(session);
            });
        
        if (hasData) sessionBtn.css('border-left', '4px solid #28a745');
        else if (sessionData && sessionData.empty) sessionBtn.css('opacity', '0.6');
        
        selector.append(sessionBtn);
    }
    
    availableSessions = Object.keys(sessionsData).filter(s => s <= numSessionsMax);
    
    if (!currentSession || currentSession < 1 || currentSession > numSessionsMax || !sessionsData[currentSession]) {
        let activeSession = findLastActiveSessionFromData(sessionsData, numSessionsMax) || 1;
        if (currentSession != activeSession) {
            currentSession = activeSession;
            updateSessionSelectorUI();
            stopAutoChecking();
            startAutoChecking();
            return;
        }
    }
    updateLastCheck();
}

function startAutoChecking() {
    checkTargets();
    if (checkInterval) clearInterval(checkInterval);
    checkInterval = setInterval(checkTargets, 5000);
}

function stopAutoChecking() {
    if (checkInterval) { clearInterval(checkInterval); checkInterval = null; }
}

function selectSession(session) {
    if (currentSession == session) return;
    currentSession = session;
    updateSessionSelectorUI();
    stopAutoChecking();
    startAutoChecking();
}

function updateSessionSelectorUI() {
    $('#session-selector .session-btn').removeClass('active');
    $(`#session-selector .session-btn:contains('Départ ${currentSession}')`).addClass('active');
    updateLastCheck();
}

function updateSessionStats(stats) {
    const statsContainer = $('#session-stats');
    statsContainer.empty();
    const sessionStats = stats.sessionStats?.[currentSession] || {};
    
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value">Départ ${currentSession}</div><div class="stat-label">Départ actif</div>`));
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value">${sessionStats.totalTargets || 0}</div><div class="stat-label">Cibles</div>`));
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value" style="color: #28a745;">${sessionStats.greenTargets || 0}</div><div class="stat-label">Vertes</div>`));
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value" style="color: #ffc107;">${sessionStats.yellowTargets || 0}</div><div class="stat-label">Jaunes</div>`));
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value" style="color: #dc3545;">${sessionStats.redTargets || 0}</div><div class="stat-label">Rouges</div>`));
    statsContainer.append($('<div>').addClass('stat-item').html(`<div class="stat-value" style="color: #17a2b8;">${sessionStats.blueTargets || 0}</div><div class="stat-label">Bleues</div>`));
}

function createBatteryIndicator(batteryInfo) {
    if (!batteryInfo) {
        const bi = $('<div>').addClass('battery-indicator');
        bi.append($('<div>').addClass('battery-level none'), $('<div>').addClass('battery-percent').text('NA'));
        return bi.attr('title', 'Aucune donnée');
    }
    const bi = $('<div>').addClass('battery-indicator');
    let cls = batteryInfo.status, txt = batteryInfo.absoluteLevel + '%', h = '20%';
    if (batteryInfo.isCharging) {
        cls = 'charging'; h = '100%';
        bi.append($('<div>').addClass('charging-icon').html('⚡'));
    } else {
        if (batteryInfo.status === 'high') h = '80%';
        else if (batteryInfo.status === 'medium') h = '50%';
        else if (batteryInfo.status === 'low') h = '25%';
        else if (batteryInfo.status === 'critical') h = '10%';
    }
    bi.append($('<div>').addClass('battery-level ' + cls).css('height', h), $('<div>').addClass('battery-percent').text(txt));
    if (!batteryInfo.isCharging && (batteryInfo.status === 'low' || batteryInfo.status === 'critical')) bi.append($('<div>').addClass('battery-warning').html('!'));
    return bi.attr('title', batteryInfo.isCharging ? `⚡ En charge: ${batteryInfo.absoluteLevel}%` : `Batterie: ${batteryInfo.level}%`);
}

function checkTargets() {
    if (currentSession === null && isFirstLoad) { initializeSession(); return; }
    if (currentSession === null) return;
    
    $.ajax({
        url: 'ajax_check_fleches_session.php',
        type: 'POST',
        dataType: 'json',
        data: { TourId: <?php echo isset($_SESSION['TourId']) ? $_SESSION['TourId'] : '0'; ?>, session: currentSession },
        success: function(response) {
            if (response.success) {
                if (response.nbArrowsToShow) { nbArrowsToShow = response.nbArrowsToShow; tourTypeName = response.tourTypeName || ''; }
                const numSessionsMax = response.numSessionsMax || 1;
                if (response.sessionsData && Object.keys(response.sessionsData).length > 0) updateSessionSelector(response.sessionsData, numSessionsMax);
                displayTargets(response.targets);
                updateSessionStats(response.stats || {});
                updateLastCheck();
                isFirstLoad = false;
            } else {
                showCustomNotification('Erreur: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            let msg = 'Erreur réseau';
            if (status === 'parsererror') msg = 'Erreur de parsing JSON';
            else if (xhr.status === 404) msg = 'Fichier ajax_check_fleches_session.php non trouvé';
            else if (xhr.status === 500) msg = 'Erreur serveur (500)';
            showCustomNotification(msg, 'error');
            $('#targets-container').html(`<div class="no-targets-message"><h3 style="color: #dc3545;">Erreur</h3><p>${msg}</p></div>`);
        }
    });
}

function initializeSession() {
    $.ajax({
        url: 'ajax_check_fleches_session.php',
        type: 'POST',
        dataType: 'json',
        data: { TourId: <?php echo isset($_SESSION['TourId']) ? $_SESSION['TourId'] : '0'; ?>, get_sessions_list: true },
        success: function(response) {
            if (response.nbArrowsToShow) { nbArrowsToShow = response.nbArrowsToShow; tourTypeName = response.tourTypeName || ''; }
            const numSessionsMax = response.numSessionsMax || 1;
            if (response.success && response.sessionsData) {
                updateSessionSelector(response.sessionsData, numSessionsMax);
                currentSession = response.lastActiveSession || findLastActiveSessionFromData(response.sessionsData, numSessionsMax) || 1;
                updateSessionSelectorUI();
                checkTargets();
            } else {
                currentSession = 1;
                checkTargets();
            }
        },
        error: function() { currentSession = 1; checkTargets(); }
    });
}

function displayTargets(targets) {
    const container = $('#targets-container');
    container.empty();
    if (targets.length === 0) {
        container.html(`<div class="no-targets-message"><h3>Aucune cible</h3></div>`);
        return;
    }
    targets.forEach(target => {
        const card = $('<div>').addClass('target-card ' + target.status);
        card.append(createBatteryIndicator(target.battery));
        card.append($('<div>').addClass('target-number').text(target.targetNumber));
        card.append($('<div>').addClass('target-session').text('Départ ' + target.session));
        card.append($('<div>').addClass('target-archers').html(`<i class="fas fa-user"></i> ${target.archerCount || 0} archer(s)`));
        if (target.status === 'yellow' || target.status === 'red') card.append($('<div>').addClass('target-warning').html('⚠️'));
        card.click(() => showTargetDetails(target));
        container.append(card);
    });
}

function showTargetDetails(target) {
    $('#detail-target-number').text(target.targetNumber);
    $('#detail-session').text(target.session);
    $('#details-panel').addClass('active');
    
    let statusText = '', statusClass = '';
    switch(target.status) {
        case 'green': statusText = '✓ Tous les scores des archers sont à jour'; statusClass = 'text-success'; break;
        case 'yellow': statusText = '⚠️ Scores en cours ou décalage entre archers'; statusClass = 'text-warning'; break;
        case 'red': statusText = '✗ Cible en retard'; statusClass = 'text-danger'; break;
        case 'blue': statusText = 'ℹ️ Cible sans données ou vide'; statusClass = 'text-info'; break;
    }
    $('#detail-status').html(`<div class="alert ${statusClass.replace('text-', 'alert-')}">${statusText}</div>`);
    
    const arrowsLabel = nbArrowsToShow === 3 ? '3 dernières flèches' : '6 dernières flèches';
    $('#detail-table thead tr').html(`
        <th>Départ</th><th>Cible</th><th>Lettre</th><th>Archer</th><th>Licence</th>
        <th>N° Volée S1</th><th>${arrowsLabel} S1</th><th>Total S1</th>
        <th>N° Volée S2</th><th>${arrowsLabel} S2</th><th>Total S2</th>
        <th>Total</th><th>Statut</th>
    `);
    
    const tbody = $('#detail-table-body');
    tbody.empty();
    
    if (target.archers && target.archers.length > 0) {
        target.archers.forEach(archer => {
            const row = $('<tr>');
            if (archer.quId) {
                row.addClass('archer-row-clickable').css('cursor', 'pointer').attr('title', 'Cliquer pour ouvrir la carte');
                row.click(function(e) {
                    e.stopPropagation();
					// Dans ScoreCibles.php, fonction showTargetDetails
					const url = 'WriteScoreCard.php?QuId=' + encodeURIComponent(archer.quId);
					window.open(url, '_self');
                });
            }
            
            row.append($('<td>').text(archer.session || '-'));
            row.append($('<td>').text(archer.targetNumber || '-'));
            row.append($('<td>').text(archer.targetLetter || '-'));
            row.append($('<td>').text(archer.archerName || '-'));
            row.append($('<td>').text(archer.license || '-'));
            row.append($('<td>').html(`<span class="volley-number">${archer.volleyNumberD1 || 0}</span>`));
            row.append($('<td>').html(`<span class="badge badge-secondary">${archer.lastScoreD1 || '-'}</span>`));
            row.append($('<td>').html(`<span class="score-total d1">${archer.totalScoreD1 || 0}</span>`));
            row.append($('<td>').html(`<span class="volley-number">${archer.volleyNumberD2 || 0}</span>`));
            row.append($('<td>').html(`<span class="badge badge-secondary">${archer.lastScoreD2 || '-'}</span>`));
            row.append($('<td>').html(`<span class="score-total d2">${archer.totalScoreD2 || 0}</span>`));
            row.append($('<td>').html(`<span class="score-total combined">${archer.totalScore || 0}</span>`));
            
            let arrowClass = 'ok', statusTextDetails = 'OK';
            const totalArrows = (archer.arrowsD1 || 0) + (archer.arrowsD2 || 0);
            if (totalArrows % 3 !== 0) { arrowClass = 'error'; statusTextDetails = 'A vérifier'; }
            else if (target.hasDifferentArrowCounts && archer.arrowsTotal !== target.averageArrows) { arrowClass = 'warning'; statusTextDetails = 'Scores en cours'; }
            row.append($('<td>').html(`<span class="badge badge-${arrowClass}">${statusTextDetails}</span>`));
            
            tbody.append(row);
        });
    } else {
        tbody.html('<tr><td colspan="13" style="text-align: center;">Aucun archer</td></tr>');
    }
    
    $('html, body').animate({ scrollTop: $('#details-panel').offset().top - 20 }, 500);
}

function showCustomNotification(message, type) {
    const n = $('<div>').css({ position: 'fixed', top: '20px', right: '20px', padding: '15px', borderRadius: '5px', color: 'white', backgroundColor: type === 'success' ? '#28a745' : '#dc3545', zIndex: 9999 }).text(message);
    $('body').append(n);
    setTimeout(() => n.fadeOut(), 5000);
}

$(document).ready(function() {
    startAutoChecking();
    updateLastCheck();
    $(document).click(function(event) {
        if (!$(event.target).closest('#details-panel').length && !$(event.target).closest('.target-card').length) $('#details-panel').removeClass('active');
    });
    $(window).on('beforeunload', stopAutoChecking);
});
</script>

<?php include('Common/Templates/tail.php'); ?>