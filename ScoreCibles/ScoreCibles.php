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
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .target-card {
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        color: white;
        font-weight: bold;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    
    .target-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .target-session {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 5px;
        background-color: rgba(255,255,255,0.2);
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-block;
    }
    
    .target-arrows {
        font-size: 18px;
        margin: 10px 0;
        background-color: rgba(255,255,255,0.2);
        padding: 5px;
        border-radius: 4px;
    }
    
    .target-archers {
        font-size: 12px;
        margin-top: 8px;
        opacity: 0.9;
    }
    
    .target-warning {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(255,255,255,0.3);
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
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
    
    .btn-control {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        margin-right: 10px;
        transition: all 0.3s;
    }
    
    .btn-start {
        background-color: #28a745;
        color: white;
    }
    
    .btn-start:hover {
        background-color: #218838;
    }
    
    .btn-stop {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-stop:hover {
        background-color: #c82333;
    }
    
    .btn-refresh {
        background-color: #17a2b8;
        color: white;
    }
    
    .btn-refresh:hover {
        background-color: #138496;
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
    
    .default-session-info {
        color: #2c5f2d;
        font-weight: bold;
        margin-left: 10px;
        padding: 5px 10px;
        background-color: #e8f5e9;
        border-radius: 4px;
        font-size: 14px;
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
</style>

<div class="verification-container">
    
    <div class="controls">
        <div class="refresh-info">
            <span id="status-indicator" class="status-indicator status-active"></span>
            <span id="status-text">Actualisation toutes les 5 secondes</span>
            <span id="last-check" style="float: right;"></span>
        </div>
        
        <div>
            <button id="btn-start" class="btn-control btn-start" disabled>
                ▶️ Démarrer
            </button>
            <button id="btn-stop" class="btn-control btn-stop">
                ⏸️ Pause
            </button>
            <button id="btn-refresh" class="btn-control btn-refresh">
                🔄 Rafraîchir maintenant
            </button>
        </div>
        
        <h3 style="margin-top: 20px; margin-bottom: 10px;">Sélectionnez un départ :</h3>
        <div class="session-selector" id="session-selector">
            <!-- Les boutons de session seront générés dynamiquement -->
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
let checkInterval;
let isChecking = true;
let lastCheckTime = null;
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
    lastCheckTime = now;
    $('#last-check').text('Dernière vérification: ' + formatDateTime(now));
}

// Fonction pour démarrer la vérification automatique
function startChecking() {
    if (!isChecking) {
        isChecking = true;
        $('#status-indicator').removeClass('status-paused').addClass('status-active');
        $('#status-text').text('Vérification active - Actualisation toutes les 5 secondes');
        $('#btn-start').prop('disabled', true);
        $('#btn-stop').prop('disabled', false);
        checkTargets(); // Vérifier immédiatement
        checkInterval = setInterval(checkTargets, 5000); // 5 secondes
    }
}

// Fonction pour arrêter la vérification automatique
function stopChecking() {
    if (isChecking) {
        isChecking = false;
        clearInterval(checkInterval);
        $('#status-indicator').removeClass('status-active').addClass('status-paused');
        $('#status-text').text('Vérification en pause');
        $('#btn-start').prop('disabled', false);
        $('#btn-stop').prop('disabled', true);
    }
}

// Fonction pour mettre à jour le sélecteur de session
function updateSessionSelector(sessionsData) {
    const selector = $('#session-selector');
    selector.empty();
    
    // Supprimer l'info par défaut si elle existe
    $('.default-session-info').remove();
    
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
    
    // Ajouter une info si c'est la session 1 par défaut
    if (currentSession === '1' && availableSessions.includes('1')) {
        selector.append(
            $('<span>').addClass('default-session-info')
                .text('Départ 1 active par défaut')
        );
    }
}

// Fonction pour sélectionner une session
function selectSession(session) {
    currentSession = session;
    updateSessionSelectorUI();
    filterTargets();
}

// Fonction pour mettre à jour l'UI du sélecteur de session
function updateSessionSelectorUI() {
    $('#session-selector .session-btn').removeClass('active');
    $(`#session-selector .session-btn:contains('Session ${currentSession}')`).addClass('active');
    
    // Mettre à jour l'info par défaut
    $('.default-session-info').remove();
    if (currentSession === '1') {
        $('#session-selector').append(
            $('<span>').addClass('default-session-info')
                .text('Session 1 active par défaut')
        );
    }
}

// Fonction pour afficher les statistiques de session
function updateSessionStats(stats) {
    const statsContainer = $('#session-stats');
    statsContainer.empty();
    
    // Statistiques pour la session sélectionnée uniquement
    const sessionStats = stats.sessionStats?.[currentSession] || {};
    
    $('<div>').addClass('stat-item').html(`
        <div class="stat-value">Départ ${currentSession}</div>
        <div class="stat-label">Départ active</div>
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
}

// Fonction pour filtrer les cibles selon la session sélectionnée
function filterTargets() {
    checkTargets();
}

// Fonction principale pour vérifier les cibles
function checkTargets() {
    $.ajax({
        url: 'ajax_check_fleches_session.php',
        type: 'POST',
        dataType: 'json',
        data: {
            TourId: <?php echo $TourId; ?>,
            session: currentSession
        },
        success: function(response) {
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
                showCustomNotification('Erreur: ' + response.message, 'error');
            }
        },
        error: function() {
            showCustomNotification('Erreur réseau lors de la vérification', 'error');
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
        
        // Numéro de cible
        $('<div>').addClass('target-number').text(target.targetNumber).appendTo(targetCard);
        
        // Session
        $('<div>').addClass('target-session').text('Départ ' + target.session).appendTo(targetCard);
        
        // Nombre de flèches
        if (target.averageArrows > 0) {
            $('<div>').addClass('target-arrows')
                .html(`<i class="fas fa-bullseye"></i> ${target.averageArrows} flèches`)
                .appendTo(targetCard);
        }
        
        // Nombre d'archers
        if (target.archerCount > 0) {
            $('<div>').addClass('target-archers')
                .html(`<i class="fas fa-users"></i> ${target.archerCount} archer(s)`)
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
            
            // Formater les numéros de volée
            const volleyD1 = archer.volleyNumberD1 > 0 ? archer.volleyNumberD1 : '-';
            const volleyD2 = archer.volleyNumberD2 > 0 ? archer.volleyNumberD2 : '-';
            
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
            row.append($('<td>').html(`<span class="badge badge-info">${volleyD1}</span>`));
            row.append($('<td>').html(`<span class="badge badge-secondary">${lastScoreD1}</span>`));
            row.append($('<td>').html(`<span class="score-total d1">${totalScoreD1}</span>`));
            row.append($('<td>').html(`<span class="badge badge-info">${volleyD2}</span>`));
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

// Initialisation lorsque le document est prêt
$(document).ready(function() {
    // Démarrer la vérification automatique (avec 5 secondes)
    startChecking();
    
    // Gestion des boutons
    $('#btn-start').click(startChecking);
    $('#btn-stop').click(stopChecking);
    $('#btn-refresh').click(function() {
        checkTargets();
        showCustomNotification('Vérification manuelle effectuée');
    });
    
    // Fermer le panneau de détails en cliquant à l'extérieur
    $(document).click(function(event) {
        if (!$(event.target).closest('#details-panel').length && 
            !$(event.target).closest('.target-card').length) {
            $('#details-panel').removeClass('active');
        }
    });
    
    // Mettre à jour l'heure de la dernière vérification
    updateLastCheck();
    
    // Faire une première vérification immédiate
    setTimeout(() => {
        checkTargets();
    }, 100);
});
</script>

<?php include('Common/Templates/tail.php'); ?>