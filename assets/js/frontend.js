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
            // Loading element removed - no longer needed

            this.isSpeaking = false;
            this.currentText = '';

            // Conversation storage - Use fixed key without instanceId
            // so conversation persists across page refreshes
            this.storageKey = 'aiha_conversation';

            // Generate sessionId only if we don't have one from localStorage
            // loadConversation will set this.sessionId if a conversation exists
            this.sessionId = this.generateSessionId();

            // User name storage - will be extracted from conversation
            this.userName = null;

            // Video settings - separate playback rates for each video
            this.videoSilencePlaybackRate = config.videoSilencePlaybackRate || 1.0;
            this.videoSpeakingPlaybackRate = config.videoSpeakingPlaybackRate || 1.0;

            // Voice settings
            this.enableVoice = config.enableVoice || false;
            this.voiceName = config.voiceName || 'default';
            this.synth = null;
            this.voices = [];
            this.selectedVoice = null;
            this.userHasInteracted = false; // Track if user has interacted (required for Chrome autoplay policy)
            this.pendingMessageForSpeech = null; // Store message for speech synthesis
            this.isSpeechActive = false; // Track if speech is currently active

            // Load conversation from localStorage (this will update sessionId if conversation exists)
            this.conversation = this.loadConversation();

            this.init();
        }

        generateSessionId() {
            return 'aiha_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Load conversation from localStorage
         * Uses fixed key 'aiha_conversation' - only one conversation per browser
         */
        loadConversation() {
            try {
                const stored = localStorage.getItem(this.storageKey);
                console.log('Loading conversation from localStorage:', this.storageKey, stored ? 'found' : 'not found');

                if (stored) {
                    const parsed = JSON.parse(stored);
                    console.log('Parsed conversation:', parsed);
                    // Verify it's a valid conversation structure
                    if (parsed && Array.isArray(parsed.messages)) {
                        // Always use the sessionId from localStorage if it exists
                        if (parsed.sessionId) {
                            this.sessionId = parsed.sessionId;
                            console.log('Restored sessionId from localStorage:', this.sessionId);
                        }

                        // Extract user name from conversation if available
                        if (parsed.userName) {
                            this.userName = parsed.userName;
                            console.log('Restored user name from localStorage:', this.userName);
                        } else {
                            // Try to extract name from conversation messages
                            this.userName = this.extractUserNameFromConversation(parsed);
                            if (this.userName) {
                                console.log('Extracted user name from conversation:', this.userName);
                            }
                        }

                        // Return conversation even if empty (to preserve sessionId)
                        if (parsed.messages.length > 0) {
                            console.log('Loaded conversation with', parsed.messages.length, 'messages');
                        } else {
                            console.log('Loaded empty conversation, but preserving sessionId');
                        }
                        return parsed;
                    } else {
                        console.log('Conversation structure invalid');
                    }
                } else {
                    console.log('No conversation found in localStorage');
                }
            } catch (e) {
                console.warn('Error loading conversation from localStorage:', e);
            }
            // Return empty conversation structure with new sessionId
            console.log('Returning empty conversation structure with new sessionId:', this.sessionId);
            return {
                sessionId: this.sessionId,
                messages: []
            };
        }

        /**
         * Save conversation to localStorage
         */
        saveConversation() {
            try {
                // Save user name in conversation object
                if (this.userName) {
                    this.conversation.userName = this.userName;
                }
                const dataToSave = JSON.stringify(this.conversation);
                localStorage.setItem(this.storageKey, dataToSave);
                console.log('Saved conversation to localStorage:', this.storageKey, 'Messages:', this.conversation.messages ? this.conversation.messages.length : 0);
            } catch (e) {
                console.warn('Error saving conversation to localStorage:', e);
            }
        }

        /**
         * Extract user name from conversation messages
         * Looks for patterns like "Bună, [Name]!" or "Ma numesc [Name]"
         */
        extractUserNameFromConversation(conversation) {
            if (!conversation || !conversation.messages) {
                return null;
            }

            // Look for name in AI responses (AI usually greets user by name)
            for (let i = conversation.messages.length - 1; i >= 0; i--) {
                const msg = conversation.messages[i];
                if (msg.role === 'assistant' && msg.text) {
                    // Pattern: "Bună, [Name]!" or "Salut, [Name]!" or "Hello, [Name]!"
                    const greetingPattern = /(?:Bună|Salut|Hello|Hi|Buna|Bună ziua)[,\s]+([A-ZĂÂÎȘȚ][a-zăâîșț]+)[!.,]?/i;
                    const match = msg.text.match(greetingPattern);
                    if (match && match[1]) {
                        return match[1];
                    }
                }
            }

            // Look for name in user messages (user introduces themselves)
            for (let i = conversation.messages.length - 1; i >= 0; i--) {
                const msg = conversation.messages[i];
                if (msg.role === 'user' && msg.text) {
                    // Pattern: "Ma numesc [Name]" or "Numele meu este [Name]" or "Sunt [Name]"
                    const introPattern = /(?:Ma numesc|Numele meu este|Sunt|I'm|I am)[\s]+([A-ZĂÂÎȘȚ][a-zăâîșț]+)/i;
                    const match = msg.text.match(introPattern);
                    if (match && match[1]) {
                        return match[1];
                    }
                }
            }

            return null;
        }

        /**
         * Get display name for user messages
         */
        getUserDisplayName() {
            return this.userName || 'Utilizator';
        }

        /**
         * Detect and save user name from message
         */
        detectAndSaveUserName(message) {
            if (!message || this.userName) {
                return; // Already have a name or no message
            }

            // Pattern: "Ma numesc [Name]" or "Numele meu este [Name]" or "Sunt [Name]"
            const introPattern = /(?:Ma numesc|Numele meu este|Sunt|I'm|I am)[\s]+([A-ZĂÂÎȘȚ][a-zăâîșț]+)/i;
            const match = message.match(introPattern);
            if (match && match[1]) {
                this.userName = match[1];
                console.log('Detected user name:', this.userName);
                // Update all user message senders in the display
                this.updateUserDisplayNames();
                // Save conversation to persist name
                this.saveConversation();
            }
        }

        /**
         * Extract user name from AI response message
         */
        extractUserNameFromMessage(text) {
            if (!text) return null;

            // Pattern: "Bună, [Name]!" or "Salut, [Name]!" or "Hello, [Name]!"
            const greetingPattern = /(?:Bună|Salut|Hello|Hi|Buna|Bună ziua)[,\s]+([A-ZĂÂÎȘȚ][a-zăâîșț]+)[!.,]?/i;
            const match = text.match(greetingPattern);
            if (match && match[1]) {
                return match[1];
            }

            return null;
        }

        /**
         * Update all user message sender names in the display
         */
        updateUserDisplayNames() {
            if (!this.subtitleEl || !this.userName) {
                return;
            }

            const displayName = this.getUserDisplayName();
            const userSenders = this.subtitleEl.querySelectorAll('.aiha-message-user .aiha-message-sender');
            userSenders.forEach(sender => {
                sender.textContent = displayName;
            });
        }

        /**
         * Add message to conversation
         */
        addMessageToConversation(role, text) {
            if (!this.conversation) {
                this.conversation = {
                    sessionId: this.sessionId,
                    messages: []
                };
            }
            if (!this.conversation.messages) {
                this.conversation.messages = [];
            }
            this.conversation.messages.push({
                role: role,
                text: text,
                timestamp: Date.now()
            });
            console.log('Added message to conversation:', role, 'Total messages:', this.conversation.messages.length);
            this.saveConversation();
        }

        init() {
            this.setupColorVariables();
            this.setupVideos();
            this.setupEventListeners();
            this.setupVoice();

            // Load existing conversation or show initial message
            console.log('Checking conversation:', this.conversation);
            if (this.conversation && this.conversation.messages && this.conversation.messages.length > 0) {
                console.log('Loading existing conversation with', this.conversation.messages.length, 'messages');
                this.loadExistingConversation();
            } else {
                console.log('No existing conversation, showing initial message');
                this.showInitialMessage();
            }

            // Start with silent state
            this.setSilentState();
        }

        /**
         * Convert hex color to rgba and set CSS variables for opacity variations
         */
        setupColorVariables() {
            const heroSection = document.querySelector(`#${this.instanceId} .aiha-hero-section`);
            if (!heroSection) return;

            // Get color values from CSS variables
            const gradientStart = getComputedStyle(heroSection).getPropertyValue('--gradient-start').trim();
            const gradientEnd = getComputedStyle(heroSection).getPropertyValue('--gradient-end').trim();
            const gradientColor3 = getComputedStyle(heroSection).getPropertyValue('--gradient-color-3').trim();
            const gradientColor4 = getComputedStyle(heroSection).getPropertyValue('--gradient-color-4').trim();

            // Helper function to convert hex to rgba
            const hexToRgba = (hex, alpha) => {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            };

            // Set rgba variables for different opacity levels
            if (gradientStart) {
                heroSection.style.setProperty('--gradient-start-rgba-40', hexToRgba(gradientStart, 0.4));
                heroSection.style.setProperty('--gradient-start-rgba-80', hexToRgba(gradientStart, 0.8));
            }
            if (gradientEnd) {
                heroSection.style.setProperty('--gradient-end-rgba-40', hexToRgba(gradientEnd, 0.4));
                heroSection.style.setProperty('--gradient-end-rgba-80', hexToRgba(gradientEnd, 0.8));
            }
            if (gradientColor3) {
                heroSection.style.setProperty('--gradient-color-3-rgba-60', hexToRgba(gradientColor3, 0.6));
            }
            if (gradientColor4) {
                heroSection.style.setProperty('--gradient-color-4-rgba-70', hexToRgba(gradientColor4, 0.7));
            }
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

                    // Check if error is "not-allowed" (Chrome autoplay policy or site settings)
                    if (event.error === 'not-allowed') {
                        console.error('Speech synthesis blocked by browser. Possible causes:');
                        console.error('1. Chrome site settings: Click the lock icon in address bar → Site settings → Sound → Allow');
                        console.error('2. Chrome autoplay policy: Speech must start from user gesture (click/touch)');
                        console.error('3. System sound settings: Check if sound is muted or blocked');

                        // Show user-friendly message
                        this.showSpeechBlockedMessage();
                    }

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

        /**
         * Show message when speech is blocked
         */
        showSpeechBlockedMessage() {
            // Check if message already exists
            if (document.getElementById('aiha-speech-blocked-message')) {
                return;
            }

            // Create message element
            const messageEl = document.createElement('div');
            messageEl.id = 'aiha-speech-blocked-message';
            messageEl.className = 'alert alert-warning alert-dismissible fade show position-fixed';
            messageEl.style.cssText = 'top: 20px; right: 20px; z-index: 10000; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            messageEl.innerHTML = `
                <strong>Sunet blocat</strong><br>
                Pentru a activa vocea, permite sunetul în setările Chrome:<br>
                <small>1. Click pe iconița de lăcătuș din bara de adresă<br>
                2. Site settings → Sound → Allow<br>
                3. Reîncarcă pagina</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            // Add to page
            document.body.appendChild(messageEl);

            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 10000);
        }

        setupVideos() {
            // Set playback rates for each video independently
            const silencePlaybackRate = this.videoSilencePlaybackRate || 1.0;
            const speakingPlaybackRate = this.videoSpeakingPlaybackRate || 1.0;
            
            // Ensure videos are loaded and playing
            if (this.videoSilence) {
                this.videoSilence.playbackRate = silencePlaybackRate;
                this.videoSilence.addEventListener('loadeddata', () => {
                    this.videoSilence.playbackRate = silencePlaybackRate;
                    this.videoSilence.play().catch(e => console.log('Video play error:', e));
                });
            }

            if (this.videoSpeaking) {
                this.videoSpeaking.playbackRate = speakingPlaybackRate;
                this.videoSpeaking.addEventListener('loadeddata', () => {
                    this.videoSpeaking.playbackRate = speakingPlaybackRate;
                    this.videoSpeaking.play().catch(e => console.log('Video play error:', e));
                });
            }
        }

        /**
         * Set video container to silent state (hide speaking video)
         */
        setSilentState() {
            if (this.videoSpeaking) {
                this.videoSpeaking.style.opacity = '0';
            }
            this.isSpeaking = false;
        }

        /**
         * Set video container to speaking state (show speaking video)
         */
        setSpeakingState() {
            if (this.videoSpeaking) {
                this.videoSpeaking.style.opacity = '1';
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

            // Listen for various user interactions (but don't stop speech on these)
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
                    // Don't stop speech when user types - only when sending message
                });

                this.inputEl.addEventListener('focus', markUserInteraction, { once: true });
                this.inputEl.addEventListener('click', (e) => {
                    markUserInteraction();
                    // Don't stop speech when clicking in textarea - only when sending message
                });

                // Auto-resize textarea
                this.inputEl.addEventListener('input', () => {
                    markUserInteraction();
                    this.inputEl.style.height = 'auto';
                    this.inputEl.style.height = Math.min(this.inputEl.scrollHeight, 50) + 'px';
                    // Don't stop speech when typing - only when sending message
                });
            }
        }

        showInitialMessage() {
            setTimeout(() => {
                // Don't speak initial message automatically (Chrome autoplay policy)
                // Only display it, speech will work after user interaction
                // For initial message, we display it directly without typing effect

                // Check if initial message already exists in conversation
                const hasInitialMessage = this.conversation && this.conversation.messages &&
                    this.conversation.messages.some(msg => msg.role === 'assistant' && msg.text === this.config.heroMessage);

                if (this.subtitleEl) {
                    const initialMessageHTML = '<div class="aiha-message-wrapper aiha-message-assistant">' +
                        '<div class="aiha-message-bubble aiha-message-bubble-assistant">' +
                        '<div class="aiha-message-sender">AI</div>' +
                        '<div class="aiha-message-content">' + this.formatMessageText(this.config.heroMessage) + '</div>' +
                        '</div></div>';
                    this.subtitleEl.innerHTML = initialMessageHTML;
                    setTimeout(() => {
                        this.scrollToBottom();
                    }, 100);
                }

                // Save initial message to conversation only if it doesn't already exist
                if (!hasInitialMessage) {
                    console.log('Saving initial message to conversation');
                    this.addMessageToConversation('assistant', this.config.heroMessage);
                } else {
                    console.log('Initial message already exists in conversation, skipping save');
                }
            }, 1000);
        }

        /**
         * Load and display existing conversation from localStorage
         */
        loadExistingConversation() {
            console.log('loadExistingConversation called, conversation:', this.conversation);
            if (!this.conversation || !this.conversation.messages || this.conversation.messages.length === 0) {
                console.log('No messages to load, showing initial message instead');
                this.showInitialMessage();
                return;
            }

            console.log('Loading', this.conversation.messages.length, 'messages from conversation');

            // Build HTML from all messages (user + assistant) in chat format
            let conversationHTML = '';

            this.conversation.messages.forEach((msg) => {
                const isUser = msg.role === 'user';
                const alignClass = isUser ? 'aiha-message-user' : 'aiha-message-assistant';
                const bgClass = isUser ? 'aiha-message-bubble-user' : 'aiha-message-bubble-assistant';

                conversationHTML += '<div class="aiha-message-wrapper ' + alignClass + '">';
                conversationHTML += '<div class="aiha-message-bubble ' + bgClass + '">';

                if (isUser) {
                    // User message - plain text, no markdown
                    const displayName = this.getUserDisplayName();
                    conversationHTML += '<div class="aiha-message-sender">' + this.escapeHtml(displayName) + '</div>';
                    conversationHTML += '<div class="aiha-message-content">' + this.escapeHtml(msg.text) + '</div>';
                } else {
                    // Assistant message - format with markdown
                    conversationHTML += '<div class="aiha-message-sender">AI</div>';
                    const formatted = this.formatMessageText(msg.text);
                    conversationHTML += '<div class="aiha-message-content">' + formatted + '</div>';
                }

                conversationHTML += '</div>';
                conversationHTML += '</div>';
            });

            // Display the conversation directly (no typing effect for loaded conversation)
            if (this.subtitleEl && conversationHTML) {
                this.subtitleEl.innerHTML = conversationHTML;
                // Scroll to bottom
                setTimeout(() => {
                    this.scrollToBottom();
                }, 100);
            }
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Auto-scroll to bottom of subtitle
         */
        scrollToBottom() {
            if (this.subtitleEl) {
                // Use requestAnimationFrame for smooth and correct scroll
                requestAnimationFrame(() => {
                    // Scroll to bottom with small delay to allow DOM to update
                    setTimeout(() => {
                        if (this.subtitleEl) {
                            this.subtitleEl.scrollTop = this.subtitleEl.scrollHeight;
                        }
                    }, 10);
                });
            }
        }

        /**
         * Format text for display (uses common markdown formatting)
         */
        formatMessageText(text) {
            if (!text) return '';
            // Use global markdown formatting function if available
            if (typeof formatMarkdownMessage !== 'undefined') {
                return formatMarkdownMessage(text);
            }
            // Fallback to simple formatting
            return text.replace(/\n/g, '<br>');
        }

        /**
         * Format partial text for typing effect (optimized fast version)
         */
        formatPartialText(partialText) {
            if (!partialText) return '';

            // Use fast partial formatting function if available
            if (typeof formatMarkdownPartial !== 'undefined') {
                try {
                    return formatMarkdownPartial(partialText);
                } catch (e) {
                    console.warn('Error formatting partial text:', e);
                    return partialText.replace(/\n/g, '<br>');
                }
            }

            // Fallback to simple formatting
            return partialText.replace(/\n/g, '<br>');
        }

        typeText(text, callback, skipSpeech = false) {
            // Stop any ongoing speech only if this is a new message
            // (not when user just clicks in textarea)
            this.stopSpeaking();

            // Reset video to silent first, then switch to speaking when typing starts
            // This ensures video resets correctly for each new message
            this.setSilentState();

            // Small delay to ensure video transition is visible
            setTimeout(() => {
                // Switch to speaking state when typing starts
                this.setSpeakingState();
            }, 50);

            this.currentText = '';

            // Get existing conversation HTML (preserve ALL previous messages)
            // We'll append the new message at the end
            // Only remove the last AI message if there's an incomplete one being typed
            let baseHTML = '';
            if (this.subtitleEl && this.subtitleEl.innerHTML) {
                // Check if there's already a typing animation in progress
                // If currentText was being typed, we need to remove the incomplete message
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = this.subtitleEl.innerHTML;
                const existingMessages = Array.from(tempDiv.querySelectorAll('.aiha-message-wrapper'));

                // Find the last assistant message
                let lastAssistantIndex = -1;
                for (let i = existingMessages.length - 1; i >= 0; i--) {
                    const sender = existingMessages[i].querySelector('.aiha-message-sender');
                    if (sender && sender.textContent === 'AI') {
                        lastAssistantIndex = i;
                        break;
                    }
                }

                // Only remove last AI message if we're starting a NEW typing animation
                // (i.e., if there was a previous incomplete message being typed)
                // Since we reset currentText to '' at the start, we know we're starting fresh
                // So we should keep all messages - the new one will be appended
                baseHTML = this.subtitleEl.innerHTML;
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
                        // Format partial text with markdown during typing
                        const partialFormatted = this.formatPartialText(this.currentText);
                        // Append new assistant message bubble to existing conversation
                        const newMessageHTML = '<div class="aiha-message-wrapper aiha-message-assistant">' +
                            '<div class="aiha-message-bubble aiha-message-bubble-assistant">' +
                            '<div class="aiha-message-sender">AI</div>' +
                            '<div class="aiha-message-content">' + partialFormatted + '</div>' +
                            '</div></div>';

                        this.subtitleEl.innerHTML = baseHTML + newMessageHTML;
                        // Auto-scroll more frequently for long text
                        if (index % 5 === 0 || index === text.length - 1) {
                            this.scrollToBottom();
                        }
                    }
                    index++;
                    setTimeout(typeChar, typingSpeed);
                } else {
                    if (this.subtitleEl) {
                        // At the end, display fully formatted text (to ensure everything is correct)
                        const finalMessageHTML = '<div class="aiha-message-wrapper aiha-message-assistant">' +
                            '<div class="aiha-message-bubble aiha-message-bubble-assistant">' +
                            '<div class="aiha-message-sender">AI</div>' +
                            '<div class="aiha-message-content">' + formattedText + '</div>' +
                            '</div></div>';

                        this.subtitleEl.innerHTML = baseHTML + finalMessageHTML;
                        // Final scroll with delay to allow DOM to update
                        setTimeout(() => {
                            this.scrollToBottom();
                        }, 50);
                    }
                    if (callback) callback();
                    // Don't switch to silent state immediately if voice is enabled
                    // setSilentState will be called automatically when voice reading ends
                    // Keep video in speaking state while speech is active
                    if (!this.enableVoice || !this.isSpeechActive) {
                        this.setSilentState();
                    }
                }
            };

            typeChar();
        }

        async sendMessage() {
            const message = this.inputEl ? this.inputEl.value.trim() : '';
            if (!message) {
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

            // Save user message to conversation immediately (before displaying)
            this.addMessageToConversation('user', message);

            // Try to extract user name from message
            this.detectAndSaveUserName(message);

            // Add user message to conversation display immediately
            if (this.subtitleEl) {
                const displayName = this.getUserDisplayName();
                const userMessageHTML = '<div class="aiha-message-wrapper aiha-message-user">' +
                    '<div class="aiha-message-bubble aiha-message-bubble-user">' +
                    '<div class="aiha-message-sender">' + this.escapeHtml(displayName) + '</div>' +
                    '<div class="aiha-message-content">' + this.escapeHtml(message) + '</div>' +
                    '</div></div>';
                this.subtitleEl.innerHTML += userMessageHTML;
                // Scroll to show new message
                setTimeout(() => {
                    this.scrollToBottom();
                }, 50);
            }

            // Clear input
            if (this.inputEl) {
                this.inputEl.value = '';
                this.inputEl.style.height = 'auto';
            }

            // Store message for potential speech synthesis
            this.pendingMessageForSpeech = message;

            this.sendToAI(message);
        }

        async sendToAI(message) {
            if (this.sendBtn) {
                this.sendBtn.disabled = true;
            }

            // User message already saved in sendMessage(), so we don't save it again here

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

                    // Save AI response to conversation
                    this.addMessageToConversation('assistant', aiResponse);

                    // Try to extract user name from AI response (AI might greet user by name)
                    if (!this.userName) {
                        const extractedName = this.extractUserNameFromMessage(aiResponse);
                        if (extractedName) {
                            this.userName = extractedName;
                            console.log('Detected user name from AI response:', this.userName);
                            // Update all user message senders in the display
                            this.updateUserDisplayNames();
                            // Save conversation to persist name
                            this.saveConversation();
                        }
                    }

                    // Start speech synthesis immediately (still in click handler context)
                    if (this.enableVoice && this.userHasInteracted) {
                        // Start speech synthesis right away
                        // Video will be set to speaking state in speakText
                        this.speakText(aiResponse, () => {
                            // When speech is complete, switch to silent state
                            this.setSilentState();
                        });
                    }

                    // Then start typing animation
                    // typeText will reset video to silent first, then switch to speaking
                    this.typeText(aiResponse, () => {
                        // After typing finishes
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
                const errorMessage = 'Sorry, an error occurred. Please try again.';
                this.addMessageToConversation('assistant', errorMessage);
                this.typeText(errorMessage, () => {
                    this.setSilentState();
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
