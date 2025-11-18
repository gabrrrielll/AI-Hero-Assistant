/**
 * AI Hero Assistant - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    class AIHeroAssistant {
        constructor(instanceId, config) {
            this.instanceId = instanceId;
            this.config = config;
            this.canvas = document.getElementById(`aiha-canvas-${instanceId}`);
            this.ctx = this.canvas.getContext('2d');
            this.subtitleEl = document.getElementById(`aiha-subtitle-${instanceId}`);
            this.inputEl = document.getElementById(`aiha-input-${instanceId}`);
            this.sendBtn = document.getElementById(`aiha-send-${instanceId}`);
            this.loadingEl = document.getElementById(`aiha-loading-${instanceId}`);
            
            this.particles = [];
            this.animationId = null;
            this.isSpeaking = false;
            this.currentText = '';
            this.sessionId = this.generateSessionId();
            
            this.init();
        }
        
        generateSessionId() {
            return 'aiha_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        init() {
            this.setupCanvas();
            this.createParticles();
            this.setupEventListeners();
            this.animate();
            this.showInitialMessage();
        }
        
        setupCanvas() {
            const container = this.canvas.parentElement;
            const size = Math.min(container.offsetWidth, 300);
            this.canvas.width = size;
            this.canvas.height = size;
            this.centerX = this.canvas.width / 2;
            this.centerY = this.canvas.height / 2;
        }
        
        createParticles() {
            const particleCount = 300; // Mai multe particule pentru efect mai dens
            const radius = Math.min(this.canvas.width, this.canvas.height) / 2 - 30;
            
            for (let i = 0; i < particleCount; i++) {
                const angle = Math.random() * Math.PI * 2;
                const distance = radius * (0.3 + Math.random() * 0.7); // Distribuție mai uniformă
                const x = this.centerX + Math.cos(angle) * distance;
                const y = this.centerY + Math.sin(angle) * distance;
                
                // Viteză inițială aleatorie pentru mișcare organică
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
                    angleSpeed: (Math.random() - 0.5) * 0.02, // Viteză de rotație
                    radius: distance,
                    pulsePhase: Math.random() * Math.PI * 2, // Pentru efect de puls
                    noiseOffset: Math.random() * 1000 // Pentru Perlin noise simplificat
                });
            }
        }
        
        setupEventListeners() {
            // Send button
            this.sendBtn.addEventListener('click', () => this.sendMessage());
            
            // Enter key (Shift+Enter pentru new line)
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
            
            // Window resize
            window.addEventListener('resize', () => {
                this.setupCanvas();
                this.createParticles();
            });
        }
        
        showInitialMessage() {
            setTimeout(() => {
                this.typeText(this.config.heroMessage, () => {
                    this.isSpeaking = false;
                });
            }, 1000);
        }
        
        // Simple noise function pentru mișcare organică
        noise(x, y, time) {
            return Math.sin(x * 0.01 + time) * Math.cos(y * 0.01 + time) * 0.5 + 0.5;
        }
        
        animate() {
            const time = Date.now() * 0.001; // Time în secunde
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            const faceRadius = Math.min(this.canvas.width, this.canvas.height) / 2 - 20;
            
            // Eye positions
            const eyeOffsetX = 45;
            const eyeOffsetY = -35;
            const leftEyeX = this.centerX - eyeOffsetX;
            const leftEyeY = this.centerY + eyeOffsetY;
            const rightEyeX = this.centerX + eyeOffsetX;
            const rightEyeY = this.centerY + eyeOffsetY;
            const eyeRadius = 25;
            
            // Mouth area
            const mouthX = this.centerX;
            const mouthY = this.centerY + 50;
            const mouthRadius = 35;
            
            // Update particles cu mișcare organică
            this.particles.forEach((particle, index) => {
                // Mișcare circulară organică cu noise
                particle.angle += particle.angleSpeed;
                particle.noiseOffset += 0.01;
                
                // Noise pentru mișcare organică
                const noiseX = this.noise(particle.x, particle.y, time + particle.noiseOffset);
                const noiseY = this.noise(particle.y, particle.x, time + particle.noiseOffset);
                
                // Calculăm poziția de bază pe cerc
                const baseAngle = Math.atan2(particle.baseY - this.centerY, particle.baseX - this.centerX);
                const currentAngle = baseAngle + particle.angle;
                
                // Distanța de la centru cu variație organică
                const radiusVariation = Math.sin(time * 0.5 + index * 0.1) * 15;
                const currentRadius = particle.radius + radiusVariation;
                
                // Poziția țintă pe cerc
                let targetX = this.centerX + Math.cos(currentAngle) * currentRadius;
                let targetY = this.centerY + Math.sin(currentAngle) * currentRadius;
                
                // Adaugă noise pentru mișcare organică
                targetX += (noiseX - 0.5) * 20;
                targetY += (noiseY - 0.5) * 20;
                
                // Evită zonele ochilor - respinge particulele
                const distToLeftEye = Math.sqrt(
                    Math.pow(particle.x - leftEyeX, 2) + 
                    Math.pow(particle.y - leftEyeY, 2)
                );
                const distToRightEye = Math.sqrt(
                    Math.pow(particle.x - rightEyeX, 2) + 
                    Math.pow(particle.y - rightEyeY, 2)
                );
                
                if (distToLeftEye < eyeRadius) {
                    const pushAngle = Math.atan2(particle.y - leftEyeY, particle.x - leftEyeX);
                    targetX = leftEyeX + Math.cos(pushAngle) * eyeRadius;
                    targetY = leftEyeY + Math.sin(pushAngle) * eyeRadius;
                }
                
                if (distToRightEye < eyeRadius) {
                    const pushAngle = Math.atan2(particle.y - rightEyeY, particle.x - rightEyeX);
                    targetX = rightEyeX + Math.cos(pushAngle) * eyeRadius;
                    targetY = rightEyeY + Math.sin(pushAngle) * eyeRadius;
                }
                
                // Animație gură când vorbește
                if (this.isSpeaking) {
                    const distToMouth = Math.sqrt(
                        Math.pow(particle.x - mouthX, 2) + 
                        Math.pow(particle.y - mouthY, 2)
                    );
                    
                    if (distToMouth < mouthRadius + 25) {
                        const mouthAngle = Math.atan2(particle.y - mouthY, particle.x - mouthX);
                        // Wave effect mai pronunțat
                        const wave = Math.sin(time * 8 + index * 0.2) * 15;
                        const pulse = Math.sin(time * 3) * 5;
                        targetX = mouthX + Math.cos(mouthAngle) * (mouthRadius + wave + pulse);
                        targetY = mouthY + Math.sin(mouthAngle) * (mouthRadius + wave + pulse);
                    }
                }
                
                // Interacțiune între particule (attraction/repulsion) - optimizat
                let fx = 0, fy = 0;
                const checkRadius = 40;
                const checkRadiusSq = checkRadius * checkRadius;
                
                // Verifică doar particulele apropiate pentru performanță
                for (let otherIndex = index + 1; otherIndex < this.particles.length; otherIndex++) {
                    const other = this.particles[otherIndex];
                    const dx = other.x - particle.x;
                    const dy = other.y - particle.y;
                    const distanceSq = dx * dx + dy * dy;
                    
                    if (distanceSq < checkRadiusSq && distanceSq > 0) {
                        const distance = Math.sqrt(distanceSq);
                        const force = (distance < 20) ? -0.08 : 0.03; // Repulsion aproape, attraction departe
                        const forceX = (dx / distance) * force;
                        const forceY = (dy / distance) * force;
                        fx += forceX;
                        fy += forceY;
                        // Aplică forța și celuilalt particul pentru simetrie
                        other.vx -= forceX;
                        other.vy -= forceY;
                    }
                }
                
                // Aplică forțe
                particle.vx += fx;
                particle.vy += fy;
                
                // Smooth movement către țintă
                const dx = targetX - particle.x;
                const dy = targetY - particle.y;
                particle.vx += dx * 0.02;
                particle.vy += dy * 0.02;
                
                // Friction pentru mișcare fluidă
                particle.vx *= 0.95;
                particle.vy *= 0.95;
                
                // Update poziție
                particle.x += particle.vx;
                particle.y += particle.vy;
                
                // Păstrează particulele în limitele chip-ului
                const distFromCenter = Math.sqrt(
                    Math.pow(particle.x - this.centerX, 2) + 
                    Math.pow(particle.y - this.centerY, 2)
                );
                
                if (distFromCenter > faceRadius + 10) {
                    const pullAngle = Math.atan2(this.centerY - particle.y, this.centerX - particle.x);
                    particle.x = this.centerX + Math.cos(pullAngle) * faceRadius;
                    particle.y = this.centerY + Math.sin(pullAngle) * faceRadius;
                }
                
                // Pulse effect pentru opacitate
                const pulseOpacity = 0.3 + Math.sin(time * 2 + particle.pulsePhase) * 0.3;
                particle.currentOpacity = pulseOpacity * particle.opacity;
            });
            
            // Draw connections între particule apropiate (efect de nor) - optimizat
            const connectionRadius = 35;
            const connectionRadiusSq = connectionRadius * connectionRadius;
            
            for (let i = 0; i < this.particles.length; i++) {
                for (let j = i + 1; j < this.particles.length; j++) {
                    const dx = this.particles[i].x - this.particles[j].x;
                    const dy = this.particles[i].y - this.particles[j].y;
                    const distanceSq = dx * dx + dy * dy;
                    
                    if (distanceSq < connectionRadiusSq) {
                        const distance = Math.sqrt(distanceSq);
                        const opacity = 0.12 * (1 - distance / connectionRadius);
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.particles[i].x, this.particles[i].y);
                        this.ctx.lineTo(this.particles[j].x, this.particles[j].y);
                        this.ctx.strokeStyle = `rgba(255, 255, 255, ${opacity})`;
                        this.ctx.lineWidth = 0.8;
                        this.ctx.stroke();
                    }
                }
            }
            
            // Draw particles cu glow effect
            this.particles.forEach((particle) => {
                // Glow effect
                const gradient = this.ctx.createRadialGradient(
                    particle.x, particle.y, 0,
                    particle.x, particle.y, particle.size * 3
                );
                gradient.addColorStop(0, `rgba(255, 255, 255, ${particle.currentOpacity})`);
                gradient.addColorStop(0.5, `rgba(255, 255, 255, ${particle.currentOpacity * 0.5})`);
                gradient.addColorStop(1, `rgba(255, 255, 255, 0)`);
                
                this.ctx.beginPath();
                this.ctx.arc(particle.x, particle.y, particle.size * 3, 0, Math.PI * 2);
                this.ctx.fillStyle = gradient;
                this.ctx.fill();
                
                // Particle core
                this.ctx.beginPath();
                this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                this.ctx.fillStyle = `rgba(255, 255, 255, ${particle.currentOpacity})`;
                this.ctx.fill();
            });
            
            this.animationId = requestAnimationFrame(() => this.animate());
        }
        
        typeText(text, callback) {
            this.isSpeaking = true;
            this.currentText = '';
            this.subtitleEl.innerHTML = '';
            
            let index = 0;
            const typingSpeed = 30; // milliseconds per character
            
            const typeChar = () => {
                if (index < text.length) {
                    this.currentText += text[index];
                    this.subtitleEl.innerHTML = this.currentText + '<span class="typing-cursor"></span>';
                    index++;
                    setTimeout(typeChar, typingSpeed);
                } else {
                    this.subtitleEl.innerHTML = this.currentText;
                    if (callback) callback();
                }
            };
            
            typeChar();
        }
        
        async sendMessage() {
            const message = this.inputEl.value.trim();
            if (!message || this.loadingEl.style.display !== 'none') {
                return;
            }
            
            // Clear subtitle and send to AI
            this.subtitleEl.innerHTML = '';
            this.inputEl.value = '';
            this.inputEl.style.height = 'auto';
            
            this.sendToAI(message);
        }
        
        async sendToAI(message) {
            this.loadingEl.style.display = 'flex';
            this.sendBtn.disabled = true;
            this.isSpeaking = true;
            
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
                        this.isSpeaking = false;
                        this.loadingEl.style.display = 'none';
                        this.sendBtn.disabled = false;
                    });
                } else {
                    throw new Error(response.data.message || 'Eroare necunoscută');
                }
            } catch (error) {
                console.error('Error:', error);
                this.typeText('Îmi pare rău, a apărut o eroare. Vă rugăm să încercați din nou.', () => {
                    this.isSpeaking = false;
                    this.loadingEl.style.display = 'none';
                    this.sendBtn.disabled = false;
                });
            }
        }
    }
    
    // Initialize all instances
    $(document).ready(function() {
        $('.aiha-container').each(function() {
            const instanceId = $(this).data('instance-id');
            const configData = $(this).siblings('.aiha-initial-data').text();
            
            if (configData) {
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

