<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PCC WooOTEC Pro Mapper
 * Handles flexible field mapping between Moodle and WooCommerce
 */
final class PCC_WooOTEC_Pro_Mapper {
    private static ?PCC_WooOTEC_Pro_Mapper $instance = null;

    public static function instance(): PCC_WooOTEC_Pro_Mapper {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get default field mappings
     */
    public function get_default_mappings(): array {
        return array(
            'fullname'  => array('target' => 'post_title', 'label' => 'Nombre del curso', 'enabled' => 'yes'),
            'summary'   => array('target' => 'post_content', 'label' => 'Descripción / Temario', 'enabled' => 'yes'),
            'startdate' => array('target' => '_start_date', 'label' => 'Fecha de Inicio', 'enabled' => 'yes'),
            'enddate'   => array('target' => '_end_date', 'label' => 'Fecha de Término', 'enabled' => 'yes'),
            'teacher'   => array('target' => '_instructor', 'label' => 'Relator / Docente', 'enabled' => 'yes'),
            'duration'  => array('target' => '_duration', 'label' => 'Duración Cronológica', 'enabled' => 'yes'),
            'modality'  => array('target' => '_modality', 'label' => 'Modalidad de Estudio', 'enabled' => 'yes'),
        );
    }

    /**
     * Map a Moodle course to WooCommerce data
     */
    public function map_course_data(array $course_data): array {
        $mappings = $this->get_mappings();
        $mapped = array();

        foreach ($mappings as $moodle_key => $config) {
            if (isset($course_data[$moodle_key]) && ($config['enabled'] ?? 'yes') === 'yes') {
                $mapped[$config['target']] = $course_data[$moodle_key];
            }
        }

        return apply_filters('pcc_woootec_mapped_course_data', $mapped, $course_data);
    }

    /**
     * Get current mappings from options or defaults
     */
    public function get_mappings(): array {
        $saved = get_option('pcc_woootec_pro_mappings', array());
        return !empty($saved) ? $saved : $this->get_default_mappings();
    }

    /**
     * Save mappings
     */
    public function save_mappings(array $mappings): void {
        update_option('pcc_woootec_pro_mappings', $mappings);
    }
}
