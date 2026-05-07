/**
 * OSQ Stress Check Admin Logic.
 */

(function ($) {
    'use strict';

    var OSQ_Admin = {
        init: function () {
            this.initCharts();
        },

        initCharts: function () {
            if (typeof Chart === 'undefined' || !$('.osq-chart').length) {
                return;
            }

            $('.osq-chart').each(function () {
                var ctx = this.getContext('2d');
                var config = $(this).data('config'); // Assuming config is passed via data attribute

                if (config) {
                    new Chart(ctx, config);
                }
            });
        },

        initReset: function () {
            var $btn = $('#osq-reset-all-data');
            var $msg = $('#osq-reset-message');

            if (!$btn.length) return;

            $btn.on('click', function () {
                var confirm1 = confirm("全従業員データ、回答データ、ログインアカウントを完全に消去しますか？\nAre you sure you want to permanently delete ALL employee data, responses, and accounts?");
                if (!confirm1) return;

                var confirm2 = confirm("この操作は取り消せません。本当によろしいですか？\nThis action CANNOT be undone. Are you absolutely sure?");
                if (!confirm2) return;

                $btn.prop('disabled', true).text('処理中... (Processing...)');

                $.ajax({
                    url: osq_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'osq_admin_reset_all_data',
                        nonce: osq_admin_vars.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $msg.text(response.data.message).css('color', '#10b981').show();
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $msg.text('エラー: (Error:) ' + response.data).css('color', '#ef4444').show();
                            $btn.prop('disabled', false).text('すべての従業員データをリセット (Reset All Employee Data)');
                        }
                    },
                    error: function () {
                        $msg.text('システムエラーが発生しました。(System error occurred.)').css('color', '#ef4444').show();
                        $btn.prop('disabled', false).text('すべての従業員データをリセット (Reset All Employee Data)');
                    }
                });
            });
        }
    };

    $(document).ready(function () {
        OSQ_Admin.init();
        OSQ_Admin.initReset();
    });

})(jQuery);
