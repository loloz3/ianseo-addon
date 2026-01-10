<?php
// github_update_simple_auto.php - Version ultra simple sans confirmation

echo "<h2>🔄 Mise à jour automatique depuis GitHub</h2>";
echo "<style>
    body {font-family: Arial; padding: 20px; background: #f5f5f5;}
    .container {max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
    .success {color: green; font-weight: bold;}
    .error {color: red; font-weight: bold;}
</style>";
echo "<div class='container'>";

// Configuration
$githubRepo = "loloz3/ianseo-addon";
$branch = "main";
$currentDir = dirname(__FILE__);
$customDir = dirname($currentDir);

echo "<p>Début de la mise à jour...</p>";

// 1. Télécharger le ZIP
echo "<p>1. Téléchargement depuis GitHub...</p>";
$zipUrl = "https://github.com/{$githubRepo}/archive/{$branch}.zip";

$zipContent = @file_get_contents($zipUrl);
if ($zipContent === false && function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $zipContent = curl_exec($ch);
    curl_close($ch);
}

if ($zipContent === false) {
    echo "<p class='error'>❌ Erreur de téléchargement</p>";
    exit;
}

// 2. Extraire
echo "<p>2. Extraction...</p>";
$tempZip = tempnam(sys_get_temp_dir(), 'github_') . '.zip';
file_put_contents($tempZip, $zipContent);

$zip = new ZipArchive;
if ($zip->open($tempZip) !== TRUE) {
    echo "<p class='error'>❌ Erreur ZIP</p>";
    exit;
}

$tempDir = sys_get_temp_dir() . '/github_' . time();
mkdir($tempDir);
$zip->extractTo($tempDir);
$zip->close();
unlink($tempZip);

// 3. Trouver le dossier extrait
$items = scandir($tempDir);
$sourceDir = '';
foreach ($items as $item) {
    if ($item != '.' && $item != '..') {
        $sourceDir = $tempDir . '/' . $item;
        break;
    }
}

if (empty($sourceDir)) {
    echo "<p class='error'>❌ Archive vide</p>";
    exit;
}

// 4. Copier les fichiers
echo "<p>3. Copie des fichiers...</p>";
$count = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir));
        $destPath = $customDir . $relativePath;
        
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        if (copy($item->getPathname(), $destPath)) {
            $count++;
        }
    }
}

// 5. Nettoyer
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($files as $file) {
    if ($file->isDir()) {
        @rmdir($file->getPathname());
    } else {
        @unlink($file->getPathname());
    }
}
@rmdir($tempDir);

// 6. Résultat
echo "<p class='success'>✅ Mise à jour terminée !</p>";
echo "<p><strong>$count fichiers mis à jour</strong></p>";

echo "<script>
    setTimeout(function() {
        alert('Mise à jour terminée ($count fichiers mis à jour)');
        window.location.href = 'aide-concours.php';
    }, 1000);
</script>";

echo "</div>";
?>