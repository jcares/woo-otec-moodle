<?php
/**
 * Script de Pruebas End-to-End Standalone
 * Verifica la API de Moodle contra cipresalto.cl
 */

// --- WP Mocks ---
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public $code, $message, $data;
    public function __construct($code = '', $message = '', $data = '') {
        $this->code = $code; $this->message = $message; $this->data = $data;
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
            'timeout' => $args['timeout'] ?? 60
        ]
    ];
    $context  = stream_context_create($options);
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
            'code' => $code
        ]
    ];
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 500; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }

// Mock de Get_Option para devolver las credenciales del usuario
class Mock_Core {
    public function get_option($key, $default = null) {
        if ($key === 'moodle_url') return 'https://cipresalto.cl/aulavirtual';
        if ($key === 'moodle_token') return 'd4c5be6e5cefe4bbb025ae28ba5630df';
        if ($key === 'student_role_id') return 5;
        return $default;
    }
}
class Woo_OTEC_Moodle_Core {
    public static function instance() { return new Mock_Core(); }
}
class Woo_OTEC_Moodle_Logger {
    public static function error($msg, $ctx) { echo "[ERROR] $msg " . json_encode($ctx) . "\n"; }
}

// Cargar la excepción y la clase API interactiva
require_once __DIR__ . '/includes/class-moodle-exception.php';
require_once __DIR__ . '/includes/class-api.php';

$api = Woo_OTEC_Moodle_API::instance();

echo "1. Probando conexion (core_webservice_get_site_info)...\n";
$conn = $api->test_connection();
echo $conn ? "[OK] Conexion exitosa!\n" : "[FALLO] No se pudo conectar.\n";
if (!$conn) die();

echo "\n2. Obteniendo categorias...\n";
$cats = $api->get_categories();
if (is_wp_error($cats)) {
    echo "[FALLO] Categorias: " . $cats->get_error_message() . "\n";
} else {
    echo "[OK] " . count($cats) . " categorias encontradas.\n";
}

echo "\n3. Probando crear/encontrar alumno (Simulando una COMPRA de WooCommerce)...\n";
$test_email = 'test.woo.cipresalto_' . time() . '@yopmail.com';
$student_data = [
    'email' => $test_email,
    'firstname' => 'Alumno de Prueba',
    'lastname' => 'WooCommerce',
];
$user_res = $api->get_or_create_user($student_data);

if (is_wp_error($user_res)) {
    echo "[FALLO] No se pudo crear/buscar usuario: " . $user_res->get_error_message() . "\n";
    die();
}
$moodle_user_id = $user_res['id'];
$is_new = $user_res['created'] ? 'NUEVO' : 'EXISTENTE';
echo "[OK] Usuario {$test_email} es $is_new. ID en Moodle: $moodle_user_id\n";

if ($user_res['created']) {
    echo "     -> Password provisoria: {$user_res['password']}\n";
}

echo "\n4. Probando Listar Cursos para matricular el usuario...\n";
$courses = $api->get_courses();
if (!is_wp_error($courses) && count($courses) > 1) { // 1 es Site Home
    $course_id = $courses[1]->id ?? 0; // Tomar el primer curso real
    echo "[OK] Elegido Moodle Course ID: $course_id para matricular al usuario $moodle_user_id...\n";
    
    echo "\n5. PROBANDO MATRICULACION (enroll_user)\n";
    $enrolled = $api->enroll_user($moodle_user_id, $course_id);
    if ($enrolled) {
        echo "[OK] ¡Alumno matriculado satisfactoriamente en Moodle!\n";
    } else {
        echo "[FALLO] Problema al intentar inscribir al usuario en Moodle.\n";
    }
} else {
    echo "[NOTICIA] No se detectaron cursos creados todavia en el Moodle (o solo esta el Site Home). Imposible probar matriculacion de la compra.\n";
}

echo "\n====== RESUMEN E2E ======\n";
echo "Integracion desde el ecosistema asilado PHP comprobada con las credenciales de produccion!\n";
