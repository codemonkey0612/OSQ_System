/**
 * OSQ Stress Check Questionnaire Logic.
 *
 * Uses data-* attributes on #osq-questionnaire-form to avoid inline-script CSP violations.
 * The PHP template embeds ajax_url and nonce as data-ajax-url and data-nonce attributes.
 */

(function ($) {
    'use strict';

    var OSQ_Questionnaire = {
        currentTab: 0,
        autosaveInterval: null,
        ajaxUrl: '',
        token: '',
        sectionKeys: [],

        init: function () {
            // Read config from data attributes — NO inline scripts, CSP-safe.
            var $form = $('#osq-questionnaire-form');
            this.ajaxUrl = $form.data('ajax-url') || '';
            this.token = $form.data('token') || '';
            this.uid = $form.data('uid') || 0;

            if (!this.ajaxUrl || !this.token) {
                console.warn('OSQ: Missing ajax-url or token on #osq-questionnaire-form.');
                return;
            }

            this.bindEvents();

            // Build ordered section keys from tabs
            var self = this;
            $('.osq-tab').each(function() {
                self.sectionKeys.push($(this).data('section'));
            });

            this.updateUI();
            this.loadProgress(); // Fetch saved answers on load
        },

        loadProgress: function () {
            var self = this;
            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'osq_get_progress',
                    token: self.token,
                    uid: self.uid
                },
                success: function (response) {
                    if (response.success && response.data.answers) {
                        // Delay checking slightly to ensure DOM elements are fully initialized
                        // and not overridden by other scripts.
                        setTimeout(function () {
                            $.each(response.data.answers, function (key, value) {
                                var $input = $('input[name="answers[' + key + ']"][value="' + value + '"]');
                                if ($input.length) {
                                    $input.prop('checked', true);
                                    // Clear selection from sibling options, then highlight this one
                                    var $question = $input.closest('.osq-question');
                                    $question.find('.osq-radio-label').removeClass('selected');
                                    $input.closest('.osq-radio-label').addClass('selected');
                                }
                            });
                            self.updateProgress(); // Update progress after populating answers
                        }, 100);
                    }
                    // Always start autosave after loading (success or not).
                    self.startAutosave();
                },
                error: function () {
                    self.startAutosave();
                }
            });
        },

        bindEvents: function () {
            var self = this;

            // Tab switching
            $('.osq-tab').on('click', function () {
                self.goToSection($(this).data('section'));
            });

            // Option selection styling
            $('.osq-radio-label input').on('change', function () {
                var name = $(this).attr('name');
                $('input[name="' + name + '"]').closest('.osq-radio-label').removeClass('selected');
                $(this).closest('.osq-radio-label').addClass('selected');
                self.updateProgress();
            });

            // Manual save
            $('#osq-save-btn').on('click', function () {
                self.saveResponse(false);
            });

            // Next button
            $('#osq-next-btn').on('click', function () {
                var currentIdx = self.sectionKeys.indexOf(self.getCurrentSection());
                if (currentIdx < self.sectionKeys.length - 1) {
                    self.goToSection(self.sectionKeys[currentIdx + 1]);
                }
            });

            // Previous button
            $('#osq-prev-btn').on('click', function () {
                var currentIdx = self.sectionKeys.indexOf(self.getCurrentSection());
                if (currentIdx > 0) {
                    self.goToSection(self.sectionKeys[currentIdx - 1]);
                }
            });

            // Form submission
            $('#osq-questionnaire-form').on('submit', function (e) {
                e.preventDefault();
                if (!self.validateAll()) {
                    alert('すべての質問に回答してください。(Please answer all questions before submitting.)');
                } else {
                    self.submitForm();
                }
            });
        },

        getCurrentSection: function () {
            var $active = $('.osq-section.osq-section--active');
            if ($active.length) {
                return $active.attr('id').replace('osq-section-', '');
            }
            return this.sectionKeys[0] || 'A';
        },

        goToSection: function (sectionKey) {
            $('.osq-section').removeClass('osq-section--active');
            $('#osq-section-' + sectionKey).addClass('osq-section--active');

            $('.osq-tab').removeClass('osq-tab--active').attr('aria-selected', 'false');
            $('.osq-tab[data-section="' + sectionKey + '"]').addClass('osq-tab--active').attr('aria-selected', 'true');

            this.updateNavButtons();
            window.scrollTo(0, 0);
        },

        updateUI: function () {
            this.updateProgress();
        },

        updateProgress: function () {
            var total = $('.osq-question').length;
            var answered = $('.osq-radio-label input:checked').length;
            var percentage = total > 0 ? Math.round((answered / total) * 100) : 0;

            $('#osq-progress-fill').css('width', percentage + '%');
            $('#osq-answered-count').text(answered);

            // Update per-section progress
            $('.osq-section').each(function() {
                var sectionId = $(this).attr('id');
                var sectionKey = sectionId.replace('osq-section-', '');
                var sTotal = $(this).find('.osq-question').length;
                var sAnswered = $(this).find('.osq-radio-label input:checked').length;
                var sPerc = sTotal > 0 ? Math.round((sAnswered / sTotal) * 100) : 0;
                
                $('#osq-progress-' + sectionKey).css('width', sPerc + '%');
                
                var $tab = $('.osq-tab[data-section="' + sectionKey + '"]');
                if (sTotal === sAnswered && sTotal > 0) {
                    $tab.addClass('osq-tab--completed');
                } else {
                    $tab.removeClass('osq-tab--completed');
                }
            });

            // Show submit only when all questions answered
            if (answered === total && total > 0) {
                $('#osq-submit-btn').show();
            } else {
                $('#osq-submit-btn').hide();
            }

            this.updateNavButtons();
        },

        updateNavButtons: function () {
            var currentIdx = this.sectionKeys.indexOf(this.getCurrentSection());
            var isFirst = (currentIdx <= 0);
            var isLast = (currentIdx >= this.sectionKeys.length - 1);

            if (isFirst) {
                $('#osq-prev-btn').hide();
            } else {
                $('#osq-prev-btn').show();
            }

            if (isLast) {
                $('#osq-next-btn').hide();
            } else {
                $('#osq-next-btn').show();
            }
        },

        validateAll: function () {
            var total = $('.osq-question').length;
            var answered = $('.osq-radio-label input:checked').length;
            return answered === total;
        },

        startAutosave: function () {
            var self = this;
            if (self.autosaveInterval) {
                clearInterval(self.autosaveInterval);
            }
            self.autosaveInterval = setInterval(function () {
                self.saveResponse(false);
            }, 20000); // 20 seconds
        },

        saveResponse: function (isComplete) {
            var self = this;
            var $status = $('#osq-save-status');
            var answers = {};

            // Collect checked answers as a plain object (avoids wp_nonce_field conflict)
            $('#osq-questionnaire-form input[type="radio"]:checked').each(function () {
                var match = $(this).attr('name').match(/answers\[(.+)\]/);
                if (match) {
                    answers[match[1]] = $(this).val();
                }
            });

            $status.text('保存中... (Saving...)');

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'osq_save_progress',
                    token: self.token,
                    uid: self.uid,
                    answers: answers
                },
                success: function (response) {
                    if (response.success) {
                        $status.text('保存完了 (Saved)');
                        setTimeout(function () { $status.text(''); }, 3000);
                    } else {
                        $status.text('保存失敗: ' + (response.data.message || ''));
                    }
                },
                error: function (xhr) {
                    var errorMsg = '保存エラー (Error ' + xhr.status + ')';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = '保存失敗: ' + xhr.responseJSON.data.message;
                    }
                    $status.text(errorMsg);
                }
            });
        },

        submitForm: function () {
            var self = this;
            var answers = {};

            $('#osq-questionnaire-form input[type="radio"]:checked').each(function () {
                var match = $(this).attr('name').match(/answers\[(.+)\]/);
                if (match) {
                    answers[match[1]] = $(this).val();
                }
            });

            $.ajax({
                url: self.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'osq_submit_questionnaire',
                    token: self.token,
                    uid: self.uid,
                    answers: answers
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.href = '/osq-dashboard/';
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function (xhr) {
                    var errorMsg = '送信エラー (Submit Error ' + xhr.status + ')';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg += '\n' + xhr.responseJSON.data.message;
                    }
                    alert(errorMsg);
                }
            });
        }
    };

    $(document).ready(function () {
        if ($('#osq-questionnaire-form').length) {
            OSQ_Questionnaire.init();
        }

        // Tab Switching Logic for Dashboards (inner tab bar)
        $('.osq-inner-tab-nav li[data-tab]').on('click', function (e) {
            e.preventDefault();

            // Don't allow switching if password change is forced
            if (typeof osq_employee_vars !== 'undefined' && osq_employee_vars.must_change_password) {
                if ($(this).data('tab') !== 'profile') {
                    return;
                }
            }

            $('.osq-inner-tab-nav li').removeClass('active');
            $(this).addClass('active');

            var target = $(this).data('tab');
            $('.osq-tab-panel').hide();
            $('#tab-' + target).fadeIn(300);

            // Update header title
            var tabContent = $(this).find('span:not(.dashicons)').text().trim() || $(this).clone().children().remove().end().text().trim();
            if ($('#osq-tab-title').length) {
                $('#osq-tab-title').text(tabContent);
            }
        });

        // Password Form Submission for Employee Dashboard
        $('#osq-employee-password-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#osq-password-message');

            // Assume we can get ajax_url and nonce from osq_questionnaire_vars if available, 
            // or from a data attribute if we embedded it. We can just use standard /wp-admin/admin-ajax.php
            // The template enqueues JS, let's verify if osq_employee_vars is available.
            var ajaxurl = (typeof osq_employee_vars !== 'undefined') ? osq_employee_vars.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof osq_employee_vars !== 'undefined') ? osq_employee_vars.nonce : '';

            $button.prop('disabled', true).css('opacity', '0.7');
            $message.hide().removeClass('osq-message--error osq-message--success');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'osq_change_password',
                    nonce: nonce,
                    current_password: $form.find('input[name="current_password"]').val(),
                    new_password: $form.find('input[name="new_password"]').val(),
                    confirm_password: $form.find('input[name="confirm_password"]').val()
                },
                success: function (response) {
                    $button.prop('disabled', false).css('opacity', '1');
                    if (response.success) {
                        $message.css({
                            'background': '#dcfce7',
                            'color': '#166534',
                            'border': '1px solid #bbf7d0',
                            'display': 'block'
                        }).text(response.data.message);
                        $form[0].reset();

                        // If password change was required, reload after a short delay
                        if (typeof osq_employee_vars !== 'undefined' && osq_employee_vars.must_change_password) {
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        $message.css({
                            'background': '#fee2e2',
                            'color': '#dc2626',
                            'border': '1px solid #fecaca',
                            'display': 'block'
                        }).text(response.data.message);
                    }
                },
                error: function () {
                    $button.prop('disabled', false).css('opacity', '1');
                    $message.css({
                        'background': '#fee2e2',
                        'color': '#dc2626',
                        'border': '1px solid #fecaca',
                        'display': 'block'
                    }).text('A server error occurred. Please try again.');
                }
            });
        });
    });

})(jQuery);
