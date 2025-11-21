<?php

/**
 * Plugin Name: AI Hero Assistant
 * Plugin URI:https://github.com/gabrrrielll/AI-Hero-Assistant.git
 * Description: An advanced AI chatbot with an animated avatar for the hero section, integrated with the Google Gemini API
 * Version: 1.0.0
 * Author: Gabriel Sandu
 * Author URI: https://github.com/gabrrrielll
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-hero-assistant
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIHA_VERSION', '1.0.0');
define('AIHA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIHA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIHA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader pentru clase
spl_autoload_register(function ($class) {
    $prefix = 'AIHA_';
    $base_dir = AIHA_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Include fișierele necesare
require_once AIHA_PLUGIN_DIR . 'includes/class-gemini-api.php';
require_once AIHA_PLUGIN_DIR . 'includes/class-database.php';
require_once AIHA_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once AIHA_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once AIHA_PLUGIN_DIR . 'includes/class-ajax-handler.php';

/**
 * Clasa principală a plugin-ului
 */
class AI_Hero_Assistant
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Inline asset loading (tested method)
        // Frontend CSS and JS
        add_action('wp_head', array($this, 'inline_frontend_css'));
        add_action('wp_footer', array($this, 'inline_frontend_js'));

        // Admin CSS and JS
        add_action('admin_head', array($this, 'inline_admin_css'));
        add_action('admin_footer', array($this, 'inline_admin_js'));
    }

    public function activate()
    {
        // Creează tabelele în baza de date
        AIHA_Database::create_tables();

        // Verifică și actualizează schema (pentru upgrade-uri)
        AIHA_Database::ensure_schema_up_to_date();

        // Setează opțiuni default
        $default_options = array(
            'api_key' => '',
            'model' => 'gemini-1.5-flash',
            'company_name' => '',
            'ai_instructions' => 'Ești un asistent virtual prietenos pentru o firmă de programare. Scopul tău este să informați vizitatorii despre serviciile companiei și să îi îndemni să contacteze firma. Răspunde întotdeauna în limba în care primești întrebarea.',
            'gradient_start' => '#6366f1',
            'gradient_end' => '#ec4899',
            'font_family' => 'Inter, sans-serif',
            'hero_message' => 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?',
              'assistant_gender' => 'feminin',
              'send_lead_email' => 0,
              'lead_notification_email' => get_option('admin_email')
        );

        add_option('aiha_settings', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        // Cleanup dacă e necesar
        flush_rewrite_rules();
    }

    public function init()
    {
        // Load text domain pentru traduceri
        load_plugin_textdomain('ai-hero-assistant', false, dirname(AIHA_PLUGIN_BASENAME) . '/languages');

        // Asigură că schema este actualizată (pentru upgrade-uri)
        AIHA_Database::ensure_schema_up_to_date();

        // Initialize components
        new AIHA_Admin_Settings();
        new AIHA_Shortcode();
        new AIHA_Ajax_Handler();
    }

    /**
     * Inline Frontend CSS
     */
    public function inline_frontend_css()
    {
        // Verifică dacă există shortcode-ul pe pagină sau în widget-uri
        global $post;
        $has_shortcode = false;

        if (is_a($post, 'WP_Post')) {
            $has_shortcode = has_shortcode($post->post_content, 'ai_hero_assistant');
        }

        // Verifică și în widget-uri (do_action pentru widget-uri)
        if (!$has_shortcode) {
            // Verifică dacă există container-ul în output (pentru widget-uri sau alte locații)
            // Dacă nu există shortcode, încărcăm CSS oricum pentru flexibilitate
            // Poți comenta următoarea linie dacă vrei să încarci doar când există shortcode
            // return;
        }

        // Include CSS Variables first, then main CSS
        $variables_path = AIHA_PLUGIN_DIR . 'assets/css/variables.css';
        $css_path = AIHA_PLUGIN_DIR . 'assets/css/frontend.css';

        if (file_exists($css_path)) {
            echo '<style type="text/css" id="aiha-frontend-css">';
            // Include variables first if exists
            if (file_exists($variables_path)) {
                echo file_get_contents($variables_path);
            }
            // Then include main CSS
            echo file_get_contents($css_path);
            echo '</style>';
        }
    }

    /**
     * Inline Frontend JavaScript
     */
    public function inline_frontend_js()
    {
        // Verifică dacă există shortcode-ul pe pagină
        global $post;
        $has_shortcode = false;

        if (is_a($post, 'WP_Post')) {
            $has_shortcode = has_shortcode($post->post_content, 'ai_hero_assistant');
        }

        // Verifică dacă jQuery este disponibil și îl încarcă dacă nu este
        if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'done')) {
            // jQuery va fi încărcat de WordPress automat, dar îl includem pentru siguranță
            echo '<script src="' . includes_url('js/jquery/jquery.min.js') . '"></script>';
        }

        $js_path = AIHA_PLUGIN_DIR . 'assets/js/frontend.js';

        if (file_exists($js_path)) {
            // AJAX data pentru JavaScript
            $ajax_data = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiha_nonce'),
                'settings' => get_option('aiha_settings', array())
            );

            echo '<script type="text/javascript" id="aiha-frontend-js">';
            echo 'var aihaData = ' . json_encode($ajax_data) . ';';
            echo file_get_contents($js_path);
            echo '</script>';
        }
    }

    /**
     * Inline Admin CSS
     */
    public function inline_admin_css()
    {
        if (!is_admin()) {
            return;
        }

        $screen = get_current_screen();
        $current_screen_id = $screen ? $screen->id : 'no_screen';

        // Verificăm dacă suntem pe pagina de setări
        if ('settings_page_aiha-settings' !== $current_screen_id) {
            return;
        }

        // Include Bootstrap CSS inline
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">';

        $css_path = AIHA_PLUGIN_DIR . 'assets/css/admin.css';

        if (file_exists($css_path)) {
            echo '<style type="text/css" id="aiha-admin-css">';
            echo file_get_contents($css_path);
            echo '</style>';
        }
    }

    /**
     * Inline Admin JavaScript
     */
    public function inline_admin_js()
    {
        if (!is_admin()) {
            return;
        }

        $screen = get_current_screen();
        $current_screen_id = $screen ? $screen->id : 'no_screen';

        // Verificăm dacă suntem pe pagina de setări
        if ('settings_page_aiha-settings' !== $current_screen_id) {
            return;
        }

        // Enqueue jQuery și media pentru upload
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }
        wp_enqueue_media();

        // Include Bootstrap JS inline
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>';

        // Localize script pentru AJAX și URL-uri
        $admin_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiha_admin_nonce'),
            'settingsPageUrl' => admin_url('options-general.php?page=aiha-settings&tab=conversations')
        );
        echo '<script type="text/javascript">window.aihaAdminData = ' . json_encode($admin_data) . ';</script>';

        $js_path = AIHA_PLUGIN_DIR . 'assets/js/admin.js';

        if (file_exists($js_path)) {
            echo '<script type="text/javascript" id="aiha-admin-js">';
            echo file_get_contents($js_path);
            echo '</script>';
        }
    }
}

// Initialize plugin
AI_Hero_Assistant::get_instance();
