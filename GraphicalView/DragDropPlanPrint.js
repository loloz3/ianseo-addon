// Fonction pour générer un PDF/rapport des cibles avec image + colonnes A C B D
const generatePDFReport = (archers, selectedSession, targets, SESSIONS) => {
    // Ouvrir une nouvelle fenêtre pour l'impression
    const printWindow = window.open('', '_blank', 'width=1800,height=1000,scrollbars=yes,resizable=yes');
    
    if (!printWindow) {
        showNotification('Veuillez autoriser les fenêtres popup pour générer le PDF', 'error');
        return;
    }
    
    // Récupérer les cibles qui ont des archers assignés
    const targetsForPDF = targets.filter(target => 
        archers.some(a => 
            a.targetNo && 
            a.targetNo.startsWith(target.id) && 
            a.session === selectedSession
        )
    );
    
    // Construire le contenu HTML pour l'impression
    let html = `
        <!DOCTYPE html>
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
                    -webkit-print-color-adjust: exact;
                    color-adjust: exact;
                }
                .page-container {
                    width: 100%;
                    padding: 8mm;
                    box-sizing: border-box;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 15mm;
                    page-break-after: avoid;
                }
                .print-header h1 {
                    font-size: 24px;
                    margin: 0 0 5px 0;
                    color: #000;
                    font-weight: bold;
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
                    page-break-inside: avoid;
                }
                .targets-row {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    page-break-inside: avoid;
                }
                .target-row-wrapper {
                    display: flex;
                    justify-content: space-between;
                    width: 100%;
                    max-width: 100%;
                }
                .target-card {
                    flex: 0 0 7%;
                    text-align: center;
                    page-break-inside: avoid;
                }
                .target-header {
                    margin-bottom: 6px;
                }
                .target-number {
                    font-size: 13px;
                    font-weight: bold;
                    color: #000;
                    margin-bottom: 4px;
                }
                .target-image-container {
                    margin-bottom: 8px;
                }
                .target-image {
                    width: 55px;
                    height: 55px;
                    object-fit: contain;
                    display: block;
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
                    border-radius: 2px;
                    padding: 3px 2px;
                    background: #fafafa;
                    width: 100%;
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
                    border-radius: 1px;
                    min-height: 15px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .archer-content {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    width: 100%;
                    height: 200px;
                    overflow: hidden;
                    padding: 0 1px;
                }
                .archer-full-line {
                    font-size: 7.5px;
                    line-height: 1.1;
                    color: #000;
                    word-wrap: break-word;
                    writing-mode: vertical-lr;
                    text-orientation: mixed;
                    height: 200px;
                    display: flex;
                    align-items: center;
                    text-align: center;
                    width: 100%;
                    padding: 1px;
                }
                .club-code-bold {
                    font-weight: bold;
                }
                .club-details-normal {
                    font-weight: normal;
                }
                .archer-name-bold {
                    font-weight: bold;
                }
                .city-bold {
                    font-weight: bold;
                }
                .separator {
                    margin: 3px;
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
                    page-break-before: avoid;
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
                    font-size: 14px;
                    font-weight: bold;
                }
                .print-button:hover {
                    background: #2563eb;
                }
                .close-button {
                    padding: 10px 20px;
                    background: #6b7280;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: bold;
                }
                .close-button:hover {
                    background: #4b5563;
                }
                .empty-row {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    page-break-inside: avoid;
                    opacity: 0.3;
                }
                .empty-target {
                    flex: 0 0 9.5%;
                    text-align: center;
                }
                @media print {
                    body {
                        background: white;
                    }
                    .print-controls {
                        display: none !important;
                    }
                    .page-container {
                        padding: 0;
                    }
                    .targets-row {
                        gap: 8px;
                    }
                    .target-card {
                        page-break-inside: avoid;
                    }
                    .archer-column {
                        padding: 2px 1px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-controls">
                <button class="print-button" onclick="window.print();">Imprimer</button>
                <button class="close-button" onclick="window.close();">Fermer</button>
            </div>
            
            <div class="page-container">
                <div class="print-header">
                    <h1>Cibles - Départ ${selectedSession}</h1>
                    <div class="subtitle">
                        ${SESSIONS.find(s => s.order === selectedSession)?.description || ''} | ${new Date().toLocaleDateString('fr-FR')}
                    </div>
                </div>
                
                <div class="targets-layout">
    `;
    
    // Fonction pour créer la ligne complète verticale
    const createVerticalLine = (archer) => {
        if (!archer) return '';
        
        const division = archer.division || '';
        const archerClass = archer.class || '';
        const name = archer.name || '';
        const country = archer.countryName || '';
        
        let line = '';
        
        // Division en gras + Classe en normal
        if (division) {
            line += `<span class="club-code-bold">${division}</span>`;
        }
        
        // Classe (sans mise en forme spéciale)
        if (archerClass) {
            if (line && !line.endsWith(' ')) {
                line += ' ';
            }
            line += `<span class="club-details-normal">${archerClass}</span>`;
        }
        
        // Séparateur avant le nom (uniquement si division/classe présente)
        if ((division || archerClass) && name) {
            line += `<span class="separator"> </span>`;
        }
        
        // Nom de l'archer
        if (name) {
            line += `<span class="archer-name-bold">${name}</span>`;
        }
        
        // Séparateur avant le pays (uniquement si nom présent)
        if (name && country) {
            line += `<span class="separator"> </span>`;
        }
        
        // Pays en gras
        if (country) {
            line += `<span class="city-bold">${country}</span>`;
        }
        
        return line;
    };
    
    // Fonction pour créer une carte de cible
    const createTargetCard = (target) => {
        // Image de combinaison
        const combinationImage = getCombinationImage(target);
        
        // Récupérer les archers dans l'ordre A, C, B, D
        const positions = [
            { letter: 'A', order: 1 },
            { letter: 'C', order: 3 },
            { letter: 'B', order: 2 },
            { letter: 'D', order: 4 }
        ];
        
        const archersByPosition = {};
        
        positions.forEach(pos => {
            const archer = archers.find(a => 
                a.targetNo === `${target.id}${pos.letter}` && 
                a.session === selectedSession
            );
            archersByPosition[pos.letter] = {
                archer: archer,
                order: pos.order
            };
        });
        
        let cardHtml = `
            <div class="target-card">
                <div class="target-header">
                    <div class="target-number">${target.id}</div>
                    <div class="target-image-container">
        `;
        
        if (combinationImage) {
            cardHtml += `
                <img 
                    src="${combinationImage}" 
                    alt="Cible ${target.id}"
                    class="target-image"
                    onerror="this.style.display='none'"
                />
            `;
        } else {
            cardHtml += `
                <div style="width: 55px; height: 55px; margin: 0 auto; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 3px;">
                    <div style="font-size: 8px; color: #999;">Pas de cible</div>
                </div>
            `;
        }
        
        cardHtml += `
                    </div>
                </div>
                
                <div class="target-archers-grid">
        `;
        
        // Afficher dans l'ordre A, C, B, D
        positions.forEach(pos => {
            const { archer, order } = archersByPosition[pos.letter];
            
            cardHtml += `
                <div class="archer-column">
                    <div class="position-header">${pos.letter}</div>
                    <div class="archer-content">
            `;
            
            if (archer) {
                const verticalLine = createVerticalLine(archer);
                cardHtml += `
                        <div class="archer-full-line">
                            ${verticalLine}
                        </div>
                `;
            } else {
                cardHtml += `
                        <div class="empty-position">vide</div>
                `;
            }
            
            cardHtml += `
                    </div>
                </div>
            `;
        });
        
        cardHtml += `
                </div>
            </div>
        `;
        
        return cardHtml;
    };
    
    // Fonction pour créer une carte de cible vide
    const createEmptyTargetCard = () => {
        return `
            <div class="target-card" style="opacity: 0.3;">
                <div class="target-header">
                    <div class="target-number"></div>
                    <div style="width: 55px; height: 55px; margin: 0 auto; background: #f5f5f5; border-radius: 3px;"></div>
                </div>
                <div class="target-archers-grid">
                    <div class="archer-column" style="background: #f9f9f9;"></div>
                    <div class="archer-column" style="background: #f9f9f9;"></div>
                    <div class="archer-column" style="background: #f9f9f9;"></div>
                    <div class="archer-column" style="background: #f9f9f9;"></div>
                </div>
            </div>
        `;
    };
    
    // Diviser les cibles en deux lignes de 10 maximum
    const firstRowTargets = targetsForPDF.slice(0, 10);
    const secondRowTargets = targetsForPDF.slice(10, 20);
    
    // Première ligne
    html += `
        <div class="targets-row">
            <div class="target-row-wrapper">
    `;
    
    // Ajouter les 10 premières cibles
    for (let i = 0; i < 10; i++) {
        if (i < firstRowTargets.length) {
            html += createTargetCard(firstRowTargets[i]);
        } else {
            html += createEmptyTargetCard();
        }
    }
    
    html += `
            </div>
        </div>
    `;
    
    // Deuxième ligne
    html += `
        <div class="targets-row">
            <div class="target-row-wrapper">
    `;
    
    // Ajouter les 10 cibles suivantes (ou des cibles vides)
    for (let i = 0; i < 10; i++) {
        if (i < secondRowTargets.length) {
            html += createTargetCard(secondRowTargets[i]);
        } else {
            html += createEmptyTargetCard();
        }
    }
    
    html += `
            </div>
        </div>
    `;
    
    html += `
                </div>
                
                <div class="print-footer">
                    ${targetsForPDF.length} cibles assignées | Généré le ${new Date().toLocaleDateString('fr-FR')} ${new Date().toLocaleTimeString('fr-FR')}
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    // Ajuster la taille du texte si nécessaire
                    const adjustTextSize = () => {
                        document.querySelectorAll('.archer-full-line').forEach(lineElement => {
                            if (lineElement.scrollHeight > lineElement.clientHeight) {
                                lineElement.style.fontSize = '7px';
                            }
                        });
                    };
                    
                    setTimeout(() => {
                        adjustTextSize();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `;
    
    // Écrire le contenu dans la nouvelle fenêtre
    printWindow.document.write(html);
    printWindow.document.close();
    
    // Centrer la fenêtre
    printWindow.moveTo(
        Math.max(0, (screen.width - 1800) / 2),
        Math.max(0, (screen.height - 1000) / 2)
    );
    
    printWindow.focus();
    
    showNotification('PDF généré. Utilisez les boutons en haut à droite pour imprimer.', 'success');
};