<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Excepción dedicada para errores de la API de Moodle.
 * Permite capturar errores específicos de Moodle con try/catch
 * en lugar de condicionales is_wp_error() dispersos.
 */
class PCC_Moodle_Exception extends RuntimeException {

    private string $error_code;
    private string $moodle_function;
    private array  $context;

    public function __construct(
        string    $message,
        string    $error_code      = '',
        string    $moodle_function = '',
        array     $context         = [],
        int       $code            = 0,
        ?\Throwable $previous       = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->error_code      = $error_code;
        $this->moodle_function = $moodle_function;
        $this->context         = $context;
    }

    public function get_error_code(): string {
        return $this->error_code;
    }

    public function get_moodle_function(): string {
        return $this->moodle_function;
    }

    public function get_context(): array {
        return $this->context;
    }

    /**
     * Convierte la excepción a WP_Error para compatibilidad con WordPress.
     */
    public function to_wp_error(): \WP_Error {
        return new \WP_Error(
            $this->error_code ?: 'pcc_moodle_error',
            $this->getMessage(),
            $this->context
        );
    }
}
