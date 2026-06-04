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

        // Org analysis report PDF.
        $(document).on('click', '#osq-download-org-report', function (e) {
            e.preventDefault();

            var $btn = $(this);
            $btn.prop('disabled', true).text('生成中...');

            var adminVars = (typeof osq_admin_vars !== 'undefined') ? osq_admin_vars : null;
            if ( ! adminVars ) { alert('設定エラー: osq_admin_vars が見つかりません。'); $btn.prop('disabled', false).text('組織分析レポートPDF出力'); return; }
            var nonce    = adminVars.nonce;
            var ajaxUrl  = adminVars.ajax_url;
            var orgLevel = $('#osq-analysis-org-level').val() || 'organization_1';
            var minSize  = $('#osq-min-group-size').val() || '';
            var excOrgs  = $('#osq-exclude-orgs').val() || '';

            $.get(ajaxUrl, {
                action:       'osq_admin_get_org_report_data',
                nonce:        nonce,
                org_level:    orgLevel,
                min_group_size: minSize,
                exclude_orgs: excOrgs,
            })
            .done(function (res) {
                $btn.prop('disabled', false).text('組織分析レポートPDF出力');
                if ( ! res.success ) { alert('レポートデータの取得に失敗しました。'); return; }

                var html     = res.data.html;
                var filename = res.data.filename || 'osq-org-report';

                var printWin = window.open('', '_blank', 'width=900,height=950,scrollbars=yes,resizable=yes');
                if ( ! printWin ) {
                    alert('ポップアップがブロックされています。ブラウザの設定でこのサイトのポップアップを許可してください。');
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
                    '  body { margin: 0; padding: 0; background:#fff; }' +
                    '  #osq-print-toolbar { background:#1e293b; color:#fff; padding:12px 20px; display:flex; gap:12px; align-items:center; position:sticky; top:0; z-index:999; }' +
                    '  #osq-print-toolbar button { padding:8px 18px; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }' +
                    '  #osq-btn-print { background:#166534; color:#fff; }' +
                    '  #osq-btn-close { background:#475569; color:#fff; }' +
                    '  #osq-print-content { padding:30px; }' +
                    '  @media print { #osq-print-toolbar { display:none !important; } #osq-print-content { padding:0; } }' +
                    '</style>' +
                    '</head>' +
                    '<body>' +
                    '<div id="osq-print-toolbar">' +
                    '  <button id="osq-btn-print">PDF保存 / Save as PDF</button>' +
                    '  <button id="osq-btn-close">閉じる / Close</button>' +
                    '  <span style="font-size:13px;opacity:0.7;">印刷ダイアログで「PDFとして保存」を選んでください。</span>' +
                    '</div>' +
                    '<div id="osq-print-content">' + html + '</div>' +
                    '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"><\/script>' +
                    '<script>' +
                    'document.getElementById("osq-btn-print").addEventListener("click",function(){ window.print(); });' +
                    'document.getElementById("osq-btn-close").addEventListener("click",function(){ window.close(); });' +
                    '(function waitForChart() {' +
                    '  if (typeof Chart === "undefined") { setTimeout(waitForChart, 80); return; }' +
                    '  var dataEl = document.getElementById("osq-org-chart-data");' +
                    '  if (!dataEl) return;' +
                    '  var d = JSON.parse(dataEl.textContent);' +
                    '  var barEl = document.getElementById("osq-org-bar-chart");' +
                    '  if (barEl) new Chart(barEl, d.bar);' +
                    '  if (d.top3 && d.top3.length) {' +
                    '    d.top3.forEach(function(g, i) {' +
                    '      var radarEl = document.getElementById("osq-org-radar-chart-" + i);' +
                    '      if (!radarEl || !g.scales || !Object.keys(g.scales).length) return;' +
                    '      var labels = Object.keys(g.scales).map(function(k){ return d.scaleLabels[k] || k; });' +
                    '      var vals   = Object.values(g.scales);' +
                    '      new Chart(radarEl, {' +
                    '        type: "radar",' +
                    '        data: { labels: labels, datasets: [{ label: g.label, data: vals, fill: true,' +
                    '          backgroundColor: "rgba(220,38,38,0.15)", borderColor: "rgb(220,38,38)",' +
                    '          pointBackgroundColor: "rgb(220,38,38)" }] },' +
                    '        options: { responsive: true, scales: { r: { beginAtZero: true, max: 5 } } }' +
                    '      });' +
                    '    });' +
                    '  }' +
                    '  setTimeout(function(){ window.print(); }, 800);' +
                    '})();' +
                    '<\/script>' +
                    '</body>' +
                    '</html>'
                );
                printWin.document.close();
            })
            .fail(function () {
                $btn.prop('disabled', false).text('組織分析レポートPDF出力');
                alert('通信エラーが発生しました。');
            });
        });

})(jQuery);
