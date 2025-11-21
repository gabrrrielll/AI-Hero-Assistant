<?php
/**
 * Clasă pentru integrarea cu Google Gemini API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Gemini_API {
    
    private $api_key;
    private $model;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    public function __construct() {
        $settings = get_option('aiha_settings', array());
        $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $this->model = isset($settings['model']) ? $settings['model'] : 'gemini-1.5-flash';
    }
    
    /**
     * Detectează limba textului
     */
    private function detect_language($text) {
        // Simplificat - poate fi îmbunătățit cu o librărie de detectare
        $romanian_keywords = array('bună', 'salut', 'bună ziua', 'mulțumesc', 'vă rog', 'da', 'nu');
        $text_lower = mb_strtolower($text);
        
        foreach ($romanian_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                return 'ro';
            }
        }
        
        return 'en'; // Default English
    }
    
    /**
     * Procesează documentația încărcată și extrage textul
     */
    private function process_documentation() {
        $settings = get_option('aiha_settings', array());
        $docs = isset($settings['documentation_files']) ? $settings['documentation_files'] : array();
        
        if (empty($docs)) {
            return '';
        }
        
        $documentation_text = "\n\nDOCUMENTAȚIE DESPRE SERVICII:\n";
        $documentation_text .= "Următoarele informații sunt disponibile despre serviciile companiei:\n";
        
        // Pentru simplitate, vom include doar URL-urile fișierelor
        // În producție, poți adăuga procesare reală a PDF/DOC/TXT
        foreach ($docs as $doc_url) {
            $documentation_text .= "- Document: " . basename($doc_url) . "\n";
        }
        
        $documentation_text .= "\nFolosește aceste informații pentru a răspunde la întrebările despre servicii.\n";
        
        return $documentation_text;
    }
    
    /**
     * Construiește contextul pentru AI
     */
    private function build_context($conversation_history, $user_message) {
        $settings = get_option('aiha_settings', array());
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : '';
        $ai_instructions = isset($settings['ai_instructions']) ? $settings['ai_instructions'] : '';
        
        // Detectează limba mesajului utilizatorului
        $language = $this->detect_language($user_message);
        
        // Obține genul asistentului
        $assistant_gender = isset($settings['assistant_gender']) ? $settings['assistant_gender'] : 'feminin';
        
        // Construiește sistemul de prompt
        $system_instruction = $ai_instructions;
        if ($company_name) {
            $system_instruction = str_replace('{company_name}', $company_name, $system_instruction);
        }
        
        // Adaugă instrucțiuni pentru gen
        if ($assistant_gender === 'masculin') {
            $system_instruction .= "\n\nIMPORTANT - GEN ASISTENT: Ești un asistent virtual MASCULIN. Folosește formele masculine în răspunsurile tale (ex: 'Sunt bucuros', 'Mulțumit', 'Încântat', etc.). Adaptează-ți exprimarea pentru a reflecta genul masculin.";
        } else {
            $system_instruction .= "\n\nIMPORTANT - GEN ASISTENT: Ești o asistentă virtuală FEMININĂ. Folosește formele feminine în răspunsurile tale (ex: 'Sunt bucuroasă', 'Mulțumită', 'Încântată', etc.). Adaptează-ți exprimarea pentru a reflecta genul feminin.";
        }
        
        // Adaugă documentația
        $system_instruction .= $this->process_documentation();
        
        // Adaugă instrucțiuni pentru lead generation
        $system_instruction .= "\n\nIMPORTANT: În timpul conversației, încearcă să obții de la utilizator numărul de telefon sau adresa de email pentru a putea fi contactat de echipa noastră. Fă acest lucru într-un mod natural și prietenos, nu agresiv.";
        
        // Construiește mesajele pentru API
        $messages = array();
        
        // Adaugă sistem instruction ca prim mesaj
        $messages[] = array(
            'role' => 'user',
            'parts' => array(array('text' => $system_instruction))
        );
        $messages[] = array(
            'role' => 'model',
            'parts' => array(array('text' => 'Înțeles. Voi ajuta utilizatorii să înțeleagă serviciile companiei și voi încerca să obțin date de contact într-un mod natural.'))
        );
        
        // Adaugă istoricul conversației
        foreach ($conversation_history as $msg) {
            $role = $msg->role === 'user' ? 'user' : 'model';
            $messages[] = array(
                'role' => $role,
                'parts' => array(array('text' => $msg->content))
            );
        }
        
        // Adaugă mesajul curent
        $messages[] = array(
            'role' => 'user',
            'parts' => array(array('text' => $user_message))
        );
        
        return $messages;
    }
    
    /**
     * Trimite request către Gemini API
     */
    public function chat($user_message, $conversation_history = array()) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key nu este configurată'
            );
        }
        
        $url = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $messages = $this->build_context($conversation_history, $user_message);
        
        $body = array(
            'contents' => $messages,
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = isset($response_body['error']['message']) 
                ? $response_body['error']['message'] 
                : 'Eroare necunoscută de la API';
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        // Extrage textul din răspuns
        $text = '';
        if (isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $response_body['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return array(
            'success' => true,
            'text' => $text
        );
    }
    
    /**
     * Streaming chat (pentru typing effect în timp real)
     */
    public function chat_stream($user_message, $conversation_history = array()) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key nu este configurată'
            );
        }
        
        $url = $this->api_url . $this->model . ':streamGenerateContent?key=' . $this->api_key;
        
        $messages = $this->build_context($conversation_history, $user_message);
        
        $body = array(
            'contents' => $messages,
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 60,
            'stream' => true
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        // Pentru streaming, returnăm response-ul direct
        // Frontend-ul va procesa stream-ul
        return array(
            'success' => true,
            'stream' => $response
        );
    }
}

