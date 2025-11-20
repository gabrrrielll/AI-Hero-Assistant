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
        
        // Detectează email/telefon în mesajul utilizatorului
        $this->extract_and_save_lead($conversation_id, $user_message);
        
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
        
        // Extrage email
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $matches)) {
            $email = $matches[0];
        }
        
        // Extrage telefon (formate comune: +40..., 07..., etc.)
        if (preg_match('/\+?\d{1,4}[\s\-]?\(?\d{1,4}\)?[\s\-]?\d{1,4}[\s\-]?\d{1,9}/', $text, $matches)) {
            $phone = preg_replace('/[\s\-\(\)]/', '', $matches[0]);
        }
        
        if ($email || $phone) {
            AIHA_Database::save_lead($conversation_id, $email, $phone);
        }
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



