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
 * - Laurent Petroff - Les Archers de Perols - (modif: 2026-02-06)
 * 
 * Dernière modification: 2026-02-06 par Laurent Petroff
 *
 * Plan de Cibles graphique
 *
**/

define('debug', false); // Mettre à true pour activer l'affichage debug des textes de face

require_once(dirname(dirname(__FILE__)) . '/config.php');
CheckTourSession(true);
checkFullACL(AclParticipants, 'pTarget', AclReadWrite);
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/Fun_Sessions.inc.php');

$PAGE_TITLE = get_text('ManualTargetAssignment', 'Tournament');

// Intégration des templates depuis Verification.php
$IncludeJquery = true;

// Affichage du header avant le contenu HTML
include('Common/Templates/head.php');
?>

<style>
/* Styles de Verification.php */
.anomaly-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.anomaly-table th {
    background-color: #2c5f2d;
    color: white;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
.anomaly-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.anomaly-table tr:hover {
    background-color: #f5f5f5;
}
.anomaly-table tr.premier-depart {
    background-color: #fff3cd;
}
.anomaly-table tr.depart-supplementaire {
    background-color: #f8d7da;
}
.anomaly-table tr.inscription-unique {
    background-color: #ffe4b5;
}
.summary {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 5px;
    padding: 15px;
    margin: 20px 0;
}
.summary h2 {
    margin-top: 0;
    color: #0c5460;
}
.no-anomaly {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    font-size: 18px;
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

/* Styles spécifiques à DragDropPlan.php */
.targets-overview {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}
.targets-overview::-webkit-scrollbar {
    height: 8px;
}
.targets-overview::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.targets-overview::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.targets-overview::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.target-thumbnail {
    transition: all 0.2s ease;
}
.target-thumbnail:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.target-thumbnail.active {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.target-draggable-container {
    transition: all 0.2s ease;
}
.target-draggable-container.drag-ready:hover {
    transform: scale(1.05);
}
.drop-target-hover {
    border-color: #10b981 !important;
    background-color: rgba(16, 185, 129, 0.05);
}
.target-with-archers:hover {
    background-color: rgba(16, 185, 129, 0.05) !important;
    transition: background-color 0.2s ease;
}
.invalid-configuration {
    border-color: #ef4444 !important;
    background-color: rgba(239, 68, 68, 0.05);
}
.blocked-position {
    cursor: not-allowed !important;
}
.no-select {
    user-select: none;
}
.dragging-target {
    opacity: 0.6;
    border-color: #3b82f6 !important;
}

.archer-draggable {
    transition: all 0.2s ease;
}
.archer-drag-preview {
    position: absolute !important;
    top: -1000px !important;
    left: -1000px !important;
    width: 190px !important;
    padding: 10px !important;
    border-radius: 8px !important;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2) !important;
    font-family: 'Inter', sans-serif !important;
    z-index: 10000 !important;
    opacity: 0.95 !important;
    pointer-events: none !important;
    backdrop-filter: blur(2px);
    border: 2px solid;
}
.archer-drag-preview.wheelchair {
    background: linear-gradient(135deg, rgba(219, 234, 254, 0.98), rgba(191, 219, 254, 0.98)) !important;
    border-color: #3b82f6 !important;
}
.archer-drag-preview.regular {
    background: linear-gradient(135deg, rgba(220, 252, 231, 0.98), rgba(187, 247, 208, 0.98)) !important;
    border-color: '#10b981 !important;
}
.dragging-archer {
    opacity: 0.3 !important;
    transition: all 0.2s ease !important;
}

/* Styles pour les positions d'archers */
.position-container {
    transition: all 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.position-occupied {
    cursor: move !important;
}

.position-occupied:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px) !important;
    z-index: 10 !important;
}

.position-empty {
    cursor: default !important;
}

.position-empty:hover {
    box-shadow: none !important;
    transform: none !important;
}

.position-blocked {
    cursor: not-allowed !important;
    opacity: 0.7 !important;
}

.position-blocked:hover {
    box-shadow: none !important;
    transform: none !important;
}

.archer-content {
    height: calc(100% - 28px); 
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.position-header {
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.75rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    background-color: inherit;
    z-index: 2;
    position: relative;
}

.archer-original-look {
    padding: 6px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    line-height: 1.3 !important;
}

.archer-original-look .archer-info {
    padding: 4px 6px !important;
    border-radius: 4px !important;
    margin-bottom: 4px !important;
}

.archer-original-look .archer-details {
    font-size: 10px !important;
    padding: 2px 4px !important;
}

/* Styles pour les bordures spécifiques */
.archer-assigned {
    border: 2px solid #15803d !important; /* Vert foncé pour les assignés */
    background-color: #dcfce7 !important; /* Vert clair */
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

.archer-wheelchair-assigned {
    border: 2px solid #1d4ed8 !important; /* Bleu foncé pour les fauteuils */
    background-color: #dbeafe !important; /* Bleu clair */
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

.position-empty {
    border: 2px solid #e5e7eb !important; /* Gris clair pour les positions vides */
    background-color: #f9fafb !important; /* Gris très clair */
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

.archer-unassigned {
    border: 2px solid #ea580c !important; /* Orange foncé pour les non-assignés */
    background-color: #ffedd5 !important; /* Orange clair */
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

.target-container {
    border: 2px solid #e5e7eb !important; /* Gris clair pour les cibles */
    background-color: #f8fafc !important; /* Gris très clair */
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

/* S'assurer que les bordures soient visibles */
.p-3.border-2.rounded-lg {
    border-width: 2px !important;
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
}

/* Position container spécifique */
.position-container {
    border-radius: 0.5rem !important; /* S'assurer que le border-radius est le même */
    overflow: hidden !important; /* Empêcher le débordement */
}

/* Ajustements pour la visibilité des bordures */
.border-2 {
    border-width: 2px !important;
}

/* Spécifique pour les archers dans les positions */
.archer-draggable {
    border-radius: 0.375rem !important; 
}

/* Ajuster le header des positions */
.position-header {
    border-top-left-radius: 0.5rem !important;
    border-top-right-radius: 0.5rem !important;
    border-bottom-left-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

/* S'assurer que le contenu archer s'aligne bien */
.archer-content {
    border-bottom-left-radius: 0.375rem !important;
    border-bottom-right-radius: 0.375rem !important;
}

/* Styles pour les miniatures de cibles en haut - VERT CLAIR */
.target-thumbnail.has-archers {
    border: 3px solid #86efac !important; /* Vert clair plus épais */
    background: linear-gradient(135deg, #f0fdf4, #dcfce7) !important;
    box-shadow: 0 2px 8px rgba(134, 239, 172, 0.3) !important;
}

.target-thumbnail.has-archers:hover {
    border-color: #4ade80 !important;
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(74, 222, 128, 0.4) !important;
}

.target-thumbnail.has-archers.active {
    border-color: #22c55e !important;
    background: linear-gradient(135deg, #dcfce7, #bbf7d0) !important;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.4), 
                0 4px 12px rgba(34, 197, 94, 0.3) !important;
}

/* Cible sans archers */
.target-thumbnail:not(.has-archers) {
    border-color: #e5e7eb !important;
    background-color: #f9fafb !important;
}

/* Cible invalide (rouge) */
.target-thumbnail:not(.is-valid) {
    border-color: #fca5a5 !important;
    background-color: #fef2f2 !important;
}

/* Numéro de cible en gras pour les cibles avec archers */
.target-thumbnail.has-archers .font-bold {
    color: #15803d !important; /* Vert foncé pour le numéro */
    font-weight: 800 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Indicateur visuel supplémentaire pour les cibles avec archers */
.target-thumbnail.has-archers::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: inherit;
    background: linear-gradient(45deg, transparent, rgba(134, 239, 172, 0.1), transparent);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.target-thumbnail.has-archers:hover::before {
    opacity: 1;
}

/* Style pour le highlight de la cible sélectionnée */
.highlight-target {
    border-color: #3b82f6 !important;
    background-color: rgba(59, 130, 246, 0.1) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3), 
                0 4px 12px rgba(59, 130, 246, 0.2) !important;
    transform: translateY(-2px);
    transition: all 0.3s ease;
    z-index: 10;
    position: relative;
}

/* Animation de pulsation */
@keyframes targetPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    }
}

.highlight-target::after {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: inherit;
    animation: targetPulse 1.5s infinite;
    z-index: -1;
}

/* Assurer que le bouton est visible */
.flex.items-center.gap-2 button {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Style spécifique pour le bouton d'impression */
button:has(.w-4.h-4) {
    display: inline-flex !important;
}

.bg-blue-600 { 
    background-color: #2563eb !important; 
}
.text-white { 
    color: #ffffff !important; 
}
.hover\:bg-blue-700:hover { 
    background-color: #1d4ed8 !important; 
}

/* Styles pour la zone de drop améliorée */
.drop-zone-active {
    border-color: #f97316 !important;
    background-color: rgba(254, 215, 170, 0.3) !important;
    border-width: 3px !important;
}

.drop-zone-hint {
    animation: dropZonePulse 1.5s infinite;
}

@keyframes dropZonePulse {
    0%, 100% {
        border-color: #f97316;
    }
    50% {
        border-color: #fb923c;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
    }
}

.cursor-copy {
    cursor: copy !important;
}

/* Styles pour les badges de cibles */
.target-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 20px;
    height: 20px;
    background-color: #10b981;
    color: white;
    font-size: 10px;
    font-weight: bold;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    z-index: 10;
}

/* Style pour EvCode et distance */
.evcode-distance-badge {
    background-color: #d1fae5; /* Vert clair */
    color: #059669; /* Vert foncé */
    font-size: 0.7rem;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    margin-top: 3px;
    border: 1px solid #10b981;
}

.distance-badge {
    background-color: #fbbf24; /* Jaune */
    color: #92400e; /* Jaune foncé */
    font-size: 0.7rem;
    font-weight: bold;
    padding: 1px 4px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 3px;
}

/* Styles pour les informations de taille de cible */
.target-size-badge {
    background-color: #e0e7ff; /* Violet clair */
    color: #4f46e5; /* Violet foncé */
    font-size: 0.7rem;
    font-weight: bold;
    padding: 1px 4px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 2px;
    border: 1px solid #c7d2fe;
}

.face-badge {
    background-color: #fce7f3; /* Rose clair */
    color: #be185d; /* Rose foncé */
    font-size: 0.7rem;
    font-weight: bold;
    padding: 1px 4px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 2px;
    border: 1px solid #fbcfe8;
}

.tournament-type-badge {
    background-color: #fef3c7; /* Jaune clair */
    color: #92400e; /* Jaune foncé */
    font-size: 0.6rem;
    font-weight: bold;
    padding: 1px 3px;
    border-radius: 2px;
    display: inline-block;
    margin-left: 4px;
    text-transform: uppercase;
}

/* Limiter la largeur de la colonne principale */
.main-target-column {
    flex: 1;
    max-width: 80% !important;
    width: 80% !important;
}

/* Pour le conteneur flex parent */
.flex-container {
    max-width: 90vw !important;
    margin: 0 auto !important;
}

.target-thumbnail {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    position: relative;
}

.target-thumbnail .relative {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 64px;
}

.target-thumbnail img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.target-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    background-color: #10b981;
    color: white;
    font-size: 10px;
    font-weight: bold;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    z-index: 10;
}

.target-thumbnail .font-bold {
    margin-top: 4px;
    font-size: 12px;
    width: 100%;
    text-align: center;
}

.target-distance {
    font-size: 0.75rem;
    font-weight: bold;
    color: #2563eb;
    background-color: rgba(37, 99, 235, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 4px;
    display: inline-block;
    border: 1px solid rgba(37, 99, 235, 0.2);
}

.target-distance-indoor {
    color: #059669;
    background-color: rgba(5, 150, 105, 0.1);
    border-color: rgba(5, 150, 105, 0.2);
}

.target-distance-outdoor {
    color: #dc2626;
    background-color: rgba(220, 38, 38, 0.1);
    border-color: rgba(220, 38, 38, 0.2);
}

.target-main-distance {
    font-size: 0.9rem;
    font-weight: bold;
    margin-top: 4px;
    padding: 3px 8px;
    border-radius: 5px;
    display: inline-block;
}

</style>


<div id="root"></div>


<style>
    body {
       // font-family: 'Inter', sans-serif;
    }
    
    /* Style CLUTH comme sur l'image */
    .cluth-header {
        background-color: #2c5f2d;
        color: white;
        padding: 8px 15px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 4px 4px 0 0;
        margin: 0;
        font-size: 14px;
    }
    
    .border-action-entry {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-top: none;
        padding: 10px 15px;
        margin: 0 0 20px 0;
        border-radius: 0 0 4px 4px;
    }
    
    .border-action-entry p {
        margin: 0;
        padding: 2px 0;
        color: #333;
    }
    
    .border-action-entry strong {
        color: #2c5f2d;
    }
    
    /* Style pour le format de texte de l'image */
    .text-format-example {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.4;
        color: #333;
        background-color: #f5f5f5;
        padding: 10px;
        border: 1px solid #ddd;
        margin: 10px 0;
        white-space: pre-wrap;
    }

</style>



<!-- Références aux bibliothèques locales -->
<script src="./Lib/react-18.2.0.production.min.js"></script>
<script src="./Lib/react-dom-18.2.0.production.min.js"></script>
<script src="./Lib/babel-7.23.2.min.js"></script>
<!-- Alternative pour Tailwind CSS pour éviter l'avertissement -->
<script>
// Configuration Tailwind CSS minimaliste
(function() {
    // Créer un objet tailwind global pour éviter les erreurs
    if (!window.tailwind) {
        window.tailwind = {
            config: {},
            init: function() {
                console.log('Tailwind CSS initialisé (version locale)');
            }
        };
    }
    
    // Initialiser tailwind
    document.addEventListener('DOMContentLoaded', function() {
        if (window.tailwind && window.tailwind.init) {
            window.tailwind.init();
        }
        
        // Ajouter quelques classes Tailwind utiles si nécessaire
        const style = document.createElement('style');
        style.textContent = `
            /* Classes utilitaires Tailwind de base */
            .flex { display: flex; }
            .grid { display: grid; }
            .hidden { display: none; }
            .block { display: block; }
            .inline-block { display: inline-block; }
            .inline-flex { display: inline-flex; }
            .flex-col { flex-direction: column; }
            .flex-row { flex-direction: row; }
            .items-center { align-items: center; }
            .justify-center { justify-content: center; }
            .justify-between { justify-content: space-between; }
            .gap-1 { gap: 0.25rem; }
            .gap-2 { gap: 0.5rem; }
            .gap-3 { gap: 0.75rem; }
            .gap-4 { gap: 1rem; }
            .gap-6 { gap: 1.5rem; }
            .p-1 { padding: 0.25rem; }
            .p-2 { padding: 0.5rem; }
            .p-3 { padding: 0.75rem; }
            .p-4 { padding: 1rem; }
            .p-6 { padding: 1.5rem; }
            .m-0 { margin: 0; }
            .mb-1 { margin-bottom: 0.25rem; }
            .mb-2 { margin-bottom: 0.5rem; }
            .mb-3 { margin-bottom: 0.75rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mb-6 { margin-bottom: 1.5rem; }
            .mt-1 { margin-top: 0.25rem; }
            .mt-2 { margin-top: 0.5rem; }
            .mt-3 { margin-top: 0.75rem; }
            .mt-4 { margin-top: 1rem; }
            .mt-6 { margin-top: 1.5rem; }
            .rounded { border-radius: 0.25rem; }
            .rounded-lg { border-radius: 0.5rem; }
            .rounded-full { border-radius: 9999px; }
            .border { border-width: 1px; }
            .border-2 { border-width: 2px; }
            .border-3 { border-width: 3px; }
            .border-t { border-top-width: 1px; }
            .border-b { border-bottom-width: 1px; }
            .border-gray-200 { border-color: #e5e7eb; }
            .border-gray-300 { border-color: #d1d5db; }
            .border-orange-300 { border-color: #fdba74; }
            .border-orange-400 { border-color: #fb923c; }
            .border-orange-500 { border-color: #f97316; }
            .border-blue-300 { border-color: #93c5fd; }
            .border-blue-400 { border-color: #60a5fa; }
            .border-blue-500 { border-color: #3b82f6; }
            .border-blue-600 { border-color: #2563eb; }
            .border-green-300 { border-color: #86efac; }
            .border-green-400 { border-color: #4ade80; }
            .border-green-500 { border-color: #10b981; }
            .border-green-600 { border-color: #059669; }
            .border-red-300 { border-color: #fca5a5; }
            .border-red-500 { border-color: #ef4444; }
            .bg-white { background-color: #ffffff; }
            .bg-gray-50 { background-color: #f9fafb; }
            .bg-gray-100 { background-color: #f3f4f6; }
            .bg-orange-50 { background-color: #fff7ed; }
            .bg-orange-100 { background-color: #ffedd5; }
            .bg-blue-50 { background-color: #eff6ff; }
            .bg-blue-100 { background-color: #dbeafe; }
            .bg-green-50 { background-color: #f0fdf4; }
            .bg-green-100 { background-color: #dcfce7; }
            .bg-green-200 { background-color: #bbf7d0; }
            .bg-red-50 { background-color: #fef2f2; }
            .bg-red-100 { background-color: #fee2e2; }
            .bg-yellow-100 { background-color: #fef3c7; }
            .bg-slate-50 { background-color: #f8fafc; }
            .bg-slate-100 { background-color: #f1f5f9; }
            .bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-stops)); }
            .from-slate-50 { --tw-gradient-from: #f8fafc; --tw-gradient-to: rgb(248 250 252 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
            .to-slate-100 { --tw-gradient-to: #f1f5f9; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .text-right { text-align: right; }
            .text-xs { font-size: 0.75rem; line-height: 1rem; }
            .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
            .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
            .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
            .text-2xl { font-size: 1.5rem; line-height: 2rem; }
            .font-bold { font-weight: 700; }
            .font-medium { font-weight: 500; }
            .font-semibold { font-weight: 600; }
            .text-gray-400 { color: #9ca3af; }
            .text-gray-500 { color: #6b7280; }
            .text-gray-600 { color: #4b5563; }
            .text-gray-800 { color: #1f2937; }
            .text-blue-600 { color: #2563eb; }
            .text-blue-700 { color: #1d4ed8; }
            .text-green-600 { color: #16a34a; }
            .text-green-700 { color: #15803d; }
            .text-green-800 { color: #166534; }
            .text-red-600 { color: #dc2626; }
            .text-red-800 { color: #991b1b; }
            .text-yellow-800 { color: #92400e; }
            .text-orange-600 { color: #ea580c; }
            .text-orange-700 { color: #c2410c; }
            .text-white { color: #ffffff; }
            .opacity-40 { opacity: 0.4; }
            .opacity-50 { opacity: 0.5; }
            .opacity-70 { opacity: 0.7; }
            .shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
            .shadow-md { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
            .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
            .sticky { position: sticky; }
            .top-6 { top: 1.5rem; }
            .fixed { position: fixed; }
            .bottom-6 { bottom: 1.5rem; }
            .right-6 { right: 1.5rem; }
            .left-6 { left: 1.5rem; }
            .z-10 { z-index: 10; }
            .z-10000 { z-index: 10000; }
            .w-4 { width: 1rem; }
            .w-5 { width: 1.25rem; }
            .w-6 { width: 1.5rem; }
            .w-8 { width: 2rem; }
            .w-11 { width: 2.75rem; }
            .w-12 { width: 3rem; }
            .w-16 { width: 4rem; }
            .w-19 { width: 19rem; }
            .w-24 { width: 6rem; }
            .w-60 { width: 15rem; }
            .h-4 { height: 1rem; }
            .h-5 { height: 1.25rem; }
            .h-6 { height: 1.5rem; }
            .h-8 { height: 2rem; }
            .h-12 { height: 3rem; }
            .h-16 { height: 4rem; }
            .h-20 { height: 5rem; }
            .h-28 { height: 7rem; }
            .min-w-0 { min-width: 0; }
            .min-w-\[200px\] { min-width: 200px; }
            .min-h-\[300px\] { min-height: 300px; }
            .min-h-\[350px\] { min-height: 350px; }
            .max-h-\[600px\] { max-height: 600px; }
            .max-h-\[calc\(100vh-200px\)\] { max-height: calc(100vh - 200px); }
            .overflow-x-auto { overflow-x: auto; }
            .overflow-y-auto { overflow-y: auto; }
            .flex-shrink-0 { flex-shrink: 0; }
            .flex-1 { flex: 1 1 0%; }
            .cursor-pointer { cursor: pointer; }
            .cursor-move { cursor: move; }
            .cursor-default { cursor: default; }
            .cursor-copy { cursor: copy; }
            .cursor-not-allowed { cursor: not-allowed; }
            .transition { transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
            .transition-all { transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
            .transition-colors { transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
            .transition-shadow { transition-property: box-shadow; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
            .hover\:bg-blue-700:hover { background-color: #1d4ed8; }
            .hover\:bg-gray-100:hover { background-color: #f3f4f6; }
            .hover\:bg-orange-50:hover { background-color: #fff7ed; }
            .hover\:bg-orange-100:hover { background-color: #ffedd5; }
            .hover\:shadow-md:hover { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
            .hover\:shadow-lg:hover { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
            .hover\:text-gray-800:hover { color: #1f2937; }
            .hover\:border-orange-400:hover { border-color: #fb923c; }
            .focus\:outline-none:focus { outline: 2px solid transparent; outline-offset: 2px; }
            .focus\:ring-2:focus { ring-width: 2px; }
            .focus\:ring-blue-500:focus { --tw-ring-color: #3b82f6; }
            .focus\:ring-offset-2:focus { --tw-ring-offset-width: 2px; }
            .focus\:border-blue-500:focus { border-color: #3b82f6; }
            .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .space-y-3 > * + * { margin-top: 0.75rem; }
            .space-y-4 > * + * { margin-top: 1rem; }
            .mx-auto { margin-left: auto; margin-right: auto; }
            .pt-1 { padding-top: 0.25rem; }
            .pt-3 { padding-top: 0.75rem; }
            .pt-4 { padding-top: 1rem; }
            .pb-1 { padding-bottom: 0.25rem; }
            .pb-3 { padding-bottom: 0.75rem; }
            .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
            .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
            .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
            .py-12 { padding-top: 3rem; padding-bottom: 3rem; }
            .relative { position: relative; }
            .absolute { position: absolute; }
            .inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
            .inline-flex { display: inline-flex; }
            .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .object-contain { object-fit: contain; }
            .no-select { user-select: none; }
            .pointer-events-none { pointer-events: none; }
            .border-dashed { border-style: dashed; }
        `;
        document.head.appendChild(style);
    });
})();
</script>

<script type="text/babel">
    // Icônes SVG utilisées dans l'interface
    const Icons = {
        Grid: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
        ),
        Users: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
            </svg>
        ),
        ChevronUp: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
            </svg>
        ),
        ChevronDown: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
            </svg>
        ),
        Target: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="12" cy="12" r="8" strokeWidth="2" />
                <circle cx="12" cy="12" r="4" strokeWidth="2" />
                <circle cx="12" cy="12" r="1" strokeWidth="2" />
            </svg>
        ),
		Print: () => (
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
			</svg>
		),
        Lock: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        ),
        Wheelchair: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
        ),
        Warning: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5" />
            </svg>
        ),
        Check: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
        ),
        AlertCircle: () => (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        )
    };
</script>

<script type="text/babel">
    const { useState, useEffect, useRef } = React;
    
    // Définir le mode debug pour les textes de face
    const isDebugMode = <?php echo defined('debug') && debug ? 'true' : 'false'; ?>;
    
    // FONCTION CORRIGÉE SELON LES NOUVELLES RÈGLES
    const checkPairValid = (face1, face2) => {
        if (!face1 || !face2) return true; // Une position vide est toujours valide
        
        // Face 3 (80cm) - règles spéciales : ne peut être qu'avec une autre face 3
        if (face1 === '3' || face2 === '3') {
            return face1 === face2; // Doivent être identiques
        }
        
        // Faces 4 et 6 - compatibles entre elles
        if ((face1 === '4' && face2 === '6') || (face1 === '6' && face2 === '4')) {
            return true;
        }
        
        // Toutes les autres faces doivent être identiques
        return face1 === face2;
    };

    const convertDbToInterface = (dbTargetNo) => {
        // Si targetNo est vide, null, undefined, 0 ou false, retourner chaîne vide
        if (!dbTargetNo || dbTargetNo === '' || dbTargetNo === '0' || dbTargetNo === 0) {
            return '';
        }
        
        // Si la longueur est inférieure à 5, c'est probablement un format invalide
        if (dbTargetNo.length < 5) {
            return '';
        }
        
        return dbTargetNo.substring(1);
    };

    const convertInterfaceToDb = (targetNo, session) => {
        // Si targetNo est vide, null, undefined ou 0, retourner chaîne vide
        if (!targetNo || targetNo === '' || targetNo === '0' || targetNo === 0) {
            return '';
        }
        return session + targetNo;
    };

    const getCombinationImage = (target) => {
        const positions = ['A', 'B', 'C', 'D'];
        const targetFaces = {};
        const distances = {};
        const categories = {};
        const ages = {};
        const isCO = {};
        const isCOInternational = {};
        const isCONational = {};
        const targetSizes = {};
        const evCodes = {};
        
        positions.forEach(letter => {
            const position = target.positions.find(p => p.letter === letter);
            if (position?.archer) {
                targetFaces[letter] = position.archer.targetFace || null;
                distances[letter] = position.archer.distance || null;
                categories[letter] = position.archer.division || ''; 
                ages[letter] = position.archer.division || '';
                isCO[letter] = categories[letter] === 'CO' || (categories[letter] && categories[letter].includes('CO')); 
                evCodes[letter] = position.archer.evCode || '';
                isCONational[letter] = evCodes[letter]?.startsWith('N') || false;
                isCOInternational[letter] = isCO[letter] && !isCONational[letter];
                targetSizes[letter] = position.archer.targetSize || '';
            } else {
                targetFaces[letter] = null;
                distances[letter] = null;
                categories[letter] = null;
                ages[letter] = null;
                isCO[letter] = false;
                isCONational[letter] = false;
                isCOInternational[letter] = false;
                evCodes[letter] = '';
                targetSizes[letter] = '';
            }
        });
        
        // Récupérer la distance commune (si elle existe)
        const occupiedPositions = positions.filter(letter => targetFaces[letter]);
        let commonDistance = null;
        if (occupiedPositions.length > 0) {
            const firstDistance = distances[occupiedPositions[0]];
            const allSameDistance = occupiedPositions.every(letter => distances[letter] === firstDistance);
            
            if (allSameDistance && firstDistance) {
                commonDistance = firstDistance;
            }
        }
        
        // Règle 1: Vérifier si tous les archers ont la même distance
        if (occupiedPositions.length > 0) {
            const firstDistance = distances[occupiedPositions[0]];
            const allSameDistance = occupiedPositions.every(letter => distances[letter] === firstDistance);
            
            if (!allSameDistance) {
                return { image: 'Img/xx.png', distance: commonDistance };
            }
        }
        
        // Règle 2: Face 3 (80cm) - tous doivent être de type 3
        const hasTargetFace3 = Object.values(targetFaces).some(face => face === '3');
        if (hasTargetFace3) {
            const hasDifferentFace = Object.values(targetFaces).some(face => 
                face && face !== '3'
            );
            
            if (hasDifferentFace) {
                return { image: 'Img/xx.png', distance: commonDistance };
            }
        }
        
        // Règle 3: Vérifier les paires A/C et B/D selon les nouvelles règles
        const checkPair = (pos1, pos2) => {
            const face1 = targetFaces[pos1];
            const face2 = targetFaces[pos2];
            
            if (!checkPairValid(face1, face2)) {
                return false;
            }
            return true;
        };
        
        if (!checkPair('A', 'C') || !checkPair('B', 'D')) {
            return { image: 'Img/xx.png', distance: commonDistance };
        }
        
        // Règle 4: Vérifier les combinaisons spécifiques selon le type
        const getTensDigit = () => {
            if (targetFaces['A']) return targetFaces['A'];
            if (targetFaces['C']) return targetFaces['C'];
            return 'x';
        };
        
        const getUnitsDigit = () => {
            if (targetFaces['B']) return targetFaces['B'];
            if (targetFaces['D']) return targetFaces['D'];
            return 'x';
        };
        
        const tensDigit = getTensDigit();
        const unitsDigit = getUnitsDigit();
        
        // Règle 5: Vérifier les combinaisons autorisées
        const isValidCombination = () => {
            // Si toutes les positions sont vides
            if (tensDigit === 'x' && unitsDigit === 'x') {
                return true;
            }
            
            // Type 3 (80cm) - seulement 3, 33, 3x, x3
            if (hasTargetFace3) {
                const validFor3 = ['3', '33', '3x', 'x3'];
                return validFor3.includes(tensDigit + unitsDigit);
            }
            
            // Type 2 (60cm) - 2x, x2, 21, 22, 24, 26, 27, 12, 42, 62, 72
            const validFor2 = ['2x', 'x2', '21', '22', '24', '26', '27', '12', '42', '62', '72'];
            if (validFor2.includes(tensDigit + unitsDigit)) {
                return true;
            }
            
            // Type 1 (40cm) - 1x, x1, 11, 12, 14, 16, 17, 21, 41, 61, 71
            const validFor1 = ['1x', 'x1', '11', '12', '14', '16', '17', '21', '41', '61', '71'];
            if (validFor1.includes(tensDigit + unitsDigit)) {
                return true;
            }
            
            // Type 4 (40TCL) - 4x, x4, 44, 41, 42, 46, 47, 14, 24, 64, 74
            const validFor4 = ['4x', 'x4', '44', '41', '42', '46', '47', '14', '24', '64', '74'];
            if (validFor4.includes(tensDigit + unitsDigit)) {
                return true;
            }
            
            // Type 6 (40TCL) - 6x, x6, 66, 61, 62, 64, 67, 16, 26, 46, 76
            const validFor6 = ['6x', 'x6', '66', '61', '62', '64', '67', '16', '26', '46', '76'];
            if (validFor6.includes(tensDigit + unitsDigit)) {
                return true;
            }
            
            // Type 7 (30T) - 7x, x7, 77, 71, 72, 74, 76, 17, 27, 47, 67
            const validFor7 = ['7x', 'x7', '77', '71', '72', '74', '76', '17', '27', '47', '67'];
            if (validFor7.includes(tensDigit + unitsDigit)) {
                return true;
            }
            
            return false;
        };
        
        if (!isValidCombination()) {
            return { image: 'Img/xx.png', distance: commonDistance };
        }
        
        // Règle 6: Vérifier si on est en outdoor (distance > 18m)
        const firstDistance = distances[occupiedPositions[0]];
        const isOutdoor = firstDistance && parseInt(firstDistance) > 18;
        
        // NOUVELLE RÈGLE : Si distance = 18 et taille de cible = 80cm, afficher Img/3.png
        if (firstDistance && parseInt(firstDistance) === 18) {
            const firstTargetSize = targetSizes[occupiedPositions[0]];
            if (firstTargetSize === '80') {
                // Vérifier que toutes les positions ont la même taille 80cm
                const all80cm = occupiedPositions.every(letter => targetSizes[letter] === '80');
                if (all80cm) {
                    return { image: 'Img/3.png', distance: commonDistance };
                }
            }
        }
        
        if (isOutdoor && occupiedPositions.length > 0) {
            // Règle 6a: U11 -> cible type 3 (80cm)   même si les U11 n'ont pas d'exterieur !
            const hasU11 = occupiedPositions.some(letter => ages[letter] === 'U11');
            if (hasU11) {
                const allU11 = occupiedPositions.every(letter => ages[letter] === 'U11');
                if (allU11) {
                    return { image: 'Img/3.png', distance: commonDistance };
                } else {
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
            }
            
            // Règle 6b: U13, U15 -> cible standard 80cm - CORRECTION: Img/3b.png  (à 1m30)
            const hasU13U15 = occupiedPositions.some(letter => 
                ages[letter] === 'U13' || ages[letter] === 'U15'
            );
            if (hasU13U15) {
                const allU13U15 = occupiedPositions.every(letter => 
                    ages[letter] === 'U13' || ages[letter] === 'U15'
                );
                if (allU13U15) {
                    // NOUVELLE RÈGLE: U13/U15 -> Img/3b.png
                    return { image: 'Img/3b.png', distance: commonDistance };
                } else {
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
            }
            
            // Règle 6c: ARCHERS CO (COMPOUND) EN EXTÉRIEUR - GESTION CORRIGÉE
            const hasCOInternational = occupiedPositions.some(letter => isCOInternational[letter]);
            const hasCONational = occupiedPositions.some(letter => isCONational[letter]);
            
            // CO International (H/F) - règles spécifiques
            if (hasCOInternational) {
                // Vérifier que tous les archers sur cette cible sont CO International
                const allCOInternational = occupiedPositions.every(letter => isCOInternational[letter]);
                
                if (!allCOInternational) {
                    return { image: 'Img/xx.png', distance: commonDistance }; // Mélange CO International + autre chose non autorisé
                }
                
                // Vérifier la taille de cible - doit être 80cm pour CO International en extérieur
                const has80cm = occupiedPositions.some(letter => targetSizes[letter] === '80');
                
                if (!has80cm) {
                    return { image: 'Img/xx.png', distance: commonDistance }; // CO International en extérieur doit être sur 80cm
                }
                
                // Vérifier que tous ont la même taille 80cm
                const all80cm = occupiedPositions.every(letter => targetSizes[letter] === '80');
                if (!all80cm) {
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
                
                // Prendre le premier archer CO International pour déterminer le sessionAth4Target
                const firstCOArcher = target.positions.find(p => 
                    p.archer && 
                    (p.archer.division === 'CO' || (p.archer.division && p.archer.division.includes('CO'))) && 
                    !(p.archer.evCode || '').startsWith('N')
                )?.archer;
                
                if (firstCOArcher) {
                    // Utiliser la valeur sessionAth4Target qui vient de la table Session
                    const sessionAth4Target = firstCOArcher.sessionAth4Target || 4; // Valeur par défaut 4
                    
                    // Logique spécifique pour CO International selon sessionAth4Target
                    if (sessionAth4Target == 4) {
                        return { image: 'Img/84.png', distance: commonDistance };  //  AB/CD
                    } else if (sessionAth4Target == 3) {
                        return { image: 'Img/83.png', distance: commonDistance };  //  ABC
                    }
                    
                    // Si sessionAth4Target n'est pas 3 ou 4, utiliser l'image standard CO  (ne devrait jamais arriver)
                    return { image: 'Img/80CO.png', distance: commonDistance };
                }
            }
            
            // CO National (M/W) - règles différentes
            if (hasCONational) {
                // CO National peut être mélangé avec d'autres archers sur 122cm
                // Vérifier que tous les archers sont sur 122cm
                const has122cm = occupiedPositions.some(letter => targetSizes[letter] === '122');
                const all122cm = occupiedPositions.every(letter => targetSizes[letter] === '122');
                
                if (!has122cm || !all122cm) {
                    return { image: 'Img/xx.png', distance: commonDistance }; // CO National doit être sur 122cm
                }
                
                // Vérifier les faces de cible - CO National sur 122cm est face 2
                const hasFace2 = occupiedPositions.some(letter => targetFaces[letter] === '2');
                const allFace2 = occupiedPositions.every(letter => targetFaces[letter] === '2');
                
                if (!hasFace2 || !allFace2) {
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
                
                return { image: 'Img/5.png', distance: commonDistance }; // Cible 122cm standard
            }
            
            // Règle 6d: DÉTERMINER L'IMAGE POUR L'EXTÉRIEUR BASÉ SUR LA TAILLE ET LA FACE
            if (occupiedPositions.length > 0) {
                const firstTargetSize = targetSizes[occupiedPositions[0]];
                const firstTargetFace = targetFaces[occupiedPositions[0]];
                
                // Vérifier que toutes les positions ont la même taille
                const allSameSize = occupiedPositions.every(letter => targetSizes[letter] === firstTargetSize);
                if (!allSameSize) {
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
                
                // CAS 1: TAILLE 122cm (CIBLE STANDARD EXTRÉRIEUR)
                if (firstTargetSize === '122') {
                    // Type 2 (122cm) -> Img/5.png
                    if (firstTargetFace === '2') {
                        // Vérifier que toutes les positions ont la même face
                        const allFace2 = occupiedPositions.every(letter => targetFaces[letter] === '2');
                        if (allFace2) {
                            return { image: 'Img/5.png', distance: commonDistance };
                        }
                    }
                    return { image: 'Img/xx.png', distance: commonDistance };
                }
                
                // CAS 2: TAILLE 80cm (NON-CO, NON-U13/U15)
                if (firstTargetSize === '80' && !hasCOInternational) {
                    // Type 1 (80cm standard) -> Img/4.png
                    if (firstTargetFace === '1') {
                        const allFace1 = occupiedPositions.every(letter => targetFaces[letter] === '1');
                        if (allFace1) {
                            return { image: 'Img/4.png', distance: commonDistance };
                        }
                    }
                    
                    // Type 3 (80cm) -> vérifier si U13/U15 ou standard
                    if (firstTargetFace === '3') {
                        const allFace3 = occupiedPositions.every(letter => targetFaces[letter] === '3');
                        if (allFace3) {
                            // Vérifier si c'est U13/U15
                            const hasU13U15Check = occupiedPositions.some(letter => 
                                ages[letter] === 'U13' || ages[letter] === 'U15'
                            );
                            if (hasU13U15Check) {
                                return { image: 'Img/3b.png', distance: commonDistance }; // U13/U15
                            }
                            return { image: 'Img/3.png', distance: commonDistance }; // Standard
                        }
                    }
                }
            }
            
            // Règle 6e: SI AUCUNE TAILLE SPÉCIFIQUE N'EST DÉTECTÉE, UTILISER LE MAPPING PAR COMBINAISON
            // Mapping spécial pour l'extérieur basé sur la combinaison de faces
            const outdoorCombinationMapping = {
                // CIBLE 122cm (standard extérieur)
                '22': 'Img/5.png',      // Type 2 -> 122cm
                '2x': 'Img/5.png',      // Type 2 partiel -> 122cm
                'x2': 'Img/5.png',      // Type 2 partiel -> 122cm
                
                // CIBLE 80cm STANDARD
                '11': 'Img/4.png',      // Type 1 -> 80cm standard
                '1x': 'Img/4.png',      // Type 1 partiel -> 80cm standard
                'x1': 'Img/4.png',      // Type 1 partiel -> 80cm standard
                
                // CIBLE 80cm (type 3) standard
                '33': 'Img/3.png',      // Type 3 -> 80cm standard
                '3x': 'Img/3.png',      // Type 3 partiel -> 80cm standard
                'x3': 'Img/3.png',      // Type 3 partiel -> 80cm standard
                
                // CIBLE 80cm U13/U15 - NOUVELLE RÈGLE
                '33_U13U15': 'Img/3b.png',  // Type 3 pour U13/U15
                '3x_U13U15': 'Img/3b.png',
                'x3_U13U15': 'Img/3b.png',
                
            };
            
            const combination = tensDigit + unitsDigit;
            
            // Vérifier si c'est U13/U15
            const hasU13U15Check = occupiedPositions.some(letter => 
                ages[letter] === 'U13' || ages[letter] === 'U15'
            );
            
            const combinationKey = hasU13U15Check && combination.includes('3') 
                ? `${combination}_U13U15` 
                : combination;
            
            // Vérifier d'abord le mapping spécifique pour l'extérieur
            if (outdoorCombinationMapping[combinationKey]) {
                return { image: outdoorCombinationMapping[combinationKey], distance: commonDistance };
            } else if (outdoorCombinationMapping[combination]) {
                return { image: outdoorCombinationMapping[combination], distance: commonDistance };
            }
            
            // Si la combinaison n'est pas dans le mapping extérieur mais est valide,
            // utiliser l'image standard (au cas où)
            return { image: `Img/${tensDigit}${unitsDigit}.png`, distance: commonDistance };
        } else {
            // INDOOR ou distance ≤ 18m
            if (tensDigit === 'x' && unitsDigit === 'x') {
                return { image: null, distance: commonDistance };
            }
            
            return { image: `Img/${tensDigit}${unitsDigit}.png`, distance: commonDistance };
        }
    };

    const getTargetFaceImage = (targetSize, targetFace, tournamentType, archer) => {
        if (!targetSize || !targetFace) return '';
        
        const isOutdoor = archer?.distance && parseInt(archer.distance) > 18;
        const isCO = archer?.division === 'CO' || (archer?.division && archer.division.includes('CO'));
        const isCONational = archer?.evCode?.startsWith('N') || false;
        const isU13U15 = archer?.division === 'U13' || archer?.division === 'U15';
        
        if (isOutdoor) {
            // Extérieur
            if (targetSize === '122') {
                return 'Img/122.png';
            } else if (targetSize === '80') {
                if (isCO && !isCONational) {
                    return 'Img/80CO.png';
                } else if (isU13U15 && targetFace === '3') {
                    return 'Img/3b.png'; // U13/U15
                } else if (targetFace === '1') {
                    return 'Img/80.png';
                } else if (targetFace === '3') {
                    return 'Img/3.png';
                }
            }
        } else {
            // Intérieur 
            if (targetSize === '80' && targetFace === '3') {
                return 'Img/80.png';
            } else if (targetSize === '60' && targetFace === '2') {
                return 'Img/60.png';
            } else if (targetSize === '60' && targetFace === '7') {
                return 'Img/60T.png';
            } else if (targetSize === '40' && targetFace === '1') {
                return 'Img/40.png';
            } else if (targetSize === '40' && targetFace === '4') {
                return 'Img/40TCO.png';
            } else if (targetSize === '40' && targetFace === '6') {
                return 'Img/40TCL.png';
            }
        }
        
        // Fallback
        if (targetSize === '80') return 'Img/80.png';
        if (targetSize === '122') return 'Img/122.png';
        if (targetSize === '60') return 'Img/60.png';
        if (targetSize === '40') return 'Img/40.png';
        
        return '';
    };

    const isPositionBlockedByWheelchair = (target, positionLetter, archers, session) => {
        const wheelchairPositions = target.positions
            .filter(pos => pos.archer && pos.archer.wheelchair && pos.archer.session === session)
            .map(pos => pos.letter);
        
        if (wheelchairPositions.length === 0) return false;
        
        const blockingPairs = {
            'A': 'C',
            'C': 'A',
            'B': 'D',
            'D': 'B'
        };
        
        return wheelchairPositions.some(wcPos => {
            if (wcPos === positionLetter) return false;
            return blockingPairs[wcPos] === positionLetter;
        });
    };

    // FONCTION CORRIGÉE POUR LA COMPATIBILITÉ DES DROPS
    const checkDropCompatibility = (target, positionLetter, archerToDrop, archers, session) => {
        const targetFaces = {};
        const distances = {};
        const targetSizes = {};
        const categories = {};
        const ages = {};
        const evCodes = {};
        const positions = ['A', 'B', 'C', 'D'];
        
        positions.forEach(letter => {
            const position = target.positions.find(p => p.letter === letter);
            if (position?.archer) {
                targetFaces[letter] = position.archer.targetFace || null;
                distances[letter] = position.archer.distance || null;
                targetSizes[letter] = position.archer.targetSize || '';
                categories[letter] = position.archer.division || ''; 
                ages[letter] = position.archer.division || '';
                evCodes[letter] = position.archer.evCode || '';
            } else {
                targetFaces[letter] = null;
                distances[letter] = null;
                targetSizes[letter] = '';
                categories[letter] = null;
                ages[letter] = null;
                evCodes[letter] = '';
            }
        });
        
        const otherPositions = positions.filter(letter => 
            letter !== positionLetter && targetFaces[letter]
        );
        
        // Règle 1: Même distance
        if (otherPositions.length > 0) {
            const firstDistance = distances[otherPositions[0]];
            if (archerToDrop.distance && firstDistance && archerToDrop.distance !== firstDistance) {
                return { 
                    valid: false, 
                    reason: `Distance incompatible (${archerToDrop.distance}m ≠ ${firstDistance}m)` 
                };
            }
        }
        
        // Règle 2: Pas de mélange 80cm avec 122cm
        if (otherPositions.length > 0) {
            const firstTargetSize = targetSizes[otherPositions[0]];
            if (archerToDrop.targetSize && firstTargetSize) {
                const is80cm = archerToDrop.targetSize === '80';
                const is122cm = archerToDrop.targetSize === '122';
                const otherIs80cm = firstTargetSize === '80';
                const otherIs122cm = firstTargetSize === '122';
                
                // 80cm et 122cm ne peuvent pas être mélangés
                if ((is80cm && otherIs122cm) || (is122cm && otherIs80cm)) {
                    return { 
                        valid: false, 
                        reason: `Type de cible incompatible: ne peut pas mélanger ${archerToDrop.targetSize}cm avec ${firstTargetSize}cm` 
                    };
                }
            }
        }
        
        // Règle 3: Face 3 (80cm) ne peut être mélangée avec d'autres faces
        if (archerToDrop.targetFace === '3') {
            const hasOtherFaces = positions.some(letter => 
                targetFaces[letter] && targetFaces[letter] !== '3' && letter !== positionLetter
            );
            if (hasOtherFaces) {
                return { valid: false, reason: 'Face 3 (80cm) ne peut être mélangée avec d\'autres faces' };
            }
        }
        
        // Règle 4: Si déjà un Face 3, pas d'autre face
        const existingFace3 = positions.some(letter => 
            targetFaces[letter] === '3' && letter !== positionLetter
        );
        if (existingFace3 && archerToDrop.targetFace !== '3') {
            return { valid: false, reason: 'Ne peut pas être mélangé avec Face 3 (80cm)' };
        }
        
        // Règle 5: Compatibilité avec la paire opposée
        const pairPositions = {
            'A': 'C',
            'C': 'A',
            'B': 'D',
            'D': 'B'
        };
        const otherPairLetter = pairPositions[positionLetter];
        const otherPairFace = targetFaces[otherPairLetter];
        
        if (otherPairFace && archerToDrop.targetFace) {
            if (!checkPairValid(archerToDrop.targetFace, otherPairFace)) {
                return { 
                    valid: false, 
                    reason: `Incompatible avec la position ${otherPairLetter} (face ${otherPairFace})` 
                };
            }
        }
        
        // Règle 6: Vérifier les règles Outdoor
        const isOutdoor = archerToDrop.distance && parseInt(archerToDrop.distance) > 18;
        
        if (isOutdoor) {
            // Règle 6a: U11
            if (archerToDrop.division === 'U11') {
                const hasNonU11 = otherPositions.some(letter => ages[letter] !== 'U11');
                if (hasNonU11) {
                    return { valid: false, reason: 'U11 ne peut pas être mixé avec d\'autres catégories' };
                }
            } else {
                const hasU11 = otherPositions.some(letter => ages[letter] === 'U11');
                if (hasU11) {
                    return { valid: false, reason: 'Ne peut pas être mixé avec U11' };
                }
            }
            
            // Règle 6b: U13/U15 - doivent être entre eux
            if (archerToDrop.division === 'U13' || archerToDrop.division === 'U15') {
                const hasOtherAges = otherPositions.some(letter => 
                    ages[letter] !== 'U13' && ages[letter] !== 'U15'
                );
                if (hasOtherAges) {
                    return { valid: false, reason: 'U13/U15 ne peuvent être mixés qu\'entre eux' };
                }
            } else {
                const hasU13U15 = otherPositions.some(letter => 
                    ages[letter] === 'U13' || ages[letter] === 'U15'
                );
                if (hasU13U15) {
                    return { valid: false, reason: 'Ne peut pas être mixé avec U13/U15' };
                }
            }
            
            // Règle 6c: Vérifier CO International vs CO National
            const isArcherCO = archerToDrop.division === 'CO' || (archerToDrop.division && archerToDrop.division.includes('CO'));
            const isArcherCONational = archerToDrop.evCode?.startsWith('N') || false;
            const isArcherCOInternational = isArcherCO && !isArcherCONational;
            
            if (isArcherCOInternational) {
                // CO International ne peut être mélangé qu'avec d'autres CO International
                const hasNonCOInternational = otherPositions.some(letter => {
                    const isOtherCO = categories[letter] === 'CO' || (categories[letter] && categories[letter].includes('CO'));
                    const isOtherCONational = evCodes[letter]?.startsWith('N') || false;
                    const isOtherCOInternational = isOtherCO && !isOtherCONational;
                    return !isOtherCOInternational;
                });
                
                if (hasNonCOInternational) {
                    return { 
                        valid: false, 
                        reason: 'CO International (H/F) ne peut pas être mixé avec d\'autres catégories' 
                    };
                }
            }
            
            if (isArcherCONational) {
                // CO National peut être mélangé avec d'autres archers sur 122cm
                // Vérifier que tous sont sur 122cm
                const all122cm = otherPositions.every(letter => targetSizes[letter] === '122');
                if (!all122cm) {
                    return { 
                        valid: false, 
                        reason: 'CO National (M/W) doit être sur cible 122cm avec d\'autres archers sur 122cm' 
                    };
                }
            }
            
            // Règle 6d: Si déjà un CO International sur la cible, pas d'autres catégories
            const hasExistingCOInternational = positions.some(letter => {
                const isOtherCO = categories[letter] === 'CO' || (categories[letter] && categories[letter].includes('CO'));
                const isOtherCONational = evCodes[letter]?.startsWith('N') || false;
                return isOtherCO && !isOtherCONational && letter !== positionLetter;
            });
            
            if (hasExistingCOInternational && !isArcherCOInternational) {
                return { 
                    valid: false, 
                    reason: 'Ne peut pas être mixé avec CO International (H/F)' 
                };
            }
        }
        
        return { valid: true };
    };

    const INITIAL_ARCHERS = <?php
    $tournamentType = '';
    $tournamentQuery = "SELECT ToTypeName FROM Tournament WHERE ToId=" . StrSafe_DB($_SESSION['TourId']);
    $tournamentResult = safe_r_sql($tournamentQuery);
    if ($tournamentRow = safe_fetch($tournamentResult)) {
        $tournamentType = $tournamentRow->ToTypeName ?: '';
    }
    
    // Récupérer les SesAth4Target pour chaque session
    $sessionAth4Target = [];
    $sessionQuery = "SELECT SesOrder, SesAth4Target FROM Session WHERE SesTournament=" . StrSafe_DB($_SESSION['TourId']) . " AND SesType='Q'";
    $sessionResult = safe_r_sql($sessionQuery);
    while ($sessionRow = safe_fetch($sessionResult)) {
        $sessionAth4Target[$sessionRow->SesOrder] = $sessionRow->SesAth4Target;
    }
    
$Select = "SELECT 
            EnId, 
            EnCode, 
            EnName, 
            EnFirstName, 
            EnCountry, 
            EnDivision, 
            EnClass, 
            QuSession, 
            QuTargetNo, 
            EnWChair, 
            QuScore, 
            QuGold, 
            QuXNine,
            CoCode, 
            CoName, 
            EnTargetFace,
            -- Distance depuis Events (sans valeur par défaut)
            EvDistance as EvDistance,
            -- Taille de blason depuis Events (sans valeur par défaut)
            EvTargetSize as EvTargetSize,
            -- Code événement formaté selon les nouvelles règles
            CASE 
                -- Pour les divisions BB (Barebow)
                WHEN EnDivision LIKE '%BB%' OR TRIM(EnDivision) = 'BB' THEN 
                    CASE 
                        -- S1HBB/S1FBB, S2HBB/S2FBB, S3HBB/S3FBB, BB -> SHBB/SFBB
                        WHEN EnDivision IN ('S1HBB', 'S1FBB', 'S2HBB', 'S2FBB', 'S3HBB', 'S3FBB', 'BB') THEN
                            CASE 
                                -- Pour 'BB' seul, déterminer le genre à partir de EnClass
                                WHEN TRIM(EnDivision) = 'BB' THEN
                                    CASE 
                                        -- Si EnClass contient 'W' -> SFBB, sinon -> SHBB
                                        WHEN TRIM(EnClass) LIKE '%W' THEN 'SFBB'
                                        ELSE 'SHBB'
                                    END
                                -- Prendre les 2 derniers caractères pour HBB/FBB
                                WHEN RIGHT(TRIM(EnDivision), 3) IN ('HBB', 'FBB') THEN 
                                    CONCAT('S', RIGHT(TRIM(EnDivision), 3))
                                ELSE TRIM(EnDivision)
                            END
                        -- U15HBB/U15FBB, U18HBB/U18FBB, U21HBB/U21FBB -> garder tel quel
                        ELSE TRIM(EnDivision)
                    END
                -- Pour les autres divisions
                ELSE CONCAT(
                    -- 'N' si national (M/W), sinon rien
                    CASE 
                        WHEN TRIM(EnClass) LIKE '%M' OR TRIM(EnClass) LIKE '%W' THEN 'N'
                        ELSE ''
                    END,
                    -- Classe sans le M/W à la fin
                    CASE 
                        WHEN TRIM(EnClass) LIKE '%M' THEN REPLACE(TRIM(EnClass), 'M', 'H')
                        WHEN TRIM(EnClass) LIKE '%W' THEN REPLACE(TRIM(EnClass), 'W', 'F')
                        ELSE TRIM(EnClass)
                    END,
                    -- Pour handisport OPCL - garder uniquement les 2 derniers caractères (CL)
                    CASE 
                        WHEN TRIM(EnDivision) = 'OPCL' THEN 'CL'
                        -- Division avec H/F selon M/W
                        WHEN TRIM(EnClass) LIKE '%M' THEN 
                            CASE 
                                WHEN RIGHT(TRIM(EnDivision), 1) = 'M' THEN 
                                    CONCAT(LEFT(TRIM(EnDivision), LENGTH(TRIM(EnDivision)) - 1), 'H')
                                ELSE TRIM(EnDivision)
                            END
                        WHEN TRIM(EnClass) LIKE '%W' THEN 
                            CASE 
                                WHEN RIGHT(TRIM(EnDivision), 1) = 'W' THEN 
                                    CONCAT(LEFT(TRIM(EnDivision), LENGTH(TRIM(EnDivision)) - 1), 'F')
                                ELSE TRIM(EnDivision)
                            END
                        ELSE TRIM(EnDivision)
                    END
                )
            END as EvCode
           FROM Entries 
           INNER JOIN Qualifications ON EnId=QuId 
           INNER JOIN Countries ON EnCountry=CoId AND EnTournament=CoTournament
           -- LEFT JOIN sur Events pour récupérer les infos distance et taille blason
           LEFT JOIN Events ON 
                Events.EvCode = CASE 
                    -- Pour les divisions BB (Barebow)
                    WHEN EnDivision LIKE '%BB%' OR TRIM(EnDivision) = 'BB' THEN 
                        CASE 
                            WHEN EnDivision IN ('S1HBB', 'S1FBB', 'S2HBB', 'S2FBB', 'S3HBB', 'S3FBB', 'BB') THEN
                                CASE 
                                    WHEN TRIM(EnDivision) = 'BB' THEN
                                        CASE 
                                            WHEN TRIM(EnClass) LIKE '%W' THEN 'SFBB'
                                            ELSE 'SHBB'
                                        END
                                    -- Prendre les 2 derniers caractères pour HBB/FBB
                                    WHEN RIGHT(TRIM(EnDivision), 3) IN ('HBB', 'FBB') THEN 
                                        CONCAT('S', RIGHT(TRIM(EnDivision), 3))
                                    ELSE TRIM(EnDivision)
                                END
                            ELSE TRIM(EnDivision)
                        END
                    ELSE CONCAT(
                        CASE 
                            WHEN TRIM(EnClass) LIKE '%M' OR TRIM(EnClass) LIKE '%W' THEN 'N'
                            ELSE ''
                        END,
                        CASE 
                            WHEN TRIM(EnClass) LIKE '%M' THEN REPLACE(TRIM(EnClass), 'M', 'H')
                            WHEN TRIM(EnClass) LIKE '%W' THEN REPLACE(TRIM(EnClass), 'W', 'F')
                            ELSE TRIM(EnClass)
                        END,
                        -- Pour handisport OPCL - garder uniquement les 2 derniers caractères (CL)
                        CASE 
                            WHEN TRIM(EnDivision) = 'OPCL' THEN 'CL'
                            -- Division avec H/F selon M/W
                            WHEN TRIM(EnClass) LIKE '%M' THEN 
                                CASE 
                                    WHEN RIGHT(TRIM(EnDivision), 1) = 'M' THEN 
                                        CONCAT(LEFT(TRIM(EnDivision), LENGTH(TRIM(EnDivision)) - 1), 'H')
                                    ELSE TRIM(EnDivision)
                                END
                            WHEN TRIM(EnClass) LIKE '%W' THEN 
                                CASE 
                                    WHEN RIGHT(TRIM(EnDivision), 1) = 'W' THEN 
                                        CONCAT(LEFT(TRIM(EnDivision), LENGTH(TRIM(EnDivision)) - 1), 'F')
                                    ELSE TRIM(EnDivision)
                                END
                            ELSE TRIM(EnDivision)
                        END
                    )
                END 
                AND EnTournament = Events.EvTournament
           WHERE EnTournament=" . StrSafe_DB($_SESSION['TourId']) . " AND EnAthlete=1";


    if (isset($_REQUEST["Event"]) && preg_match("/^[0-9A-Z%_]+$/i", $_REQUEST["Event"])) {
        $Select .= " AND CONCAT(TRIM(EnClass),TRIM(EnDivision)) LIKE " . StrSafe_DB($_REQUEST["Event"]) . " ";
    }

    $Select .= " ORDER BY EnName";
    $Rs = safe_r_sql($Select);
    $archers = [];

    while ($row = safe_fetch($Rs)) {
        $enClass = $row->EnClass ?: '';
        $enDivision = $row->EnDivision ?: '';
        
        if ($enDivision && strpos($enClass, $enDivision) === 0) {
            $pureClass = substr($enClass, strlen($enDivision));
        } else {
            $pureClass = $enClass;
        }
        
        // Récupérer le SesAth4Target de la session correspondante
        $sessionAth4 = isset($sessionAth4Target[$row->QuSession]) ? $sessionAth4Target[$row->QuSession] : 4;
        
        $archers[] = [
            'id' => (int)$row->EnId,
            'code' => $row->EnCode ?: '',
            'name' => trim($row->EnFirstName . ' ' . $row->EnName),
            'country' => $row->CoCode ?: '',
            'countryName' => $row->CoName ?: '',
            'division' => $enDivision,
            'class' => $pureClass,
            'session' => (int)$row->QuSession,
            'targetNo' => $row->QuTargetNo ?: '',
            'score' => ($row->QuScore ? $row->QuScore . '/' . $row->QuGold . '/' . $row->QuXNine : '0/0/0'),
            'wheelchair' => (bool)$row->EnWChair,
            'targetFace' => $row->EnTargetFace ?: '',
            'distance' => $row->EvDistance ?: '',
            'evCode' => $row->EvCode ?: '',
            'targetSize' => $row->EvTargetSize ?: '',
            'tournamentType' => $tournamentType,
            'sessionAth4Target' => $sessionAth4  // Inclure la valeur SesAth4Target de la session
        ];
    }

    echo json_encode($archers);
    ?>.map(archer => ({
        ...archer,
        targetNo: convertDbToInterface(archer.targetNo)
    }));

    const SESSIONS = <?php
        $sessions = GetSessions('Q');
        $sessionsList = [];
        foreach ($sessions as $s) {
            $sessionsList[] = [
                'order' => (int)$s->SesOrder,
                'description' => $s->Descr
            ];
        }
        echo json_encode($sessionsList);
    ?>;

    const getTargetSizeLabel = (targetSize) => {
        if (!targetSize) return '';
        
        const sizeMap = {
            '80': '80cm',
            '122': '122cm',
            '40': '40cm (triple)',
            '60': '60cm',
            '3x40': '3x40cm (triple)',
            '3x20': '3x20cm (triple)'
        };
        
        return sizeMap[targetSize] || `${targetSize}cm`;
    };

    const getTargetFaceLabel = (targetFace) => {
        if (!targetFace) return '';
        
        const faceMap = {
            '1': 'Face 1',
            '2': 'Face 2',
            '3': 'Face 3',
            '4': 'Face 4',
            '6': 'Face 6',
            '7': 'Face 7',
            'A': 'Face A',
            'B': 'Face B',
            'C': 'Face C',
            'D': 'Face D',
            'E': 'Face E'
        };
        
        return faceMap[targetFace] || `Face ${targetFace}`;
    };

    const isIndoorTournament = (tournamentType) => {
        if (!tournamentType) return false;
        
        const indoorKeywords = ['indoor', 'salle', 'intérieur', 'hall'];
        const lowerType = tournamentType.toLowerCase();
        
        return indoorKeywords.some(keyword => lowerType.includes(keyword));
    };

    const getArcherTargetFaceImage = (archer) => {
        if (!archer) return '';
        return getTargetFaceImage(archer.targetSize, archer.targetFace, archer.tournamentType, archer);
    };

    const TargetAssignmentUI = () => {
        const [archers, setArchers] = useState(INITIAL_ARCHERS);
        const [selectedSession, setSelectedSession] = useState(SESSIONS[0]?.order || 1);
        const [searchTerm, setSearchTerm] = useState('');
        const [draggedArcher, setDraggedArcher] = useState(null);
        const [draggedTargetId, setDraggedTargetId] = useState(null);
        const [autoSaveBlocked, setAutoSaveBlocked] = useState(false);
        const [notification, setNotification] = useState(null);
        const [isSaving, setIsSaving] = useState(false);
        const [hoverTargetId, setHoverTargetId] = useState(null);
        const [showTargetsOverview, setShowTargetsOverview] = useState(true);
        const dragStartPos = useRef({ x: 0, y: 0 });
        const targetsOverviewRef = useRef(null);
        const [scrollPosition, setScrollPosition] = useState(0);

        const getArcherBgColor = (archer, isAssigned = true) => {
            if (!isAssigned) {
                return 'archer-unassigned';
            }
            if (archer.wheelchair) return 'archer-wheelchair-assigned';
            return 'archer-assigned';
        };

        const getArcherTextColor = (archer, isAssigned = true) => {
            if (!isAssigned) {
                return 'text-orange-700';
            }
            if (archer.wheelchair) return 'text-blue-700';
            return 'text-green-700';
        };

        const handleArcherDragStart = (e, archer) => {
            setDraggedArcher(archer);
            e.dataTransfer.setData('text/plain', `archer:${archer.id}`);
            e.dataTransfer.effectAllowed = 'move';
            dragStartPos.current = { x: e.clientX, y: e.clientY };
            
            const bgColor = archer.wheelchair ? 
                'linear-gradient(135deg, rgba(219, 234, 254, 0.98), rgba(191, 219, 254, 0.98))' : 
                'linear-gradient(135deg, rgba(220, 252, 231, 0.98), rgba(187, 247, 208, 0.98))';
            
            const borderColor = archer.wheelchair ? '#3b82f6' : '#10b981';
            const textColor = archer.wheelchair ? '#1e40af' : '#166534';
            
            const dragImage = document.createElement('div');
            dragImage.className = `archer-drag-preview ${archer.wheelchair ? 'wheelchair' : 'regular'} archer-original-look`;
            dragImage.setAttribute('data-drag-preview', 'true');
            
            const nameParts = archer.name.split(' ');
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            const shortLastName = lastName.length > 10 ? lastName.substring(0, 8) + '...' : lastName;
            
            const targetFaceImage = getArcherTargetFaceImage(archer);
            const targetSizeLabel = getTargetSizeLabel(archer.targetSize);
            const targetFaceLabel = getTargetFaceLabel(archer.targetFace);
            const isIndoor = isIndoorTournament(archer.tournamentType);
            const isCONational = archer.evCode?.startsWith('N') || false;
            const isCOInternational = (archer.division === 'CO' || (archer.division && archer.division.includes('CO'))) && !isCONational;
            
            dragImage.innerHTML = `
                <div style="
                    display: flex; 
                    align-items: center; 
                    gap: 8px; 
                    margin-bottom: 4px;
                    padding: 4px 6px;
                    background: ${archer.wheelchair ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)'};
                    border-radius: 4px;
                    border: 1px solid ${archer.wheelchair ? 'rgba(59, 130, 246, 0.3)' : 'rgba(16, 185, 129, 0.3)'};
                ">
                    ${targetFaceImage ? `
                        <img src="${targetFaceImage}" 
                             alt="Target face" 
                             style="width: 30px; height: 30px; object-fit: contain; border-radius: 3px;">
                    ` : ''}
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: baseline; gap: 4px; margin-bottom: 1px;">
                            <div style="font-weight: 600; font-size: 11px; color: ${textColor}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${firstName}
                            </div>
                            <div style="font-weight: 500; font-size: 10px; color: #374151;">
                                ${shortLastName}
                            </div>
                            ${archer.wheelchair ? 
                                '<div style="margin-left: 2px; font-size: 11px; color: #3b82f6;" title="Archer en fauteuil">♿</div>' : 
                                ''
                            }
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="font-size: 9px; color: #4b5563;">
                                ${archer.countryName || archer.country}
                            </div>
                            <div style="font-size: 9px; font-weight: 600; color: ${textColor};">
                                ${archer.evCode || (archer.class + archer.division)}
                            </div>
                        </div>
                    </div>
                </div>
                
                ${archer.targetNo && archer.targetNo !== '' ? `
                <div style="
                    text-align: center; 
                    font-size: 10px; 
                    font-weight: 700; 
                    color: ${textColor};
                    margin: 4px 0;
                    padding: 2px 4px;
                    background: ${archer.wheelchair ? 'rgba(59, 130, 246, 0.15)' : 'rgba(16, 185, 129, 0.15)'};
                    border-radius: 3px;
                    border: 1px dashed ${borderColor};
                ">
                    ${archer.targetNo}
                </div>
                ` : `
                <div style="
                    text-align: center; 
                    font-size: 9px; 
                    color: #9ca3af;
                    margin: 4px 0;
                    padding: 2px 4px;
                    background: rgba(0,0,0,0.05);
                    border-radius: 3px;
                ">
                    Non assigné
                </div>
                `}
                
                ${isDebugMode && (targetSizeLabel || targetFaceLabel) ? `
                <div style="
                    display: flex; 
                    flex-wrap: wrap; 
                    gap: 3px; 
                    margin: 2px 0;
                    justify-content: center;
                ">
                    ${targetSizeLabel && isDebugMode ? `
                    <div style="
                        font-size: 8px; 
                        font-weight: bold;
                        color: #4f46e5;
                        padding: 1px 4px;
                        background: #e0e7ff;
                        border-radius: 3px;
                        border: 1px solid #c7d2fe;
                    ">
                        ${targetSizeLabel}
                    </div>
                    ` : ''}
                    
                    ${targetFaceLabel && isDebugMode ? `
                    <div style="
                        font-size: 8px; 
                        font-weight: bold;
                        color: #be185d;
                        padding: 1px 4px;
                        background: #fce7f3;
                        border-radius: 3px;
                        border: 1px solid #fbcfe8;
                    ">
                        ${targetFaceLabel}
                    </div>
                    ` : ''}
                </div>
                ` : ''}
                
                ${archer.evCode && archer.distance ? `
                <div style="
                    text-align: center; 
                    font-size: 9px; 
                    font-weight: bold;
                    color: #059669;
                    margin: 2px 0;
                    padding: 2px 4px;
                    background: #d1fae5;
                    border-radius: 3px;
                    border: 1px solid #10b981;
                ">
                    ${archer.evCode} / ${archer.distance}m
                </div>
                ` : archer.distance ? `
                <div style="
                    text-align: center; 
                    font-size: 9px; 
                    font-weight: bold;
                    color: #92400e;
                    margin: 2px 0;
                    padding: 2px 4px;
                    background: #fbbf24;
                    border-radius: 3px;
                ">
                    ${archer.distance}m
                </div>
                ` : archer.evCode ? `
                <div style="
                    text-align: center; 
                    font-size: 9px; 
                    font-weight: bold;
                    color: #059669;
                    margin: 2px 0;
                    padding: 2px 4px;
                    background: #d1fae5;
                    border-radius: 3px;
                    border: 1px solid #10b981;
                ">
                    ${archer.evCode}
                </div>
                ` : ''}
                
                <div style="
                    text-align: center; 
                    font-size: 8px; 
                    color: #6b7280; 
                    margin-top: 4px; 
                    padding-top: 4px; 
                    border-top: 1px dashed rgba(0,0,0,0.1);
                ">
                    Glisser pour déplacer
                </div>
            `;
            
            document.body.appendChild(dragImage);
            
            const rect = dragImage.getBoundingClientRect();
            
            e.dataTransfer.setDragImage(dragImage, rect.width / 2, rect.height / 2);
            
            if (e.target.classList) {
                e.target.classList.add('dragging-archer');
            }
            
            setTimeout(() => {
                const preview = document.querySelector('[data-drag-preview="true"]');
                if (preview) {
                    document.body.removeChild(preview);
                }
            }, 0);
        };

        const handleArcherDragEnd = (e) => {
            const draggingElements = document.querySelectorAll('.dragging-archer');
            draggingElements.forEach(el => el.classList.remove('dragging-archer'));
            
            const remainingPreviews = document.querySelectorAll('[data-drag-preview="true"]');
            remainingPreviews.forEach(preview => preview.remove());
            
            setDraggedArcher(null);
        };

		const generateSimplePDF = () => {
			console.log('=== GÉNÉRATION PDF SIMPLIFIÉE ===');
			
			// Récupérer les données
			const assignedArchers = archers.filter(a => 
				a.session === selectedSession && a.targetNo && a.targetNo.length >= 4
			);
			
			// Extraire les cibles uniques
			const targetNumbers = [];
			assignedArchers.forEach(archer => {
				const targetNum = archer.targetNo.substring(0, 3);
				if (!targetNumbers.includes(targetNum)) {
					targetNumbers.push(targetNum);
				}
			});
			
			// Trier
			targetNumbers.sort((a, b) => parseInt(a) - parseInt(b));
			
			console.log(`✅ ${targetNumbers.length} cibles:`, targetNumbers);
			
			if (targetNumbers.length === 0) {
				alert('Aucune cible assignée pour ce départ');
				return;
			}
			
			// Récupérer le chemin de base actuel
			const currentPath = window.location.pathname;
			const baseDir = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
			// Construire le chemin absolu pour les images
			const baseUrl = window.location.origin + baseDir + 'Img/';
			
			// Fonction pour récupérer les infos archer d'une position spécifique
			const getArcherForPosition = (targetId, letter) => {
				return assignedArchers.find(a => a.targetNo === `${targetId}${letter}`);
			};
			
			// Ouvrir une fenêtre pour le PDF
			const printWindow = window.open('', '_blank', 'width=1800,height=1000');
			
			if (!printWindow) {
				alert('⚠️ Veuillez autoriser les popups pour générer le PDF');
				return;
			}
			
			// Créer le HTML avec le CSS cible
			let html = `<!DOCTYPE html>
			<html>
			<head>
				<title>Cibles - Départ ${selectedSession}</title>
				<meta charset="UTF-8">
				<style>
					@page { 
						size: landscape; 
						margin: 5mm; 
					}
					body { 
						margin: 0; 
						padding: 0; 
						font-family: 'Segoe UI', Arial, sans-serif;
						background: white; 
					}
					.page-container { 
						padding: 8mm; 
					}
					.print-header { 
						text-align: center; 
						margin-bottom: 5mm; 
					}
					.print-header h1 { 
						font-size: 24px; 
						margin: 0 0 5px 0; 
						color: #000; 
					}
					.print-header .subtitle { 
						font-size: 14px; 
						color: #666; 
					}
					.targets-layout { 
						display: flex; 
						flex-direction: column; 
						gap: 15px; 
						margin-bottom: 15mm; 
					}
					.targets-row { 
						display: flex; 
						justify-content: center; 
						gap: 10px; 
					}
					.target-row-wrapper { 
						display: flex; 
						justify-content: space-between; 
						width: 100%; 
					}
					.target-card { 
						flex: 0 0 7%; 
						text-align: center; 
					}
					.target-number { 
						font-size: 13px; 
						font-weight: bold; 
						color: #000; 
						margin-bottom: 4px; 
					}
					.target-image { 
						width: 55px; 
						height: 55px; 
						object-fit: contain; 
						margin: 0 auto; 
						border: 1px solid #ddd;
					}
					.target-archers-grid { 
						display: grid; 
						grid-template-columns: repeat(4, 1fr); 
						gap: 3px; 
						font-size: 8px; 
					}
					.archer-column { 
						display: flex; 
						flex-direction: column; 
						align-items: center; 
						min-height: 200px; 
						border: 1px solid #e0e0e0; 
						padding: 3px 2px; 
						background: #fafafa; 
					}
					.position-header { 
						font-weight: bold; 
						font-size: 9px; 
						color: #333; 
						margin-bottom: 2px; 
						width: 100%; 
						text-align: center; 
						background: #f0f0f0; 
						padding: 1px 0; 
					}
					.archer-content { 
						display: flex; 
						flex-direction: column; 
						align-items: center; 
						width: 100%; 
						height: 200px; 
						overflow: hidden; 
						padding: 0 1px; 
					}
					.archer-full-line { 
						font-size: 8px; 
						writing-mode: vertical-lr; 
						text-orientation: mixed; 
						height: 200px; 
						display: flex; 
						align-items: center; 
						text-align: center; 
						width: 100%; 
					}
					.empty-position { 
						color: #999; 
						font-size: 7.5px; 
						font-style: italic; 
						writing-mode: vertical-lr; 
						text-align: center; 
						width: 100%; 
						display: flex; 
						align-items: center; 
						height: 100%; 
					}
					.print-footer { 
						text-align: center; 
						margin-top: 12mm; 
						font-size: 11px; 
						color: #666; 
						border-top: 1px solid #ddd; 
						padding-top: 4mm; 
					}
					.print-controls { 
						position: fixed;
						top: 20px; 
						right: 20px; 
						z-index: 10000; 
						display: flex; 
						gap: 10px; 
					}
					.print-button { 
						padding: 10px 20px; 
						background: #3b82f6; 
						color: white; 
						border: none; 
						border-radius: 5px; 
						cursor: pointer; 
					}
					.close-button { 
						padding: 10px 20px; 
						background: #6b7280; 
						color: white; 
						border: none; 
						border-radius: 5px; 
						cursor: pointer; 
					}
					.page-break { 
						page-break-before: always; 
					}
					@media print { 
						.print-controls { 
							display: none !important; 
						} 
						.page-container { 
							padding: 0; 
						} 
					}
				</style>
			</head>
			<body>
				<div class="print-controls">
					<button class="print-button" onclick="window.print()">Imprimer</button>
					<button class="close-button" onclick="window.close()">Fermer</button>
				</div>
				
				<div class="page-container">`;
			
			// Diviser en pages de 20 cibles
			const targetsPerPage = 20;
			const totalPages = Math.ceil(targetNumbers.length / targetsPerPage);
			
			for (let page = 0; page < totalPages; page++) {
				const start = page * targetsPerPage;
				const end = start + targetsPerPage;
				const pageTargets = targetNumbers.slice(start, end);
				
				// Ajouter un saut de page sauf pour la première page
				if (page > 0) {
					html += `<div class="page-break"></div>`;
				}
				
				html += `
					<div class="print-header">
						<h1>Cibles - Départ ${selectedSession}</h1>
						<div class="subtitle">Page ${page + 1} sur ${totalPages}</div>
					</div>
					
					<div class="targets-layout">`;
				
				// 2 lignes de 10 cibles
				for (let row = 0; row < 2; row++) {
					const rowStart = row * 10;
					const rowEnd = rowStart + 10;
					const rowTargets = pageTargets.slice(rowStart, rowEnd);
					
					if (rowTargets.length > 0) {
						html += `<div class="targets-row">
							<div class="target-row-wrapper">`;
						
						for (let i = 0; i < 10; i++) {
							if (i < rowTargets.length) {
								const targetId = rowTargets[i];
								// Créer un objet cible pour getCombinationImage
								const targetObj = {
									id: targetId,
									positions: ['A', 'B', 'C', 'D'].map(letter => ({
										id: `${targetId}${letter}`,
										letter: letter,
										archer: getArcherForPosition(targetId, letter)
									}))
								};
								const combinationData = getCombinationImage(targetObj);
								const combinationImage = combinationData.image;
								
								html += `<div class="target-card">
									<div class="target-number">${targetId}</div>`;
								
								if (combinationImage && combinationImage !== 'Img/xx.png') {
									// Utiliser le chemin absolu pour l'image
									const absoluteImagePath = baseUrl + combinationImage.replace('Img/', '');
									html += `<img src="${absoluteImagePath}" class="target-image" alt="Cible ${targetId}" onerror="this.onerror=null; this.src=''; this.style.display='none'; console.error('Image non trouvée:', '${combinationImage}')">`;
								} else {
									html += `<div class="target-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 3px;">
										<div style="font-size: 8px; color: #999;">Pas d'image</div>
									</div>`;
								}
								
								html += `<div class="target-archers-grid">`;
								
								// Afficher les archers dans l'ordre A, C, B, D
								['A', 'C', 'B', 'D'].forEach(letter => {
									const archer = getArcherForPosition(targetId, letter);
									
									html += `<div class="archer-column">
										<div class="position-header">${letter}</div>
										<div class="archer-content">`;
									
									if (archer) {
										const division = archer.division || '';
										const archerClass = archer.class || '';
										const name = archer.name || '';
										const country = archer.countryName || '';
										
										let line = '';
										if (division) line += `<span style="font-weight: bold;">${division}</span>`;
										if (archerClass) line += (line ? ' ' : '') + `<span>${archerClass}</span>`;
										if ((division || archerClass) && name) line += ' ';
										if (name) line += `<span style="font-weight: bold;">&nbsp;${name}&nbsp;</span>`;
										if (name && country) line += ' ';
										if (country) line += `<span style="font-weight: bold;">&nbsp;/ ${country}</span>`;
										
										html += `<div class="archer-full-line">${line}</div>`;
									} else {
										html += `<div class="empty-position">vide</div>`;
									}
									
									html += `</div></div>`;
								});
								
								html += `</div></div>`;
							} else {
								// Case vide
								html += `<div class="target-card" style="opacity:0.3;">
									<div class="target-number"></div>
									<div class="target-image" style="background: #f5f5f5;"></div>
									<div class="target-archers-grid">
										<div class="archer-column"><div class="position-header">A</div><div class="archer-content" style="background: #f9f9f9;"></div></div>
										<div class="archer-column"><div class="position-header">C</div><div class="archer-content" style="background: #f9f9f9;"></div></div>
										<div class="archer-column"><div class="position-header">B</div><div class="archer-content" style="background: #f9f9f9;"></div></div>
										<div class="archer-column"><div class="position-header">D</div><div class="archer-content" style="background: #f9f9f9;"></div></div>
									</div>
								</div>`;
							}
						}
						
						html += `</div></div>`;
					}
				}
				
				html += `</div>`;
				
			}
			
			html += `</div></body></html>`;
			
			// Afficher
			printWindow.document.write(html);
			printWindow.document.close();
			printWindow.focus();
			
			console.log('✅ PDF généré avec succès');
			console.log('Base URL pour les images:', baseUrl);
		};


        const debugTargets = () => {
            console.log('=== DÉBUG COMPLET DES CIBLES ===');
            
            const assignedArchers = archers.filter(a => 
                a.session === selectedSession && a.targetNo && a.targetNo !== ''
            );
            
            const targetNumbers = [...new Set(assignedArchers.map(a => a.targetNo.substring(0, 3)))];
            targetNumbers.sort((a, b) => parseInt(a) - parseInt(b));
            
            console.log(`Cibles assignées: ${targetNumbers.length}`);
            console.log('Liste:', targetNumbers);
            
            console.log('Cible 021:', targetNumbers.includes('021'));
            console.log('Cible 022:', targetNumbers.includes('022'));
        };

        const generateTargets = () => {
            let highestTarget = 0;
            archers.forEach(archer => {
                if (archer.targetNo && archer.targetNo !== '') {
                    const targetNum = parseInt(archer.targetNo.substring(0, 3));
                    if (targetNum > highestTarget) {
                        highestTarget = targetNum;
                    }
                }
            });
            
            const maxTargets = Math.max(highestTarget + 5, 30);
            
            const targets = [];
            for (let targetNum = 1; targetNum <= maxTargets; targetNum++) {
                const targetId = String(targetNum).padStart(3, '0');
                targets.push({
                    number: targetNum,
                    id: targetId,
                    positions: ['A', 'B', 'C', 'D'].map(letter => ({
                        id: `${targetId}${letter}`,
                        letter: letter,
                        archer: null
                    }))
                });
            }
            return targets;
        };

        const recalculateTargets = (archersList, session) => {
            const targets = generateTargets();
            return targets.map(target => ({
                ...target,
                positions: target.positions.map(pos => {
                    const archer = archersList.find(a => 
                        a.targetNo === pos.id && 
                        a.session === session
                    );
                    return { ...pos, archer };
                })
            }));
        };

        const [targets, setTargets] = useState(recalculateTargets(archers, selectedSession));

        useEffect(() => {
            const updatedTargets = recalculateTargets(archers, selectedSession);
            setTargets(updatedTargets);
        }, [selectedSession]);

        const filteredArchers = archers.filter(archer => 
            archer.session === selectedSession &&
            (archer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
             archer.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
             archer.country.toLowerCase().includes(searchTerm.toLowerCase()))
        );

        const unassignedArchers = filteredArchers.filter(a => !a.targetNo || a.targetNo === '');
        const assignedArchers = filteredArchers.filter(a => a.targetNo && a.targetNo !== '');

        const getLastAssignedTarget = () => {
            let lastTarget = 0;
            assignedArchers.forEach(archer => {
                if (archer.targetNo && archer.targetNo !== '') {
                    const targetNum = parseInt(archer.targetNo.substring(0, 3));
                    if (targetNum > lastTarget) {
                        lastTarget = targetNum;
                    }
                }
            });
            return lastTarget;
        };

        const lastAssignedTarget = getLastAssignedTarget();
        
        const getTargetsToDisplay = () => {
            const assignedTargetNumbers = [];
            assignedArchers.forEach(archer => {
                if (archer.targetNo && archer.targetNo !== '') {
                    const targetNum = parseInt(archer.targetNo.substring(0, 3));
                    if (!assignedTargetNumbers.includes(targetNum)) {
                        assignedTargetNumbers.push(targetNum);
                    }
                }
            });
            
            assignedTargetNumbers.sort((a, b) => a - b);
            
            if (assignedTargetNumbers.length === 0) {
                return 10;
            }
            
            const maxAssigned = Math.max(...assignedTargetNumbers);
            return Math.max(maxAssigned + 2, 10);
        };

        const displayTargetsCount = getTargetsToDisplay();

        const hasAssignedArchers = (targetId) => {
            return assignedArchers.some(archer => 
                archer.targetNo && archer.targetNo !== '' && archer.targetNo.startsWith(targetId)
            );
        };

        const countArchersOnTarget = (targetId) => {
            return assignedArchers.filter(archer => 
                archer.targetNo && archer.targetNo !== '' && archer.targetNo.startsWith(targetId)
            ).length;
        };

        const handleOverviewScroll = () => {
            if (targetsOverviewRef.current) {
                setScrollPosition(targetsOverviewRef.current.scrollLeft);
            }
        };

        useEffect(() => {
            const overviewElement = targetsOverviewRef.current;
            if (overviewElement) {
                overviewElement.addEventListener('scroll', handleOverviewScroll);
                return () => {
                    overviewElement.removeEventListener('scroll', handleOverviewScroll);
                };
            }
        }, []);

        const handleDragOver = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        };

        const handleArcherDrop = (e, positionId) => {
            e.preventDefault();
            
            const data = e.dataTransfer.getData('text/plain');
            if (!data.startsWith('archer:')) return;
            
            const archerId = parseInt(data.split(':')[1]);
            const draggedArcher = archers.find(a => a.id === archerId);
            
            if (!draggedArcher) return;
            
            const targetId = positionId.substring(0, 3);
            const positionLetter = positionId.charAt(3);
            const target = targets.find(t => t.id === targetId);
            
            if (!target) return;
            
            const isBlocked = isPositionBlockedByWheelchair(target, positionLetter, archers, selectedSession);
            if (isBlocked) {
                showNotification('Cette position est bloquée par un archer en fauteuil', 'error');
                return;
            }
            
            if (draggedArcher.wheelchair) {
                const blockingPairs = { 'A': 'C', 'C': 'A', 'B': 'D', 'D': 'B' };
                const blockedPositionLetter = blockingPairs[positionLetter];
                const blockedPosition = target.positions.find(p => p.letter === blockedPositionLetter);
                
                if (blockedPosition?.archer) {
                    showNotification(
                        `Impossible de placer un fauteuil en ${positionLetter} : la position ${blockedPositionLetter} est occupée`,
                        'error'
                    );
                    return;
                }
            }
            
            const compatibilityCheck = checkDropCompatibility(
                target, 
                positionLetter, 
                draggedArcher, 
                archers, 
                selectedSession
            );
            
            if (!compatibilityCheck.valid) {
                showNotification(compatibilityCheck.reason, 'error');
                return;
            }
            
            const currentArcherOnPosition = archers.find(a => 
                a.targetNo === positionId && 
                a.session === selectedSession
            );
            
            let updatedArchers = [...archers];
            
            if (currentArcherOnPosition) {
                const compatibilityCheck2 = checkDropCompatibility(
                    target,
                    positionLetter,
                    currentArcherOnPosition,
                    updatedArchers.map(a => a.id === draggedArcher.id ? 
                        { ...a, targetNo: '' } : a),
                    selectedSession
                );
                
                if (!compatibilityCheck2.valid) {
                    showNotification(`Échange impossible: ${compatibilityCheck2.reason}`, 'error');
                    return;
                }
                
                updatedArchers = updatedArchers.map(archer => {
                    if (archer.id === draggedArcher.id) {
                        return { ...archer, targetNo: positionId };
                    }
                    if (archer.id === currentArcherOnPosition.id) {
                        return { ...archer, targetNo: draggedArcher.targetNo || '' };
                    }
                    return archer;
                });
            } else {
                updatedArchers = updatedArchers.map(archer => {
                    if (archer.id === draggedArcher.id) {
                        return { ...archer, targetNo: positionId };
                    }
                    return archer;
                });
            }
            
            setArchers(updatedArchers);
            
            const updatedTargets = recalculateTargets(updatedArchers, selectedSession);
            setTargets(updatedTargets);
            
            if (!autoSaveBlocked) {
                saveToDatabase(draggedArcher.id, positionId, selectedSession);
                if (currentArcherOnPosition) {
                    saveToDatabase(
                        currentArcherOnPosition.id, 
                        draggedArcher.targetNo || '', 
                        selectedSession
                    );
                }
            }
            
            showNotification(
                currentArcherOnPosition
                    ? `${draggedArcher.name} échangé avec ${currentArcherOnPosition.name}`
                    : `${draggedArcher.name} assigné(e) à ${positionId}`,
                'success'
            );
        };

        const handleTargetNumberDragStart = (e, targetId) => {
            const hasArchers = archers.some(a => 
                a.targetNo && 
                a.targetNo !== '' &&
                a.targetNo.startsWith(targetId) && 
                a.session === selectedSession
            );
            
            if (!hasArchers) {
                e.preventDefault();
                return;
            }
            
            setDraggedTargetId(targetId);
            e.dataTransfer.setData('text/plain', `target:${targetId}`);
            e.dataTransfer.effectAllowed = 'move';
            dragStartPos.current = { x: e.clientX, y: e.clientY };
            
            setTimeout(() => {
                const targetElement = e.target.closest('[data-target-id]');
                if (targetElement) {
                    targetElement.classList.add('dragging-target');
                }
            }, 0);
        };

        const handleTargetNumberDragEnd = (e) => {
            const targetElement = e.target.closest('[data-target-id]');
            if (targetElement) {
                targetElement.classList.remove('dragging-target');
            }
            setDraggedTargetId(null);
            setHoverTargetId(null);
        };

        const handleTargetNumberDragOver = (e, targetId) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (targetId !== draggedTargetId) {
                setHoverTargetId(targetId);
            }
        };

        const handleTargetNumberDragLeave = (e, targetId) => {
            if (hoverTargetId === targetId) {
                setHoverTargetId(null);
            }
        };

        const handleTargetNumberDrop = (e, targetId) => {
            e.preventDefault();
            
            const data = e.dataTransfer.getData('text/plain');
            if (!data.startsWith('target:')) return;
            
            const sourceTargetId = data.split(':')[1];
            
            if (!sourceTargetId || sourceTargetId === targetId) {
                setHoverTargetId(null);
                return;
            }
            
            const sourceArchers = archers.filter(a => 
                a.targetNo && 
                a.targetNo !== '' &&
                a.targetNo.startsWith(sourceTargetId) && 
                a.session === selectedSession
            );
            
            if (sourceArchers.length === 0) {
                showNotification(`La cible ${sourceTargetId} n'a pas d'archers assignés`, 'error');
                setHoverTargetId(null);
                return;
            }
            
            const destArchers = archers.filter(a => 
                a.targetNo && 
                a.targetNo !== '' &&
                a.targetNo.startsWith(targetId) && 
                a.session === selectedSession
            );
            
            const simulateExchange = () => {
                const allArchersAfterExchange = archers.map(archer => {
                    if (archer.session !== selectedSession) return archer;
                    
                    if (archer.targetNo && archer.targetNo !== '' && archer.targetNo.startsWith(sourceTargetId)) {
                        const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                        return { ...archer, targetNo: targetId + letter };
                    }
                    
                    if (archer.targetNo && archer.targetNo !== '' && archer.targetNo.startsWith(targetId)) {
                        const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                        return { ...archer, targetNo: sourceTargetId + letter };
                    }
                    
                    return archer;
                });
                
                const sourceTargetAfter = {
                    id: sourceTargetId,
                    positions: ['A', 'B', 'C', 'D'].map(letter => ({
                        id: `${sourceTargetId}${letter}`,
                        letter: letter,
                        archer: allArchersAfterExchange.find(a => 
                            a.targetNo === `${sourceTargetId}${letter}` && 
                            a.session === selectedSession
                        )
                    }))
                };
                
                const destTargetAfter = {
                    id: targetId,
                    positions: ['A', 'B', 'C', 'D'].map(letter => ({
                        id: `${targetId}${letter}`,
                        letter: letter,
                        archer: allArchersAfterExchange.find(a => 
                            a.targetNo === `${targetId}${letter}` && 
                            a.session === selectedSession
                        )
                    }))
                };
                
                const sourceDataAfter = getCombinationImage(sourceTargetAfter);
                const destDataAfter = getCombinationImage(destTargetAfter);
                
                return {
                    sourceInvalid: sourceDataAfter.image === 'Img/xx.png',
                    destInvalid: destDataAfter.image === 'Img/xx.png',
                    allArchersAfterExchange
                };
            };
            
            const simulation = simulateExchange();
            
            if (simulation.sourceInvalid || simulation.destInvalid) {
                let errorMsg = 'Échange impossible : ';
                const errors = [];
                if (simulation.sourceInvalid) errors.push(`cible ${sourceTargetId} deviendrait invalide`);
                if (simulation.destInvalid) errors.push(`cible ${targetId} deviendrait invalide`);
                errorMsg += errors.join(' et ');
                
                showNotification(errorMsg, 'error');
                setHoverTargetId(null);
                return;
            }
            
            const updatedArchers = simulation.allArchersAfterExchange;

            setArchers(updatedArchers);
            
            const updatedTargets = recalculateTargets(updatedArchers, selectedSession);
            setTargets(updatedTargets);

            if (!autoSaveBlocked) {
                sourceArchers.forEach(archer => {
                    const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                    saveToDatabase(archer.id, targetId + letter, selectedSession);
                });
                
                destArchers.forEach(archer => {
                    const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                    saveToDatabase(archer.id, sourceTargetId + letter, selectedSession);
                });
            }

            showNotification(
                `Cibles ${sourceTargetId} et ${targetId} échangées (${sourceArchers.length + destArchers.length} archers)`,
                'success'
            );
            
            setDraggedTargetId(null);
            setHoverTargetId(null);
        };

        const handleDropToUnassigned = (e) => {
            e.preventDefault();
            
            const data = e.dataTransfer.getData('text/plain');
            if (!data.startsWith('archer:')) return;
            
            const archerId = parseInt(data.split(':')[1]);
            const draggedArcher = archers.find(a => a.id === archerId);
            
            if (!draggedArcher) return;

            const updatedArchers = archers.map(a => {
                if (a.id === draggedArcher.id) {
                    return { ...a, targetNo: '' };
                }
                return a;
            });

            setArchers(updatedArchers);
            
            const updatedTargets = recalculateTargets(updatedArchers, selectedSession);
            setTargets(updatedTargets);

            if (!autoSaveBlocked) {
                saveToDatabase(draggedArcher.id, '', selectedSession);
            }

            showNotification(`${draggedArcher.name} est maintenant non assigné`, 'info');
        };

        const saveToDatabase = async (archerId, targetNo, session) => {
            setIsSaving(true);
            try {
                const formData = new FormData();
                formData.append('EnId', archerId);
                
                const dbTargetNo = convertInterfaceToDb(targetNo, session);
                
                formData.append('QuTargetNo', dbTargetNo);
                formData.append('QuSession', session);

                const response = await fetch('<?php echo $CFG->ROOT_DIR; ?>Modules/Custom/GraphicalView/SaveTargetAssignment.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (!result.success) {
                    showNotification('Erreur lors de la sauvegarde: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showNotification('Erreur de communication avec le serveur', 'error');
            } finally {
                setIsSaving(false);
            }
        };

        const showNotification = (message, type) => {
            setNotification({ message, type });
            setTimeout(() => setNotification(null), 3000);
        };

        const getPositionColor = (position, isBlocked) => {
            if (isBlocked) return 'bg-gray-100 border-gray-300';
            if (!position.archer) return 'position-empty';
            if (position.archer.wheelchair) return 'archer-wheelchair-assigned';
            return 'archer-assigned';
        };

        const scrollToTarget = (targetId) => {
            const element = document.querySelector(`[data-target-id="${targetId}"]`);
            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
                
                element.classList.add('highlight-target');
                
                setTimeout(() => {
                    element.classList.remove('highlight-target');
                }, 2000);
            }
        };

        const getTargetCombinationImage = (targetId) => {
            const target = targets.find(t => t.id === targetId);
            if (!target) return null;
            return getCombinationImage(target);
        };

        const isTargetValid = (targetId) => {
            const target = targets.find(t => t.id === targetId);
            if (!target) return true;
            const combinationData = getCombinationImage(target);
            return combinationData.image !== 'Img/xx.png';
        };

        return (
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6">
                <div className="mx-auto">

                    <div className="bg-white rounded-lg shadow-lg p-4 mb-6">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                                <div className="w-5 h-5 text-blue-600"><Icons.Grid /></div>
                                <div className="flex items-center gap-3 ml-2">
                                    <h2 className="text-lg font-bold text-gray-800">Départ</h2>
                                    <select
                                        value={selectedSession}
                                        onChange={(e) => {
                                            const newSession = Number(e.target.value);
                                            setSelectedSession(newSession);
                                            const updatedTargets = recalculateTargets(archers, newSession);
                                            setTargets(updatedTargets);
                                        }}
                                        className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[200px]"
                                    >
                                        {SESSIONS.map(session => (
                                            <option key={session.order} value={session.order}>
                                                {session.description}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => {
                                        debugTargets();
                                        generateSimplePDF();
                                    }}
                                    className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                                    style={{
                                        backgroundColor: '#2563eb',
                                        color: 'white',
                                        border: '2px solid #1d4ed8'
                                    }}
                                    onMouseEnter={(e) => {
                                        e.currentTarget.style.backgroundColor = '#1d4ed8';
                                    }}
                                    onMouseLeave={(e) => {
                                        e.currentTarget.style.backgroundColor = '#2563eb';
                                    }}
                                    title="Générer un PDF de toutes les cibles via DragDropPlanPDF.php"
                                >
                                    <div className="w-4 h-4"><Icons.Print /></div>
                                    <span>Imprimer PDF</span>
                                </button>
                                    
                                    <button
                                        onClick={() => setShowTargetsOverview(!showTargetsOverview)}
                                        className="flex items-center gap-1 px-3 py-1 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded"
                                    >
                                        {showTargetsOverview ? (
                                            <>
                                                <span>Masquer</span>
                                                <div className="w-4 h-4"><Icons.ChevronUp /></div>
                                            </>
                                        ) : (
                                            <>
                                                <span>Afficher</span>
                                                <div className="w-4 h-4"><Icons.ChevronDown /></div>
                                            </>
                                        )}
                                    </button>
                                </div>
                        </div>
                        
                        {showTargetsOverview && (
                            <>
                                <div 
                                    ref={targetsOverviewRef}
                                    className="targets-overview flex gap-3 pb-3 overflow-x-auto"
                                    style={{ maxHeight: '180px' }}
                                >
                                    {Array.from({ length: displayTargetsCount }, (_, index) => {
                                        const targetNum = index + 1;
                                        const targetId = targetNum.toString().padStart(3, '0');
                                        
                                        if (!targets.find(t => t.id === targetId)) return null;
                                        
                                        const combinationData = getTargetCombinationImage(targetId);
                                        const combinationImage = combinationData ? combinationData.image : null;
                                        const distance = combinationData ? combinationData.distance : null;
                                        const isValid = isTargetValid(targetId);
                                        const hasArchers = hasAssignedArchers(targetId);
                                        const archersCount = countArchersOnTarget(targetId);
                                        const isActive = hoverTargetId === targetId;
                                        
                                        return (
                                            <div
                                                key={targetId}
                                                className={`target-thumbnail flex-shrink-0 w-16 p-2 border-2 rounded-lg text-center cursor-pointer transition-all relative ${
                                                    isActive ? 'active border-blue-500 bg-blue-50' : 
                                                    !isValid ? 'border-red-300 bg-red-50' :
                                                    hasArchers ? 'has-archers' : 
                                                    'border-gray-200 bg-gray-50'
                                                }`}
                                                onClick={() => scrollToTarget(targetId)}
                                                onMouseEnter={() => setHoverTargetId(targetId)}
                                                onMouseLeave={() => {
                                                    if (hoverTargetId === targetId) {
                                                        setHoverTargetId(null);
                                                    }
                                                }}
                                                title={`Cible ${targetId} - ${archersCount} archer(s) - Cliquer pour aller à la cible`}
                                            >
                                                <div className="relative w-full h-16 mb-1 flex items-center justify-center">
                                                    {combinationImage ? (
                                                        <img 
                                                            src={combinationImage} 
                                                            alt={`Cible ${targetId}`}
                                                            className="max-w-full max-h-full object-contain"
                                                            onError={(e) => {
                                                                e.target.style.display = 'none';
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center bg-gray-100 rounded">
                                                            <div className="w-8 h-8 text-gray-400">
                                                                <Icons.Target />
                                                            </div>
                                                        </div>
                                                    )}
                                                    
                                                    {archersCount > 0 && (
                                                        <div className="target-badge">
                                                            {archersCount}
                                                        </div>
                                                    )}
                                                </div>
                                                
                                                <div className={`font-bold mt-1 ${
                                                    !isValid ? 'text-red-600' :
                                                    hasArchers ? 'text-green-800' : 
                                                    'text-gray-500'
                                                }`}>
                                                    {targetId}
                                                </div>
                                                
                                                {/* AFFICHAGE DE LA DISTANCE */}
                                                {distance && (
                                                    <div className={`target-distance mt-1 ${
                                                        parseInt(distance) <= 18 ? 'target-distance-indoor' : 'target-distance-outdoor'
                                                    }`}>
                                                        {distance}m
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    }).filter(Boolean)}
                                </div>
                                
                            </>
                        )}
                    </div>

                    <div className="flex gap-6 flex-container">
                        <div className="w-19 flex-shrink-0">
                            <div className="bg-white rounded-lg shadow-lg p-4 sticky top-6">

                                
                                <div 
                                    className="space-y-3 max-h-[calc(100vh-200px)] overflow-y-auto p-4 rounded-lg border-2 border-dashed border-gray-300 hover:border-orange-400 transition-colors bg-gray-50 min-h-[300px] cursor-copy"
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        e.currentTarget.classList.add('border-orange-400', 'bg-orange-50', 'drop-zone-active');
                                    }}
                                    onDragLeave={(e) => {
                                        e.currentTarget.classList.remove('border-orange-400', 'bg-orange-50', 'drop-zone-active');
                                    }}
                                    onDrop={(e) => {
                                        e.currentTarget.classList.remove('border-orange-400', 'bg-orange-50', 'drop-zone-active');
                                        handleDropToUnassigned(e);
                                    }}
                                >
                                    <div className="text-center mb-4 pb-3 border-b border-gray-200">
                                        <div className="text-lg font-bold text-gray-800 mb-1">
                                            Non assignés ({unassignedArchers.length})
                                        </div>
                                        <div className="text-sm text-gray-600">
                                            Glissez les archers ici pour les désassigner
                                        </div>
                                    </div>
                                    
                                    {unassignedArchers.map(archer => {
                                        const targetSizeLabel = getTargetSizeLabel(archer.targetSize);
                                        const targetFaceLabel = getTargetFaceLabel(archer.targetFace);
                                        const isIndoor = isIndoorTournament(archer.tournamentType);
                                        const targetFaceImage = getArcherTargetFaceImage(archer);
                                        const isCONational = archer.evCode?.startsWith('N') || false;
                                        const isCOInternational = (archer.division === 'CO' || (archer.division && archer.division.includes('CO'))) && !isCONational;
                                        
                                        return (
                                            <div
                                                key={archer.id}
                                                draggable
                                                onDragStart={(e) => handleArcherDragStart(e, archer)}
                                                onDragEnd={handleArcherDragEnd}
                                                className={`p-3 border-2 rounded-lg cursor-move hover:shadow-md transition no-select ${getArcherBgColor(archer, false)}`}
                                                data-archer-id={archer.id}
                                            >
                                                <div className="flex items-start gap-2">
                                                    {targetFaceImage && (
                                                        <img 
                                                            src={targetFaceImage} 
                                                            alt="Target face"
                                                            className="w-12 h-12 flex-shrink-0"
                                                            onError={(e) => {
                                                                e.target.style.display = 'none';
                                                            }}
                                                        />
                                                    )}
                                                    <div className="flex-1 min-w-0">
                                                        <div className={`font-bold text-sm truncate ${getArcherTextColor(archer, false)}`}>
                                                            {archer.name}
                                                            {archer.wheelchair && ' ♿'}
                                                        </div>
                                                        <div className="text-xs text-gray-600 mt-1 truncate">
                                                            {archer.countryName}
                                                        </div>
                                                        <div className={`text-xs font-medium mt-1 truncate ${getArcherTextColor(archer, false)}`}>
                                                            {archer.evCode || (archer.class + archer.division)}
                                                            {archer.distance && ` / ${archer.distance}m`}
                                                        </div>
                                                        
                                                        {isDebugMode && (targetSizeLabel || targetFaceLabel) && (
                                                            <div className="flex flex-wrap gap-1 mt-2">
                                                                {targetSizeLabel && isDebugMode && (
                                                                    <span className="target-size-badge">
                                                                        {targetSizeLabel}
                                                                    </span>
                                                                )}
                                                                {targetFaceLabel && isDebugMode && (
                                                                    <span className="face-badge">
                                                                        {getTargetFaceLabel(archer.targetFace)}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}

                                    {unassignedArchers.length === 0 && (
                                        <div className="text-center text-gray-500 text-sm py-12 border-2 border-dashed border-gray-300 rounded-lg bg-white">
                                            <div className="w-12 h-12 text-gray-300 mx-auto mb-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 4v16m8-8H4" />
                                                </svg>
                                            </div>
                                            <div className="font-medium text-gray-600 mb-1">
                                                Zone de désassignement vide
                                            </div>
                                            <div className="text-gray-500">
                                                Glissez un archer ici pour le désassigner
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="main-target-column">
                            <div className="bg-white rounded-lg shadow-lg p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-xl font-bold text-gray-800">
                                        Terrain de Tir - Départ {selectedSession}
                                    </h2>
                                    <div className="text-sm text-gray-600">
                                        {assignedArchers.length} / {filteredArchers.length} archers assignés
                                    </div>
                                </div>
                                
                                <div 
                                    id="targets-scroll-container"
                                    className="space-y-4 max-h-[600px] overflow-y-auto p-1"
                                >
                                    {targets.map((target) => {
                                        const hasArchers = archers.some(a => 
                                            a.targetNo && 
                                            a.targetNo !== '' &&
                                            a.targetNo.startsWith(target.id) && 
                                            a.session === selectedSession
                                        );
                                        const isHovered = hoverTargetId === target.id;
                                        const combinationData = getCombinationImage(target);
                                        const combinationImage = combinationData.image;
                                        const distance = combinationData.distance;
                                        const combinationLabel = "";
                                        const isInvalidConfig = combinationImage === 'Img/xx.png';
                                        
                                        return (
                                            <div 
                                                key={target.id} 
                                                data-target-id={target.id}
                                                className={`target-container border-2 rounded-lg p-4 transition-all target-with-archers ${
                                                    isHovered ? 'drop-target-hover' : ''
                                                } ${
                                                    isInvalidConfig ? 'invalid-configuration' : ''
                                                }`}
                                                onMouseEnter={(e) => {
                                                    if (hasArchers) {
                                                        e.currentTarget.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
                                                    }
                                                }}
                                                onMouseLeave={(e) => {
                                                    if (hasArchers) {
                                                        e.currentTarget.style.backgroundColor = '';
                                                    }
                                                }}
                                                onDragOver={(e) => handleTargetNumberDragOver(e, target.id)}
                                                onDragLeave={(e) => handleTargetNumberDragLeave(e, target.id)}
                                                onDrop={(e) => handleTargetNumberDrop(e, target.id)}
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="flex-1 grid grid-cols-4 gap-2">
                                                        {target.positions.map((position) => {
                                                            const isBlockedByWheelchair = isPositionBlockedByWheelchair(
                                                                target, 
                                                                position.letter, 
                                                                archers, 
                                                                selectedSession
                                                            );
                                                            const isOccupied = !!position.archer;
                                                            
                                                            return (
                                                                <div
                                                                    key={position.id}
                                                                    onDragOver={(e) => {
                                                                        if (!isBlockedByWheelchair) {
                                                                            handleDragOver(e);
                                                                        }
                                                                    }}
                                                                    onDrop={(e) => {
                                                                        if (!isBlockedByWheelchair) {
                                                                            handleArcherDrop(e, position.id);
                                                                        } else {
                                                                            showNotification('Cette position est bloquée par un archer en fauteuil', 'error');
                                                                        }
                                                                    }}
                                                                    className={`position-container h-28 border-2 rounded-lg relative ${
                                                                        getPositionColor(position, isBlockedByWheelchair)
                                                                    } ${
                                                                        isBlockedByWheelchair ? 'position-blocked' :
                                                                        isOccupied ? 'position-occupied' : 'position-empty'
                                                                    }`}
                                                                    title={isBlockedByWheelchair ? 'Position bloquée par un archer en fauteuil' : ''}
                                                                >
                                                                    <div className="position-header">
                                                                        <span className={isBlockedByWheelchair ? 'text-gray-400' : 'text-gray-500'}>
                                                                            {position.letter}
                                                                            {isBlockedByWheelchair && (
                                                                                <span className="ml-1 inline-block w-3 h-3 text-gray-400">
                                                                                    <Icons.Lock />
                                                                                </span>
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                    
                                                                    {isBlockedByWheelchair ? (
                                                                        <div className="archer-content flex flex-col items-center justify-center p-2">
                                                                            <div className="w-6 h-6 text-gray-400 mb-1">
                                                                                <Icons.Wheelchair />
                                                                            </div>
                                                                            <div className="text-xs text-gray-400 text-center">
                                                                                Bloqué
                                                                            </div>
                                                                    </div>
                                                                    ) : isOccupied ? (
                                                                        <div 
                                                                            draggable
                                                                            onDragStart={(e) => handleArcherDragStart(e, position.archer)}
                                                                            onDragEnd={handleArcherDragEnd}
                                                                            className="archer-content p-2 no-select archer-draggable"
                                                                            data-archer-id={position.archer.id}
                                                                        >
                                                                            <div className="flex items-center gap-2 h-full">
                                                                                {(() => {
                                                                                    const targetFaceImage = getArcherTargetFaceImage(position.archer);
                                                                                    if (targetFaceImage) {
                                                                                        return (
                                                                                            <img 
                                                                                                src={targetFaceImage} 
                                                                                                alt="Target face"
                                                                                                className="w-12 h-12 flex-shrink-0"
                                                                                                onError={(e) => {
                                                                                                    e.target.style.display = 'none';
                                                                                                }}
                                                                                            />
                                                                                        );
                                                                                    }
                                                                                    return null;
                                                                                })()}
                                                                                <div className="flex-1 min-w-0">
                                                                                    <div className={`font-bold text-xs truncate ${
                                                                                        position.archer.wheelchair ? 'text-blue-700' : 'text-green-700'
                                                                                    }`}>
                                                                                        {position.archer.name}
                                                                                        {position.archer.wheelchair && ' ♿'}
                                                                                    </div>
                                                                                    <div className="text-xs text-gray-600 mt-1 truncate">
                                                                                        {position.archer.countryName}
                                                                                    </div>
                                                                                    <div className={`text-xs font-medium mt-1 truncate ${
                                                                                        position.archer.wheelchair ? 'text-blue-600' : 'text-green-600'
                                                                                    }`}>
                                                                                        {position.archer.evCode || (position.archer.class + position.archer.division)}
                                                                                        {position.archer.distance && ` / ${position.archer.distance}m`}
                                                                                    </div>
                                                                                    
                                                                                    {isDebugMode && (position.archer.targetSize || position.archer.targetFace) && (
                                                                                        <div className="flex flex-wrap gap-1 mt-2">
                                                                                            {position.archer.targetSize && isDebugMode && (
                                                                                                <span className="target-size-badge">
                                                                                                    {getTargetSizeLabel(position.archer.targetSize)}
                                                                                                </span>
                                                                                            )}
                                                                                            {position.archer.targetFace && isDebugMode && (
                                                                                                <span className="face-badge">
                                                                                                    {getTargetFaceLabel(position.archer.targetFace)}
                                                                                                </span>
                                                                                            )}
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    ) : (
                                                                        <div className="archer-content flex items-center justify-center">
                                                                            <div className="w-6 h-6 text-gray-300">
                                                                                <Icons.Target />
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                    
                                                    <div className="flex-shrink-0 w-24 text-center">
                                                        <div 
                                                            draggable={hasArchers}
                                                            onDragStart={(e) => handleTargetNumberDragStart(e, target.id)}
                                                            onDragEnd={handleTargetNumberDragEnd}
                                                            className={`target-draggable-container ${
                                                                hasArchers 
                                                                    ? 'cursor-move drag-ready no-select' 
                                                                    : 'cursor-default'
                                                            }`}
                                                            title={hasArchers ? "Glisser pour échanger cette cible (avec image)" : "Cible vide"}
                                                        >
                                                            {combinationImage && (
                                                                <div className="relative mb-2">
                                                                    <img 
                                                                        src={combinationImage} 
                                                                        alt={combinationLabel}
                                                                        className="w-60 h-20 mx-auto object-contain"
                                                                        onError={(e) => {
                                                                            e.target.style.display = 'none';
                                                                            console.warn(`Image non trouvée: ${combinationImage}`);
                                                                        }}
                                                                    />
                                                                    <div className={`text-xs mt-1 ${
                                                                        isInvalidConfig ? 'text-red-600 font-medium' : 'text-gray-600'
                                                                    }`}>
                                                                        {combinationLabel}
                                                                        {isInvalidConfig && (
                                                                            <div className="w-4 h-4 inline-block ml-1">
                                                                                <Icons.Warning />
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            )}
                                                            
                                                            <div className={`text-2xl font-bold ${
                                                                hasArchers 
                                                                    ? 'text-blue-600' 
                                                                    : 'text-gray-400'
                                                            }`}>
                                                                {target.id}
                                                            </div>
                                                            
                                                            {/* AFFICHAGE DE LA DISTANCE SOUS LE NUMÉRO DE CIBLE */}
                                                            {distance && (
                                                                <div className={`target-main-distance mt-1 ${
                                                                    parseInt(distance) <= 18 ? 'target-distance-indoor' : 'target-distance-outdoor'
                                                                }`}>
                                                                    {distance}m
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {isHovered && draggedTargetId && draggedTargetId !== target.id && (
                                                    <div className="mt-2 text-center">
                                                        <div className="inline-flex items-center gap-2 px-2 py-0.5 bg-green-100 text-green-800 text-sm rounded-full">
                                                            <Icons.Check />
                                                            <span>Relâcher pour échanger avec la cible {draggedTargetId}</span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {notification && (
                    <div className={`fixed bottom-6 right-6 p-4 rounded-lg shadow-lg flex items-center gap-3 ${
                        notification.type === 'success' ? 'bg-green-100 text-green-800' :
                        notification.type === 'error' ? 'bg-red-100 text-red-800' :
                        'bg-blue-100 text-blue-800'
                    }`}>
                        <div className="w-5 h-5">
                            {notification.type === 'success' ? <Icons.Check /> : <Icons.AlertCircle />}
                        </div>
                        <span className="font-medium">{notification.message}</span>
                    </div>
                )}

                {isSaving && (
                    <div className="fixed bottom-6 left-6 p-4 rounded-lg shadow-lg flex items-center gap-3 bg-yellow-100 text-yellow-800">
                        <span className="font-medium">💾 Sauvegarde en cours...</span>
                    </div>
                )}
            </div>
        );
    };

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<TargetAssignmentUI />);
</script>

<?php 
include('Common/Templates/tail.php');
?>