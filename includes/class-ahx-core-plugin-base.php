<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AHX_Core_Plugin_Base')) {
    abstract class AHX_Core_Plugin_Base {

        protected $plugin_file;
        protected $plugin_slug;
        protected $log_source;

        public function __construct(array $config = array()) {
            $this->plugin_file = isset($config['plugin_file']) ? (string) $config['plugin_file'] : '';
            $this->plugin_slug = isset($config['plugin_slug']) ? (string) $config['plugin_slug'] : '';
            $this->log_source = isset($config['log_source']) ? (string) $config['log_source'] : $this->plugin_slug;
        }

        public static function boot(array $config = array()) {
            $instance = new static($config);
            $instance->register_hooks();
            return $instance;
        }

        abstract protected function register_hooks();

        protected function add_action($hook, $method, $priority = 10, $accepted_args = 1) {
            add_action($hook, array($this, $method), $priority, $accepted_args);
        }

        protected function add_filter($hook, $method, $priority = 10, $accepted_args = 1) {
            add_filter($hook, array($this, $method), $priority, $accepted_args);
        }

        protected function log($level, $message) {
            if (function_exists('ahx_wp_core_log')) {
                ahx_wp_core_log((string) $level, (string) $message, $this->log_source);
            }
        }
    }
}
