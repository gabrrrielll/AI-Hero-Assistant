<?php
/**
 * Class for formatting messages consistently across email, browser, and admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Message_Formatter
{
    /**
     * Get AI name from settings or return default
     * 
     * @return string AI name or 'AI' as default
     */
    public static function get_ai_name()
    {
        $settings = get_option('aiha_settings', array());
        $ai_name = isset($settings['ai_name']) ? trim($settings['ai_name']) : '';
        
        // Return AI name if set, otherwise default to 'AI'
        return !empty($ai_name) ? $ai_name : __('AI', 'ai-hero-assistant');
    }

    /**
     * Get user display name (default: Utilizator)
     * 
     * @return string User display name
     */
    public static function get_user_display_name()
    {
        return __('Utilizator', 'ai-hero-assistant');
    }

    /**
     * Format message sender name based on role
     * 
     * @param string $role Message role ('user' or 'assistant')
     * @return string Formatted sender name
     */
    public static function get_sender_name($role)
    {
        if ($role === 'user') {
            return self::get_user_display_name();
        } else {
            return self::get_ai_name();
        }
    }

    /**
     * Format conversation messages for email HTML
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @return string HTML formatted conversation
     */
    public static function format_conversation_for_email($messages)
    {
        if (empty($messages)) {
            return '';
        }

        $html = '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 600px; overflow-y: auto;">';

        foreach ($messages as $msg) {
            $is_user = $msg->role === 'user';
            $bg_color = $is_user ? '#0073aa' : '#f0f0f1';
            $text_color = $is_user ? '#fff' : '#000';
            $align = $is_user ? 'right' : 'left';
            $sender = self::get_sender_name($msg->role);

            $html .= '<div style="margin-bottom: 15px; text-align: ' . $align . ';">';
            $html .= '<div style="display: inline-block; max-width: 80%; background: ' . $bg_color . '; color: ' . $text_color . '; padding: 10px 15px; border-radius: 8px; text-align: left;">';
            $html .= '<div style="font-weight: bold; margin-bottom: 5px; font-size: 12px; opacity: 0.9;">' . esc_html($sender) . '</div>';
            $html .= '<div style="line-height: 1.5;">' . nl2br(esc_html($msg->content)) . '</div>';
            if (!empty($msg->created_at)) {
                $html .= '<div style="font-size: 11px; margin-top: 5px; opacity: 0.7;">' . esc_html($msg->created_at) . '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format conversation messages for admin modal (JSON format)
     * Returns array of formatted messages ready for JavaScript
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @return array Array of formatted messages
     */
    public static function format_conversation_for_admin($messages)
    {
        if (empty($messages)) {
            return array();
        }

        $formatted = array();
        foreach ($messages as $msg) {
            $formatted[] = array(
                'role' => $msg->role ?? '',
                'content' => $msg->content ?? '',
                'created_at' => $msg->created_at ?? '',
                'sender' => self::get_sender_name($msg->role ?? '')
            );
        }

        return $formatted;
    }

    /**
     * Format conversation messages for browser display (JSON format)
     * Returns array ready for JavaScript consumption
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @return array Array of formatted messages
     */
    public static function format_conversation_for_browser($messages)
    {
        if (empty($messages)) {
            return array();
        }

        $formatted = array();
        foreach ($messages as $msg) {
            $formatted[] = array(
                'role' => $msg->role ?? '',
                'content' => $msg->content ?? '',
                'created_at' => $msg->created_at ?? '',
                'sender' => self::get_sender_name($msg->role ?? '')
            );
        }

        return $formatted;
    }
}
