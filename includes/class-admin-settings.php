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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php settings_fields('aiha_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('Google Gemini API Key', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="api_key" 
                                   name="aiha_settings[api_key]" 
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="AIza...">
                            <p class="description"><?php _e('Obține cheia API de la <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="model"><?php _e('Model Gemini', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <select id="model" name="aiha_settings[model]">
                                <!-- Gemini 1.5 Series -->
                                <optgroup label="Gemini 1.5 Series">
                                    <option value="gemini-1.5-flash" <?php selected($settings['model'] ?? '', 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash (Fast, Efficient)</option>
                                    <option value="gemini-1.5-pro" <?php selected($settings['model'] ?? '', 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro (Balanced)</option>
                                </optgroup>
                                
                                <!-- Gemini 2.0 Series -->
                                <optgroup label="Gemini 2.0 Series">
                                    <option value="gemini-2.0-flash" <?php selected($settings['model'] ?? '', 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash (Multimodal)</option>
                                    <option value="gemini-2.0-flash-lite" <?php selected($settings['model'] ?? '', 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash-Lite (Cost-Efficient)</option>
                                    <option value="gemini-2.0-pro" <?php selected($settings['model'] ?? '', 'gemini-2.0-pro'); ?>>Gemini 2.0 Pro (Advanced Reasoning)</option>
                                </optgroup>
                                
                                <!-- Gemini 2.5 Series -->
                                <optgroup label="Gemini 2.5 Series">
                                    <option value="gemini-2.5-pro" <?php selected($settings['model'] ?? '', 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro (Enhanced Reasoning)</option>
                                    <option value="gemini-2.5-flash" <?php selected($settings['model'] ?? '', 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Fast)</option>
                                    <option value="gemini-2.5-flash-lite" <?php selected($settings['model'] ?? '', 'gemini-2.5-flash-lite'); ?>>Gemini 2.5 Flash-Lite (Lightweight)</option>
                                </optgroup>
                                
                                <!-- Gemini 3.0 Series -->
                                <optgroup label="Gemini 3.0 Series">
                                    <option value="gemini-3.0-pro" <?php selected($settings['model'] ?? '', 'gemini-3.0-pro'); ?>>Gemini 3.0 Pro (Most Powerful)</option>
                                    <option value="gemini-3.0-deep-think" <?php selected($settings['model'] ?? '', 'gemini-3.0-deep-think'); ?>>Gemini 3.0 Deep Think (Premium, Testing)</option>
                                </optgroup>
                                
                                <!-- Legacy Models -->
                                <optgroup label="Legacy Models">
                                    <option value="gemini-pro" <?php selected($settings['model'] ?? '', 'gemini-pro'); ?>>Gemini Pro (Legacy)</option>
                                </optgroup>
                            </select>
                            <p class="description"><?php _e('Selectează modelul Gemini. Modelele Flash sunt mai rapide și mai eficiente, iar modelele Pro oferă performanță superioară pentru sarcini complexe.', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="company_name"><?php _e('Nume Firmă', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="company_name" 
                                   name="aiha_settings[company_name]" 
                                   value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Numele firmei tale care va apărea în mesajele chatbot-ului', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="hero_message"><?php _e('Mesaj Inițial Hero', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea id="hero_message" 
                                      name="aiha_settings[hero_message]" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($settings['hero_message'] ?? 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?'); ?></textarea>
                            <p class="description"><?php _e('Folosește {company_name} pentru a insera numele firmei', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ai_instructions"><?php _e('Instrucțiuni AI', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea id="ai_instructions" 
                                      name="aiha_settings[ai_instructions]" 
                                      rows="8" 
                                      class="large-text"><?php echo esc_textarea($settings['ai_instructions'] ?? ''); ?></textarea>
                            <p class="description"><?php _e('Instrucțiuni detaliate pentru comportamentul AI. Poți include informații despre servicii, prețuri, procese, etc.', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="documentation_files"><?php _e('Documentație (Fișiere)', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="file" 
                                   id="documentation_files" 
                                   name="aiha_documentation[]" 
                                   multiple 
                                   accept=".pdf,.doc,.docx,.txt">
                            <p class="description"><?php _e('Încarcă documente PDF, DOC sau TXT care conțin informații despre serviciile firmei. Acestea vor fi folosite pentru a instrui AI-ul.', 'ai-hero-assistant'); ?></p>
                            <?php if (!empty($settings['documentation_files'])): ?>
                                <ul style="margin-top: 10px;">
                                    <?php foreach ($settings['documentation_files'] as $index => $file_url): ?>
                                        <li style="margin-bottom: 5px;">
                                            <a href="<?php echo esc_url($file_url); ?>" target="_blank"><?php echo esc_html(basename($file_url)); ?></a>
                                            <a href="<?php echo esc_url(add_query_arg(array('aiha_remove_file' => $index, 'aiha_nonce' => wp_create_nonce('aiha_remove_file')))); ?>" 
                                               style="color: #dc3232; margin-left: 10px; text-decoration: none;">[Șterge]</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gradient_start"><?php _e('Culoare Gradient Start', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="gradient_start" 
                                   name="aiha_settings[gradient_start]" 
                                   value="<?php echo esc_attr($settings['gradient_start'] ?? '#6366f1'); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gradient_end"><?php _e('Culoare Gradient End', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="gradient_end" 
                                   name="aiha_settings[gradient_end]" 
                                   value="<?php echo esc_attr($settings['gradient_end'] ?? '#ec4899'); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="font_family"><?php _e('Font Family', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <select id="font_family" name="aiha_settings[font_family]">
                                <option value="Inter, sans-serif" <?php selected($settings['font_family'] ?? '', 'Inter, sans-serif'); ?>>Inter</option>
                                <option value="Roboto, sans-serif" <?php selected($settings['font_family'] ?? '', 'Roboto, sans-serif'); ?>>Roboto</option>
                                <option value="Open Sans, sans-serif" <?php selected($settings['font_family'] ?? '', 'Open Sans, sans-serif'); ?>>Open Sans</option>
                                <option value="Lato, sans-serif" <?php selected($settings['font_family'] ?? '', 'Lato, sans-serif'); ?>>Lato</option>
                                <option value="Poppins, sans-serif" <?php selected($settings['font_family'] ?? '', 'Poppins, sans-serif'); ?>>Poppins</option>
                                <option value="Montserrat, sans-serif" <?php selected($settings['font_family'] ?? '', 'Montserrat, sans-serif'); ?>>Montserrat</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="video_silence_url"><?php _e('Video URL (Silence)', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="video_silence_url" 
                                   name="aiha_settings[video_silence_url]" 
                                   value="<?php echo esc_attr($settings['video_silence_url'] ?? ''); ?>" 
                                   class="regular-text"
                                   placeholder="https://example.com/videos/tacere.mp4">
                            <p class="description"><?php _e('URL-ul videoclipului cu persoana care tace. Acest video va fi afișat când AI nu vorbește.', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="video_speaking_url"><?php _e('Video URL (Speaking)', 'ai-hero-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="video_speaking_url" 
                                   name="aiha_settings[video_speaking_url]" 
                                   value="<?php echo esc_attr($settings['video_speaking_url'] ?? ''); ?>" 
                                   class="regular-text"
                                   placeholder="https://example.com/videos/vorbire.mp4">
                            <p class="description"><?php _e('URL-ul videoclipului cu persoana care vorbește. Acest video va fi afișat când AI răspunde.', 'ai-hero-assistant'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Leads Capturate', 'ai-hero-assistant'); ?></h2>
            <?php
            $leads = AIHA_Database::get_all_leads(50);
        if (!empty($leads)):
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Email', 'ai-hero-assistant'); ?></th>
                            <th><?php _e('Telefon', 'ai-hero-assistant'); ?></th>
                            <th><?php _e('Nume', 'ai-hero-assistant'); ?></th>
                            <th><?php _e('IP', 'ai-hero-assistant'); ?></th>
                            <th><?php _e('Data', 'ai-hero-assistant'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?php echo esc_html($lead->email ?: '-'); ?></td>
                                <td><?php echo esc_html($lead->phone ?: '-'); ?></td>
                                <td><?php echo esc_html($lead->name ?: '-'); ?></td>
                                <td><?php echo esc_html($lead->user_ip); ?></td>
                                <td><?php echo esc_html($lead->conversation_date); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('Nu există leads capturate încă.', 'ai-hero-assistant'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
