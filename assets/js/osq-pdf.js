/**
 * OSQ PDF Generation Handler
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Handle PDF download button click
        $('.osq-js-download-pdf').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const originalHtml = $btn.html();

            // Show loading state
            $btn.html('<span class="dashicons dashicons-update osq-spin"></span> Generating...');
            $btn.prop('disabled', true);

            const element = document.getElementById('osq-pdf-template');
            
            // Show element for capture
            $(element).css('display', 'block');
            
            const opt = {
                margin: [10, 10], // Slightly reduced margin
                filename: (osq_pdf_vars.filename || 'stress-check-results') + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true, 
                    letterRendering: true,
                    logging: false,
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(function () {
                // Restore button and hide template
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                $(element).css('display', 'none');
            }).catch(function (err) {
                console.error('PDF Generation Error:', err);
                $btn.html('<span class="dashicons dashicons-warning"></span> Error');
                $(element).css('display', 'none');
                alert('Failed to generate PDF. Please try again or contact support.');
            });
        });
    });

})(jQuery);
