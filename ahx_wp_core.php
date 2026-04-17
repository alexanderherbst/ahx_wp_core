<?php
/*
Plugin Name: AHX WP Core
Description: Zentraler Core mit wiederverwendbaren Basisfunktionen fuer AHX-Plugins.
Version: v0.1.0
Author: AHX
*/

if (!defined('ABSPATH')) {
    exit;
}

define('AHX_WP_CORE_VERSION', 'v0.1.0');
define('AHX_WP_CORE_FILE', __FILE__);
define('AHX_WP_CORE_DIR', plugin_dir_path(__FILE__));

require_once AHX_WP_CORE_DIR . 'includes/class-ahx-core-plugin-base.php';
require_once AHX_WP_CORE_DIR . 'includes/class-ahx-core-logging.php';
require_once AHX_WP_CORE_DIR . 'includes/functions-notices.php';
require_once AHX_WP_CORE_DIR . 'includes/functions-utils.php';
require_once AHX_WP_CORE_DIR . 'includes/functions-plugin-about-links.php';

function ahx_wp_core_init() {
    // Setzt den Default-Kanal und das globale Log-Level, falls der Logger vorhanden ist.
    if (class_exists('AHX_Logging') && method_exists('AHX_Logging', 'get_instance')) {
        $logger = AHX_Logging::get_instance();
        $logger->set_log_channel('default', get_option('ahx_wp_main_log_channel', 'all'));
        $logger->set_log_level('default', get_option('ahx_wp_main_level_of_logging_overall', 'WARNING'));
        $logger->set_log_level('ahx_wp_core', get_option('ahx_wp_core_level_of_logging', 'WARNING'));
        return;
    }

    if (class_exists('AHX_Core_Logging') && method_exists('AHX_Core_Logging', 'get_instance')) {
        $logger = AHX_Core_Logging::get_instance();
        $logger->set_log_channel('default', get_option('ahx_wp_main_log_channel', 'all'));
        $logger->set_log_level('default', get_option('ahx_wp_main_level_of_logging_overall', 'WARNING'));
        $logger->set_log_level('ahx_wp_core', get_option('ahx_wp_core_level_of_logging', 'WARNING'));
    }
}
add_action('plugins_loaded', 'ahx_wp_core_init', 1);
