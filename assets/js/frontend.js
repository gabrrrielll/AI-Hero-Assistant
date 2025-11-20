/**
 * AI Hero Assistant - Frontend JavaScript
 * NEW VERSION: Uses two overlapping videos instead of particle system
 */

(function($) {
    'use strict';
    
    class AIHeroAssistant {
        constructor(instanceId, config) {
            this.instanceId = instanceId;
            this.config = config;
            
            // Video elements
            this.videoContainer = document.querySelector(`#${instanceId} .aiha-video-container`);
            this.videoSilence = document.getElementById(`aiha-video-silence-${instanceId}`);
            this.videoSpeaking = document.getElementById(`aiha-video-speaking-${instanceId}`);
            
            // UI elements
            this.subtitleEl = document.getElementById(`aiha-subtitle-${instanceId}`);
            this.inputEl = document.getElementById(`aiha-input-${instanceId}`);
            this.sendBtn = document.getElementById(`aiha-send-${instanceId}`);
            this.loadingEl = document.getElementById(`aiha-loading-${instanceId}`);
            
            this.isSpeaking = false;
            this.currentText = '';
            this.sessionId = this.generateSessionId();
            
            this.init();
        }
        
        generateSessionId() {
            return 'aiha_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        init() {
            this.setupVideos();
            this.setupEventListeners();
            this.showInitialMessage();
            // Start with silent state
            this.setSilentState();
        }
        
        setupVideos() {
            // Ensure videos are loaded and playing
            if (this.videoSilence) {
                this.videoSilence.addEventListener('loadeddata', () => {
                    this.videoSilence.play().catch(e => console.log('Video play error:', e));
                });
            }
            
            if (this.videoSpeaking) {
                this.videoSpeaking.addEventListener('loadeddata', () => {
                    this.videoSpeaking.play().catch(e => console.log('Video play error:', e));
                });
            }
        }
        
        /**
         * Set video container to silent state (show silence video)
         */
        setSilentState() {
            if (this.videoContainer) {
                this.videoContainer.classList.remove('speaking');
                this.videoContainer.classList.add('silent');
            }
            this.isSpeaking = false;
        }
        
        /**
         * Set video container to speaking state (show speaking video)
         */
        setSpeakingState() {
            if (this.videoContainer) {
                this.videoContainer.classList.remove('silent');
                this.videoContainer.classList.add('speaking');
            }
            this.isSpeaking = true;
        }
        
        setupEventListeners() {
            // Send button
            if (this.sendBtn) {
                this.sendBtn.addEventListener('click', () => this.sendMessage());
            }
            
            // Enter key (Shift+Enter pentru new line)
            if (this.inputEl) {
                this.inputEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
                
                // Auto-resize textarea
                this.inputEl.addEventListener('input', () => {
                    this.inputEl.style.height = 'auto';
                    this.inputEl.style.height = Math.min(this.inputEl.scrollHeight, 120) + 'px';
                });
            }
        }
        
        showInitialMessage() {
            setTimeout(() => {
                this.typeText(this.config.heroMessage, () => {
                    // After initial message, go back to silent
                    setTimeout(() => {
                        this.setSilentState();
                    }, 500);
                });
            }, 1000);
        }
        
        /**
         * Scroll automat la finalul subtitle-ului
         */
        scrollToBottom() {
            if (this.subtitleEl) {
                // Scroll smooth la final
                this.subtitleEl.scrollTop = this.subtitleEl.scrollHeight;
            }
        }
        
        typeText(text, callback) {
            // Switch to speaking state when typing starts
            this.setSpeakingState();
            
            this.currentText = '';
            if (this.subtitleEl) {
                this.subtitleEl.innerHTML = '';
                // Reset scroll la început
                this.subtitleEl.scrollTop = 0;
            }
            
            let index = 0;
            const typingSpeed = 30; // milliseconds per character
            
            const typeChar = () => {
                if (index < text.length) {
                    this.currentText += text[index];
                    if (this.subtitleEl) {
                        this.subtitleEl.innerHTML = this.currentText + '<span class="typing-cursor"></span>';
                        // Scroll automat la fiecare caracter (doar când e aproape de final)
                        if (index % 10 === 0 || index === text.length - 1) {
                            this.scrollToBottom();
                        }
                    }
                    index++;
                    setTimeout(typeChar, typingSpeed);
                } else {
                    if (this.subtitleEl) {
                        this.subtitleEl.innerHTML = this.currentText;
                        // Scroll final pentru a vedea tot textul
                        this.scrollToBottom();
                    }
                    if (callback) callback();
                }
            };
            
            typeChar();
        }
        
        async sendMessage() {
            const message = this.inputEl ? this.inputEl.value.trim() : '';
            if (!message || (this.loadingEl && this.loadingEl.style.display !== 'none')) {
                return;
            }
            
            // Clear subtitle and send to AI
            if (this.subtitleEl) {
                this.subtitleEl.innerHTML = '';
            }
            if (this.inputEl) {
                this.inputEl.value = '';
                this.inputEl.style.height = 'auto';
            }
            
            this.sendToAI(message);
        }
        
        async sendToAI(message) {
            if (this.loadingEl) {
                this.loadingEl.style.display = 'flex';
            }
            if (this.sendBtn) {
                this.sendBtn.disabled = true;
            }
            
            try {
                const response = await $.ajax({
                    url: aihaData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aiha_send_message',
                        nonce: aihaData.nonce,
                        message: message,
                        session_id: this.sessionId
                    }
                });
                
                if (response.success) {
                    this.typeText(response.data.message, () => {
                        // After AI finishes speaking, switch back to silent state
                        setTimeout(() => {
                            this.setSilentState();
                            if (this.loadingEl) {
                                this.loadingEl.style.display = 'none';
                            }
                            if (this.sendBtn) {
                                this.sendBtn.disabled = false;
                            }
                        }, 500);
                    });
                } else {
                    throw new Error(response.data.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.typeText('Sorry, an error occurred. Please try again.', () => {
                    this.setSilentState();
                    if (this.loadingEl) {
                        this.loadingEl.style.display = 'none';
                    }
                    if (this.sendBtn) {
                        this.sendBtn.disabled = false;
                    }
                });
            }
        }
    }
    
    // OLD PARTICLE SYSTEM CODE - COMMENTED OUT
    /*
    // ============================================
    // OLD PARTICLE SYSTEM IMPLEMENTATION
    // This code is kept for reference but not used
    // ============================================
    
    setupCanvas() {
        const container = this.canvas.parentElement;
        const size = Math.min(container.offsetWidth, 300);
        this.canvas.width = size;
        this.canvas.height = size;
        this.centerX = this.canvas.width / 2;
        this.centerY = this.canvas.height / 2;
    }
    
    createParticles() {
        const particleCount = 300;
        const radius = Math.min(this.canvas.width, this.canvas.height) / 2 - 30;
        
        for (let i = 0; i < particleCount; i++) {
            const angle = Math.random() * Math.PI * 2;
            const distance = radius * (0.3 + Math.random() * 0.7);
            const x = this.centerX + Math.cos(angle) * distance;
            const y = this.centerY + Math.sin(angle) * distance;
            
            const speed = 0.5 + Math.random() * 1.5;
            const angleVel = Math.random() * Math.PI * 2;
            
            this.particles.push({
                x: x,
                y: y,
                baseX: x,
                baseY: y,
                vx: Math.cos(angleVel) * speed,
                vy: Math.sin(angleVel) * speed,
                size: 1.5 + Math.random() * 2.5,
                opacity: 0.4 + Math.random() * 0.6,
                targetX: x,
                targetY: y,
                angle: angle,
                angleSpeed: (Math.random() - 0.5) * 0.02,
                radius: distance,
                pulsePhase: Math.random() * Math.PI * 2,
                noiseOffset: Math.random() * 1000
            });
        }
    }
    
    noise(x, y, time) {
        return Math.sin(x * 0.01 + time) * Math.cos(y * 0.01 + time) * 0.5 + 0.5;
    }
    
    animate() {
        // ... particle animation code ...
        // This entire function is commented out as we're using videos now
    }
    */
    
    // Initialize all instances
    $(document).ready(function() {
        $('.aiha-container').each(function() {
            const instanceId = $(this).attr('id');
            const configData = $(this).siblings('.aiha-initial-data').text();
            
            if (configData && instanceId) {
                try {
                    const config = JSON.parse(configData);
                    new AIHeroAssistant(instanceId, config);
                } catch (e) {
                    console.error('Error parsing AI Hero Assistant config:', e);
                }
            }
        });
    });
    
})(jQuery);
