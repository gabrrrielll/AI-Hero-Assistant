<?php
/**
 * Clasă pentru gestionarea request-urilor AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_aiha_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_nopriv_aiha_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_aiha_save_lead', array($this, 'handle_save_lead'));
        add_action('wp_ajax_nopriv_aiha_save_lead', array($this, 'handle_save_lead'));
    }
    
    /**
     * Gestionează trimiterea mesajului
     */
    public function handle_send_message() {
        check_ajax_referer('aiha_nonce', 'nonce');
        
        $user_message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($user_message)) {
            wp_send_json_error(array('message' => 'Mesajul nu poate fi gol'));
        }
        
        // Obține IP-ul utilizatorului
        $user_ip = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Creează sau obține conversația
        $conversation_id = AIHA_Database::get_or_create_conversation($session_id, $user_ip, $user_agent);
        
        // Salvează mesajul utilizatorului
        AIHA_Database::save_message($conversation_id, 'user', $user_message);
        
        // Obține istoricul conversației
        $history = AIHA_Database::get_conversation_history($conversation_id);
        
        // Trimite request către Gemini
        $gemini = new AIHA_Gemini_API();
        $response = $gemini->chat($user_message, $history);
        
        if (!$response['success']) {
            wp_send_json_error(array('message' => $response['error'] ?? 'Eroare la comunicarea cu AI'));
        }
        
        $ai_response = $response['text'];
        
        // Salvează răspunsul AI
        AIHA_Database::save_message($conversation_id, 'assistant', $ai_response);
        
        // Detectează email/telefon în toată conversația (mesaj utilizator + răspuns AI)
        $full_conversation_text = $user_message . ' ' . $ai_response;
        $this->extract_and_save_lead($conversation_id, $full_conversation_text);
        
        // Verifică și în toată conversația existentă pentru leads pierdute (doar dacă nu s-a găsit deja)
        // Obține lead-ul existent pentru a verifica dacă trebuie să scanăm din nou
        global $wpdb;
        $table_leads = $wpdb->prefix . 'aiha_leads';
        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_leads WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));
        
        // Dacă nu există lead sau nu are email/telefon, scanează toată conversația
        if (!$existing_lead || (empty($existing_lead->email) && empty($existing_lead->phone))) {
            $this->check_conversation_for_leads($conversation_id);
        }
        
        wp_send_json_success(array(
            'message' => $ai_response,
            'conversation_id' => $conversation_id
        ));
    }
    
    /**
     * Gestionează salvarea unui lead explicit
     */
    public function handle_save_lead() {
        check_ajax_referer('aiha_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        
        $user_ip = $this->get_user_ip();
        $conversation_id = AIHA_Database::get_or_create_conversation($session_id, $user_ip);
        
        AIHA_Database::save_lead($conversation_id, $email, $phone, $name);
        
        wp_send_json_success(array('message' => 'Lead salvat cu succes'));
    }
    
    /**
     * Extrage email/telefon din text și salvează lead
     */
    private function extract_and_save_lead($conversation_id, $text) {
        $email = '';
        $phone = '';
        $name = '';
        
        // Extrage email - regex îmbunătățit
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i', $text, $matches)) {
            $email = strtolower(trim($matches[0]));
            // Validare suplimentară
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }
        }
        
        // Extrage telefon - regex îmbunătățit pentru formate românești și internaționale
        // Formate: +40..., 07..., 0040..., etc.
        $phone_patterns = array(
            '/\+40[\s\-]?[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{4}/', // +40 format
            '/0040[\s\-]?[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{4}/', // 0040 format
            '/07[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{3}/', // 07 format românesc
            '/\+?\d{1,4}[\s\-\.]?\(?\d{1,4}\)?[\s\-\.]?\d{1,4}[\s\-\.]?\d{1,9}/', // Format general
        );
        
        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $phone = preg_replace('/[\s\-\(\)\.]/', '', $matches[0]);
                // Normalizează formatul
                if (strpos($phone, '0040') === 0) {
                    $phone = '+' . substr($phone, 2);
                } elseif (strpos($phone, '0') === 0 && strlen($phone) == 10) {
                    $phone = '+4' . $phone;
                } elseif (strpos($phone, '40') === 0 && strlen($phone) >= 10) {
                    $phone = '+' . $phone;
                }
                // Verifică că are cel puțin 10 cifre
                $digits_only = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($digits_only) >= 10) {
                    break; // Găsit un telefon valid
                } else {
                    $phone = ''; // Reset dacă nu e valid
                }
            }
        }
        
        // Încearcă să extragă nume din context (simplificat)
        // Caută pattern-uri comune: "numele meu este", "mă numesc", etc.
        if (preg_match('/(?:numele\s+meu\s+este|mă\s+numesc|sunt|eu\s+sunt)\s+([A-ZĂÂÎȘȚ][a-zăâîșț]+\s+[A-ZĂÂÎȘȚ][a-zăâîșț]+)/ui', $text, $matches)) {
            $name = trim($matches[1]);
        }
        
        // Log pentru debugging - verifică ce s-a găsit
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA Lead extraction - Text length: ' . strlen($text));
            error_log('AIHA Lead extraction - Found email: ' . ($email ?: 'none'));
            error_log('AIHA Lead extraction - Found phone: ' . ($phone ?: 'none'));
            error_log('AIHA Lead extraction - Found name: ' . ($name ?: 'none'));
        }
        
        // Salvează lead dacă există email sau telefon
        if ($email || $phone) {
            $result = AIHA_Database::save_lead($conversation_id, $email, $phone, $name);
            
            // Log pentru debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA Lead saved: Email=' . $email . ', Phone=' . $phone . ', Name=' . $name . ', Conversation=' . $conversation_id . ', Result=' . ($result ? 'success' : 'failed'));
            }
        } else {
            // Log când nu se găsește nimic
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA Lead extraction - No email or phone found in text');
            }
        }
    }
    
    /**
     * Verifică toată conversația pentru leads
     */
    private function check_conversation_for_leads($conversation_id) {
        // Obține toate mesajele din conversație
        $messages = AIHA_Database::get_conversation_history($conversation_id, 50);
        
        if (empty($messages)) {
            return;
        }
        
        // Construiește textul complet al conversației
        $full_text = '';
        foreach ($messages as $msg) {
            $full_text .= $msg->content . ' ';
        }
        
        // Extrage leads din toată conversația
        $this->extract_and_save_lead($conversation_id, $full_text);
    }
    
    /**
     * Obține IP-ul utilizatorului
     */
    private function get_user_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}



