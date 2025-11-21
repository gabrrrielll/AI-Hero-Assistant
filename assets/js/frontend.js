/**
 * AI Hero Assistant - Frontend JavaScript
 * Uses two overlapping videos that alternate based on AI speaking state
 */

(function ($) {
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
                // Folosește requestAnimationFrame pentru scroll smooth și corect
                requestAnimationFrame(() => {
                    // Scroll la final cu un mic delay pentru a permite DOM-ului să se actualizeze
                    setTimeout(() => {
                        if (this.subtitleEl) {
                            this.subtitleEl.scrollTop = this.subtitleEl.scrollHeight;
                        }
                    }, 10);
                });
            }
        }

        /**
         * Formatează textul pentru afișare (markdown simplu -> HTML)
         */
        formatMessageText(text) {
            if (!text) return '';
            
            let formatted = text;
            
            // Escapă HTML-ul existent pentru siguranță
            const div = document.createElement('div');
            div.textContent = formatted;
            formatted = div.innerHTML;
            
            // Convertește **bold** în <strong>
            formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            
            // Convertește *italic* în <em> (doar dacă nu este la început de linie pentru liste)
            formatted = formatted.replace(/(?<!^|\n)\*([^*\n]+?)\*(?!\*)/g, '<em>$1</em>');
            
            // Convertește liste cu bullet points (* sau -)
            // Format: * item sau - item
            const lines = formatted.split('\n');
            let inList = false;
            let result = [];
            
            lines.forEach((line, index) => {
                const trimmed = line.trim();
                const isListItem = /^[\*\-\•]\s+(.+)$/.test(trimmed);
                
                if (isListItem) {
                    if (!inList) {
                        result.push('<ul class="aiha-message-list">');
                        inList = true;
                    }
                    const content = trimmed.replace(/^[\*\-\•]\s+/, '');
                    result.push('<li>' + content + '</li>');
                } else {
                    if (inList) {
                        result.push('</ul>');
                        inList = false;
                    }
                    if (trimmed) {
                        result.push('<p class="aiha-message-paragraph">' + trimmed + '</p>');
                    } else {
                        result.push('<br>');
                    }
                }
            });
            
            if (inList) {
                result.push('</ul>');
            }
            
            formatted = result.join('');
            
            // Convertește newlines rămase în <br>
            formatted = formatted.replace(/\n/g, '<br>');
            
            return formatted;
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
            const formattedText = this.formatMessageText(text);

            const typeChar = () => {
                if (index < text.length) {
                    this.currentText += text[index];
                    if (this.subtitleEl) {
                        // Formatează textul parțial, dar păstrează formatarea corectă
                        // Folosim text simplu pentru typing, apoi formatăm la final
                        const displayText = this.currentText.replace(/\n/g, '<br>');
                        this.subtitleEl.innerHTML = displayText + '<span class="typing-cursor"></span>';
                        // Scroll automat mai frecvent pentru text lung
                        if (index % 5 === 0 || index === text.length - 1) {
                            this.scrollToBottom();
                        }
                    }
                    index++;
                    setTimeout(typeChar, typingSpeed);
                } else {
                    if (this.subtitleEl) {
                        // La final, afișează textul complet formatat cu toate stilurile
                        this.subtitleEl.innerHTML = formattedText;
                        // Scroll final cu delay pentru a permite DOM-ului să se actualizeze
                        setTimeout(() => {
                            this.scrollToBottom();
                        }, 50);
                    }
                    if (callback) callback();
                    // După ce se termină typing-ul, trec la silent state imediat (fără delay)
                    // pentru a evita flash-ul
                    this.setSilentState();
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
                        // setSilentState() este apelat direct în typeText callback pentru tranziție smooth
                        if (this.loadingEl) {
                            this.loadingEl.style.display = 'none';
                        }
                        if (this.sendBtn) {
                            this.sendBtn.disabled = false;
                        }
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


    // Initialize all instances
    $(document).ready(function () {
        $('.aiha-container').each(function () {
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
