<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AHX_Core_Logging')) {
    class AHX_Core_Logging {

        private static $instance = null;

        private $log_channels_selected = array();
        private $log_levels_selected = array();

        private $log_levels = array(
            'DEBUG' => 100,
            'INFO' => 200,
            'NOTICE' => 250,
            'WARNING' => 300,
            'ERROR' => 400,
            'CRITICAL' => 500,
            'ALERT' => 550,
            'EMERGENCY' => 600,
        );

        private function __construct() {
            $this->log_channels_selected['default'] = array('file' => true, 'db' => false);
            $this->log_levels_selected['default'] = $this->log_levels['WARNING'];
        }

        public static function get_instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function set_log_channel($log_source, $log_channel) {
            $source = (string) $log_source;
            $channel = strtolower((string) $log_channel);

            if ($channel === 'none') {
                $this->log_channels_selected[$source] = array('file' => false, 'db' => false);
                return;
            }

            if ($channel === 'db') {
                $this->log_channels_selected[$source] = array('file' => false, 'db' => true);
                return;
            }

            if ($channel === 'all') {
                $this->log_channels_selected[$source] = array('file' => true, 'db' => true);
                return;
            }

            $this->log_channels_selected[$source] = array('file' => true, 'db' => false);
        }

        public function set_log_level($log_source, $log_level) {
            $source = (string) $log_source;
            $level = strtoupper((string) $log_level);
            if (!isset($this->log_levels[$level])) {
                $level = 'WARNING';
            }
            $this->log_levels_selected[$source] = $this->log_levels[$level];
        }

        public function log($log_message, $log_level = 'DEBUG', $log_source = '') {
            $source = (string) $log_source;
            $level = strtoupper((string) $log_level);

            if (!isset($this->log_levels[$level])) {
                $level = 'DEBUG';
            }

            $active_level = isset($this->log_levels_selected[$source])
                ? $this->log_levels_selected[$source]
                : $this->log_levels_selected['default'];

            if ($this->log_levels[$level] < $active_level) {
                return;
            }

            $channel = isset($this->log_channels_selected[$source])
                ? $this->log_channels_selected[$source]
                : $this->log_channels_selected['default'];

            if (empty($channel['file']) && empty($channel['db'])) {
                return;
            }

            $prefix = $source !== '' ? '[' . $source . ']' : '[ahx_wp_core]';
            error_log($prefix . '[' . $level . '] ' . (string) $log_message);
        }

        public function log_debug($message, $source = '') {
            $this->log($message, 'DEBUG', $source);
        }

        public function log_info($message, $source = '') {
            $this->log($message, 'INFO', $source);
        }

        public function log_notice($message, $source = '') {
            $this->log($message, 'NOTICE', $source);
        }

        public function log_warning($message, $source = '') {
            $this->log($message, 'WARNING', $source);
        }

        public function log_error($message, $source = '') {
            $this->log($message, 'ERROR', $source);
        }

        public function log_critical($message, $source = '') {
            $this->log($message, 'CRITICAL', $source);
        }

        public function log_alert($message, $source = '') {
            $this->log($message, 'ALERT', $source);
        }

        public function log_emergency($message, $source = '') {
            $this->log($message, 'EMERGENCY', $source);
        }
    }
}

if (!function_exists('ahx_wp_core_log')) {
    function ahx_wp_core_log($level, $message, $source = 'ahx_wp_core', array $context = array()) {
        if (class_exists('AHX_Logging') && method_exists('AHX_Logging', 'get_instance')) {
            $logger = AHX_Logging::get_instance();
            $method = 'log_' . strtolower((string) $level);
            if (method_exists($logger, $method)) {
                $logger->{$method}((string) $message, (string) $source, $context);
                return;
            }
            if (method_exists($logger, 'log_with_context')) {
                $logger->log_with_context((string) $message, strtoupper((string) $level), (string) $source, $context);
                return;
            }
            if (method_exists($logger, 'log')) {
                $logger->log((string) $message, strtoupper((string) $level), (string) $source, $context);
                return;
            }
        }

        if (class_exists('AHX_Core_Logging') && method_exists('AHX_Core_Logging', 'get_instance')) {
            $logger = AHX_Core_Logging::get_instance();
            $method = 'log_' . strtolower((string) $level);
            if (method_exists($logger, $method)) {
                $logger->{$method}((string) $message, (string) $source);
                return;
            }
            if (method_exists($logger, 'log')) {
                $logger->log((string) $message, strtoupper((string) $level), (string) $source);
                return;
            }
        }

        $context_json = !empty($context)
            ? (function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context))
            : '';

        error_log('[' . (string) $source . '][' . strtoupper((string) $level) . '] ' . (string) $message . ($context_json !== '' ? ' ' . $context_json : ''));
    }
}
