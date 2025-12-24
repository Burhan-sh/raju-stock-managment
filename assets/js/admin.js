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
