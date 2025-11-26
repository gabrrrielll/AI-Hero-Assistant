<?php

/**
 * Class for handling AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Ajax_Handler
{
    public function __construct()
    {
        add_action('wp_ajax_aiha_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_nopriv_aiha_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_aiha_save_lead', array($this, 'handle_save_lead'));
        add_action('wp_ajax_nopriv_aiha_save_lead', array($this, 'handle_save_lead'));

        // Admin AJAX handlers for conversations
        add_action('wp_ajax_aiha_get_conversation', array($this, 'handle_get_conversation'));
        add_action('wp_ajax_aiha_delete_conversation', array($this, 'handle_delete_conversation'));
        add_action('wp_ajax_aiha_delete_conversations_bulk', array($this, 'handle_delete_conversations_bulk'));
    }

    /**
     * Handle message sending
     */
    public function handle_send_message()
    {
        check_ajax_referer('aiha_nonce', 'nonce');

        $user_message = sanitize_text_field($_POST['message'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($user_message)) {
            wp_send_json_error(array('message' => 'Message cannot be empty'));
        }

        // Get user IP
        $user_ip = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Create or get conversation
        $conversation_id = AIHA_Database::get_or_create_conversation($session_id, $user_ip, $user_agent);

        // Save user message
        AIHA_Database::save_message($conversation_id, 'user', $user_message);

        // Get conversation history
        $history = AIHA_Database::get_conversation_history($conversation_id);

        // Get Accept-Language header from browser
        $accept_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';

        // Send request to Gemini
        $gemini = new AIHA_Gemini_API();
        $response = $gemini->chat($user_message, $history, $accept_language);

        if (!$response['success']) {
            wp_send_json_error(array('message' => $response['error'] ?? 'Error communicating with AI'));
        }

        $ai_response = $response['text'];
        
        // Normalize text: remove multiple consecutive empty lines (max 1 empty line between paragraphs)
        $ai_response = $this->normalize_text_spacing($ai_response);

        // Save AI response
        AIHA_Database::save_message($conversation_id, 'assistant', $ai_response);

        // Detect email/phone in entire conversation (user message + AI response)
        $full_conversation_text = $user_message . ' ' . $ai_response;
        $this->extract_and_save_lead($conversation_id, $full_conversation_text);

        // Check entire existing conversation for missed leads (only if not already found)
        // Get existing lead to check if we need to scan again
        global $wpdb;
        $table_leads = $wpdb->prefix . 'aiha_leads';
        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_leads WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));

        // If no lead exists or it doesn't have email/phone, scan entire conversation
        if (!$existing_lead || (empty($existing_lead->email) && empty($existing_lead->phone))) {
            $this->check_conversation_for_leads($conversation_id);
        }

        wp_send_json_success(array(
            'message' => $ai_response,
            'conversation_id' => $conversation_id
        ));
    }

    /**
     * Handle explicit lead saving
     */
    public function handle_save_lead()
    {
        check_ajax_referer('aiha_nonce', 'nonce');

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');

        $user_ip = $this->get_user_ip();
        $conversation_id = AIHA_Database::get_or_create_conversation($session_id, $user_ip);

        if (!$this->save_lead_and_notify($conversation_id, $email, $phone, $name)) {
            wp_send_json_error(array('message' => __('Lead-ul nu a putut fi salvat.', 'ai-hero-assistant')));
        }

        wp_send_json_success(array('message' => 'Lead salvat cu succes'));
    }

    /**
     * Extract email/phone from text and save lead
     */
    private function extract_and_save_lead($conversation_id, $text)
    {
        $email = '';
        $phone = '';
        $name = '';

        // Extract email - improved regex
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i', $text, $matches)) {
            $email = strtolower(trim($matches[0]));
            // Additional validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }
        }

        // Extract phone - improved regex for Romanian and international formats
        // Formats: +40..., 07..., 0040..., etc.
        $phone_patterns = array(
            '/\+40[\s\-]?[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{4}/', // +40 format
            '/0040[\s\-]?[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{4}/', // 0040 format
            '/07[0-9]{2}[\s\-]?[0-9]{3}[\s\-]?[0-9]{3}/', // 07 format românesc
            '/\+?\d{1,4}[\s\-\.]?\(?\d{1,4}\)?[\s\-\.]?\d{1,4}[\s\-\.]?\d{1,9}/', // Format general
        );

        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $phone = preg_replace('/[\s\-\(\)\.]/', '', $matches[0]);
                // Normalize format
                if (strpos($phone, '0040') === 0) {
                    $phone = '+' . substr($phone, 2);
                } elseif (strpos($phone, '0') === 0 && strlen($phone) == 10) {
                    $phone = '+4' . $phone;
                } elseif (strpos($phone, '40') === 0 && strlen($phone) >= 10) {
                    $phone = '+' . $phone;
                }
                // Verify it has at least 10 digits
                $digits_only = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($digits_only) >= 10) {
                    break; // Found a valid phone
                } else {
                    $phone = ''; // Reset if not valid
                }
            }
        }

        // Try to extract name from context (simplified)
        // Look for common patterns: "numele meu este", "mă numesc", etc.
        if (preg_match('/(?:numele\s+meu\s+este|mă\s+numesc|sunt|eu\s+sunt)\s+([A-ZĂÂÎȘȚ][a-zăâîșț]+\s+[A-ZĂÂÎȘȚ][a-zăâîșț]+)/ui', $text, $matches)) {
            $extracted_name = trim($matches[1]);
            // Validate name before using it
            if ($this->is_valid_name($extracted_name)) {
                $name = $extracted_name;
            }
        }

        // Log for debugging - check what was found
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA Lead extraction - Text length: ' . strlen($text));
            error_log('AIHA Lead extraction - Found email: ' . ($email ?: 'none'));
            error_log('AIHA Lead extraction - Found phone: ' . ($phone ?: 'none'));
            error_log('AIHA Lead extraction - Found name: ' . ($name ?: 'none'));
        }

        // Save lead if email or phone exists
        if ($email || $phone) {
            $result = $this->save_lead_and_notify($conversation_id, $email, $phone, $name);

            // Log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA Lead saved: Email=' . $email . ', Phone=' . $phone . ', Name=' . $name . ', Conversation=' . $conversation_id . ', Result=' . ($result ? 'success' : 'failed'));
            }
        } else {
            // Log when nothing is found
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA Lead extraction - No email or phone found in text');
            }
        }
    }

    /**
     * Check entire conversation for leads
     */
    private function check_conversation_for_leads($conversation_id)
    {
        // Get all messages from conversation
        $messages = AIHA_Database::get_conversation_history($conversation_id, 50);

        if (empty($messages)) {
            return;
        }

        // Build complete conversation text
        $full_text = '';
        foreach ($messages as $msg) {
            $full_text .= $msg->content . ' ';
        }

        // Extract leads from entire conversation
        $this->extract_and_save_lead($conversation_id, $full_text);
    }

    /**
     * Save lead and send notification if applicable
     */
    private function save_lead_and_notify($conversation_id, $email, $phone, $name = '')
    {
        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA save_lead_and_notify: Called - conversation_id=' . $conversation_id . ', email=' . ($email ?: 'empty') . ', phone=' . ($phone ?: 'empty') . ', name=' . ($name ?: 'empty'));
        }

        if (empty($email) && empty($phone)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead_and_notify: Skipped - both email and phone are empty');
            }
            return false;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'aiha_leads';

        $existing_lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_leads WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));

        $normalized_new_email = $email ? strtolower($email) : '';
        $normalized_existing_email = ($existing_lead && !empty($existing_lead->email)) ? strtolower($existing_lead->email) : '';

        $has_new_email = $normalized_new_email && $normalized_new_email !== $normalized_existing_email;
        $has_new_phone = $phone && (!$existing_lead || $existing_lead->phone !== $phone);

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA save_lead_and_notify: Existing lead=' . ($existing_lead ? 'yes' : 'no') . ', has_new_email=' . ($has_new_email ? 'yes' : 'no') . ', has_new_phone=' . ($has_new_phone ? 'yes' : 'no'));
        }

        $result = AIHA_Database::save_lead($conversation_id, $email, $phone, $name);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA save_lead_and_notify: save_lead result=' . ($result ? 'success' : 'failed'));
        }

        if ($result && ($has_new_email || $has_new_phone)) {
            $final_email = $email ?: ($existing_lead->email ?? '');
            $final_phone = $phone ?: ($existing_lead->phone ?? '');
            $final_name = $name ?: ($existing_lead->name ?? '');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead_and_notify: Calling maybe_send_lead_notification_email - final_email=' . ($final_email ?: 'empty') . ', final_phone=' . ($final_phone ?: 'empty') . ', final_name=' . ($final_name ?: 'empty'));
            }

            $this->maybe_send_lead_notification_email($conversation_id, $final_email, $final_phone, $final_name);
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead_and_notify: NOT calling email notification - result=' . ($result ? 'success' : 'failed') . ', has_new_email=' . ($has_new_email ? 'yes' : 'no') . ', has_new_phone=' . ($has_new_phone ? 'yes' : 'no'));
            }
        }

        return $result;
    }

    /**
     * Validate if a name is valid (not a phrase or common words)
     */
    private function is_valid_name($name)
    {
        if (empty($name)) {
            return false;
        }

        // Remove multiple spaces and normalize
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Check minimum length (at least 4 characters)
        if (strlen($name) < 4) {
            return false;
        }

        // Check if it has at least 2 words (first name + last name)
        $words = explode(' ', $name);
        if (count($words) < 2) {
            return false;
        }

        // List of common words that are not names
        $invalid_words = array(
            'sunt', 'suntem', 'sunteți', 'este', 'să', 'sau', 'și', 'cu', 'de', 'la', 'în', 'pe',
            'încântată', 'încântat', 'bucuroasă', 'bucuros', 'mulțumită', 'mulțumit',
            'fericită', 'fericit', 'satisfăcută', 'satisfăcut', 'mulțumesc', 'mulțumim',
            'vă', 'te', 'mă', 'ne', 'le', 'lui', 'ei', 'lor', 'meu', 'mea', 'mei', 'mele',
            'tău', 'ta', 'tăi', 'tale', 'său', 'sa', 'săi', 'sale', 'nostru', 'noastră',
            'voastră', 'voștri', 'voastre', 'lor', 'lui', 'ei'
        );

        // Check if it contains invalid words
        $name_lower = mb_strtolower($name);
        foreach ($invalid_words as $invalid_word) {
            if (strpos($name_lower, $invalid_word) !== false) {
                return false;
            }
        }

        // Check if each word starts with uppercase letter
        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }
            // Check if first letter is uppercase
            $first_char = mb_substr($word, 0, 1);
            if (!preg_match('/[A-ZĂÂÎȘȚ]/u', $first_char)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send notification email if option is enabled
     */
    private function maybe_send_lead_notification_email($conversation_id, $email, $phone, $name)
    {
        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA maybe_send_lead_notification_email: Called - conversation_id=' . $conversation_id . ', email=' . ($email ?: 'empty') . ', phone=' . ($phone ?: 'empty') . ', name=' . ($name ?: 'empty'));
        }

        $settings = get_option('aiha_settings', array());

        // Log settings
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA maybe_send_lead_notification_email: Settings - send_lead_email=' . (isset($settings['send_lead_email']) ? $settings['send_lead_email'] : 'not set') . ', lead_notification_email=' . (isset($settings['lead_notification_email']) ? $settings['lead_notification_email'] : 'not set'));
        }

        if (empty($settings['send_lead_email'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: SKIPPED - send_lead_email is not enabled');
            }
            return;
        }

        $recipient = !empty($settings['lead_notification_email']) ? sanitize_email($settings['lead_notification_email']) : '';
        if (empty($recipient)) {
            $recipient = get_option('admin_email');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: Using admin_email as recipient: ' . $recipient);
            }
        }
        $recipient = sanitize_email($recipient);

        if (empty($recipient) || !is_email($recipient)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: SKIPPED - Invalid recipient email: ' . $recipient);
            }
            return;
        }

        $subject = sprintf(__('New Lead AI Hero Assistant (#%d)', 'ai-hero-assistant'), $conversation_id);
        $conversation_url = admin_url('options-general.php?page=aiha-settings&tab=conversations&conversation_id=' . $conversation_id);

        $body = '<p>' . esc_html__('A new lead has been captured in AI Hero Assistant.', 'ai-hero-assistant') . '</p>';
        $body .= '<ul>';
        // Display name only if valid
        if (!empty($name) && $this->is_valid_name($name)) {
            $body .= '<li><strong>' . esc_html__('Name', 'ai-hero-assistant') . ':</strong> ' . esc_html($name) . '</li>';
        }
        if (!empty($email)) {
            $body .= '<li><strong>' . esc_html__('Email', 'ai-hero-assistant') . ':</strong> ' . esc_html($email) . '</li>';
        }
        if (!empty($phone)) {
            $body .= '<li><strong>' . esc_html__('Phone', 'ai-hero-assistant') . ':</strong> ' . esc_html($phone) . '</li>';
        }
        $body .= '</ul>';

        // Add full conversation
        $messages = AIHA_Database::get_conversation_history($conversation_id, 1000);
        if (!empty($messages)) {
            $body .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
            $body .= '<h3 style="margin-top: 0;">' . esc_html__('Conversația completă', 'ai-hero-assistant') . '</h3>';
            $body .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 600px; overflow-y: auto;">';

            foreach ($messages as $msg) {
                $is_user = $msg->role === 'user';
                $bg_color = $is_user ? '#0073aa' : '#f0f0f1';
                $text_color = $is_user ? '#fff' : '#000';
                $align = $is_user ? 'right' : 'left';
                $sender = $is_user ? esc_html__('Utilizator', 'ai-hero-assistant') : esc_html__('AI', 'ai-hero-assistant');

                $body .= '<div style="margin-bottom: 15px; text-align: ' . $align . ';">';
                $body .= '<div style="display: inline-block; max-width: 80%; background: ' . $bg_color . '; color: ' . $text_color . '; padding: 10px 15px; border-radius: 8px; text-align: left;">';
                $body .= '<div style="font-weight: bold; margin-bottom: 5px; font-size: 12px; opacity: 0.9;">' . esc_html($sender) . '</div>';
                $body .= '<div style="line-height: 1.5;">' . nl2br(esc_html($msg->content)) . '</div>';
                if (!empty($msg->created_at)) {
                    $body .= '<div style="font-size: 11px; margin-top: 5px; opacity: 0.7;">' . esc_html($msg->created_at) . '</div>';
                }
                $body .= '</div>';
                $body .= '</div>';
            }

            $body .= '</div>';
        }

        $body .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
        $body .= '<p><a href="' . esc_url($conversation_url) . '" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px;">' . esc_html__('Vezi conversația în WordPress', 'ai-hero-assistant') . '</a></p>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Log before sending
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA maybe_send_lead_notification_email: Attempting to send email - recipient=' . $recipient . ', subject=' . $subject);
            error_log('AIHA maybe_send_lead_notification_email: Body length=' . strlen($body) . ' chars');
        }

        // Hook to capture PHPMailer errors
        add_action('wp_mail_failed', function ($wp_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: wp_mail_failed hook triggered');
                error_log('AIHA maybe_send_lead_notification_email: WP_Error message: ' . $wp_error->get_error_message());
                error_log('AIHA maybe_send_lead_notification_email: WP_Error code: ' . $wp_error->get_error_code());
                if ($wp_error->get_error_data()) {
                    error_log('AIHA maybe_send_lead_notification_email: WP_Error data: ' . print_r($wp_error->get_error_data(), true));
                }
            }
        });

        $mail_result = wp_mail($recipient, $subject, $body, $headers);

        // Check PHPMailer for additional errors
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: PHPMailer object exists');
                if (!empty($phpmailer->ErrorInfo)) {
                    error_log('AIHA maybe_send_lead_notification_email: PHPMailer ErrorInfo: ' . $phpmailer->ErrorInfo);
                }
                // Verifică dacă este configurat SMTP
                if (method_exists($phpmailer, 'isSMTP') && $phpmailer->isSMTP()) {
                    error_log('AIHA maybe_send_lead_notification_email: Using SMTP - Host=' . ($phpmailer->Host ?? 'not set') . ', Port=' . ($phpmailer->Port ?? 'not set'));
                } else {
                    error_log('AIHA maybe_send_lead_notification_email: WARNING - Using PHP mail() function (not SMTP)');
                    error_log('AIHA maybe_send_lead_notification_email: PHP mail() may be blocked by server or emails may go to spam');
                    error_log('AIHA maybe_send_lead_notification_email: SOLUTION: Install SMTP plugin (WP Mail SMTP, Easy WP SMTP, etc.)');
                }
                if (method_exists($phpmailer, 'getSMTPInstance')) {
                    $smtp = $phpmailer->getSMTPInstance();
                    if ($smtp && method_exists($smtp, 'getError')) {
                        $smtp_error = $smtp->getError();
                        if ($smtp_error) {
                            error_log('AIHA maybe_send_lead_notification_email: SMTP Error: ' . print_r($smtp_error, true));
                        }
                    }
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA maybe_send_lead_notification_email: WARNING - PHPMailer object not available');
            }
        }

        // Log result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($mail_result) {
                error_log('AIHA maybe_send_lead_notification_email: SUCCESS - wp_mail returned true for ' . $recipient);
                error_log('AIHA maybe_send_lead_notification_email: NOTE - If email not received, check:');
                error_log('AIHA maybe_send_lead_notification_email: 1. Spam/junk folder');
                error_log('AIHA maybe_send_lead_notification_email: 2. Server email logs');
                error_log('AIHA maybe_send_lead_notification_email: 3. Install SMTP plugin for reliable email delivery');
            } else {
                error_log('AIHA maybe_send_lead_notification_email: FAILED - wp_mail returned false for ' . $recipient);
            }
        }
    }

    /**
     * Get user IP address
     */
    private function get_user_ip()
    {
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

    /**
     * Get a conversation for display in modal
     */
    public function handle_get_conversation()
    {
        check_ajax_referer('aiha_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have the required permission'));
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => 'Invalid conversation ID'));
        }

        $conversation = AIHA_Database::get_conversation_by_id($conversation_id);

        if (!$conversation) {
            wp_send_json_error(array('message' => 'Conversation not found'));
        }

        // Parse JSON if it exists
        $messages = array();
        if (!empty($conversation->conversation_json)) {
            $messages = json_decode($conversation->conversation_json, true);
            if (!is_array($messages)) {
                $messages = array();
            }
        }

        // If JSON doesn't exist, load from messages table
        if (empty($messages)) {
            $messages_raw = AIHA_Database::get_conversation_history($conversation_id, 1000);
            foreach ($messages_raw as $msg) {
                $messages[] = array(
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'created_at' => $msg->created_at ?? ''
                );
            }
        }

        wp_send_json_success(array(
            'conversation' => $conversation,
            'messages' => $messages
        ));
    }

    /**
     * Delete a conversation
     */
    public function handle_delete_conversation()
    {
        check_ajax_referer('aiha_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have the required permission'));
        }

        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if (!$conversation_id) {
            wp_send_json_error(array('message' => 'Invalid conversation ID'));
        }

        $result = AIHA_Database::delete_conversation($conversation_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Conversation deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Error deleting conversation'));
        }
    }

    /**
     * Delete multiple conversations (bulk)
     */
    public function handle_delete_conversations_bulk()
    {
        check_ajax_referer('aiha_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have the required permission'));
        }

        $conversation_ids = isset($_POST['conversation_ids']) ? $_POST['conversation_ids'] : array();

        if (empty($conversation_ids) || !is_array($conversation_ids)) {
            wp_send_json_error(array('message' => 'No conversations selected'));
        }

        $conversation_ids = array_map('intval', $conversation_ids);
        $conversation_ids = array_filter($conversation_ids);

        if (empty($conversation_ids)) {
            wp_send_json_error(array('message' => 'Invalid IDs'));
        }

        $result = AIHA_Database::delete_conversations($conversation_ids);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf('%d conversations deleted', count($conversation_ids)),
                'deleted_count' => count($conversation_ids)
            ));
        } else {
            wp_send_json_error(array('message' => 'Error deleting conversations'));
        }
    }
    
    /**
     * Normalize text spacing: remove multiple consecutive empty lines
     * Keep maximum one empty line between paragraphs
     * @param string $text - Text to normalize
     * @return string - Normalized text
     */
    private function normalize_text_spacing($text) {
        if (empty($text)) {
            return $text;
        }
        
        // Remove all carriage return characters
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        
        // Remove multiple consecutive empty lines (2+ empty lines become max 1)
        // Use regex to replace 2+ consecutive newlines with max 1 newline (without empty line)
        $text = preg_replace('/\n{2,}/', "\n", $text);
        
        // Remove multiple consecutive spaces (keep max 1 space)
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remove spaces from start and end of each line
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);
        
        // Remove empty lines from start and end
        $text = trim($text);
        
        return $text;
    }
}
