/**
 * Formatare Markdown pentru mesaje AI Hero Assistant
 * Convertește markdown simplu în HTML formatat
 */

(function () {
    'use strict';

    /**
     * Formatează textul markdown parțial rapid (pentru typing effect)
     * @param {string} text - Textul markdown parțial de formatat
     * @returns {string} - HTML formatat
     */
    window.formatMarkdownPartial = function (text) {
        if (!text) return '';

        // Normalizează textul: elimină linii goale multiple consecutive
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        text = text.replace(/\n{2,}/g, '\n'); // Elimină 2+ newlines consecutive (păstrează doar 1)
        text = text.replace(/[ \t]+/g, ' '); // Elimină spații multiple
        
        let formatted = text;

        // Escapă HTML-ul existent pentru siguranță
        const div = document.createElement('div');
        div.textContent = formatted;
        formatted = div.innerHTML;

        // Procesează rapid doar elementele inline și simple
        // Bold (**text** sau __text__)
        formatted = formatted.replace(/\*\*([^*\n]+?)\*\*/g, '<strong class="aiha-bold">$1</strong>');
        formatted = formatted.replace(/__(.+?)__/g, '<strong class="aiha-bold">$1</strong>');

        // Italic (*text* sau _text_) - doar dacă nu este la început de linie
        formatted = formatted.replace(/(?<!^|\n|\*)\*([^*\n]+?)\*(?!\*)/g, '<em class="aiha-italic">$1</em>');
        formatted = formatted.replace(/(?<!^|\n|_)_(?!_)([^_\n]+?)_(?!_)/g, '<em class="aiha-italic">$1</em>');

        // Headers (### H3, #### H4, etc.) - doar dacă sunt complete
        formatted = formatted.replace(/^###\s+(.+)$/gm, '<h3 class="aiha-header aiha-h1">$1</h3>');
        formatted = formatted.replace(/^####\s+(.+)$/gm, '<h4 class="aiha-header aiha-h2">$1</h4>');
        formatted = formatted.replace(/^##\s+(.+)$/gm, '<h2 class="aiha-header aiha-h1">$1</h2>');
        formatted = formatted.replace(/^#\s+(.+)$/gm, '<h1 class="aiha-header aiha-h1">$1</h1>');

        // Liste cu bullet points (* sau -) - doar dacă sunt complete
        const lines = formatted.split('\n');
        let result = [];
        let inList = false;

        lines.forEach((line) => {
            const trimmed = line.trim();
            const listMatch = trimmed.match(/^[\*\-\•]\s+(.+)$/);

            if (listMatch) {
                if (!inList) {
                    result.push('<ul class="aiha-message-list">');
                    inList = true;
                }
                let content = listMatch[1];
                // Formatează conținutul listei (bold, italic)
                content = content.replace(/\*\*([^*\n]+?)\*\*/g, '<strong class="aiha-bold">$1</strong>');
                result.push('<li class="aiha-list-item">' + content + '</li>');
            } else {
                if (inList) {
                    result.push('</ul>');
                    inList = false;
                }
                if (trimmed) {
                    result.push('<p class="aiha-message-paragraph">' + trimmed + '</p>');
                } else {
                    result.push('<br class="aiha-line-break">');
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
    };

    /**
     * Formatează textul markdown în HTML (versiune completă)
     * @param {string} text - Textul markdown de formatat
     * @returns {string} - HTML formatat
     */
    window.formatMarkdownMessage = function (text) {
        if (!text) return '';

        // Normalizează textul: elimină linii goale multiple consecutive
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        text = text.replace(/\n{2,}/g, '\n'); // Elimină 2+ newlines consecutive (păstrează doar 1)
        text = text.replace(/[ \t]+/g, ' '); // Elimină spații multiple
        
        let formatted = text;

        // Escapă HTML-ul existent pentru siguranță
        const div = document.createElement('div');
        div.textContent = formatted;
        formatted = div.innerHTML;

        // Procesează linie cu linie pentru a detecta structura
        const lines = formatted.split('\n');
        let result = [];
        let inList = false;
        let inCodeBlock = false;
        let codeBlockContent = [];

        lines.forEach((line, index) => {
            const trimmed = line.trim();
            const originalLine = line;

            // Code blocks (```)
            if (trimmed.startsWith('```')) {
                if (inCodeBlock) {
                    // Închide code block
                    result.push('<pre class="aiha-code-block"><code>' + codeBlockContent.join('\n') + '</code></pre>');
                    codeBlockContent = [];
                    inCodeBlock = false;
                } else {
                    // Deschide code block
                    if (inList) {
                        result.push('</ul>');
                        inList = false;
                    }
                    inCodeBlock = true;
                    const lang = trimmed.substring(3).trim();
                    if (lang) {
                        codeBlockContent.push('<!-- language: ' + lang + ' -->');
                    }
                }
                return;
            }

            if (inCodeBlock) {
                codeBlockContent.push(originalLine);
                return;
            }

            // Headers (### H3, #### H4, etc.)
            const headerMatch = trimmed.match(/^(#{1,6})\s+(.+)$/);
            if (headerMatch) {
                if (inList) {
                    result.push('</ul>');
                    inList = false;
                }
                const level = headerMatch[1].length;
                const content = headerMatch[2];
                const tag = 'h' + Math.min(level + 2, 6); // h3-h6
                result.push('<' + tag + ' class="aiha-header aiha-h' + level + '">' + content + '</' + tag + '>');
                return;
            }

            // Liste cu bullet points (* sau -)
            const listMatch = trimmed.match(/^[\*\-\•]\s+(.+)$/);
            if (listMatch) {
                if (!inList) {
                    result.push('<ul class="aiha-message-list">');
                    inList = true;
                }
                let content = listMatch[1];
                // Formatează conținutul listei (bold, italic, etc.)
                content = formatInlineMarkdown(content);
                result.push('<li class="aiha-list-item">' + content + '</li>');
                return;
            }

            // Liste numerotate (1. item)
            const numberedListMatch = trimmed.match(/^\d+\.\s+(.+)$/);
            if (numberedListMatch) {
                if (!inList) {
                    result.push('<ol class="aiha-message-list aiha-numbered-list">');
                    inList = true;
                }
                let content = numberedListMatch[1];
                content = formatInlineMarkdown(content);
                result.push('<li class="aiha-list-item">' + content + '</li>');
                return;
            }

            // Linie goală sau text normal
            if (inList) {
                result.push('</ul>');
                inList = false;
            }

            if (trimmed) {
                // Formatează inline markdown
                let content = formatInlineMarkdown(trimmed);
                result.push('<p class="aiha-message-paragraph">' + content + '</p>');
            } else {
                result.push('<br class="aiha-line-break">');
            }
        });

        // Închide liste sau code blocks deschise
        if (inList) {
            result.push('</ul>');
        }
        if (inCodeBlock && codeBlockContent.length > 0) {
            result.push('<pre class="aiha-code-block"><code>' + codeBlockContent.join('\n') + '</code></pre>');
        }

        return result.join('');
    };

    /**
     * Formatează markdown inline (bold, italic, links, etc.)
     * @param {string} text - Textul de formatat
     * @returns {string} - HTML formatat
     */
    function formatInlineMarkdown(text) {
        if (!text) return '';

        let formatted = text;

        // Bold (**text** sau __text__)
        formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong class="aiha-bold">$1</strong>');
        formatted = formatted.replace(/__(.+?)__/g, '<strong class="aiha-bold">$1</strong>');

        // Italic (*text* sau _text_)
        formatted = formatted.replace(/(?<!\*)\*([^*\n]+?)\*(?!\*)/g, '<em class="aiha-italic">$1</em>');
        formatted = formatted.replace(/(?<!_)_([^_\n]+?)_(?!_)/g, '<em class="aiha-italic">$1</em>');

        // Strikethrough (~~text~~)
        formatted = formatted.replace(/~~(.+?)~~/g, '<del class="aiha-strikethrough">$1</del>');

        // Links [text](url)
        formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="aiha-link" target="_blank" rel="noopener">$1</a>');

        // Inline code (`code`)
        formatted = formatted.replace(/`([^`]+)`/g, '<code class="aiha-inline-code">$1</code>');

        return formatted;
    }
})();

