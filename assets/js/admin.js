/**
 * AI Hero Assistant - Admin JavaScript
 * Bootstrap-enhanced admin interface
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
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
        $('.nav-tabs .nav-link').on('click', function (e) {
            e.preventDefault();

            // Try Bootstrap Tab if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                try {
                    const bsTab = new bootstrap.Tab(this);
                    bsTab.show();

                    // Update URL hash
                    $(this).one('shown.bs.tab', function () {
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
                setTimeout(function () {
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
        $('#documentation_files').on('change', function () {
            const files = this.files;
            if (files.length > 0) {
                console.log('Files selected:', files.length);
            }
        });

        // ============================================
        // CONVERSATIONS MANAGEMENT
        // ============================================

        // Filtrare conversații
        $('#aiha-conversations-filter').on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serialize();
            // Construiește URL-ul corect cu pagina și tab-ul
            const baseUrl = window.aihaAdminData && window.aihaAdminData.settingsPageUrl 
                ? window.aihaAdminData.settingsPageUrl 
                : window.location.href.split('?')[0].replace(/&tab=[^&]*/, '').replace(/tab=[^&]*&?/, '') + '&tab=conversations';
            const url = baseUrl + (formData ? '&' + formData : '');
            window.location.href = url;
        });

        // Reset filtre
        $('#reset-filters').on('click', function () {
            const baseUrl = window.aihaAdminData && window.aihaAdminData.settingsPageUrl 
                ? window.aihaAdminData.settingsPageUrl 
                : window.location.href.split('?')[0] + '?page=aiha-settings&tab=conversations';
            window.location.href = baseUrl;
        });

        // Select all checkbox
        $('#select-all-conversations').on('change', function () {
            $('.conversation-checkbox').prop('checked', this.checked);
            updateBulkDeleteButton();
        });

        // Individual checkbox change
        $(document).on('change', '.conversation-checkbox', function () {
            updateBulkDeleteButton();
            // Update select all checkbox
            const total = $('.conversation-checkbox').length;
            const checked = $('.conversation-checkbox:checked').length;
            $('#select-all-conversations').prop('checked', total === checked);
        });

        // Update bulk delete button state
        function updateBulkDeleteButton() {
            const checked = $('.conversation-checkbox:checked').length;
            $('#bulk-delete-btn').prop('disabled', checked === 0);
        }

        // View conversation modal
        $(document).on('click', '.view-conversation', function () {
            const conversationId = $(this).data('conversation-id');
            const modal = new bootstrap.Modal(document.getElementById('conversationModal'));
            const modalBody = $('#conversation-modal-body');

            // Show loading
            modalBody.html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Se încarcă...</span></div></div>');
            modal.show();

            // Load conversation
            $.ajax({
                url: window.aihaAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiha_get_conversation',
                    nonce: window.aihaAdminData.nonce,
                    conversation_id: conversationId
                },
                success: function (response) {
                    if (response.success && response.data) {
                        const conv = response.data.conversation;
                        const messages = response.data.messages || [];

                        let html = '<div class="conversation-details mb-3">';
                        html += '<div class="row g-3">';
                        html += '<div class="col-md-3 col-6"><strong>ID:</strong> #' + conv.id + '</div>';
                        html += '<div class="col-md-3 col-6"><strong>IP:</strong> <code>' + conv.user_ip + '</code></div>';
                        html += '<div class="col-md-3 col-6"><strong>Data:</strong> ' + conv.created_at + '</div>';
                        html += '<div class="col-md-3 col-6"><strong>Mesaje:</strong> ' + messages.length + '</div>';
                        html += '</div>';
                        html += '</div>';
                        
                        // Store conversation ID for delete button
                        $('#conversationModal').data('conversation-id', conversationId);

                        html += '<div class="conversation-messages" style="max-height: 500px; overflow-y: auto;">';
                        if (messages.length > 0) {
                            messages.forEach(function (msg) {
                                const isUser = msg.role === 'user';
                                const bgClass = isUser ? 'bg-primary text-white' : 'bg-light';
                                const alignClass = isUser ? 'text-end' : 'text-start';

                                html += '<div class="message mb-3 ' + alignClass + '">';
                                html += '<div class="d-inline-block p-3 rounded ' + bgClass + '" style="max-width: 80%;">';
                                html += '<div class="fw-bold mb-1">' + (isUser ? 'Utilizator' : 'AI') + '</div>';
                                // Folosește formatare markdown
                                const formattedContent = typeof formatMarkdownMessage !== 'undefined' 
                                    ? formatMarkdownMessage(msg.content) 
                                    : msg.content.replace(/\n/g, '<br>');
                                html += '<div class="aiha-message-content">' + formattedContent + '</div>';
                                if (msg.created_at) {
                                    html += '<div class="small mt-2 opacity-75">' + msg.created_at + '</div>';
                                }
                                html += '</div>';
                                html += '</div>';
                            });
                        } else {
                            html += '<p class="text-muted">Nu există mesaje în această conversație.</p>';
                        }
                        html += '</div>';

                        modalBody.html(html);
                    } else {
                        modalBody.html('<div class="alert alert-danger">Eroare la încărcarea conversației: ' + (response.data?.message || 'Eroare necunoscută') + '</div>');
                    }
                },
                error: function () {
                    modalBody.html('<div class="alert alert-danger">Eroare la comunicarea cu serverul.</div>');
                }
            });
        });

        // Delete single conversation from table
        $(document).on('click', '.delete-conversation', function () {
            if (!confirm('Ești sigur că vrei să ștergi această conversație?')) {
                return;
            }

            const conversationId = $(this).data('conversation-id');
            const row = $(this).closest('tr');

            $.ajax({
                url: window.aihaAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiha_delete_conversation',
                    nonce: window.aihaAdminData.nonce,
                    conversation_id: conversationId
                },
                success: function (response) {
                    if (response.success) {
                        row.fadeOut(300, function () {
                            $(this).remove();
                            if ($('#conversations-table tbody tr').length === 0) {
                                $('#conversations-list-container').html('<div class="alert alert-info">Nu există conversații.</div>');
                            }
                        });
                    } else {
                        alert('Eroare: ' + (response.data?.message || 'Eroare necunoscută'));
                    }
                },
                error: function () {
                    alert('Eroare la comunicarea cu serverul.');
                }
            });
        });
        
        // Delete conversation from modal
        $(document).on('click', '#delete-conversation-from-modal', function () {
            if (!confirm('Ești sigur că vrei să ștergi această conversație?')) {
                return;
            }

            const conversationId = $('#conversationModal').data('conversation-id');
            const modal = bootstrap.Modal.getInstance(document.getElementById('conversationModal'));
            const row = $('tr[data-conversation-id="' + conversationId + '"]');

            $.ajax({
                url: window.aihaAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiha_delete_conversation',
                    nonce: window.aihaAdminData.nonce,
                    conversation_id: conversationId
                },
                success: function (response) {
                    if (response.success) {
                        // Close modal
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Remove row from table
                        if (row.length) {
                            row.fadeOut(300, function () {
                                $(this).remove();
                                if ($('#conversations-table tbody tr').length === 0) {
                                    $('#conversations-list-container').html('<div class="alert alert-info">Nu există conversații.</div>');
                                }
                            });
                        } else {
                            // Reload page if row not found
                            window.location.reload();
                        }
                    } else {
                        alert('Eroare: ' + (response.data?.message || 'Eroare necunoscută'));
                    }
                },
                error: function () {
                    alert('Eroare la comunicarea cu serverul.');
                }
            });
        });

        // Bulk delete conversations
        $('#bulk-delete-btn').on('click', function () {
            const checked = $('.conversation-checkbox:checked');
            if (checked.length === 0) {
                return;
            }

            if (!confirm('Ești sigur că vrei să ștergi ' + checked.length + ' conversații selectate?')) {
                return;
            }

            const conversationIds = checked.map(function () {
                return $(this).val();
            }).get();

            $.ajax({
                url: window.aihaAdminData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiha_delete_conversations_bulk',
                    nonce: window.aihaAdminData.nonce,
                    conversation_ids: conversationIds
                },
                success: function (response) {
                    if (response.success) {
                        checked.closest('tr').fadeOut(300, function () {
                            $(this).remove();
                            if ($('#conversations-table tbody tr').length === 0) {
                                $('#conversations-list-container').html('<div class="alert alert-info">Nu există conversații.</div>');
                            }
                        });
                        $('#select-all-conversations').prop('checked', false);
                        updateBulkDeleteButton();
                        alert(response.data.message || 'Conversațiile au fost șterse cu succes.');
                    } else {
                        alert('Eroare: ' + (response.data?.message || 'Eroare necunoscută'));
                    }
                },
                error: function () {
                    alert('Eroare la comunicarea cu serverul.');
                }
            });
        });
    });

})(jQuery);
