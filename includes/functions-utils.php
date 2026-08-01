<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ahx_wp_core_normalize_path')) {
    function ahx_wp_core_normalize_path(string $path): string {
        if (function_exists('wp_normalize_path')) {
            return wp_normalize_path($path);
        }

        $path = str_replace('\\', '/', (string) $path);
        if ($path === '') {
            return '';
        }

        $prefix = '';
        if (preg_match('#^[A-Za-z]:/?#', $path, $matches)) {
            $prefix = strtoupper(substr($matches[0], 0, 2));
            $path = substr($path, strlen($matches[0]));
        } elseif (strpos($path, '//') === 0) {
            $prefix = '//';
            $path = ltrim(substr($path, 2), '/');
        } elseif (strpos($path, '/') === 0) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $parts = array_values(array_filter(explode('/', $path), function ($part) {
            return $part !== '' && $part !== '.';
        }));
        $normalized = implode('/', $parts);

        if ($prefix === '') {
            return $normalized;
        }
        if ($prefix === '//') {
            return $normalized === '' ? '//' : '//' . $normalized;
        }
        if ($prefix === '/') {
            return $normalized === '' ? '/' : '/' . $normalized;
        }

        return $normalized === '' ? ($prefix . '/') : ($prefix . '/' . $normalized);
    }
}

if (!function_exists('ahx_wp_core_return_bytes_formatted')) {
    function ahx_wp_core_return_bytes_formatted($bytes): string {
        $bytes = (float) $bytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        return round($bytes / 1099511627776, 2) . ' TB';
    }
}

if (!function_exists('ahx_wp_core_delete_file')) {
    function ahx_wp_core_delete_file(string $filepath, string $log_source = 'ahx_wp_core', string $trashcan_option = 'ahx_wp_main_trashcan'): bool {
        $filepath = realpath($filepath);
        if ($filepath === false || !is_file($filepath)) {
            ahx_wp_core_log('debug', "Datei '$filepath' existiert nicht oder ist ungueltig.", $log_source);
            return false;
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            ahx_wp_core_log('debug', 'Windows erkannt (' . PHP_OS_FAMILY . ')', $log_source);
            $trashcan_mode = get_option($trashcan_option, 'none');

            if ($trashcan_mode === 'native') {
                $powershell_path = trim((string) shell_exec('where powershell 2>NUL'));
                if ($powershell_path !== '') {
                    $escaped_path = str_replace("'", "''", $filepath);
                    $command = 'powershell -NoProfile -NonInteractive -Command "'
                        . "Add-Type -AssemblyName Microsoft.VisualBasic; "
                        . "[Microsoft.VisualBasic.FileIO.FileSystem]::DeleteFile('"
                        . $escaped_path
                        . "', 'OnlyErrorDialogs', 'SendToRecycleBin')\"";

                    exec($command, $output, $result_code);
                    if ($result_code === 0) {
                        ahx_wp_core_log('debug', "Datei '$filepath' erfolgreich in den Papierkorb verschoben.", $log_source);
                        return true;
                    }

                    ahx_wp_core_log('error', "PowerShell konnte '$filepath' nicht in den Papierkorb verschieben. Exit-Code: $result_code", $log_source);
                }
            }

            if ($trashcan_mode === 'workaround') {
                $trashcan_dir = 'C:/Temp/wp_trashcan';
                if (!is_dir($trashcan_dir) && !mkdir($trashcan_dir, 0777, true) && !is_dir($trashcan_dir)) {
                    ahx_wp_core_log('error', "Konnte das Verzeichnis '$trashcan_dir' nicht erstellen.", $log_source);
                } else {
                    $target = $trashcan_dir . '/' . basename($filepath);
                    if (@rename($filepath, $target)) {
                        ahx_wp_core_log('debug', "Datei '$filepath' erfolgreich nach '$target' verschoben.", $log_source);
                        return true;
                    }
                    ahx_wp_core_log('error', "Konnte Datei '$filepath' nicht nach '$target' verschieben.", $log_source);
                }
            }
        }

        ahx_wp_core_log('debug', "Versuche Datei '$filepath' direkt zu loeschen.", $log_source);
        if (@unlink($filepath)) {
            ahx_wp_core_log('debug', "Datei '$filepath' erfolgreich geloescht.", $log_source);
            return true;
        }

        ahx_wp_core_log('error', "Loeschen von '$filepath' fehlgeschlagen.", $log_source);
        return false;
    }
}

if (!function_exists('ahx_wp_core_debug_log_has_content')) {
    function ahx_wp_core_debug_log_has_content(): bool {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        return (file_exists($log_file) && filesize($log_file) > 0);
    }
}

if (!function_exists('ahx_wp_core_zip_directory')) {
    function ahx_wp_core_zip_directory($source, $destination, $exclude = array()): bool {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source = realpath((string) $source);
        if ($source === false) {
            return false;
        }

        $base_length = strlen($source) + 1;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file_path = realpath((string) $file);
            if ($file_path === false) {
                continue;
            }

            $relative_path = substr($file_path, $base_length);

            foreach ($exclude as $excluded_item) {
                if (strpos((string) $relative_path, (string) $excluded_item) === 0) {
                    continue 2;
                }
            }

            if (preg_match('/^file_backup.*\.zip$/', basename((string) $relative_path))) {
                continue;
            }

            if (is_dir($file_path)) {
                $zip->addEmptyDir((string) $relative_path);
            } else {
                $zip->addFile($file_path, (string) $relative_path);
            }
        }

        return (bool) $zip->close();
    }
}

if (!function_exists('ahx_wp_core_localize_ajax_script')) {
    function ahx_wp_core_localize_ajax_script(string $handle, string $object_name, array $data = array(), string $ajax_key = 'ajaxUrl'): bool {
        if ($handle === '' || $object_name === '') {
            return false;
        }

        if (!isset($data[$ajax_key])) {
            $data[$ajax_key] = admin_url('admin-ajax.php');
        }

        return (bool) wp_localize_script($handle, $object_name, $data);
    }
}

if (!function_exists('ahx_wp_core_enqueue_localized_script')) {
    function ahx_wp_core_enqueue_localized_script(
        string $handle,
        string $src,
        array $deps = array(),
        $ver = null,
        bool $in_footer = true,
        string $object_name = '',
        array $data = array(),
        string $ajax_key = 'ajaxUrl'
    ): bool {
        if ($handle === '' || $src === '') {
            return false;
        }

        wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);

        if ($object_name !== '') {
            return ahx_wp_core_localize_ajax_script($handle, $object_name, $data, $ajax_key);
        }

        return true;
    }
}

if (!function_exists('normalize_path')) {
    function normalize_path(string $path): string {
        return ahx_wp_core_normalize_path($path);
    }
}
