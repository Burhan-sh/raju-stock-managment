/**
 * Raju Stock Management - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        RSM.init();
    });
    
    var RSM = {
        
        init: function() {
            this.initProductSearch();
            this.initFormHandlers();
            this.initDeleteHandler();
            this.initStockForm();
            this.initMappingHandlers();
            this.initScreenOptions();
            this.initPrintStock();
        },
        
        /**
         * Initialize screen options handlers
         */
        initScreenOptions: function() {
            // Intercept the default WordPress Apply button
            $(document).on('click', '#screen-options-apply, #rsm-save-screen-options', function(e) {
                // Check if we have our custom options present
                if ($('.rsm-toggle-column').length > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    RSM.saveScreenOptions($(this));
                    return false;
                }
            });
            
            // Also intercept form submission
            $(document).on('submit', '#adv-settings', function(e) {
                if ($('.rsm-toggle-column').length > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    RSM.saveScreenOptions($('#screen-options-apply'));
                    return false;
                }
            });
        },
        
        /**
         * Save screen options via AJAX
         */
        saveScreenOptions: function($button) {
            var originalText = $button.val() || $button.text();
            
            // Collect hidden columns (unchecked ones)
            var hiddenColumns = [];
            $('.rsm-toggle-column').each(function() {
                if (!$(this).is(':checked')) {
                    hiddenColumns.push($(this).val());
                }
            });
            
            // Get view mode
            var viewMode = $('input[name="rsm_view_mode"]:checked').val() || 'list';
            
            // Get per page
            var perPage = $('input[name="wp_screen_options[value]"]').val() || 20;
            
            $button.prop('disabled', true);
            if ($button.is('input')) {
                $button.val(rsm_ajax.strings.loading);
            } else {
                $button.text(rsm_ajax.strings.loading);
            }
            
            $.ajax({
                url: rsm_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rsm_save_screen_options',
                    nonce: rsm_ajax.nonce,
                    hidden_columns: hiddenColumns,
                    view_mode: viewMode,
                    per_page: perPage
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to apply changes
                        window.location.reload(true);
                    } else {
                        RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                        $button.prop('disabled', false);
                        if ($button.is('input')) {
                            $button.val(originalText);
                        } else {
                            $button.text(originalText);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', status, error);
                    RSM.showNotice(rsm_ajax.strings.error, 'error');
                    $button.prop('disabled', false);
                    if ($button.is('input')) {
                        $button.val(originalText);
                    } else {
                        $button.text(originalText);
                    }
                }
            });
        },
        
        /**
         * Initialize Print Stock functionality
         */
        initPrintStock: function() {
            $(document).on('click', '#rsm-print-stock', function(e) {
                e.preventDefault();
                RSM.showPrintPreview();
            });
        },
        
        /**
         * Show Print Preview Modal
         */
        showPrintPreview: function() {
            if (typeof rsmPrintData === 'undefined') {
                RSM.showNotice('Print data not available.', 'error');
                return;
            }
            
            var data = rsmPrintData;
            
            // Create modal HTML
            var modalHtml = '<div class="rsm-print-modal-overlay">' +
                '<div class="rsm-print-modal">' +
                    '<div class="rsm-print-modal-header">' +
                        '<h2>' + (rsm_ajax.strings.print_preview || 'Print Preview') + '</h2>' +
                        '<button type="button" class="rsm-print-modal-close">&times;</button>' +
                    '</div>' +
                    '<div class="rsm-print-modal-body" id="rsm-print-content">' +
                        RSM.generatePrintHTML(data) +
                    '</div>' +
                    '<div class="rsm-print-modal-footer">' +
                        '<button type="button" class="button rsm-print-modal-close">' + (rsm_ajax.strings.cancel || 'Cancel') + '</button>' +
                        '<button type="button" class="button button-primary" id="rsm-do-print">' +
                            '<span class="dashicons dashicons-printer" style="vertical-align: middle;"></span> ' + 
                            (rsm_ajax.strings.print || 'Print') +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            $('body').append(modalHtml);
            
            // Close modal handlers
            $('.rsm-print-modal-close').on('click', function() {
                $('.rsm-print-modal-overlay').remove();
            });
            
            $('.rsm-print-modal-overlay').on('click', function(e) {
                if ($(e.target).hasClass('rsm-print-modal-overlay')) {
                    $(this).remove();
                }
            });
            
            // Print button
            $('#rsm-do-print').on('click', function() {
                RSM.doPrint();
            });
            
            // ESC key to close
            $(document).on('keyup.rsm-print', function(e) {
                if (e.key === 'Escape') {
                    $('.rsm-print-modal-overlay').remove();
                    $(document).off('keyup.rsm-print');
                }
            });
        },
        
        /**
         * Generate Print HTML
         */
        generatePrintHTML: function(data) {
            var html = '<div class="rsm-print-document">';
            
            // Header
            html += '<div class="rsm-print-header">';
            html += '<h1>' + data.siteName + '</h1>';
            html += '<h2>Stock Report</h2>';
            html += '<p class="rsm-print-date">Generated: ' + data.dateTime + '</p>';
            html += '</div>';
            
            // Table
            html += '<table class="rsm-print-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th class="rsm-print-sno">#</th>';
            html += '<th class="rsm-print-code">Product Code</th>';
            html += '<th class="rsm-print-qty">Quantity</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            $.each(data.products, function(index, product) {
                var rowClass = (index % 2 === 0) ? 'rsm-print-row-even' : 'rsm-print-row-odd';
                html += '<tr class="' + rowClass + '">';
                html += '<td class="rsm-print-sno">' + (index + 1) + '</td>';
                html += '<td class="rsm-print-code">' + RSM.escapeHtml(product.code) + '</td>';
                html += '<td class="rsm-print-qty">' + product.stock + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '<tfoot>';
            html += '<tr class="rsm-print-total-row">';
            html += '<td colspan="2" class="rsm-print-total-label"><strong>Total Stock Quantity</strong></td>';
            html += '<td class="rsm-print-total-value"><strong>' + data.totalStock + '</strong></td>';
            html += '</tr>';
            html += '</tfoot>';
            html += '</table>';
            
            // Summary
            html += '<div class="rsm-print-summary">';
            html += '<p>Total Products: <strong>' + data.products.length + '</strong></p>';
            html += '<p>Total Stock Units: <strong>' + data.totalStock + '</strong></p>';
            html += '</div>';
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Execute Print
         */
        doPrint: function() {
            var printContent = document.getElementById('rsm-print-content').innerHTML;
            
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html><head>');
            printWindow.document.write('<title>Stock Report - ' + rsmPrintData.siteName + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write(RSM.getPrintStyles());
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
            };
            
            // Close modal
            $('.rsm-print-modal-overlay').remove();
        },
        
        /**
         * Get Print Styles
         */
        getPrintStyles: function() {
            return `
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #333; }
                
                .rsm-print-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #0073aa; }
                .rsm-print-header h1 { font-size: 24px; color: #0073aa; margin-bottom: 5px; }
                .rsm-print-header h2 { font-size: 18px; color: #666; font-weight: normal; margin-bottom: 10px; }
                .rsm-print-date { font-size: 12px; color: #888; }
                
                .rsm-print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .rsm-print-table th { background: #0073aa; color: #fff; padding: 12px 10px; text-align: left; font-size: 14px; }
                .rsm-print-table td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
                .rsm-print-table .rsm-print-sno { width: 60px; text-align: center; }
                .rsm-print-table .rsm-print-qty { width: 100px; text-align: right; }
                .rsm-print-table th.rsm-print-qty { text-align: right; }
                .rsm-print-row-even { background: #f9f9f9; }
                .rsm-print-row-odd { background: #fff; }
                
                .rsm-print-total-row { background: #e8f4fc !important; }
                .rsm-print-total-row td { padding: 15px 10px; border-top: 2px solid #0073aa; }
                .rsm-print-total-label { text-align: right; padding-right: 20px !important; }
                .rsm-print-total-value { text-align: right; font-size: 16px; color: #0073aa; }
                
                .rsm-print-summary { background: #f5f5f5; padding: 15px 20px; border-radius: 4px; margin-top: 20px; }
                .rsm-print-summary p { margin: 5px 0; font-size: 14px; }
                .rsm-print-summary strong { color: #0073aa; }
                
                @media print {
                    body { padding: 0; }
                    .rsm-print-table th { background: #333 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    .rsm-print-row-even { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    .rsm-print-total-row { background: #ddd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            `;
        },
        
        /**
         * Escape HTML helper
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },
        
        /**
         * Initialize product search autocomplete for mappings
         */
        initProductSearch: function() {
            var $search = $('#wc_product_search');
            var $productId = $('#mapping_product_id');
            var $variationsContainer = $('#rsm-variations-container');
            var $variationSelect = $('#mapping_variation_id');
            
            if (!$search.length) return;
            
            $search.autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: rsm_ajax.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'rsm_search_products',
                            nonce: rsm_ajax.nonce,
                            term: request.term
                        },
                        success: function(data) {
                            if (data.success) {
                                response(data.data);
                            } else {
                                response([]);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $productId.val(ui.item.id);
                    $search.val(ui.item.label);
                    
                    // Check if variable product
                    if (ui.item.type === 'variable') {
                        RSM.loadVariations(ui.item.id, $variationSelect, $variationsContainer);
                    } else {
                        $variationsContainer.hide();
                        $variationSelect.val(0);
                    }
                    
                    return false;
                }
            });
            
            // Clear product ID if search is cleared
            $search.on('input', function() {
                if ($(this).val() === '') {
                    $productId.val(0);
                    $variationsContainer.hide();
                    $variationSelect.val(0);
                }
            });
        },
        
        /**
         * Load variations for a product
         */
        loadVariations: function(productId, $variationSelect, $variationsContainer, selectedId) {
            if (!$variationSelect) {
                $variationSelect = $('#mapping_variation_id');
            }
            if (!$variationsContainer) {
                $variationsContainer = $('#rsm-variations-container');
            }
            
            $variationSelect.prop('disabled', true).html('<option value="0">' + rsm_ajax.strings.loading + '</option>');
            $variationsContainer.show();
            
            $.ajax({
                url: rsm_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rsm_get_variations',
                    nonce: rsm_ajax.nonce,
                    product_id: productId
                },
                success: function(data) {
                    $variationSelect.prop('disabled', false);
                    
                    if (data.success && data.data.variations.length > 0) {
                        var html = '<option value="0">All variations (map to all)</option>';
                        
                        $.each(data.data.variations, function(i, variation) {
                            var label = variation.name;
                            if (variation.sku) {
                                label += ' (SKU: ' + variation.sku + ')';
                            }
                            var selected = (selectedId && selectedId == variation.id) ? ' selected' : '';
                            html += '<option value="' + variation.id + '"' + selected + '>' + label + '</option>';
                        });
                        
                        $variationSelect.html(html);
                    } else {
                        $variationsContainer.hide();
                    }
                },
                error: function() {
                    $variationSelect.prop('disabled', false);
                    $variationsContainer.hide();
                }
            });
        },
        
        /**
         * Initialize form handlers
         */
        initFormHandlers: function() {
            // Add product form
            $('#rsm-add-product-form').on('submit', function(e) {
                e.preventDefault();
                RSM.submitProductForm($(this), 'rsm_add_product');
            });
            
            // Edit product form
            $('#rsm-edit-product-form').on('submit', function(e) {
                e.preventDefault();
                RSM.submitProductForm($(this), 'rsm_update_product');
            });
        },
        
        /**
         * Submit product form
         */
        submitProductForm: function($form, action) {
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(rsm_ajax.strings.loading);
            
            var formData = $form.serialize();
            formData += '&action=' + action + '&nonce=' + rsm_ajax.nonce;
            
            $.ajax({
                url: rsm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            RSM.showNotice(response.data.message, 'success');
                            $button.prop('disabled', false).text(originalText);
                        }
                    } else {
                        RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    RSM.showNotice(rsm_ajax.strings.error, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Initialize delete handler
         */
        initDeleteHandler: function() {
            $(document).on('click', '.rsm-delete-product', function(e) {
                e.preventDefault();
                
                if (!confirm(rsm_ajax.strings.confirm_delete)) {
                    return;
                }
                
                var $button = $(this);
                var $row = $button.closest('tr');
                var id = $button.data('id');
                
                $button.prop('disabled', true).text(rsm_ajax.strings.loading);
                
                $.ajax({
                    url: rsm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rsm_delete_product',
                        nonce: rsm_ajax.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                            $button.prop('disabled', false).text('Delete');
                        }
                    },
                    error: function() {
                        RSM.showNotice(rsm_ajax.strings.error, 'error');
                        $button.prop('disabled', false).text('Delete');
                    }
                });
            });
        },
        
        /**
         * Initialize mapping handlers
         */
        initMappingHandlers: function() {
            // Add mapping
            $('#rsm-add-mapping').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                var productCodeId = $('#mapping_product_code_id').val();
                var productId = $('#mapping_product_id').val();
                var variationId = $('#mapping_variation_id').val() || 0;
                
                if (!productId || productId == '0') {
                    RSM.showNotice('Please select a product first.', 'error');
                    return;
                }
                
                $button.prop('disabled', true).text(rsm_ajax.strings.loading);
                
                $.ajax({
                    url: rsm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rsm_add_mapping',
                        nonce: rsm_ajax.nonce,
                        product_code_id: productCodeId,
                        product_id: productId,
                        variation_id: variationId
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text(originalText);
                        
                        if (response.success) {
                            // Add new row to table
                            var $table = $('#rsm-mappings-list');
                            var newRow = '<tr data-mapping-id="' + response.data.mapping_id + '">' +
                                '<td>' + response.data.mapping_name + '</td>' +
                                '<td><button type="button" class="button button-small rsm-remove-mapping" data-mapping-id="' + response.data.mapping_id + '">Remove</button></td>' +
                                '</tr>';
                            
                            if ($table.length) {
                                $table.append(newRow);
                            } else {
                                // If no table exists, reload the page
                                location.reload();
                            }
                            
                            // Clear the search
                            $('#wc_product_search').val('');
                            $('#mapping_product_id').val(0);
                            $('#rsm-variations-container').hide();
                            $('#mapping_variation_id').val(0);
                            
                            // Remove "no mappings" message if exists
                            $('.rsm-no-mappings').remove();
                            
                            RSM.showNotice(response.data.message, 'success');
                        } else {
                            RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text(originalText);
                        RSM.showNotice(rsm_ajax.strings.error, 'error');
                    }
                });
            });
            
            // Remove mapping
            $(document).on('click', '.rsm-remove-mapping', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to remove this mapping?')) {
                    return;
                }
                
                var $button = $(this);
                var $row = $button.closest('tr');
                var mappingId = $button.data('mapping-id');
                
                $button.prop('disabled', true).text(rsm_ajax.strings.loading);
                
                $.ajax({
                    url: rsm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rsm_remove_mapping',
                        nonce: rsm_ajax.nonce,
                        mapping_id: mappingId
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if table is now empty
                                if ($('#rsm-mappings-list tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                            $button.prop('disabled', false).text('Remove');
                        }
                    },
                    error: function() {
                        RSM.showNotice(rsm_ajax.strings.error, 'error');
                        $button.prop('disabled', false).text('Remove');
                    }
                });
            });
        },
        
        /**
         * Initialize stock form
         */
        initStockForm: function() {
            $('#rsm-stock-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('#rsm-update-stock');
                var originalText = $button.text();
                
                $button.prop('disabled', true).text(rsm_ajax.strings.loading);
                
                var formData = $form.serialize();
                formData += '&action=rsm_update_stock&nonce=' + rsm_ajax.nonce;
                
                $.ajax({
                    url: rsm_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        $button.prop('disabled', false).text(originalText);
                        
                        if (response.success) {
                            // Update stock display
                            $('#rsm-current-stock').text(response.data.new_stock);
                            
                            // Reset form
                            $form.find('#stock_quantity').val(1);
                            $form.find('#stock_comment').val('');
                            
                            RSM.showNotice(response.data.message, 'success');
                            
                            // Reload page to show updated history
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            RSM.showNotice(response.data.message || rsm_ajax.strings.error, 'error');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text(originalText);
                        RSM.showNotice(rsm_ajax.strings.error, 'error');
                    }
                });
            });
        },
        
        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.rsm-wrap > .notice, .rsm-wrap .rsm-section > .notice').remove();
            
            // Add new notice
            var $target = $('.rsm-wrap h1').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.rsm-wrap').prepend($notice);
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Show loading overlay
         */
        showLoading: function() {
            if (!$('.rsm-loading-overlay').length) {
                $('body').append('<div class="rsm-loading-overlay"><span class="spinner is-active"></span></div>');
            }
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('.rsm-loading-overlay').remove();
        }
    };
    
    // Expose RSM globally
    window.RSM = RSM;
    
})(jQuery);
