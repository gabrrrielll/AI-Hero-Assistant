<?php
/**
 * Clasă pentru shortcode-ul principal
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIHA_Shortcode {
    
    public function __construct() {
        add_shortcode('ai_hero_assistant', array($this, 'render_shortcode'));
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '600px'
        ), $atts);
        
        $settings = get_option('aiha_settings', array());
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : '';
        $hero_message = isset($settings['hero_message']) ? $settings['hero_message'] : 'Bună! Sunt asistentul virtual al {company_name}. Cum vă pot ajuta cu serviciile noastre de programare?';
        $hero_message = str_replace('{company_name}', $company_name, $hero_message);
        
        $gradient_start = isset($settings['gradient_start']) ? $settings['gradient_start'] : '#6366f1';
        $gradient_end = isset($settings['gradient_end']) ? $settings['gradient_end'] : '#ec4899';
        $font_family = isset($settings['font_family']) ? $settings['font_family'] : 'Inter, sans-serif';
        
        // Video URLs from settings
        $video_silence_url = isset($settings['video_silence_url']) ? $settings['video_silence_url'] : '';
        $video_speaking_url = isset($settings['video_speaking_url']) ? $settings['video_speaking_url'] : '';
        
        // Generăm un ID unic pentru această instanță
        $instance_id = 'aiha-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="aiha-container" data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <div class="aiha-hero-section" style="--gradient-start: <?php echo esc_attr($gradient_start); ?>; --gradient-end: <?php echo esc_attr($gradient_end); ?>; --font-family: <?php echo esc_attr($font_family); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
                <!-- Video Container - Două videoclipuri suprapuse -->
                <div class="aiha-video-container">
                    <!-- Video pentru tăcere (default vizibil) -->
                    <video 
                        id="aiha-video-silence-<?php echo esc_attr($instance_id); ?>" 
                        class="aiha-video aiha-video-silence" 
                        autoplay 
                        loop 
                        muted 
                        playsinline>
                        <?php if ($video_silence_url): ?>
                            <source src="<?php echo esc_url($video_silence_url); ?>" type="video/mp4">
                        <?php endif; ?>
                    </video>
                    
                    <!-- Video pentru vorbire (ascuns inițial) -->
                    <video 
                        id="aiha-video-speaking-<?php echo esc_attr($instance_id); ?>" 
                        class="aiha-video aiha-video-speaking" 
                        autoplay 
                        loop 
                        muted 
                        playsinline
                        style="display: none;">
                        <?php if ($video_speaking_url): ?>
                            <source src="<?php echo esc_url($video_speaking_url); ?>" type="video/mp4">
                        <?php endif; ?>
                    </video>
                    
                    <!-- Fallback message dacă nu sunt videoclipuri -->
                    <?php if (empty($video_silence_url) && empty($video_speaking_url)): ?>
                        <div class="aiha-video-placeholder">
                            <p><?php esc_html_e('Please configure video URLs in plugin settings', 'ai-hero-assistant'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Subtitrare cu typing effect -->
                <div class="aiha-subtitle-container">
                    <div class="aiha-subtitle" id="aiha-subtitle-<?php echo esc_attr($instance_id); ?>"></div>
                </div>
                
                <!-- Textarea pentru input -->
                <div class="aiha-input-container">
                    <div class="aiha-input-wrapper">
                        <textarea 
                            id="aiha-input-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-textarea" 
                            placeholder="<?php esc_attr_e('Scrieți mesajul dvs...', 'ai-hero-assistant'); ?>"
                            rows="3"></textarea>
                        <button 
                            id="aiha-send-<?php echo esc_attr($instance_id); ?>" 
                            class="aiha-send-btn"
                            aria-label="<?php esc_attr_e('Trimite mesaj', 'ai-hero-assistant'); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                    <div class="aiha-loading" id="aiha-loading-<?php echo esc_attr($instance_id); ?>" style="display: none;">
                        <span></span><span></span><span></span>
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
            "videoSpeakingUrl": "<?php echo esc_js($video_speaking_url); ?>"
        }
        </script>
        <?php
        return ob_get_clean();
    }
}



