<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_API {
    private static ?PCC_WooOTEC_Pro_API $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_API {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function get_moodle_url(): string {
        $url = (string) PCC_WooOTEC_Pro_Core::instance()->get_option('moodle_url', '');
        return rtrim(trim($url), '/');
    }

    public function get_token(): string {
        return trim((string) PCC_WooOTEC_Pro_Core::instance()->get_option('moodle_token', ''));
    }

    public function get_student_role_id(): int {
        return max(1, (int) PCC_WooOTEC_Pro_Core::instance()->get_option('student_role_id', 5));
    }

    public function request(string $function, array $params = array(), array $args = array()): mixed {
        $moodle_url = $this->get_moodle_url();
        $token = $this->get_token();

        if ($moodle_url === '' || $token === '') {
            return new WP_Error('pcc_missing_config', 'Moodle URL o token no configurado.');
        }

        $response = wp_remote_post(
            $moodle_url . '/webservice/rest/server.php',
            wp_parse_args(
                $args,
                array(
                    'timeout' => 60,
                    'body'    => array_merge(
                        array(
                            'wstoken'            => $token,
                            'wsfunction'         => sanitize_key($function),
                            'moodlewsrestformat' => 'json',
                        ),
                        $params
                    ),
                )
            )
        );

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if (strpos($error_msg, 'Could not resolve host') !== false) {
                $error_msg = 'Error de Red: No se puede encontrar el servidor de Moodle (DNS). Revisa que la URL sea correcta.';
            } elseif (strpos($error_msg, 'Timeout was reached') !== false || strpos($error_msg, 'timed out') !== false) {
                $error_msg = 'Error de Tiempo de Espera: El servidor de Moodle tardó demasiado en responder. Intenta nuevamente o contacta a tu proveedor de hosting para revisar la conexión saliente.';
            }
            PCC_WooOTEC_Pro_Logger::error('Error HTTP Moodle', array('function' => $function, 'error' => $response->get_error_message()));
            return new WP_Error('pcc_http_error', $error_msg);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            PCC_WooOTEC_Pro_Logger::error('Respuesta HTTP invalida Moodle', array('function' => $function, 'status' => $status_code, 'body' => substr($body, 0, 500)));
            return new WP_Error('pcc_http_error', 'Respuesta HTTP invalida desde Moodle.');
        }

        $decoded = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            PCC_WooOTEC_Pro_Logger::error('JSON invalido desde Moodle', array('function' => $function, 'body' => substr($body, 0, 500)));
            return new WP_Error('pcc_invalid_json', 'JSON invalido desde Moodle.');
        }

        if (is_object($decoded) && (isset($decoded->exception) || isset($decoded->errorcode))) {
            PCC_WooOTEC_Pro_Logger::error('Excepcion Moodle', array(
                'function'  => $function,
                'exception' => isset($decoded->exception) ? (string) $decoded->exception : '',
                'errorcode' => isset($decoded->errorcode) ? (string) $decoded->errorcode : '',
                'message'   => isset($decoded->message) ? (string) $decoded->message : '',
            ));

            return new WP_Error('pcc_moodle_exception', isset($decoded->message) ? (string) $decoded->message : 'Error Moodle API');
        }

        return $decoded;
    }

    public function test_connection(): bool {
        $response = $this->request('core_webservice_get_site_info');
        return !is_wp_error($response);
    }

    public function get_categories(): array|WP_Error {
        $response = $this->request('core_course_get_categories');
        if (is_wp_error($response)) {
            return $response;
        }
        return is_array($response) ? $response : array();
    }

    public function get_courses(): array|WP_Error {
        $response = $this->request('core_course_get_courses');
        if (is_wp_error($response)) {
            return $response;
        }
        return is_array($response) ? $response : array();
    }

    public function get_courses_by_category(int $category_id): array|WP_Error {
        $response = $this->request('core_course_get_courses_by_field', array(
            'field' => 'category',
            'value' => (string) $category_id,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return is_object($response) && isset($response->courses) ? (array) $response->courses : array();
    }

    public function get_course_teachers(int $course_id): array {
        $response = $this->request('core_enrol_get_enrolled_users', array('courseid' => $course_id));
        if (is_wp_error($response)) {
            // Si hay error de permisos, logueamos pero no detenemos el proceso
            if ($response->get_error_code() === 'pcc_moodle_exception') {
                PCC_WooOTEC_Pro_Logger::error('Permisos insuficientes para obtener profesores. Asegúrate de que la función core_enrol_get_enrolled_users esté habilitada en Moodle.', array('course_id' => $course_id));
            }
            return array();
        }

        if (!is_array($response)) {
            return array();
        }

        $teachers = array();
        foreach ($response as $user) {
            if (!is_object($user) || empty($user->roles) || !is_array($user->roles)) {
                continue;
            }

            foreach ($user->roles as $role) {
                $shortname = is_object($role) && !empty($role->shortname) ? strtolower((string) $role->shortname) : '';
                if (in_array($shortname, array('teacher', 'editingteacher'), true)) {
                    $teachers[] = !empty($user->fullname) ? (string) $user->fullname : '';
                    break;
                }
            }
        }

        return array_values(array_filter(array_unique($teachers)));
    }

    public function find_user_by_email(string $email): int {
        $response = $this->request(
            'core_user_get_users',
            array(
                'criteria' => array(
                    array(
                        'key'   => 'email',
                        'value' => sanitize_email($email),
                    ),
                ),
            )
        );

        if (!is_object($response) || empty($response->users) || !is_array($response->users)) {
            return 0;
        }

        foreach ($response->users as $user) {
            if (is_object($user) && !empty($user->id)) {
                return (int) $user->id;
            }
        }

        return 0;
    }

    public function create_user($user): array|WP_Error {
        $payload = $this->normalize_user_payload($user);
        if (!$payload) {
            return new WP_Error('pcc_invalid_user_payload', 'Datos insuficientes para crear usuario Moodle.');
        }

        $password = wp_generate_password(14, true, true);
        $response = $this->request(
            'core_user_create_users',
            array(
                'users' => array(
                    array(
                        'username'  => (string) $payload['email'],
                        'password'  => $password,
                        'firstname' => (string) $payload['firstname'],
                        'lastname'  => (string) $payload['lastname'],
                        'email'     => (string) $payload['email'],
                    ),
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        if (!is_array($response) || empty($response[0]->id)) {
            return new WP_Error('pcc_moodle_user_create_failed', 'No fue posible crear el usuario en Moodle.');
        }

        return array(
            'id'       => (int) $response[0]->id,
            'password' => $password,
        );
    }

    public function get_or_create_user(array $data): array|WP_Error {
        $email = sanitize_email($data['email'] ?? '');
        if ($email === '') {
            return new WP_Error('pcc_invalid_email', 'Email invalido.');
        }

        // 1. Buscar por email
        $existing = $this->request('core_user_get_users_by_field', array(
            'field'  => 'email',
            'values' => array($email),
        ));

        if (!is_wp_error($existing) && !empty($existing) && is_array($existing)) {
            $user = is_object($existing[0]) ? $existing[0] : (object)$existing[0];
            return array(
                'id'      => (int) $user->id,
                'created' => false,
            );
        }

        // 2. Crear si no existe
        $password = wp_generate_password(12, true, true);
        // Moodle requiere al menos una mayuscula, una minuscula, un numero y un especial
        $password .= 'Aa1!'; 

        $new_user = array(
            'username'  => strtolower(preg_replace('/[^a-z0-9]/', '', strstr($email, '@', true)) . mt_rand(10, 99)),
            'password'  => $password,
            'firstname' => $data['firstname'] ?: 'Alumno',
            'lastname'  => $data['lastname'] ?: 'Nuevo',
            'email'     => $email,
            'auth'      => 'manual',
        );

        $created = $this->request('core_user_create_users', array(
            'users' => array($new_user),
        ));

        if (is_wp_error($created)) {
            return $created;
        }

        if (!empty($created) && is_array($created)) {
            $user = is_object($created[0]) ? $created[0] : (object)$created[0];
            return array(
                'id'       => (int) $user->id,
                'password' => $password,
                'created'  => true,
            );
        }

        return new WP_Error('pcc_create_failed', 'No se pudo crear el usuario en Moodle.');
    }

    public function enroll_user(int $moodle_user_id, int $course_id): bool {
        $response = $this->request(
            'enrol_manual_enrol_users',
            array(
                'enrolments' => array(
                    array(
                        'roleid'   => $this->get_student_role_id(),
                        'userid'   => $moodle_user_id,
                        'courseid' => $course_id,
                    ),
                ),
            )
        );

        return !is_wp_error($response);
    }

    private function normalize_user_payload($user): array|false {
        if ($user instanceof WP_User) {
            $email = sanitize_email((string) $user->user_email);
            if ($email === '') {
                return false;
            }

            return array(
                'email'     => $email,
                'firstname' => (string) ($user->first_name !== '' ? $user->first_name : $user->display_name),
                'lastname'  => (string) ($user->last_name !== '' ? $user->last_name : 'Alumno'),
            );
        }

        if (is_array($user)) {
            $email = sanitize_email((string) ($user['email'] ?? ''));
            if ($email === '') {
                return false;
            }

            $firstname = sanitize_text_field((string) ($user['firstname'] ?? ''));
            $lastname = sanitize_text_field((string) ($user['lastname'] ?? ''));

            return array(
                'email'     => $email,
                'firstname' => $firstname !== '' ? $firstname : 'Alumno',
                'lastname'  => $lastname !== '' ? $lastname : 'Alumno',
            );
        }

        return false;
    }
}
