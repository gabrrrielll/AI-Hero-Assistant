<?php
/**
 * Clasă pentru pagina de setări din admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Admin_Settings
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_file_uploads'));
    }

    public function add_admin_menu()
    {
        add_options_page(
            __('AI Hero Assistant Settings', 'ai-hero-assistant'),
            __('AI Hero Assistant', 'ai-hero-assistant'),
            'manage_options',
            'aiha-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('aiha_settings_group', 'aiha_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();

        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['model'] = sanitize_text_field($input['model'] ?? 'gemini-1.5-flash');
        $sanitized['company_name'] = sanitize_text_field($input['company_name'] ?? '');
        $sanitized['ai_instructions'] = wp_kses_post($input['ai_instructions'] ?? '');
        $sanitized['gradient_start'] = sanitize_hex_color($input['gradient_start'] ?? '#6366f1');
        $sanitized['gradient_end'] = sanitize_hex_color($input['gradient_end'] ?? '#ec4899');
        $sanitized['font_family'] = sanitize_text_field($input['font_family'] ?? 'Inter, sans-serif');
        $sanitized['hero_message'] = sanitize_textarea_field($input['hero_message'] ?? '');
        $sanitized['video_silence_url'] = esc_url_raw($input['video_silence_url'] ?? '');
        $sanitized['video_speaking_url'] = esc_url_raw($input['video_speaking_url'] ?? '');
        $sanitized['assistant_gender'] = sanitize_text_field($input['assistant_gender'] ?? 'feminin');

        // Păstrează fișierele existente
        $current_settings = get_option('aiha_settings', array());
        $sanitized['documentation_files'] = $current_settings['documentation_files'] ?? array();

        return $sanitized;
    }

    /**
     * Procesează upload-urile de fișiere separat
     */
    public function handle_file_uploads()
    {
        // Handle file removal
        if (isset($_GET['aiha_remove_file']) && isset($_GET['aiha_nonce'])) {
            if (wp_verify_nonce($_GET['aiha_nonce'], 'aiha_remove_file')) {
                $index = intval($_GET['aiha_remove_file']);
                $settings = get_option('aiha_settings', array());
                if (isset($settings['documentation_files'][$index])) {
                    unset($settings['documentation_files'][$index]);
                    $settings['documentation_files'] = array_values($settings['documentation_files']);
                    update_option('aiha_settings', $settings);
                }
                wp_redirect(remove_query_arg(array('aiha_remove_file', 'aiha_nonce')));
                exit;
            }
        }

        // Handle file uploads
        if (!empty($_FILES['aiha_documentation']['tmp_name']) && is_array($_FILES['aiha_documentation']['tmp_name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');

            $uploaded_files = array();
            $current_settings = get_option('aiha_settings', array());
            $existing_files = $current_settings['documentation_files'] ?? array();

            foreach ($_FILES['aiha_documentation']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['aiha_documentation']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $_FILES['aiha_documentation']['name'][$key],
                        'type' => $_FILES['aiha_documentation']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['aiha_documentation']['error'][$key],
                        'size' => $_FILES['aiha_documentation']['size'][$key]
                    );

                    $upload = wp_handle_upload($file, array('test_form' => false));
                    if ($upload && !isset($upload['error'])) {
                        $uploaded_files[] = $upload['url'];
                    }
                }
            }

            if (!empty($uploaded_files)) {
                $merged_files = array_merge($existing_files, $uploaded_files);
                $current_settings['documentation_files'] = array_unique($merged_files);
                update_option('aiha_settings', $current_settings);
            }
        }
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('aiha_settings', array());
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'conversations';
        ?>
        <div class="wrap aiha-admin-wrap">
            <h1 class="mb-4"><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Bootstrap Tabs -->
            <ul class="nav nav-tabs mb-4" id="aihaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'conversations' ? 'active' : ''; ?>" 
                            id="conversations-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#conversations" 
                            type="button" 
                            role="tab" 
                            aria-controls="conversations" 
                            aria-selected="<?php echo $active_tab === 'conversations' ? 'true' : 'false'; ?>">
                        <?php _e('Conversații', 'ai-hero-assistant'); ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
                            id="settings-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#settings" 
                            type="button" 
                            role="tab" 
                            aria-controls="settings" 
                            aria-selected="<?php echo $active_tab === 'settings' ? 'true' : 'false'; ?>">
                        <?php _e('Setări', 'ai-hero-assistant'); ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="aihaTabContent">
                <!-- Conversații Tab -->
                <div class="tab-pane fade <?php echo $active_tab === 'conversations' ? 'show active' : ''; ?>" 
                     id="conversations" 
                     role="tabpanel" 
                     aria-labelledby="conversations-tab">
                    
                    <!-- Filtrare Conversații -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h2 class="h4 mb-0"><?php _e('Filtrare Conversații', 'ai-hero-assistant'); ?></h2>
                        </div>
                        <div class="card-body">
                            <form id="aiha-conversations-filter" class="row g-3">
                                <div class="col-md-2">
                                    <label for="filter_ip" class="form-label"><?php _e('IP', 'ai-hero-assistant'); ?></label>
                                    <input type="text" id="filter_ip" name="ip" class="form-control" value="<?php echo esc_attr($filters['ip'] ?? ''); ?>" placeholder="<?php esc_attr_e('Filtrează după IP', 'ai-hero-assistant'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_from" class="form-label"><?php _e('De la dată', 'ai-hero-assistant'); ?></label>
                                    <input type="date" id="filter_date_from" name="date_from" class="form-control" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_to" class="form-label"><?php _e('Până la dată', 'ai-hero-assistant'); ?></label>
                                    <input type="date" id="filter_date_to" name="date_to" class="form-control" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_message_count_min" class="form-label"><?php _e('Min. mesaje', 'ai-hero-assistant'); ?></label>
                                    <input type="number" id="filter_message_count_min" name="message_count_min" class="form-control" value="<?php echo esc_attr($filters['message_count_min'] ?? ''); ?>" min="0" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_message_count_max" class="form-label"><?php _e('Max. mesaje', 'ai-hero-assistant'); ?></label>
                                    <input type="number" id="filter_message_count_max" name="message_count_max" class="form-control" value="<?php echo esc_attr($filters['message_count_max'] ?? ''); ?>" min="0" placeholder="∞">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_search" class="form-label"><?php _e('Caută în mesaje', 'ai-hero-assistant'); ?></label>
                                    <input type="text" id="filter_search" name="search" class="form-control" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" placeholder="<?php esc_attr_e('Caută text...', 'ai-hero-assistant'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_has_leads" class="form-label"><?php _e('Are Lead-uri', 'ai-hero-assistant'); ?></label>
                                    <select id="filter_has_leads" name="has_leads" class="form-select">
                                        <option value=""><?php _e('Toate', 'ai-hero-assistant'); ?></option>
                                        <option value="yes" <?php selected($filters['has_leads'] ?? '', 'yes'); ?>><?php _e('Da', 'ai-hero-assistant'); ?></option>
                                        <option value="no" <?php selected($filters['has_leads'] ?? '', 'no'); ?>><?php _e('Nu', 'ai-hero-assistant'); ?></option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><?php _e('Filtrează', 'ai-hero-assistant'); ?></button>
                                    <button type="button" id="reset-filters" class="btn btn-secondary"><?php _e('Resetează', 'ai-hero-assistant'); ?></button>
                                    <span class="ms-3 text-muted">
                                        <strong><?php echo number_format($total_conversations); ?></strong> <?php _e('conversații găsite', 'ai-hero-assistant'); ?>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lista Conversații -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0"><?php _e('Conversații', 'ai-hero-assistant'); ?></h2>
                            <div>
                                <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-sm" disabled>
                                    <i class="dashicons dashicons-trash"></i> <?php _e('Șterge selectate', 'ai-hero-assistant'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="conversations-list-container">
                                <?php
                                // Obține filtrele din URL sau POST (toate criteriile)
                                $filters = array(
                                    'ip' => isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : '',
                                    'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
                                    'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
                                    'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
                                    'message_count_min' => isset($_GET['message_count_min']) ? intval($_GET['message_count_min']) : '',
                                    'message_count_max' => isset($_GET['message_count_max']) ? intval($_GET['message_count_max']) : '',
                                    'has_leads' => isset($_GET['has_leads']) ? sanitize_text_field($_GET['has_leads']) : ''
                                );

        // Elimină filtrele goale
        $filters = array_filter($filters, function ($value) {
            return $value !== '' && $value !== null;
        });

        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $conversations = AIHA_Database::get_all_conversations($per_page, $offset, $filters);
        $total_conversations = AIHA_Database::count_conversations($filters);
        $total_pages = ceil($total_conversations / $per_page);

        if (!empty($conversations)):
            ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="conversations-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="40">
                                                        <input type="checkbox" id="select-all-conversations" class="form-check-input">
                                                    </th>
                                                    <th><?php _e('ID', 'ai-hero-assistant'); ?></th>
                                                    <th><?php _e('IP', 'ai-hero-assistant'); ?></th>
                                                    <th><?php _e('Mesaje', 'ai-hero-assistant'); ?></th>
                                                    <th><?php _e('Lead-uri', 'ai-hero-assistant'); ?></th>
                                                    <th><?php _e('Data', 'ai-hero-assistant'); ?></th>
                                                    <th width="150"><?php _e('Acțiuni', 'ai-hero-assistant'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                global $wpdb;
            $table_leads = $wpdb->prefix . 'aiha_leads';

            foreach ($conversations as $conv):
                // Obține lead-urile pentru această conversație
                $leads = $wpdb->get_results($wpdb->prepare(
                    "SELECT email, phone, name FROM $table_leads WHERE conversation_id = %d LIMIT 5",
                    $conv->id
                ));
                $has_lead = !empty($leads);

                // Verifică și actualizează message_count dacă este 0 dar există mesaje
                if (($conv->message_count ?? 0) == 0) {
                    $actual_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}aiha_messages WHERE conversation_id = %d",
                        $conv->id
                    ));
                    if ($actual_count > 0) {
                        AIHA_Database::update_message_count($conv->id);
                        $conv->message_count = intval($actual_count);
                    }
                }
                ?>
                                                    <tr data-conversation-id="<?php echo esc_attr($conv->id); ?>">
                                                        <td>
                                                            <input type="checkbox" class="form-check-input conversation-checkbox" value="<?php echo esc_attr($conv->id); ?>">
                                                        </td>
                                                        <td><strong>#<?php echo esc_html($conv->id); ?></strong></td>
                                                        <td><code><?php echo esc_html($conv->user_ip); ?></code></td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo esc_html($conv->message_count ?? 0); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($has_lead): ?>
                                                                <span class="badge bg-success" title="<?php esc_attr_e('Are lead-uri capturate', 'ai-hero-assistant'); ?>">
                                                                    <i class="dashicons dashicons-yes"></i> <?php echo count($leads); ?>
                                                                </span>
                                                                <?php if (count($leads) > 0): ?>
                                                                    <div class="small text-muted mt-1">
                                                                        <?php foreach ($leads as $lead): ?>
                                                                            <?php if (!empty($lead->email)): ?>
                                                                                <div><i class="dashicons dashicons-email"></i> <?php echo esc_html($lead->email); ?></div>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($lead->phone)): ?>
                                                                                <div><i class="dashicons dashicons-phone"></i> <?php echo esc_html($lead->phone); ?></div>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><?php _e('Nu', 'ai-hero-assistant'); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($conv->created_at))); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-info view-conversation" data-conversation-id="<?php echo esc_attr($conv->id); ?>">
                                                                <i class="dashicons dashicons-visibility"></i> <?php _e('Vezi', 'ai-hero-assistant'); ?>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger delete-conversation" data-conversation-id="<?php echo esc_attr($conv->id); ?>">
                                                                <i class="dashicons dashicons-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Paginare conversații">
                                            <ul class="pagination justify-content-center">
                                                <?php
                            $base_url = remove_query_arg('paged');
                                        for ($i = 1; $i <= $total_pages; $i++):
                                            $url = add_query_arg('paged', $i, $base_url);
                                            ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="dashicons dashicons-info"></i> <?php _e('Nu există conversații.', 'ai-hero-assistant'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal pentru afișarea conversației -->
                    <div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="conversationModalLabel"><?php _e('Conversație', 'ai-hero-assistant'); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="conversation-modal-body">
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden"><?php _e('Se încarcă...', 'ai-hero-assistant'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php _e('Închide', 'ai-hero-assistant'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Setări Tab -->
                <div class="tab-pane fade <?php echo $active_tab === 'settings' ? 'show active' : ''; ?>" 
                     id="settings" 
                     role="tabpanel" 
                     aria-labelledby="settings-tab">
                    <form method="post" action="options.php" enctype="multipart/form-data">
                        <?php settings_fields('aiha_settings_group'); ?>
                        
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h2 class="h4 mb-0"><?php _e('Configurare Generală', 'ai-hero-assistant'); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- API Key -->
                                    <div class="col-12">
                                        <label for="api_key" class="form-label fw-bold"><?php _e('Google Gemini API Key', 'ai-hero-assistant'); ?></label>
                                        <input type="text" 
                                               id="api_key" 
                                               name="aiha_settings[api_key]" 
                                               value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                                               class="form-control" 
                                               placeholder="AIza...">
                                        <div class="form-text"><?php _e('Obține cheia API de la <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>', 'ai-hero-assistant'); ?></div>
                                    </div>
                    
                                    <!-- Model & Company Name -->
                                    <div class="col-md-6">
                                        <label for="model" class="form-label fw-bold"><?php _e('Model Gemini', 'ai-hero-assistant'); ?></label>
                                        <select id="model" name="aiha_settings[model]" class="form-select">
                                            <optgroup label="Gemini 1.5 Series">
                                                <option value="gemini-1.5-flash" <?php selected($settings['model'] ?? '', 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash (Fast, Efficient)</option>
                                                <option value="gemini-1.5-pro" <?php selected($settings['model'] ?? '', 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro (Balanced)</option>
                                            </optgroup>
                                            <optgroup label="Gemini 2.0 Series">
                                                <option value="gemini-2.0-flash" <?php selected($settings['model'] ?? '', 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash (Multimodal)</option>
                                                <option value="gemini-2.0-flash-lite" <?php selected($settings['model'] ?? '', 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash-Lite (Cost-Efficient)</option>
                                                <option value="gemini-2.0-pro" <?php selected($settings['model'] ?? '', 'gemini-2.0-pro'); ?>>Gemini 2.0 Pro (Advanced Reasoning)</option>
                                            </optgroup>
                                            <optgroup label="Gemini 2.5 Series">
                                                <option value="gemini-2.5-pro" <?php selected($settings['model'] ?? '', 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro (Enhanced Reasoning)</option>
                                                <option value="gemini-2.5-flash" <?php selected($settings['model'] ?? '', 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Fast)</option>
                                                <option value="gemini-2.5-flash-lite" <?php selected($settings['model'] ?? '', 'gemini-2.5-flash-lite'); ?>>Gemini 2.5 Flash-Lite (Lightweight)</option>
                                            </optgroup>
                                            <optgroup label="Gemini 3.0 Series">
                                                <option value="gemini-3.0-pro" <?php selected($settings['model'] ?? '', 'gemini-3.0-pro'); ?>>Gemini 3.0 Pro (Most Powerful)</option>
                                                <option value="gemini-3.0-deep-think" <?php selected($settings['model'] ?? '', 'gemini-3.0-deep-think'); ?>>Gemini 3.0 Deep Think (Premium, Testing)</option>
                                            </optgroup>
                                            <optgroup label="Legacy Models">
                                                <option value="gemini-pro" <?php selected($settings['model'] ?? '', 'gemini-pro'); ?>>Gemini Pro (Legacy)</option>
                                            </optgroup>
                                        </select>
                                        <div class="form-text"><?php _e('Selectează modelul Gemini. Modelele Flash sunt mai rapide și mai eficiente, iar modelele Pro oferă performanță superioară pentru sarcini complexe.', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="company_name" class="form-label fw-bold"><?php _e('Nume Firmă', 'ai-hero-assistant'); ?></label>
                                        <input type="text" 
                                               id="company_name" 
                                               name="aiha_settings[company_name]" 
                                               value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>" 
                                               class="form-control">
                                        <div class="form-text"><?php _e('Numele firmei tale care va apărea în mesajele chatbot-ului', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <!-- Hero Message -->
                                    <div class="col-12">
                                        <label for="hero_message" class="form-label fw-bold"><?php _e('Mesaj Inițial Hero', 'ai-hero-assistant'); ?></label>
                                        <textarea id="hero_message" 
                                                  name="aiha_settings[hero_message]" 
                                                  rows="3" 
                                                  class="form-control"><?php echo esc_textarea($settings['hero_message'] ?? 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?'); ?></textarea>
                                        <div class="form-text"><?php _e('Folosește {company_name} pentru a insera numele firmei', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <!-- AI Instructions -->
                                    <div class="col-12">
                                        <label for="ai_instructions" class="form-label fw-bold"><?php _e('Instrucțiuni AI', 'ai-hero-assistant'); ?></label>
                                        <textarea id="ai_instructions" 
                                                  name="aiha_settings[ai_instructions]" 
                                                  rows="8" 
                                                  class="form-control"><?php echo esc_textarea($settings['ai_instructions'] ?? ''); ?></textarea>
                                        <div class="form-text"><?php _e('Instrucțiuni detaliate pentru comportamentul AI. Poți include informații despre servicii, prețuri, procese, etc.', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <!-- Documentation Files -->
                                    <div class="col-12">
                                        <label for="documentation_files" class="form-label fw-bold"><?php _e('Documentație (Fișiere)', 'ai-hero-assistant'); ?></label>
                                        <input type="file" 
                                               id="documentation_files" 
                                               name="aiha_documentation[]" 
                                               multiple 
                                               accept=".pdf,.doc,.docx,.txt"
                                               class="form-control">
                                        <div class="form-text"><?php _e('Încarcă documente PDF, DOC sau TXT care conțin informații despre serviciile firmei. Acestea vor fi folosite pentru a instrui AI-ul.', 'ai-hero-assistant'); ?></div>
                                        <?php if (!empty($settings['documentation_files'])): ?>
                                            <div class="mt-3">
                                                <ul class="list-group">
                                                    <?php foreach ($settings['documentation_files'] as $index => $file_url): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="text-decoration-none">
                                                                <i class="dashicons dashicons-media-document"></i> <?php echo esc_html(basename($file_url)); ?>
                                                            </a>
                                                            <a href="<?php echo esc_url(add_query_arg(array('aiha_remove_file' => $index, 'aiha_nonce' => wp_create_nonce('aiha_remove_file')))); ?>" 
                                                               class="btn btn-sm btn-outline-danger">
                                                                <i class="dashicons dashicons-trash"></i> Șterge
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Gradient Colors -->
                                    <div class="col-md-6">
                                        <label for="gradient_start" class="form-label fw-bold"><?php _e('Culoare Gradient Start', 'ai-hero-assistant'); ?></label>
                                        <input type="color" 
                                               id="gradient_start" 
                                               name="aiha_settings[gradient_start]" 
                                               value="<?php echo esc_attr($settings['gradient_start'] ?? '#6366f1'); ?>"
                                               class="form-control form-control-color">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="gradient_end" class="form-label fw-bold"><?php _e('Culoare Gradient End', 'ai-hero-assistant'); ?></label>
                                        <input type="color" 
                                               id="gradient_end" 
                                               name="aiha_settings[gradient_end]" 
                                               value="<?php echo esc_attr($settings['gradient_end'] ?? '#ec4899'); ?>"
                                               class="form-control form-control-color">
                                    </div>
                                    
                                    <!-- Font Family -->
                                    <div class="col-md-6">
                                        <label for="font_family" class="form-label fw-bold"><?php _e('Font Family', 'ai-hero-assistant'); ?></label>
                                        <select id="font_family" name="aiha_settings[font_family]" class="form-select">
                                            <option value="Inter, sans-serif" <?php selected($settings['font_family'] ?? '', 'Inter, sans-serif'); ?>>Inter</option>
                                            <option value="Roboto, sans-serif" <?php selected($settings['font_family'] ?? '', 'Roboto, sans-serif'); ?>>Roboto</option>
                                            <option value="Open Sans, sans-serif" <?php selected($settings['font_family'] ?? '', 'Open Sans, sans-serif'); ?>>Open Sans</option>
                                            <option value="Lato, sans-serif" <?php selected($settings['font_family'] ?? '', 'Lato, sans-serif'); ?>>Lato</option>
                                            <option value="Poppins, sans-serif" <?php selected($settings['font_family'] ?? '', 'Poppins, sans-serif'); ?>>Poppins</option>
                                            <option value="Montserrat, sans-serif" <?php selected($settings['font_family'] ?? '', 'Montserrat, sans-serif'); ?>>Montserrat</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Assistant Gender -->
                                    <div class="col-md-6">
                                        <label for="assistant_gender" class="form-label fw-bold"><?php _e('Gen Asistent Virtual', 'ai-hero-assistant'); ?></label>
                                        <select id="assistant_gender" name="aiha_settings[assistant_gender]" class="form-select">
                                            <option value="feminin" <?php selected($settings['assistant_gender'] ?? 'feminin', 'feminin'); ?>><?php _e('Feminin', 'ai-hero-assistant'); ?></option>
                                            <option value="masculin" <?php selected($settings['assistant_gender'] ?? 'feminin', 'masculin'); ?>><?php _e('Masculin', 'ai-hero-assistant'); ?></option>
                                        </select>
                                        <div class="form-text"><?php _e('Selectează genul asistentului virtual. Acest lucru va influența modul în care AI-ul se exprimă (ex: "bucuroasă" vs "bucuros").', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <!-- Video URLs -->
                                    <div class="col-md-6">
                                        <label for="video_silence_url" class="form-label fw-bold"><?php _e('Video URL (Silence)', 'ai-hero-assistant'); ?></label>
                                        <input type="url" 
                                               id="video_silence_url" 
                                               name="aiha_settings[video_silence_url]" 
                                               value="<?php echo esc_attr($settings['video_silence_url'] ?? ''); ?>" 
                                               class="form-control"
                                               placeholder="https://example.com/videos/tacere.mp4">
                                        <div class="form-text"><?php _e('URL-ul videoclipului cu persoana care tace. Acest video va fi afișat când AI nu vorbește.', 'ai-hero-assistant'); ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="video_speaking_url" class="form-label fw-bold"><?php _e('Video URL (Speaking)', 'ai-hero-assistant'); ?></label>
                                        <input type="url" 
                                               id="video_speaking_url" 
                                               name="aiha_settings[video_speaking_url]" 
                                               value="<?php echo esc_attr($settings['video_speaking_url'] ?? ''); ?>" 
                                               class="form-control"
                                               placeholder="https://example.com/videos/vorbire.mp4">
                                        <div class="form-text"><?php _e('URL-ul videoclipului cu persoana care vorbește. Acest video va fi afișat când AI răspunde.', 'ai-hero-assistant'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <?php submit_button(__('Salvează Setările', 'ai-hero-assistant'), 'primary', 'submit', false, array('class' => 'btn btn-primary')); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
