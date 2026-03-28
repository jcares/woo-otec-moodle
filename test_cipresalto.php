<?php
/**
 * Script de pruebas E2E standalone para Woo OTEC Moodle.
 *
 * Uso (CLI):
 *   set PCC_MOODLE_URL=https://tu-campus.example
 *   set PCC_MOODLE_TOKEN=tu_token
 *   php test_cipresalto.php
 */

if (PHP_SAPI !== 'cli') {
    echo "Este script solo puede ejecutarse por CLI.\n";
    exit(1);
}

$moodle_url = trim((string) getenv('PCC_MOODLE_URL'));
$moodle_token = trim((string) getenv('PCC_MOODLE_TOKEN'));

if ($moodle_url === '' || $moodle_token === '') {
    echo "Faltan variables de entorno requeridas: PCC_MOODLE_URL y PCC_MOODLE_TOKEN.\n";
    exit(1);
}

// --- WP Mocks ---
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public $code, $message, $data;
    public function __construct($code = '', $message = '', $data = '') {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

function wp_parse_args($args, $defaults = []) { return array_merge($defaults, $args); }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key)); }
function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
function wp_generate_password($length = 12, $special = true, $extra = false) {
    return 'Pwd' . rand(1000, 9999) . '!a';
}

function wp_remote_post($url, $args = []) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($args['body'] ?? []),
            'timeout' => $args['timeout'] ?? 60,
        ],
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return new WP_Error('http_error', 'Error al conectar con la URL');
    }

    $code = 200;
    if (!empty($http_response_header[0]) && preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches)) {
        $code = (int) $matches[1];
    }

    return [
        'body' => $result,
        'response' => [
            'code' => $code,
        ],
    ];
}

function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 500; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }

class Mock_Core {
    public function get_option($key, $default = null) {
        if ($key === 'moodle_url') {
            return trim((string) getenv('PCC_MOODLE_URL'));
        }
        if ($key === 'moodle_token') {
            return trim((string) getenv('PCC_MOODLE_TOKEN'));
        }
        if ($key === 'student_role_id') {
            return 5;
        }
        return $default;
    }
}

class Woo_OTEC_Moodle_Core {
    public static function instance() { return new Mock_Core(); }
}

class Woo_OTEC_Moodle_Logger {
    public static function error($msg, $ctx) {
        echo "[ERROR] $msg " . json_encode($ctx) . "\n";
    }
}

require_once __DIR__ . '/includes/class-moodle-exception.php';
require_once __DIR__ . '/includes/class-api.php';

$api = Woo_OTEC_Moodle_API::instance();

echo "1. Probando conexion (core_webservice_get_site_info)...\n";
$conn = $api->test_connection();
echo $conn ? "[OK] Conexion exitosa!\n" : "[FALLO] No se pudo conectar.\n";
if (!$conn) {
    exit(1);
}

echo "\n2. Obteniendo categorias...\n";
$cats = $api->get_categories();
if (is_wp_error($cats)) {
    echo "[FALLO] Categorias: " . $cats->get_error_message() . "\n";
} else {
    echo "[OK] " . count($cats) . " categorias encontradas.\n";
}

echo "\n3. Probando crear/encontrar alumno...\n";
$test_email = 'test.woo.' . time() . '@example.invalid';
$student_data = [
    'email' => $test_email,
    'firstname' => 'Alumno de Prueba',
    'lastname' => 'WooCommerce',
];
$user_res = $api->get_or_create_user($student_data);

if (is_wp_error($user_res)) {
    echo "[FALLO] No se pudo crear/buscar usuario: " . $user_res->get_error_message() . "\n";
    exit(1);
}

$moodle_user_id = (int) ($user_res['id'] ?? 0);
$is_new = !empty($user_res['created']) ? 'NUEVO' : 'EXISTENTE';
echo "[OK] Usuario {$test_email} es {$is_new}. ID en Moodle: {$moodle_user_id}\n";
if (!empty($user_res['created']) && !empty($user_res['password'])) {
    echo "     -> Password provisoria generada correctamente.\n";
}

echo "\n4. Probando listado de cursos y matriculacion...\n";
$courses = $api->get_courses();
if (!is_wp_error($courses) && is_array($courses) && count($courses) > 1) {
    $course_id = (int) ($courses[1]->id ?? 0);
    if ($course_id > 0) {
        echo "[OK] Probando matricula en Course ID {$course_id}...\n";
        $enrolled = $api->enroll_user($moodle_user_id, $course_id);
        echo $enrolled
            ? "[OK] Alumno matriculado satisfactoriamente en Moodle.\n"
            : "[FALLO] Problema al intentar inscribir al usuario en Moodle.\n";
    } else {
        echo "[NOTICIA] No se detecto un curso valido para prueba.\n";
    }
} else {
    echo "[NOTICIA] No se detectaron cursos disponibles para prueba de matriculacion.\n";
}

echo "\n====== RESUMEN E2E ======\n";
echo "Prueba finalizada usando credenciales desde variables de entorno.\n";

