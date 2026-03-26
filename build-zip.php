<?php
/**
 * WooOTEC Moodle - Build System
 * Autor: JCares
 * Propietario: PCCurico.cl
 */

$sourcePath = __DIR__;
$pluginsDir = $sourcePath . '/plugins';
$buildDir = $sourcePath . '/.build-temp';
$backupLimit = 4;

$baseName = "woo-otec-moodle";

if (!is_dir($pluginsDir))
    mkdir($pluginsDir, 0755, true);
if (is_dir($buildDir))
    rrmdir($buildDir);
mkdir($buildDir, 0755, true);

/**
 * =========================
 * VERSIONADO
 * =========================
 */
$versionFile = $sourcePath . '/version.txt';

if (!file_exists($versionFile)) {
    file_put_contents($versionFile, '1.0.0');
}

$currentVersion = trim(file_get_contents($versionFile));

function bump_patch($v)
{
    $p = explode('.', $v);
    $p[2] = (int)$p[2] + 1;
    return implode('.', $p);
}

$newVersion = bump_patch($currentVersion);
file_put_contents($versionFile, $newVersion);

echo "Version: $newVersion\n";

/**
 * =========================
 * ROTACION BACKUPS
 * =========================
 */
$files = glob($pluginsDir . "/$baseName-v*.zip");

usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

if (count($files) >= $backupLimit) {
    foreach (array_slice($files, $backupLimit - 1) as $f) {
        unlink($f);
    }
}

/**
 * =========================
 * COPIA PROYECTO A BUILD
 * =========================
 */
copy_recursive($sourcePath, $buildDir);

/**
 * =========================
 * INYECTAR LICENCIAS (PLUGIN)
 * =========================
 */
$licenseClass = <<<PHP

<?php
if (!defined('ABSPATH')) exit;

class PCC_License_Manager {

    private \$api_url = 'https://www.pccurico.cl/api/license-server.php';

    public function __construct() {
        add_action('admin_init', [\$this, 'check_license']);
    }

    public function validate_license(\$key) {
        \$url = \$this->api_url . '?action=validate&license_key=' . \$key . '&domain=' . \$_SERVER['HTTP_HOST'];
        \$r = wp_remote_get(\$url);
        return json_decode(wp_remote_retrieve_body(\$r), true);
    }

    public function check_license() {
        \$key = get_option('pcc_license_key');
        if (!\$key) return;

        \$res = \$this->validate_license(\$key);

        if (!isset(\$res['status']) || \$res['status'] !== 'valid') {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>Licencia invalida.</p></div>';
            });
        }
    }
}
PHP;

file_put_contents("$buildDir/includes/class-license.php", $licenseClass);

/**
 * =========================
 * INYECTAR UPDATER
 * =========================
 */
$updaterClass = <<<PHP

<?php
if (!defined('ABSPATH')) exit;

class PCC_Plugin_Updater {

    private \$url = 'https://www.pccurico.cl/updates/metadata.json';
    private \$plugin = 'woo-otec-moodle/woo-otec-moodle.php';

    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [\$this,'check']);
    }

    public function check(\$t) {
        if (empty(\$t->checked)) return \$t;

        \$r = wp_remote_get(\$this->url);
        \$data = json_decode(wp_remote_retrieve_body(\$r));

        if (version_compare(\$data->version, \$t->checked[\$this->plugin], '>')) {
            \$t->response[\$this->plugin] = (object)[
                'slug' => 'woo-otec-moodle',
                'new_version' => \$data->version,
                'package' => \$data->download_url
            ];
        }

        return \$t;
    }
}
PHP;

file_put_contents("$buildDir/includes/class-updater.php", $updaterClass);

/**
 * =========================
 * API LICENCIAS (SERVIDOR)
 * =========================
 */
$api = <<<PHP

<?php
header('Content-Type: application/json');
\$file = __DIR__.'/licenses.json';

if (!file_exists(\$file)) file_put_contents(\$file, json_encode([]));
\$data = json_decode(file_get_contents(\$file), true);

\$action = \$_GET['action'] ?? '';

if (\$action === 'validate') {
    \$k = \$_GET['license_key'] ?? '';
    \$d = \$_GET['domain'] ?? '';

    if (isset(\$data[\$k]) && \$data[\$k]['domain'] === \$d) {
        echo json_encode(['status'=>'valid']);
    } else {
        echo json_encode(['status'=>'invalid']);
    }
}
PHP;

file_put_contents("$buildDir/api-license.php", $api);

/**
 * =========================
 * METADATA UPDATE
 * =========================
 */
$metadata = json_encode([
    'version' => $newVersion,
    'download_url' => "https://www.pccurico.cl/downloads/woo-otec-moodle-v$newVersion.zip"
], JSON_PRETTY_PRINT);

file_put_contents("$buildDir/metadata.json", $metadata);

/**
 * =========================
 * CREAR ZIP
 * =========================
 */
$zipName = "$baseName-v$newVersion.zip";
$zipPath = "$pluginsDir/$zipName";

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)    );

foreach ($it as $file) {
    if ($file->isFile()) {
        $path = str_replace($buildDir . '/', '', $file);
        $zip->addFile($file, "$baseName/$path");
    }
}

$zip->close();

/**
 * =========================
 * CHECKSUM
 * =========================
 */
$hash = hash_file('sha256', $zipPath);
file_put_contents("$zipPath.sha256", $hash);

echo "Build listo: $zipName\n";

/**
 * =========================
 * HELPERS
 * =========================
 */
function copy_recursive($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..') && $file != '.build-temp') {
            if (is_dir("$src/$file")) {
                copy_recursive("$src/$file", "$dst/$file");
            }
            else {
                copy("$src/$file", "$dst/$file");
            }
        }
    }
    closedir($dir);
}

function rrmdir($dir)
{
    if (!is_dir($dir))
        return;
    foreach (scandir($dir) as $f) {
        if ($f != '.' && $f != '..') {
            $p = "$dir/$f";
            is_dir($p) ? rrmdir($p) : unlink($p);
        }
    }
    rmdir($dir);
}