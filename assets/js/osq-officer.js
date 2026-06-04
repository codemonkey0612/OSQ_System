/**
 * OSQ Implementation Officer Interface Scripts.
 */
(function ($) {
    'use strict';

    const ajaxVars = (typeof osq_officer_vars !== 'undefined') ? osq_officer_vars : {};
    const i18n = ajaxVars.i18n || {};
    const orgLabels = i18n.org_labels || {};

    console.log('OSQ Officer JS loaded');

    function translateOrgLabel(label) {
        if (!label) {
            return '-';
        }
        return orgLabels[label] || label;
    }

    // Load Employee Responses Table
    function loadResponses() {
        const $tbody = $('#osq-responses-table tbody');
        $tbody.empty().append(`<tr><td colspan="6" class="osq-empty-table">${i18n.loading}</td></tr>`);

        console.log('Making AJAX request to:', ajaxVars.ajax_url);
        console.log('With data:', {
            action: 'osq_officer_get_responses',
            nonce: ajaxVars.nonce
        });

        $.ajax({
            url: ajaxVars.ajax_url,
            type: 'GET',
            data: {
                action: 'osq_officer_get_responses',
                nonce: ajaxVars.nonce
            },
            success: function (response) {
                console.log('AJAX Success response:', response);
                if (response.success) {
                    $tbody.empty();
                    const employees = response.data.employees;

                    if (employees.length === 0) {
                        $tbody.append(`<tr><td colspan="6" class="osq-empty-table">${i18n.no_employees}</td></tr>`);
                        return;
                    }

                    employees.forEach(function (emp) {
                        // Determine Stress Status
                        let statusLabel = '-';
                        let actionBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.download_pdf}</button>`;
                        let viewBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.label_view_details || 'View Details'}</button>`;
                        let followBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.label_follow_up || 'Follow-up'}</button>`;

                        if (emp.is_complete == 1) {
                            if (emp.is_high_stress == 1) {
                                statusLabel = `<span class="osq-status-badge osq-status-badge--high-stress">${i18n.high_stress}</span>`;
                            } else {
                                statusLabel = `<span class="osq-status-badge osq-status-badge--normal">${i18n.normal}</span>`;
                            }
                            actionBtn = `<button class="osq-btn-download osq-js-officer-pdf" data-emp-id="${emp.employee_id}">${i18n.download_pdf}</button>`;
                            viewBtn = `<button class="osq-btn-download osq-view-detailed" data-emp-id="${emp.employee_id}">${i18n.label_view_details || 'View Details'}</button>`;
                            followBtn = `<button class="osq-btn-download osq-update-followup" data-emp-id="${emp.employee_id}">${i18n.label_follow_up || 'Follow-up'}</button>`;
                        } else {
                            statusLabel = `<span class="osq-status-badge">${i18n.pending}</span>`;
                        }

                        // Decode HTML entities for proper Japanese text display
                        const decodedName = $('<div/>').html(emp.name).text();
                        const orgParts = [];
                        for (let n = 1; n <= 5; n++) {
                            const v = emp['organization_' + n];
                            if (v) { orgParts.push($('<div/>').html(v).text()); }
                        }
                        const orgDisplay = orgParts.length ? orgParts.join(' › ') : '-';

                        const row = `
								<tr>
									<td><input type="checkbox" class="osq-employee-checkbox" data-emp-id="${emp.employee_id}"> ${emp.employee_number}</td>
									<td><strong>${decodedName}</strong></td>
                                    <td>${orgDisplay}</td>
                                    <td>${statusLabel}</td>
                                    <td>${emp.completed_at || '-'}</td>
                                    <td>
										${actionBtn}
										${viewBtn}
										${followBtn}
                                    </td>
                                </tr>
							`;
                        $tbody.append(row);
                    });
                }
            },
            error: function (xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                $tbody.empty().append(`<tr><td colspan="6" class="osq-empty-table">Error loading data: ${error}</td></tr>`);
            }
        });
    }

    $(document).ready(function () {
        console.log('Document ready - initializing officer dashboard');
        console.log('AJAX Vars:', ajaxVars);
        console.log('i18n:', i18n);

        // Initial Data Load
        loadResponses();

        // Hamburger Menu Toggle
        $('#osq-mobile-toggle, #osq-sidebar-overlay').on('click', function(e) {
            if (window.innerWidth <= 1024) {
                $('#osq-officer-dashboard').toggleClass('osq-sidebar-open');
            }
        });

        // Tab Switching Logic (inner tab bar)
        $('.osq-inner-tab-nav li').on('click', function () {
            const tabId = $(this).data('tab');

            $('.osq-inner-tab-nav li').removeClass('active');
            $(this).addClass('active');

            $('.osq-tab-panel').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            $('#osq-tab-title').text($(this).find('span:not(.dashicons)').text().trim());

            // Load data for specific tabs when switched
            if (tabId === 'followup') {
                loadFollowupTracking();
            }
        });

        // Handle PDF Download — uses browser native print (no html2canvas dependency)
        $(document).on('click', '.osq-js-officer-pdf', function (e) {
            e.preventDefault();
            const $btn   = $(this);
            const empId  = $btn.data('emp-id');
            const origHtml = $btn.html();

            $btn.html('<span class="dashicons dashicons-update osq-spin"></span>...').prop('disabled', true);

            $.ajax({
                url:  ajaxVars.ajax_url,
                type: 'POST',
                data: { action: 'osq_officer_get_pdf_html', nonce: ajaxVars.nonce, employee_id: empId },
                success: function (response) {
                    $btn.html(origHtml).prop('disabled', false);

                    if (!response.success) {
                        alert('PDF error: ' + JSON.stringify(response.data));
                        return;
                    }

                    const filename = response.data.filename || 'stress-check-results';
                    const win = window.open('', '_blank', 'width=860,height=740,scrollbars=yes,resizable=yes');
                    if (!win) {
                        alert(
                            'ポップアップがブロックされました。ブラウザのポップアップ許可設定を確認してください。\n' +
                            'Popup blocked — please allow popups for this site and click the button again.'
                        );
                        return;
                    }

                    win.document.open();
                    win.document.write(
                        '<!DOCTYPE html><html lang="ja"><head>' +
                        '<meta charset="UTF-8">' +
                        '<title>' + filename + '</title>' +
                        '<style>' +
                        'body{margin:0;padding:0;font-family:"Hiragino Kaku Gothic Pro",Meiryo,sans-serif;}' +
                        '.osq-bar{background:#007cba;padding:10px 20px;display:flex;gap:8px;' +
                        'justify-content:flex-end;position:sticky;top:0;z-index:999;}' +
                        '.osq-bar button{background:#fff;border:1px solid #bbb;padding:7px 16px;' +
                        'cursor:pointer;font-size:13px;border-radius:4px;font-family:inherit;}' +
                        '.osq-bar button:hover{background:#f0f0f0;}' +
                        '@media print{.osq-bar{display:none!important;}' +
                        'body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}' +
                        '</style></head><body>' +
                        '<div class="osq-bar">' +
                        '<button onclick="window.print()">&#128438; Print / Save as PDF</button>' +
                        '<button onclick="window.close()">&#10005; Close</button>' +
                        '</div>' +
                        response.data.html +
                        '</body></html>'
                    );
                    win.document.close();
                },
                error: function (xhr, status, err) {
                    console.error('PDF AJAX error:', status, err, xhr.responseText);
                    $btn.html(origHtml).prop('disabled', false);
                    alert('Network error loading PDF data (' + status + '). Please try again.');
                }
            });
        });

        // Search functionality
        $('.osq-input-search').on('input', function () {
            const value = $(this).val().normalize('NFKC').toLowerCase();
            $('#osq-responses-table tbody tr').filter(function () {
                // exclude empty state row
                if ($(this).find('.osq-empty-table').length > 0) return;
                $(this).toggle($(this).text().normalize('NFKC').toLowerCase().indexOf(value) > -1)
            });
        });

        // Settings form submission
        $('#osq-settings-form').on('submit', function (e) {
            e.preventDefault();

            const $form = $(this);
            const $message = $('#osq-settings-message');

            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Saving...');

            // Uses existing saving logic if 'osq_admin_save_settings' from admin handler works 
            // OR we can make a specific 'osq_officer_save_settings'.
            // Actually, language is a global option, so the officer saving it sets the global site lang, 
            // unless we store it as user meta. The requirement for bilingual says "The selected language is remembered via a secure cookie and persists across sessions." 
            // Wait! In admin, the setting saves the plugin default via `update_option`, BUT we also set a cookie `osq_lang` to remember choice.
            // So we'll update the cookie without needing AJAX to the DB if we only want a personal preference.
            // I'll leave the AJAX action as `osq_admin_save_settings` since the officer needs a personal UI. Wait, `is_osq_admin` is required for `osq_admin_save_settings`. So it will fail!
            // Instead, I'll just set the cookie and reload, like the frontend does.

            const lang = $form.find('select[name="language"]').val();
            const cookieValue = lang === 'ja' ? 'ja' : 'en_US';

            // Set the cookie with a root path so WordPress can read it on reload
            document.cookie = 'osq_lang=' + cookieValue + '; path=/; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';

            $message
                .removeClass('osq-message--error')
                .addClass('osq-message--success')
                .text('Language preference saved locally.')
                .show();

            setTimeout(function () {
                window.location.reload(true);
            }, 500);
        });

        // Password Form Submission for Officer Dashboard
        $('#osq-officer-password-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $message = $('#osq-officer-password-message');

            var ajaxurl = (typeof osq_officer_vars !== 'undefined') ? osq_officer_vars.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof osq_officer_vars !== 'undefined' && osq_officer_vars.password_nonce) ? osq_officer_vars.password_nonce : ((typeof osq_officer_vars !== 'undefined') ? osq_officer_vars.nonce : '');

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
                            'display': 'block',
                            'padding': '15px',
                            'border-radius': '6px'
                        }).text(response.data.message);
                        $form[0].reset();
                    } else {
                        $message.css({
                            'background': '#fee2e2',
                            'color': '#dc2626',
                            'border': '1px solid #fecaca',
                            'display': 'block',
                            'padding': '15px',
                            'border-radius': '6px'
                        }).text(response.data.message);
                    }
                },
                error: function () {
                    $button.prop('disabled', false).css('opacity', '1');
                    $message.css({
                        'background': '#fee2e2',
                        'color': '#dc2626',
                        'border': '1px solid #fecaca',
                        'display': 'block',
                        'padding': '15px',
                        'border-radius': '6px'
                    }).text(i18n.label_server_error_try_again || 'A server error occurred. Please try again.');
                }
            });
        });

        // Enhanced filtering functionality
        $('#osq-apply-filters').on('click', function() {
            applyFilters();
        });

        $('#osq-clear-filters').on('click', function() {
            clearFilters();
        });

        $('#osq-employee-search').on('input', function() {
            const searchTerm = $(this).val().normalize('NFKC').toLowerCase();
            filterTableRows(searchTerm);
        });

        // Bulk actions
        $('#osq-select-all').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.osq-employee-checkbox').prop('checked', isChecked);
            updateBulkActionState();
        });

        $('.osq-employee-checkbox').on('change', function() {
            updateBulkActionState();
        });

        $('#osq-bulk-action').on('change', function() {
            updateBulkActionState();
        });

        $('#osq-execute-bulk').on('click', function() {
            executeBulkAction();
        });

        // Modal functionality
        $('.osq-modal-close, .osq-modal-close-btn, .osq-modal-overlay').on('click', function() {
            closeModal();
        });

        // Detailed response modal
        $(document).on('click', '.osq-view-detailed', function(e) {
            e.preventDefault();
            const employeeId = $(this).data('emp-id');
            showDetailedResponse(employeeId);
        });

        // Follow-up modal
        $(document).on('click', '.osq-update-followup', function(e) {
            e.preventDefault();
            const employeeId = $(this).data('emp-id');
            showFollowupModal(employeeId);
        });

        // Follow-up form submission
        $('#osq-followup-form').on('submit', function(e) {
            e.preventDefault();
            updateFollowupStatus();
        });

        // Follow-up tracking tab - load when tab becomes active
        $(document).on('click', '[data-tab="followup"]', function() {
            setTimeout(function() {
                loadFollowupTracking();
            }, 100);
        });

        // Follow-up search
        $('#osq-followup-search').on('input', function() {
            const searchTerm = $(this).val().normalize('NFKC').toLowerCase();
            filterFollowupRows(searchTerm);
        });

        // Initialize organization filters
        initializeOrganizationFilters();
    });

    // Filtering functions
    function applyFilters() {
        const orgLevel1 = $('#osq-org-filter-1').val();
        const status = $('#osq-status-filter').val();
        const searchTerm = $('#osq-employee-search').val();

        $.ajax({
            url: ajaxVars.ajax_url,
            type: 'POST',
            data: {
                action: 'osq_officer_filter_employees',
                nonce: ajaxVars.nonce,
                org_level_1: orgLevel1,
                status: status,
                search: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    updateEmployeeTable(response.data.employees);
                } else {
                    showError(i18n.label_error_apply_filters || 'Error applying filters');
                }
            },
            error: function() {
                showError(i18n.label_network_error_apply_filters || 'Network error while applying filters');
            }
        });
    }

    function clearFilters() {
        $('#osq-org-filter-1').val('');
        $('#osq-status-filter').val('');
        $('#osq-employee-search').val('');
        loadResponses(); // Reload all data
    }

    function filterTableRows(searchTerm) {
        $('#osq-responses-table tbody tr').each(function() {
            const rowText = $(this).text().normalize('NFKC').toLowerCase();
            $(this).toggle(rowText.indexOf(searchTerm) > -1);
        });
    }

    function initializeOrganizationFilters() {
        // This would typically fetch unique organization values from the database
        // For now, we'll populate with sample data or fetch via AJAX
        $.ajax({
            url: ajaxVars.ajax_url,
            type: 'GET',
            data: {
                action: 'osq_officer_get_org_filters',
                nonce: ajaxVars.nonce
            },
            success: function(response) {
                if (response.success) {
                    const orgSelect = $('#osq-org-filter-1');
                    orgSelect.empty().append(`<option value="">${i18n.label_filter_by_organization || 'Filter by Organization'}</option>`);
                    
                    response.data.organizations.forEach(function(org) {
                        if (org.organization_1) {
                            const localizedOrg = translateOrgLabel(org.organization_1);
                            orgSelect.append(`<option value="${org.organization_1}">${localizedOrg}</option>`);
                        }
                    });
                }
            }
        });
    }

    // Bulk actions
    function updateBulkActionState() {
        const hasSelection = $('.osq-employee-checkbox:checked').length > 0;
        const hasAction = $('#osq-bulk-action').val() !== '';
        $('#osq-execute-bulk').prop('disabled', !(hasSelection && hasAction));
    }

    function executeBulkAction() {
        const action = $('#osq-bulk-action').val();
        const selectedIds = [];
        
        $('.osq-employee-checkbox:checked').each(function() {
            selectedIds.push($(this).data('emp-id'));
        });

        if (selectedIds.length === 0) return;

        // Handle different bulk actions
        switch(action) {
            case 'schedule_followup':
                // Open follow-up modal with multiple employees
                alert(`Scheduling follow-up for ${selectedIds.length} employees`);
                break;
            case 'mark_completed':
                // Mark selected employees as completed
                updateMultipleFollowups(selectedIds, 'Completed');
                break;
        }
    }

    function updateMultipleFollowups(employeeIds, status) {
        employeeIds.forEach(function(empId) {
            $.ajax({
                url: ajaxVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'osq_officer_update_follow_up',
                    nonce: ajaxVars.nonce,
                    employee_id: empId,
                    status: status
                }
            });
        });
        
        showSuccess(`${employeeIds.length} ${i18n.label_updated_followup_statuses || 'Updated follow-up statuses'}`);
        loadResponses(); // Refresh the table
        loadFollowupTracking();
    }

    // Modal functions
    function showModal(modalId) {
        $(modalId).fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        $('.osq-modal').fadeOut(200);
        $('body').css('overflow', 'auto');
        
        // Reset forms
        $('#osq-followup-form')[0].reset();
        $('#osq-response-details').hide();
        $('#osq-response-loading').show();
    }

        function showDetailedResponse(employeeId) {
            showModal('#osq-detailed-response-modal');

            $.ajax({
                url: ajaxVars.ajax_url,
                type: 'POST',
                data: {
                    action: 'osq_officer_get_detailed_response',
                    nonce: ajaxVars.nonce,
                    employee_id: employeeId
                },
                success: function(response) {
                    if (response.success) {
                        displayDetailedResponse(response.data);
                    } else {
                        console.log('Detailed response error payload:', response);
                        showError(response.data?.message || 'Error loading detailed response');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Detailed response AJAX error:', status, error, xhr.responseText);
                    showError('Network error loading detailed response');
                }
            });
        }

    function displayDetailedResponse(data) {
        $('#osq-response-loading').hide();
        $('#osq-response-details').show();
        
        // Display employee info
        const empInfo = data.employee_info;
        // Build compact org chain using org_compact (array of {label, value} pairs).
        let orgHtml = '-';
        if (Array.isArray(empInfo.org_compact) && empInfo.org_compact.length) {
            orgHtml = empInfo.org_compact.map(function(pair) {
                return $('<span/>').text(pair.label + ': ' + pair.value).html();
            }).join('<br>');
        }
        $('#osq-employee-basic-info').html(`
            <p><strong>${i18n.label_name || 'Name'}:</strong> ${empInfo.name}</p>
            <p><strong>${i18n.label_employee_id || 'Employee ID'}:</strong> ${empInfo.employee_number}</p>
            <p><strong>${i18n.label_organization || 'Organization'}:</strong><br>${orgHtml}</p>
            <p><strong>${i18n.label_completed_date || 'Completed Date'}:</strong> ${empInfo.completed_at}</p>
        `);
        
        // Display questions
        const sections = {
            A: $('#osq-questions-tab-a'),
            B: $('#osq-questions-tab-b'),
            C: $('#osq-questions-tab-c'),
            D: $('#osq-questions-tab-d')
        };

        Object.values(sections).forEach(function ($container) {
            $container.empty();
        });

        data.responses.forEach(function(response) {
            const sectionKey = String(response.category || 'A').toUpperCase();
            const $container = sections[sectionKey] || sections.A;

            const sectionLabel = i18n.label_section || 'Section';
            const questionHtml = `
                <div class="osq-question-item">
                    <div class="osq-question-text">
                        ${response.question_text}
                        <span class="osq-category-tag">${sectionLabel} ${sectionKey}</span>
                        <span class="osq-scale-tag">${response.scale}</span>
                    </div>
                    <div class="osq-answer-display">
                        <strong>${i18n.label_answer || 'Answer'}:</strong> ${response.answer_label} (${response.answer_value})
                    </div>
                </div>
            `;
            $container.append(questionHtml);
        });

        Object.entries(sections).forEach(function ([key, $container]) {
            if ($container.children().length === 0) {
                $container.append(`<div class="osq-empty-table">${i18n.label_no_responses || 'No responses found.'}</div>`);
            }
        });

        // Activate first tab by default
        $('.osq-tab-btn').removeClass('active').first().addClass('active');
        $('.osq-questions-tab').removeClass('active');
        $('#osq-questions-tab-a').addClass('active');
        
        // Display scoring results
        const scoringResults = $('#osq-scoring-results');
        scoringResults.html(`
            <div class="osq-scoring-results">
                <div class="osq-scoring-card">
                    <h5>${i18n.label_method1_results || 'Method 1 Results'}</h5>
                    ${formatScoringResult(data.scoring_results.method1, 1)}
                </div>
                <div class="osq-scoring-card">
                    <h5>${i18n.label_method2_results || 'Method 2 Results'}</h5>
                    ${formatScoringResult(data.scoring_results.method2, 2)}
                </div>
            </div>
        `);
    }

    function formatScoringResult(result, method) {
        if (!result) return `<p>${i18n.label_no_scoring || 'No scoring data available'}</p>`;
        
        let html = '';
        if (method === 1) {
            html += `<div class="osq-score-item"><span>${i18n.label_section_a_total || 'Section A Total'}:</span><strong>${result.section_a_total ?? '-'}</strong></div>`;
            html += `<div class="osq-score-item"><span>${i18n.label_section_b_total || 'Section B Total'}:</span><strong>${result.section_b_total ?? '-'}</strong></div>`;
            html += `<div class="osq-score-item"><span>${i18n.label_section_c_total || 'Section C Total'}:</span><strong>${result.section_c_total ?? '-'}</strong></div>`;
        } else if (method === 2) {
            html += `<div class="osq-score-item"><span>${i18n.label_section_a_eval || 'Section A Eval'}:</span><strong>${result.section_a_eval ?? '-'}</strong></div>`;
            html += `<div class="osq-score-item"><span>${i18n.label_section_b_eval || 'Section B Eval'}:</span><strong>${result.section_b_eval ?? '-'}</strong></div>`;
            html += `<div class="osq-score-item"><span>${i18n.label_section_c_eval || 'Section C Eval'}:</span><strong>${result.section_c_eval ?? '-'}</strong></div>`;
        }

        if (result.is_high_stress !== undefined) {
            html += `<div class="osq-score-item"><span>${i18n.label_high_stress || 'High Stress'}:</span><strong>${result.is_high_stress ? (i18n.label_yes || 'Yes') : (i18n.label_no || 'No')}</strong></div>`;
        }

        if (result.criterion_a_met !== undefined) {
            html += `<div class="osq-score-item"><span>${i18n.label_criterion_a || 'Criterion A'}:</span><strong>${result.criterion_a_met ? (i18n.label_met || 'Met') : (i18n.label_not_met || 'Not Met')}</strong></div>`;
        }
        if (result.criterion_b_met !== undefined) {
            html += `<div class="osq-score-item"><span>${i18n.label_criterion_b || 'Criterion B'}:</span><strong>${result.criterion_b_met ? (i18n.label_met || 'Met') : (i18n.label_not_met || 'Not Met')}</strong></div>`;
        }
        
        return html;
    }

    function showFollowupModal(employeeId) {
        $('#osq-followup-employee-id').val(employeeId);
        showModal('#osq-followup-modal');
    }

    // Modal tab switching for detailed responses
    $(document).on('click', '.osq-tab-btn', function () {
        const tabKey = $(this).data('tab');
        $('.osq-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.osq-questions-tab').removeClass('active');
        $(`#osq-questions-tab-${String(tabKey).toLowerCase()}`).addClass('active');
    });

    function updateFollowupStatus() {
        const employeeId = $('#osq-followup-employee-id').val();
        const status = $('#osq-followup-status').val();
        const notes = $('#osq-followup-notes').val();
        const scheduleDate = $('#osq-followup-schedule-date').val();
        
        const $submitBtn = $('#osq-followup-form button[type="submit"]');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: ajaxVars.ajax_url,
            type: 'POST',
            data: {
                action: 'osq_officer_update_follow_up',
                nonce: ajaxVars.nonce,
                employee_id: employeeId,
                status: status,
                notes: notes,
                scheduled_date: scheduleDate
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    closeModal();
                    loadResponses(); // Refresh the main table
                    loadFollowupTracking(); // Refresh follow-up list if visible
                } else {
                    showError(response.data.message || 'Error updating follow-up');
                }
            },
            error: function() {
                showError('Network error updating follow-up');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    function loadFollowupTracking() {
        const $tbody = $('#osq-followup-table tbody');
        $tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.label_loading_followup || 'Loading follow-up data...'}</td></tr>`);
        
        console.log('Loading follow-up tracking data...');
        
        $.ajax({
            url: ajaxVars.ajax_url,
            type: 'GET',
            data: {
                action: 'osq_officer_get_followup_tracking',
                nonce: ajaxVars.nonce
            },
            success: function(response) {
                console.log('Follow-up tracking response:', response);
                if (response.success) {
                    displayFollowupTracking(response.data.followups);
                } else {
                    $tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.label_no_followup_data || 'No follow-up data found'}</td></tr>`);
                }
            },
            error: function(xhr, status, error) {
                console.log('Follow-up tracking error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                $tbody.empty().append(`<tr><td colspan="5" class="osq-empty-table">${i18n.label_loading_followup || 'Loading follow-up data...'}: ${error}</td></tr>`);
            }
        });
    }

    function displayFollowupTracking(followups) {
        const $tbody = $('#osq-followup-table tbody');
        $tbody.empty();
        
        if (followups.length === 0) {
            $tbody.append(`<tr><td colspan="5" class="osq-empty-table">${i18n.label_no_followup_data || 'No follow-up data found'}</td></tr>`);
            return;
        }
        
        followups.forEach(function(followup) {
            const statusClass = `osq-status-badge--${String(followup.status || '').toLowerCase()}`;
            const row = `
                <tr>
                    <td>${followup.employee_name}<br><small>ID: ${followup.employee_number}</small></td>
                    <td><span class="osq-status-badge ${statusClass}">${followup.status}</span></td>
                    <td>${followup.scheduled_date || '-'}</td>
                    <td>${followup.notes || '-'}</td>
                    <td>
                        <button class="osq-btn-download osq-update-followup" data-emp-id="${followup.employee_id}">
                            ${i18n.label_edit || 'Edit'}
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    function filterFollowupRows(searchTerm) {
        $('#osq-followup-table tbody tr').each(function() {
            const rowText = $(this).text().normalize('NFKC').toLowerCase();
            $(this).toggle(rowText.indexOf(searchTerm) > -1);
        });
    }

    function updateEmployeeTable(employees) {
        const $tbody = $('#osq-responses-table tbody');
        $tbody.empty();
        
        if (employees.length === 0) {
            $tbody.append(`<tr><td colspan="6" class="osq-empty-table">${i18n.no_employees}</td></tr>`);
            return;
        }
        
        employees.forEach(function(emp) {
            // Determine Stress Status
            let statusLabel = '-';
            let actionBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.download_pdf}</button>`;
            let viewBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.label_view_details || 'View Details'}</button>`;
            let followBtn = `<button class="osq-btn-download disabled" title="Incomplete" disabled>${i18n.label_follow_up || 'Follow-up'}</button>`;
            
            if (emp.is_complete == 1) {
                if (emp.is_high_stress == 1) {
                    statusLabel = `<span class="osq-status-badge osq-status-badge--high-stress">${i18n.high_stress}</span>`;
                } else {
                    statusLabel = `<span class="osq-status-badge osq-status-badge--normal">${i18n.normal}</span>`;
                }
                actionBtn = `<button class="osq-btn-download osq-js-officer-pdf" data-emp-id="${emp.employee_id}">${i18n.download_pdf}</button>`;
                viewBtn = `<button class="osq-btn-download osq-view-detailed" data-emp-id="${emp.employee_id}">${i18n.label_view_details || 'View Details'}</button>`;
                followBtn = `<button class="osq-btn-download osq-update-followup" data-emp-id="${emp.employee_id}">${i18n.label_follow_up || 'Follow-up'}</button>`;
            } else {
                statusLabel = `<span class="osq-status-badge">${i18n.pending}</span>`;
            }
            
            // Decode HTML entities for proper Japanese text display
            const decodedName = $('<div/>').html(emp.name).text();
            const orgPartsF = [];
            for (let n = 1; n <= 5; n++) {
                const v = emp['organization_' + n];
                if (v) { orgPartsF.push($('<div/>').html(v).text()); }
            }
            const orgDisplayF = orgPartsF.length ? orgPartsF.join(' › ') : '-';

            const row = `
                <tr>
                    <td><input type="checkbox" class="osq-employee-checkbox" data-emp-id="${emp.employee_id}"> ${emp.employee_number}</td>
                    <td><strong>${decodedName}</strong></td>
                    <td>${orgDisplayF}</td>
                    <td>${statusLabel}</td>
                    <td>${emp.completed_at || '-'}</td>
                    <td>${actionBtn} ${viewBtn} ${followBtn}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    function showSuccess(message) {
        // Simple success notification
        alert('✓ ' + message);
    }

    function showError(message) {
        // Simple error notification
        alert('✗ ' + message);
    }

})(jQuery);
