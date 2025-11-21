<?php

/**
 * Clasă pentru gestionarea bazei de date
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Database
{
    /**
     * Creează tabelele necesare în baza de date
     */
    public static function create_tables()
    {
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
            conversation_json longtext,
            message_count int(11) UNSIGNED DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_ip (user_ip),
            KEY created_at (created_at),
            KEY message_count (message_count)
        ) $charset_collate;";

        // Adaugă câmpurile noi dacă nu există (pentru upgrade)
        $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN IF NOT EXISTS conversation_json longtext AFTER user_agent");
        $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN IF NOT EXISTS message_count int(11) UNSIGNED DEFAULT 0 AFTER conversation_json");

        // Adaugă indexuri dacă nu există
        $wpdb->query("ALTER TABLE $table_conversations ADD INDEX IF NOT EXISTS created_at (created_at)");
        $wpdb->query("ALTER TABLE $table_conversations ADD INDEX IF NOT EXISTS message_count (message_count)");

        // Tabel pentru mesaje
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL,
            content text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at),
            FULLTEXT KEY content_search (content),
            FOREIGN KEY (conversation_id) REFERENCES $table_conversations(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Adaugă indexuri dacă nu există
        $wpdb->query("ALTER TABLE $table_messages ADD INDEX IF NOT EXISTS created_at (created_at)");
        // FULLTEXT index pentru căutare rapidă în conținut (doar dacă nu există)
        $index_exists = $wpdb->get_var("SHOW INDEX FROM $table_messages WHERE Key_name = 'content_search'");
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $table_messages ADD FULLTEXT INDEX content_search (content)");
        }

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
        
        // Migrare: actualizează message_count pentru conversațiile existente
        self::migrate_message_counts();
    }
    
    /**
     * Migrare: actualizează message_count pentru conversațiile existente
     */
    public static function migrate_message_counts()
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';
        
        // Obține conversațiile care au message_count = 0 sau NULL
        $conversations = $wpdb->get_results(
            "SELECT id FROM $table_conv WHERE message_count = 0 OR message_count IS NULL LIMIT 100"
        );
        
        foreach ($conversations as $conv) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_messages WHERE conversation_id = %d",
                $conv->id
            ));
            
            if ($count > 0) {
                $wpdb->update(
                    $table_conv,
                    array('message_count' => intval($count)),
                    array('id' => $conv->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }

    /**
     * Creează sau returnează o conversație existentă
     */
    public static function get_or_create_conversation($session_id, $user_ip, $user_agent = '')
    {
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
                'user_agent' => $user_agent,
                'message_count' => 0
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Salvează un mesaj în conversație
     */
    public static function save_message($conversation_id, $role, $content)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Salvează mesajul în tabelul messages
        $result = $wpdb->insert(
            $table_messages,
            array(
                'conversation_id' => $conversation_id,
                'role' => $role,
                'content' => $content
            ),
            array('%d', '%s', '%s')
        );

        // Actualizează counter-ul de mesaje (optimizat pentru performanță)
        if ($result) {
            self::update_message_count($conversation_id);
            // Actualizează JSON-ul doar dacă este necesar (lazy update - doar când se cere conversația)
            // Pentru performanță optimă, JSON-ul se actualizează la cerere, nu la fiecare mesaj
        }

        return $result;
    }

    /**
     * Actualizează counter-ul de mesaje (rapid, fără JSON)
     */
    public static function update_message_count($conversation_id)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Numără mesajele rapid
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_messages WHERE conversation_id = %d",
            $conversation_id
        ));

        // Actualizează counter-ul
        $wpdb->update(
            $table_conversations,
            array('message_count' => intval($count)),
            array('id' => $conversation_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Actualizează JSON-ul conversației cu toate mesajele (doar când este necesar)
     * Această funcție este apelată doar când se cere conversația completă
     */
    public static function update_conversation_json($conversation_id)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Obține toate mesajele
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content, created_at 
            FROM $table_messages 
            WHERE conversation_id = %d 
            ORDER BY created_at ASC",
            $conversation_id
        ));

        // Construiește array-ul pentru JSON
        $conversation_data = array();
        foreach ($messages as $message) {
            $conversation_data[] = array(
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at
            );
        }

        // Salvează ca JSON
        $json_data = json_encode($conversation_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Actualizează în baza de date
        $wpdb->update(
            $table_conversations,
            array('conversation_json' => $json_data),
            array('id' => $conversation_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Obține istoricul conversației
     */
    public static function get_conversation_history($conversation_id, $limit = 20)
    {
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
    public static function save_lead($conversation_id, $email = '', $phone = '', $name = '')
    {
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
    public static function get_all_leads($limit = 100)
    {
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

    /**
     * Obține toate conversațiile cu paginare și filtrare (optimizat pentru performanță)
     */
    public static function get_all_conversations($limit = 50, $offset = 0, $filters = array())
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';

        $where = array('1=1');
        $where_values = array();
        $joins = array();

        // Filtrare după IP (folosește index)
        if (!empty($filters['ip'])) {
            $where[] = "c.user_ip LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['ip']) . '%';
        }

        // Filtrare după dată (folosește index created_at)
        if (!empty($filters['date_from'])) {
            $where[] = "c.created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }

        // Filtrare după număr de mesaje
        if (!empty($filters['message_count_min'])) {
            $where[] = "c.message_count >= %d";
            $where_values[] = intval($filters['message_count_min']);
        }
        if (!empty($filters['message_count_max'])) {
            $where[] = "c.message_count <= %d";
            $where_values[] = intval($filters['message_count_max']);
        }

        // Filtrare după text în mesaje (optimizat cu JOIN în loc de EXISTS pentru performanță)
        if (!empty($filters['search'])) {
            // Folosește FULLTEXT search dacă este disponibil, altfel LIKE
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';

            // Verifică dacă FULLTEXT este disponibil
            $has_fulltext = $wpdb->get_var("SHOW INDEX FROM $table_messages WHERE Key_name = 'content_search'");

            if ($has_fulltext && strlen($filters['search']) > 3) {
                // Folosește FULLTEXT pentru căutare rapidă
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id";
                $where[] = "MATCH(m.content) AGAINST(%s IN BOOLEAN MODE)";
                $where_values[] = $filters['search'];
            } else {
                // Folosește LIKE cu JOIN pentru performanță mai bună decât EXISTS
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id AND m.content LIKE %s";
                $where_values[] = $search_term;
            }
        }

        $where_clause = implode(' AND ', $where);
        $join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';

        // Adaugă JOIN pentru lead-uri dacă filtrăm după lead-uri
        $table_leads = $wpdb->prefix . 'aiha_leads';
        if (!empty($filters['has_leads'])) {
            if ($filters['has_leads'] === 'yes') {
                $joins[] = "INNER JOIN $table_leads l ON l.conversation_id = c.id";
            } elseif ($filters['has_leads'] === 'no') {
                $joins[] = "LEFT JOIN $table_leads l ON l.conversation_id = c.id";
                $where[] = "l.id IS NULL";
            }
        }
        
        // Query optimizat: folosește message_count din tabel și include info despre lead-uri
        $query = "SELECT DISTINCT c.*,
                  CASE WHEN EXISTS (SELECT 1 FROM $table_leads l WHERE l.conversation_id = c.id) THEN 1 ELSE 0 END as has_lead
                  FROM $table_conv c
                  $join_clause
                  WHERE $where_clause
                  ORDER BY c.created_at DESC
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $results = $wpdb->get_results($query) ?: array();
        
        // Actualizează message_count pentru conversațiile care nu au counter-ul actualizat
        foreach ($results as $conv) {
            if ($conv->message_count == 0) {
                // Verifică dacă există mesaje
                $actual_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_messages WHERE conversation_id = %d",
                    $conv->id
                ));
                if ($actual_count > 0) {
                    // Actualizează counter-ul
                    $wpdb->update(
                        $table_conv,
                        array('message_count' => intval($actual_count)),
                        array('id' => $conv->id),
                        array('%d'),
                        array('%d')
                    );
                    $conv->message_count = intval($actual_count);
                }
            }
        }
        
        return $results;
    }

    /**
     * Obține o conversație după ID cu JSON-ul (actualizează JSON-ul dacă este necesar)
     */
    public static function get_conversation_by_id($conversation_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';

        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $conversation_id
        ));

        // Dacă JSON-ul nu există sau este vechi, îl actualizează (lazy update)
        if ($conversation && (empty($conversation->conversation_json) || $conversation->message_count != substr_count($conversation->conversation_json, '"role"'))) {
            self::update_conversation_json($conversation_id);
            // Reîncarcă conversația cu JSON actualizat
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $conversation_id
            ));
        }

        return $conversation;
    }

    /**
     * Șterge o conversație (și mesajele asociate prin CASCADE)
     */
    public static function delete_conversation($conversation_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';

        return $wpdb->delete(
            $table,
            array('id' => $conversation_id),
            array('%d')
        );
    }

    /**
     * Șterge multiple conversații
     */
    public static function delete_conversations($conversation_ids)
    {
        if (empty($conversation_ids) || !is_array($conversation_ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';

        $ids = array_map('intval', $conversation_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $query = $wpdb->prepare(
            "DELETE FROM $table WHERE id IN ($placeholders)",
            ...$ids
        );

        return $wpdb->query($query);
    }

    /**
     * Numără totalul conversațiilor cu filtrare (optimizat)
     */
    public static function count_conversations($filters = array())
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';

        $where = array('1=1');
        $where_values = array();
        $joins = array();

        // Aceleași filtre ca în get_all_conversations
        if (!empty($filters['ip'])) {
            $where[] = "c.user_ip LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['ip']) . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = "c.created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['message_count_min'])) {
            $where[] = "c.message_count >= %d";
            $where_values[] = intval($filters['message_count_min']);
        }
        if (!empty($filters['message_count_max'])) {
            $where[] = "c.message_count <= %d";
            $where_values[] = intval($filters['message_count_max']);
        }
        
        // Filtrare după lead-uri
        if (!empty($filters['has_leads'])) {
            // Se va face prin JOIN în query-ul principal
        }
        
        if (!empty($filters['search'])) {
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $has_fulltext = $wpdb->get_var("SHOW INDEX FROM $table_messages WHERE Key_name = 'content_search'");

            if ($has_fulltext && strlen($filters['search']) > 3) {
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id";
                $where[] = "MATCH(m.content) AGAINST(%s IN BOOLEAN MODE)";
                $where_values[] = $filters['search'];
            } else {
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id AND m.content LIKE %s";
                $where_values[] = $search_term;
            }
        }

        // Adaugă JOIN pentru lead-uri dacă filtrăm după lead-uri
        $table_leads = $wpdb->prefix . 'aiha_leads';
        if (!empty($filters['has_leads'])) {
            if ($filters['has_leads'] === 'yes') {
                $joins[] = "INNER JOIN $table_leads l ON l.conversation_id = c.id";
            } elseif ($filters['has_leads'] === 'no') {
                $joins[] = "LEFT JOIN $table_leads l ON l.conversation_id = c.id";
                $where[] = "l.id IS NULL";
            }
        }
        
        $where_clause = implode(' AND ', $where);
        $join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';
        
        $query = "SELECT COUNT(DISTINCT c.id) FROM $table_conv c $join_clause WHERE $where_clause";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }
}
