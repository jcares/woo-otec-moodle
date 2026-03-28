<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Cron {
    public const HOOK_SYNC = 'woo_otec_moodle_hourly_sync';

    public static function boot(): void {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::HOOK_SYNC, array(__CLASS__, 'run_sync'));
        self::ensure_scheduled();
    }

    public static function install(): void {
        self::boot();
    }

    public static function register_schedule(array $schedules): array {
        if (!isset($schedules['woo_otec_moodle_hourly'])) {
            $schedules['woo_otec_moodle_hourly'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => 'PCC WooOTEC Chile PRO - Cada hora',
            );
        }

        return $schedules;
    }

    public static function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::HOOK_SYNC)) {
            wp_schedule_event(time() + 300, 'woo_otec_moodle_hourly', self::HOOK_SYNC);
        }
    }

    public static function unschedule(): void {
        $timestamp = wp_next_scheduled(self::HOOK_SYNC);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_SYNC);
            $timestamp = wp_next_scheduled(self::HOOK_SYNC);
        }
    }

    public static function run_sync(): void {
        Woo_OTEC_Moodle_Sync::instance()->run(false);
    }
}
