<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ahx_wp_core_add_notice')) {
    function ahx_wp_core_add_notice($message, $type = 'success') {
        $allowed_types = array('success', 'error', 'info', 'warning');
        if (!in_array($type, $allowed_types, true)) {
            $type = 'info';
        }

        $notices = get_transient('ahx_wp_core_admin_notices');
        if (!is_array($notices)) {
            $notices = array();
        }

        $notices[] = array(
            'message' => sanitize_text_field((string) $message),
            'type' => $type,
        );

        set_transient('ahx_wp_core_admin_notices', $notices, 60);
    }
}

if (!function_exists('ahx_wp_core_print_notice_now')) {
    function ahx_wp_core_print_notice_now($message, $type = 'info') {
        $allowed_types = array('success', 'error', 'info', 'warning');
        if (!in_array($type, $allowed_types, true)) {
            $type = 'info';
        }

        echo "<div class='notice notice-" . esc_attr($type) . " is-dismissible'><p>" . esc_html((string) $message) . "</p></div>";
    }
}

if (!function_exists('ahx_wp_core_display_admin_notices')) {
    function ahx_wp_core_display_admin_notices() {
        $notices = get_transient('ahx_wp_core_admin_notices');
        if (!is_array($notices) || empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            $type = isset($notice['type']) ? (string) $notice['type'] : 'info';
            $message = isset($notice['message']) ? (string) $notice['message'] : '';
            if ($message === '') {
                continue;
            }

            ahx_wp_core_print_notice_now($message, $type);
        }

        delete_transient('ahx_wp_core_admin_notices');
    }

    add_action('admin_notices', 'ahx_wp_core_display_admin_notices');
}

// Rueckwaertskompatible Alias-Funktionen fuer vorhandene Plugin-Aufrufe.
if (!function_exists('ahx_wp_main_add_notice')) {
    function ahx_wp_main_add_notice($message, $type = 'success') {
        ahx_wp_core_add_notice($message, $type);
    }
}

if (!function_exists('ahx_wp_main_print_notice_now')) {
    function ahx_wp_main_print_notice_now($message, $type = 'info') {
        ahx_wp_core_print_notice_now($message, $type);
    }
}
