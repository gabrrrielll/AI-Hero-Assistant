/**
 * AI Hero Assistant - Admin JavaScript
 * Bootstrap-enhanced admin interface
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle tab switching with URL hash
        const urlHash = window.location.hash;
        if (urlHash) {
            const tabId = urlHash.substring(1);
            const tab = $('#' + tabId + '-tab');
            if (tab.length) {
                const bsTab = new bootstrap.Tab(tab[0]);
                bsTab.show();
            }
        }
        
        // Update URL hash when tab changes
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const targetId = $(e.target).attr('data-bs-target').substring(1);
            window.location.hash = targetId;
        });
        
        // Preview gradient colors
        const gradientStart = $('#gradient_start');
        const gradientEnd = $('#gradient_end');
        
        function updatePreview() {
            // Poți adăuga preview live dacă e necesar
        }
        
        gradientStart.on('change', updatePreview);
        gradientEnd.on('change', updatePreview);
        
        // File upload preview
        $('#documentation_files').on('change', function() {
            const files = this.files;
            if (files.length > 0) {
                console.log('Files selected:', files.length);
            }
        });
        
        // Smooth scroll to active tab on page load
        if (urlHash) {
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('.nav-tabs').offset().top - 50
                }, 300);
            }, 100);
        }
    });
    
})(jQuery);
