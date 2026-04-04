<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_API {
    private static ?Woo_OTEC_Moodle_API $instance = null;

    public static function instance(): Woo_OTEC_Moodle_API {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    public function get_moodle_url(): string {
        $url = (string) Woo_OTEC_Moodle_Core::instance()->get_option('moodle_url', '');
        return rtrim(trim($url), '/');
    }

    public function get_token(): string {
        return trim((string) Woo_OTEC_Moodle_Core::instance()->get_option('moodle_token', ''));
    }

    public function get_student_role_id(): int {
        return max(1, (int) Woo_OTEC_Moodle_Core::instance()->get_option('student_role_id', 5));
    }

    /**
     * Núcleo de peticiones HTTP.
     * Encapsula la comunicación con la API REST de Moodle, lanzando excepciones controladas
     * ante errores de conexión o formato de respuesta.
     */

    /**
     * Realiza la peticion HTTP a Moodle.
     * Lanza PCC_Moodle_Exception ante cualquier error.
     *
     * @throws PCC_Moodle_Exception
     */
    private function request(string $function, array $params = [], array $args = []): mixed {
        $moodle_url = $this->get_moodle_url();
        $token      = $this->get_token();

        if ($moodle_url === '' || $token === '') {
            throw new PCC_Moodle_Exception(
                esc_html__('Moodle URL o token no configurado.', 'woo-otec-moodle'),
                'pcc_missing_config',
                esc_html($function)
            );
        }

        $response = wp_remote_post(
            $moodle_url . '/webservice/rest/server.php',
            wp_parse_args(
                $args,
                [
                    'timeout' => 60,
                    'body'    => array_merge(
                        [
                            'wstoken'            => $token,
                            'wsfunction'         => sanitize_key($function),
                            'moodlewsrestformat' => 'json',
                        ],
                        $params
                    ),
                ]
            )
        );

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();

            if (strpos($error_msg, 'Could not resolve host') !== false) {
                $friendly = __('Network error: The Moodle server could not be resolved via DNS. Please verify the URL.', 'woo-otec-moodle');
            } elseif (strpos($error_msg, 'Timeout was reached') !== false || strpos($error_msg, 'timed out') !== false) {
                $friendly = __('Timeout error: The Moodle server took too long to respond.', 'woo-otec-moodle');
            } else {
                $friendly = $error_msg;
            }

            Woo_OTEC_Moodle_Logger::error('Error HTTP Moodle', ['function' => $function, 'error' => $error_msg]);

            throw new PCC_Moodle_Exception(esc_html($friendly), 'pcc_http_error', esc_html($function), ['original' => esc_html($error_msg)]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            Woo_OTEC_Moodle_Logger::error('Respuesta HTTP inválida Moodle', ['function' => $function, 'status' => $status_code]);
            throw new PCC_Moodle_Exception(
                esc_html(sprintf(
                    /* translators: %d: HTTP response code */
                    __('Invalid HTTP response from Moodle (code %d).', 'woo-otec-moodle'),
                    $status_code
                )),
                'pcc_http_error',
                esc_html($function),
                ['status_code' => esc_html((string) $status_code)]
            );
        }

        $decoded = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Woo_OTEC_Moodle_Logger::error('JSON inválido desde Moodle', ['function' => $function, 'body' => substr($body, 0, 500)]);
            throw new PCC_Moodle_Exception(esc_html__('Invalid JSON response from Moodle.', 'woo-otec-moodle'), 'pcc_invalid_json', esc_html($function));
        }

        if (is_object($decoded) && (isset($decoded->exception) || isset($decoded->errorcode))) {
            $msg = isset($decoded->message) ? (string) $decoded->message : __('Moodle API error', 'woo-otec-moodle');
            Woo_OTEC_Moodle_Logger::error('Excepción Moodle', [
                'function'  => $function,
                'exception' => isset($decoded->exception) ? (string) $decoded->exception : '',
                'errorcode' => isset($decoded->errorcode) ? (string) $decoded->errorcode : '',
                'message'   => $msg,
            ]);

            throw new PCC_Moodle_Exception(
                esc_html($msg),
                'pcc_moodle_exception',
                esc_html($function),
                ['errorcode' => isset($decoded->errorcode) ? esc_html((string) $decoded->errorcode) : '']
            );
        }

        return $decoded;
    }

    /**
     * Wrapper público: captura PCC_Moodle_Exception y devuelve WP_Error.
     * Usar en métodos que exponen la API al resto del plugin.
     */
    public function safe_request(string $function, array $params = [], array $args = []): mixed {
        try {
            return $this->request($function, $params, $args);
        } catch (PCC_Moodle_Exception $e) {
            return $e->to_wp_error();
        }
    }

    /**
     * Métodos públicos de interacción con la API de Moodle.
     */

    public function test_connection(): bool {
        try {
            $this->request('core_webservice_get_site_info');
            return true;
        } catch (PCC_Moodle_Exception $e) {
            return false;
        }
    }

    public function get_categories(): array|WP_Error {
        $response = $this->safe_request('core_course_get_categories');
        if (is_wp_error($response)) {
            return $response;
        }
        return is_array($response) ? $response : [];
    }

    public function get_courses(): array|WP_Error {
        $response = $this->safe_request('core_course_get_courses');
        if (is_wp_error($response)) {
            return $response;
        }
        return is_array($response) ? $response : [];
    }

    public function get_courses_by_category(int $category_id): array|WP_Error {
        $response = $this->safe_request('core_course_get_courses_by_field', [
            'field' => 'category',
            'value' => (string) $category_id,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return is_object($response) && isset($response->courses) ? (array) $response->courses : [];
    }

    public function get_course_teachers(int $course_id): array {
        try {
            $response = $this->request('core_enrol_get_enrolled_users', ['courseid' => $course_id]);
        } catch (PCC_Moodle_Exception $e) {
            if ($e->get_error_code() === 'pcc_moodle_exception') {
                Woo_OTEC_Moodle_Logger::error('Permisos insuficientes para obtener profesores. Verifica que core_enrol_get_enrolled_users esté habilitada en Moodle.', ['course_id' => $course_id]);
            }
            return [];
        }

        if (!is_array($response)) {
            return [];
        }

        $teachers = [];
        foreach ($response as $user) {
            if (!is_object($user) || empty($user->roles) || !is_array($user->roles)) {
                continue;
            }

            foreach ($user->roles as $role) {
                $shortname = is_object($role) && !empty($role->shortname) ? strtolower((string) $role->shortname) : '';
                if (in_array($shortname, ['teacher', 'editingteacher'], true)) {
                    $teachers[] = !empty($user->fullname) ? (string) $user->fullname : '';
                    break;
                }
            }
        }

        return array_values(array_filter(array_unique($teachers)));
    }

    public function find_user_by_email(string $email): int {
        try {
            $response = $this->request('core_user_get_users', [
                'criteria' => [['key' => 'email', 'value' => sanitize_email($email)]],
            ]);
        } catch (PCC_Moodle_Exception $e) {
            return 0;
        }

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
            return new WP_Error('pcc_invalid_user_payload', __('Insufficient data to create Moodle user.', 'woo-otec-moodle'));
        }

        $password = wp_generate_password(14, true, true);

        try {
            $response = $this->request('core_user_create_users', [
                'users' => [[
                    'username'  => (string) $payload['email'],
                    'password'  => $password,
                    'firstname' => (string) $payload['firstname'],
                    'lastname'  => (string) $payload['lastname'],
                    'email'     => (string) $payload['email'],
                ]],
            ]);
        } catch (PCC_Moodle_Exception $e) {
            return $e->to_wp_error();
        }

        if (!is_array($response) || empty($response[0]->id)) {
            return new WP_Error('pcc_moodle_user_create_failed', __('It was not possible to create the user in Moodle.', 'woo-otec-moodle'));
        }

        return [
            'id'       => (int) $response[0]->id,
            'password' => $password,
        ];
    }

    public function get_or_create_user(array $data): array|WP_Error {
        $email = sanitize_email($data['email'] ?? '');
        if ($email === '') {
            return new WP_Error('pcc_invalid_email', __('Invalid email.', 'woo-otec-moodle'));
        }

        // Paso 1: Intentar ubicar al alumno mediante su correo electrónico vigente.
        $existing = $this->safe_request('core_user_get_users_by_field', [
            'field'  => 'email',
            'values' => [$email],
        ]);

        if (!is_wp_error($existing) && !empty($existing) && is_array($existing)) {
            $user = is_object($existing[0]) ? $existing[0] : (object) $existing[0];
            return ['id' => (int) $user->id, 'created' => false];
        }

        // Paso 2: Proceder a la creación de un nuevo registro en Moodle si el alumno no cuenta con perfil activo.
        $password  = wp_generate_password(12, true, true);
        $password .= 'Aa1!';

        $new_user = [
            'username'  => strtolower(preg_replace('/[^a-z0-9]/', '', strstr($email, '@', true)) . wp_rand(10, 99)),
            'password'  => $password,
            'firstname' => !empty($data['firstname']) ? (string) $data['firstname'] : __('Student', 'woo-otec-moodle'),
            'lastname'  => !empty($data['lastname']) ? (string) $data['lastname'] : __('New', 'woo-otec-moodle'),
            'email'     => $email,
            'auth'      => 'manual',
        ];

        try {
            $created = $this->request('core_user_create_users', ['users' => [$new_user]]);
        } catch (PCC_Moodle_Exception $e) {
            return $e->to_wp_error();
        }

        if (!empty($created) && is_array($created)) {
            $user = is_object($created[0]) ? $created[0] : (object) $created[0];
            return ['id' => (int) $user->id, 'password' => $password, 'created' => true];
        }

        return new WP_Error('pcc_create_failed', __('Could not create the user in Moodle.', 'woo-otec-moodle'));
    }

    public function enroll_user(int $moodle_user_id, int $course_id): bool {
        try {
            $this->request('enrol_manual_enrol_users', [
                'enrolments' => [[
                    'roleid'   => $this->get_student_role_id(),
                    'userid'   => $moodle_user_id,
                    'courseid' => $course_id,
                ]],
            ]);
            return true;
        } catch (PCC_Moodle_Exception $e) {
            return false;
        }
    }

    private function normalize_user_payload($user): array|false {
        if ($user instanceof WP_User) {
            $email = sanitize_email((string) $user->user_email);
            if ($email === '') {
                return false;
            }
            return [
                'email'     => $email,
                'firstname' => (string) ($user->first_name !== '' ? $user->first_name : $user->display_name),
                'lastname'  => (string) ($user->last_name !== '' ? $user->last_name : __('Student', 'woo-otec-moodle')),
            ];
        }

        if (is_array($user)) {
            $email = sanitize_email((string) ($user['email'] ?? ''));
            if ($email === '') {
                return false;
            }
            $firstname = sanitize_text_field((string) ($user['firstname'] ?? ''));
            $lastname  = sanitize_text_field((string) ($user['lastname'] ?? ''));
            return [
                'email'     => $email,
                'firstname' => $firstname !== '' ? $firstname : __('Student', 'woo-otec-moodle'),
                'lastname'  => $lastname !== '' ? $lastname : __('Student', 'woo-otec-moodle'),
            ];
        }

        return false;
    }
}

