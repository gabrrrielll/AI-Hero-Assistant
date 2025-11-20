/**
 * AI Hero Assistant - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
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
    });
    
})(jQuery);



