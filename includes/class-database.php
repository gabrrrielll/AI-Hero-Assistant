<?php
/**
 * Clasă pentru gestionarea bazei de date
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Database {
    
    /**
     * Creează tabelele necesare în baza de date
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_conversations = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_leads = $wpdb->prefix . 'aiha_leads';
        
        // Tabel pentru conversații
        $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_ip (user_ip)
        ) $charset_collate;";
        
        // Tabel pentru mesaje
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL,
            content text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            FOREIGN KEY (conversation_id) REFERENCES $table_conversations(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabel pentru leads (telefon/email capturate)
        $sql_leads = "CREATE TABLE IF NOT EXISTS $table_leads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255),
            phone varchar(50),
            name varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY email (email),
            KEY phone (phone),
            FOREIGN KEY (conversation_id) REFERENCES $table_conversations(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_conversations);
        dbDelta($sql_messages);
        dbDelta($sql_leads);
    }
    
    /**
     * Creează sau returnează o conversație existentă
     */
    public static function get_or_create_conversation($session_id, $user_ip, $user_agent = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';
        
        // Caută conversația existentă
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s LIMIT 1",
            $session_id
        ));
        
        if ($conversation) {
            return $conversation->id;
        }
        
        // Creează conversație nouă
        $wpdb->insert(
            $table,
            array(
                'session_id' => $session_id,
                'user_ip' => $user_ip,
                'user_agent' => $user_agent
            ),
            array('%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Salvează un mesaj în conversație
     */
    public static function save_message($conversation_id, $role, $content) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_messages';
        
        return $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id,
                'role' => $role,
                'content' => $content
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Obține istoricul conversației
     */
    public static function get_conversation_history($conversation_id, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_messages';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM $table 
            WHERE conversation_id = %d 
            ORDER BY created_at ASC 
            LIMIT %d",
            $conversation_id,
            $limit
        ));
    }
    
    /**
     * Salvează un lead (email/telefon)
     */
    public static function save_lead($conversation_id, $email = '', $phone = '', $name = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_leads';
        
        // Nu salva dacă nu există email sau telefon
        if (empty($email) && empty($phone)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead: Skipping - no email or phone provided');
            }
            return false;
        }
        
        // Verifică dacă lead-ul există deja
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));
        
        if ($existing) {
            // Update lead existent - actualizează doar câmpurile care nu sunt goale
            $update_data = array();
            $update_format = array();
            
            if (!empty($email) && $existing->email !== $email) {
                $update_data['email'] = $email;
                $update_format[] = '%s';
            }
            if (!empty($phone) && $existing->phone !== $phone) {
                $update_data['phone'] = $phone;
                $update_format[] = '%s';
            }
            if (!empty($name) && $existing->name !== $name) {
                $update_data['name'] = $name;
                $update_format[] = '%s';
            }
            
            if (!empty($update_data)) {
                $update_format[] = '%d'; // pentru WHERE id
                $result = $wpdb->update(
                    $table,
                    $update_data,
                    array('id' => $existing->id),
                    $update_format,
                    array('%d')
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIHA save_lead: Updated existing lead ID ' . $existing->id . ', Result: ' . ($result !== false ? 'success' : 'failed'));
                    if ($result === false) {
                        error_log('AIHA save_lead: DB Error: ' . $wpdb->last_error);
                    }
                }
                
                return $result !== false;
            }
            return true; // Nu s-a făcut update dar lead-ul există deja
        } else {
            // Creează lead nou
            $result = $wpdb->insert(
                $table,
                array(
                    'conversation_id' => $conversation_id,
                    'email' => $email ?: '',
                    'phone' => $phone ?: '',
                    'name' => $name ?: ''
                ),
                array('%d', '%s', '%s', '%s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead: Inserted new lead, ID: ' . $wpdb->insert_id . ', Result: ' . ($result !== false ? 'success' : 'failed'));
                if ($result === false) {
                    error_log('AIHA save_lead: DB Error: ' . $wpdb->last_error);
                }
            }
            
            return $result !== false;
        }
    }
    
    /**
     * Obține toate lead-urile
     */
    public static function get_all_leads($limit = 100) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'aiha_leads';
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        
        // Verifică dacă tabelele există
        $leads_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_leads'");
        $conv_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_conv'");
        
        if (!$leads_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA get_all_leads: Table does not exist: ' . $table_leads);
            }
            return array();
        }
        
        // Query simplificat fără JOIN dacă tabela conversations nu există
        if (!$conv_exists) {
            $query = $wpdb->prepare(
                "SELECT l.*, '' as user_ip, l.created_at as conversation_date 
                FROM $table_leads l
                ORDER BY l.created_at DESC
                LIMIT %d",
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT l.*, c.user_ip, c.created_at as conversation_date 
                FROM $table_leads l
                LEFT JOIN $table_conv c ON l.conversation_id = c.id
                ORDER BY l.created_at DESC
                LIMIT %d",
                $limit
            );
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA get_all_leads: Query: ' . $query);
            error_log('AIHA get_all_leads: Table leads exists: ' . ($leads_exists ? 'yes' : 'no'));
            error_log('AIHA get_all_leads: Table conv exists: ' . ($conv_exists ? 'yes' : 'no'));
        }
        
        $results = $wpdb->get_results($query, OBJECT);
        
        // Debug logging pentru rezultate
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIHA get_all_leads: Results count: ' . (is_array($results) ? count($results) : 'not array'));
            error_log('AIHA get_all_leads: Results type: ' . gettype($results));
            if ($wpdb->last_error) {
                error_log('AIHA get_all_leads: DB Error: ' . $wpdb->last_error);
            }
            if (!empty($results)) {
                error_log('AIHA get_all_leads: First result: ' . print_r($results[0], true));
            }
        }
        
        return $results ? $results : array();
    }
}



