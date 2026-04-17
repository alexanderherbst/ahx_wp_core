<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ahx_wp_core_get_readme_path_for_plugin')) {
    function ahx_wp_core_get_readme_path_for_plugin(string $plugin_file): string {
        $plugin_file = ltrim($plugin_file, '/\\');
        if ($plugin_file === '' || strpos($plugin_file, '..') !== false) {
            return '';
        }

        $plugin_dir = dirname($plugin_file);
        $base_dir = trailingslashit(WP_PLUGIN_DIR);
        if ($plugin_dir !== '.' && $plugin_dir !== DIRECTORY_SEPARATOR) {
            $base_dir .= $plugin_dir;
        }

        $base_dir_real = realpath($base_dir);
        $plugins_root_real = realpath(WP_PLUGIN_DIR);
        if ($base_dir_real === false || $plugins_root_real === false) {
            return '';
        }

        $base_dir_norm = wp_normalize_path($base_dir_real);
        $plugins_root_norm = wp_normalize_path($plugins_root_real);
        if (strpos($base_dir_norm, $plugins_root_norm) !== 0) {
            return '';
        }

        $candidates = array(
            $base_dir_real . DIRECTORY_SEPARATOR . 'readme.md',
            $base_dir_real . DIRECTORY_SEPARATOR . 'README.md',
            $base_dir_real . DIRECTORY_SEPARATOR . 'Readme.md',
        );

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('ahx_wp_core_plugin_action_links_about')) {
    function ahx_wp_core_plugin_action_links_about(array $actions, string $plugin_file, array $plugin_data, string $context): array {
        if (!is_admin() || !current_user_can('activate_plugins')) {
            return $actions;
        }

        $readme_path = ahx_wp_core_get_readme_path_for_plugin($plugin_file);
        if ($readme_path === '') {
            return $actions;
        }

        $url = add_query_arg(
            array(
                'page' => 'ahx-wp-core-plugin-about',
                'plugin' => rawurlencode($plugin_file),
            ),
            self_admin_url('admin.php')
        );

        $actions['ahx_wp_core_about'] = '<a href="' . esc_url($url) . '">Über das Plugin</a>';
        return $actions;
    }

    add_filter('plugin_action_links', 'ahx_wp_core_plugin_action_links_about', 10, 4);
}

if (!function_exists('ahx_wp_core_register_plugin_about_page')) {
    function ahx_wp_core_register_plugin_about_page() {
        add_submenu_page(
            'plugins.php',
            'Über das Plugin',
            'Über das Plugin',
            'activate_plugins',
            'ahx-wp-core-plugin-about',
            'ahx_wp_core_render_plugin_about_page'
        );
    }

    add_action('admin_menu', 'ahx_wp_core_register_plugin_about_page');
}

if (!function_exists('ahx_wp_core_get_plugin_name_by_file')) {
    function ahx_wp_core_get_plugin_name_by_file(string $plugin_file): string {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        if (isset($plugins[$plugin_file]['Name']) && is_string($plugins[$plugin_file]['Name'])) {
            return $plugins[$plugin_file]['Name'];
        }

        return $plugin_file;
    }
}

if (!function_exists('ahx_wp_core_render_plugin_about_page')) {
    if (!function_exists('ahx_wp_core_markdown_inline_to_html')) {
        function ahx_wp_core_markdown_inline_to_html(string $text): string {
            $text = esc_html($text);

            // Markdown-Bilder: ![Alt](https://...)
            $text = preg_replace_callback('/!\[([^\]]*)\]\((https?:\/\/[^\)\s]+)\)/', function ($m) {
                $alt = esc_attr($m[1]);
                $url = esc_url($m[2]);
                return '<img src="' . $url . '" alt="' . $alt . '" style="max-width:100%;height:auto;" />';
            }, $text);

            // Inline-Code zuerst, damit Formatierungen darin nicht wirken.
            $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
            $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
            $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);

            // Markdown-Links: [Text](https://...)
            $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\)\s]+)\)/', function ($m) {
                $label = $m[1];
                $url = esc_url($m[2]);
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
            }, $text);

            // Reine URLs automatisch verlinken (nicht innerhalb vorhandener href/src Attribute).
            $text = preg_replace_callback('/(?<!href=")(?<!src=")(https?:\/\/[^\s<"]+)/', function ($m) {
                $url = esc_url($m[1]);
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
            }, $text);

            return $text;
        }
    }

    if (!function_exists('ahx_wp_core_markdown_to_html')) {
        function ahx_wp_core_markdown_to_html(string $markdown): string {
            $lines = preg_split('/\r\n|\r|\n/', $markdown);
            if (!is_array($lines)) {
                return '';
            }

            $html = '';
            $in_ul = false;
            $in_ol = false;
            $in_code = false;
            $in_table = false;
            $in_blockquote = false;
            $table_row_index = 0;
            $code_buffer = '';
            $code_lang = '';

            foreach ($lines as $line) {
                $raw = (string) $line;
                $trimmed = trim($raw);

                if (preg_match('/^```\s*([A-Za-z0-9_\-\+]*)\s*$/', $trimmed, $code_match)) {
                    if (!$in_code) {
                        $in_code = true;
                        $code_buffer = '';
                        $code_lang = isset($code_match[1]) ? strtolower(trim((string) $code_match[1])) : '';
                    } else {
                        $lang_class = $code_lang !== '' ? ' class="language-' . esc_attr($code_lang) . '"' : '';
                        $lang_label = $code_lang !== '' ? '<div class="ahx-core-code-lang">Sprache: ' . esc_html($code_lang) . '</div>' : '';
                        $html .= $lang_label . '<pre><code' . $lang_class . '>' . esc_html(rtrim($code_buffer, "\n")) . '</code></pre>';
                        $in_code = false;
                        $code_buffer = '';
                        $code_lang = '';
                    }
                    continue;
                }

                if ($in_code) {
                    $code_buffer .= $raw . "\n";
                    continue;
                }

                if ($trimmed === '') {
                    if ($in_ul) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if ($in_blockquote) {
                        $html .= '</blockquote>';
                        $in_blockquote = false;
                    }
                    continue;
                }

                if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
                    if ($in_ul) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if ($in_blockquote) {
                        $html .= '</blockquote>';
                        $in_blockquote = false;
                    }
                    $level = strlen($m[1]);
                    $content = ahx_wp_core_markdown_inline_to_html($m[2]);
                    $html .= '<h' . $level . '>' . $content . '</h' . $level . '>';
                    continue;
                }

                if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
                    if ($in_ul) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if ($in_blockquote) {
                        $html .= '</blockquote>';
                        $in_blockquote = false;
                    }

                    $html .= '<hr />';
                    continue;
                }

                if (preg_match('/^>\s?(.*)$/', $trimmed, $m)) {
                    if ($in_ul) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if (!$in_blockquote) {
                        $html .= '<blockquote>';
                        $in_blockquote = true;
                    }
                    $html .= '<p>' . ahx_wp_core_markdown_inline_to_html($m[1]) . '</p>';
                    continue;
                }

                if ($in_blockquote) {
                    $html .= '</blockquote>';
                    $in_blockquote = false;
                }

                if (preg_match('/^[-\*]\s+\[( |x|X)\]\s+(.+)$/', $trimmed, $m)) {
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if (!$in_ul) {
                        $html .= '<ul class="ahx-core-task-list">';
                        $in_ul = true;
                    }

                    $checked = (strtolower($m[1]) === 'x') ? ' checked="checked"' : '';
                    $html .= '<li><input type="checkbox" disabled="disabled"' . $checked . '> ' . ahx_wp_core_markdown_inline_to_html($m[2]) . '</li>';
                    continue;
                }

                if (preg_match('/^[-\*]\s+(.+)$/', $trimmed, $m)) {
                    if ($in_ol) {
                        $html .= '</ol>';
                        $in_ol = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if (!$in_ul) {
                        $html .= '<ul>';
                        $in_ul = true;
                    }
                    $html .= '<li>' . ahx_wp_core_markdown_inline_to_html($m[1]) . '</li>';
                    continue;
                }

                if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                    if ($in_ul) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    if ($in_table) {
                        $html .= '</tbody></table>';
                        $in_table = false;
                        $table_row_index = 0;
                    }
                    if (!$in_ol) {
                        $html .= '<ol>';
                        $in_ol = true;
                    }
                    $html .= '<li>' . ahx_wp_core_markdown_inline_to_html($m[1]) . '</li>';
                    continue;
                }

                if (strpos($trimmed, '|') !== false) {
                    $cells_raw = array_map('trim', explode('|', trim($trimmed, '|')));
                    $cells = array_values(array_filter($cells_raw, function ($cell) {
                        return $cell !== '';
                    }));

                    $is_separator = !empty($cells_raw);
                    foreach ($cells_raw as $cell) {
                        if ($cell === '') {
                            continue;
                        }
                        if (!preg_match('/^:?-{3,}:?$/', $cell)) {
                            $is_separator = false;
                            break;
                        }
                    }

                    if (count($cells) >= 2 || $is_separator) {
                        if ($in_ul) {
                            $html .= '</ul>';
                            $in_ul = false;
                        }
                        if ($in_ol) {
                            $html .= '</ol>';
                            $in_ol = false;
                        }
                        if (!$in_table) {
                            $html .= '<table class="widefat striped" style="max-width:100%;"><tbody>';
                            $in_table = true;
                            $table_row_index = 0;
                        }

                        if ($is_separator) {
                            continue;
                        }

                        if ($table_row_index === 0) {
                            $html .= '<tr>';
                            foreach ($cells_raw as $cell) {
                                $cell = trim($cell);
                                if ($cell === '') {
                                    continue;
                                }
                                $html .= '<th>' . ahx_wp_core_markdown_inline_to_html($cell) . '</th>';
                            }
                            $html .= '</tr>';
                            $table_row_index++;
                            continue;
                        }

                        $html .= '<tr>';
                        foreach ($cells_raw as $cell) {
                            $cell = trim($cell);
                            if ($cell === '') {
                                continue;
                            }
                            $html .= '<td>' . ahx_wp_core_markdown_inline_to_html($cell) . '</td>';
                        }
                        $html .= '</tr>';
                        $table_row_index++;
                        continue;
                    }
                }

                if ($in_ul) {
                    $html .= '</ul>';
                    $in_ul = false;
                }
                if ($in_ol) {
                    $html .= '</ol>';
                    $in_ol = false;
                }
                if ($in_table) {
                    $html .= '</tbody></table>';
                    $in_table = false;
                    $table_row_index = 0;
                }

                $html .= '<p>' . ahx_wp_core_markdown_inline_to_html($trimmed) . '</p>';
            }

            if ($in_ul) {
                $html .= '</ul>';
            }
            if ($in_ol) {
                $html .= '</ol>';
            }
            if ($in_table) {
                $html .= '</tbody></table>';
            }
            if ($in_blockquote) {
                $html .= '</blockquote>';
            }
            if ($in_code) {
                $lang_class = $code_lang !== '' ? ' class="language-' . esc_attr($code_lang) . '"' : '';
                $lang_label = $code_lang !== '' ? '<div class="ahx-core-code-lang">Sprache: ' . esc_html($code_lang) . '</div>' : '';
                $html .= $lang_label . '<pre><code' . $lang_class . '>' . esc_html(rtrim($code_buffer, "\n")) . '</code></pre>';
            }

            return $html;
        }
    }

    function ahx_wp_core_render_plugin_about_page() {
        if (!current_user_can('activate_plugins')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'default'));
        }

        $plugin_file_raw = isset($_GET['plugin']) ? wp_unslash((string) $_GET['plugin']) : '';
        $plugin_file_decoded = rawurldecode($plugin_file_raw);
        $plugin_file = ltrim(preg_replace('#[^A-Za-z0-9_\-./]#', '', $plugin_file_decoded), '/\\');

        $plugin_name = ahx_wp_core_get_plugin_name_by_file($plugin_file);
        $readme_path = ahx_wp_core_get_readme_path_for_plugin($plugin_file);

        echo '<div class="wrap">';
        echo '<style>
            .ahx-core-readme { background:#fff; border:1px solid #ccd0d4; padding:16px 18px; line-height:1.65; max-width:1100px; }
            .ahx-core-readme h1,.ahx-core-readme h2,.ahx-core-readme h3,.ahx-core-readme h4,.ahx-core-readme h5,.ahx-core-readme h6 { margin-top:1.2em; margin-bottom:.45em; }
            .ahx-core-readme p { margin:.6em 0; }
            .ahx-core-readme ul,.ahx-core-readme ol { margin:.4em 0 .9em 1.4em; }
            .ahx-core-readme ul { list-style: disc outside; }
            .ahx-core-readme ol { list-style: decimal outside; }
            .ahx-core-readme li { margin:.2em 0; }
            .ahx-core-readme blockquote { margin:.9em 0; padding:.3em .9em; border-left:4px solid #72aee6; color:#3c434a; background:#f6f7f7; }
            .ahx-core-readme pre { margin:.2em 0 1em 0; padding:12px; background:#0f172a; color:#e2e8f0; border-radius:6px; overflow:auto; }
            .ahx-core-readme code { background:#f0f0f1; padding:.1em .3em; border-radius:3px; }
            .ahx-core-readme pre code { background:transparent; padding:0; color:inherit; }
            .ahx-core-readme table { border-collapse:collapse; width:100%; margin:.8em 0 1em 0; }
            .ahx-core-readme table th,.ahx-core-readme table td { border:1px solid #dcdcde; padding:7px 9px; text-align:left; }
            .ahx-core-readme .ahx-core-code-lang { display:inline-block; font-size:12px; line-height:1; text-transform:uppercase; letter-spacing:.05em; padding:5px 8px; border:1px solid #c3c4c7; border-bottom:none; border-radius:6px 6px 0 0; background:#f6f7f7; color:#50575e; margin-top:.8em; }
            .ahx-core-readme ul.ahx-core-task-list { list-style: none; margin-left: 0; }
            .ahx-core-readme .ahx-core-task-list input[type="checkbox"] { margin-right:.45em; }
        </style>';
        echo '<h1>Über das Plugin: ' . esc_html($plugin_name) . '</h1>';
        echo '<p><a href="' . esc_url(self_admin_url('plugins.php')) . '">Zurück zur Plugin-Liste</a></p>';

        if ($readme_path === '') {
            echo '<div class="notice notice-warning"><p>Keine readme.md oder README.md gefunden.</p></div>';
            echo '</div>';
            return;
        }

        $readme_content = file_get_contents($readme_path);
        if (!is_string($readme_content)) {
            echo '<div class="notice notice-error"><p>README konnte nicht gelesen werden.</p></div>';
            echo '</div>';
            return;
        }

        $rendered = ahx_wp_core_markdown_to_html($readme_content);
        $allowed_tags = array(
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'p' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'pre' => array(),
            'code' => array(
                'class' => array(),
            ),
            'div' => array(
                'class' => array(),
            ),
            'strong' => array(),
            'em' => array(),
            'del' => array(),
            'a' => array(
                'href' => array(),
                'target' => array(),
                'rel' => array(),
            ),
            'table' => array(
                'class' => array(),
                'style' => array(),
            ),
            'tbody' => array(),
            'thead' => array(),
            'tr' => array(),
            'th' => array(),
            'td' => array(),
            'blockquote' => array(),
            'input' => array(
                'type' => array(),
                'disabled' => array(),
                'checked' => array(),
            ),
            'hr' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'style' => array(),
            ),
        );

        echo '<h2>' . esc_html(basename($readme_path)) . '</h2>';
        echo '<div class="ahx-core-readme">';
        echo wp_kses($rendered, $allowed_tags);
        echo '</div>';
        echo '</div>';
    }
}
