<?php
/**
 * Convertisseur de fichier .exp Ianseo vers .ianseo
 * Fonctionne en navigateur (avec upload) ou en ligne de commande.
 */

// Déterminer le fichier source
$expFile = null;

if (PHP_SAPI === 'cli') {
    // Mode ligne de commande
    if ($argc < 2) {
        die("Usage: php exp2ianseo.php <fichier.exp>\n");
    }
    $expFile = $argv[1];
} else {
    // Mode navigateur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['expfile'])) {
        if ($_FILES['expfile']['error'] !== UPLOAD_ERR_OK) {
            die("Erreur lors de l'upload (code " . $_FILES['expfile']['error'] . ")\n");
        }
        $expFile = $_FILES['expfile']['tmp_name'];
        $originalName = $_FILES['expfile']['name'];
    }
}

// Si aucun fichier fourni, afficher le formulaire
if ($expFile === null) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Convertisseur EXP → IANSEO</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            h1 { color: #333; }
            form { border: 2px dashed #aaa; padding: 30px; border-radius: 8px; text-align: center; }
            input[type=file] { margin: 15px 0; }
            button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #45a049; }
            .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>Convertisseur EXP → IANSEO</h1>
        <div class="info">
            Sélectionnez un fichier <strong>.exp</strong> exporté depuis Ianseo.
            Le fichier <strong>.ianseo</strong> contenu sera extrait et téléchargé.
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="expfile" accept=".exp" required>
            <br>
            <button type="submit">Extraire le fichier .ianseo</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Vérifier que le fichier existe
if (!file_exists($expFile)) {
    die("Fichier introuvable : $expFile\n");
}

// 1. Lire le contenu binaire
$rawData = file_get_contents($expFile);

if ($rawData === false || $rawData === '') {
    die("Erreur : impossible de lire le fichier ou fichier vide.\n");
}

// 2. Décompresser
$uncompressed = @gzuncompress($rawData);

if ($uncompressed === false) {
    die("Erreur : impossible de décompresser le fichier .exp. Est-ce un fichier EXP valide ?\n");
}

// 3. Désérialiser
$tourArray = @unserialize($uncompressed);

if ($tourArray === false || !is_array($tourArray)) {
    die("Erreur : le contenu désérialisé n'est pas un tableau valide.\n");
}

// 4. Chercher la clé .ianseo
$ianseoData = null;
$ianseoFileName = null;

foreach ($tourArray as $key => $value) {
    if (substr($key, -7) === '.ianseo') {
        $ianseoData = $value;
        $ianseoFileName = $key;
        break;
    }
}

if ($ianseoData === null) {
    die("Erreur : aucun fichier .ianseo trouvé dans l'archive EXP.\n");
}

// 5. Sortie
if (PHP_SAPI === 'cli') {
    $outputFile = dirname($expFile) . '/' . $ianseoFileName;
    if (file_put_contents($outputFile, $ianseoData) === false) {
        die("Erreur : impossible d'écrire le fichier $outputFile\n");
    }
    echo "Succès ! Fichier généré : $outputFile\n";
    echo "Taille : " . number_format(strlen($ianseoData)) . " octets\n";
} else {
    // Téléchargement direct dans le navigateur
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $ianseoFileName . '"');
    header('Content-Length: ' . strlen($ianseoData));
    echo $ianseoData;
    exit;
}


?>