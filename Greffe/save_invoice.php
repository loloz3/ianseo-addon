<?php
/**
 * @license Libre - Copyright (c) 2025 Auteur Original
 * 
 * save_invoice.php
 * Sauvegarde une facture HTML dans le répertoire "print"
 * Version simplifiée - retourne JSON seulement
 */

// Activer l'affichage des erreurs pour débogage
error_reporting(E_ALL);
ini_set('display_errors', 0); // Mettre à 1 pour déboguer, 0 en production

// Toujours retourner du JSON
header('Content-Type: application/json');

// Vérifier si des données POST ont été envoyées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données POST
$html_content = isset($_POST['html_content']) ? $_POST['html_content'] : '';
$filename = isset($_POST['filename']) ? $_POST['filename'] : '';

if (empty($html_content) || empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

// Définir le répertoire de sauvegarde
$printDir = dirname(__FILE__) . '/print';

// Créer le répertoire "print" s'il n'existe pas
if (!file_exists($printDir)) {
    if (!mkdir($printDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire print']);
        exit();
    }
}

// Vérifier que le répertoire est accessible en écriture
if (!is_writable($printDir)) {
    // Essayer de changer les permissions
    @chmod($printDir, 0755);
    
    if (!is_writable($printDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Répertoire non accessible en écriture']);
        exit();
    }
}

// Nettoyer le nom de fichier pour la sécurité
$filename = basename($filename);
// Remplacer les caractères non autorisés
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
// S'assurer que l'extension est .html
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'html') {
    $filename .= '.html';
}

// Chemin complet du fichier
$filepath = $printDir . '/' . $filename;

// Éviter les conflits de noms - ajouter un numéro si le fichier existe déjà
$counter = 1;
$original_filename = $filename;
while (file_exists($filepath)) {
    $filename = pathinfo($original_filename, PATHINFO_FILENAME) . "_$counter." . pathinfo($original_filename, PATHINFO_EXTENSION);
    $filepath = $printDir . '/' . $filename;
    $counter++;
    
    // Limite de sécurité
    if ($counter > 100) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Trop de fichiers avec le même nom']);
        exit();
    }
}

// Sauvegarder le fichier HTML
$result = file_put_contents($filepath, $html_content);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'écriture du fichier']);
    exit();
}

// Réponse de succès
echo json_encode([
    'success' => true,
    'message' => 'Facture sauvegardée avec succès',
    'filename' => $filename,
    'filepath' => $filepath,
    'filesize' => filesize($filepath),
    'url' => 'print/' . $filename,
    'human_size' => number_format(filesize($filepath) / 1024, 2) . ' Ko'
]);

exit();
?>