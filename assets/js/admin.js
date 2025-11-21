/**
 * AI Hero Assistant - Admin JavaScript
 * Bootstrap-enhanced admin interface
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Tab switching function (works with or without Bootstrap)
        function switchTab(tabButton) {
            const targetId = $(tabButton).attr('data-bs-target') || $(tabButton).data('target');
            if (!targetId) return;
            
            const targetPane = $(targetId);
            if (!targetPane.length) return;
            
            // Remove active class from all tabs and panes
            $('.nav-tabs .nav-link').removeClass('active').attr('aria-selected', 'false');
            $('.tab-pane').removeClass('show active');
            
            // Add active class to clicked tab and target pane
            $(tabButton).addClass('active').attr('aria-selected', 'true');
            targetPane.addClass('show active');
            
            // Update URL hash
            const hash = targetId.substring(1);
            if (history.pushState) {
                history.pushState(null, null, '#' + hash);
            } else {
                window.location.hash = hash;
            }
        }
        
        // Handle tab clicks - try Bootstrap first, fallback to manual
        $('.nav-tabs .nav-link').on('click', function(e) {
            e.preventDefault();
            
            // Try Bootstrap Tab if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                try {
                    const bsTab = new bootstrap.Tab(this);
                    bsTab.show();
                    
                    // Update URL hash
                    $(this).one('shown.bs.tab', function() {
                        const targetId = $(this).attr('data-bs-target').substring(1);
                        if (history.pushState) {
                            history.pushState(null, null, '#' + targetId);
                        } else {
                            window.location.hash = targetId;
                        }
                    });
                } catch (err) {
                    // Fallback to manual switching
                    switchTab(this);
                }
            } else {
                // Manual switching if Bootstrap not available
                switchTab(this);
            }
        });
        
        // Handle tab switching with URL hash on page load
        const urlHash = window.location.hash;
        if (urlHash) {
            const tabId = urlHash.substring(1);
            const tabButton = $('#' + tabId + '-tab');
            if (tabButton.length) {
                setTimeout(function() {
                    switchTab(tabButton[0]);
                }, 100);
            }
        }
        
        // Preview gradient colors
        const gradientStart = $('#gradient_start');
        const gradientEnd = $('#gradient_end');
        
        function updatePreview() {
            // Poți adăuga preview live dacă e necesar
        }
        
        if (gradientStart.length) {
            gradientStart.on('change', updatePreview);
        }
        if (gradientEnd.length) {
            gradientEnd.on('change', updatePreview);
        }
        
        // File upload preview
        $('#documentation_files').on('change', function() {
            const files = this.files;
            if (files.length > 0) {
                console.log('Files selected:', files.length);
            }
        });
    });
    
})(jQuery);
