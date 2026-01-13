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
 * - Laurent Petroff - Les Archers de Perols - (modif: 2025-12-11)
 * 
 * Dernière modification: 2025-12-20 par Laurent Petroff
 *
 * Plan de Cibles graphique
 *
**/

define('debug', false);

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

/* Styles pour le drag d'archer - SIMILAIRE À L'ORIGINAL */
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
    border-color: #10b981 !important;
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
    height: calc(100% - 28px); /* 28px = hauteur du header */
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

/* Style pour l'image de drag qui ressemble à l'original */
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

/* NOUVEAUX STYLES POUR LES BORDURES SPÉCIFIQUES */
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
    border-radius: 0.375rem !important; /* Un peu plus petit pour l'intérieur */
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

</style>


<div id="root"></div>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
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
	/* AJOUTER CES STYLES POUR CORRIGER L'ALIGNEMENT DES IMAGES */
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
    height: 64px; /* Fixer une hauteur pour l'image */
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

<script type="text/babel" src="DragDropPlanUtils.js"></script>
<script type="text/babel">
    const { useState, useEffect, useRef } = React;
    
    // Données PHP
    const INITIAL_ARCHERS = <?php
    $Select = "SELECT EnId, EnCode, EnName, EnFirstName, EnCountry, EnDivision, EnClass, 
               QuSession, QuTargetNo, EnWChair, QuScore, QuGold, QuXNine,
               CoCode, CoName, EnTargetFace
               FROM Entries 
               INNER JOIN Qualifications ON EnId=QuId 
               INNER JOIN Countries ON EnCountry=CoId AND EnTournament=CoTournament
               WHERE EnTournament=" . StrSafe_DB($_SESSION['TourId']) . " AND EnAthlete=1";

    if (isset($_REQUEST["Event"]) && preg_match("/^[0-9A-Z%_]+$/i", $_REQUEST["Event"])) {
        $Select .= " AND CONCAT(TRIM(EnDivision),TRIM(EnClass)) LIKE " . StrSafe_DB($_REQUEST["Event"]) . " ";
    }

    $Select .= " ORDER BY EnName";
    $Rs = safe_r_sql($Select);
    $archers = [];

    while ($row = safe_fetch($Rs)) {
        $archers[] = [
            'id' => (int)$row->EnId,
            'code' => $row->EnCode ?: '',
            'name' => trim($row->EnFirstName . ' ' . $row->EnName),
            'country' => $row->CoCode ?: '',
            'countryName' => $row->CoName ?: '',
            'division' => $row->EnDivision ?: '',
            'class' => $row->EnClass ?: '',
            'session' => (int)$row->QuSession,
            'targetNo' => $row->QuTargetNo ?: '',
            'score' => ($row->QuScore ? $row->QuScore . '/' . $row->QuGold . '/' . $row->QuXNine : '0/0/0'),
            'wheelchair' => (bool)$row->EnWChair,
            'targetFace' => $row->EnTargetFace ?: ''
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

        // Fonction pour obtenir la couleur de fond selon le type d'archer
        const getArcherBgColor = (archer, isAssigned = true) => {
            if (!isAssigned) {
                return 'archer-unassigned';
            }
            if (archer.wheelchair) return 'archer-wheelchair-assigned';
            return 'archer-assigned';
        };

        // Fonction pour obtenir la couleur de texte selon le type d'archer
        const getArcherTextColor = (archer, isAssigned = true) => {
            if (!isAssigned) {
                return 'text-orange-700';
            }
            if (archer.wheelchair) return 'text-blue-700';
            return 'text-green-700';
        };

        // Gestion du drag & drop des archers avec image SIMILAIRE À L'ORIGINALE
        const handleArcherDragStart = (e, archer) => {
            setDraggedArcher(archer);
            e.dataTransfer.setData('text/plain', `archer:${archer.id}`);
            e.dataTransfer.effectAllowed = 'move';
            dragStartPos.current = { x: e.clientX, y: e.clientY };
            
            // Obtenir les couleurs originales
            const bgColor = archer.wheelchair ? 
                'linear-gradient(135deg, rgba(219, 234, 254, 0.98), rgba(191, 219, 254, 0.98))' : 
                'linear-gradient(135deg, rgba(220, 252, 231, 0.98), rgba(187, 247, 208, 0.98))';
            
            const borderColor = archer.wheelchair ? '#3b82f6' : '#10b981';
            const textColor = archer.wheelchair ? '#1e40af' : '#166534';
            
            // Créer un élément de drag personnalisé qui ressemble à l'original
            const dragImage = document.createElement('div');
            dragImage.className = `archer-drag-preview ${archer.wheelchair ? 'wheelchair' : 'regular'} archer-original-look`;
            dragImage.setAttribute('data-drag-preview', 'true');
            
            // Formater le nom pour un affichage plus compact
            const nameParts = archer.name.split(' ');
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            const shortLastName = lastName.length > 10 ? lastName.substring(0, 8) + '...' : lastName;
            
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
                    ${archer.targetFace ? `
                        <img src="${getTargetFaceImage(archer.targetFace)}" 
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
                                ${archer.division || ''}${archer.class || ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                ${archer.targetNo ? `
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
            
            // Positionner l'image de drag (hors écran)
            const rect = dragImage.getBoundingClientRect();
            
            // Utiliser l'élément personnalisé comme image de drag (centré)
            e.dataTransfer.setDragImage(dragImage, rect.width / 2, rect.height / 2);
            
            // Ajouter une classe à l'élément drag source pour feedback visuel
            if (e.target.classList) {
                e.target.classList.add('dragging-archer');
            }
            
            // Nettoyer après le drag
            setTimeout(() => {
                const preview = document.querySelector('[data-drag-preview="true"]');
                if (preview) {
                    document.body.removeChild(preview);
                }
            }, 0);
        };

        const handleArcherDragEnd = (e) => {
            // Retirer la classe de feedback visuel
            const draggingElements = document.querySelectorAll('.dragging-archer');
            draggingElements.forEach(el => el.classList.remove('dragging-archer'));
            
            // Nettoyer les éventuels éléments de preview restants
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
            
            // Ouvrir une fenêtre pour le PDF
            const printWindow = window.open('', '_blank', 'width=1800,height=1000');
            
            if (!printWindow) {
                alert('⚠️ Veuillez autoriser les popups pour générer le PDF');
                return;
            }
            
            // Fonction pour récupérer les infos archer d'une position spécifique
            const getArcherForPosition = (targetId, letter) => {
                return assignedArchers.find(a => a.targetNo === `${targetId}${letter}`);
            };
            
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
                                const combinationImage = getCombinationImage(targetObj);
                                
                                html += `<div class="target-card">
                                    <div class="target-number">${targetId}</div>`;
                                
                                if (combinationImage && combinationImage !== 'Img/xx.png') {
                                    html += `<img src="${combinationImage}" class="target-image" alt="Cible ${targetId}" onerror="this.style.display='none'">`;
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
        };

        // Fonction de débogage
        const debugTargets = () => {
            console.log('=== DÉBUG COMPLET DES CIBLES ===');
            
            const assignedArchers = archers.filter(a => 
                a.session === selectedSession && a.targetNo
            );
            
            const targetNumbers = [...new Set(assignedArchers.map(a => a.targetNo.substring(0, 3)))];
            targetNumbers.sort((a, b) => parseInt(a) - parseInt(b));
            
            console.log(`Cibles assignées: ${targetNumbers.length}`);
            console.log('Liste:', targetNumbers);
            
            // Vérifier spécifiquement 21 et 22
            console.log('Cible 021:', targetNumbers.includes('021'));
            console.log('Cible 022:', targetNumbers.includes('022'));
        };

        const generateTargets = () => {
            // Trouver la cible la plus haute
            let highestTarget = 0;
            archers.forEach(archer => {
                if (archer.targetNo) {
                    const targetNum = parseInt(archer.targetNo.substring(0, 3));
                    if (targetNum > highestTarget) {
                        highestTarget = targetNum;
                    }
                }
            });
            
            // Générer au moins 30 cibles
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

        // Fonction pour recalculer les targets
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

        // Quand on change de session, recalculer les targets
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

        const unassignedArchers = filteredArchers.filter(a => !a.targetNo);
        const assignedArchers = filteredArchers.filter(a => a.targetNo);

        // Fonction pour obtenir la dernière cible assignée
        const getLastAssignedTarget = () => {
            let lastTarget = 0;
            assignedArchers.forEach(archer => {
                if (archer.targetNo) {
                    const targetNum = parseInt(archer.targetNo.substring(0, 3));
                    if (targetNum > lastTarget) {
                        lastTarget = targetNum;
                    }
                }
            });
            return lastTarget;
        };

        const lastAssignedTarget = getLastAssignedTarget();
        
        // Nouvelle logique pour déterminer le nombre de cibles à afficher dans l'overview
        const getTargetsToDisplay = () => {
            const assignedTargetNumbers = [];
            assignedArchers.forEach(archer => {
                if (archer.targetNo) {
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

        // Fonction pour vérifier si une cible a des archers assignés
        const hasAssignedArchers = (targetId) => {
            return assignedArchers.some(archer => 
                archer.targetNo && archer.targetNo.startsWith(targetId)
            );
        };

        // Fonction pour compter le nombre d'archers sur une cible
        const countArchersOnTarget = (targetId) => {
            return assignedArchers.filter(archer => 
                archer.targetNo && archer.targetNo.startsWith(targetId)
            ).length;
        };

        // Gestion du scroll horizontal
        const handleOverviewScroll = () => {
            if (targetsOverviewRef.current) {
                setScrollPosition(targetsOverviewRef.current.scrollLeft);
            }
        };

        // Effet pour attacher l'événement de scroll
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
            
            // Récupérer la cible et la position
            const targetId = positionId.substring(0, 3);
            const positionLetter = positionId.charAt(3);
            const target = targets.find(t => t.id === targetId);
            
            if (!target) return;
            
            // Vérifier si la position est bloquée par un fauteuil existant
            const isBlocked = isPositionBlockedByWheelchair(target, positionLetter, archers, selectedSession);
            if (isBlocked) {
                showNotification('Cette position est bloquée par un archer en fauteuil', 'error');
                return;
            }
            
            // Si on déplace un fauteuil, vérifier qu'on ne bloque pas une position occupée
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
            
            // Vérifier la compatibilité des targetFace
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
            
            // Récupérer l'archer actuellement sur cette position (s'il existe)
            const currentArcherOnPosition = archers.find(a => 
                a.targetNo === positionId && 
                a.session === selectedSession
            );
            
            // Échanger ou assigner
            let updatedArchers = [...archers];
            
            if (currentArcherOnPosition) {
                // Cas 1: Échange entre deux archers
                // Vérifier aussi la compatibilité pour l'archer déplacé
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
                // Cas 2: Assignation à une position vide
                updatedArchers = updatedArchers.map(archer => {
                    if (archer.id === draggedArcher.id) {
                        return { ...archer, targetNo: positionId };
                    }
                    return archer;
                });
            }
            
            setArchers(updatedArchers);
            
            // Recalculer les targets immédiatement
            const updatedTargets = recalculateTargets(updatedArchers, selectedSession);
            setTargets(updatedTargets);
            
            // Sauvegarde
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

        // Gestion du drag & drop des numéros de cible avec l'image
        const handleTargetNumberDragStart = (e, targetId) => {
            // Vérifier si la cible a des archers assignés
            const hasArchers = archers.some(a => 
                a.targetNo && 
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
            
            // Feedback visuel
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
            
            // Vérifier que ce n'est pas la même cible qu'on drag
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
            
            // Vérifier que ce n'est pas la même cible
            if (!sourceTargetId || sourceTargetId === targetId) {
                setHoverTargetId(null);
                return;
            }
            
            // Vérifier si la cible source a des archers
            const sourceArchers = archers.filter(a => 
                a.targetNo && 
                a.targetNo.startsWith(sourceTargetId) && 
                a.session === selectedSession
            );
            
            if (sourceArchers.length === 0) {
                showNotification(`La cible ${sourceTargetId} n'a pas d'archers assignés`, 'error');
                setHoverTargetId(null);
                return;
            }
            
            // Récupérer les archers de la cible destination
            const destArchers = archers.filter(a => 
                a.targetNo && 
                a.targetNo.startsWith(targetId) && 
                a.session === selectedSession
            );
            
            // Vérifier la compatibilité globale avant l'échange
            const simulateExchange = () => {
                const allArchersAfterExchange = archers.map(archer => {
                    if (archer.session !== selectedSession) return archer;
                    
                    // Archers de la cible source → cible destination
                    if (archer.targetNo && archer.targetNo.startsWith(sourceTargetId)) {
                        const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                        return { ...archer, targetNo: targetId + letter };
                    }
                    
                    // Archers de la cible destination → cible source
                    if (archer.targetNo && archer.targetNo.startsWith(targetId)) {
                        const letter = archer.targetNo.charAt(archer.targetNo.length - 1);
                        return { ...archer, targetNo: sourceTargetId + letter };
                    }
                    
                    return archer;
                });
                
                // Vérifier la validité des deux cibles après échange
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
                
                // Vérifier les deux cibles
                const sourceImageAfter = getCombinationImage(sourceTargetAfter);
                const destImageAfter = getCombinationImage(destTargetAfter);
                
                return {
                    sourceInvalid: sourceImageAfter === 'Img/xx.png',
                    destInvalid: destImageAfter === 'Img/xx.png',
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
            
            // Échanger les positions
            const updatedArchers = simulation.allArchersAfterExchange;

            setArchers(updatedArchers);
            
            // Recalculer les targets immédiatement
            const updatedTargets = recalculateTargets(updatedArchers, selectedSession);
            setTargets(updatedTargets);

            // Sauvegarder tous les changements
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
            
            // Recalculer les targets immédiatement
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

        // Fonction pour scroller vers une cible spécifique (CENTRER la cible dans le viewport)
        const scrollToTarget = (targetId) => {
            const element = document.querySelector(`[data-target-id="${targetId}"]`);
            if (element) {
                // Utiliser l'API native scrollIntoView pour centrer la cible
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',    // Centre verticalement la cible dans le conteneur
                    inline: 'nearest'   // Pas de décalage horizontal
                });
                
                // Feedback visuel amélioré
                element.classList.add('highlight-target');
                
                // Retirer le highlight après 2 secondes
                setTimeout(() => {
                    element.classList.remove('highlight-target');
                }, 2000);
            }
        };

        // Fonction pour calculer l'image de combinaison d'une cible
        const getTargetCombinationImage = (targetId) => {
            const target = targets.find(t => t.id === targetId);
            if (!target) return null;
            return getCombinationImage(target);
        };

        // Fonction pour vérifier si une cible a une configuration valide
        const isTargetValid = (targetId) => {
            const target = targets.find(t => t.id === targetId);
            if (!target) return true;
            const image = getCombinationImage(target);
            return image !== 'Img/xx.png';
        };

        return (
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6">
                <div className="mx-auto">

                    {/* Barre de cibles miniatures */}
                    <div className="bg-white rounded-lg shadow-lg p-4 mb-6">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                                <div className="w-5 h-5 text-blue-600"><Icons.Grid /></div>
                                <div className="flex items-center gap-3 ml-2">
                                    <h2 className="text-lg font-bold text-gray-800">Départ :</h2>
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
    {/* BOUTON IMPRIMER PDF SIMPLIFIÉ */}
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
    title="Générer un PDF de toutes les cibles"
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
                                        
                                        // Vérifier que la cible existe dans targets
                                        if (!targets.find(t => t.id === targetId)) return null;
                                        
                                        const combinationImage = getTargetCombinationImage(targetId);
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
												{/* Conteneur pour l'image */}
												<div className="relative w-full h-16 mb-1 flex items-center justify-center">
													{/* Image de combinaison */}
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
													
													{/* Badge avec nombre d'archers */}
													{archersCount > 0 && (
														<div className="target-badge">
															{archersCount}
														</div>
													)}
												</div>
												
												{/* Numéro de cible - TOUJOURS EN BAS */}
												<div className={`font-bold mt-1 ${
													!isValid ? 'text-red-600' :
													hasArchers ? 'text-green-800' : 
													'text-gray-500'
												}`}>
													{targetId}
												</div>
											</div>
										);
                                    }).filter(Boolean)}
                                </div>
                                
                            </>
                        )}
                    </div>

                    <div className="flex gap-6">
                        {/* Liste des archers non assignés - AVEC ZONE DE DROP AMÉLIORÉE */}
                        <div className="w-19 flex-shrink-0">
                            <div className="bg-white rounded-lg shadow-lg p-4 sticky top-6">

                                
                                {/* ZONE DE DROP AMÉLIORÉE - Solution 2 */}
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
                                    {/* Titre amélioré */}
                                    <div className="text-center mb-4 pb-3 border-b border-gray-200">
                                        <div className="text-lg font-bold text-gray-800 mb-1">
                                            Non assignés ({unassignedArchers.length})
                                        </div>
                                        <div className="text-sm text-gray-600">
                                            Glissez les archers ici pour les désassigner
                                        </div>
                                    </div>
                                    
                                    {unassignedArchers.map(archer => (
                                        <div
                                            key={archer.id}
                                            draggable
                                            onDragStart={(e) => handleArcherDragStart(e, archer)}
                                            onDragEnd={handleArcherDragEnd}
                                            className={`p-3 border-2 rounded-lg cursor-move hover:shadow-md transition no-select ${getArcherBgColor(archer, false)}`}
                                            data-archer-id={archer.id}
                                        >
                                            <div className="flex items-start gap-2">
                                                {archer.targetFace && (
                                                    <img 
                                                        src={getTargetFaceImage(archer.targetFace)} 
                                                        alt="Target face"
                                                        className="w-12 h-12 flex-shrink-0"
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
                                                        {archer.division}{archer.class}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}

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

                        {/* Zone principale */}
                        <div className="flex-1">
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
                                            a.targetNo.startsWith(target.id) && 
                                            a.session === selectedSession
                                        );
                                        const isHovered = hoverTargetId === target.id;
                                        const combinationImage = getCombinationImage(target);
                                        const combinationLabel = "";  //  pour debug // getCombinationLabel(target);
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
                                                    {/* 4 positions */}
                                                    <div className="flex-1 grid grid-cols-4 gap-3">
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
                                                                        // Permettre le drop même si la position est occupée
                                                                        // (pour permettre les échanges entre archers)
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
                                                                                {position.archer.targetFace && (
                                                                                    <img 
                                                                                        src={getTargetFaceImage(position.archer.targetFace)} 
                                                                                        alt="Target face"
                                                                                        className="w-12 h-12 flex-shrink-0"
                                                                                    />
                                                                                )}
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
                                                        {position.archer.division}{position.archer.class}
                                                    </div>
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
                                                    
                                                    {/* Numéro de cible + Image de combinaison - DRAGGABLE (INVERSÉ À DROITE) */}
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
                                                            {/* Image de combinaison en haut */}
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
                                                            
                                                            {/* Numéro de cible en bas */}
                                                            <div className={`text-2xl font-bold ${
                                                                hasArchers 
                                                                    ? 'text-blue-600' 
                                                                    : 'text-gray-400'
                                                            }`}>
                                                                {target.id}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {/* Indicateur visuel pour le drag de cible */}
                                                {isHovered && draggedTargetId && draggedTargetId !== target.id && (
                                                    <div className="mt-2 text-center">
                                                        <div className="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                                                            <Icons.Check />
                                                            <span>Relâcher pour échanger avec la cible {draggedTargetId}</span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="mt-6 pt-4 border-t flex flex-wrap gap-6 text-sm">
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-gray-300 bg-white rounded"></div>
                                        <span>Position libre (gris clair)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-green-600 bg-green-100 rounded"></div>
                                        <span>Archer assigné (vert)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-blue-600 bg-blue-100 rounded"></div>
                                        <span>Archer en fauteuil (bleu)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-orange-500 bg-orange-100 rounded"></div>
                                        <span>Archer non-assigné (orange)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-gray-300 bg-gray-100 rounded opacity-70"></div>
                                        <span>Position bloquée (fauteuil)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-blue-600 rounded cursor-move"></div>
                                        <span>Numéro de cible draggable</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-green-500 rounded bg-green-50"></div>
                                        <span>Cible prête pour l'échange</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-red-500 rounded bg-red-50"></div>
                                        <span>Configuration invalide</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-3 border-green-300 bg-green-50 rounded"></div>
                                        <span>Cible avec archers (en haut)</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 border-2 border-orange-400 rounded bg-orange-50 cursor-copy"></div>
                                        <span>Zone de désassignement</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Notifications */}
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
// Inclure le footer de Verification.php
include('Common/Templates/tail.php');
?>