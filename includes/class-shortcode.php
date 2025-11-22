<?php
/**
 * Clasă pentru shortcode-ul principal
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Shortcode
{
    public function __construct()
    {
        add_shortcode('ai_hero_assistant', array($this, 'render_shortcode'));
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'height' => '' // Empty by default - will use CSS responsive heights
        ), $atts);

        $settings = get_option('aiha_settings', array());
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : '';
        $hero_message = isset($settings['hero_message']) ? $settings['hero_message'] : 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?';
        $hero_message = str_replace('{company_name}', $company_name, $hero_message);

        $gradient_start = isset($settings['gradient_start']) ? $settings['gradient_start'] : '#6366f1';
        $gradient_end = isset($settings['gradient_end']) ? $settings['gradient_end'] : '#ec4899';
        $gradient_color_3 = isset($settings['gradient_color_3']) ? $settings['gradient_color_3'] : '#8b5cf6';
        $gradient_color_4 = isset($settings['gradient_color_4']) ? $settings['gradient_color_4'] : '#3b82f6';
        $animation_duration_base = isset($settings['animation_duration_base']) ? absint($settings['animation_duration_base']) : 15;
        $animation_duration_wave = isset($settings['animation_duration_wave']) ? absint($settings['animation_duration_wave']) : 20;
        $font_family = isset($settings['font_family']) ? $settings['font_family'] : 'Inter, sans-serif';
        $font_family_code = isset($settings['font_family_code']) ? $settings['font_family_code'] : 'Courier New, Courier, monospace';
        $font_size_base = isset($settings['font_size_base']) ? absint($settings['font_size_base']) : 16;

        // Video URLs from settings
        $video_silence_url = isset($settings['video_silence_url']) ? $settings['video_silence_url'] : '';
        $video_speaking_url = isset($settings['video_speaking_url']) ? $settings['video_speaking_url'] : '';

        // Voice settings
        $enable_voice = isset($settings['enable_voice']) ? (int)$settings['enable_voice'] : 0;
        $voice_name = isset($settings['voice_name']) ? $settings['voice_name'] : 'default';

        // Generăm un ID unic pentru această instanță
        $instance_id = 'aiha-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="aiha-container" data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <div class="aiha-hero-section container-fluid d-flex flex-column justify-content-between" style="--gradient-start: <?php echo esc_attr($gradient_start); ?>; --gradient-end: <?php echo esc_attr($gradient_end); ?>; --gradient-color-3: <?php echo esc_attr($gradient_color_3); ?>; --gradient-color-4: <?php echo esc_attr($gradient_color_4); ?>; --animation-duration-base: <?php echo esc_attr($animation_duration_base); ?>s; --animation-duration-wave: <?php echo esc_attr($animation_duration_wave); ?>s; --font-family: <?php echo esc_attr($font_family); ?>; --font-family-code: <?php echo esc_attr($font_family_code); ?>; --font-size-base: <?php echo esc_attr($font_size_base); ?>px<?php echo !empty($atts['height']) ? '; height: ' . esc_attr($atts['height']) . '; min-height: ' . esc_attr($atts['height']) : ''; ?>">
                <!-- Video Container - Două videoclipuri suprapuse -->
                <div class="aiha-video-container d-flex justify-content-center align-items-center flex-shrink-0 my-3">
                    <div class="position-relative" style="width: 300px; height: 300px;">
                        <!-- Video pentru tăcere (default vizibil) -->
                        <video 
                            id="aiha-video-silence-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-video aiha-video-silence position-absolute top-0 start-0 w-100 h-100" 
                            autoplay 
                            loop 
                            muted 
                            playsinline
                            style="object-fit: cover; border-radius: 50%;">
                            <?php if ($video_silence_url): ?>
                                <source src="<?php echo esc_url($video_silence_url); ?>" type="video/mp4">
                            <?php endif; ?>
                        </video>
                        
                        <!-- Video pentru vorbire (ascuns inițial) -->
                        <video 
                            id="aiha-video-speaking-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-video aiha-video-speaking position-absolute top-0 start-0 w-100 h-100" 
                            autoplay 
                            loop 
                            muted 
                            playsinline
                            style="object-fit: cover; border-radius: 50%; display: none;">
                            <?php if ($video_speaking_url): ?>
                                <source src="<?php echo esc_url($video_speaking_url); ?>" type="video/mp4">
                            <?php endif; ?>
                        </video>
                        
                        <!-- Fallback message dacă nu sunt videoclipuri -->
                        <?php if (empty($video_silence_url) && empty($video_speaking_url)): ?>
                            <div class="aiha-video-placeholder position-absolute top-50 start-50 translate-middle text-center text-white">
                                <p><?php esc_html_e('Please configure video URLs in plugin settings', 'ai-hero-assistant'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Subtitrare cu typing effect -->
                <div class="aiha-subtitle-container d-flex justify-content-center flex-grow-1 my-3" style="min-height: 0;">
                    <div class="aiha-subtitle w-100" id="aiha-subtitle-<?php echo esc_attr($instance_id); ?>" style="max-width: 800px;"></div>
                </div>
                
                <!-- Textarea pentru input -->
                <div class="aiha-input-container d-flex flex-column align-items-center flex-shrink-0 my-3 w-100">
                    <div class="aiha-input-wrapper d-flex align-items-end gap-2" style="max-width: 800px; width: 100%;">
                        <textarea 
                            id="aiha-input-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-textarea form-control" 
                            placeholder="<?php esc_attr_e('Scrieți mesajul dvs...', 'ai-hero-assistant'); ?>"
                            rows="1"></textarea>
                        <button 
                            id="aiha-send-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-send-btn btn btn-light d-flex align-items-center justify-content-center flex-shrink-0"
                            style="width: 50px; height: 50px;"
                            aria-label="<?php esc_attr_e('Trimite mesaj', 'ai-hero-assistant'); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                    <div class="aiha-loading d-none justify-content-center align-items-center gap-2 mt-2" id="aiha-loading-<?php echo esc_attr($instance_id); ?>" style="max-width: 800px; width: 100%;">
                        <span class="spinner-border spinner-border-sm text-white" role="status"></span>
                        <span class="text-white"><?php esc_html_e('Se procesează...', 'ai-hero-assistant'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="application/json" class="aiha-initial-data">
        {
            "instanceId": "<?php echo esc_js($instance_id); ?>",
            "heroMessage": <?php echo json_encode($hero_message, JSON_UNESCAPED_UNICODE); ?>,
            "gradientStart": "<?php echo esc_js($gradient_start); ?>",
            "gradientEnd": "<?php echo esc_js($gradient_end); ?>",
            "videoSilenceUrl": "<?php echo esc_js($video_silence_url); ?>",
            "videoSpeakingUrl": "<?php echo esc_js($video_speaking_url); ?>",
            "enableVoice": <?php echo $enable_voice ? 'true' : 'false'; ?>,
            "voiceName": "<?php echo esc_js($voice_name); ?>"
        }
        </script>
        <?php
        return ob_get_clean();
    }
}
