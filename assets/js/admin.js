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
        },
        
        /**
         * Initialize product search autocomplete
         */
        initProductSearch: function() {
            var $search = $('#wc_product_search');
            var $productId = $('#product_id');
            var $variationsContainer = $('#rsm-variations-container');
            var $variationSelect = $('#variation_id');
            
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
                        RSM.loadVariations(ui.item.id);
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
            
            // Load variations if editing and product is already selected
            var existingProductId = $productId.val();
            if (existingProductId && existingProductId > 0) {
                var selectedVariation = $variationSelect.data('selected');
                if (selectedVariation) {
                    RSM.loadVariations(existingProductId, selectedVariation);
                }
            }
        },
        
        /**
         * Load variations for a product
         */
        loadVariations: function(productId, selectedId) {
            var $variationsContainer = $('#rsm-variations-container');
            var $variationSelect = $('#variation_id');
            
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
                        var html = '<option value="0">' + rsm_ajax.strings.select_variation + '</option>';
                        
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
                            
                            // Update stock badge class
                            var $badge = $('#rsm-current-stock').closest('.rsm-stock-badge');
                            if (response.data.new_stock > 0) {
                                $badge.removeClass('out-of-stock').addClass('in-stock');
                            } else {
                                $badge.removeClass('in-stock').addClass('out-of-stock');
                            }
                            
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
            $('.rsm-wrap > .notice').remove();
            
            // Add new notice
            $('.rsm-wrap h1').first().after($notice);
            
            // Make dismissible
            if (typeof wp !== 'undefined' && wp.a11y) {
                wp.a11y.speak(message);
            }
            
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
