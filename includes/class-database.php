<?php

/**
 * Class for database management
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Database
{
    /**
     * Create necessary database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_conversations = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_leads = $wpdb->prefix . 'aiha_leads';

        // Table for conversations
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

        // Add new fields if they don't exist (for upgrade) - manual check for compatibility
        $columns = $wpdb->get_col("DESCRIBE $table_conversations");

        if (!in_array('conversation_json', $columns)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN conversation_json longtext AFTER user_agent");
        }

        if (!in_array('message_count', $columns)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN message_count int(11) UNSIGNED DEFAULT 0 AFTER conversation_json");
        }

        // Add indexes if they don't exist - manual check
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_conversations");
        $index_names = array();
        foreach ($indexes as $index) {
            $index_names[] = $index->Key_name;
        }

        if (!in_array('created_at', $index_names)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD INDEX created_at (created_at)");
        }

        if (!in_array('message_count', $index_names)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD INDEX message_count (message_count)");
        }

        // Table for messages
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

        // Add indexes if they don't exist
        $wpdb->query("ALTER TABLE $table_messages ADD INDEX IF NOT EXISTS created_at (created_at)");
        // FULLTEXT index for fast content search (only if it doesn't exist)
        $index_exists = $wpdb->get_var("SHOW INDEX FROM $table_messages WHERE Key_name = 'content_search'");
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $table_messages ADD FULLTEXT INDEX content_search (content)");
        }

        // Table for leads (captured phone/email)
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

        // Migration: update message_count for existing conversations
        self::migrate_message_counts();
    }

    /**
     * Ensure schema is up to date (for upgrades)
     */
    public static function ensure_schema_up_to_date()
    {
        global $wpdb;
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_conversations'") === $table_conversations;

        if (!$table_exists) {
            // If table doesn't exist, create it completely
            self::create_tables();
            return;
        }

        // Check existing columns
        $columns = $wpdb->get_col("DESCRIBE $table_conversations");

        // Add missing columns
        if (!in_array('conversation_json', $columns)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN conversation_json longtext AFTER user_agent");
        }

        if (!in_array('message_count', $columns)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD COLUMN message_count int(11) UNSIGNED DEFAULT 0 AFTER conversation_json");
        }

        // Check indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_conversations");
        $index_names = array();
        foreach ($indexes as $index) {
            $index_names[] = $index->Key_name;
        }

        if (!in_array('created_at', $index_names)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD INDEX created_at (created_at)");
        }

        if (!in_array('message_count', $index_names)) {
            $wpdb->query("ALTER TABLE $table_conversations ADD INDEX message_count (message_count)");
        }
    }

    /**
     * Migration: update message_count for existing conversations
     */
    public static function migrate_message_counts()
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';

        // Get conversations that have message_count = 0 or NULL
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
     * Create or return an existing conversation
     */
    public static function get_or_create_conversation($session_id, $user_ip, $user_agent = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';

        // Search for existing conversation
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s LIMIT 1",
            $session_id
        ));

        if ($conversation) {
            return $conversation->id;
        }

        // Check if message_count column exists
        $columns = $wpdb->get_col("DESCRIBE $table");
        $has_message_count = in_array('message_count', $columns);

        // Create new conversation
        $insert_data = array(
            'session_id' => $session_id,
            'user_ip' => $user_ip,
            'user_agent' => $user_agent
        );
        $insert_format = array('%s', '%s', '%s');

        // Add message_count only if column exists
        if ($has_message_count) {
            $insert_data['message_count'] = 0;
            $insert_format[] = '%d';
        }

        $result = $wpdb->insert(
            $table,
            $insert_data,
            $insert_format
        );

        if ($result === false) {
            // If insertion failed due to missing column, try to add it and retry
            if (strpos($wpdb->last_error, 'message_count') !== false) {
                // Add column if missing
                $wpdb->query("ALTER TABLE $table ADD COLUMN message_count int(11) UNSIGNED DEFAULT 0");
                // Retry insertion without message_count to avoid error
                $wpdb->insert(
                    $table,
                    array(
                        'session_id' => $session_id,
                        'user_ip' => $user_ip,
                        'user_agent' => $user_agent
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }

        return $wpdb->insert_id;
    }

    /**
     * Save a message in conversation
     */
    public static function save_message($conversation_id, $role, $content)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Validation
        if (empty($conversation_id) || empty($role) || empty($content)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_message: Invalid parameters - conversation_id=' . $conversation_id . ', role=' . $role . ', content_length=' . strlen($content));
            }
            return false;
        }

        // Save message in messages table
        $result = $wpdb->insert(
            $table_messages,
            array(
                'conversation_id' => $conversation_id,
                'role' => $role,
                'content' => $content
            ),
            array('%d', '%s', '%s')
        );

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($result === false) {
                error_log('AIHA save_message: Failed to insert message - ' . $wpdb->last_error);
            } else {
                error_log('AIHA save_message: Success - conversation_id=' . $conversation_id . ', role=' . $role . ', message_id=' . $wpdb->insert_id);
            }
        }

        // Update message counter (optimized for performance)
        if ($result) {
            self::update_message_count($conversation_id);
            // Update JSON only if necessary (lazy update - only when conversation is requested)
            // For optimal performance, JSON is updated on demand, not on every message
        }

        return $result;
    }

    /**
     * Update message counter (fast, without JSON)
     */
    public static function update_message_count($conversation_id)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Count messages quickly
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_messages WHERE conversation_id = %d",
            $conversation_id
        ));

        // Update counter
        $update_result = $wpdb->update(
            $table_conversations,
            array('message_count' => intval($count)),
            array('id' => $conversation_id),
            array('%d'),
            array('%d')
        );

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($update_result === false) {
                error_log('AIHA update_message_count: Failed to update - ' . $wpdb->last_error . ', conversation_id=' . $conversation_id . ', count=' . $count);
            } else {
                error_log('AIHA update_message_count: Success - conversation_id=' . $conversation_id . ', count=' . $count);
            }
        }
    }

    /**
     * Update conversation JSON with all messages (only when necessary)
     * This function is called only when full conversation is requested
     */
    public static function update_conversation_json($conversation_id)
    {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiha_messages';
        $table_conversations = $wpdb->prefix . 'aiha_conversations';

        // Get all messages
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content, created_at 
            FROM $table_messages 
            WHERE conversation_id = %d 
            ORDER BY created_at ASC",
            $conversation_id
        ));

        // Build array for JSON
        $conversation_data = array();
        foreach ($messages as $message) {
            $conversation_data[] = array(
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at
            );
        }

        // Save as JSON
        $json_data = json_encode($conversation_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Update in database
        $wpdb->update(
            $table_conversations,
            array('conversation_json' => $json_data),
            array('id' => $conversation_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Get conversation history
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
     * Save a lead (email/phone)
     */
    public static function save_lead($conversation_id, $email = '', $phone = '', $name = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_leads';

        // Don't save if no email or phone
        if (empty($email) && empty($phone)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA save_lead: Skipping - no email or phone provided');
            }
            return false;
        }

        // Check if lead already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));

        if ($existing) {
            // Update existing lead - update only non-empty fields
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
                $update_format[] = '%d'; // for WHERE id
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
            return true; // No update made but lead already exists
        } else {
            // Create new lead
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
     * Get all leads
     */
    public static function get_all_leads($limit = 100)
    {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'aiha_leads';
        $table_conv = $wpdb->prefix . 'aiha_conversations';

        // Check if tables exist
        $leads_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_leads'");
        $conv_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_conv'");

        if (!$leads_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIHA get_all_leads: Table does not exist: ' . $table_leads);
            }
            return array();
        }

        // Simplified query without JOIN if conversations table doesn't exist
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

        // Debug logging for results
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
     * Get all conversations with pagination and filtering (optimized for performance)
     */
    public static function get_all_conversations($limit = 50, $offset = 0, $filters = array())
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';

        $where = array('1=1');
        $where_values = array();
        $joins = array();

        // Filter by IP (uses index)
        if (!empty($filters['ip'])) {
            $where[] = "c.user_ip LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['ip']) . '%';
        }

        // Filter by date (uses created_at index)
        if (!empty($filters['date_from'])) {
            $where[] = "c.created_at >= %s";
            $where_values[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "c.created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }

        // Filter by message count (allows 0)
        if (isset($filters['message_count_min'])) {
            $where[] = "c.message_count >= %d";
            $where_values[] = intval($filters['message_count_min']);
        }
        if (isset($filters['message_count_max'])) {
            $where[] = "c.message_count <= %d";
            $where_values[] = intval($filters['message_count_max']);
        }

        // Filter by leads (must be before search for JOIN)
        $table_leads = $wpdb->prefix . 'aiha_leads';
        if (isset($filters['has_leads']) && $filters['has_leads'] !== '' && $filters['has_leads'] !== null) {
            if ($filters['has_leads'] === 'yes') {
                $joins[] = "INNER JOIN $table_leads l_filter ON l_filter.conversation_id = c.id";
            } elseif ($filters['has_leads'] === 'no') {
                $joins[] = "LEFT JOIN $table_leads l_filter ON l_filter.conversation_id = c.id";
                $where[] = "l_filter.id IS NULL";
            }
        }

        // Filter by text in messages (optimized with JOIN instead of EXISTS for performance)
        if (!empty($filters['search'])) {
            // Use FULLTEXT search if available, otherwise LIKE
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';

            // Check if FULLTEXT is available
            $has_fulltext = $wpdb->get_var("SHOW INDEX FROM $table_messages WHERE Key_name = 'content_search'");

            if ($has_fulltext && strlen($filters['search']) > 3) {
                // Use FULLTEXT for fast search
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id";
                $where[] = "MATCH(m.content) AGAINST(%s IN BOOLEAN MODE)";
                $where_values[] = $filters['search'];
            } else {
                // Use LIKE with JOIN for better performance than EXISTS
                $joins[] = "INNER JOIN $table_messages m ON m.conversation_id = c.id AND m.content LIKE %s";
                $where_values[] = $search_term;
            }
        }

        $where_clause = implode(' AND ', $where);
        $join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';

        // Optimized query: uses message_count from table and includes lead info
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

        // Update message_count for conversations that don't have updated counter
        foreach ($results as $conv) {
            if ($conv->message_count == 0) {
                // Check if messages exist
                $actual_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_messages WHERE conversation_id = %d",
                    $conv->id
                ));
                if ($actual_count > 0) {
                    // Update counter
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
     * Get a conversation by ID with JSON (updates JSON if necessary)
     */
    public static function get_conversation_by_id($conversation_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiha_conversations';

        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $conversation_id
        ));

        // If JSON doesn't exist or is old, update it (lazy update)
        if ($conversation && (empty($conversation->conversation_json) || $conversation->message_count != substr_count($conversation->conversation_json, '"role"'))) {
            self::update_conversation_json($conversation_id);
            // Reload conversation with updated JSON
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $conversation_id
            ));
        }

        return $conversation;
    }

    /**
     * Delete a conversation (and associated messages via CASCADE)
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
     * Delete multiple conversations
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
     * Count total conversations with filtering (optimized)
     */
    public static function count_conversations($filters = array())
    {
        global $wpdb;
        $table_conv = $wpdb->prefix . 'aiha_conversations';
        $table_messages = $wpdb->prefix . 'aiha_messages';

        $where = array('1=1');
        $where_values = array();
        $joins = array();

        // Same filters as in get_all_conversations
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
        // Filter by message count (allows 0)
        if (isset($filters['message_count_min'])) {
            $where[] = "c.message_count >= %d";
            $where_values[] = intval($filters['message_count_min']);
        }
        if (isset($filters['message_count_max'])) {
            $where[] = "c.message_count <= %d";
            $where_values[] = intval($filters['message_count_max']);
        }

        // Filter by leads (must be before search for JOIN)
        $table_leads = $wpdb->prefix . 'aiha_leads';
        if (isset($filters['has_leads']) && $filters['has_leads'] !== '' && $filters['has_leads'] !== null) {
            if ($filters['has_leads'] === 'yes') {
                $joins[] = "INNER JOIN $table_leads l_filter ON l_filter.conversation_id = c.id";
            } elseif ($filters['has_leads'] === 'no') {
                $joins[] = "LEFT JOIN $table_leads l_filter ON l_filter.conversation_id = c.id";
                $where[] = "l_filter.id IS NULL";
            }
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

        $where_clause = implode(' AND ', $where);
        $join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';

        $query = "SELECT COUNT(DISTINCT c.id) FROM $table_conv c $join_clause WHERE $where_clause";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }
}
