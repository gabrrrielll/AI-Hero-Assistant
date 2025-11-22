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
     * Detectează limba textului (îmbunătățită)
     */
    private function detect_language($text, $conversation_history = array(), $accept_language = '') {
        // 1. Verifică header-ul Accept-Language din browser
        if (!empty($accept_language)) {
            // Parsează Accept-Language header (ex: "ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7")
            $languages = explode(',', $accept_language);
            foreach ($languages as $lang) {
                $lang = trim(explode(';', $lang)[0]);
                $lang_code = strtolower(substr($lang, 0, 2));
                if ($lang_code === 'ro') {
                    return 'ro';
                }
            }
        }
        
        // 2. Verifică istoricul conversației pentru consistență
        if (!empty($conversation_history)) {
            $history_text = '';
            foreach ($conversation_history as $msg) {
                $history_text .= ' ' . $msg->content;
            }
            if ($this->is_romanian_text($history_text)) {
                return 'ro';
            }
        }
        
        // 3. Analizează textul curent
        if ($this->is_romanian_text($text)) {
            return 'ro';
        }
        
        // 4. Default: English
        return 'en';
    }
    
    /**
     * Verifică dacă textul este în română
     */
    private function is_romanian_text($text) {
        $text_lower = mb_strtolower($text);
        
        // Caractere speciale românești (ă, â, î, ș, ț)
        $romanian_chars = array('ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț');
        $has_romanian_chars = false;
        foreach ($romanian_chars as $char) {
            if (mb_strpos($text, $char) !== false) {
                $has_romanian_chars = true;
                break;
            }
        }
        
        // Cuvinte cheie românești (extinse)
        $romanian_keywords = array(
            'bună', 'salut', 'bună ziua', 'buna ziua', 'bună seara', 'buna seara',
            'mulțumesc', 'multumesc', 'vă rog', 'va rog', 'te rog',
            'da', 'nu', 'bine', 'rău', 'rau', 'cum', 'ce', 'când', 'cand',
            'unde', 'de ce', 'dece', 'cât', 'cat', 'câtă', 'cata',
            'servicii', 'servicii', 'programare', 'dezvoltare', 'aplicație', 'aplicatie',
            'website', 'site', 'firmă', 'firma', 'companie', 'echipă', 'echipa',
            'contact', 'telefon', 'email', 'adresă', 'adresa', 'informații', 'informatii'
        );
        
        $keyword_count = 0;
        foreach ($romanian_keywords as $keyword) {
            if (mb_strpos($text_lower, $keyword) !== false) {
                $keyword_count++;
            }
        }
        
        // Dacă are caractere românești SAU cel puțin 2 cuvinte cheie, consideră română
        if ($has_romanian_chars || $keyword_count >= 2) {
            return true;
        }
        
        // Verifică pattern-uri comune românești
        $romanian_patterns = array(
            '/\b(ce|care|unde|cum|când|de ce)\b/ui',
            '/\b(vă|te|mă|ne|vă|le)\b/ui',
            '/\b(sunt|este|suntem|sunteți)\b/ui'
        );
        
        $pattern_matches = 0;
        foreach ($romanian_patterns as $pattern) {
            if (preg_match($pattern, $text_lower)) {
                $pattern_matches++;
            }
        }
        
        // Dacă are cel puțin 2 pattern-uri, consideră română
        if ($pattern_matches >= 2) {
            return true;
        }
        
        return false;
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
    private function build_context($conversation_history, $user_message, $accept_language = '') {
        $settings = get_option('aiha_settings', array());
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : '';
        $ai_instructions = isset($settings['ai_instructions']) ? $settings['ai_instructions'] : '';
        
        // Detectează limba mesajului utilizatorului (îmbunătățită)
        $language = $this->detect_language($user_message, $conversation_history, $accept_language);
        
        // Obține genul asistentului
        $assistant_gender = isset($settings['assistant_gender']) ? $settings['assistant_gender'] : 'feminin';
        
        // Construiește sistemul de prompt
        $system_instruction = $ai_instructions;
        if ($company_name) {
            $system_instruction = str_replace('{company_name}', $company_name, $system_instruction);
        }
        
        // Adaugă instrucțiuni pentru LIMBĂ (CRITIC)
        if ($language === 'ro') {
            $system_instruction .= "\n\nCRITIC - LIMBĂ: Utilizatorul vorbește în ROMÂNĂ. TREBUIE să răspunzi EXCLUSIV în ROMÂNĂ. Folosește diacriticele corecte (ă, â, î, ș, ț). Adaptează-ți exprimarea pentru a fi naturală și corectă în română.";
        } else {
            $system_instruction .= "\n\nCRITICAL - LANGUAGE: The user is speaking in ENGLISH. You MUST respond EXCLUSIVELY in ENGLISH. Adapt your expression to be natural and correct in English.";
        }
        
        // Adaugă instrucțiuni pentru gen
        if ($assistant_gender === 'masculin') {
            if ($language === 'ro') {
                $system_instruction .= "\n\nIMPORTANT - GEN ASISTENT: Ești un asistent virtual MASCULIN. Folosește formele masculine în răspunsurile tale (ex: 'Sunt bucuros', 'Mulțumit', 'Încântat', etc.). Adaptează-ți exprimarea pentru a reflecta genul masculin.";
            } else {
                $system_instruction .= "\n\nIMPORTANT - ASSISTANT GENDER: You are a MASCULINE virtual assistant. Use masculine forms in your responses. Adapt your expression to reflect the masculine gender.";
            }
        } else {
            if ($language === 'ro') {
                $system_instruction .= "\n\nIMPORTANT - GEN ASISTENT: Ești o asistentă virtuală FEMININĂ. Folosește formele feminine în răspunsurile tale (ex: 'Sunt bucuroasă', 'Mulțumită', 'Încântată', etc.). Adaptează-ți exprimarea pentru a reflecta genul feminin.";
            } else {
                $system_instruction .= "\n\nIMPORTANT - ASSISTANT GENDER: You are a FEMININE virtual assistant. Use feminine forms in your responses. Adapt your expression to reflect the feminine gender.";
            }
        }
        
        // Adaugă documentația
        $system_instruction .= $this->process_documentation();
        
        // Adaugă instrucțiuni pentru lead generation
        if ($language === 'ro') {
            $system_instruction .= "\n\nIMPORTANT: În timpul conversației, încearcă să obții de la utilizator numărul de telefon sau adresa de email pentru a putea fi contactat de echipa noastră. Fă acest lucru într-un mod natural și prietenos, nu agresiv.";
            $system_instruction .= "\n\nCRITIC - FORMATARE TEXT: NU folosi NICIODATĂ spații de două rânduri între propoziții sau paragrafe. Folosește DOAR un singur rând între paragrafe. Nu adăuga linii goale multiple în răspunsurile tale. Textul trebuie să fie compact și fără spații excesive.";
        } else {
            $system_instruction .= "\n\nIMPORTANT: During the conversation, try to obtain the user's phone number or email address so they can be contacted by our team. Do this in a natural and friendly way, not aggressively.";
            $system_instruction .= "\n\nCRITICAL - TEXT FORMATTING: NEVER use double line breaks between sentences or paragraphs. Use ONLY a single line break between paragraphs. Do not add multiple empty lines in your responses. Text must be compact without excessive spacing.";
        }
        
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
    public function chat($user_message, $conversation_history = array(), $accept_language = '') {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key nu este configurată'
            );
        }
        
        $url = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;
        
        $messages = $this->build_context($conversation_history, $user_message, $accept_language);
        
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
    public function chat_stream($user_message, $conversation_history = array(), $accept_language = '') {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key nu este configurată'
            );
        }
        
        $url = $this->api_url . $this->model . ':streamGenerateContent?key=' . $this->api_key;
        
        $messages = $this->build_context($conversation_history, $user_message, $accept_language);
        
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

