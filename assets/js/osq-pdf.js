/**
 * OSQ Employee PDF Generation Handler — print-window approach.
 * Replaces html2pdf/html2canvas (broken inside flex containers) with a
 * native browser print window so the user can Save as PDF.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        $('.osq-js-download-pdf').on('click', function (e) {
            e.preventDefault();

            var templateEl = document.getElementById('osq-pdf-template');
            if ( ! templateEl ) {
                return;
            }

            var content  = templateEl.innerHTML;
            var filename = (typeof osq_pdf_vars !== 'undefined' && osq_pdf_vars.filename)
                ? osq_pdf_vars.filename
                : 'stress-check-results';

            var printWin = window.open('', '_blank', 'width=860,height=900,scrollbars=yes,resizable=yes');

            if ( ! printWin ) {
                alert(
                    'ポップアップがブロックされています。ブラウザの設定でこのサイトのポップアップを許可してください。\n\n' +
                    'Pop-up blocked. Please allow pop-ups for this site and try again.'
                );
                return;
            }

            printWin.document.open();
            printWin.document.write(
                '<!DOCTYPE html>' +
                '<html>' +
                '<head>' +
                '<meta charset="UTF-8">' +
                '<title>' + filename + '</title>' +
                '<style>' +
                '  body { font-family: "Hiragino Kaku Gothic Pro", "Meiryo", sans-serif; color: #333; line-height: 1.6; background: #fff; margin: 0; padding: 0; }' +
                '  #osq-print-toolbar { background: #1e293b; color: #fff; padding: 12px 20px; display: flex; gap: 12px; align-items: center; position: sticky; top: 0; z-index: 999; }' +
                '  #osq-print-toolbar button { padding: 8px 18px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }' +
                '  #osq-btn-print { background: #166534; color: #fff; }' +
                '  #osq-btn-close { background: #475569; color: #fff; }' +
                '  #osq-print-content { padding: 30px; max-width: 760px; margin: 0 auto; }' +
                '  @media print { #osq-print-toolbar { display: none !important; } #osq-print-content { padding: 0; max-width: 100%; } }' +
                '</style>' +
                '</head>' +
                '<body>' +
                '<div id="osq-print-toolbar">' +
                '  <button id="osq-btn-print">PDF保存 / Save as PDF</button>' +
                '  <button id="osq-btn-close">閉じる / Close</button>' +
                '  <span style="font-size:13px;opacity:0.7;">印刷ダイアログで「PDFとして保存」を選んでください。</span>' +
                '</div>' +
                '<div id="osq-print-content">' + content + '</div>' +
                '<script>' +
                '  document.getElementById("osq-btn-print").addEventListener("click", function(){ window.print(); });' +
                '  document.getElementById("osq-btn-close").addEventListener("click", function(){ window.close(); });' +
                '<\/script>' +
                '</body>' +
                '</html>'
            );
            printWin.document.close();
        });

    });

})(jQuery);
