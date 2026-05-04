<?php
/**
 * Script de simulation/débogage pour entrer automatiquement des flèches
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');

CheckTourSession(true);
checkACL(AclParticipants, AclReadWrite);

$TourId = isset($_SESSION['TourId']) ? (int)$_SESSION['TourId'] : 0;

if(!$TourId) {
    die('TourId invalide');
}

$IncludeJquery = true;
$IncludeJqueryUI = true;

include('Common/Templates/head.php');
?>

<style>
    .simulation-container { padding: 20px; max-width: 1400px; margin: 0 auto; }
    .title-section { background: linear-gradient(135deg, #2c5f2d, #1e4620); color: white; padding: 20px; border-radius: 10px; margin-bottom: 25px; text-align: center; }
    .controls-panel { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 25px; border: 1px solid #dee2e6; }
    .btn-sim { padding: 12px 25px; font-size: 16px; font-weight: bold; border-radius: 8px; margin: 5px; cursor: pointer; border: none; transition: transform 0.2s; }
    .btn-sim:hover { transform: translateY(-2px); }
    .btn-primary-sim { background: #007bff; color: white; }
    .btn-success-sim { background: #28a745; color: white; }
    .btn-warning-sim { background: #ffc107; color: #212529; }
    .btn-danger-sim { background: #dc3545; color: white; }
    .btn-info-sim { background: #17a2b8; color: white; }
    .btn-secondary-sim { background: #6c757d; color: white; }
    .btn-save-sim { background: #8B4513; color: white; }
    .btn-save-sim:hover { background: #6B3410; }
    .log-container { background: #1e1e1e; color: #d4d4d4; border-radius: 10px; padding: 15px; font-family: monospace; font-size: 12px; height: 300px; overflow-y: auto; margin-top: 20px; }
    .log-success { color: #4ec9b0; }
    .log-error { color: #f48771; }
    .log-info { color: #9cdcfe; }
    .log-warning { color: #dcdcaa; }
    .stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
    .stat-card { background: white; border-radius: 10px; padding: 15px; text-align: center; border-left: 4px solid #28a745; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .stat-card .stat-value { font-size: 32px; font-weight: bold; color: #2c5f2d; }
    .archers-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .archers-table th { background: #2c5f2d; color: white; padding: 12px; text-align: left; }
    .archers-table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; }
    .archers-table tr:hover { background: #f8f9fa; }
    .score-badge { 
        display: inline-block; 
        min-width: 28px;
        text-align: center;
        padding: 3px 6px; 
        border-radius: 4px; 
        font-size: 12px; 
        font-weight: bold; 
        margin: 1px;
        font-family: monospace;
    }
    .score-10, .score-9 { background: #ffd700; color: #000; }
    .score-8, .score-7 { background: #dc3545; color: #fff; }
    .score-6, .score-5 { background: #007bff; color: #fff; }
    .score-4, .score-3 { background: #000000; color: #fff; }
    .score-2, .score-1 { background: #6c757d; color: #fff; }
    .score-0 { background: #28a745; color: #fff; }
    .badge-info { background: #17a2b8; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .badge-secondary { background: #6c757d; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .tab-container { margin-top: 20px; }
    .tabs { display: flex; gap: 5px; border-bottom: 2px solid #dee2e6; margin-bottom: 15px; }
    .tab-btn { padding: 10px 20px; background: #f8f9fa; border: none; border-radius: 8px 8px 0 0; cursor: pointer; transition: all 0.2s; }
    .tab-btn:hover { background: #e9ecef; }
    .tab-btn.active { background: #2c5f2d; color: white; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .config-panel { background: #e8f5e9; border-radius: 10px; padding: 15px; margin-bottom: 20px; border: 1px solid #c8e6c9; }
    .config-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 10px; }
    .config-row label { font-weight: bold; min-width: 150px; }
    select, input { padding: 8px 12px; border-radius: 5px; border: 1px solid #ced4da; }
    .mt-2 { margin-top: 10px; }
    .btn-sm { padding: 5px 10px; font-size: 12px; }
    .btn { background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; }
    .score-total { font-weight: bold; font-size: 14px; }
    .text-center { text-align: center; }
    .arrow-cell { max-width: 150px; overflow-x: auto; white-space: nowrap; }
    .warning-banner {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        color: #856404;
        padding: 12px 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }
    .warning-banner .icon {
        font-size: 24px;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .saving {
        animation: pulse 0.5s ease-in-out;
    }
	.badge-warning {
		background: #ffc107;
		color: #212529;
		padding: 5px 10px;
		border-radius: 4px;
		font-weight: bold;
	}
	.badge-success {
		background: #28a745;
		color: white;
		padding: 5px 10px;
		border-radius: 4px;
		font-weight: bold;
}
</style>

<div class="simulation-container">
    <div class="title-section">
        <h1><i class="fas fa-robot"></i> Simulation / Débogage - Tir Automatique</h1>
        <p>Outil pour générer automatiquement des flèches pour tous les archers</p>
    </div>
    
    <div class="warning-banner">
        <div class="icon">⚠️</div>
        <div>
            <strong>ATTENTION :</strong> Cet outil modifie directement la base de données.
            <strong>Effectuez une sauvegarde avant toute modification !</strong><br>
            <button id="btn-save-backup" class="btn-sim btn-save-sim">
                💾 Sauvegarder la compétition
            </button>
        </div>
    </div>
    
    <div class="controls-panel">
        <h3>Contrôles de simulation</h3>
        
        <div class="config-panel">
            <h4>Configuration</h4>
            <div class="config-row">
                <label>Sélectionner les archers :</label>
                <select id="archer-selector">
                    <option value="all">Tous les archers</option>
                    <option value="session1">Départ 1 uniquement</option>
                    <option value="session2">Départ 2 uniquement</option>
                    <option value="co_only">Archers CO uniquement</option>
                    <option value="spot_only">Archers Spot uniquement</option>
                </select>
            </div>
            <div class="config-row">
                <label>Type de flèche :</label>
                <select id="arrow-type">
                    <option value="random">Aléatoire (réaliste)</option>
                    <option value="all_10">Toutes des 10</option>
                    <option value="all_9">Toutes des 9</option>
                    <option value="all_8">Toutes des 8</option>
                </select>
            </div>
            <div class="config-row">
                <label>Distance / Départ :</label>
                <select id="target-distance">
                    <option value="both">D1 et D2 (les deux)</option>
                    <option value="d1">D1 uniquement</option>
                    <option value="d2">D2 uniquement</option>
                </select>
            </div>
			<!-- <button id="btn-test-connection" class="btn-sim btn-info-sim">🔍 Tester connexion AJAX</button> -->
			<div class="config-row">
				<label>Type de tournoi détecté :</label>
				<span id="tournament-type-display" class="badge-info" style="padding: 5px 10px;">Chargement...</span>
			</div>
			<div class="config-row">
				<label>Configuration :</label>
				<span id="tournament-config-display" style="font-size: 12px; color: #666;">Chargement...</span>
			</div>
        </div>
        
        <div>
            <button id="btn-add-3" class="btn-sim btn-primary-sim">➕ 1 volée </button>
            <!-- <button id="btn-add-6" class="btn-sim btn-success-sim">➕ 2 volée</button> -->
            <button id="btn-complete" class="btn-sim btn-warning-sim">🎯 Compléter session (Toutes les flèches)</button>
            <button id="btn-reset" class="btn-sim btn-danger-sim">🗑️ Réinitialiser toutes les flèches</button>
            <button id="btn-refresh" class="btn-sim btn-secondary-sim">🔄 Rafraîchir</button>
        </div>
    </div>
    
    <div class="tab-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="archers">📋 Liste des archers</button>
            <button class="tab-btn" data-tab="log">📝 Journal</button>
        </div>
        
        <div id="tab-archers" class="tab-content active">
            <div style="overflow-x: auto;">
                <table class="archers-table" id="archers-table">
                    <thead>
                        <tr>
                            <th>Départ</th>
                            <th>Cible</th>
                            <th>Lettre</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>D1</th>
                            <th>Score D1</th>
                            <th>Dernière volée (points)</th>
                            <th>D2</th>
                            <th>Score D2</th>
                            <th>Dernière volée (points)</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="archers-table-body">
                        <tr><td colspan="12" class="text-center">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="tab-log" class="tab-content">
            <div class="log-container" id="log-container">
                <div class="log-entry log-info">📋 Prêt - Simulation prête</div>
                <div class="log-entry log-warning">⚠️ ATTENTION : Effectuez une sauvegarde avant toute modification !</div>
            </div>
            <button id="btn-clear-log" class="btn btn-sm mt-2">🗑️ Effacer le journal</button>
        </div>
    </div>
</div>

<script>
// Correction 1: Définir les variables globales correctement
var ajaxUrl = 'ajax_simulate.php';
var tourId = <?php echo (int)$TourId; ?>;
var saveUrl = '../../../Tournament/TournamentExport.php';

// Correction 2: Ajouter une fonction de vérification jQuery
if (typeof jQuery === 'undefined') {
    console.error('jQuery non chargé !');
}

function addLog(msg, type) {
    var div = $('<div>').addClass('log-entry log-' + (type || 'info'));
    div.html('[' + new Date().toLocaleTimeString() + '] ' + msg);
    $('#log-container').append(div);
    $('#log-container').scrollTop($('#log-container')[0].scrollHeight);
}

function letterToScore(letter) {
    var conversion = {
        'A': 0, 'M': 0,
        'B': 1, 'C': 2, 'D': 3, 'E': 4,
        'F': 5, 'G': 6, 'H': 7, 'I': 8, 'J': 9,
        'K': 10, 'L': 10, 'X': 10
    };
    return conversion[letter.toUpperCase()] || 0;
}

// Correction 3: Améliorer la fonction de sauvegarde avec meilleure gestion d'erreur
// Correction de la fonction de sauvegarde - Version qui force le téléchargement
function sauvegarderCompetition() {
    var btn = $('#btn-save-backup');
    btn.addClass('saving');
    btn.html('⏳ Préparation...');
    btn.prop('disabled', true);
    
    // Récupérer les paramètres actuels (si nécessaire)
    var params = $.param({
        TourId: tourId,
        // Ajoutez d'autres paramètres nécessaires pour l'export
        action: 'export' // si votre script en a besoin
    });
    
    // Créer un formulaire temporaire pour forcer le téléchargement
    var form = $('<form>', {
        method: 'GET',
        action: saveUrl,
        target: '_blank' // Ouvre dans un nouvel onglet pour le téléchargement
    });
    
    // Ajouter les paramètres au formulaire
    $('<input>').attr({
        type: 'hidden',
        name: 'TourId',
        value: tourId
    }).appendTo(form);
    
    // Ajouter d'autres paramètres si nécessaire
    $('<input>').attr({
        type: 'hidden',
        name: 'action',
        value: 'export'
    }).appendTo(form);
    
    // Soumettre le formulaire
    form.appendTo('body').submit();
    form.remove();
    
    addLog('✅ Téléchargement de la sauvegarde initié', 'success');
    showBackupNotification('✅ Téléchargement démarré !', 'success');
    
    setTimeout(function() {
        btn.removeClass('saving');
        btn.html('💾 Sauvegarder la compétition');
        btn.prop('disabled', false);
    }, 1000);
}

function showBackupNotification(message, type) {
    var notification = $('<div>')
        .css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '5px',
            color: 'white',
            fontWeight: 'bold',
            zIndex: '9999',
            boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
            minWidth: '300px',
            textAlign: 'center',
            backgroundColor: type === 'success' ? '#28a745' : '#dc3545'
        })
        .html(message + ' <button onclick="$(this).parent().remove()" style="background:transparent; border:none; color:white; font-size:18px; cursor:pointer; margin-left:10px;">×</button>')
        .appendTo('body');
    
    setTimeout(function() {
        notification.fadeOut(300, function() { $(this).remove(); });
    }, 3000);
}

function formatArrowsAsNumbers(str) {
    if (!str || str === '') return '-';
    var html = '';
    for (var i = 0; i < str.length; i++) {
        var c = str[i].toUpperCase();
        var score = letterToScore(c);
        var cls = '';
        
        if (score >= 9) {
            cls = 'score-9';
        } else if (score >= 7) {
            cls = 'score-7';
        } else if (score >= 5) {
            cls = 'score-5';
        } else if (score >= 3) {
            cls = 'score-3';
        } else if (score >= 1) {
            cls = 'score-1';
        } else {
            cls = 'score-0';
        }
        
        html += '<span class="score-badge ' + cls + '" title="Lettre ' + c + ' = ' + score + ' points">' + score + '</span>';
    }
    return html;
}

function loadData() {
    console.log('Chargement des données...'); // Debug
    $.post(ajaxUrl, { action: 'get_data', TourId: tourId }, function(r) {
        console.log('Réponse reçue:', r); // Debug
        if (r.success) {
            displayTable(r.archers);
            $('#total-archers').text(r.stats.total_archers);
            $('#total-arrows').text(r.stats.total_arrows);
            $('#total-score').text(r.stats.total_score);
            
            // Afficher le type de tournoi si disponible
            if (r.tournamentType) {
                var typeText = (r.tournamentType === 'indoor') ? '🏠 INDOOR (18m) - 3 flèches/volée' : '🌳 OUTDOOR - 6 flèches/volée';
                var configText = (r.tournamentType === 'indoor') ? 
                    'Configuration: 3 flèches par volée | Maximum: ' + r.maxArrowsPerDistance + ' flèches par distance (Total: ' + r.totalArrowsMax + ' flèches)' : 
                    'Configuration: 6 flèches par volée | Maximum: ' + r.maxArrowsPerDistance + ' flèches par distance (Total: ' + r.totalArrowsMax + ' flèches)';
                
                if ($('#tournament-type-display').length) {
                    $('#tournament-type-display').text(typeText);
                    $('#tournament-config-display').text(configText);
                    
                    // Ajouter une classe CSS selon le type
                    if (r.tournamentType === 'indoor') {
                        $('#tournament-type-display').removeClass('badge-info').addClass('badge-warning');
                    } else {
                        $('#tournament-type-display').removeClass('badge-warning').addClass('badge-info');
                    }
                }
                
                addLog('🏆 Tournoi détecté: ' + typeText, 'success');
                addLog('📊 ' + configText, 'info');
            } else {
                addLog('⚠️ Type de tournoi non détecté, utilisation configuration par défaut (OUTDOOR)', 'warning');
            }
        } else {
            addLog('Erreur: ' + (r.message || 'Erreur inconnue'), 'error');
            console.error('Erreur retournée par le serveur:', r);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) { 
        var errorMsg = 'Erreur AJAX: ' + textStatus + ' - ' + errorThrown;
        addLog(errorMsg, 'error');
        console.error('AJAX Error Details:', {
            status: jqXHR.status,
            statusText: jqXHR.statusText,
            responseText: jqXHR.responseText,
            textStatus: textStatus,
            errorThrown: errorThrown
        });
        
        // Afficher un message d'erreur dans le tableau
        $('#archers-table-body').html('<tr><td colspan="12" class="text-center" style="color: red;">❌ Erreur de connexion au serveur. Vérifiez que ajax_simulate.php est accessible.</td></tr>');
    });
}

function displayTable(archers) {
    var tbody = $('#archers-table-body').empty();
    if (!archers || archers.length === 0) {
        tbody.html('<tr><td colspan="12" class="text-center">Aucun archer trouvé</td></tr>');
        return;
    }
    
    for (var i = 0; i < archers.length; i++) {
        var a = archers[i];
        var total = (a.scoreD1 || 0) + (a.scoreD2 || 0);
        var row = '<tr>' +
            '<td>' + (a.session || '-') + '</td>' +
            '<td>' + (a.target || '-') + '</td>' +
            '<td>' + (a.targetLetter || '-') + '</td>' +
            '<td>' + (a.firstName || '') + ' ' + (a.lastName || '') + '</td>' +
            '<td><span class="badge-' + (a.archerType === 'CO' ? 'info' : 'secondary') + '">' + (a.archerType || '-') + '</span></td>' +
            '<td>' + (a.arrowsD1 || '0') + '</td>' +
            '<td>' + (a.scoreD1 || '0') + '</td>' +
            '<td class="arrow-cell">' + formatArrowsAsNumbers(a.lastVolleyD1) + '</td>' +
            '<td>' + (a.arrowsD2 || '0') + '</td>' +
            '<td>' + (a.scoreD2 || '0') + '</td>' +
            '<td class="arrow-cell">' + formatArrowsAsNumbers(a.lastVolleyD2) + '</td>' +
            '<td class="score-total">' + total + '</td>' +
            '</tr>';
        tbody.append(row);
    }
}

// Correction 4: Fonction sendAction améliorée avec validation
function sendAction(action, extra) {
    // Validation du TourId
    if (!tourId || tourId === 0) {
        addLog('Erreur: TourId invalide', 'error');
        return;
    }
    
    var data = { 
        action: action, 
        TourId: tourId,
        target_distance: $('#target-distance').val()
    };
    if (extra) $.extend(data, extra);
    
    var actionText = '';
    switch(action) {
        case 'add_arrows': actionText = 'Ajout de flèches'; break;
        case 'reset_arrows': actionText = 'Réinitialisation'; break;
        case 'complete_session': actionText = 'Complétion de session'; break;
        default: actionText = action;
    }
    
    addLog('⚠️ ' + actionText + ' - Assurez-vous d\'avoir sauvegardé !', 'warning');
    
    $.post(ajaxUrl, data, function(r) {
        if (r.success) {
            addLog('✓ ' + r.message, 'success');
            loadData();
        } else {
            addLog('✗ Erreur: ' + r.message, 'error');
        }
    }, 'json').fail(function(xhr, status, error) { 
        addLog('✗ Erreur AJAX: ' + status + ' - ' + error, 'error');
        console.error('AJAX Error Details:', {xhr: xhr, status: status, error: error});
    });
}

$(document).ready(function() {
    // Vérifier que les éléments existent avant de charger
    if ($('#archers-table-body').length) {
        loadData();
    } else {
        console.error('Table des archers non trouvée');
    }
    
    // Bouton de sauvegarde
    $('#btn-save-backup').click(function(e) {
        e.preventDefault();
        sauvegarderCompetition();
    });
    
    $('#btn-add-3').click(function(e) {
        e.preventDefault();
        sendAction('add_arrows', { 
            num_volleys: 1, 
            arrow_type: $('#arrow-type').val(), 
            archer_group: $('#archer-selector').val() 
        });
    });
    
    $('#btn-add-6').click(function(e) {
        e.preventDefault();
        sendAction('add_arrows', { 
            num_volleys: 2, 
            arrow_type: $('#arrow-type').val(), 
            archer_group: $('#archer-selector').val() 
        });
    });
    
    $('#btn-complete').click(function(e) {
        e.preventDefault();
        sendAction('complete_session', { 
            arrow_type: $('#arrow-type').val(), 
            archer_group: $('#archer-selector').val() 
        });
    });
    
    $('#btn-reset').click(function(e) {
        e.preventDefault();
        if (confirm('⚠️⚠️⚠️ RÉINITIALISATION TOTALE ⚠️⚠️⚠️\n\nCette action va supprimer TOUTES les flèches pour les archers sélectionnés !\n\nCette action est IRRÉVERSIBLE !\n\nAvez-vous fait une sauvegarde ?')) {
            sendAction('reset_arrows', { archer_group: $('#archer-selector').val() });
        }
    });
    
    $('#btn-refresh').click(function(e) { 
        e.preventDefault();
        loadData(); 
        addLog('Données rafraîchies', 'info'); 
    });
    
    $('#btn-clear-log').click(function(e) { 
        e.preventDefault();
        $('#log-container').empty(); 
        addLog('Journal effacé', 'info'); 
        addLog('⚠️ ATTENTION : Effectuez une sauvegarde avant toute modification !', 'warning');
    });
    
    $('.tab-btn').click(function() {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active');
        $('#tab-' + $(this).data('tab')).addClass('active');
    });
    
    // Correction 5: Intervalle avec nettoyage possible
    var refreshInterval = setInterval(loadData, 10000);
    
    // Nettoyer l'intervalle si nécessaire (optionnel)
    $(window).on('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
});

// Fonction de test pour vérifier l'accès au fichier AJAX
function testAjaxConnection() {
    addLog('🔍 Test de connexion à ' + ajaxUrl, 'info');
    
    // Test simple pour voir si le fichier existe
    $.ajax({
        url: ajaxUrl,
        type: 'GET',
        data: { test: 1 },
        success: function(data, status, xhr) {
            addLog('✅ Fichier AJAX accessible', 'success');
            // Vérifier le type de contenu retourné
            var contentType = xhr.getResponseHeader('Content-Type');
            addLog('Content-Type reçu: ' + contentType, 'info');
            
            // Vérifier si la réponse commence par < (donc HTML)
            if (data.trim().startsWith('<')) {
                addLog('⚠️ Le script retourne du HTML au lieu de JSON. Erreur PHP probable.', 'warning');
                // Afficher les 200 premiers caractères pour debug
                addLog('Début de la réponse: ' + data.substring(0, 200), 'warning');
            }
        },
        error: function(xhr, status, error) {
            addLog('❌ Erreur d\'accès: ' + status + ' - ' + error, 'error');
            addLog('Status HTTP: ' + xhr.status, 'error');
        }
    });
}

$('#btn-test-connection').click(function(e) { 
    e.preventDefault();
    testAjaxConnection(); 
});

</script>

<?php include('Common/Templates/tail.php'); ?>