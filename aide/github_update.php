<?php
// github_update.php - Version sans création de fichiers backup

// Configuration
set_time_limit(300);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '128M');

echo "<h2>🔄 Mise à jour depuis GitHub</h2>";
echo "<style>
    body {font-family: Arial; padding: 20px; background: #f5f5f5;}
    .container {max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);}
    .success {color: green; font-weight: bold;}
    .error {color: red; font-weight: bold;}
    .warning {color: orange; font-weight: bold;}
    .info {color: blue;}
    .log {background: #f8f9fa; border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px; font-family: monospace; max-height: 400px; overflow-y: auto;}
</style>";
echo "<div class='container'>";

// Détecter le système
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
echo "<p class='info'>Système: " . ($isWindows ? 'Windows' : 'Linux') . "</p>";

// Configuration
$githubRepo = "loloz3/ianseo-addon";
$branch = "main";
$currentDir = __DIR__;
$customDir = dirname($currentDir);

echo "<p>Début de la mise à jour...</p>";
echo "<div class='log'>";

// Fonction de log
function logMsg($msg, $type = 'info') {
    $prefix = date('H:i:s') . ' - ';
    switch($type) {
        case 'success': $prefix .= '✅ '; break;
        case 'error': $prefix .= '❌ '; break;
        case 'warning': $prefix .= '⚠️ '; break;
        default: $prefix .= 'ℹ️ '; break;
    }
    echo $prefix . $msg . "<br>";
    flush();
}

logMsg("Vérification des permissions...");

// Vérifier les permissions
if (!is_writable($customDir)) {
    logMsg("Le répertoire $customDir n'est pas accessible en écriture", 'error');
    if (!$isWindows) {
        echo "<p class='warning'>Sur Linux, exécutez :</p>";
        echo "<pre>chmod -R 755 " . htmlspecialchars($customDir) . "</pre>";
        echo "<p class='warning'>Si nécessaire :</p>";
        echo "<pre>chown -R www-data:www-data " . htmlspecialchars($customDir) . "</pre>";
    }
    exit;
}

logMsg("Permissions OK", 'success');

// 1. Télécharger le ZIP
logMsg("Téléchargement depuis GitHub...");
$zipUrl = "https://github.com/{$githubRepo}/archive/{$branch}.zip";

$zipContent = false;

// Essayer cURL d'abord
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'IANSEO-Updater');
    
    $zipContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $zipContent === false) {
        logMsg("Échec cURL (code $httpCode)", 'error');
        $zipContent = false;
    } else {
        logMsg("Téléchargement réussi via cURL (" . strlen($zipContent) . " octets)", 'success');
    }
}

// Fallback: file_get_contents
if ($zipContent === false && ini_get('allow_url_fopen')) {
    logMsg("Essai avec file_get_contents...", 'warning');
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 60
        ]
    ]);
    
    $zipContent = @file_get_contents($zipUrl, false, $context);
    
    if ($zipContent !== false) {
        logMsg("Téléchargement réussi via file_get_contents (" . strlen($zipContent) . " octets)", 'success');
    } else {
        logMsg("Échec du téléchargement", 'error');
    }
}

if ($zipContent === false) {
    logMsg("Impossible de télécharger. Vérifiez la connexion Internet.", 'error');
    exit;
}

// 2. Extraire
logMsg("Extraction de l'archive...");
$tempZip = tempnam(sys_get_temp_dir(), 'github_') . '.zip';
file_put_contents($tempZip, $zipContent);

$zip = new ZipArchive;
if ($zip->open($tempZip) !== TRUE) {
    logMsg("Erreur lors de l'ouverture du ZIP", 'error');
    @unlink($tempZip);
    exit;
}

$tempDir = sys_get_temp_dir() . '/' . 'github_' . time();
mkdir($tempDir, 0755, true);
$extractResult = $zip->extractTo($tempDir);
$zip->close();

if (!$extractResult) {
    logMsg("Échec de l'extraction", 'error');
    @unlink($tempZip);
    @rmdir($tempDir);
    exit;
}

logMsg("Archive extraite", 'success');
@unlink($tempZip);

// 3. Trouver le dossier extrait
$items = scandir($tempDir);
$sourceDir = '';
foreach ($items as $item) {
    if ($item != '.' && $item != '..' && is_dir($tempDir . '/' . $item)) {
        $sourceDir = $tempDir . '/' . $item;
        break;
    }
}

if (empty($sourceDir)) {
    logMsg("Archive vide ou structure incorrecte", 'error');
    exit;
}

logMsg("Dossier source: " . basename($sourceDir), 'info');

// 4. Copier les fichiers (SANS BACKUP) avec exclusion
logMsg("Copie des fichiers (sans backup - fichiers protégés exclus)...");
$count = 0;
$errorCount = 0;
$skippedCount = 0;

// Fichiers à ne PAS remplacer s'ils existent déjà
$protectedFiles = ['menu.php', 'Prix.txt'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir));
        $destPath = $customDir . $relativePath;
        $filename = basename($destPath);
        
        // Vérifier si c'est un fichier protégé qui existe déjà
        if (in_array($filename, $protectedFiles) && file_exists($destPath)) {
            logMsg("Fichier protégé conservé: $filename", 'info');
            $skippedCount++;
            continue;
        }
        
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                logMsg("Impossible de créer: $destDir", 'error');
                $errorCount++;
                continue;
            }
        }
        
        // COPIE DIRECTE SANS BACKUP
        if (copy($item->getPathname(), $destPath)) {
            $count++;
            // Ajuster les permissions sur Linux
            if (!$isWindows) {
                @chmod($destPath, 0644);
            }
            
            // Afficher progression tous les 20 fichiers
            if ($count % 20 === 0) {
                logMsg("$count fichiers copiés...");
                flush();
            }
        } else {
            logMsg("Échec copie: " . basename($item->getPathname()), 'error');
            $errorCount++;
        }
    }
}

// 5. Nettoyer
logMsg("Nettoyage des fichiers temporaires...");
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

logMsg("Nettoyage terminé", 'success');

echo "</div>"; // Fermer la div .log

// 6. Résultat
echo "<hr>";
echo "<h3>Résumé de la mise à jour</h3>";

if ($count > 0 || $skippedCount > 0) {
    echo "<p class='success'>✅ Mise à jour terminée avec succès !</p>";
    echo "<p><strong>Fichiers copiés :</strong> $count</p>";
    
    if ($skippedCount > 0) {
        echo "<p class='info'>Fichiers conservés (existant déjà) : $skippedCount</p>";
        echo "<ul>";
        foreach ($protectedFiles as $file) {
            if (file_exists($customDir . '/' . $file)) {
                echo "<li class='info'>✅ $file - conservé (existant déjà)</li>";
            }
        }
        echo "</ul>";
    }
    
    if ($errorCount > 0) {
        echo "<p class='warning'>Erreurs : $errorCount</p>";
    }
    
    // Vérifier quelques fichiers importants
    echo "<p><strong>Vérification rapide :</strong></p>";
    $checkFiles = [
        'aide-concours.php' => $customDir . '/aide/aide-concours.php',
        'github_update.php' => $customDir . '/aide/github_update.php',
        'menu.php (protégé)' => $customDir . '/menu.php',
        'Prix.txt (protégé)' => $customDir . '/Greffe/Prix.txt',
    ];
    
    foreach ($checkFiles as $name => $path) {
        if (file_exists($path)) {
            $status = strpos($name, 'protégé') !== false ? 'conservé' : 'présent';
            echo "<p class='success'>✅ $name - $status</p>";
        } else {
            if (strpos($name, 'protégé') === false) {
                echo "<p class='error'>❌ $name absent</p>";
            } else {
                echo "<p class='info'>ℹ️ $name - non présent dans la source</p>";
            }
        }
    }
    
    echo "<script>
        setTimeout(function() {
            alert('Mise à jour terminée !\\\\n$count fichiers mis à jour.\\\\n$skippedCount fichiers conservés.');
            window.location.href = 'aide-concours.php';
        }, 1000);
    </script>";
    
} else {
    echo "<p class='error'>❌ Aucun fichier n'a été copié</p>";
    echo "<p>Vérifiez les permissions d'écriture dans le dossier Custom</p>";
}

echo "<p><a href='aide-concours.php' style='display: inline-block; padding: 10px 20px; background: #2c5f2d; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Retour à l'aide</a></p>";

echo "</div>";
?>