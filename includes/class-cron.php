<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Cron {
    public const HOOK_SYNC = 'pcc_woootec_pro_hourly_sync';

    public static function boot(): void {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        add_action(self::HOOK_SYNC, array(__CLASS__, 'run_sync'));
        self::ensure_scheduled();
    }

    public static function install(): void {
        self::boot();
    }

    public static function register_schedule(array $schedules): array {
        if (!isset($schedules['pcc_woootec_hourly'])) {
            $schedules['pcc_woootec_hourly'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => 'PCC WooOTEC Chile PRO - Cada hora',
            );
        }

        return $schedules;
    }

    public static function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::HOOK_SYNC)) {
            wp_schedule_event(time() + 300, 'pcc_woootec_hourly', self::HOOK_SYNC);
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
        PCC_WooOTEC_Pro_Sync::instance()->run(false);
    }
}
