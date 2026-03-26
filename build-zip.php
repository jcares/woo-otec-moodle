<?php
/**
 * Empaquetador ZIP para WordPress (Evita el problema de backslashes de Windows)
 */
$sourcePath = __DIR__;
$pluginsDir = $sourcePath . '/plugins';

if (!is_dir($pluginsDir)) {
    mkdir($pluginsDir, 0755, true);
}

// Renombrar pcc-woootec-chile antiguo a la nueva nomenclatura
if (file_exists($pluginsDir . '/pcc-woootec-chile.zip')) {
    rename($pluginsDir . '/pcc-woootec-chile.zip', $pluginsDir . '/pcc-woootec-chile-' . date('Ymd-His') . '.zip');
}

$zipName = 'woo-otec-moodle.zip';
$zipPath = $pluginsDir . '/' . $zipName;
$baseFolder = 'woo-otec-moodle';

if (file_exists($zipPath)) {
    $dateStr = date('Ymd-His');
    $oldZipName = "woo-otec-moodle-$dateStr.zip";
    rename($zipPath, $pluginsDir . '/' . $oldZipName);
    echo "Respaldo anterior guardado como $oldZipName\n";
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Error al crear el archivo ZIP\n");
}

$excludes = ['.git', 'node_modules', 'logs', 'plugins', 'etapas.txt', 'prompt_mejoras.txt', 'readme.md', '.gitattributes', 'build-zip.php'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($sourcePath) + 1);
    
    // Convertir de \ a / para asegurar compatibilidad universal en WP (Linux)
    $relativePath = str_replace('\\', '/', $relativePath);
    
    // Ignorar archivos no deseados
    $skip = false;
    foreach ($excludes as $exclude) {
        if (strpos($relativePath, $exclude . '/') === 0 || $relativePath === $exclude) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    $zipPathInternal = $baseFolder . '/' . $relativePath;
    $zip->addFile($filePath, $zipPathInternal);
}

$zip->close();
echo "-> NUEVO ZIP CREADO CORRECTAMENTE EN: $zipPath (Con rutas Unix soportadas por WordPress)\n";
