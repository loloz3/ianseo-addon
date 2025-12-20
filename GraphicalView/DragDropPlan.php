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
</style>

<!-- Titre avec le même style que Verification.php -->
<div class="Title"><?php echo $PAGE_TITLE; ?></div>

<div id="root"></div>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
    }
</style>

<!-- React et ReactDOM -->
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

<!-- Babel pour transformer JSX -->
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script src="https://cdn.tailwindcss.com"></script>


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
<script type="text/babel" src="DragDropPlanPrint.js"></script>
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
        const maxTargets = calculateMaxTargets();
        const [isSaving, setIsSaving] = useState(false);
        const [hoverTargetId, setHoverTargetId] = useState(null);
        const [showTargetsOverview, setShowTargetsOverview] = useState(true);
        const dragStartPos = useRef({ x: 0, y: 0 });
        const targetsOverviewRef = useRef(null);
        const [scrollPosition, setScrollPosition] = useState(0);

        const generateTargets = () => {
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
        const displayTargetsCount = Math.max(lastAssignedTarget + 2, 10); // Afficher au moins 10 cibles

        // Fonction pour vérifier si une cible a des archers assignés
        const hasAssignedArchers = (targetId) => {
            return assignedArchers.some(archer => 
                archer.targetNo && archer.targetNo.startsWith(targetId)
            );
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

        // Gestion du drag & drop des archers
        const handleArcherDragStart = (e, archer) => {
            setDraggedArcher(archer);
            e.dataTransfer.setData('text/plain', `archer:${archer.id}`);
            e.dataTransfer.effectAllowed = 'move';
            dragStartPos.current = { x: e.clientX, y: e.clientY };
            
            // Feedback visuel
            setTimeout(() => {
                e.target.classList.add('opacity-50');
            }, 0);
        };

        const handleArcherDragEnd = (e) => {
            e.target.classList.remove('opacity-50');
            setDraggedArcher(null);
        };

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
            if (!position.archer) return 'bg-white border-gray-300';
            if (position.archer.wheelchair) return 'bg-blue-100 border-blue-400';
            return 'bg-green-100 border-green-400';
        };

        // Fonction pour scroller vers une cible spécifique
        const scrollToTarget = (targetId) => {
            const element = document.querySelector(`[data-target-id="${targetId}"]`);
            if (element) {
                element.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center',
                    inline: 'nearest'
                });
                
                // Feedback visuel temporaire
                element.classList.add('border-blue-500', 'bg-blue-50');
                setTimeout(() => {
                    element.classList.remove('border-blue-500', 'bg-blue-50');
                }, 1500);
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
 								<div className="flex items-center gap-3">
                                <h2 className="text-lg font-bold text-gray-800"> Départ :</h2>
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
                                {/* BOUTON IMPRIMER */}
                                <button
                                    onClick={() => generatePDFReport(archers, selectedSession, targets, SESSIONS)}
                                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
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
                                        const combinationImage = getTargetCombinationImage(targetId);
                                        const isValid = isTargetValid(targetId);
                                        const hasArchers = hasAssignedArchers(targetId);
                                        const isActive = hoverTargetId === targetId;
                                        
                                        return (
                                            <div
                                                key={targetId}
                                                className={`target-thumbnail flex-shrink-0 w-16 p-2 border-2 rounded-lg text-center cursor-pointer transition-all ${
                                                    isActive ? 'active border-blue-500 bg-blue-50' : 
                                                    !isValid ? 'border-red-300 bg-red-50' :
                                                    hasArchers ? 'border-green-300 bg-green-50' : 
                                                    'border-gray-200 bg-gray-50'
                                                }`}
                                                onClick={() => scrollToTarget(targetId)}
                                                onMouseEnter={() => setHoverTargetId(targetId)}
                                                onMouseLeave={() => {
                                                    if (hoverTargetId === targetId) {
                                                        setHoverTargetId(null);
                                                    }
                                                }}
                                                title={`Cible ${targetId} - Cliquer pour aller à la cible`}
                                            >
                                                {/* Image de combinaison */}
                                                {combinationImage ? (
                                                    <div className="mb-1">
                                                        <img 
                                                            src={combinationImage} 
                                                            alt={`Cible ${targetId}`}
                                                            className="w-16 h-16 mx-auto object-contain"
                                                            onError={(e) => {
                                                                e.target.style.display = 'none';
                                                            }}
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="w-11 h-16 mx-auto mb-1 flex items-center justify-center bg-gray-100 rounded">
                                                        <div className="w-8 h-8 text-gray-400">
                                                            <Icons.Target />
                                                        </div>
                                                    </div>
                                                )}
                                                
                                                {/* Numéro de cible */}
                                                <div className={`font-bold ${
                                                    !isValid ? 'text-red-600' :
                                                    hasArchers ? 'text-green-600' : 
                                                    'text-gray-500'
                                                }`}>
                                                    {targetId}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                
                                {/* Légende pour la barre de miniatures */}
                                <div className="flex flex-wrap gap-4 text-xs text-gray-600 mt-3 pt-3 border-t">
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full bg-green-100 border border-green-300"></div>
                                        <span>Cible avec archers</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full bg-gray-100 border border-gray-300"></div>
                                        <span>Cible vide</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full bg-red-100 border border-red-300"></div>
                                        <span>Configuration invalide</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full bg-blue-100 border border-blue-300"></div>
                                        <span>Cible sélectionnée</span>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="flex gap-6">
                        {/* Liste des archers non assignés */}
                        <div className="w-19 flex-shrink-0">
                            <div className="bg-white rounded-lg shadow-lg p-4 sticky top-6">
                                <div className="flex items-center gap-2 mb-4">
                                    <div className="w-5 h-5 text-blue-600"><Icons.Users /></div>
                                    <h2 className="text-lg font-bold text-gray-800">
                                        Non assignés ({unassignedArchers.length})
                                    </h2>
                                </div>

                                <div 
                                    className="space-y-3 max-h-[calc(100vh-200px)] overflow-y-auto p-1"
                                    onDragOver={handleDragOver}
                                    onDrop={handleDropToUnassigned}
                                >
                                    {unassignedArchers.map(archer => (
                                        <div
                                            key={archer.id}
                                            draggable
                                            onDragStart={(e) => handleArcherDragStart(e, archer)}
                                            onDragEnd={handleArcherDragEnd}
                                            className={`p-3 border-2 rounded-lg cursor-move hover:shadow-md transition no-select ${
                                                archer.wheelchair 
                                                    ? 'bg-blue-50 border-blue-300' 
                                                    : 'bg-orange-50 border-orange-300'
                                            }`}
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
                                                    <div className="font-bold text-sm text-gray-800 truncate">
                                                        {archer.name}
                                                        {archer.wheelchair && ' ♿'}
                                                    </div>
                                                    <div className="text-xs text-gray-600 mt-1 truncate">
                                                        {archer.countryName}
                                                    </div>
                                                    <div className="text-xs text-gray-500 mt-1 truncate">
                                                        {archer.division}{archer.class}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    {unassignedArchers.length === 0 && (
                                        <div className="text-center text-gray-500 text-sm py-8">
                                            Tous les archers sont assignés
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
                                
                                <div className="space-y-4 max-h-[600px] overflow-y-auto p-1">
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
                                                className={`border-2 rounded-lg p-4 transition-all ${
                                                    isHovered ? 'drop-target-hover' : 'border-gray-200'
                                                } ${
                                                    isInvalidConfig ? 'invalid-configuration' : ''
                                                }`}
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
                                                                    className={`h-28 border-2 rounded-lg transition-all relative ${
                                                                        getPositionColor(position, isBlockedByWheelchair)
                                                                    } ${isBlockedByWheelchair ? 'blocked-position opacity-70' : ''} ${
                                                                        isOccupied ? 'cursor-move' : 'cursor-default'
                                                                    }`}
                                                                    title={isBlockedByWheelchair ? 'Position bloquée par un archer en fauteuil' : ''}
                                                                >
                                                                    <div className="text-center font-bold text-xs pt-1 pb-1 border-b border-gray-200">
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
                                                                        <div className="h-full flex flex-col items-center justify-center p-2">
                                                                            <div className="w-6 h-6 text-gray-400 mb-1">
                                                                                <Icons.Wheelchair />
                                                                            </div>
                                                                            <div className="text-xs text-gray-400 text-center">
                                                                                Bloqué
                                                                            </div>
                                                                        </div>
                                                                    ) : position.archer ? (
                                                                        <div 
                                                                            draggable
                                                                            onDragStart={(e) => handleArcherDragStart(e, position.archer)}
                                                                            onDragEnd={handleArcherDragEnd}
                                                                            className="h-full p-2 cursor-move hover:shadow-lg transition-shadow no-select"
                                                                        >
                                                                            <div className="flex items-center gap-2">
                                                                                {position.archer.targetFace && (
                                                                                    <img 
                                                                                        src={getTargetFaceImage(position.archer.targetFace)} 
                                                                                        alt="Target face"
                                                                                        className="w-12 h-12 flex-shrink-0"
                                                                                    />
                                                                                )}
                                                                                <div className="flex-1 min-w-0">
                                                                                    <div className="font-bold text-xs text-gray-800 truncate">
                                                                                        {position.archer.name}
                                                                                        {position.archer.wheelchair && ' ♿'}
                                                                                    </div>
                                                                                    <div className="text-xs text-gray-600 mt-1 truncate">
                                                                                        {position.archer.countryName}
                                                                                    </div>
                                                                                    <div className="text-xs text-gray-500 mt-1 truncate">
                                                                                        {position.archer.division}{position.archer.class}
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    ) : (
                                                                        <div className="h-full flex items-center justify-center">
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
                                                                        className="w-20 h-20 mx-auto object-contain"
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
                                        <div className="w-4 h-4 bg-white border-2 border-gray-300 rounded"></div>
                                        <span>Position libre</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 bg-green-100 border-2 border-green-400 rounded"></div>
                                        <span>Position occupée</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 bg-blue-100 border-2 border-blue-400 rounded"></div>
                                        <span>Archer en fauteuil</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="w-4 h-4 bg-gray-100 border-2 border-gray-300 rounded opacity-70"></div>
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