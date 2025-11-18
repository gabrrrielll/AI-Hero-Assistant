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

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function activate()
    {
        // Creează tabelele în baza de date
        AIHA_Database::create_tables();

        // Setează opțiuni default
        $default_options = array(
            'api_key' => '',
            'model' => 'gemini-1.5-flash',
            'company_name' => '',
            'ai_instructions' => 'Ești un asistent virtual prietenos pentru o firmă de programare. Scopul tău este să informați vizitatorii despre serviciile companiei și să îi îndemni să contacteze firma. Răspunde întotdeauna în limba în care primești întrebarea.',
            'gradient_start' => '#6366f1',
            'gradient_end' => '#ec4899',
            'font_family' => 'Inter, sans-serif',
            'hero_message' => 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?'
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

        // Initialize components
        new AIHA_Admin_Settings();
        new AIHA_Shortcode();
        new AIHA_Ajax_Handler();
    }

    public function enqueue_assets()
    {
        // CSS
        wp_enqueue_style(
            'aiha-frontend',
            AIHA_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AIHA_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'aiha-frontend',
            AIHA_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            AIHA_VERSION,
            true
        );

        // Localize script pentru AJAX
        wp_localize_script('aiha-frontend', 'aihaData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiha_nonce'),
            'settings' => get_option('aiha_settings', array())
        ));
    }

    public function enqueue_admin_assets($hook)
    {
        // Doar pe pagina de settings
        if ('settings_page_aiha-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'aiha-admin',
            AIHA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIHA_VERSION
        );

        wp_enqueue_script(
            'aiha-admin',
            AIHA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AIHA_VERSION,
            true
        );

        wp_enqueue_media(); // Pentru upload de fișiere
    }
}

// Initialize plugin
AI_Hero_Assistant::get_instance();

