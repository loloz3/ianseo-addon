// Fonction pour calculer le nombre maximum de cibles
const calculateMaxTargets = () => {
    let maxTarget = 0;
    INITIAL_ARCHERS.forEach(archer => {
        if (archer.targetNo) {
            const targetNum = parseInt(archer.targetNo.substring(0, 3));
            if (targetNum > maxTarget) {
                maxTarget = targetNum;
            }
        }
    });
    return Math.max(maxTarget + 5, 10);
};

// Fonction pour obtenir l'image de la cible (target face individuelle)
const getTargetFaceImage = (targetFace) => {
    if (!targetFace) return null;
    
    const faceMap = {
        '1': 'Img/1a.png', 
        '2': 'Img/2a.png', 
        '3': 'Img/3a.png',
        '4': 'Img/4a.png', 
        '5': 'Img/5a.png',
        '6': 'Img/6a.png', 
        '7': 'Img/7a.png',
    };
    
    return faceMap[targetFace] || faceMap['1'];
};

// Fonction pour vérifier la validité d'une paire de positions (A/C ou B/D)
const checkPairValid = (face1, face2) => {
    if (!face1 || !face2) return true; // Une position vide est toujours valide
    
    // Règle 1: Pas de mélange 4/6 avec autres faces
    const is4or6 = (face) => face === '4' || face === '6';
    const isOtherFace = (face) => !is4or6(face);
    
    if ((is4or6(face1) && isOtherFace(face2)) || 
        (isOtherFace(face1) && is4or6(face2))) {
        return false; // Mélange interdit
    }
    
    // Règle 2: Pour les autres faces (1,2,5,7), doivent être identiques
    if (isOtherFace(face1) && isOtherFace(face2)) {
        return face1 === face2; // Doivent être identiques
    }
    
    // Règle 3: Pour 4/6, toujours valide (4/4, 6/6, 4/6, 6/4)
    return true;
};

// Fonction pour obtenir l'image de combinaison basée sur les targetFace
const getCombinationImage = (target) => {
    const positions = ['A', 'B', 'C', 'D'];
    const targetFaces = {};
    
    // Récupérer les targetFace pour chaque position
    positions.forEach(letter => {
        const position = target.positions.find(p => p.letter === letter);
        targetFaces[letter] = position?.archer?.targetFace || null;
    });
    
    // Vérifier s'il y a un archer avec targetFace = 3 (80cm)
    const hasTargetFace3 = Object.values(targetFaces).some(face => face === '3');
    
    // Si un archer est en 3 (80cm), TOUS doivent être en 3 ou vides
    if (hasTargetFace3) {
        // Vérifier s'il y a un archer avec une face différente de 3
        const hasDifferentFace = Object.values(targetFaces).some(face => 
            face && face !== '3'
        );
        
        if (hasDifferentFace) {
            return 'Img/xx.png'; // Configuration invalide
        }
        
        // Tous les archers sont en 3 ou vides
        return 'Img/3.png';
    }
    
    // Vérifier la consistance des paires A/C et B/D
    // Règle: Si les deux positions d'une paire sont occupées, elles doivent être compatibles
    const checkPairConsistency = (pos1, pos2) => {
        const face1 = targetFaces[pos1];
        const face2 = targetFaces[pos2];
        
        // Si les deux positions sont occupées
        if (face1 && face2) {
            // Vérifier la compatibilité
            if (!checkPairValid(face1, face2)) {
                return false;
            }
        }
        return true;
    };
    
    // Vérifier les paires A/C et B/D
    if (!checkPairConsistency('A', 'C') || !checkPairConsistency('B', 'D')) {
        return 'Img/xx.png'; // Configuration invalide
    }
    
    // Déterminer les chiffres pour l'image
    // Pour A/C : si A existe, prendre A, sinon si C existe, prendre C, sinon 'x'
    let tensDigit = 'x';
    if (targetFaces['A']) {
        tensDigit = targetFaces['A'];
    } else if (targetFaces['C']) {
        tensDigit = targetFaces['C'];
    }
    
    // Pour B/D : si B existe, prendre B, sinon si D existe, prendre D, sinon 'x'
    let unitsDigit = 'x';
    if (targetFaces['B']) {
        unitsDigit = targetFaces['B'];
    } else if (targetFaces['D']) {
        unitsDigit = targetFaces['D'];
    }
    
    // Si aucun archer n'est assigné sur toute la cible
    if (tensDigit === 'x' && unitsDigit === 'x') {
        return null; // Pas d'image à afficher
    }
    
    return `Img/${tensDigit}${unitsDigit}.png`;
};

// Fonction pour obtenir le label de l'image de combinaison
const getCombinationLabel = (target) => {
    const positions = ['A', 'B', 'C', 'D'];
    const targetFaces = {};
    
    // Récupérer les targetFace pour chaque position
    positions.forEach(letter => {
        const position = target.positions.find(p => p.letter === letter);
        targetFaces[letter] = position?.archer?.targetFace || null;
    });
    
    // Vérifier s'il y a un archer avec targetFace = 3 (80cm)
    const hasTargetFace3 = Object.values(targetFaces).some(face => face === '3');
    
    // Si un archer est en 3 (80cm), TOUS doivent être en 3 ou vides
    if (hasTargetFace3) {
        // Vérifier s'il y a un archer avec une face différente de 3
        const hasDifferentFace = Object.values(targetFaces).some(face => 
            face && face !== '3'
        );
        
        if (hasDifferentFace) {
            return 'Configuration invalide (80cm mixte)';
        }
        
        // Compter combien d'archers en 3
        const count3 = Object.values(targetFaces).filter(face => face === '3').length;
        return `80cm (${count3} archer${count3 > 1 ? 's' : ''})`;
    }
    
    // Vérifier la consistance des paires A/C et B/D
    const checkPairConsistency = (pos1, pos2) => {
        const face1 = targetFaces[pos1];
        const face2 = targetFaces[pos2];
        
        // Si les deux positions sont occupées
        if (face1 && face2) {
            // Vérifier la compatibilité
            if (!checkPairValid(face1, face2)) {
                return false;
            }
        }
        return true;
    };
    
    // Vérifier les paires A/C et B/D
    if (!checkPairConsistency('A', 'C')) {
        const faceA = targetFaces['A'];
        const faceC = targetFaces['C'];
        if (faceA && faceC) {
            return `Configuration invalide (A=${faceA}, C=${faceC} incompatibles)`;
        }
    }
    
    if (!checkPairConsistency('B', 'D')) {
        const faceB = targetFaces['B'];
        const faceD = targetFaces['D'];
        if (faceB && faceD) {
            return `Configuration invalide (B=${faceB}, D=${faceD} incompatibles)`;
        }
    }
    
    // Déterminer les chiffres pour l'image
    let tensDigit = 'x';
    let tensSource = '';
    if (targetFaces['A']) {
        tensDigit = targetFaces['A'];
        tensSource = 'A';
    } else if (targetFaces['C']) {
        tensDigit = targetFaces['C'];
        tensSource = 'C';
    }
    
    let unitsDigit = 'x';
    let unitsSource = '';
    if (targetFaces['B']) {
        unitsDigit = targetFaces['B'];
        unitsSource = 'B';
    } else if (targetFaces['D']) {
        unitsDigit = targetFaces['D'];
        unitsSource = 'D';
    }
    
    if (tensDigit === 'x' && unitsDigit === 'x') {
        return 'Pas de configuration';
    }
    
    return `Configuration: ${tensDigit}${unitsDigit}`;
};

// Fonction pour vérifier si une position est bloquée par un fauteuil
const isPositionBlockedByWheelchair = (target, positionLetter, archers, session) => {
    // Vérifier s'il y a un fauteuil dans la cible
    const wheelchairPositions = target.positions
        .filter(pos => pos.archer && pos.archer.wheelchair && pos.archer.session === session)
        .map(pos => pos.letter);
    
    if (wheelchairPositions.length === 0) return false;
    
    // Règles de blocage: A↔C et B↔D
    const blockingPairs = {
        'A': 'C',
        'C': 'A',
        'B': 'D',
        'D': 'B'
    };
    
    return wheelchairPositions.some(wcPos => {
        // Si la position actuelle est celle du fauteuil
        if (wcPos === positionLetter) return false; // La position elle-même n'est pas bloquée
        
        // Si la position actuelle est bloquée par le fauteuil
        return blockingPairs[wcPos] === positionLetter;
    });
};

// Fonction pour vérifier la compatibilité avant un drop
const checkDropCompatibility = (target, positionLetter, archerToDrop, archers, session) => {
    const targetFaces = {};
    const positions = ['A', 'B', 'C', 'D'];
    
    // Récupérer les targetFace actuelles de la cible
    positions.forEach(letter => {
        const position = target.positions.find(p => p.letter === letter);
        targetFaces[letter] = position?.archer?.targetFace || null;
    });
    
    // Simuler l'assignation
    targetFaces[positionLetter] = archerToDrop.targetFace;
    
    // Vérifier la règle du 80cm
    const hasTargetFace3 = Object.values(targetFaces).some(face => face === '3');
    if (hasTargetFace3) {
        // Si on essaie d'ajouter un archer non-80cm à une cible avec 80cm
        if (archerToDrop.targetFace !== '3') {
            const existing3 = positions.some(letter => 
                targetFaces[letter] === '3' && letter !== positionLetter
            );
            if (existing3) {
                return { valid: false, reason: 'Impossible de mélanger 80cm avec d\'autres faces' };
            }
        }
        // Si on essaie d'ajouter un 80cm à une cible avec d'autres faces
        if (archerToDrop.targetFace === '3') {
            const hasOtherFaces = positions.some(letter => 
                targetFaces[letter] && targetFaces[letter] !== '3' && letter !== positionLetter
            );
            if (hasOtherFaces) {
                return { valid: false, reason: 'Impossible de mélanger 80cm avec d\'autres faces' };
            }
        }
    }
    
    // Déterminer la paire de la position
    const pairPositions = {
        'A': 'C',
        'C': 'A',
        'B': 'D',
        'D': 'B'
    };
    const otherPosition = pairPositions[positionLetter];
    const otherFace = targetFaces[otherPosition];
    
    // Vérifier la compatibilité avec l'autre position de la paire
    if (otherFace && archerToDrop.targetFace) {
        if (!checkPairValid(archerToDrop.targetFace, otherFace)) {
            return { 
                valid: false, 
                reason: `Incompatible avec la position ${otherPosition} (face ${otherFace})`
            };
        }
    }
    
    return { valid: true };
};

// Fonction pour convertir le format DB (1001A) vers le format interface (001A)
const convertDbToInterface = (dbTargetNo) => {
    if (!dbTargetNo || dbTargetNo.length < 5) return '';
    return dbTargetNo.substring(1);
};

// Fonction pour convertir le format interface (001A) vers le format DB (1001A)
const convertInterfaceToDb = (targetNo, session) => {
    if (!targetNo) return '';
    return session + targetNo;
};

// Icônes
const Icons = {
    Users: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
    ),
    Target: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <circle cx="12" cy="12" r="6"/>
            <circle cx="12" cy="12" r="2"/>
        </svg>
    ),
    Check: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
    ),
    AlertCircle: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    ),
    Warning: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
    ),
    Wheelchair: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="10" rx="2"/>
            <circle cx="8" cy="16" r="1"/>
            <circle cx="16" cy="16" r="1"/>
            <path d="M10 11V6a2 2 0 0 1 2-2v0a2 2 0 0 1 2 2"/>
            <path d="M12 18v-2"/>
            <path d="M8 22v-2"/>
            <path d="M16 22v-2"/>
        </svg>
    ),
    Lock: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    ),
    Grid: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
        </svg>
    ),
    ChevronDown: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    ),
    ChevronUp: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="18 15 12 9 6 15"/>
        </svg>
    ),
    Print: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
    )
};