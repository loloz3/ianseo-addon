<?php
/**
 * Script de mise à jour depuis GitHub - Compatible Linux/Windows
 * 
 * Télécharge et installe la dernière version du addon IANSEO
 * depuis https://github.com/loloz3/ianseo-addon
 */

// Désactiver la limite d'exécution
set_time_limit(300);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// Début de l'output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Mise à jour GitHub - IANSEO Addon</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c5f2d;
            border-bottom: 3px solid #2c5f2d;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #e7f3ff;
        }
        .step-title {
            font-weight: bold;
            color: #0056b3;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2c5f2d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #1e3d24;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            width: 0%;
            transition: width 0.5s ease;
        }
        .permissions-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔄 Mise à jour automatique depuis GitHub</h1>";
        
// Fonction de logging
function logMessage($message, $type = 'info') {
    $prefix = date('H:i:s') . ' - ';
    switch($type) {
        case 'success': $prefix .= '✅ '; break;
        case 'error': $prefix .= '❌ '; break;
        case 'warning': $prefix .= '⚠️ '; break;
        default: $prefix .= 'ℹ️ '; break;
    }
    echo "<div class='$type'>$prefix$message</div>\n";
    flush();
    ob_flush();
}

// Vérifier si c'est Windows ou Linux
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
logMessage("Système détecté: " . ($isWindows ? 'Windows' : 'Linux'), 'info');

// Configuration
$githubRepo = "loloz3/ianseo-addon";
$branch = "main";
$currentDir = __DIR__;
$customDir = dirname($currentDir); // Remonter d'un niveau (dossier Custom)

// Vérifier le dossier de destination
logMessage("Dossier de destination: $customDir", 'info');

// Vérifier les permissions d'écriture
echo "<div class='step'>
        <div class='step-title'>1. Vérification des permissions</div>";

if (!is_writable($customDir)) {
    logMessage("ATTENTION: Le dossier $customDir n'est pas accessible en écriture", 'warning');
    
    echo "<div class='permissions-info'>
            <strong>Solution pour Linux :</strong><br>
            1. Donner les permissions d'écriture :<br>
            <code>sudo chmod -R 755 " . htmlspecialchars($customDir) . "</code><br><br>
            2. Changer le propriétaire (si nécessaire) :<br>
            <code>sudo chown -R www-data:www-data " . htmlspecialchars($customDir) . "</code><br>
            ou<br>
            <code>sudo chown -R apache:apache " . htmlspecialchars($customDir) . "</code>
          </div>";
    
    // Essayer de changer les permissions via PHP (si possible)
    if (!$isWindows) {
        @chmod($customDir, 0755);
        logMessage("Tentative de changement de permissions sur $customDir", 'info');
    }
}

// Vérifier l'extension ZIP
if (!class_exists('ZipArchive')) {
    logMessage("ERREUR: L'extension ZipArchive n'est pas disponible", 'error');
    echo "<p>Installez l'extension ZIP de PHP :</p>
          <ul>
            <li>Ubuntu/Debian: <code>sudo apt-get install php-zip</code></li>
            <li>CentOS/RHEL: <code>sudo yum install php-zip</code></li>
            <li>Windows: Activez extension=zip dans php.ini</li>
          </ul>";
    exit;
}

logMessage("Permissions vérifiées", 'success');
echo "</div>";

// 1. Télécharger le ZIP
echo "<div class='step'>
        <div class='step-title'>2. Téléchargement depuis GitHub</div>";

$zipUrl = "https://github.com/{$githubRepo}/archive/{$branch}.zip";
logMessage("URL: $zipUrl", 'info');

// Utiliser cURL si disponible, sinon file_get_contents
$zipContent = false;
$useCurl = false;

if (function_exists('curl_init')) {
    logMessage("Utilisation de cURL pour le téléchargement", 'info');
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $zipUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (IANSEO-Updater)'
    ]);
    
    $zipContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($zipContent === false || $httpCode !== 200) {
        logMessage("Erreur cURL ($httpCode): $error", 'error');
        $zipContent = false;
    } else {
        logMessage("Téléchargement réussi via cURL (" . strlen($zipContent) . " octets)", 'success');
        $useCurl = true;
    }
}

// Fallback: file_get_contents avec contexte
if ($zipContent === false && ini_get('allow_url_fopen')) {
    logMessage("Essai avec file_get_contents...", 'info');
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 60,
            'user_agent' => 'IANSEO-Updater/1.0'
        ]
    ]);
    
    $zipContent = @file_get_contents($zipUrl, false, $context);
    
    if ($zipContent !== false) {
        logMessage("Téléchargement réussi via file_get_contents (" . strlen($zipContent) . " octets)", 'success');
    } else {
        logMessage("Échec du téléchargement", 'error');
    }
}

if ($zipContent === false) {
    logMessage("Impossible de télécharger depuis GitHub. Vérifiez la connexion Internet.", 'error');
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='progress-bar'><div class='progress-fill' style='width: 25%'></div></div>";
echo "</div>";

// 2. Extraire le ZIP
echo "<div class='step'>
        <div class='step-title'>3. Extraction de l'archive</div>";

// Créer un fichier temporaire
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ianseo_update_' . time();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$tempZip = $tempDir . DIRECTORY_SEPARATOR . 'archive.zip';
file_put_contents($tempZip, $zipContent);
logMessage("Archive sauvegardée: " . filesize($tempZip) . " octets", 'info');

// Extraire
$zip = new ZipArchive;
if ($zip->open($tempZip) !== TRUE) {
    logMessage("Impossible d'ouvrir l'archive ZIP", 'error');
    @unlink($tempZip);
    @rmdir($tempDir);
    exit;
}

$extractResult = $zip->extractTo($tempDir);
$zip->close();

if (!$extractResult) {
    logMessage("Échec de l'extraction", 'error');
    @unlink($tempZip);
    @rmdir($tempDir);
    exit;
}

logMessage("Archive extraite avec succès", 'success');
@unlink($tempZip);

// Trouver le dossier extrait
$items = scandir($tempDir);
$sourceDir = '';
foreach ($items as $item) {
    if ($item != '.' && $item != '..' && is_dir($tempDir . DIRECTORY_SEPARATOR . $item)) {
        $sourceDir = $tempDir . DIRECTORY_SEPARATOR . $item;
        break;
    }
}

if (empty($sourceDir) || !is_dir($sourceDir)) {
    logMessage("Dossier source non trouvé dans l'archive", 'error');
    exit;
}

logMessage("Dossier source trouvé: " . basename($sourceDir), 'info');

echo "<div class='progress-bar'><div class='progress-fill' style='width: 50%'></div></div>";
echo "</div>";

// 3. Copier les fichiers
echo "<div class='step'>
        <div class='step-title'>4. Copie des fichiers</div>";

$count = 0;
$errorCount = 0;
$skipped = 0;
$totalFiles = 0;

// Compter les fichiers d'abord
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $totalFiles++;
    }
}

logMessage("$totalFiles fichiers à copier", 'info');

// Copier les fichiers
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
            if (!mkdir($destDir, 0755, true)) {
                logMessage("Impossible de créer le dossier: $destDir", 'error');
                $errorCount++;
                continue;
            }
        }
        
        // Vérifier si le fichier existe déjà
        if (file_exists($destPath)) {
            // Sauvegarder l'ancienne version si c'est un fichier important
            $backupExt = '.backup_' . date('Ymd_His');
            if (preg_match('/\.(php|inc|js|css|sql)$/i', $destPath)) {
                @copy($destPath, $destPath . $backupExt);
            }
        }
        
        if (copy($item->getPathname(), $destPath)) {
            $count++;
            // Changer les permissions sur Linux
            if (!$isWindows) {
                @chmod($destPath, 0644);
            }
            
            // Afficher la progression tous les 10 fichiers
            if ($count % 10 === 0) {
                $percent = min(50 + (($count / $totalFiles) * 25), 75);
                echo "<script>document.querySelector('.progress-fill').style.width = '$percent%';</script>";
                flush();
                ob_flush();
            }
        } else {
            logMessage("Échec de copie: " . basename($item->getPathname()), 'error');
            $errorCount++;
        }
    }
}

echo "<div class='progress-bar'><div class='progress-fill' style='width: 75%'></div></div>";
echo "</div>";

// 4. Nettoyer
echo "<div class='step'>
        <div class='step-title'>5. Nettoyage</div>";

// Supprimer le dossier temporaire
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

logMessage("Nettoyage terminé", 'success');

echo "<div class='progress-bar'><div class='progress-fill' style='width: 100%'></div></div>";
echo "</div>";

// 5. Résumé
echo "<div class='step'>
        <div class='step-title'>6. Résumé de la mise à jour</div>";

if ($count > 0) {
    logMessage("✅ Mise à jour terminée avec succès !", 'success');
    echo "<p><strong>Statistiques :</strong></p>
          <ul>
            <li>Fichiers copiés : <span class='success'>$count</span></li>
            <li>Erreurs : <span class='" . ($errorCount > 0 ? 'error' : 'success') . "'>$errorCount</span></li>
            <li>Fichiers ignorés : $skipped</li>
          </ul>";
    
    // Vérifier certains fichiers importants
    $importantFiles = [
        'aide-concours.php',
        'github_update.php',
        'PrintScoreAuto.php'
    ];
    
    echo "<p><strong>Vérification des fichiers importants :</strong></p><ul>";
    foreach ($importantFiles as $file) {
        $fullPath = $customDir . DIRECTORY_SEPARATOR . 'aide' . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            echo "<li><span class='success'>✅</span> $file</li>";
        } else {
            echo "<li><span class='error'>❌</span> $file (manquant)</li>";
        }
    }
    echo "</ul>";
    
    // Message de succès
    echo "<div style='text-align: center; margin: 30px 0;'>
            <h2 class='success'>✅ Mise à jour réussie !</h2>
            <p>Le addon a été mis à jour avec la dernière version depuis GitHub.</p>
            <a href='aide-concours.php' class='btn'>Retour à l'aide concours</a>
          </div>";
} else {
    logMessage("Aucun fichier n'a été copié. Vérifiez les permissions.", 'error');
    echo "<a href='aide-concours.php' class='btn'>Retour</a>";
}

echo "</div>";

// Pied de page
echo "<div class='footer'>
        <p>Script de mise à jour GitHub - IANSEO Addon</p>
        <p>Repository : <a href='https://github.com/loloz3/ianseo-addon' target='_blank'>https://github.com/loloz3/ianseo-addon</a></p>
        <p>Dernière exécution : " . date('d/m/Y H:i:s') . "</p>
      </div>";

echo "</div>
    <script>
        // Redirection automatique après 5 secondes si succès
        if (" . ($count > 0 ? 'true' : 'false') . ") {
            setTimeout(function() {
                window.location.href = 'aide-concours.php';
            }, 5000);
        }
    </script>
</body>
</html>";
?>