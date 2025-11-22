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
            
            // Voice settings
            this.enableVoice = config.enableVoice || false;
            this.voiceName = config.voiceName || 'default';
            this.synth = null;
            this.voices = [];
            this.selectedVoice = null;
            this.userHasInteracted = false; // Track if user has interacted (required for Chrome autoplay policy)
            this.pendingMessageForSpeech = null; // Store message for speech synthesis
            this.isSpeechActive = false; // Track if speech is currently active

            this.init();
        }

        generateSessionId() {
            return 'aiha_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        init() {
            this.setupVideos();
            this.setupEventListeners();
            this.setupVoice();
            this.showInitialMessage();
            // Start with silent state
            this.setSilentState();
        }

        /**
         * Initialize text-to-speech
         */
        setupVoice() {
            if (!('speechSynthesis' in window)) {
                console.warn('Speech synthesis not supported in this browser');
                return;
            }

            this.synth = window.speechSynthesis;

            // Load voices (may need to wait for voices to be loaded)
            const loadVoices = () => {
                this.voices = this.synth.getVoices();
                this.selectVoice();
            };

            // Chrome loads voices asynchronously
            if (this.synth.onvoiceschanged !== undefined) {
                this.synth.onvoiceschanged = loadVoices;
            }

            // Try to load voices immediately
            loadVoices();
        }

        /**
         * Select the voice based on voiceName setting
         */
        selectVoice() {
            if (!this.voices || this.voices.length === 0) {
                console.warn('No voices available');
                return;
            }

            // Log available voices for debugging
            console.log('Available voices:', this.voices.map(v => ({ name: v.name, lang: v.lang, default: v.default })));
            console.log('Looking for voice:', this.voiceName);

            if (this.voiceName === 'default') {
                // Use default voice (usually first available)
                this.selectedVoice = this.voices.find(v => v.default) || this.voices[0];
                console.log('Using default voice:', this.selectedVoice?.name);
            } else {
                // Try multiple matching strategies

                // 1. Exact match
                this.selectedVoice = this.voices.find(v => v.name === this.voiceName);

                // 2. Case-insensitive exact match
                if (!this.selectedVoice) {
                    this.selectedVoice = this.voices.find(v =>
                        v.name.toLowerCase() === this.voiceName.toLowerCase()
                    );
                }

                // 3. Contains match (case-insensitive)
                if (!this.selectedVoice) {
                    this.selectedVoice = this.voices.find(v =>
                        v.name.toLowerCase().includes(this.voiceName.toLowerCase()) ||
                        this.voiceName.toLowerCase().includes(v.name.toLowerCase())
                    );
                }

                // 4. Match by key words (e.g., "Female", "Male", "UK", "US")
                if (!this.selectedVoice) {
                    const keywords = this.voiceName.toLowerCase().split(/\s+/);
                    this.selectedVoice = this.voices.find(v => {
                        const voiceNameLower = v.name.toLowerCase();
                        return keywords.every(keyword => voiceNameLower.includes(keyword));
                    });
                }

                // 5. Match by gender preference
                if (!this.selectedVoice) {
                    const isFemale = this.voiceName.toLowerCase().includes('female') ||
                        this.voiceName.toLowerCase().includes('feminin') ||
                        this.voiceName.toLowerCase().includes('zira') ||
                        this.voiceName.toLowerCase().includes('hazel') ||
                        this.voiceName.toLowerCase().includes('samantha') ||
                        this.voiceName.toLowerCase().includes('victoria');

                    const isMale = this.voiceName.toLowerCase().includes('male') ||
                        this.voiceName.toLowerCase().includes('masculin') ||
                        this.voiceName.toLowerCase().includes('david') ||
                        this.voiceName.toLowerCase().includes('mark') ||
                        this.voiceName.toLowerCase().includes('alex') ||
                        this.voiceName.toLowerCase().includes('daniel');

                    if (isFemale) {
                        this.selectedVoice = this.voices.find(v =>
                            v.name.toLowerCase().includes('female') ||
                            v.name.toLowerCase().includes('zira') ||
                            v.name.toLowerCase().includes('hazel')
                        );
                    } else if (isMale) {
                        this.selectedVoice = this.voices.find(v =>
                            v.name.toLowerCase().includes('male') ||
                            v.name.toLowerCase().includes('david') ||
                            v.name.toLowerCase().includes('mark')
                        );
                    }
                }

                // Fallback to default if still not found
                if (!this.selectedVoice) {
                    this.selectedVoice = this.voices.find(v => v.default) || this.voices[0];
                    console.warn(`Voice "${this.voiceName}" not found, using default voice:`, this.selectedVoice?.name);
                } else {
                    console.log('Found voice:', this.selectedVoice.name);
                }
            }
        }

        /**
         * Speak text using text-to-speech
         * @param {string} text - Text to speak
         * @param {Function} onComplete - Callback when speech is complete
         */
        speakText(text, onComplete) {
            if (!this.enableVoice || !this.synth || !text) {
                if (onComplete) {
                    onComplete();
                }
                return;
            }

            // Mark speech as active
            this.isSpeechActive = true;
            
            // Ensure video is in speaking state
            this.setSpeakingState();

            // Stop any ongoing speech
            this.synth.cancel();

            // Remove markdown formatting and HTML tags for clean speech
            // First, decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = text;
            let cleanText = tempDiv.textContent || tempDiv.innerText || text;

            // Additional cleanup for markdown and special characters
            cleanText = cleanText
                .replace(/\*\*(.*?)\*\*/g, '$1') // Remove bold markdown
                .replace(/\*(.*?)\*/g, '$1') // Remove italic markdown
                .replace(/#{1,6}\s/g, '') // Remove headers
                .replace(/`([^`]*)`/g, '$1') // Remove inline code
                .replace(/\[([^\]]*)\]\([^\)]*\)/g, '$1') // Remove links
                .replace(/\n+/g, '. ') // Replace newlines with periods
                .replace(/\s+/g, ' ') // Normalize whitespace
                .trim();

            if (!cleanText) {
                console.warn('No text to speak after cleaning');
                return;
            }

            // Wait for voices to be loaded if needed
            if (this.voices.length === 0) {
                setTimeout(() => {
                    this.setupVoice();
                    this.speakText(text);
                }, 100);
                return;
            }

            // Re-select voice each time to ensure it's current
            this.selectVoice();

            if (!this.selectedVoice) {
                console.warn('No voice selected, cannot speak');
                return;
            }

            // Split long text into chunks if needed (some browsers have limits)
            const maxLength = 20000; // Safe limit for most browsers
            const textChunks = [];

            if (cleanText.length > maxLength) {
                // Split by sentences
                const sentences = cleanText.match(/[^.!?]+[.!?]+/g) || [cleanText];
                let currentChunk = '';

                for (const sentence of sentences) {
                    if ((currentChunk + sentence).length > maxLength && currentChunk) {
                        textChunks.push(currentChunk.trim());
                        currentChunk = sentence;
                    } else {
                        currentChunk += sentence;
                    }
                }

                if (currentChunk.trim()) {
                    textChunks.push(currentChunk.trim());
                }
            } else {
                textChunks.push(cleanText);
            }

            // Speak all chunks sequentially
            let chunkIndex = 0;
            
            const speakChunk = () => {
                if (chunkIndex >= textChunks.length) {
                    // All chunks spoken
                    this.isSpeechActive = false;
                    if (onComplete) {
                        onComplete();
                    }
                    return;
                }
                
                const utterance = new SpeechSynthesisUtterance(textChunks[chunkIndex]);
                utterance.voice = this.selectedVoice;
                
                // Configure speech properties
                utterance.rate = 1.0; // Normal speed
                utterance.pitch = 1.0; // Normal pitch
                utterance.volume = 1.0; // Full volume
                
                // Handle speech events
                utterance.onend = () => {
                    chunkIndex++;
                    if (chunkIndex < textChunks.length) {
                        // Speak next chunk - keep video in speaking state
                        this.setSpeakingState();
                        speakChunk();
                    } else {
                        // All chunks finished
                        this.isSpeechActive = false;
                        if (onComplete) {
                            onComplete();
                        }
                    }
                };
                
                utterance.onerror = (event) => {
                    console.warn('Speech synthesis error:', event);
                    chunkIndex++;
                    if (chunkIndex < textChunks.length) {
                        // Try next chunk even if error - keep video in speaking state
                        this.setSpeakingState();
                        speakChunk();
                    } else {
                        // All chunks finished (or failed)
                        this.isSpeechActive = false;
                        if (onComplete) {
                            onComplete();
                        }
                    }
                };
                
                // Speak this chunk
                this.synth.speak(utterance);
            };
            
            // Start speaking first chunk
            speakChunk();
        }

        /**
         * Stop speaking
         */
        stopSpeaking() {
            if (this.synth) {
                this.synth.cancel();
            }
            this.isSpeechActive = false;
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
            // Don't stop speech automatically - let it finish naturally
            // Speech will be stopped only when explicitly needed (new message, etc.)
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
            // Track user interaction for speech synthesis (Chrome autoplay policy)
            const markUserInteraction = () => {
                if (!this.userHasInteracted) {
                    this.userHasInteracted = true;
                    // Re-initialize voice after user interaction
                    if (this.enableVoice) {
                        this.setupVoice();
                    }
                }
            };

            // Listen for various user interactions
            const interactionEvents = ['click', 'touchstart', 'keydown', 'mousedown'];
            interactionEvents.forEach(eventType => {
                document.addEventListener(eventType, markUserInteraction, { once: true, passive: true });
            });

            // Send button
            if (this.sendBtn) {
                this.sendBtn.addEventListener('click', () => {
                    markUserInteraction();
                    this.sendMessage();
                });
            }

            // Enter key (Shift+Enter pentru new line)
            if (this.inputEl) {
                this.inputEl.addEventListener('keydown', (e) => {
                    markUserInteraction();
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });

                this.inputEl.addEventListener('focus', markUserInteraction, { once: true });
                this.inputEl.addEventListener('click', markUserInteraction, { once: true });

                // Auto-resize textarea
                this.inputEl.addEventListener('input', () => {
                    markUserInteraction();
                    this.inputEl.style.height = 'auto';
                    this.inputEl.style.height = Math.min(this.inputEl.scrollHeight, 50) + 'px';
                });
            }
        }

        showInitialMessage() {
            setTimeout(() => {
                // Don't speak initial message automatically (Chrome autoplay policy)
                // Only display it, speech will work after user interaction
                this.typeText(this.config.heroMessage, () => {
                    // After initial message, go back to silent
                    setTimeout(() => {
                        this.setSilentState();
                    }, 500);
                }, false); // Pass false to skip speech for initial message
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
         * Formatează textul pentru afișare (folosește formatare markdown comună)
         */
        formatMessageText(text) {
            if (!text) return '';
            // Folosește funcția globală de formatare markdown dacă există
            if (typeof formatMarkdownMessage !== 'undefined') {
                return formatMarkdownMessage(text);
            }
            // Fallback la formatare simplă
            return text.replace(/\n/g, '<br>');
        }

        /**
         * Formatează textul parțial pentru typing effect (versiune optimizată rapidă)
         */
        formatPartialText(partialText) {
            if (!partialText) return '';

            // Folosește funcția rapidă de formatare parțială dacă există
            if (typeof formatMarkdownPartial !== 'undefined') {
                try {
                    return formatMarkdownPartial(partialText);
                } catch (e) {
                    console.warn('Error formatting partial text:', e);
                    return partialText.replace(/\n/g, '<br>');
                }
            }

            // Fallback la formatare simplă
            return partialText.replace(/\n/g, '<br>');
        }

        typeText(text, callback, skipSpeech = false) {
            // Switch to speaking state when typing starts
            this.setSpeakingState();

            // Stop any ongoing speech
            this.stopSpeaking();

            this.currentText = '';
            if (this.subtitleEl) {
                this.subtitleEl.innerHTML = '';
                // Reset scroll la început
                this.subtitleEl.scrollTop = 0;
            }

            let index = 0;
            const typingSpeed = 30; // milliseconds per character
            const formattedText = this.formatMessageText(text);

            // Speech is now started from sendToAI handler (user gesture context)
            // So we skip speech here if it's already been started
            // This ensures Chrome autoplay policy is satisfied

            const typeChar = () => {
                if (index < text.length) {
                    this.currentText += text[index];
                    if (this.subtitleEl) {
                        // Formatează textul parțial cu markdown în timpul typing-ului
                        const partialFormatted = this.formatPartialText(this.currentText);
                        this.subtitleEl.innerHTML = partialFormatted + '<span class="typing-cursor"></span>';
                        // Scroll automat mai frecvent pentru text lung
                        if (index % 5 === 0 || index === text.length - 1) {
                            this.scrollToBottom();
                        }
                    }
                    index++;
                    setTimeout(typeChar, typingSpeed);
                } else {
                    if (this.subtitleEl) {
                        // La final, afișează textul complet formatat (pentru a ne asigura că totul este corect)
                        this.subtitleEl.innerHTML = formattedText;
                        // Scroll final cu delay pentru a permite DOM-ului să se actualizeze
                        setTimeout(() => {
                            this.scrollToBottom();
                        }, 50);
                    }
                    if (callback) callback();
                    // Nu trecem la silent state imediat dacă vocea este activată
                    // setSilentState va fi apelat automat când se termină citirea vocală
                    // Menținem video-ul în starea speaking cât timp speech-ul este activ
                    if (!this.enableVoice || !this.isSpeechActive) {
                        this.setSilentState();
                    }
                }
            };

            typeChar();
        }

        async sendMessage() {
            const message = this.inputEl ? this.inputEl.value.trim() : '';
            if (!message || (this.loadingEl && !this.loadingEl.classList.contains('d-none'))) {
                return;
            }

            // Mark user interaction - this enables speech synthesis in Chrome
            // (sending a message is a valid user gesture)
            this.userHasInteracted = true;
            
            // Re-initialize voice after user interaction if needed
            if (this.enableVoice && this.voices.length === 0) {
                this.setupVoice();
            }

            // Stop any ongoing speech
            this.stopSpeaking();

            // Clear subtitle and send to AI
            if (this.subtitleEl) {
                this.subtitleEl.innerHTML = '';
            }
            if (this.inputEl) {
                this.inputEl.value = '';
                this.inputEl.style.height = 'auto';
            }

            // Store message for potential speech synthesis
            this.pendingMessageForSpeech = message;

            this.sendToAI(message);
        }

        async sendToAI(message) {
            if (this.loadingEl) {
                this.loadingEl.classList.remove('d-none');
                this.loadingEl.classList.add('d-flex');
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
                    // Start speech immediately while still in user gesture context (Chrome requirement)
                    // Store the response message for speech
                    const aiResponse = response.data.message;
                    
                    // Start speech synthesis immediately (still in click handler context)
                    if (this.enableVoice && this.userHasInteracted) {
                        // Ensure video is in speaking state
                        this.setSpeakingState();
                        
                        // Start speech synthesis right away
                        this.speakText(aiResponse, () => {
                            // When speech is complete, switch to silent state
                            this.setSilentState();
                        });
                    }
                    
                    // Then start typing animation
                    this.typeText(aiResponse, () => {
                        // After typing finishes
                        if (this.loadingEl) {
                            this.loadingEl.classList.add('d-none');
                            this.loadingEl.classList.remove('d-flex');
                        }
                        if (this.sendBtn) {
                            this.sendBtn.disabled = false;
                        }
                        // Don't switch to silent here if speech is still active
                        if (!this.enableVoice || !this.isSpeechActive) {
                            this.setSilentState();
                        }
                    }, !this.enableVoice); // Skip speech in typeText since we already started it
                } else {
                    throw new Error(response.data.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error:', error);
                this.typeText('Sorry, an error occurred. Please try again.', () => {
                    this.setSilentState();
                    if (this.loadingEl) {
                        this.loadingEl.classList.add('d-none');
                        this.loadingEl.classList.remove('d-flex');
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
