# AHX WP Core

**Version:** v0.1.1  
**Autor:** AHX

## Beschreibung

AHX WP Core stellt zentrale Basisfunktionen fuer AHX-Plugins bereit. Ziel ist, gemeinsame Logik nur noch einmal zu pflegen und in den einzelnen Plugins als Wrapper/Delegation zu nutzen.

## Enthaltene Bausteine

### Plugin-Basisklasse
- `AHX_Core_Plugin_Base` in `includes/class-ahx-core-plugin-base.php`
- Standardisierte Hook-Registrierung fuer Plugin-Klassen

### Logging
- `AHX_Core_Logging` in `includes/class-ahx-core-logging.php`
- `ahx_wp_core_log($level, $message, $source)` als Fassade
- Fallback-Reihenfolge: `AHX_Logging` -> `AHX_Core_Logging` -> `error_log`

### Notices
- `ahx_wp_core_add_notice(...)`
- `ahx_wp_core_print_notice_now(...)`
- `ahx_wp_core_display_admin_notices()`

### Utilities
- `ahx_wp_core_normalize_path(...)`
- `ahx_wp_core_return_bytes_formatted(...)`
- `ahx_wp_core_delete_file(...)`
- `ahx_wp_core_zip_directory(...)`
- `ahx_wp_core_debug_log_has_content()`
- `ahx_wp_core_localize_ajax_script(...)`
- `ahx_wp_core_enqueue_localized_script(...)`

### Plugin-Liste: "Uber das Plugin"
- Fuer Plugins mit `readme.md` oder `README.md` fuegt der Core in der WordPress-Plugin-Liste einen Link `Uber das Plugin` hinzu.
- Der Link oeffnet eine Core-Seite und zeigt den Inhalt der README-Datei als Markdown an.
- Unterstuetzt werden u. a. Ueberschriften, Listen (inkl. nummerierte Listen und Checkbox-Listen), Tabellen, Blockquotes, horizontale Linien, Bilder (`![alt](url)`), Codeblöcke (inkl. Sprach-Hinweis aus fenced code blocks wie ```php), Inline-Code, Fett/Kursiv, Strikethrough (`~~text~~`), Markdown-Links und Auto-Links fuer reine URLs.

## Migration-Prinzip

Bestehende Funktionen in `ahx_wp_main` bleiben als kompatible Wrapper erhalten und delegieren bevorzugt an `ahx_wp_core`, falls verfuegbar.
