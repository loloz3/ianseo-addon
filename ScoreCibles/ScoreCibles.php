<?php
/**
 * Page de suivi des cibles - Version avec sélection de session
 * Adapté pour afficher 3 ou 6 dernières flèches selon le type de tournoi (Indoor/Outdoor)
 *
 * VERSION COMPATIBLE NAVIGATEURS ANCIENS (Safari 12 / iPad mini 2 iOS 12.5.x) :
 *  - JavaScript en ES5 (pas de ?., let/const, flèches, template literals)
 *  - CSS sans "gap" sur flexbox (non supporté avant Safari 14.1)
 *  - Pas d'animations infinies (lourdes sur un vieil iPad)
 *  - Rendu des cibles en une seule injection HTML (plus rapide)
 *  - Pas de requêtes AJAX qui s'empilent, reprise au retour sur la page (iOS met les timers en pause)
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

    /* Sélecteur de départs : marges à la place de "gap" (flex gap non supporté Safari < 14.1) */
    .session-selector { display: flex; flex-wrap: wrap; margin-bottom: 5px; align-items: center; }
    .session-btn { -webkit-appearance: none; appearance: none; font-family: inherit; font-size: 16px; min-height: 44px; padding: 10px 20px; margin: 0 10px 10px 0; border: 2px solid #6c757d; background-color: white; border-radius: 5px; cursor: pointer; font-weight: bold; min-width: 80px; text-align: center; color: #212529; }
    .session-btn.active { background-color: #2c5f2d; color: white; border-color: #2c5f2d; }
    .session-btn.has-data { border-left: 4px solid #28a745; }
    .session-btn.is-empty { opacity: 0.6; }

    /* Grille des cibles ("grid-gap" + "gap" : Safari 12 comprend grid-gap) */
    .targets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); grid-gap: 10px; gap: 10px; margin-top: 20px; }
    .target-card { border-radius: 6px; padding: 10px; text-align: center; color: white; font-weight: bold; position: relative; overflow: hidden; cursor: pointer; min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; -webkit-tap-highlight-color: rgba(0,0,0,0.1); }
    .target-card.green { background: #28a745; background: linear-gradient(135deg, #28a745, #218838); border: 2px solid #1e7e34; }
    .target-card.yellow { background: #ffc107; background: linear-gradient(135deg, #ffc107, #e0a800); border: 2px solid #d39e00; color: #212529; }
    .target-card.red { background: #dc3545; background: linear-gradient(135deg, #dc3545, #c82333); border: 2px solid #bd2130; }
    .target-card.blue { background: #17a2b8; background: linear-gradient(135deg, #17a2b8, #138496); border: 2px solid #117a8b; }
    .target-number { font-size: 24px; font-weight: bold; margin-bottom: 3px; }
    .target-session { font-size: 12px; opacity: 0.9; margin-bottom: 5px; background-color: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 8px; display: inline-block; }
    .target-archers { font-size: 12px; margin-top: 5px; opacity: 0.9; background-color: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 12px; display: inline-block; }
    .target-warning { position: absolute; top: 3px; right: 5px; background-color: rgba(255,255,255,0.3); border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; }

    /* Effet survol uniquement sur appareils à souris (évite le survol "collé" sur iPad) */
    @media (hover: hover) {
        .session-btn:hover { background-color: #e9ecef; }
        .session-btn.active:hover { background-color: #2c5f2d; }
        .target-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.3); }
    }

    .details-panel { background-color: white; border-radius: 8px; padding: 20px; margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: none; }
    .details-panel.active { display: block; }
    .detail-close { -webkit-appearance: none; appearance: none; font-family: inherit; font-size: 15px; min-height: 40px; padding: 6px 16px; border: 2px solid #6c757d; background-color: white; border-radius: 5px; cursor: pointer; font-weight: bold; color: #495057; float: right; }
    .details-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .details-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .details-table th { background-color: #2c5f2d; color: white; padding: 10px; text-align: left; }
    .details-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
    .details-table tr.archer-row-clickable { cursor: pointer; }
    .details-table tr.archer-row-clickable td:first-child::before { content: '🔗 '; font-size: 11px; opacity: 0.6; }
    @media (hover: hover) {
        .details-table tr:hover { background-color: #f8f9fa; }
        .details-table tr.archer-row-clickable:hover { background-color: #d1ecf1 !important; box-shadow: inset 3px 0 0 #17a2b8; }
    }
    .arrow-count { font-weight: bold; padding: 2px 8px; border-radius: 4px; display: inline-block; }
    .arrow-count.ok { background-color: #d4edda; color: #155724; }
    .arrow-count.warning { background-color: #fff3cd; color: #856404; }
    .arrow-count.error { background-color: #f8d7da; color: #721c24; }
    .refresh-info { background-color: #d1ecf1; border: 1px solid #bee5eb; border-left: 5px solid #17a2b8; color: #0c5460; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; display: none; }

    /* Légende : marges à la place de "gap" */
    .legend { display: flex; flex-wrap: wrap; margin: 20px 0; padding: 15px 15px 5px 15px; background-color: #f8f9fa; border-radius: 5px; }
    .legend-item { display: flex; align-items: center; margin: 0 20px 10px 0; }
    .legend-color { width: 20px; height: 20px; border-radius: 4px; margin-right: 8px; }

    .session-stats { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; }
    .stat-item { text-align: center; padding: 0 15px; }
    .stat-value { font-size: 24px; font-weight: bold; color: #2c5f2d; }
    .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; margin-top: 5px; }
    .no-targets-message { grid-column: 1 / -1; text-align: center; padding: 40px; color: #6c757d; font-style: italic; background-color: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6; }
    .current-session-badge { background-color: #2c5f2d; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; margin-left: 10px; font-size: 16px; display: inline-block; }
    .last-check-info { color: #2c5f2d; font-weight: bold; margin: 0 0 10px 10px; padding: 5px 10px; background-color: #e8f5e9; border-radius: 4px; font-size: 14px; border: 1px solid #c3e6cb; }
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

    /* Indicateur de batterie (animations infinies supprimées : trop lourdes sur iPad mini 2) */
    .battery-indicator { position: absolute; top: 5px; left: 5px; width: 28px; height: 36px; border: 2px solid rgba(255, 255, 255, 0.7); border-radius: 4px; background: rgba(0, 0, 0, 0.3); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; color: white; z-index: 10; overflow: hidden; }
    .battery-level { position: absolute; bottom: 0; left: 0; width: 100%; border-radius: 2px; }
    .battery-level.high { background: #4CAF50; background: linear-gradient(to top, #4CAF50, #8BC34A); height: 80%; }
    .battery-level.medium { background: #FFC107; background: linear-gradient(to top, #FFC107, #FF9800); height: 50%; }
    .battery-level.low { background: #FF9800; background: linear-gradient(to top, #FF9800, #FF5722); height: 25%; }
    .battery-level.critical { background: #F44336; background: linear-gradient(to top, #F44336, #D32F2F); height: 10%; }
    .battery-level.charging { background: #2196F3; background: linear-gradient(to top, #2196F3, #1976D2); height: 100%; }
    .battery-level.none { background: rgba(128, 128, 128, 0.5); height: 20%; }
    .battery-percent { position: relative; z-index: 2; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.9); font-size: 9px; font-weight: bold; color: white; padding: 1px; background-color: rgba(0, 0, 0, 0.3); border-radius: 2px; }
    .battery-warning { position: absolute; top: -2px; right: -2px; background-color: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; font-size: 8px; z-index: 11; }
    .charging-icon { position: absolute; top: 2px; right: 2px; color: #FFEB3B; font-size: 10px; z-index: 11; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8); }
    .battery-indicator::before { content: ''; position: absolute; top: -6px; left: 50%; -webkit-transform: translateX(-50%); transform: translateX(-50%); width: 10px; height: 4px; background: rgba(255, 255, 255, 0.7); border-radius: 2px 2px 0 0; }
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
        <button type="button" id="detail-close" class="detail-close">&#10005; Fermer</button>
        <h3>Détails de la cible <span id="detail-target-number"></span> (Départ <span id="detail-session"></span>)</h3>
        <div id="detail-status"></div>
        <p style="font-size: 13px; color: #6c757d; font-style: italic; margin: 10px 0;">
            <i class="fas fa-info-circle"></i> Touchez une ligne d'archer pour ouvrir sa carte de score.
        </p>
        <div class="details-scroll">
            <table class="details-table" id="detail-table">
                <thead><tr></tr></thead>
                <tbody id="detail-table-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
/* ===========================================================================
 * JavaScript volontairement écrit en ES5 (compatible Safari 12 / iOS 12)
 * =========================================================================== */
var TOUR_ID = <?php echo isset($_SESSION['TourId']) ? intval($_SESSION['TourId']) : 0; ?>;
var AJAX_URL = 'ajax_check_fleches_session.php';
var WRITE_SCORECARD_URL = 'WriteScoreCard.php';
var REFRESH_MS = 5000;

var checkInterval = null;
var currentSession = null;
var isFirstLoad = true;
var nbArrowsToShow = 6;
var tourTypeName = '';
var ajaxBusy = false;       // évite d'empiler les requêtes si le réseau est lent
var reqCounter = 0;         // permet d'ignorer les réponses périmées
var targetsByNumber = {};   // cibles de la session affichée (pour le panneau de détails)

function pad2(n) {
    return n < 10 ? '0' + n : '' + n;
}

function formatDateTime(d) {
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
}

function esc(s) {
    return String(s === null || s === undefined ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function updateLastCheck() {
    $('.last-check-info').remove();
    $('#session-selector').append(
        $('<span>').addClass('last-check-info')
            .html('<i class="fas fa-sync-alt"></i> Dernière vérification: ' + formatDateTime(new Date()))
    );
}

function findLastActiveSessionFromData(sessionsData, numSessionsMax) {
    for (var s = numSessionsMax; s >= 1; s--) {
        var sd = sessionsData[s];
        if (sd && !sd.empty && sd.nbArchers > 0) {
            return s;
        }
    }
    return null;
}

function updateSessionSelector(sessionsData, numSessionsMax) {
    var html = '';
    for (var s = 1; s <= numSessionsMax; s++) {
        var sd = sessionsData[s] || { empty: true, nbArchers: 0 };
        var cls = 'session-btn';
        if (currentSession == s) cls += ' active';
        if (sd.empty) cls += ' is-empty';
        else if (sd.nbArchers > 0) cls += ' has-data';
        html += '<button type="button" class="' + cls + '" data-session="' + s + '">Départ ' + s + '</button>';
    }
    $('#session-selector').html(html);

    if (!currentSession || currentSession < 1 || currentSession > numSessionsMax || !sessionsData[currentSession]) {
        var activeSession = findLastActiveSessionFromData(sessionsData, numSessionsMax) || 1;
        if (currentSession != activeSession) {
            currentSession = activeSession;
            updateSessionSelectorUI();
            stopAutoChecking();
            startAutoChecking();
            return false;   // une nouvelle vérification est lancée, on abandonne cette réponse
        }
    }
    updateLastCheck();
    return true;
}

function startAutoChecking() {
    checkTargets(true);
    if (checkInterval) clearInterval(checkInterval);
    checkInterval = setInterval(function () { checkTargets(false); }, REFRESH_MS);
}

function stopAutoChecking() {
    if (checkInterval) {
        clearInterval(checkInterval);
        checkInterval = null;
    }
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
    $('#session-selector .session-btn[data-session="' + currentSession + '"]').addClass('active');
    updateLastCheck();
}

function updateSessionStats(stats) {
    var ss = (stats && stats.sessionStats && stats.sessionStats[currentSession]) ? stats.sessionStats[currentSession] : {};
    var html = '';
    html += '<div class="stat-item"><div class="stat-value">Départ ' + esc(currentSession) + '</div><div class="stat-label">Départ actif</div></div>';
    html += '<div class="stat-item"><div class="stat-value">' + (ss.totalTargets || 0) + '</div><div class="stat-label">Cibles</div></div>';
    html += '<div class="stat-item"><div class="stat-value" style="color: #28a745;">' + (ss.greenTargets || 0) + '</div><div class="stat-label">Vertes</div></div>';
    html += '<div class="stat-item"><div class="stat-value" style="color: #ffc107;">' + (ss.yellowTargets || 0) + '</div><div class="stat-label">Jaunes</div></div>';
    html += '<div class="stat-item"><div class="stat-value" style="color: #dc3545;">' + (ss.redTargets || 0) + '</div><div class="stat-label">Rouges</div></div>';
    html += '<div class="stat-item"><div class="stat-value" style="color: #17a2b8;">' + (ss.blueTargets || 0) + '</div><div class="stat-label">Bleues</div></div>';
    $('#session-stats').html(html);
}

function batteryHtml(b) {
    if (!b) {
        return '<div class="battery-indicator" title="Aucune donnée"><div class="battery-level none"></div><div class="battery-percent">NA</div></div>';
    }
    var cls = b.status, h = '20%', extra = '', title, warn = '';
    if (b.isCharging) {
        cls = 'charging';
        h = '100%';
        extra = '<div class="charging-icon">⚡</div>';
        title = '⚡ En charge: ' + b.absoluteLevel + '%';
    } else {
        if (b.status === 'high') h = '80%';
        else if (b.status === 'medium') h = '50%';
        else if (b.status === 'low') h = '25%';
        else if (b.status === 'critical') h = '10%';
        title = 'Batterie: ' + b.level + '%';
        if (b.status === 'low' || b.status === 'critical') warn = '<div class="battery-warning">!</div>';
    }
    return '<div class="battery-indicator" title="' + esc(title) + '">' + extra +
           '<div class="battery-level ' + cls + '" style="height:' + h + ';"></div>' +
           '<div class="battery-percent">' + b.absoluteLevel + '%</div>' + warn + '</div>';
}

function showError(msg) {
    $('.js-notif').remove();
    var n = $('<div>').addClass('js-notif').css({
        position: 'fixed', top: '20px', right: '20px', padding: '15px',
        borderRadius: '5px', color: 'white', backgroundColor: '#dc3545', zIndex: 9999
    }).text(msg);
    $('body').append(n);
    setTimeout(function () { n.fadeOut(function () { n.remove(); }); }, 5000);
}

function checkTargets(force) {
    if (currentSession === null && isFirstLoad) { initializeSession(); return; }
    if (currentSession === null) return;
    if (ajaxBusy && !force) return;

    ajaxBusy = true;
    var myId = ++reqCounter;

    $.ajax({
        url: AJAX_URL,
        type: 'POST',
        dataType: 'json',
        timeout: 15000,
        data: { TourId: TOUR_ID, session: currentSession },
        success: function (response) {
            if (myId !== reqCounter) return;    // réponse périmée
            ajaxBusy = false;
            if (response && response.success) {
                if (response.nbArrowsToShow) {
                    nbArrowsToShow = response.nbArrowsToShow;
                    tourTypeName = response.tourTypeName || '';
                }
                var numSessionsMax = response.numSessionsMax || 1;
                if (response.sessionsData && Object.keys(response.sessionsData).length > 0) {
                    if (!updateSessionSelector(response.sessionsData, numSessionsMax)) return;
                }
                displayTargets(response.targets || []);
                updateSessionStats(response.stats || {});
                updateLastCheck();
                isFirstLoad = false;
            } else {
                showError('Erreur: ' + (response && response.message ? response.message : 'réponse invalide'));
            }
        },
        error: function (xhr, status) {
            if (myId !== reqCounter) return;
            ajaxBusy = false;
            var msg = 'Erreur réseau';
            if (status === 'parsererror') msg = 'Erreur de parsing JSON';
            else if (status === 'timeout') msg = 'Délai dépassé (réseau lent)';
            else if (xhr.status === 404) msg = 'Fichier ajax_check_fleches_session.php non trouvé';
            else if (xhr.status === 500) msg = 'Erreur serveur (500)';
            showError(msg);
            if (isFirstLoad) {
                $('#targets-container').html('<div class="no-targets-message"><h3 style="color: #dc3545;">Erreur</h3><p>' + esc(msg) + '</p></div>');
            }
        }
    });
}

function initializeSession() {
    if (ajaxBusy) return;
    ajaxBusy = true;
    var myId = ++reqCounter;

    $.ajax({
        url: AJAX_URL,
        type: 'POST',
        dataType: 'json',
        timeout: 15000,
        data: { TourId: TOUR_ID, get_sessions_list: 'true' },
        success: function (response) {
            if (myId !== reqCounter) return;
            ajaxBusy = false;
            if (response && response.nbArrowsToShow) {
                nbArrowsToShow = response.nbArrowsToShow;
                tourTypeName = response.tourTypeName || '';
            }
            var numSessionsMax = (response && response.numSessionsMax) || 1;
            if (response && response.success && response.sessionsData) {
                currentSession = response.lastActiveSession || findLastActiveSessionFromData(response.sessionsData, numSessionsMax) || 1;
                updateSessionSelector(response.sessionsData, numSessionsMax);
                updateSessionSelectorUI();
            } else {
                currentSession = 1;
            }
            checkTargets(true);
        },
        error: function () {
            if (myId !== reqCounter) return;
            ajaxBusy = false;
            currentSession = 1;
            checkTargets(true);
        }
    });
}

function displayTargets(targets) {
    var container = $('#targets-container');
    targetsByNumber = {};

    if (!targets || targets.length === 0) {
        container.html('<div class="no-targets-message"><h3>Aucune cible</h3></div>');
        return;
    }

    var html = [];
    for (var i = 0; i < targets.length; i++) {
        var t = targets[i];
        targetsByNumber[t.targetNumber] = t;
        html.push(
            '<div class="target-card ' + esc(t.status) + '" data-target="' + esc(t.targetNumber) + '">' +
                batteryHtml(t.battery) +
                '<div class="target-number">' + esc(t.targetNumber) + '</div>' +
                '<div class="target-session">Départ ' + esc(t.session) + '</div>' +
                '<div class="target-archers"><i class="fas fa-user"></i> ' + (t.archerCount || 0) + ' archer(s)</div>' +
                ((t.status === 'yellow' || t.status === 'red') ? '<div class="target-warning">⚠️</div>' : '') +
            '</div>'
        );
    }
    container.html(html.join(''));
}

function showTargetDetails(target) {
    $('#detail-target-number').text(target.targetNumber);
    $('#detail-session').text(target.session);
    $('#details-panel').addClass('active');

    var statusText = '', alertClass = '';
    switch (target.status) {
        case 'green':  statusText = '✓ Tous les scores des archers sont à jour'; alertClass = 'alert-success'; break;
        case 'yellow': statusText = '⚠️ Scores en cours ou décalage entre archers'; alertClass = 'alert-warning'; break;
        case 'red':    statusText = '✗ Cible en retard'; alertClass = 'alert-danger'; break;
        case 'blue':   statusText = 'ℹ️ Cible sans données ou vide'; alertClass = 'alert-info'; break;
    }
    $('#detail-status').html('<div class="alert ' + alertClass + '">' + statusText + '</div>');

    var arrowsLabel = (nbArrowsToShow === 3) ? '3 dernières flèches' : '6 dernières flèches';
    $('#detail-table thead tr').html(
        '<th>Départ</th><th>Cible</th><th>Lettre</th><th>Archer</th><th>Licence</th>' +
        '<th>N° Volée S1</th><th>' + arrowsLabel + ' S1</th><th>Total S1</th>' +
        '<th>N° Volée S2</th><th>' + arrowsLabel + ' S2</th><th>Total S2</th>' +
        '<th>Total</th><th>Statut</th>'
    );

    var rows = '';
    if (target.archers && target.archers.length > 0) {
        for (var i = 0; i < target.archers.length; i++) {
            var a = target.archers[i];

            var arrowClass = 'ok', statusTextDetails = 'OK';
            var totalArrows = (a.arrowsD1 || 0) + (a.arrowsD2 || 0);
            if (totalArrows % 3 !== 0) {
                arrowClass = 'error';
                statusTextDetails = 'A vérifier';
            } else if (target.hasDifferentArrowCounts && a.arrowsTotal !== target.averageArrows) {
                arrowClass = 'warning';
                statusTextDetails = 'Scores en cours';
            }

            rows += '<tr' + (a.quId ? ' class="archer-row-clickable" data-quid="' + esc(a.quId) + '"' : '') + '>' +
                '<td>' + esc(a.session || '-') + '</td>' +
                '<td>' + esc(a.targetNumber || '-') + '</td>' +
                '<td>' + esc(a.targetLetter || '-') + '</td>' +
                '<td>' + esc(a.archerName || '-') + '</td>' +
                '<td>' + esc(a.license || '-') + '</td>' +
                '<td><span class="volley-number">' + (a.volleyNumberD1 || 0) + '</span></td>' +
                '<td><span class="badge badge-secondary">' + esc(a.lastScoreD1 || '-') + '</span></td>' +
                '<td><span class="score-total d1">' + (a.totalScoreD1 || 0) + '</span></td>' +
                '<td><span class="volley-number">' + (a.volleyNumberD2 || 0) + '</span></td>' +
                '<td><span class="badge badge-secondary">' + esc(a.lastScoreD2 || '-') + '</span></td>' +
                '<td><span class="score-total d2">' + (a.totalScoreD2 || 0) + '</span></td>' +
                '<td><span class="score-total combined">' + (a.totalScore || 0) + '</span></td>' +
                '<td><span class="badge badge-' + arrowClass + '">' + statusTextDetails + '</span></td>' +
                '</tr>';
        }
    } else {
        rows = '<tr><td colspan="13" style="text-align: center;">Aucun archer</td></tr>';
    }
    $('#detail-table-body').html(rows);

    var top = $('#details-panel').offset().top - 20;
    $('html, body').animate({ scrollTop: top }, 300);
}

$(document).ready(function () {
    // Clic sur un bouton de départ
    $('#session-selector').on('click', '.session-btn', function () {
        selectSession(parseInt($(this).attr('data-session'), 10));
    });

    // Clic sur une cible
    $('#targets-container').on('click', '.target-card', function () {
        var t = targetsByNumber[$(this).attr('data-target')];
        if (t) showTargetDetails(t);
    });

    // Clic sur un archer : ouvre sa carte de score
    $('#detail-table-body').on('click', 'tr.archer-row-clickable', function (e) {
        e.stopPropagation();
        window.location.href = WRITE_SCORECARD_URL + '?QuId=' + encodeURIComponent($(this).attr('data-quid'));
    });

    // Bouton Fermer (plus fiable que le clic hors du panneau sur iOS)
    $('#detail-close').on('click', function (e) {
        e.stopPropagation();
        $('#details-panel').removeClass('active');
    });

    // Clic en dehors : ferme le panneau
    $(document).on('click', function (event) {
        if (!$(event.target).closest('#details-panel').length && !$(event.target).closest('.target-card').length) {
            $('#details-panel').removeClass('active');
        }
    });

    // iOS suspend les timers quand l'onglet est caché ou la page mise en cache (retour depuis la carte de score)
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopAutoChecking();
        else startAutoChecking();
    }, false);
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) startAutoChecking();
    }, false);
    window.addEventListener('pagehide', stopAutoChecking, false);

    startAutoChecking();
    updateLastCheck();
});
</script>

<?php include('Common/Templates/tail.php'); ?>
